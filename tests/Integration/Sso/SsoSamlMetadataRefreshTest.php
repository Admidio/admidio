<?php
/**
 * Regression coverage for the automatic refresh of the metadata of a SAML client (SAML-05).
 *
 * The fetch is scripted, so the test covers what the service does with the answers: applying changed
 * metadata, recording the check, the schedule, the conditional requests, the failures and the
 * verification of signed metadata.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Service\MetadataFetcher;
use Admidio\SSO\Service\SAMLMetadataImporter;
use Admidio\SSO\Service\SAMLMetadataRefreshService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

class SsoSamlMetadataRefreshTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private static string $certificate = '';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$certificate = self::keyPair()[0];
    }

    /**
     * @return array{0:string,1:string} Certificate and private key PEM of a fresh key pair
     */
    private static function keyPair(): array
    {
        $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        openssl_x509_export(openssl_csr_sign(openssl_csr_new(array('commonName' => 'refresh-test'), $key), null, $key, 1), $certificate);
        openssl_pkey_export($key, $privateKey);
        return array(trim($certificate), $privateKey);
    }

    private function body(string $pem): string
    {
        return preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
    }

    private function metadata(string $entityId, string $acs, string $attributes = '', ?string $certificate = null): string
    {
        return '<?xml version="1.0"?><md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"'
            . ' ID="_' . md5($entityId . $acs) . '" entityID="' . $entityId . '"' . $attributes . '>'
            . '<md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            . '<md:KeyDescriptor use="signing"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>' . $this->body($certificate ?? self::$certificate)
            . '</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>'
            . '<md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="' . $acs . '" index="0"/>'
            . '</md:SPSSODescriptor></md:EntityDescriptor>';
    }

    /**
     * Sign the root element of a metadata document, with the signature as its first child.
     */
    private function sign(string $xml, string $certificate, string $privateKey): string
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);
        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $document->documentElement,
            XMLSecurityDSig::SHA256,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
            array('id_name' => 'ID', 'overwrite' => false)
        );
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type' => 'private'));
        $key->loadKey($privateKey);
        $signature->sign($key);
        $signature->add509Cert($certificate);
        $signature->appendSignature($document->documentElement, true);
        return $document->saveXML();
    }

    /**
     * A service whose fetches are answered from a list; each answer is a body, or an exception.
     * @param array<int,array|\Throwable> $answers
     */
    private function service(array $answers, array &$requests = array()): SAMLMetadataRefreshService
    {
        $fetcher = new class ($answers, $requests) extends MetadataFetcher {
            public function __construct(private array $answers, private array &$requests)
            {
                parent::__construct(true);
            }

            public function fetchConditional(string $url, ?string $etag = null, ?string $lastModified = null): array
            {
                $this->requests[] = array($url, $etag, $lastModified);
                $answer = array_shift($this->answers);
                if ($answer instanceof \Throwable) {
                    throw $answer;
                }
                return $answer;
            }
        };
        return new SAMLMetadataRefreshService($this->getDatabase(), static fn () => $fetcher);
    }

    private function answer(?string $body, ?string $etag = null): array
    {
        return array('body' => $body, 'etag' => $etag, 'lastModified' => null);
    }

    private function clientWithUrl(array $columns = array()): SAMLClient
    {
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_metadata_url', 'https://sp.example/metadata');
        $client->setValue('smc_metadata_refresh', true);
        foreach ($columns as $column => $value) {
            $client->setValue($column, $value);
        }
        $client->save();
        return new SAMLClient($this->getDatabase(), (int) $client->getValue('smc_id'));
    }

    private function reload(SAMLClient $client): SAMLClient
    {
        return new SAMLClient($this->getDatabase(), (int) $client->getValue('smc_id'));
    }

    private function logRows(SAMLClient $client): int
    {
        return (int) $this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_LOG_CHANGES . ' WHERE log_table = ? AND log_record_id = ?',
            array('saml_clients', (int) $client->getValue('smc_id'))
        )->fetchColumn();
    }

    /**
     * @testdox The next check comes after 75 percent of the validity, between 15 minutes and 4 hours
     */
    public function testTheScheduleFollowsTheValidity(): void
    {
        $now = 1000000;
        $delay = static fn (?int $until, ?int $cache) => SAMLMetadataRefreshService::getDelayAfterSuccess($until, $cache, $now);

        $this->assertSame(14400, $delay(null, null), 'no statement: every 4 hours');
        $this->assertSame(14400, $delay($now + 14 * 86400, null), 'two weeks: capped at 4 hours');
        $this->assertSame(3600, $delay($now + 4800, null), '75 percent of 80 minutes');
        $this->assertSame(900, $delay($now + 600, null), 'short validity: at least 15 minutes');
        $this->assertSame(900, $delay($now - 600, null), 'already past: at least 15 minutes');
        $this->assertSame(1800, $delay($now + 86400, 2400), 'the shorter of cacheDuration and validUntil counts');
    }

    /**
     * @testdox After a failure the check is repeated after 15 minutes, doubling up to 4 hours
     */
    public function testTheRetryDelayGrows(): void
    {
        $this->assertSame(
            array(900, 1800, 3600, 7200, 14400, 14400),
            array_map(static fn (int $failures) => SAMLMetadataRefreshService::getDelayAfterFailure($failures), array(1, 2, 3, 4, 5, 20))
        );
    }

    /**
     * @testdox A cache duration is read as an xs:duration
     */
    public function testDurationsAreParsed(): void
    {
        $this->assertSame(86400, SAMLMetadataImporter::parseDuration('P1D'));
        $this->assertSame(5400, SAMLMetadataImporter::parseDuration('PT1H30M'));
        $this->assertSame(90, SAMLMetadataImporter::parseDuration('PT90.5S'));
        $this->assertNull(SAMLMetadataImporter::parseDuration('tomorrow'));
        $this->assertNull(SAMLMetadataImporter::parseDuration('P'));
        $this->assertNull(SAMLMetadataImporter::parseDuration('P1DT'));
    }

    /**
     * @testdox Changed metadata is applied and the check is recorded
     */
    public function testChangedMetadataIsApplied(): void
    {
        $client = $this->clientWithUrl();
        $entityId = (string) $client->getValue('smc_client_id');
        $until = gmdate('Y-m-d\TH:i:s\Z', time() + 86400);
        $requests = array();

        $result = $this->service(array($this->answer($this->metadata($entityId, 'https://sp.example/new-acs', ' validUntil="' . $until . '"'), '"v1"')), $requests)
            ->refresh($client);

        $this->assertSame(SAMLMetadataRefreshService::UPDATED, $result);
        $client = $this->reload($client);
        $this->assertSame('https://sp.example/new-acs', $client->getValue('smc_acs_url'));
        $this->assertSame('"v1"', $client->getValue('smc_metadata_etag', 'database'));
        $this->assertSame(0, (int) $client->getValue('smc_metadata_failures'));
        $this->assertSame('', (string) $client->getValue('smc_metadata_error'));
        $this->assertEqualsWithDelta(time() + 14400, strtotime($client->getValue('smc_metadata_next_check', 'database')), 10);
        $this->assertEqualsWithDelta(strtotime($until), strtotime($client->getValue('smc_metadata_valid_until', 'database')), 5);
        $this->assertSame(array(array('https://sp.example/metadata', null, null)), $requests);
    }

    /**
     * @testdox The version of the last check is sent with the next one, and unchanged metadata changes nothing
     */
    public function testUnchangedMetadataIsNotApplied(): void
    {
        $client = $this->clientWithUrl();
        $entityId = (string) $client->getValue('smc_client_id');
        $xml = $this->metadata($entityId, 'https://sp.example/acs-1');
        $requests = array();
        $service = $this->service(array($this->answer($xml, '"v1"'), $this->answer(null, '"v1"'), $this->answer($xml, '"v2"')), $requests);

        $this->assertSame(SAMLMetadataRefreshService::UPDATED, $service->refresh($client));
        $client = $this->reload($client);
        // someone edits the client by hand; neither 304 nor the same document brings the old value back
        $client->setValue('smc_acs_url', 'https://sp.example/by-hand');
        $client->save();

        $this->assertSame(SAMLMetadataRefreshService::UNCHANGED, $service->refresh($client));
        $this->assertSame(SAMLMetadataRefreshService::UNCHANGED, $service->refresh($client));
        $this->assertSame('https://sp.example/by-hand', $this->reload($client)->getValue('smc_acs_url'));
        $this->assertSame('"v2"', $this->reload($client)->getValue('smc_metadata_etag', 'database'));
        $this->assertSame('"v1"', $requests[1][1], 'the ETag of the last check is sent');
    }

    /**
     * @testdox A forced refresh ignores the version and applies the metadata again
     */
    public function testAForcedRefreshAppliesTheMetadataAgain(): void
    {
        $client = $this->clientWithUrl();
        $xml = $this->metadata((string) $client->getValue('smc_client_id'), 'https://sp.example/acs-1');
        $service = $this->service(array($this->answer($xml, '"v1"'), $this->answer($xml, '"v1"')));

        $service->refresh($client);
        $client = $this->reload($client);
        $client->setValue('smc_acs_url', 'https://sp.example/by-hand');
        $client->save();

        $this->assertSame(SAMLMetadataRefreshService::UPDATED, $service->refresh($client, true));
        $this->assertSame('https://sp.example/acs-1', $this->reload($client)->getValue('smc_acs_url'));
    }

    /**
     * @testdox A failed fetch keeps the settings and is repeated later, a later success clears it
     */
    public function testAFailedFetchKeepsTheSettings(): void
    {
        $client = $this->clientWithUrl();
        $acs = (string) $client->getValue('smc_acs_url');
        $xml = $this->metadata((string) $client->getValue('smc_client_id'), 'https://sp.example/acs-1');
        $service = $this->service(array(new RuntimeException('down'), new RuntimeException('down'), $this->answer($xml)));

        foreach (array(900, 1800) as $expectedDelay) {
            try {
                $service->refresh($this->reload($client));
                $this->fail('The failure was not reported.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_SSO_METADATA_LOAD_FAILED', $exception->getTranslationId());
            }
            $failed = $this->reload($client);
            $this->assertSame($acs, $failed->getValue('smc_acs_url'));
            $this->assertSame('SYS_SSO_METADATA_LOAD_FAILED', $failed->getValue('smc_metadata_error'));
            $this->assertEqualsWithDelta(time() + $expectedDelay, strtotime($failed->getValue('smc_metadata_next_check', 'database')), 10);
        }
        $this->assertSame(2, (int) $this->reload($client)->getValue('smc_metadata_failures'));

        $service->refresh($this->reload($client));
        $this->assertSame(0, (int) $this->reload($client)->getValue('smc_metadata_failures'));
        $this->assertSame('', (string) $this->reload($client)->getValue('smc_metadata_error'));
    }

    /**
     * @testdox Metadata that is past its validUntil stays in use and is marked
     */
    public function testExpiredMetadataStaysInUse(): void
    {
        $client = $this->clientWithUrl();
        $past = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
        $xml = $this->metadata((string) $client->getValue('smc_client_id'), 'https://sp.example/expired', ' validUntil="' . $past . '"');

        $this->assertSame(SAMLMetadataRefreshService::UPDATED, $this->service(array($this->answer($xml)))->refresh($client));
        $client = $this->reload($client);
        $this->assertSame('https://sp.example/expired', $client->getValue('smc_acs_url'));
        $this->assertSame('SYS_SSO_METADATA_EXPIRED', $client->getValue('smc_metadata_error'));
    }

    /**
     * @testdox Metadata of another entity is refused and recorded as a failure
     */
    public function testMetadataOfAnotherEntityIsRefused(): void
    {
        $client = $this->clientWithUrl();
        $service = $this->service(array($this->answer($this->metadata('https://other.example/sp', 'https://other.example/acs'))));

        try {
            $service->refresh($client);
            $this->fail('The metadata was accepted.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_SSO_METADATA_ENTITY_MISSING', $exception->getTranslationId());
        }
        $this->assertSame('SYS_SSO_METADATA_ENTITY_MISSING', $this->reload($client)->getValue('smc_metadata_error'));
    }

    /**
     * @testdox The record of a check stays out of the changelog, the settings it applies do not
     */
    public function testOnlyAppliedSettingsAreLogged(): void
    {
        global $gSettingsManager;
        $previous = array($gSettingsManager->get('changelog_module_enabled'), $gSettingsManager->get('changelog_table_saml_clients'));
        $gSettingsManager->set('changelog_module_enabled', 1);
        $gSettingsManager->set('changelog_table_saml_clients', 1);
        Entity::setLoggingEnabled(true);

        try {
            $this->assertOnlyAppliedSettingsAreLogged();
        } finally {
            Entity::setLoggingEnabled(false);
            $gSettingsManager->set('changelog_module_enabled', $previous[0]);
            $gSettingsManager->set('changelog_table_saml_clients', $previous[1]);
        }
    }

    private function assertOnlyAppliedSettingsAreLogged(): void
    {
        $client = $this->clientWithUrl();
        $entityId = (string) $client->getValue('smc_client_id');
        $before = $this->logRows($client);

        $service = $this->service(array(
            new RuntimeException('down'),
            $this->answer($this->metadata($entityId, 'https://sp.example/acs-2')),
            $this->answer(null)
        ));
        try {
            $service->refresh($this->reload($client));
        } catch (Exception) {
        }
        $this->assertSame($before, $this->logRows($client), 'a failed check is not a change');

        $service->refresh($this->reload($client), true);
        $afterUpdate = $this->logRows($client);
        $this->assertGreaterThan($before, $afterUpdate, 'the new endpoint is a change');

        $service->refresh($this->reload($client));
        $this->assertSame($afterUpdate, $this->logRows($client), 'an unchanged check is not a change');
    }

    /**
     * @testdox Only clients that are due are checked
     */
    public function testOnlyDueClientsAreChecked(): void
    {
        $due = $this->clientWithUrl();
        $later = $this->clientWithUrl(array('smc_metadata_next_check' => date('Y-m-d H:i:s', time() + 3600)));
        $manual = $this->clientWithUrl(array('smc_metadata_refresh' => false));
        $requests = array();

        $rows = $this->service(array($this->answer($this->metadata((string) $due->getValue('smc_client_id'), 'https://sp.example/due'))), $requests)
            ->refreshDue();

        $ids = array_column($rows, 'client');
        $this->assertContains((string) $due->getValue('smc_client_id'), $ids);
        $this->assertNotContains((string) $later->getValue('smc_client_id'), $ids);
        $this->assertNotContains((string) $manual->getValue('smc_client_id'), $ids);

        // the check moved its next date, so a second run finds nothing for it
        $again = array_column($this->service(array(), $requests)->refreshDue(), 'client');
        $this->assertNotContains((string) $due->getValue('smc_client_id'), $again);
    }

    /**
     * @testdox Signed metadata is accepted with the certificate it is signed with, in a list of several
     */
    public function testSignedMetadataIsVerified(): void
    {
        [$certificate, $privateKey] = self::keyPair();
        [$otherCertificate] = self::keyPair();
        $client = $this->clientWithUrl(array('smc_metadata_signing_certificate' => $otherCertificate . "\n" . $certificate));
        $signed = $this->sign($this->metadata((string) $client->getValue('smc_client_id'), 'https://sp.example/signed'), $certificate, $privateKey);

        $this->assertSame(SAMLMetadataRefreshService::UPDATED, $this->service(array($this->answer($signed)))->refresh($client));
        $this->assertSame('https://sp.example/signed', $this->reload($client)->getValue('smc_acs_url'));
    }

    /**
     * @testdox Unsigned, wrongly signed and tampered metadata is refused when a signing certificate is configured
     */
    public function testUntrustedMetadataIsRefused(): void
    {
        [$certificate, $privateKey] = self::keyPair();
        [$otherCertificate, $otherKey] = self::keyPair();
        $client = $this->clientWithUrl(array('smc_metadata_signing_certificate' => $certificate));
        $acs = (string) $client->getValue('smc_acs_url');
        $entityId = (string) $client->getValue('smc_client_id');
        $plain = $this->metadata($entityId, 'https://sp.example/evil');

        $cases = array(
            'SYS_SSO_METADATA_SIGNATURE_MISSING' => $plain,
            'SYS_SSO_METADATA_SIGNATURE_INVALID' => $this->sign($plain, $otherCertificate, $otherKey),
        );
        // the document is changed after it was signed
        $cases['tampered'] = str_replace('https://sp.example/evil', 'https://sp.example/other', $this->sign($plain, $certificate, $privateKey));

        foreach ($cases as $expected => $xml) {
            try {
                $this->service(array($this->answer($xml)))->refresh($this->reload($client), true);
                $this->fail('The metadata of case "' . $expected . '" was accepted.');
            } catch (Exception $exception) {
                $this->assertSame($expected === 'tampered' ? 'SYS_SSO_METADATA_SIGNATURE_INVALID' : $expected, $exception->getTranslationId());
            }
            $this->assertSame($acs, $this->reload($client)->getValue('smc_acs_url'), 'the settings stay as they were');
        }
    }

    /**
     * @testdox Without a signing certificate the metadata does not have to be signed
     */
    public function testUnsignedMetadataIsAcceptedWithoutATrustAnchor(): void
    {
        $client = $this->clientWithUrl();
        $xml = $this->metadata((string) $client->getValue('smc_client_id'), 'https://sp.example/plain');

        $this->assertSame(SAMLMetadataRefreshService::UPDATED, $this->service(array($this->answer($xml)))->refresh($client));
    }
}
