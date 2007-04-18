<?php
/******************************************************************************
 * Profilfelder anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * usf_id: ID des Feldes, das bearbeitet werden soll
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
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Moderatoren duerfen Profilfelder erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["usf_id"]))
{
    if(is_numeric($_GET["usf_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $usf_id = $_GET["usf_id"];
}
else
{
    $usf_id = 0;
}

$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['fields_request']))
{
   $form_values = $_SESSION['fields_request'];
   unset($_SESSION['fields_request']);
}
else
{ 
    $form_values['name']        = "";
    $form_values['description'] = "";
    $form_values['type']        = "";
    $form_values['locked']      = 0;
    
    // Wenn eine Feld-ID uebergeben wurde, soll das Feld geaendert werden
    // -> Felder mit Daten des Feldes vorbelegen    
    if($usf_id > 0)
    {
        $sql    = "SELECT * FROM ". TBL_USER_FIELDS. " WHERE usf_id = {0}";
        $sql    = prepareSQL($sql, array($usf_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
    
        if (mysql_num_rows($result) > 0)
        {
            $row_usf = mysql_fetch_object($result);
    
            $form_values['name']        = $row_usf->usf_name;
            $form_values['description'] = $row_usf->usf_description;
            $form_values['type']        = $row_usf->usf_type;
            $form_values['locked']      = $row_usf->usf_locked;
        }
    }
}

// zusaetzliche Daten fuer den Html-Kopf setzen
if($usf_id > 0)
{
    $g_layout['title']  = "Profilfeld &auml;ndern";
}
else
{
    $g_layout['title']  = "Profilfeld anlegen";
}

// Html-Kopf ausgeben
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<form action=\"fields_function.php?usf_id=$usf_id&amp;mode=1\" method=\"post\" id=\"edit_field\">
    <div class=\"formHead\" style=\"width: 400px\">$g_layout['title']</div>
    <div class=\"formBody\" style=\"width: 400px\">
        <div>
            <div style=\"text-align: right; width: 28%; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"text\" id=\"name\" name=\"name\" size=\"20\" maxlength=\"13\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"text\" name=\"description\" size=\"38\" maxlength=\"255\" value=\"". htmlspecialchars($form_values['description'], ENT_QUOTES). "\">
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Datentyp:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <select size=\"1\" name=\"type\">
                    <option value=\" \""; 
                        if(strlen($form_values['type']) == 0) 
                        {
                            echo " selected=\"selected\"";
                        }
                        echo ">- Bitte w&auml;hlen -</option>\n
                    <option value=\"TEXT\"";     
                        if($form_values['type'] == "TEXT") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Text (30 Zeichen)</option>\n
                    <option value=\"TEXT_BIG\""; 
                        if($form_values['type'] == "TEXT_BIG") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Text (255 Zeichen)</option>\n
                    <option value=\"NUMERIC\"";  
                        if($form_values['type'] == "NUMERIC") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Zahl</option>\n
                    <option value=\"CHECKBOX\""; 
                        if($form_values['type'] == "CHECKBOX") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Ja / Nein</option>\n
                </select>
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">
                <img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur f&uuml;r Moderatoren sichtbar\">
            </div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"checkbox\" id=\"locked\" name=\"locked\" ";
                if(isset($form_values['locked']) && $form_values['locked'] == 1)
                {
                    echo " checked ";
                }
                echo " value=\"1\" />
                <label for=\"locked\">Feld nur f&uuml;r Moderatoren sichtbar&nbsp;</label>
                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_locked','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
        </div>

        <hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
            &nbsp;Speichern</button>
        </div>";
    echo "</div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('name').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>