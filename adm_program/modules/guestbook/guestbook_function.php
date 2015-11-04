<?php
/******************************************************************************
 * Various functions for guestbook module
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * id       : Id of one guestbook entry that should be edited
 * mode:    1 - Neue Gaestebucheintrag anlegen
 *          2 - Gaestebucheintrag loeschen
 *          3 - Gaestebucheintrag editieren
 *          4 - Kommentar zu einem Eintrag anlegen
 *          5 - Kommentar eines Gaestebucheintrages loeschen
 *          8 - Kommentar eines Gaestebucheintrages editieren
 *          9 - Gaestebucheintrag moderieren
 *          10 - Gaestebuchkommentar moderieren
 * headline : Title of the guestbook module. This will be shown in the whole module.
 *            (Default) GBO_GUESTBOOK
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id', 'numeric');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($getMode == 2 || $getMode == 3 || $getMode == 4 || $getMode == 5 || $getMode == 8)
{

    if ($getMode == 4)
    {
        // Wenn nicht jeder kommentieren darf, muss man eingeloggt zu sein
        if ($gPreferences['enable_gbook_comments4all'] == 0)
        {
            require_once('../../system/login_valid.php');

            // Ausserdem werden dann commentGuestbook-Rechte benoetigt
            if (!$gCurrentUser->commentGuestbookRight())
            {
                $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            }
        }

    }
    else
    {
        // Der User muss fuer die anderen Modes auf jeden Fall eingeloggt sein
        require_once('../../system/login_valid.php');
    }



    if ($getMode == 2 || $getMode == 3 || $getMode == 5 || $getMode == 8)
    {
        // Fuer die modes 2,3,5,6,7 und 8 werden editGuestbook-Rechte benoetigt
        if(!$gCurrentUser->editGuestbookRight())
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
}

if ($getMode == 1 || $getMode == 2 || $getMode == 3 || $getMode == 9)
{
    // Gaestebuchobjekt anlegen
    $guestbook = new TableGuestbook($gDb);

    if($getGboId > 0)
    {
        $guestbook->readDataById($getGboId);

        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if($guestbook->getValue('gbo_org_id') != $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
}
else
{
    // Gaestebuchobjekt anlegen
    $guestbook_comment = new TableGuestbookComment($gDb);

    if($getGboId > 0 && $getMode != 4)
    {
        $guestbook_comment->readDataById($getGboId);

        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if($guestbook_comment->getValue('gbo_org_id') != $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
}


if ($getMode == 1 || $getMode == 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_POST;

    // if login and new entry then fill name with login user
    if($getMode == 1 && $gCurrentUser->getValue('usr_id') > 0)
    {
        $_POST['gbo_name'] = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
    }

    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($getMode == 1 && !$gValidLogin && $gPreferences['enable_guestbook_captcha'] == 1)
    {
        if (!isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']))
        {
            if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
            elseif($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
        }
    }

    // make html in description secure
    $_POST['gbo_text'] = admFuncVariableIsValid($_POST, 'gbo_text', 'html');

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
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')));
                }
                elseif($key == 'gbo_homepage')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $gL10n->get('SYS_WEBSITE')));
                }
            }
        }
    }

    if (strlen($guestbook->getValue('gbo_name')) > 0 && strlen($guestbook->getValue('gbo_text'))  > 0)
    {
        // Gaestebucheintrag speichern

        if($gValidLogin)
        {
            if(strlen($guestbook->getValue('gbo_name')) == 0)
            {
                // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $guestbook->setValue('gbo_name', $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'));
            }
        }
        else
        {
            if($gPreferences['flooding_protection_time'] != 0)
            {
                // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                // einen GB-Eintrag erzeugt hat...
                $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK. '
                         WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
                           AND gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                           AND gbo_ip_address = \''. $guestbook->getValue('gbo_ip_adress'). '\'';
                $result = $gDb->query($sql);
                $row    = $gDb->fetch_array($result);
                if($row[0] > 0)
                {
                    //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
                }
            }
        }

        // Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if(($gPreferences['enable_guestbook_moderation'] == 1 && $gValidLogin == false)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && $gCurrentUser->editGuestbookRight() == false))
        {
            $guestbook->setValue('gbo_locked', '1');
        }

        // Daten in Datenbank schreiben
        $return_code = $guestbook->save();

        if($return_code < 0)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }

        if($return_code == 0)
        {
            // Benachrichtigungs-Email für neue Einträge
            if(!$gValidLogin)
            {
                $gbo_name  = $_POST['gbo_name'];
                $gbo_email = $_POST['gbo_email'];
                $gbo_text  = $_POST['gbo_text'];
            }
            else
            {
                $gbo_name  = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
                $gbo_email = $gCurrentUser->getValue('EMAIL');
                $gbo_text  = $_POST['gbo_text'];
            }
            $sender_name = $gbo_name;
            if(!strValidCharacters($gbo_email, 'email'))
            {
                $gbo_email = $gPreferences['email_administrator'];
                $sender_name = 'Administrator '.$gCurrentOrganization->getValue('org_homepage');
            }
            $notification = new Email();
            $notification->adminNotfication($gL10n->get('GBO_EMAIL_NOTIFICATION_TITLE'), $gL10n->get('GBO_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $gbo_text, $gbo_name, date($gPreferences['system_date'], time())), $sender_name, $gbo_email);
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);
        $gNavigation->deleteLastUrl();

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }

        $url = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?headline='. $getHeadline;

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if(($gPreferences['enable_guestbook_moderation'] == 1 && $gValidLogin == false)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && $gCurrentUser->editGuestbookRight() == false))
        {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
        }

        header('Location: '.$url);
        exit();
    }
    else
    {
        if(strlen($guestbook->getValue('gbo_name')) > 0)
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
        }
    }
}

elseif($getMode == 2)
{
    // den Gaestebucheintrag loeschen...
    $guestbook->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($getMode == 5)
{
    //Gaestebuchkommentar loeschen...
    $guestbook_comment->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
// Moderationsfunktion
elseif ($getMode == 9)
{
    // den Gaestebucheintrag freischalten...
    $guestbook->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($getMode == 10)
{
    // den Gaestebucheintrag freischalten...
    $guestbook_comment->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif($getMode == 4 || $getMode == 8)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_comment_request'] = $_POST;

    // if login then fill name with login user
    if($getMode == 4 && $gCurrentUser->getValue('usr_id') > 0)
    {
        $_POST['gbc_name'] = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
    }

    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($getMode == 4 && !$gValidLogin && $gPreferences['enable_guestbook_captcha'] == 1)
    {
        if (!isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']))
        {
            if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
            elseif($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
        }
    }

    // make html in description secure
    $_POST['gbc_text'] = admFuncVariableIsValid($_POST, 'gbc_text', 'html');

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
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')));
                }
            }
        }
    }

    if($getMode == 4)
    {
        $guestbook_comment->setValue('gbc_gbo_id', $getGboId);
    }

    if (strlen($guestbook_comment->getValue('gbc_name')) > 0 && strlen($guestbook_comment->getValue('gbc_text'))  > 0)
    {
        // Gaestebuchkommentar speichern

        if($gValidLogin)
        {
            if(strlen($guestbook_comment->getValue('gbc_name')) == 0)
            {
                // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $guestbook_comment->setValue('gbc_name', $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'));
            }
        }
        else
        {
            if($gPreferences['flooding_protection_time'] != 0)
            {
                // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                // einen GB-Eintrag/Kommentar erzeugt hat...
                $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK_COMMENTS. '
                         WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
                           AND gbc_ip_address = \''. $guestbook_comment->getValue('gbc_ip_adress'). '\'';
                $result = $gDb->query($sql);
                $row = $gDb->fetch_array($result);
                if($row[0] > 0)
                {
                    //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
                }
            }
        }

        // Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if(($gPreferences['enable_guestbook_moderation'] == 1 && $gValidLogin == false)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && $gCurrentUser->editGuestbookRight() == false))
        {
            $guestbook_comment->setValue('gbc_locked', '1');
        }

        // Daten in Datenbank schreiben
        $return_code = $guestbook_comment->save();

        if($return_code < 0)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }

        if($return_code == 0)
        {
            // Benachrichtigungs-Email für neue Einträge
            if(!$gValidLogin)
            {
                $gbc_name  = $guestbook_comment->getValue('gbc_name');
                $gbc_email = $guestbook_comment->getValue('gbc_email');
            }
            else
            {
                $gbc_name  = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
                $gbc_email = $gCurrentUser->getValue('EMAIL');
            }
            $sender_name = $gbc_name;
            if($gbc_email === '')
            {
                $gbc_email = $gPreferences['email_administrator'];
                $sender_name = 'Administrator '.$gCurrentOrganization->getValue('org_homepage');
            }
            $message = $gL10n->get('GBO_EMAIL_NOTIFICATION_GBC_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $guestbook_comment->getValue('gbc_text'), $gbc_name, date($gPreferences['system_date'], time()));
            $notification = new Email();
            $notification->adminNotfication($gL10n->get('GBO_EMAIL_NOTIFICATION_GBC_TITLE'), $message, $sender_name, $gbc_email);

        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_comment_request']);
        $gNavigation->deleteLastUrl();

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }

        $url = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?id='. $guestbook_comment->getValue('gbc_gbo_id'). '&headline='. $getHeadline;

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if(($gPreferences['enable_guestbook_moderation'] == 1 && $gValidLogin == false)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && $gCurrentUser->editGuestbookRight() == false))
        {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
        }

        header('Location: '.$url);
        exit();
    }
    else
    {
        if(strlen($guestbook_comment->getValue('gbc_name')) > 0)
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_COMMENT')));
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
        }
    }
}
else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}
