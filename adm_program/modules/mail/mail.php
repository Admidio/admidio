<?php
/******************************************************************************
 * E-Mails verschicken
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * au_id   - E-Mail an den entsprechenden Benutzer schreiben
 * rolle   - E-Mail an alle Mitglieder der Rolle schreiben
 * subject - Betreff der E-Mail
 * body    - Inhalt der E-Mail
 * kopie   - 1 (Default) Checkbox "Kopie an mich senden" ist gesetzt
 *         - 0 Checkbox "Kopie an mich senden" ist NICHT gesetzt
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
require("../../system/tbl_user.php");

// Prüfungen, ob die Seite regulaer aufgerufen wurde

if($g_current_organization->mail_extern == 1)
{
  // es duerfen oder koennen keine Mails ueber den Server verschickt werden
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=mail_extern";
   header($location);
   exit();
}

if(array_key_exists("rolle", $_GET))
{
   if($g_session_valid)
   {
      $sql    = "SELECT ar_r_mail_login FROM ". TBL_ROLES. "
                  WHERE ar_ag_shortname    = '$g_organization'
                    AND UPPER(ar_funktion) = UPPER('". $_GET['rolle']. "') ";
   }
   else
   {
      $sql    = "SELECT ar_r_mail_logout FROM ". TBL_ROLES. "
                  WHERE ar_ag_shortname    = '$g_organization'
                    AND UPPER(ar_funktion) = UPPER('". $_GET['rolle']. "') ";
   }
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $row = mysql_fetch_array($result);

   if($row[0] != 1)
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid";
      header($location);
      exit();
   }
}

if(!array_key_exists("subject", $_GET))
   $_GET["subject"] = "";
if(!array_key_exists("body", $_GET))
   $_GET["body"]  = "";
if(!array_key_exists("kopie", $_GET))
   $_GET["kopie"] = "1";

if($g_current_user->id != 0)
{
   $user     = new CUser;
   $user->GetUser($g_current_user->id, $g_adm_con);
}

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - E-Mail verschicken</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"mail_send.php?";
      // au_id wird mit GET uebergeben,
      // da keine E-Mail-Adresse von mail_send angenommen werden soll
      if(array_key_exists("au_id", $_GET))
         echo "au_id=". $_GET['au_id']. "&";
      echo "url=". urlencode(getHttpReferer()). "\" method=\"post\" name=\"Mail\" enctype=\"multipart/form-data\">

      <div class=\"formHead\">";
         if($_GET["subject"] == "")
            echo strspace("E-Mail verschicken");
         else
            echo strspace($_GET["subject"]);
      echo "</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 70px; float: left;\">an:</div>
            <div style=\"text-align: left; margin-left: 80px;\">";
               if(array_key_exists("au_id", $_GET))
               {
                  // au_id wurde uebergeben, dann E-Mail direkt an den User schreiben
                  $sql    = "SELECT au_mail FROM ". TBL_USERS. " WHERE au_id = '". $_GET['au_id']. "' ";
                  $result = mysql_query($sql, $g_adm_con);
                  db_error($result, true);

                  $row = mysql_fetch_array($result);
                  echo "<input class=\"readonly\" readonly type=\"text\" name=\"mailto\" size=\"35\" maxlength=\"50\" value=\"". $row[0]. "\">";
               }
               elseif(array_key_exists("rolle", $_GET))
               {
                  // Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
                  echo "<input class=\"readonly\" readonly type=\"text\" name=\"rolle\" size=\"28\" maxlength=\"30\" value=\"". $_GET['rolle']. "\">
                     &nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_mail','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">";
               }
               else
               {
                  // keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
                  echo "<select size=\"1\" name=\"rolle\">";
                  echo "<option value=\"\" selected=\"selected\">- Bitte wählen -</option>";

                  if($g_session_valid)
                  {
                     // im eingeloggten Zustand duerfen nur Moderatoren an gelocked Rollen schreiben
                     if(isModerator())
                     {
                        $sql    = "SELECT ar_funktion FROM ". TBL_ROLES. "
                                    WHERE ar_ag_shortname = '$g_organization'
                                      AND ar_r_mail_login = 1
                                      AND ar_valid        = 1
                                    ORDER BY ar_funktion ";
                     }
                     else
                     {
                        $sql    = "SELECT ar_funktion FROM ". TBL_ROLES. "
                                    WHERE ar_ag_shortname = '$g_organization'
                                      AND ar_r_mail_login = 1
                                      AND ar_r_locked     = 0
                                      AND ar_valid        = 1
                                    ORDER BY ar_funktion ";
                     }
                  }
                  else
                  {
                     $sql    = "SELECT ar_funktion FROM ". TBL_ROLES. "
                                 WHERE ar_ag_shortname  = '$g_organization'
                                   AND ar_r_mail_logout = 1
                                   AND ar_valid         = 1
                                 ORDER BY ar_funktion ";
                  }
                  $result = mysql_query($sql, $g_adm_con);
                  db_error($result, true);

                  while($row = mysql_fetch_array($result))
                  {
                     echo "<option value=\"$row[0]\">$row[0]</option>";
                  }

                  echo "</select>&nbsp;
                  <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" alt=\"Hilfe\" title=\"Hilfe\"
                  onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_mail','Message','width=400,height=200,left=310,top=200')\">";
               }
            echo "</div>
         </div>

         <hr width=\"90%\" />

         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 70px; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 80px;\">";
               if($g_current_user->id != 0)
                  echo "<input class=\"readonly\" readonly type=\"text\" name=\"name\" size=\"30\" maxlength=\"50\" value=\"$user->m_vorname $user->m_name\">";
               else
                  echo "<input type=\"text\" name=\"name\" size=\"30\" maxlength=\"50\" value=\"\">";
            echo "</div>
         </div>
         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 70px; float: left;\">E-Mail:</div>
            <div style=\"text-align: left; margin-left: 80px;\">";
               if($g_current_user->id != 0)
                  echo "<input class=\"readonly\" readonly type=\"text\" name=\"mailfrom\" size=\"50\" maxlength=\"50\" value=\"$user->m_mail\">";
               else
                  echo "<input type=\"text\" name=\"mailfrom\" size=\"50\" maxlength=\"50\" value=\"\">";
            echo "</div>
         </div>

         <hr width=\"90%\" />

         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 70px; float: left;\">Betreff:</div>
            <div style=\"text-align: left; margin-left: 80px;\">";
               if($_GET["subject"] == "")
                  echo "<input type=\"text\" name=\"subject\" size=\"50\" maxlength=\"50\">";
               else
                  echo "<input class=\"readonly\" readonly type=\"text\" name=\"subject\" size=\"50\" maxlength=\"50\" value=\"". $_GET["subject"]. "\">";
            echo "</div>
         </div>
         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 70px; float: left;\">Nachricht:</div>
            <div style=\"text-align: left; margin-left: 80px;\">
               <textarea name=\"body\" rows=\"10\" cols=\"45\">". $_GET["body"]. "</textarea>
            </div>
         </div>";

         // Nur eingeloggte User duerfen Attachments mit max 3MB anhaengen...
         if(($g_session_valid) && ($g_current_organization->mail_attachement_size > 0))
         {
           echo "
           <div style=\"margin-top: 8px;\">
              <div style=\"text-align: right; width: 70px; float: left;\">Anhang:</div>
              <div style=\"text-align: left; margin-left: 80px;\">
                 <input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" . ($g_current_organization->mail_attachement_size * 1024) . "\">
                 <input name=\"userfile\" size=\"40\" type=\"file\">
              </div>
           </div>";
         }

         echo "
         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: left; margin-left: 80px;\">
               <input type=\"checkbox\" id=\"kopie\" name=\"kopie\" value=\"1\" ";
               if($_GET["kopie"] == 1) echo " checked=\"checked\" ";
               echo "> <label for=\"kopie\">Kopie der E-Mail an mich senden</label>
            </div>
         </div>

         <hr width=\"90%\" />

         <div style=\"margin-top: 8px;\">
            <button name=\"abschicken\" type=\"submit\" value=\"abschicken\">
               <img src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Abschicken\">
               Abschicken</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
               Zur&uuml;ck</button>
         </div>
      </div>
   </form>

   </div>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>