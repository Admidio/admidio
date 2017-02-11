<?php
/**
 ***********************************************************************************************
 * Sidebar Online
 *
 * Version 1.7.0
 * Plugin shows visitors and registered members of the homepage
 *
 * Compatible with Admidio version 3.2
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// create path to plugin
$pluginFolderPos = strpos(__FILE__, 'adm_plugins') + 11;
$pluginFilePos   = strpos(__FILE__, 'sidebar_online.php');
$pluginFolder    = substr(__FILE__, $pluginFolderPos + 1, $pluginFilePos - $pluginFolderPos - 2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $pluginFolderPos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$pluginFolder.'/config.php');

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$pluginFolder.'/languages');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(!isset($plg_time_online) || !is_numeric($plg_time_online))
{
    $plg_time_online = 10;
}

if(!isset($plg_show_visitors) || !is_numeric($plg_show_visitors))
{
    $plg_show_visitors = 1;
}

if(!isset($plg_show_self) || !is_numeric($plg_show_self))
{
    $plg_show_self = 1;
}

if(!isset($plg_show_users_side_by_side) || !is_numeric($plg_show_users_side_by_side))
{
    $plg_show_users_side_by_side = 0;
}

if(isset($plg_link_class))
{
    $plg_link_class = strip_tags($plg_link_class);
}
else
{
    $plg_link_class = '';
}

if(isset($plg_link_target))
{
    $plg_link_target = strip_tags($plg_link_target);
}
else
{
    $plg_link_target = '_self';
}

// Referenzzeit setzen
$now = new DateTime();
$minutesOffset = new DateInterval('PT' . $plg_time_online . 'M');
$refDate = $now->sub($minutesOffset)->format('Y-m-d H:i:s');

// User IDs alles Sessons finden, die in genannter aktueller und referenz Zeit sind
$sql = 'SELECT ses_usr_id, usr_login_name
          FROM '.TBL_SESSIONS.'
     LEFT JOIN '.TBL_USERS.'
            ON usr_id = ses_usr_id
         WHERE ses_timestamp BETWEEN ? AND ? -- $refDate AND DATETIME_NOW
           AND ses_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')';
$queryParams = array($refDate, DATETIME_NOW, $gCurrentOrganization->getValue('org_id'));
if(!$plg_show_visitors)
{
    $sql .= '
        AND ses_usr_id IS NOT NULL';
}
if(!$plg_show_self && $gValidLogin)
{
    $sql .= '
         AND ses_usr_id <> ? -- $gCurrentUser->getValue(\'usr_id\')';
    $queryParams[] = $gCurrentUser->getValue('usr_id');
}
$sql .= '
     ORDER BY ses_usr_id';
$onlineUsersStatement = $gDb->queryPrepared($sql, $queryParams);

echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
if($plg_show_headline)
{
    echo '<h3>'.$gL10n->get('PLG_ONLINE_HEADLINE').'</h3>';
}

if($onlineUsersStatement->rowCount() > 0)
{
    echo $plg_online_text;

    $usrIdMerker   = 0;
    $countVisitors = 0;

    while($row = $onlineUsersStatement->fetchObject())
    {
        if($row->ses_usr_id > 0)
        {
            if((int) $row->ses_usr_id !== $usrIdMerker)
            {
                echo '<strong><a class="'. $plg_link_class. '" target="'. $plg_link_target. '" title="'.$gL10n->get('SYS_SHOW_PROFILE').'"
                    href="'. ADMIDIO_URL. FOLDER_MODULES. '/profile/profile.php?user_id='. $row->ses_usr_id. '">'. $row->usr_login_name. '</a></strong>';

                // User neben-/untereinander anzeigen
                if($plg_show_users_side_by_side)
                {
                    echo ', ';
                }
                else
                {
                    echo '<br />';
                }
                $usrIdMerker = (int) $row->ses_usr_id;
            }
        }
        else
        {
            ++$countVisitors;
        }
    }

    if($plg_show_visitors && $countVisitors > 0)
    {
        echo $gL10n->get('PLG_ONLINE_VAR_NUM_VISITORS', $countVisitors);
    }
}
else
{
    echo $gL10n->get('PLG_ONLINE_NO_VISITORS_ON_WEBSITE');
}

echo '</div>';
