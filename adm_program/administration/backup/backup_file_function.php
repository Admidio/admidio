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
 * Based on backupDB Version 1.2.5a-200806190803 
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Webmaster duerfen ein Backup starten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show("norights");
}

$backupabsolutepath = SERVER_PATH. "/adm_my_files/backup/"; // make sure to include trailing slash

if(isset($_GET['job']) && $_GET['job'] != 'rename' && $_GET['job'] != 'delete')
{
    $g_message->show('invalid');
}

if(!isset($_GET['file_id']))
{
    $g_message->show('invalid');
}

if(isset($_GET['job']) && $_GET['job']=='delete')
{
    if(unlink($backupabsolutepath.$_GET['file_id']))
    {
        echo 'done'; 
    }
    exit();
}
