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
require("../../system/user_field_class.php");

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->editUser())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usf_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['usf_id']))
{
    if(is_numeric($_GET['usf_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_usf_id = $_GET['usf_id'];
}

$_SESSION['navigation']->addUrl($g_current_url);

// benutzerdefiniertes Feldobjekt anlegen
$user_field = new UserField($g_adm_con);

if($req_usf_id > 0)
{
    $user_field->getUserField($req_usf_id);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if($user_field->getValue("usf_org_id") >  0
    && $user_field->getValue("usf_org_id") != $g_current_organization->id)
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['fields_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['fields_request'] as $key => $value)
    {
        if(strpos($key, "usf_") == 0)
        {
            $user_field->setValue($key, $value);
        }        
    }
    unset($_SESSION['fields_request']);
}

$html_disabled = "";
if($user_field->getValue("usf_system") == 1)
{
    $html_disabled = " disabled ";
}

// zusaetzliche Daten fuer den Html-Kopf setzen
if($req_usf_id > 0)
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
<form action=\"fields_function.php?usf_id=$req_usf_id&amp;mode=1\" method=\"post\" id=\"edit_field\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
    <div class=\"formBody\">
        <div>
            <div style=\"text-align: right; width: 28%; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"text\" id=\"usf_name\" name=\"usf_name\" $html_disabled size=\"20\" maxlength=\"15\" value=\"". htmlspecialchars($user_field->getValue("usf_name"), ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"text\" name=\"usf_description\" style=\"width: 330px;\" maxlength=\"255\" value=\"". htmlspecialchars($user_field->getValue("usf_description"), ENT_QUOTES). "\">
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Kategorie:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <select size=\"1\" name=\"usf_cat_id\" $html_disabled>
                    <option value=\" \""; 
                        if($user_field->getValue("usf_cat_id") == 0) 
                        {
                            echo " selected=\"selected\"";
                        }
                        echo ">- Bitte w&auml;hlen -</option>";
                        
                    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                             WHERE (  cat_org_id = $g_current_organization->id
                                   OR cat_org_id IS NULL )
                               AND cat_type   = 'USF'
                             ORDER BY cat_sequence ASC ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);

                    while($row = mysql_fetch_object($result))
                    {
                        echo "<option value=\"$row->cat_id\"";
                            if($user_field->getValue("usf_cat_id") == $row->cat_id)
                            {
                                echo " selected ";
                            }
                        echo ">$row->cat_name</option>";
                    }
                echo "</select>
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>        
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Datentyp:</div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <select size=\"1\" name=\"usf_type\" $html_disabled>
                    <option value=\" \""; 
                        if(strlen($user_field->getValue("usf_type")) == 0) 
                        {
                            echo " selected=\"selected\"";
                        }
                        echo ">- Bitte w&auml;hlen -</option>\n
                    <option value=\"DATE\""; 
                        if($user_field->getValue("usf_type") == "DATE") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Datum</option>\n
                    <option value=\"EMAIL\""; 
                        if($user_field->getValue("usf_type") == "EMAIL") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">E-Mail</option>\n
                    <option value=\"CHECKBOX\""; 
                        if($user_field->getValue("usf_type") == "CHECKBOX") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Ja / Nein</option>\n
                    <option value=\"TEXT\"";     
                        if($user_field->getValue("usf_type") == "TEXT") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Text (50 Zeichen)</option>\n
                    <option value=\"TEXT_BIG\""; 
                        if($user_field->getValue("usf_type") == "TEXT_BIG") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Text (255 Zeichen)</option>\n
                    <option value=\"URL\""; 
                        if($user_field->getValue("usf_type") == "URL") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">URL</option>\n
                    <option value=\"NUMERIC\"";  
                        if($user_field->getValue("usf_type") == "NUMERIC") 
                        {
                            echo " selected=\"selected\""; 
                        }
                        echo ">Zahl</option>\n
                </select>
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">
                <img src=\"$g_root_path/adm_program/images/eye.png\" alt=\"Feld f&uuml;r alle Benutzer sichtbar\">
            </div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"checkbox\" id=\"usf_hidden\" name=\"usf_hidden\" ";
                if($user_field->getValue("usf_hidden") == 0)
                {
                    echo " checked ";
                }
                echo " value=\"1\" />
                <label for=\"usf_hidden\">Feld f&uuml;r alle Benutzer sichtbar&nbsp;</label>
                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_hidden','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">
                <img src=\"$g_root_path/adm_program/images/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\">
            </div>
            <div style=\"text-align: left; margin-left: 29%;\">
                <input type=\"checkbox\" id=\"usf_disabled\" name=\"usf_disabled\" ";
                if($user_field->getValue("usf_disabled") == 1)
                {
                    echo " checked ";
                }
                echo " value=\"1\" />
                <label for=\"usf_disabled\">Feld nur f&uuml;r berechtigte Benutzer editierbar&nbsp;</label>
                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_disabled','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
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
    document.getElementById('usf_name').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>