<?php
/******************************************************************************
 * Verschiedene Funktionen fuer das Gaestebuch
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * id:    ID die bearbeitet werden soll
 * mode:     1 - Neue Gaestebucheintrag anlegen
 *           2 - Gaestebucheintrag loeschen
 *           3 - Gaestebucheintrag editieren
 *           4 - Kommentar zu einem Eintrag anlegen
 *           5 - Kommentar eines Gaestebucheintrages loeschen
 *           8 - Kommentar eines Gaestebucheintrages editieren
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Gaestebuch steht
 *           (Default) Gaestebuch
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/table_guestbook.php');
require('../../system/classes/table_guestbook_comment.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}
elseif($g_preferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}


// Uebergabevariablen pruefen

if (array_key_exists('id', $_GET))
{
    if (is_numeric($_GET['id']) == false)
    {
        $g_message->show('invalid');
    }
}
else
{
    $_GET['id'] = 0;
}


if (array_key_exists('mode', $_GET))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show('invalid');
    }
}


if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = 'Gästebuch';
}


// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 4 || $_GET['mode'] == 5 || $_GET['mode'] == 6 || $_GET['mode'] == 7 || $_GET['mode'] == 8 )
{

    if ($_GET['mode'] == 4)
    {
        // Wenn nicht jeder kommentieren darf, muss man eingeloggt zu sein
        if ($g_preferences['enable_gbook_comments4all'] == 0)
        {
            require('../../system/login_valid.php');

            // Ausserdem werden dann commentGuestbook-Rechte benoetigt
            if (!$g_current_user->commentGuestbookRight())
            {
                $g_message->show('norights');
            }
        }

    }
    else
    {
        // Der User muss fuer die anderen Modes auf jeden Fall eingeloggt sein
        require('../../system/login_valid.php');
    }



    if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 5 || $_GET['mode'] == 6 || $_GET['mode'] == 7 || $_GET['mode'] == 8)
    {
        // Fuer die modes 2,3,5,6,7 und 8 werden editGuestbook-Rechte benoetigt
        if(!$g_current_user->editGuestbookRight())
        {
            $g_message->show('norights');
        }
    }
}

if ($_GET['mode'] == 1 || $_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 6)
{
    // Gaestebuchobjekt anlegen
    $guestbook = new TableGuestbook($g_db);
    
    if($_GET['id'] > 0)
    {
        $guestbook->readData($_GET['id']);
        
        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if($guestbook->getValue('gbo_org_id') != $g_current_organization->getValue('org_id'))
        {
            $g_message->show('norights');
        }
    }
}
else
{
    // Gaestebuchobjekt anlegen
    $guestbook_comment = new TableGuestbookComment($g_db);
    
    if($_GET['id'] > 0 && $_GET['mode'] != 4)
    {
        $guestbook_comment->readData($_GET['id']);
        
        // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
        if($guestbook_comment->getValue('gbo_org_id') != $g_current_organization->getValue('org_id'))
        {
            $g_message->show('norights');
        }
    }
}


if ($_GET['mode'] == 1 || $_GET['mode'] == 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_REQUEST;


    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($_GET['mode'] == 1 && !$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
        {
            $g_message->show('captcha_code');
        }
    }


    // POST Variablen in das Gaestebuchobjekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'gbo_') === 0)
        {
            $guestbook->setValue($key, $value);
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
                $guestbook->setValue('gbo_name', $g_current_user->getValue('Vorname'). ' '. $g_current_user->getValue('Nachname'));
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
                        where unix_timestamp(gbo_timestamp) > unix_timestamp()-'. $g_preferences['flooding_protection_time']. '
                          and gbo_org_id = '. $g_current_organization->getValue('org_id'). '
                          and gbo_ip_address = "'. $guestbook->getValue('gbo_ip_adress'). '"';
                $result = $g_db->query($sql);
                $row    = $g_db->fetch_array($result);
                if($row[0] > 0)
                {
                    //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $g_message->show('flooding_protection', $g_preferences['flooding_protection_time']);
                }
            }
        }
        
        // Daten in Datenbank schreiben
        $return_code = $guestbook->save();
    
        if($return_code < 0)
        {
            $g_message->show('norights');
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);
        $_SESSION['navigation']->deleteLastUrl();

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }


        header('Location: '.$g_root_path.'/adm_program/modules/guestbook/guestbook.php?headline='. $_GET['headline']);
        exit();
    }
    else
    {
        if(strlen($guestbook->getValue('gbo_name')) > 0)
        {
            $g_message->show('feld', 'Text');
        }
        else
        {
            $g_message->show('feld', 'Name');
        }
    }
}

elseif($_GET['mode'] == 2)
{
    // den Gaestebucheintrag loeschen...
    $guestbook->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}

elseif($_GET['mode'] == 4 || $_GET['mode'] == 8)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_comment_request'] = $_REQUEST;


    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($_GET['mode'] == 4 && !$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
        {
            $g_message->show('captcha_code');
        }
    }

    // POST Variablen in das Gaestebuchkommentarobjekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'gbc_') === 0)
        {
            $guestbook_comment->setValue($key, $value);
        }
    }
    
    if($_GET['mode'] == 4)
    {
        $guestbook_comment->setValue('gbc_gbo_id', $_GET['id']);
    }

    if (strlen($guestbook_comment->getValue('gbc_name')) > 0 && strlen($guestbook_comment->getValue('gbc_text'))  > 0)
    {
        // Gaestebuchkommentar speichern
        
        if($g_valid_login)
        {
            if(strlen($guestbook_comment->getValue('gbc_name')) == 0)
            {
                // Falls der User eingeloggt ist, wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $guestbook_comment->setValue('gbc_name', $g_current_user->getValue('Vorname'). ' '. $g_current_user->getValue('Nachname'));
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
                         WHERE unix_timestamp(gbc_timestamp) > unix_timestamp()-'. $g_preferences['flooding_protection_time']. '
                           AND gbc_ip_address = "'. $guestbook_comment->getValue('gbc_ip_adress'). '"';
                $result = $g_db->query($sql);
                $row = $g_db->fetch_array($result);
                if($row[0] > 0)
                {
                    //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                    $g_message->show('flooding_protection', $g_preferences['flooding_protection_time']);
                }
            }
        }
        
        // Daten in Datenbank schreiben
        $return_code = $guestbook_comment->save();
    
        if($return_code < 0)
        {
            $g_message->show('norights');
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_comment_request']);
        $_SESSION['navigation']->deleteLastUrl();

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }

        header('Location: '.$g_root_path.'/adm_program/modules/guestbook/guestbook.php?id='. $guestbook_comment->getValue('gbc_gbo_id'). '&headline='. $_GET['headline']);
        exit();
    }
    else
    {
        if(strlen($guestbook_comment->getValue('gbc_name')) > 0)
        {
            $g_message->show('feld', 'Text');
        }
        else
        {
            $g_message->show('feld', 'Name');
        }
    }
}

elseif ($_GET['mode'] == 5)
{
    //Gaestebuchkommentar loeschen...
    $guestbook_comment->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}

else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $g_message->show('invalid');
}
?>