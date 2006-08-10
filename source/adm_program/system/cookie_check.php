<?php
/******************************************************************************
 * Prueft, ob Cookies angelegt werden koennen
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

require("common.php");

if(isset($_COOKIE['adm_session']) == false)
{
	unset($_SESSION['login_forward_url']);
	$g_message->setForwardUrl("home");
	$g_message->show("no_cookie", $g_current_organization->homepage);
}
else
{
	$g_message->setForwardUrl($_SESSION['login_forward_url'], 2000);
	unset($_SESSION['login_forward_url']);
	$g_message->show("login");	
}
?>