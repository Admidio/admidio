<?php

use Admidio\Events\Entity\Event;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;

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
try {
    $rootPath = dirname(__DIR__, 2);
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/adm_program/system/common.php');

    // only include config file if it exists
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    $eventListPlugin = new Overview($pluginFolder);

    // set default values if there is no value has been stored in the config.php
    if (!isset($plg_max_number_events_shown) || !is_numeric($plg_max_number_events_shown)) {
        $plg_max_number_events_shown = 2;
    }

    if (!isset($plg_show_date_end) || !is_numeric($plg_show_date_end)) {
        $plg_show_date_end = 1;
    }

    if (!isset($plg_events_show_preview) || !is_numeric($plg_events_show_preview)) {
        $plg_events_show_preview = 70;
    }

    if (!isset($plgShowFullDescription) || !is_numeric($plgShowFullDescription)) {
        $plgShowFullDescription = 1;
    }

    if (!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word)) {
        $plg_max_char_per_word = 0;
    }

    if (!isset($plg_kal_cat)) {
        $plg_kal_cat = array();
        $plgSqlCategories = '';
    } else {
        $plgSqlCategories = ' AND cat_name IN (' . Database::getQmForValues($plg_categories) . ') ';
    }

    if ($gSettingsManager->getInt('events_module_enabled') > 0) {
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
                 LIMIT ' . $plg_max_number_events_shown;

        $pdoStatement = $gDb->queryPrepared($sql, array_merge($catIdParams, array(DATETIME_NOW), $plg_kal_cat));
        $plgEventList = $pdoStatement->fetchAll();

        if ($gSettingsManager->getInt('events_module_enabled') === 1
            || ($gSettingsManager->getInt('events_module_enabled') === 2 && $gValidLogin)) {
            if ($pdoStatement->rowCount() > 0) {
                // get announcements data
                $plgEvent = new Event($gDb);
                $eventArray = array();

                foreach ($plgEventList as $plgRow) {
                    $plgEvent->clear();
                    $plgEvent->setArray($plgRow);

                    if ($plg_max_char_per_word > 0) {
                        $plgNewHeadline = '';

                        // Pause words if they are too long
                        $plgWords = explode(' ', $plgEvent->getValue('dat_headline'));

                        foreach ($plgWords as $plgValue) {
                            if (strlen($plgValue) > $plg_max_char_per_word) {
                                $plgNewHeadline .= ' ' . substr($plgValue, 0, $plg_max_char_per_word) . '-<br />' .
                                    substr($plgValue, $plg_max_char_per_word);
                            } else {
                                $plgNewHeadline .= ' ' . $plgValue;
                            }
                        }
                    } else {
                        $plgNewHeadline = $plgEvent->getValue('dat_headline');
                    }

                    // show preview text
                    if ($plgShowFullDescription === 1) {
                        $plgNewDescription = $plgEvent->getValue('dat_description');
                    } elseif ($plg_events_show_preview > 0) {
                        // remove all html tags except some format tags
                        $plgNewDescription = strip_tags($plgEvent->getValue('dat_description'));

                        // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                        $plgNewDescription = substr($plgNewDescription, 0, $plg_events_show_preview + 15);
                        $plgNewDescription = substr($plgNewDescription, 0, strrpos($plgNewDescription, ' ')) . '
                            <a class="admidio-icon-link" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MORE') . '"
                                href="' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/events/events.php',
                                    array('view' => 'detail', 'dat_uuid' => $plgEvent->getValue('dat_uuid'))
                                ) . '">»</a>';
                    }

                    $eventArray[] = array(
                        'uuid' => $plgEvent->getValue('dat_uuid'),
                        'dateTimePeriod' => $plgEvent->getDateTimePeriod($plg_show_date_end),
                        'headline' => $plgNewHeadline,
                        'description' => $plgNewDescription
                    );
                }

                $eventListPlugin->assignTemplateVariable('events', $eventArray);
            } else {
                $eventListPlugin->assignTemplateVariable('message',$gL10n->get('SYS_NO_ENTRIES'));
            }
        } else {
            $eventListPlugin->assignTemplateVariable('message',$gL10n->get('PLG_EVENT_LIST_NO_ENTRIES_VISITORS'));
        }

        if (isset($page)) {
            echo $eventListPlugin->html('plugin.event-list.tpl');
        } else {
            $eventListPlugin->showHtmlPage('plugin.event-list.tpl');
        }
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
