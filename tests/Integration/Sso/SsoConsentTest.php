<?php
/**
 * Regression coverage for the stored OIDC consent.
 *
 * A consent records what the user was actually shown. The scopes alone do not describe that,
 * because the claim mapping of the client decides which profile fields a scope releases, so a
 * changed mapping has to bring the user back to the consent screen. See findings 39 and 47.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\OIDCConsent;
use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use ReflectionMethod;

class SsoConsentTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox A stored consent covers the scopes it was given for and nothing beyond them
     */
    public function testAConsentOnlyCoversTheScopesItWasGivenFor(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('sso-consent-' . $suffix, 'sso-consent-' . $suffix . '@example.local');
        $client = $this->createRestrictedClient($suffix, array());

        $consent = new OIDCConsent($this->getDatabase());
        $consent->setValue('oco_org_id', (int) $gCurrentOrgId);
        $consent->setValue('oco_usr_id', (int) $user['usr_id']);
        $consent->setValue('oco_ocl_id', (int) $client->getValue('ocl_id'));
        $consent->setValue('oco_scopes', 'openid profile');
        $consent->setValue('oco_policy_hash', str_repeat('a', 64));
        $consent->save();

        $stored = new OIDCConsent($this->getDatabase());
        $this->assertTrue($stored->readDataByUserAndClient((int) $gCurrentOrgId, (int) $user['usr_id'],
            (int) $client->getValue('ocl_id')));

        $this->assertTrue($stored->coversScopes(array('openid')));
        $this->assertTrue($stored->coversScopes(array('openid', 'profile')));
        $this->assertTrue($stored->coversScopes(array()));

        // A scope that was never approved is not covered, so the user is asked again.
        $this->assertFalse($stored->coversScopes(array('openid', 'email')));
        $this->assertFalse($stored->coversScopes(array('groups')));
    }

    /**
     * @testdox A consent of another user or another organization is not read
     */
    public function testAConsentIsScopedToItsUserAndOrganization(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('sso-c1-' . $suffix, 'sso-c1-' . $suffix . '@example.local');
        $otherUser = $fixture->createAndSaveUser('sso-c2-' . $suffix, 'sso-c2-' . $suffix . '@example.local');
        $client = $this->createRestrictedClient($suffix, array());
        $clientId = (int) $client->getValue('ocl_id');

        $consent = new OIDCConsent($this->getDatabase());
        $consent->setValue('oco_org_id', (int) $gCurrentOrgId);
        $consent->setValue('oco_usr_id', (int) $user['usr_id']);
        $consent->setValue('oco_ocl_id', $clientId);
        $consent->setValue('oco_scopes', 'openid');
        $consent->setValue('oco_policy_hash', str_repeat('b', 64));
        $consent->save();

        $forOtherUser = new OIDCConsent($this->getDatabase());
        $this->assertFalse($forOtherUser->readDataByUserAndClient((int) $gCurrentOrgId,
            (int) $otherUser['usr_id'], $clientId));

        $forOtherOrganization = new OIDCConsent($this->getDatabase());
        $this->assertFalse($forOtherOrganization->readDataByUserAndClient((int) $gCurrentOrgId + 1000,
            (int) $user['usr_id'], $clientId));
    }

    /**
     * @testdox A consent that describes no release policy never matches
     */
    public function testAConsentWithoutAPolicyNeverMatches(): void
    {
        $consent = new OIDCConsent($this->getDatabase());
        $consent->setValue('oco_policy_hash', '');

        $this->assertFalse($consent->matchesReleasePolicy(str_repeat('c', 64)));
        $this->assertFalse($consent->matchesReleasePolicy(''));

        $consent->setValue('oco_policy_hash', str_repeat('c', 64));
        $this->assertTrue($consent->matchesReleasePolicy(str_repeat('c', 64)));
        $this->assertFalse($consent->matchesReleasePolicy(str_repeat('d', 64)));
    }

    /**
     * Changing what a client receives has to invalidate the consent that was given for the old
     * mapping, even when the scopes did not change at all.
     *
     * @testdox Changing the claim mapping of a client invalidates the stored consent
     */
    public function testChangingTheClaimMappingInvalidatesTheConsent(): void
    {
        global $gCurrentUser;

        $suffix = $this->suffix();
        $client = $this->createRestrictedClient($suffix, array());
        $clientId = (int) $client->getValue('ocl_id');

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        $hashOf = new ReflectionMethod($service, 'getReleasePolicyHash');
        $hashOf->setAccessible(true);

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_CLIENTS . ' SET ocl_field_mapping = ? WHERE ocl_id = ?',
            array(json_encode(array('mail' => 'EMAIL')), $clientId)
        );
        $before = (string) $hashOf->invoke($service, $this->readClient($clientId));

        // The user consented to exactly this policy.
        $consent = new OIDCConsent($this->getDatabase());
        $consent->setValue('oco_policy_hash', $before);
        $this->assertTrue($consent->matchesReleasePolicy($before));

        // The administrator now releases the date of birth as well. Same scopes, more data.
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_CLIENTS . ' SET ocl_field_mapping = ? WHERE ocl_id = ?',
            array(json_encode(array('mail' => 'EMAIL', 'birthday' => 'BIRTHDAY')), $clientId)
        );
        $after = (string) $hashOf->invoke($service, $this->readClient($clientId));

        $this->assertNotSame($before, $after);
        $this->assertFalse($consent->matchesReleasePolicy($after));
    }

    /**
     * The order in which the mapping was entered says nothing about what is released, so it must
     * not send every user back to the consent screen.
     *
     * @testdox Reordering the claim mapping does not invalidate the consent
     */
    public function testReorderingTheClaimMappingKeepsTheConsent(): void
    {
        global $gCurrentUser;

        $suffix = $this->suffix();
        $client = $this->createRestrictedClient($suffix, array());
        $clientId = (int) $client->getValue('ocl_id');

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        $hashOf = new ReflectionMethod($service, 'getReleasePolicyHash');
        $hashOf->setAccessible(true);

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_CLIENTS . ' SET ocl_field_mapping = ? WHERE ocl_id = ?',
            array(json_encode(array('mail' => 'EMAIL', 'birthday' => 'BIRTHDAY')), $clientId)
        );
        $first = (string) $hashOf->invoke($service, $this->readClient($clientId));

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_CLIENTS . ' SET ocl_field_mapping = ? WHERE ocl_id = ?',
            array(json_encode(array('birthday' => 'BIRTHDAY', 'mail' => 'EMAIL')), $clientId)
        );
        $second = (string) $hashOf->invoke($service, $this->readClient($clientId));

        $this->assertSame($first, $second);
    }

    /**
     * Read a client back from the database, so that the mapping accessors see the stored value.
     */
    private function readClient(int $clientId): OIDCClient
    {
        $client = new OIDCClient($this->getDatabase());
        $client->readDataById($clientId);

        return $client;
    }
}
