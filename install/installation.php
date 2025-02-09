<?php

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Session\Entity\Session;
use Admidio\UI\View\Installation;
use Admidio\Infrastructure\Entity\Entity;

/**
 ***********************************************************************************************
 * Installation and configuration of Admidio database and config file
 *
 * @copyright The Admidio Team
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
 *        installation_successful : Installation successful finished
 ***********************************************************************************************
 */
try {
    /**
     * Get the url of the Admidio installation with all subdirectories, a forwarded host
     * and a port. e.g. https://www.admidio.org/playground
     * @param bool $checkForwardedHost If set to true the script will check if a forwarded host is set and add him to the url
     * @return string The url of the Admidio installation
     */
    function getAdmidioUrl(bool $checkForwardedHost = true): string
    {
        $ssl = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'on') === 0;
        $sp = strtolower($_SERVER['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . ($ssl ? 's' : '');
        $port = (int)$_SERVER['SERVER_PORT'];
        $port = ((!$ssl && $port === 80) || ($ssl && $port === 443)) ? '' : ':' . $port;
        $host = ($checkForwardedHost && isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : ($_SERVER['HTTP_HOST'] ?? null);
        $host = $host ?? $_SERVER['SERVER_NAME'] . $port;
        $fullUrl = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
        return substr($fullUrl, 0, strpos($fullUrl, 'install/') - 1);
    }

    $rootPath = dirname(__DIR__);

    // if config file already exists then load file with their variables
    $configPath = $rootPath . '/adm_my_files/config.php';
    $g_organization = '';

    if (is_file($configPath)) {
        require_once($configPath);
    } elseif (is_file($rootPath . '/config.php')) {
        exit('<div style="color: #cc0000;">Old Admidio version 1.x or 2.x config file detected! Please update first to the latest version 3 of Admidio and after that you can perform an update to version 4!<br /><br />Please view <a href="https://www.admidio.org/dokuwiki/doku.php?id=de:2.0:update_von_2.x_auf_3.x">our documentation</a>.</div>');
    } else {
        $g_root_path = getAdmidioUrl();
    }

    require_once($rootPath . '/adm_program/system/bootstrap/bootstrap.php');

    $availableSteps = array('welcome', 'connect_database', 'create_organization', 'create_administrator', 'create_config', 'download_config', 'start_installation', 'installation_successful');

    if (isset($_GET['mode']) && $_GET['mode'] == 'check') {
        $mode = 'check';
    } else {
        $mode = 'html';
    }
    if (empty($_GET['step'])) {
        $step = $availableSteps[0];
    } else {
        $step = $_GET['step'];
    }

    if (!in_array($step, $availableSteps, true)) {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'welcome')));
        // => EXIT
    }

    // start PHP session
    try {
        Session::start('ADMIDIO_INSTALLATION');
    } catch (RuntimeException $exception) {
        // TODO
    }

    define('THEME_URL', 'layout');

    // create language and language data object to handle translations
    $language = '';
    if (isset($_SESSION['language'])) {
        $language = $_SESSION['language'];
    }

    $gL10n = new Language($language);

    $language = $gL10n->getLanguage();

    /* Disable logging changes to the database. This will not be reverted,
     *  i.e. during installation / setup no logs are written. The next user 
     * call will use the default value of true and properly log changes...
     */
    Entity::setLoggingEnabled(false);

    // check if adm_my_files has "write" privileges and check some sub folders of adm_my_files
    \Admidio\InstallationUpdate\Service\Installation::checkFolderPermissions();

    // if config file exists then connect to database
    if (is_file($configPath) && $step !== 'installation_successful') {
        $db = Database::createDatabaseInstance();
        global $gDb;
        $gDb = $db; // Make the database object globally available to all classes, just like after installation

        if (!is_object($db)) {
            $page = new Installation('adm_installation_message', $gL10n->get('INS_INSTALLATION'));
            $page->showMessage(
                'error',
                $gL10n->get('SYS_NOTE'),
                $gL10n->get('SYS_DATABASE_NO_LOGIN_CONFIG_FILE'),
                $gL10n->get('SYS_OVERVIEW'),
                'bi-house-door-fill',
                '../index.php'
            );
            // => EXIT
        }

        // now check if a valid installation exists
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $pdoStatement = $db->queryPrepared($sql, array(), false);

        // Check the query for results in case installation is running at this time and the config file is already created but database is not installed so far
        if ($pdoStatement !== false && $pdoStatement->rowCount() > 0) {
            // valid installation exists -> exit installation
            $page = new Installation('adm_installation_message', $gL10n->get('INS_INSTALLATION'));
            $page->showMessage(
                'error',
                $gL10n->get('SYS_NOTE'),
                $gL10n->get('INS_INSTALLATION_EXISTS'),
                $gL10n->get('SYS_OVERVIEW'),
                'bi-house-door-fill',
                '../index.php'
            );
            // => EXIT
        }

        // if config exists then take parameters out of this file
        if ($step === 'welcome') {
            // save database parameters of config.php in session variables
            $_SESSION['db_engine'] = DB_ENGINE;
            $_SESSION['db_host'] = DB_HOST;
            $_SESSION['db_port'] = DB_PORT;
            $_SESSION['db_name'] = DB_NAME;
            $_SESSION['db_username'] = DB_USERNAME;
            $_SESSION['db_password'] = DB_PASSWORD;
            $_SESSION['table_prefix'] = TABLE_PREFIX;

            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization')));
            // => EXIT
        }
    }

    switch ($step) {
        case 'welcome': // (Default) Welcome to installation
            $gLogger->info('INSTALLATION: Welcome to installation');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/welcome.php');
            break;

        case 'connect_database': // Enter database access information
            $gLogger->info('INSTALLATION: Enter database access information');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/connect_database.php');
            break;

        case 'create_organization': // Creating organization
            $gLogger->info('INSTALLATION: Creating organisation');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/create_organization.php');
            break;

        case 'create_administrator': // Creating administrator
            $gLogger->info('INSTALLATION: Creating administrator');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/create_administrator.php');
            break;

        case 'create_config': // Creating configuration file
            $gLogger->info('INSTALLATION: Creating configuration file');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/create_config.php');
            break;

        case 'download_config': // Download configuration file
            $gLogger->info('INSTALLATION: Download configuration file');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/download_config.php');
            break;

        case 'start_installation': // Start installation
            $gLogger->info('INSTALLATION: Start installation');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/start_installation.php');
            break;

        case 'installation_successful': // Start installation
            $gLogger->info('INSTALLATION: Installation_successful');
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_steps/installation_successful.php');
            break;
    }
} catch (Throwable $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
