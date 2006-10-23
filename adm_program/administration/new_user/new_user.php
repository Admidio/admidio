<?php
/******************************************************************************
 * Neue User auflisten
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
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Webmaster dürfen User bestätigen, ansonsten Seite verlassen
if(!hasRole("Webmaster"))
{
   $g_message->show("norights");
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

// Neue Mitglieder der Gruppierung selektieren
$sql    = "SELECT * FROM ". TBL_USERS. " 
            WHERE usr_valid = 0
              AND usr_reg_org_shortname = '$g_organization' 
            ORDER BY usr_last_name, usr_first_name ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$member_found = mysql_num_rows($result);

if ($member_found == 0)
{
   $g_message->setForwardUrl("home");
   $g_message->show("nomembers", "", "Anmeldungen");
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Neue Anmeldungen</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if lt IE 7]>
   <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "<div align=\"center\">
   <h1>Neue Anmeldungen</h1>

   <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
      <tr>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Nachname</th>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Vorname</th>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Benutzername</th>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;E-Mail</th>
         <th class=\"tableHeader\" style=\"text-align: center;\">&nbsp;Funktionen</th>
      </tr>";

      while($row = mysql_fetch_object($result))
      {
         echo "<tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                  <td style=\"text-align: left;\">&nbsp;$row->usr_last_name</td>
                  <td style=\"text-align: left;\">&nbsp;$row->usr_first_name</td>
                  <td style=\"text-align: left;\">&nbsp;$row->usr_login_name</td>
                  <td style=\"text-align: left;\">&nbsp;<a href=\"mailto:$row->usr_email\">$row->usr_email</a></td>
                  <td style=\"text-align: center;\">
                     <a href=\"new_user_function.php?mode=3&amp;new_user_id=$row->usr_id\">
                        <img src=\"$g_root_path/adm_program/images/properties.png\" border=\"0\" alt=\"Anmeldung zuordnen\" title=\"Anmeldung zuordnen\"></a>&nbsp;&nbsp;
                     <a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_new_user&amp;err_text=$row->usr_first_name $row->usr_last_name&amp;err_head=L&ouml;schen&amp;button=2&amp;url=". urlencode("$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$row->usr_id&amp;mode=4"). "\">
                        <img src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Anmeldung l&ouml;schen\" title=\"Anmeldung l&ouml;schen\"></a>
                  </td>
               </tr>";
      }

   echo "</table>
   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>