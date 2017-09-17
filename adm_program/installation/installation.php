<?php
/**
 ***********************************************************************************************
 * Installation and configuration of Admidio database and config file
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode = 1 : (Default) Choose language
 *        2 : Welcome to installation
 *        3 : Enter database access information
 *        4 : Creating organization
 *        5 : Creating administrator
 *        6 : Creating configuration file
 *        7 : Download configuration file
 *        8 : Start installation
 ***********************************************************************************************
 */

// if config file already exists then load file with their variables
$configFile = '../../adm_my_files/config.php';
if (is_file($configFile))
{
    require_once($configFile);
}
else
{
    $g_organization = '';
    $g_root_path    = '';
}

if (!isset($_SESSION['create_config_file']))
{
    $_SESSION['create_config_file'] = true;
}

if (!isset($g_tbl_praefix))
{
    if (isset($_SESSION['prefix']))
    {
        $g_tbl_praefix = $_SESSION['prefix'];
    }
    else
    {
        // default praefix is "adm" because of compatibility to older versions
        $g_tbl_praefix = 'adm';
    }
}

$rootPath = substr(__FILE__, 0, strpos(__FILE__, DIRECTORY_SEPARATOR . 'adm_program'));
require_once($rootPath . '/adm_program/system/init_globals.php');
require_once($rootPath . '/adm_program/system/constants.php');

// check PHP version and show notice if version is too low
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
{
    exit('<div style="color: #cc0000;">Error: Your PHP version '.PHP_VERSION.' does not fulfill
        the minimum requirements for this Admidio version. You need at least PHP '.MIN_PHP_VERSION.' or higher.</div>');
}

require_once(ADMIDIO_PATH . '/adm_program/installation/install_functions.php');
require_once(ADMIDIO_PATH . '/adm_program/system/function.php');
require_once(ADMIDIO_PATH . '/adm_program/system/string.php');
require_once(ADMIDIO_PATH . '/adm_program/system/logging.php');

// Initialize and check the parameters
Session::start('ADMIDIO');

define('THEME_URL', 'layout');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1));
$message = '';

// create language and language data object to handle translations
$language = '';

if (isset($_SESSION['language']))
{
    $language = $_SESSION['language'];
}

$gL10n = new Language();
$gLanguageData = new LanguageData($language);

$gL10n->addLanguageData($gLanguageData);
$language = $gL10n->getLanguage();

$pathConfigFile = ADMIDIO_PATH . FOLDER_DATA . '/config.php';

// if config file exists then connect to database
if (is_file($pathConfigFile))
{
    try
    {
        $db = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
    }
    catch (AdmException $e)
    {
        showNotice(
            $gL10n->get('SYS_DATABASE_NO_LOGIN', $e->getText()),
            'installation.php?mode=3',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // now check if a valid installation exists.
    $sql = 'SELECT org_id FROM '.TBL_ORGANIZATIONS;
    $pdoStatement = $db->query($sql, false);

    // Check the query for results in case installation is runnnig at this time and the config file is already created but database is not installed so far
    if ($pdoStatement !== false && $pdoStatement->rowCount() > 0)
    {
        // valid installation exists -> exit installation
        showNotice(
            $gL10n->get('INS_INSTALLATION_EXISTS'),
            '../index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png'
        );
        // => EXIT
    }

    // if config exists then take parameters out of this file
    if ($getMode < 3)
    {
        // save database parameters of config.php in session variables
        $_SESSION['db_type']     = $gDbType;
        $_SESSION['db_server']   = $g_adm_srv;
        $_SESSION['db_port']     = $g_adm_port;
        $_SESSION['db_user']     = $g_adm_usr;
        $_SESSION['db_password'] = $g_adm_pw;
        $_SESSION['db_database'] = $g_adm_db;
        $_SESSION['prefix']      = $g_tbl_praefix;

        admRedirect(ADMIDIO_URL . '/adm_program/installation/installation.php?mode=4');
        // => EXIT
    }
}
elseif (is_file('../../config.php'))
{
    // Config file found at location of version 2. Then go to update
    admRedirect(ADMIDIO_URL . '/adm_program/installation/update.php');
    // => EXIT
}

if ($getMode === 1) // (Default) Choose language
{
    $gLogger->info('INSTALLATION: Choose language');

    session_destroy();

    // create form with selectbox where user can select a language
    // the possible languages will be read from a xml file
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=2');
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_CHOOSE_LANGUAGE'));
    $form->addSelectBoxFromXml(
        'system_language', $gL10n->get('SYS_LANGUAGE'), ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.xml',
        'isocode', 'name', array('property' => FIELD_REQUIRED, 'defaultValue' => $gL10n->getLanguage())
    );
    $form->closeGroupBox();
    $form->addSubmitButton('next_page', $gL10n->get('SYS_NEXT'), array('icon' => 'layout/forward.png'));
    echo $form->show();
}
elseif ($getMode === 2)  // Welcome to installation
{
    $gLogger->info('INSTALLATION: Welcome to installation');

    // check if a language string was committed
    if (!isset($_POST['system_language']) || trim($_POST['system_language']) === '')
    {
        showNotice(
            $gL10n->get('INS_LANGUAGE_NOT_CHOOSEN'),
            'installation.php?mode=1',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }
    else
    {
        $_SESSION['language'] = $_POST['system_language'];
        $gL10n->setLanguage($_SESSION['language']);
    }

    // create the text that should be shown in the form
    $message = $gL10n->get('INS_WELCOME_TEXT');

    // if this is a beta version then show a notice to the user
    if (ADMIDIO_VERSION_BETA > 0)
    {
        $gLogger->notice('INSTALLATION: This is a BETA release!');

        $message .= '
            <div class="alert alert-warning alert-small" role="alert">
                <span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_WARNING_BETA_VERSION').'
            </div>';
    }

    // if safe mode is used then show a notice to the user
    // deprecated: Remove if PHP 5.3 dropped
    if (ini_get('safe_mode') === '1')
    {
        $gLogger->warning('DEPRECATED: Safe-Mode is enabled!');
        $message .= '
            <div class="alert alert-warning alert-small" role="alert">
                <span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_WARNING_SAFE_MODE').'
            </div>';
    }

    // create a page with the notice that the installation must be configured on the next pages
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=3');
    $form->setFormDescription($message, $gL10n->get('INS_WELCOME_TO_INSTALLATION'));
    $form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?mode=1'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_DATABASE_LOGIN'), array('icon' => 'layout/forward.png'));
    echo $form->show();
}
elseif ($getMode === 3)  // Enter database access information
{
    $gLogger->info('INSTALLATION: Enter database access information');

    // initialize form data
    if (isset($_SESSION['db_server']))
    {
        $dbType   = $_SESSION['db_type'];
        $server   = $_SESSION['db_server'];
        $port     = $_SESSION['db_port'];
        $user     = $_SESSION['db_user'];
        $database = $_SESSION['db_database'];
        $prefix   = $_SESSION['prefix'];
    }
    else
    {
        $dbType   = 'mysql';
        $server   = '';
        $port     = '';
        $user     = '';
        $database = '';
        $prefix   = 'adm';
    }

    // create a page to enter all necessary database connection information
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=4');
    $form->setFormDescription($gL10n->get('INS_DATABASE_LOGIN_DESC'), $gL10n->get('INS_ENTER_LOGIN_TO_DATABASE'));
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATABASE_LOGIN'));
    $form->addSelectBoxFromXml('db_type', $gL10n->get('INS_DATABASE_SYSTEM'), ADMIDIO_PATH.'/adm_program/system/databases.xml',
                               'identifier', 'name', array('property' => FIELD_REQUIRED, 'defaultValue' => $dbType));
    $form->addInput('db_server', $gL10n->get('SYS_SERVER'), $server, array('maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addInput('db_port', $gL10n->get('SYS_PORT'), $port, array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 65535, 'step' => 1, 'helpTextIdLabel' => 'INS_DATABASE_PORT_INFO'));
    $form->addInput('db_user', $gL10n->get('SYS_USERNAME'), $user, array('maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addInput('db_password', $gL10n->get('SYS_PASSWORD'), null, array('type' => 'password'));
    $form->addInput('db_database', $gL10n->get('SYS_DATABASE'), $database, array('maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addInput('db_prefix', $gL10n->get('INS_TABLE_PREFIX'), $prefix, array('maxLength' => 10, 'property' => FIELD_REQUIRED, 'class' => 'form-control-small'));
    $form->closeGroupBox();
    $form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?mode=2'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_SET_ORGANIZATION'), array('icon' => 'layout/forward.png'));
    echo $form->show();
}
elseif ($getMode === 4)  // Creating organization
{
    $gLogger->info('INSTALLATION: Creating organisation');

    if (isset($_POST['db_server']))
    {
        if ($_POST['db_prefix'] === '')
        {
            $_POST['db_prefix'] = 'adm';
        }
        else
        {
            // wenn letztes Zeichen ein _ dann abschneiden
            if (strrpos($_POST['db_prefix'], '_')+1 === strlen($_POST['db_prefix']))
            {
                $_POST['db_prefix'] = substr($_POST['db_prefix'], 0, -1);
            }

            // nur gueltige Zeichen zulassen
            $anz = strspn($_POST['db_prefix'], 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_');

            if ($anz !== strlen($_POST['db_prefix']))
            {
                showNotice(
                    $gL10n->get('INS_TABLE_PREFIX_INVALID'),
                    'installation.php?mode=3',
                    $gL10n->get('SYS_BACK'),
                    'layout/back.png'
                );
                // => EXIT
            }
        }

        $dbPort = null;
        if (strStripTags($_POST['db_port']))
        {
            $dbPort = (int) strStripTags($_POST['db_port']);
        }

        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['db_type']     = strStripTags($_POST['db_type']);
        $_SESSION['db_server']   = strStripTags($_POST['db_server']);
        $_SESSION['db_port']     = $dbPort;
        $_SESSION['db_user']     = strStripTags($_POST['db_user']);
        $_SESSION['db_password'] = strStripTags($_POST['db_password']);
        $_SESSION['db_database'] = strStripTags($_POST['db_database']);
        $_SESSION['prefix']      = strStripTags($_POST['db_prefix']);

        if ($_SESSION['db_type']     === ''
        ||  $_SESSION['db_server']   === ''
        ||  $_SESSION['db_user']     === ''
        ||  $_SESSION['db_database'] === '')
        {
            showNotice(
                $gL10n->get('INS_MYSQL_LOGIN_NOT_COMPLETELY'),
                'installation.php?mode=3',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // for security reasons only check database connection if no config file exists
        if (!is_file($pathConfigFile))
        {
            // check database connections
            try
            {
                $db = new Database($_SESSION['db_type'], $_SESSION['db_server'], $_SESSION['db_port'], $_SESSION['db_database'], $_SESSION['db_user'], $_SESSION['db_password']);
            }
            catch (AdmException $e)
            {
                showNotice(
                    $gL10n->get('SYS_DATABASE_NO_LOGIN', $e->getText()),
                    'installation.php?mode=3',
                    $gL10n->get('SYS_BACK'),
                    'layout/back.png'
                );
                // => EXIT
            }

            // check database version
            $message = checkDatabaseVersion($db);
            if ($message !== '')
            {
                showNotice($message, 'installation.php?mode=3', $gL10n->get('SYS_BACK'), 'layout/back.png');
                // => EXIT
            }

            // now check if a valid installation exists.
            $sql = 'SELECT org_id FROM '.$_SESSION['prefix'].'_organizations';
            $pdoStatement = $db->query($sql, false);

            if ($pdoStatement !== false && $pdoStatement->rowCount() > 0)
            {
                // valid installation exists -> exit installation
                showNotice(
                    $gL10n->get('INS_INSTALLATION_EXISTS'),
                    '../index.php',
                    $gL10n->get('SYS_OVERVIEW'),
                    'layout/application_view_list.png'
                );
                // => EXIT
            }
        }
    }

    // create a page to enter the organization names
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=5');

    // initialize form data
    $shortnameProperty = FIELD_REQUIRED;

    if (isset($_SESSION['orga_shortname']))
    {
        $orgaShortName = $_SESSION['orga_shortname'];
        $orgaLongName  = $_SESSION['orga_longname'];
        $orgaEmail     = $_SESSION['orga_email'];
    }
    else
    {
        $orgaShortName = $g_organization;
        $orgaLongName  = '';
        $orgaEmail     = '';

        if ($g_organization !== '')
        {
            $shortnameProperty = FIELD_READONLY;
        }
    }

    // create array with possible PHP timezones
    $allTimezones = DateTimeZone::listIdentifiers();
    $timezones = array();
    foreach ($allTimezones as $timezone)
    {
        $timezones[$timezone] = $timezone;
    }

    $form->setFormDescription($gL10n->get('ORG_NEW_ORGANIZATION_DESC'), $gL10n->get('INS_SET_ORGANIZATION'));
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATA_OF_ORGANIZATION'));
    $form->addInput('orga_shortname', $gL10n->get('SYS_NAME_ABBREVIATION'), $orgaShortName, array('maxLength' => 10, 'property' => $shortnameProperty, 'class' => 'form-control-small'));
    $form->addInput('orga_longname', $gL10n->get('SYS_NAME'), $orgaLongName, array('maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addInput('orga_email', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $orgaEmail, array('type' => 'email', 'maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addSelectBox('orga_timezone', $gL10n->get('ORG_TIMEZONE'), $timezones, array('property' => FIELD_REQUIRED, 'defaultValue' => date_default_timezone_get()));
    $form->closeGroupBox();
    $form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?mode=3'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_CREATE_ADMINISTRATOR'), array('icon' => 'layout/forward.png'));
    echo $form->show();
}
elseif ($getMode === 5)  // Creating administrator
{
    $gLogger->info('INSTALLATION: Creating administrator');

    if (isset($_POST['orga_shortname']))
    {
        // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
        $_SESSION['orga_shortname'] = strStripTags($_POST['orga_shortname']);
        $_SESSION['orga_longname']  = strStripTags($_POST['orga_longname']);
        $_SESSION['orga_email']     = strStripTags($_POST['orga_email']);
        $_SESSION['orga_timezone']  = strStripTags($_POST['orga_timezone']);

        if ($_SESSION['orga_shortname'] === ''
        ||  $_SESSION['orga_longname']  === ''
        ||  $_SESSION['orga_email']     === '')
        {
            showNotice(
                $gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'),
                'installation.php?mode=4',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }
    }

    // initialize form data
    if (isset($_SESSION['user_last_name']))
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

    $userData = array($userLastName, $userFirstName, $userEmail, $userLogin);

    // create a page to enter all necessary data to create a administrator user
    $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=6');
    $form->addHeader('<script type="text/javascript" src="../libs/zxcvbn/dist/zxcvbn.js"></script>');
    $form->addHeader('
        <script type="text/javascript">
            $(function() {
                $("#admidio-password-strength-minimum").css("margin-left", "calc(" + $("#admidio-password-strength").css("width") + " / 4)");

                $("#user_password").keyup(function(e) {
                    var result = zxcvbn(e.target.value, ' . json_encode($userData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
                    var cssClasses = ["progress-bar-danger", "progress-bar-danger", "progress-bar-warning", "progress-bar-info", "progress-bar-success"];

                    var progressBar = $("#admidio-password-strength .progress-bar");
                    progressBar.attr("aria-valuenow", result.score * 25);
                    progressBar.css("width", result.score * 25 + "%");
                    progressBar.removeClass(cssClasses.join(" "));
                    progressBar.addClass(cssClasses[result.score]);
                });
            });
        </script>
    ');
    $form->setFormDescription($gL10n->get('INS_DATA_OF_ADMINISTRATOR_DESC'), $gL10n->get('INS_CREATE_ADMINISTRATOR'));
    $form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATA_OF_ADMINISTRATOR'));
    $form->addInput('user_last_name', $gL10n->get('SYS_LASTNAME'), $userLastName, array('maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addInput('user_first_name', $gL10n->get('SYS_FIRSTNAME'), $userFirstName, array('maxLength' => 50, 'property' => FIELD_REQUIRED));
    $form->addInput('user_email', $gL10n->get('SYS_EMAIL'), $userEmail, array('maxLength' => 255, 'property' => FIELD_REQUIRED));
    $form->addInput('user_login', $gL10n->get('SYS_USERNAME'), $userLogin, array('maxLength' => 35, 'property' => FIELD_REQUIRED));
    $form->addInput(
        'user_password', $gL10n->get('SYS_PASSWORD'), null,
        array('type' => 'password', 'property' => FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH, 'passwordStrength' => true, 'passwordUserData' => $userData, 'helpTextIdLabel' => 'PRO_PASSWORD_DESCRIPTION')
    );
    $form->addInput('user_password_confirm', $gL10n->get('SYS_CONFIRM_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH));
    $form->closeGroupBox();
    $form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?mode=4'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_CONTINUE_INSTALLATION'), array('icon' => 'layout/forward.png'));
    echo $form->show();
}
elseif ($getMode === 6)  // Creating configuration file
{
    $gLogger->info('INSTALLATION: Creating configuration file');

    if (isset($_POST['user_last_name']))
    {
        // Daten des Administrators in Sessionvariablen gefiltert speichern
        $_SESSION['user_last_name']        = strStripTags($_POST['user_last_name']);
        $_SESSION['user_first_name']       = strStripTags($_POST['user_first_name']);
        $_SESSION['user_email']            = strStripTags($_POST['user_email']);
        $_SESSION['user_login']            = strStripTags($_POST['user_login']);
        $_SESSION['user_password']         = $_POST['user_password'];
        $_SESSION['user_password_confirm'] = $_POST['user_password_confirm'];

        if ($_SESSION['user_last_name']  === ''
        ||  $_SESSION['user_first_name'] === ''
        ||  $_SESSION['user_email']      === ''
        ||  $_SESSION['user_login']      === ''
        ||  $_SESSION['user_password']   === '')
        {
            showNotice(
                $gL10n->get('INS_ADMINISTRATOR_DATA_NOT_COMPLETELY'),
                'installation.php?mode=5',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // username should only have valid chars
        if (!strValidCharacters($_SESSION['user_login'], 'noSpecialChar'))
        {
            showNotice(
                $gL10n->get('SYS_FIELD_INVALID_CHAR', $gL10n->get('SYS_USERNAME')),
                'installation.php?mode=5',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // email should only have valid chars
        $_SESSION['user_email'] = admStrToLower($_SESSION['user_email']);

        if (!strValidCharacters($_SESSION['user_email'], 'email'))
        {
            showNotice(
                $gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')),
                'installation.php?mode=5',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // Password min length is 8 chars
        if (strlen($_SESSION['user_password']) < PASSWORD_MIN_LENGTH)
        {
            showNotice(
                $gL10n->get('PRO_PASSWORD_LENGTH'),
                'installation.php?mode=5',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // password must be the same with password confirm
        if ($_SESSION['user_password'] !== $_SESSION['user_password_confirm'])
        {
            showNotice(
                $gL10n->get('INS_PASSWORDS_NOT_EQUAL'),
                'installation.php?mode=5',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // check if password is strong enough
        $userData = array(
            $_SESSION['user_last_name'],
            $_SESSION['user_first_name'],
            $_SESSION['user_email'],
            $_SESSION['user_login']
        );
        // Admin Password should have a minimum strength of 1
        if (PasswordHashing::passwordStrength($_SESSION['user_password'], $userData) < 1)
        {
            showNotice(
                $gL10n->get('PRO_PASSWORD_NOT_STRONG_ENOUGH'),
                'installation.php?mode=5',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }
    }

    // if config file exists than don't create a new one
    if (is_file($pathConfigFile))
    {
        admRedirect(ADMIDIO_URL . '/adm_program/installation/installation.php?mode=8');
        // => EXIT
    }

    // read configuration file structure
    $filename          = 'config.php';
    $configFileHandle  = fopen($filename, 'rb');
    $configFileContent = fread($configFileHandle, filesize($filename));
    fclose($configFileHandle);

    $port = 'null';
    if ($_SESSION['db_port'])
    {
        $port = $_SESSION['db_port'];
    }

    // detect root path
    $rootPath = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $rootPath = substr($rootPath, 0, strpos($rootPath, '/adm_program'));
    if (!strpos($rootPath, 'http://') && !strpos($rootPath, 'https://'))
    {
        if (HTTPS)
        {
            $rootPath = 'https://' . $rootPath;
        }
        else
        {
            $rootPath = 'http://' . $rootPath;
        }
    }

    // replace placeholders in configuration file structure with data of installation wizard
    $replaceArray = array(
        '%PREFIX%'       => $_SESSION['prefix'],
        '%DB_TYPE%'      => $_SESSION['db_type'],
        '%SERVER%'       => $_SESSION['db_server'],
        '\'%PORT%\''     => $port,
        '%USER%'         => $_SESSION['db_user'],
        '%PASSWORD%'     => $_SESSION['db_password'],
        '%DATABASE%'     => $_SESSION['db_database'],
        '%ROOT_PATH%'    => $rootPath,
        '%ORGANIZATION%' => $_SESSION['orga_shortname'],
        '%TIMEZONE%'     => $_SESSION['orga_timezone']
    );
    $configFileContent = str_replace(array_keys($replaceArray), array_values($replaceArray), $configFileContent);

    $_SESSION['config_file_content'] = $configFileContent;

    // now save new configuration file in Admidio folder if user has write access to this folder
    $configFileHandle = @fopen($pathConfigFile, 'ab');

    if ($configFileHandle)
    {
        // save config file in Admidio folder
        fwrite($configFileHandle, $configFileContent);
        fclose($configFileHandle);

        // start installation
        $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=8');
        $form->setFormDescription($gL10n->get('INS_DATA_FULLY_ENTERED'), $gL10n->get('INS_INSTALL_ADMIDIO'));
        $form->addSubmitButton('next_page', $gL10n->get('INS_INSTALL_ADMIDIO'), array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_WILL_BE_ESTABLISHED')));
        echo $form->show();
    }
    else
    {
        // if user doesn't has write access then create a page with a download link for the config file
        $form = new HtmlFormInstallation('installation-form', 'installation.php?mode=8');
        $form->setFormDescription($gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE_DESC', 'config.php', ADMIDIO_PATH . FOLDER_DATA, 'adm_my_files'), $gL10n->get('INS_CREATE_CONFIGURATION_FILE'));
        $form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?mode=5'));
        $form->addButton('download_config', $gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE'), array('icon' => 'layout/page_white_download.png', 'link' => 'installation.php?mode=7'));
        $form->addSubmitButton('next_page', $gL10n->get('INS_INSTALL_ADMIDIO'), array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_WILL_BE_ESTABLISHED')));
        echo $form->show();
    }
}
elseif ($getMode === 7) // Download configuration file
{
    $gLogger->info('INSTALLATION: Download configuration file');

    $filename   = 'config.php';
    $fileLength = strlen($_SESSION['config_file_content']);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: '.$fileLength);
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $_SESSION['config_file_content'];
    exit();
}
elseif ($getMode === 8) // Start installation
{
    $gLogger->info('INSTALLATION: Start installation');

    // Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
    if (!is_file($pathConfigFile))
    {
        showNotice(
            $gL10n->get('INS_CONFIGURATION_FILE_NOT_FOUND', 'config.php'),
            'installation.php?mode=6',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // set execution time to 5 minutes because we have a lot to do :)
    // there should be no error output because of safe mode
    @set_time_limit(300);

    // first check if session is filled (if installation was aborted then this is not filled)
    // if previous dialogs were filled then check if the settings are equal to config file
    if (isset($_SESSION['prefix'])
    &&    ($g_tbl_praefix  !== $_SESSION['prefix']
        || $gDbType        !== $_SESSION['db_type']
        || $g_adm_srv      !== $_SESSION['db_server']
        || $g_adm_port     !== $_SESSION['db_port']
        || $g_adm_usr      !== $_SESSION['db_user']
        || $g_adm_pw       !== $_SESSION['db_password']
        || $g_adm_db       !== $_SESSION['db_database']
        || $g_organization !== $_SESSION['orga_shortname']))
    {
        showNotice(
            $gL10n->get('INS_DATA_DO_NOT_MATCH', 'config.php'),
            'installation.php?mode=6',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // read data from sql script db.sql and execute all statements to the current database
    $sqlQueryResult = querySqlFile($db, 'db.sql');

    if (is_string($sqlQueryResult))
    {
        showNotice($sqlQueryResult, 'installation.php?mode=6', $gL10n->get('SYS_BACK'), 'layout/back.png');
        // => EXIT
    }

    // create default data

    // add system component to database
    $component = new ComponentUpdate($db);
    $component->setValue('com_type', 'SYSTEM');
    $component->setValue('com_name', 'Admidio Core');
    $component->setValue('com_name_intern', 'CORE');
    $component->setValue('com_version', ADMIDIO_VERSION);
    $component->setValue('com_beta', (string) ADMIDIO_VERSION_BETA);
    $component->setValue('com_update_step', $component->getMaxUpdateStep());
    $component->save();

    // create a hidden system user for internal use
    // all recordsets created by installation will get the create id of the system user
    $gCurrentUser = new TableAccess($db, TBL_USERS, 'usr');
    $gCurrentUser->setValue('usr_login_name', $gL10n->get('SYS_SYSTEM'));
    $gCurrentUser->setValue('usr_valid', '0');
    $gCurrentUser->setValue('usr_timestamp_create', DATETIME_NOW);
    $gCurrentUser->save(false); // no registered user -> UserIdCreate couldn't be filled
    $systemUserId = (int) $gCurrentUser->getValue('usr_id');

    // create all modules components
    $sql = 'INSERT INTO '.TBL_COMPONENTS.' (com_type, com_name, com_name_intern, com_version, com_beta)
            VALUES (\'MODULE\', \'ANN_ANNOUNCEMENTS\', \'ANNOUCEMENTS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'BAC_DATABASE_BACKUP\', \'BACKUP\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'SYS_CATEGORIES\', \'CATEGORIES\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'DAT_DATES\', \'DATES\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'DOW_DOWNLOADS\', \'DOWNLOADS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'GBO_GUESTBOOK\', \'GUESTBOOK\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'LNK_WEBLINKS\', \'LINKS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'LST_LISTS\', \'LISTS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'MEM_USER_MANAGEMENT\', \'MEMBERS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'SYS_MESSAGES\', \'MESSAGES\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'PHO_PHOTOS\', \'PHOTOS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'SYS_SETTINGS\', \'PREFERENCES\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'PRO_PROFILE\', \'PROFILE\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'SYS_REGISTRATION\', \'REGISTRATION\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'ROL_ROLE_ADMINISTRATION\', \'ROLES\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                 , (\'MODULE\', \'ROO_ROOM_MANAGEMENT\', \'ROOMS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')';
    $db->query($sql);

    // create organization independent categories
    $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
            VALUES (NULL, \'USF\', \'MASTER_DATA\', \'SYS_MASTER_DATA\', 0, 1, 1, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
    $categoryIdMasterData = $db->lastInsertId();

    $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
            VALUES (NULL, \'USF\', \'SOCIAL_NETWORKS\', \'SYS_SOCIAL_NETWORKS\', 0, 0, 2, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
    $categoryIdSocialNetworks = $db->lastInsertId();

    $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
            VALUES (NULL, \'ROL\', \'CONFIRMATION_OF_PARTICIPATION\', \'SYS_CONFIRMATION_OF_PARTICIPATION\', 1, 0, 1, 5, '.$systemUserId.',\''. DATETIME_NOW.'\')
                 , (NULL, \'USF\', \'ADDIDIONAL_DATA\', \'INS_ADDIDIONAL_DATA\', 0, 0, 0, 3, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);

    // create inventory categories
    $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
            VALUES (NULL, \'INF\', \'MASTER_DATA\', \'SYS_MASTER_DATA\', 0, 1, 1, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
    $db->query($sql);
    $categoryIdMasterInventory = $db->lastInsertId();

    // create roles rights
    $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS.' (ror_name_intern, ror_table)
            VALUES (\'folder_view\', \'adm_folders\')
                 , (\'folder_upload\', \'adm_folders\') ';
    $db->query($sql);

    // create profile fields of category master data
    $sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_value_list, usf_system, usf_disabled, usf_mandatory, usf_sequence, usf_usr_id_create, usf_timestamp_create)
            VALUES ('.$categoryIdMasterData.', \'TEXT\', \'LAST_NAME\', \'SYS_LASTNAME\', NULL, NULL, 1, 1, 1, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'TEXT\', \'FIRST_NAME\',\'SYS_FIRSTNAME\', NULL, NULL, 1, 1, 1, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'TEXT\', \'ADDRESS\',   \'SYS_ADDRESS\', NULL, NULL, 0, 0, 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'TEXT\', \'POSTCODE\',  \'SYS_POSTCODE\', NULL, NULL, 0, 0, 0, 4, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'TEXT\', \'CITY\',      \'SYS_CITY\', NULL, NULL, 0, 0, 0, 5, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'TEXT\', \'COUNTRY\',   \'SYS_COUNTRY\', NULL, NULL, 0, 0, 0, 6, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'PHONE\', \'PHONE\',     \'SYS_PHONE\', NULL, NULL, 0, 0, 0, 7, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'PHONE\', \'MOBILE\',    \'SYS_MOBILE\', NULL, NULL, 0, 0, 0, 8, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'PHONE\', \'FAX\',       \'SYS_FAX\', NULL, NULL, 0, 0, 0, 9, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'DATE\', \'BIRTHDAY\',  \'SYS_BIRTHDAY\', NULL, NULL, 0, 0, 0, 10, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'RADIO_BUTTON\', \'GENDER\', \'SYS_GENDER\', NULL, \'male.png|SYS_MALE
female.png|SYS_FEMALE\', 0, 0, 0, 11, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'EMAIL\', \'EMAIL\',    \'SYS_EMAIL\', NULL, NULL, 1, 0, 1, 12, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterData.', \'URL\',  \'WEBSITE\',   \'SYS_WEBSITE\', NULL, NULL, 0, 0, 0, 13, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\') ';
    $db->query($sql);

    // create profile fields of category social networks
    $sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_icon, usf_url, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
            VALUES ('.$categoryIdSocialNetworks.', \'TEXT\', \'AOL_INSTANT_MESSENGER\', \'INS_AOL_INSTANT_MESSENGER\', NULL, \'aim.png\', NULL, 0, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'FACEBOOK\',       \'INS_FACEBOOK\', \''.$gL10n->get('INS_FACEBOOK_DESC').'\', \'facebook.png\', \'https://www.facebook.com/#user_content#\', 0, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'GOOGLE_PLUS\',    \'INS_GOOGLE_PLUS\', \''.$gL10n->get('INS_GOOGLE_PLUS_DESC').'\', \'google_plus.png\', \'https://plus.google.com/#user_content#/posts\', 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'ICQ\',            \'INS_ICQ\', \''.$gL10n->get('INS_ICQ_DESC').'\', \'icq.png\', \'https://www.icq.com/people/#user_content#\', 0, 4, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'SKYPE\',          \'INS_SKYPE\', \''.$gL10n->get('INS_SKYPE_DESC').'\', \'skype.png\', NULL, 0, 5, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'TWITTER\',        \'INS_TWITTER\', \''.$gL10n->get('INS_TWITTER_DESC').'\', \'twitter.png\', \'https://twitter.com/#user_content#\', 0, 6, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'XING\',           \'INS_XING\', \''.$gL10n->get('INS_XING_DESC').'\', \'xing.png\', \'https://www.xing.com/profile/#user_content#\', 0, 7, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdSocialNetworks.', \'TEXT\', \'YAHOO_MESSENGER\',\'INS_YAHOO_MESSENGER\', NULL, \'yahoo.png\', NULL, 0, 8, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\') ';
    $db->query($sql);

    // create user relation types
    $sql = 'INSERT INTO '.TBL_USER_RELATION_TYPES.' (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
            VALUES (1, \''.$gL10n->get('INS_PARENT').'\', \''.$gL10n->get('INS_FATHER').'\', \''.$gL10n->get('INS_MOTHER').'\', null, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (2, \''.$gL10n->get('INS_CHILD').'\', \''.$gL10n->get('INS_SON').'\', \''.$gL10n->get('INS_DAUGHTER').'\', 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (3, \''.$gL10n->get('INS_SIBLING').'\', \''.$gL10n->get('INS_BROTHER').'\', \''.$gL10n->get('INS_SISTER').'\', 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (4, \''.$gL10n->get('INS_SPOUSE').'\', \''.$gL10n->get('INS_HUSBAND').'\', \''.$gL10n->get('INS_WIFE').'\', 4, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (5, \''.$gL10n->get('INS_COHABITANT').'\', \''.$gL10n->get('INS_COHABITANT_MALE').'\', \''.$gL10n->get('INS_COHABITANT_FEMALE').'\', 5, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (6, \''.$gL10n->get('INS_COMPANION').'\', \''.$gL10n->get('INS_BOYFRIEND').'\', \''.$gL10n->get('INS_GIRLFRIEND').'\', 6, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (7, \''.$gL10n->get('INS_SUPERIOR').'\', \''.$gL10n->get('INS_SUPERIOR_MALE').'\', \''.$gL10n->get('INS_SUPERIOR_FEMALE').'\', null, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , (8, \''.$gL10n->get('INS_SUBORDINATE').'\', \''.$gL10n->get('INS_SUBORDINATE_MALE').'\', \''.$gL10n->get('INS_SUBORDINATE_FEMALE').'\', 7, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')';
    $db->query($sql);

    $sql = 'UPDATE '.TBL_USER_RELATION_TYPES.' SET urt_id_inverse = 2
             WHERE urt_id = 1';
    $db->query($sql);

    $sql = 'UPDATE '.TBL_USER_RELATION_TYPES.' SET urt_id_inverse = 8
             WHERE urt_id = 7';
    $db->query($sql);

    // Inventoryfelder anlegen
    $sql = 'INSERT INTO '.TBL_INVENT_FIELDS.' (inf_cat_id, inf_type, inf_name_intern, inf_name, inf_description, inf_system, inf_disabled, inf_mandatory, inf_sequence, inf_usr_id_create, inf_timestamp_create)
            VALUES ('.$categoryIdMasterInventory.', \'TEXT\', \'ITEM_NAME\', \'SYS_ITEMNAME\', NULL, 1, 1, 1, 1, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterInventory.', \'NUMBER\', \'ROOM_ID\', \'SYS_ROOM\', NULL, 1, 1, 1, 2, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\')
                 , ('.$categoryIdMasterInventory.', \'NUMBER\', \'PRICE\',   \'SYS_QUANTITY\', NULL, 0, 0, 0, 3, '.$gCurrentUser->getValue('usr_id').',\''. DATETIME_NOW.'\') ';
    $db->query($sql);

    disableSoundexSearchIfPgsql($db);

    // create new organization
    $gCurrentOrganization = new Organization($db, $_SESSION['orga_shortname']);
    $gCurrentOrganization->setValue('org_longname', $_SESSION['orga_longname']);
    $gCurrentOrganization->setValue('org_shortname', $_SESSION['orga_shortname']);
    $gCurrentOrganization->setValue('org_homepage', $_SERVER['HTTP_HOST']);
    $gCurrentOrganization->save();

    // create administrator and assign roles
    $administrator = new User($db);
    $administrator->setValue('usr_login_name', $_SESSION['user_login']);
    $administrator->setPassword($_SESSION['user_password']);
    $administrator->setValue('usr_usr_id_create', $gCurrentUser->getValue('usr_id'));
    $administrator->setValue('usr_timestamp_create', DATETIME_NOW);
    $administrator->save(false); // no registered user -> UserIdCreate couldn't be filled

    // write all preferences from preferences.php in table adm_preferences
    require_once('db_scripts/preferences.php');

    // set some specific preferences whose values came from user input of the installation wizard
    $defaultOrgPreferences['email_administrator'] = $_SESSION['orga_email'];
    $defaultOrgPreferences['system_language']     = $language;

    // calculate the best cost value for your server performance
    $benchmarkResults = PasswordHashing::costBenchmark(0.35, 'password', $gPasswordHashAlgorithm);
    $defaultOrgPreferences['system_hashing_cost'] = $benchmarkResults['cost'];

    // create all necessary data for this organization
    $gCurrentOrganization->setPreferences($defaultOrgPreferences, false);
    $gCurrentOrganization->createBasicData((int) $administrator->getValue('usr_id'));

    // create default room for room module in database
    $sql = 'INSERT INTO '.TBL_ROOMS.' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                   VALUES (\''.$gL10n->get('INS_CONFERENCE_ROOM').'\', \''.$gL10n->get('INS_DESCRIPTION_CONFERENCE_ROOM').'\',
                           15, '.$gCurrentUser->getValue('usr_id').',\''.DATETIME_NOW.'\')';
    $db->query($sql);

    // first create a user object "current user" with administrator rights
    // because administrator is allowed to edit firstname and lastname
    $gCurrentUser = new User($db, $gProfileFields, $administrator->getValue('usr_id'));
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
    if (!is_writable(ADMIDIO_PATH . FOLDER_DATA))
    {
        $text = $text.'
            <div class="alert alert-warning alert-small" role="alert">
                <span class="glyphicon glyphicon-warning-sign"></span>
                '.$gL10n->get('INS_FOLDER_NOT_WRITABLE', 'adm_my_files').'
            </div>';
    }

    $gLogger->info('INSTALLATION: Installation successfully complete');

    // show dialog with success notification
    $form = new HtmlFormInstallation('installation-form', ADMIDIO_HOMEPAGE.'donate.php');
    $form->setFormDescription($text, '<div class="alert alert-success form-alert"><span class="glyphicon glyphicon-ok"></span>
                                      <strong>'.$gL10n->get('INS_INSTALLATION_WAS_SUCCESSFUL').'</strong></div>');
    $form->openButtonGroup();
    $form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'layout/money.png'));
    $form->addButton('main_page', $gL10n->get('SYS_LATER'), array('icon' => 'layout/application_view_list.png', 'link' => '../index.php'));
    $form->closeButtonGroup();
    echo $form->show();
}
