<?php
/******************************************************************************
 * Profil anzeigen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id: zeigt das Profil der übergebenen user_id an
 *          (wird keine user_id übergeben, dann Profil des eingeloggten Users anzeigen)
 * url:     URL auf die danach weitergeleitet wird
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

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
   $url = $_GET['url'];
else
   $url = urlencode(getHttpReferer());

if(!array_key_exists('user_id', $_GET))
{
   // wenn nichts uebergeben wurde, dann eigene Daten anzeigen
   $a_user_id = $g_user_id;
   $edit_user = true;
}
else
{
   // Daten eines anderen Users anzeigen und pruefen, ob editiert werden darf
   $a_user_id = $_GET['user_id'];
   if(editUser())
   {
      // jetzt noch schauen, ob User überhaupt Mitglied in der Gliedgemeinschaft ist
      $sql = "SELECT am_id
                FROM adm_mitglieder, adm_rolle
               WHERE ar_ag_shortname = '$g_organization'
                 AND ar_valid        = 1
                 AND am_ar_id        = ar_id
                 AND am_valid        = 1
                 AND am_au_id        = {0}";
      $sql    = prepareSQL($sql, array($_GET['user_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      if(mysql_num_rows($result) > 0)
         $edit_user = true;
      else
         $edit_user = false;
   }
   else
      $edit_user = false;
}

// User auslesen
if($a_user_id > 0)
{
   $user     = new CUser;
   $user->GetUser($a_user_id, $g_adm_con);
}

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>". $g_orga_property['ag_shortname']. " - Profil</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

      <div class=\"formHead\">";
         if($a_user_id == $g_user_id)
            echo strspace("Mein Profil", 2);
         else
            echo strspace("Profil von ". $user->m_vorname. " ". $user->m_name, 1);
      echo "</div>

      <div class=\"formBody\">
         <div style=\"width: 63%; margin-right: 3%; float: left;\">
            <div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
               <div class=\"groupBoxHeadline\">$user->m_vorname $user->m_name</div>

               <div style=\"float: left; width: 28%; text-align: left\">Adresse:";
                  if(strlen($user->m_plz) > 0 || strlen($user->m_ort) > 0)
                     echo "<br />&nbsp;";
                  if(strlen($user->m_land) > 0)
                     echo "<br />&nbsp;";
                  if(strlen($user->m_adresse) > 0
                  && (  strlen($user->m_plz)  > 0
                     || strlen($user->m_ort)  > 0 ))
                     echo "<br /><span style=\"font-size: 8pt;\">&nbsp;</span>";
               echo "</div>

               <div style=\"margin-left: 30%; text-align: left\">";
                  if(strlen($user->m_adresse) == 0 && strlen($user->m_plz) == 0 && strlen($user->m_ort) == 0)
                     echo "<i>keine Daten vorhanden</i>";
                  if(strlen($user->m_adresse) > 0)
                     echo $user->m_adresse;
                  if(strlen($user->m_plz) > 0 || strlen($user->m_ort) > 0)
                  {
                     echo "<br />";
                     if(strlen($user->m_plz) > 0)
                        echo $user->m_plz. " ";
                     if(strlen($user->m_ort) > 0)
                        echo $user->m_ort;
                  }
                  if(strlen($user->m_land) > 0)
                     echo "<br />". $user->m_land;

                  if(strlen($user->m_adresse) > 0
                  && (  strlen($user->m_plz)  > 0
                     || strlen($user->m_ort)  > 0 ))
                  {
                     // Button mit Karte anzeigen
                     $map_url = "http://link2.map24.com/?lid=8a24364a&maptype=JAVA&street0=$user->m_adresse";
                     if(strlen($user->m_plz)  > 0)
                        $map_url = $map_url. "&zip0=$user->m_plz";
                     if(strlen($user->m_ort)  > 0)
                        $map_url = $map_url. "&city0=$user->m_ort";

                     echo "<br />
                     <span style=\"font-size: 8pt;\">( <a href=\"$map_url\" target=\"_blank\">Stadtplan</a>";

                     if($g_user_id != $a_user_id)
                     {
                        $own_user = new CUser;
                        $own_user->GetUser($g_user_id, $g_adm_con);

                        if(strlen($own_user->m_adresse) > 0
                        && (  strlen($own_user->m_plz)  > 0
                           || strlen($own_user->m_ort)  > 0 ))
                        {
                           $route_url = "http://link2.map24.com/?lid=8a24364a&maptype=JAVA&action=route&sstreet=$own_user->m_adresse&dstreet=$user->m_adresse";
                           if(strlen($own_user->m_plz)  > 0)
                              $route_url = $route_url. "&szip=$own_user->m_plz";
                           if(strlen($own_user->m_ort)  > 0)
                              $route_url = $route_url. "&scity=$own_user->m_ort";
                           if(strlen($user->m_plz)  > 0)
                              $route_url = $route_url. "&dzip=$user->m_plz";
                           if(strlen($user->m_ort)  > 0)
                              $route_url = $route_url. "&dcity=$user->m_ort";
                           echo " - <a href=\"$route_url\" target=\"_blank\">Route berechnen</a>";
                        }
                     }

                     echo " )</span>";
                  }
               echo "</div>

               <div style=\"float: left; margin-top: 10px; width: 28%; text-align: left\">Telefon:</div>
               <div style=\"margin-top: 10px; margin-left: 30%; text-align: left\">$user->m_tel1&nbsp;</div>";

               if(strlen($user->m_tel2) > 0)
               {
                  echo "<div style=\"float: left; width: 28%; text-align: left\">2. Telefon:</div>
                  <div style=\"margin-left: 30%; text-align: left\">$user->m_tel2</div>";
               }

               echo "<div style=\"float: left; width: 28%; text-align: left\">Handy:</div>
               <div style=\"margin-left: 30%; text-align: left\">$user->m_mobil&nbsp;</div>";

               if(strlen($user->m_fax) > 0)
               {
                  echo "<div style=\"float: left; width: 28%; text-align: left\">Fax:</div>
                  <div style=\"margin-left: 30%; text-align: left\">$user->m_fax</div>";
               }

               // Block Geburtstag und Benutzer

               echo "<div style=\"float: left; margin-top: 10px; width: 28%; text-align: left\">Geburtstag:</div>
               <div style=\"margin-top: 10px; margin-left: 30%; text-align: left\">";
                  if(strlen($user->m_geburtstag) > 0 && strcmp($user->m_geburtstag, "0000-00-00") != 0)
                  {
                     echo mysqldatetime('d.m.y', $user->m_geburtstag);
                     // Alter berechnen
                     $act_date = getDate(time());
                     $geb_date = getDate(mysqlmaketimestamp($user->m_geburtstag));
                     $birthday = false;

                     if($act_date['mon'] >= $geb_date['mon'])
                     {
                        if($act_date['mon'] == $geb_date['mon'])
                        {
                           if($act_date['mday'] >= $geb_date['mday'])
                              $birthday = true;
                        }
                        else
                           $birthday = true;
                     }
                     $age = $act_date['year'] - $geb_date['year'];
                     if($birthday == false)
                        $age--;
                     echo "&nbsp;&nbsp;&nbsp;($age Jahre)";
                  }
                  else
                     echo "&nbsp;";
               echo "</div>
               <div style=\"float: left; width: 28%; text-align: left\">Benutzer:</div>
               <div style=\"margin-left: 30%; text-align: left\">$user->m_login&nbsp;</div>";

               // Block E-Mail und Homepage

               echo "<div style=\"float: left; margin-top: 10px; width: 28%; text-align: left\">E-Mail:</div>
               <div style=\"margin-top: 10px; margin-left: 30%; text-align: left\">";
                  if(strlen($user->m_mail) > 0)
                  {
                     echo "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?au_id=$user->m_id\">
                        <img src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle;\" alt=\"E-Mail an $user->m_mail schreiben\"
                        title=\"E-Mail an $user->m_mail schreiben\" border=\"0\"></a>
                     <a href=\"$g_root_path/adm_program/modules/mail/mail.php?au_id=$user->m_id\" style=\" overflow: visible; display: inline;\">$user->m_mail</a>";
                  }
                  else
                     echo "&nbsp;";
               echo "</div>
               <div style=\"float: left; width: 28%; text-align: left\">Homepage:</div>
               <div style=\"margin-left: 30%; text-align: left\">";
                  if(strlen($user->m_weburl) > 0)
                  {
                     $user->m_weburl = stripslashes($user->m_weburl);
                     $user->m_weburl = str_replace ("http://", "", $user->m_weburl);
                     echo "
                     <a href=\"http://$user->m_weburl\" target=\"_blank\">
                        <img src=\"$g_root_path/adm_program/images/globe.png\" style=\"vertical-align: middle;\" alt=\"Gehe zu $user->m_weburl\"
                           title=\"Gehe zu $user->m_weburl\" border=\"0\"></a>
                     <a href=\"http://$user->m_weburl\" target=\"_blank\">$user->m_weburl</a>";
                  }
                  else
                     echo "&nbsp;";
               echo "</div>
            </div>
         </div>

         <div style=\"width: 34%; float: left\">";
            // alle zugeordneten Messengerdaten einlesen
            $sql = "SELECT auf_name, auf_description, aud_value
                      FROM adm_user_data, adm_user_field
                     WHERE aud_au_id        = $user->m_id
                       AND aud_auf_id       = auf_id
                       AND auf_ag_shortname IS NULL
                       AND auf_type         = 'MESSENGER'
                     ORDER BY auf_name ASC ";
            $result_msg = mysql_query($sql, $g_adm_con);
            db_error($result_msg, true);
            $count_msg = mysql_num_rows($result_msg);

            // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
            if(isModerator())
            {
               // auch gesperrte Rollen, aber nur von dieser Gruppierung anzeigen
               $sql    = "SELECT ar_funktion, ar_ag_shortname, am_leiter
                            FROM adm_mitglieder, adm_rolle
                           WHERE am_ar_id = ar_id
                             AND am_valid = 1
                             AND am_au_id = $a_user_id
                             AND ar_valid = 1
                             AND (  ar_ag_shortname LIKE '$g_organization'
                                 OR (   ar_ag_shortname NOT LIKE '$g_organization'
                                    AND ar_r_locked = 0 ))
                           ORDER BY ar_ag_shortname, ar_funktion ";
            }
            else
            {
               // kein Moderator, dann keine gesperrten Rollen anzeigen
               $sql    = "SELECT ar_funktion, ar_ag_shortname, am_leiter
                            FROM adm_mitglieder, adm_rolle
                           WHERE am_ar_id    = ar_id
                             AND am_valid    = 1
                             AND am_au_id    = $a_user_id
                             AND ar_valid    = 1
                             AND ar_r_locked = 0
                           ORDER BY ar_ag_shortname, ar_funktion";
            }
            $result_role = mysql_query($sql, $g_adm_con);
            db_error($result_role, true);
            $count_role = mysql_num_rows($result_role);

            // 4. Spalte mit Daten fuer Messenger und Rollen
            if($count_msg > 0)
            {
               // Messenger anzeigen
               mysql_data_seek($result_msg, 0);
               $i=0;

               echo "<div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
               <div class=\"groupBoxHeadline\">Messenger</div>";

               while($row = mysql_fetch_object($result_msg))
               {
                  if($i > 0) echo "<br />";
                  echo "<img src=\"$g_root_path/adm_program/images/";
                  if($row->auf_name == 'AIM')
                      echo "aim.png";
                  elseif($row->auf_name == 'Google Talk')
                      echo "google.gif";
                  elseif($row->auf_name == 'ICQ')
                      echo "icq.png";
                  elseif($row->auf_name == 'MSN')
                      echo "msn.png";
                  elseif($row->auf_name == 'Skype')
                      echo "skype.png";
                  elseif($row->auf_name == 'Yahoo')
                      echo "yahoo.png";
                  echo "\" style=\"vertical-align: middle;\" alt=\"$row->auf_description\" title=\"$row->auf_description\" />";
                  if(strlen($row->aud_value) > 20)
                     echo "<span style=\"font-size: 8pt;\">&nbsp;&nbsp;$row->aud_value</span>";
                  else
                     echo "&nbsp;&nbsp;$row->aud_value";
                  $i++;
               }
               echo "</div>";
            }
            if($count_role > 0)
            {
               // Rollen anzeigen
               $sql = "SELECT ag_shortname FROM adm_gruppierung";
               $result = mysql_query($sql, $g_adm_con);
               db_error($result, true);

               $count_grp = mysql_num_rows($result);
               $i = 0;

               if($count_msg > 0) echo "<br />";

               echo "<div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
               <div class=\"groupBoxHeadline\">Rollen</div>";

               while($row = mysql_fetch_object($result_role))
               {
                  // jede einzelne Rolle anzeigen
                  if($i > 0) echo "<br />";

                  if($count_grp > 1)
                     echo "$row->ar_ag_shortname, ";
                  echo $row->ar_funktion;
                  if($row->am_leiter == 1) echo ", Leiter";
                  $i++;
               }
               echo "</div>";
            }
         echo "</div>";

         // gruppierungsspezifische Felder einlesen
         $sql = "SELECT *
                   FROM adm_user_field LEFT JOIN adm_user_data
                     ON aud_auf_id = auf_id
                    AND aud_au_id        = $user->m_id
                  WHERE auf_ag_shortname = '$g_organization' ";
         if(!isModerator())
            $sql = $sql. " AND auf_locked = 0 ";
         $sql = $sql. " ORDER BY auf_name ASC ";
         $result_field = mysql_query($sql, $g_adm_con);
         db_error($result_field, true);
         $count_field = mysql_num_rows($result_field);

         if($count_field > 0)
         {
            echo "<div style=\"clear: left;\"><br /></div>

            <div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
               <div class=\"groupBoxHeadline\">Zus&auml;tzliche Daten:</div>";
               $i = 1;

               while($row_field = mysql_fetch_object($result_field))
               {
                  $zweite_spalte = $i % 2;
                  if($zweite_spalte == 1)
                  {
                     // 1. Spalte
                     echo "<div style=\"max-height: 25px;\">
                        <div style=\"float: left; width: 20%; text-align: left\">$row_field->auf_name:</div>
                        <div style=\"";
                           if($i < $count_field) echo " float: left;  width: 30%; ";
                        echo "  position: relative; text-align: left\">";
                  }
                  else
                  {
                     // 2. Spalte
                        echo "<div style=\"float: left; width: 20%; position: relative; text-align: left\">$row_field->auf_name:</div>
                        <div style=\"text-align: left; position: relative;\">";
                  }

                  // Feldinhalt ausgeben
                  if($row_field->auf_type == 'CHECKBOX')
                  {
                     if($row_field->aud_value == 1)
                        echo "&nbsp;<img src=\"$g_root_path/adm_program/images/checkbox_checked.gif\">";
                     else
                        echo "&nbsp;<img src=\"$g_root_path/adm_program/images/checkbox.gif\">";
                  }
                  else
                  {
                     echo "$row_field->aud_value&nbsp;";
                  }

                  echo "</div>";

                  if(($zweite_spalte == 1 && $i == $count_field)
                  || $zweite_spalte == 0)
                     echo "</div>";
                  $i++;
               }
            echo "</div>";
         }

         echo "<div style=\"clear: left;\"><br /></div>

         <div style=\"margin-top: 6px;\">";
            if($edit_user)
            {
               echo "<button style=\"width: 150px;\" type=\"button\" name=\"bearbeiten\" value=\"bearbeiten\"
                  onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_edit.php?user_id=$a_user_id&amp;url=$url'\">
                  <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Profil bearbeiten\">
                  &nbsp;Profil bearbeiten</button>";
            }

            // Moderatoren & Gruppenleiter duerfen neue Rollen zuordnen
            if(isModerator() || isGroupLeader() || editUser())
            {
                  echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<button name=\"funktionen\" type=\"button\" value=\"funktionen\"
                     onClick=\"window.open('roles.php?user_id=$a_user_id&amp;popup=1','Titel','width=550,height=450,left=310,top=100,scrollbars=yes,resizable=yes')\">
                     <img src=\"$g_root_path/adm_program/images/wand.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Rollen zuordnen\">
                     &nbsp;Rollen zuordnen</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            }
            else
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

            echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='". urldecode($url). "'\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
               &nbsp;Zur&uuml;ck</button>
         </div>
      </div>
   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";

?>