<?php

namespace AdmidioPlugin\WhoIsOnline;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\WhoIsOnline\Presenter\WhoIsOnlinePreferencesPresenter;
use DateInterval;
use DateTime;

/**
 * Who is online - shows the visitors and the members that are currently on the website.
 *
 * The plugin contributes two things and registers both from its entry file: a widget on the
 * overview page and a panel in the preferences.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class WhoIsOnline
{
    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'who-is-online';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 8;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

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
                    'create' => array(WhoIsOnlinePreferencesPresenter::class, 'createForm')
                );

                return $panels;
            },
            PluginPanel::DEFAULT_SEQUENCE,
            1,
            $plugin->id
        );
    }

    /**
     * Build the widget of the overview page. Whether it is shown at all was decided before this is
     * called, by the preference **who_is_online_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin): string
    {
        return $plugin->renderTemplate($page, 'plugin.who-is-online.tpl', array(
            'name' => $plugin->id,
            'message' => self::getText($plugin)
        ));
    }

    /**
     * Who is on the website right now, as one readable sentence.
     * @param Plugin $plugin
     * @return string
     * @throws Exception
     */
    private static function getText(Plugin $plugin): string
    {
        global $gCurrentOrgId, $gDb, $gL10n, $gValidLogin, $gCurrentUserId;

        $config = $plugin->getSettingValues();

        // Find the user IDs of all sessions between the reference time and now.
        $refDate = (new DateTime())
            ->sub(new DateInterval('PT' . $config['who_is_online_time_still_active'] . 'M'))
            ->format('Y-m-d H:i:s');
        $showVisitors = (bool)$config['who_is_online_show_visitors'];
        $showMembersToVisitors = (int)$config['who_is_online_show_members_to_visitors'];

        $sql = 'SELECT ses_usr_id, usr_uuid, usr_login_name
            FROM ' . TBL_SESSIONS . '
        LEFT JOIN ' . TBL_USERS . '
                ON usr_id = ses_usr_id
            WHERE ses_timestamp BETWEEN ? AND ? -- $refDate AND DATETIME_NOW
            AND ses_org_id = ? -- $gCurrentOrgId';
        $queryParams = array($refDate, DATETIME_NOW, $gCurrentOrgId);
        if (!$showVisitors) {
            $sql .= '
            AND ses_usr_id IS NOT NULL';
        }
        if (!$config['who_is_online_show_self'] && $gValidLogin) {
            $sql .= '
            AND ses_usr_id <> ? -- $gCurrentUserId';
            $queryParams[] = $gCurrentUserId;
        }
        $sql .= '
        ORDER BY ses_usr_id';
        $onlineUsersStatement = $gDb->queryPrepared($sql, $queryParams);

        if ($onlineUsersStatement->rowCount() === 0) {
            return $gL10n->get('PLG_WHO_IS_ONLINE_NO_VISITORS_ON_WEBSITE');
        }

        $usrIdMerker = 0;
        $countMembers = 0;
        $countVisitors = 0;
        $allVisibleOnlineUsers = array();

        while ($row = $onlineUsersStatement->fetch()) {
            if ($row['ses_usr_id'] > 0) {
                if ((int)$row['ses_usr_id'] !== $usrIdMerker && ($showMembersToVisitors === 1 || $gValidLogin)) {
                    $allVisibleOnlineUsers[] = '<strong><a title="' . $gL10n->get('SYS_SHOW_PROFILE') . '"
                        href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $row['usr_uuid'])) . '">' . $row['usr_login_name'] . '</a></strong>';
                    $usrIdMerker = (int)$row['ses_usr_id'];
                }
                ++$countMembers;
            } else {
                ++$countVisitors;
            }
        }

        if (!$gValidLogin && $showMembersToVisitors === 2 && $countMembers > 0) {
            $allVisibleOnlineUsers[] = $countMembers > 1
                ? $gL10n->get('PLG_WHO_IS_ONLINE_VAR_NUM_MEMBERS', array($countMembers))
                : $gL10n->get('PLG_WHO_IS_ONLINE_VAR_NUM_MEMBER', array($countMembers));
        }

        if ($showVisitors && $countVisitors > 0) {
            $allVisibleOnlineUsers[] = $gL10n->get('PLG_WHO_IS_ONLINE_VAR_NUM_VISITORS', array($countVisitors));
        }

        $textOnlineVisitors = $config['who_is_online_show_users_side_by_side']
            ? implode(', ', $allVisibleOnlineUsers)
            : '<br />' . implode('<br />', $allVisibleOnlineUsers);

        return $onlineUsersStatement->rowCount() === 1
            ? $gL10n->get('PLG_WHO_IS_ONLINE_VAR_ONLINE_IS', array($textOnlineVisitors))
            : $gL10n->get('PLG_WHO_IS_ONLINE_VAR_ONLINE_ARE', array($textOnlineVisitors));
    }
}
