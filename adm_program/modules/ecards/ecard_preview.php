<?php
/******************************************************************************
 * Grußkarte Vorschau
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/
 
/****************** includes *************************************************/
require_once("../../system/common.php");
require_once("ecard_function.php");


/****************** Ausgabe des geparsten Templates **************************/
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

getVars();
list($error,$ecard_data_to_parse) = getEcardTemplate($ecard["template_name"],$tmpl_folder);
if ($error) 
{
	echo "ERROR 404";
} 
else 
{
	echo parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$propotional_width,$propotional_height);
}
?>
