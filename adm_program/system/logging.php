<?php
/**
 ***********************************************************************************************
 * Init Admidio Logger
 *
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Processor\IntrospectionProcessor;

$gLogger = new Logger('Admidio');

$logLevel = Logger::WARNING;
if ($gDebug)
{
    $logLevel = Logger::DEBUG;
}

// If "$gDebug = true" append line/file/class/function where the log message came from
$gLogger->pushProcessor(new IntrospectionProcessor($logLevel));

$formatter = new LineFormatter(null, null, false, true);
$streamHandler = new StreamHandler(SERVER_PATH . '/adm_my_files/logs/admidio.log', $logLevel);
$errorLogHandler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR);

$streamHandler->setFormatter($formatter);
$errorLogHandler->setFormatter($formatter);

$gLogger->pushHandler($streamHandler);
$gLogger->pushHandler($errorLogHandler);

$gLogger->info('#################################################################################################');
$gLogger->info('Admidio Logger initialized');
$gLogger->info($_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING']);
$gLogger->info('Memory usage: ' . round(memory_get_usage() / 1024, 1) . ' KB');
