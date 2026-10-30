<?php
/**
 * Phase B of an update: from the verified package to installed and verified files. It runs in the
 * unpacked updater of the new release, which phase A extracted (see entry.php), and
 * installs every file of the release, this updater included, so that a rollback restores the old
 * updater as well.
 *
 * prepared     - phase A has extracted and checked the package
 * planning     - every file of the installation is compared with the new release: what is created,
 *                replaced and removed; checks D1-D3 and D5
 * review       - the administrator sees the plan and starts the installation
 * ready        - the plan is confirmed, with the choice of the database backup
 * dumping      - maintenance mode on; the database is backed up into the directory of the backups
 * installing   - system/bootstrap/ first, then the rest; every replaced or
 *                removed file is copied to the workspace first and every step is journaled
 * archiving    - the replaced and removed files that were changed or unknown go into the kept-files
 *                archive in the directory of the backups, which is never removed automatically
 * verifying    - every installed file is hashed again; then OPcache is reset
 * files_installed - install/update.php takes over and updates the database (phase C)
 *
 * runStep() in process.php does the work of each of these states, one request after the other.
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
const ALWAYS_PRESERVED = array('adm_my_files', 'config.php', 'custom_css.css', 'custom_favicon.png',
    'custom_logo.png', '.htaccess', '.user.ini', 'robots.txt');

/** The inventory of an installation: the manifest of its release. */
const INSTALLED_MANIFEST = 'install/update-manifest.json';

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
        // A migration that holds its lock but has not yet recorded its start may be about to.
        if (!in_array($state['state'], array('installing', 'archiving', 'verifying', 'rollback_required', 'files_installed'), true)
            || !empty($state['databaseStarted']) || isDatabaseUpdateRunning($root, $updateId)) {
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
    if (($state['dumpFile'] ?? '') === '' || !is_file($file = updateBackupFile($ctx['config']['rootPath'], $state, (string)$state['dumpFile']))) {
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
// The release
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
 * the preserved paths.
 * @param array<string,mixed> $manifest
 * @return array<int,string>
 */
function releaseFiles(array $manifest): array
{
    $files = array_values(array_filter(array_keys($manifest['files']), static fn(string $path): bool => !isPreserved($path, $manifest)));
    sort($files, SORT_STRING);

    return $files;
}

// ------------------------------------------------------------------------------------------------
// Planning
// ------------------------------------------------------------------------------------------------

/**
 * Start the plan of the installation with the package that phase A has extracted.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function startPlanning(array $ctx, array $state): array
{
    $workspace = workspace($ctx['config']['rootPath'], $state['updateId']);
    @unlink($workspace . '/plan.jsonl');

    return array_merge($state, array('state' => 'planning', 'planStep' => 'files', 'cursor' => 0,
        'cursorTotal' => count(releaseFiles(workspaceManifest($workspace, $state)))));
}

/**
 * Compare the next files of the installation with the new release. The files of the release are
 * compared by hash; a file that is already right gets no operation. When all are done, the managed
 * directories are scanned for files the release does not have, and the plan is checked (D1-D3, D5).
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
    // D5: the backups can be written. The update keeps this directory, also if the preference is
    // changed before it ends, so that its backup is found for a restore.
    $backupsDirectory = configuredBackupsDirectory($ctx);
    if (!canCreateAndRename($backupsDirectory)) {
        $checks[] = array('check' => 'D5', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_BACKUPS_NOT_WRITABLE',
            'params' => array(displayPath($root, $backupsDirectory)));
    }

    return array_merge($state, array(
        'state' => 'review',
        'plan' => $counts + array('unchanged' => count($files) - $counts['create'] - $counts['replace'], 'inventory' => $inventory !== null),
        'planChecks' => $checks,
        'dumpMethod' => $dumpMethod,
        'backupsDirectory' => $backupsDirectory,
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
 * The files in the managed directories that the new release does not have, the root files
 * that the installed release had and the new one has not, and the code of Admidio 4.x (LEGACY_PATHS).
 * Unknown root files are never touched.
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

    foreach (hasLegacyCode($root) ? LEGACY_PATHS : array() as $legacy) {
        if (is_link($root . '/' . $legacy)) {
            continue;
        }
        $entries = is_dir($root . '/' . $legacy)
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $legacy, \FilesystemIterator::SKIP_DOTS))
            : (is_file($root . '/' . $legacy) ? array(new \SplFileInfo($root . '/' . $legacy)) : array());
        foreach ($entries as $entry) {
            $path = substr(str_replace('\\', '/', $entry->getPathname()), strlen($root) + 1);
            if (($entry->isFile() || $entry->isLink()) && !isset($manifest['files'][$path]) && !isPreserved($path, $manifest)) {
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
 * The directory of the backups of an update, which its plan has recorded.
 * @param array<string,mixed> $state
 */
function updateBackupsDirectory(string $root, array $state): string
{
    return backupsDirectory($root, (string)($state['backupsDirectory'] ?? ''));
}

/**
 * The path of a backup of an update.
 * @param array<string,mixed> $state
 * @throws UpdaterException The name is not the name of a backup.
 */
function updateBackupFile(string $root, array $state, string $name): string
{
    return backupFile(updateBackupsDirectory($root, $state), $name);
}

/**
 * Where a backup of an update is, as the administrator is shown it.
 * @param array<string,mixed> $state
 */
function updateBackupDisplayPath(string $root, array $state, string $name): string
{
    return displayPath($root, updateBackupsDirectory($root, $state) . '/' . $name);
}

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
 * in the directory of the backups at the end (see lib/backup.php).
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
    $backup = packDatabaseBackup($ctx, $workspace . '/dump', updateBackupsDirectory($root, $state), (string)$state['installedVersion']);
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
            ensureDirectory(updateBackupsDirectory($root, $state));
        }
        $file = updateBackupFile($root, $state, $state['keptArchive']);
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
        @unlink(updateBackupFile($root, $state, $state['keptArchive']));
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
    if ($zip->open(updateBackupFile($root, $state, $state['dumpFile']), \ZipArchive::RDONLY) !== true) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $state['dumpFile'] . ' cannot be opened.');
    }
    if (!empty($state['dumpEncrypted'])) {
        $zip->setPassword(dumpPassword($ctx));
    }

    // Each file of the backup is unpacked and run line by line, so that neither a large table nor
    // the dump of pg_dump has to fit into the memory of PHP or into one packet of the database.
    $directory = workspace($root, $state['updateId']) . '/restore';
    try {
        $names = array();
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = (string)$zip->getNameIndex($index);
        }
        sort($names, SORT_STRING);
        $tables = array();

        if (($state['restoreMethod'] ?? '') === 'psql') {
            $file = $directory . '/restore.sql';
            $tables = unpackSqlFile(extractBackupEntry($zip, $names[0] ?? '', $directory, $state['dumpFile']), $file, $config['tablePrefix']);
            putenv('PGPASSWORD=' . $config['dbPassword']);
            exec('psql --host=' . escapeshellarg($config['dbHost']) . ($config['dbPort'] !== null ? ' --port=' . (int)$config['dbPort'] : '')
                . ' --username=' . escapeshellarg($config['dbUser']) . ' --dbname=' . escapeshellarg($config['dbName'])
                . ' --set=ON_ERROR_STOP=1 --quiet --file=' . escapeshellarg($file) . ' 2>&1', $output, $status);
            putenv('PGPASSWORD');
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
                runSqlFile($pdo, extractBackupEntry($zip, $name, $directory, $state['dumpFile']), $name . ' of ' . $state['dumpFile']);
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
        deleteDirectory($directory);
    }

    dropTablesExcept($pdo, $config, $tables);
    updateLog($root, $state['updateId'], 'database restored from ' . $state['dumpFile'] . ' (' . count($tables) . ' tables)');

    return array_merge($state, array('state' => 'rolling_back', 'cursor' => 0, 'cursorTotal' => 0));
}

/**
 * Unpack one file of a backup into $directory, without reading it into memory. A file that cannot
 * be unpacked - a wrong password - is an error, never an empty table.
 * @return string The unpacked file.
 */
function extractBackupEntry(\ZipArchive $zip, string $name, string $directory, string $backup): string
{
    if (preg_match('/^[A-Za-z0-9._-]+\.sql\.gz$/', $name) !== 1) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $backup . ' contains no table file "' . $name . '".');
    }
    ensureDirectory($directory);
    $file = $directory . '/' . $name;
    @unlink($file);
    if (!$zip->extractTo($directory, $name) || !is_file($file)) {
        @unlink($file);
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $name . ' of ' . $backup . ' cannot be read.');
    }

    return $file;
}

/**
 * Run the statements of a table file of the backup one after the other, as mysqldump-php writes
 * them: a statement ends with a line that ends with ";", and a value never spans lines, because its
 * line breaks are escaped. Only one statement is in memory at a time. A file without the table, a
 * damaged one for example, is an error.
 */
function runSqlFile(PDO $pdo, string $file, string $description): void
{
    $handle = @gzopen($file, 'rb');
    if ($handle === false) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $description . ' cannot be read.');
    }

    $statement = '';
    $created = false;
    try {
        while (($line = gzgets($handle)) !== false) {
            if ($statement === '' && (trim($line) === '' || str_starts_with($line, '--'))) {
                continue;
            }
            $statement .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $created = $created || str_starts_with($statement, 'CREATE TABLE');
                $pdo->exec($statement);
                $statement = '';
            }
        }
    } finally {
        gzclose($handle);
    }

    if (!$created || trim($statement) !== '') {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', $description . ' is incomplete.');
    }
}

/**
 * Unpack the backup of pg_dump into a file for psql, line by line, and find the tables of the
 * installation it creates.
 * @return array<int,string> The tables.
 */
function unpackSqlFile(string $file, string $target, string $tablePrefix): array
{
    $source = @gzopen($file, 'rb');
    $output = @fopen($target, 'wb');
    if ($source === false || $output === false) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', basename($file) . ' cannot be unpacked.');
    }

    $tables = array();
    try {
        while (($line = gzgets($source)) !== false) {
            if (fwrite($output, $line) !== strlen($line)) {
                throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
            }
            if (preg_match('/^CREATE TABLE (?:\w+\.)?"?(' . preg_quote($tablePrefix, '/') . '_\w+)"?/', $line, $matches) === 1) {
                $tables[] = $matches[1];
            }
        }
    } finally {
        gzclose($source);
        fclose($output);
    }

    return $tables;
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
