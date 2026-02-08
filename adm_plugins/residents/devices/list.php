<?php
/**
 ***********************************************************************************************
 * Manage Device tab content - manage mobile login devices
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

global $gDb, $gL10n, $gSettingsManager, $page;

$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    $page->addHtml('<div class="alert alert-warning">' . $gL10n->get('SYS_NO_RIGHTS') . '</div>');
    return;
}

$deviceStatus = admFuncVariableIsValid($_GET, 'device_status', 'string');
$deviceMessage = admFuncVariableIsValid($_GET, 'device_message', 'string');
if ($deviceStatus === 'deleted') {
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_DEVICE_DELETED') . '</div>');
} elseif ($deviceStatus === 'approved') {
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_DEVICE_APPROVED') . '</div>');
}elseif ($deviceStatus === 'reset') {
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_DEVICE_RESET') . '</div>');
} elseif ($deviceStatus === 'unapproved') {
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_DEVICE_UNAPPROVED') . '</div>');
} elseif ($deviceStatus === 'error') {
    $msg = $deviceMessage !== '' ? htmlspecialchars($deviceMessage) : 'Action failed.';
    $page->addHtml('<div class="alert alert-danger">' . $msg . '</div>');
}
$baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';

// Read filters
$getGroup  = admFuncVariableIsValid($_GET, 'filter_group', 'int');
$getUser   = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$getActive = admFuncVariableIsValid($_GET, 'filter_active', 'string');
$getActive = trim((string)$getActive);
$getQ      = admFuncVariableIsValid($_GET, 'q', 'string');
// Optional date range filters (refer events list implementation)
// $getDateFrom  = admFuncVariableIsValid($_GET, 'date_from', 'date');
// $getDateTo    = admFuncVariableIsValid($_GET, 'date_to', 'date');

// Default to current month range on initial load when no explicit filter provided
// if ($getDateFrom === '' && $getDateTo === '') {
    //   $getDateFrom = date('Y-m-01');
    //   $getDateTo = date('Y-m-t');
    // }

$defaultPageLength = 25;

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
        $sqlUsers = 'SELECT DISTINCT u.usr_id, u.usr_login_name AS login_name'
        . ' FROM ' . TBL_USERS . ' u'
        . ' INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id AND m.mem_begin <= ? AND m.mem_end > ?'
        . ' INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true'
        . ' INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)'
        . ' WHERE u.usr_valid = true'
        . ' ORDER BY u.usr_login_name';
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
                $loginName = trim((string)($row['login_name'] ?? ''));
                if ($loginName === '') {
                    $loginName = 'User #' . (int)$row['usr_id'];
                }
                $userOptions[(int)$row['usr_id']] = $loginName;
            }
        }
    } else {
        $userOptions = residentsGetOwnerOptions($effectiveOwnerGroup);
    }

    if ($getUser > 0 && !isset($userOptions[$getUser])) {
        $userOptions[$getUser] = residentsFetchUserNameById($getUser);
    }
    $userOptionsWithAll = array('0' => $gL10n->get('RE_ALL')) + $userOptions;

    $roles = residentsGetRoleOptions();
    $rolesWithAll = array('0' => $gL10n->get('RE_ALL')) + $roles;

    $labelSearch = '<i class="bi bi-search" alt="'.$gL10n->get('RE_SEARCH').'" title="'.$gL10n->get('RE_SEARCH').'"></i>';
    $labelGroup  = '<i class="bi bi-people" alt="'.$gL10n->get('RE_GROUPS_ROLES').'" title="'.$gL10n->get('RE_GROUPS_ROLES').'"></i>';
    $labelUser   = '<i class="bi bi-person" alt="'.$gL10n->get('RE_USER').'" title="'.$gL10n->get('RE_USER').'"></i>';


    $filterNavbar = new HtmlNavbar('navbar_filter', '', $page, 'filter');
    $filterForm = new HtmlForm(
    'device_filter',
    SecurityUtils::encodeUrl($baseUrl, array('tab' => 'devices')),
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

    $filterForm->addCheckbox(
    'filter_active',
    '<span class="re-check-square-checked" aria-hidden="true"><i class="bi bi-check-square"></i></span><span class="re-check-square-unchecked" aria-hidden="true"></span> '.$gL10n->get('RE_DEVICE_ACTIVE'),
    ($getActive === '1'),
    array('class' => 're-filter-checkbox')
    );

    $filterForm->addInput('q', $labelSearch, $getQ);
    $filterForm->addInput('tab', '', 'devices', array('type' => 'hidden'));
    // $filterForm->addInput('date_from', $gL10n->get('SYS_START'), $getDateFrom, array('type' => 'date', 'maxLength' => 10));
    // $filterForm->addInput('date_to', $gL10n->get('SYS_END'), $getDateTo, array('type' => 'date', 'maxLength' => 10));
    $filterForm->addButton(
    'device_filter_apply',
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
      var formEl = $('#device_filter');
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
    $('input[name=filter_active]').on('change', submitFilters);
    $('input[name=q], input[name=date_from], input[name=date_to]').on('change', submitFilters);
  });
  JS;
    $jsFilter = str_replace('{{LOAD_USERS_URL}}', $loadUsersUrlJs, $jsFilter);
    $jsFilter = str_replace('{{ALL_LABEL}}', $allLabelJs, $jsFilter);
    $page->addJavascript("\n".$jsFilter."\n", true);

    $filterNavbar->addForm($filterForm->show());
    $page->addHtml($filterNavbar->show());
}

$serverParams = array(
'filter_group' => $getGroup,
'filter_user' => $getUser,
'filter_active' => $getActive,
'q' => $getQ
// 'date_from' => $getDateFrom,
// 'date_to' => $getDateTo
);

$serverUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/devices/list_data.php', $serverParams);


$tableHeaderStyle = '#table_re_devices thead{border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;background-color:#fff;}#table_re_devices thead th{font-weight:700;color:#495057;padding:12px 15px;white-space:nowrap;border:none;}';
$tableHeaderStyle .= '
#filter_active_group.form-switch {padding-left: 0 !important;}
#filter_active_group.form-switch .form-check-input {margin-left: 0 !important;background-image: none !important;}
input.re-filter-checkbox {position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;margin:0;}
.re-check-square-checked {display: none;margin-right:0.35rem;line-height:1;font-size:1.15em;vertical-align:-0.1em;}
.re-check-square-unchecked {
    display: inline-block;
    width: 1.15em;
    height: 1.15em;
    border: 2px solid currentColor;
    border-radius: 0.15em;
    vertical-align: middle;
    margin-right: 0.35rem;
}
#filter_active_group input.re-filter-checkbox:checked + label .re-check-square-checked { display: inline-block !important;}
#filter_active_group input.re-filter-checkbox:checked + label .re-check-square-unchecked {display: none !important;}
#filter_active_group .form-check-label {cursor: pointer; display: inline-flex; align-items: center;}';
$tableHeaderStyle .= '#table_re_devices_wrapper .dataTables_length,#table_re_devices_length{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}'
. '#table_re_devices_wrapper .dataTables_length label,#table_re_devices_length label{margin-bottom:0;display:flex;align-items:center;gap:0.35rem;white-space:nowrap;}';
$tableHeaderStyle .= '#table_re_devices_wrapper .dataTables_length select,#table_re_devices_length select{width:auto;min-width:70px;display:inline-block;}';
$tableHeaderStyle .= '#table_re_devices thead th:nth-last-child(2){padding-right:34px;}';
$tableHeaderStyle .= '#table_re_devices thead th:last-child{padding-left:22px;padding-right:22px;}';
$tableHeaderStyle .= '#table_re_devices_filter{display:none!important;}';
$tableHeaderStyle .= '#device_filter #q_group { flex: 0 0 auto;}';
$tableHeaderStyle .= '#device_filter #filter_active_group { align-content: center;}';
if ($isAdmin) {
    $tableHeaderStyle .= '#table_re_devices thead th:first-child:before,#table_re_devices thead th:first-child:after{display:none!important;}';
}
$page->addHtml('<style>'.$tableHeaderStyle.'</style>');

$table = new HtmlTable('table_re_devices', $page, true, true, 'table table-hover align-middle');
$table->setServerSideProcessing($serverUrl);
$table->setDatatablesRowsPerPage($defaultPageLength);
$table->setDatatablesOrderColumns(array(array(2, 'desc')));
$table->disableDatatablesColumnsSort(array(1,10));
$table->setColumnAlignByArray(array('center', 'left', 'left', 'left', 'left', 'left', 'left', 'left', 'left', 'left'));
$table->addRowHeadingByArray(array(
'<input type="checkbox" id="re-select-all-devices" />',
$gL10n->get('RE_DEVICE_NUMBER'),
$gL10n->get('RE_USER'),
$gL10n->get('RE_DEVICE_ID'),
$gL10n->get('RE_DEVICE_ACTIVE'),
$gL10n->get('RE_DEVICE_ACTIVE_DATE'),
$gL10n->get('RE_DEVICE_PLATFORM'),
$gL10n->get('RE_DEVICE_BRAND'),
$gL10n->get('RE_DEVICE_MODEL'),
$gL10n->get('RE_ACTIONS')
));

if ($isAdmin) {
    $bulkDeleteUrlDev = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/devices/delete_all.php');
    $bulkDeleteUrlDevJs = json_encode($bulkDeleteUrlDev);
    $devicesDeleteConfirm = json_encode($gL10n->get('RE_DELETE_DEVICE_CONFIRM'));
    $devicesDeleteError = json_encode('Error deleting selected devices');
    $deleteAllLabel = json_encode($gL10n->get('RE_DELETE_ALL'));

    $jsDevices = <<<'JS'
  $(function(){
    var bulkDeleteUrl = {{BULK_DELETE_URL}};
    var deleteConfirmMsg = {{DELETE_CONFIRM}};
    var deleteErrorMsg = {{DELETE_ERROR}};
    var deleteButtonLabel = {{DELETE_BUTTON_LABEL}};
    
    var tableEl = $('#table_re_devices');
    var dataTable = tableEl.DataTable();
    var wrapperEl = $('#table_re_devices_wrapper');
    function locateLengthContainer(){
      var wrapperEl = $('#table_re_devices_wrapper');
      var lengthEl = $('#table_re_devices_length');
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
      var buttonEl = $('#re-delete-selected-devices');
      if (buttonEl.length) {
        return buttonEl;
      }
      var newButtonEl = $('<button type="button" id="re-delete-selected-devices" class="btn btn-danger btn-sm ms-2"><i class="bi bi-trash"></i> ' + deleteButtonLabel + '</button>');
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
    function syncHeaderCheckboxDevices(){
      var total = tableEl.find('tbody input.re-row-select').length;
      var selected = tableEl.find('tbody input.re-row-select:checked').length;
      var hdr = $('#re-select-all-devices').get(0);
      if (!hdr) { return; }
      hdr.indeterminate = selected > 0 && selected < total;
      hdr.checked = total > 0 && selected === total;
    }
    tableEl.find('thead')
      .on('click', '#re-select-all-devices', function(e){ e.stopPropagation(); })
      .on('change', '#re-select-all-devices', function(e){
        e.stopPropagation();
        var checked = this.checked;
        tableEl.find('tbody input.re-row-select')
          .prop('checked', checked)
          .trigger('change');
        syncHeaderCheckboxDevices();
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
      syncHeaderCheckboxDevices();
      deleteButtonEl = getDeleteButton();
      updateDeleteButtonState();
    });
    tableEl.on('change', 'input.re-row-select', function(){
      updateInfo();
      syncHeaderCheckboxDevices();
      updateDeleteButtonState();
    });
    updateInfo();
    syncHeaderCheckboxDevices();
    updateDeleteButtonState();
  });
  JS;
    $jsDevices = strtr($jsDevices, array(
    '{{BULK_DELETE_URL}}' => $bulkDeleteUrlDevJs,
    '{{DELETE_CONFIRM}}' => $devicesDeleteConfirm,
    '{{DELETE_ERROR}}' => $devicesDeleteError,
    '{{DELETE_BUTTON_LABEL}}' => $deleteAllLabel,
    ));
    $page->addJavascript("\n".$jsDevices."\n", true);
}

$page->addHtml($table->show(false));
