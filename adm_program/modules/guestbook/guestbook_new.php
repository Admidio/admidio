<?php
/**
 ***********************************************************************************************
 * Create and edit guestbook entries
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id         - Id of one guestbook entry that should be shown
 * headline   - Title of the guestbook module. This will be shown in the whole module.
 *              (Default) GBO_GUESTBOOK
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id',       'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// set headline of the script
if ($getGboId > 0)
{
    $headline = $gL10n->get('GBO_EDIT_ENTRY', $getHeadline);
}
else
{
    $headline = $gL10n->get('GBO_CREATE_VAR_ENTRY', $getHeadline);
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// Gaestebuchobjekt anlegen
$guestbook = new TableGuestbook($gDb);

if($getGboId > 0)
{
    // Falls ein Eintrag bearbeitet werden soll muss geprueft weden ob die Rechte gesetzt sind...
    require('../../system/login_valid.php');

    if (!$gCurrentUser->editGuestbookRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $guestbook->readDataById($getGboId);

    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if($guestbook->getValue('gbo_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

// Wenn keine ID uebergeben wurde, der User aber eingeloggt ist koennen zumindest
// Name, Emailadresse und Homepage vorbelegt werden...
if ($getGboId === 0 && $gValidLogin)
{
    $guestbook->setValue('gbo_name', $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'));
    $guestbook->setValue('gbo_email', $gCurrentUser->getValue('EMAIL'));
    $guestbook->setValue('gbo_homepage', $gCurrentUser->getValue('WEBSITE'));
}

if(isset($_SESSION['guestbook_entry_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $guestbook->setArray($_SESSION['guestbook_entry_request']);
    unset($_SESSION['guestbook_entry_request']);
}

if (!$gValidLogin && $gPreferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT COUNT(*)
              FROM '.TBL_GUESTBOOK.'
             WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
               AND gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               AND gbo_ip_address = \''. $guestbook->getValue('gbo_ip_address'). '\'';
    $statement = $gDb->query($sql);
    $row = $statement->fetch();
    if($row[0] > 0)
    {
        // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
        $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
    }
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$guestbookCreateMenu = $page->getMenu();
$guestbookCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// Html des Modules ausgeben
if ($getGboId > 0)
{
    $mode = '3';
}
else
{
    $mode = '1';
}

// show form
$form = new HtmlForm('guestbook_edit_form', $g_root_path.'/adm_program/modules/guestbook/guestbook_function.php?id='. $getGboId. '&amp;headline='. $getHeadline. '&amp;mode='.$mode, $page);
if ($gCurrentUser->getValue('usr_id') > 0)
{
    // registered users should not change their name
    $form->addInput('gbo_name', $gL10n->get('SYS_NAME'), $guestbook->getValue('gbo_name'), array('maxLength' => 60, 'property' => FIELD_DISABLED));
}
else
{
    $form->addInput('gbo_name', $gL10n->get('SYS_NAME'), $guestbook->getValue('gbo_name'), array('maxLength' => 60, 'property' => FIELD_REQUIRED));
}
$form->addInput('gbo_email', $gL10n->get('SYS_EMAIL'), $guestbook->getValue('gbo_email'), array('type' => 'email', 'maxLength' => 50));
$form->addInput('gbo_homepage', $gL10n->get('SYS_WEBSITE'), $guestbook->getValue('gbo_homepage'), array('maxLength' => 50));
$form->addEditor('gbo_text', $gL10n->get('SYS_MESSAGE'), $guestbook->getValue('gbo_text'), array('property' => FIELD_REQUIRED, 'toolbar' => 'AdmidioGuestbook'));

// if captchas are enabled then visitors of the website must resolve this
if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
{
    $form->openGroupBox('gb_confirmation_of_entry', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha', $gPreferences['captcha_type']);
    $form->closeGroupBox();
}

// show information about user who creates the recordset and changed it
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($guestbook->getValue('gbo_usr_id_create'), $guestbook->getValue('gbo_timestamp_create'), $guestbook->getValue('gbo_usr_id_change'), $guestbook->getValue('gbo_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
