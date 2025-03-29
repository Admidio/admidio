<?php

use Admidio\Announcements\Entity\Announcement;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 ***********************************************************************************************
 * Announcement list
 *
 * Plugin that lists the latest announcements in a slim interface and
 * can thus be ideally used in a sidebar
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

    $announcementListPlugin = new Overview($pluginFolder);

    // set default values if no value has been stored in the config.php
    if (!isset($plg_announcements_count) || !is_numeric($plg_announcements_count)) {
        $plg_announcements_count = 2;
    }

    if (!isset($plg_show_preview) || !is_numeric($plg_show_preview)) {
        $plg_show_preview = 70;
    }

    if (!isset($plgShowFullDescription) || !is_numeric($plgShowFullDescription)) {
        $plgShowFullDescription = 0;
    }

    if (!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word)) {
        $plg_max_char_per_word = 0;
    }

    if (!isset($plg_categories)) {
        $plg_categories = array();
        $plgSqlCategories = '';
    } else {
        $plgSqlCategories = ' AND cat_name IN (' . Database::getQmForValues($plg_categories) . ') ';
    }

    if ($gSettingsManager->getInt('announcements_module_enabled') > 0) {
        // read announcements from database
        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('ANN'));

        $sql = 'SELECT cat.*, ann.*
                  FROM ' . TBL_ANNOUNCEMENTS . ' AS ann
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat_id = ann_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($catIdParams) . ')
                       ' . $plgSqlCategories . '
              ORDER BY ann_timestamp_create DESC
                 LIMIT ' . $plg_announcements_count;

        $pdoStatement = $gDb->queryPrepared($sql, array_merge($catIdParams, $plg_categories));
        $plgAnnouncementsList = $pdoStatement->fetchAll();

        if ($gSettingsManager->getInt('announcements_module_enabled') === 1
            || ($gSettingsManager->getInt('announcements_module_enabled') === 2 && $gValidLogin)) {
            if ($pdoStatement->rowCount() > 0) {
                // get announcements data
                $plgAnnouncement = new Announcement($gDb);
                $announcementArray = array();

                foreach ($plgAnnouncementsList as $plgRow) {
                    $plgAnnouncement->clear();
                    $plgAnnouncement->setArray($plgRow);

                    if ($plg_max_char_per_word > 0) {
                        // Interrupt words of headline if they are too long
                        $plgNewHeadline = '';

                        $plgWords = explode(' ', $plgAnnouncement->getValue('ann_headline'));

                        foreach ($plgWords as $plgValue) {
                            if (strlen($plgValue) > $plg_max_char_per_word) {
                                $plgNewHeadline .= ' ' . substr($plgValue, 0, $plg_max_char_per_word) . '-<br />' .
                                    substr($plgValue, $plg_max_char_per_word);
                            } else {
                                $plgNewHeadline .= ' ' . $plgValue;
                            }
                        }
                    } else {
                        $plgNewHeadline = $plgAnnouncement->getValue('ann_headline');
                    }

                    // show preview text
                    if ($plgShowFullDescription === 1) {
                        $plgNewDescription = $plgAnnouncement->getValue('ann_description');
                    } elseif ($plg_show_preview > 0) {
                        // remove all html tags except some format tags
                        $plgNewDescription = strip_tags($plgAnnouncement->getValue('ann_description'));

                        // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                        $plgNewDescription = substr($plgNewDescription, 0, $plg_show_preview + 15);
                        $plgNewDescription = substr($plgNewDescription, 0, strrpos($plgNewDescription, ' ')) . '
                            <a class="admidio-icon-link" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MORE') . '"
                                href="' . SecurityUtils::encodeUrl(
                                    ADMIDIO_URL . FOLDER_MODULES . '/announcements.php',
                                    array('announcement_uuid' => $plgAnnouncement->getValue('ann_uuid'))
                                ) . '">»</a>';
                    }

                    $announcementArray[] = array(
                        'uuid' => $plgAnnouncement->getValue('ann_uuid'),
                        'headline' => $plgNewHeadline,
                        'description' => $plgNewDescription,
                        'creationDate' => $plgAnnouncement->getValue('ann_timestamp_create', $gSettingsManager->getString('system_date'))
                    );
                }

                $announcementListPlugin->assignTemplateVariable('announcements', $announcementArray);
            } else {
                $announcementListPlugin->assignTemplateVariable('message',$gL10n->get('SYS_NO_ENTRIES'));
            }
        } else {
            $announcementListPlugin->assignTemplateVariable('message',$gL10n->get('PLG_ANNOUNCEMENT_LIST_NO_ENTRIES_VISITORS'));
        }
        if (isset($page)) {
            echo $announcementListPlugin->html('plugin.announcement-list.tpl');
        } else {
            $announcementListPlugin->showHtmlPage('plugin.announcement-list.tpl');
        }
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
