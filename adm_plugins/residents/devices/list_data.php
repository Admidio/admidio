<?php
/**
 ***********************************************************************************************
 * Returns device list data as JSON for DataTables server-side processing
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');
require_once(__DIR__ . '/../classes/TableResidentsDevice.php');

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gSettingsManager, $gCurrentOrgId, $gCurrentOrganization, $gDbType;

header('Content-Type: application/json');

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    http_response_code(403);
    echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $gL10n->get('SYS_NO_RIGHTS')));
    exit;
}

$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $gL10n->get('SYS_NO_RIGHTS')));
    exit;
}

try {
    $draw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
    $start = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
    $length = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
    $searchValue = '';
    if (isset($_GET['search'])) {
        $searchValue = admFuncVariableIsValid($_GET['search'], 'value', 'string');
    }
} catch (AdmException $e) {
    echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $e->getMessage()));
    exit;
}

$length = $length < 0 ? 1000 : $length;
$start = max(0, $start);

$orderColumn = 'number';
$orderDirection = 'DESC';
$columnMap = array(
    0 => 'select',
    1 => 'number',
    2 => 'user',
    3 => 'device_id',
    4 => 'active',
    5 => 'active_date',
    6 => 'platform',
    7 => 'brand',
    8 => 'model'
    );
if (isset($_GET['order'][0]['column'])) {
    $columnIndex = (int)$_GET['order'][0]['column'];
    if (array_key_exists($columnIndex, $columnMap)) {
        $orderColumn = $columnMap[$columnIndex];
    }
}
if (isset($_GET['order'][0]['dir'])) {
    $dir = strtoupper((string)$_GET['order'][0]['dir']);
    if (in_array($dir, array('ASC', 'DESC'), true)) {
        $orderDirection = $dir;
    }
}

$getGroup = admFuncVariableIsValid($_GET, 'filter_group', 'int');
$getUser = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$getActive = trim((string)admFuncVariableIsValid($_GET, 'filter_active', 'string'));
// $getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
// $getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date');
// if ($getDateFrom === '' && $getDateTo === '') {
//     $getDateFrom = date('Y-m-01');
//     $getDateTo = date('Y-m-t');
// }
$getQ = admFuncVariableIsValid($_GET, 'q', 'string');

$isAdmin = isResidentsAdminBySettings();
$listFilters = array(
    'org_id' => isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : (int)$gCurrentOrgId,
    'is_admin' => $isAdmin,
    'filter_group' => $getGroup,
    'filter_user' => $getUser,
    'filter_active' => $getActive,
    // 'date_from' => $getDateFrom,
    // 'date_to' => $getDateTo,
    'search' => ($searchValue !== '' ? $searchValue : $getQ),
    'sort_col' => $orderColumn,
    'sort_dir' => $orderDirection
);
$listOptions = array(
    'length' => $length,
    'offset' => $start,
    'db_type' => $gDbType,
    'profile_first_name_id' => (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    'profile_last_name_id' => (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id')
);

try {
    $listResult = TableResidentsDevice::fetchList($gDb, $listFilters, $listOptions);
} catch (Exception $e) {
    echo json_encode(array('draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $e->getMessage()));
    exit;
}

$rows = $listResult['rows'];
$recordsFiltered = (int)($listResult['total'] ?? 0);
$recordsTotal = (int)($listResult['total_base'] ?? $recordsFiltered);
$activeLabel = $gL10n->get('RE_DEVICE_ACTIVE');
$inactiveLabel = $gL10n->get('RE_INACTIVE');
$csrfToken = htmlspecialchars($GLOBALS['gCurrentSession']->getCsrfToken(), ENT_QUOTES, 'UTF-8');

$data = array();
foreach ($rows as $row) {

$isActive = ((int)($row['rde_is_active'] ?? 0) === 1);
$activeText = $isActive ? $activeLabel : $inactiveLabel;
$badgeClass = $isActive ? 'badge bg-success' : 'badge bg-warning text-dark';
$activeHtml = '<span class="' . $badgeClass . '">' . htmlspecialchars($activeText, ENT_QUOTES, 'UTF-8') . '</span>';
$deleteConfirm = htmlspecialchars($gL10n->get('RE_DELETE_DEVICE_CONFIRM'), ENT_QUOTES, 'UTF-8');
$approveConfirm = htmlspecialchars($gL10n->get('RE_APPROVE_DEVICE_CONFIRM'), ENT_QUOTES, 'UTF-8');
$resetConfirm = htmlspecialchars($gL10n->get('RE_RESET_DEVICE_CONFIRM'), ENT_QUOTES, 'UTF-8');
$unapproveConfirm = htmlspecialchars($gL10n->get('RE_UNAPPROVE_DEVICE_CONFIRM'), ENT_QUOTES, 'UTF-8');

$deleteUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/devices/delete.php';
$btnHtml = '';
if ($row && !$isActive) {
    $btnHtml = '<a class="btn btn-sm btn-primary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/devices/approve.php', array('id' => $row['rde_id'])) . '" onclick="return confirm(\'' . $approveConfirm . '\');"><i class="bi bi-check-circle"></i> ' . $gL10n->get('RE_APPROVE') . '</a>';
}else{
    $btnHtml = '<a class="btn btn-sm btn-primary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/devices/reset.php', array('id' => $row['rde_id'])) . '" onclick="return confirm(\'' . $resetConfirm . '\');"><i class="bi bi-check-circle"></i> ' . $gL10n->get('RE_RESET') . '</a>';
    $btnHtml .= ' <a class="btn btn-sm btn-danger text-white d-inline-flex align-items-center gap-1" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/devices/unapprove.php', array('id' => $row['rde_id'])) . '" onclick="return confirm(\'' . $unapproveConfirm . '\');"><i class="bi bi-slash-circle"></i> ' . $gL10n->get('RE_UNAPPROVE') . '</a>';
}
if ($btnHtml !== '') {
    $actions = '<span class="re-actions-pay">' . $btnHtml . '</span>';
}
$actions .= ' <form method="post" action="' . $deleteUrl . '" class="d-inline" style="margin-left: 5px;" onsubmit="return confirm(\'' . $deleteConfirm . '\');">'
    . '<input type="hidden" name="id" value="' . (int)$row['rde_id'] . '" />'
    . '<input type="hidden" name="admidio-csrf-token" value="' . $csrfToken . '" />'
    . '<button type="submit" class="admidio-icon-link text-danger" title="' . $gL10n->get('SYS_DELETE') . '" style="border:0;background:none;padding:0;">'
    . '<i class="bi bi-trash"></i>'
    . '</button>'
    . '</form>';

$selectHtml = $isAdmin ? '<input type="checkbox" class="re-row-select" value="' . $row['rde_id'] . '" />' : '';
    $data[] = array(
            $selectHtml,
            (int)$row['rde_id'],
            htmlspecialchars((string)($row['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$row['rde_device_id'], ENT_QUOTES, 'UTF-8'),
            $activeHtml,
            htmlspecialchars((string)$row['rde_active_date'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$row['rde_platform'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$row['rde_brand'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$row['rde_model'], ENT_QUOTES, 'UTF-8'),
            $actions
    );
}

echo json_encode(
    array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
    )
);
