<?php
/******************************************************************************
 * Login page
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');

$headline = $gL10n->get('SYS_LOGIN');

// remember url (will be removed in cookie_check)
$gNavigation->addUrl(CURRENT_URL, $headline);

// read id of webmaster role
$sql = 'SELECT rol_id FROM '.TBL_ROLES.', '.TBL_CATEGORIES.'
         WHERE rol_name LIKE \''.$gL10n->get('SYS_WEBMASTER').'\'
           AND rol_webmaster = 1
           AND rol_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
               OR cat_org_id IS NULL )';
$gDb->query($sql);
$row = $gDb->fetch_array();

// create role object for webmaster
$roleWebmaster = new TableRoles($gDb, $row['rol_id']);

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$loginMenu = $page->getMenu();
$loginMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('login_form', $g_root_path.'/adm_program/system/login_check.php', $page, array('showRequiredFields' => false));

$form->addInput('usr_login_name', $gL10n->get('SYS_USERNAME'), null,
                array('maxLength' => 35, 'property' => FIELD_REQUIRED, 'class' => 'form-control-small'));
$form->addInput('usr_password', $gL10n->get('SYS_PASSWORD'), null,
                array('type' => 'password', 'property' => FIELD_REQUIRED, 'class' => 'form-control-small'));

// show selectbox with all organizations of database
if($gPreferences['system_organization_select'] == 1)
{
    $sql = 'SELECT org_id, org_longname FROM '.TBL_ORGANIZATIONS.' ORDER BY org_longname ASC, org_shortname ASC';
    $form->addSelectBoxFromSql('org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql,
                               array('property' => FIELD_REQUIRED, 'defaultValue' => $gCurrentOrganization->getValue('org_id')));
}

if($gPreferences['enable_auto_login'] == 1)
{
    $form->addCheckbox('auto_login', $gL10n->get('SYS_REMEMBER_ME'), '0');
}
$form->addSubmitButton('btn_login', $gL10n->get('SYS_LOGIN'), array('icon' => THEME_PATH.'/icons/key.png'));
$page->addHtml($form->show(false));

if($gPreferences['registration_mode'] > 0)
{
    $page->addHtml('<div id="login_registration_link">
        <small><a href="'.$g_root_path.'/adm_program/modules/registration/registration.php">'.$gL10n->get('SYS_WANT_REGISTER').'</a></small>
    </div>');
}

// Link bei Loginproblemen
if($gPreferences['enable_password_recovery'] == 1 && $gPreferences['enable_system_mails'] == 1)
{
    // neues Passwort zusenden
    $mail_link = $g_root_path.'/adm_program/system/lost_password.php';
}
elseif($gPreferences['enable_mail_module'] == 1 && $roleWebmaster->getValue('rol_mail_this_role') == 3)
{
    // Mailmodul aufrufen mit Webmaster als Ansprechpartner
    $mail_link = $g_root_path.'/adm_program/modules/messages/messages_write.php?rol_id='.$roleWebmaster->getValue('rol_id').'&amp;subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
}
else
{
    // direkte Mail an den Webmaster ueber einen externen Mailclient
    $mail_link = 'mailto:'.$gPreferences['email_administrator'].'?subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
}

$page->addHtml('<div id="login_forgot_password_link">
    <small><a href="'.$mail_link.'">'.$gL10n->get('SYS_FORGOT_MY_PASSWORD').'</a></small>
</div>
<div id="login_admidio_link">
    <small>Powered by <a href="http://www.admidio.org">Admidio</a></small>
</div>');

$page->show();

?>
