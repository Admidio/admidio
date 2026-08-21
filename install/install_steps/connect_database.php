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

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;

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
        $dbEngine = $_SESSION['db_type'];
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
    $page = new InstallationPresenter('adm_installation_connect_database', $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT)));
    $page->addTemplateFile('installation.tpl');
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_ENTER_LOGIN_TO_DATABASE'));
    $page->assignSmartyVariable('text', $gL10n->get('INS_DATABASE_LOGIN_DESC'));

    $form = new FormPresenter(
        'adm_installation_connect_database_form',
        'installation.connect-database.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'connect_database', 'mode' => 'check')),
        $page
    );
    $form->addSelectBoxFromXml(
        'adm_db_type',
        $gL10n->get('INS_DATABASE_SYSTEM'),
        ADMIDIO_PATH . FOLDER_SYSTEM . '/databases.xml',
        'identifier',
        'name',
        array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $dbEngine)
    );
    $form->addInput(
        'adm_db_host',
        $gL10n->get('SYS_HOST'),
        $dbHost,
        array('pattern' => $hostRegex, 'maxLength' => 64, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'INS_DATABASE_HOST_INFO')
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
        array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_db_username',
        $gL10n->get('SYS_USERNAME'),
        $dbUsername,
        array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => FormPresenter::FIELD_REQUIRED)
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
        array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 10, 'property' => FormPresenter::FIELD_REQUIRED, 'class' => 'form-control-small')
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

    // Store database access data filtered in session variables
    $_SESSION['db_type']      = $formValues['adm_db_type'];
    $_SESSION['db_host']      = $formValues['adm_db_host'];
    $_SESSION['db_port']      = InstallationConfig::normalizePort($formValues['adm_db_port']);
    $_SESSION['db_name']      = $formValues['adm_db_name'];
    $_SESSION['db_username']  = $formValues['adm_db_username'];
    $_SESSION['db_password']  = $formValues['adm_db_password'];
    $_SESSION['table_prefix'] = $formValues['adm_table_prefix'];

    // check the entered values with the same rules that a headless installation uses
    Installation::validateDatabaseInput(
        $_SESSION['db_type'],
        $_SESSION['db_host'],
        $_SESSION['db_port'],
        $_SESSION['db_name'],
        $_SESSION['db_username'],
        $_SESSION['table_prefix']
    );

    // a weak database password is not an error of the installation, but it should be logged
    $zxcvbnScore = PasswordUtils::passwordStrength($_SESSION['db_password']);
    if ($zxcvbnScore <= 2) {
        $gLogger->warning('Database password is weak! (zxcvbn lib)', array('score' => $zxcvbnScore));
    }

    // for security reasons only check database connection if no config file exists
    if (!is_file($configPath)) {
        $gDebug = true;
        Installation::connectDatabase(InstallationConfig::fromInstallerSession($_SESSION, ADMIDIO_URL));
        $gDebug = false;
    }

    echo json_encode(array(
        'status' => 'success',
        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization'))));
    exit();
}
