<?php
/******************************************************************************
 * Kategorien anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Moderatoren duerfen Kategorien erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["cat_id"]))
{
    if(is_numeric($_GET["cat_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $cat_id = $_GET["cat_id"];
}
else
{
    // neue Kategorie anlegen
    $cat_id = 0;
    
    if(isset($_GET["type"]))
    {
        if($_GET["type"] != "ROL" && $_GET["type"] != "LNK")
        {
            $g_message->show("invalid");
        }
    }
    else
    {
        $g_message->show("invalid");
    }    
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
    if($cat_id > 0)
    {
        $sql    = "SELECT * FROM ". TBL_CATEGORIES. " WHERE cat_id = {0}";
        $sql    = prepareSQL($sql, array($cat_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
    
        if (mysql_num_rows($result) > 0)
        {
            $row_cat = mysql_fetch_object($result);
            $form_values['name']   = $row_cat->cat_name;
            $form_values['hidden'] = $row_cat->cat_hidden;
        }
    }
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Kategorie</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"categories_function.php?cat_id=$cat_id&amp;type=". $_GET["type"]. "&amp;mode=1\" method=\"post\" id=\"edit_category\">
            <div class=\"formHead\">";
                if($cat_id > 0)
                {
                    echo strspace("Kategorie ändern");
                }
                else
                {
                    echo strspace("Kategorie anlegen");
                }
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 23%; float: left;\">Name:</div>
                    <div style=\"text-align: left; margin-left: 24%;\">
                        <input type=\"text\" id=\"name\" name=\"name\" size=\"30\" maxlength=\"100\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">
                        <acronym title=\"Pflichtfeld\" style=\"color: #990000;\">*</acronym>
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

                <hr width=\"85%\" />

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
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('name').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>