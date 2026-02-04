<?php
/**
    * Device Registration API
    * 
    * This endpoint records a device request for admin approval. It does NOT issue an API key.
    * Uses the TBL_RE_DEVICES table (adm_re_devices) defined in ConfigTables.php.
    * The table must be installed via the residents plugin installation page.
    */
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$currentOrgId = isset($gCurrentOrgId)
    ? (int) $gCurrentOrgId
    : (isset($gCurrentOrganization) ? (int) $gCurrentOrganization->getValue('org_id') : 0);

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$device = $data['device'] ?? [];

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

// Fetch user and validate password
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
    [$today, $today, $username, $currentOrgId],
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

//Check whether the user is allowed to log in
validateUserLogin($userId, $password);

// Ensure the user belongs to the current organization
if ($currentOrgId > 0 && !isMember($userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'User has no active membership in this organization.']);
    exit;
}

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

// Check for existing device entry for this user
$existingStmt = $gDb->queryPrepared(
    'SELECT rde_id, rde_is_active FROM ' . TBL_RE_DEVICES . ' WHERE rde_usr_id = ? AND rde_device_id = ? AND rde_org_id = ? ORDER BY rde_timestamp_create DESC LIMIT 1',
    [$userId, $deviceId, $currentOrgId],
    false
);
$existing = $existingStmt ? $existingStmt->fetch() : false;
if ($existingStmt === false) {
    admidioApiError('Database error', 500);
}

if ($existing) {
    // If device is already approved, do not change its status.
    if ((int) $existing['rde_is_active'] === 1) {
        echo json_encode([
            'status' => 'approved',
            'message' => 'Device is already approved.',
            'device_id' => (int) $existing['rde_id'],
        ]);
        exit;
    }

    // Pending device: refresh metadata but keep it pending.
    $updated = $gDb->queryPrepared(
    'UPDATE ' . TBL_RE_DEVICES . ' SET rde_platform = ?, rde_brand = ?, rde_model = ?, rde_timestamp_change = NOW() WHERE rde_id = ?',
    [$platform, $brand, $model, $existing['rde_id']],
    false
    );
    if ($updated === false) {
        admidioApiError('Database error', 500);
    }
    $requestId = (int) $existing['rde_id'];
} else {
    // Insert new device entry
    $inserted = $gDb->queryPrepared(
    'INSERT INTO ' . TBL_RE_DEVICES . ' (rde_device_id, rde_usr_id, rde_org_id, rde_is_active, rde_platform, rde_brand, rde_model, rde_timestamp_create) VALUES (?, ?, ?, 0, ?, ?, ?, NOW())',
    [$deviceId, $userId, $currentOrgId, $platform, $brand, $model],
    false
    );
    if ($inserted === false) {
        admidioApiError('Database error', 500);
    }
    $requestId = (int) $gDb->lastInsertId();
}

echo json_encode([
    'status' => 'pending',
    'message' => 'Device is registered. Ask admin to approve to login.',
    'device_id' => $requestId,
]);
