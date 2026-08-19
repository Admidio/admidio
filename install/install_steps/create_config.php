<?php
/**
 ***********************************************************************************************
 * Installation step: create_config
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_config.php') {
    exit('This page may not be called directly!');
}

// if config file exists than don't create a new one
if (is_file($configPath)) {
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation')));
    // => EXIT
}

// create the content of the configuration file out of the data of the installation wizard
$installationConfig = InstallationConfig::fromInstallerSession($_SESSION, $g_root_path);
$_SESSION['config_file_content'] = Installation::generateConfigFileContent($installationConfig);

$page = new InstallationPresenter('adm_installation_create_config', $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT)));
$page->addTemplateFile('installation.tpl');

// now save new configuration file in Admidio folder if user has write access to this folder
$configFileWritten = true;

try {
    Installation::writeConfigFile($installationConfig, $configPath);
} catch (RuntimeException $exception) {
    $configFileWritten = false;
}

if ($configFileWritten) {
    // start installation
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_INSTALL_ADMIDIO'));
    $page->assignSmartyVariable('text', $gL10n->get('INS_DATA_FULLY_ENTERED'));
    $page->assignSmartyVariable('urlInstallation', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation')));

    $form = new FormPresenter(
        'adm_installation_install_admidio_form',
        'installation.install-admidio.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation')),
        $page
    );
    $form->addSubmitButton(
        'adm_next_page',
        $gL10n->get('INS_INSTALL_ADMIDIO'),
        array('icon' => 'bi-arrow-repeat', 'class' => ' btn-primary admidio-margin-bottom')
    );
} else {
    // if user doesn't has write access then create a page with a download link for the config file
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_CREATE_CONFIGURATION_FILE'));
    $page->assignSmartyVariable('text', $gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE_DESC', array('config.php', ADMIDIO_URL . FOLDER_DATA, 'adm_my_files')));

    $form = new FormPresenter(
        'adm_installation_install_admidio_form',
        'installation.download-config.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION. '/installation.php', array('step' => 'start_installation')),
        $page
    );
    $form->addButton(
        'adm_previous_page',
        $gL10n->get('SYS_BACK'),
        array(
            'icon' => 'bi-arrow-left-circle-fill',
            'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION. '/installation.php', array('step' => 'create_administrator'))
        )
    );
    $form->addButton(
        'adm_download_config',
        $gL10n->get('INS_DOWNLOAD_CONFIGURATION_FILE'),
        array(
            'icon' => 'bi-download',
            'class' => 'btn-primary admidio-margin-bottom ms-2',
            'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION. '/installation.php', array('step' => 'download_config'))
        )
    );
    $form->addSubmitButton(
        'adm_next_page',
        $gL10n->get('INS_INSTALL_ADMIDIO'),
        array(
            'icon' => 'bi-arrow-repeat',
            'class' => ' btn-primary admidio-margin-bottom ms-2'
        )
    );
}

$form->addToHtmlPage();
$page->show();
