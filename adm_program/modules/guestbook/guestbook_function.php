<?php
/**
 ***********************************************************************************************
 * Various functions for guestbook module
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
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
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id',       'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

$getHeadline = urlencode($getHeadline);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif($gPreferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($getMode === 2 || $getMode === 3 || $getMode === 4 || $getMode === 5 || $getMode === 8)
{

    if ($getMode === 4)
    {
        // Wenn nicht jeder kommentieren darf, muss man eingeloggt zu sein
        if ($gPreferences['enable_gbook_comments4all'] == 0)
        {
            require_once('../../system/login_valid.php');

            // Ausserdem werden dann commentGuestbook-Rechte benoetigt
            if (!$gCurrentUser->commentGuestbookRight())
            {
                $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
                // => EXIT
            }
        }

    }
    else
    {
        // Der User muss fuer die anderen Modes auf jeden Fall eingeloggt sein
        require_once('../../system/login_valid.php');
    }

    if ($getMode === 2 || $getMode === 3 || $getMode === 5 || $getMode === 8)
    {
        // Fuer die modes 2,3,5,6,7 und 8 werden editGuestbook-Rechte benoetigt
        if(!$gCurrentUser->editGuestbookRight())
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
}

if ($getMode === 1 || $getMode === 2 || $getMode === 3 || $getMode === 9)
{
    // Gaestebuchobjekt anlegen
    $guestbook = new TableGuestbook($gDb);

    if($getGboId > 0)
    {
        $guestbook->readDataById($getGboId);

        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if((int) $guestbook->getValue('gbo_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
}
else
{
    // Gaestebuchobjekt anlegen
    $guestbook_comment = new TableGuestbookComment($gDb);

    if($getGboId > 0 && $getMode !== 4)
    {
        $guestbook_comment->readDataById($getGboId);

        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if((int) $guestbook_comment->getValue('gbo_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
}

if ($getMode === 1 || $getMode === 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_POST;

    // if login and new entry then fill name with login user
    if($getMode === 1 && $gCurrentUser->getValue('usr_id') > 0)
    {
        $_POST['gbo_name'] = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
    }

    // if user is not logged in and captcha is activated then check captcha
    if ($getMode === 1 && !$gValidLogin && $gPreferences['enable_guestbook_captcha'] == 1)
    {
        try
        {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        }
        catch(AdmException $e)
        {
            $e->showHtml();
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
                if($key === 'gbo_email')
                {
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')));
                    // => EXIT
                }
                elseif($key === 'gbo_homepage')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $gL10n->get('SYS_WEBSITE')));
                    // => EXIT
                }
            }
        }
    }

    if (strlen($guestbook->getValue('gbo_name')) > 0 && strlen($guestbook->getValue('gbo_text')) > 0)
    {
        // Gaestebucheintrag speichern

        if($gValidLogin)
        {
            if(strlen($guestbook->getValue('gbo_name')) === 0)
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
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_GUESTBOOK.'
                         WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
                           AND gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                           AND gbo_ip_address = \''. $guestbook->getValue('gbo_ip_adress'). '\'';
                $pdoStatement = $gDb->query($sql);

                if($pdoStatement->fetchColumn() > 0)
                {
                    // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
                    // => EXIT
                }
            }
        }

        // Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if(($gPreferences['enable_guestbook_moderation'] == 1 && !$gValidLogin)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $guestbook->setValue('gbo_locked', '1');
        }

        // Daten in Datenbank schreiben
        $return_code = $guestbook->save();

        if($return_code === false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        if($return_code === true)
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
            try
            {
                $notification = new Email();
                $notification->adminNotfication($gL10n->get('GBO_EMAIL_NOTIFICATION_TITLE'), $gL10n->get('GBO_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $gbo_text, $gbo_name, date($gPreferences['system_date'], time())), $sender_name, $gbo_email);
            }
            catch(AdmException $e)
            {
                $e->showHtml();
            }
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);
        $gNavigation->deleteLastUrl();

        $url = ADMIDIO_URL . FOLDER_MODULES.'/guestbook/guestbook.php?headline=' . $getHeadline;

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if(($gPreferences['enable_guestbook_moderation'] == 1 && !$gValidLogin)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
            // => EXIT
        }

        admRedirect($url);
        // => EXIT
    }
    else
    {
        if(strlen($guestbook->getValue('gbo_name')) > 0)
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
            // => EXIT
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
            // => EXIT
        }
    }
}

elseif($getMode === 2)
{
    // den Gaestebucheintrag loeschen...
    $guestbook->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($getMode === 5)
{
    // Gaestebuchkommentar loeschen...
    $guestbook_comment->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
// Moderationsfunktion
elseif ($getMode === 9)
{
    // den Gaestebucheintrag freischalten...
    $guestbook->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($getMode === 10)
{
    // den Gaestebucheintrag freischalten...
    $guestbook_comment->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif($getMode === 4 || $getMode === 8)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_comment_request'] = $_POST;

    // if login then fill name with login user
    if($getMode === 4 && $gCurrentUser->getValue('usr_id') > 0)
    {
        $_POST['gbc_name'] = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
    }

    // if user is not logged in and captcha is activated then check captcha
    if ($getMode === 4 && !$gValidLogin && $gPreferences['enable_guestbook_captcha'] == 1)
    {
        try
        {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        }
        catch(AdmException $e)
        {
            $e->showHtml();
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
                if($key === 'gbc_email')
                {
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')));
                    // => EXIT
                }
            }
        }
    }

    if($getMode === 4)
    {
        $guestbook_comment->setValue('gbc_gbo_id', $getGboId);
    }

    if (strlen($guestbook_comment->getValue('gbc_name')) > 0 && strlen($guestbook_comment->getValue('gbc_text')) > 0)
    {
        // Gaestebuchkommentar speichern

        if($gValidLogin)
        {
            if(strlen($guestbook_comment->getValue('gbc_name')) === 0)
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
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_GUESTBOOK_COMMENTS.'
                         WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
                           AND gbc_ip_address = \''. $guestbook_comment->getValue('gbc_ip_adress'). '\'';
                $pdoStatement = $gDb->query($sql);

                if($pdoStatement->fetchColumn() > 0)
                {
                    // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
                    // => EXIT
                }
            }
        }

        // Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if(($gPreferences['enable_guestbook_moderation'] == 1 && !$gValidLogin)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $guestbook_comment->setValue('gbc_locked', '1');
        }

        // Daten in Datenbank schreiben
        $return_code = $guestbook_comment->save();

        if($return_code === false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        if($return_code === true)
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
            try
            {
                $notification = new Email();
                $notification->adminNotfication($gL10n->get('GBO_EMAIL_NOTIFICATION_GBC_TITLE'), $message, $sender_name, $gbc_email);
            }
            catch(AdmException $e)
            {
                $e->showHtml();
            }
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_comment_request']);
        $gNavigation->deleteLastUrl();

        $url = ADMIDIO_URL . FOLDER_MODULES.'/guestbook/guestbook.php?id=' . $guestbook_comment->getValue('gbc_gbo_id') . '&headline=' . $getHeadline;

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if(($gPreferences['enable_guestbook_moderation'] == 1 && !$gValidLogin)
        || ($gPreferences['enable_guestbook_moderation'] == 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
            // => EXIT
        }

        admRedirect($url);
        // => EXIT
    }
    else
    {
        if(strlen($guestbook_comment->getValue('gbc_name')) > 0)
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_COMMENT')));
            // => EXIT
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
            // => EXIT
        }
    }
}
else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}
