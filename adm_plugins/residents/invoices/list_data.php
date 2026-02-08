<?php
/**
 ***********************************************************************************************
 * Server-side endpoint for the Residents invoices DataTable
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gSettingsManager, $gCurrentOrgId, $gCurrentOrganization, $gDbType;

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

$length = $length < 0 ? 1000 : $length;
$start = max(0, $start);

$orderColumn = 'start_date';
$orderDirection = 'DESC';
$columnMap = array(
    0 => 'select',
    1 => 'number',
    2 => 'start_date',
    3 => 'end_date',
    4 => 'status',
    5 => 'user',
    6 => 'due_date',
    7 => 'amount'
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
$getPaid = trim((string)admFuncVariableIsValid($_GET, 'filter_paid', 'string'));
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date');
if ($getDateFrom === '' && $getDateTo === '') {
    $getDateFrom = date('Y-m-01');
    $getDateTo = date('Y-m-t');
}
$getQ = admFuncVariableIsValid($_GET, 'q', 'string');

$isAdmin = isResidentsAdminBySettings();

$listFilters = array(
    'org_id' => isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : (int)$gCurrentOrgId,
    'is_admin' => $isAdmin,
    'current_user_id' => (int)$gCurrentUser->getValue('usr_id'),
    'filter_group' => $getGroup,
    'filter_user' => $getUser,
    'filter_paid' => $getPaid,
    'date_from' => $getDateFrom,
    'date_to' => $getDateTo,
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
    $listResult = TableResidentsInvoice::fetchList($gDb, $listFilters, $listOptions);
} catch (Exception $e) {
    echo json_encode(array('draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $e->getMessage()));
    exit;
}

$rows = $listResult['rows'];
$recordsFiltered = (int)($listResult['total'] ?? 0);
$recordsTotal = (int)($listResult['total_base'] ?? $recordsFiltered);
$currencyFallback = $gSettingsManager->getString('system_currency');
    $paidLabel = $gL10n->get('RE_PAID');
    $unpaidLabel = $gL10n->get('RE_UNPAID');
$currentUserId = (int)$gCurrentUser->getValue('usr_id');
    $csrfToken = htmlspecialchars($GLOBALS['gCurrentSession']->getCsrfToken(), ENT_QUOTES, 'UTF-8');

$formatDate = static function ($value) use ($gSettingsManager) {
    if (empty($value)) {
        return '';
    }
    try {
        $dt = new DateTime((string)$value);
        return $dt->format($gSettingsManager->getString('system_date'));
    } catch (Exception $e) {
        $s = (string)$value;
        return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
    }
};

$data = array();
foreach ($rows as $row) {
    $currency = $row['total_currency'] ?: $currencyFallback;
    $amountDisplay = htmlspecialchars(trim($currency . ' ' . number_format((float)$row['total_amount'], 2, '.', '')), ENT_QUOTES, 'UTF-8');

    $isPaid = ((int)($row['riv_is_paid'] ?? 0) === 1);
    $statusText = $isPaid ? $paidLabel : $unpaidLabel;
    $badgeClass = $isPaid ? 'badge bg-success' : 'badge bg-warning text-dark';
    $statusHtml = '<span class="' . $badgeClass . '">' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</span>';

    $actionIcons = '<a class="admidio-icon-link" title="' . $gL10n->get('RE_VIEW') . '" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/detail.php', array('id' => $row['riv_id'])) . '"><i class="bi bi-eye"></i></a>';
    $actionIcons .= ' <a class="admidio-icon-link" title="' . $gL10n->get('SYS_PDF') . '" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/pdf.php', array('id' => $row['riv_id'])) . '"><i class="bi bi-file-earmark-pdf"></i></a>';
    if ($isAdmin) {
        if (!$isPaid) {
            $actionIcons .= ' <a class="admidio-icon-link" title="' . $gL10n->get('SYS_EDIT') . '" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/edit.php', array('id' => $row['riv_id'])) . '"><i class="bi bi-pencil-square"></i></a>';
    }
        $confirmText = htmlspecialchars($gL10n->get('RE_DELETE_INVOICE_CONFIRM'), ENT_QUOTES, 'UTF-8');
        if (!$isPaid) {
            $deleteActionUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/delete.php';
            $actionIcons .= ' <form method="post" action="' . $deleteActionUrl . '" class="d-inline" onsubmit="return confirm(\'' . $confirmText . '\');">'
            . '<input type="hidden" name="id" value="' . (int)$row['riv_id'] . '" />'
            . '<input type="hidden" name="admidio-csrf-token" value="' . $csrfToken . '" />'
            . '<button type="submit" class="admidio-icon-link text-danger" title="' . $gL10n->get('SYS_DELETE') . '" style="border:0;background:none;padding:0;">'
            . '<i class="bi bi-trash"></i>'
            . '</button>'
            . '</form>';
    }
    }

    $ownsInvoice = ((int)$row['riv_usr_id'] === $currentUserId);
    $payButtonHtml = '';
    if ($ownsInvoice && !$isPaid) {
        $payButtonHtml = '<a class="btn btn-sm btn-primary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/confirm_pay.php', array('invoice_id' => $row['riv_id'])) . '"><i class="bi bi-credit-card"></i> ' . $gL10n->get('RE_PAY_NOW') . '</a>';
    }
    $actions = '<div class="re-actions"><span class="re-actions-icons">' . $actionIcons . '</span>';
    if ($payButtonHtml !== '') {
        $actions .= '<span class="re-actions-pay">' . $payButtonHtml . '</span>';
    }
    $actions .= '</div>';

    $amountRaw = number_format((float)$row['total_amount'], 2, '.', '');
    $currencyAttr = htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
    $ownerId = (int)$row['riv_usr_id'];
    $selectHtml = '<input type="checkbox" class="re-row-select" value="'.(int)$row['riv_id'].'" data-amount="' . htmlspecialchars($amountRaw, ENT_QUOTES, 'UTF-8') . '" data-currency="' . $currencyAttr . '" data-owner="' . $ownerId . '" data-paid="' . ($isPaid ? '1' : '0') . '" />';
    $data[] = array(
    $selectHtml,
    htmlspecialchars((string)$row['riv_number'], ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($formatDate($row['riv_start_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($formatDate($row['riv_end_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
    $statusHtml,
    htmlspecialchars((string)($row['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($formatDate($row['riv_due_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
    $amountDisplay,
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
