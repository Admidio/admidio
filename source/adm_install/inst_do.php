<?php
/******************************************************************************
 * Einrichtungsscript fuer die MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * file : 0 (Default)
 *        1 config.php erstellen und an Browser schicken
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

require("../adm_program/system/function.php");
require("../adm_program/system/string.php");
require("../adm_program/system/date.php");

// Diese Funktion zeigt eine Fehlerausgabe an
// mode = 0 (Default) Fehlerausgabe
// mode = 1 Aktion wird durchgefuehrt (Abbrechen)
// mode = 2 Aktion erfolgreich durchgefuehrt
function showError($err_msg, $err_head = "Fehler", $mode = 1)
{
    global $g_root_path;

    echo '
    <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org -->
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
    <html>
    <head>
        <title>Admidio - Installation</title>

        <meta http-equiv="content-type" content="text/html; charset=ISO-8859-15">
        <meta name="author"   content="Markus Fassbender">
        <meta name="robots"   content="index,follow">
        <meta name="language" content="de">

        <link rel="stylesheet" type="text/css" href="../adm_config/main.css">
    </head>
    <body>
        <div style="margin-top: 10px; margin-bottom: 10px;" align="center"><br />
            <div class="formHead" style="width: 300px;">'. $err_head. '</div>
            <div class="formBody" style="width: 300px;">
                <p>'. $err_msg. '</p>
                <p><button id="zurueck" type="button" value="zurueck" onclick="';
                if($mode == 1)
                {
                    // Fehlermeldung (Zurueckgehen)
                    echo 'history.back()">
                    <img src="../adm_program/images/back.png" style="vertical-align: middle; padding-bottom: 1px;" width="16" height="16" border="0" alt="Zurueck">
                    &nbsp;Zur&uuml;ck';
                }
                elseif($mode == 2)
                {
                    // Erfolgreich durchgefuehrt
                    echo 'self.location.href=\'../adm_program/index.php\'">
                    <img src="../adm_program/images/table.png" style="vertical-align: middle; padding-bottom: 1px;" width="16" height="16" border="0" alt="Zurueck">
                    &nbsp;Admidio &Uuml;bersicht';
                }
                echo '</button></p>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

if($_GET['mode'] == 1)
{
    // Installation 1.Seite
    session_start();

    // Tabellenpraefix pruefen
    $g_tbl_praefix = $_POST['praefix'];
    if(strlen($g_tbl_praefix) == 0)
    {
        $g_tbl_praefix = "adm";
    }
    else
    {
        // wenn letztes Zeichen ein _ dann abschneiden
        if(strrpos($g_tbl_praefix, "_")+1 == strlen($g_tbl_praefix))
        {
            $g_tbl_praefix = substr($g_tbl_praefix, 0, strlen($g_tbl_praefix)-1);
        }

        // nur gueltige Zeichen zulassen
        $anz = strspn($g_tbl_praefix, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_");

        if($anz != strlen($g_tbl_praefix))
        {
            showError("Das Tabellenpr&auml;fix enth&auml;lt ung&uuml;ltige Zeichen !");
        }
    }

    // Session-Variablen merken
    $_SESSION['praefix']  = $g_tbl_praefix;
    $_SESSION['server']   = $_POST['server'];
    $_SESSION['user']     = $_POST['user'];
    $_SESSION['password'] = $_POST['password'];
    $_SESSION['database'] = $_POST['database'];
    $_SESSION['verein-name-kurz'] = $_POST['verein-name-kurz'];
}
elseif($_GET['mode'] == 2)
{
    // Installation 2.Seite
    session_start();

    // MySQL-Zugangsdaten in config.php schreiben
    // Datei auslesen
    $filename     = "config.php";
    $config_file  = fopen($filename, "r");
    $file_content = fread($config_file, filesize($filename));
    fclose($config_file);

    // den Root-Pfad ermitteln
    $root_path = $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
    $root_path = substr($root_path, 0, strpos($root_path, "/adm_install"));
    if(!strpos($root_path, "http://"))
    {
        $root_path = "http://". $root_path;
    }

    $file_content = str_replace("%PRAEFIX%", $_SESSION['praefix'], $file_content);
    $file_content = str_replace("%SERVER%",  $_SESSION['server'],  $file_content);
    $file_content = str_replace("%USER%",    $_SESSION['user'],    $file_content);
    $file_content = str_replace("%PASSWORD%",  $_SESSION['password'], $file_content);
    $file_content = str_replace("%DATABASE%",  $_SESSION['database'], $file_content);
    $file_content = str_replace("%ROOT_PATH%", $root_path,            $file_content);
    $file_content = str_replace("%DOMAIN%",    $_SERVER['HTTP_HOST'], $file_content);
    $file_content = str_replace("%ORGANIZATION%", $_SESSION['verein-name-kurz'], $file_content);

    // die erstellte Config-Datei an den User schicken
    $filename = "config.php";
    header("Content-Type: application/force-download");
    header("Content-Type: application/download");
    header("Content-Type: text/csv; charset=ISO-8859-1");
    header("Content-Disposition: attachment; filename=$filename");
    echo $file_content;
    exit();
}
elseif($_GET['mode'] == 5)
{
    if(file_exists("../adm_config/config.php"))
    {
        showError("Die Datenbank wurde erfolgreich angelegt und die Datei config.php erstellt.<br><br>
            Sie k&ouml;nnen nun mit Admidio arbeiten.", "Fertig", 2);
    }
    else
    {
        showError("Die Datei <b>config.php</b> befindet sich nicht im Verzeichnis <b>adm_config</b> !");
    }
}
else
{
    require("../adm_config/config.php");
}

// Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = "adm";
}

// Defines fuer alle Datenbanktabellen
define("TBL_ANNOUNCEMENTS",     $g_tbl_praefix. "_announcements");
define("TBL_DATES",             $g_tbl_praefix. "_dates");
define("TBL_FOLDERS",           $g_tbl_praefix. "_folders");
define("TBL_FOLDER_ROLES",      $g_tbl_praefix. "_folder_roles");
define("TBL_GUESTBOOK",         $g_tbl_praefix. "_guestbook");
define("TBL_GUESTBOOK_COMMENTS",$g_tbl_praefix. "_guestbook_comments");
define("TBL_LINKS",             $g_tbl_praefix. "_links");
define("TBL_MEMBERS",           $g_tbl_praefix. "_members");
define("TBL_ORGANIZATIONS",     $g_tbl_praefix. "_organizations");
define("TBL_PHOTOS",            $g_tbl_praefix. "_photos");
define("TBL_PREFERENCES",       $g_tbl_praefix. "_preferences");
define("TBL_ROLE_CATEGORIES",   $g_tbl_praefix. "_role_categories");
define("TBL_ROLE_DEPENDENCIES", $g_tbl_praefix. "_role_dependencies");
define("TBL_ROLES",             $g_tbl_praefix. "_roles");
define("TBL_SESSIONS",          $g_tbl_praefix. "_sessions");
define("TBL_USERS",             $g_tbl_praefix. "_users");
define("TBL_USER_DATA",         $g_tbl_praefix. "_user_data");
define("TBL_USER_FIELDS",       $g_tbl_praefix. "_user_fields");

/*------------------------------------------------------------*/
// Eingabefelder pruefen
/*------------------------------------------------------------*/

if(strlen($_POST['server'])    == 0
|| strlen($_POST['user'])     == 0
// bei localhost muss es kein Passwort geben
//|| strlen($_POST['password'])  == 0
|| strlen($_POST['database']) == 0 )
{
    showError("Es sind nicht alle Zugangsdaten zur MySql-Datenbank eingegeben worden !");
}

if($_GET['mode'] == 3)
{
    if($_POST['version'] == 0)
    {
        showError("Bei einem Update m&uuml;ssen Sie Ihre bisherige Version angeben !");
    }
}

// bei Installation oder hinzufuegen einer Organisation
if($_GET['mode'] == 1 || $_GET['mode'] == 4)
{
    $_POST['user-surname']   = strStripTags($_POST['user-surname']);
    $_POST['user-firstname'] = strStripTags($_POST['user-firstname']);
    $_POST['user-email']     = strStripTags($_POST['user-email']);
    $_POST['user-login']     = strStripTags($_POST['user-login']);

    if(strlen($_POST['user-surname'])   == 0
    || strlen($_POST['user-firstname']) == 0
    || strlen($_POST['user-email']) == 0
    || strlen($_POST['user-login'])     == 0
    || strlen($_POST['user-passwort'])  == 0 )
    {
        showError("Es sind nicht alle Benutzerdaten eingegeben worden !");
    }

    if(isValidEmailAddress($_POST['user-email']) == false)
    {
        showError("Die E-Mail-Adresse ist nicht g&uuml;ltig.");
    }

    if(  strlen($_POST['verein-name-lang']) == 0
    || strlen($_POST['verein-name-kurz']) == 0 )
    {
        showError("Sie m&uuml;ssen einen Namen f&uuml;r die Organisation / den Verein eingeben !");
    }
}

/*------------------------------------------------------------*/
// Daten verarbeiten
/*------------------------------------------------------------*/

// Verbindung zu Datenbank herstellen
$connection = mysql_connect ($_POST['server'], $_POST['user'], $_POST['password'])
              or showError("Es konnte keine Verbindung zur Datenbank hergestellt werden.<br /><br />
                            Pr&uuml;fen Sie noch einmal Ihre MySql-Zugangsdaten !");

if(!mysql_select_db($_POST['database'], $connection ))
{
    showError("Die angegebene Datenbank <b>". $_POST['database']. "</b> konnte nicht gefunden werden !");
}

if($_GET['mode'] == 1)
{
    $error    = 0;
    $filename = "db.sql";
    $file     = fopen($filename, "r")
                or showError("Die Datei <b>db.sql</b> konnte nicht im Verzeichnis <b>adm_install</b> gefunden werden.");
    $content  = fread($file, filesize($filename));
    $sql_arr  = explode(";", $content);
    fclose($file);

    foreach($sql_arr as $sql)
    {
        if(strlen(trim($sql)) > 0)
        {
            // Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
            $sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
            $result = mysql_query($sql, $connection);
            if(!$result)
            {
                showError(mysql_error());
                $error++;
            }
        }
    }
    if($error > 0)
    {
        exit();
    }

    // Default-Daten anlegen

    // Messenger anlegen
    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_shortname, usf_type, usf_name, usf_description)
                                       VALUES (NULL, 'MESSENGER', 'AIM', 'AOL Instant Messenger') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_shortname, usf_type, usf_name, usf_description)
                                       VALUES (NULL, 'MESSENGER', 'ICQ', 'ICQ') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_shortname, usf_type, usf_name, usf_description)
                                       VALUES (NULL, 'MESSENGER', 'MSN', 'MSN Messenger') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_shortname, usf_type, usf_name, usf_description)
                                       VALUES (NULL, 'MESSENGER', 'Yahoo', 'Yahoo! Messenger') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_shortname, usf_type, usf_name, usf_description)
                                       VALUES (NULL, 'MESSENGER', 'Skype', 'Skype') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_shortname, usf_type, usf_name, usf_description)
                                       VALUES (NULL, 'MESSENGER', 'Google Talk', 'Google Talk') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

if($_GET['mode'] == 3)
{
    // Updatescripte fuer die Datenbank verarbeiten
    if($_POST['version'] > 0)
    {
        for($i = $_POST['version']; $i <= 3; $i++)
        {
            $error = 0;
            if($i == 1)
            {
                $filename = "upd_1_1_db.sql";
            }
            elseif($i == 3)
            {
                $filename = "upd_1_3_db.sql";
            }
            else
            {
                $filename = "";
            }

            if(strlen($filename) > 0)
            {
                $file    = fopen($filename, "r")
                           or showError("Die Datei <b>$filename</b> konnte nicht im Verzeichnis <b>adm_install</b> gefunden werden.");
                $content = fread($file, filesize($filename));
                $sql_arr = explode(";", $content);
                fclose($file);

                foreach($sql_arr as $sql)
                {
                    if(strlen(trim($sql)) > 0)
                    {
                        // Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
                        $sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
                        $result = mysql_query($sql, $connection);
                        if(!$result)
                        {
                            showError(mysql_error());
                            $error++;
                        }
                    }
                }
                if($error > 0)
                {
                    exit();
                }
            }

            if($i == 1)
            {
                include("upd_1_1_konv.php");
            }
            elseif($i == 2)
            {
                include("upd_1_2_konv.php");
            }
            elseif($i == 3)
            {
                include("upd_1_3_konv.php");
            }
        }
    }
    else
    {
        showError("Sie haben Ihre bisherige Version nicht angegeben !");
    }
}

if($_GET['mode'] == 1 || $_GET['mode'] == 4)
{
    // neue Organisation anlegen

    $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE org_shortname = {0} ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result)
    {
        showError(mysql_error());
    }

    if(mysql_num_rows($result) > 0)
    {
        showError("Eine Organisation mit dem angegebenen kurzen Namen <b>". $_POST['verein-name-kurz']. "</b> existiert bereits.<br /><br />
                   W&auml;hlen Sie bitte einen anderen kurzen Namen !");
    }

    $sql = "INSERT INTO ". TBL_ORGANIZATIONS. " (org_shortname, org_longname, org_homepage)
                                         VALUES ({0}, {1}, '". $_SERVER['HTTP_HOST']. "') ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz'], $_POST['verein-name-lang']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $org_id = mysql_insert_id($connection);

    // Einstellungen anlegen
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($org_id, 'max_email_attachment_size', '1024') ";
    $result = mysql_query($sql, $connection);
    db_error($result);

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($org_id, 'max_file_upload_size', '3072') ";
    $result = mysql_query($sql, $connection);
    db_error($result);

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($org_id, 'send_email_extern', '0') ";
    $result = mysql_query($sql, $connection);
    db_error($result);

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($org_id, 'enable_rss', '1') ";
    $result = mysql_query($sql, $connection);
    db_error($result);

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($org_id, 'enable_bbcode', '1') ";
    $result = mysql_query($sql, $connection);
    db_error($result);

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($org_id, 'email_administrator', 'webmaster@". $_SERVER['HTTP_HOST']. "') ";
    $result = mysql_query($sql, $connection);
    db_error($result);

    // Rollen-Kategorie eintragen
    $sql = "INSERT INTO ". TBL_ROLE_CATEGORIES. " (rlc_org_shortname, rlc_name, rlc_locked)
                                           VALUES ({0}, 'Allgemein', 1)";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    $category_common = mysql_insert_id();

    if(!$result) showError(mysql_error());
    $sql = "INSERT INTO ". TBL_ROLE_CATEGORIES. " (rlc_org_shortname, rlc_name)
                                           VALUES ({0}, 'Gruppen')";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $sql = "INSERT INTO ". TBL_ROLE_CATEGORIES. " (rlc_org_shortname, rlc_name)
                                           VALUES ({0}, 'Kurse')";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $sql = "INSERT INTO ". TBL_ROLE_CATEGORIES. " (rlc_org_shortname, rlc_name)
                                           VALUES ({0}, 'Mannschaften')";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    // nun die Default-Rollen anlegen

    // Webmaster
    $sql = "INSERT INTO ". TBL_ROLES. " (rol_org_shortname, rol_rlc_id, rol_name, rol_description, rol_valid,
                                         rol_moderation, rol_announcements, rol_dates, rol_download, 
                                         rol_guestbook, rol_guestbook_comments, rol_photo, rol_weblinks,
                                         rol_edit_user, rol_mail_logout, rol_mail_login)
                                 VALUES ({0}, $category_common, 'Webmaster', 'Gruppe der Administratoren des Systems', 1,
                                         1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1) ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    // Mitglied
    $sql = "INSERT INTO ". TBL_ROLES. " (rol_org_shortname, rol_rlc_id, rol_name, rol_description, rol_valid,
                                         rol_moderation, rol_announcements, rol_dates, rol_download,
                                         rol_guestbook, rol_guestbook_comments, rol_photo, rol_weblinks,
                                         rol_edit_user, rol_mail_logout, rol_mail_login)
                                 VALUES ({0}, $category_common, 'Mitglied', 'Alle Mitglieder der Organisation', 1,
                                         0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1) ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    // Vorstand
    $sql = "INSERT INTO ". TBL_ROLES. " (rol_org_shortname, rol_rlc_id, rol_name, rol_description, rol_valid,
                                         rol_moderation, rol_announcements, rol_dates, rol_download, 
                                         rol_guestbook, rol_guestbook_comments, rol_photo, rol_weblinks,
                                         rol_edit_user, rol_mail_logout, rol_mail_login)
                                 VALUES ({0}, $category_common, 'Vorstand', 'Vorstand des Vereins', 1,
                                         0, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1) ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

// bei Installation oder hinzufuegen einer Organisation
if($_GET['mode'] == 1 || $_GET['mode'] == 4)
{
    // User Webmaster anlegen

    $pw_md5 = md5($_POST['user-passwort']);
    $sql = "INSERT INTO ". TBL_USERS. " (usr_last_name, usr_first_name, usr_email, usr_login_name, usr_password, usr_valid)
                                 VALUES ({0}, {1}, {2}, {3}, '$pw_md5', 1) ";
    $sql = prepareSQL($sql, array($_POST['user-surname'], $_POST['user-firstname'], $_POST['user-email'], $_POST['user-login']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $user_id = mysql_insert_id();

    // Mitgliedschaft "Webmaster" anlegen
    $sql = "SELECT rol_id FROM ". TBL_ROLES. "
             WHERE rol_org_shortname = {0}
               AND rol_name          = 'Webmaster' ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $row = mysql_fetch_array($result);

    $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin, mem_valid)
                                   VALUES ($row[0], $user_id, NOW(), 1) ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    // Mitgliedschaft "Mitglied" anlegen
    $sql = "SELECT rol_id FROM ". TBL_ROLES. "
             WHERE rol_org_shortname = {0}
               AND rol_name          = 'Mitglied' ";
    $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $row = mysql_fetch_array($result);

    $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin, mem_valid)
                                   VALUES ($row[0], $user_id, NOW(), 1) ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

if($_GET['mode'] == 1)
{
    $location = "location: index.php?mode=2";
    header($location);
    exit();
}
else
{
    showError("Die Einrichtung der Datenbank konnte erfolgreich abgeschlossen werden.", "Fertig", 2);
}
?>