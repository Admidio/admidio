<?php
/**
 ***********************************************************************************************
 * The page of the Hello World plugin.
 *
 * A page of a plugin is an ordinary Admidio module file. It is reachable at
 * plugins/hello-world/modules/index.php, and at modules/hello-world/index.php as well when the
 * preference plugin_module_pages is switched on.
 *
 * The Admidio bootstrap has already checked that this plugin is installed and enabled for the
 * current organization; a page of a disabled plugin is refused before it runs.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\HelloWorld\Greeting;

try {
    require_once(__DIR__ . '/../../../system/common.php');

    $plugin = PluginRegistry::get('hello-world');

    $page = PagePresenter::withHtmlIDAndHeadline('adm_hello_world', $gL10n->get('PLG_HELLO_WORLD_NAME'));
    $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), $plugin->icon);

    $page->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));
    $page->addTemplateFile('plugin.hello-world.tpl');
    $page->assignSmartyVariable('greeting', Greeting::getText());
    $page->assignSmartyVariable('pluginVersion', $plugin->version);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
