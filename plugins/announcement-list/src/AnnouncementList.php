<?php

namespace AdmidioPlugin\AnnouncementList;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\AnnouncementList\Presenter\AnnouncementListPreferencesPresenter;

use Exception;

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
final class AnnouncementList
{
    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'announcement-list';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 6;

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
                    'create' => array(AnnouncementListPreferencesPresenter::class, 'createForm')
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

        if (($config['announcement_list_displayed_categories'] ?? null) === self::ALL_CATEGORIES) {
            $config['announcement_list_displayed_categories'] = $gCurrentUser->getAllVisibleCategories('ANN');
        }

        return $config;
    }

    /**
     * Build the widget of the overview page. Whether it is shown at all was decided before this is
     * called, by the preference **announcement_list_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin): string
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        $variables = array('name' => $plugin->id, 'message' => '', 'announcements' => array());
        $module = $gSettingsManager->getInt('announcements_module_enabled');

        if ($module === 0) {
            $variables['message'] = $gL10n->get('SYS_MODULE_DISABLED');
        } elseif ($module === 1 || ($module === 2 && $gValidLogin)) {
            $announcementArray = self::getAnnouncementsData(self::getConfig($plugin));
            if (!empty($announcementArray)) {
                $variables['announcements'] = $announcementArray;
            } else {
                $variables['message'] = $gL10n->get('SYS_NO_ENTRIES');
            }
        } else {
            $variables['message'] = $gL10n->get('PLG_ANNOUNCEMENT_LIST_NO_ENTRIES_VISITORS');
        }

        return $plugin->renderTemplate($page, 'plugin.announcement-list.tpl', $variables);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<int,array<string,mixed>>
     * @throws Exception
     */
    private static function getAnnouncementsData(array $config) : array
    {
        global $gSettingsManager, $gCurrentUser, $gDb, $gL10n;

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
                if ($config['announcement_list_show_full_description'] === true) {
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
}
