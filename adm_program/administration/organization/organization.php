<?php
/******************************************************************************
 * Gruppierung bearbeiten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
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
 *****************************************************************************/
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/string.php");
require("../../system/tbl_user.php");
require("../../system/session_check_login.php");

// nur Webmaster duerfen Gruppierungen bearbeiten
if(!hasRole("Webmaster"))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$sql    = "SELECT * FROM adm_gruppierung WHERE ag_shortname = '$g_organization' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$row_orga = mysql_fetch_object($result);

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_title - $row_orga->ag_longname bearbeiten</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"organization_function.php?ag_id=$row_orga->ag_id\" method=\"post\" name=\"Gruppierung bearbeiten\">
      
      <div class=\"formHead\">$row_orga->ag_longname bearbeiten</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 40%; float: left;\">Name (Abk.):</div>
            <div style=\"text-align: left; margin-left: 42%;\">
               <input type=\"text\" name=\"shortname\" class=\"readonly\" readonly size=\"10\" maxlength=\"10\" value=\"$row_orga->ag_shortname\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 40%; float: left;\">Name (lang):</div>
            <div style=\"text-align: left; margin-left: 42%;\">
               <input type=\"text\" name=\"longname\" size=\"30\" maxlength=\"60\" value=\"$row_orga->ag_longname\">
            </div>
         </div>";

         // bei mehr als einer Gruppierung, Checkbox anzeigen, ob, Termin bei anderen angezeigt werden soll
         $sql = "SELECT * FROM adm_gruppierung
                  WHERE ag_shortname NOT LIKE '$g_organization' ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
         
         if(mysql_num_rows($result) > 0)
         {
            // Auswahlfeld fuer die uebergeordnete Gruppierung
            echo "
            <div style=\"margin-top: 6px;\">
               <div style=\"text-align: right; width: 40%; float: left;\">Übergeordnete Gruppierung:</div>
               <div style=\"text-align: left; margin-left: 42%;\">
                  <select size=\"1\" name=\"mutter\">
                     <option value=\" \" ";
                        if(strlen($row_orga->ag_mutter) == 0)
                           echo " selected ";
                     echo ">Ausw&auml;hlen ...</option>";
                     
                     while($row = mysql_fetch_object($result))
                     {
                        echo "<option value=\"$row->ag_shortname\"";
                           if($row_orga->ag_mutter == $row->ag_shortname)
                              echo " selected ";
                        echo ">";
                        
                        if(strlen($row->ag_longname) > 2)
                           echo $row->ag_longname;
                        else
                           echo $row->ag_shortname;
                        echo "</option>";
                     }
                  echo "</select>
               </div>
            </div>";
         }
         
         echo "<hr width=\"85%\" />";
         
         // gruppierungsspezifische Felder anzeigen
         $sql = "SELECT * FROM adm_user_field
                  WHERE auf_ag_shortname LIKE '$g_organization' ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);

         if(mysql_num_rows($result) > 0)
         {
            echo "<p>Gruppierungsspezifische Profilfelder:
            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profil_felder','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </p>
            <table class=\"tableList\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
               <tr>
                  <th class=\"tableHeader\" style=\"text-align: left;\">Feld</th>
                  <th class=\"tableHeader\" style=\"text-align: left;\">Beschreibung</th>
                  <th class=\"tableHeader\" style=\"text-align: left;\">Datentyp</th>
                  <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur für Moderatoren sichtbar\" title=\"Feld nur für Moderatoren sichtbar\"></th>
                  <th class=\"tableHeader\">&nbsp;</th>
               </tr>";
               
            while($row = mysql_fetch_object($result))
            {
               echo "
               <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                  <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/organization/field.php?auf_id=$row->auf_id\">$row->auf_name</a></td>
                  <td style=\"text-align: left;\">$row->auf_description</td>
                  <td style=\"text-align: left;\">";
                  if($row->auf_type == "TEXT")
                     echo "Text (30)";
                  elseif($row->auf_type == "TEXT_BIG")
                     echo "Text (255)";
                  elseif($row->auf_type == "NUMERIC")
                     echo "Zahl";
                  elseif($row->auf_type == "CHECKBOX")
                     echo "Ja / Nein";
                  echo "</td>
                  <td style=\"text-align: center;\">";
                  if($row->auf_locked == 1)
                     echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur für Moderatoren sichtbar\" title=\"Feld nur für Moderatoren sichtbar\">";
                  else
                     echo "&nbsp;";
                  echo "</td>
                  <td style=\"text-align: right;\">
                     <a href=\"$g_root_path/adm_program/administration/organization/field.php?auf_id=$row->auf_id\">
                        <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";
                        $load_url =urlencode("$g_root_path/adm_program/administration/organization/field_function.php?auf_id=$row->auf_id&mode=2");
                     echo "<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_field&err_text=$row->auf_name&err_head=Profilfeld l&ouml;schen&button=2&url=$load_url\">
                        <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Veranstaltung löschen\" title=\"Veranstaltung löschen\"></a>
                  </td>
               </tr>";
            }
            echo "</table>";
         }
         else
         {
            echo "
            Es wurden keine Profilfelder erstellt !
            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profil_felder','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            <br />";
         }

         echo "
         <button name=\"field\" type=\"button\" value=\"field\"
            onClick=\"self.location.href='$g_root_path/adm_program/administration/organization/field.php'\">
            <img src=\"$g_root_path/adm_program/images/wand.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Rollen zuordnen\">
            &nbsp;Feld hinzuf&uuml;gen</button>

         <hr width=\"85%\" />

         <div style=\"margin-top: 6px;\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
            Speichern</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            Zur&uuml;ck</button>
         </div>
      </div>
   </form>
   
   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>