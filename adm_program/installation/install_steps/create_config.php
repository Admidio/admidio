<?php
/**
 ***********************************************************************************************
 * Installation step: create_config
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_config.php')
{
    exit('This page may not be called directly!');
}

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
            'installation.php?step=create_administrator',
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
            'installation.php?step=create_administrator',
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
            'installation.php?step=create_administrator',
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
            'installation.php?step=create_administrator',
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
            'installation.php?step=create_administrator',
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
            'installation.php?step=create_administrator',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }
}

// if config file exists than don't create a new one
if (is_file($pathConfigFile))
{
    admRedirect(ADMIDIO_URL . '/adm_program/installation/installation.php?step=start_installation');
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
$replaceArray = array(
    '%PREFIX%'       => $_SESSION['prefix'],
    '%DB_TYPE%'      => $_SESSION['db_type'],
    '%SERVER%'       => $_SESSION['db_host'],
    '\'%PORT%\''     => $port,
    '%DATABASE%'     => $_SESSION['db_database'],
    '%USER%'         => $_SESSION['db_user'],
    '%PASSWORD%'     => $_SESSION['db_password'],
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
    $form = new HtmlFormInstallation('installation-form', 'installation.php?step=start_installation');
    $form->setFormDescription($gL10n->get('INS_DATA_FULLY_ENTERED'), $gL10n->get('INS_INSTALL_ADMIDIO'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_INSTALL_ADMIDIO'), array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_WILL_BE_ESTABLISHED')));
    echo $form->show();
}
else
{
    // if user doesn't has write access then create a page with a download link for the config file
    $form = new HtmlFormInstallation('installation-form', 'installation.php?step=start_installation');
    $form->setFormDescription($gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE_DESC', 'config.php', ADMIDIO_PATH . FOLDER_DATA, 'adm_my_files'), $gL10n->get('INS_CREATE_CONFIGURATION_FILE'));
    $form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?step=create_administrator'));
    $form->addButton('download_config', $gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE'), array('icon' => 'layout/page_white_download.png', 'link' => 'installation.php?step=download_config'));
    $form->addSubmitButton('next_page', $gL10n->get('INS_INSTALL_ADMIDIO'), array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_WILL_BE_ESTABLISHED')));
    echo $form->show();
}
