<?php
/******************************************************************************
 * Gruppierung bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * url:     URL auf die danach weitergeleitet wird
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

require("../../system/common.php");
require("../../system/login_valid.php");

// nur Webmaster duerfen Gruppierungen bearbeiten
if(!hasRole("Webmaster"))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
   $url = $_GET['url'];
else
   $url = urlencode(getHttpReferer());

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - bearbeiten</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"organization_function.php?org_id=$g_current_organization->id&amp;url=$url\" method=\"post\" name=\"Gruppierung bearbeiten\">

      <div class=\"formHead\">$g_current_organization->longname bearbeiten</div>
      <div class=\"formBody\">
           <div>
            <div style=\"text-align: right; width: 48%; float: left;\">Name (Abk.):</div>
            <div style=\"text-align: left; margin-left: 50%;\">
               <input type=\"text\" name=\"shortname\" class=\"readonly\" readonly size=\"10\" maxlength=\"10\" value=\"$g_current_organization->longname\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 48%; float: left;\">Name (lang):</div>
            <div style=\"text-align: left; margin-left: 50%;\">
               <input type=\"text\" name=\"longname\" size=\"30\" maxlength=\"60\" value=\"$g_current_organization->longname\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 48%; float: left;\">Homepage:</div>
            <div style=\"text-align: left; margin-left: 50%;\">
               <input type=\"text\" name=\"homepage\" size=\"30\" maxlength=\"30\" value=\"$g_current_organization->homepage\">
            </div>
         </div>";

         // Pruefung ob dieser Orga bereits andere Orgas untergeordnet sind
         $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE org_org_id_parent = $g_current_organization->id";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);

         //Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
         if(mysql_num_rows($result)==0)
         {
             $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                      WHERE org_id <> $g_current_organization->id
                      AND org_org_id_parent is NULL
                      ORDER BY org_longname ASC, org_shortname ASC ";
             $result = mysql_query($sql, $g_adm_con);
             db_error($result);

             if(mysql_num_rows($result) > 0)
             {
                // Auswahlfeld fuer die uebergeordnete Gruppierung
                echo "
                <div style=\"margin-top: 6px;\">
                   <div style=\"text-align: right; width: 48%; float: left;\">&Uuml;bergeordnete Gruppierung:</div>
                   <div style=\"text-align: left; margin-left: 50%;\">
                      <select size=\"1\" name=\"parent\">
                         <option value=\"0\" ";
                            if(strlen($g_current_organization->org_id_parent) == 0)
                               echo " selected ";
                         echo ">keine</option>";

                         while($row = mysql_fetch_object($result))
                         {
                            echo "<option value=\"$row->org_id\"";
                               if($g_current_organization->org_id_parent == $row->org_id)
                                  echo " selected ";
                            echo ">";

                            if(strlen($row->org_longname) > 2)
                               echo $row->org_longname;
                            else
                               echo $row->org_shortname;
                            echo "</option>";
                         }
                      echo "</select>
                   </div>
                </div>";
             }

         }

         echo "
         <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
             <div class=\"groupBoxHeadline\">Einstellungen</div>
             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: right; width: 47%; float: left;\">Externes Mailprogramm:</div>
                 <div style=\"text-align: left; margin-left: 50%;\">
                     <input type=\"checkbox\" id=\"mail_extern\" name=\"mail_extern\" ";
                     if($g_current_organization->mail_extern == 1)
                         echo " checked ";
                     echo " value=\"1\" />
                     <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=mail_extern','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                 </div>
             </div>

             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: right; width: 47%; float: left;\">BBCode zulassen:</div>
                 <div style=\"text-align: left; margin-left: 50%;\">
                     <input type=\"checkbox\" id=\"bbcode\" name=\"bbcode\" ";
                     if($g_current_organization->bbcode == 1)
                         echo " checked ";
                     echo " value=\"1\" />
                     <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=350,left=310,top=200,scrollbars=yes')\">
                 </div>
             </div>

             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: right; width: 47%; float: left;\">RSS-Feeds aktivieren:</div>
                 <div style=\"text-align: left; margin-left: 50%;\">
                     <input type=\"checkbox\" id=\"enable_rss\" name=\"enable_rss\" ";
                     if($g_current_organization->enable_rss == 1)
                         echo " checked ";
                     echo " value=\"1\" />
                     <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=enable_rss','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                 </div>
             </div>
         </div>

         <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
             <div class=\"groupBoxHeadline\">Maximale Dateigr&ouml;&szlig;e f&uuml;r&nbsp;&nbsp;
                 <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                 onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=file_size','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
             </div>
             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: right; width: 47%; float: left;\">E-Mail-Attachments:</div>
                 <div style=\"text-align: left; margin-left: 50%;\">
                     <input type=\"text\" name=\"attachment_size\" size=\"4\" maxlength=\"4\" value=\"$g_current_organization->mail_size\"> KB
                 </div>
             </div>

             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: right; width: 47%; float: left;\">Downloads:</div>
                 <div style=\"text-align: left; margin-left: 50%;\">
                     <input type=\"text\" name=\"upload_size\" size=\"4\" maxlength=\"4\" value=\"$g_current_organization->upload_size\"> KB
                 </div>
             </div>
             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: right; width: 47%; float: left;\">Fotos:</div>
                 <div style=\"text-align: left; margin-left: 50%;\">
                     <input type=\"text\" name=\"photo_size\" size=\"4\" maxlength=\"4\" value=\"$g_current_organization->photo_size\"> KB
                 </div>
             </div>
         </div>";

         /*------------------------------------------------------------*/
         // Rollen-Kategorien
         /*------------------------------------------------------------*/

         $sql = "SELECT * FROM ". TBL_ROLE_CATEGORIES. "
                  WHERE rlc_org_shortname LIKE '$g_organization'
                  ORDER BY rlc_name ASC ";
         $cat_result = mysql_query($sql, $g_adm_con);
         db_error($cat_result);

            echo "<br>
            <table class=\"tableList\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
                <tr>
                    <th class=\"tableHeader\" style=\"text-align: left;\">Rollen-Kategorien</th>
                    <th class=\"tableHeader\">&nbsp;</th>
                </tr>";

            while($cat_row = mysql_fetch_object($cat_result))
            {
                // schauen, ob Rollen zu dieser Kategorie existieren
                $sql = "SELECT * FROM ". TBL_ROLES. "
                            WHERE rol_rlc_id = $cat_row->rlc_id ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                $row_num = mysql_num_rows($result);

                echo "
                <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                    <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/roles/categories.php?rlc_id=$cat_row->rlc_id\">$cat_row->rlc_name</a></td>
                    <td style=\"text-align: right; width: 45px;\">
                        <a href=\"$g_root_path/adm_program/administration/roles/categories.php?rlc_id=$cat_row->rlc_id&amp;url=$url\">
                            <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>";
                        // nur Kategorien loeschen, die keine Rollen zugeordnet sind
                        if($row_num == 0)
                        {
                            $load_url = urlencode("$g_root_path/adm_program/administration/roles/categories_function.php?rlc_id=$cat_row->rlc_id&mode=2&url=$url");
                            echo "&nbsp;<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_category&err_text=$cat_row->rlc_name&err_head=Kategorie l&ouml;schen&button=2&url=$load_url\"><img
                            src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>";
                        }
                        else
                            echo "&nbsp;<img src=\"$g_root_path/adm_program/images/dummy.gif\" width=\"16\" border=\"0\" alt=\"Dummy\">";
                    echo "</td>
                </tr>";
            }
            echo "</table>

         <button id=\"new_category\" type=\"button\" value=\"new_category\" style=\"margin-top: 3px; width: 180px;\"
            onClick=\"self.location.href='$g_root_path/adm_program/administration/roles/categories.php?url=$url'\">
            <img src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Kategorie hinzuf&uuml;gen\">
            &nbsp;Kategorie hinzuf&uuml;gen</button>
         <br><br>";

         /*------------------------------------------------------------*/
         // gruppierungsspezifische Felder anzeigen
         /*------------------------------------------------------------*/

         $sql = "SELECT * FROM ". TBL_USER_FIELDS. "
                  WHERE usf_org_shortname LIKE '$g_organization'
                  ORDER BY usf_name ASC ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);

         if(mysql_num_rows($result) > 0)
         {
            echo "<div style=\"margin-top: 3px; margin-bottom: 7px;\">Gruppierungsspezifische Profilfelder:
            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profil_felder','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
            <table class=\"tableList\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
               <tr>
                  <th class=\"tableHeader\" style=\"text-align: left;\">Feld</th>
                  <th class=\"tableHeader\" style=\"text-align: left;\">Beschreibung</th>
                  <th class=\"tableHeader\" style=\"text-align: left;\">Datentyp</th>
                  <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur f&uuml;r Moderatoren sichtbar\" title=\"Feld nur f&uuml;r Moderatoren sichtbar\"></th>
                  <th class=\"tableHeader\">&nbsp;</th>
               </tr>";

            while($row = mysql_fetch_object($result))
            {
               echo "
               <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                  <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/organization/field.php?usf_id=$row->usf_id\">$row->usf_name</a></td>
                  <td style=\"text-align: left;\">$row->usf_description</td>
                  <td style=\"text-align: left;\">";
                  if($row->usf_type == "TEXT")
                     echo "Text (30)";
                  elseif($row->usf_type == "TEXT_BIG")
                     echo "Text (255)";
                  elseif($row->usf_type == "NUMERIC")
                     echo "Zahl";
                  elseif($row->usf_type == "CHECKBOX")
                     echo "Ja / Nein";
                  echo "</td>
                  <td style=\"text-align: center;\">";
                  if($row->usf_locked == 1)
                     echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur f&uuml;r Moderatoren sichtbar\" title=\"Feld nur f&uuml;r Moderatoren sichtbar\">";
                  else
                     echo "&nbsp;";
                  echo "</td>
                  <td style=\"text-align: right; width: 45px;\">
                     <a href=\"$g_root_path/adm_program/administration/organization/field.php?usf_id=$row->usf_id&amp;url=$url\">
                        <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";
                        $load_url = urlencode("$g_root_path/adm_program/administration/organization/field_function.php?usf_id=$row->usf_id&mode=2&url=$url");
                     echo "<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_field&err_text=$row->usf_name&err_head=Profilfeld l&ouml;schen&button=2&url=$load_url\"><img
                     src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
                  </td>
               </tr>";
            }
            echo "</table>";
         }
         else
         {
            echo "
            Es wurden keine Profilfelder erstellt !
            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profil_felder','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            <br />";
         }

         echo "
         <button id=\"new_field\" type=\"button\" value=\"new_field\" style=\"margin-top: 3px; width: 180px;\"
            onClick=\"self.location.href='$g_root_path/adm_program/administration/organization/field.php?url=$url'\">
            <img src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Feld hinzuf&uuml;gen\">
            &nbsp;Feld hinzuf&uuml;gen</button>

         <hr width=\"85%\" />

         <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='". urldecode($url). "'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
            &nbsp;Speichern</button>
         </div>
      </div>
   </form>

   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>