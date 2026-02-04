<?php
$originalScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
$scriptFilenameTemporarilyChanged = false;
if ($originalScriptFilename && basename($originalScriptFilename) === basename(__FILE__)) {
    // Prevent bootstrap guard in system/common.php from exiting for same-named scripts.
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/message_common_entry.php';
    $scriptFilenameTemporarilyChanged = true;
}

require_once(__DIR__ . '/../../../../system/common.php');
if ($scriptFilenameTemporarilyChanged && $originalScriptFilename !== null) {
    $_SERVER['SCRIPT_FILENAME'] = $originalScriptFilename;
}
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$endpointName = 'message/common';

if (!defined('DATE_NOW')) {
    define('DATE_NOW', date('Y-m-d'));
}
if (!defined('DATETIME_NOW')) {
    define('DATETIME_NOW', date('Y-m-d H:i:s'));
}

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

if (!$gSettingsManager->getBool('pm_module_enabled') && $gSettingsManager->getInt('mail_module_enabled') === 0) {
    admidioApiError('Messages module is disabled', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

if (!isMember($currentUserId)) {
    admidioApiError('User has no active membership in this organization', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

$limitsOnly = admFuncVariableIsValid($_GET, 'limits_only', 'bool', array('defaultValue' => false));
$attachmentsLimit = emailAttachmentLimitPayload();

if ($limitsOnly) {
    echo json_encode(array('limits' => array('attachments' => $attachmentsLimit)));
    exit();
}

$scopeInput = strtolower(admFuncVariableIsValid($_GET, 'scope', 'string', array('defaultValue' => 'user')));
$query = trim((string) admFuncVariableIsValid($_GET, 'query', 'string', array('defaultValue' => '')));
$limit = (int) admFuncVariableIsValid($_GET, 'limit', 'int', array('defaultValue' => 25));
$offset = (int) admFuncVariableIsValid($_GET, 'offset', 'int', array('defaultValue' => 0));
$membershipMode = (int) admFuncVariableIsValid($_GET, 'membership_mode', 'int', array('defaultValue' => 0));

if ($limit < 1) {
    $limit = 1;
} elseif ($limit > 100) {
    $limit = 100;
}
if ($offset < 0) {
    $offset = 0;
}
if ($membershipMode < 0 || $membershipMode > 2) {
    $membershipMode = 0;
}

$scope = 'user';
switch ($scopeInput) {
    case 'role':
    case 'roles':
    case 'group':
    case 'groups':
    $scope = 'role';
    break;
    case 'user':
    case 'users':
    default:
    $scope = 'user';
    break;
}

try {
    if ($scope === 'role') {
        $items = fetchRoleRecipients($query, $limit, $offset, $membershipMode);
    } else {
        $items = fetchUserRecipients($query, $limit, $offset, $membershipMode);
    }

    echo json_encode(array(
    'scope' => $scope,
    'query' => $query,
    'membership_mode' => $membershipMode,
    'count' => count($items),
    'items' => $items,
    'limits' => array('attachments' => $attachmentsLimit),
    ));
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ));
}

function fetchUserRecipients($search, $limit, $offset, $membershipMode)
{
    global $gDb, $gProfileFields, $currentUserId;

    $firstNameId = (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id');
    $lastNameId = (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id');
    $emailId = (int) $gProfileFields->getProperty('EMAIL', 'usf_id');

    $params = array(DATE_NOW);

    $sql = 'SELECT DISTINCT u.usr_id,
            u.usr_uuid,
            u.usr_login_name,
            fn.usd_value AS first_name,
            ln.usd_value AS last_name,
            mail.usd_value AS email
    FROM ' . TBL_USERS . ' u
    LEFT JOIN ' . TBL_USER_DATA . ' fn
            ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $firstNameId . '
    LEFT JOIN ' . TBL_USER_DATA . ' ln
            ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lastNameId . '
    LEFT JOIN ' . TBL_USER_DATA . ' mail
            ON mail.usd_usr_id = u.usr_id AND mail.usd_usf_id = ' . $emailId . '
    INNER JOIN ' . TBL_MEMBERS . ' mem
            ON mem.mem_usr_id = u.usr_id
            AND mem.mem_begin <= ?';

    switch ($membershipMode) {
        case 1:
            $sql .= ' AND mem.mem_end IS NOT NULL AND mem.mem_end < ?';
            $params[] = DATE_NOW;
            break;
        case 2:
            // include all members regardless of end date
            break;
        case 0:
        default:
            $sql .= ' AND (mem.mem_end IS NULL OR mem.mem_end >= ?)';
            $params[] = DATE_NOW;
            break;
    }

    $sql .= '
    INNER JOIN ' . TBL_ROLES . ' r
            ON r.rol_id = mem.mem_rol_id
            AND r.rol_valid = true
    WHERE u.usr_valid = true';

    if (!empty($currentUserId)) {
        $sql .= ' AND u.usr_id <> ?';
        $params[] = $currentUserId;
    }

    if ($search !== '') {
        $sql .= <<<'SQL'
        AND (
          LOWER(COALESCE(fn.usd_value, '')) LIKE ?
        OR LOWER(COALESCE(ln.usd_value, '')) LIKE ?
        OR LOWER(COALESCE(mail.usd_value, '')) LIKE ?
        OR LOWER(u.usr_login_name) LIKE ?
        OR LOWER(COALESCE(CONCAT_WS(' ', fn.usd_value, ln.usd_value), '')) LIKE ?
        )
SQL;
        $like = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY
            COALESCE(ln.usd_value, \'\') ASC,
            COALESCE(fn.usd_value, \'\') ASC,
            u.usr_login_name ASC
    LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $statement = $gDb->queryPrepared($sql, $params, false);
    if ($statement === false) {
        admidioApiError('Database error', 500);
    }

    $results = array();
    if ($statement !== false) {
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $firstName = trim((string) (isset($row['first_name']) ? $row['first_name'] : ''));
            $lastName = trim((string) (isset($row['last_name']) ? $row['last_name'] : ''));
            $displayName = trim($firstName . ' ' . $lastName);
            if ($displayName === '') {
                $displayName = trim((string) (isset($row['usr_login_name']) ? $row['usr_login_name'] : ''));
            }

            $results[] = array(
        'type' => 'user',
        'id' => (int) $row['usr_id'],
        'usr_uuid' => $row['usr_uuid'],
        'display_name' => $displayName !== '' ? $displayName : 'User #' . (int) $row['usr_id'],
        'email' => trim((string) (isset($row['email']) ? $row['email'] : '')),
            );
    }
    }

    return $results;
}

function fetchRoleRecipients($search, $limit, $offset, $membershipMode)
{
    global $gDb, $gCurrentOrganization;

    $orgId = isset($gCurrentOrganization) ? (int) $gCurrentOrganization->getValue('org_id') : 0;
    $params = array(DATE_NOW);

    $sql = 'SELECT r.rol_id,
    r.rol_uuid,
    r.rol_name,
    c.cat_name,
    c.cat_org_id,
    COALESCE(member_counts.total_members, 0) AS member_count
            FROM ' . TBL_ROLES . ' AS r
    INNER JOIN ' . TBL_CATEGORIES . ' AS c
    ON c.cat_id = r.rol_cat_id
    LEFT JOIN (
    SELECT mem.mem_rol_id,
                    COUNT(DISTINCT mem.mem_usr_id) AS total_members
    FROM ' . TBL_MEMBERS . ' AS mem
    WHERE mem.mem_begin <= ?';

    switch ($membershipMode) {
        case 1:
            $sql .= ' AND mem.mem_end IS NOT NULL AND mem.mem_end < ?';
            $params[] = DATE_NOW;
            break;
        case 2:
            // all members
            break;
        case 0:
        default:
            $sql .= ' AND (mem.mem_end IS NULL OR mem.mem_end >= ?)';
            $params[] = DATE_NOW;
            break;
    }

    $sql .= ' GROUP BY mem.mem_rol_id
            ) AS member_counts
    ON member_counts.mem_rol_id = r.rol_id
            WHERE r.rol_valid = true
    AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)';

    $params[] = $orgId;

    if ($search !== '') {
        $sql .= ' AND LOWER(r.rol_name) LIKE ?';
        $params[] = '%' . mb_strtolower($search, 'UTF-8') . '%';
    }

    $sql .= ' ORDER BY (c.cat_name IS NULL) ASC, c.cat_name ASC, r.rol_name ASC
            LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $statement = $gDb->queryPrepared($sql, $params, false);
    if ($statement === false) {
        admidioApiError('Database error', 500);
    }

    $results = array();
    if ($statement !== false) {
        $modeOptions = recipientRoleModeOptions();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $results[] = array(
        'type' => 'role',
        'id' => (int) $row['rol_id'],
        'role_uuid' => $row['rol_uuid'],
        'display_name' => (string) $row['rol_name'],
        'category' => (string) (isset($row['cat_name']) ? $row['cat_name'] : ''),
        'member_count' => (int) (isset($row['member_count']) ? $row['member_count'] : 0),
        'default_mode' => $membershipMode,
        'mode_options' => $modeOptions,
            );
    }
    }

    return $results;
}

if (!function_exists('admidioApiLog')) {
    function admidioApiLog($message, $context = array(), $level = 'error')
    {
        global $gLogger;
        $prefix = '[Residents Messages API] ';
        if (isset($gLogger) && method_exists($gLogger, $level)) {
            $gLogger->{$level}($prefix . $message, $context);
            return;
    }
        if (isset($gLogger)) {
            $gLogger->error($prefix . $message, $context);
            return;
    }
        $encoded = empty($context) ? '' : ' ' . json_encode($context);
        error_log($prefix . $message . $encoded);
    }
}

if (!function_exists('admidioApiError')) {
    function admidioApiError($message, $statusCode, $context = array())
    {
        $context['status'] = $statusCode;
        admidioApiLog($message, $context);
        http_response_code($statusCode);
        echo json_encode(array('error' => $message));
        exit();
    }
}

function recipientRoleModeOptions()
{
    return array(
    array('value' => 0, 'label' => 'Active members'),
    array('value' => 1, 'label' => 'Former members'),
    array('value' => 2, 'label' => 'All members'),
    );
}
