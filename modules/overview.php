<?php
/**
 ***********************************************************************************************
 * A small overview of all Admidio modules with the integration of Admidio plugins
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\UI\Presenter\PagePresenter;

try {
    // if the config file doesn't exist, then show the installation dialog
    if (!is_file(dirname(__DIR__) . '/adm_my_files/config.php')) {
        header('Location: ../install/index.php');
        exit();
    }

    require_once(__DIR__ . '/../system/common.php');

    $headline = $gL10n->get('SYS_OVERVIEW');

    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-house-door-fill');

    // create html page object and load template file
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-overview', $headline);
    $page->setContentFullWidth();

    // create a list of all overview plugins and their visibility
    $overviewPlugins = array(
        'login-form' => array('name' => 'login_form', 'file' => 'index.php', 'show' => true),
        'birthday' => array('name' => 'birthday', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_birthday_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_birthday_enabled') === 2 && $gValidLogin)) ? true : false),
        'calendar' => array('name' => 'calendar', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_calendar_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_calendar_enabled') === 2 && $gValidLogin)) ? true : false),
        'random-photo' => array('name' => 'random_photo', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_random_photo_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_random_photo_enabled') === 2 && $gValidLogin)) ? true : false),
        'latest-documents-files' => array('name' => 'latest-documents-files', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_latest_documents_files_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_latest_documents_files_enabled') === 2 && $gValidLogin)) ? true : false),
        'announcement-list' => array('name' => 'announcement-list', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_announcement_list_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_announcement_list_enabled') === 2 && $gValidLogin)) ? true : false),
        'event-list' => array('name' => 'event-list', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_event_list_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_event_list_enabled') === 2 && $gValidLogin)) ? true : false),
        'who-is-online' => array('name' => 'who-is-online', 'file' => 'index.php', 'show' => ($gSettingsManager->getInt('overview_plugin_who_is_online_enabled') === 1 || ($gSettingsManager->getInt('overview_plugin_who_is_online_enabled') === 2 && $gValidLogin)) ? true : false),
    );
    
    $page->assignSmartyVariable('overviewPlugins', $overviewPlugins);
    $page->addTemplateFile('system/overview.tpl');

    $page->show();
} catch (Throwable $e) {
    if (isset($gMessage)) {
        $gMessage->show($e->getMessage());
    } else {
        echo $e->getMessage();
    }
}
