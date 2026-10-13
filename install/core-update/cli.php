<?php
/**
 * Command line entry point of the standalone core updater.
 *
 *   php install/core-update/cli.php status [--format=text|json]
 *   php install/core-update/cli.php apply [--channel=CHANNEL] [--ref=REF] [--backup=dump|own] [--on-changed=archive|cancel]
 *                                         [--dry-run] [--skip-database] [--resume] [--yes] [--format=text|json]
 *   php install/core-update/cli.php rollback [--database-restored] [--yes] [--format=text|json]
 *   php install/core-update/cli.php --help
 *
 * The admidio commands update:apply and update:rollback run this script in a process of their own,
 * because the Admidio command line has src/ and vendor/ loaded, which an update replaces. It can
 * also be called directly, which is the way to continue an update while Admidio itself cannot run,
 * and the only way for an installation that got the updater copied in by hand. Shell access to the
 * installation is the proof of authority, so there is no login. Like the Admidio command line, it
 * answers in English, and it reads config.php with the request host of --host, of the environment
 * variable ADMIDIO_HOST or "localhost", because some configurations choose their database by that
 * host.
 *
 * apply starts two more processes of this script: after phase A "apply --resume", which runs the
 * updater of the new release, and after the files "database", which loads system/bootstrap/cli.php
 * of the new release and updates the database with its code (see lib/cli.php).
 *
 * Exit codes are those of the Admidio command line: 0 success, 1 error, 2 usage, 5 rejected,
 * 6 failed.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

use Admidio\InstallationUpdate\Service\CoreUpdateHandover;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/cli.php';

$arguments = array_slice($argv, 1);
$command = '';
$values = array('format' => 'text', 'host' => (string)getenv('ADMIDIO_HOST'), 'channel' => 'stable', 'ref' => '', 'backup' => '',
    'on-changed' => 'archive');
$flags = array('dry-run' => false, 'skip-database' => false, 'resume' => false, 'yes' => false, 'no-interaction' => false,
    'database-restored' => false);
foreach ($arguments as $argument) {
    $name = str_starts_with($argument, '--') ? explode('=', substr($argument, 2), 2)[0] : '';
    if (array_key_exists($name, $values) && str_contains($argument, '=')) {
        $values[$name] = substr($argument, strlen($name) + 3);
    } elseif (array_key_exists($name, $flags) && !str_contains($argument, '=')) {
        $flags[$name] = true;
    } elseif ($argument === '-y') {
        $flags['yes'] = true;
    } elseif ($argument === '--help' || $argument === '-h') {
        $command = 'help';
    } elseif ($command === '' && !str_starts_with($argument, '-')) {
        $command = $argument;
    } else {
        fwrite(STDERR, 'Unknown argument: ' . $argument . PHP_EOL);
        exit(U\EXIT_USAGE);
    }
}

if (!in_array($command, array('status', 'apply', 'rollback', 'database'), true) || !in_array($values['format'], array('text', 'json'), true)
    || !in_array($values['backup'], array('', 'dump', 'own'), true) || !in_array($values['on-changed'], U\ON_CHANGED, true)) {
    fwrite($command === 'help' ? STDOUT : STDERR, 'Usage: php install/core-update/cli.php <command> [options] [--format=text|json] [--host=<host>]' . PHP_EOL
        . PHP_EOL
        . 'Commands:' . PHP_EOL
        . '  status     Show the unfinished core update and its state' . PHP_EOL
        . '  apply      Install the newest release: check, download, install the files, update the database' . PHP_EOL
        . '  rollback   Undo the unfinished update: cancel it, restore the previous files, or after a failed' . PHP_EOL
        . '             database update the previous version with its database' . PHP_EOL
        . PHP_EOL
        . 'Options of apply:' . PHP_EOL
        . '  --channel=CHANNEL           stable (default); with $gDebug also beta, master, branch, tag, pr' . PHP_EOL
        . '  --ref=REF                   the branch, the tag or the number of the pull request' . PHP_EOL
        . '  --backup=dump|own           the updater backs up the database (default where it can), or you have' . PHP_EOL
        . '                              your own backup' . PHP_EOL
        . '  --on-changed=archive|cancel files that were changed or do not belong to the installed version are' . PHP_EOL
        . '                              kept in an archive and replaced (default), or the update is cancelled' . PHP_EOL
        . '  --dry-run                   check and plan the update, then cancel it; the updater of the new' . PHP_EOL
        . '                              version stays installed' . PHP_EOL
        . '  --skip-database             install the files only; maintenance mode stays on until the database' . PHP_EOL
        . '                              is updated with apply --resume or install/update.php' . PHP_EOL
        . '  --resume                    continue the unfinished update' . PHP_EOL
        . '  --yes, -y                   do not ask before the download and before the installation' . PHP_EOL
        . '  --no-interaction            never ask; without --yes the update stops before it changes anything' . PHP_EOL
        . PHP_EOL
        . 'Options of rollback:' . PHP_EOL
        . '  --database-restored         after a failed database update without a backup of the updater: you' . PHP_EOL
        . '                              have restored the database of the previous version yourself' . PHP_EOL
        . '  --yes, -y, --no-interaction as for apply' . PHP_EOL);
    exit($command === 'help' ? U\EXIT_SUCCESS : U\EXIT_USAGE);
}

// Only a host name with an optional port, so that no path or header break reaches config.php.
$host = $values['host'] !== '' ? $values['host'] : 'localhost';
if (preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?(?::\d{1,5})?$/', $host) !== 1) {
    fwrite(STDERR, 'The host "' . $host . '" is not a valid host name.' . PHP_EOL);
    exit(U\EXIT_USAGE);
}
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;

$texts = null;
try {
    $config = U\loadConfig(dirname(__DIR__, 2));
    if (in_array($config['timezone'], timezone_identifiers_list(), true)) {
        date_default_timezone_set($config['timezone']);
    }

    if ($command === 'status') {
        exit(coreUpdateShowStatus($config['rootPath'], $values['format']));
    }

    if ($command === 'database') {
        // The code of the new release updates the database. system/bootstrap/cli.php is included
        // here, at the top level, so that its globals are globals. loadConfig() has read config.php
        // in a scope of its own, so the require_once of the bootstrap would skip it: it is included
        // here first.
        $cliHost = $host;
        include $config['rootPath'] . '/adm_my_files/config.php';
        require $config['rootPath'] . '/system/bootstrap/cli.php';

        $updateId = U\activeUpdateId($config['rootPath']);
        if ($updateId === null) {
            fwrite(STDERR, 'No core update is in progress.' . PHP_EOL);
            exit(U\EXIT_REJECTED);
        }
        CoreUpdateHandover::load($updateId)->updateDatabase();
        exit(U\EXIT_SUCCESS);
    }

    if (!in_array($values['channel'], array_merge(U\RELEASE_CHANNELS, U\DEVELOPER_CHANNELS), true)) {
        fwrite(STDERR, 'Unknown channel: ' . $values['channel'] . PHP_EOL);
        exit(U\EXIT_USAGE);
    }
    if (!in_array($values['channel'], U\availableChannels($config), true)) {
        fwrite(STDERR, 'The channel ' . $values['channel'] . ' needs $gDebug = true in adm_my_files/config.php.' . PHP_EOL);
        exit(U\EXIT_USAGE);
    }

    coreUpdateOwnerWarning($config['rootPath']);
    $context = U\commandContext($config, __DIR__);
    $texts = $context['texts'];
    $out = static function (string $line) use ($values): void {
        if ($values['format'] === 'text') {
            echo $line, PHP_EOL;
        }
    };
    $confirm = static function (string $question) use ($flags): bool {
        if ($flags['yes']) {
            return true;
        }
        if ($flags['no-interaction'] || !stream_isatty(STDIN)) {
            fwrite(STDERR, $question . ' Confirm it with --yes.' . PHP_EOL);

            return false;
        }

        fwrite(STDERR, $question . ' [y/N] ');
        $answer = fgets(STDIN);

        return $answer !== false && in_array(strtolower(trim($answer)), array('y', 'yes'), true);
    };

    if ($command === 'rollback') {
        $result = U\commandRollback($context, $flags['database-restored'], $out, $confirm);
    } else {
        $options = array(
            'channel' => $values['channel'],
            'ref' => $values['ref'],
            'backup' => $values['backup'],
            'onChanged' => $values['on-changed'],
            'dryRun' => $flags['dry-run'],
            'skipDatabase' => $flags['skip-database'],
            'resume' => $flags['resume'],
            'databaseTried' => false
        );
        $updateId = null;
        while (true) {
            $result = U\commandApply($context, $options, $out, $confirm, $updateId);
            if ($result['next'] === 'restart') {
                // The updater of the new release continues with the same options.
                $next = array_merge(array('apply', '--resume'), array_values(array_diff($arguments, array('apply', '--resume'))));
                $exit = coreUpdateRun($next);
                if ($exit !== null) {
                    exit($exit);
                }
                $result = U\commandResult(U\EXIT_FAILED, 'PHP cannot start processes here. The updater of the new version is installed;'
                    . ' continue with: ' . coreUpdateCommandLine($next), $result['state']);
            } elseif ($result['next'] === 'database') {
                if (coreUpdateRun(array('database', '--host=' . $host)) === null) {
                    $result = U\commandResult(U\EXIT_FAILED, 'PHP cannot start processes here. Update the database with: '
                        . coreUpdateCommandLine(array('database', '--host=' . $host)) . ', then run ' . coreUpdateCommandLine(array('apply', '--resume')), $result['state']);
                } else {
                    // The state tells how the database update went.
                    $options['resume'] = true;
                    $options['databaseTried'] = true;
                    $updateId = $result['updateId'];
                    continue;
                }
            }
            break;
        }
    }

    if ($values['format'] === 'json') {
        echo json_encode(array('status' => $result['exit'] === U\EXIT_SUCCESS ? 'success' : 'error', 'message' => $result['message'],
            'update' => U\commandStateSummary($result['state'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    } elseif ($result['message'] !== '') {
        fwrite($result['exit'] === U\EXIT_SUCCESS ? STDOUT : STDERR, $result['message'] . PHP_EOL);
    }
    exit($result['exit']);
} catch (UpdaterException $exception) {
    fwrite(STDERR, 'Error: ' . ($texts !== null ? $exception->getText($texts) : $exception->getMessage())
        . ($exception->getDetail() !== '' ? ' (' . $exception->getDetail() . ')' : '') . PHP_EOL);
    exit(U\EXIT_ERROR);
} catch (Throwable $exception) {
    // An error of the database update is the HTML page of Database::showError().
    fwrite(STDERR, 'Error: ' . ($command === 'database' && class_exists(CoreUpdateHandover::class, false)
        ? CoreUpdateHandover::toText($exception->getMessage()) : $exception->getMessage()) . PHP_EOL);
    exit($command === 'database' ? U\EXIT_FAILED : U\EXIT_ERROR);
}

/**
 * Print the unfinished update, if there is one.
 * @return int The exit code.
 */
function coreUpdateShowStatus(string $rootPath, string $format): int
{
    $updateId = U\activeUpdateId($rootPath);
    $data = array('active' => $updateId !== null) + ($updateId !== null ? U\commandStateSummary(U\readState($rootPath, $updateId)) : array());

    if ($format === 'json') {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

        return U\EXIT_SUCCESS;
    }

    if ($updateId === null) {
        echo 'No core update is in progress.', PHP_EOL;

        return U\EXIT_SUCCESS;
    }

    echo 'Update:       ', $data['updateId'], PHP_EOL,
        'State:        ', $data['state'], PHP_EOL,
        'Channel:      ', $data['channel'], PHP_EOL,
        'Installed:    ', $data['installedVersion'] !== '' ? $data['installedVersion'] : '-', PHP_EOL,
        'Target:       ', $data['targetVersion'] !== '' ? $data['targetVersion'] : '-', PHP_EOL,
        'Database:     ', $data['databaseStarted'] ? 'update started' : 'not touched', PHP_EOL,
        'Last request: ', $data['updatedAt'] !== '' ? $data['updatedAt'] : '-', PHP_EOL;
    if ($data['lastError'] !== '') {
        echo 'Last error:   ', $data['lastError'], PHP_EOL;
    }

    return U\EXIT_SUCCESS;
}

/**
 * Run this script again in a process of its own, with the terminal of this one.
 * @param array<int,string> $arguments
 * @return int|null The exit code, or **null** if PHP may not start a process.
 */
function coreUpdateRun(array $arguments): ?int
{
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (PHP_BINARY === '' || !function_exists('proc_open') || in_array('proc_open', $disabled, true)) {
        return null;
    }

    $process = proc_open(array_merge(array(PHP_BINARY, __FILE__), $arguments), array(STDIN, STDOUT, STDERR), $pipes);

    return is_resource($process) ? proc_close($process) : null;
}

/**
 * The command line that runs this script with the arguments, for the administrator to type.
 * @param array<int,string> $arguments
 */
function coreUpdateCommandLine(array $arguments): string
{
    return implode(' ', array_map('escapeshellarg', array_merge(array(PHP_BINARY !== '' ? PHP_BINARY : 'php', __FILE__), $arguments)));
}

/**
 * Warn when the files of Admidio belong to another user than the one who runs this script: the
 * files the update writes then belong to this user, and an update in the browser may not be able
 * to replace them later. Files of root are no such case: the web server cannot have written them,
 * and in a container they often only look like that. The processes this one starts are not warned
 * again.
 */
function coreUpdateOwnerWarning(string $rootPath): void
{
    if (!function_exists('posix_geteuid') || getenv('ADMIDIO_CORE_UPDATE_OWNER_WARNED') === '1') {
        return;
    }
    putenv('ADMIDIO_CORE_UPDATE_OWNER_WARNED=1');

    $owner = @fileowner($rootPath . '/index.php');
    $user = posix_geteuid();
    if ($owner === false || $owner === 0 || $owner === $user) {
        return;
    }

    $name = static fn(int $id): string => (function_exists('posix_getpwuid') ? (string)(posix_getpwuid($id)['name'] ?? '') : '') ?: (string)$id;
    fwrite(STDERR, 'Warning: the files of Admidio belong to ' . $name($owner) . ', but the updater runs as ' . $name($user) . '.'
        . ' The files it writes will belong to ' . $name($user) . ', and an update in the browser may not be able to replace them.'
        . ' Run it as ' . $name($owner) . ', for example with sudo -u ' . $name($owner) . '.' . PHP_EOL);
}
