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
use Admidio\Infrastructure\Plugins\PluginPackage;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginStore;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\UI\Presenter\PluginsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list',
        'validValues' => array('list', 'add', 'settings', 'settings_save', 'upload', 'store_install', 'store_refresh', 'enable', 'disable', 'update', 'remove')));
    // Everything but the list and the settings dialog answers with JSON.
    $isAjax = !in_array($getMode, array('list', 'add', 'settings'), true);

    // check rights to use this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    /*
     * Most modes work on one plugin, addressed by its ID. The list does not, and neither do the two
     * modes that add a plugin: the page offers every plugin there is, and the upload learns which
     * plugin it holds by reading the archive.
     */
    $getPluginId = '';
    $plugin = null;
    if (!in_array($getMode, array('list', 'add', 'upload', 'store_refresh'), true)) {
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

        case 'add':
            // create an HTML page object
            $page = new PluginsPresenter();
            $page->createAddPage();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'upload':
            // check the CSRF token of the form against the session token
            $uploadForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $uploadForm->validate($_POST);

            $uploadedFile = $_FILES['userfile']['tmp_name'][0] ?? '';
            if ($uploadedFile === '') {
                throw new Exception('SYS_FIELD_EMPTY', array('SYS_FILE'));
            }
            if (($_FILES['userfile']['error'][0] ?? 0) === UPLOAD_ERR_INI_SIZE) {
                throw new Exception('SYS_PLUGIN_PACKAGE_TOO_LARGE');
            }
            if (!is_uploaded_file($uploadedFile)) {
                throw new Exception('SYS_FILE_NOT_EXIST');
            }

            $installedId = PluginPackage::install($uploadedFile, !empty($formValues['plugin_replace']));

            $gNavigation->deleteLastUrl();
            echo json_encode(array(
                'status' => 'success',
                'message' => $gL10n->get('SYS_PLUGIN_PACKAGE_INSTALLED', array($installedId)),
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php')
            ));
            break;

        case 'store_install':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $installedId = PluginStore::install($getPluginId);
            echo json_encode(array(
                'status' => 'success',
                'message' => $gL10n->get('SYS_PLUGIN_PACKAGE_INSTALLED', array($installedId))
            ));
            break;

        case 'store_refresh':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            PluginStore::refresh();
            $storeError = PluginStore::getError();
            if ($storeError !== null) {
                // The diagnostic is English, like the one a broken plugin produces.
                throw new Exception($storeError);
            }

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_STORE_REFRESHED')));
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

        case 'enable':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginInstaller::enable($plugin);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_ENABLED')));
            break;

        case 'disable':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }
            PluginInstaller::disable($plugin);
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PLUGIN_DISABLED')));
            break;

        case 'update':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($getPluginId));
            }

            /*
             * Updating is one action whatever the plugin needs. Where newer files are published they
             * are fetched first; then the update scripts run, which is all that is needed for a
             * plugin whose files were replaced by hand. The administrator is told what happened, not
             * which of the two it was.
             */
            if (PluginStore::getNewerRelease($getPluginId) !== null) {
                PluginStore::updateFiles($getPluginId);
                PluginRegistry::reset();
                $plugin = PluginRegistry::get($getPluginId);
            }

            PluginInstaller::update($plugin);
            echo json_encode(array(
                'status' => 'success',
                'message' => $gL10n->get('SYS_PLUGIN_UPDATED_TO', array($plugin->version))
            ));
            break;

        case 'remove':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            if ($plugin === null && !PluginRegistry::isInstalled($getPluginId)) {
                throw new Exception('SYS_PLUGIN_NOT_FOUND', array($getPluginId));
            }

            /*
             * The ID keeps the cleanup of an orphan working: its files are gone, so there is no
             * plugin left to pass, but its component row and its enabled flag are still there.
             */
            $filesDeleted = PluginInstaller::remove($plugin ?? $getPluginId);
            echo json_encode(array(
                'status' => 'success',
                'message' => $gL10n->get($filesDeleted ? 'SYS_PLUGIN_REMOVED' : 'SYS_PLUGIN_REMOVED_FILES_KEPT')
            ));
            break;
    }
} catch (Throwable $e) {
    handleException($e, $isAjax ?? false);
}
