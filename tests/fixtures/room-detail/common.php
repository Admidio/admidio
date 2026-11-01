<?php
/**
 * Isolated bootstrap for HTTP tests of the unmodified msg_window.php script.
 * The real parameter validator and error handler run here. Only the service and bootstrap
 * collaborators are replaced; authorization against real records is covered by RoomDetailAccessTest.
 */
namespace Admidio\Events\Service;

class EventService
{
    public function __construct($database)
    {
    }

    public function getRoomForEvent(string $eventUUID, string $roomUUID): object
    {
        if ($eventUUID !== '11111111-1111-4111-8111-111111111111'
            || $roomUUID !== '22222222-2222-4222-8222-222222222222') {
            throw new \Admidio\Infrastructure\Exception('SYS_NO_RIGHTS');
        }

        return new class {
            public function getValue(string $column): mixed
            {
                return array(
                    'room_name' => 'HTTP room name sentinel',
                    'room_capacity' => 37,
                    'room_overhang' => 9,
                    'room_description' => 'HTTP room description sentinel'
                )[$column];
            }
        };
    }
}

require getenv('ADMIDIO_ROOM_HTTP_TEST_ROOT') . '/vendor/autoload.php';
require getenv('ADMIDIO_ROOM_HTTP_TEST_ROOT') . '/system/bootstrap/function.php';

$gDb = null;
$gDebug = false;
$gLogger = new \Psr\Log\NullLogger();
$gL10n = new class {
    public function get(string $key, array $parameters = array()): string
    {
        return $key;
    }
};
$gHtmlPurifierFilter = new class {
    public function purify($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
};
$gMessage = new class {
    public function show(string $text): void
    {
        echo $text;
    }
};
