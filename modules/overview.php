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
use Admidio\Infrastructure\Plugins\PluginWidget;

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
    $page = PagePresenter::withHtmlIDAndHeadline('adm_overview', $headline);
    $page->setContentFullWidth();

    // A plugin contributes a widget to the overview through the overview_widgets filter.
    $page->assignSmartyVariable('overviewWidgets', PluginWidget::collect($page));
    $page->addTemplateFile('system/overview.tpl');

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
