<?php
/**
 * Regression coverage for the form that posts a SAML message to the service provider.
 *
 * SAML Bindings 3.5.4 says the method attribute MUST be "POST", LightSAML writes "post" and some
 * validators compare literally. Found with the Codice SAML conformance test kit, which is not part
 * of the build.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Model\Protocol\Response;
use ReflectionMethod;

class SsoSamlPostFormMethodTest extends AdministratorTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox The HTTP-POST binding form states method="POST"
     */
    public function testThePostBindingFormMethodIsUpperCase(): void
    {
        $response = new Response();
        $response->setDestination('https://sp.example/acs');
        $response->setID('ID' . bin2hex(random_bytes(8)));
        $response->setIssueInstant(new \DateTime());
        $messageContext = new MessageContext();
        $messageContext->setMessage($response);

        global $gCurrentUser;

        $service = new SAMLService($this->getDatabase(), $gCurrentUser);
        $page = (string) (new ReflectionMethod($service, 'getPostBindingContent'))->invoke($service, $messageContext);

        $this->assertStringContainsString('<form method="POST" action="https://sp.example/acs"', $page);
        $this->assertStringNotContainsString('method="post"', $page);
        $this->assertStringContainsString('name="SAMLResponse"', $page);
    }
}
