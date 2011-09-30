<?php
/******************************************************************************
 * Einrichtung einer weiteren Organisation
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode     = 1 : (Default) Willkommen zur Installation
 *            2 : Organisationsnamen eingeben
 *            3 : Administrator anlegen
 *            4 : Konfigurationsdatei erzeugen
 *            5 : Konfigurationsdatei herunterladen
 *            6 : Installation starten
 *
 *****************************************************************************/

require_once('install_functions.php');

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
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/config.php');
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/adm_program/system/constants.php');

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = 'adm';
}

// Default-DB-Type ist immer MySql
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

require_once(SERVER_PATH. '/adm_program/system/db/'. $gDbType. '.php');
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

// Verbindung zu Datenbank herstellen
$gDb = new MySqlDB();
$gDbConnection = $gDb->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// Daten der aktuellen Organisation einlesen
$gCurrentOrganization = new Organization($gDb, $g_organization);

if($gCurrentOrganization->getValue('org_id') == 0)
{
    // Organisation wurde nicht gefunden
    die('<div style="color: #CC0000;">Error: The organization of the config.php could not be found in the database!</div>');
}

// organisationsspezifische Einstellungen aus adm_preferences auslesen
$gPreferences = $gCurrentOrganization->getPreferences();

// Sprachdateien einlesen
$gL10n = new Language($gPreferences['system_language']);

$message  = '';

if($req_mode == 1)
{
    // Willkommen zur Installation
    session_destroy();
    $message = '<strong>'.$gL10n->get('INS_WELCOME_INSTALLATION_NEW_ORGANIZATION').'</strong><br /><br />
                '.$gL10n->get('INS_NECESSARY_INFORMATION');
    showPage($message, 'new_organization.php?mode=2', 'forward.png', $gL10n->get('INS_SET_ORGANIZATION'), 3);
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
        $orga_name_short = '';
        $orga_name_long  = '';
    }

    $message = '<strong>'.$gL10n->get('INS_SET_ORGANIZATION').'</strong><br /><br />
                '.$gL10n->get('INS_NAME_OF_NEW_ORGANIZATION').'

                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_NAME_OF_ORGANIZATION').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="orga_name_short">'.$gL10n->get('SYS_NAME_ABBREVIATION').':</label></dt>
                                    <dd><input type="text" name="orga_name_short" id="orga_name_short" style="width: 80px;" maxlength="10" value="'. $orga_name_short. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="orga_name_long">'.$gL10n->get('SYS_NAME').':</label></dt>
                                    <dd><input type="text" name="orga_name_long" id="orga_name_long" style="width: 250px;" maxlength="60" value="'. $orga_name_long. '" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, 'new_organization.php?mode=3', 'forward.png', $gL10n->get('INS_SET_ADMINISTRATOR'), 3);
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
            showPage($gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'), 'new_organization.php?mode=2', 'back.png', $gL10n->get('SYS_BACK'));
        }
    }

    // Formular vorbelegen
    if(isset($_SESSION['user_login']))
    {
        $user_login = $_SESSION['user_login'];
    }
    else
    {
        $user_login = '';
    }
    $message = '<strong>'.$gL10n->get('INS_SET_ADMINISTRATOR').'</strong><br /><br />
               '.$gL10n->get('INS_LOGIN_OF_WEBMASTER_DESC').'

                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_LOGIN_OF_WEBMASTER').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="user_login">'.$gL10n->get('SYS_USERNAME').':</label></dt>
                                    <dd><input type="text" name="user_login" id="user_login" style="width: 250px;" maxlength="35" value="'. $user_login. '" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="user_password">'.$gL10n->get('SYS_PASSWORD').':</label></dt>
                                    <dd><input type="password" name="user_password" id="user_password" style="width: 150px;" maxlength="20" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                <br />';
    showPage($message, 'new_organization.php?mode=4', 'forward.png', $gL10n->get('INS_CREATE_CONFIGURATION_FILE'), 3);
}
elseif($req_mode == 4)
{
    // Konfigurationsdatei erzeugen

    if(isset($_POST['user_login']))
    {
        // Daten des Administrators in Sessionvariablen gefiltert speichern
        $_SESSION['user_login'] = strStripTags($_POST['user_login']);
        $md5_password           = md5(strStripTags($_POST['user_password']));

        if(strlen($_SESSION['user_login']) == 0
        || strlen($_POST['user_password']) == 0 )
        {
            showPage($gL10n->get('INS_LOGIN_WEBMASTER_NOT_COMPLETELY'), 'new_organization.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'), 3);
        }

        // Verbindung zu Datenbank herstellen
        $db = new MySqlDB();
        $connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

        // Logindaten pruefen
        $sql    = 'SELECT DISTINCT usr_id
                     FROM '. TBL_USERS. ', '. TBL_MEMBERS. ', '. TBL_ROLES. '
                    WHERE UPPER(usr_login_name) LIKE UPPER(\''. $_SESSION['user_login']. '\')
                      AND usr_password = \''. $md5_password. '\'
                      AND usr_valid    = 1
                      AND mem_usr_id   = usr_id
                      AND mem_rol_id   = rol_id
                      AND mem_begin   <= \''.DATE_NOW.'\'
                      AND mem_end      > \''.DATE_NOW.'\'
                      AND rol_valid    = 1
                      AND rol_name     = \''.$gL10n->get('SYS_WEBMASTER').'\' ';
        $result = $db->query($sql);

        $user_found = $db->num_rows($result);
        $user_row   = $db->fetch_array($result);

        if($user_found != 1)
        {
            showPage($gL10n->get('INS_LOGIN_WEBMASTER_NOT_VALID'), 'new_organization.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'), 3);
        }
        else
        {
            $_SESSION['webmaster_id'] = $user_row['usr_id'];
        }
    }

    $message = '<strong>'.$gL10n->get('INS_CREATE_CONFIGURATION_FILE').'</strong><br /><br />
                '.$gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE', 'config.php', 'config_example.php').'<br /><br />

                <span class="iconTextLink">
                    <a href="new_organization.php?mode=5"><img
                    src="layout/page_white_download.png" alt="'.$gL10n->get('INS_DOWNLOAD', 'config.php').'" /></a>
                    <a href="new_organization.php?mode=5">'.$gL10n->get('INS_DOWNLOAD', 'config.php').'</a>
                </span>
                <br />';
    showPage($message, 'new_organization.php?mode=6', 'database_in.png', $gL10n->get('INS_SET_UP_ORGANIZATION'), 3);
}
elseif($req_mode == 5)
{
	if(isset($_SESSION['webmaster_id']) == false || $_SESSION['webmaster_id'] == 0)
	{
        showPage($gL10n->get('INS_LOGIN_WEBMASTER_NOT_COMPLETELY'), 'new_organization.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'), 3);
   	}

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

    $file_content = str_replace('%PREFIX%',    $g_tbl_praefix, $file_content);
    $file_content = str_replace('%SERVER%',    $g_adm_srv,     $file_content);
    $file_content = str_replace('%USER%',      $g_adm_usr,     $file_content);
    $file_content = str_replace('%PASSWORD%',  $g_adm_pw,      $file_content);
    $file_content = str_replace('%DATABASE%',  $g_adm_db,      $file_content);
    $file_content = str_replace('%ROOT_PATH%', $root_path,     $file_content);
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
elseif($req_mode == 6)
{
    // Installation starten

	if(isset($_SESSION['webmaster_id']) == false || $_SESSION['webmaster_id'] == 0)
	{
        showPage($gL10n->get('INS_LOGIN_WEBMASTER_NOT_COMPLETELY'), 'new_organization.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'), 3);
   	}

    if(file_exists('../config.php') == false)
    {
        showPage($gL10n->get('INS_CONFIGURATION_FILE_NOT_FOUND', 'config.php'), 'new_organization.php?mode=4', 'back.png', $gL10n->get('SYS_BACK'), 3);
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
    $sql = 'INSERT INTO '. TBL_ORGANIZATIONS. ' (org_longname, org_shortname, org_homepage)
                                         VALUES (\''.$_SESSION['orga_name_long'].'\', \''.$_SESSION['orga_name_short'].'\', \''.$_SERVER['HTTP_HOST'].'\')';
    $db->query($sql);

    $gCurrentOrganization = new Organization($db, $_SESSION['orga_name_short']);

	// create object with current user field structure
	$gProfileFields = new ProfileFields($db, $gCurrentOrganization);

    // Userobjekt anlegen
    $gCurrentUser = new User($db, $gProfileFields, $_SESSION['webmaster_id']);

    // alle Einstellungen aus preferences.php in die Tabelle adm_preferences schreiben
    include('db_scripts/preferences.php');

    // die Administrator-Email-Adresse ist erst einmal die vom Installationsuser
    $orga_preferences['email_administrator'] = $gCurrentUser->getValue('EMAIL');

    $gCurrentOrganization->setPreferences($orga_preferences, false);

    // alle Systemmails aus systemmails_texts.php in die Tabelle adm_texts schreiben
    $systemmails_texts = array('SYSMAIL_REGISTRATION_USER' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_USER'),
                               'SYSMAIL_REGISTRATION_WEBMASTER' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_WEBMASTER'),
                               'SYSMAIL_NEW_PASSWORD' => $gL10n->get('SYS_SYSMAIL_NEW_PASSWORD'),
                               'SYSMAIL_ACTIVATION_LINK' => $gL10n->get('SYS_SYSMAIL_ACTIVATION_LINK'));
    $text = new TableText($db);

    foreach($systemmails_texts as $key => $value)
    {
        // <br /> muessen zu normalen Zeilenumbruechen umgewandelt werden
        $value = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/',chr(13).chr(10),$value);

        $text->clear();
        $text->setValue('txt_name', $key);
        $text->setValue('txt_text', $value);
        $text->save();
    }

    // Admidio-Versionsnummer schreiben
    $sql = 'INSERT INTO '. TBL_PREFERENCES. ' (prf_org_id, prf_name, prf_value)
                                       VALUES ('. $gCurrentOrganization->getValue('org_id'). ', \'db_version\',      \''. ADMIDIO_VERSION. '\') 
                                            , ('. $gCurrentOrganization->getValue('org_id'). ', \'db_version_beta\', \''. BETA_VERSION. '\')';
    $db->query($sql);

    // Default-Kategorie fuer Rollen und Links eintragen
    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                           VALUES ('. $gCurrentOrganization->getValue('org_id'). ', \'ROL\', \'COMMON\', \'SYS_COMMON\', 0, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')';
    $db->query($sql);
    $category_common = $db->insert_id();

    $sql = 'INSERT INTO '. TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                     VALUES ('. $gCurrentOrganization->getValue('org_id').', \'ROL\', \'GROUPS\',  \'INS_GROUPS\', 0, 0, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'ROL\', \'COURSES\',  \'INS_COURSES\', 0, 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'ROL\', \'TEAMS\',  \'INS_TEAMS\', 0, 0, 4, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'LNK\', \'COMMON\',  \'SYS_COMMON\', 0, 0, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'LNK\', \'INTERN\',  \'INS_INTERN\', 1, 0, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'DAT\', \'COMMON\',  \'SYS_COMMON\', 0, 0, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'DAT\', \'TRAINING\',  \'INS_TRAINING\', 0, 0, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                          , ('. $gCurrentOrganization->getValue('org_id').', \'DAT\', \'COURSES\',  \'INS_COURSES\', 0, 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\') ';
    $db->query($sql);

    //DefaultOrdner fuer Downloadmodul in der DB anlegen:
    $sql = 'INSERT INTO '. TBL_FOLDERS. ' (fol_org_id, fol_type, fol_name, fol_path,
                                           fol_locked, fol_public, fol_timestamp)
                                    VALUES ('. $gCurrentOrganization->getValue('org_id'). ', \'DOWNLOAD\', \'download\', \'/adm_my_files\',
                                            0,1,\''.DATETIME_NOW.'\')';
    $db->query($sql);

    // nun die Default-Rollen anlegen

    // Webmaster
    $role_webmaster = new TableRoles($db);
    $role_webmaster->setValue('rol_cat_id', $category_common);
    $role_webmaster->setValue('rol_name', $gL10n->get('SYS_WEBMASTER'));
    $role_webmaster->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_WEBMASTER'));
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
    $role_webmaster->save();

    // Mitglied
    $role_member = new TableRoles($db);
    $role_member->setValue('rol_cat_id', $category_common);
    $role_member->setValue('rol_name', $gL10n->get('SYS_MEMBER'));
    $role_member->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_MEMBER'));
    $role_member->setValue('rol_mail_this_role', 2);
    $role_member->setValue('rol_profile', 1);
    $role_member->setValue('rol_this_list_view', 1);
    $role_member->save();

    // Vorstand
    $role_management = new TableRoles($db);
    $role_management->setValue('rol_cat_id', $category_common);
    $role_management->setValue('rol_name', $gL10n->get('INS_BOARD'));
    $role_management->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_BOARD'));
    $role_management->setValue('rol_announcements', 1);
    $role_management->setValue('rol_dates', 1);
    $role_management->setValue('rol_weblinks', 1);
    $role_management->setValue('rol_edit_user', 1);
    $role_management->setValue('rol_mail_to_all', 1);
    $role_management->setValue('rol_mail_this_role', 2);
    $role_management->setValue('rol_profile', 1);
    $role_management->setValue('rol_this_list_view', 1);
    $role_management->setValue('rol_all_lists_view', 1);
    $role_management->save();

    // die Rolle Mitglied wird als Defaultrolle fuer neue User eingestellt
	$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = '. $role_member->getValue('rol_id'). '
			 WHERE prf_name = "profile_default_role" ';
	$db->query($sql);

    // Mitgliedschaft bei Rolle 'Webmaster' anlegen
    $member = new TableMembers($db);
    $member->startMembership($role_webmaster->getValue('rol_id'), $gCurrentUser->getValue('usr_id'));
    $member->startMembership($role_member->getValue('rol_id'), $gCurrentUser->getValue('usr_id'));

    // Default-Listen-Konfigurationen anlegen
    $address_list = new ListConfiguration($db);
    $address_list->setValue('lst_name', $gL10n->get('INS_ADDRESS_LIST'));
    $address_list->setValue('lst_global', 1);
    $address_list->setValue('lst_default', 1);
    $address_list->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
    $address_list->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
    $address_list->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
    $address_list->addColumn(4, $gProfileFields->getProperty('ADDRESS', 'usf_id'));
    $address_list->addColumn(5, $gProfileFields->getProperty('POSTCODE', 'usf_id'));
    $address_list->addColumn(6, $gProfileFields->getProperty('CITY', 'usf_id'));
    $address_list->save();

    $phone_list = new ListConfiguration($db);
    $phone_list->setValue('lst_name', $gL10n->get('INS_PHONE_LIST'));
    $phone_list->setValue('lst_global', 1);
    $phone_list->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
    $phone_list->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
    $phone_list->addColumn(3, $gProfileFields->getProperty('PHONE', 'usf_id'));
    $phone_list->addColumn(4, $gProfileFields->getProperty('MOBILE', 'usf_id'));
    $phone_list->addColumn(5, $gProfileFields->getProperty('EMAIL', 'usf_id'));
    $phone_list->addColumn(6, $gProfileFields->getProperty('FAX', 'usf_id'));
    $phone_list->save();

    $contact_list = new ListConfiguration($db);
    $contact_list->setValue('lst_name', $gL10n->get('INS_CONTACT_DETAILS'));
    $contact_list->setValue('lst_global', 1);
    $contact_list->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
    $contact_list->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
    $contact_list->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
    $contact_list->addColumn(4, $gProfileFields->getProperty('ADDRESS', 'usf_id'));
    $contact_list->addColumn(5, $gProfileFields->getProperty('POSTCODE', 'usf_id'));
    $contact_list->addColumn(6, $gProfileFields->getProperty('CITY', 'usf_id'));
    $contact_list->addColumn(7, $gProfileFields->getProperty('PHONE', 'usf_id'));
    $contact_list->addColumn(8, $gProfileFields->getProperty('MOBILE', 'usf_id'));
    $contact_list->addColumn(9, $gProfileFields->getProperty('EMAIL', 'usf_id'));
    $contact_list->save();

    $former_list = new ListConfiguration($db);
    $former_list->setValue('lst_name', $gL10n->get('INS_MEMBERSHIP'));
    $former_list->setValue('lst_global', 1);
    $former_list->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'));
    $former_list->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'));
    $former_list->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
    $former_list->addColumn(4, 'mem_begin');
    $former_list->addColumn(5, 'mem_end', 'DESC');
    $former_list->save();

    $db->endTransaction();

    // Daten der Session loeschen
    session_unset();

    $message = '<img style="vertical-align: top;" src="layout/ok.png" /> <strong>'.$gL10n->get('INS_SETUP_WAS_SUCCESSFUL').'</strong><br /><br />
                '.$gL10n->get('INS_SETUP_NEW_ORGANIZATION_SUCCESSFUL', $_SESSION['orga_name_long']);
    if(is_writeable('../adm_my_files') == false)
    {
        $message = $message. '<br /><br /><img src="layout/warning.png" alt="Warnung" /> '.$gL10n->get('INS_FOLDER_NOT_WRITABLE', 'adm_my_files');
    }
    showPage($message, '../adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'));
}

?>