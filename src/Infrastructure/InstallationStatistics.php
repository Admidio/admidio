<?php
namespace Admidio\Infrastructure;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Preferences\ValueObject\SettingsManager;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * @brief Collect pseudonymous installation statistics and send the reviewed JSON payload.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class InstallationStatistics
{
    /**
     * @param string|null $previousVersion Admidio version installed before this update.
     * @param Closure|null $httpPost Optional transport accepting URL and JSON and returning the HTTP status code (0 on failure).
     */
    public function __construct(
        private ?string $previousVersion = null,
        private ?Closure $httpPost = null
    ) {
    }

    /**
     * @return array
     */
    public function getInfoData(?string $previewId = null): array
    {
        global $gDb;

        return array(
            'id' => $previewId ?? InstallationIdentifier::get($gDb),
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
    private static function getDateTime(): string
    {
        $dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $dateTime->format('Y-m-d\\TH:i:s\\Z');
    }

    /**
     * @return string
     */
    private static function getPHPVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * @return array<string, string|null>
     */
    private static function getPHPExtensions(): array
    {
        $extensions = get_loaded_extensions();
        asort($extensions);

        $extensionsVersions = array();
        foreach ($extensions as $extension) {
            $version = phpversion($extension);
            $extensionsVersions[$extension] = $version === false ? null : $version;
        }

        return $extensionsVersions;
    }

    /**
     * @return string
     */
    private static function getDatabaseType(): string
    {
        global $gDb;

        return $gDb->getInformation()[0];
    }

    /**
     * @return string
     */
    private static function getDatabaseVersion(): string
    {
        global $gDb;

        return $gDb->getVersion();
    }

    /**
     * @return string
     */
    private static function getAdmidioVersion(): string
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
     * Manifest versions of installed plugins enabled in at least one organization.
     * @return array<string,string>
     */
    private static function getAdmidioPlugins(): array
    {
        global $gSettingsManager;

        // The installer preview runs before any plugin or preference tables exist.
        if (!isset($gSettingsManager)) {
            return array();
        }
        $plugins = array();
        foreach (PluginRegistry::all() as $id => $plugin) {
            if ($plugin->isValid() && PluginRegistry::isInstalled((string)$id)
                && PluginRegistry::getEnabledOrganizations((string)$id) !== array()) {
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
     * Sends the pseudonymous data of this tenant to the Admidio server.
     * @param array|null $infoData Server-side snapshot previously shown in the update dialog.
     * @return bool Returns **true** if the data could successfully send to the server.
     */
    public function send(?array $infoData = null): bool
    {
        global $gDb, $gStatisticsUrl;

        if (!isset($gStatisticsUrl)) {
            $statement = $gDb->queryPrepared(
                'SELECT org_shortname FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?', array(1));
            if ($statement->fetchColumn() === 'ADM_DEMO') {
                return false;
            }
        }

        $infoData ??= $this->getInfoData();
        $tenantId = $gDb instanceof Database ? InstallationIdentifier::get($gDb) : null;
        if ($tenantId === null) {
            throw new Exception('SYS_TENANT_ID_NOT_INITIALIZED');
        }
        $infoData['id'] = $tenantId;
        $url = $gStatisticsUrl ?? 'https://www.admidio.org/api/installation-statistics/statistics.php';
        $json = $this->getJsonData($infoData);
        $statusCode = $this->httpPost !== null ? ($this->httpPost)($url, $json) : $this->postJson($url, $json);

        if ($statusCode === 200) {
            $this->recordSuccessfulSend($gDb);
            return true;
        }
        return false;
    }

    /** Send JSON using the cURL extension already required by Admidio. */
    private function postJson(string $url, string $json): int
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return 0;
        }

        try {
            curl_setopt_array($curl, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
                // The response body is not needed; discard it without buffering.
                CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk): int {
                    return strlen($chunk);
                }
            ));
            if (curl_exec($curl) === false) {
                return 0;
            }
            return (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }
    }

    /** Null means that the installation/update has not initialized the preference yet. */
    public static function getLastSent(Database $db): ?string
    {
        $value = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
            WHERE prf_name = ? AND prf_org_id IS NULL', array('system_statistics_last_sent'))->fetchColumn();

        return is_string($value) ? $value : null;
    }

    /** All send paths share the installation-wide success timestamp. */
    protected function recordSuccessfulSend(Database $db): void
    {
        $db->queryPrepared('UPDATE ' . TBL_PREFERENCES . '
            SET prf_value = ? WHERE prf_name = ? AND prf_org_id IS NULL',
            array(self::getDateTime(), 'system_statistics_last_sent'));
    }

    /** Whether the monthly interval or a reserved retry deadline has passed (UTC). */
    public static function isDue(string $lastSent, ?DateTimeImmutable $now = null): bool
    {
        if ($lastSent === '') {
            return true;
        }

        $utc = new DateTimeZone('UTC');
        $retry = str_starts_with($lastSent, 'retry:');
        if ($retry) {
            $lastSent = substr($lastSent, 6);
        }
        $format = str_contains($lastSent, 'T') ? 'Y-m-d\\TH:i:s\\Z' : 'Y-m-d H:i:s';
        $date = DateTimeImmutable::createFromFormat('!' . $format, $lastSent, $utc);
        if ($date === false || $date->format($format) !== $lastSent) {
            return true;
        }

        if ($retry) {
            return $date <= ($now ?? new DateTimeImmutable('now', $utc));
        }

        // Clamp to the last day of shorter months (January 31 -> February 28/29).
        $nextMonth = $date->modify('first day of next month');
        $nextMonth = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('m'),
            min((int)$date->format('d'), (int)$nextMonth->format('t')));

        return $nextMonth < ($now ?? new DateTimeImmutable('now', $utc));
    }

    /** Check when rebuilding the organization; statistics must never prevent loading a page. */
    public static function sendIfDue(Database $db, int $organizationId, ?Closure $httpPost = null): void
    {
        global $gLogger;

        $transactionStarted = false;
        try {
            // Consent remains controlled by the main organization.
            if ($organizationId !== InstallationIdentifier::getOrganizationId($db)) {
                return;
            }
            $settings = new SettingsManager($db, $organizationId);
            $lastSent = self::getLastSent($db);
            if ($lastSent === null
                || !$settings->has('system_statistics_enabled')
                || !$settings->getBool('system_statistics_enabled')
                || !self::isDue($lastSent)) {
                return;
            }

            $db->startTransaction();
            $transactionStarted = true;
            // Serialize concurrent requests and recheck after acquiring the lock.
            $db->queryPrepared('SELECT com_id FROM ' . TBL_COMPONENTS . '
                WHERE com_name_intern = ? FOR UPDATE', array('CORE'));
            $settings = new SettingsManager($db, $organizationId);
            $lastSent = self::getLastSent($db);
            if ($lastSent !== null
                && $settings->has('system_statistics_enabled')
                && $settings->getBool('system_statistics_enabled')
                && self::isDue($lastSent)) {
                // Reserve the attempt before releasing the lock. Failed requests (including
                // exceptions) keep this deadline so visitors cannot trigger immediate retries.
                $retryAt = new DateTimeImmutable('+1 hour', new DateTimeZone('UTC'));
                $db->queryPrepared('UPDATE ' . TBL_PREFERENCES . '
                    SET prf_value = ? WHERE prf_name = ? AND prf_org_id IS NULL',
                    array('retry:' . $retryAt->format('Y-m-d\\TH:i:s\\Z'), 'system_statistics_last_sent'));
            } else {
                $db->endTransaction();
                return;
            }
            $db->endTransaction();
            $transactionStarted = false;

            // Never hold the database lock while waiting for the statistics server.
            if (!(new self(null, $httpPost))->send()) {
                $gLogger->warning('Monthly pseudonymous installation statistics could not be sent.');
            }
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $db->rollback();
            }
            $gLogger->warning('Monthly pseudonymous installation statistics could not be sent.', array('exception' => $exception));
        }
    }
}
