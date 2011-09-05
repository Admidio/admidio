<?php
/******************************************************************************
 * Verschiedene Funktionen fuer das Gaestebuch
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * id:      ID die bearbeitet werden soll
 * mode:     1 - Neue Gaestebucheintrag anlegen
 *           2 - Gaestebucheintrag loeschen
 *           3 - Gaestebucheintrag editieren
 *           4 - Kommentar zu einem Eintrag anlegen
 *           5 - Kommentar eines Gaestebucheintrages loeschen
 *           8 - Kommentar eines Gaestebucheintrages editieren
 *           9 - Gaestebucheintrag moderieren
 *           10 - Gaestebuchkommentar moderieren
 * headline: Ueberschrift, die ueber den Gaestebuch steht
 *           (Default) Gaestebuch
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_guestbook.php');
require_once('../../system/classes/table_guestbook_comment.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}
elseif($g_preferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Uebergabevariablen pruefen und ggf. initialisieren
$get_gbo_id   = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$get_mode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);
$get_headline = admFuncVariableIsValid($_GET, 'headline', 'string', $g_l10n->get('GBO_GUESTBOOK'));

// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($get_mode == 2 || $get_mode == 3 || $get_mode == 4 || $get_mode == 5 || $get_mode == 8 )
{

    if ($get_mode == 4)
    {
        // Wenn nicht jeder kommentieren darf, muss man eingeloggt zu sein
        if ($g_preferences['enable_gbook_comments4all'] == 0)
        {
            require_once('../../system/login_valid.php');

            // Ausserdem werden dann commentGuestbook-Rechte benoetigt
            if (!$g_current_user->commentGuestbookRight())
            {
                $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
            }
        }

    }
    else
    {
        // Der User muss fuer die anderen Modes auf jeden Fall eingeloggt sein
        require_once('../../system/login_valid.php');
    }



    if ($get_mode == 2 || $get_mode == 3 || $get_mode == 5 || $get_mode == 8)
    {
        // Fuer die modes 2,3,5,6,7 und 8 werden editGuestbook-Rechte benoetigt
        if(!$g_current_user->editGuestbookRight())
        {
            $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
        }
    }
}

if ($get_mode == 1 || $get_mode == 2 || $get_mode == 3 || $get_mode == 9)
{
    // Gaestebuchobjekt anlegen
    $guestbook = new TableGuestbook($g_db);
    
    if($get_gbo_id > 0)
    {
        $guestbook->readData($get_gbo_id);
        
        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if($guestbook->getValue('gbo_org_id') != $g_current_organization->getValue('org_id'))
        {
            $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
        }
    }
}
else
{
    // Gaestebuchobjekt anlegen
    $guestbook_comment = new TableGuestbookComment($g_db);
    
    if($get_gbo_id > 0 && $get_mode != 4)
    {
        $guestbook_comment->readData($get_gbo_id);
        
        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if($guestbook_comment->getValue('gbo_org_id') != $g_current_organization->getValue('org_id'))
        {
            $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
        }
    }
}


if ($get_mode == 1 || $get_mode == 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_REQUEST;

	// if login then fill name with login user
	if($g_current_user->getValue('usr_id') > 0)
	{
		$_POST['gbo_name'] = $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME');
	}

    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($get_mode == 1 && !$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']) )
        {
            if($g_preferences['captcha_type']=='pic') {$g_message->show($g_l10n->get('SYS_CAPTCHA_CODE_INVALID'));}
			else if($g_preferences['captcha_type']=='calc') {$g_message->show($g_l10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
        }
    }


    // POST Variablen in das Gaestebuchobjekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'gbo_') === 0)
        {
            if(!$guestbook->setValue($key, $value))
			{
				// Daten wurden nicht uebernommen, Hinweis ausgeben
				if($key == 'gbo_email')
				{
					$g_message->show($g_l10n->get('SYS_EMAIL_INVALID', $g_l10n->get('SYS_EMAIL')));
				}
				elseif($key == 'gbo_homepage')
				{
					$g_message->show($g_l10n->get('SYS_URL_INVALID_CHAR', $g_l10n->get('SYS_WEBSITE')));
				}
			}
        }
    }

    if (strlen($guestbook->getValue('gbo_name')) > 0 && strlen($guestbook->getValue('gbo_text'))  > 0)
    {
        // Gaestebucheintrag speichern
        
        if($g_valid_login)
        {
            if(strlen($guestbook->getValue('gbo_name')) == 0)
            { 
                // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $guestbook->setValue('gbo_name', $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME'));
            }
        }
        else
        {
            if($g_preferences['flooding_protection_time'] != 0)
            {
                // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                // einen GB-Eintrag erzeugt hat...
                $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK. '
                        where unix_timestamp(gbo_timestamp_create) > unix_timestamp()-'. $g_preferences['flooding_protection_time']. '
                          and gbo_org_id = '. $g_current_organization->getValue('org_id'). '
                          and gbo_ip_address = "'. $guestbook->getValue('gbo_ip_adress'). '"';
                $result = $g_db->query($sql);
                $row    = $g_db->fetch_array($result);
                if($row[0] > 0)
                {
                    //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $g_message->show($g_l10n->get('GBO_FLOODING_PROTECTION', $g_preferences['flooding_protection_time']));
                }
            }
        }
        
    	// Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if(($g_preferences['enable_guestbook_moderation'] == 1 && $g_valid_login == false)
        || ($g_preferences['enable_guestbook_moderation'] == 2 && $g_current_user->editGuestbookRight() == false))
        {
            $guestbook->setValue('gbo_locked', '1');
        }
        
        // Daten in Datenbank schreiben
        $return_code = $guestbook->save();
    
        if($return_code < 0)
        {
            $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
        }
		
		if($return_code == 0)
		{	
			// Benachrichtigungs-Email für neue Einträge
			if($g_preferences['enable_email_notification'] == 1)
			{
				if(!$g_valid_login)
				{
					$gbo_name  = $_POST['gbo_name'];
					$gbo_email = $_POST['gbo_email'];
					$gbo_text  = $_POST['gbo_text'];
				}
				else
				{
					$gbo_name  = $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME');
					$gbo_email = $g_current_user->getValue('EMAIL');
					$gbo_text  = $_POST['gbo_text'];
				}
				$sender_name = $gbo_name;
				if(!strValidCharacters($gbo_email, 'email'))
				{
					$gbo_email = $g_preferences['email_administrator'];
					$sender_name = 'Administrator '.$g_current_organization->getValue('org_homepage');
				}
				admFuncEmailNotification($g_preferences['email_administrator'], $g_current_organization->getValue('org_shortname'). ": ".$g_l10n->get('GBO_EMAIL_NOTIFICATION_TITLE'), str_replace("<br />","\n",$g_l10n->get('GBO_EMAIL_NOTIFICATION_MESSAGE', $g_current_organization->getValue('org_longname'), $gbo_text, $gbo_name, date("d.m.Y H:m", time()))), $sender_name, $gbo_email);
			}
		}

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);
        $_SESSION['navigation']->deleteLastUrl();

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }
        
        $url = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?headline='. $get_headline;

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if(($g_preferences['enable_guestbook_moderation'] == 1 && $g_valid_login == false)
        || ($g_preferences['enable_guestbook_moderation'] == 2 && $g_current_user->editGuestbookRight() == false))
        {
            $g_message->setForwardUrl($url);
            $g_message->show($g_l10n->get('GBO_ENTRY_QUEUED'));
        }

        header('Location: '.$url);
        exit();
    }
    else
    {
        if(strlen($guestbook->getValue('gbo_name')) > 0)
        {
            $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('SYS_TEXT')));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('SYS_TEXT')));
        }
    }
}

elseif($get_mode == 2)
{
    // den Gaestebucheintrag loeschen...
    $guestbook->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($get_mode == 5)
{
    //Gaestebuchkommentar loeschen...
    $guestbook_comment->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
// Moderationsfunktion
elseif ($get_mode == 9)
{
    // den Gaestebucheintrag freischalten...
    $guestbook->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($get_mode == 10)
{
    // den Gaestebucheintrag freischalten...
    $guestbook_comment->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif($get_mode == 4 || $get_mode == 8)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_comment_request'] = $_REQUEST;

	// if login then fill name with login user
	if($g_current_user->getValue('usr_id') > 0)
	{
		$_POST['gbc_name'] = $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME');
	}

    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($get_mode == 4 && !$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']) )
        {
            if($g_preferences['captcha_type']=='pic') {$g_message->show($g_l10n->get('SYS_CAPTCHA_CODE_INVALID'));}
			else if($g_preferences['captcha_type']=='calc') {$g_message->show($g_l10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
        }
    }

    // POST Variablen in das Gaestebuchkommentarobjekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'gbc_') === 0)
        {
            if(!$guestbook_comment->setValue($key, $value))
			{
				// Daten wurden nicht uebernommen, Hinweis ausgeben
				if($key == 'gbc_email')
				{
					$g_message->show($g_l10n->get('SYS_EMAIL_INVALID', $g_l10n->get('SYS_EMAIL')));
				}
			}
        }
    }
    
    if($get_mode == 4)
    {
        $guestbook_comment->setValue('gbc_gbo_id', $get_gbo_id);
    }

    if (strlen($guestbook_comment->getValue('gbc_name')) > 0 && strlen($guestbook_comment->getValue('gbc_text'))  > 0)
    {
        // Gaestebuchkommentar speichern
        
        if($g_valid_login)
        {
            if(strlen($guestbook_comment->getValue('gbc_name')) == 0)
            {
                // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $guestbook_comment->setValue('gbc_name', $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME'));
            }
        }
        else
        {
            if($g_preferences['flooding_protection_time'] != 0)
            {
                // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                // einen GB-Eintrag/Kommentar erzeugt hat...
                $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK_COMMENTS. '
                         WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp()-'. $g_preferences['flooding_protection_time']. '
                           AND gbc_ip_address = "'. $guestbook_comment->getValue('gbc_ip_adress'). '"';
                $result = $g_db->query($sql);
                $row = $g_db->fetch_array($result);
                if($row[0] > 0)
                {
                    //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $g_message->show($g_l10n->get('GBO_FLOODING_PROTECTION', $g_preferences['flooding_protection_time']));
                }
            }
        }
        
    	// Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if(($g_preferences['enable_guestbook_moderation'] == 1 && $g_valid_login == false)
        || ($g_preferences['enable_guestbook_moderation'] == 2 && $g_current_user->editGuestbookRight() == false))
        {
            $guestbook_comment->setValue('gbc_locked', '1');
        }
        
        // Daten in Datenbank schreiben
        $return_code = $guestbook_comment->save();
    
        if($return_code < 0)
        {
            $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
        }
		
		if($return_code == 0)
		{	
			// Benachrichtigungs-Email für neue Einträge
			if($g_preferences['enable_email_notification'] == 1)
			{
				if(!$g_valid_login)
				{
					$gbc_name  = $guestbook_comment->getValue('gbc_name');
					$gbc_email = $guestbook_comment->getValue('gbc_email');
				}
				else
				{
					$gbc_name  = $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME');
					$gbc_email = $g_current_user->getValue('EMAIL');
				}
				$sender_name = $gbc_name;
				if(strlen($gbc_email) == 0)
				{
					$gbc_email = $g_preferences['email_administrator'];
					$sender_name = 'Administrator '.$g_current_organization->getValue('org_homepage');
				}
				admFuncEmailNotification($g_preferences['email_administrator'], $g_current_organization->getValue('org_shortname'). ": ".$g_l10n->get('GBO_EMAIL_NOTIFICATION_GBC_TITLE'), 
				    str_replace("<br />","\n",$g_l10n->get('GBO_EMAIL_NOTIFICATION_GBC_MESSAGE', $g_current_organization->getValue('org_longname'), 
				    $guestbook_comment->getValue('gbc_text'), $gbc_name, date("d.m.Y H:m", time()))), $sender_name, $gbc_email);
			}
		}

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_comment_request']);
        $_SESSION['navigation']->deleteLastUrl();

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }

        $url = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?id='. $guestbook_comment->getValue('gbc_gbo_id'). '&headline='. $get_headline;

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if(($g_preferences['enable_guestbook_moderation'] == 1 && $g_valid_login == false)
        || ($g_preferences['enable_guestbook_moderation'] == 2 && $g_current_user->editGuestbookRight() == false))
        {
            $g_message->setForwardUrl($url);
            $g_message->show($g_l10n->get('GBO_ENTRY_QUEUED'));
        }

        header('Location: '.$url);
        exit();
    }
    else
    {
        if(strlen($guestbook_comment->getValue('gbc_name')) > 0)
        {
            $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('SYS_COMMENT')));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('SYS_NAME')));
        }
    }
}
else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
?>