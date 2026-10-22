<?php
/**
 * Regression coverage for the way the changelog renders an SSO role mapping.
 *
 * Since several Admidio roles may be mapped to the same client role, the mapping stores a list of
 * role IDs per client role instead of a single ID. The changelog still read that entry as one
 * scalar, so every row said "Array" and PHP raised "Array to string conversion". A negative ID
 * maps the leaders of a role rather than its members, which the table has to say as well.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoRoleMappingChangelogTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Render a role mapping the way the changelog does for the ocl_role_mapping column.
     */
    private function renderMapping(array $mapping): string
    {
        return (string) ChangelogService::formatValue(json_encode($mapping), 'SSO_roles_mapping');
    }

    /**
     * @testdox A role mapping that lists several roles names every one of them
     */
    public function testAMappingWithSeveralRolesPerClientRoleNamesEveryRole(): void
    {
        $suffix = $this->suffix();
        $fixture = $this->getFixture();
        $first = $fixture->createAndSaveRole('Mapping A ' . $suffix, self::ORG_ID);
        $second = $fixture->createAndSaveRole('Mapping B ' . $suffix, self::ORG_ID);

        // Two Admidio roles mapped to the same client role, the format that broke the rendering.
        $html = $this->renderMapping(array(
            'client-role' => array($first['rol_id'], $second['rol_id']),
            '*' => false,
        ));

        $this->assertStringNotContainsString('Array', $html, 'The list must not be cast to a string.');
        $this->assertStringContainsString($first['rol_name'], $html);
        $this->assertStringContainsString($second['rol_name'], $html);

        // One row per assignment, both naming the same client role.
        $this->assertSame(2, substr_count($html, 'client-role'));
    }

    /**
     * @testdox A negative role ID in a mapping is shown as the leaders of that role
     */
    public function testANegativeRoleIdIsShownAsTheLeadersOfTheRole(): void
    {
        global $gL10n;

        $suffix = $this->suffix();
        $role = $this->getFixture()->createAndSaveRole('Mapping leader ' . $suffix, self::ORG_ID);

        $html = $this->renderMapping(array(
            'members' => array($role['rol_id']),
            'trainers' => array(-$role['rol_id']),
            '*' => false,
        ));

        $this->assertStringNotContainsString('Array', $html);

        // The role is named for both entries; the leader entry says so, the member entry does not.
        $this->assertSame(2, substr_count($html, $role['rol_name']));
        $this->assertStringContainsString($role['rol_name'] . ' - ' . $gL10n->get('SYS_LEADER'), $html);
    }

    /**
     * A mapping stored before several roles per client role were supported holds a plain ID.
     *
     * @testdox A mapping in the old format with a single role ID is still rendered
     */
    public function testTheOldSingleIdFormatIsStillRendered(): void
    {
        $suffix = $this->suffix();
        $role = $this->getFixture()->createAndSaveRole('Mapping legacy ' . $suffix, self::ORG_ID);

        $html = $this->renderMapping(array(
            'client-role' => $role['rol_id'],
            '*' => false,
        ));

        $this->assertStringNotContainsString('Array', $html);
        $this->assertStringContainsString($role['rol_name'], $html);
    }

    /**
     * @testdox A role that was deleted since the mapping was stored still shows its ID
     */
    public function testADeletedRoleFallsBackToTheStoredId(): void
    {
        // An ID that no role has, the state a mapping is left in when its role is deleted.
        $html = $this->renderMapping(array(
            'client-role' => array(987654),
            '*' => false,
        ));

        $this->assertStringNotContainsString('Array', $html);
        $this->assertStringContainsString('987654', $html, 'The stored ID is all that is left to show.');
    }
}
