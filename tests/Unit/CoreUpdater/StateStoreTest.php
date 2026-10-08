<?php
/**
 * The workspaces and the state of the core updates: the contract between the parts of an update.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\StateStore;
use AdmidioCoreUpdater\UpdaterException;

final class StateStoreTest extends AdmidioTestCase
{
    private string $rootPath = '';
    private StateStore $store;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/adm_updater_state_' . uniqid();
        mkdir($this->rootPath . '/adm_my_files', 0777, true);
        $this->store = new StateStore($this->rootPath);
    }

    protected function tearDown(): void
    {
        StateStore::deleteDirectory($this->rootPath);

        parent::tearDown();
    }

    /**
     * Assert that a call fails with the given text key or diagnostic.
     */
    private function assertFailsWith(string $expected, callable $call): void
    {
        try {
            $call();
            $this->fail('no exception, expected ' . $expected);
        } catch (UpdaterException $exception) {
            $this->assertStringContainsString($expected, $exception->getMessage());
        }
    }

    /**
     * @testdox A new update gets a workspace, the current schema and becomes the active one
     */
    public function testCreate(): void
    {
        $state = $this->store->create(array('channel' => 'master', 'installedVersion' => '5.0.14', 'updateId' => 'forged'));

        $this->assertTrue(StateStore::isValidId($state['updateId']), 'the ID cannot be given from outside');
        $this->assertSame(StateStore::SCHEMA_VERSION, $state['schemaVersion']);
        $this->assertSame('new', $state['state']);
        $this->assertSame('master', $state['channel']);
        $this->assertSame('5.0.14', $state['installedVersion']);
        $this->assertSame(array(), $state['credentials']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $state['csrfToken']);
        $this->assertSame($state['updateId'], $this->store->getActiveId());
        $stored = $this->store->read($state['updateId']);
        unset($stored['updatedAt']);
        $this->assertSame($state, $stored, 'what create() returns is what was stored');
    }

    /**
     * @testdox Only one update can be unfinished
     */
    public function testOneUpdateAtATime(): void
    {
        $this->store->create();

        $this->assertFailsWith('SYS_CORE_UPDATE_ALREADY_RUNNING', fn() => $this->store->create());
    }

    /**
     * @testdox A finished update no longer blocks a new one
     */
    public function testFinishedUpdateIsNotActive(): void
    {
        $state = $this->store->create();
        $this->store->update($state['updateId'], static fn(array $s): array => array_merge($s, array('state' => 'completed')));

        $this->assertNull($this->store->getActiveId());
        $this->assertNotSame($state['updateId'], $this->store->create()['updateId']);
    }

    /**
     * @testdox A state is written atomically, and the version before is kept
     */
    public function testWriteKeepsThePreviousVersion(): void
    {
        $state = $this->store->create();
        $workspace = $this->store->getWorkspace($state['updateId']);

        $this->store->update($state['updateId'], static fn(array $s): array => array_merge($s, array('state' => 'prepared')));

        $this->assertSame('prepared', $this->store->read($state['updateId'])['state']);
        $this->assertSame('new', json_decode((string)file_get_contents($workspace . '/state.json.previous'), true)['state']);
        $this->assertSame(array(), glob($workspace . '/*.new'), 'no temporary file is left');
    }

    /**
     * @testdox A state that cannot be read is refused and left as it is
     */
    public function testUnreadableStateIsNeverReplaced(): void
    {
        $state = $this->store->create();
        $file = $this->store->getWorkspace($state['updateId']) . '/state.json';
        file_put_contents($file, '{"updateId": "' . $state['updateId'] . '", "state": "inst');

        $this->assertFailsWith('cannot be read', fn() => $this->store->read($state['updateId']));
        $this->assertFailsWith('cannot be read', fn() => $this->store->update($state['updateId'], static fn(array $s): array => $s));
        $this->assertStringEndsWith('"state": "inst', (string)file_get_contents($file));
        $this->assertSame($state['updateId'], $this->store->getActiveId(), 'an unreadable update still blocks a new one');
    }

    /**
     * @testdox A state of a newer schema than this updater knows is refused
     */
    public function testUnknownSchemaIsRefused(): void
    {
        $state = $this->store->create();
        $this->store->write(array_merge($state, array('schemaVersion' => StateStore::SCHEMA_VERSION + 1)));

        $this->assertFailsWith('schema version', fn() => $this->store->read($state['updateId']));
    }

    /**
     * @testdox An update that does not exist is reported as such
     */
    public function testMissingUpdate(): void
    {
        $this->assertFailsWith('SYS_CORE_UPDATE_NOT_FOUND', fn() => $this->store->read(str_repeat('a', 32)));
        $this->assertFailsWith('Invalid update ID', fn() => $this->store->read('../../etc'));
        $this->assertFalse($this->store->exists('../../etc'));
    }

    /**
     * @testdox An update abandoned before it changed anything is removed after a day
     */
    public function testAbandonedUpdateIsRemoved(): void
    {
        $state = $this->store->create(array('state' => 'prepared'));
        $workspace = $this->store->getWorkspace($state['updateId']);
        $lastRequest = (int)$this->store->read($state['updateId'])['updatedAt'];

        $this->assertFalse($this->store->removeAbandoned($lastRequest + StateStore::ABANDONED_AFTER - 1), 'not yet');
        $this->assertDirectoryExists($workspace);

        $this->assertTrue($this->store->removeAbandoned($lastRequest + StateStore::ABANDONED_AFTER));
        $this->assertDirectoryDoesNotExist($workspace);
        $this->assertNull($this->store->getActiveId());
    }

    /**
     * @testdox An update that changed files is never removed, however old it is
     */
    public function testUpdateBeyondReadyIsKept(): void
    {
        $state = $this->store->create(array('state' => 'installing'));

        $this->assertFalse($this->store->removeAbandoned(time() + 10 * StateStore::ABANDONED_AFTER));
        $this->assertSame($state['updateId'], $this->store->getActiveId());
    }

    /**
     * @testdox A request that does not want to wait is refused while another one holds the lock
     */
    public function testLockConflict(): void
    {
        $other = new StateStore($this->rootPath);
        $other->acquire();

        try {
            $this->assertFailsWith('SYS_CORE_UPDATE_REQUEST_RUNNING',
                fn() => $this->store->withLock(static fn() => null, false));
        } finally {
            $other->release();
        }

        $this->assertSame('free', $this->store->withLock(static fn(): string => 'free', false));
    }

    /**
     * @testdox The lock can be taken again inside a locked section
     */
    public function testLockIsReentrant(): void
    {
        $state = $this->store->create();

        $this->store->withLock(function () use ($state): void {
            $this->store->update($state['updateId'], static fn(array $s): array => array_merge($s, array('state' => 'ready')));
            $this->store->clearActive($state['updateId']);
        });

        $this->assertSame('ready', $this->store->read($state['updateId'])['state']);
        $this->assertNull($this->store->getActiveId());
    }
}
