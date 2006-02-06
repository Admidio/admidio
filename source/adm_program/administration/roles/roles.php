<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * mode: actual  - (Default) Alle aktuellen und zukuenftige Termine anzeigen
 *       old     - Alle bereits erledigten
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
 require("../../system/session_check_login.php");

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!isModerator())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

// Alle Rollen auflisten, die der Webmaster sehen darf
$sql    = "SELECT * FROM ". TBL_ROLES. ", ". TBL_ROLE_CATEGORIES. "
            WHERE rol_org_shortname = '$g_organization'
              AND rol_valid         = 1
              AND rol_rlc_id        = rlc_id
            ORDER BY rlc_name, rol_name ";
$result = mysql_query($sql, $g_adm_con);
db_error($result, 1);

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Rollenverwaltung</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   <h1>Rollenverwaltung</h1>

   <p><button name=\"neu\" type=\"button\" value=\"neu\" onclick=\"self.location.href='roles_new.php'\">
   <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Rolle anlegen\">
   &nbsp;Rolle anlegen</button></p>

   <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
      <tr>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Rolle</th>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Kategorie</th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\" title=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/person.png\" alt=\"Daten aller Benutzer bearbeiten\" title=\"Daten aller Benutzer bearbeiten\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/history.png\" alt=\"Termine erfassen und bearbeiten\" title=\"Termine erfassen und bearbeiten\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/download.png\" alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail-open.png\" alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail-open-key.png\" alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\"></th>
         <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur für Moderatoren sichtbar\" title=\"Rolle nur für Moderatoren sichtbar\"></th>
         <th class=\"tableHeader\">Bearbeiten</th>
      </tr>";

      while($row = mysql_fetch_object($result))
      {
         echo "
         <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
				<td style=\"text-align: left;\">&nbsp;<a href=\"$g_root_path/adm_program/modules/lists/lists_show.php?typ=address&amp;mode=html&amp;rol_id=$row->rol_id\">$row->rol_name</a></td>
				<td style=\"text-align: left;\">&nbsp;$row->rlc_name</td>
				<td style=\"text-align: center;\">";
					if($row->rol_moderation == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\" title=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_edit_user == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/person.png\" alt=\"Daten aller Benutzer bearbeiten\" title=\"Daten aller Benutzer bearbeiten\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_dates == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/history.png\" alt=\"Termine erfassen und bearbeiten\" title=\"Termine erfassen und bearbeiten\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_photo == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_download == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/download.png\" alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_mail_logout == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail-open.png\" alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_mail_login == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail-open-key.png\" alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\">";
				echo "</td>
				<td style=\"text-align: center;\">";
					if($row->rol_locked == 1)
						echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur für Moderatoren sichtbar\" title=\"Rolle nur für Moderatoren sichtbar\">";
				echo "</td>
				<td style=\"text-align: center;\">
					<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=$row->rol_id\">
						<img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Rolle bearbeiten\" title=\"Rolle bearbeiten\"></a>";

					if($row->rol_name == "Webmaster")
						echo "&nbsp;";
					else
					{
						$load_url = urlencode("$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=3");
						echo "&nbsp;
						<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=remove_rolle&amp;err_text=$row->rol_name&amp;err_head=Löschen&amp;button=2&amp;url=$load_url\">
							<img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Rolle l&ouml;schen\" title=\"Rolle l&ouml;schen\"></a>";
					}
				echo "</td>
			</tr>";
      }

   echo "</table>
   </div>";
   require("../../../adm_config/body_bottom.php");
echo "</body></html>";
?>