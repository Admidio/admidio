<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * ann_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neue Ankuendigung anlegen/aendern
 *           2 - Ankuendigung loeschen
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_announcement.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// pruefen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editAnnouncements())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Uebergabevariablen pruefen

if(isset($_GET['ann_id']) && is_numeric($_GET['ann_id']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 3)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Ankuendigungsobjekt anlegen
$announcement = new TableAnnouncement($g_db);

if($_GET['ann_id'] > 0)
{
    $announcement->readData($_GET['ann_id']);
    
    // Pruefung, ob die Ankuendigung zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}

$_SESSION['announcements_request'] = $_REQUEST;

if($_GET['mode'] == 1)
{
    if(strlen($_POST['ann_headline']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY',$g_l10n->get('SYS_HEADLINE')));
    }
    if(strlen($_POST['ann_description']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY',$g_l10n->get('SYS_TEXT')));
    }

    if(isset($_POST['ann_global']) == false)
    {
        $_POST['ann_global'] = 0;
    }
    
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
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
	else
	{
		// Benachrichtigungs-Email für neue Einträge
		if($g_preferences['enable_email_notification'] == 1 && $_GET['ann_id'] == 0)
		{
			$message = str_replace("<br />","\n", $g_l10n->get('ANN_EMAIL_NOTIFICATION_MESSAGE', $g_current_organization->getValue('org_longname'), $_POST['ann_headline'], $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME'), date("d.m.Y H:m", time())));
			$sender_name = $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME');
			if(!isValidEmailAddress($g_current_user->getValue('EMAIL')))
			{
				$sender_email = $g_preferences['email_administrator'];
				$sender_name = 'Administrator '.$g_current_organization->getValue('org_homepage');
			}
			EmailNotification($g_preferences['email_administrator'], $g_current_organization->getValue('org_shortname'). ": ".$g_l10n->get('ANN_EMAIL_NOTIFICATION_TITLE'), $message, $sender_name, $sender_email);
		}
	}
    
    unset($_SESSION['announcements_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
    exit();
}
elseif($_GET['mode'] == 2)
{
    // Ankuendigung loeschen, wenn diese zur aktuellen Orga gehoert
    if($announcement->getValue('ann_org_shortname') == $g_current_organization->getValue('org_shortname'))
    {
        $announcement->delete();

        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}

?>