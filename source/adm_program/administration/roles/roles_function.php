<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rol_id: ID der Rolle, die angezeigt werden soll
 * mode:  1 - nicht mehr ben�tigt
 *        2 - Rolle anlegen oder updaten
 *        3 - Rolle loeschen
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
require("../../system/login_valid.php");

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!isModerator())
{
   $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$err_code = "";
$err_text = "";
$rol_id = $_GET['rol_id'];

if($_GET["mode"] == 1)
{
   // nicht mehr benoetigt
}
elseif($_GET["mode"] == 2)
{
   // Rolle anlegen oder updaten

   if(strlen(trim($_POST["name"])) > 0)
   {
      if(!($_GET['rol_id'] > 0))
      {
         // Schauen, ob die Rolle bereits existiert
         $sql    = "SELECT COUNT(*) FROM ". TBL_ROLES. "
                     WHERE rol_org_shortname LIKE '$g_organization'
                       AND rol_name     LIKE {0}";
         $sql    = prepareSQL($sql, array($_POST['funktion']));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
         $row = mysql_fetch_array($result);
      
         if($row[0] > 0)
         {
            $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=roleexist";
            header($location);
            exit();
         }      
      }
      
      // Zeitraum von/bis auf Gueltigkeit pruefen

      if(strlen($_POST['datum_von']) > 0)
      {
         if(dtCheckDate($_POST['datum_von']))
         {
            $d_datum_von = dtFormatDate($_POST['datum_von'], "Y-m-d");

            if(strlen($_POST['datum_bis']) > 0)
            {
               if(dtCheckDate($_POST['datum_bis']))
                  $d_datum_bis = dtFormatDate($_POST['datum_bis'], "Y-m-d");
               else
               {
                  $err_code = "datum";
                  $err_text = "Zeitraum bis";
               }
            }
            else
            {
               $err_code = "feld";
               $err_text = "Zeitraum bis";
            }
         }
         else
         {
            $err_code = "datum";
            $err_text = "Zeitraum von";
         }
      }

      // Uhrzeit von/bis auf Gueltigkeit pruefen
      
      if(strlen($err_code) == 0)
      {
         if(strlen($_POST['uhrzeit_von']) > 0)
         {
            if(dtCheckTime($_POST['uhrzeit_von']))
               $t_uhrzeit_von = dtFormatTime($_POST['uhrzeit_von'], "H:i:s");
            else
               $err_code = "uhrzeit";

            if(strlen($_POST['uhrzeit_bis']) > 0)
            {
               if(dtCheckTime($_POST['uhrzeit_bis']))
                  $t_uhrzeit_bis = dtFormatTime($_POST['uhrzeit_bis'], "H:i:s");
               else
                  $err_code = "uhrzeit";
            }
            else
            {
               $err_code = "feld";
               $err_text = "Uhrzeit bis";
            }
         }
      }

      if(strlen($err_code) == 0)
      {
         if(strcmp($_POST["name"], "Webmaster") == 0)
            $moderation = 1;
         else
         {
            if(array_key_exists("moderation", $_POST))
               $moderation = 1;
            else
               $moderation = 0;
         }

         if(array_key_exists("announcements", $_POST))
            $announcements = 1;
         else
            $announcements = 0;

         if(array_key_exists("dates", $_POST))
            $termine = 1;
         else
            $termine = 0;

         if(array_key_exists("photo", $_POST))
            $foto = 1;
         else
            $foto = 0;

         if(array_key_exists("download", $_POST))
            $download = 1;
         else
            $download = 0;

         if(array_key_exists("guestbook", $_POST))
            $guestbook = 1;
         else
            $guestbook = 0;

         if(array_key_exists("guestbook_comments", $_POST))
            $guestbook_comments = 1;
         else
            $guestbook_comments = 0;

         if(array_key_exists("user", $_POST))
            $user = 1;
         else
            $user = 0;

         if(array_key_exists("mail_logout", $_POST))
            $mail_logout = 1;
         else
            $mail_logout = 0;

         if(array_key_exists("mail_login", $_POST))
            $mail_login = 1;
         else
            $mail_login = 0;

         if(array_key_exists("weblinks", $_POST))
            $weblinks = 1;
         else
            $weblinks = 0;

         if(array_key_exists("locked", $_POST))
            $locked = 1;
         else
            $locked = 0;

         if(!array_key_exists("max_mitglieder", $_POST)
         || strlen($_POST['max_mitglieder']) == 0)
            $_POST['max_mitglieder'] = "NULL";
         elseif($_POST['max_mitglieder'] == 0)
            $_POST['max_mitglieder'] = "0";

         //Kontrollieren ob bei nachtraeglicher aenderung der amimalen Mitgleiderzahl diese nicht bereits ueberschritten wurde
         
         //Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
         if($_GET['rol_id'] > 0)
         {
             $sql    = "SELECT COUNT(*) FROM ". TBL_MEMBERS. "
                        WHERE mem_rol_id= $rol_id
                        AND mem_leader = 0
                        AND mem_valid  = 1";
             $result = mysql_query($sql, $g_adm_con);
             db_error($result);
             $role_members = mysql_fetch_array($result);
             //echo $role_members[0]."<br>".$_POST['max_mitglieder']; exit();
             
             if($_POST['max_mitglieder']!= 0 && ($role_members[0] > $_POST['max_mitglieder']))
             {
                 $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=max_members_roles_change";
                 header($location);
                 exit();
             }
         }

         if(!array_key_exists("beitrag", $_POST)
         || strlen($_POST['beitrag']) == 0)
            $_POST['beitrag'] = "NULL";
         elseif($_POST['beitrag'] == 0)
            $_POST['beitrag'] = "0";

         if($_GET['rol_id'] > 0)
         {
            $act_date = date("Y.m.d G:i:s", time());
            $sql = "UPDATE ". TBL_ROLES. "  SET rol_name    = {0}
                                        , rol_description   = {1}
                                        , rol_rlc_id        = {2}
                                        , rol_moderation    = $moderation
                                        , rol_announcements = $announcements
                                        , rol_dates         = $termine
                                        , rol_photo         = $foto
                                        , rol_download      = $download
                                        , rol_edit_user     = $user
                                        , rol_guestbook     = $guestbook
                                        , rol_guestbook_comments = $guestbook_comments
                                        , rol_mail_logout   = $mail_logout
                                        , rol_mail_login    = $mail_login
                                        , rol_weblinks      = $weblinks
                                        , rol_locked        = $locked
                                        , rol_start_date    = '$d_datum_von'
                                        , rol_start_time    = '$t_uhrzeit_von'
                                        , rol_end_date      = '$d_datum_bis'
                                        , rol_end_time      = '$t_uhrzeit_bis'
                                        , rol_weekday       = {3}
                                        , rol_location      = {4}
                                        , rol_max_members   = {5}
                                        , rol_cost          = {6}
                                        , rol_last_change   = '$act_date'
                                        , rol_usr_id_change = $g_current_user->id
                     WHERE rol_id = {7}";
         }
         else
         {
            // Rolle in Datenbank hinzufuegen
            $sql    = "INSERT INTO ". TBL_ROLES. " (rol_org_shortname, rol_name, rol_description, rol_rlc_id,
                                               rol_moderation, rol_announcements, rol_dates, rol_photo, rol_download,
                                               rol_edit_user, rol_guestbook, rol_guestbook_comments, rol_mail_logout, 
                                               rol_mail_login, rol_weblinks,  rol_locked, rol_start_date, rol_start_time,
                                               rol_end_date, rol_end_time, rol_weekday, rol_location,
                                               rol_max_members, rol_cost, rol_valid)
                       VALUES ('$g_organization', {0}, {1}, {2},
                               $moderation, $announcements, $termine, $foto, $download,
                               $user, $guestbook, $guestbook_comments, $mail_logout, 
                               $mail_login, $weblinks, $locked, '$d_datum_von', '$t_uhrzeit_von',
                               '$d_datum_bis','$t_uhrzeit_bis', {3}, {4},
                               {5}, {6}, 1) ";
         }
         $sql    = prepareSQL($sql, array(trim($_POST['name']), trim($_POST['beschreibung']), $_POST['category'], $_POST['wochentag'],
                     trim($_POST['ort']), $_POST['max_mitglieder'], $_POST['beitrag'], $_GET['rol_id']));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
      }
   }
   else
   {
      // es sind nicht alle Felder gefuellt
      $err_text = "Rolle";
      $err_code = "feld";
   }
   
   if(strlen($err_code) > 0)
   {
      $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
      header($location);
      exit();
   }

   $err_code = "save";
}
elseif($_GET["mode"] == 3)
{
   $sql = "SELECT rol_name FROM ". TBL_ROLES. "
            WHERE rol_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['rol_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   
   $row = mysql_fetch_array($result);
   
   if($row[0] == "Webmaster")
   {
      $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }

   // Rolle ungueltig machen

   $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0
                                         , mem_end   = SYSDATE()
               WHERE mem_rol_id = {0}
                 AND mem_valid  = 1 ";
   $sql    = prepareSQL($sql, array($_GET['rol_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 0
               WHERE rol_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['rol_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   
   $err_code = "delete";
}
         
$location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&timer=2000&url=$g_root_path/adm_program/administration/roles/roles.php";
header($location);
exit();
?>