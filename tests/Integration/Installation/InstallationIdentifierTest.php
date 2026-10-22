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
    }
}
