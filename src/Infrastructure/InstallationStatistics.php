<?php
namespace Admidio\Infrastructure;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @brief Collect anonymous installation statistics and send the reviewed JSON payload.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class InstallationStatistics
{
    /**
     * @var string Unique id of this tenant.
     */
    private string $tenantID;

    /**
     * Create a statistics collector for this installation.
     * @param string $tenantID Unique id of this tenant.
     * @param string|null $previousVersion Admidio version installed before this update.
     * @param ClientInterface|null $client HTTP client, optionally supplied for testing.
     */
    public function __construct(
        string $tenantID,
        private ?string $previousVersion = null,
        private ?ClientInterface $client = null
    )
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
            'timestamp' => InstallationStatistics::getDateTime(),
            'database' => array(
                'type' => InstallationStatistics::getDatabaseType(),
                'version' => InstallationStatistics::getDatabaseVersion()
            ),
            'admidio' => array(
                'version' => InstallationStatistics::getAdmidioVersion(),
                'previous_version' => $this->previousVersion,
                'language' => InstallationStatistics::getAdmidioLanguage(),
                'plugins' => InstallationStatistics::getAdmidioPlugins()
            ),
            'php' => array(
                'version' => InstallationStatistics::getPHPVersion(),
                'extensions' => InstallationStatistics::getPHPExtensions()
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

        return $gDb->getVersion();
    }

    /**
     * @return string
     */
    private static function getAdmidioVersion()
    {
        return ADMIDIO_VERSION_TEXT;
    }

    /** Return the configured system language code, including regional variants. */
    private static function getAdmidioLanguage(): string
    {
        global $gSettingsManager, $gL10n;

        if (isset($gSettingsManager) && $gSettingsManager->has('system_language')) {
            return $gSettingsManager->getString('system_language');
        }

        // During a fresh installation the wizard language is not stored in preferences yet.
        return isset($gL10n) ? $gL10n->getLanguage() : Language::REFERENCE_LANGUAGE;
    }

    /**
     * @return array[]
     */
    private static function getAdmidioPlugins()
    {
        $plugins = array();

        $pluginsFolder = ADMIDIO_PATH . DIRECTORY_SEPARATOR . FOLDER_PLUGINS;

        $dir = is_dir($pluginsFolder) ? scandir($pluginsFolder) : array();
        if ($dir === false) {
            return array($plugins);
        }
        foreach ($dir as $entry)
        {
            $pluginFolder = $pluginsFolder . DIRECTORY_SEPARATOR . $entry;
            $pluginFile = $pluginFolder . DIRECTORY_SEPARATOR . $entry . '.php';

            if (!in_array($entry, array('.', '..'), true) && is_dir($pluginFolder) && is_file($pluginFile))
            {
                $handle = fopen($pluginFile, 'r');
                if ($handle === false) {
                    continue;
                }
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

    /** Encode the same complete payload for the preview and the HTTP request. */
    public function getJsonData(?array $infoData = null): string
    {
        return json_encode($infoData ?? $this->getInfoData(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Sends the anonymous data of this tenant to the Admidio server.
     * @param array|null $infoData Server-side snapshot previously shown in the update dialog.
     * @return bool Returns **true** if the data could successfully send to the server.
     * @throws GuzzleException
     */
    public function sent(?array $infoData = null): bool
    {
        global $gDb;

        $infoData ??= $this->getInfoData();
        $tenantId = $gDb instanceof Database ? InstallationIdentifier::get($gDb) : null;
        if ($tenantId === null) {
            throw new Exception('SYS_TENANT_ID_NOT_INITIALIZED');
        }
        $infoData['id'] = $tenantId;
        $client = $this->client ?? new Client(array('connect_timeout' => 2, 'timeout' => 5));
        $response = $client->request(
            'POST',
            ADMIDIO_URL . '/api/statistics.php',
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => $this->getJsonData($infoData)
            )
        );

        if ($response->getStatusCode() === 200) {
            return true;
        }
        return false;
    }
}
