<?php
/**
 * The process of an update, request by request. runStep() does the next bounded piece of work in
 * whatever state the update is in, for the page of the browser and for the command line alike, and
 * names every state in the order an update passes through them. The work itself is done by the
 * functions of phase-a.php and phase-b.php.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use Throwable;

/**
 * Do the next bounded piece of work of the update. The script of the page, and the command line,
 * call it again and again until the update reaches a state where the administrator decides, or
 * install/update.php takes over.
 * @param array<string,mixed> $ctx See phase-a.php.
 * @return array{status: string, message: string, progress: int, state: string}
 */
function runStep(array $ctx, string $updateId): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId): array {
        $state = readState($root, $updateId);
        $before = (string)$state['state'];

        try {
            $state = match ($before) {
                // Phase A, in the installed updater
                'new', 'checking', 'checked' => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID'), // the administrator chooses and confirms
                'downloading' => downloadPart($ctx, $state),
                'downloaded' => verifyPackage($ctx, $state),
                'extracting' => extractPart($ctx, $state),

                // Phase B, in the unpacked updater of the new release (see entry.php)
                'prepared' => startPlanning($ctx, $state),
                'planning' => planPart($ctx, $state),
                'review' => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID'), // the administrator confirms the plan
                'ready' => enterMaintenance($ctx, $state),
                'dumping' => dumpPart($ctx, $state),
                'installing' => installPart($ctx, $state),
                'archiving' => archivePart($ctx, $state),
                'verifying' => verifyPart($ctx, $state),
                // Continue where the error stopped the update.
                'rollback_required' => array_merge($state, array('state' => (string)($state['failedIn'] ?? 'installing'))),

                // Phase C: install/update.php of the new release updates the database
                'files_installed', 'database_running', 'database_failed', 'completed' => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID'),

                // Undoing: after a failed database update the database first, then the files
                'restoring_database' => restoreDatabasePart($ctx, $state),
                'rolling_back' => rollbackPart($ctx, $state),
                'rolled_back' => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID'),

                default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
            };
        } catch (VerificationFailure $failure) {
            // Something is wrong with what the server delivered: the update ends.
            updateLog($root, $updateId, 'verification failed in ' . $before . ': ' . errorDetail($failure->getPrevious()));
            abortUpdate($ctx, $updateId);

            return array('status' => 'failed', 'message' => describeError($ctx, $failure->getPrevious()), 'progress' => 0, 'state' => 'failed');
        } catch (Throwable $exception) {
            // Mostly the update stays in its state, and the next request tries again.
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
                $state['failedIn'] = $before;
                $state['state'] = 'rollback_required';
            }
            writeState($root, $state);
            if ($state['state'] !== $before) {
                updateLog($root, $updateId, 'state ' . $state['state']);
            }

            return array('status' => 'error', 'message' => $state['lastError'], 'progress' => updateProgress($state), 'state' => $state['state']);
        }

        $state['lastError'] = '';
        writeState($root, $state);
        if ($state['state'] !== $before) {
            updateLog($root, $updateId, 'state ' . $state['state']
                . ($state['state'] === 'prepared' ? ': package of ' . $state['targetVersion'] . ' verified and extracted' : '')
                . ($state['state'] === 'review' ? ' ' . json_encode($state['plan']) : '')
                . (($state['dumpFile'] ?? '') !== '' && $before === 'dumping' ? ' ' . $state['dumpFile'] : '')
                . (($state['keptArchive'] ?? '') !== '' && $before === 'archiving' ? ' ' . $state['keptArchive'] : ''));
        }

        return stepResult($ctx, $state);
    }, false);
}

/**
 * The answer to the page for the state an update has reached: what to show, and whether its
 * script goes on ("success") or stops and loads the page of the new state.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array{status: string, message: string, progress: int, state: string}
 */
function stepResult(array $ctx, array $state): array
{
    $texts = $ctx['texts'];
    $version = array((string)($state['targetVersion'] ?? ''));

    return array(
        'status' => match ((string)$state['state']) {
            // The updater of the new release takes over (see entry.php).
            'prepared' => 'prepared',
            'review' => 'review',
            // The page of the database update, or of the result.
            'files_installed', 'completed' => 'reload',
            'rolled_back' => 'rolled_back',
            default => 'success'
        },
        'message' => match ((string)$state['state']) {
            'downloading', 'downloaded' => text($texts, 'SYS_CORE_UPDATE_DOWNLOADING', $version),
            'extracting' => text($texts, 'SYS_CORE_UPDATE_EXTRACTING'),
            'prepared' => text($texts, 'SYS_CORE_UPDATE_PREPARED', $version),
            'planning' => text($texts, 'SYS_CORE_UPDATE_PLANNING'),
            'ready' => text($texts, 'SYS_CORE_UPDATE_MAINTENANCE_ACTIVE'),
            'dumping' => text($texts, 'SYS_CORE_UPDATE_DUMPING'),
            'installing' => text($texts, 'SYS_CORE_UPDATE_FILES_INSTALLING'),
            'archiving' => text($texts, 'SYS_CORE_UPDATE_ARCHIVING'),
            'verifying' => text($texts, 'SYS_CORE_UPDATE_VERIFYING'),
            'restoring_database' => text($texts, 'SYS_CORE_UPDATE_RESTORING_DATABASE'),
            'rolling_back' => text($texts, 'SYS_CORE_UPDATE_ROLLING_BACK'),
            'rolled_back' => rolledBackMessage($ctx, $state),
            default => ''
        },
        'progress' => updateProgress($state),
        'state' => (string)$state['state']
    );
}

/**
 * The progress bar in percent: phase A has one on its download page, phase B one of its own.
 * @param array<string,mixed> $state
 */
function updateProgress(array $state): int
{
    $part = static fn(int $from, int $to): int => $from + (int)floor(($to - $from) * min(1, (int)($state['cursor'] ?? 0) / max(1, (int)($state['cursorTotal'] ?? 1))));

    return match ((string)$state['state']) {
        // Phase A: the download is the long part.
        'downloading' => 5 + (int)floor(70 * (int)($state['downloadedBytes'] ?? 0) / max(1, (int)($state['packageSize'] ?? 1))),
        'downloaded' => 75,
        'extracting' => $part(75, 100),
        // Phase B
        'prepared' => 0,
        'planning' => $part(0, 30),
        'review', 'ready' => 30,
        'dumping' => $part(30, 40),
        'installing' => $part(40, 80),
        'archiving' => $part(80, 85),
        'verifying' => $part(85, 95),
        'rollback_required' => 30,
        'files_installed', 'database_running', 'database_failed' => 95,
        'completed' => 100,
        // Undoing
        'restoring_database' => 100,
        'rolling_back' => 100 - $part(0, 100),
        default => 0
    };
}

/**
 * Give up an update before it changed anything: its workspace is removed, with the unpacked
 * updater of the new release.
 * @param array<string,mixed> $ctx
 */
function cancelUpdate(array $ctx, string $updateId): void
{
    $root = $ctx['config']['rootPath'];

    withLock($root, static function () use ($ctx, $root, $updateId): void {
        if (!in_array(readState($root, $updateId)['state'], array('new', 'checking', 'checked', 'downloading', 'downloaded',
            'prepared', 'extracting', 'planning', 'review', 'ready'), true)) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
        abortUpdate($ctx, $updateId);
    }, false);
}
