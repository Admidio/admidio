<?php
/******************************************************************************
 * Funktionen fuer das erstellen von iCal Dateien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 *
 * Uebergaben:
 *
 * mode   :  1 - Termin als iCal exportieren
 * dat_id: Id des Termins, die benutzt werden soll
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

$dat_id  = $_GET['dat_id'];

if($_GET["mode"] == 1)
{
    // Termindaten aus Datenbank holen
    $date = new TblDates($g_adm_con);
    $date->getDate($dat_id);

    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename="'. $date->begin. '.ics"');

    echo $date->getIcal($g_domain);
}

?>