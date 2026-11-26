<?php
/**
 * Regression coverage for the hardening of the SSO endpoints found in the end-to-end review
 * (findings 116, 117, 123, 124): the lockout after failed client authentications, the limit for an
 * inflated SAML message, the access roles that bind administrators too, and several sign-ins of one
 * session at the same SAML client.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\SAMLBoundedRedirectBinding;
use Admidio\SSO\Service\SAMLSessionParticipantService;
use Admidio\SSO\Service\SSOAuthThrottleService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Error\LightSamlBindingException;
use LightSaml\SamlConstants;
use ReflectionMethod;

class SsoHardeningTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @dataProvider lockProvider
     * @testdox $failures failed authentications lock for $seconds seconds
     */
    public function testTheLockGrows(int $failures, int $seconds): void
    {
        $this->assertSame($seconds, SSOAuthThrottleService::lockSeconds($failures));
    }

    public static function lockProvider(): array
    {
        return array(
            array(1, 0), array(9, 0), array(10, 60), array(11, 120), array(12, 240), array(13, 480),
            array(14, 900), array(50, 900)
        );
    }

    /**
     * @testdox A client is locked from the address that fails, the lock grows, and a success ends it
     */
    public function testAFailingAddressIsLockedAndASuccessEndsIt(): void
    {
        $service = new SSOAuthThrottleService($this->getDatabase());
        $client = 'client-' . $this->suffix();
        $address = '203.0.113.' . random_int(1, 250);

        for ($i = 1; $i <= 9; $i++) {
            $service->recordFailure($client, $address);
        }
        $this->assertSame(0, $service->getRetryAfter($client, $address), 'nine failures are free');

        $service->recordFailure($client, $address);
        $this->assertEqualsWithDelta(60, $service->getRetryAfter($client, $address), 2);
        $this->assertSame(0, $service->getRetryAfter($client, '198.51.100.7'), 'another address is not locked');

        $service->recordFailure($client, $address);
        $this->assertEqualsWithDelta(120, $service->getRetryAfter($client, $address), 2);

        $service->recordSuccess($client, $address);
        $this->assertSame(0, $service->getRetryAfter($client, $address));
    }

    /**
     * @testdox An address that tries many clients is locked later than one that fails at a single client
     */
    public function testAnAddressAloneIsLockedLate(): void
    {
        $service = new SSOAuthThrottleService($this->getDatabase());
        $address = '203.0.113.' . random_int(1, 250);

        for ($i = 1; $i <= 29; $i++) {
            $service->recordFailure('other-' . $i . $this->suffix(), $address);
        }
        $this->assertSame(0, $service->getRetryAfter('fresh-client', $address));

        $service->recordFailure('other-30' . $this->suffix(), $address);
        $this->assertEqualsWithDelta(60, $service->getRetryAfter('fresh-client', $address), 2);
    }

    /**
     * @testdox A deflated SAML message that inflates beyond the limit is refused, a normal one is read
     */
    public function testAnInflationBombIsRefused(): void
    {
        $binding = new SAMLBoundedRedirectBinding();
        $decode = new ReflectionMethod($binding, 'decodeMessageString');

        $xml = '<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"/>';
        $this->assertSame($xml, $decode->invoke($binding, base64_encode(gzdeflate($xml)), SamlConstants::ENCODING_DEFLATE));

        $bomb = gzdeflate(str_repeat('A', SAMLBoundedRedirectBinding::MAX_INFLATED_LENGTH * 20));
        $this->assertLessThan(SAMLBoundedRedirectBinding::MAX_ENCODED_LENGTH, strlen(base64_encode($bomb)));
        $this->expectException(LightSamlBindingException::class);
        $decode->invoke($binding, base64_encode($bomb), SamlConstants::ENCODING_DEFLATE);
    }

    /**
     * @testdox An administrator outside the access roles of a client does not pass
     */
    public function testAnAdministratorIsBoundByTheAccessRoles(): void
    {
        global $gCurrentOrgId, $gCurrentUser;

        $suffix = $this->suffix();
        $role = $this->getFixture()->createAndSaveRole('SSO only ' . $suffix, $gCurrentOrgId);

        $this->assertTrue($gCurrentUser->isAdministrator());
        $this->assertFalse($this->createRestrictedClient($suffix, array($role['rol_id']))->hasAccessRight());
        $this->assertTrue($this->createRestrictedClient($this->suffix(), array())->hasAccessRight(), 'without roles the client is open');
    }

    /**
     * @testdox A second sign-in of a session at the same client keeps the first one for the logout
     */
    public function testSeveralSignInsOfASessionAreKept(): void
    {
        global $gCurrentOrgId;

        $client = $this->createSamlClient($this->suffix());
        $service = new SAMLSessionParticipantService($this->getDatabase());
        $user = $this->getFixture()->createAndSaveUser('sso-two-' . $this->suffix(), 'x@example.local');
        $session = bin2hex(random_bytes(16));
        $later = new \DateTimeImmutable('+1 hour');

        foreach (array('index-one', 'index-two') as $index) {
            $service->persistParticipant((int) $gCurrentOrgId, $user['usr_id'], $session, (int) $client->getValue('smc_id'),
                'name-id', SamlConstants::NAME_ID_FORMAT_PERSISTENT, null, $index, new \DateTimeImmutable(), $later);
        }

        $indexes = array_column($service->getParticipants((int) $gCurrentOrgId, $session), 'ssp_session_index');
        $this->assertSame(array('index-one', 'index-two'), $indexes);
    }
}
