<?php
/******************************************************************************
 * Installation und Einrichtung der Admidio-Datenbank und der Config-Datei
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode     = 1 : (Default) Sprache auswaehlen
 *            2 : Willkommen zur Installation
 *            3 : Zugangsdaten zur Datenbank eingeben
 *            4 : Organisationsnamen eingeben
 *            5 : Daten des Administrator eingeben
 *            6 : Konfigurationsdatei erzeugen
 *            7 : Konfigurationsdatei herunterladen
 *            8 : Installation starten
 *
 *****************************************************************************/

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

if(isset($_SESSION['prefix']))
{
    $g_tbl_praefix = $_SESSION['prefix'];
}
else
{
    $g_tbl_praefix = 'adm';
}

$admidio_path = substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1);

// Konstanten und Konfigurationsdatei einbinden
require_once($admidio_path. '/adm_program/system/constants.php');
require_once('install_functions.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');
require_once(SERVER_PATH. '/adm_program/system/classes/datetime_extended.php');
require_once(SERVER_PATH. '/adm_program/system/classes/language.php');
require_once(SERVER_PATH. '/adm_program/system/classes/list_configuration.php');
require_once(SERVER_PATH. '/adm_program/system/classes/organization.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_members.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_roles.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_text.php');
require_once(SERVER_PATH. '/adm_program/system/classes/user.php');

// Default-DB-Type ist immer MySql
if(!isset($g_db_type))
{
    $g_db_type = 'mysql';
}
require_once(SERVER_PATH. '/adm_program/system/db/'. $g_db_type. '.php');

// Sprachdateien einlesen
if(isset($_SESSION['language']))
{
    $language = $_SESSION['language'];
}
else
{
    $language = 'en';
}
$g_l10n = new Language($language);

$message = '';

if($req_mode == 1)  // (Default) Sprache auswaehlen
{
    session_destroy();
    $languages = array('de' => 'deutsch', 'en' => 'english');

    $message = '<div class="groupBox">
                    <div class="groupBoxHeadline">'.$g_l10n->get('INS_PHR_CHOOSE_LANGUAGE').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="system_language">'.$g_l10n->get('SYS_LANGUAGE').':</label></dt>
                                    <dd>
                                        <select size="1" id="system_language" name="system_language">
                                            <option value="">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';
                                            foreach($languages as $key => $value)
                                            {
                                                $message .= '<option value="'.$key.'">'.$value.'</option>';
                                            }
                                        $message .= '</select>
                                    </dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, 'installation.php?mode=2', 'forward.png', $g_l10n->get('SYS_NEXT'));
}
elseif($req_mode == 2)  // Willkommen zur Installation
{   
    // Pruefen ob Sprache uebergeben wurde
    if(isset($_POST['system_language']) == false || strlen($_POST['system_language']) == 0)
    {
        showPage($g_l10n->get('INS_PHR_LANGUAGE_NOT_CHOOSEN'), 'installation.php?mode=1', 'back.png', $g_l10n->get('SYS_BACK'));
    }
    else
    {
        $_SESSION['language'] = $_POST['system_language'];
        $g_l10n->setLanguage($_SESSION['language']);
    }
    
    $message = '<strong>'.$g_l10n->get('INS_PHR_WELCOME_TO_INSTALLATION').'</strong><br /><br />'.$g_l10n->get('INS_PHR_WELCOME_TEXT');

    // falls dies eine Betaversion ist, dann Hinweis ausgeben
    if(BETA_VERSION > 0)
    {
        $message .= '<br /><br /><img style="vertical-align: top;" src="layout/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />'.$g_l10n->get('INS_PHR_WARNING_BETA_VERSION');
    }

    if(ini_get('safe_mode') == 1)
    {    
        $message .= '<br /><br /><img style="vertical-align: top;" src="layout/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />'.$g_l10n->get('INS_PHR_WARNING_SAFE_MODE');
    }
    showPage($message, 'installation.php?mode=3', 'forward.png', $g_l10n->get('INS_DATABASE_LOGIN'));
}
elseif($req_mode == 3)  // Zugangsdaten zur Datenbank eingeben
{
    // Formular vorbelegen
    if(isset($_SESSION['server']))
    {
        $server   = $_SESSION['server'];
        $user     = $_SESSION['user'];
        $database = $_SESSION['database'];
        $prefix  = $_SESSION['prefix'];
    }
    else
    {
        $server   = '';
        $user     = '';
        $database = '';
        $prefix  = 'adm';
    }

    $message = '<strong>'.$g_l10n->get('INS_ENTER_LOGIN_TO_DATABASE').'</strong><br /><br />'.$g_l10n->get('INS_PHR_DATABASE_LOGIN').'
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$g_l10n->get('INS_DATABASE_LOGIN').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="server">'.$g_l10n->get('SYS_SERVER').':</label></dt>
                                    <dd><input type="text" name="server" id="server" style="width: 250px;" maxlength="50" value="'. $server. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user">'.$g_l10n->get('SYS_LOGIN').':</label></dt>
                                    <dd><input type="text" name="user" id="user" style="width: 250px;" maxlength="50" value="'. $user. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="password">'.$g_l10n->get('SYS_PASSWORD').':</label></dt>
                                    <dd><input type="password" name="password" id="password" style="width: 250px;" maxlength="50" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="database">'.$g_l10n->get('SYS_DATABASE').':</label></dt>
                                    <dd><input type="text" name="database" id="database" style="width: 250px;" maxlength="50" value="'. $database. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="prefix">'.$g_l10n->get('INS_TABLE_PREFIX').':</label></dt>
                                    <dd><input type="text" name="prefix" id="prefix" style="width: 80px;" maxlength="10" value="'. $prefix. '" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />
                <img src="layout/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />'.$g_l10n->get('INS_PHR_TABLE_PREFIX_OVERRIDE_DATA').'<br />';
    showPage($message, 'installation.php?mode=4', 'forward.png', $g_l10n->get('INS_SET_ORGANIZATION'));
}
elseif($req_mode == 4)  // Organisationsnamen eingeben
{
    if(isset($_POST['server']))
    {
        if(strlen($_POST['prefix']) == 0)
        {
            $_POST['prefix'] = 'adm';
        }
        else
        {
            // wenn letztes Zeichen ein _ dann abschneiden
            if(strrpos($_POST['prefix'], '_')+1 == strlen($_POST['prefix']))
            {
                $_POST['prefix'] = substr($_POST['prefix'], 0, strlen($_POST['prefix'])-1);
            }

            // nur gueltige Zeichen zulassen
            $anz = strspn($_POST['prefix'], 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_');

            if($anz != strlen($_POST['prefix']))
            {
                showPage($g_l10n->get('INS_PHR_TABLE_PREFIX_INVALID'), 'installation.php?mode=3', 'back.png', $g_l10n->get('SYS_BACK'));
            }
        }

        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['server']   = strStripTags($_POST['server']);
        $_SESSION['user']     = strStripTags($_POST['user']);
        $_SESSION['password'] = strStripTags($_POST['password']);
        $_SESSION['database'] = strStripTags($_POST['database']);
        $_SESSION['prefix']   = strStripTags($_POST['prefix']);

        if(strlen($_SESSION['server'])   == 0
        || strlen($_SESSION['user'])     == 0
        || strlen($_SESSION['database']) == 0 )
        {
            showPage($g_l10n->get('INS_PHR_MYSQL_LOGIN_NOT_COMPLETELY'), 'installation.php?mode=3', 'back.png', $g_l10n->get('SYS_BACK'));
        }

        // pruefen, ob eine Verbindung zur Datenbank erstellt werden kann
        $db = new MySqlDB();
        if($db->connect($_SESSION['server'], $_SESSION['user'], $_SESSION['password'], $_SESSION['database']) == false)
        {
            showPage($g_l10n->get('INS_PHR_DATABASE_NO_LOGIN'), 'installation.php?mode=3', 'back.png', $g_l10n->get('SYS_BACK'));
        }

        //Datenbank- und PHP-Version prüfen
        if(checkVersions($db, $message) == false)
        {
            showPage($message, 'installation.php?mode=3', 'back.png', $g_l10n->get('SYS_BACK'));
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
        $orga_name_short = '';
        $orga_name_long  = '';
    }

    $message = $message.'<strong>'.$g_l10n->get('INS_SET_ORGANIZATION').'</strong><br /><br />
                '.$g_l10n->get('INS_PHR_NAME_OF_ORGANIZATION').'
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$g_l10n->get('INS_NAME_OF_ORGANIZATION').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="orga_name_short">'.$g_l10n->get('SYS_NAME_ABBREVIATION').':</label></dt>
                                    <dd><input type="text" name="orga_name_short" id="orga_name_short" style="width: 80px;" maxlength="10" value="'. $orga_name_short. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="orga_name_long">'.$g_l10n->get('SYS_NAME').':</label></dt>
                                    <dd><input type="text" name="orga_name_long" id="orga_name_long" style="width: 250px;" maxlength="60" value="'. $orga_name_long. '" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, 'installation.php?mode=5', 'forward.png', $g_l10n->get('INS_CREATE_ADMINISTRATOR'));
}
elseif($req_mode == 5)  // Daten des Administrator eingeben
{
    if(isset($_POST['orga_name_short']))
    {
        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['orga_name_short'] = strStripTags($_POST['orga_name_short']);
        $_SESSION['orga_name_long']  = strStripTags($_POST['orga_name_long']);

        if(strlen($_SESSION['orga_name_short']) == 0
        || strlen($_SESSION['orga_name_long']) == 0 )
        {
            showPage($g_l10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'), 'installation.php?mode=4', 'back.png', $g_l10n->get('SYS_BACK'));
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
        $user_last_name  = '';
        $user_first_name = '';
        $user_email      = '';
        $user_login      = '';
    }
    $message = '<strong>'.$g_l10n->get('INS_CREATE_ADMINISTRATOR').'</strong><br /><br />
                '.$g_l10n->get('INS_PHR_DATA_OF_ADMINISTRATOR').'
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$g_l10n->get('INS_DATA_OF_ADMINISTRATOR').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="user_last_name">'.$g_l10n->get('INS_LAST_NAME').':</label></dt>
                                    <dd><input type="text" name="user_last_name" id="user_last_name" style="width: 250px;" maxlength="50" value="'. $user_last_name. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_first_name">'.$g_l10n->get('INS_FIRST_NAME').':</label></dt>
                                    <dd><input type="text" name="user_first_name" id="user_first_name" style="width: 250px;" maxlength="50" value="'. $user_first_name. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_email">'.$g_l10n->get('SYS_EMAIL').':</label></dt>
                                    <dd><input type="text" name="user_email" id="user_email" style="width: 250px;" maxlength="50" value="'. $user_email. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_login">'.$g_l10n->get('SYS_USERNAME').':</label></dt>
                                    <dd><input type="text" name="user_login" id="user_login" style="width: 250px;" maxlength="35" value="'. $user_login. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_password">'.$g_l10n->get('SYS_PASSWORD').':</label></dt>
                                    <dd><input type="password" name="user_password" id="user_password" style="width: 150px;" maxlength="20" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_password_confirm">'.$g_l10n->get('SYS_CONFIRM_PASSWORD').':</label></dt>
                                    <dd><input type="password" name="user_password_confirm" id="user_password_confirm" style="width: 150px;" maxlength="20" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, 'installation.php?mode=6', 'forward.png', $g_l10n->get('INS_CREATE_CONFIGURATION_FILE'));
}
elseif($req_mode == 6)  // Konfigurationsdatei erzeugen
{
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
        || strlen($_SESSION['user_email'])     == 0
        || strlen($_SESSION['user_login'])      == 0
        || strlen($_SESSION['user_password'])   == 0 )
        {
            showPage($g_l10n->get('INS_ADMINISTRATOR_DATA_NOT_COMPLETELY'), 'installation.php?mode=5', 'back.png', $g_l10n->get('SYS_BACK'));
        }

        if(!isValidEmailAddress($_SESSION['user_email']))
        {
            showPage($g_l10n->get('SYS_PHR_EMAIL_INVALID'), 'installation.php?mode=5', 'back.png', $g_l10n->get('SYS_BACK'));
        }

        if($_SESSION['user_password'] != $_SESSION['user_password_confirm'])
        {
            showPage($g_l10n->get('INS_PHR_PASSWORDS_NOT_EQUAL'), 'installation.php?mode=5', 'back.png', $g_l10n->get('SYS_BACK'));
        }
    }

    $message = '<strong>'.$g_l10n->get('INS_CREATE_CONFIGURATION_FILE').'</strong><br /><br />
                '.$g_l10n->get('INS_PHR_DOWNLOAD_CONFIGURATION_FILE', 'config.php', 'config_default.php').'<br /><br />

                <span class="iconTextLink">
                    <a href="installation.php?mode=7"><img
                    src="layout/page_white_download.png" alt="'.$g_l10n->get('INS_PHR_DOWNLOAD', 'config.php').'" /></a>
                    <a href="installation.php?mode=7">'.$g_l10n->get('INS_PHR_DOWNLOAD', 'config.php').'</a>
                </span>
                <br />';
    showPage($message, 'installation.php?mode=8', 'database_in.png', $g_l10n->get('INS_INSTALL_ADMIDIO'));
}
elseif($req_mode == 7)
{
    // MySQL-Zugangsdaten in config.php schreiben
    // Datei auslesen
    $filename     = 'config.php';
    $config_file  = fopen($filename, 'r');
    $file_content = fread($config_file, filesize($filename));
    fclose($config_file);

    // den Root-Pfad ermitteln
    $root_path = $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
    $root_path = substr($root_path, 0, strpos($root_path, '/adm_install'));
    if(!strpos($root_path, 'http://'))
    {
        $root_path = 'http://'. $root_path;
    }

    $file_content = str_replace('%PREFIX%', $_SESSION['prefix'], $file_content);
    $file_content = str_replace('%SERVER%',  $_SESSION['server'],  $file_content);
    $file_content = str_replace('%USER%',    $_SESSION['user'],    $file_content);
    $file_content = str_replace('%PASSWORD%',  $_SESSION['password'], $file_content);
    $file_content = str_replace('%DATABASE%',  $_SESSION['database'], $file_content);
    $file_content = str_replace('%ROOT_PATH%', $root_path,            $file_content);
    $file_content = str_replace('%ORGANIZATION%', $_SESSION['orga_name_short'], $file_content);

    // die erstellte Config-Datei an den User schicken
    $file_name   = 'config.php';
    $file_length = strlen($file_content);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: '.$file_length);
    header('Content-Disposition: attachment; filename='.$file_name);
    echo $file_content;
    exit();
}
elseif($req_mode == 8)
{
    // Installation starten

    if(file_exists('../config.php') == false)
    {
        showPage($g_l10n->get('INS_PHR_CONFIGURATION_FILE_NOT_FOUND', 'config.php'), 'installation.php?mode=6', 'back.png', $g_l10n->get('SYS_BACK'));
    }

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(300);

    // Verbindung zu Datenbank herstellen
    require_once(SERVER_PATH. '/config.php');
    $db = new MySqlDB();
    $connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

    $filename = 'db_scripts/db.sql';
    $file     = fopen($filename, 'r')
                or showPage($g_l10n->get('INS_PHR_DATABASE_FILE_NOT_FOUND', 'db.sql', 'adm_install/db_scripts'), 'installation.php?mode=6', 'back.png', $g_l10n->get('SYS_BACK'));
    $content  = fread($file, filesize($filename));
    $sql_arr  = explode(';', $content);
    fclose($file);

    foreach($sql_arr as $sql)
    {
        if(strlen(trim($sql)) > 0)
        {
            // Prefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
            $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);
            $db->query($sql);
        }
    }

    // Default-Daten anlegen

    // User Webmaster schon mal in der User-Tabelle anlegen, damit die Id genutzt werden kann
    // weitere Daten folgen erst weiter unten, wenn die Profilfelder angelegt sind
    $g_current_user = new TableUsers($db);
    $g_current_user->setValue('usr_login_name', $_SESSION['user_login']);
    $g_current_user->setValue('usr_password',   $_SESSION['user_password']);
    $g_current_user->setValue('usr_timestamp_create', DATETIME_NOW);
    $g_current_user->save(false); // kein angemeldeter User -> ErstelltVon kann nicht gefuellt werden


    // Orga-Uebergreifende Kategorien anlegen
    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                      VALUES (NULL, "USF", "'.$g_l10n->get('INS_MASTER_DATA').'", 0, 1, 1) ';
    $db->query($sql);
    $cat_id_stammdaten = $db->insert_id();

    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                      VALUES (NULL, "USF", "'.$g_l10n->get('INS_MESSENGER').'", 0, 0, 2) ';
    $db->query($sql);
    $cat_id_messenger = $db->insert_id();

    // Stammdatenfelder anlegen
    $sql = 'INSERT INTO '. TBL_USER_FIELDS. ' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_system, usf_disabled, usf_mandatory, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                                       VALUES ('.$cat_id_stammdaten.', "TEXT", "LAST_NAME", "'.$g_l10n->get('INS_LAST_NAME').'", NULL, 1, 1, 1, 1, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "FIRST_NAME","'.$g_l10n->get('INS_FIRST_NAME').'", NULL, 1, 1, 1, 2, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "ADDRESS",   "'.$g_l10n->get('INS_ADDRESS').'", NULL, 1, 0, 0, 3, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "POSTCODE",  "'.$g_l10n->get('INS_POSTCODE').'", NULL, 1, 0, 0, 4, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "CITY",      "'.$g_l10n->get('INS_CITY').'", NULL, 1, 0, 0, 5, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "COUNTRY",   "'.$g_l10n->get('SYS_COUNTRY').'", NULL, 1, 0, 0, 6, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "PHONE",     "'.$g_l10n->get('INS_PHONE').'", NULL, 0, 0, 0, 7, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "MOBILE",    "'.$g_l10n->get('INS_MOBILE').'", NULL, 0, 0, 0, 8, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "TEXT", "FAX",       "'.$g_l10n->get('INS_FAX').'", NULL, 0, 0, 0, 9, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "DATE", "BIRTHDAY",  "'.$g_l10n->get('INS_BIRTHDAY').'", NULL, 1, 0, 0, 10, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "NUMERIC", "GENDER", "'.$g_l10n->get('INS_GENDER').'", NULL, 1, 0, 0, 11, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "EMAIL", "EMAIL",    "'.$g_l10n->get('SYS_EMAIL').'", NULL, 1, 0, 1, 12, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_stammdaten.', "URL",  "WEBSITE",   "'.$g_l10n->get('SYS_WEBSITE').'", NULL, 1, 0, 0, 13, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'") ';
    $db->query($sql);
    $usf_id_homepage = $db->insert_id();

    // Messenger anlegen
    $sql = 'INSERT INTO '. TBL_USER_FIELDS. ' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                                       VALUES ('.$cat_id_messenger.', "TEXT", "AOL_INSTANT_MESSENGER", "'.$g_l10n->get('INS_AOL_INSTANT_MESSENGER').'", NULL, 0, 1, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_messenger.', "TEXT", "GOOGLE_TALK",    "'.$g_l10n->get('INS_GOOGLE_TALK').'", NULL, 0, 2, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_messenger.', "TEXT", "ICQ",            "'.$g_l10n->get('INS_ICQ').'", NULL, 0, 3, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_messenger.', "TEXT", "MSN_MESSENGER",  "'.$g_l10n->get('INS_MSN_MESSENGER').'", NULL, 0, 4, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_messenger.', "TEXT", "SKYPE",          "'.$g_l10n->get('INS_SKYPE').'", NULL, 0, 5, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")
                                            , ('.$cat_id_messenger.', "TEXT", "YAHOO_MESSENGER","'.$g_l10n->get('INS_YAHOO_MESSENGER').'", NULL, 0, 6, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'") ';
    $db->query($sql);

    // Organisationsobjekt erstellen
    $g_current_organization = new Organization($db, $_SESSION['orga_name_short']);

    $g_current_organization->setValue('org_shortname', $_SESSION['orga_name_short']);
    $g_current_organization->setValue('org_longname',  $_SESSION['orga_name_long']);
    $g_current_organization->setValue('org_homepage',  $_SERVER['HTTP_HOST']);
    $g_current_organization->save();

    // alle Einstellungen aus preferences.php in die Tabelle adm_preferences schreiben
    include('db_scripts/preferences.php');

    // die Administrator-Email-Adresse ist erst einmal die vom Installationsuser
    $orga_preferences['email_administrator'] = $_SESSION['user_email'];

    $g_current_organization->setPreferences($orga_preferences, false);

    // alle Systemmails aus systemmails_texts.php in die Tabelle adm_texts schreiben
    include('db_scripts/systemmails_texts.php');
    $text = new TableText($db);

    foreach($systemmails_texts as $key => $value)
    {
        $text->clear();
        $text->setValue('txt_name', $key);
        $text->setValue('txt_text', $value);
        $text->save();
    }

    // nun noch die ausgewaehlte Sprache in den Einstellungen speichern
    $sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = "'.$_SESSION['language'].'"
             WHERE prf_name   = "system_language" 
               AND prf_org_id = '. $g_current_organization->getValue('org_id');
    $db->query($sql);

    // Admidio-Versionsnummer schreiben
    $sql = 'INSERT INTO '. TBL_PREFERENCES. ' (prf_org_id, prf_name, prf_value)
                                       VALUES ('. $g_current_organization->getValue('org_id'). ', "db_version",      "'. ADMIDIO_VERSION. '") 
                                            , ('. $g_current_organization->getValue('org_id'). ', "db_version_beta", "'. BETA_VERSION. '")';
    $db->query($sql);

    // Default-Kategorie fuer Rollen und Links eintragen
    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                           VALUES ('. $g_current_organization->getValue('org_id'). ', "ROL", "'.$g_l10n->get('SYS_COMMON').'", 0, 1)';
    $db->query($sql);
    $category_common = $db->insert_id();

    $sql = 'INSERT INTO '. TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                     VALUES ('. $g_current_organization->getValue('org_id').', "ROL", "'.$g_l10n->get('INS_GROUPS').'", 0, 0, 2)
                                          , ('. $g_current_organization->getValue('org_id').', "ROL", "'.$g_l10n->get('INS_COURSES').'", 0, 0, 3)
                                          , ('. $g_current_organization->getValue('org_id').', "ROL", "'.$g_l10n->get('INS_TEAMS').'", 0, 0, 4)
                                          , (NULL, "ROL", "'.$g_l10n->get('SYS_CONFIRMATION_OF_PARTICIPATION').'", 1, 1, 5)
                                          , ('. $g_current_organization->getValue('org_id').', "LNK", "'.$g_l10n->get('SYS_COMMON').'", 0, 0, 1)
                                          , ('. $g_current_organization->getValue('org_id').', "LNK", "'.$g_l10n->get('INS_INTERN').'", 1, 0, 1)
                                          , ('. $g_current_organization->getValue('org_id').', "DAT", "'.$g_l10n->get('SYS_COMMON').'", 0, 0, 1)
                                          , ('. $g_current_organization->getValue('org_id').', "DAT", "'.$g_l10n->get('INS_TRAINING').'", 0, 0, 1)
                                          , ('. $g_current_organization->getValue('org_id').', "DAT", "'.$g_l10n->get('INS_COURSES').'", 0, 0, 1)
                                          , (NULL, "USF", "'.$g_l10n->get('INS_ADDIDIONAL_DATA').'", 0, 0, 3) ';
    $db->query($sql);

    //DefaultOrdner fuer Downloadmodul in der DB anlegen:
    $sql = 'INSERT INTO '. TBL_FOLDERS. ' (fol_org_id, fol_type, fol_name, fol_path,
                                           fol_locked, fol_public, fol_timestamp)
                                    VALUES ('. $g_current_organization->getValue('org_id'). ', "DOWNLOAD", "download", "/adm_my_files",
                                            0,1,"'.DATETIME_NOW.'")';
    $db->query($sql);

    // User Webmaster anlegen
    $g_current_user = new User($db, $g_current_user->getValue('usr_id'));
    $g_current_user->setValue('LAST_NAME', $_SESSION['user_last_name']);
    $g_current_user->setValue('FIRST_NAME', $_SESSION['user_first_name']);
    $g_current_user->setValue('EMAIL', $_SESSION['user_email']);
    $g_current_user->setValue('usr_usr_id_create', $g_current_user->getValue('usr_id'));
    $g_current_user->save(false); // Ersteller wird selber gesetzt, da User bereits angelegt worden ist

    //Defaultraum fuer Raummodul in der DB anlegen:
    $sql = 'INSERT INTO '. TBL_ROOMS. ' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                                    VALUES ("'.$g_l10n->get('INS_CONFERENCE_ROOM').'", "'.$g_l10n->get('INS_PHR_DESCRIPTION_CONFERENCE_ROOM').'", 
                                            15, '.$g_current_user->getValue('usr_id').',"'. DATETIME_NOW.'")';
    $db->query($sql);

    // nun die Default-Rollen anlegen

    // Webmaster
    $role_webmaster = new TableRoles($db);
    $role_webmaster->setValue('rol_cat_id', $category_common);
    $role_webmaster->setValue('rol_name', $g_l10n->get('SYS_WEBMASTER'));
    $role_webmaster->setValue('rol_description', $g_l10n->get('INS_PHR_DESCRIPTION_WEBMASTER'));
    $role_webmaster->setValue('rol_assign_roles', 1);
    $role_webmaster->setValue('rol_approve_users', 1);
    $role_webmaster->setValue('rol_announcements', 1);
    $role_webmaster->setValue('rol_dates', 1);
    $role_webmaster->setValue('rol_download', 1);
    $role_webmaster->setValue('rol_guestbook', 1);
    $role_webmaster->setValue('rol_guestbook_comments', 1);
    $role_webmaster->setValue('rol_photo', 1);
    $role_webmaster->setValue('rol_weblinks', 1);
    $role_webmaster->setValue('rol_edit_user', 1);
    $role_webmaster->setValue('rol_mail_to_all', 1);
    $role_webmaster->setValue('rol_mail_this_role', 3);
    $role_webmaster->setValue('rol_profile', 1);
    $role_webmaster->setValue('rol_this_list_view', 1);
    $role_webmaster->setValue('rol_all_lists_view', 1);
    $role_webmaster->save(0);

    // Mitglied
    $role_member = new TableRoles($db);
    $role_member->setValue('rol_cat_id', $category_common);
    $role_member->setValue('rol_name', $g_l10n->get('SYS_MEMBER'));
    $role_member->setValue('rol_description', $g_l10n->get('INS_PHR_DESCRIPTION_MEMBER'));
    $role_member->setValue('rol_mail_this_role', 2);
    $role_member->setValue('rol_profile', 1);
    $role_member->setValue('rol_this_list_view', 1);
    $role_member->save(0);

    // Vorstand
    $role_management = new TableRoles($db);
    $role_management->setValue('rol_cat_id', $category_common);
    $role_management->setValue('rol_name', $g_l10n->get('INS_BOARD'));
    $role_management->setValue('rol_description', $g_l10n->get('INS_PHR_DESCRIPTION_BOARD'));
    $role_management->setValue('rol_announcements', 1);
    $role_management->setValue('rol_dates', 1);
    $role_management->setValue('rol_weblinks', 1);
    $role_management->setValue('rol_edit_user', 1);
    $role_management->setValue('rol_mail_to_all', 1);
    $role_management->setValue('rol_mail_this_role', 2);
    $role_management->setValue('rol_profile', 1);
    $role_management->setValue('rol_this_list_view', 1);
    $role_management->setValue('rol_all_lists_view', 1);
    $role_management->save(0);
    
    // die Rolle Mitglied wird als Defaultrolle fuer neue User eingestellt
	$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = '. $role_member->getValue('rol_id'). '
			 WHERE prf_name = "profile_default_role" ';
	$db->query($sql);

    // Mitgliedschaft bei Rolle "Webmaster" anlegen
    $member = new TableMembers($db);
    $member->startMembership($role_webmaster->getValue('rol_id'), $g_current_user->getValue('usr_id'));
    $member->startMembership($role_member->getValue('rol_id'), $g_current_user->getValue('usr_id'));

    // Default-Listen-Konfigurationen anlegen
    $address_list = new ListConfiguration($db);
    $address_list->setValue('lst_name', $g_l10n->get('INS_ADDRESS_LIST'));
    $address_list->setValue('lst_global', 1);
    $address_list->setValue('lst_default', 1);
    $address_list->addColumn(1, $g_current_user->getProperty('LAST_NAME', 'usf_id'), 'ASC');
    $address_list->addColumn(2, $g_current_user->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
    $address_list->addColumn(3, $g_current_user->getProperty('BIRTHDAY', 'usf_id'));
    $address_list->addColumn(4, $g_current_user->getProperty('ADDRESS', 'usf_id'));
    $address_list->addColumn(5, $g_current_user->getProperty('POSTCODE', 'usf_id'));
    $address_list->addColumn(6, $g_current_user->getProperty('CITY', 'usf_id'));
    $address_list->save();

    $phone_list = new ListConfiguration($db);
    $phone_list->setValue('lst_name', $g_l10n->get('INS_PHONE_LIST'));
    $phone_list->setValue('lst_global', 1);
    $phone_list->addColumn(1, $g_current_user->getProperty('LAST_NAME', 'usf_id'), 'ASC');
    $phone_list->addColumn(2, $g_current_user->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
    $phone_list->addColumn(3, $g_current_user->getProperty('PHONE', 'usf_id'));
    $phone_list->addColumn(4, $g_current_user->getProperty('MOBILE', 'usf_id'));
    $phone_list->addColumn(5, $g_current_user->getProperty('EMAIL', 'usf_id'));
    $phone_list->addColumn(6, $g_current_user->getProperty('FAX', 'usf_id'));
    $phone_list->save();

    $contact_list = new ListConfiguration($db);
    $contact_list->setValue('lst_name', $g_l10n->get('INS_CONTACT_DETAILS'));
    $contact_list->setValue('lst_global', 1);
    $contact_list->addColumn(1, $g_current_user->getProperty('LAST_NAME', 'usf_id'), 'ASC');
    $contact_list->addColumn(2, $g_current_user->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
    $contact_list->addColumn(3, $g_current_user->getProperty('BIRTHDAY', 'usf_id'));
    $contact_list->addColumn(4, $g_current_user->getProperty('ADDRESS', 'usf_id'));
    $contact_list->addColumn(5, $g_current_user->getProperty('POSTCODE', 'usf_id'));
    $contact_list->addColumn(6, $g_current_user->getProperty('CITY', 'usf_id'));
    $contact_list->addColumn(7, $g_current_user->getProperty('PHONE', 'usf_id'));
    $contact_list->addColumn(8, $g_current_user->getProperty('MOBILE', 'usf_id'));
    $contact_list->addColumn(9, $g_current_user->getProperty('EMAIL', 'usf_id'));
    $contact_list->save();

    $former_list = new ListConfiguration($db);
    $former_list->setValue('lst_name', $g_l10n->get('INS_MEMBERSHIP'));
    $former_list->setValue('lst_global', 1);
    $former_list->addColumn(1, $g_current_user->getProperty('LAST_NAME', 'usf_id'));
    $former_list->addColumn(2, $g_current_user->getProperty('FIRST_NAME', 'usf_id'));
    $former_list->addColumn(3, $g_current_user->getProperty('BIRTHDAY', 'usf_id'));
    $former_list->addColumn(4, 'mem_begin');
    $former_list->addColumn(5, 'mem_end', 'DESC');
    $former_list->save();

    // nach der Installation zur Sicherheit bei den Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 ';
    $db->query($sql);
    
    // Daten der Session loeschen
    session_unset();

    $message = '<img style="vertical-align: top;" src="layout/ok.png" /> <strong>'.$g_l10n->get('INS_INSTALLATION_WAS_SUCCESSFUL').'</strong><br /><br />
                '.$g_l10n->get('INS_PHR_INSTALLATION_SUCCESSFUL');
    if(is_writeable("../adm_my_files") == false)
    {
        $message = $message. '<br /><br /><img src="layout/warning.png" alt="Warnung" /> '.$g_l10n->get('INS_PHR_FOLDER_NOT_WRITABLE', 'adm_my_files');
    }
    showPage($message, '../adm_program/index.php', 'application_view_list.png', $g_l10n->get('INS_OVERVIEW'));
}

?>