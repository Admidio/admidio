<?php
namespace AdmidioPlugin\RandomPhoto\Service;

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
        LegacyConfig::import('random-photo', array(
            'plg_max_char_per_word' => array('random_photo_max_char_per_word', 'intval'),
            'plg_photos_max_width'  => array('random_photo_max_width', 'intval'),
            'plg_photos_max_height' => array('random_photo_max_height', 'intval'),
            'plg_photos_albums'     => array('random_photo_albums', 'intval'),
            'plg_photos_picnr'      => array('random_photo_album_photo_number', 'intval'),
            'plg_photos_show_link'  => array('random_photo_show_album_link', 'boolval')
        ), 'random_photo');
    }
}
