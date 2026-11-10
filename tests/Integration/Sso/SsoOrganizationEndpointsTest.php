<?php
/**
 * Regression coverage for the single sign-on endpoints of an organization (finding 101).
 *
 * Every organization is an identity provider of its own. The default organization keeps the URLs
 * without a prefix, every other one is served below o/<short name>/, and the request runs in that
 * organization instead of the one of the session.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Exception;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\SAMLService;
use Admidio\SSO\Service\SSOOrganization;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoOrganizationEndpointsTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @return array{0:int,1:string} ID and short name of a new organization
     */
    private function createOrganization(): array
    {
        $suffix = $this->suffix();
        $shortname = 'E' . substr($suffix, 0, 8);
        $organization = $this->getFixture()->createAndSaveOrganization('SSO endpoints ' . $suffix, $shortname);

        // an organization of the fixture has no preferences, a real one is given the defaults when it is created
        (new SettingsManager($this->getDatabase(), (int) $organization['org_id']))->setMulti(PreferenceDefinitions::defaults());

        return array((int) $organization['org_id'], $shortname);
    }

    /**
     * Run a callback inside the organization the way an SSO request does, and restore the globals.
     */
    private function inside(string $shortname, callable $callback): mixed
    {
        $names = array('gCurrentOrganization', 'gCurrentOrgId', 'gSettingsManager', 'gProfileFields', 'gCurrentUser',
            'gCurrentUserId', 'gCurrentUserUUID', 'gValidLogin', 'gL10n');
        $previous = array();
        foreach ($names as $name) {
            $previous[$name] = $GLOBALS[$name] ?? null;
        }

        try {
            SSOOrganization::enter($shortname);
            return $callback();
        } finally {
            foreach ($previous as $name => $value) {
                $GLOBALS[$name] = $value;
            }
        }
    }

    /**
     * @testdox The default organization is served without a prefix, every other one below o/<short name>
     */
    public function testOnlyTheDefaultOrganizationIsServedWithoutAPrefix(): void
    {
        [, $shortname] = $this->createOrganization();
        $default = SSOOrganization::getDefaultShortname();
        $base = ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php';

        $this->assertSame($base, SSOOrganization::getBaseUrl($default));
        $this->assertSame($base . '/o/' . $shortname, SSOOrganization::getBaseUrl($shortname));
        $this->assertTrue(SSOOrganization::isDefault(strtolower($default)));
        $this->assertFalse(SSOOrganization::isDefault($shortname));
    }

    /**
     * @testdox The organization is taken from the path, and the default organization has no second URL
     */
    public function testThePathNamesTheOrganization(): void
    {
        [, $shortname] = $this->createOrganization();
        $default = SSOOrganization::getDefaultShortname();

        $this->assertSame(array('', 'oidc/token'), SSOOrganization::parsePath('oidc/token'));
        $this->assertSame(array('', 'saml/metadata'), SSOOrganization::parsePath('/saml/metadata/'));
        $this->assertSame(array($shortname, 'oidc/.well-known/openid-configuration'),
            SSOOrganization::parsePath('o/' . $shortname . '/oidc/.well-known/openid-configuration'));
        $this->assertSame(array($shortname, 'saml/sso'), SSOOrganization::parsePath('o/' . strtolower($shortname) . '/saml/sso'));
        $this->assertNull(SSOOrganization::parsePath('o/NOSUCHORG/oidc/token'));
        $this->assertNull(SSOOrganization::parsePath('o/'));
        $this->assertNull(SSOOrganization::parsePath('o/' . $default . '/oidc/token'));
    }

    /**
     * @testdox Entering an organization replaces the organization, its settings and the user for the request
     */
    public function testEnteringAnOrganizationTakesItsContext(): void
    {
        [$orgId, $shortname] = $this->createOrganization();
        $sessionOrganization = $GLOBALS['gCurrentOrganization'];
        $sessionUser = $GLOBALS['gCurrentUser'];

        $this->inside($shortname, function () use ($orgId, $shortname) {
            $this->assertSame($orgId, (int) $GLOBALS['gCurrentOrgId']);
            $this->assertSame($shortname, $GLOBALS['gCurrentOrganization']->getValue('org_shortname'));
            $this->assertFalse($GLOBALS['gValidLogin']);
            $this->assertSame(0, (int) $GLOBALS['gCurrentUser']->getValue('usr_id'));
            $this->assertInstanceOf(SettingsManager::class, $GLOBALS['gSettingsManager']);
        });

        // the objects of the session are untouched
        $this->assertSame($sessionOrganization, $GLOBALS['gCurrentOrganization']);
        $this->assertSame($sessionUser, $GLOBALS['gCurrentUser']);
        $this->assertNotSame($shortname, $GLOBALS['gCurrentOrganization']->getValue('org_shortname'));
    }

    /**
     * @testdox The services of an organization publish the issuer, entity ID and endpoints of that organization
     */
    public function testTheServicesPublishTheEndpointsOfTheirOrganization(): void
    {
        global $gCurrentUser;
        [, $shortname] = $this->createOrganization();
        $db = $this->getDatabase();
        $base = ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php';

        $default = new SAMLService($db, $gCurrentUser);
        $this->assertSame(ADMIDIO_URL, $default->getIdPEntityId());
        $this->assertSame($base . '/saml/sso', $default->getSsoEndpoint());
        $this->assertSame($base . '/oidc', OIDCService::getDefaultIssuerURL());

        $this->inside($shortname, function () use ($db, $base, $shortname) {
            $saml = new SAMLService($db, $GLOBALS['gCurrentUser']);
            $this->assertSame(ADMIDIO_URL . '/o/' . $shortname, $saml->getIdPEntityId());
            $this->assertSame($base . '/o/' . $shortname . '/saml/sso', $saml->getSsoEndpoint());
            $this->assertSame($base . '/o/' . $shortname . '/saml/slo', $saml->getSloEndpoint());
            $this->assertSame($base . '/o/' . $shortname . '/saml/metadata', $saml->getMetadataUrl());
            $this->assertSame($base . '/o/' . $shortname . '/oidc', OIDCService::getDefaultIssuerURL());
        });
    }

    /**
     * @testdox A stored entity ID of a custom value is kept, the Admidio URL stands for the default of the organization
     */
    public function testAStoredEntityIdIsKeptUnlessItIsTheDefault(): void
    {
        [, $shortname] = $this->createOrganization();

        $this->assertSame('https://idp.example/club', SSOOrganization::getEffectiveEntityId('https://idp.example/club', $shortname));
        $this->assertSame(ADMIDIO_URL . '/o/' . $shortname, SSOOrganization::getEffectiveEntityId(ADMIDIO_URL, $shortname));
        $this->assertSame(ADMIDIO_URL . '/o/' . $shortname, SSOOrganization::getEffectiveEntityId('', $shortname));
        $this->assertSame(ADMIDIO_URL, SSOOrganization::getEffectiveEntityId('', SSOOrganization::getDefaultShortname()));
    }

    /**
     * @testdox Two organizations cannot present themselves with the same issuer or entity ID
     */
    public function testIdentifiersMustBeUniqueBetweenOrganizations(): void
    {
        [$orgId, $shortname] = $this->createOrganization();
        $settings = new SettingsManager($this->getDatabase(), $orgId);
        $settings->set('sso_oidc_enabled', '1');
        $settings->set('sso_saml_enabled', '1');
        $settings->set('sso_saml_entity_id', 'https://idp.example/' . $shortname);
        $settings->set('sso_oidc_issuer_url', 'https://idp.example/oidc/' . $shortname);

        // the other organization is not enabled yet: no conflict with the current one
        SSOOrganization::assertIdentifiersAreUnique(SSOOrganization::getBaseUrl() . '/oidc', ADMIDIO_URL);

        foreach (array(
            array('https://idp.example/oidc/' . $shortname, ''),
            array('', 'https://idp.example/' . $shortname)
        ) as [$issuer, $entityId]) {
            try {
                SSOOrganization::assertIdentifiersAreUnique($issuer, $entityId);
                $this->fail('The identifier of another organization should have been refused.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_SSO_IDENTIFIER_IN_USE', $exception->getTranslationId());
            }
        }

        // a protocol that is not enabled does not occupy its identifier
        $settings->set('sso_saml_enabled', '0');
        SSOOrganization::assertIdentifiersAreUnique('', 'https://idp.example/' . $shortname);
    }
}
