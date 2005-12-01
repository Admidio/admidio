<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id : zeigt das Profil der übergebenen user_id an
 * new_user - 1 : Dialog um neue Benutzer hinzuzufügen.
 * url :     URL auf die danach weitergeleitet wird
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
require("../../system/tbl_user.php");

//prüfen ob in Popup angezeigt wird oder Normal (default)
if($_GET['popup'] == 1)$popup=1;
else $popup=0;

// pruefen, ob Modus neues Mitglied erfassen
if(!array_key_exists("new_user", $_GET))
   $a_new_user = false;
else
   $a_new_user = $_GET['new_user'];
   
// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
   $url = $_GET['url'];
else
   $url = urlencode(getHttpReferer());

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if(!editUser() && $_GET['user_id'] != $g_user_id)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

// user_id und edit-Modus ermitteln
if($a_new_user)
{
   if(strlen($_GET['user_id']) > 0)
      $a_user_id = $_GET['user_id'];
   else
      $a_user_id = 0;
}
else
{
   $a_user_id = $_GET['user_id'];
   // jetzt noch schauen, ob User überhaupt Mitglied in der Gliedgemeinschaft ist
   $sql = "SELECT am_id
             FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
            WHERE ar_ag_shortname = '$g_organization'
              AND ar_valid        = 1
              AND am_ar_id        = ar_id
              AND am_valid        = 1
              AND am_au_id        = {0}";
   $sql    = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   
   if(mysql_num_rows($result) == 0)
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }
}

// User auslesen
if($a_user_id > 0)
{
   $user     = new CUser;
   if($a_new_user)
   {
      // aus User-Account ein neues Mitglied erstellen

      $sql    = "SELECT * FROM ". TBL_NEW_USER. " WHERE anu_id = $a_user_id";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
      $user_row = mysql_fetch_object($result);
      $user->m_name     = $user_row->anu_name;
      $user->m_vorname  = $user_row->anu_vorname;
      $user->m_mail     = $user_row->anu_mail;
      $user->m_login    = $user_row->anu_login;
      $user->m_password = $user_row->anu_password;
   }
   else
      $user->GetUser($a_user_id, $g_adm_con);
}

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>". $g_orga_property['ag_shortname']. " - Profil bearbeiten</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";
if($popup == 0)   
   require("../../../adm_config/header.php");
echo "</head>";
if($popup == 0)
	require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"profile_save.php?user_id=$a_user_id&amp;new_user=$a_new_user&amp;url=$url";
      if($a_new_user && $a_user_id > 0) echo "&amp;pw=$user->m_password";
      echo "\" method=\"post\" name=\"ProfilAnzeigen\">
      <div class=\"formHead\">";
         if($a_user_id == $g_user_id)
            echo strspace("Mein Profil", 2);
         else if($a_new_user)
            echo strspace("Neuer Benutzer", 2);
         else
            echo strspace("Profil von ". $user->m_vorname. " ". $user->m_name, 1);
      echo "</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 30%; float: left;\">Nachname:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_user_id == 0)
                  echo "<input type=\"text\" name=\"name\" size=\"30\" maxlength=\"30\" />";
               else
               {
                  echo "<input type=\"text\" name=\"name\" size=\"30\" maxlength=\"30\" value=\"$user->m_name\" ";
                  if(!hasRole('Webmaster'))
                     echo " class=\"readonly\" readonly ";
                  echo " />";
               }
            echo "</div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Vorname:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_user_id == 0)
                  echo "<input type=\"text\" name=\"vorname\" size=\"30\" maxlength=\"30\" />";
               else
               {
                  echo "<input type=\"text\" name=\"vorname\" size=\"30\" maxlength=\"30\" value=\"$user->m_vorname\" ";
                  if(!hasRole('Webmaster'))
                     echo " class=\"readonly\" readonly ";
                  echo " />";
               }
            echo "</div>
         </div>";
         if(!$a_user_id == 0)
         {
            echo "<div style=\"margin-top: 6px;\">
               <div style=\"text-align: right; width: 30%; float: left;\">Benutzername:</div>
               <div style=\"text-align: left; margin-left: 32%;\">
                  <input type=\"text\" name=\"login\" size=\"15\" maxlength=\"20\" value=\"$user->m_login\" ";
                  if(!hasRole('Webmaster'))
                     echo " class=\"readonly\" readonly ";
                  echo " />
               </div>
            </div>";

            // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
            if(hasRole('Webmaster') || $g_user_id == $a_user_id )
            {
               echo "<div style=\"margin-top: 6px;\">
                  <div style=\"text-align: right; width: 30%; float: left;\">Passwort:</div>
                  <div style=\"text-align: left; margin-left: 32%;\">
                     <button name=\"password\" type=\"button\" value=\"Passwort &auml;ndern\" onclick=\"window.open('password.php?user_id=$a_user_id','Titel','width=350,height=260,left=310,top=200')\">
                     <img src=\"$g_root_path/adm_program/images/lock.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Passwort &auml;ndern\">
                     Passwort &auml;ndern</button>
                  </div>
               </div>";
            }
         }

         echo "<hr width=\"80%\" />
         
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Adresse:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"adresse\" size=\"40\" maxlength=\"50\" />";
               else
                  echo "<input type=\"text\" name=\"adresse\" size=\"40\" maxlength=\"50\" value=\"$user->m_adresse\" />";
            echo "</div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Postleitzahl:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"plz\" size=\"10\" maxlength=\"10\" />";
               else
                  echo "<input type=\"text\" name=\"plz\" size=\"10\" maxlength=\"10\" value=\"$user->m_plz\" />";
            echo "</div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Ort:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"ort\" size=\"20\" maxlength=\"30\" />";
               else
                  echo "<input type=\"text\" name=\"ort\" size=\"20\" maxlength=\"30\" value=\"$user->m_ort\" />";
            echo "</div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Land:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"land\" size=\"20\" maxlength=\"30\" />";
               else
                  echo "<input type=\"text\" name=\"land\" size=\"20\" maxlength=\"30\" value=\"$user->m_land\" />";
            echo "</div>
         </div>
         
         <hr width=\"80%\" />
         
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Telefon 1:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"tel1\" size=\"15\" maxlength=\"20\" />";
               else
                  echo "<input type=\"text\" name=\"tel1\" size=\"15\" maxlength=\"20\" value=\"$user->m_tel1\" />";
            echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Tel.Nr.)</span></div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Telefon 2:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"tel2\" size=\"15\" maxlength=\"20\" />";
               else
                  echo "<input type=\"text\" name=\"tel2\" size=\"15\" maxlength=\"20\" value=\"$user->m_tel2\" />";
            echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Tel.Nr.)</span></div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Handy:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"mobil\" size=\"15\" maxlength=\"20\" />";
               else
                  echo "<input type=\"text\" name=\"mobil\" size=\"15\" maxlength=\"20\" value=\"$user->m_mobil\" />";
            echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Handynr.)</span></div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Fax:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"fax\" size=\"15\" maxlength=\"20\" />";
               else
                  echo "<input type=\"text\" name=\"fax\" size=\"15\" maxlength=\"20\" value=\"$user->m_fax\" />";
            echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Faxnr.)</span></div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Geburtstag:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"geburtstag\" size=\"10\" maxlength=\"10\" />";
               else
                  echo "<input type=\"text\" name=\"geburtstag\" size=\"10\" maxlength=\"10\" value=\"". mysqldatetime('d.m.y', $user->m_geburtstag). "\" />";
            echo "</div>
         </div>

         <hr width=\"80%\" />
         
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">E-Mail:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_user_id == 0)
                  echo "<input type=\"text\" name=\"mail\" size=\"40\" maxlength=\"50\" />";
               else
                  echo "<input type=\"text\" name=\"mail\" size=\"40\" maxlength=\"50\" value=\"$user->m_mail\" />";
            echo "</div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Homepage:</div>
            <div style=\"text-align: left; margin-left: 32%;\">";
               if($a_new_user)
                  echo "<input type=\"text\" name=\"weburl\" size=\"40\" maxlength=\"50\" />";
               else
                  echo "<input type=\"text\" name=\"weburl\" size=\"40\" maxlength=\"50\" value=\"$user->m_weburl\" />";
            echo "</div>
         </div>";
         
         if(!$a_new_user)
         {
            echo "<hr width=\"80%\" />";

            // alle zugeordneten Messengerdaten einlesen
            $sql = "SELECT auf_name, aud_value
                      FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                        ON aud_auf_id = auf_id
                       AND aud_au_id         = $user->m_id
                     WHERE auf_ag_shortname IS NULL
                       AND auf_type          = 'MESSENGER'
                     ORDER BY auf_name ASC ";
            $result_msg = mysql_query($sql, $g_adm_con);
            db_error($result_msg, true);

            while($row = mysql_fetch_object($result_msg))
            {
               echo "<div style=\"margin-top: 6px;\">
                  <div style=\"text-align: right; width: 30%; float: left;\">
                     $row->auf_name:
                     <img src=\"$g_root_path/adm_program/images/";
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
                     echo "\" style=\"vertical-align: middle;\" /></div>
                  <div style=\"text-align: left; margin-left: 32%;\">";
                     if($a_new_user)
                        echo "<input type=\"text\" name=\"$row->auf_name\" size=\"20\" maxlength=\"50\" />";
                     else
                        echo "<input type=\"text\" name=\"$row->auf_name\" size=\"20\" maxlength=\"50\" value=\"$row->aud_value\" />";
                  echo "</div>
               </div>";
            }
         }

         // gruppierungsspezifische Felder einlesen
         if($a_new_user)
         {
            $sql = "SELECT *
                      FROM ". TBL_USER_FIELDS. "
                     WHERE auf_ag_shortname = '$g_organization'
                  ORDER BY auf_name ASC ";
         }
         else
         {
            $sql = "SELECT *
                      FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                        ON aud_auf_id = auf_id
                       AND aud_au_id = $user->m_id
                     WHERE auf_ag_shortname = '$g_organization' ";
            if(!isModerator())
               $sql = $sql. " AND auf_locked = 0 ";
            $sql = $sql. " ORDER BY auf_name ASC ";
         }
         $result_field = mysql_query($sql, $g_adm_con);
         db_error($result_field, true);
         
         if(mysql_num_rows($result_field) > 0)
            echo "<hr width=\"80%\" />";

         while($row = mysql_fetch_object($result_field))
         {
            echo "<div style=\"margin-top: 6px;\">
               <div style=\"text-align: right; width: 30%; float: left;\">
                  $row->auf_name:</div>
               <div style=\"text-align: left; margin-left: 32%;\">";
                  echo "<input type=\"";
                  if($row->auf_type == "CHECKBOX")
                     echo "checkbox";
                  else
                     echo "text";
                  echo "\" id=\"". urlencode($row->auf_name). "\" name=\"". urlencode($row->auf_name). "\" ";
                  if($row->auf_type == "CHECKBOX")
                  {
                     if($row->aud_value == 1)
                        echo " checked ";
                     echo " value=\"1\" ";
                  }
                  else
                  {
                     if($row->auf_type == "NUMERIC")
                        echo " size=\"10\" maxlength=\"15\" ";
                     elseif($row->auf_type == "TEXT")
                        echo " size=\"30\" maxlength=\"30\" ";
                     elseif($row->auf_type == "TEXT_BIG")
                        echo " size=\"40\" maxlength=\"255\" ";
                     if(strlen($row->aud_value) > 0)
                        echo " value=\"$row->aud_value\" ";
                  }
                  echo ">";
               echo "</div>
            </div>";
         }

         echo "<hr width=\"80%\" />
         
         <div style=\"margin-top: 6px;\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
               <img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                 Speichern</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                 
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
              Zur&uuml;ck</button>
         </div>";

         if($user->m_last_change_id > 0)
         {
            // Angabe über die letzten Aenderungen
            $sql    = "SELECT au_vorname, au_name
                         FROM ". TBL_USERS. "
                        WHERE au_id = $user->m_last_change_id ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result, true);
            $row = mysql_fetch_array($result);

            echo "<div style=\"margin-top: 6px;\"><span style=\"font-size: 10pt\">
                     Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $user->m_last_change).
                     " durch $row[0] $row[1]</span>
                  </div>";
         }

      echo "</div>
   </form>
   </div>";
if($popup == 0)
   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";

?>