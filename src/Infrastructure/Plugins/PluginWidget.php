<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Hooks\Hooks;
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
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
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
