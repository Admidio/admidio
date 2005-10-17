<?php
/******************************************************************************
 * Rollen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * ar_id: ID der Rolle, die bearbeitet werden soll
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

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!isModerator())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$rolle          = "";
$beschreibung   = "";
$r_moderation   = 0;
$r_termin       = 0;
$r_foto         = 0;
$r_download     = 0;
$r_user         = 0;
$r_locked       = 0;
$r_mail_logout  = 0;
$r_mail_login   = 0;
$r_gruppe       = 0;
$datum_von      = "";
$uhrzeit_von    = "";
$datum_bis      = "";
$uhrzeit_bis    = "";
$wochentag      = 0;
$ort            = "";
$max_mitglieder = 0;
$beitrag        = null;

// Wenn eine Rollen-ID uebergeben wurde, soll die Rolle geaendert werden
// -> Felder mit Daten der Rolle vorbelegen

if ($_GET['ar_id'] != 0)
 {
   $sql    = "SELECT * FROM adm_rolle WHERE ar_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['ar_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   if (mysql_num_rows($result) > 0)
   {
      $row_ar = mysql_fetch_object($result);

      // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
      if($row_ar->ar_funktion == "Webmaster" && !hasRole("Webmaster"))
      {
         if($g_user_id != $row_ar->ar_au_id)
         {
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
            header($location);
            exit();
         }
      }

      $rolle         = $row_ar->ar_funktion;
      $beschreibung  = $row_ar->ar_beschreibung;
      $r_moderation  = $row_ar->ar_r_moderation;
      $r_termin      = $row_ar->ar_r_termine;
      $r_foto        = $row_ar->ar_r_foto;
      $r_download    = $row_ar->ar_r_download;
      $r_user        = $row_ar->ar_r_user_bearbeiten;
      $r_locked      = $row_ar->ar_r_locked;
      $r_mail_logout = $row_ar->ar_r_mail_logout;
      $r_mail_login  = $row_ar->ar_r_mail_login;
      $r_gruppe      = $row_ar->ar_gruppe;
      if($r_gruppe == 1)
      {
         // Daten nur fuellen, wenn die Rolle eine Gruppe ist
         $datum_von      = mysqldate("d.m.y", $row_ar->ar_datum_von);
         $uhrzeit_von    = mysqltime("h:i",   $row_ar->ar_zeit_von);
         $datum_bis      = mysqldate("d.m.y", $row_ar->ar_datum_bis);
         $uhrzeit_bis    = mysqltime("h:i",   $row_ar->ar_zeit_bis);
         if ($uhrzeit_von == "00:00") $uhrzeit_von = "";
         if ($uhrzeit_bis == "00:00") $uhrzeit_bis = "";
         $wochentag      = $row_ar->ar_wochentag;
         $ort            = $row_ar->ar_ort;
         $max_mitglieder = $row_ar->ar_max_mitglieder;
         $beitrag        = $row_ar->ar_beitrag;
      }
   }
 }

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_title - Rolle</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->

   <script language=\"JavaScript1.2\" type=\"text/javascript\"><!--\n
      function setDisabled()
      {
         if(document.TerminAnlegen.gruppe.checked == true)
         {
            document.TerminAnlegen.datum_von.disabled       = false;
            document.TerminAnlegen.datum_von.className      = '';
            document.TerminAnlegen.datum_bis.disabled       = false;
            document.TerminAnlegen.datum_bis.className      = '';
            document.TerminAnlegen.uhrzeit_von.disabled     = false;
            document.TerminAnlegen.uhrzeit_von.className    = '';
            document.TerminAnlegen.uhrzeit_bis.disabled     = false;
            document.TerminAnlegen.uhrzeit_bis.className    = '';
            document.TerminAnlegen.wochentag.disabled       = false;
            document.TerminAnlegen.wochentag.className      = '';
            document.TerminAnlegen.ort.disabled             = false;
            document.TerminAnlegen.ort.className            = '';
            document.TerminAnlegen.max_mitglieder.disabled  = false;
            document.TerminAnlegen.max_mitglieder.className = '';
            document.TerminAnlegen.beitrag.disabled         = false;
            document.TerminAnlegen.beitrag.className        = '';
         }
         else
         {
            document.TerminAnlegen.datum_von.disabled        = true;
            document.TerminAnlegen.datum_von.className      = 'disabled';
            document.TerminAnlegen.datum_bis.disabled       = true;
            document.TerminAnlegen.datum_bis.className      = 'disabled';
            document.TerminAnlegen.uhrzeit_von.disabled     = true;
            document.TerminAnlegen.uhrzeit_von.className    = 'disabled';
            document.TerminAnlegen.uhrzeit_bis.disabled     = true;
            document.TerminAnlegen.uhrzeit_bis.className    = 'disabled';
            document.TerminAnlegen.wochentag.disabled       = true;
            document.TerminAnlegen.wochentag.className      = 'disabled';
            document.TerminAnlegen.ort.disabled             = true;
            document.TerminAnlegen.ort.className            = 'disabled';
            document.TerminAnlegen.max_mitglieder.disabled  = true;
            document.TerminAnlegen.max_mitglieder.className = 'disabled';
            document.TerminAnlegen.beitrag.disabled         = true;
            document.TerminAnlegen.beitrag.className        = 'disabled';
         }
      }
   //--></script>";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"roles_function.php?ar_id=". $_GET['ar_id']. "&amp;mode=2\" method=\"post\" name=\"TerminAnlegen\">
      <div class=\"formHead\">";
         if($_GET['ar_id'] > 0)
            echo strspace("Rolle ändern", 2);
         else
            echo strspace("Rolle anlegen", 2);
      echo "</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 28%; float: left;\">Rolle:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input type=\"text\" name=\"rolle\" ";
               // bei bestimmte Rollen darf der Name nicht geaendert werden
               if(strcmp($rolle, "Webmaster") == 0)
                     echo " class=\"readonly\" readonly ";

               echo " size=\"48\" maxlength=\"50\" value=\"". htmlspecialchars($rolle, ENT_QUOTES). "\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input type=\"text\" name=\"beschreibung\" size=\"48\" maxlength=\"255\" value=\"". htmlspecialchars($beschreibung, ENT_QUOTES). "\">
            </div>
         </div>

         <hr width=\"85%\" />

         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"moderation\" name=\"moderation\" ";
               if($r_moderation == 1)
                  echo " checked ";
               if(strcmp($rolle, "Webmaster") == 0)
                  echo " disabled ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"moderation\">Moderation (Benutzer &amp; Rollen verwalten uvm.)&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_moderation','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"benutzer\" name=\"benutzer\" ";
               if($r_user == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/person.png\" alt=\"Daten aller Benutzer bearbeiten\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"benutzer\">Daten aller Benutzer bearbeiten&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_benutzer','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"termine\" name=\"termine\" ";
               if($r_termin == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/history.png\" alt=\"Termine erfassen und bearbeiten\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"termine\">Termine erfassen und bearbeiten&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_termine','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"foto\" name=\"foto\" ";
               if($r_foto == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Fotos hochladen und bearbeiten\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"foto\">Fotos hochladen und bearbeiten&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_foto','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"download\" name=\"download\" ";
               if($r_download == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/download.png\" alt=\"Downloads hochladen und bearbeiten\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"download\">Downloads hochladen und bearbeiten&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_download','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"mail_logout\" name=\"mail_logout\" ";
               if($r_mail_logout == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/mail-open.png\" alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"mail_logout\">Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_logout','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"mail_login\" name=\"mail_login\" ";
               if($r_mail_login == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/mail-open-key.png\" alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"mail_login\">Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_login','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 15%; float: left;\">
               <input type=\"checkbox\" id=\"locked\" name=\"locked\" ";
               if($r_locked == 1)
                  echo " checked ";
               echo " value=\"1\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur für Moderatoren sichtbar\">
            </div>
            <div style=\"text-align: left; margin-left: 16%;\">
               <label for=\"locked\">Rolle nur für Moderatoren sichtbar&nbsp;</label>
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_locked','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>

         <div style=\"clear: left;\"><hr width=\"85%\" /></div>

         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\"><label for=\"gruppe\">Gruppe:</label></div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input type=\"checkbox\" id=\"gruppe\" name=\"gruppe\" ";
               if($r_gruppe == 1)
                  echo " checked ";
               echo " value=\"1\" onclick=\"setDisabled()\" />&nbsp;
               <img src=\"$g_root_path/adm_program/images/gruppe.png\" alt=\"Gruppe\">&nbsp;
               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
               onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_gruppe','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Anzahl Mitglieder:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"max_mitglieder\" size=\"3\" maxlength=\"3\" value=\""; if($max_mitglieder > 0) echo $max_mitglieder; echo "\">&nbsp;(inkl. Leiter)</div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Datum von:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"datum_von\" size=\"10\" maxlength=\"10\" value=\"$datum_von\">
               bis
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"datum_bis\" size=\"10\" maxlength=\"10\" value=\"$datum_bis\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Uhrzeit:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"uhrzeit_von\" size=\"5\" maxlength=\"5\" value=\"$uhrzeit_von\">
               bis
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"uhrzeit_bis\" size=\"5\" maxlength=\"5\" value=\"$uhrzeit_bis\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Wochentag:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <select ";
               if($r_gruppe == 0)
                  echo " class=\"disabled\" disabled ";
               echo " size=\"1\" name=\"wochentag\">
               <option value=\"0\""; if($wochentag == 0) echo " selected=\"selected\""; echo ">&nbsp;</option>\n";
               for($i = 1; $i < 8; $i++)
               {
                  echo "<option value=\"$i\""; if($wochentag == $i) echo " selected=\"selected\""; echo ">". $arrDay[$i-1]. "</option>\n";
               }
               echo "</select>
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Ort:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"ort\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($ort, ENT_QUOTES). "\"></div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Beitrag:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input ";
               if($r_gruppe == 0)
                  echo "class=\"disabled\" disabled";
               echo " type=\"text\" name=\"beitrag\" size=\"6\" maxlength=\"6\" value=\"$beitrag\"> &euro;</div>
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
         if($row_ar->ar_last_change_id > 0)
         {
            // Angabe ueber die letzten Aenderungen
            $sql    = "SELECT au_vorname, au_name
                         FROM adm_user
                        WHERE au_id = $row_ar->ar_last_change_id ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result, true);
            $row = mysql_fetch_array($result);

            echo "<div style=\"margin-top: 6px;\">
               <span style=\"font-size: 10pt\">
               Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $row_ar->ar_last_change).
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