<?php
/**
 ***********************************************************************************************
 * Admidio Autoload
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Autoloading function of class files. This function will be later registered
 * for default autoload implementation. Therefore the class name must be the same
 * as the file name except for case sensitive.
 * @param string $className Name of the class for which the file should be loaded.
 * @return null|false Return @b false if the file for the class wasn't found.
 */
function admFuncAutoload($className)
{
    global $gLogger;

    $libFiles = array(
        ADMIDIO_PATH . FOLDER_CLASSES . '/' . strtolower($className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/monolog/src/' . str_replace('\\', '/', $className) . '.php',
//        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/phpass/' . strtolower($className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/phpmailer/class.' . strtolower($className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/psr/log/' . str_replace('\\', '/', $className) . '.php',
//        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/securimage/' . strtolower($className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/zxcvbn-php/src/' . substr(str_replace('\\', '/', $className), 9) . '.php'
    );

    foreach ($libFiles as $libFile)
    {
        if (is_file($libFile))
        {
            include($libFile);
            return null;
        }
    }

    $logErrorMessage = 'Class-File for Class "' . $className . '" could not be found and included!';
    if ($gLogger instanceof \Psr\Log\LoggerInterface)
    {
        $gLogger->critical($logErrorMessage);
    }
    else
    {
        error_log($logErrorMessage);
    }

    return false;
}

// now register this function in this script so only function.php must be included for autoload
spl_autoload_register('admFuncAutoload');
