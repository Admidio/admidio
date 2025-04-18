<?php
namespace Admidio\Infrastructure;

use Admidio\Infrastructure\Exception;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;

/**
 * @brief Create menu from database and serve several output formats
 *
 * This class will read the menu structure from the database table **adm_menu** and stores each main
 * node as a MenuNode object within an internal array. There are several output methods to use the
 * menu within the layout. You can create a simple html list, a bootstrap media object list or
 * add it to an existing navbar.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class TenantStatistics
{
    /**
     * @var string Unique id of this tenant.
     */
    private string $tenantID;

    /**
     * Constructor that will create an object of a TenantStatistics.
     * @param string $tenantID Unique id of this tenant.
     */
    public function __construct(string $tenantID)
    {
        $this->tenantID = $tenantID;
    }

    /**
     * @return array
     */
    public function getInfoData()
    {
        return array(
            'id' => $this->tenantID,
            'timestamp' => TenantStatistics::getDateTime(),
            'php' => array(
                'version' => TenantStatistics::getPHPVersion(),
                'extensions' => TenantStatistics::getPHPExtensions()
            ),
            'database' => array(
                'type' => TenantStatistics::getDatabaseType(),
                'version' => TenantStatistics::getDatabaseVersion()
            ),
            'admidio' => array(
                'version' => TenantStatistics::getAdmidioVersion(),
                'plugins' => TenantStatistics::getAdmidioPlugins()
            )
        );
    }

    /**
     * @return string
     */
    private static function getDateTime()
    {
        $dateTime = new DateTime('now', new DateTimeZone('UTC'));

        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * @return string
     */
    private static function getPHPVersion()
    {
        return PHP_VERSION;
    }

    /**
     * @return string[]
     */
    private static function getPHPExtensions()
    {
        $extensions = get_loaded_extensions();
        asort($extensions);

        $extensionsVersions = array();
        foreach ($extensions as $extension)
        {
            $extensionsVersions[$extension] = phpversion($extension);
        }

        return $extensionsVersions;
    }

    /**
     * @return string
     */
    private static function getDatabaseType()
    {
        global $gDbType;

        return $gDbType;
    }

    /**
     * @return string
     */
    private static function getDatabaseVersion()
    {
        global $gDb;

        $versionStatement = $gDb->query('SELECT version()');

        return $versionStatement->fetchColumn();
    }

    /**
     * @return string
     */
    private static function getAdmidioVersion()
    {
        return ADMIDIO_VERSION_TEXT;
    }

    /**
     * @return array[]
     */
    private static function getAdmidioPlugins()
    {
        $plugins = array();

        $pluginsFolder = ADMIDIO_PATH . DIRECTORY_SEPARATOR . FOLDER_PLUGINS;

        $dir = scandir($pluginsFolder);
        foreach ($dir as $entry)
        {
            $pluginFolder = $pluginsFolder . DIRECTORY_SEPARATOR . $entry;
            $pluginFile = $pluginFolder . DIRECTORY_SEPARATOR . $entry . '.php';

            if (!in_array($entry, array('.', '..'), true) && is_dir($pluginFolder) && is_file($pluginFile))
            {
                $handle = fopen($pluginFile, 'r');
                while (($line = fgets($handle)) !== false)
                {
                    preg_match('/^ \* Version (\d+\.\d+\.\d+.*$)/', $line, $matches);

                    if (count($matches) === 2)
                    {
                        $plugins[$entry] = $matches[1];
                    }
                }
                fclose($handle);
            }
        }

        return array($plugins);
    }

    /**
     * Sends the anonymous data of this tenant to the Admidio server.
     * @return bool Returns **true** if the data could successfully send to the server.
     * @throws GuzzleException
     */
    public function sent(): bool
    {
        $client = new Client();
        $response = $client->request(
            'POST',
            'http://localhost/admidio_webseite_2/api/statistics.php',
            ['json' => [$this->getInfoData()]]
        );

        if ($response->getStatusCode() === 200) {
            return true;
        }
        return false;
    }
}
