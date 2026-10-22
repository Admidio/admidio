<?php
/**
 ***********************************************************************************************
 * Installation step: start_installation
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\Service\StatisticsConsent;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'start_installation.php') {
    exit('This page may not be called directly!');
}

// Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
if (!is_file($configPath)) {
    throw new Exception('INS_CONFIGURATION_FILE_NOT_FOUND', array('config.php'));
}

// first check if session is filled (if installation was aborted then this is not filled)
// if previous dialogs were filled then check if the settings are equal to config file
if (isset($_SESSION['table_prefix'])
    && ($_SESSION['db_type'] !== DB_TYPE
        || $_SESSION['db_host'] !== DB_HOST
        || $_SESSION['db_port'] !== InstallationConfig::normalizePort(DB_PORT)
        || $_SESSION['db_name'] !== DB_NAME
        || $_SESSION['db_username'] !== DB_USERNAME
        || $_SESSION['db_password'] !== DB_PASSWORD
        || $_SESSION['table_prefix'] !== TABLE_PREFIX)) {
    throw new Exception('INS_DATA_DO_NOT_MATCH', array('config.php'));
}

if ($mode === 'html') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        // Keep upload errors in the existing AJAX form, then navigate to the confirmation.
        if (!isset($_SESSION['installationDownloadConfigForm'])) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $_SESSION['installationDownloadConfigForm']->validate($_POST);
        echo json_encode(array(
            'status' => 'success',
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation'))
        ));
        exit();
    }
    // Both existing and newly created config files lead to this final confirmation page.
    $page = new InstallationPresenter('adm_installation_start', $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT)));
    $page->addTemplateFile('installation.tpl');
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_INSTALL_ADMIDIO'));
    $page->assignSmartyVariable('text', $gL10n->get('INS_DATA_FULLY_ENTERED'));
    $form = new FormPresenter(
        'adm_installation_install_admidio_form',
        'installation.install-admidio.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation', 'mode' => 'check')),
        $page
    );
    StatisticsConsent::addToForm($form, $page);
    $form->addSubmitButton('adm_next_page', $gL10n->get('INS_INSTALL_ADMIDIO'), array('icon' => 'bi-arrow-repeat'));
    $form->addToHtmlPage();
    $_SESSION['installationStartForm'] = $form;
    $page->show();
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_SESSION['installationStartForm'])) {
    throw new Exception('SYS_INVALID_PAGE_VIEW');
}
$formValues = $_SESSION['installationStartForm']->validate($_POST);
$statisticsData = StatisticsConsent::selectedData($formValues);

// Create the database and all its data, then send only the reviewed and selected snapshot.
Installation::install($db, InstallationConfig::fromInstallerSession($_SESSION, ADMIDIO_URL), StatisticsConsent::tenantId());
StatisticsConsent::send($statisticsData);

// delete session data
session_unset();
session_destroy();

echo json_encode(array(
    'status' => 'success',
    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'installation_successful'))));
exit();
