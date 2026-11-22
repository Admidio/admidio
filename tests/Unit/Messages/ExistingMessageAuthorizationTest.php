<?php

namespace Admidio\Tests\Unit\Messages;

use Admidio\Messages\Entity\Message;
use PHPUnit\Framework\TestCase;
use LogicException;

class ExistingMessageAuthorizationTest extends TestCase
{
    public static function accessCases(): array
    {
        return array(
            'guest email' => array(false, 0, 'EMAIL', 42, false, false),
            'guest email without sender' => array(false, 0, 'EMAIL', 0, false, false),
            'invalid login with sender ID' => array(false, 42, 'EMAIL', 42, false, false),
            'login with zero ID' => array(true, 0, 'EMAIL', 0, false, false),
            'email sender' => array(true, 42, 'EMAIL', 42, false, true),
            'email sender stored as string' => array(true, 42, 'EMAIL', '42', false, true),
            'unrelated email reader' => array(true, 7, 'EMAIL', 42, false, false),
            'guest PM' => array(false, 0, 'PM', 42, 7, false),
            'PM sender' => array(true, 42, 'PM', 42, 7, true),
            'PM recipient' => array(true, 7, 'PM', 42, 7, true),
            'PM recipient stored as string' => array(true, 7, 'PM', '42', '7', true),
            'unrelated PM reader' => array(true, 9, 'PM', 42, 7, false),
            'PM without recipient' => array(true, 9, 'PM', 42, 0, false)
        );
    }

    /** @dataProvider accessCases */
    public function testExistingMessageAccess(bool $gValidLogin, int $gCurrentUserId, string $getMsgType, $sender, $partner, bool $allowed): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getValue')->with('msg_usr_id_sender')->willReturn($sender);
        $message->method('getConversationPartner')->willReturn($partner);
        $message->expects($allowed ? $this->once() : $this->never())->method('setReadValue');
        $getMsgUUID = '00000000-0000-4000-8000-000000000001';

        try {
            eval($this->authorizationBranch());
            $this->assertTrue($allowed, 'Unauthorized message access was accepted');
        } catch (LogicException $exception) {
            $this->assertFalse($allowed, 'Authorized message access was rejected');
            $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getMessage());
        }
    }

    public function testGuestCanComposeNewEmail(): void
    {
        $gValidLogin = false;
        $gCurrentUserId = 0;
        $getMsgType = Message::MESSAGE_TYPE_EMAIL;
        $getMsgUUID = '';
        $message = $this->createMock(Message::class);
        $message->expects($this->never())->method('getValue');
        $message->expects($this->never())->method('setReadValue');

        eval($this->authorizationBranch());
    }

    private function authorizationBranch(): string
    {
        // Execute the real endpoint through the read-status update, without a web session or database.
        $source = file_get_contents(dirname(__DIR__, 3) . '/modules/messages/messages_write.php');
        $marker = strpos($source, '// Update the read status of the message');
        $this->assertNotFalse($marker);
        $start = strpos($source, "    if (\$getMsgUUID !== '') {", $marker);
        $this->assertNotFalse($start);
        $end = strpos($source, '        if ($getForward === true)', $start);
        $this->assertNotFalse($end);

        return 'use Admidio\\Messages\\Entity\\Message; use LogicException as Exception;'
            . substr($source, $start, $end - $start) . "    }\n";
    }
}
