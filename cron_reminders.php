<?php
/**
 * ***********************************************************************************************
 * Admidio Cronjob Entrypoint for Event Reminders
 *
 * This script may only be executed from the command line. Configure a daily cron job at your
 * web host, for example:
 * php /path/to/admidio/cron_reminders.php
 * ***********************************************************************************************
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('This script may only be executed from the command line.');
}

// system/common.php expects the request variables of a web request. These values only make the
// existing bootstrap work in CLI mode; they do not change the TLS configuration of the server.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['SCRIPT_NAME'] = '/cron_reminders.php';

require_once(__DIR__ . '/system/common.php');

use Admidio\Infrastructure\Utils\Maintenance;

try {
    if ($gDb instanceof Admidio\Infrastructure\Database) {
        $maintenance = new Maintenance($gDb);
        echo $maintenance->sendEventReminders() . PHP_EOL;
    } else {
        fwrite(STDERR, 'Database connection not available.' . PHP_EOL);
        exit(1);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'Event reminder cron job failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
