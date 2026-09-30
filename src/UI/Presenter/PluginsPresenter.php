<?php
namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Components\Entity\Component;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginPages;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginStore;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\DataTables;

/**
 * @brief The plugin administration.
 *
 * The page lists every plugin the installation knows - the directories below **plugins/** and the
 * plugins that are only left in the database because their files were deleted - grouped by the six
 * states of the PluginRegistry, and offers the operations of the PluginInstaller for each of them.
 *
 * A broken plugin is listed with the reason it is broken. A structural problem the manifest reader
 * found - a missing manifest, a broken one, an invalid folder name - is shown as a translated
 * message, keyed off Plugin::$errorCode; Plugin::$error itself stays an English diagnostic for the
 * CLI and the log. An unmet version, PHP, extension or plugin-dependency requirement from
 * Plugin::checkRequirements() is shown as the English diagnostic it is, because it names a version
 * string or an extension name that is not worth translating around.
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
            $(document).on("click", ".admidio-open-close-caret", function() {
                showHideBlock($(this));
            });
        ', true);

        $this->addActionJavascript();

        $this->pageContent .= $this->createListContent();
    }

    /**
     * The list content alone, without the surrounding page: the group headings, the table and the
     * card view. This is what the page shows on first load and what the state toggle re-fetches
     * afterwards, so that enabling or disabling a plugin never has to reload the whole page - only
     * the list, which is also the only part a toggle actually changes.
     * @return string The HTML of the list content, wrapped in one container the toggle replaces.
     * @throws Exception
     */
    public function createListContent(): string
    {
        global $gL10n;

        $this->smarty->assign('list', $this->getGroups());
        $this->smarty->assign('failures', PluginLoader::getFailures());
        $this->smarty->assign('l10n', $gL10n);

        try {
            return $this->smarty->fetch('modules/plugins.list.tpl');
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

        // The add page is reached from the plugin manager and has to lead back to it.
        $this->addPageFunctionsMenuItem(
            'plugin_add_back',
            $gL10n->get('SYS_BACK'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php'),
            'bi-arrow-left-circle-fill'
        );

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

        $this->addActionJavascript();

        /*
         * The catalogue is read from the cache, so this costs nothing on most requests. When it
         * cannot be read the panel says so and offers a refresh instead of showing an empty table
         * that looks like a store with nothing in it.
         */
        $store = array();
        foreach (PluginStore::getPlugins() as $entry) {
            $name = Language::translateIfTranslationStrId((string)($entry['name'] ?? $entry['id']));

            $row = array(
                'id' => (string)$entry['id'],
                'name' => $name,
                'description' => Language::translateIfTranslationStrId(PluginStore::getDescription($entry)),
                'author' => (string)($entry['author'] ?? ''),
                'url' => (string)($entry['url'] ?? ''),
                'icon' => (string)($entry['icon'] ?? 'bi-puzzle'),
                'version' => (string)$entry['release']['version'],
                'installed' => (bool)$entry['installed'],
                'installedVersion' => (string)$entry['installedVersion']
            );

            // A plugin this installation already has is shown, but there is nothing to do to it here
            // - the plugin manager is where it is enabled, configured and removed.
            if (!$row['installed']) {
                // The same action shape the plugin list uses, so list.functions.tpl renders it.
                $row['actions'] = array(array(
                    'dataHref' => $this->actionScript((string)$entry['id'], 'store_install'),
                    'dataMessage' => $gL10n->get('SYS_WANT_INSTALL_FROM_STORE', array($name)),
                    'icon' => 'bi bi-plus-circle-fill',
                    'tooltip' => $gL10n->get('SYS_PLUGIN_STORE_INSTALL')
                ));
            }

            $store[] = $row;
        }

        if ($store !== array()) {
            $dataTables = new DataTables($this, 'adm_plugin_store_table');
            $dataTables->disableColumnsSort(array(3));
            $dataTables->createJavascript(count($store), 4);
        }

        $this->smarty->assign('store', $store);
        $this->smarty->assign('storeError', PluginStore::getError());
        $this->smarty->assign('storeUrl', PluginStore::getUrl());
        $this->smarty->assign('storeRefreshHref', $this->actionScript('', 'store_refresh'));

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
     * The JavaScript that performs the operations of the plugin manager.
     *
     * Installing, updating and removing all change what the other rows may offer, so those still
     * reload the whole page rather than one row being patched. Enabling and disabling is different:
     * an administrator switches several plugins in a row, so it neither asks for confirmation - there
     * is nothing destructive about it - nor reloads the page: the toggle flips its own icon and dims
     * its row before the request even answers, so the click itself already looks like something
     * happened, and once the answer arrives it re-fetches the list content and replaces it, which
     * still lets a plugin move to another group or change its actions.
     *
     * A plugin whose own pages get it a menu entry (Plugin::wantsMenuEntry()) is different again:
     * enabling or disabling it can add, remove or change that entry, which nothing outside
     * #adm_plugins_list would otherwise learn about, so its toggle is reloadTogglePlugin() instead -
     * still dims the row for the same immediate feedback, but then does a genuine
     * Post/Redirect/Get (redirectPost(), the same mechanism admidio-send-csrf-token elements use)
     * rather than an AJAX call, so the server's response is a normal full-page navigation.
     * @return void
     * @throws Exception
     */
    private function addActionJavascript(): void
    {
        $this->addJavascript('
            function parsePluginResponse(data) {
                let status = "error";
                let message = "";

                try {
                    const parsed = JSON.parse(data);
                    status = parsed.status;
                    if (typeof parsed.message !== "undefined") {
                        message = parsed.message;
                    }
                } catch (e) {
                    message = data;
                }

                return { status: status, message: message };
            }

            function showPluginActionMessage(status, message) {
                const messageText = $("#adm_status_message");

                if (status === "success") {
                    messageText.html("<div class=\"alert alert-success\"><i class=\"bi bi-check-lg\"></i> "
                        + message + "</div>");
                } else {
                    if (message.length === 0) {
                        message = "Error: Undefined error occurred!";
                    }
                    messageText.html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill\"></i> "
                        + message + "</div>");
                }
            }

            function callPluginAction(url, csrfToken) {
                $.post(url, { "adm_csrf_token": csrfToken }, function(data) {
                    const result = parsePluginResponse(data);
                    showPluginActionMessage(result.status, result.message);

                    setTimeout(function() {
                        $("#adm_modal").modal("hide");
                        $("#adm_modal_messagebox").modal("hide");
                        location.reload();
                    }, 2000);
                });
            }

            function togglePlugin(link, url, csrfToken, refreshUrl) {
                const $link = $(link);
                const $row = $link.closest("tr, .card");

                // A row already mid-flight ignores a second click rather than firing another request.
                if ($row.css("pointer-events") === "none") {
                    return;
                }

                const $icon = $link.find("i");

                // Optimistic update: flip the icon and dim the row right away, so the click gives
                // immediate feedback instead of looking unresponsive until the request comes back.
                $icon.toggleClass("bi-toggle-on bi-toggle-off");
                $link.toggleClass("text-success text-secondary");
                $row.css({ "opacity": 0.5, "pointer-events": "none" });

                function revert() {
                    $icon.toggleClass("bi-toggle-on bi-toggle-off");
                    $link.toggleClass("text-success text-secondary");
                    $row.css({ "opacity": "", "pointer-events": "" });
                }

                $.post(url, { "adm_csrf_token": csrfToken }, function(data) {
                    const result = parsePluginResponse(data);
                    showPluginActionMessage(result.status, result.message);

                    if (result.status === "success") {
                        $.get(refreshUrl, function(html) {
                            $("#adm_plugins_list").replaceWith(html);
                        });
                    } else {
                        revert();
                    }
                }).fail(revert);
            }

            function reloadTogglePlugin(link, url, csrfToken) {
                const $row = $(link).closest("tr, .card");

                // A row already mid-flight ignores a second click; the page is about to navigate
                // away anyway once the request lands.
                if ($row.css("pointer-events") === "none") {
                    return;
                }

                // No optimistic icon flip - the page is about to reload either way, so dimming the
                // row is the only feedback that is worth giving here.
                $row.css({ "opacity": 0.5, "pointer-events": "none" });

                redirectPost(url, { "adm_csrf_token": csrfToken });
            }
        ');
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

        /*
         * The name and the description of a plugin are usually keys of the plugin's own language
         * file, and that file is only on the search path once the plugin has been loaded. This list
         * shows plugins that are not loaded - available, disabled, waiting for an update - so it has
         * to put their language file there itself, or it would print the raw keys.
         */
        if ($plugin !== null) {
            PluginLoader::registerLanguages($plugin);
        }

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
            'notes' => $this->getNotes($plugin),
            'actions' => $this->getActions($id, $plugin, $state)
        );
    }

    /**
     * The switch of the state column, which shows whether the plugin is enabled and flips it.
     *
     * It shows the state the plugin is in, not the operation a click performs, so that the column
     * can be read at a glance like a row of switches.
     *
     * A plugin with its own pages gets a menu entry (Plugin::wantsMenuEntry()) that enabling or
     * disabling it can add, remove or change, which nothing outside this page would otherwise learn
     * about - so its switch does a real Post/Redirect/Get instead of the AJAX call every other
     * switch uses: see toggleScript().
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
            'dataHref' => $this->toggleScript($id, $enabled, $plugin->wantsMenuEntry())
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

    /**
     * What an administrator should know about a plugin that is working as intended.
     *
     * These are not diagnostics: nothing is wrong with the plugin. A plugin whose module is switched
     * off is installed, enabled and correct, it simply has nothing to show - which looks like a
     * broken plugin unless somebody says so.
     * @param Plugin|null $plugin
     * @return array<int,string> Translated messages, empty when there is nothing to say.
     * @throws Exception
     */
    private function getNotes(?Plugin $plugin): array
    {
        global $gL10n, $gDb;

        if ($plugin === null) {
            return array();
        }

        $notes = array();

        foreach ($plugin->getDisabledModules() as $module) {
            /*
             * The component of a module carries its name as a language key, so the note names the
             * module the way the rest of Admidio does rather than by its directory.
             */
            $component = new Component($gDb);
            $found = $component->readDataByColumns(array('com_name_intern' => strtoupper($module)));
            $name = $found ? Language::translateIfTranslationStrId((string)$component->getValue('com_name')) : $module;

            $notes[] = $gL10n->get('SYS_PLUGIN_MODULE_DISABLED', array($name));
        }

        return $notes;
    }

    /**
     * Why a plugin cannot be used, or why its pages are not published.
     *
     * A structural manifest problem (Plugin::$error set) is shown translated, via
     * translatePluginError(). An unmet version, PHP, extension or plugin-dependency requirement -
     * which is all Plugin::checkRequirements() can still find once the manifest itself is sound -
     * is shown as the English diagnostic it is; it names a version string or an extension name that
     * is not worth translating around.
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

        /*
         * checkRequirements() leads with $plugin->error when the manifest itself is the problem,
         * and in that case it never finds anything else: read() leaves requires empty for every
         * manifest error. So a structural problem is translated once here instead of calling
         * checkRequirements() at all, which would otherwise report the same problem twice.
         */
        $diagnostics = $plugin->error !== null
            ? array($this->translatePluginError($plugin))
            : $plugin->checkRequirements(PluginRegistry::getEnabledVersions());

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
     * The translated message for a structural manifest problem (Plugin::$error set).
     *
     * Plugin::$errorCode names which of a small, stable set of structural problems it is; anything
     * else - an invalid JSON manifest, a missing entry file, a missing version, an unsafe autoload
     * mapping - shares one generic translated wrapper around the English detail, the same pattern
     * SYS_PLUGIN_PACKAGE_BROKEN_MANIFEST already uses for the equivalent problem in an uploaded
     * archive.
     * @param Plugin $plugin A plugin with $plugin->error !== null.
     * @return string
     */
    private function translatePluginError(Plugin $plugin): string
    {
        global $gL10n;

        return match ($plugin->errorCode) {
            Plugin::ERROR_INVALID_ID => $gL10n->get('SYS_PLUGIN_INVALID_ID', array($plugin->id)),
            Plugin::ERROR_NO_MANIFEST => $gL10n->get('SYS_PLUGIN_NO_MANIFEST', array(Plugin::MANIFEST_FILE)),
            Plugin::ERROR_INVALID_MANIFEST => $gL10n->get('SYS_PLUGIN_INVALID_MANIFEST', array(Plugin::MANIFEST_FILE)),
            default => $gL10n->get('SYS_PLUGIN_INCOMPATIBLE', array((string)$plugin->error)),
        };
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

        /*
         * There are two reasons a plugin can need updating - its files are newer than its
         * database, or the store offers newer files - and an administrator should not have to
         * know which. One action covers both, and the route does whatever is actually needed.
         */
        if ($state === PluginRegistry::STATE_UPDATE
            || (PluginRegistry::isInstalled($id) && PluginStore::getNewerRelease($id) !== null)) {
            $actions[] = $this->action($id, 'update', 'bi bi-arrow-clockwise', 'SYS_PLUGIN_UPDATE', 'SYS_WANT_UPDATE_PLUGIN');
        }


        /*
         * The history of this one plugin. Its component record, its settings and its menu entries
         * all name that record as their related object, so the changelog can be filtered down to it -
         * installing, enabling, configuring and removing the plugin in one list.
         */
        $componentUuid = PluginRegistry::getComponentUuid($id);
        if ($componentUuid !== '') {
            $history = ChangelogService::displayHistoryButtonTable(
                array('components', 'preferences', 'menu'),
                true,
                array('related_id' => $componentUuid)
            );
            if ($history !== array()) {
                $actions[] = $history;
            }
        }
        /*
         * A plugin of the Admidio distribution has no remove action at all: its files come back with
         * the next core update, so offering to delete them would promise something Admidio cannot
         * keep. Disabling is what takes such a plugin out of use.
         */
        if (PluginRegistry::isBuiltIn($id)) {
            /*
             * An icon that is simply absent tells nobody why. This one does nothing when it is
             * clicked; it is there so that the tooltip can say what is going on.
             */
            $actions[] = array(
                'url' => 'javascript:void(0);',
                'icon' => 'bi bi-shield-lock',
                'tooltip' => $gL10n->get('SYS_PLUGIN_BUILT_IN')
            );
        } else {
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

    /**
     * The JavaScript call that flips a plugin's state, for the onclick of the unconfirmed toggle.
     *
     * A plugin with a menu entry of its own (Plugin::wantsMenuEntry()) needs a real page reload to
     * show a change to it - reloadTogglePlugin() dims the row for feedback and then does a genuine
     * Post/Redirect/Get (redirectPost(), the same mechanism admidio-send-csrf-token elements use),
     * so the server's redirect is a normal full-page navigation, not an AJAX response. Every other
     * plugin keeps the AJAX call that only ever re-fetches the list fragment.
     * @param string $id ID of the plugin.
     * @param bool $enabled Whether the plugin is enabled, so the call switches it the other way.
     * @param bool $reloadsPage Whether the plugin has a menu entry and therefore needs a real reload.
     * @return string
     * @throws Exception
     */
    private function toggleScript(string $id, bool $enabled, bool $reloadsPage): string
    {
        global $gCurrentSession;

        $url = SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/plugins.php',
            array('mode' => $enabled ? 'disable' : 'enable', 'plugin' => $id)
        );
        $csrfToken = $gCurrentSession->getCsrfToken();

        if ($reloadsPage) {
            return 'reloadTogglePlugin(this, \'' . $url . '\', \'' . $csrfToken . '\')';
        }

        $refreshUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php', array('mode' => 'list_refresh'));

        return 'togglePlugin(this, \'' . $url . '\', \'' . $csrfToken . '\', \'' . $refreshUrl . '\')';
    }
}
