<?php
/**
 ***********************************************************************************************
 * Create and edit guestbook entries
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * gbo_uuid   - UUID of one guestbook entry that should be shown
 * headline   - Title of the guestbook module. This will be shown in the whole module.
 *              (Default) GBO_GUESTBOOK
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getGboUuid  = admFuncVariableIsValid($_GET, 'gbo_uuid', 'string');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_guestbook_module') === 0) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_guestbook_module') === 2) {
    // only logged in users can access the module
    require(__DIR__ . '/../../system/login_valid.php');
}

// set headline of the script
if ($getGboUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_ENTRY');
} else {
    $headline = $gL10n->get('SYS_WRITE_ENTRY');
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// Gaestebuchobjekt anlegen
$guestbook = new TableGuestbook($gDb);

if ($getGboUuid !== '') {
    // Falls ein Eintrag bearbeitet werden soll muss geprueft weden ob die Rechte gesetzt sind...
    require(__DIR__ . '/../../system/login_valid.php');

    if (!$gCurrentUser->editGuestbookRight()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $guestbook->readDataByUuid($getGboUuid);

    // Check if the entry belongs to the current organization
    if ((int) $guestbook->getValue('gbo_org_id') !== $gCurrentOrgId) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

// If no ID was passed, but the user is logged in, at least the following can be done
// name, email address and homepage can be preset...
if ($getGboUuid === '' && $gValidLogin) {
    $guestbook->setValue('gbo_name', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
    $guestbook->setValue('gbo_email', $gCurrentUser->getValue('EMAIL'));
    $guestbook->setValue('gbo_homepage', $gCurrentUser->getValue('WEBSITE'));
}

if (isset($_SESSION['guestbook_entry_request'])) {
    // due to a wrong input the user has returned to this form, now write the previously entered content into the object
    $guestbookDescription = admFuncVariableIsValid($_SESSION['guestbook_entry_request'], 'gbo_text', 'html');
    $guestbook->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['guestbook_entry_request'])));
    $guestbook->setValue('gbo_text', $guestbookDescription);
    unset($_SESSION['guestbook_entry_request']);
}

if (!$gValidLogin && $gSettingsManager->getInt('flooding_protection_time') > 0) {
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT COUNT(*) AS count
              FROM '.TBL_GUESTBOOK.'
             WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp() - ? -- $gSettingsManager->getInt(\'flooding_protection_time\')
               AND gbo_org_id     = ? -- $gCurrentOrgId
               AND gbo_ip_address = ? -- $guestbook->getValue(\'gbo_ip_address\')';
    $queryParams = array($gSettingsManager->getInt('flooding_protection_time'), $gCurrentOrgId, $guestbook->getValue('gbo_ip_address'));
    $pdoStatement = $gDb->queryPrepared($sql, $queryParams);

    if ($pdoStatement->fetchColumn() > 0) {
        // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
        $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', array($gSettingsManager->getInt('flooding_protection_time'))));
        // => EXIT
    }
}

// create html page object
$page = new HtmlPage('admidio-guestbook-new', $getHeadline . ' - ' . $headline);

// Html des Modules ausgeben
if ($getGboUuid !== '') {
    $mode = '3';
} else {
    $mode = '1';
}

// show form
$form = new HtmlForm('guestbook_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_function.php', array('gbo_uuid' => $getGboUuid, 'headline' => $getHeadline, 'mode' => $mode)), $page);
if ($gCurrentUserId > 0) {
    // registered users should not change their name
    $form->addInput(
        'gbo_name',
        $gL10n->get('SYS_NAME'),
        $guestbook->getValue('gbo_name'),
        array('maxLength' => 60, 'property' => HtmlForm::FIELD_DISABLED)
    );
} else {
    $form->addInput(
        'gbo_name',
        $gL10n->get('SYS_NAME'),
        $guestbook->getValue('gbo_name'),
        array('maxLength' => 60, 'property' => HtmlForm::FIELD_REQUIRED)
    );
}
$form->addInput(
    'gbo_email',
    $gL10n->get('SYS_EMAIL'),
    $guestbook->getValue('gbo_email'),
    array('type' => 'email', 'maxLength' => 254)
);
$form->addInput(
    'gbo_homepage',
    $gL10n->get('SYS_WEBSITE'),
    $guestbook->getValue('gbo_homepage'),
    array('maxLength' => 50)
);
$form->addEditor(
    'gbo_text',
    $gL10n->get('SYS_MESSAGE'),
    $guestbook->getValue('gbo_text'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'toolbar' => 'AdmidioGuestbook')
);

// if captchas are enabled then visitors of the website must resolve this
if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha')) {
    $form->openGroupBox('gb_confirmation_of_entry', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha_code');
    $form->closeGroupBox();
}

// show information about user who creates the recordset and changed it
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $guestbook->getValue('gbo_usr_id_create'),
    $guestbook->getValue('gbo_timestamp_create'),
    (int) $guestbook->getValue('gbo_usr_id_change'),
    $guestbook->getValue('gbo_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
