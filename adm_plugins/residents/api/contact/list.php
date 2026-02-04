<?php
/**
 ***********************************************************************************************
 * API endpoint to return a list of contacts with profile information
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Users\Entity\User;

$currentUser = validateApiKey();
$picPath = THEME_PATH. '/images/no_profile_pic.png';

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

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

function encodeProfileImage(string $binary): array
{
    if ($binary === '') {
        return [
            'profile' => null,
            'profile_mime' => null,
            'profile_has_image' => false,
        ];
    }

    $mime = 'image/jpeg';
    $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    if ($finfo) {
        $detected = @finfo_buffer($finfo, $binary);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
    }
        @finfo_close($finfo);
    }

    return [
    'profile' => base64_encode($binary),
    'profile_mime' => $mime,
    'profile_has_image' => true,
    ];
}

$today = date('Y-m-d');

$sqlUsers = 'SELECT DISTINCT u.usr_id, u.usr_login_name
    FROM ' . TBL_USERS . ' u
    INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id
        AND m.mem_begin <= ?
        AND m.mem_end > ?
    INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id
    INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
    WHERE u.usr_valid = true
        AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)';

$users = $gDb->queryPrepared($sqlUsers, array($today, $today, (int) $gCurrentOrgId), false);
if ($users === false) {
    admidioApiError('Database error', 500);
}
$contacts = [];

$allContacts = [];
while ($row = $users->fetch()) {
    $user = new User($gDb, $gProfileFields);
    $user->readDataById($row['usr_id']);
    if (!isMemberOfOrganization($user)) {
        continue;
    }
    
    $firstName = $user->getValue('FIRST_NAME');
    $lastName = $user->getValue('LAST_NAME');
    $email = $user->getValue('EMAIL');
    
    // Apply search filter if provided
    if (!empty($searchQuery)) {
        $searchLower = strtolower($searchQuery);
        $matchFound = false;
        if (stripos($firstName, $searchQuery) !== false) {
            $matchFound = true;
        } elseif (stripos($lastName, $searchQuery) !== false) {
            $matchFound = true;
        } elseif (stripos($email, $searchQuery) !== false) {
            $matchFound = true;
        } elseif (stripos($firstName . ' ' . $lastName, $searchQuery) !== false) {
            $matchFound = true;
        }
        if (!$matchFound) {
            continue;
        }
    }
    
    $profileBinary = '';
    if ((int) $gSettingsManager->get('profile_photo_storage') === 0) {
        $usr_photo = $user->getValue('usr_photo');
        if (!empty($usr_photo)) {
            $profileBinary = $usr_photo;
        }
    }
    else {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
        if (is_file($file)) {
            $profileBinary = (string) @file_get_contents($file);
        }
    }

    $encodedProfile = encodeProfileImage($profileBinary);
    $allContacts[] = [
        'id'            => $row['usr_id'],
        'login'         => $user->getValue('usr_login_name'),
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'email'         => $email,
        'gender'        => $user->getValue('GENDER'),
        'street'        => $user->getValue('STREET'),
        'post_code'     => $user->getValue('POSTCODE'),
        'city'          => $user->getValue('CITY'),
        'country'       => $user->getValue('COUNTRY'),
        'phone'         => $user->getValue('PHONE'),
        'mobile'        => $user->getValue('MOBILE'),
        'birthday'      => $user->getValue('BIRTHDAY'),
        'website'       => $user->getValue('WEBSITE'),
        'profile'           => $encodedProfile['profile'],
        'profile_mime'      => $encodedProfile['profile_mime'],
        'profile_has_image' => $encodedProfile['profile_has_image']
    ];
}

$totalCount = count($allContacts);

if ($pagingEnabled) {
    $contacts = array_slice($allContacts, $offset, $limit);
    $hasMore = ($offset + count($contacts)) < $totalCount;
    
    echo json_encode([
        'contacts' => $contacts,
        'paging' => [
            'offset' => $offset,
            'limit' => $limit,
            'total' => $totalCount,
            'hasMore' => $hasMore
        ]
    ]);
} else {
    echo json_encode([ 'contacts' => $allContacts ]);
}
