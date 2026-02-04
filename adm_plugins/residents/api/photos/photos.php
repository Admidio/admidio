<?php
/**
 ***********************************************************************************************
 * API endpoint to return photo album details with images as base64 encoded data
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$currentUser = validateApiKey();

$currentOrgId = isset($gCurrentOrgId)
    ? (int) $gCurrentOrgId
    : (isset($gCurrentOrganization) ? (int) $gCurrentOrganization->getValue('org_id') : 0);

// Check user permissions for photos
$canEditPhotos = $currentUser->isAdministratorPhotos();

$sql = 'SELECT pho_id, pho_name, pho_quantity, pho_begin, pho_end, pho_description, pho_pho_id_parent
    FROM adm_photos
    WHERE pho_locked = 0 AND pho_org_id = ?';
$sqlParams = array($currentOrgId);

$albumId = $_GET['album_id'] ?? '';

$metaOnly = false;
if (isset($_GET['meta'])) {
    $metaOnly = in_array(strtolower((string) $_GET['meta']), array('1', 'true', 'yes'), true);
}

$namesParam = isset($_GET['names']) ? (string) $_GET['names'] : '';
$namesFilter = null;
if ($namesParam !== '') {
    $parts = array_filter(array_map('trim', explode(',', $namesParam)), 'strlen');
    $parts = array_slice($parts, 0, 200);
    $namesFilter = array();
    foreach ($parts as $p) {
        // allow common filename chars only
        $clean = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $p);
        if ($clean === '') {
            continue;
    }
        // strip extension if provided
        $clean = preg_replace('/\.[^.]+$/', '', $clean);
        $namesFilter[$clean] = true;
    }
    if (count($namesFilter) === 0) {
        $namesFilter = null;
    }
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

$pagingEnabled = $limit !== null;
if ($pagingEnabled) {
    if ($limit <= 0) {
        $limit = 50;
    }
    if ($limit > 100) {
        $limit = 100;
    }
    if ($offset < 0) {
        $offset = 0;
    }
}

if ($albumId) {
    $sql .= ' AND pho_id = ?';
    $sqlParams[] = (int) $albumId;
}
$sqlalbums = $gDb->queryPrepared($sql, $sqlParams, false);
if ($sqlalbums === false) {
    admidioApiError('Database error', 500);
}

function getAlbums(array $obj, bool $canEdit, bool $pagingEnabled, int $offset, int $limit, bool $isRootAlbum, ?array &$rootPaging, bool $metaOnly, ?array $namesFilter): array
{
    global $gDb, $currentOrgId; 
    $baseFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/';
    $photoFiles = [];
    $photoInfos = [];
    $photoId   = $obj['pho_id'];
    $beginDate = $obj['pho_begin'];

    if ((int) $obj['pho_quantity'] > 0) {
        $folderPath = $baseFolder . $beginDate . '_' . $photoId;

        // Get all image files inside the folder (deterministic order for paging)
        $files = is_dir($folderPath) ? glob($folderPath . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE) : array();
        if (!is_array($files)) {
            $files = array();
    }
        sort($files);

        $totalFiles = count($files);
        $slice = $files;
        if ($pagingEnabled && $isRootAlbum) {
            $slice = array_slice($files, $offset, $limit);
            $rootPaging = array(
        'limit' => $limit,
        'offset' => $offset,
        'total' => $totalFiles,
        'hasMore' => ($offset + count($slice)) < $totalFiles,
            );
    }

        $photoFiles = [];
        foreach ($slice as $filePath) {
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
            if (!$metaOnly && $namesFilter !== null && !isset($namesFilter[$fileName])) {
                continue;
            }
            if ($metaOnly) {
                $photoInfos[] = array(
                    'name' => $fileName,
                    'ext' => strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)),
                    'mtime' => @filemtime($filePath) ?: null,
                    'size' => @filesize($filePath) ?: null,
                );
            } else {
                $base64 = base64_encode(file_get_contents($filePath));
                $photoFiles[$fileName] = $base64;
            }
    }
    }
    $sqlChild = 'SELECT * FROM adm_photos WHERE pho_org_id = ? AND pho_pho_id_parent = ?';
    $stmtChild = $gDb->queryPrepared($sqlChild, [$currentOrgId, $obj['pho_id']], false);
    $childRows = $stmtChild ? $stmtChild->fetchAll() : array();

    $childAlbums = [];
    foreach ($childRows as $child) {
        $childAlbums[] = getAlbums($child, $canEdit, false, 0, 0, false, $rootPaging, $metaOnly, null);
    }

    return array(
    'id'            => $photoId,
    'name'          => $obj['pho_name'],
    'quantity'      => $obj['pho_quantity'],
    'start'         => $beginDate,
    'end'           => $obj['pho_end'],
    'description'   => $obj['pho_description'],
    'photos'        => $metaOnly ? (object) array() : $photoFiles,
    'photosInfo'    => $metaOnly ? $photoInfos : array(),
    'albums'        => $childAlbums,
    'canDelete'     => $canEdit,
    'canUpload'     => $canEdit,
    );
}

$row = $sqlalbums->fetch();
$albums = (object)[];
$paging = null;
if($row){
    $albums = getAlbums($row, $canEditPhotos, $pagingEnabled, $offset, (int) $limit, true, $paging, $metaOnly, $namesFilter);
}

echo json_encode([
    'albums' => $albums,
    'paging' => $paging,
]);
