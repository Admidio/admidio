<?php
/******************************************************************************
 * Dieses Script muss mit include() eingefuegt werden, wenn der User zum Aufruf
 * einer Seite eingeloggt sein MUSS
 *
 * Ist der User nicht eingeloggt, wird er automatisch auf die Loginseite weitergeleitet
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

if($g_session_valid == false)
{
   // aufgerufene URL ermitteln, damit diese nach dem Einloggen sofort aufgerufen werden kann
   $url = $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
   if(strpos($url, "http://") > 0
   || !strpos($url, "http://"))
      $url = "http://". $url;
   else
      $url = $url;

   // User nicht eingeloggt
   $location = "location: $g_root_path/adm_program/system/login.php?url=". urlencode($url);
   header($location);
   exit();
}

?>