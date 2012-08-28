<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Links
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lnk_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neuen Link anlegen
 *           2 - Link loeschen
 *           3 - Link editieren
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_weblink.php');
require_once('../../libs/htmlawed/htmlawed.php');

// Initialize and check the parameters
$getLinkId = admFuncVariableIsValid($_GET, 'lnk_id', 'numeric', 0);
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Linkobjekt anlegen
$link = new TableWeblink($gDb, $getLinkId);

$_SESSION['links_request'] = $_REQUEST;

if ($getMode == 1 || ($getMode == 3 && $getLinkId > 0) )
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
    $_POST['lnk_description'] = htmLawed(stripslashes($_POST['lnk_description']), array('safe' => 1));

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
		if($gPreferences['enable_email_notification'] == 1)
		{
			$sender_name  = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
			$sender_email = $gCurrentUser->getValue('EMAIL');
			
			if(strlen($gCurrentUser->getValue('EMAIL')) == 0)
			{
				$sender_email = $gPreferences['email_administrator'];
				$sender_name  = 'Administrator '.$gCurrentOrganization->getValue('org_homepage');
			}
			admFuncEmailNotification($gPreferences['email_administrator'], $gCurrentOrganization->getValue('org_shortname'). ': '.$gL10n->get('LNK_EMAIL_NOTIFICATION_TITLE'), 
				str_replace('<br />',"\n",$gL10n->get('LNK_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), 
				$_POST['lnk_url']. ' ('.$_POST['lnk_name'].')', $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), 
				date('d.m.Y H:m', time()))), $sender_name, $sender_email);
		}	
	}

    unset($_SESSION['links_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
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

?>