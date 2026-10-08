<?php
/**
 * The transport of the updater: which URLs it requests and how it follows redirects. The network
 * is replaced by a recorded list of answers.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\HttpClient;
use AdmidioCoreUpdater\UpdaterException;

final class HttpClientTest extends AdmidioTestCase
{
    /** @var array<int,string> The URLs the client requested. */
    private array $requested = array();

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    /**
     * A client whose network answers with the given responses, keyed by URL.
     * @param array<string,array{0: int, 1: string}> $responses url => [status, location or body]
     */
    private function client(array $responses, string $plainHttpBase = ''): HttpClient
    {
        return new HttpClient($plainHttpBase, function (string $url, int $maxBytes, $file) use ($responses): array {
            $this->requested[] = $url;
            [$status, $value] = $responses[$url] ?? array(404, '');
            $redirect = $status >= 300 && $status < 400;
            if (!$redirect && $file !== null) {
                fwrite($file, $value);
            }

            return array('status' => $status, 'location' => $redirect ? $value : '', 'body' => $redirect || $file !== null ? '' : $value);
        });
    }

    private function assertRefused(string $key, callable $call, string $message = ''): void
    {
        try {
            $call();
            $this->fail('not refused: ' . $message);
        } catch (UpdaterException $exception) {
            $this->assertSame($key, $exception->getMessage(), $message . ' - ' . $exception->getDetail());
        }
    }

    /**
     * @testdox Redirects are followed hop by hop, also relative ones
     */
    public function testRedirects(): void
    {
        $client = $this->client(array(
            'https://updates.example.org/stable/release.json' => array(302, '/mirror/release.json'),
            'https://updates.example.org/mirror/release.json' => array(301, '//cdn.example.net/a/b/release.json'),
            'https://cdn.example.net/a/b/release.json' => array(307, 'final.json?x=1'),
            'https://cdn.example.net/a/b/final.json?x=1' => array(200, '{"ok":true}')
        ));

        $this->assertSame('{"ok":true}', $client->get('https://updates.example.org/stable/release.json', 1000));
        $this->assertCount(4, $this->requested);
    }

    /**
     * @testdox A redirect to plain HTTP is refused
     */
    public function testRedirectToHttpIsRefused(): void
    {
        $client = $this->client(array(
            'https://updates.example.org/release.json' => array(302, 'http://updates.example.org/release.json')
        ));

        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => $client->get('https://updates.example.org/release.json', 1000));
        $this->assertSame(array('https://updates.example.org/release.json'), $this->requested, 'the HTTP URL was never requested');
    }

    /**
     * @testdox More than five redirects are refused
     */
    public function testRedirectLimit(): void
    {
        $responses = array();
        for ($hop = 0; $hop <= HttpClient::MAX_REDIRECTS; $hop++) {
            $responses['https://example.org/' . $hop] = array(302, '/' . ($hop + 1));
        }
        $responses['https://example.org/' . (HttpClient::MAX_REDIRECTS + 1)] = array(200, 'too late');

        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => $this->client($responses)->get('https://example.org/0', 1000));

        unset($responses['https://example.org/' . HttpClient::MAX_REDIRECTS]);
        $responses['https://example.org/' . HttpClient::MAX_REDIRECTS] = array(200, 'in time');
        $this->assertSame('in time', $this->client($responses)->get('https://example.org/0', 1000), 'five are fine');
    }

    /**
     * @testdox Only HTTPS without credentials is requested
     */
    public function testAllowedUrls(): void
    {
        $client = $this->client(array());

        foreach (array('http://example.org/a', 'ftp://example.org/a', 'file:///etc/passwd', 'https://user:pw@example.org/a', 'https:///a', 'no url') as $url) {
            $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => $client->assertAllowed($url), $url);
        }
        $client->assertAllowed('https://any-mirror.example.net/admidio.zip');
    }

    /**
     * @testdox Plain HTTP is only allowed below the test server of debug mode
     */
    public function testPlainHttpForTheTestServer(): void
    {
        $client = $this->client(array('http://localhost:8080/updates/stable/release.json' => array(200, 'local')), 'http://localhost:8080/updates/');

        $this->assertSame('local', $client->get('http://localhost:8080/updates/stable/release.json', 1000));
        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => $client->assertAllowed('http://localhost:8080/other/file'));
        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => $client->assertAllowed('http://example.org/updates/'));
    }

    /**
     * @testdox An error status is a failed download, and a failed download leaves no file
     */
    public function testErrorStatus(): void
    {
        $file = sys_get_temp_dir() . '/adm_download_' . uniqid();
        $client = $this->client(array('https://example.org/gone.zip' => array(404, 'not found')));

        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => $client->download('https://example.org/gone.zip', $file, 1000));
        $this->assertFileDoesNotExist($file);
    }

    /**
     * @testdox A download is written to the file, without the bodies of the redirects
     */
    public function testDownload(): void
    {
        $file = sys_get_temp_dir() . '/adm_download_' . uniqid();
        $client = $this->client(array(
            'https://example.org/latest.zip' => array(302, 'https://mirror.example.net/admidio.zip'),
            'https://mirror.example.net/admidio.zip' => array(200, 'PK-the-package')
        ));

        try {
            $client->download('https://example.org/latest.zip', $file, 1000);
            $this->assertSame('PK-the-package', file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }

    /**
     * @testdox The real transport reports redirects without following them and stops at the size limit
     *
     * It talks to PHP's built-in web server on localhost, through the plain HTTP exception of a
     * test server. The limit is counted on the bytes that arrive.
     */
    public function testRealTransport(): void
    {
        $directory = sys_get_temp_dir() . '/adm_http_' . uniqid();
        mkdir($directory);
        file_put_contents($directory . '/router.php', '<?php
            if ($_SERVER["REQUEST_URI"] === "/moved") { header("Location: /data", true, 302); exit; }
            echo str_repeat("x", 5000);');

        $port = 18000 + random_int(0, 999);
        $server = proc_open(
            array(PHP_BINARY, '-S', '127.0.0.1:' . $port, $directory . '/router.php'),
            array(1 => array('file', $directory . '/server.log', 'w'), 2 => array('file', $directory . '/server.log', 'a')),
            $pipes
        );

        try {
            $base = 'http://127.0.0.1:' . $port . '/';
            for ($attempt = 0; $attempt < 50 && @fsockopen('127.0.0.1', $port) === false; $attempt++) {
                usleep(100000);
            }

            $transport = new \ReflectionMethod(HttpClient::class, 'curlTransport');
            $moved = $transport->invoke(null, $base . 'moved', 1000, null);
            $this->assertSame(302, $moved['status']);
            $this->assertSame('/data', $moved['location']);

            $client = new HttpClient($base);
            $this->assertSame(5000, strlen($client->get($base . 'moved', 5000)), 'followed by the client, not by cURL');
            $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => $client->get($base . 'data', 4999));
        } finally {
            proc_terminate($server);
            proc_close($server);
            foreach (glob($directory . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
