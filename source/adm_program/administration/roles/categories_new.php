<?php
/******************************************************************************
 * Kategorien anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien, die bearbeitet werden soll
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
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

// lokale Variablen der Uebergabevariablen initialisieren
$req_type   = "";
$req_cat_id = 0;

// Uebergabevariablen pruefen

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != "ROL" && $_GET['type'] != "LNK" && $_GET['type'] != "USF")
    {
        $g_message->show("invalid");
    }
    if($_GET['type'] == "ROL" && $g_current_user->assignRoles() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "LNK" && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "USF" && $g_current_user->editUser() == false)
    {
        $g_message->show("norights");
    }
    $req_type = $_GET['type'];
}
else
{
    $g_message->show("invalid");
}

if(isset($_GET['cat_id']))
{
    if(is_numeric($_GET['cat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_cat_id = $_GET['cat_id'];
}

$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['categories_request']))
{
   $form_values = $_SESSION['categories_request'];
   unset($_SESSION['categories_request']);
}
else
{ 
    $form_values['name']   = " ";
    $form_values['hidden'] = 0;

    // Wenn eine Feld-ID uebergeben wurde, soll das Feld geaendert werden
    // -> Felder mit Daten des Feldes vorbelegen
    if($req_cat_id > 0)
    {
        $sql    = "SELECT * FROM ". TBL_CATEGORIES. " WHERE cat_id = {0}";
        $sql    = prepareSQL($sql, array($req_cat_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
    
        if (mysql_num_rows($result) > 0)
        {
            $row_cat = mysql_fetch_object($result);
            $form_values['name']   = $row_cat->cat_name;
            $form_values['hidden'] = $row_cat->cat_hidden;
        }
    }
}

// Html-Kopf ausgeben
$g_layout['title'] = "Kategorie";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"categories_function.php?cat_id=$req_cat_id&amp;type=". $_GET["type"]. "&amp;mode=1&amp;type=$req_type\" method=\"post\" id=\"edit_category\">
    <div class=\"formHead\">";
        if($req_cat_id > 0)
        {
            echo "Kategorie &auml;ndern";
        }
        else
        {
            echo "Kategorie anlegen";
        }
    echo "</div>
    <div class=\"formBody\">
        <div>
            <div style=\"text-align: right; width: 23%; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 24%;\">
                <input type=\"text\" id=\"name\" name=\"name\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 23%; float: left;\">
                <label for=\"hidden\"><img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\"></label>
            </div>
            <div style=\"text-align: left; margin-left: 24%;\">
                <input type=\"checkbox\" id=\"hidden\" name=\"hidden\" ";
                    if(isset($form_values['hidden']) && $form_values['hidden'] == 1)
                    {
                        echo " checked ";
                    }
                    echo " value=\"1\" />
                <label for=\"hidden\">Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar&nbsp;</label>
            </div>
        </div>

        <hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button id=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"speichern\" type=\"submit\" value=\"speichern\">
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