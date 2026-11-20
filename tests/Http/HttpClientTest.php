<?php

namespace Admidio\Tests\Http;

use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Support\HttpClient;
use RuntimeException;
use Symfony\Component\Process\Process;

class HttpClientTest extends AdmidioTestCase
{
    private static ?Process $server = null;
    private static string $url;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $error);
        if ($socket === false) {
            throw new RuntimeException('Cannot reserve transport test port: ' . $error);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$url = 'http://' . $address;
        self::$server = new Process([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/transport.php']);
        self::$server->setTimeout(null);
        self::$server->start();
        try {
            $deadline = microtime(true) + 10;
            do {
                if (!self::$server->isRunning()) {
                    throw new RuntimeException('Transport server exited: ' . self::$server->getErrorOutput());
                }
                $connection = @stream_socket_client('tcp://' . $address, $errorCode, $error, 0.1);
                if ($connection !== false) {
                    fclose($connection);
                    return;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            throw new RuntimeException('Transport server did not become ready.');
        } catch (\Throwable $exception) {
            self::$server->stop();
            throw $exception;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
        parent::tearDownAfterClass();
    }

    public function testRedirectIsReturnedWithoutFollowingIt(): void
    {
        $response = (new HttpClient(self::$url))->request('/redirect');
        $this->assertSame(303, $response->status);
        $this->assertSame('/echo', $response->header('Location'));
        $this->assertSame('Redirect response', $response->body);
    }

    public function testCookiesPersistOnlyWithinTheirBrowser(): void
    {
        $browser = new HttpClient(self::$url);
        $this->assertStringContainsString('HttpOnly', $browser->request('/cookie')->header('Set-Cookie'));
        $this->assertSame('browser-state', $browser->request('/echo')->json()['cookie']);
        $this->assertNull((new HttpClient(self::$url))->request('/echo')->json()['cookie']);
    }

    public function testFormEncodingAndMethodResetBetweenRequests(): void
    {
        $browser = new HttpClient(self::$url);
        $form = ['text' => 'Grüße & + = ?', 'filters' => ['first', 'second']];
        $response = $browser->request('/echo', $form, 'POST')->json();
        $this->assertSame('POST', $response['method']);
        $this->assertSame($form, $response['form']);
        $this->assertSame('DELETE', $browser->request('/echo', [], 'DELETE')->json()['method']);
        $response = $browser->request('/echo')->json();
        $this->assertSame('GET', $response['method']);
        $this->assertSame([], $response['form']);
    }

    public function testHeadersAreCaseInsensitiveAndDoNotLeakBetweenRequests(): void
    {
        $browser = new HttpClient(self::$url);
        $response = $browser->request('/echo');
        $this->assertSame('first, second', $response->header('x-TEST-value'));
        $this->assertSame('', $response->header('missing'));
        $this->assertSame('', $browser->request('/redirect')->header('X-Test-Value'));
    }

    /** @dataProvider invalidPaths */
    public function testExternalUrlsAreRejected(string $path): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only paths within the test installation');
        (new HttpClient(self::$url))->request($path);
    }

    public static function invalidPaths(): array
    {
        return [
            'absolute URL' => ['http://example.invalid/'],
            'protocol-relative URL' => ['//example.invalid/'],
            'relative path' => ['system/login.php'],
        ];
    }
}
