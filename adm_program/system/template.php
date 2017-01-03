<?php
/**
 ***********************************************************************************************
 * Common functions for Email Templates
 *
 * @copyright 2004-2017 The Admidio Team
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
 * @param  string $filename
 * @return string
 */
function admReadTemplateFile($filename)
{
    $file = ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/' . $filename;
    if (is_file($file))
    {
        $fp = fopen($file, 'rb');
        $str = '';
        while (!feof($fp))
        {
            $str .= fread($fp, 1024);
        }

        return $str;
    }

    return '#message#';
}
