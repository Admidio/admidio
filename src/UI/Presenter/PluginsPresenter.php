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
        global $gL10n, $gCurrentSession;

        $this->setHtmlID('adm_plugins');
        $this->setHeadline($gL10n->get('SYS_PLUGIN_MANAGER'));
        $this->setContentFullWidth();

        $this->prepareData();

        $this->addJavascript('
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
            $("tbody.admidio-sortable").sortable({
                axis: "y",
                handle: ".handle",
                stop: function(event, ui) {
                    const order = $(this).sortable("toArray", {attribute: "data-uuid"});
                    const uid = ui.item.attr("data-uuid");
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/plugins.php?mode=sequence&uuid=" + uid + "&order=" + order,
                        {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}
                    );
                    updateMoveActions("tbody.admidio-sortable", "adm_plugin_entry", "admidio-plugin-move");
                }
            });
            $(".admidio-plugin-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/plugins.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.url.indexOf("mode=delete") !== -1) {
                    // wait for callUrlHideElement to finish hiding the element
                    setTimeout(function() {
                        updateMoveActions("tbody.admidio-sortable", "adm_plugin_entry", "admidio-plugin-move");
                        updateMoveActions(".accordion", "adm_plugin_card_entry", "admidio-plugin-move");
                    }, 1000);
                } else {
                    updateMoveActions("tbody.admidio-sortable", "adm_plugin_entry", "admidio-plugin-move");
                    updateMoveActions(".accordion", "adm_plugin_card_entry", "admidio-plugin-move");
                }
            });

            updateMoveActions("tbody.admidio-sortable", "adm_plugin_entry", "admidio-plugin-move");
            updateMoveActions(".accordion", "adm_plugin_card_entry", "admidio-plugin-move");
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
        $templateRowPluginParent['overview'] = array('id' => 'overview_plugins', 'name' => $gL10n->get('SYS_OVERVIEW_EXTENSIONS'), 'entries' => array());
        $templateRowPluginParent['plugins'] = array('id' => 'plugins', 'name' => $gL10n->get('SYS_EXTENSIONS'), 'entries' => array());
        $templateRowPluginParent['available'] = array('id' => 'plugins_available', 'name' => $gL10n->get('SYS_EXTENSIONS_AVAILABLE'), 'entries' => array());
        foreach($plugins as $pluginName => $values) {
            $templateRow = array();
            $interface = $values['interface'] instanceof PluginAbstract ? $values['interface']::getInstance() : null;

            if ($interface != null) {
                $templateRow['id'] = ($interface->getComponentId() !== 0) ? $interface->getComponentId() : $pluginName;
                $templateRow['name'] = Language::translateIfTranslationStrId($interface->getName());
                $templateRow['description'] = Language::translateIfTranslationStrId($interface->getMetadata()['description'] ?? '');
                $templateRow['icon'] = $interface->getMetadata()['icon'] ?? '';
                $templateRow['url'] = $interface->getMetadata()['url'] ? '<a href="' . $interface->getMetadata()['url'] . '" target="_blank" data-bs-toggle="tooltip" title="' . $interface->getMetadata()['url'] . '" style="display:inline-flex;"><i class="bi bi-link-45deg"></i>' . parse_url($interface->getMetadata()['url'])['host'] . '</a>' : '';
                $templateRow['author'] = $interface->getMetadata()['author'] ?? '';
                $templateRow['version'] = $interface->getMetadata()['version'] ?? '';
                $templateRow['installedVersion'] = $interface->getVersion() !== '0.0.0' ? $interface->getVersion() : '';

                // add actions for the plugin
                if ($interface->isInstalled()) {
                    // add showPreferences action
                    $templateRow['actions'][] = array(
                        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => preg_replace('/\s+/', '_', preg_replace('/[^a-z0-9_ ]/', '', strtolower(Language::translateIfTranslationStrId($interface->getName())))))),
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
                $templateRow['url'] = '';
                $templateRow['author'] = '';
                $templateRow['version'] = '';
                $templateRow['installedVersion'] = '';
            }

            if ($interface !== null && $interface->isOverviewPlugin()) {
                // add the plugin to the overview plugins
                // for overview plugins here is a sequence number that is used to sort the plugins on the overview page
                $sequence = $interface->getPluginSequence();
                $desiredSequence = $sequence;
                if (isset($templateRowPluginParent['overview']['entries'][$desiredSequence])) {
                    $desiredSequence++;
                    while (isset($templateRowPluginParent['overview']['entries'][$desiredSequence])) {
                        $desiredSequence++;
                    }
                }
                $templateRowPluginParent['overview']['entries'][$desiredSequence] = $templateRow;
                ksort($templateRowPluginParent['overview']['entries']);
            } elseif ($interface !== null && $interface->isInstalled()) {
                // add the plugin to the normal plugins
                $templateRowPluginParent['plugins']['entries'][] = $templateRow;
            } else {
                // add the plugin to the available plugins
                $templateRowPluginParent['available']['entries'][] = $templateRow;
            }
        }
        
        // remove empty categories
        foreach ($templateRowPluginParent as $key => $value) {
            if (empty($value['entries'])) {
                unset($templateRowPluginParent[$key]);
            }
        }

        $this->templateData = $templateRowPluginParent;
    }
}