<?php
/******************************************************************************
 * Verwaltung der aller Mitglieder in der Datenbank
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * letter: alle User deren Nachnamen mit dem Buchstaben beginnt, werden angezeigt
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

// nur Moderatoren duerfen alle User sehen & Mitgliedschaften l&ouml;schen
if(!isModerator())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$restrict = "";
$listname = "";
$i = 0;

if(!array_key_exists("letter", $_GET))
{
   // alle Mitglieder zur Auswahl selektieren
   $sql    = "SELECT au_id FROM adm_user ";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   
   if(mysql_num_rows($result) > 50)
      $_GET["letter"] = "A%";
   else
      $_GET["letter"] = "%";
}
else
{
   if($_GET["letter"] != "%")
      $_GET["letter"] = $_GET["letter"]. "%";
}

// Name der Gliedgemeinschaft auslesen
$sql    = "SELECT * FROM adm_gruppierung
            WHERE ag_shortname = '$g_organization' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$row = mysql_fetch_object($result);
$orga_long_name = $row->ag_longname;

// alle Mitglieder zur Auswahl selektieren
$sql    = "SELECT * FROM adm_user
            WHERE au_name LIKE {0}
            ORDER BY au_name, au_vorname ";
$sql    = prepareSQL($sql, array($_GET['letter']));
$result_mgl = mysql_query($sql, $g_adm_con);
db_error($result_mgl);

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_title - Benutzerverwaltung</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "<div align=\"center\">

   <h1>Benutzerverwaltung</h1>

   <h1>- ";
   
   if($_GET["letter"] == "%")
      echo "Alle";
   else
      echo str_replace("%", "", $_GET["letter"]);

   echo " -</h1>
   <p>W&auml;hle den Anfangsbuchstaben des Nachnamens aus:</p>
   <p>
      <a href=\"members.php?letter=%\">Alle</a>&nbsp;&nbsp;&nbsp;";
   $letter_menu = "A";
   for($i = 0; $i < 26;$i++)
   {
      // Anzahl Mitglieder zum entsprechenden Buchstaben ermitteln
      $sql    = "SELECT COUNT(*) FROM adm_user
                  WHERE au_name LIKE '$letter_menu%' ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
      $row = mysql_fetch_array($result);
      
      if($row[0] > 0)
         echo "<a href=\"members.php?letter=$letter_menu\">$letter_menu</a>";
      else
         echo $letter_menu;
         
      echo "&nbsp;&nbsp;";
      // naechsten Buchstaben anwaehlen
      $letter_menu = strNextLetter($letter_menu);
   }
   echo "</p>";

   if(mysql_num_rows($result_mgl) > 0)
   {
      echo "
      <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
         <tr>
            <th class=\"tableHeader\" align=\"right\">Nr.</th>
            <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/person.png\" alt=\"Mitglied bei $orga_long_name\" title=\"Mitglied bei $orga_long_name\" border=\"0\"></th>
            <th class=\"tableHeader\" align=\"left\">&nbsp;Name</th>
            <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail.png\" alt=\"E-Mail\" title=\"E-Mail\"></th>
            <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Homepage\" title=\"Homepage\"></th>
            <th class=\"tableHeader\" align=\"left\">&nbsp;Benutzer</th>
            <th class=\"tableHeader\" align=\"center\">&nbsp;Aktualisiert am</th>
            <th class=\"tableHeader\" align=\"center\">Bearbeiten</th>
         </tr>";
      $i = 0;

      while($row = mysql_fetch_object($result_mgl))
      {
         $i++;

         $sql    = "SELECT COUNT(*)
                      FROM adm_rolle, adm_mitglieder
                     WHERE ar_ag_shortname = '$g_organization'
                       AND ar_valid        = 1
                       AND am_ar_id        = ar_id
                       AND am_valid        = 1
                       AND am_au_id        = $row->au_id ";
         $result    = mysql_query($sql, $g_adm_con);
         db_error($result);
         $row_count = mysql_fetch_array($result);

         echo "<tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                  <td align=\"right\">$i&nbsp;</td>
                  <td align=\"center\">";
                     if($row_count[0] > 0)
                        echo "<a href=\"$g_root_path/adm_program/moduls/profile/profile.php?user_id=$row->au_id\"><img src=\"$g_root_path/adm_program/images/person.png\" alt=\"Mitglied bei $orga_long_name\" title=\"Mitglied bei $orga_long_name\" border=\"0\"></a>";
                     else
                        echo "&nbsp;";
                  echo "</td>
                  <td align=\"left\">&nbsp;<a href=\"$g_root_path/adm_program/moduls/profile/profile.php?user_id=$row->au_id\">$row->au_name,&nbsp;$row->au_vorname</a></td>
                  <td align=\"center\">";
                     if(strlen($row->au_mail) > 0)
                     {
                        echo "<a href=\"$g_root_path/adm_program/moduls/mail/mail.php?au_id=$row->au_id\">
                           <img src=\"$g_root_path/adm_program/images/mail.png\" alt=\"E-Mail an $row->au_mail schreiben\" title=\"E-Mail an $row->au_mail schreiben\" border=\"0\"></a>";
                     }
                  echo "</td>
                  <td align=\"center\">";
                     if(strlen($row->au_weburl) > 0)
                     {
                        $row->au_weburl = stripslashes($row->au_weburl);
                        if(substr_count(strtolower($row->au_weburl), "http://") == 0)
                           $row->au_weburl = "http://". $row->au_weburl;
                        echo "<a href=\"$row->au_weburl\" target=\"_blank\">
                           <img src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Homepage\" title=\"Homepage\" border=\"0\"></a>";
                     }
                  echo "</td>
                  <td align=\"left\">&nbsp;$row->au_login</td>
                  <td align=\"center\">&nbsp;". mysqldatetime("d.m.y h:i" , $row->au_last_change). "</td>
                  <td align=\"center\">";
                  if(hasRole("Webmaster"))
                  {
                     if($row_count[0] > 0)
                     {
                        // Webmaster kann nur Mitglieder der eigenen Gliedgemeinschaft editieren
                        echo "<a href=\"$g_root_path/adm_program/moduls/profile/profile_edit.php?user_id=$row->au_id\">
                           <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Benutzerdaten bearbeiten\" title=\"Benutzerdaten bearbeiten\"></a>&nbsp;&nbsp;";
                     }
                     else
                        echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">&nbsp;&nbsp;";
                        
                     $sql    = "SELECT COUNT(*)
                                  FROM adm_rolle, adm_mitglieder
                                 WHERE ar_ag_shortname <> '$g_organization'
                                   AND ar_valid         = 1
                                   AND am_ar_id         = ar_id
                                   AND am_valid         = 1
                                   AND am_au_id         = $row->au_id ";
                     $result      = mysql_query($sql, $g_adm_con);
                     db_error($result);
                     $row_count_2 = mysql_fetch_array($result);

                     if($row_count_2[0] > 0)
                     {
                        // Webmaster duerfen Mitglieder nicht loeschen, wenn sie noch in anderen Gliedgemeinschaften aktiv sind
                        if($row_count[0] > 0)
                           echo "<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_member&err_text=$row->au_vorname $row->au_name&err_head=Entfernen&button=2&url=". urlencode("$g_root_path/adm_program/administration/members/members_function.php?user_id=$row->au_id&mode=2"). "\">
                              <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Benutzer entfernen\" title=\"Benutzer entfernen\"></a>";
                     }
                     else
                     {
                        // Webmaster kann Mitglied aus der Datenbank loeschen
                        echo "<a href=\"members_function.php?user_id=$row->au_id&amp;mode=1\">
                           <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Benutzer l&ouml;schen\" title=\"Benutzer l&ouml;schen\"></a>";
                     }
                  }
                  else
                  {
                     // Moderatoren duerfen nur Mitglieder der eigenen Gliedgemeinschaft entfernen,
                     // aber keine Webmaster !!!
                     if($row_count[0] > 0 && !hasRole("Webmaster", $row->au_id))
                     {
                        echo "
                        <a href=\"$g_root_path/adm_program/moduls/profile/profile_edit.php?user_id=$row->au_id\">
                           <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Benutzerdaten bearbeiten\" title=\"Benutzerdaten bearbeiten\"></a>&nbsp;&nbsp;
                        <a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_member&err_text=$row->au_vorname $row->au_name&err_head=Entfernen&button=2&url=". urlencode("$g_root_path/adm_program/administration/members/members_function.php?user_id=$row->au_id&mode=2"). "\">
                           <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Benutzer entfernen\" title=\"Benutzer entfernen\"></a>";
                     }
                  }
                  echo "</td>
               </tr>";
      }

      echo "</table>";
   }
   else
   {
      echo "<p>Es wurde keine Daten gefunden !</p><br />";
   }
   echo "<p>Falls der gesuchte Benutzer noch nicht existiert:</p>
   <p><button name=\"neu\" type=\"button\" value=\"neu\" onclick=\"self.location.href='$g_root_path/adm_program/moduls/profile/profile_edit.php?new_user=1'\">
   <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
   &nbsp;Benutzer anlegen</button></p>

   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>