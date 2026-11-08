<?php
namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\SSO\Entity\SAMLClient;
use Throwable;

/**
 * Keeps the settings of a SAML client in step with the metadata of its service provider.
 *
 * A client with a metadata URL and automatic update is checked again when its metadata asks for
 * it, as the Shibboleth metadata providers do: after 75 % of the time the metadata is valid
 * (the shorter of cacheDuration and the time until validUntil), but at least every 4 hours and
 * not more often than every 15 minutes. A fetch is conditional (ETag, Last-Modified), so a check
 * of metadata that did not change costs one small request. Metadata that cannot be fetched, is not
 * valid or is not signed as required leaves the last settings in place, and the check is repeated
 * after a growing delay.
 *
 * Metadata past its own validUntil is not rejected: it stays in use, but the state shows it.
 *
 * What the refresh records about its checks is kept in the client record itself, in columns the
 * changelog ignores (SAMLClient::getIgnoredLogColumns()), while the settings it applies are logged.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SAMLMetadataRefreshService
{
    public const UPDATED = 'updated';
    public const UNCHANGED = 'unchanged';
    public const FAILED = 'failed';

    /** Shortest and longest time between two checks, in seconds */
    public const MIN_INTERVAL = 900;
    public const MAX_INTERVAL = 14400;

    /** Time a check that is running keeps the client from being checked by a second process, in seconds */
    private const CLAIM_SECONDS = 300;

    private Database $db;

    /** @var callable|null Creates the fetcher for a client; replaced by the tests */
    private $fetcherFactory;

    /**
     * @param Database $db
     * @param callable|null $fetcherFactory Receives the client and returns the MetadataFetcher to use. By default
     *                                      the policy of the organization of the client applies.
     */
    public function __construct(Database $db, ?callable $fetcherFactory = null)
    {
        $this->db = $db;
        $this->fetcherFactory = $fetcherFactory;
    }

    /**
     * Seconds until the metadata is checked again after a successful check.
     * @param int|null $validUntil Timestamp up to which the metadata is valid.
     * @param int|null $cacheDuration Seconds the metadata may be cached.
     * @param int $now Timestamp of the check.
     */
    public static function getDelayAfterSuccess(?int $validUntil, ?int $cacheDuration, int $now): int
    {
        $validity = $cacheDuration;
        if ($validUntil !== null) {
            $validity = $validity === null ? $validUntil - $now : min($validity, $validUntil - $now);
        }
        if ($validity === null) {
            return self::MAX_INTERVAL;
        }
        return max(self::MIN_INTERVAL, min(self::MAX_INTERVAL, (int) floor($validity * 0.75)));
    }

    /**
     * Seconds until the metadata is checked again after a failed check: 15 minutes, doubled with every
     * further failure, at most 4 hours.
     * @param int $failures Number of failed checks in a row, including this one.
     */
    public static function getDelayAfterFailure(int $failures): int
    {
        return (int) min(self::MAX_INTERVAL, self::MIN_INTERVAL * (2 ** max(0, min($failures, 8) - 1)));
    }

    /**
     * Check the metadata of all clients that are due. Used by the maintenance job.
     * @param int $maxClients Most clients checked in one run, so that one run stays short.
     * @param int $timeBudget Seconds after which no further client is started.
     * @return array<int,array{client:string,result:string,message:string}> One row per client checked.
     * @throws Exception
     */
    public function refreshDue(int $maxClients = 20, int $timeBudget = 45): array
    {
        $now = time();
        $ids = $this->db->queryPrepared(
            'SELECT smc_id
               FROM ' . TBL_SAML_CLIENTS . '
              WHERE smc_metadata_refresh = ?
                AND smc_enabled = ?
                AND smc_metadata_url IS NOT NULL
                AND smc_metadata_url <> ?
                AND (smc_metadata_next_check IS NULL OR smc_metadata_next_check <= ?)
              ORDER BY smc_metadata_next_check, smc_id',
            array(true, true, '', date('Y-m-d H:i:s', $now))
        )->fetchAll(\PDO::FETCH_COLUMN);

        $rows = array();
        foreach ($ids as $id) {
            if (count($rows) >= $maxClients || time() - $now >= $timeBudget) {
                break;
            }

            $client = new SAMLClient($this->db, (int) $id);
            if (!$this->claim($client)) {
                continue;
            }

            try {
                $result = $this->refresh($client);
                $message = '';
            } catch (Throwable $exception) {
                $result = self::FAILED;
                $message = $exception instanceof Exception ? $exception->getTranslationId() : $exception->getMessage();
            }
            $rows[] = array('client' => $client->getIdentifier(), 'result' => $result, 'message' => $message);
        }
        return $rows;
    }

    /**
     * Fetch the metadata of the client again and apply it. The result is recorded in the client.
     * @param SAMLClient $client The client whose metadata is refreshed.
     * @param bool $force Fetch and apply the metadata even if the server says it did not change.
     * @return string UPDATED if settings of the client changed, UNCHANGED otherwise.
     * @throws Exception SYS_SSO_METADATA_URL_REQUIRED, SYS_SSO_METADATA_LOAD_FAILED, SYS_SSO_METADATA_INVALID,
     *                   SYS_SSO_METADATA_ENTITY_MISSING, SYS_SSO_METADATA_SIGNATURE_MISSING or
     *                   SYS_SSO_METADATA_SIGNATURE_INVALID. The failure is recorded before it is thrown.
     */
    public function refresh(SAMLClient $client, bool $force = false): string
    {
        global $gLogger;

        $url = trim((string) $client->getValue('smc_metadata_url'));
        if ($url === '') {
            throw new Exception('SYS_SSO_METADATA_URL_REQUIRED');
        }

        $clientId = (string) $client->getValue('smc_client_id');
        $fetchedAt = time();

        try {
            $response = $this->createFetcher($client)->fetchConditional(
                $url,
                $force ? null : $this->stored($client, 'smc_metadata_etag'),
                $force ? null : $this->stored($client, 'smc_metadata_last_modified')
            );
        } catch (Throwable $exception) {
            $gLogger->warning('Could not fetch SAML metadata.', array(
                'client' => $clientId, 'url' => $url, 'reason' => $exception->getMessage()
            ));
            $this->recordFailure($client, 'SYS_SSO_METADATA_LOAD_FAILED', $fetchedAt);
            throw new Exception('SYS_SSO_METADATA_LOAD_FAILED');
        }

        $body = $response['body'];
        $hash = $body === null ? null : hash('sha256', $body);
        if ($body === null || (!$force && $hash === $this->stored($client, 'smc_metadata_hash'))) {
            $this->recordSuccess($client, $fetchedAt, $response, $this->storedTime($client), $this->storedCacheDuration($client), false);
            return self::UNCHANGED;
        }

        try {
            $validity = (new SAMLMetadataImporter())->apply(
                $client,
                $body,
                (string) $client->getValue('smc_metadata_signing_certificate')
            );
        } catch (Exception $exception) {
            $gLogger->warning('Rejected SAML metadata.', array(
                'client' => $clientId, 'url' => $url, 'reason' => $exception->getTranslationId()
            ));
            // the importer refuses a document before it changes anything, so the client holds the old settings
            $this->recordFailure($client, $exception->getTranslationId(), $fetchedAt);
            throw $exception;
        }

        $this->recordSuccess($client, $fetchedAt, $response, $validity['validUntil'], $validity['cacheDuration'], true, $hash);
        return self::UPDATED;
    }

    private function createFetcher(SAMLClient $client): MetadataFetcher
    {
        if ($this->fetcherFactory !== null) {
            return ($this->fetcherFactory)($client);
        }

        $settings = new SettingsManager($this->db, (int) $client->getValue('smc_org_id'));
        return new MetadataFetcher($settings->getBool('sso_allow_private_network'));
    }

    /**
     * Take the client for a check, so that two processes do not check it at the same time.
     */
    private function claim(SAMLClient $client): bool
    {
        $now = time();
        $statement = $this->db->queryPrepared(
            'UPDATE ' . TBL_SAML_CLIENTS . '
                SET smc_metadata_next_check = ?
              WHERE smc_id = ?
                AND (smc_metadata_next_check IS NULL OR smc_metadata_next_check <= ?)',
            array(date('Y-m-d H:i:s', $now + self::CLAIM_SECONDS), $client->getValue('smc_id'), date('Y-m-d H:i:s', $now))
        );
        return $statement->rowCount() === 1;
    }

    private function stored(SAMLClient $client, string $column): ?string
    {
        $value = $client->getValue($column, 'database');
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function storedTime(SAMLClient $client): ?int
    {
        $value = $this->stored($client, 'smc_metadata_valid_until');
        return $value === null ? null : (strtotime($value) ?: null);
    }

    /**
     * The cacheDuration of the metadata that is in use. An unchanged answer does not carry it again,
     * and without it the next check would be scheduled as if the metadata gave no cache hint.
     */
    private function storedCacheDuration(SAMLClient $client): ?int
    {
        $value = $this->stored($client, 'smc_metadata_cache_duration');
        return $value === null ? null : (int) $value;
    }

    /**
     * @param array{body:string|null,etag:string|null,lastModified:string|null} $response
     * @param int|null $validUntil
     * @param int|null $cacheDuration
     */
    private function recordSuccess(SAMLClient $client, int $now, array $response, ?int $validUntil, ?int $cacheDuration, bool $changed, ?string $hash = null): void
    {
        $client->setValue('smc_metadata_checked', date('Y-m-d H:i:s', $now));
        $client->setValue('smc_metadata_next_check', date('Y-m-d H:i:s', $now + self::getDelayAfterSuccess($validUntil, $cacheDuration, $now)));
        $client->setValue('smc_metadata_etag', $response['etag']);
        $client->setValue('smc_metadata_last_modified', $response['lastModified']);
        if ($hash !== null) {
            $client->setValue('smc_metadata_hash', $hash);
        }
        $client->setValue('smc_metadata_valid_until', $validUntil === null ? null : date('Y-m-d H:i:s', $validUntil));
        $client->setValue('smc_metadata_cache_duration', $cacheDuration);
        $client->setValue('smc_metadata_failures', 0);
        $client->setValue('smc_metadata_error', $validUntil !== null && $validUntil < $now ? 'SYS_SSO_METADATA_EXPIRED' : null);
        // only the record of the check changes if the metadata did not, which is neither a change by a person
        // nor worth a new change date of the client
        $client->save($changed);
    }

    private function recordFailure(SAMLClient $client, string $error, int $now): void
    {
        $failures = (int) $client->getValue('smc_metadata_failures') + 1;
        $client->setValue('smc_metadata_checked', date('Y-m-d H:i:s', $now));
        $client->setValue('smc_metadata_next_check', date('Y-m-d H:i:s', $now + self::getDelayAfterFailure($failures)));
        $client->setValue('smc_metadata_failures', $failures);
        $client->setValue('smc_metadata_error', $error);
        $client->save(false);
    }
}
