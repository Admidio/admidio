<?php

namespace Admidio\Tests\Integration\Installation;

use Admidio\Infrastructure\InstallationIdentifier;
use Admidio\Infrastructure\InstallationStatistics;
use Admidio\Infrastructure\InstallationSettings;
use Admidio\InstallationUpdate\Service\UpdateStepsCode;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Ramsey\Uuid\Uuid;
use Admidio\InstallationUpdate\Service\StatisticsConsent;

class InstallationIdentifierTest extends DatabaseTestCase
{
    public function testFreshProductionInstallationAlreadyHasIdentityBeforeAnySend(): void
    {
        // DatabaseTestCase builds the fixture via the real Installation::install service.
        $this->assertValidUuid(InstallationIdentifier::get($this->getDatabase()));
    }

    public function testOrganizationCreationAndDeletionDoNotChangeInstallationSettings(): void
    {
        $db = $this->getDatabase();
        $settings = new InstallationSettings($db);
        $settings->set('system_statistics_enabled', '0');
        $id = $settings->get('system_tenant_id');
        $lastSent = $settings->get('system_statistics_last_sent');
        $other = (new AdmidioTestFixture($db))->createAndSaveOrganization('Installation settings', 'instset');
        foreach (InstallationSettings::defaults() as $name => $value) {
            $this->assertFalse((new SettingsManager($db, (int)$other['org_id']))->has($name));
        }
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ?', array($other['org_id']));
        $db->queryPrepared('DELETE FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?', array($other['org_id']));
        $this->assertSame('0', $settings->get('system_statistics_enabled'));
        $this->assertSame($id, $settings->get('system_tenant_id'));
        $this->assertSame($lastSent, $settings->get('system_statistics_last_sent'));
    }

    public function testIdentityIsMigratedAndSurvivesDeletionOfItsFormerOrganization(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'));
        $other = (new AdmidioTestFixture($db))->createAndSaveOrganization('Other tenant ID', 'tenantid');
        $legacyId = Uuid::uuid4()->toString();
        $db->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . '
            (prf_org_id, prf_name, prf_value) VALUES (?, ?, ?)', array($other['org_id'], 'system_tenant_id', $legacyId));
        $this->assertSame($legacyId, InstallationIdentifier::get($db));
        $this->assertSame($legacyId, InstallationIdentifier::ensure($db));
        $this->assertSame($legacyId, InstallationIdentifier::ensure($db, Uuid::uuid4()->toString()));
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ?', array($other['org_id']));
        $db->queryPrepared('DELETE FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?', array($other['org_id']));
        $this->assertSame($legacyId, InstallationIdentifier::get($db));
        $this->assertSame(1, (int)$db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_PREFERENCES . '
            WHERE prf_name = ? AND prf_org_id IS NULL', array('system_tenant_id'))->fetchColumn());
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

    public function testUpdateStepPreservesThePreviewIdentity(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'));
        $previousSession = $_SESSION;
        $previewId = Uuid::uuid4()->toString();
        try {
            $_SESSION['installationStatisticsData'] = array('id' => $previewId);
            UpdateStepsCode::setDatabase($db);
            UpdateStepsCode::updateStep60CreateTenantId();
            $this->assertSame($previewId, InstallationIdentifier::get($db));
            UpdateStepsCode::updateStep60CreateTenantId();
            $this->assertSame($previewId, InstallationIdentifier::get($db));
        } finally {
            $_SESSION = $previousSession;
        }
    }

    public function testReviewedIdentityIsUsedOnFirstCreation(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_tenant_id'));
        $previewId = Uuid::uuid4()->toString();
        $this->assertSame($previewId, InstallationIdentifier::ensure($db, $previewId));
        $this->assertSame($previewId, InstallationIdentifier::get($db));
    }

    public function testSenderAlwaysUsesTheInstallationIdentity(): void
    {
        $canonicalId = InstallationIdentifier::ensure($this->getDatabase());
        $history = array();
        $client = static function (string $url, string $json) use (&$history): int {
            $history[] = array('url' => $url, 'body' => $json);
            return 200;
        };
        $otherId = Uuid::uuid4()->toString();
        $statistics = new InstallationStatistics(null, $client);
        $this->assertTrue($statistics->send(array('id' => $otherId)));
        $sent = json_decode((string) $history[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($canonicalId, $sent['id']);
        $settings = new InstallationSettings($this->getDatabase());
        $this->assertFalse(InstallationStatistics::isDue(InstallationStatistics::getLastSent($this->getDatabase())));
    }

    public function testDemoProtectionFindsAnOrganizationOtherThanOne(): void
    {
        $db = $this->getDatabase();
        $demo = (new AdmidioTestFixture($db))->createAndSaveOrganization('Demo statistics', 'ADM_DEMO');
        $this->assertNotSame(1, (int)$demo['org_id']);
        $hadUrl = array_key_exists('gStatisticsUrl', $GLOBALS);
        $originalUrl = $GLOBALS['gStatisticsUrl'] ?? null;
        unset($GLOBALS['gStatisticsUrl']);
        $calls = 0;
        $statistics = new InstallationStatistics(null, static function () use (&$calls): int {
            ++$calls;
            return 503;
        });
        try {
            $this->assertFalse($statistics->send(array()));
            $this->assertSame(0, $calls);
            // Removing the demo organization allows the production transport again.
            $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ?', array($demo['org_id']));
            $db->queryPrepared('DELETE FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?', array($demo['org_id']));
            $this->assertFalse($statistics->send(array()));
            $this->assertSame(1, $calls);
        } finally {
            if ($hadUrl) {
                $GLOBALS['gStatisticsUrl'] = $originalUrl;
            } else {
                unset($GLOBALS['gStatisticsUrl']);
            }
        }
    }

    public function testMonthlySendRunsOnceForTheInstallation(): void
    {
        $db = $this->getDatabase();
        InstallationIdentifier::ensure($db);
        $settings = new InstallationSettings($db);
        $settings->set('system_statistics_enabled', '1');
        $this->setLastSent('2000-01-01 12:00:00');
        $other = (new AdmidioTestFixture($db))->createAndSaveOrganization('Monthly statistics child', 'monthstats');
        $history = array();
        $client = static function (string $url, string $json) use (&$history): int {
            $history[] = array('url' => $url, 'body' => $json);
            return 200;
        };

        InstallationStatistics::sendIfDue($db, $client);
        InstallationStatistics::sendIfDue($db, $client);
        $this->assertCount(1, $history);
        $this->assertFalse(InstallationStatistics::isDue(InstallationStatistics::getLastSent($this->getDatabase())));
        $this->assertFalse((new SettingsManager($db, (int)$other['org_id']))->has('system_statistics_last_sent'));
    }

    public function testOptOutPreventsSendingAndFailedSendReservesRetry(): void
    {
        $db = $this->getDatabase();
        InstallationIdentifier::ensure($db);
        $settings = new InstallationSettings($db);
        $this->setLastSent('2000-01-01 12:00:00');
        StatisticsConsent::savePreference(false);
        $history = array();
        $client = static function (string $url, string $json) use (&$history): int {
            $history[] = array('url' => $url, 'body' => $json);
            return count($history) === 1 ? 503 : 200;
        };

        InstallationStatistics::sendIfDue($db, $client);
        $this->assertCount(0, $history);
        $this->assertSame('2000-01-01 12:00:00', InstallationStatistics::getLastSent($db));
        StatisticsConsent::savePreference(true);
        InstallationStatistics::sendIfDue($db, $client);
        $this->assertCount(1, $history);
        $retry = InstallationStatistics::getLastSent($db);
        $this->assertStringStartsWith('retry:', $retry);
        $deadline = new \DateTimeImmutable(substr($retry, 6));
        $this->assertEqualsWithDelta(time() + 3600, $deadline->getTimestamp(), 2);
        $this->assertFalse(InstallationStatistics::isDue($retry));
        InstallationStatistics::sendIfDue($db, $client);
        $this->assertCount(1, $history);
        $this->assertSame($retry, InstallationStatistics::getLastSent($db));

        // Expire the reservation without waiting for the retry interval in real time.
        $this->setLastSent('retry:2000-01-01T13:00:00Z');
        InstallationStatistics::sendIfDue($db, $client);
        $this->assertCount(2, $history);
        $this->assertStringStartsNotWith('retry:', InstallationStatistics::getLastSent($db));
        $this->assertFalse(InstallationStatistics::isDue(InstallationStatistics::getLastSent($this->getDatabase())));
    }

    public function testMonthlySendRequiresConsentAndTheInstallationTimestamp(): void
    {
        $db = $this->getDatabase();
        $settings = new InstallationSettings($db);
        $history = array();
        $client = static function (string $url, string $json) use (&$history): int {
            $history[] = array('url' => $url, 'body' => $json);
            return 200;
        };

        foreach (array('system_statistics_last_sent', 'system_statistics_enabled') as $missingPreference) {
            $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?', array('system_statistics_last_sent'));
            $db->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . '
                (prf_org_id, prf_name, prf_value) VALUES (NULL, ?, ?)', array('system_statistics_last_sent', ''));
            $settings->set('system_statistics_enabled', '1');
            $this->setLastSent('2000-01-01 12:00:00');
            $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ?',
                array($missingPreference));

            InstallationStatistics::sendIfDue($db, $client);
            $this->assertCount(0, $history, 'No send without ' . $missingPreference);
        }
    }

    public function testStatisticsMigrationSeedsDefaultsAndPreservesExistingPreferences(): void
    {
        $db = $this->getDatabase();
        $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name IN (?, ?)',
            array('system_statistics_enabled', 'system_statistics_last_sent'));
        $update = simplexml_load_file(dirname(__DIR__, 3) . '/install/db_scripts/update_6_0.xml');
        $applyStatisticsMigration = function () use ($db, $update): void {
            foreach ($update->xpath('step[contains(., "system_statistics_enabled") or contains(., "system_statistics_last_sent")]') as $step) {
                $db->queryPrepared(str_replace('%PREFIX%', TABLE_PREFIX, (string)$step));
            }
        };
        $applyStatisticsMigration();
        $settings = new InstallationSettings($db);
        $this->assertFalse($settings->getBool('system_statistics_enabled'));
        $this->assertSame('', InstallationStatistics::getLastSent($this->getDatabase()));
        $settings->set('system_statistics_enabled', '1');
        $applyStatisticsMigration();
        $this->assertTrue((new InstallationSettings($db))->getBool('system_statistics_enabled'));
        $settings->set('system_statistics_enabled', '0');
        $this->setLastSent('2026-10-01 12:00:00');
        $applyStatisticsMigration();
        $this->assertFalse($settings->getBool('system_statistics_enabled'));
        $this->assertSame('2026-10-01 12:00:00', InstallationStatistics::getLastSent($this->getDatabase()));
    }

    private function setLastSent(string $value): void
    {
        (new InstallationSettings($this->getDatabase()))->set('system_statistics_last_sent', $value);
    }
}
