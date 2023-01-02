<?php
/**
 ***********************************************************************************************
 * Sidebar Online
 *
 * Plugin shows visitors and registered members of the homepage
 *
 * Compatible with Admidio version 4.1
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

// set default values if there no value has been stored in the config.php
if (!isset($plg_time_online) || !is_numeric($plg_time_online)) {
    $plg_time_online = 10;
}

if (!isset($plg_show_visitors) || !is_numeric($plg_show_visitors)) {
    $plg_show_visitors = 1;
}

if (!isset($plg_show_members) || !is_numeric($plg_show_members)) {
    $plg_show_members = 2;
}

if (!isset($plg_show_self) || !is_numeric($plg_show_self)) {
    $plg_show_self = 1;
}

if (!isset($plg_show_users_side_by_side) || !is_numeric($plg_show_users_side_by_side)) {
    $plg_show_users_side_by_side = 0;
}

if (isset($plg_link_target)) {
    $plg_link_target = strip_tags($plg_link_target);
} else {
    $plg_link_target = '_self';
}

if (!isset($plg_show_headline) || !is_numeric($plg_show_headline)) {
    $plg_show_headline = 1;
}

// Referenzzeit setzen
$now = new \DateTime();
$minutesOffset = new \DateInterval('PT' . $plg_time_online . 'M');
$refDate = $now->sub($minutesOffset)->format('Y-m-d H:i:s');

// User IDs alles Sessons finden, die in genannter aktueller und referenz Zeit sind
$sql = 'SELECT ses_usr_id, usr_uuid, usr_login_name
          FROM '.TBL_SESSIONS.'
     LEFT JOIN '.TBL_USERS.'
            ON usr_id = ses_usr_id
         WHERE ses_timestamp BETWEEN ? AND ? -- $refDate AND DATETIME_NOW
           AND ses_org_id = ? -- $gCurrentOrgId';
$queryParams = array($refDate, DATETIME_NOW, $gCurrentOrgId);
if (!$plg_show_visitors) {
    $sql .= '
        AND ses_usr_id IS NOT NULL';
}
if (!$plg_show_self && $gValidLogin) {
    $sql .= '
         AND ses_usr_id <> ? -- $gCurrentUserId';
    $queryParams[] = $gCurrentUserId;
}
$sql .= '
     ORDER BY ses_usr_id';
$onlineUsersStatement = $gDb->queryPrepared($sql, $queryParams);

echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
if ($plg_show_headline) {
    echo '<h3>'.$gL10n->get('PLG_ONLINE_HEADLINE').'</h3>';
}

if ($onlineUsersStatement->rowCount() > 0) {
    $usrIdMerker   = 0;
    $countMembers  = 0;
    $countVisitors = 0;
    $allVisibleOnlineUsers = array();
    $textOnlineVisitors = '';

    while ($row = $onlineUsersStatement->fetch()) {
        if ($row['ses_usr_id'] > 0) {
            if (((int) $row['ses_usr_id'] !== $usrIdMerker)
            && ($plg_show_members == 1 || $gValidLogin)) {
                $allVisibleOnlineUsers[] = '<strong><a target="'. $plg_link_target. '" title="'.$gL10n->get('SYS_SHOW_PROFILE').'"
                    href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES. '/profile/profile.php', array('user_uuid' => $row['usr_uuid'])). '">'. $row['usr_login_name']. '</a></strong>';
                $usrIdMerker = (int) $row['ses_usr_id'];
            }
            ++$countMembers;
        } else {
            ++$countVisitors;
        }
    }

    if (!$gValidLogin && $plg_show_members == 2 && $countMembers > 0) {
        if ($countMembers > 1) {
            $allVisibleOnlineUsers[] = $gL10n->get('PLG_ONLINE_VAR_NUM_MEMBERS', array($countMembers));
        } else {
            $allVisibleOnlineUsers[] = $gL10n->get('PLG_ONLINE_VAR_NUM_MEMBER', array($countMembers));
        }
    }

    if ($plg_show_visitors && $countVisitors > 0) {
        $allVisibleOnlineUsers[] = $gL10n->get('PLG_ONLINE_VAR_NUM_VISITORS', array($countVisitors));
    }

    if ($plg_show_users_side_by_side) {
        $textOnlineVisitors = implode(', ', $allVisibleOnlineUsers);
    } else {
        $textOnlineVisitors = '<br />'. implode('<br />', $allVisibleOnlineUsers);
    }

    if ($onlineUsersStatement->rowCount() === 1) {
        echo $gL10n->get('PLG_ONLINE_VAR_ONLINE_IS', array($textOnlineVisitors));
    } else {
        echo $gL10n->get('PLG_ONLINE_VAR_ONLINE_ARE', array($textOnlineVisitors));
    }
} else {
    echo $gL10n->get('PLG_ONLINE_NO_VISITORS_ON_WEBSITE');
}

echo '</div>';
