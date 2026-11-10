<?php
/**
 * ***********************************************************************************************
 * Admidio Cronjob Entrypoint for Event Reminders
 * 
 * Set up a daily cron job at your web host pointing to this file:
 * Example CLI cron job command:
 * php /path/to/admidio/cron_reminders.php
 * ***********************************************************************************************
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once(__DIR__ . '/system/common.php');
use Admidio\Infrastructure\Utils\Maintenance;

if ($gDb instanceof Admidio\Infrastructure\Database) {
    $maintenance = new Maintenance($gDb);
    echo $maintenance->sendEventReminders() . PHP_EOL;
} else {
    echo "Database connection not available." . PHP_EOL;
}
