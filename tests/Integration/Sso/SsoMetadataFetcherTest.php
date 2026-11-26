<?php
/**
 * Regression coverage for fetching the metadata of a single sign-on partner.
 *
 * The SAML metadata interoperability profile requires HTTP redirects 301, 302 and 307 to be
 * honoured (Kantara IIP-MD02.b). The outbound request policy pins cURL to the validated address
 * and therefore did not follow redirects at all; SAMLscope's metadata campaign stopped at its
 * redirect fixture. Redirects are now followed hop by hop, and every target has to pass the policy.
 *
 * The requests are scripted, so the test covers the redirect handling and the policy checks
 * without a network. The hosts are IP literals, so the policy needs no name resolution.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Service\MetadataFetcher;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use RuntimeException;

class SsoMetadataFetcherTest extends AdministratorTestCase
{
    private const START = 'https://93.184.216.34/sp/metadata';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox Redirects 301, 302 and 307 are followed to the metadata
     */
    public function testRedirectsAreFollowed(): void
    {
        $fetcher = $this->fetcher(array(
            self::START => array(301, '', 'https://93.184.216.35/moved'),
            'https://93.184.216.35/moved' => array(302, '', '/found?x=1'),
            'https://93.184.216.35/found?x=1' => array(307, '', 'final.xml'),
            'https://93.184.216.35/final.xml' => array(200, '<EntityDescriptor/>', '')
        ));

        $this->assertSame('<EntityDescriptor/>', $fetcher->fetch(self::START));
        $this->assertSame(array(
            self::START,
            'https://93.184.216.35/moved',
            'https://93.184.216.35/found?x=1',
            'https://93.184.216.35/final.xml'
        ), $fetcher->requestedUrls);
    }

    /**
     * A redirect must not lead past the outbound policy into the network of the installation.
     *
     * @testdox A redirect to a private address is refused
     */
    public function testARedirectToAPrivateAddressIsRefused(): void
    {
        $fetcher = $this->fetcher(array(self::START => array(302, '', 'https://10.0.0.5/metadata')));

        $this->expectException(Exception::class);
        $fetcher->fetch(self::START);
    }

    /**
     * @testdox A redirect to a private address is followed when private networks are allowed
     */
    public function testARedirectToAPrivateAddressIsFollowedWhenAllowed(): void
    {
        $fetcher = $this->fetcher(array(
            self::START => array(302, '', 'https://10.0.0.5/metadata'),
            'https://10.0.0.5/metadata' => array(200, '<EntityDescriptor/>', '')
        ), true);

        $this->assertSame('<EntityDescriptor/>', $fetcher->fetch(self::START));
    }

    /**
     * @testdox A redirect from HTTPS to plain HTTP is refused
     */
    public function testADowngradeToHttpIsRefused(): void
    {
        $fetcher = $this->fetcher(array(self::START => array(301, '', 'http://93.184.216.35/metadata')));

        $this->expectException(Exception::class);
        $fetcher->fetch(self::START);
    }

    /**
     * @testdox Too many redirects end the request
     */
    public function testTooManyRedirectsEndTheRequest(): void
    {
        $responses = array();
        for ($hop = 0; $hop <= MetadataFetcher::MAX_REDIRECTS + 1; $hop++) {
            $responses['https://93.184.216.34/hop' . $hop] = array(302, '', '/hop' . ($hop + 1));
        }
        $fetcher = $this->fetcher($responses);

        $this->expectException(RuntimeException::class);
        $fetcher->fetch('https://93.184.216.34/hop0');
    }

    /**
     * @testdox An error status ends the request
     */
    public function testAnErrorStatusEndsTheRequest(): void
    {
        $fetcher = $this->fetcher(array(self::START => array(404, 'Not found', '')));

        $this->expectException(RuntimeException::class);
        $fetcher->fetch(self::START);
    }

    /**
     * @dataProvider locationProvider
     * @testdox The redirect target $location is resolved to $expected
     */
    public function testTheLocationIsResolved(string $location, string $expected): void
    {
        $this->assertSame($expected, MetadataFetcher::resolveLocation('https://idp.example:8443/a/b/metadata?x=1', $location));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function locationProvider(): array
    {
        return array(
            'absolute' => array('https://other.example/m', 'https://other.example/m'),
            'scheme-relative' => array('//other.example/m', 'https://other.example/m'),
            'absolute path' => array('/m', 'https://idp.example:8443/m'),
            'relative path' => array('m.xml', 'https://idp.example:8443/a/b/m.xml'),
            'query only' => array('?y=2', 'https://idp.example:8443/a/b/metadata?y=2'),
            'with fragment' => array('/m#part', 'https://idp.example:8443/m')
        );
    }

    /**
     * A fetcher that answers from the given responses instead of the network.
     * @param array<string,array{0:int,1:string,2:string}> $responses Status, body and Location by URL.
     */
    private function fetcher(array $responses, bool $allowPrivateNetwork = false): MetadataFetcher
    {
        return new class ($responses, $allowPrivateNetwork) extends MetadataFetcher {
            /** @var array<int,string> */
            public array $requestedUrls = array();

            public function __construct(private array $responses, bool $allowPrivateNetwork)
            {
                parent::__construct($allowPrivateNetwork);
            }

            protected function request(string $url, array $requestOptions): array
            {
                $this->requestedUrls[] = $url;
                if (!isset($this->responses[$url])) {
                    throw new RuntimeException('Unexpected request to ' . $url);
                }
                return $this->responses[$url];
            }
        };
    }
}
