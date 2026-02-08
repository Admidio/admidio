<?php
/**
    * Server-side endpoint for the Residents payments DataTable.
    */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gSettingsManager, $gCurrentOrgId, $gDbType;

header('Content-Type: application/json');

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
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

$getQ = trim((string)admFuncVariableIsValid($_GET, 'q', 'string'));
if ($getQ !== '') {
    $searchValue = $getQ;
}

$length = $length < 0 ? 1000 : $length;
$start = max(0, $start);

$orderColumn = 'date';
$orderDirection = 'DESC';
$orderDirection = 'DESC';
$canManage = isPaymentAdmin();
$canCreatePayments = isResidentsAdminBySettings();
$canViewAll = $canCreatePayments || $canManage;

if ($canViewAll) {
    $columnMap = array(
    0 => 'select',
    1 => 'no',
    2 => 'date',
    3 => 'method',
    4 => 'type',
    5 => 'customer_name',
    6 => 'amount'
    );
} else {
    $columnMap = array(
    0 => 'no',
    1 => 'date',
    2 => 'method',
    3 => 'type',
    4 => 'customer_name',
    5 => 'amount'
    );
}
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

$getUser = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$getGroup = admFuncVariableIsValid($_GET, 'filter_group', 'int');
$getType = trim((string)admFuncVariableIsValid($_GET, 'filter_type', 'string'));
$getStart = admFuncVariableIsValid($_GET, 'filter_start', 'date');
$getEnd = admFuncVariableIsValid($_GET, 'filter_end', 'date');
if ($getStart === '' && $getEnd === '') {
    $getStart = date('Y-m-01');
    $getEnd = date('Y-m-t');
}

// Only admins may filter across users/groups. Normal users are restricted to their own payments.
if (!$canViewAll) {
    $getUser = 0;
    $getGroup = 0;
}

$listFilters = array(
    'org_id' => $gCurrentOrgId,
    'is_admin' => $canViewAll,
    'current_user_id' => (int)$gCurrentUser->getValue('usr_id'),
    'filter_user' => $getUser,
    'filter_group' => $getGroup,
    'filter_type' => $getType,
    'filter_start' => $getStart,
    'filter_end' => $getEnd,
    'search' => $searchValue,
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
    $listResult = TableResidentsPayment::fetchList($gDb, $listFilters, $listOptions);
} catch (Exception $e) {
    echo json_encode(array('draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $e->getMessage()));
    exit;
}

$rows = $listResult['rows'];
$recordsFiltered = (int)($listResult['total'] ?? 0);
$recordsTotal = (int)($listResult['total_base'] ?? $recordsFiltered);
$currencyFallback = $gSettingsManager->getString('system_currency');
$csrfToken = htmlspecialchars($GLOBALS['gCurrentSession']->getCsrfToken(), ENT_QUOTES, 'UTF-8');
$data = array();

foreach ($rows as $row) {
    $currency = $row['total_currency'] ?: $currencyFallback;
    $amountDisplay = htmlspecialchars($currency . ' ' . number_format((float)$row['total_amount'], 2, '.', ''), ENT_QUOTES, 'UTF-8');
    $actions = '<a class="admidio-icon-link" title="'.$gL10n->get('RE_VIEW').'" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/view.php', array('id'=>$row['rpa_id'])).'"><i class="bi bi-eye"></i></a>';
    $deleteAction = '';
    if ($canManage) {
        if ((string)($row['rpa_pay_type'] ?? '') === 'Offline') {
            $actions .= ' <a class="admidio-icon-link" title="'.$gL10n->get('SYS_EDIT').'" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/edit.php', array('id'=>$row['rpa_id'])).'"><i class="bi bi-pencil-square"></i></a>';
    }
        if ((string)($row['rpa_pay_type'] ?? '') !== 'Online') {
            $confirmText = htmlspecialchars($gL10n->get('RE_DELETE_PAYMENT_CONFIRM'), ENT_QUOTES, 'UTF-8');
            $deleteActionUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/delete.php';
            $deleteAction = ' <form method="post" action="' . $deleteActionUrl . '" class="d-inline" onsubmit="return confirm(\'' . $confirmText . '\');">'
        . '<input type="hidden" name="id" value="' . (int)$row['rpa_id'] . '" />'
        . '<input type="hidden" name="admidio-csrf-token" value="' . $csrfToken . '" />'
        . '<button type="submit" class="admidio-icon-link text-danger" title="' . $gL10n->get('SYS_DELETE') . '" style="border:0;background:none;padding:0;">'
        . '<i class="bi bi-trash"></i>'
        . '</button>'
        . '</form>';
    }
    }
    $actions .= ' <a class="admidio-icon-link" title="'.$gL10n->get('RE_DOWNLOAD_RECEIPT').'" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/pdf.php', array('id'=>$row['rpa_id'])).'"><i class="bi bi-file-earmark-pdf"></i></a>';
    $actions .= $deleteAction;

    $rowArray = array();
    if ($canViewAll) {
        $selectHtml = ($canManage && (string)($row['rpa_pay_type'] ?? '') !== 'Online') ? '<input type="checkbox" class="re-row-select" value="'.(int)$row['rpa_id'].'" />' : '';
        $rowArray[] = $selectHtml;
    }
    $rowArray[] = (int)$row['rpa_id'];
    $rowArray[] = htmlspecialchars(residentsFormatDateForUi((string)($row['rpa_date'] ?? '')), ENT_QUOTES, 'UTF-8');
    $rowArray[] = htmlspecialchars((string)$row['rpa_pg_pay_method'], ENT_QUOTES, 'UTF-8');
    
    $payTypeVal = (string)($row['rpa_pay_type'] ?? '');
    $payType = ($payTypeVal === 'Offline') ? $gL10n->get('RE_PAYMENT_TYPE_OFFLINE') : $gL10n->get('RE_PAYMENT_TYPE_ONLINE');
    $rowArray[] = htmlspecialchars($payType, ENT_QUOTES, 'UTF-8');

    $rowArray[] = htmlspecialchars((string)($row['user_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $rowArray[] = $amountDisplay;
    $rowArray[] = $actions;

    $data[] = $rowArray;
}

echo json_encode(
    array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
    )
);

