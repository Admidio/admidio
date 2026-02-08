<?php
/**
 ***********************************************************************************************
 * Export payment receipt as PDF
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');

// Check if we are in API mode (API Key provided)
$useApiAuth = false;
if (isset($_SERVER['HTTP_API_KEY']) && !empty($_SERVER['HTTP_API_KEY'])) {
    $useApiAuth = true;
} elseif (isset($_GET['api_key']) && !empty($_GET['api_key'])) {
    $useApiAuth = true;
} elseif (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
    $useApiAuth = true;
} else {
    $headers = function_exists('getallheaders') ? getallheaders() : array();
    foreach ($headers as $headerName => $headerValue) {
        if (strcasecmp((string)$headerName, 'api_key') === 0) {
            if (!empty($headerValue)) {
                $useApiAuth = true;
            }
            break;
    }
    }
}

if ($useApiAuth) {
    // API Mode: correct validateApiKey will exit if key is invalid
    validateApiKey();
} else {
    // Browser Mode: Enforce valid login
    if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
        require_once(__DIR__ . '/../../../system/login_valid.php');
    } else {
        require_once(__DIR__ . '/../../../system/login_valid.php');
    }
}

// Include TCPDF (Admidio 5+ uses vendor directory)
$tcpdfPath = __DIR__ . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    require_once($tcpdfPath);
} elseif (!class_exists('TCPDF')) {
    die('TCPDF library not found. This plugin requires Admidio 5.0 or higher.');
}

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gCurrentOrganization, $gSettingsManager;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');
$isAdmin = isResidentsAdmin();

// Fetch payment via TableAccess model
$paymentRecord = new TableResidentsPayment($gDb, $id);
if ($paymentRecord->isNewRecord()) {
    die($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Organization check: payment must belong to current organization
residentsValidateOrganization($paymentRecord, 'rpa_org_id', false);

$ownerId = (int)$paymentRecord->getValue('rpa_usr_id');
if (!$isAdmin && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    die($gL10n->get('SYS_NO_RIGHTS'));
}

$paymentData = array(
    'rpa_id' => (int)$paymentRecord->getValue('rpa_id'),
    'rpa_date' => date('d.m.Y H:i', strtotime((string)$paymentRecord->getValue('rpa_date'))),
    'rpa_status' => (string)$paymentRecord->getValue('rpa_status'),
    'rpa_trans_id' => (string)$paymentRecord->getValue('rpa_trans_id'),
    'rpa_bank_ref_no' => (string)$paymentRecord->getValue('rpa_bank_ref_no'),
    'rpa_pg_pay_method' => (string)$paymentRecord->getValue('rpa_pg_pay_method'),
    'rpa_usr_id' => $ownerId,
    'user_name' => $ownerId > 0 ? residentsFetchUserNameById($ownerId) : '',
    'user_email' => $ownerId > 0 ? residentsFetchUserEmailById($ownerId) : '',
    'user_address' => ''
);

// Fetch customer address
if ($ownerId > 0) {
    $customer = residentsGetUserAddress($ownerId);
    $addressParts = array();
    if (!empty($customer['address'])) {
        $addressParts[] = $customer['address'];
    }
    if (!empty($customer['city'])) {
        $addressParts[] = $customer['city'];
    }
    if (!empty($customer['zip'])) {
        $addressParts[] = $customer['zip'];
    }
    if (!empty($customer['country'])) {
        $addressParts[] = $customer['country'];
    }
    $paymentData['user_address'] = implode(', ', $addressParts);
}

$transactionRecord = new TableResidentsTransaction($gDb);
if ($transactionRecord->readDataByColumns(array('rtr_payment_id' => $paymentData['rpa_id']))) {
    if ($paymentData['rpa_trans_id'] === '') {
        $paymentData['rpa_trans_id'] = (string)$transactionRecord->getValue('rtr_pg_id');
    }
    if ($paymentData['rpa_bank_ref_no'] === '') {
        $paymentData['rpa_bank_ref_no'] = (string)$transactionRecord->getValue('rtr_bank_ref_no');
    }
}

$itemRows = $paymentRecord->getItems(true);
$invoiceNumbers = array();
$total = 0.0;
$currency = '';

foreach ($itemRows as $item) {
    $total += (float)$item['rpi_amount'];
    if ($currency === '' && !empty($item['rpi_currency'])) {
        $currency = $item['rpi_currency'];
    }
    if (!empty($item['riv_number'])) {
        $invoiceNumbers[] = $item['riv_number'];
    }
}

$invoiceNoStr = implode(', ', array_unique($invoiceNumbers));

// Build invoice item descriptions with month/year
$itemDescriptions = array();
foreach ($itemRows as $item) {
    if (!empty($item['rpi_inv_id'])) {
        $invoiceObj = new TableResidentsInvoice($gDb, (int)$item['rpi_inv_id']);
        $invoiceItems = $invoiceObj->getItems();
        foreach ($invoiceItems as $invItem) {
            $desc = !empty($invItem['rii_name']) ? $invItem['rii_name'] : '';
            $sortDate = !empty($invItem['rii_start_date']) ? $invItem['rii_start_date'] : '9999-12-31';
            if (!empty($invItem['rii_start_date'])) {
                $desc .= ' (' . date('M Y', strtotime($invItem['rii_start_date'])) . ')';
            }
            if (!empty($desc)) {
                $itemDescriptions[$sortDate . '_' . $desc] = $desc;
            }
    }
    }
}
ksort($itemDescriptions);
$itemDescList = array();
foreach (array_unique($itemDescriptions) as $desc) {
    $itemDescList[] = '- ' . $desc;
}
$itemDescStr = !empty($itemDescList) ? implode(',<br/>', $itemDescList) : '';
if (strlen(strip_tags($itemDescStr)) > 300) {
    $itemDescStr = substr(strip_tags($itemDescStr), 0, 300) . '...';
}

if ($currency === '') {
    $currency = $gSettingsManager->getString('system_currency');
}
// Replace Rupee symbol with Rs. (core PDF fonts can't render ₹)
if ($currency === '₹' || stripos($currency, 'rupee') !== false) {
    $currency = 'Rs.';
}
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($gCurrentOrganization->getValue('org_longname'));
$pdf->SetTitle('Payment Receipt #' . $paymentData['rpa_id']);
$pdf->SetSubject('Payment Receipt');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Set font (times is a core PDF font, no additional files needed)
$pdf->SetFont('times', '', 10);

// Add a page
$pdf->AddPage();

// Colors
$orange = '#d35400';
$green = '#28a745';
$red = '#dc3545';
$gray = '#555555';
$lightGray = '#f9f9f9';
$teal = '#3697a8';

$statusColor = ($paymentData['rpa_status'] === 'SU') ? $green : $red;
$statusLabel = residentsGetPaymentStatusLabel($paymentData['rpa_status']);

// Logo
$orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
$logoPath = '';
if ($orgId > 0) {
    $customLogoPath = ADMIDIO_PATH . FOLDER_DATA . '/residents/org_logo_' . $orgId . '.png';
    if (file_exists($customLogoPath)) {
        $logoPath = $customLogoPath;
    }
}
$orgName = $gCurrentOrganization->getValue('org_longname');
$orgWebsite = $gCurrentOrganization->getValue('org_homepage');

// Build HTML content for TCPDF
$html = '
<style>
    .header { color: ' . $orange . '; font-size: 18pt; font-weight: bold; }
    .message { background-color: ' . $lightGray . '; color: #333; padding: 10px; border-left: 4px solid ' . $orange . '; font-size: 10pt; }
    .section-header { font-size: 10pt; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; text-align: center; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 8px; border-bottom: 1px solid #eee; font-size: 10pt; }
    .label { font-weight: bold; color: #000; width: 40%; }
    .value { color: #000; width: 60%; text-align: right; font-weight: bold; }
    .status { color: ' . $statusColor . '; font-weight: bold; }
    .org-name { font-size: 16pt; font-weight: bold; text-transform: uppercase; text-align: center; color: #ffffff; }
    .receipt-title { font-size: 10pt; text-align: right; margin-top: 5px; color: #ffffff; text-transform: uppercase; }
    .welcome-msg { text-align: center; margin-top: 15px; font-size: 10pt; }
    .main-table { border: none; }
    .header-row { background-color: ' . $teal . '; color: #ffffff; }
    .content-cell { background-color: #ffffff; padding: 15px; border-bottom: none; }
</style>

<table border="0" width="100%" cellpadding="0" cellspacing="0" class="main-table">
    <tr class="header-row">
    <td width="100%">
            <table border="0" width="100%" cellpadding="3" cellspacing="0">
        <tr>
                    <td width="15%" align="left" valign="middle" style="border-bottom: none;">
            <img src="' . $logoPath . '" width="50" />
                    </td>
                    <td width="70%" align="center" valign="middle" style="border-bottom: none;">
            <div class="org-name" style="line-height: 1.2;">' . $orgName . '</div>
                    </td>
                    <td width="15%" style="border-bottom: none;"></td>
        </tr>
        <tr>
                    <td width="50%" align="left" valign="middle" style="border-bottom: none;">
                <div style="font-size: 8pt; color: #ffffff; font-weight: bold;">
                            <u>' . $orgWebsite . '</u>
                </div>
                    </td>
                    <td width="50%" align="right" valign="middle" style="border-bottom: none;" colspan="2">
            <div class="receipt-title">' . $gL10n->get('RE_PAYMENT_RECEIPT_TITLE') . '</div>
                    </td>
        </tr>
            </table>
    </td>
    </tr>
    <tr>
    <td width="100%" class="content-cell">


            <div class="section-header">' . $gL10n->get('RE_RECEIPT_DETAILS') . '</div>
            <br/>

            <table cellpadding="5" width="100%">
        <tr>
                    <td width="50%" valign="top">
            <table cellpadding="5" width="100%">
                            <tr>
                <td class="label">Receipt No</td>
                <td class="value">' . $paymentData['rpa_id'] . '</td>
                            </tr>
                            <tr>
                <td class="label">Invoice No</td>
                <td class="value">' . ($invoiceNoStr ?: '-') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('RE_AMOUNT') . '</td>
                <td class="value">' . $currency . ' ' . number_format($total, 2, '.', '') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('RE_PAYMENT_DATE') . '</td>
                <td class="value">' . $paymentData['rpa_date'] . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('RE_PAYMENT_METHOD') . '</td>
                <td class="value">' . $paymentData['rpa_pg_pay_method'] . '</td>
                            </tr>
                            <tr>
                <td class="label">Bank Ref No</td>
                <td class="value">' . ($paymentData['rpa_bank_ref_no'] ?? '-') . '</td>
                            </tr>
            </table>
                    </td>
                    <td width="50%" valign="top">
            <table cellpadding="5" width="100%">
                            <tr>
                <td class="label">' . $gL10n->get('RE_CUSTOMER') . '</td>
                <td class="value">' . ($paymentData['user_name'] ?? '') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('SYS_EMAIL') . '</td>
                <td class="value">' . ($paymentData['user_email'] ?? '') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('SYS_ADDRESS') . '</td>
                <td class="value">' . ($paymentData['user_address'] ?? '-') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('RE_TRANSACTION_ID') . '</td>
                <td class="value">' . ($paymentData['rpa_trans_id'] ?? '-') . '</td>
                            </tr>
            </table>
                    </td>
        </tr>
            </table>

            <div style="height: 10px;"></div>
            <div style="text-align: left; font-size: 10pt;">
        <b>Thank you for your payment towards:</b><br/>
        ' . $itemDescStr . '
            </div>
    </td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Output
$pdf->Output('payment_receipt_' . $paymentData['rpa_id'] . '.pdf', 'D');
