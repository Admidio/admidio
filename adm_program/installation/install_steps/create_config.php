<?php
/**
 ***********************************************************************************************
 * Installation step: create_config
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_config.php') {
    exit('This page may not be called directly!');
}

if (isset($_POST['user_last_name'])) {
    // Daten des Administrators in Sessionvariablen gefiltert speichern
    $_SESSION['user_last_name']        = StringUtils::strStripTags($_POST['user_last_name']);
    $_SESSION['user_first_name']       = StringUtils::strStripTags($_POST['user_first_name']);
    $_SESSION['user_email']            = StringUtils::strStripTags($_POST['user_email']);
    $_SESSION['user_login']            = StringUtils::strStripTags($_POST['user_login']);
    $_SESSION['user_password']         = $_POST['user_password'];
    $_SESSION['user_password_confirm'] = $_POST['user_password_confirm'];

    if ($_SESSION['user_last_name']  === ''
    ||  $_SESSION['user_first_name'] === ''
    ||  $_SESSION['user_email']      === ''
    ||  $_SESSION['user_login']      === ''
    ||  $_SESSION['user_password']   === '') {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('INS_ADMINISTRATOR_DATA_NOT_COMPLETELY'),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))
        );
        // => EXIT
    }

    // username should only have valid chars
    if (!StringUtils::strValidCharacters($_SESSION['user_login'], 'noSpecialChar')) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('SYS_FIELD_INVALID_CHAR', array($gL10n->get('SYS_USERNAME'))),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))
        );
        // => EXIT
    }

    if (!StringUtils::strValidCharacters($_SESSION['user_email'], 'email')) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))
        );
        // => EXIT
    }

    // Password min length is 8 chars
    if (strlen($_SESSION['user_password']) < PASSWORD_MIN_LENGTH) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('PRO_PASSWORD_LENGTH'),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))
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
    if (PasswordUtils::passwordStrength($_SESSION['user_password'], $userData) < 1) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('PRO_PASSWORD_NOT_STRONG_ENOUGH'),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))
        );
        // => EXIT
    }

    // password must be the same with password confirm
    if ($_SESSION['user_password'] !== $_SESSION['user_password_confirm']) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('SYS_PASSWORDS_NOT_EQUAL'),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))
        );
        // => EXIT
    }
}

// if config file exists than don't create a new one
if (is_file($configPath)) {
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation')));
    // => EXIT
}

// read configuration file structure
$filename          = 'config.php';
$configFileHandle  = fopen($filename, 'rb');
$configFileContent = fread($configFileHandle, filesize($filename));
fclose($configFileHandle);

$port = 'null';
if ($_SESSION['db_port']) {
    $port = $_SESSION['db_port'];
}

// replace placeholders in configuration file structure with data of installation wizard
$replaces = array(
    '%DB_ENGINE%'    => $_SESSION['db_engine'],
    '%DB_HOST%'      => $_SESSION['db_host'],
    '\'%DB_PORT%\''  => $port, // String -> Int
    '%DB_NAME%'      => $_SESSION['db_name'],
    '%DB_USERNAME%'  => $_SESSION['db_username'],
    '%DB_PASSWORD%'  => $_SESSION['db_password'],
    '%TABLE_PREFIX%' => $_SESSION['table_prefix'],
    '%ROOT_PATH%'    => $g_root_path,
    '%TIMEZONE%'     => $_SESSION['orga_timezone']
);
$configFileContent = StringUtils::strMultiReplace($configFileContent, $replaces);

$_SESSION['config_file_content'] = $configFileContent;

$page = new HtmlPageInstallation('admidio-installation-create-config');
$page->addTemplateFile('installation.tpl');
$page->addJavascript('
    $("#next_page").on("click", function() {
        $(this).prop("disabled", true);
        $(this).html("<i class=\"fas fa-sync fa-spin\"></i> ' . $gL10n->get('INS_DATABASE_WILL_BE_ESTABLISHED') . '");
        $("#installation-form").submit();
    });', true);

// now save new configuration file in Admidio folder if user has write access to this folder
$configFileHandle = @fopen($configPath, 'ab');

if ($configFileHandle) {
    // save config file in Admidio folder
    fwrite($configFileHandle, $configFileContent);
    fclose($configFileHandle);

    // start installation
    $page->assign('subHeadline', $gL10n->get('INS_INSTALL_ADMIDIO'));
    $page->assign('text', $gL10n->get('INS_DATA_FULLY_ENTERED'));

    $form = new HtmlForm('installation-form', SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'start_installation')));
    $form->addButton(
        'next_page',
        $gL10n->get('INS_INSTALL_ADMIDIO'),
        array('icon' => 'fa-sync', 'class' => ' btn-primary admidio-margin-bottom')
    );
} else {
    // if user doesn't has write access then create a page with a download link for the config file
    $page->assign('subHeadline', $gL10n->get('INS_CREATE_CONFIGURATION_FILE'));
    $page->assign('text', $gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE_DESC', array('config.php', ADMIDIO_URL . FOLDER_DATA, 'adm_my_files')));

    $form = new HtmlForm('installation-form', SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'start_installation')));
    $form->addButton(
        'previous_page',
        $gL10n->get('SYS_BACK'),
        array('icon' => 'fa-arrow-circle-left', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'create_administrator')))
    );
    $form->addButton(
        'download_config',
        $gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE'),
        array('icon' => 'fa-download', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'download_config')))
    );
    $form->addButton(
        'next_page',
        $gL10n->get('INS_INSTALL_ADMIDIO'),
        array('icon' => 'fa-sync', 'class' => ' btn-primary admidio-margin-bottom')
    );
}

$page->addHtml($form->show());
$page->show();
