<?php

namespace Plugins\EventList\classes;

use Admidio\Events\Entity\Event;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Plugins\PluginAbstract;

use InvalidArgumentException;
use Exception;
use Throwable;

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
class EventList extends PluginAbstract
{
    /** 
     * Get the plugin configuration
     * @return array Returns the plugin configuration
     */
    public static function getPluginConfig() : array
    {
        global $gCurrentUser;

        // get the plugin config from the parent class
        $config = parent::getPluginConfig();

        // if the key equals 'event_list_displayed_categories' and the value is still the default value, retrieve the categories from the database
        if (array_key_exists('event_list_displayed_categories', $config) && $config['event_list_displayed_categories']['value'] === self::$defaultConfig['event_list_displayed_categories']['value']) {
            $config['event_list_displayed_categories']['value'] = $gCurrentUser->getAllVisibleCategories('EVT');
        }
        return $config;
    }
    
    /**
     * Get the plugin configuration values
     * @return array Returns the plugin configuration values
     */
    public static function getPluginConfigValues() : array
    {
        global $gCurrentUser;

        // get the plugin config values from the parent class
        $config = parent::getPluginConfigValues();

        // if the key equals 'event_list_displayed_categories' and the value is still the default value, retrieve the categories from the database
        if (array_key_exists('event_list_displayed_categories', $config) && $config['event_list_displayed_categories'] === self::$defaultConfig['event_list_displayed_categories']['value']) {
            $config['event_list_displayed_categories'] = $gCurrentUser->getAllVisibleCategories('EVT');
        }

        return $config;
    }

    private static function getEventsData() : array
    {
        global $gSettingsManager, $gCurrentUser, $gDb, $gL10n;

        $config = self::getPluginConfigValues();

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
                   AND dat_begin >= ? -- DATETIME_NOW
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
                if ($config['event_list_show_full_description'] === 1) {
                    $plgNewDescription = $plgEvent->getValue('dat_description');
                } elseif ($config['event_list_show_preview_chars'] > 0) {
                    // remove all html tags except some format tags
                    $plgNewDescription = strip_tags($plgEvent->getValue('dat_description'));

                        // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                        $plgNewDescription = substr($plgNewDescription, 0, $config['event_list_show_preview_chars'] + 15);
                        $plgNewDescription = substr($plgNewDescription, 0, strrpos($plgNewDescription, ' ')) . '
                            <a class="admidio-icon-link" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MORE') . '"
                                href="' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/events/events.php',
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

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender($page = null) : bool
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        // show the event list
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $eventListPlugin = new Overview($pluginFolder);

            // check if the plugin is installed
            if (!self::isInstalled()) {
                throw new InvalidArgumentException($gL10n->get('SYS_PLUGIN_NOT_INSTALLED'));
            }

            if ($gSettingsManager->getInt('events_module_enabled') > 0) {
                if (($gSettingsManager->getInt('events_module_enabled') === 1 || ($gSettingsManager->getInt('events_module_enabled') === 2 && $gValidLogin)) &&
                    ($gSettingsManager->getInt('event_list_plugin_enabled') === 1 || ($gSettingsManager->getInt('event_list_plugin_enabled') === 2 && $gValidLogin))) {
                    $eventsArray = self::getEventsData();
                    if (!empty($eventsArray)) {
                        $eventListPlugin->assignTemplateVariable('events', $eventsArray);
                    } else {
                        $eventListPlugin->assignTemplateVariable('message',$gL10n->get('SYS_NO_ENTRIES'));
                    }
                } else {
                    $eventListPlugin->assignTemplateVariable('message',$gL10n->get('PLG_EVENT_LIST_NO_ENTRIES_VISITORS'));
                }
            } else {
                $eventListPlugin->assignTemplateVariable('message', $gL10n->get('SYS_MODULE_DISABLED'));
            }
            
            if (isset($page)) {
                echo $eventListPlugin->html('plugin.event-list.tpl');
            } else {
                $eventListPlugin->showHtmlPage('plugin.event-list.tpl');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}