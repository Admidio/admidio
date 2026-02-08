<?php
/**
 ***********************************************************************************************
 * API endpoint to list folders and files from the documents and files module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Documents\Entity\Folder;

validateApiKey();

$folderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'string');
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

/**
    * Return current folder + immediate children (folders + files).
    *
    * @param string $startFolderUuid
    * @param bool $pagingEnabled
    * @param int $offset
    * @param int $limit
    * @return array<string, mixed>
    */
function getFolderContents(string $startFolderUuid, bool $pagingEnabled = false, int $offset = 0, int $limit = 50): array
{
    global $gDb;

    $folder = new Folder($gDb);
    $folder->getFolderForDownload($startFolderUuid);

    $parentUuid = null;
    $parentId = $folder->getValue('fol_fol_id_parent');
    if ($parentId !== null && (int) $parentId > 0) {
        $stmtParent = $gDb->queryPrepared(
            'SELECT fol_uuid FROM ' . TBL_FOLDERS . ' WHERE fol_id = ? LIMIT 1',
            array((int) $parentId),
            false
        );
        $parentRow = $stmtParent ? $stmtParent->fetch(PDO::FETCH_ASSOC) : false;
        if ($parentRow && !empty($parentRow['fol_uuid'])) {
            $parentUuid = (string) $parentRow['fol_uuid'];
    }
    }

    $current = array(
    'uuid' => (string) $folder->getValue('fol_uuid'),
    'name' => (string) $folder->getValue('fol_name'),
    'parentUuid' => $parentUuid,
    );

    $entries = array();

    foreach ($folder->getSubfoldersWithProperties() as $subfolder) {
        $entries[] = array(
            'type' => 'folder',
            'uuid' => (string) ($subfolder['fol_uuid'] ?? ''),
            'name' => (string) ($subfolder['fol_name'] ?? ''),
            'description' => (string) ($subfolder['fol_description'] ?? ''),
            'timestamp' => (string) ($subfolder['fol_timestamp'] ?? ''),
        );
    }

    foreach ($folder->getFilesWithProperties() as $file) {
        $fileUuid = (string) ($file['fil_uuid'] ?? '');
        $entries[] = array(
            'type' => 'file',
            'uuid' => $fileUuid,
            'name' => (string) ($file['fil_name'] ?? ''),
            'description' => (string) ($file['fil_description'] ?? ''),
            'timestamp' => (string) ($file['fil_timestamp'] ?? ''),
            'size' => isset($file['fil_size']) ? (int) $file['fil_size'] : 0,
            'counter' => isset($file['fil_counter']) ? (int) $file['fil_counter'] : 0,
            'folder' => array(
        'uuid' => (string) $folder->getValue('fol_uuid'),
        'name' => (string) $folder->getValue('fol_name'),
            ),
            'canDownload' => true,
            'download' => array(
        'url' => SecurityUtils::encodeUrl(
                    FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/api/files/download.php',
                    array('file_uuid' => $fileUuid)
        ),
            ),
        );
    }

    usort($entries, function ($a, $b) {
        $typeA = (string) ($a['type'] ?? '');
        $typeB = (string) ($b['type'] ?? '');
        if ($typeA !== $typeB) {
            return $typeA === 'folder' ? -1 : 1;
    }
        $nameA = strtolower((string) ($a['name'] ?? ''));
        $nameB = strtolower((string) ($b['name'] ?? ''));
        return $nameA <=> $nameB;
    });

    $paging = null;
    if ($pagingEnabled) {
        $total = count($entries);
        $entries = array_slice($entries, $offset, $limit);
        $paging = array(
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'hasMore' => ($offset + count($entries)) < $total,
        );
    }

    return array(
    'currentFolder' => $current,
    'entries' => $entries,
    'paging' => $paging,
    );
}

try {
    $payload = getFolderContents((string) $folderUuid, $pagingEnabled, $offset, (int) $limit);
    echo json_encode($payload);
} catch (AdmException $e) {
    $msg = (string) $e->getMessage();
    if ($msg === 'SYS_FOLDER_NO_RIGHTS') {
        http_response_code(403);
        echo json_encode(array('error' => 'No permission to view files.'));
        exit;
    }
    if ($msg === 'SYS_FOLDER_NOT_FOUND') {
        http_response_code(404);
        echo json_encode(array('error' => 'Folder not found.'));
        exit;
    }
    http_response_code(400);
    echo json_encode(array('error' => $msg));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => 'Unable to load files.'));
}
