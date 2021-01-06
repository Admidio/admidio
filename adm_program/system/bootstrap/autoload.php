<?php
/**
 ***********************************************************************************************
 * Admidio Autoload
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'autoload.php')
{
    exit('This page may not be called directly!');
}

/**
 * Autoloading function of class files. This function will be later registered
 * for default autoload implementation. Therefore the class name must be the same
 * as the file name except for case sensitive.
 * @param string $className Name of the class for which the file should be loaded.
 */
function admFuncAutoload($className)
{
    $libFiles = array(
        ADMIDIO_PATH . FOLDER_CLASSES . '/' . $className . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/monolog/src/' . str_replace('\\', '/', $className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/smarty/' . $className . '.class.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/psr/' . str_replace('\\', '/', $className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/phpmailer/src/' . str_replace('\\', '/', substr($className, strlen('PHPMailer\\PHPMailer'))) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/zxcvbn-php/src/' . str_replace('\\', '/', substr($className, strlen('ZxcvbnPhp'))) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/securimage/' . strtolower($className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_CLIENT . '/jquery-file-upload/server/php/' . $className . '.php', // PHP files in the client folder
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/tcpdf/' . strtolower($className) . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/htmlawed/src/' . $className . '.php',
        ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/phpass/src/' . str_replace('\\', '/', $className) . '.php' // old phpass password hashing lib for backward compatibility
    );

    foreach ($libFiles as $libFile)
    {
        if (is_file($libFile))
        {
            require($libFile);
            return null;
        }
    }
}

// now register this function in this script so only function.php must be included for autoload
spl_autoload_register('admFuncAutoload');
