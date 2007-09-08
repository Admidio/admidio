<?php
/*
#################################################################
# IBPS E-C@ard                       Version 1.01               #
# Copyright 2002 IBPS Friedrichs     info@ibps-friedrichs.de    #
#################################################################
# Filename: ecard_preview.php                                   #
# Letzte Änderung: 28.01.2003                                   #
# Sprachversion: deutsch (andere noch nicht verfügbar)          #
#################################################################
*/
include("ecard_lib.php");
require_once("../../system/common.php");
$propotional_width	= "";
$propotional_height	= "";
$tmpl_folder		= "";
if(isset($_GET['width']))
{
	$propotional_width  = $_GET['width'];
}
if(isset($_GET['height']))
{
	$propotional_height = $_GET['height'];
}
if(isset($_GET['tmplfolder']))
{
	$tmpl_folder		= $_GET['tmplfolder'];
}

getPostGetVars();
list($error,$ecard_data_to_parse) = get_ecard_template($ecard["template_name"],$tmpl_folder);
if ($error) 
{
	header("Location:http://10.1.19.108/ecards/templates/error.htm");
} 
else 
{
  echo parse_ecard_template($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$propotional_width,$propotional_height);
}
?>
