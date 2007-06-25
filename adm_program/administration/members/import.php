<?php
/******************************************************************************
 * Import-Assistent fuer Benutzerdaten
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

require("../../system/common.php");
require("../../system/login_valid.php");

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUser())
{
    $g_message->show("norights");
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show("no_fileuploads");
}

// Html-Kopf ausgeben
$g_layout['title']  = "Benutzer importieren";
    
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<p>&nbsp;</p>
<form id=\"form_import\" action=\"$g_root_path/adm_program/administration/members/import_function.php\" method=\"post\" enctype=\"multipart/form-data\">
    <div class=\"formHead\">Benutzer aus Datei importieren</div>
    <div class=\"formBody\">
        <div style=\"margin-top: 15px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Format:</div>
            <div style=\"text-align: left; margin-left: 32%;\">CSV
            </div>
        </div>
        <div style=\"margin-top: 15px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Datei ausw&auml;hlen:</div>
            <div style=\"text-align: left; margin-left: 32%;\">
                <input id=\"userfile\" name=\"userfile\" size=\"30\" type=\"file\">
            </div>
        </div>
        <div style=\"margin-top: 15px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Rolle zuordnen:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
                // Combobox mit allen Rollen ausgeben
                echo generateRoleSelectBox();

                echo "&nbsp;
                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=role_assign','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
            </div>
        </div>
        <div style=\"margin-top: 15px;\">
            Bereits existierende Benutzer&nbsp;
            <select size=\"1\" id=\"user_import_mode\" name=\"user_import_mode\">
                <option value=\"1\" selected>behalten</option>
                <option value=\"2\">duplizieren</option>
                <option value=\"3\">ersetzen</option>
                <option value=\"4\">erg&auml;nzen</option>
            </select>
        </div>

        <hr class=\"formLine\" style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"weiter\" type=\"submit\" value=\"weiter\" tabindex=\"2\">Weiter&nbsp;
            <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\"></button>
        </div>
    </div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('userfile').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>