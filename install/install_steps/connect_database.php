<?php
/**
 ***********************************************************************************************
 * Installation step: connect_database
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\Form;
use Admidio\UI\View\Installation;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'connect_database.php') {
    exit('This page may not be called directly!');
}

if ($mode === 'html') {
    // HTML-Form Regex-Patterns
    $hostnameRegex = '(?:[a-z0-9-]{1,63}\.)*(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z]{2,63})?';
    $ipv4Regex = '(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)';
    $ipv6Regex = '(?:[a-fA-F0-9]{1,4}:){7}[a-fA-F0-9]{1,4}';
    $hostRegex = '^(' . $hostnameRegex . '|' . $ipv4Regex . '|' . $ipv6Regex . ')$';
    $sqlIdentifiersRegex = '^[a-zA-Z0-9_$@-]+$';

    // initialize form data
    if (isset($_SESSION['db_host'])) {
        $dbEngine = $_SESSION['db_engine'];
        $dbHost = $_SESSION['db_host'];
        $dbPort = $_SESSION['db_port'];
        $dbName = $_SESSION['db_name'];
        $dbUsername = $_SESSION['db_username'];
        $tablePrefix = $_SESSION['table_prefix'];
    } else {
        $dbEngine = '';
        $dbHost = '';
        $dbPort = '';
        $dbName = '';
        $dbUsername = '';
        $tablePrefix = 'adm';
    }

    // create a page to enter all necessary database connection information
    $page = new Installation('adm_installation_connect_database', $gL10n->get('INS_INSTALLATION'));
    $page->addTemplateFile('installation.tpl');
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_ENTER_LOGIN_TO_DATABASE'));
    $page->assignSmartyVariable('text', $gL10n->get('INS_DATABASE_LOGIN_DESC'));

    $form = new Form(
        'adm_installation_connect_database_form',
        'installation.connect-database.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'connect_database', 'mode' => 'check')),
        $page
    );
    $form->addSelectBoxFromXml(
        'adm_db_engine',
        $gL10n->get('INS_DATABASE_SYSTEM'),
        ADMIDIO_PATH . '/adm_program/system/databases.xml',
        'identifier',
        'name',
        array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $dbEngine)
    );
    $form->addInput(
        'adm_db_host',
        $gL10n->get('SYS_HOST'),
        $dbHost,
        array('pattern' => $hostRegex, 'maxLength' => 64, 'property' => Form::FIELD_REQUIRED, 'helpTextId' => 'INS_DATABASE_HOST_INFO')
    );
    $form->addInput(
        'adm_db_port',
        $gL10n->get('SYS_PORT'),
        (string)$dbPort,
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 65535, 'step' => 1, 'helpTextId' => 'INS_DATABASE_PORT_INFO')
    );
    $form->addInput(
        'adm_db_name',
        $gL10n->get('SYS_DATABASE'),
        $dbName,
        array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_db_username',
        $gL10n->get('SYS_USERNAME'),
        $dbUsername,
        array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_db_password',
        $gL10n->get('SYS_PASSWORD'),
        '',
        array('type' => 'password')
    );
    $form->addInput(
        'adm_table_prefix',
        $gL10n->get('INS_TABLE_PREFIX'),
        $tablePrefix,
        array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 10, 'property' => Form::FIELD_REQUIRED, 'class' => 'form-control-small')
    );
    $form->addButton(
        'adm_previous_page',
        $gL10n->get('SYS_BACK'),
        array('icon' => 'bi-arrow-left-circle-fill', 'class' => 'admidio-margin-bottom',
            'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'welcome')))
    );
    $form->addSubmitButton('adm_next_page', $gL10n->get('INS_SET_ORGANIZATION'), array('icon' => 'bi-arrow-right-circle-fill', 'class' => 'float-end'));

    $form->addToHtmlPage();
    $_SESSION['installationConnectDatabaseForm'] = $form;
    $page->show();
} elseif ($mode === 'check') {
    // check form field input and sanitized it from malicious content
    if (isset($_SESSION['installationConnectDatabaseForm'])) {
        $formValues = $_SESSION['installationConnectDatabaseForm']->validate($_POST);
    } else {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // PHP-Check Regex-Patterns
    $sqlIdentifiersRegex = '/^[a-zA-Z0-9_$@-]+$/';

    // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
    $_SESSION['db_engine']    = $formValues['adm_db_engine'];
    $_SESSION['db_host']      = $formValues['adm_db_host'];
    $_SESSION['db_port']      = $formValues['adm_db_port'];
    $_SESSION['db_name']      = $formValues['adm_db_name'];
    $_SESSION['db_username']  = $formValues['adm_db_username'];
    $_SESSION['db_password']  = $formValues['adm_db_password'];
    $_SESSION['table_prefix'] = $formValues['adm_table_prefix'];

    // Check DB-type
    if (!in_array($_SESSION['db_engine'], array(Database::PDO_ENGINE_MYSQL, Database::PDO_ENGINE_PGSQL), true)) {
        throw new Exception('INS_DATABASE_TYPE_INVALID');
    }

    // Check host
    // TODO: unix_server is currently not supported
    if (filter_var($_SESSION['db_host'], FILTER_VALIDATE_DOMAIN) === false && filter_var($_SESSION['db_host'], FILTER_VALIDATE_IP) === false) {
        throw new Exception('INS_HOST_INVALID');
    }

    // Check port
    if ($_SESSION['db_port'] === '' || $_SESSION['db_port'] === null) {
        $_SESSION['db_port'] = null;
    } elseif (is_numeric($_SESSION['db_port']) && (int) $_SESSION['db_port'] > 0 && (int) $_SESSION['db_port'] <= 65535) {
        $_SESSION['db_port'] = (int) $_SESSION['db_port'];
    } else {
        throw new Exception('INS_DATABASE_PORT_INVALID');
    }

    // Check database
    if (strlen($_SESSION['db_name']) > 64 || preg_match($sqlIdentifiersRegex, $_SESSION['db_name']) !== 1) {
        throw new Exception($gL10n->get('SYS_FIELD_INVALID_INPUT', array('SYS_DATABASE')));
    }

    // Check user
    if (strlen($_SESSION['db_username']) > 64 || preg_match($sqlIdentifiersRegex, $_SESSION['db_username']) !== 1) {
        throw new Exception($gL10n->get('SYS_FIELD_INVALID_INPUT', array('SYS_USERNAME')));
    }

    // Check password
    $zxcvbnScore = PasswordUtils::passwordStrength($_SESSION['db_password']);
    if ($zxcvbnScore <= 2) {
        $gLogger->warning('Database password is weak! (zxcvbn lib)', array('score' => $zxcvbnScore));
    }

    // Check prefix
    if (strlen($_SESSION['table_prefix']) > 10 || preg_match($sqlIdentifiersRegex, $_SESSION['table_prefix']) !== 1) {
        throw new Exception($gL10n->get('SYS_FIELD_INVALID_INPUT', array('INS_TABLE_PREFIX')));
    }

    // for security reasons only check database connection if no config file exists
    if (!is_file($configPath)) {
        // check database connections
        try {
            $gDebug = true;
            $db = new Database($_SESSION['db_engine'], $_SESSION['db_host'], $_SESSION['db_port'], $_SESSION['db_name'], $_SESSION['db_username'], $_SESSION['db_password']);
            $db->checkWriteAccess();
            $gDebug = false;
        } catch (Exception $e) {
            throw new Exception($gL10n->get('SYS_DATABASE_NO_LOGIN', array($e->getMessage())));
        }

        // check database version
        $message = \Admidio\InstallationUpdate\Service\Installation::checkDatabaseVersion($db);
        if ($message !== '') {
            throw new Exception($message);
        }

        // now check if a valid installation exists.
        $sql = 'SELECT org_id FROM ' . $_SESSION['table_prefix'] . '_organizations';
        $pdoStatement = $db->queryPrepared($sql, array(), false);

        if ($pdoStatement !== false && $pdoStatement->rowCount() > 0) {
            // valid installation exists -> exit installation
            throw new Exception('INS_INSTALLATION_EXISTS');
        }
    }

    echo json_encode(array(
        'status' => 'success',
        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization'))));
    exit();
}
