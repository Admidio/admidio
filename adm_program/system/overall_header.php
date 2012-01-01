<?php
/******************************************************************************
 * Html-Kopf der in allen Admidio-Dateien integriert wird
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!! W I C H T I G !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * Diese Datei bitte NICHT anpassen, da diese bei jedem Update ueberschrieben
 * werden sollte. Individuelle Anpassungen koennen in der header.php bzw. der 
 * body_top.php im Ordner adm_config gemacht werden.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 *****************************************************************************/

if(isset($gLayout['title']))
{
    $gLayout['title'] = strStripTags($gLayout['title']);
}
else
{
    $gLayout['title'] = '';
}

if(isset($gLayout['header']) == false)
{
    $gLayout['header'] = '';
}

if(isset($gLayout['onload']))
{
    $gLayout['onload'] = ' onload="'. $gLayout['onload']. '"';
}
else
{
    $gLayout['onload'] = '';
}

if(isset($gLayout['includes']) == false)
{
    $gLayout['includes'] = true;
}

if(strlen($gLayout['title']) > 0)
{
    $g_page_title = $gCurrentOrganization->getValue('org_longname'). ' - '. $gLayout['title'];
}
else
{
	$g_page_title = $gCurrentOrganization->getValue('org_longname');
}

header('Content-type: text/html; charset=utf-8'); 
echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
<head>
    <!-- (c) 2004 - 2012 The Admidio Team - http://www.admidio.org -->
    
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    
    <title>'. $g_page_title. '</title>    
    
    <link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/system.css" />
	<link rel="stylesheet" href="'.THEME_PATH. '/css/colorbox.css" type="text/css" media="screen" />

    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.js"></script>
    <script type="text/javascript"><!-- 
		var gRootPath  = "'. $g_root_path. '"; 
		var gThemePath = "'. THEME_PATH. '";
		var gMonthNames = new Array("'.$gL10n->get('SYS_JANUARY').'","'.$gL10n->get('SYS_FEBRUARY').'","'.$gL10n->get('SYS_MARCH').'","'.$gL10n->get('SYS_APRIL').'","'.$gL10n->get('SYS_MAY').'","'.$gL10n->get('SYS_JUNE').'","'.$gL10n->get('SYS_JULY').'","'.$gL10n->get('SYS_AUGUST').'","'.$gL10n->get('SYS_SEPTEMBER').'","'.$gL10n->get('SYS_OCTOBER').'","'.$gL10n->get('SYS_NOVEMBER').'","'.$gL10n->get('SYS_DECEMBER').'","'.$gL10n->get('SYS_JAN').'","'.$gL10n->get('SYS_FEB').'","'.$gL10n->get('SYS_MAR').'","'.$gL10n->get('SYS_APR').'","'.$gL10n->get('SYS_MAY').'","'.$gL10n->get('SYS_JUN').'","'.$gL10n->get('SYS_JUL').'","'.$gL10n->get('SYS_AUG').'","'.$gL10n->get('SYS_SEP').'","'.$gL10n->get('SYS_OCT').'","'.$gL10n->get('SYS_NOV').'","'.$gL10n->get('SYS_DEC').'");
        var gTranslations = new Array("'.$gL10n->get('SYS_MON').'","'.$gL10n->get('SYS_TUE').'","'.$gL10n->get('SYS_WED').'","'.$gL10n->get('SYS_THU').'","'.$gL10n->get('SYS_FRI').'","'.$gL10n->get('SYS_SAT').'","'.$gL10n->get('SYS_SUN').'","'.$gL10n->get('SYS_TODAY').'","'.$gL10n->get('SYS_LOADING_CONTENT').'");
		$(document).ready(function(){
			$("a[rel=\'colorboxHelp\']").colorbox({preloading:true,photo:false,speed:300,rel:\'nofollow\'});
		});
	--></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/colorbox/jquery.colorbox.js"></script>
    <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/js/common_functions.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/tooltip/ajax-tooltip.js"></script>
    
    '. $gLayout['header'];

    if($gLayout['includes'])
    {
        require(THEME_SERVER_PATH. '/my_header.php');
    }
    
echo '</head>
<body'. $gLayout['onload']. '>';
    if($gLayout['includes'])
    {
        require(THEME_SERVER_PATH. '/my_body_top.php');
        if(isset($gDb))
        {
            // falls Anwender andere DB nutzt, hier zur Sicherheit wieder zu Admidio-DB wechseln
            $gDb->setCurrentDB();
        }
    }

 ?>