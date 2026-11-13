<?php
namespace AdmidioPlugin\EventList\Service;

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
        LegacyConfig::import('event-list', array(
            'plg_max_number_events_shown' => array('event_list_events_count', 'intval'),
            'plg_show_date_end'           => array('event_list_show_event_date_end', 'boolval'),
            'plg_events_show_preview'     => array('event_list_show_preview_chars', 'intval'),
            'plgShowFullDescription'      => array('event_list_show_full_description', 'boolval'),
            'plg_max_char_per_word'       => array('event_list_chars_before_linebreak', 'intval'),
            'plg_kal_cat'                 => array('event_list_displayed_categories', array(self::class, 'categories'))
        ));
    }

    /**
     * The IDs of the categories an old setting names, or all categories if none of them exists.
     * @param mixed $names
     * @return array<int,mixed>
     * @throws Exception
     */
    public static function categories(mixed $names): array
    {
        $categoryIds = array();

        foreach (is_array($names) ? $names : array() as $name) {
            if (strtolower((string)$name) === 'all') {
                return array('All');
            }

            $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_name = ? AND cat_type = ?';
            $categoryId = self::$db->queryPrepared($sql, array($name, 'EVT'))->fetchColumn();
            if ($categoryId !== false) {
                $categoryIds[] = (int)$categoryId;
            }
        }

        return $categoryIds === array() ? array('All') : $categoryIds;
    }
}
