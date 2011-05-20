<?php
/******************************************************************************
 * Html-Kopf der in allen Admidio-Dateien integriert wird
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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

if(isset($g_layout['title']))
{
    $g_layout['title'] = strStripTags($g_layout['title']);
}
else
{
    $g_layout['title'] = '';
}

if(isset($g_layout['header']) == false)
{
    $g_layout['header'] = '';
}

if(isset($g_layout['onload']))
{
    $g_layout['onload'] = ' onload="'. $g_layout['onload']. '"';
}
else
{
    $g_layout['onload'] = '';
}

if(isset($g_layout['includes']) == false)
{
    $g_layout['includes'] = true;
}

if(strlen($g_layout['title']) > 0)
{
    $g_page_title = $g_current_organization->getValue('org_longname'). ' - '. $g_layout['title'];
}
else
{
	$g_page_title = $g_current_organization->getValue('org_longname');
}

header('Content-type: text/html; charset=utf-8'); 
echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
<head>
    <!-- (c) 2004 - 2011 The Admidio Team - http://www.admidio.org -->
    
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    
    <title>'. $g_page_title. '</title>    
    
    <link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/system.css" />
	<link rel="stylesheet" href="'.THEME_PATH. '/css/colorbox.css" type="text/css" media="screen" />

    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.js"></script>
    <script type="text/javascript"><!-- 
		var gRootPath  = "'. $g_root_path. '"; 
		var gThemePath = "'. THEME_PATH. '";
		var gMonthNames = new Array("'.$g_l10n->get('SYS_JANUARY').'","'.$g_l10n->get('SYS_FEBRUARY').'","'.$g_l10n->get('SYS_MARCH').'","'.$g_l10n->get('SYS_APRIL').'","'.$g_l10n->get('SYS_MAY').'","'.$g_l10n->get('SYS_JUNE').'","'.$g_l10n->get('SYS_JULY').'","'.$g_l10n->get('SYS_AUGUST').'","'.$g_l10n->get('SYS_SEPTEMBER').'","'.$g_l10n->get('SYS_OCTOBER').'","'.$g_l10n->get('SYS_NOVEMBER').'","'.$g_l10n->get('SYS_DECEMBER').'","'.$g_l10n->get('SYS_JAN').'","'.$g_l10n->get('SYS_FEB').'","'.$g_l10n->get('SYS_MAR').'","'.$g_l10n->get('SYS_APR').'","'.$g_l10n->get('SYS_MAY').'","'.$g_l10n->get('SYS_JUN').'","'.$g_l10n->get('SYS_JUL').'","'.$g_l10n->get('SYS_AUG').'","'.$g_l10n->get('SYS_SEP').'","'.$g_l10n->get('SYS_OCT').'","'.$g_l10n->get('SYS_NOV').'","'.$g_l10n->get('SYS_DEC').'");
        var gTranslations = new Array("'.$g_l10n->get('SYS_MON').'","'.$g_l10n->get('SYS_TUE').'","'.$g_l10n->get('SYS_WED').'","'.$g_l10n->get('SYS_THU').'","'.$g_l10n->get('SYS_FRI').'","'.$g_l10n->get('SYS_SAT').'","'.$g_l10n->get('SYS_SUN').'","'.$g_l10n->get('SYS_TODAY').'","'.$g_l10n->get('SYS_LOADING_CONTENT').'");
		$(document).ready(function(){
			$("a[rel=\'colorboxHelp\']").colorbox({preloading:true,photo:false,speed:300,rel:\'nofollow\'});
		});
	--></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/colorbox/jquery.colorbox.js"></script>
    <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/js/common_functions.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/tooltip/ajax-tooltip.js"></script>
    
    '. $g_layout['header'];

    if($g_layout['includes'])
    {
        require(THEME_SERVER_PATH. '/my_header.php');
    }
    
echo '</head>
<body'. $g_layout['onload']. '>';
    if($g_layout['includes'])
    {
        require(THEME_SERVER_PATH. '/my_body_top.php');
        if(isset($g_db))
        {
            // falls Anwender andere DB nutzt, hier zur Sicherheit wieder zu Admidio-DB wechseln
            $g_db->setCurrentDB();
        }
    }

 ?>