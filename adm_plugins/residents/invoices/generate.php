<?php
/**
 ***********************************************************************************************
 * Generate membership charge invoices for users and redirect back to the list.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');

global $gDb, $gL10n, $gCurrentUser, $gCurrentOrgId;

if (!function_exists('residentsRedirectToInvoiceList')) {
    function residentsRedirectToInvoiceList(array $queryParams = array()): void
    {
        $params = array('tab' => 'invoices');
        foreach ($queryParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'filter_group' && (int)$value <= 0) {
                continue;
            }
            $params[$key] = $value;
    }
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $params);
        header('Location: ' . $url);
        exit;
    }
}

$groupId = admFuncVariableIsValid($_GET, 'group', 'int');
$filterUserId = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$startDateParam = admFuncVariableIsValid($_GET, 'start_date', 'date');
$invoiceDateParam = admFuncVariableIsValid($_GET, 'invoice_date', 'date');
$noteParam = admFuncVariableIsValid($_GET, 'note', 'string');
$cfg = residentsReadConfig();
$defaultNoteSetting = residentsGetDefaultInvoiceNote($cfg);
if ($startDateParam === '') {
    $startDateParam = date('Y-m-01');
}
if ($invoiceDateParam === '') {
    $invoiceDateParam = date('Y-m-d');
}
if ($noteParam === '') {
    $noteParam = $defaultNoteSetting;
}

// Ensure date values are stored as ISO dates (Y-m-d) for DB DATE columns and correct filtering.
$startDateParam = residentsFormatDateForInput($startDateParam);
$invoiceDateParam = residentsFormatDateForInput($invoiceDateParam);

$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Build generation data (user selection + charge info) for the requested group and month
$generationData = residentsBuildInvoicePreviewData($groupId, array(
    'start_date' => $startDateParam,
    'invoice_date' => $invoiceDateParam,
    'note' => $noteParam,
    'user_id' => $filterUserId
));
$previewRows = $generationData['rows'];

if (count($previewRows) === 0) {
    $redirectParams = array(
    'generate_status' => 'failed',
    'generate_message' => $gL10n->get('RE_GENERATE_NO_USERS')
    );
    // Keep list period consistent with the requested generation range.
    $redirectParams['date_from'] = $startDateParam;
    $redirectParams['date_to'] = $startDateParam;
    if ($groupId > 0) {
        $redirectParams['filter_group'] = (string)$groupId;
    }
    if ($filterUserId > 0) {
        $redirectParams['filter_user'] = (string)$filterUserId;
    }
    residentsRedirectToInvoiceList($redirectParams);
}

// Prepare config & last number tracking
$lastNumber = (int)($cfg['numbering']['last_number'] ?? 0);

// Collect user/date combinations referenced in preview to clean duplicates before regeneration
$cleanupTargets = array();
foreach ($previewRows as $row) {
    $uid = (int)($row['user_id'] ?? 0);
    if ($uid <= 0) {
        continue;
    }
    $start = (string)($row['start_date'] ?? $startDateParam);
    $end = (string)($row['end_date'] ?? $startDateParam);
    $cleanupTargets[$uid . '|' . $start . '|' . $end] = array($uid, $start, $end);
}

try {
    $usrIdCreate = (int)$gCurrentUser->getValue('usr_id');
    // Currency from system settings
    global $gSettingsManager;
    $currency = 'USD';
    if (isset($gSettingsManager) && method_exists($gSettingsManager, 'getString')) {
        $curVal = trim((string)$gSettingsManager->getString('system_currency'));
        if ($curVal !== '') { $currency = $curVal; }
    }

    // Do not delete existing invoices; skip generation per *charge item* if overlapping invoice item exists.
    // Overlap condition: existing_item_start <= new_item_end AND existing_item_end >= new_item_start
    // Filter by org_id to only check invoices in current organization
    $existingItemCheckSql = 'SELECT COUNT(*)
    FROM ' . TBL_RE_INVOICES . ' i
    INNER JOIN ' . TBL_RE_INVOICE_ITEMS . ' it ON it.rii_inv_id = i.riv_id
    WHERE i.riv_org_id = ?
        AND i.riv_usr_id = ?
        AND it.rii_chg_id = ?
        AND it.rii_start_date <= ?
        AND it.rii_end_date >= ?';

    $createdInvoices = 0;
    // Get the starting invoice number index ONCE before the loop, then increment for each invoice in the batch
    $nextIndex = residentsNextInvoiceNumberIndex();

    // Pre-check for invoice number uniqueness so we don't hit Database->showError() on duplicate key.
    // Filter by org_id since invoice numbers are unique per organization
    $invoiceNumberExistsSql = 'SELECT 1 FROM ' . TBL_RE_INVOICES . ' WHERE riv_org_id = ? AND (riv_number = ? OR riv_number_index = ?) LIMIT 1';

    foreach ($previewRows as $row) {
        $uid = (int)($row['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
    }
        $invoiceDate = (string)($row['invoice_date'] ?? $invoiceDateParam);
        if ($invoiceDate === '') {
            $invoiceDate = $invoiceDateParam;
    }
        $invoiceDate = residentsFormatDateForInput($invoiceDate);

        $periodStart = (string)($row['start_date'] ?? $startDateParam);
        if ($periodStart === '') {
            $periodStart = $startDateParam;
    }
        $periodStart = residentsFormatDateForInput($periodStart);

        $periodEnd = (string)($row['end_date'] ?? $periodStart);
        $periodEnd = ($periodEnd === '') ? $periodStart : residentsFormatDateForInput($periodEnd);

        $cfg = residentsReadConfig();
        $dueDays = (int)($cfg['defaults']['due_days'] ?? 15);
        if ($dueDays <= 0) { $dueDays = 15; }
        $dueDate = date('Y-m-d', strtotime($invoiceDate . ' +' . $dueDays . ' days'));
        $noteValue = (string)($row['note'] ?? $noteParam ?? '');
        $items = array();
        $invoiceStart = null; // DATE (Y-m-d)
        $invoiceEnd = null;   // DATE (Y-m-d)
        foreach ((array)($row['items'] ?? array()) as $itemRow) {
            $amountValue = number_format((float)($itemRow['amount'] ?? 0), 2, '.', '');
            if ($amountValue === '0.00') {
                continue;
            }

            $itemName = trim((string)($itemRow['name'] ?? ''));
            $chargeId = (int)($itemRow['charge_id'] ?? 0);
            if ($itemName === '') {
                continue;
            }
            if ($chargeId <= 0) {
                continue;
            }

            $itemStart = (string)($itemRow['start_date'] ?? $periodStart);
            $itemEnd = (string)($itemRow['end_date'] ?? $periodEnd);
            if ($itemStart === '') {
                $itemStart = $periodStart;
            }
            if ($itemEnd === '') {
                $itemEnd = $itemStart;
            }

            $itemStart = residentsFormatDateForInput($itemStart);
            $itemEnd = residentsFormatDateForInput($itemEnd);

            // Skip this charge if it was already invoiced for an overlapping period (in current org).
            $existsItem = (int)$gDb->queryPrepared($existingItemCheckSql, array($gCurrentOrgId, $uid, $chargeId, $itemEnd, $itemStart))->fetchColumn();
            if ($existsItem > 0) {
                continue;
            }

            if ($invoiceStart === null || $itemStart < $invoiceStart) {
                $invoiceStart = $itemStart;
            }
            if ($invoiceEnd === null || $itemEnd > $invoiceEnd) {
                $invoiceEnd = $itemEnd;
            }

            $items[] = array(
        'charge_id' => $chargeId,
        'name' => $itemName,
        'type' => 'membership',
        'currency' => (string)($itemRow['currency'] ?? $currency),
        'rate' => null,
        'quantity' => null,
        'amount' => $amountValue,
        'start_date' => $itemStart,
        'end_date' => $itemEnd
            );
    }
        if (empty($items)) {
            continue;
    }

        // Assign a unique invoice number/index from our batch counter.
        // This avoids duplicate-key errors that would otherwise trigger a separate SQL error page.
        $index = $nextIndex;
        while (true) {
            $number = residentsFormatInvoiceNumber($index);
            $st = $gDb->queryPrepared($invoiceNumberExistsSql, array($gCurrentOrgId, (string)$number, (int)$index), false);
            if ($st === false) {
                throw new RuntimeException('Could not verify invoice number uniqueness.');
            }
            $exists = ($st->fetchColumn() !== false);
            if (!$exists) {
                break;
            }
            ++$index;
    }
        $lastNumber = max($lastNumber, $index);
        $nextIndex = $index + 1; // Increment for the next invoice in this batch

        $finalStart = $invoiceStart ?? $periodStart;
        $finalEnd = $invoiceEnd ?? $periodEnd;
        $finalStart = residentsFormatDateForInput($finalStart);
        $finalEnd = residentsFormatDateForInput($finalEnd);
        $newInvoice = new TableResidentsInvoice($gDb);
        $newInvoice->setValue('riv_number_index', (int)$index);
        $newInvoice->setValue('riv_number', $number);
        $newInvoice->setValue('riv_date', $invoiceDate);
        $newInvoice->setValue('riv_usr_id', $uid);
        $newInvoice->setValue('riv_start_date', $finalStart);
        $newInvoice->setValue('riv_end_date', $finalEnd);
        $newInvoice->setValue('riv_due_date', $dueDate);
        $newInvoice->setValue('riv_notes', $noteValue);
        $newInvoice->setValue('riv_org_id', (int)$gCurrentOrgId);
        
        $saved = $newInvoice->save();
        if (!$saved) {
            throw new RuntimeException('Could not save invoice record.');
        }
        $newInvoiceId = (int)$newInvoice->getValue('riv_id');

        if ($newInvoiceId <= 0) {
            throw new RuntimeException('Could not save invoice record. riv_id is ' . $newInvoiceId);
    }

        // After save, add items (replaceItems validates that invoice id exists)
        $newInvoice->replaceItems($items, $usrIdCreate);
        ++$createdInvoices;
    }
    $cfg['numbering']['last_number'] = $lastNumber;
    residentsWriteConfig($cfg);

    if ($createdInvoices === 0) {
        $emptyParams = array(
            'generate_status' => 'empty',
            'generate_message' => $gL10n->get('RE_PREVIEW_NOTHING_TO_GENERATE')
        );
        // Keep list period consistent with the requested generation range.
        $emptyParams['date_from'] = $startDateParam;
        $emptyParams['date_to'] = $startDateParam;
        if ($groupId > 0) {
            $emptyParams['filter_group'] = (string)$groupId;
    }
        if ($filterUserId > 0) {
            $emptyParams['filter_user'] = (string)$filterUserId;
    }
        residentsRedirectToInvoiceList($emptyParams);
    }

    $periodStart = (string)($generationData['parameters']['start_date'] ?? $startDateParam);
    $periodEnd = (string)($generationData['summary_end_date'] ?? '');
    if ($periodEnd === '') {
        $periodEnd = $periodStart;
    }

    $successParams = array(
    'generate_status' => 'success',
    'generate_period' => $periodStart . ($periodEnd !== $periodStart ? ' → ' . $periodEnd : ''),
    'generate_count' => (string)$createdInvoices
    );

    // After generation, automatically set list filter period to the generated range.
    $successParams['date_from'] = $periodStart;
    $successParams['date_to'] = $periodEnd;
    if ($groupId > 0) {
        $successParams['filter_group'] = (string)$groupId;
    }
    if ($filterUserId > 0) {
        $successParams['filter_user'] = (string)$filterUserId;
    }
    residentsRedirectToInvoiceList($successParams);
} catch (\Throwable $e) {
    $errorParams = array(
    'generate_status' => 'failed',
    'generate_message' => $e->getMessage()
    );
    $errorParams['date_from'] = $startDateParam;
    $errorParams['date_to'] = $startDateParam;
    if ($groupId > 0) {
        $errorParams['filter_group'] = (string)$groupId;
    }
    if ($filterUserId > 0) {
        $errorParams['filter_user'] = (string)$filterUserId;
    }
    residentsRedirectToInvoiceList($errorParams);
}

exit;
