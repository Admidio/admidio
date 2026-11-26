<?php
/**
 * Regression coverage for the metadata of the SAML identity provider (validity, cache duration, organization and
 * contact person) and for the IdP-initiated sign-in (an assertion that no request asked for).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Session\Entity\Session;
use Admidio\SSO\Service\KeyService;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use ReflectionMethod;

class SsoSamlIdpFeaturesTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private array $previousSettings = array();

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        global $gSettingsManager;
        parent::setUp();

        foreach (array('sso_saml_enabled', 'sso_saml_signing_key', 'sso_saml_metadata_valid_days', 'sso_saml_metadata_cache_hours',
                'sso_saml_organization_name', 'sso_saml_organization_url', 'sso_saml_contact_email') as $name) {
            $this->previousSettings[$name] = $gSettingsManager->get($name);
        }
        $gSettingsManager->set('sso_saml_enabled', '1');
        $gSettingsManager->set('sso_saml_signing_key', (string) (new KeyService($this->getDatabase()))->createDefaultKey());
    }

    protected function tearDown(): void
    {
        global $gSettingsManager;
        foreach ($this->previousSettings as $name => $value) {
            $gSettingsManager->set($name, (string) $value);
        }
        parent::tearDown();
    }

    private function metadata(): \DOMXPath
    {
        global $gCurrentUser;

        $document = new \DOMDocument();
        $document->loadXML((new SAMLService($this->getDatabase(), $gCurrentUser))->getMetadataXml());
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

        return $xpath;
    }

    /**
     * @testdox The metadata states how long it is valid and how long it may be cached
     */
    public function testTheMetadataIsValidForALimitedTime(): void
    {
        global $gSettingsManager;

        $gSettingsManager->set('sso_saml_metadata_valid_days', '10');
        $gSettingsManager->set('sso_saml_metadata_cache_hours', '6');
        $entity = $this->metadata()->query('/md:EntityDescriptor')->item(0);

        $this->assertSame('PT6H', $entity->getAttribute('cacheDuration'));
        $this->assertEqualsWithDelta(time() + 10 * 86400, strtotime($entity->getAttribute('validUntil')), 5);
    }

    /**
     * @testdox The metadata names the organization and a technical contact, from the settings or the defaults
     */
    public function testTheMetadataNamesOrganizationAndContact(): void
    {
        global $gSettingsManager, $gCurrentOrganization;

        $gSettingsManager->set('sso_saml_organization_name', 'The Club');
        $gSettingsManager->set('sso_saml_organization_url', 'https://club.example/');
        $gSettingsManager->set('sso_saml_contact_email', 'sso@club.example');
        $xpath = $this->metadata();

        $this->assertSame('The Club', $xpath->evaluate('string(//md:Organization/md:OrganizationName)'));
        $this->assertSame('https://club.example/', $xpath->evaluate('string(//md:Organization/md:OrganizationURL)'));
        $this->assertSame('technical', $xpath->evaluate('string(//md:ContactPerson/@contactType)'));
        $this->assertSame('sso@club.example', $xpath->evaluate('string(//md:ContactPerson/md:EmailAddress)'));

        $gSettingsManager->set('sso_saml_organization_name', '');
        $gSettingsManager->set('sso_saml_organization_url', '');
        $xpath = $this->metadata();
        $this->assertSame((string) $gCurrentOrganization->getValue('org_longname'), $xpath->evaluate('string(//md:Organization/md:OrganizationName)'));
        $this->assertSame(ADMIDIO_URL, $xpath->evaluate('string(//md:Organization/md:OrganizationURL)'));
    }

    /**
     * @testdox The IdP-initiated URL names the service provider and carries the RelayState
     */
    public function testTheIdpInitiatedUrl(): void
    {
        global $gCurrentUser;

        $service = new SAMLService($this->getDatabase(), $gCurrentUser);
        $url = $service->getIdPInitiatedUrl('https://sp.example/metadata', 'a b');

        $this->assertStringContainsString('/saml/idp-init?sp=https%3A%2F%2Fsp.example%2Fmetadata&RelayState=a%20b', $url);
    }

    /**
     * An unsolicited response has no InResponseTo, neither in the response nor in the confirmation, and goes
     * to the default ACS URL with the RelayState it was asked to carry.
     *
     * @testdox An unsolicited response goes to the default ACS URL without InResponseTo
     */
    public function testAnUnsolicitedResponseHasNoInResponseTo(): void
    {
        global $gCurrentUser;

        $previousSession = $GLOBALS['gCurrentSession'] ?? null;
        $session = new Session($this->getDatabase(), COOKIE_PREFIX);
        $session->setValue('ses_authentication_time', date('Y-m-d H:i:s'));
        $session->setValue('ses_external_session_id', bin2hex(random_bytes(16)));
        $GLOBALS['gCurrentSession'] = $session;
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_acs_url', "https://sp.example/acs1\nhttps://sp.example/acs2");
        $client->setValue('smc_sign_assertions', 0);

        $service = new SAMLService($this->getDatabase(), $gCurrentUser);
        ob_start();
        (new ReflectionMethod($service, 'issueAuthnResponse'))->invoke(
            $service, $client, $client->getAcsUrls()[0],
            array('format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified', 'spNameQualifier' => null),
            null, 'relay'
        );
        $html = (string) ob_get_clean();
        $GLOBALS['gCurrentSession'] = $previousSession;

        $this->assertStringContainsString('action="https://sp.example/acs1"', $html);
        preg_match('/name="SAMLResponse" value="([^"]+)"/', $html, $match);
        $this->assertNotEmpty($match, 'the page posts a SAMLResponse');
        $xml = base64_decode(html_entity_decode($match[1]));
        $this->assertStringNotContainsString('InResponseTo', $xml);
        $this->assertStringContainsString('https://sp.example/acs1', $xml);
        $this->assertStringContainsString('value="relay"', $html);
    }
}
