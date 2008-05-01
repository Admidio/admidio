<?php
/******************************************************************************
 * Installation und Einrichtung der Admidio-Datenbank und der Config-Datei
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode     = 1 : (Default) Willkommen zur Installation
 *            2 : Zugangsdaten zur Datenbank eingeben
 *            3 : Organisationsnamen eingeben
 *            4 : Daten des Administrator eingeben
 *            5 : Konfigurationsdatei erzeugen
 *            6 : Konfigurationsdatei herunterladen
 *            7 : Installation starten
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

if(isset($_SESSION['praefix']))
{
    $g_tbl_praefix = $_SESSION['praefix'];
}
else
{
    $g_tbl_praefix = "adm";
}

$admidio_path = substr(__FILE__, 0, strpos(__FILE__, "adm_install")-1);

// Konstanten und Konfigurationsdatei einbinden
require_once($admidio_path. "/adm_program/system/constants.php");
require_once(SERVER_PATH. "/adm_program/system/string.php");
require_once(SERVER_PATH. "/adm_program/system/function.php");
require_once(SERVER_PATH. "/adm_program/system/organization_class.php");
require_once(SERVER_PATH. "/adm_program/system/user_class.php");
require_once(SERVER_PATH. "/adm_program/system/role_class.php");

// Default-DB-Type ist immer MySql
if(!isset($g_db_type))
{
    $g_db_type = "mysql";
}

require_once(SERVER_PATH. "/adm_program/system/". $g_db_type. "_class.php");

$message = "";

if($req_mode == 1)
{
    // Willkommen zur Installation
    session_destroy();
    $message = '<strong>Willkommen zur Installation von Admidio</strong><br /><br />
                Auf den nächsten Seiten müssen Sie einige notwendige Informationen für die Einrichtung
                von Admidio eingeben. Sie benötigen dazu unter anderem die Zugangsdaten zu der
                Datenbank, auf der Admidio zukünftig laufen soll.';
    showPage($message, "installation.php?mode=2", "forward.png", "Datenbank Zugangsdaten");
}
elseif($req_mode == 2)
{
    // Zugangsdaten zur Datenbank eingeben

    // Formular vorbelegen
    if(isset($_SESSION['server']))
    {
        $server   = $_SESSION['server'];
        $user     = $_SESSION['user'];
        $database = $_SESSION['database'];
        $praefix  = $_SESSION['praefix'];
    }
    else
    {
        $server   = "";
        $user     = "";
        $database = "";
        $praefix  = "adm";
    }

    $message = '<strong>Zugangsdaten zur Datenbank eingeben</strong><br /><br />
                Geben Sie in diesem Formular Ihre Zugangsdaten zur Datenbank an. Sie können das
                Tabellenpräfix auf Wunsch verändern. Dies ist notwendig, falls Sie mehrere
                Admidio-Installationen auf derselben Datenbank einrichten möchten.

                <div class="groupBox">
                    <div class="groupBoxHeadline">Zugangsdaten zur Datenbank</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="server">Server:</label></dt>
                                    <dd><input type="text" name="server" id="server" style="width: 250px;" maxlength="50" value="'. $server. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user">Login:</label></dt>
                                    <dd><input type="text" name="user" id="user" style="width: 250px;" maxlength="50" value="'. $user. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="password">Passwort:</label></dt>
                                    <dd><input type="password" name="password" id="password" style="width: 250px;" maxlength="50" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="database">Datenbank:</label></dt>
                                    <dd><input type="text" name="database" id="database" style="width: 250px;" maxlength="50" value="'. $database. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="praefix">Tabellenpr&auml;fix:</label></dt>
                                    <dd><input type="text" name="praefix" id="praefix" style="width: 80px;" maxlength="10" value="'. $praefix. '" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, "installation.php?mode=3", "forward.png", "Organisation festlegen", "server");
}
elseif($req_mode == 3)
{
    // Organisationsnamen eingeben

    if(isset($_POST['server']))
    {
        if(strlen($_POST['praefix']) == 0)
        {
            $_POST['praefix'] = "adm";
        }
        else
        {
            // wenn letztes Zeichen ein _ dann abschneiden
            if(strrpos($_POST['praefix'], "_")+1 == strlen($_POST['praefix']))
            {
                $_POST['praefix'] = substr($_POST['praefix'], 0, strlen($_POST['praefix'])-1);
            }

            // nur gueltige Zeichen zulassen
            $anz = strspn($_POST['praefix'], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_");

            if($anz != strlen($_POST['praefix']))
            {
                $message   = "Das Tabellenpräfix enthält ungültige Zeichen !";
                showPage($message, "installation.php?mode=2", "back.png", "Zurück");
            }
        }

        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['server']   = strStripTags($_POST['server']);
        $_SESSION['user']     = strStripTags($_POST['user']);
        $_SESSION['password'] = strStripTags($_POST['password']);
        $_SESSION['database'] = strStripTags($_POST['database']);
        $_SESSION['praefix']  = strStripTags($_POST['praefix']);

        if(strlen($_SESSION['server'])   == 0
        || strlen($_SESSION['user'])     == 0
        || strlen($_SESSION['database']) == 0 )
        {
            $message   = "Es sind nicht alle Zugangsdaten zur MySql-Datenbank eingegeben worden !";
            showPage($message, "installation.php?mode=2", "back.png", "Zurück");
        }

        // pruefen, ob eine Verbindung zur Datenbank erstellt werden kann
        $db = new MySqlDB();
        if($db->connect($_SESSION['server'], $_SESSION['user'], $_SESSION['password'], $_SESSION['database']) == false)
        {
            $message   = "Mit Ihren Zugangsdaten konnte keine Verbindung zur Datenbank erstellt werden !<br /><br />
                          Korrigieren Sie gegebenenfalls Ihre Zugangsdaten bzw. kontrollieren Sie,
                          ob die Datenbank online ist.";
            showPage($message, "installation.php?mode=2", "back.png", "Zurück");
        }
    }

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
                Geben Sie in diesem Formular die Abkürzung und den offiziellen Namen der Organisation / Verein
                ein, für die Sie Admidio nutzen möchten.

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
    showPage($message, "installation.php?mode=4", "forward.png", "Administrator anlegen", "orga_name_short");
}
elseif($req_mode == 4)
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
            showPage($message, "installation.php?mode=3", "back.png", "Zurück");
        }
    }

    // Formular vorbelegen
    if(isset($_SESSION['user_last_name']))
    {
        $user_last_name  = $_SESSION['user_last_name'];
        $user_first_name = $_SESSION['user_first_name'];
        $user_email      = $_SESSION['user_email'];
        $user_login      = $_SESSION['user_login'];
    }
    else
    {
        $user_last_name  = "";
        $user_first_name = "";
        $user_email      = "";
        $user_login      = "";
    }
    $message = '<strong>Administrator anlegen</strong><br /><br />
                Geben Sie in diesem Formular Name, E-Mail und die Zugangsdaten des Administrators an.
                Mit diesem Benutzer können Sie sich nach der Installation bei Admidio anmelden.

                <div class="groupBox">
                    <div class="groupBoxHeadline">Daten des Administrators</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="user_last_name">Nachname:</label></dt>
                                    <dd><input type="text" name="user_last_name" id="user_last_name" style="width: 250px;" maxlength="50" value="'. $user_last_name. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_first_name">Vorname:</label></dt>
                                    <dd><input type="text" name="user_first_name" id="user_first_name" style="width: 250px;" maxlength="50" value="'. $user_first_name. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_email">E-Mail:</label></dt>
                                    <dd><input type="text" name="user_email" id="user_email" style="width: 250px;" maxlength="50" value="'. $user_email. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_login">Benutzername:</label></dt>
                                    <dd><input type="text" name="user_login" id="user_login" style="width: 250px;" maxlength="50" value="'. $user_login. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_password">Passwort:</label></dt>
                                    <dd><input type="password" name="user_password" id="user_password" style="width: 150px;" maxlength="50" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_password_confirm">Passwort bestätigen:</label></dt>
                                    <dd><input type="password" name="user_password_confirm" id="user_password_confirm" style="width: 150px;" maxlength="50" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, "installation.php?mode=5", "forward.png", "Konfigurationsdatei erzeugen", "user_last_name");
}
elseif($req_mode == 5)
{
    // Konfigurationsdatei erzeugen

    if(isset($_POST['user_last_name']))
    {
        // Daten des Administrators in Sessionvariablen gefiltert speichern
        $_SESSION['user_last_name']  = strStripTags($_POST['user_last_name']);
        $_SESSION['user_first_name'] = strStripTags($_POST['user_first_name']);
        $_SESSION['user_email']      = strStripTags($_POST['user_email']);
        $_SESSION['user_login']      = strStripTags($_POST['user_login']);
        $_SESSION['user_password']   = strStripTags($_POST['user_password']);
        $_SESSION['user_password_confirm'] = strStripTags($_POST['user_password_confirm']);

        if(strlen($_SESSION['user_last_name'])  == 0
        || strlen($_SESSION['user_first_name']) == 0
        || strlen($_SESSION['user_email'])      == 0
        || strlen($_SESSION['user_login'])      == 0
        || strlen($_SESSION['user_password'])   == 0 )
        {
            $message = "Es sind nicht alle Daten für den Administrator eingegeben worden !";
            showPage($message, "installation.php?mode=4", "back.png", "Zurück");
        }

        if($_SESSION['user_password'] != $_SESSION['user_password_confirm'])
        {
            $message = "Das Passwort stimmt nicht mit der Wiederholung überein !";
            showPage($message, "installation.php?mode=4", "back.png", "Zurück");
        }
    }

    $message = '<strong>Konfigurationsdatei anlegen</strong><br /><br />
                Laden Sie die Konfigurationsdatei <strong>config.php</strong> herunter und kopieren Sie
                diese in das Admidio Hauptverzeichnis. Dort liegt auch schon eine <i>config_default.php</i>.<br /><br />
                Erst nachdem Sie die Datei dort abgelegt haben, können Sie mit der Installation fortfahren.<br /><br />

                <span class="iconTextLink">
                    <a href="installation.php?mode=6"><img
                    src="layout/page_white_download.png" alt="config.php herunterladen" /></a>
                    <a href="installation.php?mode=6">config.php herunterladen</a>
                </span>
                <br />';
    showPage($message, "installation.php?mode=7", "database_in.png", "Admidio installieren", "next_page");
}
elseif($req_mode == 6)
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

    $file_content = str_replace("%PRAEFIX%", $_SESSION['praefix'], $file_content);
    $file_content = str_replace("%SERVER%",  $_SESSION['server'],  $file_content);
    $file_content = str_replace("%USER%",    $_SESSION['user'],    $file_content);
    $file_content = str_replace("%PASSWORD%",  $_SESSION['password'], $file_content);
    $file_content = str_replace("%DATABASE%",  $_SESSION['database'], $file_content);
    $file_content = str_replace("%ROOT_PATH%", $root_path,            $file_content);
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
elseif($req_mode == 7)
{
    // Installation starten

    if(file_exists("../config.php") == false)
    {
        $message = "Die Datei <strong>config.php</strong> befindet sich nicht im Admidio Hauptverzeichnis !<br /><br />
                    Laden Sie die Datei gegebenenfalls erneut herunter und kopieren Sie diese in das entsprechende Verzeichnis.";
        showPage($message, "installation.php?mode=5", "back.png", "Zurück");
    }

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(120);

     // Verbindung zu Datenbank herstellen
    $db = new MySqlDB();
    $connection = $db->connect($_SESSION['server'], $_SESSION['user'], $_SESSION['password'], $_SESSION['database']);

    $filename = "db_scripts/db.sql";
    $file     = fopen($filename, "r")
                or showPage("Die Datei <strong>db.sql</strong> konnte nicht im Verzeichnis <strong>adm_install/db_scripts</strong> gefunden werden.", "installation.php?mode=5", "back.png", "Zurück");
    $content  = fread($file, filesize($filename));
    $sql_arr  = explode(";", $content);
    fclose($file);

    foreach($sql_arr as $sql)
    {
        if(strlen(trim($sql)) > 0)
        {
            // Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
            $sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
            $db->query($sql);
        }
    }

    // Default-Daten anlegen

    // Orga-Uebergreifende Kategorien anlegen
    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                      VALUES (NULL, 'USF', 'Stammdaten', 0, 1, 0) ";
    $db->query($sql);
    $cat_id_stammdaten = $db->insert_id();

    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                      VALUES (NULL, 'USF', 'Messenger', 0, 0, 1) ";
    $db->query($sql);
    $cat_id_messenger = $db->insert_id();

    // Stammdatenfelder anlegen
    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_description, usf_system, usf_disabled, usf_mandatory, usf_sequence)
                                       VALUES ($cat_id_stammdaten, 'TEXT', 'Nachname', NULL, 1, 1, 1, 1)
                                            , ($cat_id_stammdaten, 'TEXT', 'Vorname', NULL, 1, 1, 1, 2)
                                            , ($cat_id_stammdaten, 'TEXT', 'Adresse', NULL, 1, 0, 0, 3)
                                            , ($cat_id_stammdaten, 'TEXT', 'PLZ', NULL, 1, 0, 0, 4)
                                            , ($cat_id_stammdaten, 'TEXT', 'Ort', NULL, 1, 0, 0, 5)
                                            , ($cat_id_stammdaten, 'TEXT', 'Land', NULL, 1, 0, 0, 6)
                                            , ($cat_id_stammdaten, 'TEXT', 'Telefon', NULL, 1, 0, 0, 7)
                                            , ($cat_id_stammdaten, 'TEXT', 'Handy', NULL, 1, 0, 0, 8)
                                            , ($cat_id_stammdaten, 'TEXT', 'Fax', NULL, 1, 0, 0, 9)
                                            , ($cat_id_stammdaten, 'DATE', 'Geburtstag', NULL, 1, 0, 0, 10)
                                            , ($cat_id_stammdaten, 'NUMERIC', 'Geschlecht', NULL, 1, 0, 0, 11)
                                            , ($cat_id_stammdaten, 'EMAIL','E-Mail', 'Es muss eine gültige E-Mail-Adresse angegeben werden.
                                                                    Ohne diese kann das Programm nicht genutzt werden.', 1, 0, 1, 12)
                                            , ($cat_id_stammdaten, 'URL',  'Homepage', NULL, 1, 0, 0, 13) ";
    $db->query($sql);
    $usf_id_homepage = $db->insert_id();

    // Messenger anlegen
    $sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_description, usf_system, usf_sequence)
                                       VALUES ($cat_id_messenger, 'TEXT', 'AIM', 'AOL Instant Messenger', 0, 1)
                                            , ($cat_id_messenger, 'TEXT', 'Google Talk', 'Google Talk', 0, 2)
                                            , ($cat_id_messenger, 'TEXT', 'ICQ', 'ICQ', 0, 3)
                                            , ($cat_id_messenger, 'TEXT', 'MSN', 'MSN Messenger', 0, 4)
                                            , ($cat_id_messenger, 'TEXT', 'Skype', 'Skype', 0, 5)
                                            , ($cat_id_messenger, 'TEXT', 'Yahoo', 'Yahoo! Messenger', 0, 6)  ";
    $db->query($sql);

    // Organisationsobjekt erstellen
    $g_current_organization = new Organization($db, $_SESSION['orga_name_short']);

    $g_current_organization->setValue("org_shortname", $_SESSION['orga_name_short']);
    $g_current_organization->setValue("org_longname",  $_SESSION['orga_name_long']);
    $g_current_organization->setValue("org_homepage",  $_SERVER['HTTP_HOST']);
    $g_current_organization->save();

    // alle Einstellungen aus preferences.php in die Tabelle adm_preferences schreiben
    include("db_scripts/preferences.php");

    foreach($orga_preferences as $key => $value)
    {
        if($key == 'email_administrator')
        {
            // die Administrator-Email-Adresse ist erst einmal die vom Installationsuser
            $value = $_SESSION['user_email'];
        }
        $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                           VALUES (". $g_current_organization->getValue("org_id"). ", '$key', '$value') ";
        $db->query($sql);
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
                                           , (NULL, 'USF', 'Zusätzliche Daten', 0, 2) ";
    $db->query($sql);

    //DefaultOrdner fuer Downloadmodul in der DB anlegen:
    $sql = "INSERT INTO ". TBL_FOLDERS. " (fol_org_id, fol_type, fol_name, fol_path,
                                           fol_locked, fol_public, fol_timestamp)
                                    VALUES (". $g_current_organization->getValue("org_id"). ", 'DOWNLOAD', 'download', '/adm_my_files',
                                            0,1,SYSDATE())";
    $db->query($sql);


    // User Webmaster anlegen
    $g_current_user = new User($db);
    $g_current_user->setValue("Nachname", $_SESSION['user_last_name']);
    $g_current_user->setValue("Vorname", $_SESSION['user_first_name']);
    $g_current_user->setValue("E-Mail", $_SESSION['user_email']);
    $g_current_user->setValue("usr_login_name", $_SESSION['user_login']);
    $g_current_user->setValue("usr_password", md5($_SESSION['user_password']));
    $g_current_user->b_set_last_change = false;
    $g_current_user->save();

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
    $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin, mem_valid)
                                   VALUES (". $role_webmaster->getValue("rol_id"). ", ". $g_current_user->getValue("usr_id"). ", NOW(), 1)
                                        , (". $role_member->getValue("rol_id"). ", ". $g_current_user->getValue("usr_id"). ", NOW(), 1) ";
    $db->query($sql);

    // Daten der Session loeschen
    unset($_SESSION['g_current_organisation']);
    unset($_SESSION['g_preferences']);
    unset($_SESSION['g_current_user']);

    $message = '<img style="vertical-align: top;" src="layout/ok.png" /> <strong>Die Installation war erfolgreich</strong><br /><br />
                Die Admidio-Datenbank ist nun installiert und die Konfigurationsdatei eingerichtet.
                Sie können nun mit Admidio arbeiten und sich mit den Daten des Administrators anmelden.';
    if(is_writeable("../adm_my_files") == false)
    {
        $message = $message. '<br /><br />Zuvor sollten Sie allerdings dem Ordner <strong>adm_my_files</strong>
                   Schreibrechte geben. Ohne diese können Sie keine Fotos oder Dateien hochladen.';
    }
    showPage($message, "../adm_program/index.php", "application_view_list.png", "Übersichtsseite");
}

?>