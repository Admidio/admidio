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
 * id		        - Nur einen einzigen Termin anzeigen lassen.
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
require("../../system/session_check.php");
require("../../system/bbcode.php");

if(!array_key_exists("mode", $_GET))
   $_GET["mode"] = "actual";

if(!array_key_exists("start", $_GET))
   $_GET["start"] = 0;

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
   <title>". $g_orga_property['ag_shortname']. " - Termine</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

if($g_orga_property['ag_enable_rss'] == 1)
{
echo "
   <link type=\"application/rss+xml\" rel=\"alternate\" title=\"$g_orga_property[ag_longname] - Termine\" href=\"$g_root_path/adm_program/modules/dates/rss_dates.php\">";
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


   // alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
   $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
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

   // falls eine id fuer ein bestimmtes Datum uebergeben worden ist...
   if (array_key_exists("id", $_GET))
   {
   	 $sql    = "SELECT * FROM ". TBL_DATES. "
                  WHERE at_id = $_GET[id]";
   }
   //...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
	else
	{
   	//fuer alter Termine...
   	if(strcmp($_GET['mode'], "old") == 0)
   	{
         $sql    = "SELECT * FROM ". TBL_DATES. "
                     WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                       AND at_von < SYSDATE()
                       AND at_bis < SYSDATE()
                     ORDER BY at_von DESC
                     LIMIT {0}, 10 ";
      }
      //... ansonsten fuer neue Termine
      else
      {
         $sql    = "SELECT * FROM ". TBL_DATES. "
                     WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                       AND (  at_von >= SYSDATE()
                           OR at_bis >= SYSDATE() )
                     ORDER BY at_von ASC
                     LIMIT {0}, 10 ";
      }
	}

   $sql    = prepareSQL($sql, array($_GET['start']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   // Gucken wieviele Datensaetze die Abfrage ermittelt hat...
   $row_count = mysql_num_rows($result);

   if($row_count == 0)
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
                           onclick=\"self.location.href='dates.php?mode=". $_GET["mode"]. "&amp;start=$start'\">
                           <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Vorherige Termine\">
                           vorherige Termine</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"center\">";
               // wenn Termine editiert werden duerfen, dann anzeigen
               if(editDate())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates_new.php'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neuer Termin\">
                           &nbsp;Termin anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($row_count > $_GET["start"] + 10)
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
         $sql     = "SELECT * FROM ". TBL_USERS. " WHERE au_id = $row->at_au_id";
         $result2 = mysql_query($sql, $g_adm_con);
         db_error($result2);

         $user = mysql_fetch_object($result2);

         echo "
         <div class=\"boxBody\" style=\"overflow: hidden;\">
            <div class=\"boxHead\">
               <div style=\"text-align: left; float: left;\">
                  <img src=\"$g_root_path/adm_program/images/history.png\" style=\"vertical-align: middle;\" alt=\"". strSpecialChars2Html($row->at_ueberschrift). "\">
                  ". mysqldatetime("d.m.y", $row->at_von). "
                  &nbsp;". strSpecialChars2Html($row->at_ueberschrift). "</div>";

               // aendern & loeschen darf man nur eigene Termine, ausser Moderatoren
               if (editDate() && (  isModerator() || $row->at_au_id == $g_user_id ))
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
                  echo "<br />Treffpunkt:&nbsp;<b>". strSpecialChars2Html($row->at_ort). "</b>";
               }

            echo "</div>
            <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">";
               // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
               if($g_orga_property['ag_bbcode'] == 1)
                  echo strSpecialChars2Html($bbcode->parse($row->at_beschreibung));
               else
                  echo nl2br(strSpecialChars2Html($row->at_beschreibung));
            echo "</div>
            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
                  Angelegt von ". strSpecialChars2Html($user->au_vorname). " ". strSpecialChars2Html($user->au_name).
                  " am ". mysqldatetime("d.m.y h:i", $row->at_timestamp). "
            </div>
         </div>

         <br />";
      }  // Ende While-Schleife
   }

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
               // wenn Termine editiert werden duerfen, dann anzeigen
               if(editDate())
               {
                  echo "<button name=\"new\" type=\"button\" value=\"new\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates_new.php'\">
                           <img src=\"$g_root_path/adm_program/images/write.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Neuer Termin\">
                           &nbsp;Termin anlegen</button>";
               }
            echo "</td>
            <td width=\"33%\" align=\"right\">";
               if($row_count > $_GET["start"] + 10)
               {
                  $start = $_GET["start"] + 10;
                  echo "<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 152px;\"
                           onclick=\"self.location.href='dates.php?mode=". $_GET["mode"]. "&amp;start=$start'\">n&auml;chsten Termine
                           <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chste Termine\"></button>";
               }
            echo "</td>
         </tr>
      </table>";

   echo "</div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>