<?php
/**
 ***********************************************************************************************
 * Create and edit guestbook comments
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id            - ID des Eintrages, dem ein Kommentar hinzugefuegt werden soll
 * cid           - ID des Kommentars der editiert werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id',       'int');
$getGbcId    = admFuncVariableIsValid($_GET, 'cid',      'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Es muss ein (nicht zwei) Parameter uebergeben werden: Entweder id oder cid...
if($getGboId > 0 && $getGbcId > 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// set create or edit mode
if($getGboId > 0)
{
    $id       = $getGboId;
    $mode     = '4';
    $headline = $gL10n->get('GBO_CREATE_COMMENT');
}
else
{
    $id       = $getGbcId;
    $mode     = '8';
    $headline = $gL10n->get('GBO_EDIT_COMMENT');
}

// Erst einmal die Rechte abklopfen...
if(($gPreferences['enable_guestbook_module'] == 2 || $gPreferences['enable_gbook_comments4all'] == 0) && $getGboId > 0)
{
    // Falls anonymes kommentieren nicht erlaubt ist, muss der User eingeloggt sein zum kommentieren
    require_once('../../system/login_valid.php');

    if (!$gCurrentUser->commentGuestbookRight())
    {
        // der User hat kein Recht zu kommentieren
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if($getGbcId > 0)
{
    // Zum editieren von Kommentaren muss der User auch eingeloggt sein
    require_once('../../system/login_valid.php');

    if (!$gCurrentUser->editGuestbookRight())
    {
        // der User hat kein Recht Kommentare zu editieren
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Gaestebuchkommentarobjekt anlegen
$guestbook_comment = new TableGuestbookComment($gDb);

if($getGbcId > 0)
{
    $guestbook_comment->readDataById($getGbcId);

    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if((int) $guestbook_comment->getValue('gbo_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if(isset($_SESSION['guestbook_comment_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $guestbook_comment->setArray($_SESSION['guestbook_comment_request']);
    unset($_SESSION['guestbook_comment_request']);
}

// Wenn der User eingeloggt ist und keine cid uebergeben wurde
// koennen zumindest Name und Emailadresse vorbelegt werden...
if($getGbcId === 0 && $gValidLogin)
{
    $guestbook_comment->setValue('gbc_name', $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'));
    $guestbook_comment->setValue('gbc_email', $gCurrentUser->getValue('EMAIL'));
}

if (!$gValidLogin && $gPreferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT COUNT(*) AS count
              FROM '.TBL_GUESTBOOK_COMMENTS.'
             WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
               AND gbc_ip_address = \''. $guestbook_comment->getValue('gbc_ip_address'). '\'';
    $pdoStatement = $gDb->query($sql);

    if($pdoStatement->fetchColumn() > 0)
    {
        // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
        $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
        // => EXIT
    }
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$guestbookCommentCreateMenu = $page->getMenu();
$guestbookCommentCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('guestbook_comment_edit_form', ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_function.php?id='.$id.'&amp;headline='.$getHeadline.'&amp;mode='.$mode, $page);
if ($gCurrentUser->getValue('usr_id') > 0)
{
    // registered users should not change their name
    $form->addInput('gbc_name', $gL10n->get('SYS_NAME'), $guestbook_comment->getValue('gbc_name'), array('maxLength' => 60, 'property' => FIELD_DISABLED));
}
else
{
    $form->addInput('gbc_name', $gL10n->get('SYS_NAME'), $guestbook_comment->getValue('gbc_name'), array('maxLength' => 60, 'property' => FIELD_REQUIRED));
}
$form->addInput('gbc_email', $gL10n->get('SYS_EMAIL'), $guestbook_comment->getValue('gbc_email'), array('type' => 'email', 'maxLength' => 254));
$form->addEditor('gbc_text', $gL10n->get('SYS_COMMENT'), $guestbook_comment->getValue('gbc_text'), array('property' => FIELD_REQUIRED, 'toolbar' => 'AdmidioGuestbook'));

// if captchas are enabled then visitors of the website must resolve this
if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
{
    $form->openGroupBox('gb_confirmation_of_entry', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha_code');
    $form->closeGroupBox();
}

// show information about user who creates the recordset and changed it
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($guestbook_comment->getValue('gbc_usr_id_create'), $guestbook_comment->getValue('gbc_timestamp_create'), $guestbook_comment->getValue('gbc_usr_id_change'), $guestbook_comment->getValue('gbc_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
