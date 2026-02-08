<?php
/**
* Chargers tab content: manage recurring charge definitions.
*/

global $gDb, $gL10n, $gSettingsManager, $page;

$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    $page->addHtml('<div class="alert alert-warning">' . $gL10n->get('RE_ONLY_ADMIN') . '</div>');
    return;
}

$chargeStatus = admFuncVariableIsValid($_GET, 'charge_status', 'string');
$chargeMessage = admFuncVariableIsValid($_GET, 'charge_message', 'string');
if ($chargeStatus === 'saved') {
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_CHARGERS_SAVED') . '</div>');
} elseif ($chargeStatus === 'deleted') {
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_CHARGERS_DELETED') . '</div>');
} elseif ($chargeStatus === 'error') {
    $msg = $chargeMessage !== '' ? htmlspecialchars($chargeMessage) : 'Action failed.';
    $page->addHtml('<div class="alert alert-danger">' . $msg . '</div>');
}

$newUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/charges/edit.php');
$page->addHtml('<a class="btn btn-primary" href="' . $newUrl . '"><i class="bi bi-plus-circle"></i> ' . $gL10n->get('RE_CHARGERS_ADD') . '</a><br /><br />');

if ($gCurrentOrgId > 0) {
    $countStmt = $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_RE_CHARGES . ' WHERE rch_org_id = ?', array($gCurrentOrgId));
} else {
    $countStmt = $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_RE_CHARGES, array());
}
$totalCharges = $countStmt ? (int)$countStmt->fetchColumn() : 0;

// Keep rendering table even when there are no charges; suppress empty-state banner.

$defaultPageLength = 25;

$serverUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/charges/list_data.php');

$table = new HtmlTable('residents_chargers', $page, true, true, 'table table-condensed');
$table->setServerSideProcessing($serverUrl);
$table->setDatatablesRowsPerPage($defaultPageLength);
$table->setDatatablesOrderColumns(array(array(2, 'asc')));
$table->disableDatatablesColumnsSort(array(1, 7));
$table->setColumnAlignByArray(array('center', 'left', 'left', 'right', 'left', 'left', 'center'));
$table->addRowHeadingByArray(array(
'<input type="checkbox" id="re-select-all-charges" />',
'ID',
$gL10n->get('RE_CHARGERS_NAME'),
$gL10n->get('RE_CHARGERS_PERIOD'),
$gL10n->get('RE_CHARGERS_AMOUNT'),
$gL10n->get('RE_CHARGERS_ROLES'),
$gL10n->get('RE_ACTIONS')
));

if ($isAdmin) {
    $bulkDeleteUrlCh = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/charges/delete_all.php');
    $bulkDeleteUrlChJs = json_encode($bulkDeleteUrlCh);
    $chargesDeleteConfirm = json_encode($gL10n->get('RE_CHARGERS_DELETE_CONFIRM'));
    $chargesDeleteError = json_encode('Error deleting selected charges');
    $deleteAllLabel = json_encode($gL10n->get('RE_DELETE_ALL'));

    $jsCharges = <<<'JS'
  $(function(){
    var bulkDeleteUrl = {{BULK_DELETE_URL}};
    var deleteConfirmMsg = {{DELETE_CONFIRM}};
    var deleteErrorMsg = {{DELETE_ERROR}};
    var deleteButtonLabel = {{DELETE_BUTTON_LABEL}};
    
    var tableEl = $('#residents_chargers');
    var dataTable = tableEl.DataTable();
    var wrapperEl = $('#residents_chargers_wrapper');
    function locateLengthContainer(){
      var wrapperEl = $('#residents_chargers_wrapper');
      var lengthEl = $('#residents_chargers_length');
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
    function ensureDeleteButton(){
      var lengthEl = locateLengthContainer();
      if (!lengthEl.length) {
        return $();
      }
      var buttonEl = $('#re-delete-selected-charges');
      if (buttonEl.length) {
        return buttonEl;
      }
      var newButtonEl = $('<button type="button" id="re-delete-selected-charges" class="btn btn-danger btn-sm ms-2"><i class="bi bi-trash"></i> ' + deleteButtonLabel + '</button>');
      lengthEl.append(newButtonEl);
      return newButtonEl;
    }
    function bindDeleteButton(buttonEl){
      if (!buttonEl.length || buttonEl.data('residentsDeleteBound')) {
        return;
      }
      buttonEl.data('residentsDeleteBound', true).on('click', function(e){
        e.preventDefault();
        var ids = [];
        tableEl.find('tbody input.re-row-select:checked').each(function(){ ids.push($(this).val()); });
        if (ids.length === 0) {
          return;
        }
        if (!confirm(deleteConfirmMsg)) { return; }
        $.ajax({
          type: 'POST',
          url: bulkDeleteUrl,
          data: { ids: ids },
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
      updateDeleteButtonState();
    });
    function syncHeaderCheckboxCharges(){
      var total = tableEl.find('tbody input.re-row-select').length;
      var selected = tableEl.find('tbody input.re-row-select:checked').length;
      var hdr = $('#re-select-all-charges').get(0);
      if (!hdr) { return; }
      hdr.indeterminate = selected > 0 && selected < total;
      hdr.checked = total > 0 && selected === total;
    }
    tableEl.find('thead')
      .on('click', '#re-select-all-charges', function(e){ e.stopPropagation(); })
      .on('change', '#re-select-all-charges', function(e){
        e.stopPropagation();
        var checked = this.checked;
        tableEl.find('tbody input.re-row-select')
          .prop('checked', checked)
          .trigger('change');
        syncHeaderCheckboxCharges();
      });
    function updateInfo(){
      var pageInfo = dataTable.page.info();
      var selected = tableEl.find('tbody input.re-row-select:checked').length;
      var infoEl = wrapperEl.find('.dataTables_info');
      if (selected > 0){
        infoEl.text(selected + ' selected');
      } else {
        infoEl.text('Showing ' + (pageInfo.start + 1) + ' to ' + pageInfo.end + ' of ' + pageInfo.recordsDisplay + ' entries');
      }
    }
    dataTable.on('draw', function(){
      updateInfo();
      syncHeaderCheckboxCharges();
      deleteButtonEl = getDeleteButton();
      updateDeleteButtonState();
    });
    tableEl.on('change', 'input.re-row-select', function(){
      updateInfo();
      syncHeaderCheckboxCharges();
      updateDeleteButtonState();
    });
    updateInfo();
    syncHeaderCheckboxCharges();
    updateDeleteButtonState();
  });
  JS;
    $jsCharges = strtr($jsCharges, array(
    '{{BULK_DELETE_URL}}' => $bulkDeleteUrlChJs,
    '{{DELETE_CONFIRM}}' => $chargesDeleteConfirm,
    '{{DELETE_ERROR}}' => $chargesDeleteError,
    '{{DELETE_BUTTON_LABEL}}' => $deleteAllLabel,
    ));
    $page->addJavascript("\n".$jsCharges."\n", true);
}

$tableStyle = '#residents_chargers thead{border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;background-color:#fff;}#residents_chargers thead th{font-weight:700;color:#495057;padding:12px 30px 12px 15px !important;white-space:nowrap;position:relative;border:none;background-position: right 5px center !important;}';
$tableStyle .= '#residents_chargers thead th:last-child{padding-right:15px !important;}';
$tableStyle .= '#residents_chargers_wrapper .dataTables_length,#residents_chargers_length{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}';
$tableStyle .= '#residents_chargers_wrapper .dataTables_length label,#residents_chargers_length label{margin-bottom:0;display:flex;align-items:center;gap:0.35rem;white-space:nowrap;}';
$tableStyle .= '#residents_chargers_wrapper .dataTables_length select,#residents_chargers_length select{width:auto;min-width:70px;display:inline-block;}';
if ($isAdmin) {
    $tableStyle .= '#residents_chargers thead th:first-child:before,#residents_chargers thead th:first-child:after{display:none!important;}';
}
$page->addHtml('<style>'.$tableStyle.'</style>');

$page->addHtml($table->show(false));
