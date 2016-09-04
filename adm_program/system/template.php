<?php
/**
 ***********************************************************************************************
 * Common functions for Email Templates
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
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
    if (is_file(SERVER_PATH. '/adm_my_files/mail_templates/'.$filename))
    {
        $fp = fopen(SERVER_PATH. '/adm_my_files/mail_templates/'.$filename, 'r');
        $str = '';
        while(!feof($fp))
        {
            $str .= fread($fp, 1024);
        }

        return $str;
    }

    return '#message#';
}
