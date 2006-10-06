<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

$_SESSION['navigation']->addUrl($g_current_url);
unset($_SESSION['fields_request']);

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Organisationsspezifische Profilfelder</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <h1>Organisationsspezifische Profilfelder</h1>

        <p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/organization/fields_new.php\"><img 
                src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Profilfeld anlegen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/organization/fields_new.php\">Profilfeld anlegen</a>
            </span>
        </p>";

        $sql = "SELECT * FROM ". TBL_USER_FIELDS. "
                 WHERE usf_org_shortname LIKE '$g_organization'
                 ORDER BY usf_name ASC ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        
        echo "
        <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
                <th class=\"tableHeader\" style=\"text-align: left;\">Feld <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profil_felder','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\"></th>
                <th class=\"tableHeader\" style=\"text-align: left;\">Beschreibung</th>
                <th class=\"tableHeader\" style=\"text-align: left;\">Datentyp</th>
                <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur f&uuml;r Moderatoren sichtbar\" title=\"Feld nur f&uuml;r Moderatoren sichtbar\"></th>
                <th class=\"tableHeader\">&nbsp;</th>
            </tr>";
        
            if(mysql_num_rows($result) > 0)
            {
                while($row = mysql_fetch_object($result))
                {
                    echo "
                    <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                        <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/organization/field.php?usf_id=$row->usf_id\">$row->usf_name</a></td>
                        <td style=\"text-align: left;\">$row->usf_description</td>
                        <td style=\"text-align: left;\">";
                            if($row->usf_type == "TEXT")
                            {
                                echo "Text (30)";
                            }
                            elseif($row->usf_type == "TEXT_BIG")
                            {
                                echo "Text (255)";
                            }
                            elseif($row->usf_type == "NUMERIC")
                            {
                                echo "Zahl";
                            }
                            elseif($row->usf_type == "CHECKBOX")
                            {
                                echo "Ja / Nein";
                            }
                        echo "</td>
                        <td style=\"text-align: center;\">";
                            if($row->usf_locked == 1)
                            {
                                echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur f&uuml;r Moderatoren sichtbar\" title=\"Feld nur f&uuml;r Moderatoren sichtbar\">";
                            }
                            else
                            {
                                echo "&nbsp;";
                            }
                        echo "</td>
                        <td style=\"text-align: right; width: 45px;\">
                            <a href=\"$g_root_path/adm_program/administration/organization/fields_new.php?usf_id=$row->usf_id\">
                            <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";
                            echo "<a href=\"$g_root_path/adm_program/administration/organization/fields_function.php?mode=3&amp;usf_id=$row->usf_id\"><img
                            src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
                        </td>
                    </tr>";
                }
            }
            else
            {
                echo "<tr>
                    <td colspan=\"5\" style=\"text-align: center;\">
                        <p>Es wurden noch keine organisationsspezifischen Profilfelder angelegt !</p>
                    </td>
                </tr>";
            }
        echo "</table>";

        echo "<p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
            </span>
        </p>
    </div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>