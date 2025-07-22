<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all menus
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Parameters:
 *
 *  mode     : list     - (default) Show page with a list of all menu entries
 *             edit     - Show form to create or edit a menu entry
 *             save     - Save the data of the form
 *             delete   - Delete menu entry
 *             sequence - Change sequence for parameter men_id
 * uuid      : UUID of the menu entry that should be edited
 * direction : Direction to change the sequence of the menu entry
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\UI\Presenter\PluginsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'install', 'uninstall', 'update')));
    $getPluginName = admFuncVariableIsValid($_GET, 'name', 'string', array('defaultValue' => ''));

    // check rights to use this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'list':
            // create html page object
            $page = new PluginsPresenter();
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-puzzle-fill');
            $page->show();
            break;

        case 'install':
            // install plugin
            if (!empty($getPluginName)) {
                $pluginManager = new PluginManager();
                $plugin = $pluginManager->getPluginByName($getPluginName);
                if ($plugin) {
                    $interface = $plugin instanceof PluginAbstract ? $plugin::getInstance() : null;

                    if ($interface != null) {
                        $interface->doInstall();
                    }
                }
                $gNavigation->deleteLastUrl();
                admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php'));
            } else {
                throw new Exception('SYS_PLUGIN_NAME_MISSING');
            }
            break;

        case 'uninstall':
            // uninstall plugin
            if (!empty($getPluginName)) {
                $pluginManager = new PluginManager();
                $plugin = $pluginManager->getPluginByName($getPluginName);
                if ($plugin) {
                    $interface = $plugin instanceof PluginAbstract ? $plugin::getInstance() : null;

                    if ($interface != null) {
                        $interface->doUninstall();
                    }
                }
                $gNavigation->deleteLastUrl();
                admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php'));
            } else {
                throw new Exception('SYS_PLUGIN_NAME_MISSING');
            }
            break;

        case 'update':
            // update plugin
            if (!empty($getPluginName)) {
                $pluginManager = new PluginManager();
                $plugin = $pluginManager->getPluginByName($getPluginName);
                if ($plugin) {
                    $interface = $plugin instanceof PluginAbstract ? $plugin::getInstance() : null;

                    if ($interface != null) {
                        $interface->doUpdate();
                    }
                }
                $gNavigation->deleteLastUrl();
                admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php'));
            } else {
                throw new Exception('SYS_PLUGIN_NAME_MISSING');
            }
            break;

        default:
            throw new Exception('SYS_UNKNOWN_MODE');
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('save', 'delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
