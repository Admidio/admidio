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
<div class=\"formLayout\" id=\"import_form\">
    <div class=\"formHead\">Benutzer aus Datei importieren</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt>Format:</dt>
                    <dd>CSV</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"userfile\">Datei ausw&auml;hlen:</label></dt>
                    <dd><input id=\"userfile\" name=\"userfile\" size=\"30\" type=\"file\"></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"rol_id\">Rolle zuordnen:</label></dt>
                    <dd>";
                        // Combobox mit allen Rollen ausgeben
                        echo generateRoleSelectBox();

                        echo "&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=role_assign','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                    </dd>
                </dl>
            </li>
            <li>
                <label for=\"user_import_mode\">Bereits existierende Benutzer</label>&nbsp;
                <select size=\"1\" id=\"user_import_mode\" name=\"user_import_mode\">
                    <option value=\"1\" selected>behalten</option>
                    <option value=\"2\">duplizieren</option>
                    <option value=\"3\">ersetzen</option>
                    <option value=\"4\">erg&auml;nzen</option>
                </select>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"weiter\" type=\"submit\" value=\"weiter\" tabindex=\"2\">Weiter&nbsp;
            <img src=\"$g_root_path/adm_program/images/forward.png\" alt=\"Weiter\"></button>
        </div>
    </div>
</div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('userfile').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>