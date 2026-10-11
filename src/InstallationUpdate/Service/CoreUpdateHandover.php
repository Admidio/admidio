<?php

namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\MaintenanceMode;
use AdmidioCoreUpdater as Updater;
use AdmidioCoreUpdater\UpdaterException;
use Throwable;

// The state of an update and the updater cookie have one implementation, the updater's own.
require_once ADMIDIO_PATH . '/install/core-update/lib/common.php';

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
 * The state and the cookie belong to the updater, so they are read and written with its functions,
 * which this file loads directly from install/core-update/lib/common.php.
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
     * Overrides the installation directory in which the updates are kept. Only used by tests.
     */
    private static ?string $testRootPath = null;

    /**
     * @param string $rootPath
     * @param string $updateId
     * @param array<string,mixed> $state The state of the update, or an empty array if it could not
     *                                   be read.
     */
    private function __construct(
        private readonly string $rootPath,
        private readonly string $updateId,
        private array $state
    ) {
    }

    /**
     * The core update a request names, for example through the parameter **core_update**.
     * @param string $updateId
     * @return self
     * @throws Exception The ID is malformed or no such update exists.
     */
    public static function load(string $updateId): self
    {
        $rootPath = self::getRootPath();

        try {
            return new self($rootPath, $updateId, Updater\readState($rootPath, $updateId));
        } catch (UpdaterException) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
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
        if (!Updater\isValidUpdateId($updateId)) {
            return null;
        }

        $rootPath = self::getRootPath();
        try {
            $state = Updater\readState($rootPath, $updateId);
        } catch (UpdaterException) {
            $state = array();
        }

        return new self($rootPath, $updateId, $state);
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
     * The name of the updater cookie, which carries the credential of an administrator.
     * @return string
     */
    public function getCookieName(): string
    {
        return Updater\cookieName(COOKIE_PREFIX);
    }

    /**
     * Whether a cookie value is a credential of this update that has not expired.
     * @param mixed $credential The value of the updater cookie.
     * @return bool
     */
    public function isAuthorized(mixed $credential): bool
    {
        return $this->state !== array() && Updater\isValidCredential($this->state, $credential, time());
    }

    /**
     * Record that the database update starts. From here on the files of the old release can no
     * longer be put back, because the database may already be ahead of them.
     * @return void
     */
    public function markDatabaseRunning(): void
    {
        $this->update(array('state' => self::STATE_DATABASE_RUNNING, 'databaseStarted' => true, 'lastError' => ''));
        Updater\updateLog($this->rootPath, $this->updateId, 'state database_running');
    }

    /**
     * Record that the database update failed. Maintenance mode stays on: the files are already
     * those of the new release, and the database is not.
     * @param string $error
     * @return void
     */
    public function markDatabaseFailed(string $error): void
    {
        // The error of a failed update step is the HTML page of Database::showError(); the updater
        // and the log show it as text.
        $error = trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($error), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $this->update(array('state' => self::STATE_DATABASE_FAILED, 'lastError' => $error));
        Updater\updateLog($this->rootPath, $this->updateId, 'state database_failed: ' . $error);
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
        Updater\updateLog($this->rootPath, $this->updateId, 'state completed: database updated');

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
        return ADMIDIO_URL . FOLDER_INSTALLATION . '/core-update/index.php?action=finish';
    }

    /**
     * Keep the updates in another installation directory. Pass **null** to restore the regular
     * one. Only tests use this.
     * @param string|null $rootPath
     * @return void
     */
    public static function setRootPath(?string $rootPath): void
    {
        self::$testRootPath = $rootPath;
    }

    /**
     * @return string
     */
    private static function getRootPath(): string
    {
        return self::$testRootPath ?? ADMIDIO_PATH;
    }

    /**
     * Change fields of the state, under the lock of the updater.
     * @param array<string,mixed> $changes
     * @return void
     */
    private function update(array $changes): void
    {
        $this->state = Updater\updateState($this->rootPath, $this->updateId, static fn(array $state): array => array_merge($state, $changes));
    }
}
