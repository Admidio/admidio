<?php
/**
 ***********************************************************************************************
 * Init Admidio Logger
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'logging.php') {
    exit('This page may not be called directly!');
}

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;

function createLogDirIfNotExist()
{
    // check log folder in "adm_my_files" and create if necessary
    try {
        FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/logs');
    } catch (\RuntimeException $exception) {
        error_log('Log folder could not be created! ' . $exception->getMessage());
    }
}

/**
 * @param string $logDirectory
 * @param int    $logLevel
 * @return \Psr\Log\LoggerInterface
 */
function createAdmidioLogger($logDirectory, $logLevel)
{
    createLogDirIfNotExist();

    $logger = new Logger('Admidio');

    // Append line/file/class/function where the log message came from
    $inspectionProcessor = new IntrospectionProcessor();
    $logger->pushProcessor($inspectionProcessor);

    // Params: format, dateFormat, allowInlineLineBreaks, ignoreEmptyContextAndExtra
    $formatter = new LineFormatter(null, 'Y-m-d H:i:s.u', false, true);

    $rotatingFileHandler = new RotatingFileHandler($logDirectory . '/admidio.log', 0, $logLevel, true, FileSystemUtils::DEFAULT_MODE_FILE);
    $errorLogHandler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR);

    $rotatingFileHandler->setFormatter($formatter);
    $errorLogHandler->setFormatter($formatter);

    $logger->pushHandler($rotatingFileHandler);
    $logger->pushHandler($errorLogHandler);

    initLogging($logger);

    return $logger;
}

/**
 * @param \Psr\Log\LoggerInterface $logger
 */
function initLogging(\Psr\Log\LoggerInterface $logger)
{
    $logger->info('##################################################################################################');
    $logger->info('URL: ' . CURRENT_URL);

    // Log Constants
    $constants = array(
        'VERSIONS' => array(
            'ADMIDIO' => ADMIDIO_VERSION_TEXT,
            'PHP'     => PHP_VERSION
        ),
        'SCHEME'           => SCHEME,
        'HTTPS'            => HTTPS,
        'PORT'             => PORT,
        'HOST'             => HOST,
        'DOMAIN'           => DOMAIN,
        'ADMIDIO_URL_PATH' => ADMIDIO_URL_PATH,
        'URLS' => array(
            'ADMIDIO_URL' => ADMIDIO_URL,
            'FILE_URL'    => FILE_URL,
            'CURRENT_URL' => CURRENT_URL
        ),
        'PATHS' => array(
            'SERVER_PATH'  => SERVER_PATH,
            'ADMIDIO_PATH' => ADMIDIO_PATH,
            'CURRENT_PATH' => CURRENT_PATH
        )
    );
    $logger->info('CONSTANTS: URLS & PATHS & FOLDERS', $constants);
}

$logLevel = Logger::WARNING;
if ($gDebug) {
    $logLevel = Logger::DEBUG;
}
$gLogger = createAdmidioLogger(ADMIDIO_PATH . FOLDER_DATA . '/logs', $logLevel);
