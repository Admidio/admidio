<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\PreferencesPresenter;

/**
 * The preference panels of the plugins.
 *
 * A plugin that brings preferences shows them in the Admidio preferences, in one of the two tabs
 * that exist for extensions. It used to get there by being scanned for: the core looked through the
 * autoload directories of the plugin for a file called *PreferencesPresenter.php and guessed the
 * method to call from its class name. A panel is now declared, like a widget, so that a plugin says
 * what it offers instead of the core deducing it from file names.
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
 *         'group' => PluginPanel::GROUP_OVERVIEW,
 *         'create' => array(WhoIsOnlinePreferencesPresenter::class, 'createForm')
 *     );
 *
 *     return $panels;
 * });
 * ```
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
     * The tab of the plugins that add something to the overview page.
     */
    public const GROUP_OVERVIEW = 'overview_extensions';

    /**
     * The tab of every other plugin.
     */
    public const GROUP_EXTENSIONS = 'extensions';

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
                'group' => ((string)($panel['group'] ?? self::GROUP_EXTENSIONS)) === self::GROUP_OVERVIEW
                    ? self::GROUP_OVERVIEW : self::GROUP_EXTENSIONS,
                'sequence' => (int)($panel['sequence'] ?? self::DEFAULT_SEQUENCE),
                'subcards' => (bool)($panel['subcards'] ?? false),
                'create' => $panel['create']
            );
        }

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
     * The declared panels of one tab.
     * @param string $group One of the GROUP_* constants.
     * @return array<int,array<string,mixed>>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(
            self::collect(),
            static fn(array $panel): bool => $panel['group'] === $group
        ));
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
