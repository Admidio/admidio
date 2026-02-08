<?php
/**
 ***********************************************************************************************
 * Delete an invoice and all related rows
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

if (!isResidentsAdminBySettings()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

try {
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token'] ?? '');
} catch (Exception $e) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$invoiceId = admFuncVariableIsValid($_POST, 'id', 'int');
if ($invoiceId <= 0) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$invoice = new TableResidentsInvoice($gDb, $invoiceId);
if ($invoice->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Organization check: invoice must belong to current organization
residentsValidateOrganization($invoice, 'riv_org_id');

$isPaid = (int)$invoice->getValue('riv_is_paid') === 1;
if ($isPaid) {
    $gMessage->show($gL10n->get('RE_DELETE_PAID_DENIED'));
}

$deleted = $invoice->deleteWithRelations();

$params = array('tab' => 'invoices');
if ($deleted) {
    $params['invoice_status'] = 'deleted';
} else {
    $params['invoice_status'] = 'error';
    $params['invoice_message'] = 'Failed to delete invoice.';
}

admRedirect(SecurityUtils::encodeUrl(
    ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php',
    $params
));
