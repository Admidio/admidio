<?php
/******************************************************************************
 * Installation and configuration of Admidio database and config file
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode     = 1 : (Default) Choose language
 *            2 : Welcome to installation
 *            3 : Enter database access information
 *            4 : Creating organization
 *            5 : Creating administrator
 *            6 : Creating configuration file
 *            7 : Download configuration file
 *            8 : Start installation
 *
 *****************************************************************************/

session_name('admidio_php_session_id');
session_start();

// if config file already exists then load file with their variables
if(file_exists('../../adm_my_files/config.php') == true)
{
    require_once('../../adm_my_files/config.php');
}

if(isset($g_tbl_praefix) == false)
{
    if(isset($_SESSION['prefix']))
    {
        $g_tbl_praefix = $_SESSION['prefix'];
    }
    else
    {
        // default praefix is "adm" because of compatibility to older versions
        $g_tbl_praefix = 'adm';
    }
}
 
// embed constants file
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1). '/adm_program/system/constants.php');

// check PHP version and show notice if version is too low
if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
{
    die('<div style="color: #CC0000;">Error: Your PHP version '.phpversion().' does not fulfill 
		the minimum requirements for this Admidio version. You need at least PHP '.MIN_PHP_VERSION.' or more highly.</div>');
}

require_once('install_functions.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');

// Initialize and check the parameters

define('THEME_PATH', 'layout');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', 1);
$message = '';

// default database type is always MySQL and must be set because of old config files
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

// create language and language data object to handle translations
if(isset($_SESSION['language']))
{
    $language = $_SESSION['language'];
}
else
{
    $language = 'en';
}
$gL10n = new Language();
$gLanguageData = new LanguageData($language);
$gL10n->addLanguageData($gLanguageData);

// if config file exists then connect to database
if(file_exists('../../adm_my_files/config.php') == true)
{
    $db = Database::createDatabaseObject($gDbType);
    $connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

    // now check if a valid installation exists.
    $sql = 'SELECT org_id FROM '.TBL_ORGANIZATIONS;
    $db->query($sql, false);
    $count = $db->num_rows();
    
    if($count > 0)
    {
        // valid installation exists -> exit installation
        showNotice($gL10n->get('INS_INSTALLATION_EXISTS'), '../index.php', $gL10n->get('SYS_OVERVIEW'), 'layout/application_view_list.png');
    }
    elseif($getMode != 8)
    {
        showNotice($gL10n->get('INS_CONFIGURATION_FILE_FOUND', 'config.php'), 'installation.php?mode=8', $gL10n->get('INS_CONTINUE_INSTALLATION'), 'layout/database_in.png');
    }
}
elseif(file_exists('../../config.php') == true)
{
    // Config file found at location of version 2. Then go to update
    header('Location: update.php');
    exit();
}

if($getMode == 1)  // (Default) Choose language
{
    session_destroy();

    // create form with selectbox where user can select a language
    // the possible languages will be read from a xml file
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=2');
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_CHOOSE_LANGUAGE'));
    $form->addSelectBoxFromXml('system_language', $gL10n->get('SYS_LANGUAGE'), SERVER_PATH.'/adm_program/languages/languages.xml', 'ISOCODE', 'NAME', FIELD_MANDATORY);
    $form->closeGroupBox();
    $form->addSubmitButton('next_page', $gL10n->get('SYS_NEXT'), 'layout/forward.png', null, null, 'button');
    $form->show();
}
elseif($getMode == 2)  // Welcome to installation
{   
    // check if a language string was committed
    if(isset($_POST['system_language']) == false || strlen(trim($_POST['system_language'])) == 0)
    {
        showNotice($gL10n->get('INS_LANGUAGE_NOT_CHOOSEN'), 'installation.php?mode=1', $gL10n->get('SYS_BACK'), 'layout/back.png');
    }
    else
    {
        $_SESSION['language'] = $_POST['system_language'];
        $gL10n->setLanguage($_SESSION['language']);
    }
    
    // create the text that should be shown in the form
    $message = $gL10n->get('INS_WELCOME_TEXT');

    // if this is a beta version then show a notice to the user
    if(BETA_VERSION > 0)
    {
        $message .= '<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_WARNING_BETA_VERSION').'</div>';
    }

    // if safe mode is used then show a notice to the user
    if(ini_get('safe_mode') == 1)
    {    
        $message .= '<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_WARNING_SAFE_MODE').'</div>';
    }

    // create a page with the notice that the installation must be configured on the next pages
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=3');
    $form->setFormDescription($message, $gL10n->get('INS_WELCOME_TO_INSTALLATION'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_DATABASE_LOGIN'), 'layout/forward.png', null, null, 'button');
    $form->show();
}
elseif($getMode == 3)  // Enter database access information
{
    // initialize form data
    if(isset($_SESSION['db_server']))
    {
        $dbType   = $_SESSION['db_type'];
        $server   = $_SESSION['db_server'];
        $user     = $_SESSION['db_user'];
        $database = $_SESSION['db_database'];
        $prefix   = $_SESSION['prefix'];
    }
    else
    {
		$dbType   = 'mysql';
        $server   = '';
        $user     = '';
        $database = '';
        $prefix   = 'adm';
    }

    // create a page to enter all necessary database connection informations
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=4');
    $form->setFormDescription($gL10n->get('INS_DATABASE_LOGIN_DESC'), $gL10n->get('INS_ENTER_LOGIN_TO_DATABASE'));
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATABASE_LOGIN'));
    $form->addSelectBoxFromXml('db_type', $gL10n->get('INS_DATABASE_SYSTEM'), SERVER_PATH.'/adm_program/system/databases.xml', 'IDENTIFIER', 'NAME', FIELD_MANDATORY, $dbType);
    $form->addTextInput('db_server', $gL10n->get('SYS_SERVER'), $server, 50, FIELD_MANDATORY);
    $form->addTextInput('db_user', $gL10n->get('SYS_USERNAME'), $user, 50, FIELD_MANDATORY);
    $form->addTextInput('db_password', $gL10n->get('SYS_PASSWORD'), null, 0, FIELD_MANDATORY, 'password');
    $form->addTextInput('db_database', $gL10n->get('SYS_DATABASE'), $database, 50, FIELD_MANDATORY);
    $form->addTextInput('db_prefix', $gL10n->get('INS_TABLE_PREFIX'), $prefix, 10, FIELD_MANDATORY, 'text', null, null, null, 'form-control-small');
    $form->addDescription('<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_TABLE_PREFIX_OVERRIDE_DATA').'</div>');
    $form->closeGroupBox();
    $form->addSubmitButton('next_page', $gL10n->get('INS_SET_ORGANIZATION'), 'layout/forward.png', null, null, 'button');
    $form->show();
}
elseif($getMode == 4)  // Creating organization
{
    if(isset($_POST['db_server']))
    {
        if(strlen($_POST['db_prefix']) == 0)
        {
            $_POST['db_prefix'] = 'adm';
        }
        else
        {
            // wenn letztes Zeichen ein _ dann abschneiden
            if(strrpos($_POST['db_prefix'], '_')+1 == strlen($_POST['db_prefix']))
            {
                $_POST['db_prefix'] = substr($_POST['db_prefix'], 0, strlen($_POST['db_prefix'])-1);
            }

            // nur gueltige Zeichen zulassen
            $anz = strspn($_POST['db_prefix'], 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_');

            if($anz != strlen($_POST['db_prefix']))
            {
                showNotice($gL10n->get('INS_TABLE_PREFIX_INVALID'), 'installation.php?mode=3', $gL10n->get('SYS_BACK'), 'layout/back.png');
            }
        }

        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['db_type']  = strStripTags($_POST['db_type']);
        $_SESSION['db_server']   = strStripTags($_POST['db_server']);
        $_SESSION['db_user']     = strStripTags($_POST['db_user']);
        $_SESSION['db_password'] = strStripTags($_POST['db_password']);
        $_SESSION['db_database'] = strStripTags($_POST['db_database']);
        $_SESSION['prefix']   = strStripTags($_POST['db_prefix']);

        if(strlen($_SESSION['db_type'])  == 0
		|| strlen($_SESSION['db_server'])   == 0
        || strlen($_SESSION['db_user'])     == 0
        || strlen($_SESSION['db_database']) == 0 )
        {
            showNotice($gL10n->get('INS_MYSQL_LOGIN_NOT_COMPLETELY'), 'installation.php?mode=3', $gL10n->get('SYS_BACK'), 'layout/back.png');
        }

        // for security reasons only check database connection if no config file exists
        if(file_exists('../../adm_my_files/config.php') == false)
        {
            // check database connections
            $db = Database::createDatabaseObject($_SESSION['db_type']);
            if($db->connect($_SESSION['db_server'], $_SESSION['db_user'], $_SESSION['db_password'], $_SESSION['db_database']) == false)
            {
                showNotice($gL10n->get('INS_DATABASE_NO_LOGIN'), 'installation.php?mode=3', $gL10n->get('SYS_BACK'), 'layout/back.png');
            }

            // check database version
            $message = checkDatabaseVersion($db); 
            if(strlen($message) > 0)
            {
                showNotice($message, 'installation.php?mode=3', $gL10n->get('SYS_BACK'), 'layout/back.png');
            }
        }
    }

    // initialize form data
    if(isset($_SESSION['orga_shortname']))
    {
        $orgaShortName = $_SESSION['orga_shortname'];
        $orgaLongName  = $_SESSION['orga_longname'];
    }
    else
    {
        $orgaShortName = '';
        $orgaLongName  = '';
    }

    // create a page to enter the organization names
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=5');
    $form->setFormDescription($gL10n->get('INS_NAME_OF_ORGANIZATION_DESC'), $gL10n->get('INS_SET_ORGANIZATION'));
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_NAME_OF_ORGANIZATION'));
    $form->addTextInput('orga_shortname', $gL10n->get('SYS_NAME_ABBREVIATION'), $orgaShortName, 10, FIELD_MANDATORY, 'text', null, null, null, 'form-control-small');
    $form->addTextInput('orga_longname', $gL10n->get('SYS_NAME'), $orgaLongName, 50, FIELD_MANDATORY);
    $form->closeGroupBox();
    $form->addSubmitButton('next_page', $gL10n->get('INS_CREATE_ADMINISTRATOR'), 'layout/forward.png', null, null, 'button');
    $form->show();
}
elseif($getMode == 5)  // Creating addministrator
{
    if(isset($_POST['orga_shortname']))
    {
        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['orga_shortname'] = strStripTags($_POST['orga_shortname']);
        $_SESSION['orga_longname']  = strStripTags($_POST['orga_longname']);

        if(strlen($_SESSION['orga_shortname']) == 0
        || strlen($_SESSION['orga_longname']) == 0 )
        {
            showNotice($gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'), 'installation.php?mode=4', $gL10n->get('SYS_BACK'), 'layout/back.png');
        }
    }

    // initialize form data
    if(isset($_SESSION['user_last_name']))
    {
        $userLastName  = $_SESSION['user_last_name'];
        $userFirstName = $_SESSION['user_first_name'];
        $userEmail     = $_SESSION['user_email'];
        $userLogin     = $_SESSION['user_login'];
    }
    else
    {
        $userLastName  = '';
        $userFirstName = '';
        $userEmail     = '';
        $userLogin     = '';
    }
    
    // create a page to enter all necessary data to create a administrator user
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=6');
    $form->setFormDescription($gL10n->get('INS_DATA_OF_ADMINISTRATOR_DESC'), $gL10n->get('INS_CREATE_ADMINISTRATOR'));
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATA_OF_ADMINISTRATOR'));
    $form->addTextInput('user_last_name', $gL10n->get('SYS_LASTNAME'), $userLastName, 50, FIELD_MANDATORY);
    $form->addTextInput('user_first_name', $gL10n->get('SYS_FIRSTNAME'), $userFirstName, 50, FIELD_MANDATORY);
    $form->addTextInput('user_email', $gL10n->get('SYS_EMAIL'), $userEmail, 255, FIELD_MANDATORY);
    $form->addTextInput('user_login', $gL10n->get('SYS_USERNAME'), $userLogin, 35, FIELD_MANDATORY);
    $form->addTextInput('user_password', $gL10n->get('SYS_PASSWORD'), null, 0, FIELD_MANDATORY, 'password');
    $form->addTextInput('user_password_confirm', $gL10n->get('SYS_CONFIRM_PASSWORD'), null, 0, FIELD_MANDATORY, 'password');
    $form->closeGroupBox();
    $form->addSubmitButton('next_page', $gL10n->get('INS_INSTALL_ADMIDIO'), 'layout/database_in.png', null, null, 'button');
    $form->show();
}
elseif($getMode == 6)  // Creating configuration file
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
            showNotice($gL10n->get('INS_ADMINISTRATOR_DATA_NOT_COMPLETELY'), 'installation.php?mode=5', $gL10n->get('SYS_BACK'), 'layout/back.png');
        }

        $_SESSION['user_email'] = admStrToLower($_SESSION['user_email']);
        if(!strValidCharacters($_SESSION['user_email'], 'email'))
        {
            showNotice($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')), 'installation.php?mode=5', $gL10n->get('SYS_BACK'), 'layout/back.png');
        }

        if($_SESSION['user_password'] != $_SESSION['user_password_confirm'])
        {
            showNotice($gL10n->get('INS_PASSWORDS_NOT_EQUAL'), 'installation.php?mode=5', $gL10n->get('SYS_BACK'), 'layout/back.png');
        }
    }

    // read configuration file structure
    $filename     = 'config.php';
    $configFileHandle  = fopen($filename, 'r');
    $configFileContent = fread($configFileHandle, filesize($filename));
    fclose($configFileHandle);

    // detect root path
    $rootPath = $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
    $rootPath = substr($rootPath, 0, strpos($rootPath, '/adm_program'));
    if(!strpos($rootPath, 'http://') && !strpos($rootPath, 'https://'))
    {
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		{
			$rootPath = 'https://'. $rootPath;
		}
		else
		{
			$rootPath = 'http://'. $rootPath;
		}
    }

    // replace placeholders in configuration file structure with data of installation wizard
    $configFileContent = str_replace('%PREFIX%',  $_SESSION['prefix'],  $configFileContent);
    $configFileContent = str_replace('%DB_TYPE%', $_SESSION['db_type'], $configFileContent);
    $configFileContent = str_replace('%SERVER%',  $_SESSION['db_server'],  $configFileContent);
    $configFileContent = str_replace('%USER%',    $_SESSION['db_user'],    $configFileContent);
    $configFileContent = str_replace('%PASSWORD%',$_SESSION['db_password'],$configFileContent);
    $configFileContent = str_replace('%DATABASE%',$_SESSION['db_database'],$configFileContent);
    $configFileContent = str_replace('%ROOT_PATH%', $rootPath, $configFileContent);
    $configFileContent = str_replace('%ORGANIZATION%', $_SESSION['orga_shortname'], $configFileContent);
    $_SERVER['config_file_content'] = $configFileContent;

    // now save new configuration file in Admidio folder if user has write access to this folder
    $filename   = '../../adm_my_files/config.php';
    $configFileHandle = fopen($filename, 'a');

    if($configFileHandle)
    {
        // save config file in Admidio folder
        fwrite($configFileHandle, $configFileContent);
        fclose($configFileHandle);
        
        // start installation
        header('Location: installation.php?mode=8');
    }
    else
    {
        // if user doesn't has write access then create a page with a download link for the config file
        $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=8');
        $form->setFormDescription($gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE_DESC', 'config.php', $rootPath.'/adm_my_files', 'adm_my_files'), $gL10n->get('INS_CREATE_CONFIGURATION_FILE'));
        $form->addHtml('
            <a class="icon-text-link" href="installation.php?mode=7"><img src="layout/page_white_download.png"
                alt="'.$gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE').'" />'.$gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE').'</a>
            <br />');
        $form->addSubmitButton('next_page', $gL10n->get('INS_CONTINUE_INSTALLATION'), 'layout/database_in.png', null, 'button');
        $form->show();
    }
}
elseif($getMode == 7) // Download configuration file
{
    $filename   = 'config.php';
    $fileLength = strlen($_SERVER['config_file_content']);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: '.$fileLength);
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $_SERVER['config_file_content'];
    exit();
}
elseif($getMode == 8)	// Start installation
{
    // Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
    if(file_exists('../../adm_my_files/config.php') == false)
    {
        showNotice($gL10n->get('INS_CONFIGURATION_FILE_NOT_FOUND', 'config.php'), 'installation.php?mode=6', $gL10n->get('SYS_BACK'), 'layout/back.png');
    }

    // set execution time to 6 minutes because we have a lot to do :)
    // there should be no error output because of safe mode
    @set_time_limit(300);

    // first check if session is filled (if installation was aborted then this is not filled)
    if(isset($_SESSION['prefix']))
    {
        // if previous dialogs were filled then check if the settings are equal to config file
        if($g_tbl_praefix != $_SESSION['prefix']
        || $gDbType       != $_SESSION['db_type']
        || $g_adm_srv     != $_SESSION['db_server']
        || $g_adm_usr     != $_SESSION['db_user']
        || $g_adm_pw      != $_SESSION['db_password']
        || $g_adm_db      != $_SESSION['db_database']
        || $g_organization!= $_SESSION['orga_shortname'])
        {
            showNotice($gL10n->get('INS_DATA_DO_NOT_MATCH', 'config.php'), 'installation.php?mode=6', $gL10n->get('SYS_BACK'), 'layout/back.png');
        }
    }
    
    // read data from sql script db.sql and execute all statements to the current database
    $filename = 'db_scripts/db.sql';
    $file     = fopen($filename, 'r')
                or showNotice($gL10n->get('INS_DATABASE_FILE_NOT_FOUND', 'db.sql', 'adm_program/installation/db_scripts'), 'installation.php?mode=6', $gL10n->get('SYS_BACK'), 'layout/back.png');
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

    // create default data

    // add system component to database
    $component = new ComponentUpdate($db);
    $component->setValue('com_type', 'SYSTEM');
    $component->setValue('com_name', 'Admidio Core');
    $component->setValue('com_name_intern', 'CORE');
    $component->setValue('com_version', ADMIDIO_VERSION);
    $component->setValue('com_beta', BETA_VERSION);
    $component->setValue('com_update_step', $component->getMaxUpdateStep());    
    $component->save();

    // create a hidden system user for internal use
    // all recordsets created by installation will get the create id of the system user
    $gCurrentUser = new TableUsers($db);
    $gCurrentUser->setValue('usr_login_name', $gL10n->get('SYS_SYSTEM'));
    $gCurrentUser->setValue('usr_valid', '0');
    $gCurrentUser->setValue('usr_timestamp_create', DATETIME_NOW);
    $gCurrentUser->save(false); // no registered user -> UserIdCreate couldn't be filled
	$systemUserId = $gCurrentUser->getValue('usr_id');

    // create organization independent categories
    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                      VALUES (NULL, \'USF\', \'MASTER_DATA\', \'SYS_MASTER_DATA\', 0, 1, 1, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
    $cat_id_master_data = $db->insert_id();

    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                      VALUES (NULL, \'USF\', \'SOCIAL_NETWORKS\', \'SYS_SOCIAL_NETWORKS\', 0, 0, 2, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
    $cat_id_messenger = $db->insert_id();

    $sql = 'INSERT INTO '. TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                     VALUES (NULL, \'ROL\', \'CONFIRMATION_OF_PARTICIPATION\', \'SYS_CONFIRMATION_OF_PARTICIPATION\', 1, 0, 1, 5, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                          , (NULL, \'USF\', \'ADDIDIONAL_DATA\', \'INS_ADDIDIONAL_DATA\', 0, 0, 0, 3, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);

    // Stammdatenfelder anlegen
    $sql = 'INSERT INTO '. TBL_USER_FIELDS. ' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_value_list, usf_system, usf_disabled, usf_mandatory, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                                       VALUES ('.$cat_id_master_data.', \'TEXT\', \'LAST_NAME\', \'SYS_LASTNAME\', NULL, NULL, 1, 1, 1, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'FIRST_NAME\',\'SYS_FIRSTNAME\', NULL, NULL, 1, 1, 1, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'ADDRESS\',   \'SYS_ADDRESS\', NULL, NULL, 0, 0, 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'POSTCODE\',  \'SYS_POSTCODE\', NULL, NULL, 0, 0, 0, 4, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'CITY\',      \'SYS_CITY\', NULL, NULL, 0, 0, 0, 5, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'COUNTRY\',   \'SYS_COUNTRY\', NULL, NULL, 0, 0, 0, 6, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'PHONE\',     \'SYS_PHONE\', NULL, NULL, 0, 0, 0, 7, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'MOBILE\',    \'SYS_MOBILE\', NULL, NULL, 0, 0, 0, 8, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'TEXT\', \'FAX\',       \'SYS_FAX\', NULL, NULL, 0, 0, 0, 9, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'DATE\', \'BIRTHDAY\',  \'SYS_BIRTHDAY\', NULL, NULL, 0, 0, 0, 10, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'RADIO_BUTTON\', \'GENDER\', \'SYS_GENDER\', NULL, \'male.png|SYS_MALE\r\nfemale.png|SYS_FEMALE\', 0, 0, 0, 11, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'EMAIL\', \'EMAIL\',    \'SYS_EMAIL\', NULL, NULL, 1, 0, 1, 12, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_master_data.', \'URL\',  \'WEBSITE\',   \'SYS_WEBSITE\', NULL, NULL, 0, 0, 0, 13, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
    $usf_id_homepage = $db->insert_id();

    // Messenger anlegen
    $sql = 'INSERT INTO '. TBL_USER_FIELDS. ' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_icon, usf_url, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                                       VALUES ('.$cat_id_messenger.', \'TEXT\', \'AOL_INSTANT_MESSENGER\', \'INS_AOL_INSTANT_MESSENGER\', NULL, \'aim.png\', NULL, 0, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'FACEBOOK\',       \'INS_FACEBOOK\', \''.$gL10n->get('INS_FACEBOOK_DESC').'\', \'facebook.png\', \'http://www.facebook.com/%user_content%\', 0, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'GOOGLE_PLUS\',    \'INS_GOOGLE_PLUS\', \''.$gL10n->get('INS_GOOGLE_PLUS_DESC').'\', \'google_plus.png\', NULL, 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'ICQ\',            \'INS_ICQ\', \''.$gL10n->get('INS_ICQ_DESC').'\', \'icq.png\', \'http://www.icq.com/people/%user_content%\', 0, 4, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'SKYPE\',          \'INS_SKYPE\', \''.$gL10n->get('INS_SKYPE_DESC').'\', \'skype.png\', NULL, 0, 5, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'TWITTER\',        \'INS_TWITTER\', \''.$gL10n->get('INS_TWITTER_DESC').'\', \'twitter.png\', \'http://twitter.com/#!/%user_content%\', 0, 6, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'XING\',           \'INS_XING\', \''.$gL10n->get('INS_XING_DESC').'\', \'xing.png\', \'https://www.xing.com/profile/%user_content%\', 0, 7, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                                            , ('.$cat_id_messenger.', \'TEXT\', \'YAHOO_MESSENGER\',\'INS_YAHOO_MESSENGER\', NULL, \'yahoo.png\', NULL, 0, 8, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
	
	// now set db specific admidio preferences
	$db->setDBSpecificAdmidioProperties();

    // create new organization
    $gCurrentOrganization = new Organization($db, $_SESSION['orga_shortname']);
    $gCurrentOrganization->setValue('org_longname', $_SESSION['orga_longname']);
    $gCurrentOrganization->setValue('org_shortname', $_SESSION['orga_shortname']);
    $gCurrentOrganization->setValue('org_homepage', $_SERVER['HTTP_HOST']);
    $gCurrentOrganization->save();

    // create user webmaster and assign roles
    $webmaster = new TableUsers($db);
    $webmaster->setValue('usr_login_name', $_SESSION['user_login']);
    $webmaster->setValue('usr_password',   $_SESSION['user_password']);
    $webmaster->setValue('usr_usr_id_create', $gCurrentUser->getValue('usr_id'));
    $webmaster->setValue('usr_timestamp_create', DATETIME_NOW);
    $webmaster->save(false); // no registered user -> UserIdCreate couldn't be filled

    // write all preferences from preferences.php in table adm_preferences
    require_once('db_scripts/preferences.php');

    // set the administrator email adress to the email of the installation user
    $orga_preferences['email_administrator'] = $_SESSION['user_email'];

    // create all necessary data for this organization
    $gCurrentOrganization->setPreferences($orga_preferences, false);
    $gCurrentOrganization->createBasicData($webmaster->getValue('usr_id'));

    // create default room for room module in database
    $sql = 'INSERT INTO '. TBL_ROOMS. ' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                                    VALUES (\''.$gL10n->get('INS_CONFERENCE_ROOM').'\', \''.$gL10n->get('INS_DESCRIPTION_CONFERENCE_ROOM').'\', 
                                            15, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')';
    $db->query($sql);

    // first create a user object "current user" with webmaster rights because webmaster
    // is allowed to edit firstname and lastname
    $gCurrentUser = new User($db, $gProfileFields, $webmaster->getValue('usr_id'));
    $gCurrentUser->setValue('LAST_NAME',  $_SESSION['user_last_name']);
    $gCurrentUser->setValue('FIRST_NAME', $_SESSION['user_first_name']);
    $gCurrentUser->setValue('EMAIL',      $_SESSION['user_email']);
    $gCurrentUser->save(false);

    // now create a full user object for system user
    $systemUser = new User($db, $gProfileFields, $systemUserId);
    $systemUser->setValue('LAST_NAME', $gL10n->get('SYS_SYSTEM'));
    $systemUser->save(false); // no registered user -> UserIdCreate couldn't be filled
    
    // now set current user to system user
    $gCurrentUser->readDataById($systemUserId);
    
    // delete session data
    session_unset();

    // text for dialog
    $text = $gL10n->get('INS_INSTALLATION_SUCCESSFUL').'<br /><br />'.$gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT');
    if(is_writeable('../../adm_my_files') == false)
    {
        $text = $text. '<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_FOLDER_NOT_WRITABLE', 'adm_my_files').'</div>';
    }
    
    // show dialog with success notification
    $form = new HtmlFormInstallation('installation-form', 'http://www.admidio.org/index.php?page=donate');
    $form->setFormDescription($text, '<img style="vertical-align: top;" src="layout/ok.png" /> '.$gL10n->get('INS_INSTALLATION_WAS_SUCCESSFUL'));
    $form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), 'layout/money.png', null, null, 'button');
    $form->addSubmitButton('main_page', $gL10n->get('SYS_LATER'), 'layout/application_view_list.png', '../index.php', null, 'button');
    $form->show();
}

?>