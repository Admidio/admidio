<?php
/******************************************************************************
 * Html-Kopf der in allen Admidio-Dateien integriert wird
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!! W I C H T I G !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * Diese Datei bitte NICHT anpassen, da diese bei jedem Update ueberschrieben
 * werden sollte. Individuelle Anpassungen koennen in der header.php bzw. der 
 * body_top.php im Ordner adm_config gemacht werden.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

if(isset($g_layout['title']))
{
    $g_layout['title'] = strStripTags($g_layout['title']);
}
else
{
    $g_layout['title'] = "";
}

if(isset($g_layout['header']) == false)
{
    $g_layout['header'] = "";
}

if(isset($g_layout['onload']))
{
    $g_layout['onload'] = " onload=\"". $g_layout['onload']. "\"";
}
else
{
    $g_layout['onload'] = "";
}

if(isset($g_layout['includes']) == false)
{
    $g_layout['includes'] = true;
}

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>". $g_current_organization->getValue("org_longname"); 
    if(strlen($g_layout['title']) > 0)
    {
        echo " - ". $g_layout['title'];
    }
    echo "</title>
    
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_program/layout/system.css\">\n";
    
    if(strlen($g_preferences['user_css']) > 0)
    {
        echo "    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/". $g_preferences['user_css']. "\">\n";
    }

    echo $g_layout['header']. "

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    if($g_layout['includes'])
    {
        require(SERVER_PATH. "/adm_config/header.php");
    }
    
echo "</head>
<body". $g_layout['onload']. ">";
    if($g_layout['includes'])
    {
        require(SERVER_PATH. "/adm_config/body_top.php");
    }

    echo "<div id=\"system_align\" style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"". $g_preferences['system_align']. "\">";
 
 ?>