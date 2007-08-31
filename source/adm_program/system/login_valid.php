<?php
/******************************************************************************
 * Dieses Script muss mit include() eingefuegt werden, wenn der User zum Aufruf
 * einer Seite eingeloggt sein MUSS
 *
 * Ist der User nicht eingeloggt, wird er automatisch auf die Loginseite weitergeleitet
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

if ('login_valid.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('Diese Seite darf nicht direkt aufgerufen werden !');
}

if($g_valid_login == false)
{
    if(isset($_SESSION['login_forward_url']) == false)
    {
        // aufgerufene URL merken, damit diese nach dem Einloggen sofort aufgerufen werden kann
        $_SESSION['login_forward_url'] = CURRENT_URL;
    }
    
    // User nicht eingeloggt -> Loginseite aufrufen
    $location = "Location: $g_root_path/adm_program/system/login.php";
    header($location);
    exit();
}

?>