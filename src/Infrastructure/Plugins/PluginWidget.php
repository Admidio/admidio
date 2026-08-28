<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Language;
use Admidio\UI\Presenter\PagePresenter;

/**
 * The widgets of the overview page.
 *
 * The overview used to include one PHP file per plugin through a Smarty function, so a plugin could
 * only contribute there by being a file at a place the core knew. A widget is now an ordinary hook:
 * the overview asks for the widgets, and every plugin that wants one adds it.
 *
 * **Code example** - the entry file of a plugin
 * ```
 * Hooks::addFilter(PluginWidget::HOOK, function (array $widgets, PagePresenter $page): array {
 *     global $gValidLogin;
 *
 *     if (!$gValidLogin) {
 *         return $widgets;   // a widget that decides not to appear simply does not add itself
 *     }
 *
 *     $plugin = PluginRegistry::get('birthday');
 *     $widgets[] = array(
 *         'id' => $plugin->id,
 *         'name' => $gL10n->get($plugin->name),
 *         'sequence' => 20,
 *         'html' => PluginWidget::render($page, $plugin, 'plugin.birthday.tpl', array('birthdays' => $rows))
 *     );
 *
 *     return $widgets;
 * });
 * ```
 *
 * A plugin that follows the Admidio conventions - one widget, an **_overview_sequence** preference
 * for its position and a **_plugin_enabled** preference for who may see it - does not write that
 * hook itself but calls register(), which adds it and declares the widget at the same time. The
 * declaration is what the preferences page lists, so a widget that is switched off is still shown
 * there and can be switched on again.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginWidget
{
    /**
     * Name of the hook the overview page dispatches.
     */
    public const HOOK = 'overview_widgets';

    /**
     * Where a widget without an own sequence is placed.
     */
    public const DEFAULT_SEQUENCE = 100;

    /**
     * Values of the **_plugin_enabled** preference of a widget: nobody sees it.
     */
    public const ACCESS_NOBODY = 0;

    /**
     * Values of the **_plugin_enabled** preference of a widget: everybody sees it.
     */
    public const ACCESS_EVERYBODY = 1;

    /**
     * Values of the **_plugin_enabled** preference of a widget: only a logged-in user sees it.
     */
    public const ACCESS_REGISTERED_USERS = 2;

    /**
     * The widgets that were declared with register(), as pluginId => declaration.
     * @var array<string,array<string,mixed>>
     */
    private static array $declarations = array();

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Declare the widget of a plugin and add the hook that renders it.
     *
     * This is the short form of the hook above for a plugin that follows the Admidio conventions.
     * The render callback receives the overview page and the plugin and returns the HTML of the
     * widget, or an empty string for a widget that has nothing to show.
     * @param Plugin $plugin
     * @param callable $render callable(PagePresenter, Plugin): string
     * @param array<string,mixed> $options
     *        - **name**                string  Heading of the widget. Default: the name of the
     *                                          plugin, translated if it is a language string ID.
     *        - **icon**                string  Default: the icon of the plugin.
     *        - **sequencePreference**  string  Preference that holds the position of the widget.
     *                                          Default: **&lt;plugin id&gt;_overview_sequence**.
     *        - **enabledPreference**   string  Preference that decides who sees the widget, with the
     *                                          ACCESS_* values. Default:
     *                                          **&lt;plugin id&gt;_plugin_enabled**.
     *        - **sequence**            int     Position of a widget whose preference has no value.
     * @return void
     */
    public static function register(Plugin $plugin, callable $render, array $options = array()): void
    {
        $name = str_replace('-', '_', $plugin->id);

        $declaration = array(
            'id' => $plugin->id,
            'name' => (string)($options['name'] ?? Language::translateIfTranslationStrId($plugin->name)),
            'icon' => (string)($options['icon'] ?? $plugin->icon),
            'sequencePreference' => (string)($options['sequencePreference'] ?? $name . '_overview_sequence'),
            'enabledPreference' => (string)($options['enabledPreference'] ?? $name . '_plugin_enabled'),
            'sequence' => (int)($options['sequence'] ?? self::DEFAULT_SEQUENCE)
        );

        self::$declarations[$plugin->id] = $declaration;

        Hooks::addFilter(
            self::HOOK,
            static function (array $widgets, PagePresenter $page) use ($plugin, $render, $declaration): array {
                if (!self::isVisible($declaration['enabledPreference'])) {
                    return $widgets;
                }

                $html = (string)$render($page, $plugin);
                if ($html === '') {
                    return $widgets;
                }

                $widgets[] = array(
                    'id' => $declaration['id'],
                    'name' => $declaration['name'],
                    'sequence' => self::getSequence($declaration['sequencePreference'], $declaration['sequence']),
                    'html' => $html
                );

                return $widgets;
            },
            self::DEFAULT_SEQUENCE,
            2,
            $plugin->id
        );
    }

    /**
     * The widgets that were declared with register(), as pluginId => declaration.
     *
     * A declaration exists as soon as the plugin is loaded, whether the widget is visible in this
     * request or not, so the preferences page can list and order all of them.
     * @return array<string,array{id: string, name: string, icon: string, sequencePreference: string, enabledPreference: string, sequence: int}>
     */
    public static function getDeclarations(): array
    {
        return self::$declarations;
    }

    /**
     * Who may see a widget, as one of the ACCESS_* values of its **_plugin_enabled** preference.
     * @param string $preference Name of the preference.
     * @return int
     */
    public static function getAccess(string $preference): int
    {
        global $gSettingsManager;

        if (!isset($gSettingsManager) || !$gSettingsManager->has($preference)) {
            return self::ACCESS_EVERYBODY;
        }

        return $gSettingsManager->getInt($preference);
    }

    /**
     * Whether the current visitor may see a widget, according to its **_plugin_enabled** preference.
     * @param string $preference Name of the preference that holds an ACCESS_* value.
     * @return bool
     */
    public static function isVisible(string $preference): bool
    {
        global $gValidLogin;

        $access = self::getAccess($preference);

        return $access === self::ACCESS_EVERYBODY
            || ($access === self::ACCESS_REGISTERED_USERS && !empty($gValidLogin));
    }

    /**
     * The position a widget is placed at, from its own preference.
     * @param string $preference Name of the preference that holds the position.
     * @param int $default Position of a widget whose preference has no value yet.
     * @return int
     */
    public static function getSequence(string $preference, int $default = self::DEFAULT_SEQUENCE): int
    {
        global $gSettingsManager;

        if (!isset($gSettingsManager) || !$gSettingsManager->has($preference)) {
            return $default;
        }

        return $gSettingsManager->getInt($preference);
    }

    /**
     * Forget the declared widgets. Only tests need this; the declarations of a request end with it.
     * @return void
     */
    public static function reset(): void
    {
        self::$declarations = array();
    }

    /**
     * Ask the plugins for their widgets and return them in the order they should be shown.
     * @param PagePresenter $page The overview page, so that a widget can render a template with the
     *                            template directories of the current theme already in place.
     * @return array<int,array{id: string, name: string, sequence: int, html: string}>
     */
    public static function collect(PagePresenter $page): array
    {
        $widgets = array();

        foreach (Hooks::applyTypedFilters(self::HOOK, array(), $page) as $widget) {
            if (!is_array($widget) || !isset($widget['id'], $widget['html'])) {
                continue;
            }

            $widgets[] = array(
                'id' => (string)$widget['id'],
                'name' => (string)($widget['name'] ?? ''),
                'sequence' => (int)($widget['sequence'] ?? self::DEFAULT_SEQUENCE),
                'html' => (string)$widget['html']
            );
        }

        /*
         * Two widgets may claim the same position, so the ID decides between them. Without that the
         * order would depend on the order the plugins happened to be loaded in.
         */
        usort($widgets, static function (array $first, array $second): int {
            return array($first['sequence'], $first['id']) <=> array($second['sequence'], $second['id']);
        });

        return $widgets;
    }

    /**
     * Render a template of a plugin for the overview page.
     *
     * The template is fetched through the Smarty object of the page, so the template directories of
     * the theme and its fallback are already registered and a theme can override the template of a
     * plugin.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @param string $template File name of the template, e.g. **plugin.birthday.tpl**.
     * @param array<string,mixed> $variables Variables the template should receive.
     * @return string
     * @throws \Smarty\Exception
     */
    public static function render(PagePresenter $page, Plugin $plugin, string $template, array $variables = array()): string
    {
        $smarty = $page->getSmartyTemplate();

        $templates = $plugin->getDirectory(Plugin::DIR_TEMPLATES);
        if ($templates !== null) {
            $smarty->addTemplateDir($templates);
        }

        foreach ($variables as $name => $value) {
            $smarty->assign($name, $value);
        }

        return $smarty->fetch($template);
    }
}
