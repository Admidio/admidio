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
 * start     - Angabe, ab welchem Datensatz Ankuendigungen angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) Ankuendigungen
 * id	       - Nur eine einzige Annkuendigung anzeigen lassen.
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
require_once("../../../adm_config/config.php");
require_once("../../system/function.php");
require_once("../../system/date.php");
require_once("../../system/string.php");
require_once("../../system/tbl_user.php");
require_once("../../system/session_check.php");
require_once("../../system/bbcode.php");

if(!array_key_exists("mode", $_GET))
   $_GET["mode"] = "all";

if(!array_key_exists("start", $_GET))
   $_GET["start"] = 0;

if(!array_key_exists("headline", $_GET))
   $_GET["headline"] = "Ankündigungen";

if($g_orga_property['ag_bbcode'] == 1)
{
   // Klasse fuer BBCode
   $bbcode = new ubbParser();
}

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>". $g_orga_property['ag_shortname']. " - ". $_GET["headline"]. "</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

if($g_orga_property['ag_enable_rss'] == 1)
{
echo "
   <link type=\"application/rss+xml\" rel=\"alternate\" title=\"$g_orga_property[ag_longname] - Ankuendigungen\" href=\"$g_root_path/adm_program/modules/announcements/rss_announcements.php\">";
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
   <h1>". strspace($_GET["headline"]). "</h1>";

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


   // falls eine id fuer eine bestimmte Ankuendigung uebergeben worden ist...
   if (array_key_exists("id", $_GET))
   {
      $sql    = "SELECT * FROM adm_ankuendigungen
                  WHERE aa_id = $_GET[id]";
   }
   //...ansonsten alle fuer die Gruppierung passenden Ankuendigungen aus der DB holen.
   else
   {
      $sql    = "SELECT * FROM adm_ankuendigungen
                  WHERE (  aa_ag_shortname = '$g_organization'
                        OR (   aa_global   = 1
                           AND aa_ag_shortname IN ($organizations) ))
                  ORDER BY aa_timestamp DESC
                  LIMIT ". $_GET["start"]. ", 10 ";
   }

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   // Gucken wieviele Datensaetze die Abfrage ermittelt hat...
   $row_count = mysql_num_rows($result);

   if ($row_count == 0)
   {
   	if (array_key_exists("id", $_GET))
   	{
   		echo "<p>Der angeforderte Eintrag exisitiert nicht (mehr) in der Datenbank.</p>";
   	}
   	else
   	{
   		echo "<p>Es sind keine Daten vorhanden.</p>";
   	}
   }
   else
   {

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
               if($row_count > $_GET["start"] + 10)
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
                  <img src=\"$g_root_path/adm_program/images/note.png\" style=\"vertical-align: top;\" alt=\"". strSpecialChars2Html($row->aa_ueberschrift). "\">&nbsp;".
                  strSpecialChars2Html($row->aa_ueberschrift). "
               </div>";

               // aendern & loeschen duerfen nur Moderatoren
               if(isModerator())
               {
                  echo "<div style=\"text-align: right;\">" .
                     mysqldatetime("d.m.y", $row->aa_timestamp). "&nbsp;
                     <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                     onclick=\"self.location.href='announcements_new.php?aa_id=$row->aa_id&amp;headline=". $_GET['headline']. "'\">";

                  // Loeschen darf man nur Ankuendigungen der eigenen Gliedgemeinschaft
                  if($row->aa_ag_shortname == $g_organization)
                  {
                     echo "&nbsp;
                     <img src=\"$g_root_path/adm_program/images/delete.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\" ";
                     $load_url = urlencode("$g_root_path/adm_program/modules/announcements/announcements_function.php?aa_id=$row->aa_id&amp;mode=2&amp;url=$g_root_path/adm_program/modules/announcements/announcements.php");
                     echo " onclick=\"self.location.href='$g_root_path/adm_program/system/err_msg.php?err_code=delete_announcement&amp;err_text=". urlencode($row->aa_ueberschrift). "&amp;err_head=L&ouml;schen&amp;button=2&amp;url=$load_url'\">";
                  }
                  echo "&nbsp;</div>";
               }
               else
               {
                  echo "<div style=\"text-align: right;\">". mysqldatetime("d.m.y", $row->aa_timestamp). "&nbsp;</div>";
               }
            echo "</div>

            <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">";
               // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
               if($g_orga_property['ag_bbcode'] == 1)
                  echo strSpecialChars2Html($bbcode->parse($row->aa_beschreibung));
               else
                  echo nl2br(strSpecialChars2Html($row->aa_beschreibung));
            echo "</div>
            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
                  Angelegt von ". strSpecialChars2Html($user->au_vorname). " ". strSpecialChars2Html($user->au_name).
                  " am ". mysqldatetime("d.m.y h:i", $row->aa_timestamp). "
            </div>
         </div>

         <br />";
      }  // Ende While-Schleife
   }

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
               // nur Nutzer mit Moderatorenrechten duerfen neue Ankuendigungen erfassen
               if(isModerator())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements_new.php?headline=". $_GET["headline"]. "'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neu anlegen\">
                           &nbsp;Neu anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($row_count > $_GET["start"] + 10)
               {
                  $start = $_GET["start"] + 10;
                  echo "<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 152px;\"
                           onclick=\"self.location.href='announcements.php?mode=". $_GET["mode"]. "&amp;start=$start&amp;headline=". $_GET["headline"]. "'\">N&auml;chsten
                           <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chsten\"></button>";
               }
            echo "</td>
         </tr>
      </table>";

   echo "</div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>