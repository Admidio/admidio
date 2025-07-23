<?php

namespace Plugins\WhoIsOnline\classes;

use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Utils\SecurityUtils;

use InvalidArgumentException;
use Exception;
use Throwable;
use DateTime;
use DateInterval;

/**
 ***********************************************************************************************
 * Who is online
 *
 * Plugin shows visitors and registered members of the homepage
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class WhoIsOnline extends PluginAbstract
{
    /**
     * Get the photo data
     * @return array Returns the photo data
     */
    private static function getWhoIsOnlineText() : string
    {
        global $gCurrentOrgId, $gDb, $gL10n, $gValidLogin, $gCurrentUserId;

        $config = self::getPluginConfigValues();
        $text = '';

        // Set reference time
        $now = new DateTime();
        $minutesOffset = new DateInterval('PT' . $config['who_is_online_plugin_time_still_active'] . 'M');
        $refDate = $now->sub($minutesOffset)->format('Y-m-d H:i:s');

        // Find user IDs of all sessions that are in the specified current and reference time
        $sql = 'SELECT ses_usr_id, usr_uuid, usr_login_name
            FROM ' . TBL_SESSIONS . '
        LEFT JOIN ' . TBL_USERS . '
                ON usr_id = ses_usr_id
            WHERE ses_timestamp BETWEEN ? AND ? -- $refDate AND DATETIME_NOW
            AND ses_org_id = ? -- $gCurrentOrgId';
        $queryParams = array($refDate, DATETIME_NOW, $gCurrentOrgId);
        if (!$config['who_is_online_plugin_show_visitors']) {
            $sql .= '
            AND ses_usr_id IS NOT NULL';
        }
        if (!$config['who_is_online_plugin_show_self'] && $gValidLogin) {
            $sql .= '
            AND ses_usr_id <> ? -- $gCurrentUserId';
            $queryParams[] = $gCurrentUserId;
        }
        $sql .= '
        ORDER BY ses_usr_id';
        $onlineUsersStatement = $gDb->queryPrepared($sql, $queryParams);

        if ($onlineUsersStatement->rowCount() > 0) {
            $usrIdMerker = 0;
            $countMembers = 0;
            $countVisitors = 0;
            $allVisibleOnlineUsers = array();
            $textOnlineVisitors = '';

            while ($row = $onlineUsersStatement->fetch()) {
                if ($row['ses_usr_id'] > 0) {
                    if (((int)$row['ses_usr_id'] !== $usrIdMerker)
                        && ($config['who_is_online_plugin_show_members_to_visitors'] == 1 || $gValidLogin)) {
                        $allVisibleOnlineUsers[] = '<strong><a title="' . $gL10n->get('SYS_SHOW_PROFILE') . '"
                        href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $row['usr_uuid'])) . '">' . $row['usr_login_name'] . '</a></strong>';
                        $usrIdMerker = (int)$row['ses_usr_id'];
                    }
                    ++$countMembers;
                } else {
                    ++$countVisitors;
                }
            }

            if (!$gValidLogin && $config['who_is_online_plugin_show_members_to_visitors'] == 2 && $countMembers > 0) {
                if ($countMembers > 1) {
                    $allVisibleOnlineUsers[] = $gL10n->get('PLG_WHO_IS_ONLINE_VAR_NUM_MEMBERS', array($countMembers));
                } else {
                    $allVisibleOnlineUsers[] = $gL10n->get('PLG_WHO_IS_ONLINE_VAR_NUM_MEMBER', array($countMembers));
                }
            }

            if ($config['who_is_online_plugin_show_visitors'] && $countVisitors > 0) {
                $allVisibleOnlineUsers[] = $gL10n->get('PLG_WHO_IS_ONLINE_VAR_NUM_VISITORS', array($countVisitors));
            }

            if ($config['who_is_online_plugin_show_users_side_by_side']) {
                $textOnlineVisitors = implode(', ', $allVisibleOnlineUsers);
            } else {
                $textOnlineVisitors = '<br />' . implode('<br />', $allVisibleOnlineUsers);
            }

            if ($onlineUsersStatement->rowCount() === 1) {
                $text = $gL10n->get('PLG_WHO_IS_ONLINE_VAR_ONLINE_IS', array($textOnlineVisitors));
            } else {
                $text = $gL10n->get('PLG_WHO_IS_ONLINE_VAR_ONLINE_ARE', array($textOnlineVisitors));
            }
        } else {
            $text = $gL10n->get('PLG_WHO_IS_ONLINE_NO_VISITORS_ON_WEBSITE');
        }

        return $text;
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

        // show random photo
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $whoIsOnlinePlugin = new Overview($pluginFolder);

            // check if the plugin is installed
            if (!self::isInstalled()) {
                throw new InvalidArgumentException($gL10n->get('SYS_PLUGIN_NOT_INSTALLED'));
            }

            if ($gSettingsManager->getInt('who_is_online_plugin_enabled') === 1 || ($gSettingsManager->getInt('who_is_online_plugin_enabled') === 2 && $gValidLogin)) {
                $text = self::getWhoIsOnlineText();
                $whoIsOnlinePlugin->assignTemplateVariable('message', $text);
            } else {
                $whoIsOnlinePlugin->assignTemplateVariable('message', $gL10n->get('PLG_WHO_IS_ONLINE_NO_DATA_VISITORS'));
            }

            if (isset($page)) {
                echo $whoIsOnlinePlugin->html('plugin.who-is-online.tpl');
            } else {
                $whoIsOnlinePlugin->showHtmlPage('plugin.who-is-online.tpl');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}