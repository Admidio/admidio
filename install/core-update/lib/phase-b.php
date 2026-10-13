<?php
/**
 * Phase B of an update: from the verified package to installed and verified files. It runs in the
 * updater of the new release, which phase A copied over the old one.
 *
 * extracting   - every file of the package is unpacked into the workspace and checked against the
 *                manifest
 * planning     - every file of the installation is compared with the new release: what is created,
 *                replaced and removed; checks D1-D3
 * review       - the administrator sees the plan and starts the installation
 * ready        - the plan is confirmed, with the choice of the database backup
 * dumping      - maintenance mode on; the database is backed up into adm_my_files/backups/
 * installing   - system/bootstrap/ first, then the rest; every replaced or
 *                removed file is copied to the workspace first and every step is journaled
 * archiving    - the replaced and removed files that were changed or unknown go into the kept-files
 *                archive in adm_my_files/backups/, which is never removed automatically
 * verifying    - every installed file is hashed again; then OPcache is reset
 * files_installed - install/update.php takes over and updates the database (phase C)
 *
 * Up to review nothing is changed, and cancelling removes the workspace. From installing on, an
 * error stops in rollback_required: the administrator continues the update or restores the old
 * files (rolling_back), which is possible until the database update starts. If the database update
 * fails, the previous version can be restored as a whole: first the database from the backup
 * (restoring_database), then the files.
 *
 * Every function gets the context of phase A (see phase-a.php).
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use PDO;
use Throwable;

const PHASE_B_STATES = array('prepared', 'extracting', 'planning', 'review', 'ready', 'dumping', 'installing', 'archiving',
    'verifying', 'rollback_required', 'rolling_back', 'restoring_database');

/** The states in which install/update.php has the update. */
const PHASE_C_STATES = array('files_installed', 'database_running', 'database_failed');

/** Files per request of the browser, besides the time budget. */
const FILES_PER_REQUEST = 200;

/**
 * How many files a request may work on: a request of the browser has to stay short; the command
 * line only stops for the time budget, which still keeps its progress coming, because every piece
 * of work reads and writes the state, the plan and the journal again.
 */
function filesPerRequest(int $factor = 1): int
{
    return PHP_SAPI === 'cli' ? PHP_INT_MAX : $factor * FILES_PER_REQUEST;
}

/** Never written, scanned or removed, whatever a manifest says. */
const ALWAYS_PRESERVED = array('adm_my_files', 'install/core-update', 'config.php', 'custom_css.css', 'custom_favicon.png',
    'custom_logo.png', '.htaccess', '.user.ini', 'robots.txt');

/** The inventory of an installation: the manifest of its release. */
const INSTALLED_MANIFEST = 'install/update-manifest.json';

/**
 * Do the next bounded piece of work of phase B.
 * @param array<string,mixed> $ctx
 * @return array{status: string, message: string, progress: int, state: string}
 */
function phaseBRun(array $ctx, string $updateId): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId): array {
        $state = readState($root, $updateId);
        $before = (string)$state['state'];

        try {
            $state = match ($before) {
                'prepared' => startExtraction($ctx, $state),
                'extracting' => extractPart($ctx, $state),
                'planning' => planPart($ctx, $state),
                'ready' => enterMaintenance($ctx, $state),
                'dumping' => dumpPart($ctx, $state),
                'installing' => installPart($ctx, $state),
                'archiving' => archivePart($ctx, $state),
                'verifying' => verifyPart($ctx, $state),
                'rolling_back' => rollbackPart($ctx, $state),
                'restoring_database' => restoreDatabasePart($ctx, $state),
                // Continue where the error stopped the update.
                'rollback_required' => array_merge($state, array('state' => (string)($state['failedIn'] ?? 'installing'))),
                default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
            };
        } catch (VerificationFailure $failure) {
            // Only extracting and planning verify, before anything was changed.
            updateLog($root, $updateId, 'verification failed in ' . $before . ': ' . errorDetail($failure->getPrevious()));
            abortUpdate($ctx, $updateId);

            return array('status' => 'failed', 'message' => describeError($ctx, $failure->getPrevious()), 'progress' => 0, 'state' => 'failed');
        } catch (Throwable $exception) {
            $state['lastError'] = describeError($ctx, $exception instanceof UpdaterException
                ? $exception : UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', (string)$exception));
            updateLog($root, $updateId, 'error in ' . $before . ': ' . errorDetail($exception));
            if ($before === 'dumping') {
                // No file was changed yet: back to the review, without maintenance mode.
                deleteDirectory(workspace($root, $updateId) . '/dump');
                leaveMaintenance($root, $updateId);
                $state = array_merge($state, array('state' => 'review', 'cursor' => 0, 'cursorTotal' => 0,
                    'lastError' => text($ctx['texts'], 'SYS_CORE_UPDATE_DUMP_FAILED', array($state['lastError']))));
            } elseif (in_array($before, array('installing', 'archiving', 'verifying'), true) || ($before === 'ready' && isMaintenanceOwner($root, $updateId))) {
                // Files may have changed: only continuing or restoring is left. A failed rollback
                // or a failed check before maintenance mode is simply repeated.
                $state['failedIn'] = $before === 'ready' ? 'ready' : $before;
                $state['state'] = 'rollback_required';
            }
            writeState($root, $state);
            if ($state['state'] !== $before) {
                updateLog($root, $updateId, 'state ' . $state['state']);
            }

            return array('status' => 'error', 'message' => $state['lastError'], 'progress' => phaseBProgress($state), 'state' => $state['state']);
        }

        $state['lastError'] = '';
        writeState($root, $state);
        if ($state['state'] !== $before) {
            updateLog($root, $updateId, 'state ' . $state['state']
                . ($state['state'] === 'review' ? ' ' . json_encode($state['plan']) : '')
                . (($state['dumpFile'] ?? '') !== '' && $before === 'dumping' ? ' ' . $state['dumpFile'] : '')
                . (($state['keptArchive'] ?? '') !== '' && $before === 'archiving' ? ' ' . $state['keptArchive'] : ''));
        }

        return phaseBResult($ctx, $state);
    }, false);
}

/**
 * The answer to the page for the state an update has reached.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array{status: string, message: string, progress: int, state: string}
 */
function phaseBResult(array $ctx, array $state): array
{
    $texts = $ctx['texts'];
    $result = array('status' => 'success', 'message' => '', 'progress' => phaseBProgress($state), 'state' => (string)$state['state']);

    switch ($state['state']) {
        case 'prepared':
        case 'extracting':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_EXTRACTING');
            break;
        case 'planning':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_PLANNING');
            break;
        case 'review':
            $result['status'] = 'review';
            break;
        case 'ready':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_MAINTENANCE_ACTIVE');
            break;
        case 'dumping':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_DUMPING');
            break;
        case 'installing':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_FILES_INSTALLING');
            break;
        case 'archiving':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_ARCHIVING');
            break;
        case 'verifying':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_VERIFYING');
            break;
        case 'restoring_database':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_RESTORING_DATABASE');
            break;
        case 'rolling_back':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_ROLLING_BACK');
            break;
        case 'rolled_back':
            $result['status'] = 'rolled_back';
            $result['message'] = rolledBackMessage($ctx, $state);
            break;
        case 'files_installed':
        case 'completed':
            // The page of the database update, or of the result, takes over.
            $result['status'] = 'reload';
            break;
    }

    return $result;
}

/**
 * The progress of phase B in percent.
 * @param array<string,mixed> $state
 */
function phaseBProgress(array $state): int
{
    $part = static fn(int $from, int $to): int => $from + (int)floor(($to - $from) * min(1, (int)($state['cursor'] ?? 0) / max(1, (int)($state['cursorTotal'] ?? 1))));

    return match ($state['state']) {
        'prepared' => 0,
        'extracting' => $part(0, 15),
        'planning' => $part(15, 30),
        'review', 'ready' => 30,
        'dumping' => $part(30, 40),
        'installing' => $part(40, 80),
        'archiving' => $part(80, 85),
        'verifying' => $part(85, 95),
        'rollback_required' => 30,
        'rolling_back' => 100 - $part(0, 100),
        'restoring_database' => 100,
        'files_installed', 'database_running', 'database_failed' => 95,
        'completed' => 100,
        default => 0
    };
}

/**
 * Start the installation after the administrator saw the plan and chose the backup of the
 * database: "dump" lets the updater back it up, "own" confirms an own backup. The browser gets the
 * updater cookie here, because from maintenance mode on the Admidio login cannot be used.
 * @param array<string,mixed> $ctx
 * @return array<string,mixed>
 */
function phaseBConfirm(array $ctx, string $updateId, string $backup): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId, $backup): array {
        $state = readState($root, $updateId);
        if ($state['state'] !== 'review' || checksHaveErrors((array)($state['planChecks'] ?? array()))
            || !in_array($backup, array('dump', 'own'), true) || ($backup === 'dump' && ($state['dumpMethod'] ?? '') === '')) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $cookie = issueCredential($state, time());
        $state = array_merge($state, array('state' => 'ready', 'backup' => $backup, 'lastError' => ''));
        writeState($root, $state);
        updateLog($root, $updateId, 'confirmed by the administrator, database backup: ' . $backup);
        sendCredentialCookie($ctx['config'], $cookie);

        return $state;
    }, false);
}

/**
 * Give up an update before it changed anything. The updater of the new release, which phase A
 * installed, stays: it works with the old release as well.
 * @param array<string,mixed> $ctx
 */
function phaseBCancel(array $ctx, string $updateId): void
{
    withLock($ctx['config']['rootPath'], static function () use ($ctx, $updateId): void {
        if (!in_array(readState($ctx['config']['rootPath'], $updateId)['state'], array('prepared', 'extracting', 'planning', 'review', 'ready'), true)) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
        abortUpdate($ctx, $updateId);
    }, false);
}

/**
 * Restore the old files: possible after an error and until the database update starts.
 * @param array<string,mixed> $ctx
 * @return array<string,mixed>
 */
function phaseBRollback(array $ctx, string $updateId): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($root, $updateId): array {
        $state = readState($root, $updateId);
        $before = (string)$state['state'];
        if (!in_array($state['state'], array('installing', 'archiving', 'verifying', 'rollback_required', 'files_installed'), true)
            || !empty($state['databaseStarted'])) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $state = array_merge($state, array('state' => 'rolling_back', 'cursor' => 0, 'cursorTotal' => 0, 'lastError' => ''));
        writeState($root, $state);
        updateLog($root, $updateId, 'restoring the previous files was requested in ' . $before);

        return $state;
    }, false);
}

/**
 * What the administrator is told when the update was undone.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 */
function rolledBackMessage(array $ctx, array $state): string
{
    return !empty($state['restoredVersion'])
        ? text($ctx['texts'], 'SYS_CORE_UPDATE_VERSION_RESTORED', array((string)$state['installedVersion']))
        : text($ctx['texts'], 'SYS_CORE_UPDATE_FILESYSTEM_ROLLED_BACK');
}

/**
 * How the database of a failed update can be restored by the updater: "mysql" (the table files of
 * the backup, run with PDO), "psql" (the backup of pg_dump, run by psql, if PHP may run it), or ""
 * when there is no backup of the updater or it cannot be run.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 */
function restoreMethod(array $ctx, array $state): string
{
    if (($state['dumpFile'] ?? '') === '' || !is_file($file = backupFile($ctx['config']['rootPath'], (string)$state['dumpFile']))) {
        return '';
    }
    // An encrypted backup needs its password, which may have been changed or removed since.
    if (!empty($state['dumpEncrypted'])) {
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::RDONLY) !== true) {
            return '';
        }
        $readable = $zip->setPassword(dumpPassword($ctx)) && $zip->numFiles > 0 && $zip->getFromIndex(0) !== false;
        $zip->close();
        if (!$readable) {
            return '';
        }
    }
    if ($ctx['config']['dbType'] === 'mysql') {
        return 'mysql';
    }

    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (!function_exists('exec') || in_array('exec', $disabled, true)) {
        return '';
    }
    @exec('psql --version 2>&1', $output, $status);

    return $status === 0 ? 'psql' : '';
}

/**
 * Restore the previous version after a failed database update: the database from the backup of
 * the updater, then the files from the rollback copy. Without a backup that the updater can run,
 * only the files are restored, and only after the administrator confirmed that the database was
 * restored from an own backup: the old files on a half-migrated database would not work.
 * @param array<string,mixed> $ctx
 * @param bool $thenUpdate Start a new update once the previous version is back, which finds a
 *                         corrected release if one has been published since.
 * @return array<string,mixed>
 */
function phaseBRestore(array $ctx, string $updateId, bool $ownDatabaseRestored, bool $thenUpdate = false): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId, $ownDatabaseRestored, $thenUpdate): array {
        $state = readState($root, $updateId);
        if ($state['state'] !== 'database_failed') {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $method = restoreMethod($ctx, $state);
        if ($method === '' && !$ownDatabaseRestored) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $state = array_merge($state, array('state' => $method !== '' ? 'restoring_database' : 'rolling_back', 'restoredVersion' => true,
            'restoreMethod' => $method, 'updateAfterRestore' => $thenUpdate, 'cursor' => 0, 'cursorTotal' => 0, 'lastError' => ''));
        writeState($root, $state);
        updateLog($root, $updateId, 'restoring the previous version was requested: '
            . ($method !== '' ? 'database from ' . $state['dumpFile'] : 'database restored by the administrator') . ', then the files'
            . ($thenUpdate ? ', then a new update' : ''));

        return $state;
    }, false);
}

/**
 * End an undone update after the administrator has seen its result: nobody can continue it any
 * more, and the next visit of the updater starts a new one.
 * @param array<string,mixed> $ctx
 */
function phaseBRolledBack(array $ctx, string $updateId): void
{
    $root = $ctx['config']['rootPath'];

    withLock($root, static function () use ($ctx, $root, $updateId): void {
        $state = readState($root, $updateId);
        if ($state['state'] !== 'rolled_back') {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
        revokeCredentials($state);
        writeState($root, $state);
        clearCredentialCookie($ctx['config']);
    });
}

/**
 * Clean up after install/update.php has completed the update: the workspace with the rollback
 * copy and the package is removed, nobody can continue the update any more.
 * @param array<string,mixed> $ctx
 */
function phaseBFinish(array $ctx, string $updateId): void
{
    $root = $ctx['config']['rootPath'];

    withLock($root, static function () use ($ctx, $root, $updateId): void {
        $state = readState($root, $updateId);
        if ($state['state'] !== 'completed') {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        updateLog($root, $updateId, 'completed, workspace removed');
        keepUpdateLog($root, $state);
        clearActiveUpdate($root, $updateId);
        deleteDirectory(workspace($root, $updateId));
        clearCredentialCookie($ctx['config']);
    });
}

// ------------------------------------------------------------------------------------------------
// Extracting
// ------------------------------------------------------------------------------------------------

/**
 * The manifest of the new release in the workspace, which phase A took from the verified package.
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function workspaceManifest(string $workspace, array $state): array
{
    try {
        return readManifest((string)@file_get_contents($workspace . '/update-manifest.json'), (string)$state['targetVersion']);
    } catch (UpdaterException $exception) {
        throw new VerificationFailure($exception);
    }
}

/**
 * Whether a path belongs to the paths no update touches: those of every release and those of the
 * manifest.
 * @param array<string,mixed> $manifest
 */
function isPreserved(string $path, array $manifest): bool
{
    foreach (array_merge(ALWAYS_PRESERVED, $manifest['preservePaths']) as $preserved) {
        $preserved = trim($preserved, '/');
        if ($path === $preserved || str_starts_with($path, $preserved . '/')) {
            return true;
        }
    }

    return false;
}

/**
 * The files of the new release that phase B installs, sorted: everything in the manifest except
 * the preserved paths (the updater itself was installed by phase A).
 * @param array<string,mixed> $manifest
 * @return array<int,string>
 */
function releaseFiles(array $manifest): array
{
    $files = array_values(array_filter(array_keys($manifest['files']), static fn(string $path): bool => !isPreserved($path, $manifest)));
    sort($files, SORT_STRING);

    return $files;
}

/**
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function startExtraction(array $ctx, array $state): array
{
    $workspace = workspace($ctx['config']['rootPath'], $state['updateId']);
    if (!hash_equals(confirmedPackageHash($workspace, $state), (string)hash_file('sha256', $workspace . '/package.zip'))) {
        throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The package in the workspace has changed.'));
    }

    deleteDirectory($workspace . '/extracted');
    ensureDirectory($workspace . '/extracted');

    return array_merge($state, array('state' => 'extracting', 'cursor' => 0, 'cursorTotal' => 0));
}

/**
 * Unpack the next files of the package into the workspace and check each against the manifest.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function extractPart(array $ctx, array $state): array
{
    $workspace = workspace($ctx['config']['rootPath'], $state['updateId']);
    $manifest = workspaceManifest($workspace, $state);
    $deadline = startTimeBudget(TIME_BUDGET);

    try {
        $package = openPackage($workspace . '/package.zip', MAX_EXTRACTED_SIZE);
    } catch (UpdaterException $exception) {
        throw new VerificationFailure($exception);
    }

    try {
        $indexes = array_keys($package['entries']);
        $state['cursorTotal'] = count($indexes);
        $cursor = (int)$state['cursor'];
        $directories = array();
        for ($done = 0; $cursor < count($indexes) && $done < filesPerRequest() && microtime(true) < $deadline; $cursor++, $done++) {
            $path = $package['entries'][$indexes[$cursor]];
            // A source archive also has what no installation needs: tests, the files of GitHub.
            if ($path === INSTALLED_MANIFEST || isPreserved($path, $manifest) || ($manifest['developer'] && !isset($manifest['files'][$path]))) {
                continue;
            }
            if (!isset($manifest['files'][$path])) {
                throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest does not list ' . $path . '.'));
            }

            $target = $workspace . '/extracted/' . $path;
            knownDirectory(dirname($target), $directories);
            try {
                $hash = packageExtract($package, $indexes[$cursor], $target, MAX_EXTRACTED_SIZE);
            } catch (UpdaterException $exception) {
                throw $exception->getMessage() === 'SYS_CORE_UPDATE_PACKAGE_INVALID' ? new VerificationFailure($exception) : $exception;
            }
            if (!hash_equals($manifest['files'][$path], $hash)) {
                throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', $path . ' does not match the manifest.'));
            }
        }
        $state['cursor'] = $cursor;
        // Every file the package has is extracted now, so the index of the package tells whether a
        // file of the release is missing.
        $packaged = $cursor < count($indexes) ? array() : array_flip($package['entries']);
    } finally {
        $package['zip']->close();
    }

    if ($state['cursor'] < $state['cursorTotal']) {
        return $state;
    }

    foreach (releaseFiles($manifest) as $path) {
        if (!isset($packaged[$path])) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The package lacks ' . $path . '.'));
        }
    }
    @unlink($workspace . '/plan.jsonl');

    return array_merge($state, array('state' => 'planning', 'planStep' => 'files', 'cursor' => 0, 'cursorTotal' => count(releaseFiles($manifest))));
}

// ------------------------------------------------------------------------------------------------
// Planning
// ------------------------------------------------------------------------------------------------

/**
 * Compare the next files of the installation with the new release. The files of the release are
 * compared by hash; a file that is already right gets no operation. When all are done, the managed
 * directories are scanned for files the release does not have, and the plan is checked (D1-D3).
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function planPart(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    $manifest = workspaceManifest($workspace, $state);
    $files = releaseFiles($manifest);
    $deadline = startTimeBudget(TIME_BUDGET);

    $inventory = installedInventory($root);
    $operations = array();
    $cursor = (int)$state['cursor'];
    for ($done = 0; $cursor < count($files) && $done < filesPerRequest(4) && microtime(true) < $deadline; $cursor++, $done++) {
        $path = $files[$cursor];
        $current = currentFile($root . '/' . $path);
        if (hash_equals($manifest['files'][$path], $current['hash'])) {
            continue;
        }
        $operation = array('action' => $current['exists'] ? 'replace' : 'create', 'path' => $path, 'hash' => $manifest['files'][$path])
            + plannedFile($current);
        if ($current['hash'] !== '' && ($keep = keepReason($inventory, $path, $current['hash'])) !== null) {
            $operation['keep'] = $keep;
        }
        $operations[] = $operation;
    }
    appendPlan($workspace, $operations);
    $state['cursor'] = $cursor;
    $state['cursorTotal'] = count($files);
    if ($cursor < count($files)) {
        return $state;
    }

    // The files the release does not have, and the release's own manifest as the last file. A
    // source archive removes nothing, and the manifest of the installed release no longer
    // describes the files, so it goes: the next update keeps every file it replaces.
    $removals = array();
    foreach ($manifest['developer'] ? array() : obsoleteFiles($root, $manifest) as $path) {
        $current = currentFile($root . '/' . $path);
        $removal = array('action' => 'delete', 'path' => $path, 'hash' => '') + plannedFile($current);
        if ($current['hash'] !== '' && ($keep = keepReason($inventory, $path, $current['hash'])) !== null) {
            $removal['keep'] = $keep;
        }
        $removals[] = $removal;
    }
    appendPlan($workspace, $removals);
    $manifestHash = (string)hash_file('sha256', $workspace . '/update-manifest.json');
    $installedManifest = $root . '/' . INSTALLED_MANIFEST;
    if ($manifest['developer']) {
        if (is_file($installedManifest)) {
            appendPlan($workspace, array(array('action' => 'delete', 'path' => INSTALLED_MANIFEST, 'hash' => '')));
        }
    } elseif (!is_file($installedManifest) || !hash_equals($manifestHash, (string)hash_file('sha256', $installedManifest))) {
        appendPlan($workspace, array(array('action' => is_file($installedManifest) ? 'replace' : 'create', 'path' => INSTALLED_MANIFEST,
            'hash' => $manifestHash, 'source' => 'update-manifest.json')));
    }

    $plan = orderPlan(readPlanLines($workspace));
    writeJsonFile($workspace . '/plan.json', $plan);
    @unlink($workspace . '/plan.jsonl');

    $counts = array('create' => 0, 'replace' => 0, 'delete' => 0, 'kept' => 0);
    foreach ($plan as $operation) {
        if ($operation['path'] !== INSTALLED_MANIFEST) {
            $counts[$operation['action']]++;
            $counts['kept'] += isset($operation['keep']) ? 1 : 0;
        }
    }

    // D4: a password for the backup is set, but this PHP cannot encrypt it.
    $checks = planChecks($root, $workspace, $plan);
    $dumpMethod = dumpMethod($ctx, $workspace);
    if ($dumpMethod !== '' && !canEncryptZip() && dumpPassword($ctx) !== '') {
        $checks[] = array('check' => 'D4', 'status' => 'warning', 'text' => 'SYS_CORE_UPDATE_CHECK_DUMP_UNENCRYPTED', 'params' => array());
    }

    return array_merge($state, array(
        'state' => 'review',
        'plan' => $counts + array('unchanged' => count($files) - $counts['create'] - $counts['replace'], 'inventory' => $inventory !== null),
        'planChecks' => $checks,
        'dumpMethod' => $dumpMethod,
        'cursor' => 0,
        'cursorTotal' => 0
    ));
}

/**
 * What is at a path of the installation, from one lstat(): whether anything is there, whether it is
 * a symbolic link, the size and the SHA-256 of a regular file ("" for anything else).
 * @return array{exists: bool, link: bool, size: int, hash: string}
 */
function currentFile(string $file): array
{
    $stat = @lstat($file);
    if ($stat === false) {
        return array('exists' => false, 'link' => false, 'size' => 0, 'hash' => '');
    }
    $type = $stat['mode'] & 0170000;

    return array('exists' => true, 'link' => $type === 0120000, 'size' => (int)$stat['size'],
        'hash' => $type === 0100000 ? (string)hash_file('sha256', $file) : '');
}

/**
 * What the plan keeps about the file an operation replaces or removes, so that the checks of the
 * plan need not look at it again: its size, and whether it is a symbolic link.
 * @param array{exists: bool, link: bool, size: int, hash: string} $current
 * @return array<string,int|bool>
 */
function plannedFile(array $current): array
{
    return array('size' => $current['hash'] !== '' ? $current['size'] : 0) + ($current['link'] ? array('link' => true) : array());
}

/**
 * The SHA-256 of every file of the installed release, from its manifest, or null for a release
 * without one (before the core updater) or a development tree.
 * @return array<string,string>|null
 */
function installedInventory(string $root): ?array
{
    $manifest = is_file($root . '/' . INSTALLED_MANIFEST) ? json_decode((string)file_get_contents($root . '/' . INSTALLED_MANIFEST), true) : null;

    return is_array($manifest['files'] ?? null) ? $manifest['files'] : null;
}

/**
 * Why a file that is replaced or removed goes into the kept-files archive: "modified" when it
 * differs from the original file of the installed release, "unknown" when that release does not
 * have it or has no inventory; null for an original file, which the release can always bring back.
 * @param array<string,string>|null $inventory
 */
function keepReason(?array $inventory, string $path, string $currentHash): ?string
{
    return match (true) {
        $inventory === null, !isset($inventory[$path]) => 'unknown',
        !hash_equals((string)$inventory[$path], $currentHash) => 'modified',
        default => null
    };
}

/**
 * The files in the managed directories that the new release does not have, and the root files
 * that the installed release had and the new one has not. Unknown root files are never touched.
 * @param array<string,mixed> $manifest
 * @return array<int,string>
 */
function obsoleteFiles(string $root, array $manifest): array
{
    $obsolete = array();
    foreach ($manifest['managedDirectories'] as $directory) {
        if (isPreserved($directory, $manifest) || !is_dir($root . '/' . $directory) || is_link($root . '/' . $directory)) {
            continue;
        }
        $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($entries as $entry) {
            $path = substr(str_replace('\\', '/', $entry->getPathname()), strlen($root) + 1);
            if (($entry->isFile() || $entry->isLink()) && !isset($manifest['files'][$path]) && $path !== INSTALLED_MANIFEST
                && !isPreserved($path, $manifest)) {
                $obsolete[] = $path;
            }
        }
    }

    $installed = is_file($root . '/' . INSTALLED_MANIFEST) ? json_decode((string)file_get_contents($root . '/' . INSTALLED_MANIFEST), true) : null;
    foreach (is_array($installed['managedRootFiles'] ?? null) ? $installed['managedRootFiles'] : array() as $file) {
        if (is_string($file) && isSafeRelativePath($file) && !str_contains($file, '/') && !isset($manifest['files'][$file])
            && !isPreserved($file, $manifest) && is_file($root . '/' . $file)) {
            $obsolete[] = $file;
        }
    }

    sort($obsolete, SORT_STRING);

    return array_values(array_unique($obsolete));
}

/**
 * The order of the installation: system/bootstrap/ first, so that even a release that does not
 * know maintenance mode yet shows the maintenance page from the first request on; then the other
 * new and replaced files; then the removals; the manifest of the release last.
 * @param array<int,array<string,string>> $plan
 * @return array<int,array<string,string>>
 */
function orderPlan(array $plan): array
{
    $rank = static fn(array $operation): int => match (true) {
        $operation['path'] === INSTALLED_MANIFEST => 3,
        str_starts_with($operation['path'], 'system/bootstrap/') => 0,
        $operation['action'] === 'delete' => 2,
        default => 1
    };
    usort($plan, static fn(array $a, array $b): int => array($rank($a), $a['path']) <=> array($rank($b), $b['path']));

    return $plan;
}

/**
 * D1: the directories the plan creates can be written; D2: no target is a symbolic link; D3: the
 * free space suffices for the new files and the rollback copy.
 * @param array<int,array<string,string>> $plan
 * @return array<int,array{check: string, status: string, text: string, params: array<int,string>}>
 */
function planChecks(string $root, string $workspace, array $plan): array
{
    $unwritable = array();
    $links = array();
    $needed = 0;
    $probed = array();
    // The directories found to exist, and those found missing, which an update of 6.0 has many of.
    $exists = array();

    foreach ($plan as $operation) {
        $target = $root . '/' . $operation['path'];
        // The plan knows the link and the size of the files it replaces or removes; the manifest
        // of the release is looked at here.
        if (array_key_exists('size', $operation) ? !empty($operation['link']) : is_link($target)) {
            $links[] = $operation['path'];
        }
        if ($operation['action'] !== 'delete') {
            $source = isset($operation['source']) ? $workspace . '/' . $operation['source'] : $workspace . '/extracted/' . $operation['path'];
            $needed += (int)@filesize($source);

            // The nearest directory that exists must take the new file or directory.
            $directory = dirname($target);
            while (!($exists[$directory] ??= is_dir($directory)) && dirname($directory) !== $directory) {
                $directory = dirname($directory);
            }
            if (!isset($probed[$directory])) {
                $probed[$directory] = canCreateAndRename($directory);
                if (!$probed[$directory]) {
                    $unwritable[] = substr(str_replace('\\', '/', $directory), strlen($root) + 1) . '/';
                }
            }
        }
        if ($operation['action'] !== 'create') {
            $needed += array_key_exists('size', $operation) ? (int)$operation['size'] : (is_file($target) ? (int)filesize($target) : 0);
        }
    }

    $checks = array();
    $checks[] = $unwritable !== array()
        ? array('check' => 'D1', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_NOT_WRITABLE', 'params' => array(implode(', ', array_slice($unwritable, 0, 10))))
        : array('check' => 'D1', 'status' => 'ok', 'text' => 'SYS_CORE_UPDATE_CHECK_WRITABLE', 'params' => array());
    if ($links !== array()) {
        $checks[] = array('check' => 'D2', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_SYMLINK', 'params' => array(implode(', ', array_slice($links, 0, 10))));
    }
    $freeSpace = @disk_free_space($root);
    $space = spaceCheck($freeSpace === false ? -1 : (int)$freeSpace, 0, (int)ceil($needed / 2));
    $space['check'] = 'D3';
    $checks[] = $space;

    return $checks;
}

/** @param array<int,array<string,string>> $operations */
function appendPlan(string $workspace, array $operations): void
{
    if ($operations === array()) {
        return;
    }
    $lines = implode('', array_map(static fn(array $operation): string => json_encode($operation, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", $operations));
    if (@file_put_contents($workspace . '/plan.jsonl', $lines, FILE_APPEND | LOCK_EX) !== strlen($lines)) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }
}

/** @return array<int,array<string,string>> */
function readPlanLines(string $workspace): array
{
    $plan = array();
    foreach (is_file($workspace . '/plan.jsonl') ? (array)file($workspace . '/plan.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array() as $line) {
        $plan[] = json_decode((string)$line, true, 8, JSON_THROW_ON_ERROR);
    }

    return $plan;
}

/**
 * The confirmed plan of an update.
 * @return array<int,array<string,string>>
 */
function readPlan(string $workspace): array
{
    $plan = json_decode((string)@file_get_contents($workspace . '/plan.json'), true);
    if (!is_array($plan)) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', 'plan.json cannot be read.');
    }
    foreach ($plan as $operation) {
        if (!is_array($operation) || !in_array($operation['action'] ?? '', array('create', 'replace', 'delete'), true)
            || !isSafeRelativePath((string)($operation['path'] ?? ''))) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', 'plan.json is invalid.');
        }
    }

    return $plan;
}

// ------------------------------------------------------------------------------------------------
// Maintenance mode
// ------------------------------------------------------------------------------------------------

function maintenanceOwner(string $updateId): string
{
    return 'core-update:' . $updateId;
}

/**
 * The file of the maintenance mode, the one MaintenanceMode writes and system/bootstrap/bootstrap.php
 * reads.
 */
function maintenanceFile(string $root): string
{
    return $root . '/adm_my_files/maintenance.json';
}

function isMaintenanceOwner(string $root, string $updateId): bool
{
    $state = json_decode((string)@file_get_contents(maintenanceFile($root)), true);

    return is_array($state) && ($state['owner'] ?? '') === maintenanceOwner($updateId);
}

/**
 * Switch maintenance mode on, in the format of MaintenanceMode::enable(): every request except the
 * updater and install/update.php gets the maintenance page.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function enterMaintenance(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $file = maintenanceFile($root);
    if (is_file($file) && !isMaintenanceOwner($root, $state['updateId'])) {
        throw new UpdaterException('SYS_CORE_UPDATE_MAINTENANCE_OTHER');
    }

    writeJsonFile($file, array(
        'schema' => 1,
        'owner' => maintenanceOwner($state['updateId']),
        'startedAt' => time(),
        'retryAfter' => 120,
        'title' => text($ctx['texts'], 'SYS_CORE_UPDATE_TITLE'),
        'message' => text($ctx['texts'], 'SYS_CORE_UPDATE_MAINTENANCE_MESSAGE'),
        'allowedScripts' => array('install/core-update/index.php', 'install/update.php')
    ));
    @unlink(workspace($root, $state['updateId']) . '/journal.jsonl');

    if (($state['backup'] ?? '') === 'dump') {
        return array_merge($state, array('state' => 'dumping', 'databaseStarted' => false, 'cursor' => 0, 'cursorTotal' => 0));
    }

    return array_merge($state, array('state' => 'installing', 'databaseStarted' => false, 'cursor' => 0,
        'cursorTotal' => count(readPlan(workspace($root, $state['updateId'])))));
}

// ------------------------------------------------------------------------------------------------
// Database backup
// ------------------------------------------------------------------------------------------------

/**
 * How this installation's database can be backed up (see databaseBackupMethod()), with mysqldump-php
 * from the verified package or else from the installed release.
 * @param array<string,mixed> $ctx
 */
function dumpMethod(array $ctx, string $workspace): string
{
    return databaseBackupMethod($ctx['config'], mysqldumpLibrary($ctx['config']['rootPath'], $workspace));
}

/** The file of mysqldump-php: from the verified package, or else from the installed release. */
function mysqldumpLibrary(string $root, string $workspace): string
{
    foreach (array($workspace . '/extracted', $root) as $base) {
        $file = $base . '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';
        if (is_file($file)) {
            return $file;
        }
    }

    return '';
}

/**
 * Back up the next tables of the installation, one file per table, and pack them into one backup
 * in adm_my_files/backups/ at the end (see lib/backup.php).
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function dumpPart(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    // A single large table is one piece of work, which may take longer than the usual budget, so
    // the time limit is generous; new tables are only started within the usual budget.
    startTimeBudget(300);

    $progress = array('cursor' => (int)$state['cursor']) + (isset($state['dumpTables']) ? array('tables' => $state['dumpTables']) : array());
    $done = backUpTables($ctx, (string)$state['dumpMethod'], mysqldumpLibrary($root, $workspace), $workspace . '/dump', $progress,
        microtime(true) + TIME_BUDGET);
    $state['dumpTables'] = $progress['tables'] ?? array();
    $state['cursor'] = (int)($progress['cursor'] ?? 0);
    $state['cursorTotal'] = count($state['dumpTables']);
    if (!$done) {
        return $state;
    }

    // Without support for encryption the review has warned that the backup stays unencrypted.
    $backup = packDatabaseBackup($ctx, $workspace . '/dump', (string)$state['installedVersion']);
    unset($state['dumpTables']);

    return array_merge($state, array('state' => 'installing', 'dumpFile' => $backup['file'], 'dumpEncrypted' => $backup['encrypted'],
        'cursor' => 0, 'cursorTotal' => count(readPlan($workspace))));
}

function leaveMaintenance(string $root, string $updateId): void
{
    if (isMaintenanceOwner($root, $updateId)) {
        @unlink(maintenanceFile($root));
    }
}

// ------------------------------------------------------------------------------------------------
// Installing, verifying, rolling back
// ------------------------------------------------------------------------------------------------

/**
 * Carry out the next operations of the plan. Before a file is replaced or removed it is copied to
 * backup/ in the workspace; the journal records each operation before ("prepared") and after
 * ("done") it, so that an operation cut off halfway is undone and repeated.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function installPart(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    $plan = readPlan($workspace);
    $deadline = startTimeBudget(TIME_BUDGET);

    $journal = readJournal($workspace);
    $journalFile = openJournal($workspace);
    $directories = array();
    $cursor = (int)$state['cursor'];
    try {
        for ($done = 0; $cursor < count($plan) && $done < filesPerRequest() && microtime(true) < $deadline; $cursor++, $done++) {
            if (isset($journal[$cursor]['done'])) {
                continue;
            }
            if (isset($journal[$cursor]['prepared'])) {
                // The request ended within the operation. Its file of the package has been moved
                // into place already if the target is right, otherwise it is still in the workspace.
                if (isOperationComplete($root, $plan[$cursor])) {
                    writeJournal($journalFile, array_merge($journal[$cursor]['prepared'], array('phase' => 'done')));
                    continue;
                }
                undoOperation($root, $workspace, $journal[$cursor]['prepared']);
            }
            performOperation($root, $workspace, $cursor, $plan[$cursor], $journalFile, $directories);
        }
    } finally {
        fclose($journalFile);
    }
    $state['cursor'] = $cursor;
    $state['cursorTotal'] = count($plan);
    if ($cursor < count($plan)) {
        return $state;
    }

    removeEmptiedDirectories($root, $plan);

    return array_merge($state, array('state' => 'archiving', 'cursor' => 0, 'cursorTotal' => 0));
}

/**
 * Put the next kept files into the kept-files archive, from the rollback copy. The archive is a ZIP,
 * so a kept PHP file (perhaps malware) cannot be run, and its random name cannot be guessed.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function archivePart(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    $kept = array_values(array_filter(readPlan($workspace), static fn(array $operation): bool => isset($operation['keep'])));
    $deadline = startTimeBudget(TIME_BUDGET);

    if ($kept !== array()) {
        if (($state['keptArchive'] ?? '') === '') {
            $state['keptArchive'] = backupName('files', $state['installedVersion'] . '_to_' . $state['targetVersion']
                . (isDeveloperChannel((string)$state['channel']) ? '-' . developerSource($state)
                    . (($state['commit'] ?? '') !== '' ? '-' . substr((string)$state['commit'], 0, 7) : '') : ''));
            ensureDirectory(backupsDirectory($root));
        }
        $file = backupFile($root, $state['keptArchive']);
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE) !== true) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $file . ' cannot be created.');
        }

        $cursor = (int)$state['cursor'];
        for ($done = 0; $cursor < count($kept) && $done < filesPerRequest() && microtime(true) < $deadline; $cursor++, $done++) {
            $backup = $workspace . '/backup/' . $kept[$cursor]['path'];
            if (is_file($backup)) {
                $zip->addFile($backup, $kept[$cursor]['path']);
            }
        }
        if ($cursor >= count($kept)) {
            $zip->addFromString('meta.json', json_encode(array(
                'updateId' => $state['updateId'],
                'installedVersion' => $state['installedVersion'],
                'targetVersion' => $state['targetVersion'],
                'channel' => $state['channel'],
                'date' => date('c'),
                'files' => array_map(static fn(array $operation): array => array('path' => $operation['path'], 'reason' => $operation['keep'],
                    'action' => $operation['action'] === 'delete' ? 'removed' : 'replaced'), $kept)
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        if (!$zip->close()) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $file . ' cannot be written.');
        }
        $state['cursor'] = $cursor;
        $state['cursorTotal'] = count($kept);
        if ($cursor < count($kept)) {
            return $state;
        }
    }

    return array_merge($state, array('state' => 'verifying', 'cursor' => 0, 'cursorTotal' => count(releaseFiles(workspaceManifest($workspace, $state)))));
}

/**
 * Replace, create or remove one file. The file that is replaced or removed is moved into the
 * rollback copy, and the verified file of the package is moved into place: a rename is one atomic
 * operation, where a copy reads and writes the whole file, which on a slow file system costs many
 * times more. If the workspace is on another file system, the files are copied instead. The
 * installed files are checked against the manifest by verifyPart(), so they are not hashed here.
 * @param array<string,string> $operation
 * @param resource $journal
 * @param array<string,bool> $directories The directories known to exist in this request.
 */
function performOperation(string $root, string $workspace, int $index, array $operation, $journal, array &$directories): void
{
    $path = $operation['path'];
    $target = $root . '/' . $path;
    $entry = array('index' => $index, 'phase' => 'prepared', 'action' => $operation['action'], 'path' => $path, 'mode' => null,
        'directories' => array());

    $stat = $operation['action'] !== 'create' ? @lstat($target) : false;
    $isFile = $stat !== false && ($stat['mode'] & 0170000) === 0100000;
    if ($isFile) {
        $entry['mode'] = $stat['mode'] & 0777 ?: null;
    }

    // The directories this operation creates, outermost first, so that a rollback removes them.
    for ($directory = dirname($target); !isset($directories[$directory]) && !is_dir($directory); $directory = dirname($directory)) {
        array_unshift($entry['directories'], substr($directory, strlen($root) + 1));
    }
    $directories[$directory] = true;
    writeJournal($journal, $entry);

    if ($isFile) {
        $backup = $workspace . '/backup/' . $path;
        knownDirectory(dirname($backup), $directories);
        moveFile($target, $backup, 'The rollback copy of ' . $path . ' failed.', $path);
    } elseif ($operation['action'] === 'delete' && $stat !== false && !@unlink($target)) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', $path . ' cannot be removed.', array($path));
    }

    if ($operation['action'] !== 'delete') {
        knownDirectory(dirname($target), $directories);
        if (isset($operation['source'])) {
            // A file the workspace still needs, such as the manifest of the release.
            installFile($workspace . '/' . $operation['source'], $target, $operation['hash'], $entry['mode'], $path);
        } else {
            moveFile($workspace . '/extracted/' . $path, $target, $path . ' cannot be written.', $path);
            if ($entry['mode'] !== null) {
                @chmod($target, $entry['mode']);
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($target, true);
            }
        }
    }

    $entry['phase'] = 'done';
    writeJournal($journal, $entry);
}

/**
 * Move a file, or where rename cannot (another file system) copy it next to its target, check it
 * and rename it into place, then remove the original.
 */
function moveFile(string $source, string $target, string $failure, string $path): void
{
    if (@rename($source, $target)) {
        return;
    }

    $hash = is_file($source) ? (string)hash_file('sha256', $source) : '';
    if ($hash === '') {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', $failure, array($path));
    }
    installFile($source, $target, $hash, null, $path);
    @unlink($source);
}

/**
 * Whether an operation that a request began has been carried out completely: the file of the
 * release is in place, or the removed file is gone.
 * @param array<string,string> $operation
 */
function isOperationComplete(string $root, array $operation): bool
{
    $target = $root . '/' . $operation['path'];
    if ($operation['action'] === 'delete') {
        return !file_exists($target) && !is_link($target);
    }

    return is_file($target) && !is_link($target) && hash_equals($operation['hash'], (string)hash_file('sha256', $target));
}

/**
 * Create a directory unless this request already knows it.
 * @param array<string,bool> $directories
 */
function knownDirectory(string $directory, array &$directories): void
{
    if (!isset($directories[$directory])) {
        ensureDirectory($directory);
        $directories[$directory] = true;
    }
}

/**
 * Copy a file to a temporary file next to its target, check it, and rename it into place.
 */
function installFile(string $source, string $target, string $hash, ?int $mode, string $path): void
{
    ensureDirectory(dirname($target));
    $temporary = dirname($target) . '/.' . basename($target) . '.' . bin2hex(random_bytes(4)) . '.update';
    if (!@copy($source, $temporary) || !hash_equals($hash, (string)hash_file('sha256', $temporary))) {
        @unlink($temporary);
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', $path . ' cannot be written.', array($path));
    }
    if ($mode !== null) {
        @chmod($temporary, $mode);
    }
    if (!@rename($temporary, $target)) {
        @unlink($temporary);
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', $path . ' cannot be replaced.', array($path));
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
    }
}

/**
 * Undo one operation of the journal: a created file is removed, a replaced or removed one is
 * restored from the rollback copy.
 * @param array<string,mixed> $entry
 */
function undoOperation(string $root, string $workspace, array $entry): void
{
    $path = (string)$entry['path'];
    $target = $root . '/' . $path;
    if ($entry['action'] === 'create') {
        if (is_file($target) && !@unlink($target)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', $path . ' cannot be removed.', array($path));
        }
    } elseif (is_file($workspace . '/backup/' . $path)) {
        $backup = $workspace . '/backup/' . $path;
        installFile($backup, $target, (string)hash_file('sha256', $backup), isset($entry['mode']) ? (int)$entry['mode'] : null, $path);
    }

    foreach (array_reverse((array)($entry['directories'] ?? array())) as $directory) {
        @rmdir($root . '/' . $directory); // only succeeds when it is empty again
    }
}

/**
 * Hash the next installed files against the manifest. At the end the removed files must be gone,
 * and OPcache is reset, so that no request runs code of the old release.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function verifyPart(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    $manifest = workspaceManifest($workspace, $state);
    $files = releaseFiles($manifest);
    $deadline = startTimeBudget(TIME_BUDGET);

    $cursor = (int)$state['cursor'];
    for ($done = 0; $cursor < count($files) && $done < filesPerRequest(4) && microtime(true) < $deadline; $cursor++, $done++) {
        $path = $files[$cursor];
        if (is_link($root . '/' . $path) || !hash_equals($manifest['files'][$path], (string)@hash_file('sha256', $root . '/' . $path))) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_VERIFY_FAILED', $path . ' does not match the manifest.', array($path));
        }
    }
    $state['cursor'] = $cursor;
    $state['cursorTotal'] = count($files);
    if ($cursor < count($files)) {
        return $state;
    }

    foreach (readPlan($workspace) as $operation) {
        if ($operation['action'] === 'delete' && file_exists($root . '/' . $operation['path'])) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_VERIFY_FAILED', $operation['path'] . ' still exists.', array($operation['path']));
        }
    }
    if (!$manifest['developer'] && !hash_equals((string)hash_file('sha256', $workspace . '/update-manifest.json'), (string)@hash_file('sha256', $root . '/' . INSTALLED_MANIFEST))) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_VERIFY_FAILED', INSTALLED_MANIFEST . ' is not the one of the release.', array(INSTALLED_MANIFEST));
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    saveMigrationPaths($root, $workspace, $manifest);

    // A version without the updater cannot take over the database update: the update ends with
    // its files, and the administrator runs install/update.php.
    if (!empty($state['withoutUpdater'])) {
        leaveMaintenance($root, (string)$state['updateId']);

        return array_merge($state, array('state' => 'completed', 'databaseManual' => true, 'cursor' => 0, 'cursorTotal' => 0));
    }

    return array_merge($state, array('state' => 'files_installed', 'cursor' => 0, 'cursorTotal' => 0));
}

/**
 * Undo the next operations of the journal, the last one first. At the end maintenance mode is
 * switched off and the update is over.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function rollbackPart(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    $deadline = startTimeBudget(TIME_BUDGET);

    $prepared = array();
    foreach (readJournal($workspace) as $index => $phases) {
        if (isset($phases['prepared'])) {
            $prepared[$index] = $phases['prepared'];
        }
    }
    krsort($prepared, SORT_NUMERIC);
    $prepared = array_values($prepared);

    $cursor = (int)$state['cursor'];
    for ($done = 0; $cursor < count($prepared) && $done < filesPerRequest() && microtime(true) < $deadline; $cursor++, $done++) {
        undoOperation($root, $workspace, $prepared[$cursor]);
    }
    $state['cursor'] = $cursor;
    $state['cursorTotal'] = count($prepared);
    if ($cursor < count($prepared)) {
        return $state;
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    if (!empty($state['restoredVersion'])) {
        restoreMigrationPaths($root, $workspace);
    }
    // The files are back where they were, so the archive of them is not needed.
    if (($state['keptArchive'] ?? '') !== '') {
        @unlink(backupFile($root, $state['keptArchive']));
        $state['keptArchive'] = '';
    }
    leaveMaintenance($root, $state['updateId']);
    updateLog($root, $state['updateId'], 'previous files restored, maintenance mode off');
    keepUpdateLog($root, $state);
    // The browser keeps its cookie until it has seen the result (phaseBRolledBack()).
    clearActiveUpdate($root, $state['updateId']);
    foreach (array('extracted', 'backup') as $directory) {
        deleteDirectory($workspace . '/' . $directory);
    }
    @unlink($workspace . '/package.zip');

    return array_merge($state, array('state' => 'rolled_back', 'cursor' => 0, 'cursorTotal' => 0));
}

/**
 * Restore the next tables of the database from the backup of the updater, and at the end remove the
 * tables that the new version created. Then the files are restored (rolling_back). Maintenance mode
 * is on since before the backup, so nothing that was entered since is lost.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function restoreDatabasePart(array $ctx, array $state): array
{
    $config = $ctx['config'];
    $root = $config['rootPath'];
    $pdo = $ctx['pdo'] ?? null;
    if (!$pdo instanceof PDO) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', 'The database cannot be reached.');
    }
    // A single large table is one piece of work, so the time limit is generous.
    startTimeBudget(300);
    $deadline = microtime(true) + TIME_BUDGET;

    $zip = new \ZipArchive();
    if ($zip->open(backupFile($root, $state['dumpFile']), \ZipArchive::RDONLY) !== true) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $state['dumpFile'] . ' cannot be opened.');
    }
    if (!empty($state['dumpEncrypted'])) {
        $zip->setPassword(dumpPassword($ctx));
    }

    try {
        $names = array();
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = (string)$zip->getNameIndex($index);
        }
        sort($names, SORT_STRING);
        $tables = array();

        // An entry that cannot be read - a wrong password - must never count as an empty table.
        $read = static function (string $name) use ($zip, $state): string {
            $content = $zip->getFromName($name);
            $sql = $content === false ? false : gzdecode($content);
            if ($sql === false) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $name . ' of ' . $state['dumpFile'] . ' cannot be read.');
            }

            return $sql;
        };

        if (($state['restoreMethod'] ?? '') === 'psql') {
            $file = workspace($root, $state['updateId']) . '/restore.sql';
            $sql = $read($names[0]);
            writeFile($file, $sql);
            preg_match_all('/^CREATE TABLE (?:\w+\.)?"?(' . preg_quote($config['tablePrefix'], '/') . '_\w+)"?/m', $sql, $matches);
            $tables = $matches[1];
            putenv('PGPASSWORD=' . $config['dbPassword']);
            exec('psql --host=' . escapeshellarg($config['dbHost']) . ($config['dbPort'] !== null ? ' --port=' . (int)$config['dbPort'] : '')
                . ' --username=' . escapeshellarg($config['dbUser']) . ' --dbname=' . escapeshellarg($config['dbName'])
                . ' --set=ON_ERROR_STOP=1 --quiet --file=' . escapeshellarg($file) . ' 2>&1', $output, $status);
            putenv('PGPASSWORD');
            @unlink($file);
            if ($status !== 0) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', 'psql failed: ' . implode(' ', $output));
            }
            $state['cursor'] = $state['cursorTotal'] = 1;
        } else {
            $cursor = (int)$state['cursor'];
            do {
                $name = $names[$cursor] ?? null;
                if ($name === null) {
                    break;
                }
                runSqlScript($pdo, $read($name));
                $cursor++;
            } while (microtime(true) < $deadline);
            $state['cursor'] = $cursor;
            $state['cursorTotal'] = count($names);
            if ($cursor < count($names)) {
                return $state;
            }
            $tables = array_map(static fn(string $name): string => (string)preg_replace('/^\d+_|\.sql\.gz$/', '', $name), $names);
        }
    } finally {
        $zip->close();
    }

    dropTablesExcept($pdo, $config, $tables);
    updateLog($root, $state['updateId'], 'database restored from ' . $state['dumpFile'] . ' (' . count($tables) . ' tables)');

    return array_merge($state, array('state' => 'rolling_back', 'cursor' => 0, 'cursorTotal' => 0));
}

/**
 * Run a script of SQL statements, as a table file of the backup is, and report an error of any of
 * them. Several statements in one query need emulated prepares.
 */
function runSqlScript(PDO $pdo, string $sql): void
{
    $emulated = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $statement = $pdo->query($sql);
        while ($statement !== false && $statement->nextRowset()) {
            // every result is read, so that the error of a later statement is reported
        }
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulated);
    }
}

/**
 * Remove the tables of the installation that the backup does not have: those the new version
 * created before its database update failed.
 * @param array<string,mixed> $config
 * @param array<int,string> $keep
 */
function dropTablesExcept(PDO $pdo, array $config, array $keep): void
{
    $mysql = $config['dbType'] === 'mysql';
    $statement = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ' . ($mysql ? 'DATABASE()' : 'current_schema()')
        . ' AND table_type = ? AND table_name LIKE ?');
    $statement->execute(array('BASE TABLE', str_replace('_', '\\_', $config['tablePrefix']) . '\\_%'));
    $obsolete = array_diff($statement->fetchAll(PDO::FETCH_COLUMN), $keep);

    if ($mysql) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    }
    foreach ($obsolete as $table) {
        if (preg_match('/^\w+$/', (string)$table) === 1) {
            $pdo->exec('DROP TABLE ' . $table . ($mysql ? '' : ' CASCADE'));
        }
    }
    if ($mysql) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

/**
 * Copy the paths that the database update of the release moves or removes (migrationPaths of its
 * manifest, for 6.0 adm_plugins) into the workspace, before install/update.php runs: restoring the
 * previous version needs them, and the journal only knows what the updater changed.
 * @param array<string,mixed> $manifest
 */
function saveMigrationPaths(string $root, string $workspace, array $manifest): void
{
    deleteDirectory($workspace . '/migration');
    $saved = array();
    foreach ($manifest['migrationPaths'] as $path) {
        if (file_exists($root . '/' . $path) && !is_link($root . '/' . $path)) {
            copyPath($root . '/' . $path, $workspace . '/migration/' . $path);
            $saved[] = $path;
        }
    }
    writeJsonFile($workspace . '/migration.json', $saved);
}

/**
 * Put back the paths that saveMigrationPaths() kept, as they were before the database update.
 */
function restoreMigrationPaths(string $root, string $workspace): void
{
    $saved = json_decode((string)@file_get_contents($workspace . '/migration.json'), true);
    foreach (is_array($saved) ? $saved : array() as $path) {
        if (!is_string($path) || !isSafeRelativePath($path)) {
            continue;
        }
        if (is_dir($root . '/' . $path) && !is_link($root . '/' . $path)) {
            deleteDirectory($root . '/' . $path);
        } else {
            @unlink($root . '/' . $path);
        }
        copyPath($workspace . '/migration/' . $path, $root . '/' . $path);
    }
}

/** Copy a file or a directory with its content. */
function copyPath(string $source, string $target): void
{
    if (is_file($source)) {
        ensureDirectory(dirname($target));
        if (!@copy($source, $target)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $target . ' cannot be written.');
        }

        return;
    }
    ensureDirectory($target);
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $destination = $target . '/' . substr(str_replace('\\', '/', $entry->getPathname()), strlen(str_replace('\\', '/', $source)) + 1);
        if ($entry->isDir()) {
            ensureDirectory($destination);
        } elseif (!@copy($entry->getPathname(), $destination)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $destination . ' cannot be written.');
        }
    }
}

/**
 * Remove the directories that the removals left empty. Directories that were empty before stay.
 * @param array<int,array<string,string>> $plan
 */
function removeEmptiedDirectories(string $root, array $plan): void
{
    $directories = array();
    foreach ($plan as $operation) {
        if ($operation['action'] === 'delete') {
            for ($directory = dirname($operation['path']); $directory !== '.' && $directory !== ''; $directory = dirname($directory)) {
                $directories[$directory] = true;
            }
        }
    }

    // The deepest first, so that a parent can follow its last child.
    $directories = array_keys($directories);
    usort($directories, static fn(string $a, string $b): int => substr_count($b, '/') <=> substr_count($a, '/'));
    foreach ($directories as $directory) {
        if (is_dir($root . '/' . $directory) && !is_link($root . '/' . $directory)) {
            @rmdir($root . '/' . $directory); // only succeeds when it is empty
        }
    }
}

/**
 * @param array<string,mixed> $entry
 */
function appendJournal(string $workspace, array $entry): void
{
    $journal = openJournal($workspace);
    try {
        writeJournal($journal, $entry);
    } finally {
        fclose($journal);
    }
}

/**
 * Open the journal for the entries of one request. The requests of an update are serialised by
 * its lock, so one handle can serve all of them.
 * @return resource
 */
function openJournal(string $workspace)
{
    $journal = @fopen($workspace . '/journal.jsonl', 'ab');
    if ($journal === false) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }

    return $journal;
}

/**
 * Write an entry of the journal before the step it records goes on: a request that ends at any
 * point leaves at most its last line incomplete.
 * @param resource $journal
 * @param array<string,mixed> $entry
 */
function writeJournal($journal, array $entry): void
{
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (fwrite($journal, $line) !== strlen($line) || !fflush($journal)) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }
}

/**
 * The journal by operation index and phase. A request cut off while writing can only have left
 * the last line incomplete, which is ignored.
 * @return array<int,array<string,array<string,mixed>>>
 */
function readJournal(string $workspace): array
{
    $journal = array();
    $lines = is_file($workspace . '/journal.jsonl') ? (array)file($workspace . '/journal.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
    foreach ($lines as $number => $line) {
        $entry = json_decode((string)$line, true);
        if (!is_array($entry) || !isset($entry['index'], $entry['phase'], $entry['action'], $entry['path'])) {
            if ($number === count($lines) - 1) {
                break;
            }
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', 'journal.jsonl is invalid in line ' . ($number + 1) . '.');
        }
        $journal[(int)$entry['index']][(string)$entry['phase']] = $entry;
    }

    return $journal;
}
