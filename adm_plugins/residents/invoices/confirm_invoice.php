<?php
/**
 ***********************************************************************************************
 * Intermediate form that collects scheduling details before previewing or generating invoices
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gMessage;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isResidentsAdminBySettings()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$modeRaw = strtolower(trim((string)admFuncVariableIsValid($_REQUEST, 'mode', 'string')));
$mode = $modeRaw === 'generate' ? 'generate' : 'preview';

$cfg = residentsReadConfig();
$configuredDefaultNote = residentsGetDefaultInvoiceNote($cfg);

$filters = array(
    'filter_group' => admFuncVariableIsValid($_REQUEST, 'filter_group', 'int'),
    'filter_user' => admFuncVariableIsValid($_REQUEST, 'filter_user', 'int'),
    'filter_status' => admFuncVariableIsValid($_REQUEST, 'filter_status', 'string'),
    'date_from' => admFuncVariableIsValid($_REQUEST, 'date_from', 'date'),
    'date_to' => admFuncVariableIsValid($_REQUEST, 'date_to', 'date'),
    'q' => admFuncVariableIsValid($_REQUEST, 'q', 'string')
);

$incomingInvoiceDate = admFuncVariableIsValid($_REQUEST, 'invoice_date', 'date');
$defaultStart = $filters['date_from'] !== '' ? $filters['date_from'] : date('Y-m-01');
$defaultInvoice = $incomingInvoiceDate !== '' ? $incomingInvoiceDate : date('Y-m-d');
$defaultNote = $configuredDefaultNote;

$startDateValue = $defaultStart;
$invoiceDateValue = $defaultInvoice;
$noteValue = $defaultNote;
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        SecurityUtils::validateCsrfToken((string)($_POST['adm_csrf_token'] ?? ''));
    } catch (Throwable $e) {
        $gMessage->show($e->getMessage());
    }

    $startDateValue = admFuncVariableIsValid($_POST, 'start_date', 'date');
    $invoiceDateValue = admFuncVariableIsValid($_POST, 'invoice_date', 'date');
    $noteValue = trim((string)($_POST['note'] ?? $defaultNote));

    $action = 'auto';
    if (isset($_POST['re_preview'])) {
        $action = 'preview';
    } elseif (isset($_POST['re_generate'])) {
        $action = 'generate';
    }

    if ($startDateValue === '') {
        $errors[] = $gL10n->get('RE_START_DATE') . ': ' . $gL10n->get('SYS_FIELD_EMPTY');
    }
    if ($invoiceDateValue === '') {
        $errors[] = $gL10n->get('RE_DATE') . ': ' . $gL10n->get('SYS_FIELD_EMPTY');
    }

    if (empty($errors)) {
        $listParams = array('tab' => 'invoices');
        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $listParams[$key] = $value;
    }

        // Redirect based on chosen action. If the page was opened with a fixed mode,
        // fall back to that mode when no explicit button is provided.
        $effectiveAction = $action === 'auto' ? $mode : $action;

        if ($effectiveAction === 'preview') {
            $previewParams = $listParams + array(
        'preview' => 1,
        'preview_start_date' => $startDateValue,
        'preview_invoice_date' => $invoiceDateValue
            );
            if ($noteValue !== '') {
                $previewParams['preview_note'] = $noteValue;
            }
            $redirectUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $previewParams);
            header('Location: ' . $redirectUrl);
            exit;
    }

        $generateParams = array(
            'mode' => 'generate',
            'group' => (int)$filters['filter_group'],
            'start_date' => $startDateValue,
            'invoice_date' => $invoiceDateValue
        );
        if ($noteValue !== '') {
            $generateParams['note'] = $noteValue;
    }
        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $generateParams[$key] = $value;
    }
        $redirectUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/generate.php', $generateParams);
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$pageTitle = $mode === 'generate'
    ? $gL10n->get('RE_GENERATE_BILL')
    : $gL10n->get('RE_PREVIEW_LABEL');
$page = new HtmlPage('re-run', $pageTitle);
$page->setHeadline($pageTitle);
residentsEnqueueStyles($page);

if (!empty($errors)) {
    $page->addHtml('<div class="alert alert-danger">' . implode('<br />', array_map('htmlspecialchars', $errors)) . '</div>');
}

$page->addHtml('<p class="lead">' . htmlspecialchars($gL10n->get('RE_CONFIRM_DETAILS_INTRO')) . '</p>');

$formActionParams = array('mode' => $mode);
foreach ($filters as $key => $value) {
    if ($value === '' || $value === null) {
        continue;
    }
    $formActionParams[$key] = $value;
}
$formAction = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/confirm_invoice.php', $formActionParams);

$form = new HtmlForm('re_run_form', $formAction, $page);
$form->addInput('mode', '', $mode, array('property' => HtmlForm::FIELD_HIDDEN));
// Visible filters: Group & User (others remain hidden if present)
// Build role and user options
$rolesOptions = residentsGetRoleOptions();
$rolesOptions = array('0' => $gL10n->get('RE_ALL')) + $rolesOptions;
$selectedGroup = max(0, (int)($filters['filter_group'] ?? 0));
$selectedUser = max(0, (int)($filters['filter_user'] ?? 0));
$userOptions = residentsGetOwnerOptions($selectedGroup);
if ($selectedUser > 0 && !isset($userOptions[$selectedUser])) {
    $userOptions[$selectedUser] = residentsFetchUserNameById($selectedUser);
}
$userOptions = array('0' => $gL10n->get('RE_ALL')) + $userOptions;

$form->addSelectBox(
    'filter_group',
    $gL10n->get('RE_GROUPS_ROLES'),
    $rolesOptions,
    array('defaultValue' => (string)$selectedGroup, 'showContextDependentFirstEntry' => false)
);

$form->addSelectBox(
    'filter_user',
    $gL10n->get('RE_USER'),
    $userOptions,
    array('defaultValue' => (string)$selectedUser, 'showContextDependentFirstEntry' => false)
);

// Keep other filters (status, dates, query) as hidden to preserve navigation context
foreach ($filters as $key => $value) {
    if ($key === 'filter_group' || $key === 'filter_user') {
        continue; // now visible
    }
    if ($value === '' || $value === null) {
        continue;
    }
    $form->addInput($key, '', (string)$value, array('property' => HtmlForm::FIELD_HIDDEN));
}
$form->addInput('start_date', $gL10n->get('RE_START_DATE'), $startDateValue, array(
    'type' => 'date',
    'property' => HtmlForm::FIELD_REQUIRED
));
$form->addInput('invoice_date', $gL10n->get('RE_DATE'), $invoiceDateValue, array(
    'type' => 'date',
    'property' => HtmlForm::FIELD_REQUIRED
));
$form->addMultilineTextInput('note', $gL10n->get('RE_NOTES'), $noteValue, 3);

// Show both actions on the confirm page; keep Preview button label unchanged.
$form->addSubmitButton('re_preview', $gL10n->get('RE_PREVIEW_LABEL'), array('icon' => 'fa-eye', 'class' => 'btn btn-secondary'));
$form->addSubmitButton('re_generate', $gL10n->get('RE_GENERATE_BILL'), array('icon' => 'fa-file-invoice-dollar', 'class' => 'btn btn-primary'));

$cancelParams = array('tab' => 'invoices');
foreach ($filters as $key => $value) {
    if ($value === '' || $value === null) {
        continue;
    }
    $cancelParams[$key] = $value;
}
$form->addButton('re_run_cancel', $gL10n->get('SYS_CANCEL'), array(
    'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $cancelParams),
    'class' => 'btn btn-link'
));

$page->addHtml($form->show(false));

// Add JS to reload users when group changes
$loadUsersUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/load_users.php');
$loadUsersUrlJs = json_encode($loadUsersUrl);
$jsConfirmDetails = <<<'JS'
  $(function(){
      var loadUsersUrl = {{LOAD_USERS_URL}};
      var allLabel = {{ALL_LABEL}};
      var generateConfirm = {{GENERATE_CONFIRM}};
      $('select[name=filter_group]').on('change', function(){
        var groupId = $(this).val();
        var $userSel = $('#filter_user');
        if (!$userSel.length) { return; }
        $userSel.val('0').prop('disabled', true);
        $.ajax({
          type: 'GET',
          url: loadUsersUrl,
          data: { group_id: groupId },
          dataType: 'json',
          success: function(data){
            $userSel.empty();
            $userSel.append($('<option>', { value: '0', text: allLabel }));
            $.each(data, function(key, value){
              $userSel.append($('<option>', { value: key, text: value }));
          });
            $userSel.val('0').prop('disabled', false).trigger('change');
        },
          error: function(){
            $userSel.prop('disabled', false);
        }
      });
    });

      $(document).on('click', '#re_generate, button[name=re_generate], input[name=re_generate]', function(e){
        if (!confirm(generateConfirm)) {
          e.preventDefault();
          e.stopPropagation();
          return false;
      }
        return true;
    });
  });
JS;
$jsConfirmDetails = str_replace(
    array('{{LOAD_USERS_URL}}', '{{ALL_LABEL}}', '{{GENERATE_CONFIRM}}'),
    array($loadUsersUrlJs, json_encode($gL10n->get('RE_ALL')), json_encode($gL10n->get('RE_GENERATE_CONFIRM'))),
    $jsConfirmDetails
);
$tableHeaderStyle .= '.admidio-form-required-notice {
    font-size: 9pt;
    margin: 0.5rem 0;
    text-align: start;
    width: 100%;
    display: inline-block;
    max-width: 1000px;
}';
$page->addJavascript("\n".$jsConfirmDetails."\n", true);
$page->addHtml('<div style="height: 50px;"></div>');
$page->addHtml('<style>'.$tableHeaderStyle.'</style>');
$page->show();
