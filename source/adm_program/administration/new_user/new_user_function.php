<?php
/******************************************************************************
 * Neuen User zuordnen - Funktionen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * mode: 1 - User-Account einem Benutzer zuordnen
 *       3 - Abfrage, wie der Datensatz zugeordnet werden soll
 *       4 - User-Account loeschen
 * anu_id:   Id des Logins, das verarbeitet werden soll
 * au_id:    Id des Benutzers, dem das neue Login zugeordnet werden soll
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

// nur Webmaster duerfen User bestaetigen, ansonsten Seite verlassen
if(!hasRole("Webmaster"))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights&err_text=";
   header($location);
   exit();
}

if($_GET["mode"] == 1)
{
   // User-Account einem Mitglied zuordnen

   $sql    = "SELECT * FROM ". TBL_NEW_USER. " WHERE anu_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['anu_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   if($user_row = mysql_fetch_object($result))
   {
      $sql    = "SELECT au_login
                   FROM ". TBL_USERS. "
                  WHERE au_id = {0}";
      $sql    = prepareSQL($sql, array($_GET['au_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      $row = mysql_fetch_array($result);
      $old_login = $row[0];

      // Mitgliedsdaten updaten
      $sql    = "UPDATE ". TBL_USERS. " SET au_mail     = '$user_row->anu_mail'
                                    , au_login    = '$user_row->anu_login'
                                    , au_password = '$user_row->anu_password'
                  WHERE au_id = {0}";
      $sql    = prepareSQL($sql, array($_GET['au_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      if($g_forum == 1)
      {
         mysql_select_db($g_forum_db, $g_forum_con);

         // jetzt den User im Forum updaten
         $sql    = "UPDATE ". $g_forum_praefix. "_users SET username      = '$user_row->anu_login'
                                                          , user_password = '$user_row->anu_password'
                                                          , user_email    = '$user_row->anu_mail'
                     WHERE username = '$old_login' ";
         $result = mysql_query($sql, $g_forum_con);
         db_error($result);

         mysql_select_db($g_adm_db, $g_adm_con);
      }

      // nun kann der User-Account gel&ouml;scht werden
      $sql    = "DELETE FROM ". TBL_NEW_USER. " WHERE anu_id = {0}";
      $sql    = prepareSQL($sql, array($_GET['anu_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
      if($g_orga_property['ag_mail_extern'] != 1)
      {
         mail("$user_row->anu_mail", "Anmeldung auf $g_homepage", "Hallo $user_row->anu_vorname,\n\ndeine Anmeldung auf $g_homepage ".
              "wurde bestätigt.\n\nNun kannst du dich mit deinem Benutzernamen : $user_row->anu_login\nund dem Passwort auf der Homepage ".
              "einloggen.\n\nSollten noch Fragen bestehen, schreib eine Mail an webmaster@$g_domain .\n\nViele Grüße\nDie Webmaster",
              "From: webmaster@$g_domain");
      }

      $load_url = urlencode("$g_root_path/adm_program/administration/new_user/new_user.php");
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=send_login_mail&url=$load_url";
      header($location);
   }
}
elseif($_GET["mode"] == 3)
{
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>". $g_orga_property['ag_shortname']. " - Messagebox</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      if($_GET['timer'] > 0)
      {
         echo "<script language=\"JavaScript1.2\" type=\"text/javascript\"><!--\n
               window.setTimeout(\"window.location.href='". $_GET['url']. "'\", ". $_GET['timer']. ");\n
               //--></script>";
      }

      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");
      echo "<div align=\"center\"><br /><br /><br />

      <div class=\"formHead\" style=\"width: 400px\">". strspace("Anmeldung zuordnen"). "</div>

      <div class=\"formBody\" style=\"width: 400px\">
         <p style=\"text-align: left;\">
            <img src=\"$g_root_path/adm_program/images/properties.png\" style=\"vertical-align: bottom;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
            Existiert der Benutzer bereits in der Datenbank oder bist du dir nicht sicher,
            wähle erst einmal <b>zuordnen</b> aus. Dort werden dir alle vorhandenen Benutzer
            angezeigt und du kannst die Anmeldung einem vorhandenen Benutzer zuordnen oder einen neuen
            Benutzer anlegen.
         </p>
         <p style=\"text-align: left;\">
            <img src=\"$g_root_path/adm_program/images/person_new.png\" style=\"vertical-align: bottom;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
            Existiert dieser Benutzer noch nicht, kannst du aus der vorhandenen
            Anmeldung einen neuen Benutzer <b>anlegen</b>.
         </p>

            <button name=\"zuordnen\" type=\"button\" value=\"zuordnen\"
               onclick=\"self.location.href='$g_root_path/adm_program/administration/new_user/new_user_assign.php?anu_id=". $_GET['anu_id']. "&amp;all=0'\">
               <img src=\"$g_root_path/adm_program/images/properties.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
               &nbsp;Zuordnen</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"anlegen\" type=\"button\" value=\"anlegen\"
               onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_edit.php?user_id=". $_GET['anu_id']. "&amp;new_user=1&amp;url=". urlencode("$g_root_path/adm_program/administration/new_user/new_user.php"). "'\">
               <img src=\"$g_root_path/adm_program/images/person_new.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
               &nbsp;Anlegen</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"back\" type=\"button\" value=\"back\"
               onclick=\"history.back()\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
               &nbsp;Zur&uuml;ck</button>

      </div>
      </div>";

      require("../../../adm_config/body_bottom.php");
   echo "</body></html>";
}
elseif($_GET["mode"] == 4)
{
   // User-Account loeschen

   $sql    = "DELETE FROM ". TBL_NEW_USER. " WHERE anu_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['anu_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $location = "location: $g_root_path/adm_program/administration/new_user/new_user.php";
   header($location);
}

?>