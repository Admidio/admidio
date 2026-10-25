<?php

namespace Admidio\Tests\Integration\Installation;

use Admidio\Infrastructure\InstallationIdentifier;
use Admidio\Infrastructure\InstallationStatistics;
use Admidio\InstallationUpdate\Service\UpdateStepsCode;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Admidio\InstallationUpdate\Service\StatisticsConsent;

class InstallationIdentifierTest extends DatabaseTestCase
{
    public function testFreshProductionInstallationAlreadyHasIdentityBeforeAnySend(): void
    {
        // DatabaseTestCase builds the fixture via the real Installation::install service.
        $this->assertValidUuid(InstallationIdentifier::get($this->getDatabase()));
    }

    public function testIdentityIsCreatedOnceForTheSmallestOrganizationOnly(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'));
        $other = (new AdmidioTestFixture($db))->createAndSaveOrganization('Other tenant ID', 'tenantid');
        $otherId = Uuid::uuid4()->toString();
        (new SettingsManager($db, $other['org_id']))->set('system_tenant_id', $otherId);
        $this->assertNull(InstallationIdentifier::get($db), 'Other organizations must not provide a fallback ID.');

        $id = InstallationIdentifier::ensure($db);
        $this->assertValidUuid($id);
        $this->assertSame($id, InstallationIdentifier::get($db));
        $this->assertSame($id, InstallationIdentifier::ensure($db, Uuid::uuid4()->toString()));
        $firstOrgId = (int) $db->queryPrepared('SELECT MIN(org_id) FROM ' . TBL_ORGANIZATIONS)->fetchColumn();
        $this->assertSame($id, (new SettingsManager($db, $firstOrgId))->getString('system_tenant_id'));
        $this->assertSame($otherId, (new SettingsManager($db, $other['org_id']))->getString('system_tenant_id'));
    }

    public function testUpdateStepCreatesIdentityWithoutStatisticsConsentAndKeepsItOnRetry(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'));
        UpdateStepsCode::setDatabase($db);
        UpdateStepsCode::updateStep60CreateTenantId();
        $id = InstallationIdentifier::get($db);
        $this->assertValidUuid($id);
        UpdateStepsCode::updateStep60CreateTenantId();
        $this->assertSame($id, InstallationIdentifier::get($db));
        $count = $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'))->fetchColumn();
        $this->assertSame(1, (int) $count);
    }

    public function testReviewedIdentityIsUsedOnFirstCreation(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'));
        $previewId = Uuid::uuid4()->toString();
        $this->assertSame($previewId, InstallationIdentifier::ensure($db, $previewId));
        $this->assertSame($previewId, InstallationIdentifier::get($db));
    }

    public function testSenderAlwaysUsesTheFirstOrganizationIdentity(): void
    {
        $canonicalId = InstallationIdentifier::ensure($this->getDatabase());
        $history = array();
        $handler = HandlerStack::create(new MockHandler(array(new Response(200))));
        $handler->push(Middleware::history($history));
        $otherId = Uuid::uuid4()->toString();
        $statistics = new InstallationStatistics($otherId, null, new Client(array('handler' => $handler)));
        $this->assertTrue($statistics->sent(array('id' => $otherId)));
        $sent = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($canonicalId, $sent['id']);
        $settings = new SettingsManager($this->getDatabase(), InstallationIdentifier::getOrganizationId($this->getDatabase()));
        $this->assertFalse(InstallationStatistics::isDue($settings->getString('system_statistics_last_sent')));
    }

    public function testMonthlySendRunsOnceAndOnlyForTheMainOrganization(): void
    {
        $db = $this->getDatabase();
        InstallationIdentifier::ensure($db);
        $mainId = InstallationIdentifier::getOrganizationId($db);
        $settings = new SettingsManager($db, $mainId);
        $settings->set('system_statistics_enabled', '1');
        $settings->set('system_statistics_last_sent', '2000-01-01 12:00:00');
        $other = (new AdmidioTestFixture($db))->createAndSaveOrganization('Monthly statistics child', 'monthlystats');
        $history = array();
        $handler = HandlerStack::create(new MockHandler(array(new Response(200))));
        $handler->push(Middleware::history($history));
        $client = new Client(array('handler' => $handler));

        InstallationStatistics::sendIfDue($db, (int)$other['org_id'], $client);
        $this->assertCount(0, $history);
        $this->assertSame('2000-01-01 12:00:00', $settings->getString('system_statistics_last_sent', true));

        InstallationStatistics::sendIfDue($db, $mainId, $client);
        InstallationStatistics::sendIfDue($db, $mainId, $client);
        $this->assertCount(1, $history);
        $this->assertFalse(InstallationStatistics::isDue($settings->getString('system_statistics_last_sent', true)));
        $this->assertFalse((new SettingsManager($db, (int)$other['org_id']))->has('system_statistics_last_sent'));
    }

    public function testOptOutAndFailedSendDoNotAdvanceLastSuccessfulSend(): void
    {
        $db = $this->getDatabase();
        InstallationIdentifier::ensure($db);
        $mainId = InstallationIdentifier::getOrganizationId($db);
        $settings = new SettingsManager($db, $mainId);
        $settings->set('system_statistics_last_sent', '2000-01-01 12:00:00');
        StatisticsConsent::savePreference(false);
        $history = array();
        $handler = HandlerStack::create(new MockHandler(array(new Response(503), new Response(200))));
        $handler->push(Middleware::history($history));
        $client = new Client(array('handler' => $handler));

        InstallationStatistics::sendIfDue($db, $mainId, $client);
        $this->assertCount(0, $history);
        StatisticsConsent::savePreference(true);
        InstallationStatistics::sendIfDue($db, $mainId, $client);
        $this->assertCount(1, $history);
        $this->assertSame('2000-01-01 12:00:00', $settings->getString('system_statistics_last_sent', true));
        InstallationStatistics::sendIfDue($db, $mainId, $client);
        $this->assertCount(2, $history);
        $this->assertFalse(InstallationStatistics::isDue($settings->getString('system_statistics_last_sent', true)));
    }

    public function testStatisticsMigrationSeedsDefaultsAndPreservesExistingPreferences(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name IN (?, ?)',
            array('system_statistics_enabled', 'system_statistics_last_sent'));
        $update = simplexml_load_file(dirname(__DIR__, 3) . '/install/db_scripts/update_6_0.xml');
        $applyStatisticsMigration = function () use ($db, $update): void {
            foreach ($update->xpath('step[@id="140" or @id="150"]') as $step) {
                $db->queryPrepared(str_replace('%PREFIX%', TABLE_PREFIX, (string)$step));
            }
        };
        $applyStatisticsMigration();
        $settings = new SettingsManager($db, InstallationIdentifier::getOrganizationId($db));
        $this->assertTrue($settings->getBool('system_statistics_enabled'));
        $this->assertSame('', $settings->getString('system_statistics_last_sent'));
        $settings->set('system_statistics_enabled', '0');
        $settings->set('system_statistics_last_sent', '2026-10-01 12:00:00');
        $applyStatisticsMigration();
        $this->assertFalse($settings->getBool('system_statistics_enabled', true));
        $this->assertSame('2026-10-01 12:00:00', $settings->getString('system_statistics_last_sent', true));
    }
}
