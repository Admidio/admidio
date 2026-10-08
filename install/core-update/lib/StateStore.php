<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The workspaces of the core updates and their state.
 *
 * Every update has an ID of 32 random hex characters and a workspace
 * **adm_my_files/core-update/&lt;ID&gt;/** with its **state.json**. The state is the contract between
 * the parts of an update - the old updater that prepares it, the new updater that installs it and
 * install/update.php that finishes it - so it is versioned (**schemaVersion**), and an updater
 * accepts every schema version up to its own. A state that cannot be read is an error and is never
 * replaced by an empty one, because it may be all that is left to roll an update back with.
 *
 * Only one update may be unfinished at a time. **active.json** names it, and every request that
 * changes a state holds the lock on **update.lock**.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StateStore
{
    /**
     * The schema of the state this updater writes. A change is either additive or handled where
     * the state is read.
     */
    public const SCHEMA_VERSION = 1;

    /**
     * The states before maintenance mode is entered. An update that is abandoned in one of them
     * has not changed anything, so its workspace may be removed.
     */
    public const STATES_BEFORE_CHANGES = array(
        'new', 'checking', 'downloading', 'prepared', 'extracting', 'planning', 'review', 'ready'
    );

    /**
     * The states in which an update is over.
     */
    public const STATES_FINISHED = array('completed', 'rolled_back', 'cancelled');

    /**
     * An abandoned update that never changed anything is removed after this many seconds without
     * a request.
     */
    public const ABANDONED_AFTER = 86400;

    private readonly string $directory;

    /** @var resource|null The lock while this request holds it. */
    private $lock = null;

    /**
     * @param string $rootPath Directory of the Admidio installation.
     */
    public function __construct(string $rootPath)
    {
        $this->directory = rtrim(str_replace('\\', '/', $rootPath), '/') . '/adm_my_files/core-update';
    }

    /**
     * Whether a string has the form of an update ID.
     * @param string $updateId
     * @return bool
     */
    public static function isValidId(string $updateId): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $updateId) === 1;
    }

    /**
     * The directory that holds the workspaces, the lock and the files that belong to no single
     * update.
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * The workspace of an update.
     * @param string $updateId
     * @return string
     */
    public function getWorkspace(string $updateId): string
    {
        if (!self::isValidId($updateId)) {
            throw new UpdaterException('Invalid update ID.');
        }

        return $this->directory . '/' . $updateId;
    }

    /**
     * Run a function while holding the lock of the updater.
     *
     * A request that does not want to wait is refused with "another request is running" - the
     * browser of the administrator may still be sending the previous chunk.
     * @template T
     * @param callable(): T $function
     * @param bool $wait
     * @return T
     */
    public function withLock(callable $function, bool $wait = true): mixed
    {
        if ($this->lock !== null) {
            // This request holds the lock already.
            return $function();
        }

        $this->acquire($wait);
        try {
            return $function();
        } finally {
            $this->release();
        }
    }

    /**
     * Take the lock of the updater for the rest of a step. Prefer withLock(); this is for a step
     * that spans several methods. release() must follow in a finally block.
     * @param bool $wait
     * @return void
     */
    public function acquire(bool $wait = true): void
    {
        if ($this->lock !== null) {
            throw new UpdaterException('The lock of the updater is already held by this request.');
        }

        $this->ensureDirectory($this->directory);
        $lock = @fopen($this->directory . '/update.lock', 'c+');
        if ($lock === false) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
        if (!flock($lock, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_RUNNING');
        }

        $this->lock = $lock;
    }

    /**
     * Give the lock back.
     * @return void
     */
    public function release(): void
    {
        if ($this->lock !== null) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    /**
     * Start a new update and make it the active one.
     * @param array<string,mixed> $fields Fields of the state, for example the channel.
     * @return array<string,mixed> The state of the new update.
     * @throws UpdaterException Another update is unfinished.
     */
    public function create(array $fields = array()): array
    {
        return $this->withLock(function () use ($fields): array {
            $this->removeAbandoned(time());
            if ($this->getActiveId() !== null) {
                throw new UpdaterException('SYS_CORE_UPDATE_ALREADY_RUNNING');
            }

            $updateId = bin2hex(random_bytes(16));
            $this->ensureDirectory($this->getWorkspace($updateId));

            $state = array_merge(array(
                'schemaVersion' => self::SCHEMA_VERSION,
                'updateId' => $updateId,
                'state' => 'new',
                'channel' => 'stable',
                'installedVersion' => '',
                'targetVersion' => '',
                'credentials' => array(),
                'csrfToken' => bin2hex(random_bytes(32)),
                'createdAt' => time(),
                'lastError' => ''
            ), $fields, array('updateId' => $updateId, 'schemaVersion' => self::SCHEMA_VERSION));

            $this->write($state);
            $this->writeJson($this->directory . '/active.json', array('updateId' => $updateId));

            return $state;
        });
    }

    /**
     * The state of an update.
     * @param string $updateId
     * @return array<string,mixed>
     * @throws UpdaterException The update does not exist or its state cannot be read.
     */
    public function read(string $updateId): array
    {
        $file = $this->getWorkspace($updateId) . '/state.json';
        if (!is_file($file)) {
            throw new UpdaterException('SYS_CORE_UPDATE_NOT_FOUND');
        }

        $state = json_decode((string)file_get_contents($file), true);
        if (!is_array($state) || ($state['updateId'] ?? '') !== $updateId) {
            throw new UpdaterException('The state of update ' . $updateId . ' cannot be read. It was left in place; '
                . 'state.json.previous in its workspace holds the version before.');
        }

        $schemaVersion = (int)($state['schemaVersion'] ?? 0);
        if ($schemaVersion < 1 || $schemaVersion > self::SCHEMA_VERSION) {
            throw new UpdaterException('The state of update ' . $updateId . ' has the schema version '
                . $schemaVersion . ', which this updater does not know.');
        }

        return $state;
    }

    /**
     * Whether an update exists.
     * @param string $updateId
     * @return bool
     */
    public function exists(string $updateId): bool
    {
        return self::isValidId($updateId) && is_file($this->getWorkspace($updateId) . '/state.json');
    }

    /**
     * Write the state of an update: to a temporary file that is then renamed over the old one, so a
     * reader never sees half a state. The version before is kept as state.json.previous.
     *
     * The caller holds the lock, or the update is new.
     * @param array<string,mixed> $state
     * @return void
     */
    public function write(array $state): void
    {
        $state['updatedAt'] = time();
        $file = $this->getWorkspace((string)($state['updateId'] ?? '')) . '/state.json';
        if (is_file($file) && !@copy($file, $file . '.previous')) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }

        $this->writeJson($file, $state);
    }

    /**
     * Change the state of an update under the lock.
     * @param string $updateId
     * @param callable(array<string,mixed>): array<string,mixed> $change
     * @return array<string,mixed> The new state.
     */
    public function update(string $updateId, callable $change): array
    {
        return $this->withLock(function () use ($updateId, $change): array {
            $state = $change($this->read($updateId));
            $this->write($state);

            return $state;
        });
    }

    /**
     * The ID of the unfinished update, or **null**.
     * @return string|null
     */
    public function getActiveId(): ?string
    {
        $file = $this->directory . '/active.json';
        if (!is_file($file)) {
            return null;
        }

        $active = json_decode((string)file_get_contents($file), true);
        $updateId = is_array($active) ? (string)($active['updateId'] ?? '') : '';
        if (!$this->exists($updateId)) {
            return null;
        }

        try {
            return in_array((string)($this->read($updateId)['state'] ?? ''), self::STATES_FINISHED, true) ? null : $updateId;
        } catch (UpdaterException) {
            // An unreadable state is not finished: it blocks a new update until somebody looks at it.
            return $updateId;
        }
    }

    /**
     * Forget the active update, because it is over.
     * @param string $updateId
     * @return void
     */
    public function clearActive(string $updateId): void
    {
        $this->withLock(function () use ($updateId): void {
            $file = $this->directory . '/active.json';
            $active = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
            if (is_array($active) && ($active['updateId'] ?? '') === $updateId) {
                @unlink($file);
            }
        });
    }

    /**
     * Remove the active update if it was abandoned before it changed anything: in a state before
     * maintenance mode and without a request for ABANDONED_AFTER seconds. An update that got
     * further is never removed, because its workspace holds the rollback copy.
     * @param int $now
     * @return bool Whether an update was removed.
     */
    public function removeAbandoned(int $now): bool
    {
        return $this->withLock(function () use ($now): bool {
            $file = $this->directory . '/active.json';
            $active = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
            $updateId = is_array($active) ? (string)($active['updateId'] ?? '') : '';
            if (!$this->exists($updateId)) {
                return false;
            }

            try {
                $state = $this->read($updateId);
            } catch (UpdaterException) {
                return false;
            }

            $lastRequest = (int)($state['updatedAt'] ?? $state['createdAt'] ?? 0);
            if (!in_array((string)$state['state'], self::STATES_BEFORE_CHANGES, true)
                || $lastRequest + self::ABANDONED_AFTER > $now) {
                return false;
            }

            self::deleteDirectory($this->getWorkspace($updateId));
            @unlink($file);

            return true;
        });
    }

    /**
     * Delete a directory with everything in it.
     * @param string $directory
     * @return void
     */
    public static function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($directory);
    }

    /**
     * Create a directory if it is not there yet.
     * @param string $directory
     * @return void
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
    }

    /**
     * Write a JSON file atomically: a temporary file in the same directory, then a rename.
     * @param string $file
     * @param array<string,mixed> $data
     * @return void
     */
    private function writeJson(string $file, array $data): void
    {
        $this->ensureDirectory(dirname($file));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporaryFile = $file . '.' . bin2hex(random_bytes(4)) . '.new';

        if (@file_put_contents($temporaryFile, $json, LOCK_EX) !== strlen($json) || !@rename($temporaryFile, $file)) {
            @unlink($temporaryFile);
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
    }
}
