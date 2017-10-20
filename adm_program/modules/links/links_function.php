<?php
/**
 ***********************************************************************************************
 * Several functions for weblinks module
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * lnk_id  - ID of the weblink that should be edited
 * mode    - 1 : Create new link
 *           2 : Delete link
 *           3 : Edit link
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getLinkId = admFuncVariableIsValid($_GET, 'lnk_id', 'int');
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

// check if the module is enabled for use
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// create weblink object
$link = new TableWeblink($gDb);

if($getLinkId > 0)
{
    $link->readDataById($getLinkId);

    // check if the current user could edit this weblink
    if(!$link->editable())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

$_SESSION['links_request'] = $_POST;

if ($getMode === 1 || ($getMode === 3 && $getLinkId > 0))
{
    if(strlen(strStripTags($_POST['lnk_name'])) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('LNK_LINK_NAME')));
        // => EXIT
    }
    if(strlen(strStripTags($_POST['lnk_url'])) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('LNK_LINK_ADDRESS')));
        // => EXIT
    }
    if(strlen($_POST['lnk_cat_id']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
        // => EXIT
    }

    // make html in description secure
    $_POST['lnk_description'] = admFuncVariableIsValid($_POST, 'lnk_description', 'html');

    try
    {
        // POST Variablen in das Ankuendigungs-Objekt schreiben
        foreach($_POST as $key => $value) // TODO possible security issue
        {
            if(strpos($key, 'lnk_') === 0)
            {
                $link->setValue($key, $value);
            }
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    // Link-Counter auf 0 setzen
    if ($getMode === 1)
    {
        $link->setValue('lnk_counter', '0');
    }

    // Daten in Datenbank schreiben
    $returnCode = $link->save();

    if($returnCode === false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    if($returnCode === true && $getMode === 1)
    {
        // Benachrichtigungs-Email für neue Einträge
        $message = $gL10n->get('LNK_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['lnk_url']. ' ('.$_POST['lnk_name'].')', $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date']));
        try
        {
            $notification = new Email();
            $notification->adminNotification($gL10n->get('LNK_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
        }
        catch(AdmException $e)
        {
            $e->showHtml();
        }
    }

    unset($_SESSION['links_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}
elseif ($getMode === 2 && $getLinkId > 0)
{
    // Loeschen von Weblinks...
    $link->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
else
{
    // Falls der mode unbekannt ist, ist natürlich Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}
