<?php
/**
 * Command line entry point of the standalone core updater.
 *
 *   php install/core-update/cli.php status [--format=text|json] [--host=example.org]
 *   php install/core-update/cli.php --help
 *
 * The admidio commands update:check, update:apply and update:rollback run this script in a process
 * of their own, because the Admidio command line has src/ and vendor/ loaded, which an update
 * replaces. It can also be called directly, which is the only way for an installation that got the
 * updater copied in by hand. Shell access to the installation is the proof of authority, so there is
 * no login. Like the Admidio command line, it answers in English, and it reads config.php with the
 * request host of --host, of the environment variable ADMIDIO_HOST or "localhost", because some
 * configurations choose their database by that host.
 *
 * Exit codes are those of the Admidio command line: 0 success, 1 error, 2 usage, 5 rejected,
 * 6 failed.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

use AdmidioCoreUpdater\UpdaterException;
use function AdmidioCoreUpdater\activeUpdateId;
use function AdmidioCoreUpdater\loadConfig;
use function AdmidioCoreUpdater\readState;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/lib/bootstrap.php';

const EXIT_SUCCESS = 0;
const EXIT_ERROR = 1;
const EXIT_USAGE = 2;

$arguments = array_slice($argv, 1);
$command = '';
$format = 'text';
$host = (string)getenv('ADMIDIO_HOST');
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--format=')) {
        $format = substr($argument, strlen('--format='));
    } elseif (str_starts_with($argument, '--host=')) {
        $host = substr($argument, strlen('--host='));
    } elseif ($argument === '--help' || $argument === '-h') {
        $command = 'help';
    } elseif ($command === '' && !str_starts_with($argument, '-')) {
        $command = $argument;
    } else {
        fwrite(STDERR, 'Unknown argument: ' . $argument . PHP_EOL);
        exit(EXIT_USAGE);
    }
}

if ($command === '' || $command === 'help' || !in_array($format, array('text', 'json'), true)) {
    fwrite($command === 'help' ? STDOUT : STDERR, 'Usage: php install/core-update/cli.php <command> [--format=text|json] [--host=<host>]' . PHP_EOL
        . PHP_EOL
        . 'Commands:' . PHP_EOL
        . '  status   Show the unfinished core update and its state' . PHP_EOL);
    exit($command === 'help' ? EXIT_SUCCESS : EXIT_USAGE);
}

// Only a host name with an optional port, so that no path or header break reaches config.php.
if ($host === '') {
    $host = 'localhost';
}
if (preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?(?::\d{1,5})?$/', $host) !== 1) {
    fwrite(STDERR, 'The host "' . $host . '" is not a valid host name.' . PHP_EOL);
    exit(EXIT_USAGE);
}
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;

try {
    $config = loadConfig(dirname(__DIR__, 2));
    if (in_array($config['timezone'], timezone_identifiers_list(), true)) {
        date_default_timezone_set($config['timezone']);
    }

    if ($command !== 'status') {
        fwrite(STDERR, 'Unknown command: ' . $command . PHP_EOL);
        exit(EXIT_USAGE);
    }

    exit(showStatus($config['rootPath'], $format));
} catch (UpdaterException $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
    exit(EXIT_ERROR);
}

/**
 * Print the unfinished update, if there is one.
 * @return int The exit code.
 */
function showStatus(string $rootPath, string $format): int
{
    $updateId = activeUpdateId($rootPath);
    $data = array('active' => $updateId !== null);

    if ($updateId !== null) {
        $state = readState($rootPath, $updateId);
        $data += array(
            'updateId' => $updateId,
            'state' => (string)($state['state'] ?? ''),
            'channel' => (string)($state['channel'] ?? ''),
            'installedVersion' => (string)($state['installedVersion'] ?? ''),
            'targetVersion' => (string)($state['targetVersion'] ?? ''),
            'databaseStarted' => !empty($state['databaseStarted']),
            'lastError' => (string)($state['lastError'] ?? ''),
            'updatedAt' => isset($state['updatedAt']) ? date('c', (int)$state['updatedAt']) : ''
        );
    }

    if ($format === 'json') {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

        return EXIT_SUCCESS;
    }

    if ($updateId === null) {
        echo 'No core update is in progress.', PHP_EOL;

        return EXIT_SUCCESS;
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

    return EXIT_SUCCESS;
}
