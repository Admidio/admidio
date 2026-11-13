<?php
namespace AdmidioPlugin\LatestDocumentsFiles\Service;

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
        LegacyConfig::import('latest-documents-files', array(
            'plgCountFiles'             => array('latest_documents_files_files_count', 'intval'),
            'plgMaxCharsFilename'       => array('latest_documents_files_max_chars_filename', 'intval'),
            'plg_show_upload_timestamp' => array('latest_documents_files_show_upload_timestamp', 'boolval')
        ));
    }
}
