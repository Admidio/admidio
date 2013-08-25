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

if(isset($_SESSION['prefix']))
{
    $g_tbl_praefix = $_SESSION['prefix'];
}
else
{
	// default praefix is "adm" because of compatibility to older versions
    $g_tbl_praefix = 'adm';
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
if(file_exists('../../config.php') == true)
{
    require_once(SERVER_PATH. '/config.php');

    $db = Database::createDatabaseObject($gDbType);
    $connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

    // now check if a valid installation exists.
    $sql = 'SELECT org_id FROM '.TBL_ORGANIZATIONS;
    $db->query($sql, false);
    $count = $db->num_rows();
    
    if($count > 0)
    {
        // valid installation exists -> exist installation
        showPage($gL10n->get('INS_INSTALLATION_EXISTS'), '../index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'));
    }
    /*elseif($getMode != 8)
    {
        showPage($gL10n->get('INS_CONFIGURATION_FILE_FOUND', 'config.php'), 'installation.php?mode=8', 'database_in.png', $gL10n->get('INS_CONTINUE_INSTALLATION'));
    }*/
}

if($getMode == 1)  // (Default) Choose language
{
    session_destroy();

    $message = '<div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_CHOOSE_LANGUAGE').'</div>
                    <div class="groupBoxBody">
                        <div class="admFieldList">
                            <div class="admFieldLabel">
                                <label for="system_language">'.$gL10n->get('SYS_LANGUAGE').':</label></div>
                            <div class="admFieldElement">
                                '. FormElements::generateXMLSelectBox(SERVER_PATH.'/adm_program/languages/languages.xml', 'ISOCODE', 'NAME', 'system_language').'</div>
                        </div>
                    </div>
                </div>
                <br />';
    showPage($message, 'installation.php?mode=2', 'forward.png', $gL10n->get('SYS_NEXT'));
}
elseif($getMode == 2)  // Welcome to installation
{   
    // Pruefen ob Sprache uebergeben wurde
    if(isset($_POST['system_language']) == false || strlen($_POST['system_language']) == 0)
    {
        showPage($gL10n->get('INS_LANGUAGE_NOT_CHOOSEN'), 'installation.php?mode=1', 'back.png', $gL10n->get('SYS_BACK'));
    }
    else
    {
        $_SESSION['language'] = $_POST['system_language'];
        $gL10n->setLanguage($_SESSION['language']);
    }
    
    $message = '<h2 class="admHeadline2">'.$gL10n->get('INS_WELCOME_TO_INSTALLATION').'</h2>'.$gL10n->get('INS_WELCOME_TEXT');

    // falls dies eine Betaversion ist, dann Hinweis ausgeben
    if(BETA_VERSION > 0)
    {
        $message .= '<br /><br /><img style="vertical-align: top;" src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />'.$gL10n->get('INS_WARNING_BETA_VERSION');
    }

    if(ini_get('safe_mode') == 1)
    {    
        $message .= '<br /><br /><img style="vertical-align: top;" src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />'.$gL10n->get('INS_WARNING_SAFE_MODE');
    }
    showPage($message, 'installation.php?mode=3', 'forward.png', $gL10n->get('INS_DATABASE_LOGIN'));
}
elseif($getMode == 3)  // Enter database access information
{
    // initialize form data
    if(isset($_SESSION['server']))
    {
        $dbType   = $_SESSION['db_type'];
        $server   = $_SESSION['server'];
        $user     = $_SESSION['user'];
        $database = $_SESSION['database'];
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

    $message = '<h2 class="admHeadline2">'.$gL10n->get('INS_ENTER_LOGIN_TO_DATABASE').'</h2>'.$gL10n->get('INS_DATABASE_LOGIN_DESC').'
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_DATABASE_LOGIN').'</div>
                    <div class="groupBoxBody">
                        <div class="admFieldList">
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="db_type">'.$gL10n->get('INS_DATABASE_SYSTEM').':</label></div>
                                <div class="admFieldElement">
                                    '. FormElements::generateXMLSelectBox(SERVER_PATH.'/adm_program/system/databases.xml', 'IDENTIFIER', 'NAME', 'db_type', $dbType).'</div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="server">'.$gL10n->get('SYS_SERVER').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="server" id="server" maxlength="50" value="'. $server. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user">'.$gL10n->get('SYS_USERNAME').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="user" id="user" maxlength="50" value="'. $user. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="password">'.$gL10n->get('SYS_PASSWORD').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="password" name="password" id="password" maxlength="50" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="database">'.$gL10n->get('SYS_DATABASE').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="database" id="database" maxlength="50" value="'. $database. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="prefix">'.$gL10n->get('INS_TABLE_PREFIX').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admSmallTextInput" type="text" name="prefix" id="prefix" maxlength="10" value="'. $prefix. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <img src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />&nbsp;'.$gL10n->get('INS_TABLE_PREFIX_OVERRIDE_DATA').'
                            </div>
                        </div>
                    </div>
                </div>';
    showPage($message, 'installation.php?mode=4', 'forward.png', $gL10n->get('INS_SET_ORGANIZATION'));
}
elseif($getMode == 4)  // Creating organization
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
                showPage($gL10n->get('INS_TABLE_PREFIX_INVALID'), 'installation.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'));
            }
        }

        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['db_type']  = strStripTags($_POST['db_type']);
        $_SESSION['server']   = strStripTags($_POST['server']);
        $_SESSION['user']     = strStripTags($_POST['user']);
        $_SESSION['password'] = strStripTags($_POST['password']);
        $_SESSION['database'] = strStripTags($_POST['database']);
        $_SESSION['prefix']   = strStripTags($_POST['prefix']);

        if(strlen($_SESSION['db_type'])  == 0
		|| strlen($_SESSION['server'])   == 0
        || strlen($_SESSION['user'])     == 0
        || strlen($_SESSION['database']) == 0 )
        {
            showPage($gL10n->get('INS_MYSQL_LOGIN_NOT_COMPLETELY'), 'installation.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'));
        }

        // for security reasons only check database connection if no config file exists
        if(file_exists('../../config.php') == false)
        {
            // check database connections
            $db = Database::createDatabaseObject($_SESSION['db_type']);
            if($db->connect($_SESSION['server'], $_SESSION['user'], $_SESSION['password'], $_SESSION['database']) == false)
            {
                showPage($gL10n->get('INS_DATABASE_NO_LOGIN'), 'installation.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'));
            }

            //Datenbank- und PHP-Version prüfen
            if(checkVersions($db, $message) == false)
            {
                showPage($message, 'installation.php?mode=3', 'back.png', $gL10n->get('SYS_BACK'));
            }
        }
    }

    // initialize form data
    if(isset($_SESSION['orgaShortName']))
    {
        $orgaShortName = $_SESSION['orgaShortName'];
        $orgaLongName  = $_SESSION['orgaLongName'];
    }
    else
    {
        $orgaShortName = '';
        $orgaLongName  = '';
    }

    $message = $message.'<h2 class="admHeadline2">'.$gL10n->get('INS_SET_ORGANIZATION').'</h2>
                '.$gL10n->get('INS_NAME_OF_ORGANIZATION_DESC').'
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_NAME_OF_ORGANIZATION').'</div>
                    <div class="groupBoxBody">
                        <div class="admFieldList">
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="orgaShortName">'.$gL10n->get('SYS_NAME_ABBREVIATION').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admSmallTextInput" type="text" name="orgaShortName" id="orgaShortName" maxlength="10" value="'. $orgaShortName. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="orgaLongName">'.$gL10n->get('SYS_NAME').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="orgaLongName" id="orgaLongName" maxlength="60" value="'. $orgaLongName. '" /></div>
                            </div>
                        </div>
                    </div>
                </div>
                <br />';
    showPage($message, 'installation.php?mode=5', 'forward.png', $gL10n->get('INS_CREATE_ADMINISTRATOR'));
}
elseif($getMode == 5)  // Creating addministrator
{
    if(isset($_POST['orgaShortName']))
    {
        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['orgaShortName'] = strStripTags($_POST['orgaShortName']);
        $_SESSION['orgaLongName']  = strStripTags($_POST['orgaLongName']);

        if(strlen($_SESSION['orgaShortName']) == 0
        || strlen($_SESSION['orgaLongName']) == 0 )
        {
            showPage($gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'), 'installation.php?mode=4', 'back.png', $gL10n->get('SYS_BACK'));
        }
    }

    // initialize form data
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
    $message = '<h2 class="admHeadline2">'.$gL10n->get('INS_CREATE_ADMINISTRATOR').'</h2>
                '.$gL10n->get('INS_DATA_OF_ADMINISTRATOR_DESC').'
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_DATA_OF_ADMINISTRATOR').'</div>
                    <div class="groupBoxBody">
                        <div class="admFieldList">
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user_last_name">'.$gL10n->get('SYS_LASTNAME').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" class="admTextInput" type="text" name="user_last_name" id="user_last_name" maxlength="50" value="'. $user_last_name. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user_first_name">'.$gL10n->get('SYS_FIRSTNAME').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="user_first_name" id="user_first_name" maxlength="50" value="'. $user_first_name. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user_email">'.$gL10n->get('SYS_EMAIL').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="user_email" id="user_email" maxlength="50" value="'. $user_email. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user_login">'.$gL10n->get('SYS_USERNAME').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admTextInput" type="text" name="user_login" id="user_login" maxlength="35" value="'. $user_login. '" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user_password">'.$gL10n->get('SYS_PASSWORD').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admSmallTextInput" type="password" name="user_password" id="user_password" maxlength="20" /></div>
                            </div>
                            <div class="admFieldRow">
                                <div class="admFieldLabel">
                                    <label for="user_password_confirm">'.$gL10n->get('SYS_CONFIRM_PASSWORD').':</label></div>
                                <div class="admFieldElement">
                                    <input class="admSmallTextInput" type="password" name="user_password_confirm" id="user_password_confirm" maxlength="20" /></div>
                            </div>
                        </div>
                    </div>
                </div>
                <br />';
    showPage($message, 'installation.php?mode=6', 'forward.png', $gL10n->get('INS_CREATE_CONFIGURATION_FILE'));
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
            showPage($gL10n->get('INS_ADMINISTRATOR_DATA_NOT_COMPLETELY'), 'installation.php?mode=5', 'back.png', $gL10n->get('SYS_BACK'));
        }

        $_SESSION['user_email'] = admStrToLower($_SESSION['user_email']);
        if(!strValidCharacters($_SESSION['user_email'], 'email'))
        {
            showPage($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')), 'installation.php?mode=5', 'back.png', $gL10n->get('SYS_BACK'));
        }

        if($_SESSION['user_password'] != $_SESSION['user_password_confirm'])
        {
            showPage($gL10n->get('INS_PASSWORDS_NOT_EQUAL'), 'installation.php?mode=5', 'back.png', $gL10n->get('SYS_BACK'));
        }
    }

    $message = '<h2 class="admHeadline2">'.$gL10n->get('INS_CREATE_CONFIGURATION_FILE').'</h2>
                '.$gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE', 'config.php', 'config_example.php').'<br /><br />

                <span class="iconTextLink">
                    <a href="installation.php?mode=7"><img
                    src="layout/page_white_download.png" alt="'.$gL10n->get('INS_DOWNLOAD', 'config.php').'" /></a>
                    <a href="installation.php?mode=7">'.$gL10n->get('INS_DOWNLOAD', 'config.php').'</a>
                </span>
                <br />';
    showPage($message, 'installation.php?mode=8', 'database_in.png', $gL10n->get('INS_INSTALL_ADMIDIO'));
}
elseif($getMode == 7) // Download configuration file
{
    // MySQL-Zugangsdaten in config.php schreiben
    // Datei auslesen
    $filename     = 'config.php';
    $config_file  = fopen($filename, 'r');
    $file_content = fread($config_file, filesize($filename));
    fclose($config_file);

    // den Root-Pfad ermitteln
    $rootPath = $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
    $rootPath = substr($rootPath, 0, strpos($rootPath, '/adm_program'));
    if(!strpos($rootPath, 'http://'))
    {
        $rootPath = 'http://'. $rootPath;
    }

    $file_content = str_replace('%PREFIX%',  $_SESSION['prefix'],  $file_content);
    $file_content = str_replace('%DB_TYPE%', $_SESSION['db_type'], $file_content);
    $file_content = str_replace('%SERVER%',  $_SESSION['server'],  $file_content);
    $file_content = str_replace('%USER%',    $_SESSION['user'],    $file_content);
    $file_content = str_replace('%PASSWORD%',$_SESSION['password'],$file_content);
    $file_content = str_replace('%DATABASE%',$_SESSION['database'],$file_content);
    $file_content = str_replace('%ROOT_PATH%', $rootPath, $file_content);
    $file_content = str_replace('%ORGANIZATION%', $_SESSION['orgaShortName'], $file_content);

    // die erstellte Config-Datei an den User schicken
    $file_name   = 'config.php';
    $file_length = strlen($file_content);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: '.$file_length);
    header('Content-Disposition: attachment; filename="'.$file_name.'"');
    echo $file_content;
    exit();
}
elseif($getMode == 8)	// Start installation
{
    // Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
    if(file_exists('../../config.php') == false)
    {
        showPage($gL10n->get('INS_CONFIGURATION_FILE_NOT_FOUND', 'config.php'), 'installation.php?mode=6', 'back.png', $gL10n->get('SYS_BACK'));
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
        || $g_adm_srv     != $_SESSION['server']
        || $g_adm_usr     != $_SESSION['user']
        || $g_adm_pw      != $_SESSION['password']
        || $g_adm_db      != $_SESSION['database']
        || $g_organization!= $_SESSION['orgaShortName'])
        {
            showPage($gL10n->get('INS_DATA_DO_NOT_MATCH', 'config.php'), 'installation.php?mode=6', 'back.png', $gL10n->get('SYS_BACK'));
        }
    }
    
    // read data from sql script db.sql and execute all statements to the current database
    $filename = 'db_scripts/db.sql';
    $file     = fopen($filename, 'r')
                or showPage($gL10n->get('INS_DATABASE_FILE_NOT_FOUND', 'db.sql', 'adm_program/installation/db_scripts'), 'installation.php?mode=6', 'back.png', $gL10n->get('SYS_BACK'));
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
    $this->db->query($sql);

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
    $gCurrentOrganization = new Organization($db, $_SESSION['orgaShortName']);
    $gCurrentOrganization->setValue('org_longname', $_SESSION['orgaLongName']);
    $gCurrentOrganization->setValue('org_shortname', $_SESSION['orgaShortName']);
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

    // show dialog with success notification
    $message = '<h2 class="admHeadline2"><img style="vertical-align: top;" src="layout/ok.png" /> '.$gL10n->get('INS_INSTALLATION_WAS_SUCCESSFUL').'</h2>
               '.$gL10n->get('INS_INSTALLATION_SUCCESSFUL').'<br /><br />
               '.$gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT');
    if(is_writeable('../../adm_my_files') == false)
    {
        $message = $message. '<br /><br /><img src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" /> '.$gL10n->get('INS_FOLDER_NOT_WRITABLE', 'adm_my_files');
    }
    showPage($message, 'http://www.admidio.org/index.php?page=donate', 'money.png', $gL10n->get('SYS_DONATE'));
}

?>