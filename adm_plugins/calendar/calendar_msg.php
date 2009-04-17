<?php
/******************************************************************************
 * Sidebar-Kalender
 * Ausgabe der Termine und Geburtstage für Ajax Tooltip
 * Version 1.4.1
 *
 * Plugin das den aktuellen Monatskalender auflistet und die Termine und Geburtstage
 * des Monats markiert und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Kompatible ab Admidio-Versions 2.1.0
 *
 * Copyright    : (c) 2007-2009 Matthias Roberg
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
// Header kodieren
header('Content-Type: text/html; charset=UTF-8');

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, "adm_plugins") + 11;
$plugin_file_pos   = strpos(__FILE__, "calendar_msg.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");

// Werte definieren
$geburtstage = "";
$termine_uebergabe = 0;
$geburtstag_uebergabe = 0;
 
// Übergabe Termin ermitteln
if($plg_ter_aktiv == 1)
{
	if(isset($_GET['titel']))
	{
		$titel = $_GET['titel'];
		$termine_uebergabe = 1;
	}
	if(isset($_GET['uhr']))
	{
		$uhr = $_GET['uhr'];
	}
	if(isset($_GET['ort']))
	{
		$ort = $_GET['ort'];
	}
	if(isset($_GET['ganztags']))
	{
		$ganztags = $_GET['ganztags'];
	}
	else
	{
		$ganztags = 0;
	}
	if(isset($_GET['weitere']))
	{
		$weitere = $_GET['weitere'];
	}
	else
	{
		$weitere = 0;
	}
}

// Übergabe Geburtstage ermitteln
if($plg_geb_aktiv == 1)
{
	if(isset($_GET['gebanzahl']))
	{
		$gebanzahl = $_GET['gebanzahl'];
		$geburtstag_uebergabe = 1;
		for($i=1;$i<=$gebanzahl;$i++)
		{
			$gebname = $_GET['gebname'. $i];
			$alter = $_GET['gebalter'. $i];
			if($plg_geb_icon == 1)
			{
				$icon = "<img src=\"$g_root_path/adm_plugins/$plugin_folder/cake.png\" border=\"0\"> ";
			}
			else
			{
				$icon = "";
			}
			$geburtstage = $geburtstage. $icon. "$gebname ($alter)<br>";
		}
	}
}

// Ausgabe formatieren
if($termine_uebergabe == 1)
{
	echo "<div id=\"plgCalendarMSG\" align=\"left\"><b>$titel</b><br>";
	if($ganztags == 1)
	{
		if($ort == "")
		{
			echo "<i>(ganzt&auml;giger Termin)</i></div>";
		}
		else
		{
			echo "$ort <i>(ganzt&auml;giger Termin)</i></div>";
		}
	}
	else
	{
		if($ort == "")
		{
			echo "$uhr Uhr</div>";
		}
		else
		{
			echo "$uhr Uhr, $ort</div>";
		}
	}
	if($weitere >> 0)
	{
		echo "<div id=\"plgCalendarMSG\" align=\"right\"><i>(... weitere)</i></div>";
	}
}

if($geburtstag_uebergabe == 1)
{
	echo "<div id=\"plgCalendarMSG\" align=\"left\">$geburtstage</div>";
}

?>