<?php
namespace AdmidioPlugin\AnnouncementList\Service;

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
        LegacyConfig::import('announcement-list', array(
            'plg_announcements_count' => array('announcement_list_announcements_count', 'intval'),
            'plg_show_preview'        => array('announcement_list_show_preview_chars', 'intval'),
            'plgShowFullDescription'  => array('announcement_list_show_full_description', 'boolval'),
            'plg_max_char_per_word'   => array('announcement_list_chars_before_linebreak', 'intval'),
            'plg_categories'          => array('announcement_list_displayed_categories', array(self::class, 'categories'))
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
            $categoryId = self::$db->queryPrepared($sql, array($name, 'ANN'))->fetchColumn();
            if ($categoryId !== false) {
                $categoryIds[] = (int)$categoryId;
            }
        }

        return $categoryIds === array() ? array('All') : $categoryIds;
    }
}
