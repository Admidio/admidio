<?php

namespace Plugins\AnnouncementList\classes;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Plugins\PluginAbstract;

use InvalidArgumentException;
use Exception;
use Throwable;

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
class AnnouncementList extends PluginAbstract
{
    private static function getAnnouncementsData() : array
    {
        global $gSettingsManager, $gCurrentUser, $gDb, $gL10n;

        $config = self::getPluginConfigValues();

        if (!is_array($config['announcement_list_displayed_categories']) || empty($config['announcement_list_displayed_categories'])) {
            $plgSqlCategories = '';
        } else {
            $plgSqlCategories = ' AND cat_id IN (' . Database::getQmForValues($config['announcement_list_displayed_categories']) . ') ';
        }

        // read announcements from database
        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('ANN'));

        $sql = 'SELECT cat.*, ann.*
                FROM ' . TBL_ANNOUNCEMENTS . ' AS ann
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat_id = ann_cat_id
                WHERE cat_id IN (' . Database::getQmForValues($catIdParams) . ')
                    ' . $plgSqlCategories . '
            ORDER BY ann_timestamp_create DESC
                LIMIT ' . $config['announcement_list_announcements_count'];

        $pdoStatement = $gDb->queryPrepared($sql, array_merge($catIdParams, $config['announcement_list_displayed_categories']));
        $plgAnnouncementsList = $pdoStatement->fetchAll();

        $announcementArray = array();

        if ($pdoStatement->rowCount() > 0) {
            // get announcements data
            $plgAnnouncement = new Announcement($gDb);

            foreach ($plgAnnouncementsList as $plgRow) {
                $plgAnnouncement->clear();
                $plgAnnouncement->setArray($plgRow);

                if ($config['announcement_list_chars_before_linebreak'] > 0) {
                    // Interrupt words of headline if they are too long
                    $plgNewHeadline = '';

                    $plgWords = explode(' ', $plgAnnouncement->getValue('ann_headline'));

                    foreach ($plgWords as $plgValue) {
                        if (strlen($plgValue) > $config['announcement_list_chars_before_linebreak']) {
                            $plgNewHeadline .= ' ' . substr($plgValue, 0, $config['announcement_list_chars_before_linebreak']) . '-<br />' .
                                substr($plgValue, $config['announcement_list_chars_before_linebreak']);
                        } else {
                            $plgNewHeadline .= ' ' . $plgValue;
                        }
                    }
                } else {
                    $plgNewHeadline = $plgAnnouncement->getValue('ann_headline');
                }

                // show preview text
                if ($config['announcement_list_show_full_description'] === 1) {
                    $plgNewDescription = $plgAnnouncement->getValue('ann_description');
                } elseif ($config['announcement_list_show_preview_chars'] > 0) {
                    // remove all html tags except some format tags
                    $plgNewDescription = strip_tags($plgAnnouncement->getValue('ann_description'));

                    // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                    $plgNewDescription = substr($plgNewDescription, 0, $config['announcement_list_show_preview_chars'] + 15);
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
        }
        return $announcementArray;
    }

    /**
     * @param array $config
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender(array $config = array())
    {
        global $gSettingsManager, $gL10n;

        if (!is_array($config))
        {
            throw new InvalidArgumentException('Config must be an "array".');
        }

        // show the announcement list
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $announcementListPlugin = new Overview($pluginFolder);

            if ($gSettingsManager->getInt('announcements_module_enabled') > 0) {
                if ($gSettingsManager->getInt('announcements_module_enabled') === 1
                    || ($gSettingsManager->getInt('announcements_module_enabled') === 2 && $gValidLogin)) {
                    $announcementArray = self::getAnnouncementsData();
                    if (!empty($announcementArray)) {
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

        return true;
    }
}