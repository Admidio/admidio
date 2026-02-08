<?php
/**
    * Payments tab content. Lists captured payments and allows viewing their items.
    */

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gSettingsManager, $gCurrentOrgId, $gDbType, $page;

$baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
$canManage = isPaymentAdmin();
$canCreatePayments = isResidentsAdminBySettings();
$canViewAll = $canCreatePayments || $canManage;

// Show empty-state banner if there are no payments at all, but still render filters/table
$countStmt = $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_RE_PAYMENTS . ' WHERE rpa_org_id = ?', array($gCurrentOrgId));
$totalPayments = $countStmt ? (int)$countStmt->fetchColumn() : 0;
// Continue rendering filters/table even if there are zero payments; suppress empty-state banner.

// Check for timed out payments (Initiated > X mins ago, uses config value)
global $pgConf;
$timeoutMinutes = isset($pgConf['timeout']) && (int)$pgConf['timeout'] > 0 ? (int)$pgConf['timeout'] : 15;
$timeoutDate = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
TableResidentsTransaction::expireInitiated($gDb, $timeoutDate);

// flash messages from payment gateway
$paymentStatus = admFuncVariableIsValid($_GET, 'payment_status', 'string');
$paymentMessage = admFuncVariableIsValid($_GET, 'payment_message', 'string');
$paymentMessageMap = array(
    'invalid_response' => $gL10n->get('RE_PAYMENT_MSG_INVALID_RESPONSE'),
    'missing_order' => $gL10n->get('RE_PAYMENT_MSG_MISSING_ORDER'),
    'payment_not_found' => $gL10n->get('RE_PAYMENT_MSG_PAYMENT_NOT_FOUND'),
    'processing_error' => $gL10n->get('RE_PAYMENT_MSG_PROCESSING_ERROR'),
    'Unknown error' => $gL10n->get('RE_PAYMENT_MSG_UNKNOWN')
);

if ($paymentStatus === 'success') {
    $page->addHtml('<div class="alert alert-success mb-3">' . $gL10n->get('RE_PAYMENT_SUCCESS') . '</div>');
} elseif ($paymentStatus === 'failed') {
    $msg = $gL10n->get('RE_PAYMENT_FAILED');
    if ($paymentMessage !== '' && isset($paymentMessageMap[$paymentMessage])) {
        $msg = $paymentMessageMap[$paymentMessage];
    } elseif ($paymentMessage !== '') {
        $msg .= ' (' . htmlspecialchars($paymentMessage) . ')';
    }
    $page->addHtml('<div class="alert alert-danger mb-3">' . $msg . '</div>');
} elseif ($paymentStatus === 'deleted') {
    $page->addHtml('<div class="alert alert-success mb-3">Payment deleted.</div>');
} elseif ($paymentStatus === 'error') {
    $msg = $paymentMessage !== '' ? htmlspecialchars($paymentMessage) : 'Action failed.';
    $page->addHtml('<div class="alert alert-danger mb-3">' . $msg . '</div>');
}

$getUser = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$getType = trim((string)admFuncVariableIsValid($_GET, 'filter_type', 'string'));
$getQ = trim((string)admFuncVariableIsValid($_GET, 'q', 'string'));
$getStart = admFuncVariableIsValid($_GET, 'filter_start', 'date');
$getEnd = admFuncVariableIsValid($_GET, 'filter_end', 'date');
if ($getStart === '' && $getEnd === '') {
    $getStart = date('Y-m-01');
    $getEnd = date('Y-m-t');
}

// Determine default page length for Datatables
$defaultPageLength = 25;

// filter dropdowns
$getGroup = admFuncVariableIsValid($_GET, 'filter_group', 'int');

// Hide and ignore group/user filters for normal users (only admins can filter across users/groups).
if (!$canViewAll) {
    $getUser = 0;
    $getGroup = 0;
}

$firstNameFieldId = (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
$lastNameFieldId = (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id');
$userOptions = TableResidentsPayment::fetchUserOptions($gDb, $canViewAll, $firstNameFieldId, $lastNameFieldId, (int)$gCurrentUser->getValue('usr_id'), $getGroup);





$filterAction = SecurityUtils::encodeUrl($baseUrl, array('tab' => 'payments'));
// Show "New payment" button only to residents admins
if ($canCreatePayments) {
    $buttonsHtml = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/edit.php').'" class="btn btn-secondary"><i class="bi bi-plus-circle"></i> '.$gL10n->get('RE_ADD_PAYMENT').'</a>';
    $page->addHtml('<div class="mb-3 text-start">'.$buttonsHtml.'</div>');
}

if (!$canViewAll) {
    $basicForm = new HtmlForm(
    'payments_filter_basic',
    $filterAction,
    $page,
    array('type' => 'navbar', 'setFocus' => false)
    );
    $basicForm->addInput('tab', '', 'payments', array('type' => 'hidden'));
    $basicForm->addInput('filter_start', $gL10n->get('SYS_START'), $getStart, array('type' => 'date', 'maxLength' => 10));
    $basicForm->addInput('filter_end', $gL10n->get('SYS_END'), $getEnd, array('type' => 'date', 'maxLength' => 10));
    $basicForm->addButton(
    'payments_filter_apply_basic',
    $gL10n->get('SYS_FILTER'),
    array('type' => 'submit', 'icon' => 'bi-funnel', 'class' => 'btn btn-primary btn-sm ms-2')
    );

    $basicNavbar = new HtmlNavbar('navbar_payments_filter_basic', '', $page, 'filter');
    $basicNavbar->addForm($basicForm->show());
    $page->addHtml($basicNavbar->show());
    $page->addJavascript(
    "$(function(){ var basicPayForm=$('#payments_filter_basic'); basicPayForm.find('input[type=date]').on('change', function(){ basicPayForm.submit(); }); });",
    true
    );
}

// Show filters only to admins (use same navbar form styling as Invoices)
if ($canViewAll) {
    $roles = residentsGetRoleOptions();
    $rolesWithAll = array('0' => $gL10n->get('RE_ALL')) + $roles;
    $userOptionsWithAll = array('0' => $gL10n->get('RE_ALL')) + $userOptions;

    $labelGroup = '<i class="bi bi-people" alt="'.$gL10n->get('RE_GROUP').'" title="'.$gL10n->get('RE_GROUP').'"></i>';
    $labelUser = '<i class="bi bi-person" alt="'.$gL10n->get('RE_USER').'" title="'.$gL10n->get('RE_USER').'"></i>';
    $labelSearch = '<i class="bi bi-search" alt="'.$gL10n->get('RE_SEARCH').'" title="'.$gL10n->get('RE_SEARCH').'"></i>';

    $filterNavbar = new HtmlNavbar('navbar_payments_filter', '', $page, 'filter');
    $filterForm = new HtmlForm(
    'payments_filter',
    $filterAction,
    $page,
    array('type' => 'navbar', 'setFocus' => false)
    );
    $filterForm->addInput('tab', '', 'payments', array('type' => 'hidden'));

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
    'filter_type',
    $gL10n->get('RE_TYPE'),
    array(
            '' => $gL10n->get('RE_ALL'),
            'online' => $gL10n->get('RE_PAYMENT_TYPE_ONLINE'),
            'offline' => $gL10n->get('RE_PAYMENT_TYPE_OFFLINE')
    ),
    array('defaultValue' => (string)$getType, 'showContextDependentFirstEntry' => false)
    );

    $filterForm->addInput('q', $labelSearch, $getQ);
    $filterForm->addInput('filter_start', $gL10n->get('SYS_START'), $getStart, array('type' => 'date', 'maxLength' => 10));
    $filterForm->addInput('filter_end', $gL10n->get('SYS_END'), $getEnd, array('type' => 'date', 'maxLength' => 10));
    $filterForm->addButton(
    'payments_filter_apply',
    $gL10n->get('SYS_FILTER'),
    array('type' => 'submit', 'icon' => 'bi-funnel', 'class' => 'btn btn-primary btn-sm ms-2')
    );

    $filterNavbar->addForm($filterForm->show());
    $page->addHtml($filterNavbar->show());

    $page->addJavascript(
    "$(function(){ var payForm=$('#payments_filter'); payForm.find('select, input[type=date], input[name=q]').on('change', function(){ payForm.submit(); }); });",
    true
    );
}

$paymentsStyle = '#table_re_payments{table-layout:auto;width:100%;}';
$paymentsStyle .= '#table_re_payments thead{border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;background-color:#fff;}';
$paymentsStyle .= '#table_re_payments thead th{font-weight:700;color:#495057;padding:12px 15px !important;white-space:nowrap;position:relative;border:none;}';
$paymentsStyle .= '#table_re_payments tbody td{padding:12px 15px !important;}';
// DataTables 2.x fix: Override flex-direction for numeric/date columns so sorting icon stays on the right
$paymentsStyle .= '#table_re_payments thead th.dt-type-numeric div.dt-column-header,#table_re_payments thead th.dt-type-date div.dt-column-header{flex-direction:row !important;}';
// Ensure consistent text alignment between header and body
$paymentsStyle .= '#table_re_payments thead th:nth-child(1),#table_re_payments tbody td:nth-child(1){text-align:center !important;width:40px;}'; // Checkbox
$paymentsStyle .= '#table_re_payments thead th:nth-child(2),#table_re_payments tbody td:nth-child(2){text-align:left !important;}'; // Payment #
$paymentsStyle .= '#table_re_payments thead th:nth-child(3),#table_re_payments tbody td:nth-child(3){text-align:left !important;}'; // Payment Date
$paymentsStyle .= '#table_re_payments thead th:nth-child(4),#table_re_payments tbody td:nth-child(4){text-align:left !important;}'; // Payment Method
$paymentsStyle .= '#table_re_payments thead th:nth-child(5),#table_re_payments tbody td:nth-child(5){text-align:left !important;}'; // Payment Type
$paymentsStyle .= '#table_re_payments thead th:nth-child(6),#table_re_payments tbody td:nth-child(6){text-align:left !important;}'; // Customer
$paymentsStyle .= '#table_re_payments thead th:nth-child(7),#table_re_payments tbody td:nth-child(7){text-align:left !important;}'; // Total Amount
$paymentsStyle .= '#table_re_payments thead th:nth-child(8),#table_re_payments tbody td:nth-child(8){text-align:left !important;}'; // Actions
$paymentsStyle .= '#table_re_payments_wrapper .dataTables_length,#table_re_payments_length,.dt-length{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}';
$paymentsStyle .= '#table_re_payments_wrapper .dataTables_length label,#table_re_payments_length label,.dt-length label{margin-bottom:0;display:flex;align-items:center;gap:0.35rem;white-space:nowrap;}';
$paymentsStyle .= '#table_re_payments_wrapper .dataTables_length select,#table_re_payments_length select,.dt-length select{width:auto;min-width:70px;display:inline-block;}';
$paymentsStyle .= '#table_re_payments_filter,.dt-search{display:none!important;}';
$paymentsStyle .= '#re-delete-selected-payments{margin-left:10px;}';
$paymentsStyle .= '#payments_filter #q_group { flex: 0 0 auto;}';
if ($canViewAll) {
    $paymentsStyle .= '#table_re_payments thead th:first-child:before,#table_re_payments thead th:first-child:after{display:none!important;}';
}
$page->addHtml('<style>'.$paymentsStyle.'</style>');

$serverParams = array(
    'filter_user' => $getUser,
    'filter_group' => $getGroup,
    'filter_type' => $getType,
    'q' => $getQ,
    'filter_start' => $getStart,
    'filter_end' => $getEnd
);
$serverUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/list_data.php', $serverParams);

$table = new HtmlTable('table_re_payments', $page, $isAdmin, true, 'table table-hover align-middle');
$table->setServerSideProcessing($serverUrl);
$table->setDatatablesRowsPerPage($defaultPageLength);
$table->setDatatablesOrderColumns(array(array(2, 'desc')));
if ($canViewAll) {
    $table->disableDatatablesColumnsSort(array(1,8));
    $table->setColumnAlignByArray(array('center','left','left','left','left','left','right','left'));
    $table->addRowHeadingByArray(array(
    ($canManage ? '<input type="checkbox" id="re-select-all-payments" />' : ''),
    $gL10n->get('RE_PAYMENT_NUMBER'),
    $gL10n->get('RE_PAYMENT_DATE'),
    $gL10n->get('RE_PAYMENT_METHOD'),
    $gL10n->get('RE_PAYMENT_TYPE'),
    $gL10n->get('RE_CUSTOMER'),
    $gL10n->get('RE_PAYMENT_TOTAL'),
    $gL10n->get('RE_ACTIONS')
    ));
} else {
    $table->disableDatatablesColumnsSort(array(7));
    $table->setColumnAlignByArray(array('left','left','left','left','left','right','left'));
    $table->addRowHeadingByArray(array(
    $gL10n->get('RE_PAYMENT_NUMBER'),
    $gL10n->get('RE_PAYMENT_DATE'),
    $gL10n->get('RE_PAYMENT_METHOD'),
    $gL10n->get('RE_PAYMENT_TYPE'),
    $gL10n->get('RE_CUSTOMER'),
    $gL10n->get('RE_PAYMENT_TOTAL'),
    $gL10n->get('RE_ACTIONS')
    ));
}

if ($canManage) {
    $bulkDeleteUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payments/delete_all.php');
    $bulkDeleteUrlJs = json_encode($bulkDeleteUrl);
    $paymentsDeleteConfirm = json_encode($gL10n->get('RE_DELETE_PAYMENT_CONFIRM'));
    $paymentsDeleteError = json_encode('Error deleting selected payments');
    $deleteButtonLabel = json_encode($gL10n->get('RE_DELETE_ALL'));
    $csrfTokenJs = json_encode($GLOBALS['gCurrentSession']->getCsrfToken());
    $jsPayments = <<<'JS'
  $(function(){
    var bulkDeleteUrl = {{BULK_DELETE_URL}};
    var deleteConfirmMsg = {{DELETE_CONFIRM}};
    var deleteErrorMsg = {{DELETE_ERROR}};
    var deleteButtonLabel = {{DELETE_BUTTON_LABEL}};
    var csrfToken = {{CSRF_TOKEN}};
    var tableEl = $('#table_re_payments');
    var dataTable = tableEl.DataTable();
    var tableWrapperEl = $('#table_re_payments_wrapper');
    function locateLengthContainer(){
      var wrapperEl = $('#table_re_payments_wrapper');
      var lengthEl = $('#table_re_payments_length');
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
      var lengthEl = locateLengthContainer();
      if (!lengthEl.length) {
        return $();
      }
      var buttonEl = $('#re-delete-selected-payments');
      if (buttonEl.length) {
        return buttonEl;
      }
      var newButtonEl = $('<button type="button" id="re-delete-selected-payments" class="btn btn-danger btn-sm ms-2"><i class="bi bi-trash"></i> ' + deleteButtonLabel + '</button>');
      lengthEl.append(newButtonEl);
      return newButtonEl;
    }
    function bindDeleteButton(buttonEl){
      if (!buttonEl.length || buttonEl.data('residentsDeleteBound')) {
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
          success: function(){ location.reload(); },
          error: function(){ alert(deleteErrorMsg); }
        });
      });
    }
    function getDeleteButton(){
      var buttonEl = ensureDeleteButton();
      bindDeleteButton(buttonEl);
      return buttonEl;
    }
    function updateDeleteButtonState(){
      var buttonEl = getDeleteButton();
      if (!buttonEl.length) {
        return;
      }
      var hasSelection = tableEl.find('tbody input.re-row-select:checked').length > 0;
      buttonEl.prop('disabled', !hasSelection);
    }
    var deleteButtonEl = getDeleteButton();
    dataTable.on('init.dt', function(){
      deleteButtonEl = getDeleteButton();
    });
    var headerEl = tableEl.find('thead');
    var firstHeader = headerEl.find('th').first();
    if (firstHeader.length) {
      firstHeader.removeClass('sorting sorting_asc sorting_desc');
    }
    function syncHeaderCheckbox(){
      var total = tableEl.find('tbody input.re-row-select').length;
      var selected = tableEl.find('tbody input.re-row-select:checked').length;
      var hdr = $('#re-select-all-payments').get(0);
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
      .on('click', '#re-select-all-payments', function(e){ e.stopPropagation(); })
      .on('change', '#re-select-all-payments', function(e){
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
      deleteButtonEl = getDeleteButton();
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
    $jsPayments = strtr($jsPayments, array(
        '{{BULK_DELETE_URL}}' => $bulkDeleteUrlJs,
        '{{DELETE_CONFIRM}}' => $paymentsDeleteConfirm,
        '{{DELETE_ERROR}}' => $paymentsDeleteError,
        '{{DELETE_BUTTON_LABEL}}' => $deleteButtonLabel,
        '{{CSRF_TOKEN}}' => $csrfTokenJs,
    ));
    $page->addJavascript("\n".$jsPayments."\n", true);
}

$page->addHtml($table->show(false));

