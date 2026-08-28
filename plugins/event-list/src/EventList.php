<?php

namespace AdmidioPlugin\EventList;

use Admidio\Events\Entity\Event;
use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\EventList\Presenter\EventListPreferencesPresenter;

use Exception;

/**
 ***********************************************************************************************
 * Event list
 *
 * Plugin that lists the latest events in a slim interface and
 * can thus be ideally used in an overview page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class EventList
{
    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'event-list';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 7;

    /**
     * The value a category list has as long as nobody narrowed it down: every visible category.
     */
    private const ALL_CATEGORIES = array('All');

    /**
     * Announce the widget and the preferences panel. This is what plugin.php calls.
     * @return void
     */
    public static function register(): void
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return;
        }

        PluginWidget::register($plugin, array(self::class, 'renderWidget'), array(
            'sequence' => self::DEFAULT_SEQUENCE
        ));

        Hooks::addFilter(
            PluginPanel::HOOK,
            static function (array $panels) use ($plugin): array {
                global $gL10n;

                $panels[] = array(
                    'id' => PluginPanel::normalizeId($plugin->id),
                    'title' => $gL10n->get($plugin->name),
                    'icon' => $plugin->icon,
                    'group' => PluginPanel::GROUP_OVERVIEW,
                    'sequence' => self::DEFAULT_SEQUENCE,
                    'create' => array(EventListPreferencesPresenter::class, 'createForm')
                );

                return $panels;
            },
            PluginPanel::DEFAULT_SEQUENCE,
            1,
            $plugin->id
        );
    }

    /**
     * The settings of the plugin, with the category list resolved.
     *
     * A category list that nobody narrowed down holds the sentinel "All", which means every category
     * the current user may see and is turned into the actual category IDs here.
     * @param Plugin $plugin
     * @return array<string,mixed>
     * @throws Exception
     */
    public static function getConfig(Plugin $plugin): array
    {
        global $gCurrentUser;

        $config = $plugin->getSettingValues();

        if (($config['event_list_displayed_categories'] ?? null) === self::ALL_CATEGORIES) {
            $config['event_list_displayed_categories'] = $gCurrentUser->getAllVisibleCategories('EVT');
        }

        return $config;
    }

    /**
     * Build the widget of the overview page. Whether it is shown at all was decided before this is
     * called, by the preference **event_list_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin): string
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        $variables = array('name' => $plugin->id, 'message' => '', 'events' => array());
        $module = $gSettingsManager->getInt('events_module_enabled');

        if ($module === 0) {
            $variables['message'] = $gL10n->get('SYS_MODULE_DISABLED');
        } elseif ($module === 1 || ($module === 2 && $gValidLogin)) {
            $eventsArray = self::getEventsData(self::getConfig($plugin));
            if (!empty($eventsArray)) {
                $variables['events'] = $eventsArray;
            } else {
                $variables['message'] = $gL10n->get('SYS_NO_ENTRIES');
            }
        } else {
            $variables['message'] = $gL10n->get('PLG_EVENT_LIST_NO_ENTRIES_VISITORS');
        }

        return $plugin->renderTemplate($page, 'plugin.event-list.tpl', $variables);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<int,array<string,mixed>>
     * @throws Exception
     */
    private static function getEventsData(array $config) : array
    {
        global $gSettingsManager, $gCurrentUser, $gDb, $gL10n;

        if (!is_array($config['event_list_displayed_categories']) || empty($config['event_list_displayed_categories'])) {
            $plgSqlCategories = '';
        } else {
            $plgSqlCategories = ' AND cat_id IN (' . Database::getQmForValues($config['event_list_displayed_categories']) . ') ';
        }

        // read events from database
        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('EVT'));

        $sql = 'SELECT cat.*, evt.*
                  FROM ' . TBL_EVENTS . ' AS evt
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat_id = dat_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($catIdParams) . ')
                   AND dat_end >= ? -- DATETIME_NOW
                       ' . $plgSqlCategories . '
              ORDER BY dat_begin
                 LIMIT ' . $config['event_list_events_count'];

        $pdoStatement = $gDb->queryPrepared($sql, array_merge($catIdParams, array(DATETIME_NOW), $config['event_list_displayed_categories']));
        $plgEventsList = $pdoStatement->fetchAll();

        $eventsArray = array();

        if ($pdoStatement->rowCount() > 0) {
            // get events data
            $plgEvent = new Event($gDb);

            foreach ($plgEventsList as $plgRow) {
                $plgEvent->clear();
                $plgEvent->setArray($plgRow);

                if ($config['event_list_chars_before_linebreak'] > 0) {
                    // Interrupt words of headline if they are too long
                    $plgNewHeadline = '';

                    $plgWords = explode(' ', $plgEvent->getValue('dat_headline'));

                    foreach ($plgWords as $plgValue) {
                        if (strlen($plgValue) > $config['event_list_chars_before_linebreak']) {
                            $plgNewHeadline .= ' ' . substr($plgValue, 0, $config['event_list_chars_before_linebreak']) . '-<br />' .
                                substr($plgValue, $config['event_list_chars_before_linebreak']);
                        } else {
                            $plgNewHeadline .= ' ' . $plgValue;
                        }
                    }
                } else {
                    $plgNewHeadline = $plgEvent->getValue('dat_headline');
                }

                // show preview text
                if ($config['event_list_show_full_description']) {
                    $plgNewDescription = $plgEvent->getValue('dat_description');
                } elseif ($config['event_list_show_preview_chars'] > 0) {
                    // remove all html tags except some format tags
                    $plgNewDescription = strip_tags($plgEvent->getValue('dat_description'));

                        // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                        $plgNewDescription = substr($plgNewDescription, 0, $config['event_list_show_preview_chars'] + 15);
                        $plgNewDescription = substr($plgNewDescription, 0, strrpos($plgNewDescription, ' ')) . '
                            <a class="admidio-icon-link" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MORE') . '"
                                href="' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/events.php',
                                    array('view' => 'detail', 'dat_uuid' => $plgEvent->getValue('dat_uuid'))
                                ) . '">»</a>';
                }

                $eventsArray[] = array(
                    'uuid' => $plgEvent->getValue('dat_uuid'),
                    'dateTimePeriod' => $plgEvent->getDateTimePeriod($config['event_list_show_event_date_end']),
                    'headline' => $plgNewHeadline,
                    'description' => $plgNewDescription
                );
            }
        }
        return $eventsArray;
    }
}
