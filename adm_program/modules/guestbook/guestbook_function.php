<?php
/**
 ***********************************************************************************************
 * Various functions for guestbook module
 *
 * @copyright 2004-2018 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id',       'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_guestbook_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif ((int) $gSettingsManager->get('enable_guestbook_module') === 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require(__DIR__ . '/../../system/login_valid.php');
}

// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($getMode === 4)
{
    // Wenn nicht jeder kommentieren darf, muss man eingeloggt zu sein
    if (!$gSettingsManager->getBool('enable_gbook_comments4all'))
    {
        require(__DIR__ . '/../../system/login_valid.php');

        // Ausserdem werden dann commentGuestbook-Rechte benoetigt
        if (!$gCurrentUser->commentGuestbookRight())
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }

}
elseif (in_array($getMode, array(2, 3, 5, 8), true))
{
    // Der User muss fuer die anderen Modes auf jeden Fall eingeloggt sein
    require(__DIR__ . '/../../system/login_valid.php');

    // Fuer die modes 2,3,5 und 8 werden editGuestbook-Rechte benoetigt
    if (!$gCurrentUser->editGuestbookRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if (in_array($getMode, array(1, 2, 3, 9), true))
{
    // Gaestebuchobjekt anlegen
    $guestbook = new TableGuestbook($gDb);

    if ($getGboId > 0)
    {
        $guestbook->readDataById($getGboId);

        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if ((int) $guestbook->getValue('gbo_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
}
elseif (in_array($getMode, array(4, 5, 8, 10), true))
{
    // Gaestebuchkommentarobjekt anlegen
    $gbComment = new TableGuestbookComment($gDb);

    if ($getGboId > 0 && $getMode !== 4)
    {
        $gbComment->readDataById($getGboId);

        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if ((int) $gbComment->getValue('gbo_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
}

if ($getMode === 1 || $getMode === 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_POST;

    if ($getMode === 1)
    {
        // if login and new entry then fill name with login user
        if ($gCurrentUser->getValue('usr_id') > 0)
        {
            $_POST['gbo_name'] = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
        }

        // if user is not logged in and captcha is activated then check captcha
        if (!$gValidLogin && $gSettingsManager->getBool('enable_guestbook_captcha'))
        {
            try
            {
                FormValidation::checkCaptcha($_POST['captcha_code']);
            }
            catch (AdmException $e)
            {
                $e->showHtml();
                // => EXIT
            }
        }
    }

    // make html in description secure
    $_POST['gbo_text'] = admFuncVariableIsValid($_POST, 'gbo_text', 'html');

    // POST Variablen in das Gaestebuchobjekt schreiben
    foreach ($_POST as $key => $value) // TODO possible security issue
    {
        if (StringUtils::strStartsWith($key, 'gbo_'))
        {
            if (!$guestbook->setValue($key, $value))
            {
                // Daten wurden nicht uebernommen, Hinweis ausgeben
                if ($key === 'gbo_email')
                {
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))));
                    // => EXIT
                }
                elseif ($key === 'gbo_homepage')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_WEBSITE'))));
                    // => EXIT
                }
            }
        }
    }

    if ($guestbook->getValue('gbo_name') === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }
    elseif ($guestbook->getValue('gbo_text') === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TEXT'))));
        // => EXIT
    }
    else
    {
        // Gaestebucheintrag speichern

        if ($gValidLogin)
        {
            // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
            $guestbook->setValue('gbo_name', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        }
        else
        {
            if ($gSettingsManager->getInt('flooding_protection_time') > 0)
            {
                // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                // einen GB-Eintrag erzeugt hat...
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_GUESTBOOK.'
                         WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp() - ? -- $gSettingsManager->getInt(\'flooding_protection_time\')
                           AND gbo_org_id     = ? -- $gCurrentOrganization->getValue(\'org_id\')
                           AND gbo_ip_address = ? -- $guestbook->getValue(\'gbo_ip_address\')';
                $queryParams = array($gSettingsManager->getInt('flooding_protection_time'), $gCurrentOrganization->getValue('org_id'), $guestbook->getValue('gbo_ip_address'));
                $pdoStatement = $gDb->queryPrepared($sql, $queryParams);

                if ($pdoStatement->fetchColumn() > 0)
                {
                    // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', array($gSettingsManager->getInt('flooding_protection_time'))));
                    // => EXIT
                }
            }
        }

        // Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $guestbook->setValue('gbo_locked', '1');
        }

        // Daten in Datenbank schreiben
        $returnCode = $guestbook->save();

        if ($returnCode === false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        else
        {
            // Benachrichtigungs-Email für neue Einträge
            if (!$gValidLogin)
            {
                $gboName  = $_POST['gbo_name'];
                $gboEmail = $_POST['gbo_email'];
                $gboText  = $_POST['gbo_text'];
            }
            else
            {
                $gboName  = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
                $gboEmail = $gCurrentUser->getValue('EMAIL');
                $gboText  = $_POST['gbo_text'];
            }
            $senderName = $gboName;
            if (!strValidCharacters($gboEmail, 'email'))
            {
                $gboEmail = $gSettingsManager->getString('email_administrator');
                $senderName = 'Administrator '.$gCurrentOrganization->getValue('org_homepage');
            }
            try
            {
                $notification = new Email();
                $notification->adminNotification($gL10n->get('GBO_EMAIL_NOTIFICATION_TITLE'), $gL10n->get('GBO_EMAIL_NOTIFICATION_MESSAGE', array($gCurrentOrganization->getValue('org_longname'), $gboText, $gboName, date($gSettingsManager->getString('system_date')))), $senderName, $gboEmail);
            }
            catch (AdmException $e)
            {
                $e->showHtml();
            }
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);
        $gNavigation->deleteLastUrl();

        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES.'/guestbook/guestbook.php', array('headline' => $getHeadline));

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
            // => EXIT
        }

        admRedirect($url);
        // => EXIT
    }
}
elseif ($getMode === 2)
{
    // den Gaestebucheintrag loeschen...
    $guestbook->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($getMode === 5)
{
    // Gaestebuchkommentar loeschen...
    $gbComment->delete();

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
    // den Gaestebuchkommentar freischalten...
    $gbComment->moderate();
    // Freischalten erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif ($getMode === 4 || $getMode === 8)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_comment_request'] = $_POST;

    if ($getMode === 4)
    {
        // if login then fill name with login user
        if ($gCurrentUser->getValue('usr_id') > 0)
        {
            $_POST['gbc_name'] = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
        }

        // if user is not logged in and captcha is activated then check captcha
        if (!$gValidLogin && $gSettingsManager->getBool('enable_guestbook_captcha'))
        {
            try
            {
                FormValidation::checkCaptcha($_POST['captcha_code']);
            }
            catch (AdmException $e)
            {
                $e->showHtml();
                // => EXIT
            }
        }
    }

    // make html in description secure
    $_POST['gbc_text'] = admFuncVariableIsValid($_POST, 'gbc_text', 'html');

    // POST Variablen in das Gaestebuchkommentarobjekt schreiben
    foreach ($_POST as $key => $value) // TODO possible security issue
    {
        if (StringUtils::strStartsWith($key, 'gbc_'))
        {
            if (!$gbComment->setValue($key, $value))
            {
                // Daten wurden nicht uebernommen, Hinweis ausgeben
                if ($key === 'gbc_email')
                {
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))));
                    // => EXIT
                }
            }
        }
    }

    if ($getMode === 4)
    {
        $gbComment->setValue('gbc_gbo_id', $getGboId);
    }

    if ($gbComment->getValue('gbc_name') === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }
    elseif ($gbComment->getValue('gbc_text') === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_COMMENT'))));
        // => EXIT
    }
    else
    {
        // Gaestebuchkommentar speichern

        if ($gValidLogin)
        {
            // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
            $gbComment->setValue('gbc_name', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        }
        else
        {
            if ($gSettingsManager->getInt('flooding_protection_time') > 0)
            {
                // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                // einen GB-Eintrag/Kommentar erzeugt hat...
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_GUESTBOOK_COMMENTS.'
                         WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp() - ? -- $gSettingsManager->getInt(\'flooding_protection_time\')
                           AND gbc_ip_address = ? -- $gbComment->getValue(\'gbc_ip_adress\')';
                $pdoStatement = $gDb->queryPrepared($sql, array($gSettingsManager->getInt('flooding_protection_time'), $gbComment->getValue('gbc_ip_adress')));

                if ($pdoStatement->fetchColumn() > 0)
                {
                    // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', array($gSettingsManager->getInt('flooding_protection_time'))));
                    // => EXIT
                }
            }
        }

        // Bei Moderation wird die Nachricht zunächst nicht veröffentlicht
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $gbComment->setValue('gbc_locked', '1');
        }

        // Daten in Datenbank schreiben
        $returnCode = $gbComment->save();

        if ($returnCode === false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        else
        {
            // Benachrichtigungs-Email für neue Einträge
            if (!$gValidLogin)
            {
                $gbcName  = $gbComment->getValue('gbc_name');
                $gbcEmail = $gbComment->getValue('gbc_email');
            }
            else
            {
                $gbcName  = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
                $gbcEmail = $gCurrentUser->getValue('EMAIL');
            }
            $senderName = $gbcName;
            if ($gbcEmail === '')
            {
                $gbcEmail = $gSettingsManager->getString('email_administrator');
                $senderName = 'Administrator ' . $gCurrentOrganization->getValue('org_homepage');
            }
            $message = $gL10n->get(
                'GBO_EMAIL_NOTIFICATION_GBC_MESSAGE',
                array($gCurrentOrganization->getValue('org_longname'),
                $gbComment->getValue('gbc_text'),
                $gbcName,
                date($gSettingsManager->getString('system_date')))
            );
            try
            {
                $notification = new Email();
                $notification->adminNotification($gL10n->get('GBO_EMAIL_NOTIFICATION_GBC_TITLE'), $message, $senderName, $gbcEmail);
            }
            catch (AdmException $e)
            {
                $e->showHtml();
            }
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_comment_request']);
        $gNavigation->deleteLastUrl();

        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php', array('id' => $gbComment->getValue('gbc_gbo_id'), 'headline' => $getHeadline));

        // Bei Moderation Hinweis ausgeben dass Nachricht erst noch geprüft werden muss
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight()))
        {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
            // => EXIT
        }

        admRedirect($url);
        // => EXIT
    }
}
else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}
