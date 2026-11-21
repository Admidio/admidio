<?php
namespace AdmidioPlugin\LoginForm\Service;

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
        // The ranks were switched off with an empty list. Their titles are not taken over: the new
        // ones are translated, the old ones were plain text.
        LegacyConfig::import('login-form', array(
            'plg_show_register_link' => array('login_form_show_register_link', 'boolval'),
            'plg_show_email_link'    => array('login_form_show_email_link', 'boolval'),
            'plg_show_logout_link'   => array('login_form_show_logout_link', 'boolval'),
            'plg_rank'               => array('login_form_enable_ranks', 'boolval')
        ), 'login_form');
    }
}
