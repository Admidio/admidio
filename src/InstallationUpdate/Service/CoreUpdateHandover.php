<?php

namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\MaintenanceMode;
use RuntimeException;
use Throwable;

/**
 * The side of an automatic core update that install/update.php sees.
 *
 * The core updater in install/core-update/ replaces the files and then hands over to the database
 * update. install/update.php runs the code of the new release, so it is the one that finishes the
 * update: it runs the migration, records whether it worked and switches maintenance mode off
 * again - in the same request, whether or not a browser is still waiting for the answer.
 *
 * The rules for that are kept here and not in the script, so that they can be tested:
 *
 * - The migration may only run once the updater has installed and verified every file (state
 *   **files_installed** or later). Before that the files are a mix of two releases, so the
 *   migration is refused and the maintenance mode stays on - also for somebody who opens
 *   install/update.php in another tab, without any core update parameter, while the files are
 *   being copied.
 * - Maintenance mode that a core update switched on is only switched off by the migration that
 *   finishes this update.
 * - The updater cookie lets the administrator who started the update continue without another
 *   login. Without it, the normal administrator login of install/update.php is the proof of
 *   identity.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class CoreUpdateHandover
{
    /** Every file is installed and verified; the database update can start. */
    public const STATE_FILES_INSTALLED = 'files_installed';
    /** The database update has started. From here on the files can no longer be rolled back. */
    public const STATE_DATABASE_RUNNING = 'database_running';
    /** A step of the database update failed. */
    public const STATE_DATABASE_FAILED = 'database_failed';
    /** The database is up to date and maintenance mode is off. */
    public const STATE_COMPLETED = 'completed';

    /**
     * The states in which the files belong to one release, so that the migration may run.
     */
    private const MIGRATION_STATES = array(
        self::STATE_FILES_INSTALLED, self::STATE_DATABASE_RUNNING, self::STATE_DATABASE_FAILED, self::STATE_COMPLETED
    );

    /**
     * The owner of a maintenance mode that a core update switched on is this prefix and the update
     * ID.
     */
    public const MAINTENANCE_OWNER_PREFIX = 'core-update:';

    /**
     * Overrides the directory of the update workspaces. Only used by tests.
     */
    private static ?string $workspaceRoot = null;

    /**
     * @param string $updateId
     * @param array<string,mixed> $state The decoded state.json, or an empty array if it could not
     *                                   be read.
     */
    private function __construct(private readonly string $updateId, private array $state)
    {
    }

    /**
     * The core update a request names, for example through the parameter **core_update**.
     * @param string $updateId
     * @return self
     * @throws Exception The ID is malformed or no such update exists.
     */
    public static function load(string $updateId): self
    {
        $state = self::isValidId($updateId) ? self::readState($updateId) : null;
        if ($state === null) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return new self($updateId, $state);
    }

    /**
     * The core update that owns the current maintenance mode, or **null** if maintenance mode is off
     * or was switched on by something else.
     *
     * An update whose state cannot be read is still returned, with an empty state: whatever it
     * did, it is not known to be finished with the files, so it must keep the migration out.
     * @return self|null
     */
    public static function fromMaintenance(): ?self
    {
        try {
            $maintenance = MaintenanceMode::getState();
        } catch (Throwable) {
            return null;
        }

        $owner = (string)($maintenance['owner'] ?? '');
        if (!str_starts_with($owner, self::MAINTENANCE_OWNER_PREFIX)) {
            return null;
        }

        $updateId = substr($owner, strlen(self::MAINTENANCE_OWNER_PREFIX));
        if (!self::isValidId($updateId)) {
            return null;
        }

        return new self($updateId, self::readState($updateId) ?? array());
    }

    /**
     * Whether a string has the form of an update ID: 32 lower case hex characters.
     * @param string $updateId
     * @return bool
     */
    public static function isValidId(string $updateId): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $updateId) === 1;
    }

    public function getUpdateId(): string
    {
        return $this->updateId;
    }

    /**
     * The state of the update, or an empty string if it is not known.
     * @return string
     */
    public function getState(): string
    {
        return (string)($this->state['state'] ?? '');
    }

    /**
     * Whether the files belong to one release, so that the database update may run.
     * @return bool
     */
    public function isMigrationAllowed(): bool
    {
        return in_array($this->getState(), self::MIGRATION_STATES, true);
    }

    /**
     * The name of the cookie that carries the credential of the administrator who started the
     * update.
     * @return string
     */
    public function getCookieName(): string
    {
        return (string)($this->state['cookieName'] ?? '');
    }

    /**
     * Whether a credential is the one of this update and has not expired. Only its hash is on disk.
     * @param mixed $credential The value of the updater cookie.
     * @return bool
     */
    public function isAuthorized(mixed $credential): bool
    {
        return is_string($credential) && $credential !== ''
            && isset($this->state['authorizationHash'], $this->state['expiresAt'])
            && hash_equals((string)$this->state['authorizationHash'], hash('sha256', $credential))
            && (int)$this->state['expiresAt'] >= time();
    }

    /**
     * Record that the database update starts. From here on the files of the old release can no
     * longer be put back, because the database may already be ahead of them.
     * @return void
     */
    public function markDatabaseRunning(): void
    {
        $this->update(array('state' => self::STATE_DATABASE_RUNNING, 'databaseStarted' => true, 'lastError' => ''));
    }

    /**
     * Record that the database update failed. Maintenance mode stays on: the files are already
     * those of the new release, and the database is not.
     * @param string $error
     * @return void
     */
    public function markDatabaseFailed(string $error): void
    {
        $this->update(array('state' => self::STATE_DATABASE_FAILED, 'lastError' => $error));
    }

    /**
     * Record that the database is up to date and switch off the maintenance mode of this update.
     * @return void
     * @throws Exception The files are not installed yet.
     */
    public function complete(): void
    {
        if (!$this->isMigrationAllowed()) {
            throw new Exception('SYS_CORE_UPDATE_IN_PROGRESS');
        }

        $this->update(array('state' => self::STATE_COMPLETED, 'lastError' => ''));

        $maintenance = MaintenanceMode::getState();
        if ($maintenance !== null && ($maintenance['owner'] ?? '') === self::MAINTENANCE_OWNER_PREFIX . $this->updateId) {
            MaintenanceMode::disable(self::MAINTENANCE_OWNER_PREFIX . $this->updateId);
        }
    }

    /**
     * Address of the updater page that shows the result and cleans up the workspace.
     * @return string
     */
    public function getResultUrl(): string
    {
        return ADMIDIO_URL . FOLDER_INSTALLATION . '/core-update/index.php?update_id='
            . rawurlencode($this->updateId) . '&action=finish';
    }

    /**
     * Read the workspaces from another directory. Pass **null** to restore the regular one. Only
     * tests use this.
     * @param string|null $directory
     * @return void
     */
    public static function setWorkspaceRoot(?string $directory): void
    {
        self::$workspaceRoot = $directory;
    }

    /**
     * Absolute path of the directory that holds the workspaces of the core updates.
     * @return string
     */
    private static function getWorkspaceRoot(): string
    {
        return self::$workspaceRoot ?? ADMIDIO_PATH . FOLDER_TEMP_DATA . '/core-update';
    }

    /**
     * The decoded state of an update, or **null** if it does not exist or cannot be read.
     * @param string $updateId
     * @return array<string,mixed>|null
     */
    private static function readState(string $updateId): ?array
    {
        $file = self::getWorkspaceRoot() . '/' . $updateId . '/state.json';
        if (!is_file($file)) {
            return null;
        }

        $state = json_decode((string)file_get_contents($file), true);
        if (!is_array($state) || ($state['updateId'] ?? '') !== $updateId) {
            return null;
        }

        return $state;
    }

    /**
     * Change fields of the state.
     *
     * The updater may be answering a request of its own at the same moment, so the state is read
     * again and written under the lock the updater uses. It is written to a temporary file and
     * renamed, and the version before is kept as state.json.previous, the way the updater does it.
     * @param array<string,mixed> $changes
     * @return void
     */
    private function update(array $changes): void
    {
        $lock = @fopen(self::getWorkspaceRoot() . '/update.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('The core update state could not be locked.');
        }

        try {
            $file = self::getWorkspaceRoot() . '/' . $this->updateId . '/state.json';
            $state = self::readState($this->updateId);
            if ($state === null) {
                throw new RuntimeException('The core update state could not be read.');
            }

            $this->state = array_merge($state, $changes);

            $temporaryFile = $file . '.new';
            $json = json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporaryFile, $json, LOCK_EX) !== strlen($json)) {
                @unlink($temporaryFile);
                throw new RuntimeException('The core update state could not be written.');
            }

            @copy($file, $file . '.previous');
            if (!rename($temporaryFile, $file)) {
                @unlink($temporaryFile);
                throw new RuntimeException('The core update state could not be written.');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
