<?php
/**
 * Regression coverage for opaque protocol values that have to reach the client unchanged.
 *
 * Admidio strips HTML from $_GET and $_POST, which cut the OIDC logout state of the conformance
 * suite off at its "<" (oidcc-rp-initiated-logout: CheckPostLogoutState). The SAML logout
 * transaction stores the RelayState and the return URL in a text column, which strips HTML too.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\SSO\Entity\SAMLLogoutTransaction;
use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

class SsoOpaqueParameterTest extends AdministratorTestCase
{
    /** The logout state of the conformance suite, with HTML characters and quotes */
    private const STATE = 'esFVgteXBZpu!"|{"~^[?~?~=-#}<[!$.!~<*\'}&>_';

    private array $previousServer = array();
    private array $previousGet = array();

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousServer = $_SERVER;
        $this->previousGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
        $_GET = $this->previousGet;
        parent::tearDown();
    }

    /**
     * @testdox The OIDC request keeps a state with HTML characters
     */
    public function testTheOidcRequestKeepsTheState(): void
    {
        global $gCurrentUser;

        $query = array('state' => self::STATE, 'client_id' => 'https://client.example/');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = http_build_query($query);
        // What global_request_params.php leaves of the parameters
        $_GET = StringUtils::strStripSpecialTags($query);
        $this->assertNotSame(self::STATE, $_GET['state']);

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        /** @var ServerRequestInterface $request */
        $request = (new ReflectionMethod($service, 'getRequest'))->invoke($service);

        $this->assertSame(self::STATE, $request->getQueryParams()['state'] ?? null);
        $this->assertSame('https://client.example/', $request->getQueryParams()['client_id'] ?? null);
    }

    /**
     * @testdox The SAML logout transaction keeps RelayState and return URL
     */
    public function testTheSamlLogoutTransactionKeepsRelayStateAndReturnUrl(): void
    {
        global $gCurrentOrgId;

        $returnUrl = 'https://client.example/back?' . http_build_query(array('state' => self::STATE)) . '&a=1';

        $transaction = new SAMLLogoutTransaction($this->getDatabase());
        $transaction->initialize((int) $gCurrentOrgId, 0, null, 'request-id', self::STATE, array(),
            array('type' => SAMLLogoutTransaction::COMPLETION_REDIRECT, 'url' => $returnUrl));
        $transaction->save();

        $stored = new SAMLLogoutTransaction($this->getDatabase());
        $this->assertTrue($stored->readDataByToken((string) $transaction->getValue('slt_token')));
        $this->assertSame(self::STATE, $stored->getInitiatorRelayState());
        $this->assertSame($returnUrl, $stored->getCompletionUrl());
    }
}
