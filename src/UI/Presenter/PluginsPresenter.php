<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Language;
/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the plugins module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new PluginsPresenter('adm_plugins', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PluginsPresenter extends PagePresenter
{
    protected array $templateData = array();

    /**
     * Create the list of plugins.
     * @throws Exception
     */
    public function createList(): void
    {
        global $gL10n;

        $this->setHtmlID('adm_plugins');
        $this->setHeadline($gL10n->get('SYS_PLUGIN_MANAGER'));
        
        $this->prepareData();

        $this->addJavascript('
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
            ', true
        );

        $this->smarty->assign('list', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        try {
            $this->pageContent .= $this->smarty->fetch('modules/plugins.list.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Read all available forum topics from the database and create a Bootstrap card for each topic.
     * @param int $offset Offset of the first record that should be returned.
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function createCards(): void
    {
        global $gL10n;

        $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php', array('mode' => 'cards'));

        $this->setHtmlID('adm_plugins');
        $this->setHeadline($gL10n->get('SYS_PLUGIN_MANAGER'));
        
        $this->prepareData();

        $this->smarty->assign('cards', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('pagination', admFuncGeneratePagination($baseUrl, count($this->templateData), 10, 0));

        try {
            $this->pageContent .= $this->smarty->fetch('modules/plugins.cards.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param int $offset Offset of the first record that should be returned.
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function prepareData(): void
    {
        global $gL10n;
        $pluginManager = new PluginManager();
        $plugins = $pluginManager->getAvailablePlugins();
        $templateRowPluginParent[] = array('id' => 'overview_plugins', 'name' => $gL10n->get('SYS_OVERVIEW_EXTENSIONS'), 'entries' => array());
        $templateRowPluginParent[] = array('id' => 'plugins', 'name' => $gL10n->get('SYS_EXTENSIONS'), 'entries' => array());
        foreach($plugins as $pluginName => $values) {
            $templateRow = array();
            $interface = $values['interface'] instanceof PluginAbstract ? $values['interface']::getInstance() : null;

            if ($interface != null) {
                $templateRow['id'] = ($interface->getComponentId() !== 0) ? $interface->getComponentId() : $pluginName;
                $templateRow['name'] = Language::translateIfTranslationStrId($interface->getName());
                $templateRow['description'] = Language::translateIfTranslationStrId($interface->getMetadata()['description'] ?? '');
                $templateRow['icon'] = $interface->getMetadata()['icon'] ?? '';
                $templateRow['author'] = $interface->getMetadata()['author'] ?? '';
                $templateRow['version'] = $interface->getMetadata()['version'] ?? '';
                $templateRow['installedVersion'] = $interface->getVersion() !== '0.0.0' ? $interface->getVersion() : '';

                // add actions for the plugin
                if ($interface->isInstalled()) {
                    // add showPreferences action
                    $templateRow['actions'][] = array(
                        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => str_replace(' ', '_',strtolower(Language::translateIfTranslationStrId($interface->getName()))))),
                        'icon' => 'bi bi-gear',
                        'tooltip' => $gL10n->get('SYS_PLUGIN_PREFERENCES')
                    );
                    // add update action if an update is available
                    if ($interface->isUpdateAvailable()) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php', array('mode' => 'update', 'name' => $pluginName)),
                            'icon' => 'bi bi-arrow-clockwise',
                            'tooltip' => $gL10n->get('SYS_PLUGIN_UPDATE')
                        );
                    }
                    if (!$interface->isOverviewPlugin()) {
                        // add uninstall action
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php', array('mode' => 'uninstall', 'name' => $pluginName)),
                            'icon' => 'bi bi-trash',
                            'tooltip' => $gL10n->get('SYS_PLUGIN_UNINSTALL')
                        );
                    }
                } else {
                    // add install action
                    $templateRow['actions'][] = array(
                        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php', array('mode' => 'install', 'name' => $pluginName)),
                        'icon' => 'bi bi-download',
                        'tooltip' => $gL10n->get('SYS_PLUGIN_INSTALL')
                    );
                }
            } else {
                // if the plugin does not implement the PluginInterface then we cannot show it
                $templateRow['id'] = $pluginName;
                $templateRow['name'] = $pluginName;
                $templateRow['description'] = $gL10n->get('SYS_PLUGIN_NO_INTERFACE');
                $templateRow['icon'] = '';
                $templateRow['author'] = '';
                $templateRow['version'] = '';
                $templateRow['installedVersion'] = '';
            }

            if ($interface !== null && $interface->isOverviewPlugin()) {
                // add the plugin to the overview plugins
                $templateRowPluginParent[0]['entries'][] = $templateRow;
            } else {
                // add the plugin to the normal plugins
                $templateRowPluginParent[1]['entries'][] = $templateRow;
            }
        }
        $this->templateData = $templateRowPluginParent;
    }
}