<?php
/**
 * Regression coverage for the address policy of the two server-side single sign-on requests.
 *
 * The OIDC back-channel logout notification and the SAML metadata fetch are requests that Admidio
 * makes to a URL an administrator configured. Without a policy they are a way to reach services
 * inside the network that Admidio runs in. See findings 33 and 47.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Tests\Support\AdmidioTestCase;

class SsoOutboundRequestPolicyTest extends AdmidioTestCase
{
    /**
     * @dataProvider refusedUrlProvider
     * @testdox An outbound SSO request to $url is refused
     */
    public function testAnUnsafeTargetIsRefused(string $url): void
    {
        $this->expectException(Exception::class);
        SecurityUtils::getOutboundRequestCurlOptions($url);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function refusedUrlProvider(): array
    {
        return array(
            'the loopback address' => array('https://127.0.0.1/backchannel'),
            'a private network address' => array('https://192.168.1.10/backchannel'),
            'another private range' => array('https://10.0.0.5/backchannel'),
            'the link local range' => array('https://169.254.169.254/latest/meta-data/'),
            'a reserved address' => array('https://0.0.0.0/backchannel'),
            'plain HTTP' => array('http://93.184.216.34/backchannel'),
            'a scheme that is not HTTP at all' => array('file:///etc/passwd'),
            'the gopher scheme' => array('gopher://93.184.216.34/'),
            'credentials in the URL' => array('https://user:password@93.184.216.34/backchannel'),
            'a fragment in the URL' => array('https://93.184.216.34/backchannel#fragment'),
            'not a URL at all' => array('not a url')
        );
    }

    /**
     * @testdox A public HTTPS target is accepted and pinned to the address that was checked
     */
    public function testAPublicTargetIsAcceptedAndPinned(): void
    {
        $options = SecurityUtils::getOutboundRequestCurlOptions('https://93.184.216.34/backchannel');

        $this->assertIsArray($options);
        // The connection is pinned to the address that was validated, so that a second name
        // resolution cannot answer with a private one (DNS rebinding).
        $this->assertContains(CURL_IPRESOLVE_V4, $options);
        $this->assertNotSame(array(), array_filter($options, static function ($value) {
            return is_array($value) && count(preg_grep('/93\.184\.216\.34/', $value)) > 0;
        }));
    }

    /**
     * An installation that federates with a service on its own network can allow that explicitly.
     * Nothing else may change: the address is still resolved once and pinned.
     *
     * @testdox A private target is only accepted when private destinations are allowed
     */
    public function testAPrivateTargetNeedsTheExplicitAllowance(): void
    {
        $options = SecurityUtils::getOutboundRequestCurlOptions('http://192.168.1.10/backchannel', true);

        $this->assertIsArray($options);
        $this->assertContains(CURL_IPRESOLVE_V4, $options);
    }

    /**
     * @testdox Allowing private destinations does not allow a scheme or a URL shape that is refused anyway
     */
    public function testTheAllowanceDoesNotWidenTheRestOfThePolicy(): void
    {
        $this->expectException(Exception::class);
        SecurityUtils::getOutboundRequestCurlOptions('https://user:password@192.168.1.10/backchannel', true);
    }
}
