<?php
/**
 * Regression coverage for the Subject of an AuthnRequest.
 *
 * The Subject has to be the logged-in user (SAML Profiles 4.1.4.1: another principal gets an error
 * and no assertion), but the service provider may know the user by login name, UUID or e-mail
 * address. Found with the Codice SAML conformance test kit, which is not part of the build.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\SAMLRequestException;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\SamlConstants;
use ReflectionMethod;

class SsoSamlRequestedSubjectTest extends AdministratorTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function service(): SAMLService
    {
        global $gCurrentUser;

        return new SAMLService($this->getDatabase(), $gCurrentUser);
    }

    /**
     * Call a private method of the production service.
     */
    private function call(string $method, mixed ...$arguments): mixed
    {
        $service = $this->service();

        return (new ReflectionMethod($service, $method))->invoke($service, ...$arguments);
    }

    private function requestNaming(string $value, string $format = ''): AuthnRequest
    {
        return (new AuthnRequest())->setSubject((new Subject())->setNameID(new NameID($value, $format ?: null)));
    }

    /**
     * @testdox An AuthnRequest without a Subject is accepted
     */
    public function testARequestWithoutASubjectIsAccepted(): void
    {
        $this->call('verifyRequestedSubject', new AuthnRequest(), 'issued');
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider knownIdentifierProvider
     * @testdox The Subject may be the user's $identifier
     */
    public function testTheSubjectMayBeAnyIdentifierOfTheUser(string $identifier, bool $upperCase): void
    {
        global $gCurrentUser;

        $value = (string) ($identifier === 'issued' ? 'the-issued-value' : $gCurrentUser->getValue($identifier));
        $this->assertNotSame('', $value, 'The test user has no ' . $identifier . '.');

        $this->call('verifyRequestedSubject', $this->requestNaming($upperCase ? strtoupper($value) : $value), 'the-issued-value');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function knownIdentifierProvider(): array
    {
        return array(
            'issued NameID' => array('issued', false),
            'login name' => array('usr_login_name', false),
            'login name in other case' => array('usr_login_name', true),
            'UUID' => array('usr_uuid', false)
        );
    }

    /**
     * @testdox A Subject that names another principal is refused as Requester/UnknownPrincipal
     */
    public function testASubjectNamingAnotherPrincipalIsRefused(): void
    {
        try {
            $this->call('verifyRequestedSubject', $this->requestNaming('somebody-else'), 'the-issued-value');
            $this->fail('A Subject naming another principal should have been refused.');
        } catch (SAMLRequestException $exception) {
            $this->assertSame(
                array(SamlConstants::STATUS_REQUESTER, SAMLService::STATUS_UNKNOWN_PRINCIPAL),
                $exception->getStatus()
            );
        }
    }

    /**
     * Entity and transient identifiers carry no identity, so they say nothing to contradict.
     *
     * @dataProvider formatsWithoutIdentityProvider
     * @testdox A Subject in the format $format is not checked
     */
    public function testFormatsWithoutAnIdentityAreNotChecked(string $format): void
    {
        $this->call('verifyRequestedSubject', $this->requestNaming('somebody-else', $format), 'the-issued-value');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function formatsWithoutIdentityProvider(): array
    {
        return array(
            'entity' => array(SamlConstants::NAME_ID_FORMAT_ENTITY),
            'transient' => array(SamlConstants::NAME_ID_FORMAT_TRANSIENT)
        );
    }
}
