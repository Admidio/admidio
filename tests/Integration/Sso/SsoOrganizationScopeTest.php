<?php
/**
 * Regression coverage for the organization boundary of the single sign-on data.
 *
 * Keys, clients and session participants all belong to one organization. Knowing a UUID, a client
 * identifier or an external session id must never be enough to reach the record of another
 * organization - this is where the published advisories were found. See finding 47.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\Key;
use Admidio\SSO\Service\KeyService;
use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\OIDCSessionParticipantService;
use Admidio\SSO\Service\SAMLService;
use Admidio\SSO\Service\SAMLSessionParticipantService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\PermissionContext;

class SsoOrganizationScopeTest extends AdministratorTestCase
{
    use SsoClientFixture;
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox A cryptographic key of another organization is not readable through its UUID
     */
    public function testAKeyOfAnotherOrganizationIsNotReadable(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();
        $otherOrg = $this->getFixture()->createAndSaveOrganization('SSO keys ' . $suffix, 'K' . substr($suffix, 0, 8));

        $key = new Key($this->getDatabase());
        $key->setValue('key_org_id', (int) $otherOrg['org_id']);
        $key->setValue('key_name', 'Foreign key ' . $suffix);
        $key->setValue('key_algorithm', 'RSA');
        $key->setValue('key_private', 'private-' . $suffix);
        $key->setValue('key_public', 'public-' . $suffix);
        $key->save();

        $keyUuid = (string) $key->getValue('key_uuid');
        $this->assertNotSame('', $keyUuid);

        $service = new KeyService($this->getDatabase());

        // From the current organization the key does not exist, although the UUID is correct.
        $this->assertTrue($service->createKeyObject($keyUuid)->isNewRecord());
        $this->assertSame(
            array(),
            array_filter($service->getKeysData(), static function ($row) use ($keyUuid) {
                return (string) $row['key_uuid'] === $keyUuid;
            })
        );

        // In its own organization it is found, so the test really addressed an existing key.
        $found = $this->withOrganization((int) $otherOrg['org_id'], function () use ($keyUuid) {
            return (new KeyService($this->getDatabase()))->createKeyObject($keyUuid);
        });
        $this->assertFalse($found->isNewRecord());
        $this->assertSame((int) $otherOrg['org_id'], (int) $found->getValue('key_org_id'));

        // The key of the foreign organization is not the one the current organization would sign with.
        $this->assertNotSame((int) $otherOrg['org_id'], (int) $gCurrentOrgId);
    }

    /**
     * @testdox An OIDC client of another organization is not readable through its UUID or client ID
     */
    public function testAnOidcClientOfAnotherOrganizationIsNotReadable(): void
    {
        global $gCurrentUser;

        $suffix = $this->suffix();
        $otherOrg = $this->getFixture()->createAndSaveOrganization('SSO oidc ' . $suffix, 'O' . substr($suffix, 0, 8));

        $client = $this->withOrganization((int) $otherOrg['org_id'], function () use ($suffix, $otherOrg) {
            return $this->createRestrictedClient($suffix, array(), (int) $otherOrg['org_id']);
        });
        $clientUuid = (string) $client->getValue('ocl_uuid');

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        $this->assertTrue($service->createClientObject($clientUuid)->isNewRecord());

        $own = $this->withOrganization((int) $otherOrg['org_id'], function () use ($clientUuid, $gCurrentUser) {
            return (new OIDCService($this->getDatabase(), $gCurrentUser))->createClientObject($clientUuid);
        });
        $this->assertFalse($own->isNewRecord());
    }

    /**
     * @testdox A SAML client of another organization is not readable through its UUID
     */
    public function testASamlClientOfAnotherOrganizationIsNotReadable(): void
    {
        global $gCurrentUser;

        $suffix = $this->suffix();
        $otherOrg = $this->getFixture()->createAndSaveOrganization('SSO saml ' . $suffix, 'S' . substr($suffix, 0, 8));

        $client = $this->withOrganization((int) $otherOrg['org_id'], function () use ($suffix, $otherOrg) {
            return $this->createSamlClient($suffix, array(), (int) $otherOrg['org_id']);
        });
        $clientUuid = (string) $client->getValue('smc_uuid');

        $service = new SAMLService($this->getDatabase(), $gCurrentUser);
        $this->assertTrue($service->createClientObject($clientUuid)->isNewRecord());

        $own = $this->withOrganization((int) $otherOrg['org_id'], function () use ($clientUuid, $gCurrentUser) {
            return (new SAMLService($this->getDatabase(), $gCurrentUser))->createClientObject($clientUuid);
        });
        $this->assertFalse($own->isNewRecord());
    }

    /**
     * The external session id is the only thing a logout request carries, so it must not be enough
     * to read or delete the participants that another organization tracks under the same id.
     *
     * @testdox OIDC session participants are only read and deleted within their own organization
     */
    public function testOidcSessionParticipantsAreScopedByOrganization(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();
        $fixture = $this->getFixture();
        $otherOrg = $fixture->createAndSaveOrganization('SSO part ' . $suffix, 'P' . substr($suffix, 0, 8));
        $user = $fixture->createAndSaveUser('sso-part-' . $suffix, 'sso-part-' . $suffix . '@example.local');

        $ownClient = $this->createRestrictedClient('own-' . $suffix, array());
        $foreignClient = $this->withOrganization((int) $otherOrg['org_id'], function () use ($suffix, $otherOrg) {
            return $this->createRestrictedClient('foreign-' . $suffix, array(), (int) $otherOrg['org_id']);
        });

        // The same external session id in both organizations, which is what an attacker would reuse.
        $externalSessionId = bin2hex(random_bytes(16));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $service = new OIDCSessionParticipantService($this->getDatabase());
        $service->persistParticipant((int) $gCurrentOrgId, (int) $user['usr_id'],
            (int) $ownClient->getValue('ocl_id'), $externalSessionId, 'subject-own', $expiresAt);
        $service->persistParticipant((int) $otherOrg['org_id'], (int) $user['usr_id'],
            (int) $foreignClient->getValue('ocl_id'), $externalSessionId, 'subject-foreign', $expiresAt);

        $ownParticipants = $service->getParticipants((int) $gCurrentOrgId, $externalSessionId);
        $this->assertCount(1, $ownParticipants);
        $this->assertSame('subject-own', (string) $ownParticipants[0]['osp_subject']);

        // Deleting in one organization leaves the participants of the other one alone.
        $service->deleteParticipants((int) $gCurrentOrgId, $externalSessionId);
        $this->assertCount(0, $service->getParticipants((int) $gCurrentOrgId, $externalSessionId));
        $this->assertCount(1, $service->getParticipants((int) $otherOrg['org_id'], $externalSessionId));

        // An ID token hint of the foreign session must not pass as one of this organization.
        $this->expectException(\Exception::class);
        $service->assertParticipant((int) $gCurrentOrgId, $externalSessionId,
            (int) $foreignClient->getValue('ocl_id'), 'subject-foreign');
    }

    /**
     * @testdox SAML session participants are only read within their own organization
     */
    public function testSamlSessionParticipantsAreScopedByOrganization(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();
        $fixture = $this->getFixture();
        $otherOrg = $fixture->createAndSaveOrganization('SSO sp ' . $suffix, 'Q' . substr($suffix, 0, 8));
        $user = $fixture->createAndSaveUser('sso-sp-' . $suffix, 'sso-sp-' . $suffix . '@example.local');

        $ownClient = $this->createSamlClient('own-' . $suffix);
        $foreignClient = $this->withOrganization((int) $otherOrg['org_id'], function () use ($suffix, $otherOrg) {
            return $this->createSamlClient('foreign-' . $suffix, array(), (int) $otherOrg['org_id']);
        });

        $externalSessionId = bin2hex(random_bytes(16));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $service = new SAMLSessionParticipantService($this->getDatabase());
        $authnInstant = new \DateTimeImmutable();
        $format = 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent';

        $service->persistParticipant((int) $gCurrentOrgId, (int) $user['usr_id'], $externalSessionId,
            (int) $ownClient->getValue('smc_id'), 'name-own', $format, null, 'session-own',
            $authnInstant, $expiresAt);
        $service->persistParticipant((int) $otherOrg['org_id'], (int) $user['usr_id'], $externalSessionId,
            (int) $foreignClient->getValue('smc_id'), 'name-foreign', $format, null, 'session-foreign',
            $authnInstant, $expiresAt);

        $ownParticipants = $service->getParticipants((int) $gCurrentOrgId, $externalSessionId);
        $this->assertCount(1, $ownParticipants);
        $this->assertSame('name-own', (string) $ownParticipants[0]['ssp_name_id']);

        $foreignParticipants = $service->getParticipants((int) $otherOrg['org_id'], $externalSessionId);
        $this->assertCount(1, $foreignParticipants);
        $this->assertSame('name-foreign', (string) $foreignParticipants[0]['ssp_name_id']);
    }
}
