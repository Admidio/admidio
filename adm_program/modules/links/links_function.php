<?php
/**
 ***********************************************************************************************
 * Several functions for weblinks module
 *
 * @copyright 2004-2018 The Admidio Team
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
if ((int) $gSettingsManager->get('enable_weblinks_module') === 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create weblink object
$link = new TableWeblink($gDb);

if($getLinkId > 0)
{
    $link->readDataById($getLinkId);

    // check if the current user could edit this weblink
    if(!$link->isEditable())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}
else
{
    // check if the user has the right to edit at least one category
    if(count($gCurrentUser->getAllEditableCategories('LNK')) === 0)
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
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('LNK_LINK_NAME'))));
        // => EXIT
    }
    if(strlen(strStripTags($_POST['lnk_url'])) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('LNK_LINK_ADDRESS'))));
        // => EXIT
    }
    if(strlen($_POST['lnk_cat_id']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CATEGORY'))));
        // => EXIT
    }
    // check if the current user is allowed to use the selected category
    if(!in_array((int) $_POST['lnk_cat_id'], $gCurrentUser->getAllEditableCategories('LNK'), true))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // make html in description secure
    $_POST['lnk_description'] = admFuncVariableIsValid($_POST, 'lnk_description', 'html');

    try
    {
        // POST Variablen in das Ankuendigungs-Objekt schreiben
        foreach($_POST as $key => $value) // TODO possible security issue
        {
            if(StringUtils::strStartsWith($key, 'lnk_'))
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
        $link->setValue('lnk_counter', 0);
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
        $message = $gL10n->get('LNK_EMAIL_NOTIFICATION_MESSAGE', array($gCurrentOrganization->getValue('org_longname'), $_POST['lnk_url']. ' ('.$_POST['lnk_name'].')', $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gSettingsManager->getString('system_date'))));
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
    // delete current announcements, right checks were done before
    $link->delete();

    // Delete successful -> Return for XMLHttpRequest
    echo 'done';
}
else
{
    // Falls der mode unbekannt ist, ist natürlich Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}
