<?php
/******************************************************************************
 * Uebersicht mit allen Benutzer anzeigen zum Zuordnen des Neuen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * new_user_id: ID des Users, der angezeigt werden soll
 * letter:      Anfangsbuchstabe, der Nachnamen, die angezeigt werden sollen
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

// nur Webmaster duerfen User zuordnen, ansonsten Seite verlassen
if(!hasRole("Webmaster"))
{
   $g_message->show("norights");
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

if(isset($_GET['new_user_id']) && is_numeric($_GET['new_user_id']) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET['letter']) && strlen($_GET["letter"]) > 2)
{
    $g_message->show("invalid");
}

$restrict = "";
$listname = "";
$i = 0;

if($_GET['new_user_id'] > 0)
{
   $new_user = new User($g_adm_con);
   $new_user->getUser($_GET['new_user_id']);

   if(!array_key_exists("letter", $_GET))
      $_GET["letter"] = substr($new_user->last_name, 0, 1);
}

if(!array_key_exists("letter", $_GET))
   $_GET["letter"] = "A%";
else
   $_GET["letter"] = $_GET["letter"]. "%";

if($_GET["all"] == 0)
{
   // Mitglieder mit gleichem Nachname der Gruppierung zur Auswahl selektieren
   $sql    = "SELECT *
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE rol_org_shortname = '$g_organization'
                 AND rol_valid         = 1
                 AND mem_rol_id        = rol_id
                 AND mem_valid         = 1
                 AND mem_usr_id        = usr_id
                 AND usr_last_name LIKE '$new_user->last_name'
                 AND usr_valid         = 1
               GROUP BY usr_last_name, usr_first_name
               ORDER BY usr_last_name, usr_first_name ";
   $result_all = mysql_query($sql, $g_adm_con);
   $member_found = mysql_num_rows($result_all);

   // wenn keine mit gleichem Nachnamen gefunden, dann alle selektieren
   if($member_found == 0)
   {
      // alle Mitglieder der Gruppierung zur Auswahl selektieren
      $sql    = "SELECT *
                   FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                  WHERE rol_org_shortname = '$g_organization'
                    AND rol_valid         = 1
                    AND mem_rol_id        = rol_id
                    AND mem_valid         = 1
                    AND mem_usr_id        = usr_id
                    AND usr_last_name LIKE {0}
                    AND usr_valid         = 1
                  GROUP BY usr_last_name, usr_first_name
                  ORDER BY usr_last_name, usr_first_name ";
      $sql    = prepareSQL($sql, array($_GET['letter']));
      $result_all = mysql_query($sql, $g_adm_con);
      $all    = 1;
   }
}
else
{
   // alle Mitglieder der Gruppierung zur Auswahl selektieren
   $sql    = "SELECT *
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE rol_org_shortname = '$g_organization'
                 AND rol_valid         = 1
                 AND mem_rol_id        = rol_id
                 AND mem_valid         = 1
                 AND mem_usr_id        = usr_id
                 AND usr_last_name LIKE {0}
                 AND usr_valid         = 1
               GROUP BY usr_last_name, usr_first_name
               ORDER BY usr_last_name, usr_first_name ";
   $sql    = prepareSQL($sql, array($_GET['letter']));
   $result_all = mysql_query($sql, $g_adm_con);
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Neue User zuordnen</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if lt IE 7]>
   <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
echo "<div align=\"center\">";

if($_GET['new_user_id'] > 0)
   echo "<h1>Anmeldung von $new_user->first_name $new_user->last_name zuordnen</h1>";
echo "
<h1>- ". str_replace("%", "", $_GET["letter"]). " -</h1>";

if($_GET["all"] == 1 || $member_found == 0)
{
   echo "<p>W&auml;hle den Anfangsbuchstaben des Nachnamens aus:</p>
   <p>";

   $letter_menu = "A";
   for($i = 0; $i < 26;$i++)
   {
      // Anzahl Mitglieder zum entsprechenden Buchstaben ermitteln
      $sql    = "SELECT COUNT(*)
                   FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                  WHERE rol_org_shortname = '$g_organization'
                    AND rol_valid         = 1
                    AND mem_rol_id        = rol_id
                    AND mem_valid         = 1
                    AND mem_usr_id        = usr_id
                    AND usr_last_name LIKE '$letter_menu%'
                    AND usr_valid         = 1
                  GROUP BY usr_last_name, usr_first_name ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
      $row = mysql_fetch_array($result);

      if($row[0] > 0)
         echo "<a href=\"new_user_assign.php?new_user_id=$new_user->id&amp;all=1&amp;letter=$letter_menu\">$letter_menu</a>";
      else
         echo $letter_menu;

      echo "&nbsp;&nbsp;&nbsp;";
      // naechsten Buchstaben anwaehlen
      $letter_menu = strNextLetter($letter_menu);
   }

   echo "</p>";
}

echo "
<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
   <tr>
      <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Name</th>
      <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Benutzername</th>
      <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;E-Mail</th>
      <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Funktion</th>
   </tr>";

   while($row = mysql_fetch_object($result_all))
   {
      echo "<tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
               <td style=\"text-align: left;\">&nbsp;<a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->usr_id\">$row->usr_last_name,&nbsp;$row->usr_first_name</a></td>
               <td style=\"text-align: left;\">&nbsp;$row->usr_login_name</td>
               <td style=\"text-align: left;\">&nbsp;";
               if($g_preferences['send_email_extern'] == 1)
                  echo "<a href=\"mailto:$row->usr_email\">";
               else
                  echo "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?usr_id=$row->usr_id\">";
               echo "$row->usr_email</a></td>
               <td style=\"text-align: left;\">&nbsp;";
      if(strlen($row->usr_login_name) > 0)
         echo "Angemeldet";
      else
      {
         $load_url = urlencode("$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=1");
         echo "<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=zuordnen&amp;err_text=$row->usr_first_name $row->usr_last_name&amp;err_head=Webanmeldung zuordnen&amp;button=2&amp;url=$load_url\">Zuordnen</a>";
      }

      echo "</td></tr>";
   }
echo "
</table>

<p>Falls der Benutzer noch nicht existiert:</p>
<p><button name=\"neu\" type=\"button\" value=\"neu\"
   onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_new.php?user_id=". $_GET['new_user_id']. "&amp;new_user=3&amp;url=". urlencode("$g_root_path/adm_program/administration/new_user/new_user.php"). "'\">
<img src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neuer Benutzer\">
&nbsp;Neuer Benutzer</button></p>

</div>";

require("../../../adm_config/body_bottom.php");
echo "</body></html>";
?>