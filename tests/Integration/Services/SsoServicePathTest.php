<?php

namespace Admidio\Tests\Integration\Services;

use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdministratorTestCase;

/**
 * Regression coverage for the reusable SSO service save path.
 */
class SsoServicePathTest extends AdministratorTestCase
{
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
                'ocl_userid_field' => 'usr_login_name',
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
        $this->assertSame('usr_login_name', (string)$row['ocl_userid_field']);
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
                'ocl_userid_field' => 'usr_login_name',
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
}
