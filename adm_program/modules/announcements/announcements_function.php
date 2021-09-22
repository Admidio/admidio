<?php
/**
 ***********************************************************************************************
 * Several functions for announcement module
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * ann_uuid - UUID of the announcement that should be edited
 * mode     - 1 : Create or edit announcement
 *            2 : Delete announcement
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// check if the module is enabled for use
if ((int) $gSettingsManager->get('enable_announcements_module') === 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getAnnUuid = admFuncVariableIsValid($_GET, 'ann_uuid', 'string');
$getMode    = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

// create announcement object
$announcement = new TableAnnouncement($gDb);

if($getAnnUuid !== '')
{
    $announcement->readDataByUuid($getAnnUuid);

    // check if the user has the right to edit this announcement
    if(!$announcement->isEditable())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}
else
{
    // check if the user has the right to edit at least one category
    if(count($gCurrentUser->getAllEditableCategories('ANN')) === 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

$_SESSION['announcements_request'] = $_POST;

if($getMode === 1)
{
    if(strlen($_POST['ann_headline']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_HEADLINE'))));
        // => EXIT
    }
    if(strlen($_POST['ann_description']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TEXT'))));
        // => EXIT
    }
    // check if the current user is allowed to use the selected category
    if(!in_array((int) $_POST['ann_cat_id'], $gCurrentUser->getAllEditableCategories('ANN'), true))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // make html in description secure
    $_POST['ann_description'] = admFuncVariableIsValid($_POST, 'ann_description', 'html');

    try
    {
        // write POST parameters in announcement object
        foreach($_POST as $key => $value) // TODO possible security issue
        {
            if(str_starts_with($key, 'ann_'))
            {
                $announcement->setValue($key, $value);
            }
        }

        // Daten in Datenbank schreiben
        $returnValue = $announcement->save();

        if($returnValue === false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        else
        {
            if($getAnnUuid === '')
            {
                $message = $gL10n->get('SYS_EMAIL_ANNOUNCEMENT_NOTIFICATION_MESSAGE', array($gCurrentOrganization->getValue('org_longname'), $_POST['ann_headline'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gSettingsManager->getString('system_date'))));

                $notification = new Email();
                $notification->adminNotification($gL10n->get('SYS_EMAIL_ANNOUNCEMENT_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
            }
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    unset($_SESSION['announcements_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}
elseif($getMode === 2)
{
    // delete current announcements, right checks were done before
    $announcement->delete();

    // Delete successful -> Return for XMLHttpRequest
    echo 'done';
}
