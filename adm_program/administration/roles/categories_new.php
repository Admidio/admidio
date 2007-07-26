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
require("../../system/category_class.php");

// lokale Variablen der Uebergabevariablen initialisieren
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

// UserField-objekt anlegen
$category = new Category($g_db);

if($req_cat_id > 0)
{
    $category->getCategory($req_cat_id);
    
    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue("cat_org_id") >  0
    && $category->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['categories_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['categories_request'] as $key => $value)
    {
        if(strpos($key, "cat_") == 0)
        {
            $category->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['categories_request']);
}

// Html-Kopf ausgeben
$g_layout['title'] = "Kategorie";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=$req_cat_id&amp;type=". $_GET["type"]. "&amp;mode=1\" method=\"post\" id=\"edit_category\">
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
                <input type=\"text\" id=\"cat_name\" name=\"cat_name\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($category->getValue("cat_name"), ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 23%; float: left;\">
                <label for=\"hidden\"><img src=\"$g_root_path/adm_program/images/user_key.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\"></label>
            </div>
            <div style=\"text-align: left; margin-left: 24%;\">
                <input type=\"checkbox\" id=\"cat_hidden\" name=\"cat_hidden\" ";
                    if($category->getValue("cat_hidden") == 1)
                    {
                        echo " checked ";
                    }
                    echo " value=\"1\" />
                <label for=\"cat_hidden\">Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar&nbsp;</label>
            </div>
        </div>

        <hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button id=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
            &nbsp;Speichern</button>
        </div>";
    echo "</div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('name').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>