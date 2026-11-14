<?php
namespace Admidio\UI\Component;

use Admidio\Hooks\Hooks;
use Smarty\Template;
use UnexpectedValueException;

/**
 * @brief The action icons of one row of a list, and the hook that lets plugins change them
 *
 * Every list that shows action icons (edit, delete, history, ...) builds an array of action items and
 * hands it to the template sys-template-parts/list.functions.tpl. That template calls
 * ListActions::filter() before it renders the items, so a plugin can add, hide, modify and reorder
 * the actions of any list through the **list_row_actions** filter.
 *
 * An action item is an array with these keys:
 * - **icon** (required) CSS classes of the icon, e.g. `bi bi-pencil-square`
 * - **tooltip** (required) The translated text of the tooltip and the accessible label. It is empty only
 *   for an invisible placeholder (id `spacer`) that keeps the icons of the rows aligned.
 * - **id** (optional) A stable name of the action, e.g. `edit` or `delete`. Plugins use it to find a
 *   core action; a plugin gives its own actions an ID that starts with the plugin ID.
 * - **url**, **popup**, **dataMessage** + **dataHref**, **class**, **attributes** (optional) What
 *   happens on a click. An item without any of them is purely informational: it shows its icon and
 *   tooltip and does nothing.
 *
 * The first item of a row is the one that stays visible when the row has more than two actions, the
 * others move into the menu of the row. Reordering the items therefore also decides which one is
 * shown directly.
 *
 * Hiding an action only removes its icon. The page behind the action still checks the permissions
 * of the user, as it always did.
 *
 * **Code example**
 * ```
 * // hide the delete icon of the menu entries and add an own action to the contacts
 * Hooks::addFilter('list_row_actions', function (array $actions, string $listId, array $row): array {
 *     if ($listId === 'menu') {
 *         return array_values(array_filter($actions, fn($action) => ($action['id'] ?? '') !== 'delete'));
 *     }
 *     if ($listId === 'contacts') {
 *         $actions[] = array('id' => 'myplugin_print', 'url' => '/print.php?user=' . $row['usr_uuid'],
 *                            'icon' => 'bi bi-printer', 'tooltip' => 'Print');
 *     }
 *     return $actions;
 * });
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ListActions
{
    public const HOOK = 'list_row_actions';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Let the **list_row_actions** filters change the actions of one row.
     * @param string $listId Stable name of the list, e.g. `contacts`, `menu` or `plugins`.
     *                       An empty ID means the list takes no part in the hook; the actions are returned unchanged.
     * @param array<int,array<string,mixed>> $actions The action items the row has so far.
     * @param array<string,mixed> $row The data of the row, as context for the filters. For contacts this is
     *                                 the database row, for the other lists the array the template renders.
     * @return array<int,array<string,mixed>> The action items to render, with consecutive keys.
     * @throws UnexpectedValueException If a filter returned something that is not a list of action items.
     */
    public static function filter(string $listId, array $actions, array $row = array()): array
    {
        if ($listId === '' || !Hooks::hasFilter(self::HOOK)) {
            return $actions;
        }

        $filtered = Hooks::applyTypedFilters(self::HOOK, array_values($actions), $listId, $row);

        foreach ($filtered as $index => $action) {
            if (!is_array($action) || empty($action['icon']) || !isset($action['tooltip'])) {
                throw new UnexpectedValueException(
                    'A ' . self::HOOK . ' filter for "' . $listId . '" returned an invalid action at position '
                    . $index . ': an action is an array with at least an "icon" and a "tooltip".'
                );
            }
        }

        return array_values($filtered);
    }

    /**
     * Smarty function that applies {@see ListActions::filter()} inside a template.
     *
     * **Code example**
     * ```
     * {list_actions actions=$data.actions listId=$listId row=$row assign='rowActions'}
     * ```
     * @param array<string,mixed> $params **actions** (the items), **listId**, **row** and **assign** (the name
     *                                    of the template variable that receives the filtered items).
     * @param Template $template The Smarty template the function is used in.
     * @return void
     */
    public static function smartyFunction(array $params, Template $template): void
    {
        if (empty($params['assign'])) {
            throw new UnexpectedValueException('Smarty function list_actions: missing "assign" parameter');
        }

        $template->assign($params['assign'], self::filter(
            (string)($params['listId'] ?? ''),
            (array)($params['actions'] ?? array()),
            (array)($params['row'] ?? array())
        ));
    }
}
