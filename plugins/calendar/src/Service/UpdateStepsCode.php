<?php
namespace AdmidioPlugin\Calendar\Service;

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
        LegacyConfig::import('calendar', array(
            'plg_ter_aktiv'                  => array('calendar_show_events', 'boolval'),
            'plg_geb_aktiv'                  => array('calendar_show_birthdays', 'boolval'),
            'plg_geb_login'                  => array('calendar_show_birthdays_to_guests', 'boolval'),
            'plg_geb_icon'                   => array('calendar_show_birthday_icon', 'boolval'),
            'plg_geb_displayNames'           => array('calendar_show_birthday_names', 'intval'),
            'plg_kal_cat_show'               => array('calendar_show_categories_names', 'boolval'),
            'plg_kal_cat'                    => array('calendar_show_categories', LegacyConfig::categories('EVT')),
            'plg_calendar_roles_view_plugin' => array('calendar_roles_view_plugin', array(LegacyConfig::class, 'roles')),
            'plg_rolle_sql'                  => array('calendar_roles_sql', array(LegacyConfig::class, 'roles'))
        ));
    }
}
