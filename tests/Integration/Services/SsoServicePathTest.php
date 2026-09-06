<?php

namespace Admidio\Tests\Integration\Services;

use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Users\Entity\User;

/**
 * Regression coverage for the reusable SSO service save path.
 */
class SsoServicePathTest extends AdministratorTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox OIDCService persists, hashes and updates the complete client contract
     */
    public function testOidcClientLifecycleUsesProductionService(): void
    {
        global $gCurrentUser;

        $db = $this->getDatabase();
        $suffix = bin2hex(random_bytes(6));
        $clientId = 'regression-' . $suffix;
        $secret = 'secret-' . $suffix;

        $service = new OIDCService($db, $gCurrentUser);
        $client = $service->saveData(
            null,
            array(
                'ocl_client_name' => 'Regression OIDC ' . $suffix,
                'ocl_client_id' => $clientId,
                'new_ocl_client_secret' => $secret,
                'ocl_redirect_uri' => 'https://client.example/' . $suffix . '/callback',
                'ocl_grant_types' => 'authorization_code refresh_token',
                'ocl_scope' => array('profile', 'email'),
                'ocl_userid_field' => 'usr_uuid',
                'ocl_enabled' => true,
                'fieldsmap_sso' => array('mail'),
                'fieldsmap_Admidio' => array('EMAIL'),
                'rolesmap_sso' => array(),
                'rolesmap_Admidio' => array(),
                'sso_fields_no_other' => false,
                'sso_roles_all_other' => false
            )
        );

        $uuid = (string)$client->getValue('ocl_uuid');
        $this->assertNotSame('', $uuid);

        $row = $db->queryPrepared(
            'SELECT ocl_client_name, ocl_client_id, ocl_client_secret, ocl_redirect_uri,
                    ocl_grant_types, ocl_scope, ocl_userid_field, ocl_enabled, ocl_field_mapping
               FROM ' . TBL_OIDC_CLIENTS . '
              WHERE ocl_uuid = ?',
            array($uuid)
        )->fetch();

        $this->assertIsArray($row);
        $this->assertSame($clientId, (string)$row['ocl_client_id']);
        $this->assertNotSame($secret, (string)$row['ocl_client_secret']);
        $this->assertTrue(password_verify($secret, (string)$row['ocl_client_secret']));
        $this->assertSame('https://client.example/' . $suffix . '/callback', (string)$row['ocl_redirect_uri']);
        $this->assertStringContainsString('authorization_code', (string)$row['ocl_grant_types']);
        $this->assertStringContainsString('openid', (string)$row['ocl_scope']);
        $this->assertStringContainsString('profile', (string)$row['ocl_scope']);
        $this->assertSame('usr_uuid', (string)$row['ocl_userid_field']);
        $this->assertTrue((bool)$row['ocl_enabled']);

        $mapping = json_decode((string)$row['ocl_field_mapping'], true);
        $this->assertIsArray($mapping);
        $this->assertSame('EMAIL', $mapping['mail']);

        $updatedName = 'Regression OIDC updated ' . $suffix;
        $updated = $service->saveData(
            $uuid,
            array(
                'ocl_client_name' => $updatedName,
                'ocl_client_id' => $clientId,
                'ocl_redirect_uri' => 'https://client.example/' . $suffix . '/changed',
                'ocl_grant_types' => 'authorization_code',
                'ocl_scope' => array('email'),
                'ocl_userid_field' => 'usr_uuid',
                'ocl_enabled' => false,
                'fieldsmap_sso' => array('email'),
                'fieldsmap_Admidio' => array('EMAIL'),
                'rolesmap_sso' => array(),
                'rolesmap_Admidio' => array(),
                'sso_fields_no_other' => false,
                'sso_roles_all_other' => false
            )
        );

        $this->assertSame($uuid, (string)$updated->getValue('ocl_uuid'));

        $updatedRow = $db->queryPrepared(
            'SELECT ocl_client_name, ocl_redirect_uri, ocl_client_secret, ocl_enabled
               FROM ' . TBL_OIDC_CLIENTS . '
              WHERE ocl_uuid = ?',
            array($uuid)
        )->fetch();

        $this->assertIsArray($updatedRow);
        $this->assertSame($updatedName, (string)$updatedRow['ocl_client_name']);
        $this->assertSame('https://client.example/' . $suffix . '/changed', (string)$updatedRow['ocl_redirect_uri']);
        $this->assertSame((string)$row['ocl_client_secret'], (string)$updatedRow['ocl_client_secret']);
        $this->assertFalse((bool)$updatedRow['ocl_enabled']);

        $discovery = $service->getDiscoveryConfiguration();
        $this->assertSame($service->getIssuerURL(), $discovery['issuer']);
        $this->assertSame($service->getAuthorizationEndpoint(), $discovery['authorization_endpoint']);
        $this->assertSame($service->getTokenEndpoint(), $discovery['token_endpoint']);

        $updated->delete();
        $this->assertSame(
            0,
            (int)$db->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_OIDC_CLIENTS . ' WHERE ocl_uuid = ?',
                array($uuid)
            )->fetchColumn()
        );
    }

    /**
     * Several Admidio roles have to be assignable to the same client role, e.g. to give the members
     * of both youth teams the same role in the connected application. Storing the mapping keyed by
     * the client role name used to drop all but one of those assignments without any error message.
     *
     * @testdox Several Admidio roles can be mapped to the same client role
     */
    public function testRoleMappingKeepsSeveralAdmidioRolesPerClientRole(): void
    {
        global $gCurrentUser, $gCurrentOrgId;

        $db = $this->getDatabase();
        $fixture = $this->getFixture();
        $suffix = bin2hex(random_bytes(6));

        $firstTeam = $fixture->createAndSaveRole('Regression team 1 ' . $suffix, $gCurrentOrgId);
        $secondTeam = $fixture->createAndSaveRole('Regression team 2 ' . $suffix, $gCurrentOrgId);

        $service = new OIDCService($db, $gCurrentUser);
        $client = $service->saveData(
            null,
            $this->roleMappingClientValues(
                $suffix,
                array('subscriber', 'subscriber', 'editor'),
                array($firstTeam['rol_id'], $secondTeam['rol_id'], -$secondTeam['rol_id'])
            )
        );
        $uuid = (string)$client->getValue('ocl_uuid');

        // Both teams have to survive the save, the leader mapping is kept as a negative role ID.
        $this->assertSame(
            array('subscriber' => array($firstTeam['rol_id'], $secondTeam['rol_id']),
                  'editor' => array(-$secondTeam['rol_id'])),
            $client->getRoleMapping()
        );
        $this->assertSame(
            array(
                array('subscriber', $firstTeam['rol_id']),
                array('subscriber', $secondTeam['rol_id']),
                array('editor', -$secondTeam['rol_id'])
            ),
            $client->getRoleMappingList()
        );

        // Reload from the database and save again, which is what the edit form does when it is
        // opened and submitted without any change. No assignment may get lost on the way.
        $reloaded = $service->createClientObject($uuid);
        $mappingList = $reloaded->getRoleMappingList();
        $this->assertCount(3, $mappingList);

        $resaved = $service->saveData(
            $uuid,
            $this->roleMappingClientValues(
                $suffix,
                array_column($mappingList, 0),
                array_column($mappingList, 1)
            )
        );
        $this->assertSame($mappingList, $resaved->getRoleMappingList());

        // A member of both teams may only be reported once for the client role they share, while a
        // role leadership adds the separately mapped client role.
        $user = $fixture->createAndSaveUser('regression-sso-' . $suffix, 'regression-sso-' . $suffix . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $firstTeam['rol_id']);
        $fixture->assignUserToRolePeriod($user['usr_id'], $secondTeam['rol_id'], date('Y-m-d'), '9999-12-31', true);

        $groups = $resaved->getMappedRoleMemberships($this->readUser($user['usr_id']));
        sort($groups);
        $this->assertSame(array('editor', 'subscriber'), $groups);

        $resaved->delete();
    }

    /**
     * Client configurations written before a client role could hold several Admidio roles store a
     * single role ID instead of a list. Those rows still have to be readable without a migration.
     *
     * @testdox Role mappings stored in the previous single role format are still read
     */
    public function testRoleMappingReadsThePreviousSingleRoleFormat(): void
    {
        global $gCurrentUser, $gCurrentOrgId;

        $db = $this->getDatabase();
        $fixture = $this->getFixture();
        $suffix = bin2hex(random_bytes(6));

        $role = $fixture->createAndSaveRole('Regression legacy ' . $suffix, $gCurrentOrgId);

        $service = new OIDCService($db, $gCurrentUser);
        $client = $service->saveData(
            null,
            $this->roleMappingClientValues($suffix, array('subscriber'), array($role['rol_id']))
        );
        $uuid = (string)$client->getValue('ocl_uuid');

        $db->queryPrepared(
            'UPDATE ' . TBL_OIDC_CLIENTS . ' SET ocl_role_mapping = ? WHERE ocl_uuid = ?',
            array(json_encode(array('subscriber' => $role['rol_id'], '*' => true)), $uuid)
        );

        $legacy = $service->createClientObject($uuid);
        $this->assertSame(array(array('subscriber', $role['rol_id'])), $legacy->getRoleMappingList());
        $this->assertSame(array('subscriber' => array($role['rol_id'])), $legacy->getRoleMapping());
        $this->assertTrue($legacy->getRoleMappingCatchall());

        $user = $fixture->createAndSaveUser('regression-legacy-' . $suffix, 'regression-legacy-' . $suffix . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $this->assertSame(array('subscriber'), $legacy->getMappedRoleMemberships($this->readUser($user['usr_id'])));

        $legacy->delete();
    }

    /**
     * Build the form values for an OIDC client that only differs in its role mapping.
     *
     * @param string $suffix Unique suffix for the client name and ID.
     * @param array<int,string> $clientRoles Role names that are sent to the client.
     * @param array<int,int> $admidioRoles Admidio role IDs, negative for role leaders.
     * @return array<string,mixed>
     */
    private function roleMappingClientValues(string $suffix, array $clientRoles, array $admidioRoles): array
    {
        return array(
            'ocl_client_name' => 'Regression roles ' . $suffix,
            'ocl_client_id' => 'regression-roles-' . $suffix,
            'new_ocl_client_secret' => 'secret-' . $suffix,
            'ocl_redirect_uri' => 'https://client.example/' . $suffix . '/callback',
            'ocl_grant_types' => 'authorization_code',
            'ocl_scope' => array('profile', 'groups'),
            'ocl_userid_field' => 'usr_uuid',
            'ocl_enabled' => true,
            'fieldsmap_sso' => array(),
            'fieldsmap_Admidio' => array(),
            'rolesmap_sso' => $clientRoles,
            'rolesmap_Admidio' => $admidioRoles,
            'sso_fields_no_other' => false,
            'sso_roles_all_other' => false
        );
    }

    /**
     * Load a user through the normal entity, so that role memberships are read from the database.
     */
    private function readUser(int $userId): User
    {
        global $gProfileFields;

        return new User($this->getDatabase(), $gProfileFields, $userId);
    }
}
