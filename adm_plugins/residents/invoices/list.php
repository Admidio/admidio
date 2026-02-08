<?php
/**
* Invoices tab content. Expects $page, $isAdmin to be available from residents.php
*/

global $gDb, $gProfileFields, $gCurrentUser, $gL10n, $gSettingsManager, $gCurrentOrganization, $page;

$isAdmin = isResidentsAdminBySettings();

$previewToggleForMsg = (int)admFuncVariableIsValid($_GET, 'preview', 'int');
$isPreviewForMsg = ($previewToggleForMsg === 1);

if (!$isPreviewForMsg) {
    // Check for payment status messages
    $paymentStatus = admFuncVariableIsValid($_GET, 'payment_status', 'string');
    if ($paymentStatus === 'success') {
        $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_PAYMENT_SUCCESS') . '</div>');
    } elseif ($paymentStatus === 'failed') {
        $msgKey = 'RE_PAYMENT_FAILED';
        $msgDetail = admFuncVariableIsValid($_GET, 'payment_message', 'string');

        // Map internal error codes to localized strings
        $errorMap = array(
        'invalid_response' => 'RE_PAYMENT_MSG_INVALID_RESPONSE',
        'missing_order' => 'RE_PAYMENT_MSG_MISSING_ORDER',
        'payment_not_found' => 'RE_PAYMENT_MSG_PAYMENT_NOT_FOUND',
        'processing_error' => 'RE_PAYMENT_MSG_PROCESSING_ERROR',
        'db_update_failed' => 'RE_PAYMENT_MSG_DB_UPDATE_FAILED'
        );

        if (array_key_exists($msgDetail, $errorMap)) {
            $detailedMsg = $gL10n->get($errorMap[$msgDetail]);
        } else {
            $detailedMsg = $msgDetail;
        }

        $page->addHtml('<div class="alert alert-danger">' . $gL10n->get($msgKey) . ($detailedMsg ? ' (' . htmlspecialchars($detailedMsg) . ')' : '') . '</div>');
    }

    $invoiceStatus = admFuncVariableIsValid($_GET, 'invoice_status', 'string');
    if ($invoiceStatus === 'deleted') {
        $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_INVOICE_DELETED') . '</div>');
    } elseif ($invoiceStatus === 'error') {
        $invoiceMessage = admFuncVariableIsValid($_GET, 'invoice_message', 'string');
        $msg = $invoiceMessage !== '' ? htmlspecialchars($invoiceMessage) : 'Action failed.';
        $page->addHtml('<div class="alert alert-danger">' . $msg . '</div>');
    }

    $generateStatus = admFuncVariableIsValid($_GET, 'generate_status', 'string');
    if ($generateStatus === 'success') {
        $generatePeriod = admFuncVariableIsValid($_GET, 'generate_period', 'string');
        $generateCount = admFuncVariableIsValid($_GET, 'generate_count', 'int');
        $msg = $gL10n->get('RE_GENERATE_SUCCESS_MSG');
        if ($generateCount > 0) {
            $msg .= ' ' . htmlspecialchars(sprintf($gL10n->get('RE_PREVIEW_GENERATED_COUNT'), (string)$generateCount));
        }
        if ($generatePeriod !== '') {
            $periodLabel = trim((string)$generatePeriod);
            if (strpos($periodLabel, '→') !== false) {
                $parts = explode('→', $periodLabel, 2);
                $left = trim((string)($parts[0] ?? ''));
                $right = trim((string)($parts[1] ?? ''));
                $periodLabel = residentsFormatDateForUi($left) . ' → ' . residentsFormatDateForUi($right);
            } else {
                $periodLabel = residentsFormatDateForUi($periodLabel);
            }
            $msg .= ' (' . htmlspecialchars($periodLabel) . ')';
        }
        $page->addHtml('<div class="alert alert-success">' . $msg . '</div>');
    } elseif ($generateStatus === 'empty') {
        $detail = admFuncVariableIsValid($_GET, 'generate_message', 'string');
        if ($detail === '') {
            $detail = $gL10n->get('RE_PREVIEW_NOTHING_TO_GENERATE');
        }
        $page->addHtml('<div class="alert alert-info">' . htmlspecialchars($detail) . '</div>');
    } elseif ($generateStatus === 'failed') {
        $detail = admFuncVariableIsValid($_GET, 'generate_message', 'string');
        $msg = $gL10n->get('RE_GENERATE_FAILED_MSG');
        if ($detail !== '') {
            $msg .= ' (' . htmlspecialchars($detail) . ')';
        }
        $page->addHtml('<div class="alert alert-danger">' . $msg . '</div>');
    }
}

$previewToggle = admFuncVariableIsValid($_GET, 'preview', 'int');
$previewRequested = ((int)$previewToggle === 1);
if (!$isAdmin) {
    $previewRequested = false;
}
$previewStartParam = admFuncVariableIsValid($_GET, 'preview_start_date', 'date');
$previewInvoiceParam = admFuncVariableIsValid($_GET, 'preview_invoice_date', 'date');
if ($previewInvoiceParam === '') {
    $previewInvoiceParam = date('Y-m-d');
}
$previewNoteParam = admFuncVariableIsValid($_GET, 'preview_note', 'string');
if ($previewRequested && ($previewStartParam === '' || $previewInvoiceParam === '')) {
    $previewRequested = false;
}

$baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';

// Read filters
$getGroup  = admFuncVariableIsValid($_GET, 'filter_group', 'int');
$getUser   = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$getPaid = admFuncVariableIsValid($_GET, 'filter_paid', 'string');
$getPaid = trim((string)$getPaid);
$getQ      = admFuncVariableIsValid($_GET, 'q', 'string');
// Optional date range filters (refer events list implementation)
$getDateFrom  = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo    = admFuncVariableIsValid($_GET, 'date_to', 'date');

// Default to current month range on initial load when no explicit filter provided
if ($getDateFrom === '' && $getDateTo === '') {
    $getDateFrom = date('Y-m-01');
    $getDateTo = date('Y-m-t');
}

// Show empty-state banner if there are no invoices at all, but still render filters/table
$countStmt = $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_RE_INVOICES . ' WHERE riv_org_id = ?', array($gCurrentOrgId));
$totalInvoices = $countStmt ? (int)$countStmt->fetchColumn() : 0;
// Keep rendering filters/table even when there are no invoices; avoid empty-state banner.

$defaultPageLength = 25;

// Admin action buttons (need filter values first)
if ($isAdmin) {
    $buttons = array();
    $buttons[] = '<a class="btn btn-secondary" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/edit.php').'"><i class="bi bi-plus-circle"></i> '.$gL10n->get('RE_ADD_INVOICE').'</a>';

    $runParamsBase = array();
    if ($getGroup > 0) {
        $runParamsBase['filter_group'] = $getGroup;
    }
    if ($getUser > 0) {
        $runParamsBase['filter_user'] = $getUser;
    }
    if ($getPaid !== '') {
        $runParamsBase['filter_paid'] = $getPaid;
    }
    if ($getDateFrom !== '') {
        $runParamsBase['date_from'] = $getDateFrom;
    }
    if ($getDateTo !== '') {
        $runParamsBase['date_to'] = $getDateTo;
    }
    if ($getQ !== '') {
        $runParamsBase['q'] = $getQ;
    }

    if ($previewRequested) {
        // Add Generate button with confirm when in preview mode; skip confirmation page and generate directly
        $generateParams = $runParamsBase + array(
        'group' => $getGroup,
        'filter_user' => $getUser,
        'start_date' => $previewStartParam,
        'invoice_date' => $previewInvoiceParam,
        'note' => $previewNoteParam
        );
        $genUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/generate.php', $generateParams);
        $buttons[] = '<a class="btn btn-primary" href="#" onclick="if(confirm(\''.$gL10n->get('RE_GENERATE_CONFIRM').'\')){ window.location.href=\''.$genUrl.'\'; } return false;"><i class="bi bi-receipt"></i> '.$gL10n->get('RE_GENERATE_BILL').'</a>';
    } else {
        // Preview is available on the confirmation page (do not render a Preview button here)
    }

    // Only show the general Generate button when not in preview to avoid duplicates
    if (!$previewRequested) {
        $generateRunParams = array('mode' => 'generate') + $runParamsBase;
        $buttons[] = '<a class="btn btn-secondary" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/confirm_invoice.php', $generateRunParams).'"><i class="bi bi-receipt"></i> '.$gL10n->get('RE_GENERATE_BILL').'</a>';
        // Pay now (select all unpaid invoices of current user)
        $payNowUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/confirm_pay.php', array('select_all' => 1));
        $buttons[] = '<a class="btn btn-primary" id="re-pay-now" href="'.$payNowUrl.'"><i class="bi bi-credit-card"></i> '.$gL10n->get('RE_PAY_NOW').'</a>';
    }
    $page->addHtml(implode(' ', $buttons) . '<br/><br/>' );
}

if ($isAdmin) {
    // Navbar-like filter form
    $cfg = residentsReadConfig();
    $ownerGroupId = (int)($cfg['owners']['group_id'] ?? 0);
    $effectiveOwnerGroup = $getGroup > 0 ? $getGroup : $ownerGroupId;

    // Build user dropdown options
    if ($effectiveOwnerGroup === 0) {
        $lnId = (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id');
        $fnId = (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id');
        // Filter users by organization: only return users who are members of roles belonging to current org
        $sqlUsers = 'SELECT DISTINCT u.usr_id, u.usr_login_name,
        fn.usd_value AS firstname, ln.usd_value AS lastname'
        . ' FROM ' . TBL_USERS . ' u'
        . ' INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id AND m.mem_begin <= ? AND m.mem_end > ?'
        . ' INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true'
        . ' INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)'
        . ' LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId
        . ' LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId
        . ' WHERE u.usr_valid = true'
        . ' ORDER BY lastname, firstname, u.usr_login_name';
        $stmtUsers = $gDb->queryPrepared($sqlUsers, array(DATE_NOW, DATE_NOW, $gCurrentOrgId));
        if (!empty($_GET['debug_users'])) {
            $debugRows = array();
            $stmtUsersDebug = $gDb->queryPrepared($sqlUsers, array(DATE_NOW, DATE_NOW, $gCurrentOrgId));
            if ($stmtUsersDebug !== false) {
                while ($r = $stmtUsersDebug->fetch(PDO::FETCH_ASSOC)) {
                    $debugRows[] = $r;
                }
            }
            echo '<pre class="re-debug-users">'
            . htmlspecialchars($sqlUsers) . "\n\n"
            . htmlspecialchars(json_encode($debugRows, JSON_PRETTY_PRINT))
            . '</pre>';
            exit;
        }
        $userOptions = array();
        if ($stmtUsers !== false) {
            while ($row = $stmtUsers->fetch()) {
                $f = trim((string)($row['firstname'] ?? ''));
                $l = trim((string)($row['lastname'] ?? ''));
                $displayName = trim($f . ' ' . $l);
                if ($displayName === '') {
                    $displayName = trim((string)($row['usr_login_name'] ?? ''));
                }
                $userOptions[(int)$row['usr_id']] = $displayName;
            }
        }
    } else {
        $userOptions = residentsGetOwnerOptions($effectiveOwnerGroup);
    }

    if ($getUser > 0 && !isset($userOptions[$getUser])) {
        // User is likely a Former user (not in active membership list) - add with label
        $formerUserName = residentsFetchUserNameById($getUser);
        $userOptions[$getUser] = $formerUserName . ' (Former)';
    }
    $userOptionsWithAll = array('0' => $gL10n->get('RE_ALL')) + $userOptions;

    $roles = residentsGetRoleOptions();
    $rolesWithAll = array('0' => $gL10n->get('RE_ALL')) + $roles;

    $labelSearch = '<i class="bi bi-search" alt="'.$gL10n->get('RE_SEARCH').'" title="'.$gL10n->get('RE_SEARCH').'"></i>';
    $labelGroup  = '<i class="bi bi-people" alt="'.$gL10n->get('RE_GROUPS_ROLES').'" title="'.$gL10n->get('RE_GROUPS_ROLES').'"></i>';
    $labelUser   = '<i class="bi bi-person" alt="'.$gL10n->get('RE_USER').'" title="'.$gL10n->get('RE_USER').'"></i>';

    $paidOptions = array('' => $gL10n->get('RE_ALL')) + residentsInvoiceStatusOptions('paid');


    $filterNavbar = new HtmlNavbar('navbar_filter', '', $page, 'filter');
    $filterForm = new HtmlForm(
    'invoice_filter',
    SecurityUtils::encodeUrl($baseUrl, array('tab' => 'invoices')),
    $page,
    array('type' => 'navbar', 'setFocus' => false)
    );

    $filterForm->addSelectBox(
    'filter_group',
    $labelGroup,
    $rolesWithAll,
    array('defaultValue' => (string)$getGroup, 'showContextDependentFirstEntry' => false)
    );

    $filterForm->addSelectBox(
    'filter_user',
    $labelUser,
    $userOptionsWithAll,
    array('defaultValue' => (string)$getUser, 'showContextDependentFirstEntry' => false)
    );

    $filterForm->addSelectBox(
    'filter_paid',
    $gL10n->get('RE_PAID_STATUS'),
    $paidOptions,
    array('defaultValue' => (string)$getPaid, 'showContextDependentFirstEntry' => false)
    );

    $filterForm->addInput('q', $labelSearch, $getQ);
    $filterForm->addInput('date_from', $gL10n->get('SYS_START'), $getDateFrom, array('type' => 'date', 'maxLength' => 10));
    $filterForm->addInput('date_to', $gL10n->get('SYS_END'), $getDateTo, array('type' => 'date', 'maxLength' => 10));
    $filterForm->addButton(
    're_filter_apply',
    $gL10n->get('SYS_FILTER'),
    array('type' => 'submit', 'icon' => 'bi-funnel', 'class' => 'btn btn-primary btn-sm ms-2')
    );

    $loadUsersUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/load_users.php');
    $loadUsersUrlJs = json_encode($loadUsersUrl);

    $allLabelJs = json_encode($gL10n->get('RE_ALL'));

    $jsFilter = <<<'JS'
  $(function(){
    var loadUsersUrl = {{LOAD_USERS_URL}};
    var allLabel = {{ALL_LABEL}};
    var filterGroupEl = $('select[name=filter_group]');
    var filterUserEl = $('#filter_user');
    function submitFilters(){
      var formEl = $('#invoice_filter');
      if (formEl.length) {
        formEl.submit();
      }
    }
    function loadUsers(){
      if (!filterGroupEl.length || !filterUserEl.length) {
        return;
      }
      var groupId = filterGroupEl.val();
      $.ajax({
        type: 'GET',
        url: loadUsersUrl,
        data: { group_id: groupId },
        dataType: 'json',
        success: function(data){
          filterUserEl.empty();
          filterUserEl.append($('<option>', { value: '0', text: allLabel }));
          $.each(data, function(key, value){
            filterUserEl.append($('<option>', { value: key, text: value }));
          });
        }
      });
    }
    filterGroupEl.on('change', function(){
      if (filterUserEl.length) {
        filterUserEl.val('');
      }
      loadUsers();
    });
    $('select[name=filter_user]').on('change', submitFilters);
    $('select[name=filter_paid]').on('change', submitFilters);
    $('input[name=q], input[name=date_from], input[name=date_to]').on('change', submitFilters);
  });
  JS;
    $jsFilter = str_replace('{{LOAD_USERS_URL}}', $loadUsersUrlJs, $jsFilter);
    $jsFilter = str_replace('{{ALL_LABEL}}', $allLabelJs, $jsFilter);
    $page->addJavascript("\n".$jsFilter."\n", true);

    $filterNavbar->addForm($filterForm->show());
    $page->addHtml($filterNavbar->show());
}
else {
    $basicForm = new HtmlForm(
    'invoice_filter_basic',
    SecurityUtils::encodeUrl($baseUrl, array('tab' => 'invoices')),
    $page,
    array('type' => 'navbar', 'setFocus' => false)
    );
    $paidOptions = array('' => $gL10n->get('RE_ALL')) + residentsInvoiceStatusOptions('paid');
    $basicForm->addSelectBox(
    'filter_paid',
    $gL10n->get('RE_PAID_STATUS'),
    $paidOptions,
    array('defaultValue' => (string)$getPaid, 'showContextDependentFirstEntry' => false)
    );
    $basicForm->addInput('date_from', $gL10n->get('SYS_START'), $getDateFrom, array('type' => 'date', 'maxLength' => 10));
    $basicForm->addInput('date_to', $gL10n->get('SYS_END'), $getDateTo, array('type' => 'date', 'maxLength' => 10));
    $basicForm->addButton(
    're_filter_apply_basic',
    $gL10n->get('SYS_FILTER'),
    array('type' => 'submit', 'icon' => 'bi-funnel', 'class' => 'btn btn-primary btn-sm ms-2')
    );
    $basicNavbar = new HtmlNavbar('navbar_filter_basic', '', $page, 'filter');
    $basicNavbar->addForm($basicForm->show());
    $page->addHtml($basicNavbar->show());
    $page->addJavascript(
    "$(function(){ var basicForm=$('#invoice_filter_basic'); basicForm.find('select[name=filter_paid]').on('change', function(){ basicForm.submit(); }); basicForm.find('input[type=date]').on('change', function(){ basicForm.submit(); }); });",
    true
    );
}

// Prepare server-side datatable endpoint with current filters
$serverParams = array(
'filter_group' => $getGroup,
'filter_user' => $getUser,
'filter_paid' => $getPaid,
'q' => $getQ,
'date_from' => $getDateFrom,
'date_to' => $getDateTo
);
$serverUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/list_data.php', $serverParams);

if ($previewRequested) {
    require __DIR__ . '/preview.php';
    return;
}

$tableHeaderStyle = '#table_re_invoices thead{border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;background-color:#fff;}#table_re_invoices thead th{font-weight:700;color:#495057;padding:12px 15px;white-space:nowrap;border:none;}';
$tableHeaderStyle .= '#table_re_invoices_wrapper .dataTables_length,#table_re_invoices_length{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}'
. '#table_re_invoices_wrapper .dataTables_length label,#table_re_invoices_length label{margin-bottom:0;display:flex;align-items:center;gap:0.35rem;white-space:nowrap;}';
$tableHeaderStyle .= '#table_re_invoices_wrapper .dataTables_length select,#table_re_invoices_length select{width:auto;min-width:70px;display:inline-block;}';
$tableHeaderStyle .= '#table_re_invoices thead th:nth-last-child(2){padding-right:34px;}';
$tableHeaderStyle .= '#table_re_invoices thead th:last-child{padding-left:22px;padding-right:22px;}';
$tableHeaderStyle .= '#table_re_invoices_filter{display:none!important;}';
$tableHeaderStyle .= 'input.re-filter-checkbox{position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;margin:0;}'
. '.re-check-square-checked{display:none!important;}'
. '.re-check-square-unchecked{display:inline-block;}'
. 'input.re-filter-checkbox:checked ~ .re-check-square-checked{display:inline-block!important;}'
. 'input.re-filter-checkbox:checked ~ .re-check-square-unchecked{display:none!important;}'
. '.re-check-square-checked{margin-right:0.35rem;line-height:1;font-size:1.15em;vertical-align:-0.1em;}'
. '.re-check-square-unchecked{margin-right:0.35rem;display:inline-block;width:1.05em;height:1.05em;border:2px solid currentColor;border-radius:0.15em;opacity:0.7;vertical-align:-0.15em;box-sizing:border-box;}'
. '.checkbox label{cursor:pointer;}';
$tableHeaderStyle .= '#invoice_filter #q_group { flex: 0 0 auto;}';
if ($isAdmin) {
    $tableHeaderStyle .= '#table_re_invoices thead th:first-child:before,#table_re_invoices thead th:first-child:after{display:none!important;}';
}
$page->addHtml('<style>'.$tableHeaderStyle.'</style>');

$table = new HtmlTable('table_re_invoices', $page, true, true, 'table table-hover align-middle');
$table->setServerSideProcessing($serverUrl);
$table->setDatatablesRowsPerPage($defaultPageLength);
$table->setDatatablesOrderColumns(array(array(2, 'desc')));
$headings = array(
$gL10n->get('RE_NUMBER'),
$gL10n->get('RE_START_DATE'),
$gL10n->get('RE_END_DATE'),
$gL10n->get('RE_PAID_STATUS'),
$gL10n->get('RE_USER'),
$gL10n->get('RE_DUE_DATE'),
$gL10n->get('RE_AMOUNT'),
$gL10n->get('RE_ACTIONS')
);
array_unshift($headings, '<input type="checkbox" id="re-select-all" />');
$table->disableDatatablesColumnsSort(array(1,9));
$table->setColumnAlignByArray(array('center','left','left','left','left','left','left','left','left'));
$table->addRowHeadingByArray($headings);

$bulkDeleteUrl = $isAdmin ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/delete_all.php') : '';
$bulkDeleteUrlJs = json_encode($bulkDeleteUrl);
$deleteConfirmMsg = json_encode($gL10n->get('RE_DELETE_INVOICE_CONFIRM'));
$deleteErrorMsg = json_encode('Error deleting selected invoices');
$deleteButtonLabel = json_encode($gL10n->get('RE_DELETE_ALL'));
$deletePaidMsg = json_encode($gL10n->get('RE_DELETE_PAID_DENIED'));
$isAdminJs = $isAdmin ? 'true' : 'false';
$csrfTokenJs = json_encode($GLOBALS['gCurrentSession']->getCsrfToken());

$jsActions = <<<'JS'
  $(function(){
    var isAdmin = {{IS_ADMIN}};
    var bulkDeleteUrl = {{BULK_DELETE_URL}};
    var deleteConfirmMsg = {{DELETE_CONFIRM}};
    var deleteErrorMsg = {{DELETE_ERROR}};
    var deleteButtonLabel = {{DELETE_BUTTON_LABEL}};
    var deletePaidMsg = {{DELETE_PAID_MSG}};
    var csrfToken = {{CSRF_TOKEN}};
    var tableEl = $('#table_re_invoices');
    var dataTable = tableEl.DataTable();
    var tableWrapperEl = $('#table_re_invoices_wrapper');
    function locateLengthContainer(){
      var wrapperEl = $('#table_re_invoices_wrapper');
      var lengthEl = $('#table_re_invoices_length');
      if (lengthEl.length) {
        return lengthEl;
      }
      lengthEl = wrapperEl.find('.dt-length');
      if (lengthEl.length) {
        return lengthEl;
      }
      // Direct lookup as fallback
      lengthEl = $('.dt-length');
      if (lengthEl.length) {
        return lengthEl;
      }
      return $();
    }
    function gatherSelectedIds(){
      var ids = [];
      tableEl.find('tbody input.re-row-select:checked').each(function(){
        ids.push($(this).val());
      });
      return ids;
    }

    function ensureDeleteButton(){
      if (!isAdmin) {
        return $();
      }
      var lengthEl = locateLengthContainer();
      if (!lengthEl.length) {
        return $();
      }
      var buttonEl = $('#re-delete-selected');
      if (buttonEl.length) {
        return buttonEl;
      }
      var newButtonEl = $('<button type="button" id="re-delete-selected" class="btn btn-danger btn-sm ms-2"><i class="bi bi-trash"></i> ' + deleteButtonLabel + '</button>');
      lengthEl.append(newButtonEl);
      return newButtonEl;
    }
    function bindDeleteButton(buttonEl){
      if (!isAdmin || !buttonEl.length || buttonEl.data('residentsDeleteBound')) {
        return;
      }
      buttonEl.data('residentsDeleteBound', true).on('click', function(e){
        e.preventDefault();
        var ids = gatherSelectedIds();
        if (ids.length === 0) {
          return;
        }
        if (!confirm(deleteConfirmMsg)) { return; }
        $.ajax({
          type: 'POST',
          url: bulkDeleteUrl,
          data: { ids: ids, 'admidio-csrf-token': csrfToken },
          success: function(data){
            if (isPaidDeletionResponse(data)) {
              alert(deletePaidMsg);
              return;
            }
            location.reload();
          },
          error: function(jqXHR){
            if (isPaidDeletionResponse(jqXHR)) {
              alert(deletePaidMsg);
              return;
            }
            alert(deleteErrorMsg);
          }
        });
      });
    }
    function getDeleteButton(){
      var buttonEl = ensureDeleteButton();
      bindDeleteButton(buttonEl);
      return buttonEl;
    }
    function updateDeleteButtonState(){
      if (!isAdmin) {
        return;
      }
      var buttonEl = getDeleteButton();
      if (!buttonEl.length) {
        return;
      }
      var hasSelection = tableEl.find('tbody input.re-row-select:checked').length > 0;
      buttonEl.prop('disabled', !hasSelection);
    }
    function isPaidDeletionResponse(resp){
      if (!resp) {
        return false;
      }
      if (typeof resp === 'string') {
        return resp.indexOf('PAID') === 0;
      }
      if (resp.responseText && resp.responseText.indexOf('PAID') === 0) {
        return true;
      }
      return false;
    }
    var deleteButtonEl = getDeleteButton();
    dataTable.on('init.dt', function(){
      if (isAdmin) {
        deleteButtonEl = getDeleteButton();
      }
    });
    var headerEl = tableEl.find('thead');
    function syncHeaderCheckbox(){
      var total = tableEl.find('tbody input.re-row-select').length;
      var selected = tableEl.find('tbody input.re-row-select:checked').length;
      var hdr = $('#re-select-all').get(0);
      if (!hdr) { return; }
      hdr.indeterminate = selected > 0 && selected < total;
      hdr.checked = total > 0 && selected === total;
    }
    function updateInfo(){
      var pageInfo = dataTable.page.info();
      var selected = tableEl.find('tbody input.re-row-select:checked').length;
      var infoEl = tableWrapperEl.find('.dataTables_info');
      if (selected > 0) {
        infoEl.text(selected + ' selected');
      } else {
        infoEl.text('Showing ' + (pageInfo.start + 1) + ' to ' + pageInfo.end + ' of ' + pageInfo.recordsDisplay + ' entries');
      }
      updateDeleteButtonState();
    }
    headerEl
      .on('click', '#re-select-all', function(e){ e.stopPropagation(); })
      .on('change', '#re-select-all', function(e){
        e.stopPropagation();
        var checked = this.checked;
        tableEl.find('tbody input.re-row-select')
          .prop('checked', checked)
          .trigger('change');
        syncHeaderCheckbox();
      });
    dataTable.on('draw', function(){
      updateInfo();
      syncHeaderCheckbox();
      if (isAdmin) {
        deleteButtonEl = getDeleteButton();
      }
      updateDeleteButtonState();
    });
    tableEl.on('change', 'input.re-row-select', function(){
      updateInfo();
      syncHeaderCheckbox();
    });
    updateInfo();
    syncHeaderCheckbox();
    updateDeleteButtonState();
  });
JS;

$jsActions = strtr($jsActions, array(
'{{IS_ADMIN}}' => $isAdminJs,
'{{BULK_DELETE_URL}}' => $bulkDeleteUrlJs,
'{{DELETE_CONFIRM}}' => $deleteConfirmMsg,
'{{DELETE_ERROR}}' => $deleteErrorMsg,
'{{DELETE_BUTTON_LABEL}}' => $deleteButtonLabel,
'{{DELETE_PAID_MSG}}' => $deletePaidMsg,
'{{CSRF_TOKEN}}' => $csrfTokenJs,
));
$page->addJavascript("\n" . $jsActions . "\n", true);

$page->addHtml($table->show(false));
