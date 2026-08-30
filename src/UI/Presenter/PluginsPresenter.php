<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginPages;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief The plugin administration.
 *
 * The page lists every plugin the installation knows - the directories below **plugins/** and the
 * plugins that are only left in the database because their files were deleted - grouped by the six
 * states of the PluginRegistry, and offers the operations of the PluginInstaller for each of them.
 *
 * A broken plugin is listed with the reason it is broken. That reason comes from the manifest reader
 * and from Plugin::checkRequirements(), which answer in English for a developer and not in a
 * translated message for a member, so it is shown as the diagnostic it is.
 *
 * **Code example**
 * ```
 * $page = new PluginsPresenter();
 * $page->createList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PluginsPresenter extends PagePresenter
{
    /**
     * The groups of the list, in the order they are shown, as group ID => language string ID of the
     * heading. A group with no plugin in it is dropped before the list is rendered.
     */
    private const GROUPS = array(
        PluginRegistry::STATE_ENABLED => 'SYS_ENABLED',
        PluginRegistry::STATE_UPDATE => 'SYS_UPDATE_AVAILABLE',
        PluginRegistry::STATE_AVAILABLE => 'SYS_EXTENSIONS_AVAILABLE',
        PluginRegistry::STATE_BROKEN => 'SYS_PLUGIN_BROKEN',
        PluginRegistry::STATE_ORPHANED => 'SYS_PLUGIN_ORPHANED'
    );

    /**
     * Create the list of all plugins with the operations that each of them allows.
     * @return void
     * @throws Exception
     */
    public function createList(): void
    {
        global $gL10n;

        $this->setHtmlID('adm_plugins');
        $this->setHeadline($gL10n->get('SYS_PLUGIN_MANAGER'));
        $this->setContentFullWidth();

        // Adding a plugin is a different question from managing the ones that are here, so it has a
        // page of its own rather than a panel above the list.
        $this->addPageFunctionsMenuItem(
            'plugin_add',
            $gL10n->get('SYS_PLUGIN_ADD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php', array('mode' => 'add')),
            'bi-plus-circle-fill'
        );

        $this->addJavascript('
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
        ', true);

        /*
         * Installing, enabling, updating and uninstalling all change what the other rows may offer,
         * so the page is reloaded instead of one row being patched.
         */
        $this->addJavascript('
            function callPluginAction(url, csrfToken) {
                $.post(url, { "adm_csrf_token": csrfToken }, function(data) {
                    const messageText = $("#adm_status_message");
                    let returnStatus = "error";
                    let returnMessage = "";

                    try {
                        const returnData = JSON.parse(data);
                        returnStatus = returnData.status;
                        if (typeof returnData.message !== "undefined") {
                            returnMessage = returnData.message;
                        }
                    } catch (e) {
                        returnMessage = data;
                    }

                    if (returnStatus === "success") {
                        messageText.html("<div class=\"alert alert-success\"><i class=\"bi bi-check-lg\"></i> "
                            + returnMessage + "</div>");
                    } else {
                        if (returnMessage.length === 0) {
                            returnMessage = "Error: Undefined error occurred!";
                        }
                        messageText.html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill\"></i> "
                            + returnMessage + "</div>");
                    }

                    setTimeout(function() {
                        $("#adm_modal").modal("hide");
                        $("#adm_modal_messagebox").modal("hide");
                        location.reload();
                    }, 2000);
                });
            }
        ');

        $this->smarty->assign('list', $this->getGroups());
        $this->smarty->assign('failures', PluginLoader::getFailures());
        $this->smarty->assign('l10n', $gL10n);

        try {
            $this->pageContent .= $this->smarty->fetch('modules/plugins.list.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * The page that adds a plugin the installation does not have yet.
     *
     * Uploading a file and choosing from the plugins published for Admidio are two answers to the
     * same question, so they are two panels of one page rather than two entries in a menu: an
     * administrator who wants a plugin should not have to know which of the two holds it before they
     * have looked.
     * @return void
     * @throws Exception
     */
    public function createAddPage(): void
    {
        global $gL10n, $gCurrentSession;

        $this->setHtmlID('adm_plugins_add');
        $this->setHeadline($gL10n->get('SYS_PLUGIN_ADD'));

        $form = new FormPresenter(
            'adm_plugin_upload_form',
            'modules/plugins.add.tpl',
            SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/plugins.php',
                array('mode' => 'upload')
            ),
            $this,
            array('enableFileUpload' => true)
        );

        $form->addFileUpload(
            'userfile',
            $gL10n->get('SYS_CHOOSE_FILE'),
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'allowedMimeTypes' => array('application/zip', 'application/x-zip-compressed'),
                'helpTextId' => 'SYS_PLUGIN_INSTALL_FROM_FILE_DESC'
            )
        );

        /*
         * Replacing is off unless it is asked for, because an archive that happens to carry the ID
         * of an installed plugin would otherwise overwrite it without anybody deciding to.
         */
        $form->addCheckbox(
            'plugin_replace',
            $gL10n->get('SYS_PLUGIN_PACKAGE_REPLACE'),
            false,
            array('helpTextId' => 'SYS_PLUGIN_PACKAGE_REPLACE_DESC')
        );

        $form->addSubmitButton(
            'adm_button_upload_plugin',
            $gL10n->get('SYS_PLUGIN_INSTALL_FROM_FILE'),
            array('icon' => 'bi-upload', 'class' => 'offset-sm-3')
        );

        /*
         * addToHtmlPage() renders the template into the page and binds the Admidio form submit,
         * which posts the form as FormData - so the file actually reaches the server - and expects
         * the JSON that mode=upload answers with.
         */
        $this->smarty->assign('l10n', $gL10n);
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * The settings panel of one plugin, wrapped as the content of a dialog.
     *
     * The panel itself is the very same one the preferences page shows - the plugin builds it, or it
     * is generated from the manifest - so a plugin never has to know that its settings can also be
     * opened from here.
     * @param Plugin $plugin
     * @return string The HTML of the dialog content.
     * @throws Exception
     */
    public function createSettings(Plugin $plugin): string
    {
        global $gL10n;

        $panel = $this->getSettingsPanelId($plugin);
        if ($panel === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $this->smarty->assign('pluginName', Language::translateIfTranslationStrId($plugin->name));
        $this->smarty->assign('pluginPanelBody', PluginPanel::create($panel, new PreferencesPresenter($panel)));
        /*
         * The form of the panel posts to the preferences page, which answers with the address of
         * the preferences page and would take the administrator away from the plugin list. Saving
         * through this module instead keeps them where they were.
         */
        $this->smarty->assign('pluginSaveUrl', SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/plugins.php',
            array('mode' => 'settings_save', 'plugin' => $plugin->id)
        ));
        $this->smarty->assign('l10n', $gL10n);

        try {
            return $this->smarty->fetch('modules/plugins.settings.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * The plugins of every state that has one, with everything the template shows for them.
     * @return array<string,array{id: string, name: string, entries: array<int,array<string,mixed>>}>
     * @throws Exception
     */
    private function getGroups(): array
    {
        global $gL10n;

        $groups = array();
        foreach (self::GROUPS as $state => $headline) {
            $groups[$state] = array('id' => $state, 'name' => $gL10n->get($headline), 'entries' => array());
        }

        foreach ($this->getPluginIds() as $id) {
            $plugin = PluginRegistry::get($id);
            $state = PluginRegistry::getState($plugin ?? $id);
            $groups[self::groupOf($state)]['entries'][] = $this->getEntry($id, $plugin, $state);
        }

        return array_filter($groups, static fn(array $group): bool => $group['entries'] !== array());
    }

    /**
     * The group of the list a plugin in this state is shown in.
     *
     * A plugin that was never prepared in the database and one that is prepared but switched off are
     * the same thing to an administrator: it is there and it is not running. Whether Admidio already
     * holds rows for it is a detail of how enabling works, not a state anybody chose.
     * @param string $state One of the PluginRegistry STATE_* constants.
     * @return string The key of one of the GROUPS.
     */
    private static function groupOf(string $state): string
    {
        return $state === PluginRegistry::STATE_DISABLED ? PluginRegistry::STATE_AVAILABLE : $state;
    }

    /**
     * The IDs of every plugin the installation knows: the directories below plugins/ and the
     * plugins that are only left in the database because their files were deleted.
     * @return array<int,string>
     * @throws Exception
     */
    private function getPluginIds(): array
    {
        $ids = array_unique(array_merge(
            array_keys(PluginRegistry::all()),
            array_keys(PluginRegistry::getInstallations())
        ));
        sort($ids);

        return $ids;
    }

    /**
     * One row of the list.
     * @param string $id ID of the plugin.
     * @param Plugin|null $plugin The plugin, or **null** for an orphan whose files are gone.
     * @param string $state One of the PluginRegistry STATE_* constants.
     * @return array<string,mixed>
     * @throws Exception
     */
    private function getEntry(string $id, ?Plugin $plugin, string $state): array
    {
        $homepage = $plugin?->homepage ?? '';

        return array(
            'id' => $id,
            'uuid' => $id,
            'name' => $plugin === null ? $id : Language::translateIfTranslationStrId($plugin->name),
            'description' => $plugin === null ? '' : Language::translateIfTranslationStrId($plugin->description),
            'icon' => $plugin?->icon ?? '',
            'author' => $plugin?->author ?? '',
            'url' => $homepage,
            'urlHost' => $homepage === '' ? '' : (string)(parse_url($homepage, PHP_URL_HOST) ?? $homepage),
            'version' => $plugin?->version ?? '',
            'installedVersion' => PluginRegistry::getInstalledVersion($id),
            'versionState' => $this->getVersionState($id, $plugin, $state),
            'toggle' => $this->getStateToggle($id, $plugin, $state),
            'diagnostics' => $this->getDiagnostics($plugin, $state),
            'actions' => $this->getActions($id, $plugin, $state)
        );
    }

    /**
     * The switch of the state column, which shows whether the plugin is enabled and flips it.
     *
     * It shows the state the plugin is in, not the operation a click performs, so that the column
     * can be read at a glance like a row of switches.
     * @param string $id ID of the plugin.
     * @param Plugin|null $plugin The plugin, or **null** for an orphan whose files are gone.
     * @param string $state One of the PluginRegistry STATE_* constants.
     * @return array<string,string>|null **null** for a plugin that cannot be enabled at all.
     * @throws Exception
     */
    private function getStateToggle(string $id, ?Plugin $plugin, string $state): ?array
    {
        global $gL10n;

        /*
         * An orphan has no files left to load and a broken plugin cannot be loaded, so neither has
         * a switch. Everything else has one, whether or not it has been prepared in the database:
         * switching on a plugin that is only on disk is what prepares it.
         */
        if ($plugin === null || !$plugin->isValid()) {
            return null;
        }

        $enabled = PluginRegistry::isEnabled($id);

        return array(
            'icon' => $enabled ? 'bi bi-toggle-on' : 'bi bi-toggle-off',
            'class' => $enabled ? 'text-success' : 'text-secondary',
            'label' => $gL10n->get($enabled ? 'SYS_ENABLED' : 'SYS_DISABLED'),
            'tooltip' => $gL10n->get($enabled ? 'SYS_PLUGIN_DISABLE' : 'SYS_PLUGIN_ENABLE'),
            'dataMessage' => $gL10n->get($enabled ? 'SYS_WANT_DISABLE_PLUGIN' : 'SYS_WANT_ENABLE_PLUGIN', array($id)),
            'dataHref' => $this->actionScript($id, $enabled ? 'disable' : 'enable')
        );
    }

    /**
     * How the installed version of a plugin relates to the version its files declare.
     *
     * The registry has already answered this in the state, so the list must not compare the two
     * version strings again: a plugin whose files are gone has no declared version to compare with,
     * and one whose manifest is broken may have none either.
     * @param string $id ID of the plugin.
     * @param Plugin|null $plugin The plugin, or **null** for an orphan whose files are gone.
     * @param string $state One of the PluginRegistry STATE_* constants.
     * @return string **not_installed**, **update**, **current** or **unknown**.
     * @throws Exception
     */
    private function getVersionState(string $id, ?Plugin $plugin, string $state): string
    {
        if (!PluginRegistry::isInstalled($id)) {
            return 'not_installed';
        }
        if ($state === PluginRegistry::STATE_UPDATE) {
            return 'update';
        }
        if ($plugin === null || $plugin->version === '') {
            return 'unknown';
        }

        return 'current';
    }

    /**
     * Why a plugin cannot be used, or why its pages are not published.
     *
     * These are developer diagnostics in English, not translated messages, because they name a
     * malformed manifest, an unmet version constraint or a directory that cannot be written.
     * @param Plugin|null $plugin
     * @param string $state One of the PluginRegistry STATE_* constants.
     * @return array<int,string>
     * @throws Exception
     */
    private function getDiagnostics(?Plugin $plugin, string $state): array
    {
        if ($plugin === null) {
            return array();
        }

        $diagnostics = array();
        if ($plugin->error !== null) {
            $diagnostics[] = $plugin->error;
        }
        $diagnostics = array_merge($diagnostics, $plugin->checkRequirements(PluginRegistry::getEnabledVersions()));

        /*
         * The pages of an installed plugin may be blocked although the administrator allowed them.
         * A broken plugin is not asked: it already reported why it cannot be used at all.
         */
        if ($state !== PluginRegistry::STATE_BROKEN && PluginPages::isAllowed() && $plugin->hasPages()
            && PluginRegistry::isInstalled($plugin->id) && !PluginPages::isPublished($plugin)) {
            $obstacle = PluginPages::getObstacle($plugin);
            if ($obstacle !== null) {
                $diagnostics[] = $obstacle;
            }
        }

        return $diagnostics;
    }

    /**
     * The operations a plugin allows in its current state.
     * @param string $id ID of the plugin.
     * @param Plugin|null $plugin The plugin, or **null** for an orphan whose files are gone.
     * @param string $state One of the PluginRegistry STATE_* constants.
     * @return array<int,array<string,string>>
     * @throws Exception
     */
    private function getActions(string $id, ?Plugin $plugin, string $state): array
    {
        global $gL10n;

        $actions = array();

        if ($state === PluginRegistry::STATE_ENABLED && $plugin !== null
            && $this->getSettingsPanelId($plugin) !== '') {
            // The settings open in a dialog, so that the administrator keeps their place in the list.
            $actions[] = array(
                'popup' => true,
                'dataHref' => SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/plugins.php',
                    array('mode' => 'settings', 'plugin' => $id)
                ),
                'icon' => 'bi bi-gear',
                'tooltip' => $gL10n->get('SYS_PLUGIN_PREFERENCES')
            );
        }

        if ($state === PluginRegistry::STATE_UPDATE) {
            $actions[] = $this->action($id, 'update', 'bi bi-arrow-clockwise', 'SYS_PLUGIN_UPDATE', 'SYS_WANT_UPDATE_PLUGIN');
        }

        /*
         * A plugin of the Admidio distribution has no remove action at all: its files come back with
         * the next core update, so offering to delete them would promise something Admidio cannot
         * keep. Disabling is what takes such a plugin out of use.
         */
        if (!PluginRegistry::isBuiltIn($id)) {
            /*
             * Removing reaches every organization, so the question names the ones that are still
             * using the plugin - including those this administrator does not administrate.
             */
            $organizations = PluginRegistry::getEnabledOrganizations($id);

            $actions[] = $this->action(
                $id,
                'remove',
                'bi bi-trash',
                'SYS_PLUGIN_REMOVE',
                $organizations === array() ? 'SYS_WANT_REMOVE_PLUGIN' : 'SYS_WANT_REMOVE_PLUGIN_ENABLED_IN',
                array(),
                $organizations === array() ? array($id) : array($id, implode(', ', $organizations))
            );
        }

        return $actions;
    }

    /**
     * The preferences panel that holds the settings of a plugin, or an empty string if it has none.
     *
     * A plugin that registered a panel of its own is asked for it by the ID the panel convention
     * derives from the plugin ID.
     * @param Plugin $plugin
     * @return string
     */
    private function getSettingsPanelId(Plugin $plugin): string
    {
        $id = PluginPanel::normalizeId($plugin->id);

        return PluginPanel::get($id) === null ? '' : $id;
    }

    /**
     * One operation of the list, as a confirmed POST to this module.
     * @param string $id ID of the plugin.
     * @param string $mode Mode of modules/plugins.php that performs the operation.
     * @param string $icon
     * @param string $tooltip Language string ID of the tooltip.
     * @param string $message Language string ID of the confirmation question.
     * @param array<string,string> $parameters Further URL parameters of the operation.
     * @param array<int,string>|null $messageVariables What the confirmation question interpolates,
     *                                                 defaulting to the plugin ID alone.
     * @return array<string,string>
     * @throws Exception
     */
    private function action(
        string $id,
        string $mode,
        string $icon,
        string $tooltip,
        string $message,
        array $parameters = array(),
        ?array $messageVariables = null
    ): array {
        global $gL10n;

        return array(
            'dataHref' => $this->actionScript($id, $mode, $parameters),
            'dataMessage' => $gL10n->get($message, $messageVariables ?? array($id)),
            'icon' => $icon,
            'tooltip' => $gL10n->get($tooltip)
        );
    }

    /**
     * The JavaScript call that performs one operation, for the data-href of a confirmed link.
     * @param string $id ID of the plugin.
     * @param string $mode Mode of modules/plugins.php that performs the operation.
     * @param array<string,string> $parameters Further URL parameters of the operation.
     * @return string
     * @throws Exception
     */
    private function actionScript(string $id, string $mode, array $parameters = array()): string
    {
        global $gCurrentSession;

        $url = SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/plugins.php',
            array_merge(array('mode' => $mode, 'plugin' => $id), $parameters)
        );

        return 'callPluginAction(\'' . $url . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
    }
}
