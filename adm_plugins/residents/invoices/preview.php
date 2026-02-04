<?php
/**
    * Renders invoice preview mode inside the invoices list tab.
    *
    * Expected variables (from list.php scope):
    *   $page, $gDb, $gL10n, $gSettingsManager, $getGroup, $getUser,
    *   $previewStartParam, $previewInvoiceParam, $previewNoteParam,
    *   $defaultPageLength
    */

$previewData = residentsBuildInvoicePreviewData($getGroup, array(
    'start_date' => $previewStartParam,
    'invoice_date' => $previewInvoiceParam,
    'note' => $previewNoteParam,
    'user_id' => $getUser
));

$previewTableStyle = '#table_re_invoices_preview thead{border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;background-color:#fff;}'
    . '#table_re_invoices_preview thead th{font-weight:700;color:#495057;padding:12px 15px;white-space:nowrap;border:none;}'
    . '#table_re_invoices_preview_wrapper .dataTables_length,#table_re_invoices_preview_length{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}'
    . '#table_re_invoices_preview_wrapper .dataTables_length label,#table_re_invoices_preview_length label{margin-bottom:0;display:flex;align-items:center;gap:0.35rem;white-space:nowrap;}'
    . '#table_re_invoices_preview_wrapper .dataTables_length select,#table_re_invoices_preview_length select{width:auto;min-width:70px;display:inline-block;}'
    . '#table_re_invoices_preview thead th:nth-last-child(2){padding-right:34px;}'
    . '#table_re_invoices_preview thead th:last-child{padding-left:22px;padding-right:22px;}'
    . '#table_re_invoices_preview_filter{display:none!important;}'
    . '.preview-placeholder-row{display:none!important;}';
$page->addHtml('<style>' . $previewTableStyle . '</style>');

$filteredRows = array();
$skippedAllItemsUsers = 0;
$skippedAnyItemsUsers = 0;

// Filter by org_id since we only check invoices in current organization
$itemOverlapSql = 'SELECT COUNT(*)
    FROM ' . TBL_RE_INVOICES . ' i
    INNER JOIN ' . TBL_RE_INVOICE_ITEMS . ' it ON it.rii_inv_id = i.riv_id
    WHERE i.riv_org_id = ?
    AND i.riv_usr_id = ?
    AND it.rii_chg_id = ?
    AND it.rii_start_date <= ?
    AND it.rii_end_date >= ?';

foreach ((array)$previewData['rows'] as $row) {
    $uid = (int)($row['user_id'] ?? 0);
    if ($uid <= 0) {
        continue;
    }

    $rowItems = (array)($row['items'] ?? array());
    if (empty($rowItems)) {
        continue;
    }

    $filteredItems = array();
    $userTotal = 0.0;
    $invoiceStart = null;
    $invoiceEnd = null;
    $skippedAny = false;

    foreach ($rowItems as $item) {
        $chargeId = (int)($item['charge_id'] ?? 0);
        if ($chargeId <= 0) {
            continue;
    }
        $itemStart = (string)($item['start_date'] ?? ($row['start_date'] ?? ''));
        $itemEnd = (string)($item['end_date'] ?? ($row['end_date'] ?? ''));
        if ($itemStart === '' || $itemEnd === '') {
            continue;
    }

        $existsCount = (int)$gDb->queryPrepared($itemOverlapSql, array($gCurrentOrgId, $uid, $chargeId, $itemEnd, $itemStart))->fetchColumn();
        if ($existsCount > 0) {
            $skippedAny = true;
            continue;
    }

        $filteredItems[] = $item;
        $userTotal += (float)($item['amount'] ?? 0);
        if ($invoiceStart === null || $itemStart < $invoiceStart) {
            $invoiceStart = $itemStart;
    }
        if ($invoiceEnd === null || $itemEnd > $invoiceEnd) {
            $invoiceEnd = $itemEnd;
    }
    }

    if (empty($filteredItems)) {
        $skippedAllItemsUsers++;
        continue;
    }
    if ($skippedAny) {
        $skippedAnyItemsUsers++;
    }

    $row['items'] = $filteredItems;
    $row['start_date'] = $invoiceStart ?? (string)($row['start_date'] ?? '');
    $row['end_date'] = $invoiceEnd ?? (string)($row['end_date'] ?? '');
    $row['total'] = number_format($userTotal, 2, '.', '');
    $filteredRows[] = $row;
}

$previewData['rows'] = $filteredRows;

$totalAmount = 0.0;
$summaryEnd = (string)($previewData['parameters']['start_date'] ?? $previewStartParam);
foreach ($filteredRows as $r) {
    $totalAmount += (float)($r['total'] ?? 0);
    $ed = (string)($r['end_date'] ?? '');
    if ($ed !== '' && $ed > $summaryEnd) {
        $summaryEnd = $ed;
    }
}
$previewData['total_amount'] = number_format($totalAmount, 2, '.', '');
$previewData['summary_end_date'] = $summaryEnd;
$statusText = $gL10n->get('RE_PREVIEW_STATUS');
$currencyLabel = $previewData['currency'] ?? (isset($gSettingsManager) ? trim((string)$gSettingsManager->getString('system_currency')) : '');

$existingInvoicesUsers = $skippedAllItemsUsers + $skippedAnyItemsUsers;
if ($existingInvoicesUsers > 0) {
    $page->addHtml(
    '<div class="alert alert-warning">'
    . htmlspecialchars(sprintf($gL10n->get('RE_PREVIEW_EXISTING_WARNING'), (string)$existingInvoicesUsers))
    . '</div>'
    );
}

$previewTable = new HtmlTable('table_re_invoices_preview', $page, true, true, 'table table-hover align-middle');
$previewTable->setDatatablesRowsPerPage($defaultPageLength);
$previewTable->addRowHeadingByArray(array(
    $gL10n->get('RE_NUMBER'),
    $gL10n->get('RE_START_DATE'),
    $gL10n->get('RE_END_DATE'),
    $gL10n->get('RE_STATUS'),
    $gL10n->get('RE_USER'),
    $gL10n->get('RE_DUE_DATE'),
    $gL10n->get('RE_AMOUNT'),
    $gL10n->get('RE_ACTIONS')
));

if (!empty($previewData['rows'])) {
    $paramStart = $previewData['parameters']['start_date'] ?? $previewStartParam;
    $paramEnd = $previewData['summary_end_date'] ?? $paramStart;
    $coverageLabel = $paramStart === $paramEnd
    ? residentsFormatDateForUi($paramStart)
    : residentsFormatDateForUi($paramStart) . ' → ' . residentsFormatDateForUi($paramEnd);
    $totalFormatted = ($currencyLabel !== '' ? $currencyLabel . ' ' : '') . number_format((float)($previewData['total_amount'] ?? 0), 2, '.', ',');

    $cfg = residentsReadConfig();
    $dueDays = (int)($cfg['defaults']['due_days'] ?? 15);
    if ($dueDays <= 0) {
        $dueDays = 15;
    }

    $previewRowCount = 0;
    foreach ($previewData['rows'] as $row) {
        $invoiceDateForDue = (string)($row['invoice_date'] ?? '');
        if ($invoiceDateForDue === '') {
            $invoiceDateForDue = $previewData['parameters']['invoice_date'] ?? $previewInvoiceParam;
    }
        $dueDate = date('Y-m-d', strtotime($invoiceDateForDue . ' +' . $dueDays . ' days'));
        $amountValue = number_format((float)($row['total'] ?? 0), 2, '.', ',');
        $amountCell = ($currencyLabel !== '' ? $currencyLabel . ' ' : '') . $amountValue;
        $detailUrl = SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/detail.php',
            array(
        'preview' => 1,
        'preview_user' => (int)$row['user_id'],
        'preview_group' => $getGroup,
        'filter_user' => $getUser,
        'preview_start_date' => $paramStart,
        'preview_invoice_date' => $previewData['parameters']['invoice_date'] ?? $previewInvoiceParam,
        'preview_note' => $previewData['parameters']['note'] ?? $previewNoteParam
            )
        );
        $actionsCell = '<a class="admidio-icon-link" title="' . $gL10n->get('RE_VIEW') . '" href="' . $detailUrl . '"><i class="bi bi-eye"></i></a>';
        $previewTable->addRowByArray(array(
            '&mdash;',
            htmlspecialchars(residentsFormatDateForUi((string)$row['start_date'])),
            htmlspecialchars(residentsFormatDateForUi((string)$row['end_date'])),
            array(
        'value' => '<span class="badge bg-info">' . htmlspecialchars($statusText) . '</span>',
        'order' => $statusText,
        'search' => $statusText
            ),
            htmlspecialchars((string)$row['display_name']),
            htmlspecialchars(residentsFormatDateForUi($dueDate)),
            htmlspecialchars($amountCell),
            $actionsCell
        ));
        ++$previewRowCount;
    }
}

$page->addHtml($previewTable->show(false));

// $previewPlaceholderCleanup = <<<'JS'
//   $(function(){
//       if (typeof admidioTable_table_re_invoices_preview !== 'undefined') {
//         admidioTable_table_re_invoices_preview.rows('.preview-placeholder-row').remove().draw();
//     }
//   });
// JS;
// $page->addJavascript("\n" . $previewPlaceholderCleanup . "\n", true);
