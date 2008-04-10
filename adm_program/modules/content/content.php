<?php
/******************************************************************************
 * Ankuendigungen auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * page: Datei aus der der Inhalt ausgelesen werden soll
 *
 *****************************************************************************/

require("../../system/common.php");


require(THEME_SERVER_PATH. "/overall_header.php");

// Uebergabevariablen hier erstellen
$main_uebergabe = substr(strchr($_SERVER['QUERY_STRING'], "?"), 1);
parse_str($main_uebergabe);

// die uebergebene Datei linken
if(strlen($_SERVER['QUERY_STRING']) > 0)
{
  	$query_string =  substr($_SERVER['QUERY_STRING'], strrpos($_SERVER['QUERY_STRING'], "=")+1);
        
    if(strpos($_SERVER['QUERY_STRING'], "?") > 0)
    {
    	$main_link_seite = $g_root_path."/adm_my_files/content/". substr($_SERVER['QUERY_STRING'], 0, strpos($_SERVER['QUERY_STRING'], "?"));
    }
    else
    {
        $main_link_seite = $g_root_path."/adm_my_files/content/". $query_string;
    }  
	
    $content=fopen($main_link_seite, "r");
	fpassthru($content);
}
else
{
	echo "";
}       
require(THEME_SERVER_PATH. "/overall_footer.php");

?>