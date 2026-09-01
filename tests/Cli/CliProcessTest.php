<?php
/**
 * Command Line Process Tests
 *
 * Tests the command line utility as the operating system runs it: ./admidio started as its own
 * process, with its own bootstrap, its own database connection and its own exit code. The other
 * CLI tests exercise the classes behind it, these ones exercise the entry point.
 *
 * The process is pointed at the test database with --config. Database-only workflows may write
 * through the subprocess when they create and clean up their own data; the assertions then prove
 * that a commit is visible to a second, independent Admidio process. File-writing commands are kept
 * out of this class because the utility uses the adm_my_files of the checkout for those files.
 */

namespace Admidio\Tests\Cli;

use Admidio\Tests\Support\CliSubprocess;
use Admidio\Tests\Support\DatabaseTestCase;

class CliProcessTest extends DatabaseTestCase
{
    use CliSubprocess;

    /**
     * Test that the program starts
     *
     * @testdox The utility starts as a process and describes itself
     */
    public function testTheUtilityStartsAsAProcessAndDescribesItself(): void
    {
        $process = $this->runCli(array('help'));

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Admidio command-line administration utility', $process->getOutput());
        $this->assertStringContainsString('Global options', $process->getOutput());
        $this->assertStringContainsString('--config=FILE', $process->getOutput());
    }

    /**
     * Test that the configuration option decides which installation is addressed
     *
     * @testdox The configuration file decides which installation the process works on
     */
    public function testTheConfigurationFileDecidesWhichInstallationTheProcessWorksOn(): void
    {
        // the administrator of the installation, read through the connection of the test
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $administratorId = (int) $this->getDatabase()->queryPrepared($sql, array('admin'))->fetchColumn();

        $this->assertGreaterThan(0, $administratorId);

        $process = $this->runCli(array('--as=admin', 'user:show', 'admin', '--format=json'));

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $user = $this->cliJson($process);

        // the same record, so the process really worked on the database of this test run
        $this->assertSame($administratorId, (int) $user['id']);
        $this->assertSame('admin', $user['login']);
        $this->assertSame('admin@test.local', $user['profile']['EMAIL']);
    }

     /**
     * Test a complete mutating workflow across independent CLI processes
     *
     * @testdox A user created by one real CLI process is persisted, can be read by another and can be deleted
     */
    public function testUserLifecyclePersistsAcrossIndependentProcesses(): void
    {
        $login = 'cli-e2e-' . bin2hex(random_bytes(5));
        $email = $login . '@example.local';
        $created = false;

        try {
            $create = $this->runCli(array(
                '--as=admin',
                'user:add',
                '--login=' . $login,
                '--field=FIRST_NAME=CLI',
                '--field=LAST_NAME=Regression',
                '--field=EMAIL=' . $email
            ));

            $this->assertSame(0, $create->getExitCode(), $create->getErrorOutput());
            $created = true;

            // A second process has a different PDO connection. Seeing the record here proves the
            // production CLI command committed an actual Admidio database write.
            $show = $this->runCli(array('--as=admin', 'user:show', $login, '--format=json'));
            $this->assertSame(0, $show->getExitCode(), $show->getErrorOutput());

            $user = $this->cliJson($show);
            $this->assertSame($login, $user['login']);
            $this->assertSame('CLI', $user['profile']['FIRST_NAME']);
            $this->assertSame('Regression', $user['profile']['LAST_NAME']);
            $this->assertSame($email, $user['profile']['EMAIL']);
        } finally {
            if ($created) {
                $delete = $this->runCli(array('--as=admin', 'user:delete', $login, '--yes'));
                $this->assertSame(0, $delete->getExitCode(), $delete->getErrorOutput());
            }
        }

        $missing = $this->runCli(array('--as=admin', 'user:show', $login, '--format=json'));
        $this->assertSame(2, $missing->getExitCode());
        $this->assertStringContainsString('was not found', $missing->getErrorOutput());
    }

    /**
     * @testdox Group membership mutations persist across independent CLI processes
     */
    public function testGroupMembershipLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $groupUuid = '';

        try {
            $category = $this->adminCliJson(array('category:add', 'ROL', 'CLI roles ' . $suffix));
            $categoryUuid = (string)$category['uuid'];

            $group = $this->adminCliJson(array(
                'group:add',
                'CLI group ' . $suffix,
                '--category=' . $categoryUuid
            ));
            $groupUuid = (string)$group['uuid'];

            $this->adminCliOk(array('group:adduser', $groupUuid, 'admin'));

            $members = $this->adminCliJson(array('group:members', $groupUuid));
            $membership = null;
            foreach ($members as $member) {
                if (($member['login'] ?? '') === 'admin') {
                    $membership = $member;
                    break;
                }
            }

            $this->assertIsArray($membership);
            $this->assertNotSame('', (string)$membership['uuid']);

            $this->adminCliOk(array('group:deletemembership', (string)$membership['uuid'], '--yes'));

            $members = $this->adminCliJson(array('group:members', $groupUuid));
            $this->assertFalse(
                in_array('admin', array_column($members, 'login'), true),
                'The membership deleted by one CLI process must not be visible to the next process.'
            );
        } finally {
            if ($groupUuid !== '') {
                $this->adminCliCleanup(array('group:delete', $groupUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Menu create update read and delete is a real subprocess workflow
     */
    public function testMenuLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $menuUuid = '';

        try {
            $created = $this->adminCliJson(array(
                'menu:add',
                'CLI menu ' . $suffix,
                '--description=created-' . $suffix,
                '--url=https://example.test/' . $suffix
            ));
            $menuUuid = (string)$created['uuid'];

            $shown = $this->adminCliJson(array('menu:show', $menuUuid));
            $this->assertSame('CLI menu ' . $suffix, $shown['name']);
            $this->assertSame('created-' . $suffix, $shown['description']);

            $this->adminCliOk(array(
                'menu:update',
                $menuUuid,
                '--name=CLI menu updated ' . $suffix,
                '--description=updated-' . $suffix
            ));

            $shown = $this->adminCliJson(array('menu:show', $menuUuid));
            $this->assertSame('CLI menu updated ' . $suffix, $shown['name']);
            $this->assertSame('updated-' . $suffix, $shown['description']);

            $this->adminCliOk(array('menu:delete', $menuUuid, '--yes'));
            $menuUuid = '';

            $rows = $this->adminCliJson(array('menu:list'));
            $this->assertFalse(
                in_array('CLI menu updated ' . $suffix, array_column($rows, 'name'), true)
            );
        } finally {
            if ($menuUuid !== '') {
                $this->adminCliCleanup(array('menu:delete', $menuUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Announcement mutations are committed by real CLI processes
     */
    public function testAnnouncementLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $announcementUuid = '';

        try {
            $category = $this->adminCliJson(array(
                'category:add',
                'ANN',
                'CLI announcements ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $created = $this->adminCliJson(array(
                'announcement:add',
                '--headline=CLI announcement ' . $suffix,
                '--category=' . $categoryUuid,
                '--description=created-' . $suffix
            ));
            $announcementUuid = (string)$created['uuid'];

            $shown = $this->adminCliJson(array('announcement:show', $announcementUuid));
            $this->assertSame('CLI announcement ' . $suffix, $shown['headline']);
            $this->assertSame('created-' . $suffix, $shown['description']);

            $this->adminCliOk(array(
                'announcement:update',
                $announcementUuid,
                '--headline=CLI announcement updated ' . $suffix,
                '--description=updated-' . $suffix
            ));

            $shown = $this->adminCliJson(array('announcement:show', $announcementUuid));
            $this->assertSame('CLI announcement updated ' . $suffix, $shown['headline']);
            $this->assertSame('updated-' . $suffix, $shown['description']);

            $this->adminCliOk(array('announcement:delete', $announcementUuid, '--yes'));
            $announcementUuid = '';

            $rows = $this->adminCliJson(array(
                'announcement:list',
                '--category=' . $categoryUuid,
                '--search=CLI announcement updated ' . $suffix
            ));
            $this->assertSame(array(), $rows);
        } finally {
            if ($announcementUuid !== '') {
                $this->adminCliCleanup(array('announcement:delete', $announcementUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Web-link mutations are committed by real CLI processes
     */
    public function testWeblinkLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $linkUuid = '';

        try {
            $category = $this->adminCliJson(array(
                'category:add',
                'LNK',
                'CLI links ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $created = $this->adminCliJson(array(
                'link:add',
                '--name=CLI link ' . $suffix,
                '--url=https://example.test/' . $suffix,
                '--category=' . $categoryUuid,
                '--description=created-' . $suffix
            ));
            $linkUuid = (string)$created['uuid'];

            $shown = $this->adminCliJson(array('link:show', $linkUuid));
            $this->assertSame('CLI link ' . $suffix, $shown['name']);
            $this->assertSame('https://example.test/' . $suffix, $shown['url']);

            $this->adminCliOk(array(
                'link:update',
                $linkUuid,
                '--name=CLI link updated ' . $suffix,
                '--url=https://example.test/updated-' . $suffix,
                '--description=updated-' . $suffix
            ));

            $shown = $this->adminCliJson(array('link:show', $linkUuid));
            $this->assertSame('CLI link updated ' . $suffix, $shown['name']);
            $this->assertSame('https://example.test/updated-' . $suffix, $shown['url']);

            $this->adminCliOk(array('link:delete', $linkUuid, '--yes'));
            $linkUuid = '';

            $rows = $this->adminCliJson(array('link:list', '--category=' . $categoryUuid));
            $this->assertFalse(
                in_array('CLI link updated ' . $suffix, array_column($rows, 'name'), true)
            );
        } finally {
            if ($linkUuid !== '') {
                $this->adminCliCleanup(array('link:delete', $linkUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Inventory create update checkout return retire reinstate and delete persist across CLI processes
     */
    public function testInventoryLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $itemUuid = '';

        try {
            $category = $this->adminCliJson(array(
                'category:add',
                'IVT',
                'CLI inventory ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $statusOptions = $this->adminCliJson(array('inventory:options', 'STATUS'));
            $this->assertNotEmpty($statusOptions, 'The production installer must provide inventory status options.');
            $activeStatus = (string)($statusOptions[0]['ifo_id'] ?? $statusOptions[0]['id'] ?? '');
            $this->assertNotSame('', $activeStatus);

            $itemName = 'CLI inventory item ' . $suffix;
            $this->adminCliOk(array(
                'inventory:add',
                '--field=CATEGORY=' . $categoryUuid,
                '--field=ITEMNAME=' . $itemName,
                '--field=STATUS=' . $activeStatus
            ));

            $rows = $this->adminCliJson(array(
                'inventory:list',
                '--status=all',
                '--search=' . $itemName
            ));
            $this->assertCount(1, $rows);
            $itemUuid = (string)$rows[0]['uuid'];

            $updatedName = 'CLI inventory updated ' . $suffix;
            $this->adminCliOk(array(
                'inventory:update',
                $itemUuid,
                '--field=ITEMNAME=' . $updatedName
            ));
            $shown = $this->adminCliJson(array('inventory:show', $itemUuid));
            $this->assertSame($updatedName, $shown['fields']['ITEMNAME']);

            $this->adminCliOk(array('inventory:checkout', $itemUuid, '--user=admin'));
            $shown = $this->adminCliJson(array('inventory:show', $itemUuid));
            $this->assertTrue((bool)$shown['borrowed']);

            $this->adminCliOk(array('inventory:return', $itemUuid));
            $shown = $this->adminCliJson(array('inventory:show', $itemUuid));
            $this->assertFalse((bool)$shown['borrowed']);

            $this->adminCliOk(array('inventory:retire', $itemUuid));
            $shown = $this->adminCliJson(array('inventory:show', $itemUuid));
            $this->assertTrue((bool)$shown['retired']);

            $this->adminCliOk(array('inventory:reinstate', $itemUuid));
            $shown = $this->adminCliJson(array('inventory:show', $itemUuid));
            $this->assertFalse((bool)$shown['retired']);

            $this->adminCliOk(array('inventory:delete', $itemUuid, '--yes'));
            $deletedUuid = $itemUuid;
            $itemUuid = '';

            $rows = $this->adminCliJson(array(
                'inventory:list',
                '--status=all',
                '--search=' . $updatedName
            ));
            $this->assertFalse(in_array($deletedUuid, array_column($rows, 'uuid'), true));
        } finally {
            if ($itemUuid !== '') {
                $this->adminCliCleanup(array('inventory:delete', $itemUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Profile-field mutations use the production service through independent CLI processes
     */
    public function testProfileFieldLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $fieldUuid = '';

        try {
            $category = $this->adminCliJson(array(
                'category:add',
                'USF',
                'CLI profile fields ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $fieldName = 'CLI field ' . $suffix;
            $this->adminCliOk(array(
                'profile:field-add',
                '--name=' . $fieldName,
                '--category=' . $categoryUuid,
                '--type=TEXT',
                '--description=created-' . $suffix
            ));

            $rows = $this->adminCliJson(array('profile:fields', '--category=' . $categoryUuid));
            $matching = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['name'] ?? '') === $fieldName
            ));
            $this->assertCount(1, $matching);
            $fieldUuid = (string)$matching[0]['uuid'];

            $this->adminCliOk(array(
                'profile:field-update',
                $fieldUuid,
                '--name=CLI field updated ' . $suffix,
                '--description=updated-' . $suffix
            ));

            $shown = $this->adminCliJson(array('profile:field-show', $fieldUuid));
            $this->assertSame('CLI field updated ' . $suffix, $shown['usf_name']);
            $this->assertSame('updated-' . $suffix, $shown['usf_description']);

            $this->adminCliOk(array('profile:field-delete', $fieldUuid, '--yes'));
            $fieldUuid = '';

            $rows = $this->adminCliJson(array('profile:fields', '--category=' . $categoryUuid));
            $this->assertFalse(
                in_array('CLI field updated ' . $suffix, array_column($rows, 'name'), true)
            );
        } finally {
            if ($fieldUuid !== '') {
                $this->adminCliCleanup(array('profile:field-delete', $fieldUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox OIDC client mutations use OIDCService and persist across independent CLI processes
     */
    public function testOidcLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $clientUuid = '';

        try {
            $created = $this->adminCliJson(array(
                'sso:oidc-add',
                '--name=CLI OIDC ' . $suffix,
                '--client-id=cli-client-' . $suffix,
                '--client-secret=secret-' . $suffix,
                '--redirect-uri=https://example.test/callback/' . $suffix,
                '--scope=profile',
                '--scope=email',
                '--enabled=true'
            ));
            $clientUuid = (string)$created['uuid'];

            $shown = $this->adminCliJson(array('sso:show', $clientUuid, '--type=oidc'));
            $this->assertSame('cli-client-' . $suffix, $shown['client_id']);
            $this->assertSame('CLI OIDC ' . $suffix, $shown['name']);
            $this->assertTrue((bool)$shown['enabled']);

            $updated = $this->adminCliJson(array(
                'sso:oidc-update',
                $clientUuid,
                '--name=CLI OIDC updated ' . $suffix,
                '--enabled=false'
            ));
            $this->assertSame('CLI OIDC updated ' . $suffix, $updated['name']);
            $this->assertFalse((bool)$updated['enabled']);

            $this->adminCliOk(array('sso:oidc-delete', $clientUuid, '--yes'));
            $clientUuid = '';

            $rows = $this->adminCliJson(array('sso:list', '--type=oidc'));
            $this->assertFalse(
                in_array('cli-client-' . $suffix, array_column($rows, 'client_id'), true)
            );
        } finally {
            if ($clientUuid !== '') {
                $this->adminCliCleanup(array('sso:oidc-delete', $clientUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Relation add creates and relation delete removes the production inverse row
     */
    public function testUserRelationLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $groupUuid = '';
        $relationTypeUuid = '';
        $relationUuid = '';
        $user1 = 'cli-rel-a-' . $suffix;
        $user2 = 'cli-rel-b-' . $suffix;
        $usersCreated = false;

        try {
            $category = $this->adminCliJson(array('category:add', 'ROL', 'CLI relation roles ' . $suffix));
            $categoryUuid = (string)$category['uuid'];
            $group = $this->adminCliJson(array(
                'group:add',
                'CLI relation group ' . $suffix,
                '--category=' . $categoryUuid
            ));
            $groupUuid = (string)$group['uuid'];

            foreach (array($user1 => 'A', $user2 => 'B') as $login => $lastName) {
                $this->adminCliOk(array(
                    'user:add',
                    '--login=' . $login,
                    '--field=FIRST_NAME=CLI',
                    '--field=LAST_NAME=Relation-' . $lastName,
                    '--group=' . $groupUuid
                ));
            }
            $usersCreated = true;

            $type = $this->adminCliJson(array(
                'relation-type:add',
                '--name=CLI relation ' . $suffix,
                '--type=symmetrical'
            ));
            $relationTypeUuid = (string)$type['uuid'];

            $relation = $this->adminCliJson(array(
                'relation:add',
                $user1,
                $user2,
                $relationTypeUuid
            ));
            $relationUuid = (string)$relation['uuid'];

            $rows = $this->adminCliJson(array('relation:list', $user1, '--type=' . $relationTypeUuid));
            $this->assertCount(
                2,
                $rows,
                'A symmetrical relation must be persisted in both directions by production code.'
            );
            $this->assertNotSame($rows[0]['user1_uuid'], $rows[1]['user1_uuid']);

            $this->adminCliOk(array('relation:delete', $relationUuid, '--yes'));
            $relationUuid = '';

            $rows = $this->adminCliJson(array('relation:list', $user1, '--type=' . $relationTypeUuid));
            $this->assertSame(
                array(),
                $rows,
                'UserRelation::delete() must remove the native inverse relation as well.'
            );
        } finally {
            if ($relationUuid !== '') {
                $this->adminCliCleanup(array('relation:delete', $relationUuid, '--yes'));
            }
            if ($relationTypeUuid !== '') {
                $this->adminCliCleanup(array('relation-type:delete', $relationTypeUuid, '--yes'));
            }
            if ($usersCreated) {
                $this->adminCliCleanup(array('user:delete', $user1, '--yes'));
                $this->adminCliCleanup(array('user:delete', $user2, '--yes'));
            }
            if ($groupUuid !== '') {
                $this->adminCliCleanup(array('group:delete', $groupUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Private-message deletion follows the production two-participant semantics
     */
    public function testPrivateMessageLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $groupUuid = '';
        $recipient = 'cli-pm-' . $suffix;
        $recipientCreated = false;
        $messageUuid = '';

        try {
            $category = $this->adminCliJson(array('category:add', 'ROL', 'CLI message roles ' . $suffix));
            $categoryUuid = (string)$category['uuid'];
            $group = $this->adminCliJson(array(
                'group:add',
                'CLI message group ' . $suffix,
                '--category=' . $categoryUuid
            ));
            $groupUuid = (string)$group['uuid'];

            $this->adminCliOk(array(
                'user:add',
                '--login=' . $recipient,
                '--field=FIRST_NAME=CLI',
                '--field=LAST_NAME=Recipient',
                '--group=' . $groupUuid
            ));
            $recipientCreated = true;

            $subject = 'CLI PM ' . $suffix;
            $sent = $this->adminCliJson(array(
                'message:send',
                '--type=pm',
                '--user=' . $recipient,
                '--subject=' . $subject,
                '--body=body-' . $suffix
            ));
            $messageUuid = (string)$sent['uuid'];

            $shown = $this->adminCliJson(array('message:show', $messageUuid));
            $this->assertSame('PM', $shown['type']);
            $this->assertSame($subject, $shown['subject']);
            $this->assertSame('body-' . $suffix, $shown['content']);

            $this->adminCliOk(array('message:delete', $messageUuid, '--yes'));

            $receiverShow = $this->userCliJson($recipient, array('message:show', $messageUuid));
            $this->assertSame($messageUuid, $receiverShow['uuid']);
            $this->assertSame($subject, $receiverShow['subject']);

            $this->userCliOk($recipient, array('message:delete', $messageUuid, '--yes'));
            $messageUuid = '';

            $receiverList = $this->userCliJson($recipient, array('message:list', '--type=pm'));
            $this->assertFalse(in_array($subject, array_column($receiverList, 'subject'), true));
        } finally {
            if ($messageUuid !== '') {
                $this->adminCliCleanup(array('message:delete', $messageUuid, '--yes'));
                $this->userCliCleanup($recipient, array('message:delete', $messageUuid, '--yes'));
            }
            if ($recipientCreated) {
                $this->adminCliCleanup(array('user:delete', $recipient, '--yes'));
            }
            if ($groupUuid !== '') {
                $this->adminCliCleanup(array('group:delete', $groupUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox Room mutations are committed by real CLI processes
     */
    public function testRoomLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $roomUuid = '';

        try {
            $created = $this->adminCliJson(array(
                'room:add',
                'CLI room ' . $suffix,
                '--capacity=12',
                '--overhang=3',
                '--description=created-' . $suffix
            ));
            $roomUuid = (string)$created['uuid'];

            $this->assertSame('CLI room ' . $suffix, $created['name']);
            $this->assertSame(12, (int)$created['capacity']);
            $this->assertSame(3, (int)$created['overhang']);

            $shown = $this->adminCliJson(array('room:show', $roomUuid));
            $this->assertSame('created-' . $suffix, $shown['description']);

            $this->adminCliOk(array(
                'room:update',
                $roomUuid,
                '--name=CLI room updated ' . $suffix,
                '--capacity=20',
                '--description=updated-' . $suffix
            ));

            $shown = $this->adminCliJson(array('room:show', $roomUuid));
            $this->assertSame('CLI room updated ' . $suffix, $shown['name']);
            $this->assertSame(20, (int)$shown['capacity']);
            $this->assertSame('updated-' . $suffix, $shown['description']);

            // the room is listed by name, so it can be addressed without its UUID
            $rooms = $this->adminCliJson(array('room:list'));
            $this->assertContains('CLI room updated ' . $suffix, array_column($rooms, 'name'));

            $this->adminCliOk(array('room:delete', $roomUuid, '--yes'));
            $roomUuid = '';

            $rooms = $this->adminCliJson(array('room:list'));
            $this->assertNotContains('CLI room updated ' . $suffix, array_column($rooms, 'name'));
        } finally {
            if ($roomUuid !== '') {
                $this->adminCliCleanup(array('room:delete', $roomUuid, '--yes'));
            }
        }
    }

    /**
     * An option that is not given leaves the stored value alone, so an option that is given has to
     * carry one. The columns behind these options are NOT NULL, or their edit form marks the field
     * required, and an empty value leaves a record that no list and no reference can name any more.
     *
     * @testdox A required name cannot be cleared through the option that sets it
     */
    public function testARequiredNameCannotBeClearedThroughItsOption(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $roomUuid = '';
        $categoryUuid = '';
        $groupCategoryUuid = '';
        $groupUuid = '';
        $announcementUuid = '';
        $menuUuid = '';

        try {
            $room = $this->adminCliJson(array('room:add', 'CLI named room ' . $suffix, '--capacity=4'));
            $roomUuid = (string)$room['uuid'];

            $category = $this->adminCliJson(array(
                'category:add',
                'ANN',
                'CLI named category ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $groupCategory = $this->adminCliJson(array('category:add', 'ROL', 'CLI named roles ' . $suffix));
            $groupCategoryUuid = (string)$groupCategory['uuid'];
            $group = $this->adminCliJson(array(
                'group:add',
                'CLI named group ' . $suffix,
                '--category=' . $groupCategoryUuid
            ));
            $groupUuid = (string)$group['uuid'];

            $announcement = $this->adminCliJson(array(
                'announcement:add',
                '--headline=CLI named announcement ' . $suffix,
                '--category=' . $categoryUuid,
                '--description=body-' . $suffix
            ));
            $announcementUuid = (string)$announcement['uuid'];

            $menu = $this->adminCliJson(array(
                'menu:add',
                'CLI named entry ' . $suffix,
                '--url=/modules/overview.php'
            ));
            $menuUuid = (string)$menu['uuid'];

            foreach (array(
                array('room:update', $roomUuid, '--name='),
                array('category:update', $categoryUuid, '--name='),
                array('group:update', $groupUuid, '--name='),
                array('announcement:update', $announcementUuid, '--headline='),
                array('menu:update', $menuUuid, '--name=')
            ) as $command) {
                $this->adminCliFails($command, 2, 'must not be empty');
            }

            // a value made of nothing but spaces is no name either
            $this->adminCliFails(array('room:update', $roomUuid, '--name=   '), 2, 'must not be empty');

            // none of the refused commands wrote anything
            $this->assertSame(
                'CLI named room ' . $suffix,
                $this->adminCliJson(array('room:show', $roomUuid))['name']
            );
            $this->assertSame(
                'CLI named category ' . $suffix,
                $this->adminCliJson(array('category:show', $categoryUuid))['name']
            );
            $this->assertSame(
                'CLI named announcement ' . $suffix,
                $this->adminCliJson(array('announcement:show', $announcementUuid))['headline']
            );

            // and a name that is really given still replaces the stored one
            $this->adminCliOk(array('room:update', $roomUuid, '--name=CLI renamed room ' . $suffix));
            $this->assertSame(
                'CLI renamed room ' . $suffix,
                $this->adminCliJson(array('room:show', $roomUuid))['name']
            );
        } finally {
            if ($menuUuid !== '') {
                $this->adminCliCleanup(array('menu:delete', $menuUuid, '--yes'));
            }
            if ($announcementUuid !== '') {
                $this->adminCliCleanup(array('announcement:delete', $announcementUuid, '--yes'));
            }
            if ($groupUuid !== '') {
                $this->adminCliCleanup(array('group:delete', $groupUuid, '--yes'));
            }
            if ($groupCategoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $groupCategoryUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
            if ($roomUuid !== '') {
                $this->adminCliCleanup(array('room:delete', $roomUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox A room that an event still uses is not deleted
     */
    public function testARoomThatAnEventUsesIsNotDeleted(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $roomsProcess = $this->runCli(array('--as=admin', 'config:get', 'events_rooms_enabled'));
        $this->assertSame(0, $roomsProcess->getExitCode(), $roomsProcess->getErrorOutput());
        $roomsEnabled = trim($roomsProcess->getOutput());
        $categoryUuid = '';
        $eventUuid = '';
        $roomUuid = '';

        try {
            $this->adminCliOk(array('config:set', 'events_rooms_enabled', '1'));

            $created = $this->adminCliJson(array('room:add', 'CLI booked room ' . $suffix, '--capacity=8'));
            $roomUuid = (string)$created['uuid'];

            $category = $this->adminCliJson(array(
                'category:add',
                'EVT',
                'CLI calendar ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $event = $this->adminCliJson(array(
                'event:add',
                '--headline=CLI room event ' . $suffix,
                '--from=2030-09-01T18:00',
                '--to=2030-09-01T20:00',
                '--calendar=' . $categoryUuid,
                '--room=' . $roomUuid,
                '--participate-self=0'
            ));
            $eventUuid = (string)$event['uuid'];
            $this->assertSame((int)$created['id'], (int)$event['room_id']);

            $this->adminCliFails(array('room:delete', $roomUuid, '--yes'), 5, 'still assigned to at least one event');

            // once the event is gone the room can be deleted
            $this->adminCliOk(array('event:delete', $eventUuid, '--yes'));
            $eventUuid = '';

            $this->adminCliOk(array('room:delete', $roomUuid, '--yes'));
            $roomUuid = '';
        } finally {
            if ($eventUuid !== '') {
                $this->adminCliCleanup(array('event:delete', $eventUuid, '--yes'));
            }
            if ($roomUuid !== '') {
                $this->adminCliCleanup(array('room:delete', $roomUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
            $this->adminCliCleanup(array('config:set', 'events_rooms_enabled', $roomsEnabled));
        }
    }

    /**
     * @testdox Event mutations are committed by real CLI processes
     */
    public function testEventLifecycleAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $eventUuid = '';

        try {
            $category = $this->adminCliJson(array(
                'category:add',
                'EVT',
                'CLI calendar ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            $created = $this->adminCliJson(array(
                'event:add',
                '--headline=CLI event ' . $suffix,
                '--from=2030-07-01T18:00',
                '--to=2030-07-01T20:30',
                '--calendar=' . $categoryUuid,
                '--location=Main hall',
                '--description=created-' . $suffix,
                '--participate-self=0'
            ));
            $eventUuid = (string)$created['uuid'];

            $this->assertSame('2030-07-01 18:00:00', $created['begin']);
            $this->assertSame('2030-07-01 20:30:00', $created['end']);

            $shown = $this->adminCliJson(array('event:show', $eventUuid));
            $this->assertSame('CLI event ' . $suffix, $shown['headline']);
            $this->assertSame('Main hall', $shown['location']);
            $this->assertSame('created-' . $suffix, $shown['description']);

            $this->adminCliOk(array(
                'event:update',
                $eventUuid,
                '--headline=CLI event updated ' . $suffix,
                '--location=Side room'
            ));

            $shown = $this->adminCliJson(array('event:show', $eventUuid));
            $this->assertSame('CLI event updated ' . $suffix, $shown['headline']);
            $this->assertSame('Side room', $shown['location']);

            $rows = $this->adminCliJson(array('event:list', '--calendar=' . $categoryUuid));
            $this->assertSame(array('CLI event updated ' . $suffix), array_column($rows, 'headline'));

            // the iCalendar export answers on stdout, so the process writes no file of its own
            $export = $this->runCli(array('--as=admin', 'event:export', $eventUuid));
            $this->assertSame(0, $export->getExitCode(), $export->getErrorOutput());
            $this->assertStringContainsString('BEGIN:VCALENDAR', $export->getOutput());
            $this->assertStringContainsString('SUMMARY:CLI event updated ' . $suffix, $export->getOutput());

            $this->adminCliOk(array('event:delete', $eventUuid, '--yes'));
            $eventUuid = '';

            $rows = $this->adminCliJson(array('event:list', '--calendar=' . $categoryUuid));
            $this->assertSame(array(), $rows);
        } finally {
            if ($eventUuid !== '') {
                $this->adminCliCleanup(array('event:delete', $eventUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * @testdox A recurring event generates its occurrences, and the series is edited and removed as a whole
     */
    public function testRecurringEventSeriesAcrossIndependentProcesses(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $categoryUuid = '';
        $masterUuid = '';

        try {
            $category = $this->adminCliJson(array(
                'category:add',
                'EVT',
                'CLI calendar ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid()
            ));
            $categoryUuid = (string)$category['uuid'];

            // 2030-08-05 is a Monday, so the master itself is the first occurrence of the series
            $master = $this->adminCliJson(array(
                'event:add',
                '--headline=CLI series ' . $suffix,
                '--from=2030-08-05T18:00',
                '--to=2030-08-05T19:00',
                '--calendar=' . $categoryUuid,
                '--repeat=weekly',
                '--weekday=MO',
                '--ends=count',
                '--count=4',
                '--participate-self=0'
            ));
            $masterUuid = (string)$master['uuid'];

            $this->assertSame('master', $master['recurrence_status']);
            $this->assertGreaterThan(0, (int)$master['recurrence_id']);

            $rows = $this->adminCliJson(array('event:list', '--calendar=' . $categoryUuid));
            $this->assertCount(4, $rows);
            $this->assertSame(
                array('master', 'generated', 'generated', 'generated'),
                array_column($rows, 'recurrence_status')
            );
            $this->assertSame(
                array('2030-08-05 18:00:00', '2030-08-12 18:00:00', '2030-08-19 18:00:00', '2030-08-26 18:00:00'),
                array_column($rows, 'begin')
            );

            // editing the series reaches every occurrence
            $this->adminCliOk(array(
                'event:update',
                $masterUuid,
                '--headline=CLI series renamed ' . $suffix,
                '--recurrence-scope=series'
            ));

            $rows = $this->adminCliJson(array('event:list', '--calendar=' . $categoryUuid));
            $this->assertSame(
                array_fill(0, 4, 'CLI series renamed ' . $suffix),
                array_column($rows, 'headline')
            );

            $this->adminCliOk(array('event:delete', $masterUuid, '--recurrence-scope=series', '--yes'));
            $masterUuid = '';

            $rows = $this->adminCliJson(array('event:list', '--calendar=' . $categoryUuid));
            $this->assertSame(array(), $rows);
        } finally {
            if ($masterUuid !== '') {
                $this->adminCliCleanup(array('event:delete', $masterUuid, '--recurrence-scope=series', '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
        }
    }

    /**
     * The participant limit is the reason event:participate exists next to group:adduser. It has to
     * be answered from the state before the membership is written: a membership saved first makes
     * the user a participant of the event, and Event::possibleToParticipate() then answers for
     * somebody who is already signed up and never refuses anybody.
     *
     * @testdox The participant limit of an event is enforced by the CLI
     */
    public function testEventParticipationRespectsTheParticipantLimit(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $groupCategoryUuid = '';
        $groupUuid = '';
        $categoryUuid = '';
        $eventUuid = '';
        $guest = 'cli-evt-' . $suffix;
        $guestCreated = false;

        try {
            $groupCategory = $this->adminCliJson(array('category:add', 'ROL', 'CLI event roles ' . $suffix));
            $groupCategoryUuid = (string)$groupCategory['uuid'];
            $group = $this->adminCliJson(array(
                'group:add',
                'CLI event group ' . $suffix,
                '--category=' . $groupCategoryUuid
            ));
            $groupUuid = (string)$group['uuid'];

            $this->adminCliOk(array(
                'user:add',
                '--login=' . $guest,
                '--field=FIRST_NAME=CLI',
                '--field=LAST_NAME=Guest',
                '--group=' . $groupUuid
            ));
            $guestCreated = true;
            $this->adminCliOk(array('group:adduser', $groupUuid, 'admin'));

            // the roles that may participate have to be allowed to see the calendar of the event
            $category = $this->adminCliJson(array(
                'category:add',
                'EVT',
                'CLI calendar ' . $suffix,
                '--view-role=' . $this->administratorRoleUuid(),
                '--view-role=' . $groupUuid
            ));
            $categoryUuid = (string)$category['uuid'];

            // nobody is made a leader of the participation role, so the limit applies to everybody
            $event = $this->adminCliJson(array(
                'event:add',
                '--headline=CLI limited event ' . $suffix,
                '--from=2030-10-01T18:00',
                '--to=2030-10-01T20:00',
                '--calendar=' . $categoryUuid,
                '--participation=1',
                '--participation-role=' . $groupUuid,
                '--participate-self=0',
                '--allow-guests=1',
                '--allow-comments=1',
                '--max-members=1'
            ));
            $eventUuid = (string)$event['uuid'];
            $this->assertGreaterThan(0, (int)$event['participation_role_id']);

            // the first participant fits into the event
            $this->adminCliOk(array('event:participate', $eventUuid, 'admin', '--comment=first-' . $suffix));

            $participants = $this->adminCliJson(array('event:participants', $eventUuid));
            $this->assertSame(array('first-' . $suffix), array_column($participants, 'comment'));

            // the second one does not, and nothing of the refused sign-up is written
            $this->adminCliFails(
                array('--as=' . $guest, 'event:participate', $eventUuid, '--comment=second-' . $suffix),
                5,
                'right to sign up'
            );

            $participants = $this->adminCliJson(array('event:participants', $eventUuid));
            $this->assertCount(1, $participants);
            $this->assertSame(array('first-' . $suffix), array_column($participants, 'comment'));

            // a guest of the first participant fills the event just as a second participant does
            $this->adminCliFails(
                array('event:participate', $eventUuid, 'admin', '--guests=1'),
                5,
                'exceed predefined limit'
            );

            $participants = $this->adminCliJson(array('event:participants', $eventUuid));
            $this->assertSame(array(0), array_column($participants, 'count_guests'));
        } finally {
            if ($eventUuid !== '') {
                $this->adminCliCleanup(array('event:delete', $eventUuid, '--yes'));
            }
            if ($categoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $categoryUuid, '--yes'));
            }
            if ($guestCreated) {
                $this->adminCliCleanup(array('user:delete', $guest, '--yes'));
            }
            if ($groupUuid !== '') {
                $this->adminCliCleanup(array('group:delete', $groupUuid, '--yes'));
            }
            if ($groupCategoryUuid !== '') {
                $this->adminCliCleanup(array('category:delete', $groupCategoryUuid, '--yes'));
            }
        }
    }

    /**
     * Run a command as the test administrator and assert a zero exit status.
     *
     * @param array<int,string> $command
     */
    private function adminCliOk(array $command): void
    {
        $process = $this->runCli(array_merge(array('--as=admin'), $command));
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    /**
     * Run a command as the test administrator in JSON mode.
     *
     * @param array<int,string> $command
     * @return array<mixed>
     */
    private function adminCliJson(array $command): array
    {
        $process = $this->runCli(array_merge(array('--as=admin', '--format=json'), $command));
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $data = $this->cliJson($process);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * @param array<int,string> $command
     */
    private function userCliOk(string $login, array $command): void
    {
        $process = $this->runCli(array_merge(array('--as=' . $login), $command));
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    /**
     * @param array<int,string> $command
     * @return array<mixed>
     */
    private function userCliJson(string $login, array $command): array
    {
        $process = $this->runCli(array_merge(array('--as=' . $login, '--format=json'), $command));
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $data = $this->cliJson($process);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * Resolve the installer-created administrator role without depending on its translated name.
     */
    private function administratorRoleUuid(): string
    {
        $roles = $this->adminCliJson(array('group:list'));

        foreach ($roles as $role) {
            if ((bool)($role['administrator'] ?? false)) {
                $uuid = (string)($role['uuid'] ?? '');
                if ($uuid !== '') {
                    return $uuid;
                }
            }
        }

        $this->fail('The production installation did not expose an administrator role through group:list.');
        return '';
    }

    /**
     * Run a command that has to be refused and assert its exit status and its message.
     * A command that starts with --as= brings its own acting user, everything else runs as the
     * administrator.
     *
     * @param array<int,string> $command
     */
    private function adminCliFails(array $command, int $exitCode, string $message): void
    {
        if (!str_starts_with($command[0], '--as=')) {
            $command = array_merge(array('--as=admin'), $command);
        }

        $process = $this->runCli($command);

        $this->assertSame(
            $exitCode,
            $process->getExitCode(),
            'Output: ' . $process->getOutput() . PHP_EOL . 'Errors: ' . $process->getErrorOutput()
        );
        $this->assertStringContainsString($message, $process->getErrorOutput());
    }

    /**
     * Cleanup intentionally does not assert so that it cannot mask the original failure.
     *
     * @param array<int,string> $command
     */
    private function adminCliCleanup(array $command): void
    {
        $this->runCli(array_merge(array('--as=admin', '--no-interaction'), $command));
    }

    /**
     * @param array<int,string> $command
     */
    private function userCliCleanup(string $login, array $command): void
    {
        $this->runCli(array_merge(array('--as=' . $login, '--no-interaction'), $command));
    }

    /**
     * Test that a command is refused without an acting user
     *
     * @testdox A command that acts for somebody refuses to run without an acting user
     */
    public function testACommandThatActsForSomebodyRefusesToRunWithoutAnActingUser(): void
    {
        $process = $this->runCli(array('user:show', 'admin', '--format=json'));

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('--as=', $process->getErrorOutput());
    }

    /**
     * Test that an unknown command is refused
     *
     * @testdox An unknown command is refused with a hint at the command list
     */
    public function testAnUnknownCommandIsRefusedWithAHintAtTheCommandList(): void
    {
        $process = $this->runCli(array('not:acommand'));

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('Unknown command', $process->getErrorOutput());
        $this->assertStringContainsString('admidio list', $process->getErrorOutput());
    }

    /**
     * Test that a configuration file that is not there is reported
     *
     * @testdox A missing configuration file is reported with the path that was looked for
     */
    public function testAMissingConfigurationFileIsReportedWithThePathThatWasLookedFor(): void
    {
        $missing = ADMIDIO_PATH . FOLDER_DATA . '/no-such-config.php';

        $this->assertFileDoesNotExist($missing);

        $process = $this->runCli(array('user:show', 'admin'), $missing);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('was not found', $process->getErrorOutput());
        $this->assertStringContainsString('no-such-config.php', $process->getErrorOutput());
    }
}
