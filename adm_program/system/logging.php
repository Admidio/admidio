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
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;

if (!isset($gLogger))
{
    $gLogger = new Logger('Admidio');

    $logLevel = Logger::WARNING;
    if ($gDebug)
    {
        $logLevel = Logger::DEBUG;
    }
    $gLogger->pushHandler(new StreamHandler(SERVER_PATH . '/logs/admidio.log', $logLevel));
    $gLogger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR));

    $gLogger->info('Admidio Logger initialized');
    $gLogger->info($_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING']);
    $gLogger->info('Memory usage: ' . round(memory_get_usage() / 1024, 1) . ' KB');
}
