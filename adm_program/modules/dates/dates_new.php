<?php
/******************************************************************************
 * Termine anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * at_id: ID des Termins, der bearbeitet werden soll
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
require("../../system/session_check_login.php");

if(!editDate())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$global        = 0;
$headline      = "";
$date_from     = "";
$time_from     = "";
$date_to       = "";
$time_to       = "";
$meeting_point = "";
$description   = "";

// Wenn eine Termin-ID uebergeben wurde, soll der Termin geaendert werden
// -> Felder mit Daten des Termins vorbelegen

if ($_GET["at_id"] != 0)
 {
   $sql    = "SELECT * FROM ". TBL_DATES. " WHERE at_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['at_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   if (mysql_num_rows($result) > 0)
   {
      $row_bt = mysql_fetch_object($result);

      // Normale User duerfen nur ihre eigenen Termine aendern
      if(!isModerator())
      {
         if($g_current_user->id != $row_bt->at_au_id)
         {
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
            header($location);
            exit();
         }
      }

      $global        = $row_bt->at_global;
      $headline      = $row_bt->at_ueberschrift;
      $date_from     = mysqldatetime("d.m.y", $row_bt->at_von);
      $time_from     = mysqldatetime("h:i", $row_bt->at_von);
      if($row_bt->at_von != $row_bt->at_bis)
      {
         // Datum-Bis nur anzeigen, wenn es sich von Datum-Von unterscheidet
         $date_to       = mysqldatetime("d.m.y", $row_bt->at_bis);
         $time_to       = mysqldatetime("h:i", $row_bt->at_bis);
      }
      if ($time_from == "00:00") $time_from = "";
      if ($time_to == "00:00")   $time_to = "";
      $meeting_point = $row_bt->at_ort;
      $description   = $row_bt->at_beschreibung;
   }
 }

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Termin</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"dates_function.php?at_id=". $_GET["at_id"]. "&amp;mode=";
      if($_GET["at_id"] > 0)
         echo "3";
      else
         echo "1";
      echo "\" method=\"post\" name=\"TerminAnlegen\">

      <div class=\"formHead\">";
         if($_GET["at_id"] > 0)
               echo strspace("Termin ändern", 2);
            else
               echo strspace("Termin anlegen", 2);
      echo "</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 25%; float: left;\">&Uuml;berschrift:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
               <input type=\"text\" name=\"ueberschrift\" size=\"53\" maxlength=\"100\" value=\"". htmlspecialchars($headline, ENT_QUOTES). "\">
            </div>
         </div>";

         // bei mehr als einer Gruppierung, Checkbox anzeigen, ob, Termin bei anderen angezeigt werden soll
         $sql = "SELECT COUNT(1) FROM ". TBL_ORGANIZATIONS. "
                  WHERE ag_mother IS NOT NULL ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
         $row = mysql_fetch_array($result);

         if($row[0] > 0)
         {
            echo "
            <div style=\"margin-top: 6px;\">
               <div style=\"text-align: right; width: 25%; float: left;\">&nbsp;</div>
               <div style=\"text-align: left; margin-left: 27%;\">
                  <input type=\"checkbox\" id=\"global\" name=\"global\" ";
                  if($global == 1)
                     echo " checked=\"checked\" ";
                  echo " value=\"1\" />
                  <label for=\"global\">Termin ist f&uuml;r mehrere Gruppierungen sichtbar</label>&nbsp;
                  <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" alt=\"Hilfe\" title=\"Hilfe\"
                  onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\">
               </div>
            </div>";
         }

         echo "<hr width=\"85%\" />

         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Datum Beginn:</div>
            <div style=\"text-align: left; width: 75%; position: relative; left: 2%;\">
               <input type=\"text\" name=\"datum_von\" size=\"10\" maxlength=\"10\" value=\"$date_from\">
               &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uhrzeit Beginn:&nbsp;
               <input type=\"text\" name=\"uhrzeit_von\" size=\"5\" maxlength=\"5\" value=\"$time_from\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Datum Ende:</div>
            <div style=\"text-align: left; width: 75%; position: relative; left: 2%;\">
               <input type=\"text\" name=\"datum_bis\" size=\"10\" maxlength=\"10\" value=\"$date_to\">
               &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uhrzeit Ende:&nbsp;
               <input type=\"text\" name=\"uhrzeit_bis\" size=\"5\" maxlength=\"5\" value=\"$time_to\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Treffpunkt:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
               <input type=\"text\" name=\"treffpunkt\" size=\"53\" maxlength=\"50\" value=\"". htmlspecialchars($meeting_point, ENT_QUOTES). "\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Beschreibung:";
               if($g_current_organization->bbcode == 1)
               {
                  echo "<br><br>
                  <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=400,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
               }
            echo "</div>
            <div style=\"text-align: left; margin-left: 27%;\">
               <textarea  name=\"beschreibung\" rows=\"10\" cols=\"40\">". htmlspecialchars($description, ENT_QUOTES). "</textarea>
            </div>
         </div>

         <hr width=\"85%\" />

         <div style=\"margin-top: 6px;\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
            Speichern</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            Zur&uuml;ck</button>
         </div>";
         if($row_bt->at_last_change_id > 0)
         {
            // Angabe &uuml;ber die letzten Aenderungen
            $sql    = "SELECT au_vorname, au_name
                         FROM ". TBL_USERS. "
                        WHERE au_id = $row_bt->at_last_change_id ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $row = mysql_fetch_array($result);

            echo "<div style=\"margin-top: 6px;\">
               <span style=\"font-size: 10pt\">
               Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $row_bt->at_last_change).
               " durch $row[0] $row[1]
               </span>
            </div>";
         }

      echo "</div>
   </form>

   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>