<?php
/**
 * Regression coverage for saving an SSO client with a client ID that another client already uses.
 *
 * The SAML entity ID is unique in the database, so saving ended in a raw SQL error page. Saving now
 * stops with a form error that names the client having the ID. A SAML entity ID is unique across
 * all organizations, an OIDC client ID only within its organization, because OIDC clients are
 * looked up per organization. Found with the SAMLscope conformance suite, finding SAML-06.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\PermissionContext;
use ReflectionMethod;

class SsoClientIdUniquenessTest extends AdministratorTestCase
{
    use SsoClientFixture;
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function samlService(): SAMLService
    {
        global $gCurrentUser;

        return new SAMLService($this->getDatabase(), $gCurrentUser);
    }

    private function formValues(string $clientId, string $name): array
    {
        return array(
            'smc_client_id' => $clientId,
            'smc_client_name' => $name,
            'smc_acs_url' => 'https://sp.example/acs',
            'smc_x509_certificate' => '',
            'smc_userid_field' => 'usr_uuid',
        );
    }

    private function countSamlClients(string $clientId): int
    {
        return (int) $this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_SAML_CLIENTS . ' WHERE smc_client_id = ?',
            array($clientId)
        )->fetchColumn();
    }

    /**
     * @testdox A new SAML client with the entity ID of another client is refused, naming that client
     */
    public function testANewSamlClientWithAUsedEntityIdIsRefused(): void
    {
        $existing = $this->createSamlClient($this->suffix());
        $entityId = (string) $existing->getValue('smc_client_id');

        try {
            $this->samlService()->saveData(null, $this->formValues($entityId, 'Duplicate'));
            $this->fail('The duplicate entity ID should have been refused.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_SSO_CLIENT_ID_EXISTS', $exception->getTranslationId());
            $this->assertStringContainsString((string) $existing->getValue('smc_client_name'), $exception->getMessage());
        }

        $this->assertSame(1, $this->countSamlClients($entityId), 'No second client may be written.');
    }

    /**
     * @testdox A SAML client can be saved again with its own entity ID
     */
    public function testASamlClientKeepsItsOwnEntityId(): void
    {
        $existing = $this->createSamlClient($this->suffix());

        $saved = $this->samlService()->saveData(
            (string) $existing->getValue('smc_uuid'),
            $this->formValues((string) $existing->getValue('smc_client_id'), 'Renamed')
        );

        $this->assertSame('Renamed', $saved->getValue('smc_client_name'));
    }

    /**
     * @testdox A SAML client cannot take over the entity ID of another client by editing
     */
    public function testEditingASamlClientToAUsedEntityIdIsRefused(): void
    {
        $first = $this->createSamlClient($this->suffix());
        $second = $this->createSamlClient($this->suffix());

        try {
            $this->samlService()->saveData(
                (string) $second->getValue('smc_uuid'),
                $this->formValues((string) $first->getValue('smc_client_id'), 'Second')
            );
            $this->fail('The entity ID of another client should have been refused.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_SSO_CLIENT_ID_EXISTS', $exception->getTranslationId());
        }
    }

    /**
     * @testdox A new SAML client with an unused entity ID is saved
     */
    public function testAnUnusedEntityIdIsSaved(): void
    {
        $entityId = 'https://sp.example/' . $this->suffix();

        $this->samlService()->saveData(null, $this->formValues($entityId, 'Fresh'));

        $this->assertSame(1, $this->countSamlClients($entityId));
    }

    /**
     * The name of a client of another organization is not shown, but the ID is still refused,
     * because the database allows each SAML entity ID only once.
     *
     * @testdox A SAML entity ID used in another organization is refused without naming that client
     */
    public function testASamlEntityIdOfAnotherOrganizationIsRefusedWithoutItsName(): void
    {
        $suffix = $this->suffix();
        $otherOrg = $this->getFixture()->createAndSaveOrganization('SSO ids ' . $suffix, 'I' . substr($suffix, 0, 8));
        $foreign = $this->withOrganization((int) $otherOrg['org_id'], function () use ($suffix, $otherOrg) {
            return $this->createSamlClient($suffix, array(), (int) $otherOrg['org_id']);
        });

        try {
            $this->samlService()->saveData(null, $this->formValues((string) $foreign->getValue('smc_client_id'), 'Mine'));
            $this->fail('The entity ID of another organization should have been refused.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_SSO_CLIENT_ID_EXISTS_OTHER_ORGA', $exception->getTranslationId());
            $this->assertStringNotContainsString((string) $foreign->getValue('smc_client_name'), $exception->getMessage());
        }
    }

    private function oidcAvailability(string $clientId): void
    {
        global $gCurrentUser;

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        (new ReflectionMethod($service, 'assertClientIdIsAvailable'))
            ->invoke($service, $service->createClientObject(), $clientId);
    }

    /**
     * @testdox An OIDC client ID used by a client of the same organization is refused
     */
    public function testAnOidcClientIdOfTheSameOrganizationIsRefused(): void
    {
        $existing = $this->createRestrictedClient($this->suffix(), array());

        try {
            $this->oidcAvailability((string) $existing->getValue('ocl_client_id'));
            $this->fail('The client ID of the same organization should have been refused.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_SSO_CLIENT_ID_EXISTS', $exception->getTranslationId());
        }
    }

    /**
     * @testdox An OIDC client ID used in another organization is available
     */
    public function testAnOidcClientIdOfAnotherOrganizationIsAvailable(): void
    {
        $suffix = $this->suffix();
        $otherOrg = $this->getFixture()->createAndSaveOrganization('OIDC ids ' . $suffix, 'O' . substr($suffix, 0, 8));
        $foreign = $this->withOrganization((int) $otherOrg['org_id'], function () use ($suffix, $otherOrg) {
            return $this->createRestrictedClient($suffix, array(), (int) $otherOrg['org_id']);
        });

        $this->oidcAvailability((string) $foreign->getValue('ocl_client_id'));
        $this->addToAssertionCount(1);
    }
}
