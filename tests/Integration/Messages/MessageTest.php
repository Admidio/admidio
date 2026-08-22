<?php
/**
 * Message Tests
 *
 * Tests private messages and emails. A message is spread over four tables: adm_messages holds the
 * envelope, adm_messages_content one row per part of the conversation, adm_messages_recipients one
 * row per addressed user or role, and adm_messages_attachments the files. None of them carries an
 * organization, so the tests use the installed organization throughout.
 *
 * Attachments are not covered here, they would touch the file system.
 */

namespace Admidio\Tests\Integration\Messages;

use Admidio\Messages\Entity\Message;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class MessageTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation. It is the only one with preferences, and
     * Message::save() and Message::delete() read mail_save_attachments.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create a sender and a receiver.
     *
     * @return array<int,User> Sender and receiver, loaded in the installed organization
     */
    private function twoUsers(string $prefix): array
    {
        $fixture = $this->getFixture();
        $sender = $fixture->createAndSaveUser($prefix . 'sender', $prefix . 's@example.local');
        $receiver = $fixture->createAndSaveUser($prefix . 'receiver', $prefix . 'r@example.local');

        return array(
            $this->loadUserInOrganization($sender['usr_id'], self::ORG_ID),
            $this->loadUserInOrganization($receiver['usr_id'], self::ORG_ID)
        );
    }

    /**
     * Send a private message and return its id.
     *
     * MessageService marks a new private message unread and stamps it with the current time. That
     * is also what makes the message row dirty, which Message::save() requires before it stores
     * the content and the recipients.
     */
    private function sendPrivateMessage(User $sender, int $receiverId, string $subject, string $body): int
    {
        return $this->withCurrentUser($sender, self::ORG_ID, true, function () use ($receiverId, $subject, $body) {
            $message = new Message($this->getDatabase());
            $message->setValue('msg_type', Message::MESSAGE_TYPE_PM);
            $message->setValue('msg_subject', $subject);
            $message->setValue('msg_usr_id_sender', $GLOBALS['gCurrentUserId']);
            $message->addUser($receiverId);
            $message->addContent($body);
            $message->setValue('msg_read', 1);
            $message->setValue('msg_timestamp', DATETIME_NOW);
            $message->save();

            return (int) $message->getValue('msg_id');
        });
    }

    /**
     * Send an email to a role and return its id.
     */
    private function sendEmailToRole(User $sender, int $roleId, string $subject, string $body): int
    {
        return $this->withCurrentUser($sender, self::ORG_ID, true, function () use ($roleId, $subject, $body) {
            $message = new Message($this->getDatabase());
            $message->setValue('msg_type', Message::MESSAGE_TYPE_EMAIL);
            $message->setValue('msg_subject', $subject);
            $message->setValue('msg_usr_id_sender', $GLOBALS['gCurrentUserId']);
            $message->addRole($roleId, 0, 'Role');
            $message->addContent($body);
            $message->save();

            return (int) $message->getValue('msg_id');
        });
    }

    /**
     * Ask something about a stored message as the given user.
     */
    private function askAs(User $user, int $msgId, callable $question)
    {
        return $this->withCurrentUser($user, self::ORG_ID, true, function () use ($msgId, $question) {
            return $question(new Message($this->getDatabase(), $msgId));
        });
    }

    /**
     * Test that the envelope of a private message is stored
     *
     * @testdox A private message stores its type, subject and sender
     */
    public function testPrivateMessageStoresTypeSubjectAndSender(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm1');

        $msgId = $this->sendPrivateMessage($sender, (int) $receiver->getValue('usr_id'), 'Lunch?', 'Are you coming?');

        $sql = 'SELECT msg_type, msg_subject, msg_usr_id_sender, msg_uuid, msg_timestamp
                  FROM ' . TBL_MESSAGES . ' WHERE msg_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$msgId])->fetch();

        $this->assertEquals(Message::MESSAGE_TYPE_PM, $row['msg_type']);
        $this->assertEquals('Lunch?', $row['msg_subject']);
        $this->assertEquals((int) $sender->getValue('usr_id'), (int) $row['msg_usr_id_sender']);

        // every message gets a uuid and a timestamp of its own
        $this->assertNotEmpty($row['msg_uuid']);
        $this->assertNotEmpty($row['msg_timestamp']);
    }

    /**
     * Test that the body lives in its own table
     *
     * @testdox The body of a message is stored as a row in the content table
     */
    public function testBodyIsStoredInTheContentTable(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm2');

        $msgId = $this->sendPrivateMessage($sender, (int) $receiver->getValue('usr_id'), 'Subject', 'The body');

        $sql = 'SELECT msc_msg_id, msc_usr_id, msc_message FROM ' . TBL_MESSAGES_CONTENT . ' WHERE msc_msg_id = ?';
        $rows = $this->getDatabase()->queryPrepared($sql, [$msgId])->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('The body', $rows[0]['msc_message']);

        // the author of the part is the user that saved it, not necessarily the sender of the message
        $this->assertEquals((int) $sender->getValue('usr_id'), (int) $rows[0]['msc_usr_id']);

        $content = $this->askAs($sender, $msgId, fn (Message $m) => $m->getContent());
        $this->assertEquals('The body', $content);
    }

    /**
     * Test that a recipient becomes a row of its own
     *
     * @testdox A recipient of a message is stored as a row in the recipients table
     */
    public function testRecipientIsStoredAsItsOwnRow(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm3');
        $receiverId = (int) $receiver->getValue('usr_id');

        $msgId = $this->sendPrivateMessage($sender, $receiverId, 'Subject', 'Body');

        $sql = 'SELECT msr_usr_id, msr_rol_id, msr_role_mode FROM ' . TBL_MESSAGES_RECIPIENTS . ' WHERE msr_msg_id = ?';
        $rows = $this->getDatabase()->queryPrepared($sql, [$msgId])->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals($receiverId, (int) $rows[0]['msr_usr_id']);

        // a user recipient has no role and therefore no role mode
        $this->assertNull($rows[0]['msr_rol_id']);

        $recipients = $this->askAs($sender, $msgId, fn (Message $m) => $m->readRecipientsData());
        $this->assertCount(1, $recipients);
        $this->assertEquals('user', $recipients[0]['type']);
        $this->assertEquals($receiverId, $recipients[0]['id']);
    }

    /**
     * Test that a recipient is not stored twice
     *
     * @testdox The same recipient is only added once to an email
     */
    public function testTheSameRecipientIsOnlyAddedOnce(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm4');
        $receiverId = (int) $receiver->getValue('usr_id');

        $msgId = $this->withCurrentUser($sender, self::ORG_ID, true, function () use ($receiverId) {
            $message = new Message($this->getDatabase());
            $message->setValue('msg_type', Message::MESSAGE_TYPE_EMAIL);
            $message->setValue('msg_subject', 'Subject');
            $message->setValue('msg_usr_id_sender', $GLOBALS['gCurrentUserId']);
            $message->addUser($receiverId);
            $message->addUser($receiverId);
            $message->addContent('Body');
            $message->save();

            return (int) $message->getValue('msg_id');
        });

        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_MESSAGES_RECIPIENTS . ' WHERE msr_msg_id = ?';
        $this->assertEquals(1, (int) $this->getDatabase()->queryPrepared($sql, [$msgId])->fetch()['count']);
    }

    /**
     * Test that an email can be addressed to a role
     *
     * @testdox An email can be addressed to a role instead of a user
     */
    public function testEmailCanBeAddressedToARole(): void
    {
        $fixture = $this->getFixture();
        list($sender, ) = $this->twoUsers('mail1');
        $role = $fixture->createAndSaveRole('Message Role', self::ORG_ID);

        $msgId = $this->sendEmailToRole($sender, $role['rol_id'], 'To all', 'Body');

        $sql = 'SELECT msr_usr_id, msr_rol_id, msr_role_mode FROM ' . TBL_MESSAGES_RECIPIENTS . ' WHERE msr_msg_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$msgId])->fetch();

        $this->assertEquals($role['rol_id'], (int) $row['msr_rol_id']);
        $this->assertNull($row['msr_usr_id']);

        // mode 0 addresses the active members of the role
        $this->assertEquals(0, (int) $row['msr_role_mode']);

        $recipients = $this->askAs($sender, $msgId, fn (Message $m) => $m->readRecipientsData());
        $this->assertEquals('role', $recipients[0]['type']);
        $this->assertEquals('Message Role', $recipients[0]['name']);
    }

    /**
     * Test that the partner of a conversation is the other user
     *
     * @testdox The conversation partner of a private message is the other user
     */
    public function testConversationPartnerIsTheOtherUser(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm5');
        $receiverId = (int) $receiver->getValue('usr_id');

        $msgId = $this->sendPrivateMessage($sender, $receiverId, 'Subject', 'Body');

        // seen from either side the partner is the recipient stored with the message
        $this->assertEquals($receiverId, $this->askAs($sender, $msgId, fn (Message $m) => $m->getConversationPartner()));
        $this->assertEquals($receiverId, $this->askAs($receiver, $msgId, fn (Message $m) => $m->getConversationPartner()));
    }

    /**
     * Test that an email has no conversation partner
     *
     * @testdox An email has no conversation partner
     */
    public function testEmailHasNoConversationPartner(): void
    {
        $fixture = $this->getFixture();
        list($sender, ) = $this->twoUsers('mail2');
        $role = $fixture->createAndSaveRole('Message Role', self::ORG_ID);

        $msgId = $this->sendEmailToRole($sender, $role['rol_id'], 'To all', 'Body');

        $this->assertFalse($this->askAs($sender, $msgId, fn (Message $m) => $m->getConversationPartner()));
    }

    /**
     * Test that a reply extends the conversation
     *
     * @testdox A reply adds a second part to the conversation
     */
    public function testReplyAddsASecondPartToTheConversation(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm6');
        $msgId = $this->sendPrivateMessage($sender, (int) $receiver->getValue('usr_id'), 'Subject', 'First');

        $this->withCurrentUser($receiver, self::ORG_ID, true, function () use ($msgId) {
            $message = new Message($this->getDatabase(), $msgId);
            $message->addContent('Second');
            // the reply reaches the database although no column of adm_messages changed
            $message->save();
        });

        $this->assertEquals(2, $this->askAs($sender, $msgId, fn (Message $m) => $m->countMessageParts()));

        $sql = 'SELECT msc_usr_id, msc_message FROM ' . TBL_MESSAGES_CONTENT . ' WHERE msc_msg_id = ? ORDER BY msc_id';
        $rows = $this->getDatabase()->queryPrepared($sql, [$msgId])->fetchAll();
        $this->assertEquals(['First', 'Second'], array_column($rows, 'msc_message'));

        // each part remembers who wrote it
        $this->assertEquals((int) $sender->getValue('usr_id'), (int) $rows[0]['msc_usr_id']);
        $this->assertEquals((int) $receiver->getValue('usr_id'), (int) $rows[1]['msc_usr_id']);

        // the whole conversation is returned newest first
        $conversation = $this->askAs($sender, $msgId, fn (Message $m) => $m->getConversation($msgId)->fetchAll());
        $this->assertEquals(['Second', 'First'], array_column($conversation, 'msc_message'));
    }

    /**
     * Test that a private message survives the first delete
     *
     * @testdox Deleting a private message marks it before the second delete removes it
     */
    public function testDeletingAPrivateMessageMarksItBeforeRemovingIt(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm7');
        $msgId = $this->sendPrivateMessage($sender, (int) $receiver->getValue('usr_id'), 'Subject', 'Body');

        // the first party deletes it: the message is only flagged, so the other party still has it
        $this->askAs($sender, $msgId, fn (Message $m) => $m->delete());

        $sql = 'SELECT msg_read FROM ' . TBL_MESSAGES . ' WHERE msg_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$msgId])->fetch();
        $this->assertNotFalse($row);
        $this->assertEquals(2, (int) $row['msg_read']);

        // the second party deletes it as well and now it is gone
        $this->askAs($receiver, $msgId, fn (Message $m) => $m->delete());

        $this->assertFalse($this->getDatabase()->queryPrepared($sql, [$msgId])->fetch());
    }

    /**
     * Test that deleting an email cleans up everything
     *
     * @testdox Deleting an email removes its content and its recipients
     */
    public function testDeletingAnEmailRemovesContentAndRecipients(): void
    {
        $fixture = $this->getFixture();
        list($sender, ) = $this->twoUsers('mail3');
        $role = $fixture->createAndSaveRole('Message Role', self::ORG_ID);
        $msgId = $this->sendEmailToRole($sender, $role['rol_id'], 'To all', 'Body');

        // an email is not shared with a second party, so one delete is enough
        $this->askAs($sender, $msgId, fn (Message $m) => $m->delete());

        $db = $this->getDatabase();
        $this->assertFalse($db->queryPrepared('SELECT msg_id FROM ' . TBL_MESSAGES . ' WHERE msg_id = ?', [$msgId])->fetch());
        $this->assertEquals(0, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_MESSAGES_CONTENT . ' WHERE msc_msg_id = ?', [$msgId])->fetchColumn());
        $this->assertEquals(0, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_MESSAGES_RECIPIENTS . ' WHERE msr_msg_id = ?', [$msgId])->fetchColumn());
    }

    /**
     * Test that a message is unread only for the receiver
     *
     * @testdox A private message is unread for the receiver but not for the sender
     */
    public function testPrivateMessageIsUnreadOnlyForTheReceiver(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm8');
        $msgId = $this->sendPrivateMessage($sender, (int) $receiver->getValue('usr_id'), 'Subject', 'Body');

        $this->assertTrue($this->askAs($receiver, $msgId, fn (Message $m) => $m->isUnread()));
        $this->assertFalse($this->askAs($sender, $msgId, fn (Message $m) => $m->isUnread()));

        // once the flag is cleared the message counts as read for everybody
        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_MESSAGES . ' SET msg_read = ? WHERE msg_id = ?', [0, $msgId]);
        $this->assertFalse($this->askAs($receiver, $msgId, fn (Message $m) => $m->isUnread()));
    }

    /**
     * Test that unread messages are counted per user
     *
     * @testdox Unread messages are counted for the user they are addressed to
     */
    public function testUnreadMessagesAreCountedPerUser(): void
    {
        list($sender, $receiver) = $this->twoUsers('pm9');
        $receiverId = (int) $receiver->getValue('usr_id');
        $senderId = (int) $sender->getValue('usr_id');

        $this->sendPrivateMessage($sender, $receiverId, 'One', 'Body');
        $this->sendPrivateMessage($sender, $receiverId, 'Two', 'Body');

        $counts = $this->withCurrentUser($receiver, self::ORG_ID, true, function () use ($receiverId, $senderId) {
            $message = new Message($this->getDatabase());

            return array($message->countUnreadMessageRecords($receiverId), $message->countUnreadMessageRecords($senderId));
        });

        $this->assertEquals(2, $counts[0]);

        // the sender is not a recipient of his own message
        $this->assertEquals(0, $counts[1]);
    }
}
