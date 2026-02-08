<?php
/**
 ***********************************************************************************************
 * Server-side endpoint for the Residents charges DataTable
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');
require_once(__DIR__ . '/../classes/TableResidentsCharge.php');

global $gDb, $gL10n, $gSettingsManager;

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

$orderColumn = 'name';
$orderDirection = 'ASC';
// Include select and ID columns in mapping for sorting behavior when admin view is active
$columnMap = $isAdmin
    ? array(
    0 => 'select',
    1 => 'id',
    2 => 'name',
    3 => 'period',
    4 => 'amount',
    5 => 'roles',
    6 => 'actions'
    )
    : array(
    0 => 'name',
    1 => 'period',
    2 => 'amount',
    3 => 'roles',
    4 => 'actions'
    );
if (isset($_GET['order'][0]['column'])) {
    $columnIndex = (int)$_GET['order'][0]['column'];
    if (array_key_exists($columnIndex, $columnMap)) {
        $orderColumn = $columnMap[$columnIndex];
    }
}
if (isset($_GET['order'][0]['dir'])) {
    $dir = strtoupper((string)$_GET['order'][0]['dir']);
    if ($dir === 'DESC') {
        $orderDirection = 'DESC';
    }
}

if ($gCurrentOrgId > 0) {
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_CHARGES . ' WHERE rch_org_id = ?', array($gCurrentOrgId), false);
} else {
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_CHARGES, array(), false);
}
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
$recordsTotal = count($rows);

$searchNeedle = trim(mb_strtolower($searchValue));
if ($searchNeedle !== '') {
    $rows = array_filter($rows, static function ($row) use ($searchNeedle) {
        $name = mb_strtolower((string)($row['rch_name'] ?? ''));
        $period = mb_strtolower((string)($row['rch_period'] ?? ''));
        $amount = mb_strtolower((string)($row['rch_amount'] ?? ''));
        if ($name !== '' && mb_strpos($name, $searchNeedle) !== false) {
            return true;
    }
        if ($period !== '' && mb_strpos($period, $searchNeedle) !== false) {
            return true;
    }
        return $amount !== '' && mb_strpos($amount, $searchNeedle) !== false;
    });
}

$filteredRows = array_values($rows);
$recordsFiltered = count($filteredRows);

if ($recordsFiltered > 1) {
    $fieldMap = array(
    'id' => 'rch_id',
    'name' => 'rch_name',
    'period' => 'rch_period',
    'amount' => 'rch_amount',
    'roles' => 'rch_role_ids'
    );
    $field = $fieldMap[$orderColumn] ?? 'rch_name';

    usort($filteredRows, static function ($a, $b) use ($field, $orderDirection) {
        $aVal = $a[$field] ?? '';
        $bVal = $b[$field] ?? '';
        if ($field === 'rch_amount' || $field === 'rch_id') {
            $aVal = (float)$aVal;
            $bVal = (float)$bVal;
    } else {
            $aVal = mb_strtolower((string)$aVal);
            $bVal = mb_strtolower((string)$bVal);
    }
        if ($aVal == $bVal) {
            return 0;
    }
        $result = ($aVal < $bVal) ? -1 : 1;
        return ($orderDirection === 'DESC') ? -$result : $result;
    });
}

$pagedRows = ($length >= 0) ? array_slice($filteredRows, $start, $length) : $filteredRows;

$currencyLabel = '';
if (isset($gSettingsManager) && method_exists($gSettingsManager, 'getString')) {
    $currencyLabel = trim((string)$gSettingsManager->getString('system_currency'));
}
$rolesMap = residentsGetRoleOptions();
$periodLabels = array();
foreach (TableRoles::getCostPeriods() as $key => $label) {
    $periodLabels[(string)$key] = $label;
}

$data = array();
$chargeModel = new TableResidentsCharge($gDb);
$deleteConfirm = htmlspecialchars($gL10n->get('RE_CHARGERS_DELETE_CONFIRM'), ENT_QUOTES, 'UTF-8');

foreach ($pagedRows as $row) {
    $chargeModel->clear();
    $chargeModel->setArray($row);

    $roleLabels = array();
    foreach ($chargeModel->getRoleIds() as $roleId) {
        if (isset($rolesMap[$roleId])) {
            $roleLabels[] = $rolesMap[$roleId];
    }
    }

    $amountValue = number_format((float)$chargeModel->getValue('rch_amount'), 2, '.', ',');
    $amountDisplay = $currencyLabel !== '' ? $currencyLabel . ' ' . $amountValue : $amountValue;

    $chargeId = (int)$chargeModel->getValue('rch_id');
    $editUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/charges/edit.php', array('id' => $chargeId));
    $deleteUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/charges/delete.php', array('id' => $chargeId));

    $actions = '<a class="admidio-icon-link" title="' . $gL10n->get('SYS_EDIT') . '" href="' . $editUrl . '"><i class="bi bi-pencil-square"></i></a>';
    $actions .= ' <a class="admidio-icon-link text-danger" title="' . $gL10n->get('SYS_DELETE') . '" href="' . $deleteUrl . '" onclick="return confirm(\'' . $deleteConfirm . '\');"><i class="bi bi-trash"></i></a>';

    $periodValue = (string)$chargeModel->getValue('rch_period');
    $periodDisplay = ($periodValue !== '' && isset($periodLabels[$periodValue])) ? $periodLabels[$periodValue] : $periodValue;

    // Selection checkbox for admins (settings-based)
    $selectCol = $isAdmin ? '<input type="checkbox" class="re-row-select" value="' . $chargeId . '" />' : '';

    $rowData = $isAdmin
    ? array(
            $selectCol,
            $chargeId,
            htmlspecialchars((string)$chargeModel->getValue('rch_name'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($periodDisplay, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(implode(', ', $roleLabels), ENT_QUOTES, 'UTF-8'),
            $actions
    )
    : array(
            htmlspecialchars((string)$chargeModel->getValue('rch_name'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($periodDisplay, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(implode(', ', $roleLabels), ENT_QUOTES, 'UTF-8'),
            $actions
    );

    $data[] = $rowData;
}

echo json_encode(
    array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
    )
);
