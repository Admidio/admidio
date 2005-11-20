<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * mode: actual  - (Default) Alle aktuellen und zukuenftige Termine anzeigen
 *       old     - Alle bereits erledigten
 * start         - Angabe, ab welchem Datensatz Termine angezeigt werden sollen
 * dateid		 - Nur einen einzigen Termin anzeigen lassen.
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
   $_GET["mode"] = "actual";

if(!array_key_exists("start", $_GET))
   $_GET["start"] = 0;

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>". $g_orga_property['ag_shortname']. " - Termine</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

if($g_orga_property['ag_enable_rss'] == 1)
{
echo "
   <link type=\"application/rss+xml\" rel=\"alternate\" title=\"$g_orga_property[ag_homepage] - Die naechsten 10 Termine\" href=\"$g_root_path/adm_program/modules/rss/rss_dates.php\">";
};

echo "
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   <h1>";
      if(strcmp($_GET['mode'], "old") == 0)
         echo strspace("Alte Termine");
      else
         echo strspace("Termine");
   echo "</h1>";

   $act_date = date("Y.m.d 00:00:00", time());

   // alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
   $sql = "SELECT * FROM adm_gruppierung
            WHERE ag_shortname = '$g_organization'
               OR ag_mother    = '$g_organization' ";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $organizations = "";
   $i             = 0;

   while($row = mysql_fetch_object($result))
   {
      if($i > 0) $organizations = $organizations. ", ";

      if($row->ag_shortname == $g_organization)
         $organizations = $organizations. "'$row->ag_mother'";
      else
         $organizations = $organizations. "'$row->ag_shortname'";

      $i++;
   }

   if(strcmp($_GET['mode'], "old") == 0)
   {
      $sql    = "SELECT COUNT(*) FROM adm_termine
                  WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                    AND at_von < '$act_date'
                    AND at_bis < '$act_date'
                  ORDER BY at_von DESC ";
   }
   else
   {
      $sql    = "SELECT COUNT(*) FROM adm_termine
                  WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                    AND (  at_von >= '$act_date'
                        OR at_bis >= '$act_date' )
                  ORDER BY at_von ASC ";
   }
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $row = mysql_fetch_array($result);
   $count_date = $row[0];

   if($count_date == 0)
   {
      echo "<p>Es sind keine Termine vorhanden.</p>";
   }
   else
   {
      if(strcmp($_GET['mode'], "old") == 0)
      {
         $sql    = "SELECT * FROM adm_termine
                     WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                       AND at_von < '$act_date'
                       AND at_bis < '$act_date'
                     ORDER BY at_von DESC
                     LIMIT {0}, 10 ";
      }
      else
      {
         $sql    = "SELECT * FROM adm_termine
                     WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                       AND (  at_von >= '$act_date'
                           OR at_bis >= '$act_date' )
                     ORDER BY at_von ASC
                     LIMIT {0}, 10 ";
      }
      if (array_key_exists("dateid", $_GET))
      {
      	 $sql    = "SELECT * FROM adm_termine
                     WHERE at_id = $_GET[dateid]";
      }
      $sql    = prepareSQL($sql, array($_GET['start']));
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
                           onclick=\"self.location.href='dates.php?mode=". $_GET["mode"]. "&amp;start=$start'\">
                           <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Vorherige Termine\">
                           vorherige Termine</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"center\">";
               // wenn nicht eingeloggt oder Termin editiert werden darf, dann anzeigen
               if(!$g_session_valid || editDate())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates_new.php'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neuer Termin\">
                           &nbsp;Termin anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($count_date > $_GET["start"] + 10)
               {
                  $start = $_GET["start"] + 10;
                  echo "<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates.php?mode=". $_GET["mode"]. "&amp;start=$start'\">n&auml;chsten Termine
                           <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chste Termine\"></button>";
               }
            echo "</td>
         </tr>
      </table>";

      // Termine auflisten

      while($row = mysql_fetch_object($result))
      {
         $sql     = "SELECT * FROM adm_user WHERE au_id = $row->at_au_id";
         $result2 = mysql_query($sql, $g_adm_con);
         db_error($result2);

         $user = mysql_fetch_object($result2);

         echo "
         <div class=\"boxBody\" style=\"overflow: hidden;\">
            <div class=\"boxHead\">
               <div style=\"text-align: left; float: left;\">
                  <img src=\"$g_root_path/adm_program/images/history.png\" style=\"vertical-align: middle;\" alt=\"". specialChars2Html($row->at_ueberschrift). "\">
                  ". mysqldatetime("d.m.y", $row->at_von). "
                  &nbsp;". specialChars2Html($row->at_ueberschrift). "</div>";

               // aendern & loeschen darf man nur eigene Termine, ausser Moderatoren
               if (editDate()
               && (  isModerator()
                  || $row->at_au_id == $g_user_id ))
               {
                  echo "<div style=\"text-align: right;\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                     onclick=\"self.location.href='dates_new.php?at_id=$row->at_id'\">";

                  // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                  if($row->at_ag_shortname == $g_organization)
                  {
                     echo "&nbsp;
                     <img src=\"$g_root_path/adm_program/images/delete.png\" style=\"cursor: pointer\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\" ";
                     $load_url = urlencode("$g_root_path/adm_program/modules/dates/dates_function.php?at_id=$row->at_id&amp;mode=2&amp;url=$g_root_path/adm_program/modules/dates/dates.php");
                     echo " onclick=\"self.location.href='$g_root_path/adm_program/system/err_msg.php?err_code=delete_date&amp;err_text=". urlencode($row->at_ueberschrift). "&amp;err_head=L&ouml;schen&amp;button=2&amp;url=$load_url'\">";
                  }

                  echo "&nbsp;</div>";
               }
            echo "</div>

            <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">";
               if (mysqldatetime("h:i", $row->at_von) != "00:00")
               {
                  echo "Beginn:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>".
                     mysqldatetime("h:i", $row->at_von). "</b> Uhr&nbsp;&nbsp;&nbsp;&nbsp;";
               }

               if($row->at_von != $row->at_bis)
               {
                  if (mysqldatetime("h:i", $row->at_von) != "00:00")
                     echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

                  echo "Ende:&nbsp;";
                  if(mysqldatetime("d.m.y", $row->at_von) != mysqldatetime("d.m.y", $row->at_bis))
                  {
                     echo "<b>". mysqldatetime("d.m.y", $row->at_bis). "</b>";

                     if (mysqldatetime("h:i", $row->at_bis) != "00:00")
                        echo " um ";
                  }

                  if (mysqldatetime("h:i", $row->at_bis) != "00:00")
                     echo "<b>". mysqldatetime("h:i", $row->at_bis). "</b> Uhr";
               }

               if ($row->at_ort != "")
               {
                  echo "<br />Treffpunkt:&nbsp;<b>". specialChars2Html($row->at_ort). "</b>";
               }

            echo "</div>
            <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">". nl2br(specialChars2Html($row->at_beschreibung)). "</div>
            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
                  Angelegt von ". specialChars2Html($user->au_vorname). " ". specialChars2Html($user->au_name).
                  " am ". mysqldatetime("d.m.y h:i", $row->at_timestamp). "
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
                           onclick=\"self.location.href='dates.php?mode=". $_GET["mode"]. "&amp;start=$start'\">
                           <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Vorherige Termine\">
                           vorherige Termine</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"center\">";
               // wenn nicht eingeloggt oder Termin editiert werden darf, dann anzeigen
               if(!$g_session_valid || editDate())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates_new.php'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neuer Termin\">
                           &nbsp;Termin anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($count_date > $_GET["start"] + 10)
               {
                  $start = $_GET["start"] + 10;
                  echo "<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates.php?mode=". $_GET["mode"]. "&amp;start=$start'\">n&auml;chsten Termine
                           <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chste Termine\"></button>";
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