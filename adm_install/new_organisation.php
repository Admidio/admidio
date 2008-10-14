<?php
/******************************************************************************
 * Einrichtung einer weiteren Organisation 
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode     = 1 : (Default) Willkommen zur Installation
 *            2 : Organisationsnamen eingeben
 *            3 : Administrator anlegen
 *            4 : Konfigurationsdatei erzeugen
 *            5 : Konfigurationsdatei herunterladen
 *            6 : Installation starten
 *
 *****************************************************************************/

require_once("install_functions.php");

// Uebergabevariablen pruefen

if(isset($_GET['mode']) && is_numeric($_GET['mode']))
{
   $req_mode = $_GET['mode'];
}
else
{
    $req_mode = 1;
}

session_name('admidio_php_session_id');
session_start();

// Konstanten und Konfigurationsdatei einbinden
require_once(substr(__FILE__, 0, strpos(__FILE__, "adm_install")-1). "/config.php");
require_once(substr(__FILE__, 0, strpos(__FILE__, "adm_install")-1). "/adm_program/system/constants.php");

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = "adm";
}

// Default-DB-Type ist immer MySql
if(!isset($g_db_type))
{
    $g_db_type = "mysql";
}

require_once(SERVER_PATH. "/adm_program/system/db/". $g_db_type. ".php");
require_once(SERVER_PATH. "/adm_program/system/string.php");
require_once(SERVER_PATH. "/adm_program/system/function.php");
require_once(SERVER_PATH. "/adm_program/system/classes/organization.php");
require_once(SERVER_PATH. "/adm_program/system/classes/user.php");
require_once(SERVER_PATH. "/adm_program/system/classes/role.php");
require_once(SERVER_PATH. "/adm_program/system/classes/text.php");
require_once(SERVER_PATH. "/adm_program/system/classes/table_members.php");

$message  = "";
$act_date     = date("Y-m-d", time());
$act_datetime = date("Y-m-d H:i:s", time());

if($req_mode == 1)
{
    // Willkommen zur Installation
    session_destroy();
    $message = '<strong>Willkommen zur Einrichtung einer weiteren Organisation</strong><br /><br />
                Auf den nächsten Seiten musst du einige notwendige Informationen der neuen Organisation eingeben.';
    showPage($message, "new_organisation.php?mode=2", "forward.png", "Organisation festlegen", 3);
}
elseif($req_mode == 2)
{
    // Formular vorbelegen
    if(isset($_SESSION['orga_name_short']))
    {
        $orga_name_short = $_SESSION['orga_name_short'];
        $orga_name_long  = $_SESSION['orga_name_long'];
    }
    else
    {
        $orga_name_short = "";
        $orga_name_long  = "";
    }

    $message = '<strong>Organisation festlegen</strong><br /><br />
                Gib in diesem Formular die Abkürzung und den offiziellen Namen der Organisation / Verein
                ein, die du nun hinzufügen möchtest.

                <div class="groupBox">
                    <div class="groupBoxHeadline">Name der Organisation</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="orga_name_short">Name (Abkürzung):</label></dt>
                                    <dd><input type="text" name="orga_name_short" id="orga_name_short" style="width: 80px;" maxlength="10" value="'. $orga_name_short. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="orga_name_long">Name (ausgeschrieben):</label></dt>
                                    <dd><input type="text" name="orga_name_long" id="orga_name_long" style="width: 250px;" maxlength="60" value="'. $orga_name_long. '" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, "new_organisation.php?mode=3", "forward.png", "Administrator festlegen", 3);
}
elseif($req_mode == 3)
{
    // Daten des Administrator eingeben

    if(isset($_POST['orga_name_short']))
    {
        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['orga_name_short'] = strStripTags($_POST['orga_name_short']);
        $_SESSION['orga_name_long']  = strStripTags($_POST['orga_name_long']);

        if(strlen($_SESSION['orga_name_short']) == 0
        || strlen($_SESSION['orga_name_long']) == 0 )
        {
            $message = "Die Bezeichnung der Organisation wurde nicht vollständig eingegeben !";
            showPage($message, "new_organisation.php?mode=2", "back.png", "Zurück");
        }
    }

    // Formular vorbelegen
    if(isset($_SESSION['user_login']))
    {
        $user_login = $_SESSION['user_login'];
    }
    else
    {
        $user_login = "";
    }
    $message = '<strong>Administrator anlegen</strong><br /><br />
                Gib in diesem Formular die Zugangsdaten eines bereits existierenden Webmasters einer bestehenden Organisation ein.
                Dieser wird der neue Administrator der neuen Organisation mit dem du dich direkt an der Organisation anmelden kannst. 

                <div class="groupBox">
                    <div class="groupBoxHeadline">Zugangsdaten eines Webmasters</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="user_login">Benutzername:</label></dt>
                                    <dd><input type="text" name="user_login" id="user_login" style="width: 250px;" maxlength="35" value="'. $user_login. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_password">Passwort:</label></dt>
                                    <dd><input type="password" name="user_password" id="user_password" style="width: 150px;" maxlength="20" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, "new_organisation.php?mode=4", "forward.png", "Konfigurationsdatei erzeugen", 3);
}
elseif($req_mode == 4)
{
    // Konfigurationsdatei erzeugen

    if(isset($_POST['user_login']))
    {
        // Daten des Administrators in Sessionvariablen gefiltert speichern
        $_SESSION['user_login']      = strStripTags($_POST['user_login']);
        $_SESSION['user_password']   = strStripTags($_POST['user_password']);

        if(strlen($_SESSION['user_login'])      == 0
        || strlen($_SESSION['user_password'])   == 0 )
        {
            $message = "Die Zugangsdaten des Webmasters sind nicht vollständig eingegeben worden !";
            showPage($message, "new_organisation.php?mode=3", "back.png", "Zurück", 3);
        }

        // Verbindung zu Datenbank herstellen
        $db = new MySqlDB();
        $connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);
        
        // Logindaten pruefen        
        $sql    = "SELECT DISTINCT usr_id
                     FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
                    WHERE usr_login_name LIKE '". $_SESSION['user_login']. "'
                      AND usr_password   = '". $_SESSION['user_password']. "'
                      AND usr_valid      = 1
                      AND mem_usr_id     = usr_id
                      AND mem_rol_id     = rol_id
                      AND mem_valid      = 1
                      AND rol_valid      = 1
                      AND rol_name       = 'Webmaster' ";
        $result = $db->query($sql);
        
        $user_found = $db->num_rows($result);
        $user_row   = $db->fetch_array($result);
        
        if($user_found != 1)
        {
            $message = "Die Zugangsdaten entsprechen keinem gültigen Login eines Webmasters einer anderen Organisation !";
            showPage($message, "new_organisation.php?mode=3", "back.png", "Zurück", 3);
        }
        else
        {
            $_SESSION['webmaster_id'] = $user_row['usr_id'];
        }
    }

    $message = '<strong>Konfigurationsdatei anlegen</strong><br /><br />
                Laden Sie die Konfigurationsdatei <strong>config.php</strong> herunter und kopieren Sie
                diese in das Admidio Hauptverzeichnis der neuen Organisation. Dort liegt auch schon eine 
                <i>config_default.php</i>.<br /><br />

                <span class="iconTextLink">
                    <a href="new_organisation.php?mode=5"><img
                    src="layout/page_white_download.png" alt="config.php herunterladen" /></a>
                    <a href="new_organisation.php?mode=5">config.php herunterladen</a>
                </span>
                <br />';
    showPage($message, "new_organisation.php?mode=6", "database_in.png", "Organisation einrichten", 3);
}
elseif($req_mode == 5)
{
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

    $file_content = str_replace("%PRAEFIX%",   $g_tbl_praefix, $file_content);
    $file_content = str_replace("%SERVER%",    $g_adm_srv,     $file_content);
    $file_content = str_replace("%USER%",      $g_adm_usr,     $file_content);
    $file_content = str_replace("%PASSWORD%",  $g_adm_pw,      $file_content);
    $file_content = str_replace("%DATABASE%",  $g_adm_db,      $file_content);
    $file_content = str_replace("%ROOT_PATH%", $root_path,     $file_content);
    $file_content = str_replace("%ORGANIZATION%", $_SESSION['orga_name_short'], $file_content);

    // die erstellte Config-Datei an den User schicken
    $file_name   = "config.php";
    $file_length = strlen($file_content);

    header("Content-Type: text/plain; charset=utf-8");
    header("Content-Length: $file_length");
    header("Content-Disposition: attachment; filename=$file_name");
    echo $file_content;
    exit();
}
elseif($req_mode == 6)
{
    // Installation starten

    if(file_exists("../config.php") == false)
    {
        $message = "Die Datei <strong>config.php</strong> befindet sich nicht im Admidio Hauptverzeichnis !<br /><br />
                    Laden Sie die Datei gegebenenfalls erneut herunter und kopieren Sie diese in das entsprechende Verzeichnis.";
        showPage($message, "new_organisation.php?mode=4", "back.png", "Zurück", 3);
    }

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(120);

     // Verbindung zu Datenbank herstellen
    $db = new MySqlDB();
    $connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);
    $db->startTransaction();

    // Default-Daten anlegen
    
    // Organisationsobjekt erstellen
    $g_current_organization = new Organization($db, $_SESSION['orga_name_short']);

    $g_current_organization->setValue("org_shortname", $_SESSION['orga_name_short']);
    $g_current_organization->setValue("org_longname",  $_SESSION['orga_name_long']);
    $g_current_organization->setValue("org_homepage",  $_SERVER['HTTP_HOST']);
    $g_current_organization->save();

    // Userobjekt anlegen
    $g_current_user = new User($db, $_SESSION['webmaster_id']);

    // alle Einstellungen aus preferences.php in die Tabelle adm_preferences schreiben
    include("db_scripts/preferences.php");
    
    // die Administrator-Email-Adresse ist erst einmal die vom Installationsuser
    $orga_preferences['email_administrator'] = $_SESSION['user_email'];

    $g_current_organization->setPreferences($orga_preferences, false);
    
    // alle Systemmails aus systemmails_texts.php in die Tabelle adm_texts schreiben
    include("db_scripts/systemmails_texts.php");
    $text = new Text($db);

    foreach($systemmails_texts as $key => $value)
    {
        $text->clear();
        $text->setValue("txt_name", $key);
        $text->setValue("txt_text", $value);
        $text->save();
    }    

    // Datenbank-Versionsnummer schreiben
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES (". $g_current_organization->getValue("org_id"). ", 'db_version', '". ADMIDIO_VERSION. "') ";
    $db->query($sql);

    // Default-Kategorie fuer Rollen und Links eintragen
    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                           VALUES (". $g_current_organization->getValue("org_id"). ", 'ROL', 'Allgemein', 0, 1)";
    $db->query($sql);
    $category_common = $db->insert_id();

    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                      VALUES (". $g_current_organization->getValue("org_id"). ", 'ROL', 'Gruppen', 0, 2)
                                           , (". $g_current_organization->getValue("org_id"). ", 'ROL', 'Kurse', 0, 3)
                                           , (". $g_current_organization->getValue("org_id"). ", 'ROL', 'Mannschaften', 0, 4)
                                           , (". $g_current_organization->getValue("org_id"). ", 'LNK', 'Allgemein', 0, 1) 
                                           , (". $g_current_organization->getValue("org_id"). ", 'DAT', 'Allgemein', 0, 1) ";
    $db->query($sql);

    //Default-Ordner fuer Downloadmodul in der DB anlegen:
    $sql = "INSERT INTO ". TBL_FOLDERS. " (fol_org_id, fol_type, fol_name, fol_path,
                                           fol_locked, fol_public, fol_timestamp)
                                    VALUES (". $g_current_organization->getValue("org_id"). ", 'DOWNLOAD', 'download', '/adm_my_files',
                                            0,1, '". $act_datetime. "')";
    $db->query($sql);

    // nun die Default-Rollen anlegen

    // Webmaster
    $role_webmaster = new Role($db);
    $role_webmaster->setValue("rol_cat_id", $category_common);
    $role_webmaster->setValue("rol_name", "Webmaster");
    $role_webmaster->setValue("rol_description", "Gruppe der Administratoren des Systems");
    $role_webmaster->setValue("rol_assign_roles", 1);
    $role_webmaster->setValue("rol_approve_users", 1);
    $role_webmaster->setValue("rol_announcements", 1);
    $role_webmaster->setValue("rol_dates", 1);
    $role_webmaster->setValue("rol_download", 1);
    $role_webmaster->setValue("rol_guestbook", 1);
    $role_webmaster->setValue("rol_guestbook_comments", 1);
    $role_webmaster->setValue("rol_photo", 1);
    $role_webmaster->setValue("rol_weblinks", 1);
    $role_webmaster->setValue("rol_edit_user", 1);
    $role_webmaster->setValue("rol_mail_logout", 1);
    $role_webmaster->setValue("rol_mail_login", 1);
    $role_webmaster->setValue("rol_profile", 1);
    $role_webmaster->setValue("rol_this_list_view", 1);
    $role_webmaster->setValue("rol_all_lists_view", 1);
    $role_webmaster->save(0);

    // Mitglied
    $role_member = new Role($db);
    $role_member->setValue("rol_cat_id", $category_common);
    $role_member->setValue("rol_name", "Mitglied");
    $role_member->setValue("rol_description", "Alle Mitglieder der Organisation");
    $role_member->setValue("rol_mail_login", 1);
    $role_member->setValue("rol_profile", 1);
    $role_member->setValue("rol_this_list_view", 1);
    $role_member->save(0);

    // Vorstand
    $role_management = new Role($db);
    $role_management->setValue("rol_cat_id", $category_common);
    $role_management->setValue("rol_name", "Vorstand");
    $role_management->setValue("rol_description", "Vorstand des Vereins");
    $role_management->setValue("rol_announcements", 1);
    $role_management->setValue("rol_dates", 1);
    $role_management->setValue("rol_weblinks", 1);
    $role_management->setValue("rol_edit_user", 1);
    $role_management->setValue("rol_mail_logout", 1);
    $role_management->setValue("rol_mail_login", 1);
    $role_management->setValue("rol_profile", 1);
    $role_management->setValue("rol_this_list_view", 1);
    $role_management->setValue("rol_all_lists_view", 1);
    $role_management->save(0);

    // Mitgliedschaft bei Rolle "Webmaster" anlegen
    $member = new TableMembers($db);
    $member->startMembership($role_webmaster->getValue("rol_id"), $g_current_user->getValue("usr_id"));
    $member->startMembership($role_member->getValue("rol_id"), $g_current_user->getValue("usr_id"));    
    
    $db->endTransaction();

    // Daten der Session loeschen
    unset($_SESSION['g_current_organisation']);
    unset($_SESSION['g_preferences']);
    unset($_SESSION['g_current_user']);

    $message = '<img style="vertical-align: top;" src="layout/ok.png" /> <strong>Die Einrichtung war erfolgreich</strong><br /><br />
                Die neue Organisation '. $_SESSION['orga_name_short']. ' wurde eingerichtet und die Konfigurationsdatei erstellt.
                Sie können nun mit Admidio arbeiten und sich mit den Daten des Administrators anmelden.';
    if(is_writeable("../adm_my_files") == false)
    {
        $message = $message. '<br /><br />Zuvor sollten Sie allerdings dem Ordner <strong>adm_my_files</strong>
                   Schreibrechte geben. Ohne diese können Sie keine Fotos oder Dateien hochladen.';
    }
    showPage($message, "../adm_program/index.php", "application_view_list.png", "Übersichtsseite");
}

?>