<?php
/**
 ***********************************************************************************************
 * Create/Edit manual payment.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gProfileFields, $gCurrentUser, $gL10n, $gSettingsManager;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$isPaymentAdmin = isPaymentAdmin();
$isResidentsAdmin = isResidentsAdminBySettings();
if (!$isPaymentAdmin && !$isResidentsAdmin) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');

if ($id <= 0 && !$isResidentsAdmin) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        SecurityUtils::validateCsrfToken((string)($_POST['admidio-csrf-token'] ?? ''));
    } catch (Throwable $e) {
        $gMessage->show($e->getMessage());
    }

    $id = (int)($_POST['rpa_id'] ?? 0);
    $ownerId = (int)($_POST['rpa_usr_id'] ?? 0);
    $date = date('Y-m-d H:i:s', strtotime(($_POST['rpa_date'] ?? date('Y-m-d')) . ' ' . date('H:i:s')));
    $method = trim((string)($_POST['rpa_pg_pay_method'] ?? ''));
    $type = trim((string)($_POST['rpa_pay_type'] ?? ''));
    $transId = trim((string)($_POST['rtr_pg_id'] ?? ''));
    $bankRef = trim((string)($_POST['rtr_bank_ref_no'] ?? ''));

    $invoiceInputs = $_POST['rpi_inv_id'] ?? array();
    if (!is_array($invoiceInputs)) {
        $invoiceInputs = array();
    }

    $normalizedItems = array();
    $mainCurrency = $gSettingsManager->getString('system_currency');
    $processedInvoices = array();

    $processedInvoices = array();

    // Only validate invoices and build items for NEW payments.
    // Existing payments have their items locked to prevent "already paid" errors and maintain integrity.
    if ($id === 0) {
        foreach ($invoiceInputs as $invoiceInput) {
            $invoiceId = is_numeric($invoiceInput) ? (int)$invoiceInput : 0;
            if ($invoiceId <= 0 || isset($processedInvoices[$invoiceId])) {
                continue;
            }

            $processedInvoices[$invoiceId] = true;
            $invoice = new TableResidentsInvoice($gDb, $invoiceId);
            if ($invoice->isNewRecord()) {
                $gMessage->show($gL10n->get('RE_VALIDATION_INVOICE_REQUIRED'));
            }

            if ((int)$invoice->getValue('riv_usr_id') !== $ownerId) {
                $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            }

            $isPaidInvoice = (int)$invoice->getValue('riv_is_paid') === 1;
            if ($isPaidInvoice) {
                $gMessage->show($gL10n->get('RE_INVOICE_ALREADY_PAID'));
            }

            $invoiceItems = $invoice->getItems();
            $amountTotal = 0.0;
            $currency = '';
            foreach ($invoiceItems as $invoiceItem) {
                $amountTotal += (float)$invoiceItem['rii_amount'];
                $itemCurrency = trim((string)($invoiceItem['rii_currency'] ?? ''));
                if ($currency === '' && $itemCurrency !== '') {
                    $currency = $itemCurrency;
    }
            }

            if ($amountTotal == 0.0) {
                continue;
            }

            if ($currency === '') {
                $currency = $mainCurrency;
            } else {
                $mainCurrency = $currency;
            }

            $normalizedItems[] = array(
        'amount' => number_format($amountTotal, 2, '.', ''),
        'currency' => $currency,
        'invoice_id' => $invoiceId
            );
    }
    }

    if ($ownerId <= 0 || ($id === 0 && count($normalizedItems) === 0)) {
        $gMessage->show($gL10n->get('RE_VALIDATION_OWNER_AND_ITEM'));
    }

    $currentUserId = (int)$gCurrentUser->getValue('usr_id');
    $currentOrgId = isset($gCurrentOrgId) ? (int)$gCurrentOrgId : (isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0);

    try {
            $payment = new TableResidentsPayment($gDb, $id);
            if ($id > 0 && $payment->isNewRecord()) {
                $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
            }

            if ($payment->isNewRecord()) {
                $payment->setValue('rpa_usr_id', $ownerId);
            }
            if ($currentOrgId > 0) {
                $payment->setValue('rpa_org_id', $currentOrgId);
            }
            $payment->setValue('rpa_date', $date);
            $payment->setValue('rpa_pg_pay_method', $method);
            $payment->setValue('rpa_pay_type', $type);
            $payment->setValue('rpa_trans_id', $transId);
            $payment->setValue('rpa_bank_ref_no', $bankRef);

            if ($payment->isNewRecord()) {
                $payment->setValue('rpa_status', 'SU');
                $payment->setValue('rpa_usr_id_create', $currentUserId);
                $payment->setValue('rpa_timestamp_create', date('Y-m-d H:i:s'));
            } else {
                $payment->setValue('rpa_usr_id_change', $currentUserId);
                $payment->setValue('rpa_timestamp_change', date('Y-m-d H:i:s'));
            }

        $isNewPayment = $payment->isNewRecord();
        $saveOk = $payment->save();
        if ($isNewPayment && !$saveOk) {
                    throw new Exception('Failed to save payment record.');
        }
            $paymentId = (int)$payment->getValue('rpa_id');

            // Only manage items and invoice status for NEW payments
            if ($id === 0) {
                $payment->replaceItems($normalizedItems, $currentUserId);

                foreach ($normalizedItems as $item) {
                    $invoice = new TableResidentsInvoice($gDb, (int)$item['invoice_id']);
                    if ($invoice->isNewRecord()) {
                        continue;
                    }
                    $invoice->setValue('riv_is_paid', 1);
                    $invoice->setValue('riv_usr_id_change', $currentUserId);
                    $invoice->setValue('riv_timestamp_change', date('Y-m-d H:i:s'));
                    if (!$invoice->save()) {
                        throw new Exception('Failed to update invoice paid status.');
                    }
    }
            }

            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/view.php', array('id' => $paymentId)));
    } catch (Exception $e) {
            $gMessage->show('Error saving payment: ' . $e->getMessage());
    }
}

// Load data if ID provided
$paymentData = array(
    'rpa_id' => 0,
    'rpa_usr_id' => 0,
    'rpa_date' => date('Y-m-d'),
    'rpa_pg_pay_method' => '',
    'rpa_pay_type' => 'Offline',
    'rpa_trans_id' => '',
    'rpa_bank_ref_no' => ''
);
$items = array();
$totalAmount = 0.0;

if ($id > 0) {
    $paymentRecord = new TableResidentsPayment($gDb, $id);
    if ($paymentRecord->isNewRecord()) {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // Organization check: payment must belong to current organization
    residentsValidateOrganization($paymentRecord, 'rpa_org_id');

    $paymentData = array(
    'rpa_id' => (int)$paymentRecord->getValue('rpa_id'),
    'rpa_usr_id' => (int)$paymentRecord->getValue('rpa_usr_id'),
    'rpa_date' => (string)$paymentRecord->getValue('rpa_date', 'Y-m-d'),
    'rpa_pg_pay_method' => (string)$paymentRecord->getValue('rpa_pg_pay_method'),
    'rpa_pay_type' => (string)$paymentRecord->getValue('rpa_pay_type'),
    'rpa_trans_id' => (string)$paymentRecord->getValue('rpa_trans_id'),
    'rpa_bank_ref_no' => (string)$paymentRecord->getValue('rpa_bank_ref_no')
    );

    $items = $paymentRecord->getItems(true);
    foreach ($items as $item) {
        $totalAmount += (float)$item['rpi_amount'];
    }

    // Restriction: Only 'Offline' payments can be edited
    if ($paymentRecord->getValue('rpa_pay_type') !== 'Offline') {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

$page = new HtmlPage('plg-residents', $id > 0 ? $gL10n->get('RE_EDIT_PAYMENT') : $gL10n->get('RE_ADD_PAYMENT'));
$page->setHeadline($gL10n->get('RE_TAB_PAYMENTS'));
residentsEnqueueStyles($page);

$cfg = residentsReadConfig();
$ownerGroupId = (int)($cfg['owners']['group_id'] ?? 0);
$users = residentsGetOwnerOptions($ownerGroupId);

// For existing payments, ensure the payment owner is in the dropdown even if they are now a "Former" user
if ($id > 0 && !empty($paymentData['rpa_usr_id'])) {
    residentsEnsureUserInOptions($users, (int)$paymentData['rpa_usr_id']);
}

$systemCurrency = $gSettingsManager->getString('system_currency');

// If new payment, total is 0 initially
if ($id <= 0) {
    $totalAmount = 0.0;
}

$formAction = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/edit.php');

ob_start();
?>
<style>
    .re-editor .card { border: none; border-radius: 0.9rem; box-shadow: 0 12px 24px rgba(20, 24, 45, 0.08); }
    .re-editor .card+.card { margin-top: 1.5rem; }
    .re-editor .card-header { border-bottom: none; background: transparent; font-weight: 600; letter-spacing: .08em; font-size: .75rem; text-transform: uppercase; color: #6c757d; }
    .re-editor .form-label { display: block; font-weight: 600; letter-spacing: .05em; }
    .re-editor .invoice-hero { background: #f6f8fb; color: #0f172a; }
    .re-editor .invoice-hero input, .re-editor .invoice-hero select { background: #fff; border: 1px solid rgba(15, 23, 42, 0.15); color: #0f172a; }
    .re-editor .form-control, .re-editor .form-select { height: 3rem; }
</style>

<form id="re_payment_edit" class="re-editor" method="post" action="<?php echo $formAction; ?>">
    <input type="hidden" name="rpa_id" value="<?php echo (int)$paymentData['rpa_id']; ?>" />
    <input type="hidden" name="admidio-csrf-token" value="<?php echo htmlspecialchars($gCurrentSession->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
    
    <div class="d-flex justify-content-end mb-3">
    <div class="text-end small"><span class="text-danger">*</span> <?php echo $gL10n->get('SYS_REQUIRED_INPUT'); ?></div>
    </div>

    <!-- Payment Details Section -->
    <div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('RE_PAYMENT_DETAILS'); ?></div>
    <div class="card-body">
            <div class="row g-3 mb-3">
    <div class="col-md-4">
                    <label class="form-label fw-bold"><?php echo $gL10n->get('RE_USER'); ?> <span class="text-danger">*</span></label>
                    <?php if ($id > 0): ?>
                            <input type="hidden" name="rpa_usr_id" value="<?php echo (int)$paymentData['rpa_usr_id']; ?>" />
                            <input class="form-control" value="<?php echo htmlspecialchars($users[$paymentData['rpa_usr_id']] ?? ''); ?>" disabled />
                    <?php else: ?>
                    <select class="form-select" name="rpa_usr_id" required>
            <option value=""><?php echo $gL10n->get('RE_USER'); ?>...</option>
            <?php foreach ($users as $uid => $uname) : ?>
                            <option value="<?php echo (int)$uid; ?>" <?php echo ((int)$paymentData['rpa_usr_id'] === (int)$uid ? ' selected' : ''); ?>><?php echo htmlspecialchars((string)$uname); ?></option>
            <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
    </div>
    <div class="col-md-8">
                    <label class="form-label fw-bold"><?php echo $gL10n->get('RE_PAYMENT_DATE'); ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="rpa_date" value="<?php echo htmlspecialchars((string)$paymentData['rpa_date']); ?>" required />
    </div>
            </div>
            <div class="row g-3">
    <div class="col-md-4">
                    <label class="form-label fw-bold"><?php echo $gL10n->get('RE_PAYMENT_TYPE'); ?></label>
                    <?php
            $payTypeInternal = $paymentData['rpa_pay_type'] ?? 'Offline';
            if ($payTypeInternal === '') { $payTypeInternal = 'Offline'; }
            $payTypeDisplay = ($payTypeInternal === 'Online') ? $gL10n->get('RE_PAYMENT_TYPE_ONLINE') : $gL10n->get('RE_PAYMENT_TYPE_OFFLINE');
                    ?>
                    <input class="form-control-plaintext fw-bold" value="<?php echo htmlspecialchars($payTypeDisplay); ?>" readonly />
                    <input type="hidden" name="rpa_pay_type" value="<?php echo htmlspecialchars($payTypeInternal); ?>" />
    </div>
    <div class="col-md-8">
                    <label class="form-label fw-bold"><?php echo $gL10n->get('RE_PAYMENT_METHOD'); ?></label>
            <input class="form-control" name="rpa_pg_pay_method" value="<?php echo htmlspecialchars((string)$paymentData['rpa_pg_pay_method']); ?>" placeholder="<?php echo htmlspecialchars($gL10n->get('RE_PAY_METHOD_EXAMPLE')); ?>" />
    </div>
            </div>
    </div>
    </div>

    <!-- Transaction Details Section -->
    <div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('RE_TRANSACTION_DETAILS'); ?></div>
    <div class="card-body">
            <div class="row g-3">
    <div class="col-md-6">
                    <label class="form-label fw-bold"><?php echo $gL10n->get('RE_TRANSACTION_ID'); ?></label>
                    <input class="form-control" name="rtr_pg_id" value="<?php echo htmlspecialchars((string)($paymentData['rpa_trans_id'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($gL10n->get('RE_OPTIONAL')); ?>" />
    </div>
    <div class="col-md-6">
                    <label class="form-label fw-bold"><?php echo $gL10n->get('RE_BANK_REF_NO'); ?></label>
                    <input class="form-control" name="rtr_bank_ref_no" value="<?php echo htmlspecialchars((string)($paymentData['rpa_bank_ref_no'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($gL10n->get('RE_OPTIONAL')); ?>" />
    </div>
            </div>
    </div>
    </div>

    <!-- Payment Items Section -->
    <div class="card bg-light mb-4">
    <div class="card-header d-flex justify-content-between align-items-center fw-bold">
            <span><?php echo $gL10n->get('RE_PAYMENT_ITEMS'); ?></span>
    </div>
    <div class="card-body p-0">
            <div class="table-responsive">
    <table class="table mb-0" id="re-items-table">
                    <thead>
            <tr>
                            <th style="width:5%"></th>
                            <th style="width:25%"><?php echo $gL10n->get('RE_NUMBER'); ?> <span class="text-danger">*</span></th>
                            <th class="text-center" style="width:55%"><?php echo $gL10n->get('RE_AMOUNT'); ?> <span class="text-danger">*</span></th>
                            <th class="text-center" style="width:10%"></th>
            </tr>
                    </thead>
                    <tbody>
            <?php 
            if (count($items) > 0) {
                foreach ($items as $item) {
                    ?>
                    <tr class="align-middle">
                                            <td class="text-center align-middle"><div class="d-flex align-items-center justify-content-center" style="min-height: 38px;"><input type="checkbox" class="form-check-input re-item-checkbox m-0" checked disabled /></div></td>
                                            <td>
                                                    <input type="hidden" name="rpi_inv_id[]" value="<?php echo htmlspecialchars((string)$item['rpi_inv_id']); ?>" />
                                                    <input class="form-control" value="<?php echo htmlspecialchars((string)$item['riv_number']); ?>" readonly />
                                            </td>
                                            <td>
            <div class="input-group">
                                                    <input class="form-control" name="rpi_currency[]" value="<?php echo htmlspecialchars((string)$item['rpi_currency']); ?>" style="max-width: 80px;" readonly />
                                                    <input class="form-control" name="rpi_amount[]" value="<?php echo htmlspecialchars((string)$item['rpi_amount']); ?>" readonly />
            </div>
                                            </td>
                                            <td class="text-center"></td>
                    </tr>
                    <?php
        }
            }
            ?>
                    </tbody>
    </table>
            </div>
    </div>
    </div>

    <div class="d-flex justify-content-end align-items-center mt-3" style="gap: 20px;">
            <div>
                    <strong><?php echo $gL10n->get('RE_PAYMENT_TOTAL'); ?>: <span id="re-total-display"><?php echo htmlspecialchars($systemCurrency) . ' ' . number_format($totalAmount, 2, '.', ''); ?></span></strong>
            </div>
    </div>

    <div class="d-flex justify-content-end mt-4" style="gap:0.5rem;">
    <a class="btn btn-secondary" href="<?php echo SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'payments')); ?>"><?php echo $gL10n->get('SYS_CANCEL'); ?></a>
    <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-floppy me-2"></i><?php echo $gL10n->get('SYS_SAVE'); ?>
    </button>
    </div>
</form>

<script>
(function($) {
    $(function() {
        var $table = $("#re-items-table tbody");
        var $totalDisplay = $("#re-total-display");
        var systemCurrency = "<?php echo htmlspecialchars($systemCurrency); ?>";

        function updateTotal() {
        var total = 0.0;
        var currency = systemCurrency;
    
        $table.find("tr").each(function() {
            var $row = $(this);
            // Only count if checkbox is checked
            if ($row.find(".re-item-checkbox").length > 0 && !$row.find(".re-item-checkbox").is(":checked")) {
                return;
            }

            var amountStr = $row.find("input[name='rpi_amount[]']").val();
            var currStr = $row.find("input[name='rpi_currency[]']").val();
            
            if (currStr && currStr.trim() !== "") {
                currency = currStr;
            }
            
            if (amountStr) {
                var val = parseFloat(amountStr.replace(/,/g, ''));
                if (!isNaN(val)) {
                    total += val;
        }
            }
    });
    
        $totalDisplay.text(currency + ' ' + total.toFixed(2));
    }


    
        $(document).on("click", ".re-remove-row", function() {
        if ($table.find("tr").length > 1) {
            $(this).closest("tr").remove();
            updateTotal();
    }
    });
    
        $(document).on("input", "input[name='rpi_amount[]'], input[name='rpi_currency[]']", function() {
        updateTotal();
    });



        // Handle checkbox toggle
        $(document).on("change", ".re-item-checkbox", function() {
        var $row = $(this).closest("tr");
        var isChecked = $(this).is(":checked");
    
        // Toggle disabled state of inputs in this row
        $row.find("input:not(.re-item-checkbox)").prop("disabled", !isChecked);
    
        updateTotal();
    });

        // Handle User Change - Auto-load invoices
        $("select[name='rpa_usr_id']").on("change", function() {
        var userId = $(this).val();
    
        if (!userId) {
            $table.empty();
            updateTotal();
            return;
    }

        // Only auto-load if we are in "New Payment" mode (or if the table is empty/default)
        // We can check if the URL has 'id' param, but here we can just check if we have many existing items.
        // For now, let's assume we always load for new user selection to help the user.
    
        $.ajax({
            url: '<?php echo ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . "/payments/ajax_get_open_invoices.php"; ?>',
            data: { usr_id: userId },
            dataType: 'json',
            success: function(data) {
                $table.empty(); // Clear existing items
                if (data && data.length > 0) {
                    $.each(data, function(index, inv) {
                        var rowHtml = '<tr class="align-middle">' +
                            '<td class="text-center align-middle"><div class="d-flex align-items-center justify-content-center" style="min-height: 38px;"><input type="checkbox" class="form-check-input re-item-checkbox m-0" checked /></div></td>' +
                            '<td><input type="hidden" name="rpi_inv_id[]" value="' + inv.id + '" /><input class="form-control" value="' + inv.number + '" readonly /></td>' +
                            '<td>' +
                        '<div class="input-group">' +
                                    '<input class="form-control" name="rpi_currency[]" value="' + inv.currency + '" style="max-width: 80px;" readonly />' +
                                    '<input class="form-control" name="rpi_amount[]" value="' + inv.amount + '" readonly />' +
                        '</div>' +
                            '</td>' +
                            '<td class="text-center"></td>' +
                        '</tr>';
                        $table.append(rowHtml);
                    });
        } else {
                    $table.append('<tr><td colspan="4" class="text-center text-danger fw-bold py-3">No open invoices found for this user!</td></tr>');
        }
                updateTotal();
            }
    });
    });
    
        // Initial calculation
        updateTotal();
    });
})(jQuery);
</script>
<div style="height: 50px;"></div>

<?php
$page->addHtml(ob_get_clean());
$page->show();
