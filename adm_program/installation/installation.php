<?php
/**
 ***********************************************************************************************
 * Installation and configuration of Admidio database and config file
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * step = welcome              : (Default) Welcome to installation
 *        connect_database     : Enter database access information
 *        create_organization  : Creating organization
 *        create_administrator : Creating administrator
 *        create_config        : Creating configuration file
 *        download_config      : Download configuration file
 *        start_installation   : Start installation
 ***********************************************************************************************
 */

$rootPath = dirname(dirname(__DIR__));

// if config file already exists then load file with their variables
$configPath = $rootPath . '/adm_my_files/config.php';
if (is_file($configPath))
{
    require_once($configPath);
}
elseif (is_file($rootPath . '/config.php'))
{
    exit('<div style="color: #cc0000;">Old v1.x or v2.x Config-File detected! Please update first to the latest v3.3 Version!</div>');
}
else
{
    $g_organization = '';
    $g_root_path    = '../..';
}

require_once($rootPath . '/adm_program/system/bootstrap.php');
require_once(ADMIDIO_PATH . '/adm_program/installation/install_functions.php');

$availableSteps = array('welcome', 'connect_database', 'create_organization', 'create_administrator', 'create_config', 'download_config', 'start_installation');

if (empty($_GET['step']))
{
    $step = $availableSteps[0];
}
else
{
    $step = $_GET['step'];
}

if (!in_array($step, $availableSteps, true))
{
    admRedirect(safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'welcome')));
    // => EXIT
}

// start PHP session
try
{
    Session::start('ADMIDIO_INSTALLATION');
}
catch (\RuntimeException $exception)
{
    // TODO
}

define('THEME_URL', 'layout');

// create language and language data object to handle translations
$language = '';
if (isset($_SESSION['language']))
{
    $language = $_SESSION['language'];
}

$gLanguageData = new LanguageData($language);
$gL10n = new Language($gLanguageData);

$language = $gL10n->getLanguage();

// if config file exists then connect to database
if (is_file($configPath))
{
    try
    {
        $db = Database::createDatabaseInstance();
    }
    catch (AdmException $e)
    {
        showNotice(
            $gL10n->get('SYS_DATABASE_NO_LOGIN_CONFIG_FILE', array($e->getText())),
            safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'connect_database')),
            $gL10n->get('INS_CONTINUE_INSTALLATION'),
            'layout/forward.png'
        );
        // => EXIT
    }

    // now check if a valid installation exists
    $sql = 'SELECT org_id FROM '.TBL_ORGANIZATIONS;
    $pdoStatement = $db->queryPrepared($sql, array(), false);

    // Check the query for results in case installation is running at this time and the config file is already created but database is not installed so far
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
    if ($step === 'welcome')
    {
        // save database parameters of config.php in session variables
        $_SESSION['db_engine']    = DB_ENGINE;
        $_SESSION['db_host']      = DB_HOST;
        $_SESSION['db_port']      = DB_PORT;
        $_SESSION['db_name']      = DB_NAME;
        $_SESSION['db_username']  = DB_USERNAME;
        $_SESSION['db_password']  = DB_PASSWORD;
        $_SESSION['table_prefix'] = TABLE_PREFIX;

        admRedirect(safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'create_organization')));
        // => EXIT
    }
}

switch ($step)
{
    case 'welcome': // (Default) Welcome to installation
        $gLogger->info('INSTALLATION: Welcome to installation');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/welcome.php');
        break;

    case 'connect_database': // Enter database access information
        $gLogger->info('INSTALLATION: Enter database access information');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/connect_database.php');
        break;

    case 'create_organization': // Creating organization
        $gLogger->info('INSTALLATION: Creating organisation');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/create_organization.php');
        break;

    case 'create_administrator': // Creating administrator
        $gLogger->info('INSTALLATION: Creating administrator');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/create_administrator.php');
        break;

    case 'create_config': // Creating configuration file
        $gLogger->info('INSTALLATION: Creating configuration file');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/create_config.php');
        break;

    case 'download_config': // Download configuration file
        $gLogger->info('INSTALLATION: Download configuration file');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/download_config.php');
        break;

    case 'start_installation': // Start installation
        $gLogger->info('INSTALLATION: Start installation');
        require_once(ADMIDIO_PATH . '/adm_program/installation/install_steps/start_installation.php');
        break;
}
