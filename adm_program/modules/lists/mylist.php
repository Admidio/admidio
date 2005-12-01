<?php
/******************************************************************************
 * Eigene Listen erstellen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rolle  : das Feld Rolle kann mit der entsprechenden Rolle vorbelegt werden
 * former : 0 - (Default) aktuelle Mitglieder der Rolle anzeigen
 *          1 - Ehemalige Mitglieder der Rolle anzeigen
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
require("../../system/common.php");
require("../../system/session_check_login.php");

if(!isset($_GET['rolle']))
   $_GET['rolle'] = "";

if(!isset($_GET['former']))
   $_GET['former'] = 0;

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>". $g_orga_property['ag_shortname']. " - Eigene Liste - Einstellungen</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";
   
   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   
   <form action=\"mylist_prepare.php\" method=\"post\" name=\"properties\">
      <div class=\"formHead\">";
         echo strspace("Eigene Liste", 1);
      echo "</div>
      <div class=\"formBody\">
      <b>1.</b> Wähle eine Rolle aus von der du eine Mitgliederliste erstellen willst:
      <p><b>Rolle :</b>&nbsp;&nbsp;
         <select size=\"1\" name=\"rolle\">
            <option value=\"\" selected=\"selected\"></option>";
            // Rollen selektieren

            // Webmaster und Moderatoren dürfen Listen zu allen Rollen sehen
            if(isModerator())
            {
               $sql     = "SELECT * FROM ". TBL_ROLES. "
                            WHERE ar_ag_shortname     = '$g_organization'
                              AND ar_valid            = 1
                            ORDER BY ar_funktion";
            }
            else
            {
               $sql     = "SELECT * FROM ". TBL_ROLES. "
                            WHERE ar_ag_shortname = '$g_organization'
                              AND ar_r_locked     = 0
                              AND ar_valid        = 1
                            ORDER BY ar_funktion";
            }
            $result_lst = mysql_query($sql, $g_adm_con);
            db_error($result_lst, true);

            while($row = mysql_fetch_object($result_lst))
            {
               echo "<option value=\"$row->ar_funktion\" ";
               if($_GET['rolle'] == $row->ar_funktion) echo " selected=\"selected\" ";
               echo ">$row->ar_funktion</option>";
            }
         echo "</select>
         &nbsp;&nbsp;&nbsp;
         <input type=\"checkbox\" id=\"former\" name=\"former\" value=\"1\" ";
         if($_GET['former'] == 1) echo " checked=\"checked\" ";
         echo " /> <label for=\"former\">nur Ehemalige</label></p>
         
         <p><b>2.</b> Bestimme die Felder, die in der Liste angezeigt werden sollen:</p>
         
         <table class=\"tableList\" style=\"width: 90%;\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
               <th class=\"tableHeader\">Nr.</th>
               <th class=\"tableHeader\">Feld</th>
               <th class=\"tableHeader\">Sortierung</th>
               <th class=\"tableHeader\">Bedingung
                  <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                  onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=condition','Message','width=450,height=250,left=310,top=200,scrollbars=yes')\">
               </th>
            </tr>";

            for($i = 1; $i < 9; $i++)
            {
               echo"<tr>
                  <td align=\"center\">&nbsp;$i. Feld :&nbsp;</td>
                  <td align=\"center\">
                     <select size=\"1\" name=\"column$i\">
                        <option value=\"\" selected=\"selected\"></option>
                        <option value=\"au_name\" ";
                           if($i == 1) echo " selected=\"selected\" ";
                           echo ">Nachname</option>
                        <option value=\"au_vorname\" ";
                           if($i == 2) echo " selected=\"selected\" ";
                           echo ">Vorname</option>
                        <option value=\"au_adresse\">Adresse</option>
                        <option value=\"au_plz\">PLZ</option>
                        <option value=\"au_ort\">Ort</option>
                        <option value=\"au_land\">Land</option>
                        <option value=\"au_tel1\">Telefon</option>
                        <option value=\"au_tel2\">Telefon 2</option>
                        <option value=\"au_mobil\">Handy</option>
                        <option value=\"au_mail\">E-Mail</option>
                        <option value=\"au_fax\">Fax</option>
                        <option value=\"au_geburtstag\">Geburtstag</option>
                        <option value=\"au_weburl\">Homepage</option>
                     </select>&nbsp;&nbsp;
                  </td>
                  <td align=\"center\">
                     <select size=\"1\" name=\"sort$i\">
                        <option value=\"\" selected=\"selected\">&nbsp;</option>
                        <option value=\"ASC\">A bis Z</option>
                        <option value=\"DESC\">Z bis A</option>
                     </select>
                  </td>
                  <td align=\"center\">
                     <input type=\"text\" name=\"condition$i\" size=\"15\" maxlength=\"30\" />
                  </td>
               </tr>";
            }
         echo "</table>
         
         <p>
            <button name=\"anzeigen\" type=\"submit\" value=\"anzeigen\">
            <img src=\"$g_root_path/adm_program/images/list.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Liste anzeigen\">
            &nbsp;Liste anzeigen</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            Zur&uuml;ck</button>
         </p>
      </div>
   </form>
   </div>";
   
   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>