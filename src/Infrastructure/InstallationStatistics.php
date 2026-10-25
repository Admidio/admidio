<?php
namespace Admidio\Infrastructure;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Preferences\ValueObject\SettingsManager;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

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
     * Versions declared by the plugins present in the plugins directory.
     * @return array<string,string>
     */
    private static function getAdmidioPlugins(): array
    {
        $plugins = array();
        foreach (PluginRegistry::all() as $id => $plugin) {
            if ($plugin->isValid()) {
                $plugins[$id] = $plugin->version;
            }
        }

        return $plugins;
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
            $this->recordSuccessfulSend($gDb);
            return true;
        }
        return false;
    }

    /** All send paths share the installation-wide success timestamp. */
    protected function recordSuccessfulSend(Database $db): void
    {
        $settings = new SettingsManager($db, InstallationIdentifier::getOrganizationId($db));
        $settings->set('system_statistics_last_sent', self::getDateTime());
    }

    /** Whether a complete calendar month has passed since the last successful send (UTC). */
    public static function isDue(string $lastSent, ?DateTimeImmutable $now = null): bool
    {
        if ($lastSent === '') {
            return true;
        }

        $utc = new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $lastSent, $utc);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $lastSent) {
            return true;
        }

        // Clamp to the last day of shorter months (January 31 -> February 28/29).
        $nextMonth = $date->modify('first day of next month');
        $nextMonth = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('m'),
            min((int)$date->format('d'), (int)$nextMonth->format('t')));

        return $nextMonth < ($now ?? new DateTimeImmutable('now', $utc));
    }

    /** Check once on authentication; telemetry errors must never prevent a successful login. */
    public static function sendIfDue(Database $db, int $organizationId, ?ClientInterface $client = null): void
    {
        global $gLogger;

        $transactionStarted = false;
        try {
            if ($organizationId !== InstallationIdentifier::getOrganizationId($db)) {
                return;
            }

            $db->startTransaction();
            $transactionStarted = true;
            // Serialize concurrent logins until the successful send and its timestamp are stored.
            $db->queryPrepared('SELECT org_id FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ? FOR UPDATE', array($organizationId));
            $settings = new SettingsManager($db, $organizationId);
            $enabled = !$settings->has('system_statistics_enabled') || $settings->getBool('system_statistics_enabled');
            $lastSent = $settings->has('system_statistics_last_sent') ? $settings->getString('system_statistics_last_sent') : '';
            if ($enabled && self::isDue($lastSent)) {
                $tenantId = InstallationIdentifier::get($db);
                try {
                    if ($tenantId !== null && !(new self($tenantId, null, $client))->sent()) {
                        $gLogger->warning('Monthly anonymous installation statistics could not be sent.');
                    }
                } catch (GuzzleException $exception) {
                    // A network failure changes no data and must not roll back an enclosing login transaction.
                    $gLogger->warning('Monthly anonymous installation statistics could not be sent.', array('exception' => $exception));
                }
            }
            $db->endTransaction();
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $db->rollback();
            }
            $gLogger->warning('Monthly anonymous installation statistics could not be sent.', array('exception' => $exception));
        }
    }
}
