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
            $category = $this->adminCliJson(array('category:add', 'ANN', 'CLI announcements ' . $suffix));
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
            $category = $this->adminCliJson(array('category:add', 'LNK', 'CLI links ' . $suffix));
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
            $category = $this->adminCliJson(array('category:add', 'IVT', 'CLI inventory ' . $suffix));
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
            $category = $this->adminCliJson(array('category:add', 'USF', 'CLI profile fields ' . $suffix));
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
            $this->assertNotSame((int)$rows[0]['user1_id'], (int)$rows[1]['user1_id']);

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
