<?php
/**
 * Regression coverage for the single sign-on access that a role membership justifies.
 *
 * A client whose access is restricted to roles is checked while the authorization request runs.
 * What that check issues - the access token, the refresh token - outlives the request, so the end
 * of the membership has to reach the tokens as well. See finding 48.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Service\SSOAccessRevocationService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoAccessRevocationTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox Ending the membership of the role that granted access revokes the tokens of that client
     */
    public function testEndingTheMembershipRevokesTheTokensOfTheClient(): void
    {
        global $gCurrentOrgId;

        $fixture = $this->getFixture();
        $suffix = $this->suffix();

        $accessRole = $fixture->createAndSaveRole('SSO access ' . $suffix, $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('sso-revoke-' . $suffix, 'sso-revoke-' . $suffix . '@example.local');
        $membership = $fixture->assignUserToRole($user['usr_id'], $accessRole['rol_id']);

        $client = $this->createRestrictedClient($suffix, array($accessRole['rol_id']));
        $accessToken = $this->createToken($client, $user['usr_id'], TBL_OIDC_ACCESS_TOKENS, 'oat');
        $refreshToken = $this->createToken($client, $user['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort');

        // As long as the membership stands, the tokens are untouched.
        (new SSOAccessRevocationService($this->getDatabase()))->revokeLostClientAccess($user['usr_id']);
        $this->assertFalse($this->isRevoked(TBL_OIDC_ACCESS_TOKENS, 'oat', $accessToken));
        $this->assertFalse($this->isRevoked(TBL_OIDC_REFRESH_TOKENS, 'ort', $refreshToken));

        // Removing the user from the role has to reach the tokens through Membership::delete().
        (new Membership($this->getDatabase(), (int) $membership['mem_id']))->delete();

        $this->assertTrue($this->isRevoked(TBL_OIDC_ACCESS_TOKENS, 'oat', $accessToken));
        $this->assertTrue($this->isRevoked(TBL_OIDC_REFRESH_TOKENS, 'ort', $refreshToken));
    }

    /**
     * A client that is open to everybody must not lose its tokens when an unrelated membership ends.
     *
     * @testdox An unrestricted client keeps its tokens when a membership ends
     */
    public function testAnUnrestrictedClientKeepsItsTokens(): void
    {
        global $gCurrentOrgId;

        $fixture = $this->getFixture();
        $suffix = $this->suffix();

        $otherRole = $fixture->createAndSaveRole('SSO unrelated ' . $suffix, $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('sso-open-' . $suffix, 'sso-open-' . $suffix . '@example.local');
        $membership = $fixture->assignUserToRole($user['usr_id'], $otherRole['rol_id']);

        $client = $this->createRestrictedClient($suffix, array());
        $accessToken = $this->createToken($client, $user['usr_id'], TBL_OIDC_ACCESS_TOKENS, 'oat');

        (new Membership($this->getDatabase(), (int) $membership['mem_id']))->delete();

        $this->assertFalse($this->isRevoked(TBL_OIDC_ACCESS_TOKENS, 'oat', $accessToken));
    }

    /**
     * A user who keeps a second role that also grants the access has not lost anything.
     *
     * @testdox Losing one of two roles that grant the access leaves the tokens alone
     */
    public function testASecondGrantingRoleKeepsTheAccess(): void
    {
        global $gCurrentOrgId;

        $fixture = $this->getFixture();
        $suffix = $this->suffix();

        $firstRole = $fixture->createAndSaveRole('SSO access A ' . $suffix, $gCurrentOrgId);
        $secondRole = $fixture->createAndSaveRole('SSO access B ' . $suffix, $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('sso-two-' . $suffix, 'sso-two-' . $suffix . '@example.local');
        $firstMembership = $fixture->assignUserToRole($user['usr_id'], $firstRole['rol_id']);
        $fixture->assignUserToRole($user['usr_id'], $secondRole['rol_id']);

        $client = $this->createRestrictedClient($suffix, array($firstRole['rol_id'], $secondRole['rol_id']));
        $accessToken = $this->createToken($client, $user['usr_id'], TBL_OIDC_ACCESS_TOKENS, 'oat');

        (new Membership($this->getDatabase(), (int) $firstMembership['mem_id']))->delete();

        $this->assertFalse($this->isRevoked(TBL_OIDC_ACCESS_TOKENS, 'oat', $accessToken));
    }

    /**
     * Deleting a role removes its memberships with one bulk statement that never reaches
     * Membership::delete(), so the members have to be collected before the role is gone.
     *
     * @testdox Deleting a granting role revokes the tokens of its members
     */
    public function testDeletingTheGrantingRoleRevokesTheTokens(): void
    {
        global $gCurrentOrgId;

        $fixture = $this->getFixture();
        $suffix = $this->suffix();

        $accessRole = $fixture->createAndSaveRole('SSO deleted ' . $suffix, $gCurrentOrgId);
        $keptRole = $fixture->createAndSaveRole('SSO kept ' . $suffix, $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('sso-roledel-' . $suffix, 'sso-roledel-' . $suffix . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $accessRole['rol_id']);

        // The client stays restricted after the deletion. Were this its last access role, it would
        // be open to everybody afterwards and the tokens would rightly survive.
        $client = $this->createRestrictedClient($suffix, array($accessRole['rol_id'], $keptRole['rol_id']));
        $accessToken = $this->createToken($client, $user['usr_id'], TBL_OIDC_ACCESS_TOKENS, 'oat');

        (new Role($this->getDatabase(), (int) $accessRole['rol_id']))->delete();

        $this->assertTrue($this->isRevoked(TBL_OIDC_ACCESS_TOKENS, 'oat', $accessToken));
    }

    /**
     * A membership can also simply expire, which no code path in Admidio announces. The right is
     * therefore judged again when the refresh token is redeemed.
     *
     * @testdox A refresh token of an expired membership counts as revoked
     */
    public function testARefreshTokenOfAnExpiredMembershipIsRefused(): void
    {
        global $gCurrentOrgId;

        $fixture = $this->getFixture();
        $suffix = $this->suffix();

        $accessRole = $fixture->createAndSaveRole('SSO expiring ' . $suffix, $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('sso-expire-' . $suffix, 'sso-expire-' . $suffix . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $accessRole['rol_id']);

        $client = $this->createRestrictedClient($suffix, array($accessRole['rol_id']));
        $plainToken = 'refresh-' . $suffix;
        $this->createToken($client, $user['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort', $plainToken);

        $repository = new RefreshTokenRepository($this->getDatabase());
        $this->assertFalse($repository->isRefreshTokenRevoked($plainToken));

        // The membership is not deleted, it just ends in the past, exactly as it would over time.
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_MEMBERS . ' SET mem_end = ? WHERE mem_rol_id = ? AND mem_usr_id = ?',
            array(date('Y-m-d', strtotime('-2 days')), $accessRole['rol_id'], $user['usr_id'])
        );

        $this->assertTrue($repository->isRefreshTokenRevoked($plainToken));

        // The refusal is recorded, so the token is not judged again on the next attempt.
        $this->assertTrue($this->isRevoked(TBL_OIDC_REFRESH_TOKENS, 'ort', hash('sha256', $plainToken)));
    }

    /**
     * Whether a token row is marked as revoked.
     */
    private function isRevoked(string $table, string $prefix, string $tokenHash): bool
    {
        return (bool) $this->getDatabase()->queryPrepared(
            'SELECT ' . $prefix . '_revoked FROM ' . $table . ' WHERE ' . $prefix . '_token = ?',
            array($tokenHash)
        )->fetchColumn();
    }
}
