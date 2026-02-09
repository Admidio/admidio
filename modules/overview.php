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
use Admidio\Infrastructure\Plugins\PluginManager;

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

    // get all overview plugins and add them to the template
    $pluginManager = new PluginManager();
    $plugins = $pluginManager->getOverviewPlugins();

    $overviewPlugins = array();
    foreach ($plugins as $plugin) {
        $overviewPlugins[] =  array(
            'id' => $plugin['id'],
            'name' => $plugin['name'],
            'file' => basename($plugin['file'])
        );
    }
    $page->assignSmartyVariable('overviewPlugins', $overviewPlugins);
    $page->addTemplateFile('system/overview.tpl');

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
