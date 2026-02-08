<?php
/**
 ***********************************************************************************************
 * Create or edit a charge definition.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isResidentsAdminBySettings()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$chargeId = admFuncVariableIsValid($_GET, 'id', 'int');
$rolesOptions = residentsGetRoleOptions();
$rawPeriodOptions = \Admidio\Roles\Entity\Role::getCostPeriods();
$periodOptions = array();
foreach ($rawPeriodOptions as $key => $label) {
    if ((int)$key === -1) {
        continue;
    }
    $periodOptions[(string)$key] = $label;
}

$charge = new TableResidentsCharge($gDb, $chargeId);
if ($chargeId > 0 && $charge->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Organization check: charge must belong to current organization
if ($chargeId > 0) {
    residentsValidateOrganization($charge, 'rch_org_id');
}

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chargeId = (int)($_POST['charge_id'] ?? $chargeId);
    if ($chargeId !== (int)$charge->getValue('rch_id')) {
        $charge = new TableResidentsCharge($gDb, $chargeId);
        if ($chargeId > 0 && $charge->isNewRecord()) {
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    }
    $name = trim((string)($_POST['charge_name'] ?? ''));
    $period = trim((string)($_POST['charge_period'] ?? ''));
    $amountRaw = trim((string)($_POST['charge_amount'] ?? ''));
    $rolesSelected = isset($_POST['charge_roles']) ? array_map('intval', (array)$_POST['charge_roles']) : array();

    if ($period !== '' && !array_key_exists($period, $periodOptions)) {
        $period = '';
    }

    if ($name === '') {
        $errors[] = $gL10n->get('RE_CHARGERS_NAME_REQUIRED');
    }
    if ($amountRaw === '' || !is_numeric($amountRaw)) {
        $errors[] = $gL10n->get('RE_CHARGERS_AMOUNT_REQUIRED');
    }
    if ($amountRaw !== '' && is_numeric($amountRaw) && (float)$amountRaw <= 0) {
        $errors[] = $gL10n->get('RE_VALIDATION_AMOUNT_POSITIVE');
    }


    if (empty($errors)) {
        $charge->setValue('rch_name', $name);
        $charge->setValue('rch_period', $period);
        $charge->setAmountFromString($amountRaw);
        $charge->setRoleIds($rolesSelected);
        
        if ($charge->isNewRecord()) {
            $charge->setValue('rch_org_id', $gCurrentOrgId);
    }
        
        $saved = $charge->save();
        if ($saved || ($chargeId > 0 && !$saved)) {
        // For edits, save() can return false when no columns changed.
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'chargers', 'charge_status' => 'saved')));
    }
        $errors[] = 'Failed to save charge. Please try again.';
    }

    $charge->setValue('rch_name', $name);
    $charge->setValue('rch_period', $period);
    $charge->setValue('rch_amount', $amountRaw);
    $charge->setRoleIds($rolesSelected);
}

$pageTitle = $chargeId > 0 ? $gL10n->get('RE_CHARGERS_EDIT_TITLE') : $gL10n->get('RE_CHARGERS_ADD_TITLE');
$page = new HtmlPage('residents-charge-edit');
$page->setTitle($pageTitle);
$page->setHeadline($gL10n->get('RE_TAB_CHARGERS'));
$pageTitleJs = json_encode($pageTitle);
$jsChargeEdit = <<<JS
  $(function(){
      document.title = {$pageTitleJs};
  });
JS;
$page->addJavascript("\n".$jsChargeEdit."\n", true);
residentsEnqueueStyles($page);

if (!empty($errors)) {
    $page->addHtml('<div class="alert alert-danger">' . implode('<br />', array_map('htmlspecialchars', $errors)) . '</div>');
}

$formAction = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/charges/edit.php', $chargeId > 0 ? array('id' => $chargeId) : array());
$form = new HtmlForm('charge_edit_form', $formAction, $page);
$form->addInput('charge_id', '', (string)$charge->getValue('rch_id'), array('property' => HtmlForm::FIELD_HIDDEN));
$form->addInput('charge_name', $gL10n->get('RE_CHARGERS_NAME'), (string)$charge->getValue('rch_name'), array('maxLength' => 150, 'property' => HtmlForm::FIELD_REQUIRED));
$form->addSelectBox('charge_period', $gL10n->get('RE_CHARGERS_PERIOD'), $periodOptions, array(
    'defaultValue' => (string)$charge->getValue('rch_period'),
    'showContextDependentFirstEntry' => false
));
$form->addInput('charge_amount', $gL10n->get('RE_CHARGERS_AMOUNT'), (string)$charge->getValue('rch_amount'), array('type' => 'number', 'step' => '0.01', 'minNumber' => 0.01, 'property' => HtmlForm::FIELD_REQUIRED));
$form->addSelectBox('charge_roles', $gL10n->get('RE_CHARGERS_ROLES'), $rolesOptions, array(
    'defaultValue' => $charge->getRoleIds(),
    'multiselect' => true,
    'showContextDependentFirstEntry' => true
));
$form->addSubmitButton('charge_save', $gL10n->get('SYS_SAVE'));
$form->addButton('charge_cancel', $gL10n->get('SYS_CANCEL'), array('link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'chargers'))));

$page->addHtml($form->show(false));
$page->addHtml('<div style="height: 50px;"></div>');
$page->show();
