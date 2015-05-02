<?php
/******************************************************************************
 * Handle update of Admidio database to a new version
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode     = 1 : (Default) Check update status and show dialog with status
 *            2 : Perform update
 *            3 : Show result of update
 *
 *****************************************************************************/
// embed config and constants file
if(file_exists('../../adm_my_files/config.php') == true)
{
    require_once('../../adm_my_files/config.php');
}
elseif(file_exists('../../config.php') == true)
{
    // config file at destination of version 2.0 exists -> copy config file to new destination
    if(@copy('../../config.php', '../../adm_my_files/config.php') == false)
    {
         die('<div style="color: #CC0000;">Error: The file <b>config.php</b> could not be copied to the folder <b>adm_my_files</b>.
         Please check if this folder has the necessary write rights. If it\'s not possible to set this right then copy the
         config.php from the Admidio main folder to adm_my_files with your FTP program.</div>');
    }
    require_once('../../adm_my_files/config.php');
}
else
{
    // no config file exists -> go to installation
    header('Location: installation.php');
    exit();
}

if(strlen($g_tbl_praefix) == 0)
{
    // default praefix is "adm" because of compatibility to older versions
    $g_tbl_praefix = 'adm';
}

// if there is no debug flag in config.php than set debug to false
if(isset($gDebug) == false || $gDebug != 1)
{
    $gDebug = 0;
}

require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1). '/adm_program/system/constants.php');

// check PHP version and show notice if version is too low
if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
{
    die('<div style="color: #CC0000;">Error: Your PHP version '.phpversion().' does not fulfill
        the minimum requirements for this Admidio version. You need at least PHP '.MIN_PHP_VERSION.' or higher.</div>');
}

require_once('install_functions.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');

// Initialize and check the parameters

define('THEME_PATH', 'layout');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('defaultValue' => 1));
$message = '';

// Default-DB-Type ist immer MySql
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

// connect to database
$gDb = Database::createDatabaseObject($gDbType);
$gDbConnection = $gDb->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// now check if a valid installation exists.
$sql = 'SELECT org_id FROM '.TBL_ORGANIZATIONS;
$gDb->query($sql, false);
$count = $gDb->num_rows();

if($count == 0)
{
    // no valid installation exists -> show installation wizard
    header('Location: installation.php');
}

// Daten der aktuellen Organisation einlesen
$gCurrentOrganization = new Organization($gDb, $g_organization);

if($gCurrentOrganization->getValue('org_id') == 0)
{
    // Organisation wurde nicht gefunden
    die('<div style="color: #CC0000;">Error: The organization of the config.php could not be found in the database!</div>');
}

// organisationsspezifische Einstellungen aus adm_preferences auslesen
$gPreferences = $gCurrentOrganization->getPreferences();

// create language and language data object to handle translations
if(isset($gPreferences['system_language']) == false)
{
    $gPreferences['system_language'] = 'de';
}
$gL10n = new Language();
$gLanguageData = new LanguageData($gPreferences['system_language']);
$gL10n->addLanguageData($gLanguageData);

// config.php exists at wrong place
if(file_exists('../../config.php') == true && file_exists('../../adm_my_files/config.php') == true)
{
    // try to delete the config file at the old place otherwise show notice to user
    if(@unlink('../../config.php') == false)
    {
        showNotice($gL10n->get('INS_DELETE_CONFIG_FILE', $g_root_path), $g_root_path.'/adm_program/installation/index.php', $gL10n->get('SYS_OVERVIEW'), 'layout/application_view_list.png');
    }
}

// check database version
$message = checkDatabaseVersion($gDb);

if(strlen($message) > 0)
{
    showNotice($message, $g_root_path.'/adm_program/index.php', $gL10n->get('SYS_OVERVIEW'), 'layout/application_view_list.png');
}

// read current version of Admidio database
$installedDbVersion     = '';
$installedDbBetaVersion = '';
$maxUpdateStep          = 0;
$currentUpdateStep      = 0;

if($gDb->query('SELECT 1 FROM '.TBL_COMPONENTS, false) == false)
{
    // in Admidio version 2 the database version was stored in preferences table
    if(isset($gPreferences['db_version']))
    {
        $installedDbVersion     = $gPreferences['db_version'];
        $installedDbBetaVersion = $gPreferences['db_version_beta'];
    }
}
else
{
    // read system component
    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

    if($componentUpdateHandle->getValue('com_id') > 0)
    {
        $installedDbVersion     = $componentUpdateHandle->getValue('com_version');
        $installedDbBetaVersion = $componentUpdateHandle->getValue('com_beta');
        $currentUpdateStep      = $componentUpdateHandle->getValue('com_update_step');
        $maxUpdateStep          = $componentUpdateHandle->getMaxUpdateStep();
    }
}

// if databse version is not set then show notice
if(strlen($installedDbVersion) == 0)
{
    $message = '<div class="alert alert-danger alert-small" role="alert"><span class="glyphicon glyphicon-exclamation-sign"></span>
                    <strong>'.$gL10n->get('INS_UPDATE_NOT_POSSIBLE').'</strong></div>
                <p>'.$gL10n->get('INS_NO_INSTALLED_VERSION_FOUND', ADMIDIO_VERSION).'</p>';
    showNotice($message, $g_root_path.'/adm_program/index.php', $gL10n->get('SYS_OVERVIEW'), 'layout/application_view_list.png', true);
}


if($getMode == 1)
{
    // if database version is smaller then source version -> update
    // if database version is equal to source but beta has a differnce -> update
    if(version_compare($installedDbVersion, ADMIDIO_VERSION) < 0
    ||(version_compare($installedDbVersion, ADMIDIO_VERSION) == 0 && $maxUpdateStep > $currentUpdateStep))
    {
        // create a page with the notice that the installation must be configured on the next pages
        $form = new HtmlFormInstallation('update_login_form', 'update.php?mode=2');
        $form->setUpdateModus();
        $form->setFormDescription('<h3>'.$gL10n->get('INS_DATABASE_NEEDS_UPDATED_VERSION', $installedDbVersion, ADMIDIO_VERSION_TEXT).'</h3>');

        if($gDebug == false)
        {
            $form->addDescription($gL10n->get('INS_WEBMASTER_LOGIN_DESC'));
            $form->addInput('login_name', $gL10n->get('SYS_USERNAME'), null, array('maxLength' => 35, 'property' => FIELD_MANDATORY, 'class' => 'form-control-small'));
            $form->addInput('password', $gL10n->get('SYS_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_MANDATORY, 'class' => 'form-control-small'));
        }

        // if this is a beta version then show a warning message
        if(BETA_VERSION > 0)
        {
            $form->addDescription('<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>
                            '.$gL10n->get('INS_WARNING_BETA_VERSION').'</div>');
        }
        $form->addSubmitButton('next_page', $gL10n->get('INS_UPDATE_DATABASE'), array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_IS_UPDATED')));
        $form->show();
    }
    // if versions are equal > no update
    elseif(version_compare($installedDbVersion, ADMIDIO_VERSION) == 0 && $maxUpdateStep == $currentUpdateStep)
    {
        $message = '<div class="alert alert-success form-alert"><span class="glyphicon glyphicon-ok"></span>
                        <strong>'.$gL10n->get('INS_DATABASE_IS_UP_TO_DATE').'</strong></div>
                    <p>'.$gL10n->get('INS_DATABASE_DOESNOT_NEED_UPDATED').'</p>';
        showNotice($message, $g_root_path.'/adm_program/index.php', $gL10n->get('SYS_OVERVIEW'), 'layout/application_view_list.png', true);
    }
    // if source version smaller then database -> show error
    else
    {
        $message = '<div class="alert alert-danger form-alert"><span class="glyphicon glyphicon-exclamation-sign"></span>
                        <strong>'.$gL10n->get('SYS_ERROR').'</strong>
                        <p>'.$gL10n->get('SYS_WEBMASTER_FILESYSTEM_INVALID', $installedDbVersion, ADMIDIO_VERSION, '<a href="http://www.admidio.org/index.php?page=download">', '</a>').'</p></div>';
        showNotice($message, $g_root_path.'/adm_program/index.php', $gL10n->get('SYS_OVERVIEW'), 'layout/application_view_list.png', true);
    }
}
elseif($getMode == 2)
{
    /**************************************/
    /* execute update script for database */
    /**************************************/

    if($gDebug == false)
    {
        try
        {
            // check name and password
            // user must have membership of one role of the organization
            $loginName    = admFuncVariableIsValid($_POST, 'login_name', 'string', array('requireValue' => true, 'directOutput' => true));
            $password     = admFuncVariableIsValid($_POST, 'password', 'string', array('requireValue' => true, 'directOutput' => true));
            $sqlWebmaster = '';

            // only check for webmaster role if version > 2.3 because before we don't have that flag
            if(version_compare($installedDbVersion, '2.4.0') > 0)
            {
                $sqlWebmaster = ' AND rol_webmaster  = 1 ';
            }

            $sql    = 'SELECT DISTINCT usr_id
                         FROM '. TBL_USERS. ', '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                        WHERE UPPER(usr_login_name) LIKE UPPER(\''.$loginName.'\')
                          AND usr_valid      = 1
                          AND mem_usr_id     = usr_id
                          AND mem_rol_id     = rol_id
                          AND mem_begin     <= \''.DATE_NOW.'\'
                          AND mem_end        > \''.DATE_NOW.'\'
                          AND rol_valid      = 1
                              '.$sqlWebmaster.'
                          AND rol_cat_id     = cat_id
                          AND cat_org_id     = '.$gCurrentOrganization->getValue('org_id');
            $result = $gDb->query($sql);

            $userFound = $gDb->num_rows($result);
            $userRow   = $gDb->fetch_array($result);

            if ($userFound == 1)
            {
                // create object with current user field structure und user object
                $gProfileFields = new ProfileFields($gDb, $gCurrentOrganization->getValue('org_id'));
                $gCurrentUser   = new User($gDb, $gProfileFields, $userRow['usr_id']);

                // check login. If login failed an exception will be thrown
                $gCurrentUser->checkLogin($password, false, false);
            }
            else
            {
                $message = '<div class="alert alert-danger alert-small" role="alert"><span class="glyphicon glyphicon-exclamation-sign"></span>
                                <strong>'.$gL10n->get('INS_WEBMASTER_LOGIN_FAILED').'</strong></div>';
                showNotice($message, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
            }
        }
        catch(AdmException $e)
        {
            $message = '<div class="alert alert-danger alert-small" role="alert"><span class="glyphicon glyphicon-exclamation-sign"></span>
                            <strong>'.$e->getText().'</strong></div>';
            showNotice($message, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
        }
    }

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(300);

    $mainVersion      = substr($installedDbVersion, 0, 1);
    $subVersion       = substr($installedDbVersion, 2, 1);
    $microVersion     = substr($installedDbVersion, 4, 1);
    $microVersion     = $microVersion + 1;
    $flagNextVersion  = true;

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    include('db_scripts/preferences.php');

    $sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
    $result_orga = $gDb->query($sql);

    while($row_orga = $gDb->fetch_array($result_orga))
    {
        $gCurrentOrganization->setValue('org_id', $row_orga['org_id']);
        $gCurrentOrganization->setPreferences($orga_preferences, false);
    }

    if($gDbType == 'mysql')
    {
        // disable foreign key checks for mysql, so tables can easily deleted
        $sql = 'SET foreign_key_checks = 0 ';
        $gDb->query($sql);
    }

    // before version 3 we had an other update mechanism which will be handled here
    if($mainVersion < 3)
    {
        // nun in einer Schleife die Update-Scripte fuer alle Versionen zwischen der Alten und Neuen einspielen
        while($flagNextVersion)
        {
            $flagNextVersion = false;

            if($mainVersion < 3)
            {
                // until version 3 Admidio had sql and php files where the update statements where stored
                // these files must be excecuted

                // in der Schleife wird geschaut ob es Scripte fuer eine Microversion (3.Versionsstelle) gibt
                // Microversion 0 sollte immer vorhanden sein, die anderen in den meisten Faellen nicht
                for($microVersion = $microVersion; $microVersion < 15; $microVersion++)
                {
                    // Update-Datei der naechsten hoeheren Version ermitteln
                    $sqlUpdateFile = 'db_scripts/upd_'. $mainVersion. '_'. $subVersion. '_'. $microVersion. '_db.sql';
                    $phpUpdateFile = 'db_scripts/upd_'. $mainVersion. '_'. $subVersion. '_'. $microVersion. '_conv.php';

                    // output of the version number for better debugging
                    if($gDebug)
                    {
                        error_log('Update to version '.$mainVersion.'.'.$subVersion.'.'.$microVersion);
                    }

                    if(file_exists($sqlUpdateFile))
                    {
                        // SQL-Script abarbeiten
                        $file    = fopen($sqlUpdateFile, 'r')
                                   or showNotice($gL10n->get('INS_ERROR_OPEN_FILE', $sqlUpdateFile), 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
                        $content = fread($file, filesize($sqlUpdateFile));
                        $sql_arr = explode(';', $content);
                        fclose($file);

                        foreach($sql_arr as $sql)
                        {
                            if(strlen(trim($sql)) > 0)
                            {
                                // replace prefix with installation specific table prefix
                                $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);
                                // now execute update sql
                                $gDb->query($sql);
                            }
                        }

                        $flagNextVersion = true;
                    }

                    // now set db specific admidio preferences
                    $gDb->setDBSpecificAdmidioProperties($mainVersion. '.'. $subVersion. '.'. $microVersion);

                    // check if an php update file exists and then execute the script
                    if(file_exists($phpUpdateFile))
                    {
                        include($phpUpdateFile);
                        $flagNextVersion = true;
                    }
                }

                // keine Datei mit der Microversion gefunden, dann die Main- oder Subversion hochsetzen,
                // solange bis die aktuelle Versionsnummer erreicht wurde
                if($flagNextVersion == false
                && version_compare($mainVersion. '.'. $subVersion. '.'. $microVersion, ADMIDIO_VERSION) == -1)
                {
                    if($subVersion == 4) // we do not have more then 4 subversions with old updater
                    {
                        $mainVersion = $mainVersion + 1;
                        $subVersion  = 0;
                    }
                    else
                    {
                        $subVersion  = $subVersion + 1;
                    }

                    $microVersion    = 0;
                    $flagNextVersion = true;
                }
            }
        }
    }

    // since version 3 we do the update with xml files and a new class model
    if($mainVersion >= 3)
    {
        // reread component because in version 3.0 the component will be created within the update
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
        $componentUpdateHandle->setTargetVersion(ADMIDIO_VERSION);
        $componentUpdateHandle->update();
    }

    if($gDbType == 'mysql')
    {
        // activate foreign key checks, so database is consistant
        $sql = 'SET foreign_key_checks = 1 ';
        $gDb->query($sql);
    }

    // nach dem Update erst einmal bei Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 ';
    $gDb->query($sql);

    // create an installation unique cookie prefix and remove special characters
    $gCookiePraefix = 'ADMIDIO_'.$g_organization.'_'.$g_adm_db.'_'.$g_tbl_praefix;
    $gCookiePraefix = strtr($gCookiePraefix, ' .,;:', '_____');

    // start php session and remove session object with all data, so that
    // all data will be read after the update
    session_name($gCookiePraefix. '_PHP_ID');
    session_start();
    unset($_SESSION['gCurrentSession']);

    // show notice that update was successful
    $form = new HtmlFormInstallation('installation-form', 'http://www.admidio.org/index.php?page=donate');
    $form->setFormDescription($gL10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', ADMIDIO_VERSION_TEXT).'<br /><br />'.$gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT'), '<div class="alert alert-success form-alert"><span class="glyphicon glyphicon-ok"></span><strong>'.$gL10n->get('INS_UPDATING_WAS_SUCCESSFUL').'</strong></div>');
    $form->openButtonGroup();
    $form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'layout/money.png'));
    $form->addButton('main_page', $gL10n->get('SYS_LATER'), array('icon' => 'layout/application_view_list.png', 'link' => '../index.php'));
    $form->closeButtonGroup();
    $form->show();
}

?>
