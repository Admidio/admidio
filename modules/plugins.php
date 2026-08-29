<?php
/**
 ***********************************************************************************************
 * Administration of the plugins of this Admidio installation
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Parameters:
 *
 *  mode     : list          - (default) Show the list of all plugins with their state
 *             settings      - Show the settings of a plugin as the content of a dialog
 *             settings_save - Save the settings a plugin was given in that dialog
 *             install       - Install a plugin
 *             enable        - Enable a plugin for the current organization
 *             disable       - Disable a plugin for the current organization
 *             update        - Run the update scripts of a plugin
 *             uninstall     - Uninstall a plugin
 *  plugin   : ID of the plugin, which is the name of its directory below plugins/
 *  data     : uninstall - also run db_scripts/uninstall.sql and destroy the data of the plugin
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\PluginInstaller;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\UI\Presenter\PluginsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list',
        'validValues' => array('list', 'settings', 'settings_save', 'install', 'enable', 'disable', 'update', 'uninstall')));
    // Everything but the list and the settings dialog answers with JSON.
    $isAjax = !in_array($getMode, array('list', 'settings'), true);

    // check rights to use this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Every mode but the list works on one plugin, addressed by its ID.
    $getPluginId = '';
    $plugin = null;
    if ($getMode !== 'list') {
        $getPluginId = admFuncVariableIsValid($_GET, 'plugin', 'string', array('requireValue' => true));
        $plugin = PluginRegistry::get($getPluginId);
    }

    switch ($getMode) {
        case 'list':
            // create an HTML page object
            $page = new PluginsPresenter();
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-puzzle-fill');
            $page->show();
            break;

        case 'settings':
            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginRegistry::requireEnabled($getPluginId);

            echo (new PluginsPresenter())->createSettings($plugin);
            break;

        case 'settings_save':
            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginRegistry::requireEnabled($getPluginId);

            // The form was built by the preferences page, so the preferences service validates and
            // stores it; only the answer differs, because the administrator stays in the plugin list.
            (new PreferencesService())->save(PluginPanel::normalizeId($getPluginId), $_POST);

            echo json_encode(array(
                'status' => 'success',
                'message' => $gL10n->get('SYS_SAVE_DATA'),
                'url' => ADMIDIO_URL . FOLDER_MODULES . '/plugins.php'
            ));
            break;

        case 'install':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginInstaller::install($plugin);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_INSTALLED')));
            break;

        case 'enable':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginInstaller::setEnabled($plugin, true);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_ENABLED')));
            break;

        case 'disable':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginInstaller::setEnabled($plugin, false);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_DISABLED')));
            break;

        case 'update':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginInstaller::update($plugin);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_UPDATED')));
            break;

        case 'uninstall':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $getRemoveData = admFuncVariableIsValid($_GET, 'data', 'bool', array('defaultValue' => false));

            if ($plugin === null && !PluginRegistry::isInstalled($getPluginId)) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }

            /*
             * The ID keeps the cleanup of an orphan working: its files are gone, so there is no
             * plugin left to pass, but its component row and its enabled flag are still there.
             * Such a plugin also has no uninstall.sql any more, so its data cannot be destroyed.
             */
            PluginInstaller::uninstall($plugin ?? $getPluginId, $plugin !== null && $getRemoveData);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_UNINSTALLED')));
            break;
    }
} catch (Throwable $e) {
    handleException($e, $isAjax ?? false);
}
