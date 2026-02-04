<?php
/**
 ***********************************************************************************************
 * Intermediate page to confirm payment and select multiple invoices
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
    require_once(__DIR__ . '/../../../system/login_valid.php');
} else {
    require_once(__DIR__ . '/../../../system/login_valid.php');
}

global $gDb, $gCurrentUser, $gL10n, $gProfileFields, $gSettingsManager;

$page = new HtmlPage('plg-re-confirm', $gL10n->get('RE_PAYMENT_DETAILS'));

// Collect any invoice ids passed from the list (single via GET or multiple via POST)
$currentInvoiceId = admFuncVariableIsValid($_GET, 'invoice_id', 'int', array('defaultValue' => 0));
$incomingInvoiceIds = array();
if (isset($_POST['invoice_ids']) && is_array($_POST['invoice_ids'])) {
    $incomingInvoiceIds = $_POST['invoice_ids'];
} elseif ($currentInvoiceId > 0) {
    $incomingInvoiceIds = array($currentInvoiceId);
}
$selectedInvoiceIds = array_unique(array_filter(array_map('intval', $incomingInvoiceIds), function ($id) {
    return $id > 0;
}));

$selectAll = ((int)admFuncVariableIsValid($_GET, 'select_all', 'int', array('defaultValue' => 0)) === 1);

// Fetch all unpaid invoices for the current user in the current organization
$userId = (int)$gCurrentUser->getValue('usr_id');
$sql = 'SELECT riv_id, riv_number, riv_date, COALESCE(riv_is_paid, 0) AS riv_is_paid 
    FROM ' . TBL_RE_INVOICES . ' 
    WHERE riv_usr_id = ? AND riv_org_id = ? AND COALESCE(riv_is_paid, 0) = 0 
    ORDER BY riv_date ASC';
$stmt = $gDb->queryPrepared($sql, array($userId, (int)$gCurrentOrgId), false);

if ($stmt === false) {
    $invoices = array();
} else {
    $invoices = $stmt->fetchAll();
}

// If requested, preselect all unpaid invoices.
if ($selectAll && count($selectedInvoiceIds) === 0 && is_array($invoices)) {
    $selectedInvoiceIds = array_values(array_filter(array_map('intval', array_column($invoices, 'riv_id')), function ($id) {
        return $id > 0;
    }));
}

$page->addHtml('<div class="card">
    <div class="card-header">
    <h3 class="card-title">' . $gL10n->get('RE_PAYMENT_DETAILS') . '</h3>
    </div>
    <div class="card-body">
    <form action="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/ccavenue_pay.php') . '" method="post" id="confirm_pay_form">
            <input type="hidden" name="admidio-csrf-token" value="' . $gCurrentSession->getCsrfToken() . '" />
            <p>'. $gL10n->get('RE_SELECT_INVOICES_PAY') .'</p>
            <table class="table table-striped table-hover">
    <thead>
                    <tr>
            <th style="width: 40px;"><input type="checkbox" id="select_all"></th>
            <th>' . $gL10n->get('RE_NUMBER') . '</th>
            <th>' . $gL10n->get('RE_DATE') . '</th>
            <th>' . $gL10n->get('RE_AMOUNT') . '</th>
                    </tr>
    </thead>
    <tbody>');

$grandTotal = 0;
$hasInvoices = false;
$currencySymbol = $gSettingsManager->getString('system_currency');

foreach ($invoices as $inv) {
    $hasInvoices = true;
    $invId = (int)$inv['riv_id'];
    $totals = residentsGetInvoiceTotals($invId);
    $amount = (float)$totals['amount'];
    $currency = $totals['currency'];
    
    // Update global symbol if we find a specific one (last one wins if mixed)
    if (!empty($currency)) {
        $currencySymbol = $currency;
    }
    
    // Preselect invoices passed in via POST/GET so the user sees their previous selection
    $checked = in_array($invId, $selectedInvoiceIds, true) ? 'checked' : '';
    
    $page->addHtml('<tr>
    <td>
            <input type="checkbox" name="invoice_ids[]" value="' . $invId . '" class="inv-checkbox" data-amount="' . $amount . '" ' . $checked . '>
    </td>
    <td>' . htmlspecialchars($inv['riv_number']) . '</td>
    <td>' . htmlspecialchars(residentsFormatDateForUi((string)($inv['riv_date'] ?? ''))) . '</td>
    <td>' . $currency . ' ' . number_format($amount, 2) . '</td>
    </tr>');
}

if (!$hasInvoices) {
    $page->addHtml('<tr><td colspan="4" class="text-center">' . $gL10n->get('RE_NO_DATA') . '</td></tr>');
}

$page->addHtml('</tbody>
    <tfoot>
                    <tr>
            <th colspan="3" class="text-end">' . $gL10n->get('RE_TOTAL_PAYABLE') . '</th>
            <th id="total_display">0.00</th>
                    </tr>
    </tfoot>
            </table>
            
            <div class="d-flex justify-content-end mt-3" style="gap:0.5rem;">
    <a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'invoices')) . '" class="btn btn-secondary me-2">' . $gL10n->get('SYS_CANCEL') . '</a>
    <button type="submit" class="btn btn-primary" id="btn_pay" disabled>'. $gL10n->get('RE_CONFIRM_PAY') .'</button>
            </div>
    </form>
    </div>
</div>');

// Add JS for calculation and select all
$page->addHtml('<script>
document.addEventListener("DOMContentLoaded", function() {
    const checkboxes = document.querySelectorAll(".inv-checkbox");
    const selectAll = document.getElementById("select_all");
    const totalDisplay = document.getElementById("total_display");
    const btnPay = document.getElementById("btn_pay");
    const currencySymbol = "' . $currencySymbol . '";

    function calculateTotal() {
        let total = 0;
        let checkedCount = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                total += parseFloat(cb.getAttribute("data-amount"));
                checkedCount++;
            }
    });
        totalDisplay.textContent = currencySymbol + " " + total.toFixed(2);
        btnPay.disabled = checkedCount === 0;

        // Keep header checkbox in sync (including the single-row case)
        if (selectAll) {
        const totalCount = checkboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        selectAll.checked = totalCount > 0 && checkedCount === totalCount;
    }
    }

    if (selectAll) {
        selectAll.addEventListener("change", function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            calculateTotal();
    });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener("change", calculateTotal);
    });

    // Initial calculation
    calculateTotal();
});
</script>');

$page->addHtml('<div style="height: 50px;"></div>');
$page->show();
