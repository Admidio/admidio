<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based uppon backupDB Version 1.2.5a-200806190803 
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/login_valid.php");
require_once('backupDB.functions.php');


// nur Webmaster duerfen ein Backup starten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show("norights");
}

$backupabsolutepath = SERVER_PATH. "/adm_my_files/backup/"; // make sure to include trailing slash

//ggf. Ordner für Backups anlegen
if(!file_exists($backupabsolutepath))
{
    mkdir($backupabsolutepath, 0777);
    chmod($backupabsolutepath, 0777);
}

$last_backup_file = '';

//Zeitpunkt des letzten Backups bestimmen
if ($handle = opendir($backupabsolutepath)) 
{
    while (false !== ($file = readdir($handle))) 
	{
        if ($file != "." && $file != "..") 
		{
            $last_backup_file = $backupabsolutepath.$file;
        }
    }
    closedir($handle);	
}

$g_layout['title'] = "Datenbank Backup";
require(THEME_SERVER_PATH. "/overall_header.php");
echo "
<h1 class=\"moduleHeadline\">Datenbank Backup</h1>";

flush();

echo "Das letzte Backup ist vom ". date ("d.m.Y G:i:s", filemtime($last_backup_file))."<br/>";
echo "Neues Backup starten!";


echo "<form action=\"$g_root_path/adm_program/administration/backup/backupDB_function.php\" method=\"post\">";
echo '<input type="submit" value="Go">';
echo '</form>';



require(THEME_SERVER_PATH. "/overall_footer.php");
?>
