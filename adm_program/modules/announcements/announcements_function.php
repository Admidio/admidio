<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * ann_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neue Ankuendigung anlegen/aendern
 *           2 - Ankuendigung loeschen
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_announcement.php');
require_once('../../libs/htmlawed/htmlawed.php');
require_once(SERVER_PATH. '/adm_program/system/classes/email.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// pruefen, ob der User auch die entsprechenden Rechte hat
if(!$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getAnnId = admFuncVariableIsValid($_GET, 'ann_id', 'numeric', 0);
$getMode  = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);

// Ankuendigungsobjekt anlegen
$announcement = new TableAnnouncement($gDb);

if($getAnnId > 0)
{
    $announcement->readDataById($getAnnId);
    
    // Pruefung, ob die Ankuendigung zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

$_SESSION['announcements_request'] = $_POST;

if($getMode == 1)
{
    if(isset($_POST['ann_global']) == false)
    {
        $_POST['ann_global'] = 0;
    }

    if(strlen($_POST['ann_headline']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY',$gL10n->get('SYS_HEADLINE')));
    }
    if(strlen($_POST['ann_description']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY',$gL10n->get('SYS_TEXT')));
    }
    
    // make html in description secure
    $_POST['ann_description'] = htmLawed(stripslashes($_POST['ann_description']), array('safe' => 1));
    
    // POST Variablen in das Ankuendigungs-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'ann_') === 0)
        {
            $announcement->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $announcement->save();

    if($return_code < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
	else
	{
	    if($getAnnId == 0)
        {
    	    $message = $gL10n->get('ANN_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['ann_headline'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));      	    
    	    $notification = new Email();
    	    $notification->adminNotfication($gL10n->get('ANN_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
        }
	}
    
    unset($_SESSION['announcements_request']);
    $gNavigation->deleteLastUrl();

    header('Location: '. $gNavigation->getUrl());
    exit();
}
elseif($getMode == 2)
{
    // Ankuendigung loeschen, wenn diese zur aktuellen Orga gehoert
    if($announcement->getValue('ann_org_shortname') == $gCurrentOrganization->getValue('org_shortname'))
    {
        $announcement->delete();

        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}

?>