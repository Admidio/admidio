<?php
/**
 ***********************************************************************************************
 * Common functions for Email Templates
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'template.php')
{
    exit('This page may not be called directly!');
}

/**
 * Function to Read a file and store all data into a variable
 * @deprecated 3.3.0:4.0.0 "admReadTemplateFile($filename)" is deprecated. Use "FileSystemUtils::readFile($path)" instead.
 * @param string $filename
 * @return string
 */
function admReadTemplateFile($filename)
{
    global $gLogger;

    $gLogger->warning('DEPRECATED: "admReadTemplateFile($filename)" is deprecated. Use "FileSystemUtils::readFile($path)" instead!');

    $file = ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/' . $filename;

    try
    {
        return FileSystemUtils::readFile($file);
    }
    catch (\RuntimeException $exception)
    {
        return '#message#';
    }
}
