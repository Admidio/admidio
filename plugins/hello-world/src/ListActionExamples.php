<?php

namespace AdmidioPlugin\HelloWorld;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * Examples of the **list_row_actions** filter, which lets a plugin change the action icons
 * (edit, delete, history, ...) at the end of the rows of every list.
 *
 * The filter receives the actions of one row as an array, the ID of the list (`contacts`, `menu`,
 * `plugins`, ... - see ListActions in the core for what a list ID looks like) and the data of the
 * row. It returns the actions that are shown. Each action is an array:
 *
 * - **icon** and **tooltip** - what is shown. The tooltip is also the accessible label.
 * - **id** - a stable name. Core actions have one (`edit`, `delete`, `copy`, `history`, ...), and a
 *   plugin names its own actions with its plugin ID as a prefix, so that other plugins can find them.
 * - **url**, **popup**, **dataMessage** + **dataHref**, **class**, **attributes** - what a click
 *   does. An action with none of them is purely informational.
 *
 * The first action of a row stays visible when the row has more than two actions, the others move
 * into the menu of the row. Hiding an action only removes its icon: the page behind it still checks
 * the permissions of the user.
 *
 * Every example below is a separate branch of one callback, so that each can be copied on its own.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class ListActionExamples
{
    /**
     * Callback of the **list_row_actions** filter.
     * @param array<int,array<string,mixed>> $actions The actions the row has so far.
     * @param string $listId The list the row belongs to.
     * @param array<string,mixed> $row The data of the row. For the contacts list this is the database
     *                                 row, for the other lists the data the template renders.
     * @return array<int,array<string,mixed>>
     */
    public static function adjustActions(array $actions, string $listId, array $row): array
    {
        $plugin = PluginRegistry::get(Greeting::PLUGIN_ID);
        if ($plugin === null || !($plugin->getSettingValues()['hello_world_list_actions'] ?? false)) {
            return $actions;
        }

        return self::apply($actions, $listId, $row);
    }

    /**
     * The changes themselves, separate from the check of the preference so that they can be tested.
     * @param array<int,array<string,mixed>> $actions The actions the row has so far.
     * @param string $listId The list the row belongs to.
     * @param array<string,mixed> $row The data of the row.
     * @return array<int,array<string,mixed>>
     */
    public static function apply(array $actions, string $listId, array $row): array
    {
        global $gL10n;

        // ADD an action - here one that only shows a message. A message without "dataHref" has just
        // an OK button. The name is encoded because the message is shown as HTML.
        if ($listId === 'contacts') {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $actions[] = array(
                'id' => Greeting::PLUGIN_ID . '_greet',
                'dataMessage' => SecurityUtils::encodeHTML(Greeting::getText()) . ' ' . SecurityUtils::encodeHTML($name),
                'icon' => 'bi bi-emoji-smile',
                'tooltip' => $gL10n->get('PLG_HELLO_WORLD_ACTION_GREET')
            );

            // REORDER: the first action is the one that stays visible, so moving the e-mail action to
            // the front puts it next to the menu instead of inside it.
            usort($actions, static fn(array $a, array $b) => (($b['id'] ?? '') === 'send_email') <=> (($a['id'] ?? '') === 'send_email'));
        }

        // HIDE an action: announcements cannot be copied.
        if ($listId === 'announcements') {
            $actions = array_values(array_filter($actions, static fn(array $action) => ($action['id'] ?? '') !== 'copy'));
        }

        // MODIFY an action: the delete icons of the menu entries are shown in red. A CSS class is
        // added to the ones the action already has.
        if ($listId === 'menu') {
            foreach ($actions as $index => $action) {
                if (($action['id'] ?? '') === 'delete') {
                    $actions[$index]['class'] = trim(($action['class'] ?? '') . ' text-danger');
                }
            }
        }

        // ADD an informational action: it has no target and only explains something. Here the row of
        // this plugin in the plugin manager says where the examples are.
        if ($listId === 'plugins' && ($row['id'] ?? '') === Greeting::PLUGIN_ID) {
            array_unshift($actions, array(
                'id' => Greeting::PLUGIN_ID . '_info',
                'icon' => 'bi bi-info-circle',
                'tooltip' => $gL10n->get('PLG_HELLO_WORLD_ACTION_INFO')
            ));
        }

        return $actions;
    }
}
