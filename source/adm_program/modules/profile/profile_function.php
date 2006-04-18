<?php
/******************************************************************************
 * verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 *
 * Uebergaben:
 *
 * mode   :  1 - User als vCard exportieren
 * user_id: Id des Users, der bearbeitet werden soll
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

require("../../system/common.php");
require("../../system/login_valid.php");

$user_id  = $_GET['user_id'];

if($_GET["mode"] == 1)
{
    // Userdaten aus Datenbank holen
    $user = new TblUsers($g_adm_con);
    $user->getUser($user_id);

    header('Content-Type: text/x-vcard');
    header('Content-Disposition: attachment; filename="'. $user->first_name. ' '. $user->last_name. '.vcf"');

    echo $user->getVCard();
}

?>