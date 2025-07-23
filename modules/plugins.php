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

use Admidio\Components\Service\ComponentService;
use Admidio\Infrastructure\Exception;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\UI\Presenter\PluginsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'install', 'uninstall', 'update', 'sequence')));
    $getPluginName = admFuncVariableIsValid($_GET, 'name', 'string', array('defaultValue' => ''));
    $getPluginId = admFuncVariableIsValid($_GET, 'uuid', 'int');

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
        case 'sequence':
            // Update menu entry sequence
            $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('validValues' => array(MenuEntry::MOVE_UP, MenuEntry::MOVE_DOWN)));
            $getOrder      = admFuncVariableIsValid($_GET, 'order', 'array');

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $componentService = new ComponentService($gDb, $getPluginId);

            if (!empty($getOrder)) {
                // set new order (drag and drop)
                $ret = $componentService->setSequence(explode(',', $getOrder));
            } else {
                $ret = $componentService->moveSequence($postDirection);
            }

            echo json_encode(array('status' =>  ($ret ? 'success' : 'error')));
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
