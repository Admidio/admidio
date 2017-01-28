<?php
/**
 ***********************************************************************************************
 * Several functions for announcement module
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * ann_id  - ID of the announcement that should be edited
 * mode    - 1 : Create or edit announcement
 *           2 : Delete announcement
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// check if the module is enabled for use
if ($gPreferences['enable_announcements_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// pruefen, ob der User auch die entsprechenden Rechte hat
if(!$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Initialize and check the parameters
$getAnnId = admFuncVariableIsValid($_GET, 'ann_id', 'int');
$getMode  = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

// Ankuendigungsobjekt anlegen
$announcement = new TableAnnouncement($gDb);

if($getAnnId > 0)
{
    $announcement->readDataById($getAnnId);

    // Pruefung, ob die Ankuendigung zur aktuellen Organisation gehoert bzw. global ist
    if(!$announcement->editRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

$_SESSION['announcements_request'] = $_POST;

if($getMode === 1)
{
    if(!isset($_POST['ann_global']))
    {
        $_POST['ann_global'] = 0;
    }

    if(strlen($_POST['ann_headline']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_HEADLINE')));
        // => EXIT
    }
    if(strlen($_POST['ann_description']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
        // => EXIT
    }

    // make html in description secure
    $_POST['ann_description'] = admFuncVariableIsValid($_POST, 'ann_description', 'html');

    // POST Variablen in das Ankuendigungs-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'ann_') === 0)
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
        if($getAnnId === 0)
        {
            $message = $gL10n->get('ANN_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['ann_headline'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));

            try
            {
                $notification = new Email();
                $notification->adminNotfication($gL10n->get('ANN_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
            }
            catch(AdmException $e)
            {
                $e->showHtml();
            }
        }
    }

    unset($_SESSION['announcements_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}
elseif($getMode === 2)
{
    // Ankuendigung loeschen, wenn diese zur aktuellen Orga gehoert
    if((int) $announcement->getValue('cat_org_id') === (int) $gCurrentOrganization->getValue('org_id'))
    {
        $announcement->delete();

        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
