<?php
/**
 ***********************************************************************************************
 * Create/Edit invoice (admins only). Handles GET (form) and POST (save), with backend numbering.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
// Enforce valid login
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gProfileFields, $gCurrentUser, $gL10n, $gSettingsManager;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');
$invoice = new TableResidentsInvoice($gDb, $id);
if ($id > 0 && $invoice->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}
// Organization check: invoice must belong to current organization
if ($id > 0) {
    residentsValidateOrganization($invoice, 'riv_org_id');
}
if ($id > 0) {
    $isPaidExisting = (int)$invoice->getValue('riv_is_paid') === 1;
    if ($isPaidExisting) {
        $gMessage->show($gL10n->get('RE_INVOICE_ALREADY_PAID'));
    }
}

// AJAX: return charge for selected owner (auto-fill disabled; settings removed)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'owner_charge') {
    header('Content-Type: application/json');
    $ownerIdAjax = admFuncVariableIsValid($_GET, 'owner_id', 'int');
    $currencyAjax = isset($gSettingsManager) ? (string)$gSettingsManager->getString('system_currency') : '';

    $chargeDefinitionsAjax = residentsFetchChargeDefinitions();
    $activeRoleIds = array();
    if ($ownerIdAjax > 0) {
        $activeRoleIds = residentsGetActiveRoleIdsForUser((int)$ownerIdAjax);
    }

    $allowedDefs = array();
    foreach ($chargeDefinitionsAjax as $chargeDef) {
        $chargeRoleIds = $chargeDef['role_ids'] ?? array();
        if (!is_array($chargeRoleIds)) {
        $chargeRoleIds = array();
    }
        // No role restriction => available to everyone
        if (count($chargeRoleIds) === 0) {
        $allowedDefs[] = $chargeDef;
        continue;
    }
        // Has restrictions but user has no roles => not allowed
        if (count($activeRoleIds) === 0) {
        continue;
    }
        if (count(array_intersect($chargeRoleIds, $activeRoleIds)) > 0) {
        $allowedDefs[] = $chargeDef;
    }
    }

    $charges = array();
    foreach ($allowedDefs as $chargeDef) {
        $charges[] = array(
        'id' => (int)($chargeDef['id'] ?? 0),
        'name' => (string)($chargeDef['name'] ?? ''),
        'amount' => (float)($chargeDef['amount'] ?? 0.0),
        'period_months' => (int)($chargeDef['period_months'] ?? 1)
        );
    }

    // Keep legacy keys to avoid surprises, but primary payload is `charges`
    echo json_encode(array(
    'charges' => $charges,
    'currency' => $currencyAjax,
    'charge' => null,
    'period' => '',
    'period_code' => null,
    'quantity_factor' => null
    ));
    exit;
}

// Inline error message for POST failures (avoid separate SQL error page)
$inlineErrorMessage = '';
$hasSaveError = false;
$itemsFromPost = null;

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        SecurityUtils::validateCsrfToken((string)($_POST['admidio-csrf-token'] ?? ''));
    } catch (Throwable $e) {
        $gMessage->show($e->getMessage());
    }

    $postedId = (int)($_POST['riv_id'] ?? 0);
    if ($postedId !== (int)$invoice->getValue('riv_id')) {
        $invoice = new TableResidentsInvoice($gDb, $postedId);
        if ($postedId > 0 && $invoice->isNewRecord()) {
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    }

    // Do not allow changing invoice owner once the invoice exists
    if ($invoice->isNewRecord()) {
        $ownerId = (int)($_POST['riv_usr_id'] ?? $gCurrentUser->getValue('usr_id'));
    } else {
        $ownerId = (int)$invoice->getValue('riv_usr_id');
    }

    // Validate owner and at least one invoice item
    $chgIdsV = $_POST['rii_chg_id'] ?? array();
    $amtsV = $_POST['rii_amount'] ?? array();
    $hasItem = false;
    $cntV = max(count($chgIdsV), count($amtsV));
    for ($vi = 0; $vi < $cntV; $vi++) {
        $n = (int)($chgIdsV[$vi] ?? 0);
        $a = trim((string)($amtsV[$vi] ?? ''));
        if ($n > 0 || $a !== '') {
            $hasItem = true;
            break;
    }
    }
    if ($ownerId <= 0 || !$hasItem) {
        $gMessage->show($gL10n->get('RE_VALIDATION_OWNER_AND_ITEM'));
    }

    // Validate that all charge amounts are greater than zero
    for ($vi = 0; $vi < $cntV; $vi++) {
        $amtVal = trim((string)($amtsV[$vi] ?? ''));
        if ($amtVal !== '') {
            $amtNum = (float)$amtVal;
            if ($amtNum <= 0) {
                $gMessage->show($gL10n->get('RE_VALIDATION_AMOUNT_POSITIVE'));
            }
    }
    }

    // Validate required date fields
    $invoiceDatePost = trim((string)($_POST['riv_date'] ?? ''));
    $startDatePost = trim((string)($_POST['riv_start_date'] ?? ''));
    $endDatePost = trim((string)($_POST['riv_end_date'] ?? ''));
    if ($invoiceDatePost === '' || $startDatePost === '' || $endDatePost === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('RE_DATE') . ', ' . $gL10n->get('RE_START_DATE') . ', ' . $gL10n->get('RE_END_DATE'))));
    }

    // Numbering: generate next if creating and number empty
    $number = trim((string)($_POST['riv_number'] ?? ''));
    if ($invoice->isNewRecord() && $number === '') {
        $cfg = residentsReadConfig();
        $last = (int)($cfg['numbering']['last_number'] ?? 0);
        $try = $last + 1;
        while (true) {
            // NOTE: PDO::rowCount() is not reliable for SELECT (especially on MySQL).
            // Use fetchColumn() to detect an existing row.
            // Filter by org_id since invoice numbers are unique per organization
            $st = $gDb->queryPrepared(
        'SELECT 1 FROM ' . TBL_RE_INVOICES . ' WHERE riv_org_id = ? AND riv_number = ? LIMIT 1',
        array($gCurrentOrgId, (string)$try),
        false
            );
            $exists = ($st !== false && $st->fetchColumn() !== false);
            if (!$exists) {
                break;
            }
            $try++;
    }
        $number = (string)$try;
        // Persist new last_number
        $cfg['numbering']['last_number'] = $try;
        residentsWriteConfig($cfg);
    }

    // Validate uniqueness of invoice number (prevents Database->showError on duplicate key)
    // Filter by org_id since invoice numbers are unique per organization
    if (!$hasSaveError && $number !== '') {
        $dupStmt = $gDb->queryPrepared(
            'SELECT riv_id FROM ' . TBL_RE_INVOICES . ' WHERE riv_org_id = ? AND riv_number = ? LIMIT 1',
            array($gCurrentOrgId, (string)$number),
            false
        );
        if ($dupStmt === false) {
            $hasSaveError = true;
            $inlineErrorMessage = $gL10n->get('SYS_DATABASE_ERROR');
    } else {
            $dupId = (int)$dupStmt->fetchColumn();
            $currentId = (int)$invoice->getValue('riv_id');
            if ($dupId > 0 && ($invoice->isNewRecord() || $dupId !== $currentId)) {
                $hasSaveError = true;
                $inlineErrorMessage = 'Invoice number already exists. Please choose another number.';
            }
    }
    }

    $invoice->setValue('riv_number', $number);

    if ($invoice->isNewRecord()) {
        // Required (NOT NULL) on many installs; also used for sorting.
        $invoice->setValue('riv_number_index', residentsNextInvoiceNumberIndex());
        $invoice->setValue('riv_org_id', $gCurrentOrgId);
    }

    $invoiceDatePost = (string)($_POST['riv_date'] ?? '');
    $invoice->setValue('riv_date', $invoiceDatePost);
    $invoice->setValue('riv_usr_id', $ownerId);
    $invoice->setValue('riv_start_date', (string)($_POST['riv_start_date'] ?? null));
    $invoice->setValue('riv_end_date', (string)($_POST['riv_end_date'] ?? null));
    $postedDue = (string)($_POST['riv_due_date'] ?? '');
    if ($postedDue === '') {
        $cfgTmp = residentsReadConfig();
        $dueDaysCfg = (int)($cfgTmp['defaults']['due_days'] ?? 15);
        if ($dueDaysCfg <= 0) { $dueDaysCfg = 15; }
        $baseDate = $invoiceDatePost !== '' ? $invoiceDatePost : date('Y-m-d');
        $computedDue = date('Y-m-d', strtotime($baseDate . ' +' . $dueDaysCfg . ' days'));
        $invoice->setValue('riv_due_date', $computedDue);
    } else {
        $invoice->setValue('riv_due_date', $postedDue);
    }
    $invoice->setValue('riv_notes', (string)($_POST['riv_notes'] ?? ''));

    if (!$hasSaveError) {
        try {
            $saveOk = $invoice->save();
    } catch (Exception $e) {
            $saveOk = false;
            $hasSaveError = true;
            $inlineErrorMessage = $gL10n->get('SYS_DATABASE_ERROR') . ': ' . htmlspecialchars($e->getMessage());
    }
    } else {
        $saveOk = false;
    }

    $invoiceId = (int)$invoice->getValue('riv_id');
    // TableAccess::save() returns false if no invoice header fields changed.
    // That is NOT an error for editing items on an existing invoice.
    // Only treat it as an error if we don't have a valid invoice id.
    if ($invoiceId <= 0) {
        $hasSaveError = true;
        if ($inlineErrorMessage === '') {
            $inlineErrorMessage = $gL10n->get('SYS_DATABASE_ERROR');
    }
    }

    $chgIds = $_POST['rii_chg_id'] ?? array();
    $startDates = $_POST['rii_start_date'] ?? array();
    $endDates = $_POST['rii_end_date'] ?? array();
    $types = $_POST['rii_type'] ?? array();
    $currs = $_POST['rii_currency'] ?? array();
    $rates = $_POST['rii_rate'] ?? array();
    $qtys = $_POST['rii_quantity'] ?? array();
    $amts = $_POST['rii_amount'] ?? array();
    $count = max(count($chgIds), count($startDates), count($endDates), count($types), count($currs), count($rates), count($qtys), count($amts));
    $itemRows = array();
    $itemsFromPost = array();

    // Resolve posted charge ids to names (single source of truth is charges table)
    $chargeDefinitionsForSave = residentsFetchChargeDefinitions();
    $chargeNameById = array();
    foreach ($chargeDefinitionsForSave as $cd) {
        $cid = (int)($cd['id'] ?? 0);
        if ($cid > 0) {
            $chargeNameById[$cid] = (string)($cd['name'] ?? '');
    }
    }

    for ($i = 0; $i < $count; $i++) {
        $chargeId = (int)($chgIds[$i] ?? 0);
        $chargeName = $chargeId > 0 ? (string)($chargeNameById[$chargeId] ?? '') : '';
        $itemRows[] = array(
            'charge_id' => $chargeId,
            'name' => $chargeName,
            'start_date' => $startDates[$i] ?? null,
            'end_date' => $endDates[$i] ?? null,
            'type' => $types[$i] ?? '',
            'currency' => $currs[$i] ?? '',
            'rate' => $rates[$i] ?? null,
            'quantity' => $qtys[$i] ?? null,
            'amount' => $amts[$i] ?? null
        );

        $itemsFromPost[] = array(
            'rii_chg_id' => $chargeId,
            'rii_name' => $chargeName,
            'rii_start_date' => $startDates[$i] ?? null,
            'rii_end_date' => $endDates[$i] ?? null,
            'rii_type' => $types[$i] ?? '',
            'rii_currency' => $currs[$i] ?? '',
            'rii_rate' => $rates[$i] ?? null,
            'rii_quantity' => $qtys[$i] ?? null,
            'rii_amount' => $amts[$i] ?? null
        );
    }

    if (!$hasSaveError) {
        try {
            $invoice->replaceItems($itemRows, (int)$gCurrentUser->getValue('usr_id'));
    } catch (Exception $e) {
            $hasSaveError = true;
            $inlineErrorMessage = $gL10n->get('SYS_DATABASE_ERROR') . ': ' . htmlspecialchars($e->getMessage());
    }
    }

    if (!$hasSaveError) {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/detail.php', array('id' => $invoiceId)));
    }
}

// GET: load data
$row = array(
    'riv_id' => (int)$invoice->getValue('riv_id'),
    'riv_number' => (string)$invoice->getValue('riv_number'),
    'riv_date' => (string)$invoice->getValue('riv_date'),
    'riv_usr_id' => (int)($invoice->getValue('riv_usr_id') ?: $gCurrentUser->getValue('usr_id')),
    'riv_start_date' => (string)$invoice->getValue('riv_start_date'),
    'riv_end_date' => (string)$invoice->getValue('riv_end_date'),
    'riv_due_date' => (string)$invoice->getValue('riv_due_date'),
    'riv_notes' => (string)$invoice->getValue('riv_notes')
);
if ($invoice->isNewRecord()) {
    $row['riv_id'] = 0;
    $row['riv_usr_id'] = (int)$gCurrentUser->getValue('usr_id');
}

$items = $invoice->isNewRecord() ? array() : $invoice->getItems();
if ($hasSaveError && is_array($itemsFromPost)) {
    $items = $itemsFromPost;
}

$page = new HtmlPage('bl-residents-edit', $gL10n->get('RES_TITLE'));
$page->setHeadline($gL10n->get('RE_TAB_INVOICES'));
residentsEnqueueStyles($page);

$cfg = residentsReadConfig();
$ownerGroupId = (int)($cfg['owners']['group_id'] ?? 0);
$users = residentsGetOwnerOptions($ownerGroupId);

// For existing invoices, ensure the invoice owner is in the dropdown even if they are now a "Former" user
if (!$invoice->isNewRecord() && !empty($row['riv_usr_id'])) {
    residentsEnsureUserInOptions($users, (int)$row['riv_usr_id']);
}

$ownerDetails = array();
if (!empty($row['riv_usr_id'])) {
    $ownerDetails = residentsGetUserAddress((int)$row['riv_usr_id']);
    if (!empty($ownerDetails['name'])) {
        $ownerDetails['name'] = ucwords((string)$ownerDetails['name']);
    }
}


$isNewInvoice = $invoice->isNewRecord();
$chargeDefinitions = residentsFetchChargeDefinitions();

// Filter charge definitions for the currently selected owner
$chargeDefinitionsForOwner = $chargeDefinitions;
{
    $activeRoleIdsForOwner = array();
    if (!empty($row['riv_usr_id'])) {
        $activeRoleIdsForOwner = residentsGetActiveRoleIdsForUser((int)$row['riv_usr_id']);
    }
    $chargeDefinitionsForOwner = array();
    foreach ($chargeDefinitions as $chargeDef) {
        $chargeRoleIds = $chargeDef['role_ids'] ?? array();
        if (!is_array($chargeRoleIds)) {
            $chargeRoleIds = array();
    }
        if (count($chargeRoleIds) === 0) {
            $chargeDefinitionsForOwner[] = $chargeDef;
            continue;
    }
        if (count($activeRoleIdsForOwner) === 0) {
            continue;
    }
        if (count(array_intersect($chargeRoleIds, $activeRoleIdsForOwner)) > 0) {
            $chargeDefinitionsForOwner[] = $chargeDef;
    }
    }
}

$initialChargesForJs = array();
foreach ($chargeDefinitionsForOwner as $chargeDef) {
    $initialChargesForJs[] = array(
    'id' => (int)($chargeDef['id'] ?? 0),
    'name' => (string)($chargeDef['name'] ?? ''),
    'amount' => (float)($chargeDef['amount'] ?? 0.0),
    'period_months' => (int)($chargeDef['period_months'] ?? 1)
    );
}

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// New invoice: auto-populate one row per available charge (initial page load)
if ($isNewInvoice && !$isPost && is_array($items) && count($items) === 0 && count($chargeDefinitionsForOwner) > 0) {
    $defaultCurrency = $gSettingsManager->getString('system_currency');
    $todayIso = date('Y-m-d');
    $items = array();
    foreach ($chargeDefinitionsForOwner as $chargeDef) {
        $chargeName = (string)($chargeDef['name'] ?? '');
        $chargeId = (int)($chargeDef['id'] ?? 0);
        if ($chargeName === '') {
            continue;
    }
        $chargeAmount = (float)($chargeDef['amount'] ?? 0.0);
        $items[] = array(
            'rii_chg_id' => $chargeId,
            'rii_name' => $chargeName,
            'rii_start_date' => $todayIso,
            'rii_end_date' => '',
            'rii_currency' => $defaultCurrency,
            'rii_rate' => number_format($chargeAmount, 2, '.', ''),
            'rii_quantity' => '1',
            'rii_amount' => number_format($chargeAmount, 2, '.', '')
        );
    }
}

$rowsCount = ($isNewInvoice && !$isPost) ? max(1, count($items)) : (count($items) > 0 ? count($items) : 3);
$currencyLabel = $gSettingsManager->getString('system_currency');
$initialTotal = 0.0;
foreach ($items as $item) {
    $val = preg_replace('/[^0-9.,-]/', '', (string)($item['rii_amount'] ?? '0'));
    $initialTotal += (float)str_replace(',', '', $val);
    if (!empty($item['rii_currency'])) {
        $currencyLabel = (string)$item['rii_currency'];
    }
}
$initialTotalFormatted = number_format($initialTotal, 2, '.', ',');

$formAction = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/edit.php');

ob_start();
?>
<style>
    .re-editor .card {
        border: none;
        border-radius: 0.9rem;
        box-shadow: 0 12px 24px rgba(20, 24, 45, 0.08);
    }

    .re-editor .card+.card {
        margin-top: 1.5rem;
    }

    .re-editor .card-header {
        border-bottom: none;
        background: transparent;
        font-weight: 600;
        letter-spacing: .08em;
        font-size: .75rem;
        text-transform: uppercase;
        color: #6c757d;
    }

    .re-editor .table thead th {
        font-size: .8rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6c757d;
        border-bottom: 1px solid #eef2f7;
    }

    .re-editor .table td {
        vertical-align: middle;
    }

    .re-editor .form-label {
        display: block;
        font-weight: 600;
        letter-spacing: .05em;
    }

    .re-editor .invoice-hero {
        background: #f6f8fb;
        color: #0f172a;
    }

    .re-editor .invoice-hero .form-label {
        color: #111827;
    }

    .re-editor .invoice-hero input,
    .re-editor .invoice-hero select {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.15);
        color: #0f172a;
    }

    .re-editor .invoice-hero select:disabled {
        background: #eef2f7;
        cursor: not-allowed;
    }

    /* Uniform sizing for all visible inputs on this page (header + items) */
    .re-editor input.form-control:not([type="hidden"]),
    .re-editor select.form-control,
    .re-editor select.form-select,
    .re-editor textarea.form-control,
    .re-editor #re-items-table .re-end-date-label {
        font-size: 1rem !important;
        line-height: 1.5 !important;
        padding: 0.5rem 0.75rem !important;
        height: 42px !important;
        box-sizing: border-box !important;
    }

    .re-editor #re-items-table .re-end-date-input {
        /* Editable end date input styling */
    }

    .re-editor .invoice-hero input::placeholder {
        color: rgba(15, 23, 42, 0.45);
    }

    .re-editor .invoice-hero option {
        color: #111;
    }

    .re-editor .invoice-hero .col-md-6 {
        max-width: 25%;
    }

    .re-editor td.amount {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .re-editor .re-amount-input {
        width: 10rem;
        max-width: 100%;
    }
</style>

<form id="re_edit" class="re-editor" method="post" action="<?php echo $formAction; ?>">
    <?php if (!empty($inlineErrorMessage)) : ?>
    <div class="alert alert-danger mb-4" role="alert"><?php echo $inlineErrorMessage; ?></div>
    <?php endif; ?>
    <div class="card invoice-hero mb-4">
    <div class="card-header"><?php echo $gL10n->get('RE_USER'); ?></div>
    <div class="card-body">
            <?php if (!empty($ownerDetails)) : ?>
    <div class="mb-3">
                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string)($ownerDetails['name'] ?? '')); ?></div>
                    <?php if (!empty($ownerDetails['email'])) : ?>
            <div class="small text-muted"><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars((string)$ownerDetails['email']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($ownerDetails['tel'])) : ?>
            <div class="small text-muted"><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars((string)$ownerDetails['tel']); ?></div>
                    <?php endif; ?>
    </div>
            <?php endif; ?>
    </div>
    </div>

    <div class="card invoice-hero mb-4">
    <div class="card-body">
            <div class="row g-4 align-items-center">
    <div class="col-md-6">
                    <label class="form-label text-uppercase text-dark small mb-1"><?php echo $gL10n->get('RE_NUMBER'); ?></label>
                    <input class="form-control form-control-lg" name="riv_number" value="<?php echo htmlspecialchars((string)$row['riv_number']); ?>" maxlength="10" style="width: 50%;" />
    </div>
    <div class="col-md-6">
                    <label class="form-label text-uppercase text-dark small mb-1"><?php echo $gL10n->get('RE_USER'); ?></label>
        <select class="form-control form-control-lg" name="riv_usr_id" id="riv_usr_id" <?php echo $isNewInvoice ? '' : 'disabled'; ?>>
            <?php foreach ($users as $uid => $uname) : ?>
                            <option value="<?php echo (int)$uid; ?>" <?php echo ((int)$row['riv_usr_id'] === (int)$uid ? ' selected' : ''); ?>><?php echo htmlspecialchars((string)$uname); ?></option>
            <?php endforeach; ?>
                    </select>
        <?php if (!$isNewInvoice) : ?>
            <input type="hidden" name="riv_usr_id" value="<?php echo (int)$row['riv_usr_id']; ?>" />
        <?php endif; ?>
    </div>
            </div>
    </div>
    </div>

    <div class="card invoice-hero">
    <div class="card-body">
            <div class="row g-4">
    <div class="col-md-6">
                    <label class="form-label text-uppercase text-dark small mb-1"><?php echo $gL10n->get('RE_DATE'); ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" name="riv_date" value="<?php echo htmlspecialchars(residentsFormatDateForInput((string)($row['riv_date'] ?? ''))); ?>" required />
    </div>
    <div class="col-md-6">
                    <label class="form-label text-uppercase text-dark small mb-1"><?php echo $gL10n->get('RE_START_DATE'); ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" name="riv_start_date" value="<?php echo htmlspecialchars(residentsFormatDateForInput((string)($row['riv_start_date'] ?? ''))); ?>" required />
    </div>
    <div class="col-md-6">
                    <label class="form-label text-uppercase text-dark small mb-1"><?php echo $gL10n->get('RE_END_DATE'); ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" name="riv_end_date" value="<?php echo htmlspecialchars(residentsFormatDateForInput((string)($row['riv_end_date'] ?? ''))); ?>" required />
    </div>
    <div class="col-md-6">
                    <label class="form-label text-uppercase text-dark small mb-1"><?php echo $gL10n->get('RE_DUE_DATE'); ?></label>
                    <input type="date" class="form-control form-control-lg" name="riv_due_date" value="<?php echo htmlspecialchars(residentsFormatDateForInput((string)($row['riv_due_date'] ?? ''))); ?>" />
    </div>
            </div>
    </div>
    </div>

    <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
            <span><?php echo $gL10n->get('RE_INVOICE_ITEMS'); ?></span>
            <button type="button" class="btn btn-primary btn-sm" id="re-add-item"><i class="bi bi-plus-circle"></i> <?php echo $gL10n->get('RE_INVOICE_ITEMS'); ?></button>
    </div>
    <div class="card-body p-0">
            <div class="table-responsive">
    <table class="table mb-0" id="re-items-table">
                    <thead>
            <tr>
                    <th style="width:30%"><?php echo $gL10n->get('SYS_NAME'); ?></th>
                            <th style="width:17%"><?php echo $gL10n->get('RE_START_DATE'); ?></th>
                            <th style="width:17%"><?php echo $gL10n->get('RE_END_DATE'); ?></th>
                            <th class="text-end" style="width:30%"><?php echo $gL10n->get('RE_AMOUNT'); ?></th>
                            <th class="text-center" style="width:6%"></th>
            </tr>
                    </thead>
                    <tbody>
            <?php for ($i = 0; $i < $rowsCount; $i++) :
            $it = $items[$i] ?? array('rii_chg_id' => 0, 'rii_name' => '', 'rii_start_date' => '', 'rii_end_date' => '', 'rii_currency' => '', 'rii_rate' => '', 'rii_quantity' => '', 'rii_amount' => '');
                            $nameId = $i === 0 ? ' id="rii_name_0"' : '';
                            $amountId = $i === 0 ? ' id="rii_amount_0"' : '';
                    $endId = $i === 0 ? ' id="rii_end_date_0"' : '';
                    $endLabelId = $i === 0 ? ' id="rii_end_date_label_0"' : '';
                            $itemCurrency = trim((string)$it['rii_currency']) !== '' ? (string)$it['rii_currency'] : (string)$currencyLabel;
            ?>
                            <tr class="align-middle">
        <td>
                    <select class="form-control form-control-sm re-charge-select" name="rii_chg_id[]" <?php echo $nameId; ?>>
                <?php if (count($chargeDefinitionsForOwner) === 0) : ?>
                    <option value="0" selected></option>
                <?php else : ?>
                <?php foreach ($chargeDefinitionsForOwner as $chargeDef) :
                    $chargeId = (int)($chargeDef['id'] ?? 0);
                    $chargeName = (string)($chargeDef['name'] ?? '');
                    $chargeAmount = (string)number_format((float)($chargeDef['amount'] ?? 0.0), 2, '.', '');
                    $chargeMonths = (int)($chargeDef['period_months'] ?? 1);
                    $selected = ((int)($it['rii_chg_id'] ?? 0) > 0 && (int)($it['rii_chg_id'] ?? 0) === $chargeId) ? ' selected' : '';
        ?>
        <option value="<?php echo (int)$chargeId; ?>" data-name="<?php echo htmlspecialchars($chargeName); ?>" data-period-months="<?php echo (int)$chargeMonths; ?>" data-amount="<?php echo htmlspecialchars($chargeAmount); ?>"<?php echo $selected; ?>><?php echo htmlspecialchars($chargeName); ?></option>
                <?php endforeach; ?>
                <?php endif; ?>
                    </select>
        </td>
                            <td><input type="date" class="form-control form-control-sm" name="rii_start_date[]" value="<?php echo htmlspecialchars(residentsFormatDateForInput((string)($it['rii_start_date'] ?? ''))); ?>" /></td>
                    <td>
                    <input type="date" class="form-control form-control-sm re-end-date-input" name="rii_end_date[]"<?php echo $endId; ?> value="<?php echo htmlspecialchars(residentsFormatDateForInput((string)($it['rii_end_date'] ?? ''))); ?>" />
                    </td>
        <td class="amount">
                                    <span class="re-row-currency"><?php echo htmlspecialchars($itemCurrency); ?></span>
                                    <input type="hidden" name="rii_currency[]" value="<?php echo htmlspecialchars((string)$itemCurrency); ?>" />
                                    <input type="hidden" name="rii_quantity[]" value="<?php echo htmlspecialchars((string)$it['rii_quantity']); ?>" />
                                    <input type="hidden" name="rii_rate[]" value="<?php echo htmlspecialchars((string)$it['rii_rate']); ?>" />
                                    <input class="form-control form-control-sm text-end re-amount-input" name="rii_amount[]" <?php echo $amountId; ?> value="<?php echo htmlspecialchars((string)$it['rii_amount']); ?>" />
        </td>
        <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger text-decoration-none re-remove-row" title="<?php echo htmlspecialchars($gL10n->get('SYS_DELETE')); ?>">
                    <i class="bi bi-x"></i>
                                    </button>
        </td>
                            </tr>
            <?php endfor; ?>
                    </tbody>
    </table>
            </div>
    </div>
    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap">
            <div class="text-muted small"><?php echo htmlspecialchars($gL10n->get('RE_ADD_ROWS_HELP')); ?></div>
            <div class="ms-auto text-end">
    <div class="text-muted small"><?php echo htmlspecialchars($gL10n->get('RE_ESTIMATED_TOTAL')); ?></div>
    <div class="fs-4 fw-semibold"><span id="re-total-currency"><?php echo htmlspecialchars((string)$currencyLabel); ?></span> <span id="re-total-value"><?php echo htmlspecialchars($initialTotalFormatted); ?></span></div>
            </div>
    </div>
    </div>

    <div class="card mt-4">
    <div class="card-header"><?php echo $gL10n->get('RE_NOTES'); ?></div>
    <div class="card-body">
            <textarea class="form-control" name="riv_notes" rows="6" style="min-height: 140px;" placeholder="Add note for this invoice..."><?php echo htmlspecialchars((string)$row['riv_notes']); ?></textarea>
    </div>
    </div>

    <template id="re-row-template">
    <tr class="align-middle">
            <td>
        <select class="form-control form-control-sm re-charge-select" name="rii_chg_id[]">
                    <?php if (count($chargeDefinitionsForOwner) === 0) : ?>
            <option value="0" selected></option>
                    <?php else : ?>
                    <?php foreach ($chargeDefinitionsForOwner as $chargeDef) :
                                    $chargeId = (int)($chargeDef['id'] ?? 0);
                                    $chargeName = (string)($chargeDef['name'] ?? '');
                                    $chargeAmount = (string)number_format((float)($chargeDef['amount'] ?? 0.0), 2, '.', '');
                                    $chargeMonths = (int)($chargeDef['period_months'] ?? 1);
                    ?>
                    <option value="<?php echo (int)$chargeId; ?>" data-name="<?php echo htmlspecialchars($chargeName); ?>" data-period-months="<?php echo (int)$chargeMonths; ?>" data-amount="<?php echo htmlspecialchars($chargeAmount); ?>"><?php echo htmlspecialchars($chargeName); ?></option>
                    <?php endforeach; ?>
                    <?php endif; ?>
        </select>
            </td>
            <td><input type="date" class="form-control form-control-sm" name="rii_start_date[]" /></td>
            <td>
        <input type="date" class="form-control form-control-sm re-end-date-input" name="rii_end_date[]" />
            </td>
            <td class="amount">
    <span class="re-row-currency"><?php echo htmlspecialchars((string)$currencyLabel); ?></span>
    <input type="hidden" name="rii_currency[]" value="<?php echo htmlspecialchars((string)$currencyLabel); ?>" />
    <input type="hidden" name="rii_quantity[]" />
    <input type="hidden" name="rii_rate[]" />
    <input class="form-control form-control-sm text-end re-amount-input" name="rii_amount[]" />
            </td>
            <td class="text-center">
    <button type="button" class="btn btn-link text-danger text-decoration-none re-remove-row" title="<?php echo htmlspecialchars($gL10n->get('SYS_DELETE')); ?>">
                    <i class="bi bi-x"></i>
    </button>
            </td>
    </tr>
    </template>

    <input type="hidden" name="riv_id" value="<?php echo (int)$row['riv_id']; ?>" />
    <input type="hidden" name="admidio-csrf-token" value="<?php echo htmlspecialchars($gCurrentSession->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
    <div class="d-flex justify-content-end mt-4" style="gap:0.5rem;">
    <a class="btn btn-secondary" href="<?php echo SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'invoices')); ?>"><?php echo $gL10n->get('SYS_CANCEL'); ?></a>
    <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-floppy me-2"></i><?php echo $gL10n->get('SYS_SAVE'); ?>
    </button>
    </div>
</form>
<?php
$page->addHtml(ob_get_clean());

$ownerChargeEndpoint = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/edit.php');
$ownerChargeEndpointJs = addslashes($ownerChargeEndpoint);

$script = <<<'JS'
(function($) {
    $(function() {
      var isNewInvoice = {{isNewInvoice}};
      var $itemsTable = $("#re-items-table");
      var $itemsBody = $itemsTable.length ? $itemsTable.find("tbody") : $();
      var $templateEl = $("#re-row-template");
      var templateMarkup = $templateEl.length ? $.trim($templateEl.html()) : "";
      var $addBtn = $("#re-add-item");
      var $totalValueEl = $("#re-total-value");
      var $totalCurrencyEl = $("#re-total-currency");
      var currentCurrencyLabel = $totalCurrencyEl.length ? $.trim($totalCurrencyEl.text()) : "";
      var $ownerSel = $("#riv_usr_id");
      var currentCharges = {{initialCharges}};

      function clearRowValues($row) {
        if (!$row || !$row.length) { return; }
        var $start = $row.find('input[name="rii_start_date[]"]');
        if ($start.length) { $start.val(""); }
        var $amount = $row.find('.re-amount-input');
        if ($amount.length) { $amount.val(""); }
        var $end = $row.find('.re-end-date-input');
        if ($end.length) { $end.val(""); }
    }

      function buildOptions($select, keepValue) {
        if (!$select || !$select.length) { return { changed: false, value: keepValue }; }
        var previous = keepValue;
        $select.empty();
        if (!currentCharges || !currentCharges.length) {
          $select.append($("<option>", { value: "", text: "", selected: true }));
          return { changed: previous ? true : false, value: "" };
      }

        var found = false;
        for (var i = 0; i < currentCharges.length; i++) {
          var c = currentCharges[i] || {};
          var id = parseInt(c.id, 10);
          if (!id || id < 1) { continue; }
          var name = String(c.name || "");
          var amt = isFinite(parseFloat(c.amount)) ? parseFloat(c.amount) : 0;
          var months = parseInt(c.period_months, 10);
          if (!months || months < 1) { months = 1; }
          var $opt = $("<option>", { value: String(id), text: name });
          $opt.attr("data-amount", amt.toFixed(2));
          $opt.attr("data-period-months", months);
          $opt.attr("data-name", name);
          $select.append($opt);
          if (previous && String(id) === String(previous)) { found = true; }
      }

        if (previous && found) {
          $select.val(previous);
      } else {
          var firstValue = $select.find('option').first().val();
          $select.val(firstValue);
      }
        var newValue = $select.val();
        var changed = String(previous || '') !== String(newValue || '');
        return { changed: changed, value: newValue };
    }

      function refreshChargeSelects() {
        if (!isNewInvoice || !$itemsBody.length) { return; }
        var hasCharges = !!(currentCharges && currentCharges.length);
        $itemsBody.find('tr').each(function() {
          var $row = $(this);
          var $select = $row.find('.re-charge-select');
          if (!$select.length) { return; }
          var desired = $select.data('desiredCharge');
          var prev = (desired !== undefined && desired !== null && String(desired) !== '') ? String(desired) : $select.val();
          var result = buildOptions($select, prev);
          $select.removeData('desiredCharge');
          if (!hasCharges) {
          clearRowValues($row);
          return;
      }

          // Auto-load start/end/amount when fields are blank (common when switching from a user with no charges)
          var $startInput = $row.find('input[name="rii_start_date[]"]');
          if ($startInput.length && !$startInput.val()) {
          $startInput.val(currentMonthStartIso());
      }
          var $amountInput = $row.find('.re-amount-input');
          if ($amountInput.length && $.trim($amountInput.val()) === '') {
          var $opt = $select.find('option:selected');
          var optAmount = $opt.length ? $opt.data('amount') : null;
          if (optAmount !== null && optAmount !== undefined && optAmount !== '') {
            $amountInput.val(formatCurrency(optAmount));
        }
      }

          updateEndDateForRow($row);
      });
        updateTotals();
    }

      function isoDateFromDate(d) {
        var year = d.getFullYear();
        var month = String(d.getMonth() + 1).padStart(2, "0");
        var day = String(d.getDate()).padStart(2, "0");
        return year + "-" + month + "-" + day;
    }

      function formatDateForDisplay(isoString) {
        if (!isoString) return "";
        var parts = isoString.split("-");
        if (parts.length !== 3) return isoString;
        return parts[2] + "-" + parts[1] + "-" + parts[0];
    }

      function currentMonthStartIso() {
        var d = new Date();
        d.setHours(0, 0, 0, 0);
        d.setDate(1);
        return isoDateFromDate(d);
    }

      function computeEndDate(startIso, months) {
        if (!startIso) { return ""; }
        var start = new Date(String(startIso) + "T00:00:00");
        if (!isFinite(start.getTime())) { return ""; }
        var m = parseInt(months, 10);
        if (!m || m < 1) {
          return isoDateFromDate(start);
      }
        var end = new Date(start);
        end.setMonth(end.getMonth() + m);
        end.setDate(end.getDate() - 1);
        return isoDateFromDate(end);
    }

      function updateEndDateForRow($row, forceUpdate) {
        if (!$row || !$row.length) { return; }
        var $endInput = $row.find('.re-end-date-input');
        if (!$endInput.length) { return; }

        // If end date already has a value and we're not forcing update, don't overwrite user's manual entry
        if (!forceUpdate && $endInput.val()) { return; }

        var startIso = $row.find('input[name="rii_start_date[]"]').val();
        if (!startIso) {
          $endInput.val("");
          return;
      }

        var months = 0;
        var $select = $row.find('.re-charge-select');
        if ($select.length) {
          var $opt = $select.find('option:selected');
          if ($opt.length) {
            months = $opt.data('period-months');
        }
      }
        var endIso = computeEndDate(startIso, months);
        $endInput.val(endIso);
    }

      function applyDefaultsForRow($row) {
        if (!$row || !$row.length) { return; }

        var $select = $row.find('.re-charge-select');
        var hasCharges = !!(currentCharges && currentCharges.length);
        if ($select.length && (!hasCharges || !$.trim($select.val() || ''))) {
          clearRowValues($row);
          updateTotals();
          return;
      }
        if ($select.length && !$select.val()) {
          var $firstValueOpt = $select.find('option').filter(function() {
            return $.trim($(this).attr('value') || '') !== '';
        }).first();
          if ($firstValueOpt.length) {
            $select.val($firstValueOpt.val());
        }
      }

        var $startInput = $row.find('input[name="rii_start_date[]"]');
        if ($startInput.length && !$startInput.val()) {
          $startInput.val(currentMonthStartIso());
      }

        var $amountInput = $row.find('.re-amount-input');
        if ($amountInput.length && $.trim($amountInput.val()) === '' && $select.length) {
          var $opt = $select.find('option:selected');
          var optAmount = $opt.length ? $opt.data('amount') : null;
          if (optAmount !== null && optAmount !== undefined && optAmount !== '') {
            $amountInput.val(formatCurrency(optAmount));
        }
      }

        updateEndDateForRow($row);
        updateTotals();
    }

      function createRow() {
        if ($templateEl.length && $templateEl[0].content && $templateEl[0].content.firstElementChild) {
          return $($templateEl[0].content.firstElementChild.cloneNode(true));
      }
        if (templateMarkup) {
          return $(templateMarkup).first();
      }
        return null;
    }

      function parseAmount(value) {
        if (!value) { return 0; }
        var normalized = String(value).replace(/[^0-9.,-]/g, "").replace(/,/g, "");
        var num = parseFloat(normalized);
        return isNaN(num) ? 0 : num;
    }

      function formatCurrency(value) {
        var num = parseFloat(value);
        if (!isFinite(num)) { return ""; }
        return num.toFixed(2);
    }

      function formatQuantity(value) {
        var num = parseFloat(value);
        if (!isFinite(num) || num <= 0) { return ""; }
        var rounded = Math.round(num * 10000) / 10000;
        var str = rounded.toFixed(4);
        str = str.replace(/0+$/, '').replace(/\.$/, '');
        return str;
    }

      function updateTotals() {
        if (!$itemsBody.length || !$totalValueEl.length) { return; }
        var total = 0;
        $itemsBody.find(".re-amount-input").each(function() {
          total += parseAmount($(this).val());
      });
        $totalValueEl.text(total.toFixed(2));
    }

      function syncCurrency($row, currencyText) {
        if (!$row || !$row.length || !currencyText) { return; }
        var $currencySpan = $row.find(".re-row-currency");
        if ($currencySpan.length) {
          $currencySpan.text(currencyText);
      }
        var $currencyInput = $row.find('input[name="rii_currency[]"]');
        if ($currencyInput.length) {
          $currencyInput.val(currencyText);
      }
    }

      function addRow($row) {
        if (!$row || !$row.length) { return; }
        $row.find(".re-amount-input").each(function() {
          $(this).on("input", updateTotals);
      });

        var $removeBtn = $row.find(".re-remove-row");
        if ($removeBtn.length) {
          $removeBtn.on("click", function() {
            if (!$itemsBody.length) { return; }
            var $rows = $itemsBody.find("tr");
            if ($rows.length <= 1) {
              $row.find("input, select").each(function() {
                $(this).val("");
            });
              if (currentCurrencyLabel) {
                syncCurrency($row, currentCurrencyLabel);
            }
              updateEndDateForRow($row);
              updateTotals();
              return;
          }
            $row.remove();
            updateTotals();
        });
      }

        updateEndDateForRow($row);
        applyDefaultsForRow($row);
    }

      function rebuildRowsForCharges() {
        if (!isNewInvoice || !$itemsBody.length) { return; }

        var hasCharges = !!(currentCharges && currentCharges.length);
        var desiredCount = hasCharges ? currentCharges.length : 1;
        $itemsBody.empty();

        for (var i = 0; i < desiredCount; i++) {
          var $row = createRow();
          if (!$row || !$row.length) { continue; }
          if (currentCurrencyLabel) {
            syncCurrency($row, currentCurrencyLabel);
        }
          $itemsBody.append($row);
          addRow($row);
      }

        if (hasCharges) {
          $itemsBody.find('tr').each(function(idx) {
            var c = currentCharges[idx] || {};
            var chargeId = parseInt(c.id, 10);
            var chargeAmount = isFinite(parseFloat(c.amount)) ? parseFloat(c.amount) : 0;
            var $row = $(this);
            var $select = $row.find('.re-charge-select');
            if ($select.length) {
              // Will be applied after options are rebuilt
              $select.data('desiredCharge', chargeId);
          }
            var $startInput = $row.find('input[name="rii_start_date[]"]');
            if ($startInput.length) {
              $startInput.val(currentMonthStartIso());
          }
            var $amountInput = $row.find('.re-amount-input');
            if ($amountInput.length) {
              $amountInput.val(formatCurrency(chargeAmount));
          }
        });
      }

        refreshChargeSelects();
        updateTotals();
    }

      // Delegated handlers (covers initial rows + dynamically added rows)
      if ($itemsBody.length) {
        $itemsBody.on('change', '.re-charge-select', function() {
          var $row = $(this).closest('tr');
          var $amountInput = $row.find('.re-amount-input');
          var $startInput = $row.find('input[name="rii_start_date[]"]');

          if ($startInput.length && !$startInput.val()) {
            $startInput.val(currentMonthStartIso());
        }

          // Always update amount when user changes the charge dropdown selection
          if ($amountInput.length) {
            var $opt = $(this).find('option:selected');
            var optAmount = $opt.length ? $opt.data('amount') : null;
            if (optAmount !== null && optAmount !== undefined && optAmount !== '') {
              $amountInput.val(formatCurrency(optAmount));
          }
        }
          // Force update end date when charge selection changes (period may differ)
          updateEndDateForRow($row, true);
          updateTotals();
      });

        $itemsBody.on('change input', 'input[name="rii_start_date[]"]', function() {
          // Force update end date when start date changes
          updateEndDateForRow($(this).closest('tr'), true);
      });
    }

      function fetchOwnerCharge(ownerId) {
        var numericId = parseInt(ownerId, 10);
        if (!numericId) { return; }
        $.ajax({
          url: "{{ownerChargeEndpoint}}",
          method: "GET",
          dataType: "json",
          data: {
            ajax: "owner_charge",
            owner_id: numericId
        },
          xhrFields: { withCredentials: true }
      }).done(function(data) {
          if (!data) { return; }
          if (data.currency) {
          currentCurrencyLabel = data.currency;
          if ($totalCurrencyEl.length) {
            $totalCurrencyEl.text(data.currency);
        }
      }
          currentCharges = (data.charges && $.isArray(data.charges)) ? data.charges : [];
          // New invoice: auto-load one row per available charge for the selected owner.
          rebuildRowsForCharges();
      }).fail(function() {
          // ignore errors to keep form usable
      });
    }

      if ($ownerSel.length) {
        $ownerSel.on("change", function() {
          fetchOwnerCharge($(this).val());
      });
        if ($ownerSel.val()) {
          fetchOwnerCharge($ownerSel.val());
      }
    }

      if ($itemsBody.length) {
        $itemsBody.find("tr").each(function() {
          addRow($(this));
      });
        if (isNewInvoice) {
          refreshChargeSelects();
      }
    }

      if ($addBtn.length && $itemsBody.length && ($templateEl.length || templateMarkup)) {
        $addBtn.on("click", function(e) {
          e.preventDefault();
          var $clone = createRow();
          if (!$clone || !$clone.length) { return; }
          if (currentCurrencyLabel) {
            syncCurrency($clone, currentCurrencyLabel);
        }
          $itemsBody.append($clone);
          if (isNewInvoice) {
            // ensure new rows always use current filtered charge list
            refreshChargeSelects();
        }
          addRow($clone);
          applyDefaultsForRow($clone);
          var focusEl = $clone.find('select, input').get(0);
          if (focusEl) { focusEl.focus(); }
      });
    }

      // Form submit validation - ensure all amounts are greater than zero
      var $form = $itemsBody.length ? $itemsBody.closest('form') : $();
      if ($form.length) {
        $form.on('submit', function(e) {
          var hasError = false;
          var errorMessage = '{{amountPositiveMsg}}';
          $itemsBody.find('.re-amount-input').each(function() {
            var val = $.trim($(this).val());
            if (val !== '') {
              var num = parseAmount(val);
              if (num <= 0) {
                hasError = true;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
          }
        });
          if (hasError) {
            e.preventDefault();
            alert(errorMessage);
            return false;
        }
          return true;
      });
    }

      updateTotals();
  });
})(jQuery);
JS;

$amountPositiveMsg = addslashes($gL10n->get('RE_VALIDATION_AMOUNT_POSITIVE'));
$script = strtr($script, array(
    '{{ownerChargeEndpoint}}' => $ownerChargeEndpointJs,
    '{{groupChargeLabel}}' => '',
    '{{isNewInvoice}}' => $isNewInvoice ? 'true' : 'false',
    '{{initialCharges}}' => json_encode($initialChargesForJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    '{{amountPositiveMsg}}' => $amountPositiveMsg,
));

$page->addJavascript($script, true);
$page->addHtml('<div style="height: 50px;"></div>');
$page->show();
