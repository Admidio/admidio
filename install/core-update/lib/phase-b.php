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
 * ready        - the plan is confirmed
 * installing   - maintenance mode on; system/bootstrap/ first, then the rest; every replaced or
 *                removed file is copied to the workspace first and every step is journaled
 * verifying    - every installed file is hashed again; then OPcache is reset
 * files_installed - install/update.php takes over and updates the database (phase C)
 *
 * Up to review nothing is changed, and cancelling removes the workspace. From installing on, an
 * error stops in rollback_required: the administrator continues the update or restores the old
 * files (rolling_back), which is possible until the database update starts.
 *
 * Every function gets the context of phase A (see phase-a.php).
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use Throwable;

const PHASE_B_STATES = array('prepared', 'extracting', 'planning', 'review', 'ready', 'installing', 'verifying',
    'rollback_required', 'rolling_back');

/** The states in which install/update.php has the update. */
const PHASE_C_STATES = array('files_installed', 'database_running', 'database_failed');

/** Files per request, besides the time budget. */
const FILES_PER_REQUEST = 200;

/** Never written, scanned or removed, whatever a manifest says. */
const ALWAYS_PRESERVED = array('adm_my_files', 'install/core-update', 'config.php', 'custom_css.css', 'custom_favicon.png',
    'custom_logo.png', '.htaccess', '.user.ini', 'robots.txt');

/** The inventory of an installation: the manifest of its release. */
const INSTALLED_MANIFEST = 'install/update-manifest.json';

/**
 * Do the next bounded piece of work of phase B.
 * @param array<string,mixed> $ctx
 * @return array{status: string, message: string, progress: int, state: string, url?: string}
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
                'installing' => installPart($ctx, $state),
                'verifying' => verifyPart($ctx, $state),
                'rolling_back' => rollbackPart($ctx, $state),
                // Continue where the error stopped the update.
                'rollback_required' => array_merge($state, array('state' => (string)($state['failedIn'] ?? 'installing'))),
                default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
            };
        } catch (VerificationFailure $failure) {
            // Only extracting and planning verify, before anything was changed.
            abortUpdate($ctx, $updateId);

            return array('status' => 'failed', 'message' => describeError($ctx, $failure->getPrevious()), 'progress' => 0, 'state' => 'failed');
        } catch (Throwable $exception) {
            $state['lastError'] = describeError($ctx, $exception instanceof UpdaterException
                ? $exception : UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', (string)$exception));
            if (in_array($before, array('installing', 'verifying'), true) || ($before === 'ready' && isMaintenanceOwner($root, $updateId))) {
                // Files may have changed: only continuing or restoring is left. A failed rollback
                // or a failed check before maintenance mode is simply repeated.
                $state['failedIn'] = $before === 'ready' ? 'ready' : $before;
                $state['state'] = 'rollback_required';
            }
            writeState($root, $state);

            return array('status' => 'error', 'message' => $state['lastError'], 'progress' => phaseBProgress($state), 'state' => $state['state']);
        }

        $state['lastError'] = '';
        writeState($root, $state);

        return phaseBResult($ctx, $state);
    }, false);
}

/**
 * The answer to the page for the state an update has reached.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array{status: string, message: string, progress: int, state: string, url?: string}
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
        case 'installing':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_FILES_INSTALLING');
            break;
        case 'verifying':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_VERIFYING');
            break;
        case 'rolling_back':
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_ROLLING_BACK');
            break;
        case 'rolled_back':
            $result['status'] = 'rolled_back';
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_FILESYSTEM_ROLLED_BACK');
            break;
        case 'files_installed':
            $result['status'] = 'redirect';
            $result['message'] = text($texts, 'SYS_CORE_UPDATE_FILES_INSTALLED_DB_START');
            $result['url'] = databaseUpdateUrl($ctx['config'], (string)$state['updateId']);
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
        'installing' => $part(30, 85),
        'verifying' => $part(85, 95),
        'rollback_required' => 30,
        'rolling_back' => 100 - $part(0, 100),
        'files_installed', 'database_running', 'database_failed' => 95,
        'completed' => 100,
        default => 0
    };
}

/**
 * Start the installation after the administrator saw the plan. The browser gets the updater
 * cookie here, because from maintenance mode on the Admidio login cannot be used.
 * @param array<string,mixed> $ctx
 * @return array<string,mixed>
 */
function phaseBConfirm(array $ctx, string $updateId): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId): array {
        $state = readState($root, $updateId);
        if ($state['state'] !== 'review' || checksHaveErrors((array)($state['planChecks'] ?? array()))) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $cookie = issueCredential($state, time());
        $state = array_merge($state, array('state' => 'ready', 'lastError' => ''));
        writeState($root, $state);
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
        if (!in_array($state['state'], array('installing', 'verifying', 'rollback_required', 'files_installed'), true) || !empty($state['databaseStarted'])) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $state = array_merge($state, array('state' => 'rolling_back', 'cursor' => 0, 'cursorTotal' => 0, 'lastError' => ''));
        writeState($root, $state);

        return $state;
    }, false);
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

        clearActiveUpdate($root, $updateId);
        deleteDirectory(workspace($root, $updateId));
        clearCredentialCookie($ctx['config']);
    });
}

/** @param array<string,mixed> $config */
function databaseUpdateUrl(array $config, string $updateId): string
{
    return $config['baseUrl'] . '/install/update.php?core_update=' . rawurlencode($updateId);
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
    $release = workspaceRelease($workspace, $state);
    if (!hash_equals($release['packageSha256'], (string)hash_file('sha256', $workspace . '/package.zip'))) {
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
        for ($done = 0; $cursor < count($indexes) && $done < FILES_PER_REQUEST && microtime(true) < $deadline; $cursor++, $done++) {
            $path = $package['entries'][$indexes[$cursor]];
            if ($path === INSTALLED_MANIFEST || isPreserved($path, $manifest)) {
                continue;
            }
            if (!isset($manifest['files'][$path])) {
                throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest does not list ' . $path . '.'));
            }

            $target = $workspace . '/extracted/' . $path;
            ensureDirectory(dirname($target));
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
    } finally {
        $package['zip']->close();
    }

    if ($state['cursor'] < $state['cursorTotal']) {
        return $state;
    }

    foreach (releaseFiles($manifest) as $path) {
        if (!is_file($workspace . '/extracted/' . $path)) {
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

    $operations = array();
    $cursor = (int)$state['cursor'];
    for ($done = 0; $cursor < count($files) && $done < 4 * FILES_PER_REQUEST && microtime(true) < $deadline; $cursor++, $done++) {
        $path = $files[$cursor];
        $target = $root . '/' . $path;
        if (is_file($target) && !is_link($target) && hash_equals($manifest['files'][$path], (string)hash_file('sha256', $target))) {
            continue;
        }
        $operations[] = array('action' => file_exists($target) || is_link($target) ? 'replace' : 'create', 'path' => $path,
            'hash' => $manifest['files'][$path]);
    }
    appendPlan($workspace, $operations);
    $state['cursor'] = $cursor;
    $state['cursorTotal'] = count($files);
    if ($cursor < count($files)) {
        return $state;
    }

    // The files the release does not have, and the release's own manifest as the last file.
    appendPlan($workspace, array_map(static fn(string $path): array => array('action' => 'delete', 'path' => $path, 'hash' => ''),
        obsoleteFiles($root, $manifest)));
    $manifestHash = (string)hash_file('sha256', $workspace . '/update-manifest.json');
    $installedManifest = $root . '/' . INSTALLED_MANIFEST;
    if (!is_file($installedManifest) || !hash_equals($manifestHash, (string)hash_file('sha256', $installedManifest))) {
        appendPlan($workspace, array(array('action' => is_file($installedManifest) ? 'replace' : 'create', 'path' => INSTALLED_MANIFEST,
            'hash' => $manifestHash, 'source' => 'update-manifest.json')));
    }

    $plan = orderPlan(readPlanLines($workspace));
    writeJsonFile($workspace . '/plan.json', $plan);
    @unlink($workspace . '/plan.jsonl');

    $counts = array('create' => 0, 'replace' => 0, 'delete' => 0);
    foreach ($plan as $operation) {
        if ($operation['path'] !== INSTALLED_MANIFEST) {
            $counts[$operation['action']]++;
        }
    }

    return array_merge($state, array(
        'state' => 'review',
        'plan' => $counts + array('unchanged' => count($files) - $counts['create'] - $counts['replace']),
        'planChecks' => planChecks($root, $workspace, $plan),
        'cursor' => 0,
        'cursorTotal' => 0
    ));
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

    foreach ($plan as $operation) {
        $target = $root . '/' . $operation['path'];
        if (is_link($target)) {
            $links[] = $operation['path'];
        }
        if ($operation['action'] !== 'delete') {
            $source = isset($operation['source']) ? $workspace . '/' . $operation['source'] : $workspace . '/extracted/' . $operation['path'];
            $needed += (int)@filesize($source);

            // The nearest directory that exists must take the new file or directory.
            $directory = dirname($target);
            while (!is_dir($directory) && dirname($directory) !== $directory) {
                $directory = dirname($directory);
            }
            if (!isset($probed[$directory])) {
                $probed[$directory] = canCreateAndRename($directory);
                if (!$probed[$directory]) {
                    $unwritable[] = substr(str_replace('\\', '/', $directory), strlen($root) + 1) . '/';
                }
            }
        }
        if ($operation['action'] !== 'create' && is_file($target)) {
            $needed += (int)filesize($target);
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

function isMaintenanceOwner(string $root, string $updateId): bool
{
    $state = json_decode((string)@file_get_contents($root . '/adm_my_files/maintenance.json'), true);

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
    $file = $root . '/adm_my_files/maintenance.json';
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

    return array_merge($state, array('state' => 'installing', 'databaseStarted' => false, 'cursor' => 0,
        'cursorTotal' => count(readPlan(workspace($root, $state['updateId'])))));
}

function leaveMaintenance(string $root, string $updateId): void
{
    if (isMaintenanceOwner($root, $updateId)) {
        @unlink($root . '/adm_my_files/maintenance.json');
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
    $cursor = (int)$state['cursor'];
    for ($done = 0; $cursor < count($plan) && $done < FILES_PER_REQUEST && microtime(true) < $deadline; $cursor++, $done++) {
        if (isset($journal[$cursor]['done'])) {
            continue;
        }
        if (isset($journal[$cursor]['prepared'])) {
            undoOperation($root, $workspace, $journal[$cursor]['prepared']);
        }
        performOperation($root, $workspace, $cursor, $plan[$cursor]);
    }
    $state['cursor'] = $cursor;
    $state['cursorTotal'] = count($plan);
    if ($cursor < count($plan)) {
        return $state;
    }

    removeEmptiedDirectories($root, $plan);

    return array_merge($state, array('state' => 'verifying', 'cursor' => 0, 'cursorTotal' => count(releaseFiles(workspaceManifest($workspace, $state)))));
}

/**
 * @param array<string,string> $operation
 */
function performOperation(string $root, string $workspace, int $index, array $operation): void
{
    $path = $operation['path'];
    $target = $root . '/' . $path;
    $entry = array('index' => $index, 'phase' => 'prepared', 'action' => $operation['action'], 'path' => $path, 'mode' => null,
        'directories' => array());

    if ($operation['action'] !== 'create' && is_file($target)) {
        $backup = $workspace . '/backup/' . $path;
        ensureDirectory(dirname($backup));
        if (!@copy($target, $backup) || !hash_equals((string)hash_file('sha256', $target), (string)hash_file('sha256', $backup))) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', 'The rollback copy of ' . $path . ' failed.', array($path));
        }
        $entry['mode'] = @fileperms($target) & 0777 ?: null;
    }

    // The directories this operation creates, outermost first, so that a rollback removes them.
    for ($directory = dirname($target); !is_dir($directory); $directory = dirname($directory)) {
        array_unshift($entry['directories'], substr($directory, strlen($root) + 1));
    }
    appendJournal($workspace, $entry);

    if ($operation['action'] === 'delete') {
        if (file_exists($target) && !@unlink($target)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILE_FAILED', $path . ' cannot be removed.', array($path));
        }
    } else {
        $source = isset($operation['source']) ? $workspace . '/' . $operation['source'] : $workspace . '/extracted/' . $path;
        installFile($source, $target, $operation['hash'], $entry['mode'], $path);
    }

    $entry['phase'] = 'done';
    appendJournal($workspace, $entry);
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
    for ($done = 0; $cursor < count($files) && $done < 4 * FILES_PER_REQUEST && microtime(true) < $deadline; $cursor++, $done++) {
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
    if (!hash_equals((string)hash_file('sha256', $workspace . '/update-manifest.json'), (string)@hash_file('sha256', $root . '/' . INSTALLED_MANIFEST))) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_VERIFY_FAILED', INSTALLED_MANIFEST . ' is not the one of the release.', array(INSTALLED_MANIFEST));
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
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
    for ($done = 0; $cursor < count($prepared) && $done < FILES_PER_REQUEST && microtime(true) < $deadline; $cursor++, $done++) {
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
    leaveMaintenance($root, $state['updateId']);
    revokeCredentials($state);
    clearActiveUpdate($root, $state['updateId']);
    clearCredentialCookie($ctx['config']);
    foreach (array('extracted', 'backup') as $directory) {
        deleteDirectory($workspace . '/' . $directory);
    }
    @unlink($workspace . '/package.zip');

    return array_merge($state, array('state' => 'rolled_back', 'cursor' => 0, 'cursorTotal' => 0));
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
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (@file_put_contents($workspace . '/journal.jsonl', $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
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
