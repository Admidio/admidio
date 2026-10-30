<?php

namespace Admidio\Tests\Integration\Events;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class RoomDetailHttpTest extends TestCase
{
    private static Process $server;
    private static string $directory;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new \RuntimeException('Cannot bind HTTP test server: ' . $errorMessage);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $root = dirname(__DIR__, 3);
        self::$directory = sys_get_temp_dir() . '/admidio-room-http-' . bin2hex(random_bytes(8));
        mkdir(self::$directory, 0700);
        // Copy the production entry point unchanged, with a controlled common.php beside it.
        copy($root . '/system/msg_window.php', self::$directory . '/msg_window.php');
        copy($root . '/tests/fixtures/room-detail/common.php', self::$directory . '/common.php');
        self::$baseUrl = 'http://' . $address . '/msg_window.php?';
        self::$server = new Process(
            [PHP_BINARY, '-S', $address, '-t', self::$directory],
            $root,
            ['ADMIDIO_ROOM_HTTP_TEST_ROOT' => $root]
        );
        self::$server->setTimeout(10);
        self::$server->start();
        if (!self::$server->waitUntil(static fn ($type, $output) => str_contains($output, 'Development Server'))) {
            throw new \RuntimeException('HTTP test server did not start: ' . self::$server->getErrorOutput());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$server)) {
            self::$server->stop();
        }
        if (isset(self::$directory)) {
            unlink(self::$directory . '/msg_window.php');
            unlink(self::$directory . '/common.php');
            rmdir(self::$directory);
        }
    }

    public static function requests(): array
    {
        $eventUUID = '11111111-1111-4111-8111-111111111111';
        $roomUUID = '22222222-2222-4222-8222-222222222222';
        $unknownUUID = '33333333-3333-4333-8333-333333333333';
        return array(
            'authorized popup' => [['dat_uuid' => $eventUUID, 'room_uuid' => $roomUUID], true, ''],
            'legacy numeric link' => [['message_var1' => '1'], false, 'room_uuid'],
            'numeric room UUID' => [['dat_uuid' => $eventUUID, 'room_uuid' => '1'], false, 'room_uuid'],
            'invalid room UUID' => [['dat_uuid' => $eventUUID, 'room_uuid' => 'invalid'], false, 'room_uuid'],
            'invalid event UUID' => [['dat_uuid' => 'invalid', 'room_uuid' => $roomUUID], false, 'dat_uuid'],
            'missing event UUID' => [['room_uuid' => $roomUUID], false, 'dat_uuid'],
            'denied room' => [['dat_uuid' => $eventUUID, 'room_uuid' => $unknownUUID], false, 'SYS_NO_RIGHTS'],
            'denied event' => [['dat_uuid' => $unknownUUID, 'room_uuid' => $roomUUID], false, 'SYS_NO_RIGHTS']
        );
    }

    /** @dataProvider requests */
    public function testHttpResponse(array $parameters, bool $allowed, string $expectedError): void
    {
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $response = file_get_contents(self::$baseUrl . http_build_query(array_merge(
            ['message_id' => 'room_detail', 'inline' => 'true'],
            $parameters
        )), false, $context);
        $this->assertIsString($response);
        if ($allowed) {
            $this->assertStringContainsString('HTTP room name sentinel', $response);
            $this->assertStringContainsString('HTTP room description sentinel', $response);
            $this->assertStringContainsString('>37<', $response);
            $this->assertStringContainsString('>9<', $response);
        } else {
            $this->assertStringContainsString($expectedError, $response);
            $this->assertStringNotContainsString('HTTP room name sentinel', $response);
            $this->assertStringNotContainsString('HTTP room description sentinel', $response);
            $this->assertStringNotContainsString('modal-body', $response);
        }
    }
}
