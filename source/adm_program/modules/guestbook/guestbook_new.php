<?php
/******************************************************************************
 * Create and edit guestbook entries
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * id         - Id of one guestbook entry that should be shown
 * headline   - Title of the guestbook module. This will be shown in the whole module.
 *              (Default) GBO_GUESTBOOK
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('GBO_GUESTBOOK'));

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

$gNavigation->addUrl(CURRENT_URL);

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
if ($getGboId == 0 && $gValidLogin)
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

    $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK. '
             WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
               AND gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               AND gbo_ip_address = \''. $guestbook->getValue('gbo_ip_address'). '\'';
    $result = $gDb->query($sql);
    $row = $gDb->fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
    }
}

// Html-Kopf ausgeben
if ($getGboId > 0)
{
    $gLayout['title'] = $gL10n->get('GBO_EDIT_ENTRY', $getHeadline);
}
else
{
    $gLayout['title'] = $gL10n->get('GBO_CREATE_VAR_ENTRY', $getHeadline);
}

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// show back link
echo $gNavigation->getHtmlBackButton();

// show headline of module
echo '<h1 class="admHeadline">'.$gLayout['title'].'</h1>';

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
$form = new HtmlForm('guestbook_edit_form', $g_root_path.'/adm_program/modules/guestbook/guestbook_function.php?id='. $getGboId. '&amp;headline='. $getHeadline. '&amp;mode='.$mode);
$form->openGroupBox('gb_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));
if ($gCurrentUser->getValue('usr_id') > 0)
{
    // registered users should not change their name
    $form->addTextInput('gbo_name', $gL10n->get('SYS_NAME'), $guestbook->getValue('gbo_name'), 60, FIELD_DISABLED);
}
else
{
    $form->addTextInput('gbo_name', $gL10n->get('SYS_NAME'), $guestbook->getValue('gbo_name'), 60, FIELD_MANDATORY);
}
$form->addTextInput('gbo_email', $gL10n->get('SYS_EMAIL'), $guestbook->getValue('gbo_email'), 50);
$form->addTextInput('gbo_homepage', $gL10n->get('SYS_WEBSITE'), $guestbook->getValue('gbo_homepage'), 50);
$form->closeGroupBox();
$form->openGroupBox('gb_message', $gL10n->get('SYS_MESSAGE'));
$form->addEditor('gbo_text', null, $guestbook->getValue('gbo_text'), FIELD_MANDATORY, 'AdmidioGuestbook');
$form->closeGroupBox();

// if captchas are enabled then visitors of the website must resolve this
if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
{
    $form->openGroupBox('gb_confirmation_of_entry', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha', $gPreferences['captcha_type']);
    $form->closeGroupBox();
}

// show informations about user who creates the recordset and changed it
$form->addString(admFuncShowCreateChangeInfoById($guestbook->getValue('gbo_usr_id_create'), $guestbook->getValue('gbo_timestamp_create'), $guestbook->getValue('gbo_usr_id_change'), $guestbook->getValue('gbo_timestamp_change')));
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
$form->show();

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>
