<?php
/**
 ***********************************************************************************************
 * Login page
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

$headline = $gL10n->get('SYS_LOGIN');

// remember url (will be removed in login_check)
$gNavigation->addUrl(CURRENT_URL, $headline);

// read id of administrator role
$sql = 'SELECT rol_id
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')
           AND rol_administrator = 1
           AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
               OR cat_org_id IS NULL )';
$pdoStatement = $gDb->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR'), (int) $gCurrentOrganization->getValue('org_id')));

// create role object for administrator
$roleAdministrator = new TableRoles($gDb, $pdoStatement->fetchColumn());

// create html page object
$page = new HtmlPage('admidio-login', $headline);

// show form
$form = new HtmlForm('login_form', ADMIDIO_URL.'/adm_program/system/login_check.php', $page, array('showRequiredFields' => false));

$form->addInput(
    'usr_login_name', $gL10n->get('SYS_USERNAME'), '',
    array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
);
$form->addInput(
    'usr_password', $gL10n->get('SYS_PASSWORD'), '',
    array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
);

// show selectbox with all organizations of database
if($gSettingsManager->getBool('system_organization_select'))
{
    $sql = 'SELECT org_id, org_longname
              FROM '.TBL_ORGANIZATIONS.'
          ORDER BY org_longname ASC, org_shortname ASC';
    $form->addSelectBoxFromSql(
        'org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql,
        array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => (int) $gCurrentOrganization->getValue('org_id'), 'class' => 'form-control-small')
    );
}

if($gSettingsManager->getBool('enable_auto_login'))
{
    $form->addCheckbox('auto_login', $gL10n->get('SYS_REMEMBER_ME'));
}
$form->addSubmitButton('btn_login', $gL10n->get('SYS_LOGIN'), array('icon' => 'fa-key', 'class' => ' offset-sm-3'));
$page->addHtml($form->show());

if($gSettingsManager->getBool('registration_enable_module'))
{
    $page->addHtml('
        <div id="login_registration_link">
            <small>
                <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php">'.$gL10n->get('SYS_WANT_REGISTER').'</a>
            </small>
        </div>');
}

// Link bei Loginproblemen
if($gSettingsManager->getBool('enable_password_recovery') && $gSettingsManager->getBool('enable_system_mails'))
{
    // neues Passwort zusenden
    $forgotPasswordLink = ADMIDIO_URL.'/adm_program/system/lost_password.php';
}
elseif($gSettingsManager->getBool('enable_mail_module') && $roleAdministrator->getValue('rol_mail_this_role') == 3)
{
    // show link of message module to send mail to administrator role
    $forgotPasswordLink = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('rol_id' => (int) $roleAdministrator->getValue('rol_id'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
}
else
{
    // show link to send mail with local mail-client to administrator
    $forgotPasswordLink = SecurityUtils::encodeUrl('mailto:'.$gSettingsManager->getString('email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
}

$page->addHtml('
    <div id="login_forgot_password_link">
        <small><a href="'.$forgotPasswordLink.'">'.$gL10n->get('SYS_FORGOT_MY_PASSWORD').'</a></small>
    </div>
    <div id="login_admidio_link">
        <small>Powered by <a href="'.ADMIDIO_HOMEPAGE.'">Admidio</a></small>
    </div>');

$page->show();
