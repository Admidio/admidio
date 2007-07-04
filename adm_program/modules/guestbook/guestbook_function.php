<?php
/******************************************************************************
 * Verschiedene Funktionen fuer das Gaestebuch
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * id:    ID die bearbeitet werden soll
 * mode:     1 - Neue Gaestebucheintrag anlegen
 *           2 - Gaestebucheintrag loeschen
 *           3 - Gaestebucheintrag editieren
 *           4 - Kommentar zu einem Eintrag anlegen
 *           5 - Kommentar eines Gaestebucheintrages loeschen
 *           6 - Nachfrage ob Gaestebucheintrag geloescht werden soll
 *           7 - Nachfrage ob Gaestebuchkommentar geloescht werden soll
 *           8 - Kommentar eines Gaestebucheintrages editieren
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Gaestebuch steht
 *           (Default) Gaestebuch
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}


// Uebergabevariablen pruefen

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}


if (array_key_exists("mode", $_GET))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }
}


if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "G&auml;stebuch";
}


// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 4 || $_GET['mode'] == 5 || $_GET['mode'] == 6 || $_GET['mode'] == 7 || $_GET['mode'] == 8 )
{

    if ($_GET['mode'] == 4)
    {
        // Wenn nicht jeder kommentieren darf, muss man eingeloggt zu sein
        if ($g_preferences['enable_gbook_comments4all'] == 0)
        {
            require("../../system/login_valid.php");

            // Ausserdem werden dann commentGuestbook-Rechte benoetigt
            if (!$g_current_user->commentGuestbookRight())
            {
                $g_message->show("norights");
            }
        }

    }
    else
    {
        // Der User muss fuer die anderen Modes auf jeden Fall eingeloggt sein
        require("../../system/login_valid.php");
    }



    if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 5 || $_GET['mode'] == 6 || $_GET['mode'] == 7 || $_GET['mode'] == 8)
    {
        // Fuer die modes 2,3,5,6,7 und 8 werden editGuestbook-Rechte benoetigt
        if(!$g_current_user->editGuestbookRight())
        {
            $g_message->show("norights");
        }
    }




    // Abschliessend wird jetzt noch geprueft ob die uebergebene ID ueberhaupt zur Orga gehoert
    if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 4 || $_GET['mode'] == 6 )
    {
        $sql    = "SELECT * FROM ". TBL_GUESTBOOK. " WHERE gbo_id = {0} and gbo_org_id = $g_current_organization->id";
        $sql    = prepareSQL($sql, array($_GET['id']));
    }

    if ($_GET['mode'] == 5 || $_GET['mode'] == 7 || $_GET['mode'] == 8)
    {
        $sql    = "SELECT * FROM ". TBL_GUESTBOOK_COMMENTS. ", ". TBL_GUESTBOOK. " WHERE gbc_id = {0} and gbc_gbo_id = gbo_id and gbo_org_id = $g_current_organization->id";
        $sql    = prepareSQL($sql, array($_GET['id']));
    }

    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    if (mysql_num_rows($result) == 0)
    {
        $g_message->show("invalid");
    }

    $gbObject = mysql_fetch_object($result);

}


$err_code = "";
$err_text = "";


if ($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_REQUEST;


    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($_GET["mode"] == 1 && !$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
        {
            $g_message->show("captcha_code");
        }
    }


    // Daten fuer die DB werden nun aufbereitet...

    $name      = strStripTags($_POST['name']);
    $text      = strStripTags($_POST['entry']);

    $email     = strStripTags($_POST['email']);
    if (!isValidEmailAddress($email))
    {
        // falls die Email ein ungueltiges Format aufweist wird sie einfach auf null gesetzt
        $email = null;
    }


    $homepage  = strStripTags(trim($_POST['homepage']));
    if (strlen($homepage) != 0)
    {
        // Die Webadresse wird jetzt, falls sie nicht mit http:// oder https:// beginnt, entsprechend aufbereitet
        if (substr($homepage, 0, 7) != 'http://' && substr($homepage, 0, 8) != 'https://' )
        {
            $homepage = "http://". $homepage;
        }
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $actDate   = date("Y.m.d G:i:s", time());


    if (strlen($name) > 0 && strlen($text)  > 0)
    {

        // Gaestebucheintrag speichern

        if ($_GET['id'] == 0)
        {
            if ($g_valid_login)
            {
                // Falls der User eingeloggt ist wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $realName = $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname");

                $sql = "INSERT INTO ". TBL_GUESTBOOK. " (gbo_org_id, gbo_usr_id, gbo_name, gbo_text, gbo_email,
                                                         gbo_homepage, gbo_timestamp, gbo_ip_address)
                                         VALUES ($g_current_organization->id, ". $g_current_user->getValue("usr_id"). ", '$realName', {0}, {1},
                                                 {2}, '$actDate', '$ipAddress')";

                $sql    = prepareSQL($sql, array($text, $email, $homepage));
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);

            }
            else
            {
                if ($g_preferences['flooding_protection_time'] != 0)
                {
                    // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                    // einen GB-Eintrag erzeugt hat...
                    $sql = "SELECT count(*) FROM ". TBL_GUESTBOOK. "
                            where unix_timestamp(gbo_timestamp) > unix_timestamp()-". $g_preferences['flooding_protection_time']. "
                              and gbo_org_id = $g_current_organization->id
                              and gbo_ip_address = '$ipAddress' ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);
                    $row = mysql_fetch_array($result);
                    if($row[0] > 0)
                    {
                        //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                        $g_message->show("flooding_protection", $g_preferences['flooding_protection_time']);
                    }
                }

                // Falls er nicht eingeloggt ist, gibt es das sql-Statement natürlich ohne die UserID
                $sql = "INSERT INTO ". TBL_GUESTBOOK. " (gbo_org_id, gbo_name, gbo_text, gbo_email,
                                                         gbo_homepage, gbo_timestamp, gbo_ip_address)
                                         VALUES ($g_current_organization->id, {0}, {1}, {2},
                                                 {3}, '$actDate', '$ipAddress')";

                $sql    = prepareSQL($sql, array($name, $text, $email, $homepage));
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
            }

        }
        else
        {
            $sql = "UPDATE ". TBL_GUESTBOOK. " SET  gbo_name     = {0}
                                                  , gbo_text     = {1}
                                                  , gbo_email    = {2}
                                                  , gbo_homepage = {3}
                                                  , gbo_last_change   = '$actDate'
                                                  , gbo_usr_id_change = ". $g_current_user->getValue("usr_id"). "
                     WHERE gbo_id = {4}";
            $sql    = prepareSQL($sql, array($name, $text, $email, $homepage, $_GET['id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }


        $location = "Location: $g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET['headline'];
        header($location);
        exit();
    }
    else
    {
        if(strlen($name) > 0)
        {
            $err_text = "Text";
        }
        else
        {
            $err_text = "Name";
        }
        $err_code = "feld";
    }
}

elseif($_GET["mode"] == 2)
{
    //erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
    $sql = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_gbo_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    //dann den Eintrag selber loeschen...
    $sql = "DELETE FROM ". TBL_GUESTBOOK. " WHERE gbo_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}

elseif($_GET["mode"] == 4 || $_GET["mode"] == 8)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_comment_request'] = $_REQUEST;


    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($_GET["mode"] == 4 && !$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
        {
            $g_message->show("captcha_code");
        }
    }


    //Daten fuer die DB vorbereiten
    $name      = strStripTags($_POST['name']);
    $text      = strStripTags($_POST['entry']);

    $email     = strStripTags($_POST['email']);
    if (!isValidEmailAddress($email))
    {
        // falls die Email ein ungueltiges Format aufweist wird sie einfach auf null gesetzt
        $email = null;
    }


    $actDate   = date("Y.m.d G:i:s", time());
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    if (strlen($name) > 0 && strlen($text)  > 0)
    {

        if ($_GET["mode"] == 4)
        {
            if ($g_valid_login)
            {
                // Falls der User eingeloggt ist wird die aktuelle UserId und der korrekte Name mitabgespeichert...
                $realName = $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname");

                $sql = "INSERT INTO ". TBL_GUESTBOOK_COMMENTS. " (gbc_gbo_id, gbc_usr_id, gbc_name, gbc_text, gbc_email, gbc_timestamp, gbc_ip_address)
                                                         VALUES ({0}, ". $g_current_user->getValue("usr_id"). ", '$realName', {1}, {2}, '$actDate', '$ipAddress')";
                $sql    = prepareSQL($sql, array($_GET['id'], $text, $email));
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
            }
            else
            {
                if ($g_preferences['flooding_protection_time'] != 0)
                {
                    // Falls er nicht eingeloggt ist, wird vor dem Abspeichern noch geprueft ob der
                    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
                    // einen GB-Eintrag/Kommentar erzeugt hat...
                    $sql = "SELECT count(*) FROM ". TBL_GUESTBOOK_COMMENTS. "
                            where unix_timestamp(gbc_timestamp) > unix_timestamp()-". $g_preferences['flooding_protection_time']. "
                              and gbc_ip_address = '$ipAddress' ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);
                    $row = mysql_fetch_array($result);
                    if($row[0] > 0)
                    {
                        //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
                        $g_message->show("flooding_protection", $g_preferences['flooding_protection_time']);
                    }
                }

                // Falls er nicht eingeloggt ist, gibt es das sql-Statement natürlich ohne die UserID
                $sql = "INSERT INTO ". TBL_GUESTBOOK_COMMENTS. " (gbc_gbo_id, gbc_name, gbc_text, gbc_email, gbc_timestamp, gbc_ip_address)
                                                         VALUES ({0}, {1}, {2}, {3}, '$actDate', '$ipAddress')";
                $sql    = prepareSQL($sql, array($_GET['id'], $name, $text, $email));

                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);

            }
        }
        else
        {
            // hier wird der Eintrag natuerlich nur modifiziert
            $sql = "UPDATE ". TBL_GUESTBOOK_COMMENTS. " SET  gbc_name     = {0}
                                                           , gbc_text     = {1}
                                                           , gbc_email    = {2}
                                                           , gbc_last_change   = '$actDate'
                                                           , gbc_usr_id_change = ". $g_current_user->getValue("usr_id"). "
                     WHERE gbc_id = {3}";
            $sql    = prepareSQL($sql, array($name, $text, $email, $_GET['id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_comment_request']);

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }

        $location = "Location: $g_root_path/adm_program/modules/guestbook/guestbook.php?id=". $gbObject->gbo_id. "&headline=". $_GET['headline'];
        header($location);
        exit();

    }
    else
    {
        if(strlen($name) > 0)
        {
            $err_text = "Text";
        }
        else
        {
            $err_text = "Name";
        }
        $err_code = "feld";
    }
}

elseif ($_GET["mode"] == 5)
{
    //Gaestebuchkommentar loeschen...
    $sql = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}

elseif ($_GET["mode"] == 6)
{
    //Nachfrage ob Gaestebucheintrag geloescht werden soll
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/guestbook/guestbook_function.php?id=$_GET[id]&mode=2&url=$g_root_path/adm_program/modules/guestbook/guestbook.php");
    $g_message->show("delete_gbook_entry", utf8_encode($gbObject->gbo_name));
}

elseif ($_GET["mode"] == 7)
{
    //Nachfrage ob Gaestebucheintrag geloescht werden soll
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/guestbook/guestbook_function.php?id=$_GET[id]&mode=5&url=$g_root_path/adm_program/modules/guestbook/guestbook.php?id=$gbObject->gbc_gbo_id");
    $g_message->show("delete_gbook_comment", utf8_encode($gbObject->gbc_name));
}

else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $g_message->show("invalid");
}

$g_message->show($err_code, $err_text);
?>