<?php
/******************************************************************************
 * Common functions for Email Templates
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('template.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('This page may not be called directly !');
}
 
// Function to Read a file
// and store all data into a variable
function admReadTemplateFile($filename) {

if (file_exists(SERVER_PATH. '/adm_my_files/mail_templates/'.$filename)) {
    $fp = fopen(SERVER_PATH. '/adm_my_files/mail_templates/'.$filename,"r");
	$str = "";
	while(!feof($fp)) {
	$str .= fread($fp,1024);
	}
	return $str;
} else {
    return '#message#';
}

}
?>