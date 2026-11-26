<?php

namespace Admidio\Tests\Unit\Profile;

use Admidio\Infrastructure\ChangeNotification;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Hooks\ValueObject\EntityFieldChange;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;
use PHPUnit\Framework\TestCase;

class EmailChangeSecurityTest extends TestCase
{
    private array $previousGlobals;

    protected function setUp(): void
    {
        $this->previousGlobals = array();
        foreach (array('gCurrentUser', 'gDb', 'gL10n', 'gLogger', 'gSettingsManager', 'gCurrentOrganization', 'gChangeNotification', 'gProfileFields') as $name) {
            $this->previousGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $GLOBALS['gDb'] = null;
        $GLOBALS['gChangeNotification'] = null;
        $GLOBALS['gL10n'] = new class {
            public function get($id, $params = array()) { return $id . implode(' ', $params); }
        };
        $GLOBALS['gLogger'] = new \Psr\Log\NullLogger();
        $GLOBALS['gSettingsManager'] = new class {
            public function has($name) { return false; }
        };
    }

    protected function tearDown(): void
    {
        foreach ($this->previousGlobals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
    }

    private function user(int $id, string $login, bool $admin = false): User
    {
        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
            ->onlyMethods(array('getValue', 'isAdministrator', 'hasRightEditProfile'))->getMock();
        $user->method('getValue')->willReturnCallback(static function ($field) use ($id, $login) {
            return $field === 'usr_id' ? $id : ($field === 'usr_login_name' ? $login : '');
        });
        $user->method('isAdministrator')->willReturn($admin);
        $user->method('hasRightEditProfile')->willReturn(true);
        $fields = $this->createMock(ProfileFields::class);
        $fields->method('isEditable')->willReturn(true);
        $fields->method('getValue')->willReturn('old@example.org');
        $fields->method('getProperty')->willReturn('EMAIL');
        $fields->method('setValue')->willReturn(true);
        $property = new \ReflectionProperty(User::class, 'mProfileFieldsData');
        $property->setValue($user, $fields);
        (new \ReflectionProperty(User::class, 'newRecord'))->setValue($user, false);
        return $user;
    }

    public static function permissions(): array
    {
        return array(
            'owner' => array(42, false, 'admin', true),
            'administrator' => array(7, true, 'member', true),
            'delegated editor of administrator' => array(7, false, 'admin', false),
            'delegated editor of member' => array(7, false, 'member', false),
            'contact without login' => array(7, false, '', true),
            'guest' => array(0, false, 'member', false)
        );
    }

    /** @dataProvider permissions */
    public function testFieldPermissions(int $editorId, bool $admin, string $login, bool $allowed): void
    {
        $editor = $this->user($editorId, 'editor', $admin);
        $target = $this->user(42, $login);
        $this->assertSame($allowed, $editor->allowedEditProfileField($target, 'EMAIL'));
        $this->assertTrue($editor->allowedEditProfileField($target, 'SECOND_EMAIL'));
        $this->assertTrue($editor->allowedEditProfileField($target, 'LAST_NAME'));
    }

    /** @dataProvider permissions */
    public function testDirectSetterPermissions(int $editorId, bool $admin, string $login, bool $allowed): void
    {
        $GLOBALS['gCurrentUser'] = $this->user($editorId, 'editor', $admin);
        $target = $this->user(42, $login);
        if (!$allowed) {
            $this->expectException(Exception::class);
        }
        $this->assertTrue($target->setValue('EMAIL', 'new@example.org'));
    }

    public function testUnchangedAddressIsAccepted(): void
    {
        $GLOBALS['gCurrentUser'] = $this->user(7, 'editor');
        $this->assertTrue($this->user(42, 'admin')->setValue('EMAIL', 'old@example.org'));
    }

    public static function notifications(): array
    {
        return array(
            'committed change' => array('EMAIL', 'member', 'new@example.org', true),
            'contact without login' => array('EMAIL', '', 'new@example.org', false),
            'change reverted' => array('EMAIL', 'member', 'old@example.org', false),
            'other email field' => array('SECOND_EMAIL', 'member', 'new@example.org', false)
        );
    }

    /** @dataProvider notifications */
    public function testNotificationUsesOldAddress(string $field, string $login, string $newEmail, bool $sent): void
    {
        $GLOBALS['gProfileFields'] = new class($field) {
            public function __construct(private string $field) {}
            public function getPropertyById($id, $property, $format = '') { return $this->field; }
            public function formatValue($name, $value, $format = '') { return $value; }
        };
        $GLOBALS['gCurrentOrganization'] = new class {
            public function getValue($field) { return $field === 'org_email_administrator' ? 'admin@example.org' : 'https://example.org'; }
        };
        $mail = $this->createMock(Email::class);
        $mail->expects($sent ? $this->once() : $this->never())->method('addRecipient')->with('old@example.org')->willReturn(true);
        $mail->expects($sent ? $this->once() : $this->never())->method('sendEmail')->willReturn(true);
        if ($sent) {
            $mail->expects($this->once())->method('setText')->with($this->callback(static function ($text) {
                return str_contains($text, 'old@example.org') && str_contains($text, 'new@example.org');
            }));
        }
        $queue = new class($mail, $login) extends ChangeNotification {
            public function __construct(private Email $mail, private string $login) {}
            protected function createEmailChangeNotification(): Email { return $this->mail; }
            protected function identity(array $change): array { return array('usr_login_name' => $this->login); }
            protected function dispatchCumulatedChanges(int $userID = 0): void {}
        };
        foreach (array(array('old@example.org', 'intermediate@example.org'), array('intermediate@example.org', $newEmail)) as [$old, $new]) {
            $queue->onUserDataChanged(new EntityChangeSet(
                'user_data', 'UserData', 'user_data', 'usd', 'usd_id', 1, null,
                EntityChangeSet::OPERATION_UPDATE, 'test-operation',
                array('usd_value' => new EntityFieldChange('usd_value', $old, $new, 'varchar', EntityFieldChange::KIND_BUSINESS)),
                array('usd_usr_id' => 42, 'usd_usf_id' => 5)
            ));
        }
        $queue->sendNotifications();
        $queue->sendNotifications(); // A committed change is notified only once.
    }
}
