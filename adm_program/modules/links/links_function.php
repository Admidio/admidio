<?php
/******************************************************************************
 * Several functions for weblinks module
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lnk_id  - ID of the weblink that should be edited
 * mode    - 1 : Create new link
 *           2 : Delete link
 *           3 : Edit link
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getLinkId = admFuncVariableIsValid($_GET, 'lnk_id', 'numeric');
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));

// check if the module is enabled for use
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Linkobjekt anlegen
$link = new TableWeblink($gDb, $getLinkId);

$_SESSION['links_request'] = $_POST;

if ($getMode == 1 || ($getMode == 3 && $getLinkId > 0))
{
    if(strlen(strStripTags($_POST['lnk_name'])) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('LNK_LINK_NAME')));
    }
    if(strlen(strStripTags($_POST['lnk_url'])) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('LNK_LINK_ADDRESS')));
    }
    if(strlen($_POST['lnk_cat_id']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
    }

    // make html in description secure
    $_POST['lnk_description'] = admFuncVariableIsValid($_POST, 'lnk_description', 'html');

    // POST Variablen in das Ankuendigungs-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'lnk_') === 0)
        {
            if($link->setValue($key, $value) == false)
            {
                // Daten wurden nicht uebernommen, Hinweis ausgeben
                if($key == 'lnk_url')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $gL10n->get('SYS_WEBSITE')));
                }
            }
        }
    }

    // Link-Counter auf 0 setzen
    if ($getMode == 1)
    {
        $link->setValue('lnk_counter', '0');
    }

    // Daten in Datenbank schreiben
    $return_code = $link->save();

    if($return_code < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    if($return_code == 0 && $getMode == 1)
    {
        // Benachrichtigungs-Email für neue Einträge
        $message = $gL10n->get('LNK_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['lnk_url']. ' ('.$_POST['lnk_name'].')', $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));
        $notification = new Email();
        $notification->adminNotfication($gL10n->get('LNK_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
    }

    unset($_SESSION['links_request']);
    $gNavigation->deleteLastUrl();

    header('Location: '. $gNavigation->getUrl());
    exit();
}

elseif ($getMode == 2 && $getLinkId > 0)
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
}
