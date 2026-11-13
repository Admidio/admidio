<?php
namespace AdmidioPlugin\WhoIsOnline\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\LegacyConfig;

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class UpdateStepsCode
{
    /**
     * @var Database
     */
    private static Database $db;

    /**
     * Set the database
     * @param Database $database The database instance
     */
    public static function setDatabase(Database $database)
    {
        self::$db = $database;
    }

    /**
     * Take over the settings of the config.php the plugin had before it kept them as preferences.
     * @throws Exception
     */
    public static function updateStep10RetrievePreviousSettings()
    {
        LegacyConfig::import('who-is-online', array(
            'plg_time_online'             => array('who_is_online_time_still_active', 'intval'),
            'plg_show_visitors'           => array('who_is_online_show_visitors', 'boolval'),
            'plg_show_members'            => array('who_is_online_show_members_to_visitors', 'intval'),
            'plg_show_self'               => array('who_is_online_show_self', 'boolval'),
            'plg_show_users_side_by_side' => array('who_is_online_show_users_side_by_side', 'boolval')
        ));
    }
}
