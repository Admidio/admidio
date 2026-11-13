<?php
namespace AdmidioPlugin\Birthday\Service;

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
        LegacyConfig::import('birthday', array(
            'plg_show_names_extern'          => array('birthday_show_names_extern', 'boolval'),
            'plg_show_names'                 => array('birthday_show_names', 'intval'),
            'plg_show_age'                   => array('birthday_show_age', 'boolval'),
            'plg_show_alter_anrede'          => array('birthday_show_age_salutation', 'intval'),
            'plg_show_hinweis_keiner'        => array('birthday_show_notice_none', 'boolval'),
            'plg_show_zeitraum'              => array('birthday_show_past', 'intval'),
            'plg_show_future'                => array('birthday_show_future', 'intval'),
            'plg_show_display_limit'         => array('birthday_show_display_limit', 'intval'),
            'plg_show_email_extern'          => array('birthday_show_email_extern', 'intval'),
            'plg_birthday_roles_view_plugin' => array('birthday_roles_view_plugin', array(self::class, 'roles')),
            'plg_rolle_sql'                  => array('birthday_roles_sql', array(self::class, 'roles')),
            'plg_sort_sql'                   => array('birthday_sort_sql', 'strval')
        ));
    }

    /**
     * The roles an old setting names, or all of them if it names none.
     * @param mixed $roles
     * @return array<int,mixed>
     */
    public static function roles(mixed $roles): array
    {
        return is_array($roles) && $roles !== array() ? $roles : array('All');
    }
}
