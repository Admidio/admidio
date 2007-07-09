<?php
/******************************************************************************
 * Dieses Script kann als Zurueck-Button verlinkt werden und ruft das letzte
 * sinnvolle Script von Admidio auf. Die Scripte werden intern in der 
 * Navigation-Klasse verwaltet
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

include("common.php");

// die letzte Url aus dem Stack loeschen, da dies die aktuelle Seite ist
$_SESSION['navigation']->deleteLastUrl();

// Jetzt die "neue" letzte Url aufrufen
$next_url = $_SESSION['navigation']->getUrl();
if(strlen($next_url) == 0)
{
    $next_url = "$g_root_path/$g_main_page";
}
header("Location: $next_url");
 
?>