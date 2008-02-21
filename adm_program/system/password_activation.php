<?php
/******************************************************************************
 * Passwort Aktivierung
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * aid		..	Activation Id für die Bestaetigung das der User wirklich ein neues Passwort wuenscht
 * usr_id	..  Die Id des Useres der ein neues Passwort wuenscht
 *****************************************************************************/
 
require_once("common.php");
getVars();
/*********************HTML_TEIL*******************************/

// Html-Kopf ausgeben
$g_layout['title'] = $g_organization." - Passwort aktivieren";

require(THEME_SERVER_PATH. "/overall_header.php");

if(isset($aid) && isset($usr_id))
{
	$password_md5	= "";
	$aid_db			= "";
	
	list($aid_db,$password_md5) = getActivationcodeAndNewPassword($usr_id);
	
	if($aid_db == $aid)
	{
		$sql    = "UPDATE ". TBL_USERS. " SET usr_password = '$password_md5'
				WHERE `". TBL_USERS. "`.`usr_id` = ". $usr_id;
		$result = $g_db->query($sql);
		$sql    = "UPDATE ". TBL_USERS. " SET usr_activation_code = ''
				WHERE `". TBL_USERS. "`.`usr_id` = ". $usr_id;
		$result = $g_db->query($sql);
		$sql    = "UPDATE ". TBL_USERS. " SET usr_new_password  = ''
				WHERE `". TBL_USERS. "`.`usr_id` = ". $usr_id;
		$result = $g_db->query($sql);
		
		$g_message->setForwardUrl("".$g_root_path."/adm_program/system/login.php", 2000);
		$g_message->show("password_activation_password_saved");
	}
	else
	{
		$g_message->show("password_activation_id_not_valid");
		die();
	}
}
else
{
	$g_message->show("invalid");
	die();
}

/***************************Seitenende***************************/
require(THEME_SERVER_PATH. "/overall_footer.php");

function getVars() 
{
  global $HTTP_GET_VARS;
  foreach ($HTTP_GET_VARS as $key => $value) 
  {
    global $$key;
    $$key = $value;
  }
}
function getActivationcodeAndNewPassword($user_id)
{
	global $g_db;
	$sql = "SELECT usr_activation_code,usr_new_password FROM ". TBL_USERS. " WHERE `". TBL_USERS. "`.`usr_id` = ". $user_id;
	$result = $g_db->query($sql);
	while ($row = $g_db->fetch_object($result))
	{
		return array($row->usr_activation_code,$row->usr_new_password);
	}
}