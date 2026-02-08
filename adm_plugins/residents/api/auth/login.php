<?php
/**
 ***********************************************************************************************
 * Device Login API - Authenticates a user and returns an API key if the device is approved
 *
 * Uses the TBL_RE_DEVICES table (adm_re_devices) defined in ConfigTables.php.
 * The table must be installed via the residents plugin installation page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Users\Entity\User;

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$device = $data['device'] ?? [];
$orgId = $_GET['org_id'] ?? $gCurrentOrgId;

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

$today = date('Y-m-d');
$guser = $gDb->queryPrepared(
    'SELECT DISTINCT u.usr_id, u.usr_password
        FROM ' . TBL_USERS . ' u
        INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id
            AND m.mem_begin <= ?
            AND m.mem_end > ?
        INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
        WHERE u.usr_login_name = ?
            AND u.usr_valid = true
            AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
        LIMIT 1',
    [$today, $today, $username, $orgId],
    false
);
if ($guser === false) {
    admidioApiError('Database error', 500);
}
$row = $guser->fetch();

if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

$userId = (int) $row['usr_id'];
$GLOBALS['gCurrentOrgId'] = (int) $orgId;

//Check whether the user is allowed to log in
validateUserLogin($userId, $password);

// Device info is required to match the correct device record.
if (!is_array($device)
    || empty($device['deviceId'])
    || empty($device['platform'])
    || empty($device['brand'])
    || empty($device['model'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Device information is required.']);
    exit;
}

$platform = (string) $device['platform'];
$brand = (string) $device['brand'];
$model = (string) $device['model'];
$deviceId = (string) $device['deviceId'];

// Check device approval status
$orgFilter = ($gCurrentOrgId > 0) ? ' AND rde_org_id = ?' : '';
$deviceParams = [$userId, $deviceId];
if ($gCurrentOrgId > 0) {
    $deviceParams[] = $gCurrentOrgId;
}

$deviceStmt = $gDb->queryPrepared(
    'SELECT rde_id, rde_is_active, rde_api_key FROM ' . TBL_RE_DEVICES . ' WHERE rde_usr_id = ? AND rde_device_id = ?' . $orgFilter . ' ORDER BY rde_timestamp_create DESC LIMIT 1',
    $deviceParams,
    false
);
if ($deviceStmt === false) {
    admidioApiError('Database error', 500);
}
$deviceRow = $deviceStmt->fetch();

if (!$deviceRow) {
    // Auto-create a pending device request on first login attempt.
    $insertColumns = 'rde_device_id, rde_usr_id, rde_is_active, rde_platform, rde_brand, rde_model, rde_timestamp_create';
    $insertValues = '?, ?, 0, ?, ?, ?, NOW()';
    $insertParams = [$deviceId, $userId, $platform, $brand, $model];
    
    if ($gCurrentOrgId > 0) {
        $insertColumns .= ', rde_org_id';
        $insertValues .= ', ?';
        $insertParams[] = $gCurrentOrgId;
    }
    
    $inserted = $gDb->queryPrepared(
    'INSERT INTO ' . TBL_RE_DEVICES . ' (' . $insertColumns . ') VALUES (' . $insertValues . ')',
    $insertParams,
    false
    );
    if ($inserted === false) {
        admidioApiError('Database error', 500);
    }
    $requestId = (int) $gDb->lastInsertId();

    echo json_encode([
    'status' => 'pending',
    'message' => 'Device request submitted. Ask admin to approve to login.',
    'device_id' => $requestId,
    ]);
    exit;
}

if (!$deviceRow['rde_is_active']) {
    echo json_encode([
    'status' => 'pending',
    'message' => 'Device request is not approved yet.',
    'device_id' => (int) $deviceRow['rde_id'],
    ]);
    exit;
}

// Generate API key if not already set
$apiKey = (string) ($deviceRow['rde_api_key'] ?? '');
if ($apiKey === '') {
    // Safety net: in case an old device was approved without an API key.
    $apiKey = bin2hex(random_bytes(20));
    $updated = $gDb->queryPrepared(
    'UPDATE ' . TBL_RE_DEVICES . ' SET rde_api_key = ?, rde_timestamp_change = NOW() WHERE rde_id = ?',
    [$apiKey, $deviceRow['rde_id']],
    false
    );
    if ($updated === false) {
        admidioApiError('Database error', 500);
    }
}

$user = new User($gDb, $gProfileFields);
$user->readDataById($userId);
$user->updateLoginData();

$userData = [
    'id'         => $userId,
    'login'      => $user->getValue('usr_login_name'),
    'first_name' => $user->getValue('FIRST_NAME'),
    'last_name'  => $user->getValue('LAST_NAME'),
    'email'      => $user->getValue('EMAIL'),
    'api_key'    => $apiKey,
];

echo json_encode([
    'status' => 'approved',
    'user'   => $userData,
]);
