<?php
/******************************************************************************
 * Ankuendigungen auflisten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * mode: all - (Default) Alle (alte und neue) Ankuendigungen anzeigen
 *       new - Alle aktuellen und zukuenftige Ankuendigungen anzeigen
 *       old - Alle bereits erledigten
 * start     - Angabe, ab welchem Datensatz Ankuendigungen angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) Ankuendigungen
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
require("../../system/session_check.php");

if(!array_key_exists("mode", $_GET))
   $_GET["mode"] = "all";

if(!array_key_exists("start", $_GET))
   $_GET["start"] = 0;

if(!array_key_exists("headline", $_GET))
   $_GET["headline"] = "Ankündigungen";

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_title - ". $_GET["headline"]. "</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   <h1>". strspace($_GET["headline"]). "</h1>";

   $act_date = date("Y.m.d 00:00:00", time());

   // alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
   $sql = "SELECT * FROM adm_gruppierung
            WHERE ag_shortname = '$g_organization'
               OR ag_mutter    = '$g_organization' ";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $organizations = "";
   $i             = 0;

   while($row = mysql_fetch_object($result))
   {
      if($i > 0) $organizations = $organizations. ", ";

      if($row->ag_shortname == $g_organization)
         $organizations = $organizations. "'$row->ag_mutter'";
      else
         $organizations = $organizations. "'$row->ag_shortname'";

      $i++;
   }

   if(strcmp($_GET['mode'], "old") == 0)
   {
      $sql    = "SELECT COUNT(*) FROM adm_ankuendigungen
                  WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                    AND aa_datum <= '$act_date'
                  ORDER BY aa_datum DESC ";
   }
   elseif(strcmp($_GET['mode'], "new") == 0)
   {
      $sql    = "SELECT COUNT(*) FROM adm_ankuendigungen
                  WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                    AND aa_datum >= '$act_date'
                  ORDER BY aa_datum ASC ";
   }
   else
   {
      $sql    = "SELECT COUNT(*) FROM adm_ankuendigungen
                  WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                  ORDER BY aa_datum ASC ";
   }
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $row = mysql_fetch_array($result);
   $count_date = $row[0];

   if($count_date == 0)
   {
      echo "<p>Es sind keine Daten vorhanden.</p>";
   }
   else
   {
      if(strcmp($_GET['mode'], "old") == 0)
      {
         $sql    = "SELECT * FROM adm_ankuendigungen
                     WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                       AND aa_datum <= '$act_date'
                     ORDER BY aa_datum DESC
                     LIMIT ". $_GET["start"]. ", 10 ";
      }
      elseif(strcmp($_GET['mode'], "new") == 0)
      {
         $sql    = "SELECT * FROM adm_ankuendigungen
                     WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                       AND aa_datum >= '$act_date'
                     ORDER BY aa_datum ASC
                     LIMIT ". $_GET["start"]. ", 10 ";
      }
      else
      {
         $sql    = "SELECT * FROM adm_ankuendigungen
                     WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                     ORDER BY aa_datum DESC
                     LIMIT ". $_GET["start"]. ", 10 ";
      }
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      // Tabelle mit den vor- und zurück und neu Buttons

      echo "
      <table style=\"margin-top: 10px; margin-bottom: 10px;\" border=\"0\">
         <tr>
            <td width=\"33%\" align=\"left\">";
               if($_GET["start"] > 0)
               {
                  $start = $_GET["start"] - 10;
                  if($start < 0) $start = 0;
                  echo "<button name=\"back\" type=\"button\" value=\"back\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements.php?mode=". $_GET["mode"]. "&amp;start=$start&amp;headline=". $_GET["headline"]. "'\">
                           <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Vorherige\">
                           Vorherige</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"center\">";
               // nur Webmaster darf neue Ankuendigungen erfassen
               if(isModerator())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements_new.php?headline=". $_GET["headline"]. "'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neu anlegen\">
                           &nbsp;Neu anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($count_date > $_GET["start"] + 10)
               {
                  $start = $_GET["start"] + 10;
                  echo "<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements.php?mode=". $_GET["mode"]. "&amp;start=$start&amp;headline=". $_GET["headline"]. "'\">N&auml;chsten
                           <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chsten\"></button>";
               }
            echo "</td>
         </tr>
      </table>";

      // Ankuendigungen auflisten

      while($row = mysql_fetch_object($result))
      {
         $sql     = "SELECT * FROM adm_user WHERE au_id = $row->aa_au_id";
         $result2 = mysql_query($sql, $g_adm_con);
         db_error($result2);

         $user = mysql_fetch_object($result2);

         echo "
         <div class=\"boxBody\" style=\"overflow: hidden;\">
            <div class=\"boxHead\">
               <div style=\"text-align: left; float: left;\">
                  <img src=\"$g_root_path/adm_program/images/note.png\" style=\"vertical-align: top;\" alt=\"". specialChars2Html($row->aa_ueberschrift). "\">
                  ". mysqldatetime("d.m.y", $row->aa_datum);
                  if(mysqldatetime("h:i", $row->aa_datum) != "00:00")
                     echo "&nbsp;&nbsp;". mysqldatetime("h:i", $row->aa_datum). "&nbsp;";
                  echo "&nbsp;". specialChars2Html($row->aa_ueberschrift). "
               </div>";

               // aendern & loeschen darf man nur eigene Termine, ausser Admins & Moderatoren
               if(isModerator())
               {
                  echo "<div style=\"text-align: right;\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                     onclick=\"self.location.href='announcements_new.php?aa_id=$row->aa_id&amp;headline=". $_GET['headline']. "'\">";

                  // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                  if($row->aa_ag_shortname == $g_organization)
                  {
                     echo "&nbsp;
                     <img src=\"$g_root_path/adm_program/images/delete.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\" ";
                     $load_url = urlencode("$g_root_path/adm_program/modules/announcements/announcements_function.php?aa_id=$row->aa_id&amp;mode=2&amp;url=$g_root_path/adm_program/modules/announcements/announcements.php");
                     echo " onclick=\"self.location.href='$g_root_path/adm_program/system/err_msg.php?err_code=delete_announcement&amp;err_text=". urlencode($row->aa_ueberschrift). "&amp;err_head=L&ouml;schen&amp;button=2&amp;url=$load_url'\">";
                  }
                  echo "&nbsp;</div>";
               }
            echo "</div>

            <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">". nl2br(specialChars2Html($row->aa_beschreibung)). "</div>
            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
                  Angelegt von ". specialChars2Html($user->au_vorname). " ". specialChars2Html($user->au_name).
                  " am ". mysqldatetime("d.m.y h:i", $row->aa_timestamp). "
            </div>
         </div>

         <br />";
      }  // Ende While-Schleife
   }

   // wenn nicht eingeloggt oder Termin editiert werden darf, dann anzeigen
   if(!$g_session_valid || editDate())
   {
      // Tabelle mit den vor- und zurück und neu Buttons

      echo "
      <table style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"500\" border=\"0\">
         <tr>
            <td width=\"33%\" align=\"left\">";
               if($_GET["start"] > 0)
               {
                  $start = $_GET["start"] - 10;
                  if($start < 0) $start = 0;
                  echo "<button name=\"back\" type=\"button\" value=\"back\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements.php?mode=". $_GET["mode"]. "&amp;start=$start&amp;headline=". $_GET["headline"]. "'\">
                           <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Vorherige\">
                           Vorherige</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"center\">";
               // nur Webmaster darf neue Ankuendigungen erfassen
               if(isModerator())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements_new.php?headline=". $_GET["headline"]. "'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neu anlegen\">
                           &nbsp;Neu anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($count_date > $_GET["start"] + 10)
               {
                  $start = $_GET["start"] + 10;
                  echo "<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements.php?mode=". $_GET["mode"]. "&amp;start=$start&amp;headline=". $_GET["headline"]. "'\">N&auml;chsten
                           <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chsten\"></button>";
               }
            echo "</td>
         </tr>
      </table>";
   }
   echo "</div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>