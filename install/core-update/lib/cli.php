<?php
/**
 * The command line of the updater: apply and rollback drive the same functions as the page of the
 * browser, from one decision to the next. install/core-update/cli.php reads the arguments, prints
 * and asks; the functions here decide what happens next.
 *
 * Where the page loads a new page, the command line needs a new process: after phase A the updater
 * of the new release continues ("restart"), because this process has the old one loaded, and the
 * database is updated by the code of the new release ("database"). A function asks for such a
 * process by returning it as "next", and cli.php starts it.
 *
 * Like the Admidio command line, it answers in English; the texts of the updater are its English
 * ones.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use PDO;

/** The exit codes of the Admidio command line. */
const EXIT_SUCCESS = 0;
const EXIT_ERROR = 1;
const EXIT_USAGE = 2;
const EXIT_REJECTED = 5;
const EXIT_FAILED = 6;

/** What a run with --on-changed does with files that were changed or do not belong to the installed release. */
const ON_CHANGED = array('archive', 'cancel');

/**
 * The context of phase A and B for the command line.
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function commandContext(array $config, string $updaterPath): array
{
    try {
        $pdo = connectDatabase($config);
    } catch (UpdaterException) {
        // The update itself does not need the database; the checks say what is missing.
        $pdo = null;
    }

    return array(
        'config' => $config,
        'pdo' => $pdo instanceof PDO ? $pdo : null,
        'texts' => loadTexts($updaterPath, $config['rootPath'], 'en'),
        'updaterPath' => $updaterPath,
        'http' => array('plainHttpBase' => $config['serverUrl']),
        'chunkSize' => CHUNK_SIZE
    );
}

/**
 * Install an update, or continue the unfinished one with resume, until it is done or needs a
 * process of its own.
 * @param array<string,mixed> $ctx
 * @param array{channel: string, ref: string, backup: string, onChanged: string, dryRun: bool, skipDatabase: bool,
 *              resume: bool, databaseTried: bool} $options databaseTried: the database process has run for this update.
 * @param callable(string): void $out Prints a line of progress.
 * @param callable(string): bool $confirm Asks before something is changed.
 * @param string|null $updateId The update this process continues; otherwise the active one.
 * @return array{exit: int, next: string, message: string, updateId: string, state: array<string,mixed>}
 */
function commandApply(array $ctx, array $options, callable $out, callable $confirm, ?string $updateId = null): array
{
    $root = $ctx['config']['rootPath'];
    $texts = $ctx['texts'];
    // An update whose check has not been confirmed has changed nothing: a new run checks again
    // with its own channel. Every other update is only continued on request.
    $check = !$options['resume'];

    if ($updateId === null) {
        $updateId = activeUpdateId($root);
        if ($updateId === null) {
            if ($options['resume']) {
                return commandResult(EXIT_REJECTED, 'No core update is in progress.');
            }
            $updateId = (string)createUpdate($root, array('installedVersion' => filesVersion($root)))['updateId'];
        }

        $state = readState($root, $updateId);
        if (!$options['resume'] && !in_array((string)$state['state'], array('new', 'checking', 'checked'), true)) {
            return commandResult(EXIT_REJECTED, 'The update to ' . commandTarget($state) . ' is unfinished (state ' . $state['state'] . ').'
                . ' Continue it with --resume, or undo it with rollback.', $state);
        }
    }

    $phaseA = false;
    $progress = array('message' => '', 'progress' => -100);
    while (true) {
        $state = readState($root, $updateId);

        switch ($state['state']) {
            case 'new':
            case 'checking':
            case 'checked':
                if ($state['state'] !== 'checked' || $check) {
                    $check = false;
                    $state = phaseACheck($ctx, $updateId, $options['channel'], $options['ref']);
                    if ($state['state'] !== 'checked') {
                        abortUpdate($ctx, $updateId);

                        return commandResult(EXIT_FAILED, (string)$state['lastError'], $state);
                    }
                }

                $checks = (array)($state['checks'] ?? array());
                $out(commandCheckReport($state, $texts));
                if (checksHaveErrors($checks)) {
                    abortUpdate($ctx, $updateId);

                    return commandResult(EXIT_REJECTED, 'The update cannot be installed: a check failed.', $state);
                }
                if (!$confirm('Download and verify Admidio ' . commandTarget($state) . '?')) {
                    abortUpdate($ctx, $updateId);

                    return commandResult(EXIT_FAILED, text($texts, 'SYS_CORE_UPDATE_CANCELLED'), $state);
                }
                phaseAConfirm($ctx, $updateId);
                $phaseA = true;
                break;

            case 'downloading':
            case 'downloaded':
                $phaseA = true;
                $result = phaseARun($ctx, $updateId);
                commandProgress($result, $out, $progress);
                if ($result['status'] === 'failed') {
                    return commandResult(EXIT_FAILED, $result['message'], $state);
                }
                if ($result['status'] === 'error') {
                    return commandResult(EXIT_FAILED, $result['message'] . ' Continue the download with --resume.', readState($root, $updateId));
                }
                break;

            case 'review':
                $out(commandPlanReport($ctx, $state));
                $plan = (array)($state['plan'] ?? array());
                if (checksHaveErrors((array)($state['planChecks'] ?? array()))) {
                    phaseBCancel($ctx, $updateId);

                    return commandResult(EXIT_REJECTED, 'The update cannot be installed: a check failed.', $state);
                }
                if ($options['dryRun']) {
                    phaseBCancel($ctx, $updateId);

                    return commandResult(EXIT_SUCCESS, 'Dry run: the update was checked and planned. Nothing was installed.', $state);
                }
                if ((int)($plan['kept'] ?? 0) > 0 && $options['onChanged'] === 'cancel') {
                    phaseBCancel($ctx, $updateId);

                    return commandResult(EXIT_REJECTED, 'The update was cancelled, as --on-changed=cancel asks: ' . (int)$plan['kept']
                        . ' files were changed or do not belong to the installed version.', $state);
                }

                $canDump = (string)($state['dumpMethod'] ?? '') !== '';
                $backup = $options['backup'] !== '' ? $options['backup'] : ($canDump ? 'dump' : '');
                if ($backup === '' || ($backup === 'dump' && !$canDump)) {
                    return commandResult(EXIT_REJECTED, text($texts, 'SYS_CORE_UPDATE_BACKUP_NOT_POSSIBLE')
                        . ' Then continue with --resume --backup=own.', $state);
                }
                if (!$confirm('Install Admidio ' . commandTarget($state) . ' now? '
                    . ($backup === 'dump' ? 'The updater backs up the database first.' : 'You have confirmed your own backup of the database.'))) {
                    phaseBCancel($ctx, $updateId);

                    return commandResult(EXIT_FAILED, text($texts, 'SYS_CORE_UPDATE_CANCELLED'), $state);
                }
                phaseBConfirm($ctx, $updateId, $backup);
                break;

            case 'prepared':
                if ($phaseA) {
                    // Phase B runs in the updater that phase A has just installed.
                    return commandResult(EXIT_SUCCESS, '', $state, 'restart');
                }
                // no break: phase B starts
            case 'extracting':
            case 'planning':
            case 'ready':
            case 'dumping':
            case 'installing':
            case 'archiving':
            case 'verifying':
            case 'rollback_required':
                $result = phaseBRun($ctx, $updateId);
                commandProgress($result, $out, $progress);
                if ($result['status'] === 'failed') {
                    return commandResult(EXIT_FAILED, $result['message'], $state);
                }
                if ($result['status'] === 'error') {
                    $state = readState($root, $updateId);

                    return commandResult(EXIT_FAILED, $result['message'] . ($state['state'] === 'rollback_required'
                        ? "\n" . text($texts, 'SYS_CORE_UPDATE_ROLLBACK_REQUIRED', array((string)$state['installedVersion']))
                            . "\nContinue the update with --resume, or restore version " . $state['installedVersion'] . ' with rollback.'
                        : ''), $state);
                }
                break;

            case 'files_installed':
                if ($options['skipDatabase']) {
                    return commandResult(EXIT_SUCCESS, 'The files of Admidio ' . commandTarget($state) . ' are installed.'
                        . ' Admidio stays in maintenance mode until the database is updated with apply --resume or install/update.php.', $state);
                }
                if ($options['databaseTried']) {
                    return commandResult(EXIT_FAILED, 'The database update did not start.', $state);
                }

                return commandResult(EXIT_SUCCESS, '', $state, 'database');

            case 'database_running':
                if (isDatabaseUpdateRunning($root, $updateId)) {
                    return commandResult(EXIT_REJECTED, text($texts, 'SYS_CORE_UPDATE_DATABASE_RUNNING'), $state);
                }
                if ($options['databaseTried']) {
                    return commandResult(EXIT_FAILED, 'The database update was interrupted. Retry it with --resume.', $state);
                }

                return commandResult(EXIT_SUCCESS, '', $state, 'database');

            case 'database_failed':
                if (!$options['databaseTried']) {
                    // --resume retries the database update.
                    return commandResult(EXIT_SUCCESS, '', $state, 'database');
                }
                $canRestore = restoreMethod($ctx, $state) !== '';

                return commandResult(EXIT_FAILED, (string)$state['lastError'] . "\n"
                    . text($texts, $canRestore ? 'SYS_CORE_UPDATE_DATABASE_FAILED' : 'SYS_CORE_UPDATE_DATABASE_FAILED_OWN',
                        array(commandTarget($state), (string)$state['installedVersion']))
                    . "\nRetry the database update with --resume, or restore version " . $state['installedVersion'] . ' with rollback'
                    . ($canRestore ? '.' : ' --database-restored after you restored the database from your own backup.'), $state);

            case 'completed':
                phaseBFinish($ctx, $updateId);

                return commandResult(EXIT_SUCCESS, commandCompletedReport($state, $texts), $state);

            case 'rolling_back':
            case 'restoring_database':
                return commandResult(EXIT_REJECTED, 'The update is being undone. Continue that with rollback.', $state);

            default:
                return commandResult(EXIT_ERROR, 'The update is in the unknown state ' . $state['state'] . '.', $state);
        }
    }
}

/**
 * Undo the unfinished update: cancel it before it changed anything, restore the previous files,
 * or after a failed database update restore the previous version with its database.
 * @param array<string,mixed> $ctx
 * @param bool $databaseRestored The administrator has restored the database from an own backup.
 * @param callable(string): void $out
 * @param callable(string): bool $confirm
 * @return array{exit: int, next: string, message: string, updateId: string, state: array<string,mixed>}
 */
function commandRollback(array $ctx, bool $databaseRestored, callable $out, callable $confirm): array
{
    $root = $ctx['config']['rootPath'];
    $texts = $ctx['texts'];
    $updateId = activeUpdateId($root);
    if ($updateId === null) {
        return commandResult(EXIT_SUCCESS, 'No core update is in progress.');
    }

    $state = readState($root, $updateId);
    $previous = (string)$state['installedVersion'];
    switch ($state['state']) {
        case 'new':
        case 'checking':
        case 'checked':
        case 'downloading':
        case 'downloaded':
        case 'prepared':
        case 'extracting':
        case 'planning':
        case 'review':
        case 'ready':
            if (!$confirm('Cancel the update to Admidio ' . commandTarget($state) . '?')) {
                return commandResult(EXIT_FAILED, 'Nothing was done.', $state);
            }
            in_array($state['state'], PHASE_A_STATES, true) ? phaseACancel($ctx, $updateId) : phaseBCancel($ctx, $updateId);

            return commandResult(EXIT_SUCCESS, text($texts, 'SYS_CORE_UPDATE_CANCELLED'), $state);

        case 'installing':
        case 'archiving':
        case 'verifying':
        case 'rollback_required':
        case 'files_installed':
            if (!$confirm('Restore the files of Admidio ' . $previous . '?')) {
                return commandResult(EXIT_FAILED, 'Nothing was done.', $state);
            }
            phaseBRollback($ctx, $updateId);
            break;

        case 'database_failed':
            $method = restoreMethod($ctx, $state);
            if ($method === '' && !$databaseRestored) {
                return commandResult(EXIT_REJECTED, 'The updater has no backup of the database that it can restore.'
                    . ' Restore the database of version ' . $previous . ' from your own backup, then run rollback --database-restored.', $state);
            }
            if (!$confirm('Restore Admidio ' . $previous . ($method !== '' ? ', the database from the backup of the updater and the files?' : ', the files?'))) {
                return commandResult(EXIT_FAILED, 'Nothing was done.', $state);
            }
            phaseBRestore($ctx, $updateId, $databaseRestored);
            break;

        case 'dumping':
            return commandResult(EXIT_REJECTED, 'The database is being backed up. Continue the update with apply --resume.', $state);

        case 'database_running':
            return commandResult(EXIT_REJECTED, isDatabaseUpdateRunning($root, $updateId)
                ? text($texts, 'SYS_CORE_UPDATE_DATABASE_RUNNING')
                : 'The database update was interrupted. Retry it with apply --resume.', $state);

        case 'rolling_back':
        case 'restoring_database':
            break;

        default:
            return commandResult(EXIT_ERROR, 'The update is in the unknown state ' . $state['state'] . '.', $state);
    }

    $progress = array('message' => '', 'progress' => -100);
    while (($state = readState($root, $updateId))['state'] !== 'rolled_back') {
        $result = phaseBRun($ctx, $updateId);
        commandProgress($result, $out, $progress);
        if ($result['status'] === 'error') {
            return commandResult(EXIT_FAILED, $result['message'] . ' Run rollback again to continue.', readState($root, $updateId));
        }
    }
    phaseBRolledBack($ctx, $updateId);

    return commandResult(EXIT_SUCCESS, rolledBackMessage($ctx, $state), $state);
}

/**
 * What the status, the result and the JSON output tell about an update.
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function commandStateSummary(array $state): array
{
    if ($state === array()) {
        return array();
    }

    return array(
        'updateId' => (string)($state['updateId'] ?? ''),
        'state' => (string)($state['state'] ?? ''),
        'channel' => (string)($state['channel'] ?? ''),
        'installedVersion' => (string)($state['installedVersion'] ?? ''),
        'targetVersion' => (string)($state['targetVersionText'] ?? $state['targetVersion'] ?? ''),
        'databaseStarted' => !empty($state['databaseStarted']),
        'dumpFile' => (string)($state['dumpFile'] ?? ''),
        'keptArchive' => (string)($state['keptArchive'] ?? ''),
        'lastError' => (string)($state['lastError'] ?? ''),
        'updatedAt' => isset($state['updatedAt']) ? date('c', (int)$state['updatedAt']) : ''
    );
}

/**
 * @param array<string,mixed> $state
 * @return array{exit: int, next: string, message: string, updateId: string, state: array<string,mixed>}
 */
function commandResult(int $exit, string $message, array $state = array(), string $next = ''): array
{
    return array('exit' => $exit, 'next' => $next, 'message' => $message, 'updateId' => (string)($state['updateId'] ?? ''), 'state' => $state);
}

/**
 * The version an update installs, as the administrator knows it.
 * @param array<string,mixed> $state
 */
function commandTarget(array $state): string
{
    return (string)(($state['targetVersionText'] ?? '') !== '' ? $state['targetVersionText'] : ($state['targetVersion'] ?? ''));
}

/**
 * Print a line of progress when the step changes or another tenth is done.
 * @param array{message: string, progress: int, status: string} $result
 * @param array{message: string, progress: int} $last
 */
function commandProgress(array $result, callable $out, array &$last): void
{
    if ($result['message'] === '' || ($result['message'] === $last['message'] && $result['progress'] < $last['progress'] + 10)) {
        return;
    }

    $out(sprintf('[%3d%%] %s', $result['progress'], $result['message']));
    $last = array('message' => $result['message'], 'progress' => $result['progress']);
}

/**
 * What the check of phase A found.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 */
function commandCheckReport(array $state, array $texts): string
{
    $lines = array(
        'Installed:  ' . $state['installedVersion'],
        // The version of a developer channel names its branch, tag or pull request already.
        'Target:     ' . commandTarget($state) . ' (' . $state['channel'] . ')'
    );
    if (($state['commit'] ?? '') !== '') {
        $lines[] = 'Commit:     ' . $state['commit'];
    }
    if ($state['channel'] === 'master') {
        $lines[] = 'Warning:    ' . text($texts, 'SYS_CORE_UPDATE_MASTER_WARNING');
    }

    return implode(PHP_EOL, $lines) . PHP_EOL . commandChecks((array)($state['checks'] ?? array()), $texts);
}

/**
 * What the plan of phase B changes and keeps, and the checks D1-D3 that did not pass.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 */
function commandPlanReport(array $ctx, array $state): string
{
    $texts = $ctx['texts'];
    $plan = (array)($state['plan'] ?? array());
    $lines = array(text($texts, 'SYS_CORE_UPDATE_REVIEW_DESC', array((string)$state['installedVersion'], commandTarget($state))));
    foreach (array('create' => 'SYS_CORE_UPDATE_PLAN_CREATE', 'replace' => 'SYS_CORE_UPDATE_PLAN_REPLACE',
        'delete' => 'SYS_CORE_UPDATE_PLAN_DELETE', 'unchanged' => 'SYS_CORE_UPDATE_PLAN_UNCHANGED') as $key => $text) {
        $lines[] = '  ' . text($texts, $text, array((string)($plan[$key] ?? 0)));
    }

    if ((int)($plan['kept'] ?? 0) > 0) {
        $lines[] = text($texts, 'SYS_CORE_UPDATE_KEPT_DESC', array((string)$plan['kept']))
            . (empty($plan['inventory']) ? ' ' . text($texts, 'SYS_CORE_UPDATE_NO_INVENTORY') : '');
        // Without an inventory every file that is replaced is listed, which says nothing.
        foreach (empty($plan['inventory']) ? array() : readPlan(workspace($ctx['config']['rootPath'], (string)$state['updateId'])) as $operation) {
            if (($operation['keep'] ?? '') !== '') {
                $lines[] = sprintf('  %-9s%s', $operation['keep'], $operation['path']);
            }
        }
    }

    $problems = array_filter((array)($state['planChecks'] ?? array()), static fn(array $check): bool => $check['status'] !== 'ok');

    return implode(PHP_EOL, $lines) . ($problems !== array() ? PHP_EOL . commandChecks($problems, $texts) : '');
}

/**
 * @param array<int,array{check: string, status: string, text: string, params: array<int,string>}> $checks
 * @param array{language: string, texts: array<string,string>} $texts
 */
function commandChecks(array $checks, array $texts): string
{
    $lines = array();
    foreach ($checks as $check) {
        $lines[] = sprintf('  %-8s%-4s%s', $check['status'], $check['check'], text($texts, $check['text'], $check['params']));
    }

    return implode(PHP_EOL, $lines);
}

/**
 * What a completed update tells: the version, the backup, the kept files and what is left to do.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 */
function commandCompletedReport(array $state, array $texts): string
{
    $lines = array(!empty($state['databaseManual'])
        ? text($texts, 'SYS_CORE_UPDATE_FILES_ONLY', array(commandTarget($state)))
        : text($texts, 'SYS_CORE_UPDATE_SUCCESSFUL', array(commandTarget($state))));
    if (!empty($state['composerChanged'])) {
        $lines[] = text($texts, 'SYS_CORE_UPDATE_COMPOSER_REQUIRED');
    }
    if ((string)($state['dumpFile'] ?? '') !== '') {
        $lines[] = text($texts, empty($state['dumpEncrypted']) ? 'SYS_CORE_UPDATE_DUMP_FILE' : 'SYS_CORE_UPDATE_DUMP_FILE_ENCRYPTED', array('adm_my_files/core-update/' . $state['dumpFile']));
    }
    foreach ((array)($state['databaseNotes'] ?? array()) as $note) {
        $lines[] = (string)$note['text'];
    }
    if ((string)($state['keptArchive'] ?? '') !== '') {
        $lines[] = text($texts, 'SYS_CORE_UPDATE_KEPT_ARCHIVE', array('adm_my_files/core-update/' . $state['keptArchive']));
    }

    return implode(PHP_EOL, $lines);
}
