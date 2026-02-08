<?php
/**
 ***********************************************************************************************
 * View payment details and related items.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
// Enforce valid login
if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
    require_once(__DIR__ . '/../../../system/login_valid.php');
} else {
    require_once(__DIR__ . '/../../../system/login_valid.php');
}

global $gDb, $gL10n, $gProfileFields, $gCurrentUser;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');
$isAdmin = isPaymentAdmin();
$canViewAll = isResidentsAdmin() || $isAdmin;

$paymentRecord = new TableResidentsPayment($gDb, $id);
if ($paymentRecord->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Organization check: payment must belong to current organization
residentsValidateOrganization($paymentRecord, 'rpa_org_id');

$ownerId = (int)$paymentRecord->getValue('rpa_usr_id');
if (!$canViewAll && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$paymentData = array(
    'user_name' => $ownerId > 0 ? residentsFetchUserNameById($ownerId) : '',
    'rpa_date' => date('d.m.Y H:i', strtotime((string)$paymentRecord->getValue('rpa_date'))),
    'rpa_pay_type' => (string)$paymentRecord->getValue('rpa_pay_type'),
    'rpa_pg_pay_method' => (string)$paymentRecord->getValue('rpa_pg_pay_method'),
    'rpa_trans_id' => (string)$paymentRecord->getValue('rpa_trans_id'),
    'rpa_bank_ref_no' => (string)$paymentRecord->getValue('rpa_bank_ref_no'),
    'rpa_id' => (int)$paymentRecord->getValue('rpa_id')
);

$page = new HtmlPage('plg-residents', $gL10n->get('RE_PAYMENT_DETAILS'));
$page->setHeadline($gL10n->get('RE_TAB_PAYMENTS'));
residentsEnqueueStyles($page);

ob_start();
?>
<style>
    .re-editor .card { border: none; border-radius: 0.9rem; box-shadow: 0 12px 24px rgba(20, 24, 45, 0.08); }
    .re-editor .card+.card { margin-top: 1.5rem; }
    .re-editor .card-header { border-bottom: none; background: transparent; font-weight: 600; letter-spacing: .08em; font-size: .75rem; text-transform: uppercase; color: #6c757d; }
    .re-editor .form-label { display: block; font-weight: 600; letter-spacing: .05em; }
    .re-editor .form-control-plaintext { padding: 0.5rem 0; }
</style>

<div class="re-editor">
<!-- Payment Details Section -->
<div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('RE_PAYMENT_DETAILS'); ?></div>
    <div class="card-body">
    <div class="row g-3 mb-3">
            <div class="col-md-4">
        <label class="form-label fw-bold"><?php echo $gL10n->get('RE_USER'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)($paymentData['user_name'] ?? '')); ?></div>
            </div>
            <div class="col-md-8">
        <label class="form-label fw-bold"><?php echo $gL10n->get('RE_PAYMENT_DATE'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)$paymentData['rpa_date']); ?></div>
            </div>
    </div>
    <div class="row g-3">
            <div class="col-md-4">
        <label class="form-label fw-bold"><?php echo $gL10n->get('RE_PAYMENT_TYPE'); ?></label>
        <div class="form-control-plaintext">
                    <?php 
                    $type = $paymentData['rpa_pay_type'] ?? '';
                    if ($type === 'Online') echo $gL10n->get('RE_PAYMENT_TYPE_ONLINE');
                    elseif ($type === 'Offline') echo $gL10n->get('RE_PAYMENT_TYPE_OFFLINE');
                    else echo htmlspecialchars((string)$type);
                    ?>
        </div>
            </div>
            <div class="col-md-8">
        <label class="form-label fw-bold"><?php echo $gL10n->get('RE_PAYMENT_METHOD'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)$paymentData['rpa_pg_pay_method']); ?></div>
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
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)($paymentData['rpa_trans_id'] ?? '-')); ?></div>
            </div>
            <div class="col-md-6">
        <label class="form-label fw-bold"><?php echo $gL10n->get('RE_BANK_REF_NO'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)($paymentData['rpa_bank_ref_no'] ?? '-')); ?></div>
            </div>
    </div>
    </div>
</div>

<!-- Payment Items Section -->
<div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('RE_PAYMENT_ITEMS'); ?></div>
    <div class="card-body p-0">
    <div class="table-responsive">
            <table class="table mb-0">
        <thead>
                    <tr>
            <th style="width:25%"><?php echo $gL10n->get('RE_NUMBER'); ?></th>
            <th style="width:75%"><?php echo $gL10n->get('RE_AMOUNT'); ?></th>
                    </tr>
        </thead>
        <tbody>
                    <?php
                    $total = 0.0;
                    $currency = '';
                    $itemRows = $paymentRecord->getItems(true);
                    foreach ($itemRows as $item) {
                        $currency = $item['rpi_currency'] ?: $currency;
                        $amount = (float)$item['rpi_amount'];
                        $total += $amount;

                        $invoiceLink = '-';
                        if (!empty($item['rpi_inv_id'])) {
                            $label = $item['riv_number'] ?? ('#' . (int)$item['rpi_inv_id']);
                            $invoiceLink = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/detail.php', array('id' => $item['rpi_inv_id'])) . '">' . htmlspecialchars((string)$label) . '</a>';
            }
                        ?>
                        <tr>
                            <td><?php echo $invoiceLink; ?></td>
                            <td><?php echo htmlspecialchars((string)$item['rpi_currency']) . ' ' . number_format($amount, 2, '.', ''); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
        </tbody>
            </table>
    </div>
    </div>
</div>

<div class="d-flex justify-content-end align-items-center mt-3 flex-wrap" style="gap: 20px;">
    <div>
    <strong><?php echo $gL10n->get('RE_PAYMENT_TOTAL'); ?>: <?php echo htmlspecialchars((string)$currency) . ' ' . number_format($total, 2, '.', ''); ?></strong>
    </div>
    <?php $exportUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/pdf.php', array('id' => $id)); ?>
    <a href="<?php echo $exportUrl; ?>" class="btn btn-primary"><i class="bi bi-file-earmark-pdf"></i> <?php echo $gL10n->get('RE_DOWNLOAD_RECEIPT'); ?></a>
    <?php if ($isAdmin && $paymentData['rpa_pay_type'] !== 'Online') : ?>
            <?php $confirmText = htmlspecialchars($gL10n->get('RE_DELETE_PAYMENT_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>
            <form method="post" action="<?php echo ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/delete.php'; ?>" class="d-inline" onsubmit="return confirm('<?php echo $confirmText; ?>');">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>" />
        <input type="hidden" name="admidio-csrf-token" value="<?php echo htmlspecialchars($gCurrentSession->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
        <button type="submit" class="btn btn-danger text-white"><i class="bi bi-trash"></i> <?php echo $gL10n->get('SYS_DELETE'); ?></button>
            </form>
    <?php endif; ?>
</div>
</div>
<div style="height: 50px;"></div>

<?php
$page->addHtml(ob_get_clean());
$page->show();

