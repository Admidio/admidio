<?php
namespace Admidio\UI\Component;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Language;
use UnexpectedValueException;

/**
 * @brief The places of the page frame that plugins may fill: header bar, profile menu and notices
 *
 * The frame of every page - the header bar with the profile menu, the area above the content - is
 * rendered by the theme from index.tpl. Plugins add to it through three filters instead of moving
 * HTML around in the browser. Each filter receives a list and the PagePresenter of the page and
 * returns the list; it is dispatched once per page, right after **page_before_render**.
 *
 * **page_header_items** – items of the header bar, left of the profile photo (and left of the login
 * link for visitors). An item is an array with these keys:
 * - **id** (required) Stable name, lowercase letters, digits, `-` and `_`; a plugin starts it with its
 *   plugin ID. It becomes the HTML id `adm_header_item_<id>`.
 * - **label** (required unless there is an icon) The text, or a translation ID.
 * - **icon** (optional) Bootstrap icon class, e.g. `bi-translate`.
 * - **image** (optional) URL of a small image shown instead of the icon, e.g. a flag. It is scaled to
 *   the height of the text.
 * - **url** (optional) The link. An item without url and without items is shown as plain text.
 * - **popup** (optional) Open the url in the modal dialog of the page instead of following it; the url
 *   then answers with the content of the dialog (modal-header, modal-body), like the other popups of
 *   Admidio.
 * - **tooltip** (optional) Title of the item, or a translation ID.
 * - **items** (optional) A list of items of the same shape (without further items): the item becomes a
 *   dropdown. A child with **header** => true is a heading, one with **divider** => true a separator.
 * - **active** (optional, child items) Mark the current choice.
 * - **class** (optional) Additional CSS classes, e.g. `text-bg-warning`.
 * - **sequence** (optional, default 100) Lower values are shown further left.
 *
 * **page_user_menu_items** – entries of the profile menu, between "My profile" and "Logout". Same
 * keys as a header item, without **items**. Only filtered for a logged-in user.
 *
 * **page_notices** – banners at the top of the content area, e.g. "You are acting as …". A notice is
 * an array with **id**, **html** (trusted HTML of the plugin; it has to encode what it did not write
 * itself) and **type** (a Bootstrap alert type, default `info`), optionally **sequence**.
 *
 * Texts and URLs are encoded by the templates. A filter that fails or answers with something that is
 * not a list of valid entries is logged and ignored: a broken plugin must not take the page frame
 * down.
 *
 * **Code example**
 * ```
 * Hooks::addFilter('page_header_items', function (array $items, PagePresenter $page): array {
 *     $items[] = array('id' => 'myplugin_help', 'label' => 'PLG_MYPLUGIN_HELP', 'icon' => 'bi-question-circle',
 *                      'url' => ADMIDIO_URL . '/plugins/myplugin/modules/help.php');
 *     return $items;
 * }, 10, 2, 'myplugin-header');
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PageExtensions
{
    public const HOOK_HEADER_ITEMS = 'page_header_items';
    public const HOOK_USER_MENU_ITEMS = 'page_user_menu_items';
    public const HOOK_NOTICES = 'page_notices';

    public const DEFAULT_SEQUENCE = 100;

    private const ALERT_TYPES = array('primary', 'secondary', 'success', 'info', 'warning', 'danger', 'light', 'dark');

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * The items of the header bar.
     * @param object $page The page that is rendered (a PagePresenter).
     * @return array<int,array<string,mixed>>
     */
    public static function getHeaderItems(object $page): array
    {
        return self::collect(self::HOOK_HEADER_ITEMS, $page, static fn(mixed $item): array => self::normalizeItem($item, true));
    }

    /**
     * The additional entries of the profile menu.
     * @param object $page The page that is rendered (a PagePresenter).
     * @return array<int,array<string,mixed>>
     */
    public static function getUserMenuItems(object $page): array
    {
        return self::collect(self::HOOK_USER_MENU_ITEMS, $page, static fn(mixed $item): array => self::normalizeItem($item, false));
    }

    /**
     * The notices above the content.
     * @param object $page The page that is rendered (a PagePresenter).
     * @return array<int,array<string,mixed>>
     */
    public static function getNotices(object $page): array
    {
        return self::collect(self::HOOK_NOTICES, $page, array(self::class, 'normalizeNotice'));
    }

    /**
     * Run one filter, check every entry and sort them by their sequence.
     * @param callable(mixed):array<string,mixed> $normalize Throws for an invalid entry.
     * @return array<int,array<string,mixed>>
     */
    private static function collect(string $hook, object $page, callable $normalize): array
    {
        global $gLogger;

        if (!Hooks::hasFilter($hook)) {
            return array();
        }

        try {
            $entries = array();
            foreach (Hooks::applyTypedFilters($hook, array(), $page) as $index => $entry) {
                try {
                    $entries[] = $normalize($entry);
                } catch (UnexpectedValueException $exception) {
                    throw new UnexpectedValueException('A ' . $hook . ' filter returned an invalid entry at position ' . $index . ': ' . $exception->getMessage());
                }
            }
        } catch (\Throwable $exception) {
            if ($gLogger instanceof \Psr\Log\LoggerInterface) {
                $gLogger->error('The page extensions could not be collected.', array('hook' => $hook, 'error' => $exception->getMessage()));
            }
            return array();
        }

        // a stable sort: entries of the same sequence keep the order of the filters
        $positions = array_keys($entries);
        array_multisort(array_column($entries, 'sequence'), SORT_NUMERIC, $positions, SORT_NUMERIC, $entries);
        return $entries;
    }

    /**
     * Check an item of the header bar or the profile menu and bring it into the form the templates use.
     * @param mixed $item
     * @param bool $allowChildren Whether the item may have **items**.
     * @return array{id:string, label:string, icon:string, image:string, url:string, popup:bool, tooltip:string, class:string, active:bool, header:bool, divider:bool, sequence:int, items:array<int,array<string,mixed>>}
     * @throws UnexpectedValueException
     */
    public static function normalizeItem(mixed $item, bool $allowChildren): array
    {
        if (!is_array($item)) {
            throw new UnexpectedValueException('an item is an array.');
        }

        $id = (string)($item['id'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id) && empty($item['divider']) && empty($item['header'])) {
            throw new UnexpectedValueException('an item needs an "id" of lowercase letters, digits, "-" and "_".');
        }

        $label = Language::translateIfTranslationStrId((string)($item['label'] ?? ''));
        $icon = (string)($item['icon'] ?? '');
        $image = (string)($item['image'] ?? '');
        if ($label === '' && $icon === '' && $image === '' && empty($item['divider'])) {
            throw new UnexpectedValueException('an item needs a "label", an "icon" or an "image".');
        }
        if ($icon !== '' && !preg_match('/^[a-z0-9 -]+$/', $icon)) {
            throw new UnexpectedValueException('the "icon" of an item is a Bootstrap icon class such as "bi-translate".');
        }
        if ($icon !== '' && !str_starts_with($icon, 'bi ')) {
            $icon = 'bi ' . $icon;
        }

        $children = array();
        if (!empty($item['items'])) {
            if (!$allowChildren || !is_array($item['items'])) {
                throw new UnexpectedValueException('only an item of the header bar may have "items", and they are a list.');
            }
            foreach ($item['items'] as $child) {
                $children[] = self::normalizeItem($child, false);
            }
        }

        return array(
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
            'image' => $image,
            'url' => (string)($item['url'] ?? ''),
            'popup' => !empty($item['popup']) && ($item['url'] ?? '') !== '',
            'tooltip' => Language::translateIfTranslationStrId((string)($item['tooltip'] ?? '')),
            'class' => preg_replace('/[^A-Za-z0-9 _-]/', '', (string)($item['class'] ?? '')),
            'active' => !empty($item['active']),
            'header' => !empty($item['header']),
            'divider' => !empty($item['divider']),
            'sequence' => (int)($item['sequence'] ?? self::DEFAULT_SEQUENCE),
            'items' => $children
        );
    }

    /**
     * Check a notice and bring it into the form the templates use.
     * @param mixed $notice
     * @return array{id:string, type:string, html:string, sequence:int}
     * @throws UnexpectedValueException
     */
    public static function normalizeNotice(mixed $notice): array
    {
        if (!is_array($notice) || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', (string)($notice['id'] ?? '')) || !isset($notice['html'])) {
            throw new UnexpectedValueException('a notice is an array with an "id" and "html".');
        }
        $type = (string)($notice['type'] ?? 'info');
        if (!in_array($type, self::ALERT_TYPES, true)) {
            throw new UnexpectedValueException('the "type" of a notice is one of ' . implode(', ', self::ALERT_TYPES) . '.');
        }

        return array(
            'id' => (string)$notice['id'],
            'type' => $type,
            'html' => (string)$notice['html'],
            'sequence' => (int)($notice['sequence'] ?? self::DEFAULT_SEQUENCE)
        );
    }
}
