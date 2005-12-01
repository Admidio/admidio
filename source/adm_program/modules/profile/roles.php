<?php
/******************************************************************************
 * Funktionen zuordnen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id     - Funktionen der uebergebenen user_id aendern
 * popup   : 0 - (Default) Fenster wird normal mit Homepagerahmen angezeigt
 *           1 - Fenster wurde im Popupmodus aufgerufen
 * url:        - URL auf die danach weitergeleitet wird
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
require("../../system/tbl_user.php");

// nur Webmaster & Moderatoren d&uuml;rfen Rollen zuweisen
if(!isModerator() && !isGroupLeader() && !editUser())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

if(!array_key_exists("popup", $_GET))
   $_GET['popup']    = 0;
if(!array_key_exists("new_user", $_GET))
   $_GET['new_user'] = 0;

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
   $url = $_GET['url'];
else
   $url = "";

$user     = new CUser;
$user->GetUser($_GET['user_id'], $g_adm_con);

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>Rollen zuordnen</title>
   <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";
   if($_GET['popup'] == 0)
      require("../../../adm_config/header.php");
echo "</head>";

if($_GET['popup'] == 0)
   require("../../../adm_config/body_top.php");
else
   echo "<body>";
   
echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"roles_save.php?user_id=". $_GET['user_id']. "&amp;popup=". $_GET['popup']. "&amp;new_user=". $_GET['new_user']. "&amp;url=$url\" method=\"post\" name=\"Funktionen\">
      <table class=\"tableList\" cellpadding=\"3\" cellspacing=\"0\" ";
         if($_GET['popup'] == 1)
            echo "style=\"width: 95%;\">";
         echo "<tr>
            <th class=\"tableHeader\" width=\"100%\" colspan=\"5\">Rollen f&uuml;r $user->m_vorname $user->m_name zuordnen</th>
         </tr>";

         if(isModerator())
         {
            // Alle Rollen der Gruppierung auflisten
            $sql    = "SELECT ar_funktion, ar_beschreibung, ar_gruppe, am_au_id, am_leiter
                         FROM ". TBL_ROLES. " LEFT JOIN ". TBL_MEMBERS. "
                           ON ar_id    = am_ar_id
                          AND am_au_id = {0}
                          AND am_valid = 1
                        WHERE ar_ag_shortname = '$g_organization'
                          AND ar_valid        = 1
                        ORDER BY ar_funktion";
         }
         elseif(isGroupLeader())
         {
            // Alle Rollen auflisten, bei denen das Mitglied Leiter ist
            $sql    = "SELECT br.ar_funktion, br.ar_beschreibung, br.ar_gruppe, mgl.am_au_id, mgl.am_leiter
                         FROM ". TBL_MEMBERS. " bm, ". TBL_ROLES. " br LEFT JOIN ". TBL_MEMBERS. " mgl
                           ON br.ar_id     = mgl.am_ar_id
                          AND mgl.am_au_id = {0}
                          AND mgl.am_valid = 1
                        WHERE bm.am_au_id  = $g_user_id
                          AND bm.am_valid  = 1
                          AND bm.am_leiter = 1
                          AND br.ar_id     = bm.am_ar_id
                          AND br.ar_ag_shortname = '$g_organization'
                          AND br.ar_valid        = 1
                          AND br.ar_r_locked     = 0
                        ORDER BY br.ar_funktion";
         }
         elseif(editUser())
         {
            // Alle Rollen auflisten, die keinen Moderatorenstatus haben
            $sql    = "SELECT ar_funktion, ar_beschreibung, ar_gruppe, am_au_id, am_leiter
                         FROM ". TBL_ROLES. " LEFT JOIN ". TBL_MEMBERS. "
                           ON ar_id    = am_ar_id
                          AND am_au_id = {0}
                          AND am_valid = 1
                        WHERE ar_ag_shortname = '$g_organization'
                          AND ar_valid        = 1
                          AND ar_r_moderation = 0
                          AND ar_r_locked     = 0
                        ORDER BY ar_funktion";
         }
         $sql    = prepareSQL($sql, array($_GET['user_id']));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result, true);
         $i = 0;

         while($row = mysql_fetch_object($result))
         {
            echo "<tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
               <td style=\"text-align: center; vertical-align: top;\">
                  <input type=\"checkbox\" id=\"fkt-$i\" name=\"fkt-$i\" ";
                     if($row->am_au_id > 0)
                        echo " checked ";
                        
                     // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                     if($row->ar_funktion == 'Webmaster' && !hasRole('Webmaster'))
                        echo " disabled ";
                        
                     echo " value=\"1\" />
               </td>
               <td style=\"text-align: left; vertical-align: top;\"><label for=\"fkt-$i\">$row->ar_funktion</label></td>
               <td style=\"text-align: left; vertical-align: top;\">$row->ar_beschreibung</td>
               <td style=\"text-align: center; vertical-align: top;\">";
                  if($row->ar_gruppe == 1)
                  {
                     echo "<input type=\"checkbox\" id=\"leiter-$i\" name=\"leiter-$i\" ";
                     if($row->am_leiter > 0)
                        echo " checked ";

                     echo " value=\"1\" />";
                  }
                  else
                     echo "&nbsp;";
               echo "</td>
               <td style=\"text-align: left; vertical-align: top;\">";
                  if($row->ar_gruppe == 1)
                  {
                     echo "<label for=\"leiter-$i\">Leiter</label>";
                  }
                  else
                     echo "&nbsp;";
               echo "</td>
            </tr>";
            $i++;
         }
   
         echo "<tr class=\"listMouseOut\">
            <td colspan=\"5\" style=\"text-align: center;\">
               <div style=\"margin: 8px;\">
                  <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                        <img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

                  <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
                        <img src=\"$g_root_path/adm_program/images/error.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Schlie&szlig;en\">
                        &nbsp;Schlie&szlig;en</button>
               </div>
            </td>
         </tr>
     </table>
   </form>
   
   </div>";
   
   if($_GET['popup'] == 0)
      require("../../../adm_config/body_bottom.php");

echo "</body>
</html>";
?>