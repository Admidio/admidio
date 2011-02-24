<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

$backupabsolutepath = SERVER_PATH. "/adm_my_files/backup/"; // make sure to include trailing slash

if(isset($_GET['job']) && $_GET['job'] != 'rename' && $_GET['job'] != 'delete')
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(!isset($_GET['file_id']))
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['job']) && $_GET['job']=='delete')
{
//Pruefungen damit nur Backupfiles geloescht werden koennen

$filename= $_GET['file_id'];

//Pruefung, ob nur gute Dateinamen uebergeben werden
if( -2 == isValidFileName($filename) )
{
	exit();
}


//jetzt darf geloescht werden!
    if(unlink($backupabsolutepath.$_GET['file_id']))
    {
        echo 'done'; 
    }
    exit();
}
