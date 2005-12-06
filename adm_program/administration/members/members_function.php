<?php
/******************************************************************************
 * User auf der Homepage und im Forum ausloggen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * mode: 1 - MsgBox, in der erklaert wird, welche Auswirkungen das Loeschen hat
 *       2 - User NUR aus der Gliedgemeinschaft entfernen
 *       3 - User aus der Datenbank l&ouml;schen
 * 		4 - User E-Mail mit neuen Zugangsdaten schicken
 * user_id - Id des Benutzers, der bearbeitet werden soll
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

$err_code = "";
$err_text = "";

// nur Moderatoren duerfen Mitgliedschaften beenden
if(!isModerator())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

if($_GET["mode"] == 1)
{
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_current_organization->longname - Messagebox</title>
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

      <div class=\"formHead\" style=\"width: 400px\">". strspace("Mitglied löschen"). "</div>

      <div class=\"formBody\" style=\"width: 400px\">
         <p align=\"left\">
            Du kannst den Benutzer zu einem <b>Ehemaligen</b> machen. Dies hat den Vorteil, dass die Daten
            erhalten bleiben und du sp&auml;ter immer wieder sehen kannst, welchen Rollen diese Person
            zugeordnet war.
         </p>
         <p align=\"left\">
            Wenn du <b>L&ouml;schen</b> ausw&auml;hlst, dann wird der Datensatz entg&uuml;ltig aus der Datenbank
            entfernt und es ist sp&auml;ter nicht mehr m&ouml;glich Daten dieser Person einzusehen.
         </p>

            <button name=\"former\" type=\"button\" value=\"former\"
               onclick=\"self.location.href='$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET['user_id']. "&mode=2'\">
               <img src=\"$g_root_path/adm_program/images/person.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\">
               Ehemaliger</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"delete\" type=\"button\" value=\"delete\"
               onclick=\"self.location.href='$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET['user_id']. "&mode=3'\">
               <img src=\"$g_root_path/adm_program/images/delete.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\">
               L&ouml;schen</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"back\" type=\"button\" value=\"back\"
               onclick=\"history.back()\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\">
               Zur&uuml;ck</button>

      </div>
      </div>";

      require("../../../adm_config/body_bottom.php");
   echo "</body></html>";
   exit();
}
elseif($_GET["mode"] == 2)
{
   // User NUR aus der Gliedgemeinschaft entfernen

   // Moderatoren duerfen keine Webmaster entfernen
   if(isModerator() && hasRole("Webmaster", $user_id))
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }

   $sql = "SELECT am_id
             FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
            WHERE ar_ag_shortname = '$g_organization'
              AND ar_valid        = 1
              AND am_ar_id        = ar_id
              AND am_valid        = 1
              AND am_au_id        = {0}";
   $sql        = prepareSQL($sql, array($_GET['user_id']));
   $result_mgl = mysql_query($sql, $g_adm_con);
   db_error($result_mgl);

   while($row = mysql_fetch_object($result_mgl))
   {
      // alle Rollen der aktuellen Gliedgemeinschaft auf ungueltig setzen
      $sql    = "UPDATE ". TBL_MEMBERS. " SET am_valid = 0
                  WHERE am_id = $row->am_id ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
   }

   if($g_forum)
   {
      mysql_select_db($g_forum_db, $g_forum_con);

      // Loginname auslesen
      $sql = "SELECT au_login FROM ". TBL_USERS. " WHERE au_id = {0}";
      $sql = prepareSQL($sql, array($_GET['user_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      $row = mysql_fetch_array($result);
      $login = $row[0];

      // User in Foren-Tabelle suchen
      $sql    = "SELECT user_id FROM ". $g_forum_praefix. "_users WHERE username LIKE '$login'";
      $result = mysql_query($sql, $g_forum_con);
      db_error($result);

      $row = mysql_fetch_array($result);
      $forum_user_id = $row[0];

      // nur l&ouml;schen, wenn auch ein User existiert
      if(strlen($forum_user_id) > 0)
      {
         // erst einmal alle bisherigen Gruppen des Users loeschen
         $sql    = "DELETE FROM ". $g_forum_praefix. "_user_group WHERE user_id = $forum_user_id";
         $result = mysql_query($sql, $g_forum_con);
         db_error($result);

         // jetzt User loeschen
         $sql    = "DELETE FROM ". $g_forum_praefix. "_users WHERE user_id = $forum_user_id";
         $result = mysql_query($sql, $g_forum_con);
         db_error($result);
      }

      mysql_select_db($g_adm_db, $g_adm_con);
   }

   $err_code = "delete_member_ok";
}
elseif($_GET["mode"] == 3)
{
   // nur Webmaster duerfen User physikalisch loeschen
   if(!hasRole("Webmaster"))
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }

   // User aus der Datenbank loeschen

   $sql = "SELECT au_login FROM ". TBL_USERS. " WHERE au_id = {0}";
   $sql = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $row = mysql_fetch_array($result);
   $login = $row[0];

   $sql    = "DELETE FROM ". TBL_MEMBERS. " WHERE am_au_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $sql    = "DELETE FROM ". TBL_USERS. " WHERE au_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   if($g_forum)
   {
      mysql_select_db($g_forum_db, $g_forum_con);

      // User in Foren-Tabelle suchen
      $sql    = "SELECT user_id FROM ". $g_forum_praefix. "_users WHERE username LIKE '$login'";
      $result = mysql_query($sql, $g_forum_con);
      db_error($result);

      $row = mysql_fetch_array($result);
      $forum_user_id = $row[0];

      // nur l&ouml;schen, wenn auch ein User existiert
      if(strlen($forum_user_id) > 0)
      {
         // erst einmal alle bisherigen Gruppen des Users loeschen
         $sql    = "DELETE FROM ". $g_forum_praefix. "_user_group WHERE user_id = $forum_user_id";
         $result = mysql_query($sql, $g_forum_con);
         db_error($result);

         // jetzt User loeschen
         $sql    = "DELETE FROM ". $g_forum_praefix. "_users WHERE user_id = $forum_user_id";
         $result = mysql_query($sql, $g_forum_con);
			db_error($result);
      }

		mysql_select_db($g_adm_db, $g_adm_con);
   }

   $err_code = "delete";
}
elseif($_GET["mode"] == 4)
{
   // nur Webmaster duerfen User neue Zugangsdaten zuschicken
   // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
   if(!hasRole("Webmaster") || $g_current_organization->mail_extern == 1)
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }

   $user     = new TblUsers;
   $user->GetUser($_GET['user_id'], $g_adm_con);

	if($g_current_organization->mail_extern != 1)
	{
		// neues Passwort generieren
		$password = substr(md5(time()), 0, 8);
		$password_md5 = md5($password);

		// Passwort des Users updaten
		$sql    = "UPDATE ". TBL_USERS. " SET au_password = '$password_md5'
				      WHERE au_id = $user->id ";
		$result = mysql_query($sql, $g_adm_con);
		db_error($result);

		mail("$user->email", "Logindaten für ". $g_current_organization->homepage, "Hallo $user->first_name,\n\ndu erhälst deine ".
			"Logindaten für $g_current_organization->homepage.\n\nBenutzername: $user->login_name\nPasswort: $password\n\n" .
			"Das Passwort wurde automatisch generiert.\nDu solltest es nach dem Login in deinem Profil ändern.\n\n" .
			"Viele Grüße\nDie Webmaster", "From: webmaster@$g_domain");

		$err_code = "mail_send";
		$err_text = $user->email;
	}
}

$load_url = urlencode("$g_root_path/adm_program/administration/members/members.php");
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text&timer=2000&url=$load_url";
header($location);
exit();
?>