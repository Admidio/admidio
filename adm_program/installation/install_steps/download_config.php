<?php
/**
 ***********************************************************************************************
 * Installation step: download_config
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'download_config.php') {
    exit('This page may not be called directly!');
}

$filename   = 'config.php';
$fileLength = strlen($_SESSION['config_file_content']);

header('Content-Type: text/plain; charset=utf-8');
header('Content-Length: '.$fileLength);
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo $_SESSION['config_file_content'];
exit();
