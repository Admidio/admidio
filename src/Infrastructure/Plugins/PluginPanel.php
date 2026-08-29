<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\UI\Presenter\PluginSettingsPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;

/**
 * The preference panels of the plugins.
 *
 * A plugin that brings preferences shows them in the Admidio preferences, in the tab its manifest
 * names. It used to get there by being scanned for: the core looked through the autoload
 * directories of the plugin for a file called *PreferencesPresenter.php and guessed the method to
 * call from its class name. A panel is now declared, like a widget, so that a plugin says what it
 * offers instead of the core deducing it from file names.
 *
 * **Code example** - the manifest of a plugin
 * ```
 * "preferences": { "section": "overview_extensions", "sequence": 20 }
 * ```
 *
 * That is all a plugin whose settings form is generated has to say. One that builds the form itself
 * registers the callback that builds it:
 *
 * **Code example** - the entry file of a plugin
 * ```
 * Hooks::addFilter(PluginPanel::HOOK, function (array $panels): array {
 *     global $gL10n;
 *
 *     $plugin = PluginRegistry::get('who-is-online');
 *     $panels[] = array(
 *         'id' => 'who_is_online',
 *         'title' => $gL10n->get($plugin->name),
 *         'icon' => $plugin->icon,
 *         'create' => array(WhoIsOnlinePreferencesPresenter::class, 'createForm')
 *     );
 *
 *     return $panels;
 * });
 * ```
 *
 * The panel takes its section and its sequence from the manifest of the plugin it is named after, so
 * only a plugin that declares several panels has to name a **section** here as well.
 *
 * The **create** callback receives the PreferencesPresenter and returns the HTML of the panel. It is
 * only called for the panel the administrator actually opens, because the preferences page fetches
 * one panel per request.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginPanel
{
    /**
     * Name of the hook the preferences page dispatches.
     */
    public const HOOK = 'preferences_panels';

    /**
     * The preferences tab a plugin lands in when it names none, or names one that does not exist.
     */
    public const SECTION_DEFAULT = 'extensions';

    /**
     * The tab of the plugins that add something to the overview page.
     */
    public const SECTION_OVERVIEW = 'overview_extensions';

    public const SECTION_SYSTEM = 'system';

    public const SECTION_LOGIN_SECURITY = 'login_security';

    public const SECTION_USER_MANAGEMENT = 'user_management';

    public const SECTION_COMMUNICATION = 'communication';

    public const SECTION_CONTENT = 'content_management';

    /**
     * Every section a plugin may put its panel in, which is every tab of the preferences page.
     *
     * A plugin is not restricted to the two tabs that carry no core panels: a module that used to be
     * part of the core keeps the tab it has always been shown in once it becomes a plugin, and an
     * allow-list would only have to be widened at every such conversion.
     * @var array<int,string>
     */
    public const SECTIONS = array(
        self::SECTION_SYSTEM,
        self::SECTION_LOGIN_SECURITY,
        self::SECTION_USER_MANAGEMENT,
        self::SECTION_COMMUNICATION,
        self::SECTION_CONTENT,
        self::SECTION_OVERVIEW,
        self::SECTION_DEFAULT
    );

    /**
     * Where a panel without an own sequence is placed inside its tab.
     */
    public const DEFAULT_SEQUENCE = 100;

    /**
     * The declared panels of this request, or **null** while they have not been asked for yet.
     * @var array<int,array<string,mixed>>|null
     */
    private static ?array $panels = null;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Ask the plugins for their preference panels, in the order they should be shown. The hook is
     * dispatched once per request, because the preferences page asks for the panels several times
     * while it builds its tabs.
     * @return array<int,array{id: string, title: string, icon: string, group: string, sequence: int, subcards: bool, create: callable}>
     */
    public static function collect(): array
    {
        if (self::$panels !== null) {
            return self::$panels;
        }

        self::$panels = array();

        foreach (Hooks::applyTypedFilters(self::HOOK, array()) as $panel) {
            if (!is_array($panel) || !isset($panel['id'], $panel['create']) || !is_callable($panel['create'])) {
                continue;
            }

            $id = self::normalizeId((string)$panel['id']);
            if ($id === '') {
                continue;
            }

            self::$panels[] = array(
                'id' => $id,
                'title' => (string)($panel['title'] ?? $id),
                'icon' => (string)($panel['icon'] ?? 'bi-puzzle'),
                'section' => self::resolveSection($panel, $id),
                'sequence' => self::resolveSequence($panel, $id),
                'subcards' => (bool)($panel['subcards'] ?? false),
                'create' => $panel['create']
            );
        }

        self::addGeneratedPanels();

        /*
         * Two panels may claim the same position, so the ID decides between them. Without that the
         * order would depend on the order the plugins happened to be loaded in.
         */
        usort(self::$panels, static function (array $first, array $second): int {
            return array($first['sequence'], $first['id']) <=> array($second['sequence'], $second['id']);
        });

        return self::$panels;
    }

    /**
     * Give every loaded plugin that declared settings but no panel a panel built from its manifest.
     *
     * A plugin declares each setting with a type, a label and a description, which is everything a
     * form needs, so a plugin that wants nothing more should not have to write a presenter to make
     * its settings reachable. A plugin that did declare a panel keeps it: the generated one is only
     * added where none exists.
     * @return void
     */
    private static function addGeneratedPanels(): void
    {
        $declared = array_column(self::$panels, 'id');

        foreach (PluginLoader::getLoaded() as $plugin) {
            $id = self::normalizeId($plugin->id);
            if ($id === '' || $plugin->settings === array() || in_array($id, $declared, true)) {
                continue;
            }

            self::$panels[] = array(
                'id' => $id,
                'title' => Language::translateIfTranslationStrId($plugin->name),
                'icon' => $plugin->icon === '' ? 'bi-puzzle' : $plugin->icon,
                'section' => self::sectionOf($plugin->preferences['section']),
                'sequence' => $plugin->preferences['sequence'] ?? self::DEFAULT_SEQUENCE,
                'subcards' => false,
                'create' => static fn(PreferencesPresenter $presenter): string
                    => PluginSettingsPresenter::createForm($plugin, $id, $presenter)
            );
        }
    }

    /**
     * The declared panels of one tab.
     * @param string $section One of the SECTION_* constants.
     * @return array<int,array<string,mixed>>
     */
    public static function inSection(string $section): array
    {
        return array_values(array_filter(
            self::collect(),
            static fn(array $panel): bool => $panel['section'] === $section
        ));
    }

    /**
     * The tab one declared panel belongs in.
     *
     * A panel may name its section itself, which is what a plugin does that declares more than one.
     * A plugin that declares a single panel does not have to repeat in code what its manifest
     * already says, so the manifest of the plugin the panel is named after answers for it.
     * @param array<string,mixed> $panel The panel as the plugin declared it.
     * @param string $id The normalized panel ID.
     * @return string
     */
    private static function resolveSection(array $panel, string $id): string
    {
        $declared = (string)($panel['section'] ?? '');

        if ($declared === '') {
            $declared = self::pluginFor($id)?->preferences['section'] ?? '';
        }

        return self::sectionOf($declared);
    }

    /**
     * Where one declared panel sits inside its tab, by the same rule as its section.
     * @param array<string,mixed> $panel
     * @param string $id The normalized panel ID.
     * @return int
     */
    private static function resolveSequence(array $panel, string $id): int
    {
        if (isset($panel['sequence'])) {
            return (int)$panel['sequence'];
        }

        return self::pluginFor($id)?->preferences['sequence'] ?? self::DEFAULT_SEQUENCE;
    }

    /**
     * The section of that name, or the general tab.
     *
     * A section that does not exist is not an error: the preferences page may drop a tab between two
     * Admidio versions, and a plugin whose settings then became unreachable would be worse than one
     * whose settings moved.
     * @param string $section
     * @return string One of the SECTIONS.
     */
    private static function sectionOf(string $section): string
    {
        $section = self::normalizeId($section);

        return in_array($section, self::SECTIONS, true) ? $section : self::SECTION_DEFAULT;
    }

    /**
     * The loaded plugin a panel of this ID belongs to, or **null** if no loaded plugin is named
     * after it.
     * @param string $id A normalized panel ID.
     * @return Plugin|null
     */
    private static function pluginFor(string $id): ?Plugin
    {
        foreach (PluginLoader::getLoaded() as $plugin) {
            if (self::normalizeId($plugin->id) === $id) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * One declared panel by its ID, or **null** if no plugin declared it.
     * @param string $id
     * @return array<string,mixed>|null
     */
    public static function get(string $id): ?array
    {
        foreach (self::collect() as $panel) {
            if ($panel['id'] === $id) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * Build the HTML of one declared panel.
     * @param string $id
     * @param PreferencesPresenter $presenter The preferences page, so that the panel can build its
     *                                        form and reach the Smarty object of the current theme.
     * @return string
     * @throws Exception
     */
    public static function create(string $id, PreferencesPresenter $presenter): string
    {
        $panel = self::get($id);
        if ($panel === null) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return (string)call_user_func($panel['create'], $presenter);
    }

    /**
     * A panel ID appears in the URL of the preferences page and in the ID of an HTML element, so it
     * is restricted to the form the core panel names use. A plugin ID is turned into it by
     * replacing the hyphens.
     * @param string $id
     * @return string An empty string if nothing usable is left.
     */
    public static function normalizeId(string $id): string
    {
        return (string)preg_replace('/[^a-z0-9_]/', '', str_replace('-', '_', strtolower($id)));
    }

    /**
     * Forget the declared panels, so that the hook is dispatched again. Only tests need this.
     * @return void
     */
    public static function reset(): void
    {
        self::$panels = null;
    }
}
