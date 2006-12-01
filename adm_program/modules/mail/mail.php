<?php
/******************************************************************************
 * E-Mails verschicken
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * usr_id  - E-Mail an den entsprechenden Benutzer schreiben
 * rolle   - E-Mail an alle Mitglieder der Rolle schreiben
 * cat     - In Kombination mit dem Rollennamen muss auch der Kategoriename uebergeben werden
 * rol_id  - Statt einem Rollennamen/Kategorienamen kann auch eine RollenId uebergeben werden
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

// Pruefungen, ob die Seite regulaer aufgerufen wurde
if ($g_preferences['send_email_extern'] == 1)
{
    // es duerfen oder koennen keine Mails ueber den Server verschickt werden
    $g_message->show("mail_extern");
}


if ($g_session_valid && !isValidEmailAddress($g_current_user->email))
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $g_message->show("profile_mail", "$g_root_path/adm_program/modules/profile/profile.php");
}


//Falls ein Rollenname uebergeben wurde muss auch der Kategoriename uebergeben werden und umgekehrt...
if ( (isset($_GET["rolle"]) && !isset($_GET["cat"])) || (!isset($_GET["rolle"]) && isset($_GET["cat"])) )
{
    $g_message->show("invalid");
}


if (isset($_GET["usr_id"]))
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$g_session_valid)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $g_message->show("invalid");
    }

    if (is_numeric($_GET["usr_id"]) == false)
    {
        $g_message->show("invalid");
    }

    if (!editUser())
    {
        $sql    = "SELECT DISTINCT usr_id, usr_email
                     FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
                    WHERE mem_usr_id = usr_id
                      AND mem_rol_id = rol_id
                      AND rol_org_shortname = '$g_current_organization->shortname'
                      AND usr_id  = {0} ";
    }
    else
    {
        $sql    = "SELECT usr_id, usr_email
                     FROM ". TBL_USERS. "
                    WHERE usr_id  = {0} ";
    }
    $sql    = prepareSQL($sql, array($_GET['usr_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_object($result);

    if (mysql_num_rows($result) != 1)
    {
        $g_message->show("usrid_not_found");
    }

    if (!isValidEmailAddress($row->usr_email))
    {
        $g_message->show("usrmail_not_found");
    }

    $userEmail = $row->usr_email;
}
elseif (isset($_GET["rol_id"]))
{
    // Falls eine rol_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf
    if (is_numeric($_GET["rol_id"]) == false)
    {
        $g_message->show("invalid");
    }

    if ($g_session_valid)
    {
        $sql    = "SELECT rol_mail_login, rol_name FROM ". TBL_ROLES. "
                   WHERE rol_org_shortname    = '$g_organization'
                   AND rol_id = {0} ";
    }
    else
    {
        $sql    = "SELECT rol_mail_logout, rol_name FROM ". TBL_ROLES. "
                   WHERE rol_org_shortname    = '$g_organization'
                   AND rol_id = {0} ";
    }
    $sql    = prepareSQL($sql, array($_GET['rol_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);

    if ($row[0] != 1)
    {
        $g_message->show("invalid");
    }

    $rollenName = $row[1];
    $rollenID   = $_GET['rol_id'];
}
elseif (isset($_GET["rolle"]) && isset($_GET["cat"]))
{
    // Falls eine rolle und eine category uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf
    $_GET["rolle"] = strStripTags($_GET["rolle"]);
    $_GET["cat"]   = strStripTags($_GET["cat"]);

    if ($g_session_valid)
    {
        $sql    = "SELECT rol_mail_login, rol_id
                    FROM ". TBL_ROLES. " ,". TBL_CATEGORIES. "
                   WHERE rol_org_shortname    = '$g_organization'
                   AND UPPER(rol_name) = UPPER({0})
                   AND rol_cat_id      = cat_id
                   AND UPPER(cat_name) = UPPER({1})";
    }
    else
    {
        $sql    = "SELECT rol_mail_logout, rol_id
                    FROM ". TBL_ROLES. " ,". TBL_CATEGORIES. "
                   WHERE rol_org_shortname    = '$g_organization'
                   AND UPPER(rol_name) = UPPER({0})
                   AND rol_cat_id      = cat_id
                   AND UPPER(cat_name) = UPPER({1})";
    }
    $sql    = prepareSQL($sql, array($_GET['rolle'],$_GET['cat']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);

    if ($row[0] != 1)
    {
        $g_message->show("invalid");
    }

    $rollenName = $_GET['rolle'];
    $rollenID   = $row[1];
}

if (array_key_exists("subject", $_GET))
{
    $_GET["subject"] = strStripTags($_GET["subject"]);
}
else
{
    $_GET["subject"] = "";
}

if (array_key_exists("body", $_GET))
{
    $_GET["body"] = strStripTags($_GET["body"]);
}
else
{
    $_GET["body"]  = "";
}

if (!array_key_exists("kopie", $_GET) || !is_numeric($_GET["kopie"]))
{
    $_GET["kopie"] = "1";
}

// Wenn die letzte URL in der Zuruecknavigation die des Scriptes mail_send.php ist,
// dann soll das Formular gefuellt werden mit den Werten aus der Session
if (strpos($_SESSION['navigation']->getUrl(),'mail_send.php') > 0 && isset($_SESSION['mail_request']))
{
    // Das Formular wurde also schon einmal ausgefüllt,
    // da der User hier wieder gelandet ist nach der Mailversand-Seite
    $form_values = $_SESSION['mail_request'];
    unset($_SESSION['mail_request']);

    $_SESSION['navigation']->deleteLastUrl();
}
else
{
    $form_values['name']         = "";
    $form_values['mailfrom']     = "";
    $form_values['subject']      = "";
    $form_values['body']         = "";
    $form_values['rol_id']       = "";
}



// Seiten fuer Zuruecknavigation merken
if(isset($_GET["usr_id"]) == false && isset($_GET["rol_id"]) == false)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl($g_current_url);

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - E-Mail verschicken</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

    <form action=\"mail_send.php?";
      // usr_id wird mit GET uebergeben,
      // da keine E-Mail-Adresse von mail_send angenommen werden soll
      if (array_key_exists("usr_id", $_GET))
      {
          echo "usr_id=". $_GET['usr_id']. "&";
      }
      echo "\" method=\"post\" name=\"Mail\" enctype=\"multipart/form-data\">

      <div class=\"formHead\">";
      if ($_GET["subject"] == "")
      {
          echo strspace("E-Mail verschicken");
      }
      else
      {
          echo strspace($_GET["subject"]);
      }
      echo "</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 25%; float: left;\">an:</div>
            <div style=\"text-align: left; margin-left: 27%;\">";
               if (array_key_exists("usr_id", $_GET))
               {
                   // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
                   echo "<input class=\"readonly\" readonly type=\"text\" name=\"mailto\" style=\"width: 350px;\" maxlength=\"50\" value=\"$userEmail\">";
               }
               elseif ( array_key_exists("rol_id", $_GET) || (array_key_exists("rolle", $_GET) && array_key_exists("cat", $_GET)) )
               {
                   // Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
                   echo "
                    <select size=\"1\" id=\"rol_id\" name=\"rol_id\"><option value=\"$rollenID\" selected=\"selected\">$rollenName</option></select>&nbsp;";
               }
               else
               {
                   // keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
                   echo "<select size=\"1\" id=\"rol_id\" name=\"rol_id\">";
                   if ($form_values['rol_id'] == "")
                   {
                       echo "<option value=\"\" selected=\"selected\">- Bitte w&auml;hlen -</option>";
                   }

                   if ($g_session_valid)
                   {
                       if (isModerator())
                       {
                            // im eingeloggten Zustand duerfen nur Moderatoren an gelocked Rollen schreiben
                               $sql    = "SELECT rol_name, rol_id, cat_name FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                       WHERE rol_org_shortname = '$g_organization'
                                       AND rol_mail_login = 1
                                       AND rol_valid      = 1
                                       AND rol_cat_id     = cat_id
                                       ORDER BY cat_name, rol_name ";
                       }
                       else
                       {
                            // alle nicht gelocked Rollen auflisten,
                            // an die im eingeloggten Zustand Mails versendet werden duerfen
                               $sql    = "SELECT rol_name, rol_id, cat_name FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                       WHERE rol_org_shortname = '$g_organization'
                                       AND rol_mail_login = 1
                                       AND rol_locked     = 0
                                       AND rol_valid      = 1
                                       AND rol_cat_id     = cat_id
                                       ORDER BY cat_name, rol_name ";
                       }
                   }
                   else
                   {
                        // alle Rollen auflisten,
                        // an die im nicht eingeloggten Zustand Mails versendet werden duerfen
                           $sql    = "SELECT rol_name, rol_id, cat_name FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                   WHERE rol_org_shortname  = '$g_organization'
                                   AND rol_mail_logout = 1
                                   AND rol_valid       = 1
                                   AND rol_cat_id      = cat_id
                                   ORDER BY cat_name, rol_name ";
                   }
                   $result = mysql_query($sql, $g_adm_con);
                   db_error($result);
                   $act_category = "";

                   while ($row = mysql_fetch_object($result))
                   {
                       if($act_category != $row->cat_name)
                        {
                            if(strlen($act_category) > 0)
                            {
                                echo "</optgroup>";
                            }
                            echo "<optgroup label=\"$row->cat_name\">";
                            $act_category = $row->cat_name;
                        }
                        echo "<option value=\"$row->rol_id\" ";
                        if ($row->rol_id == $form_values['rol_id'])
                        {
                            echo "selected=\"selected\"";
                        }
                        echo ">$row->rol_name</option>";
                   }

                   echo "</optgroup>
                   </select>&nbsp;
                   <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" alt=\"Hilfe\" title=\"Hilfe\"
                   onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_mail','Message','width=400,height=400,left=310,top=200')\">";
               }
            echo "</div>
         </div>

         <hr width=\"90%\" />

         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 27%;\">";
               if ($g_current_user->id != 0)
               {
                   echo "<input class=\"readonly\" readonly type=\"text\" name=\"name\" style=\"width: 200px;\" maxlength=\"50\" value=\"$g_current_user->first_name $g_current_user->last_name\">";
               }
               else
               {
                   echo "<input type=\"text\" id=\"name\" name=\"name\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['name']. "\">";
               }
            echo "</div>
         </div>
         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">E-Mail:</div>
            <div style=\"text-align: left; margin-left: 27%;\">";
               if ($g_current_user->id != 0)
               {
                   echo "<input class=\"readonly\" readonly type=\"text\" name=\"mailfrom\" style=\"width: 350px;\" maxlength=\"50\" value=\"$g_current_user->email\">";
               }
               else
               {
                   echo "<input type=\"text\" name=\"mailfrom\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $form_values['mailfrom']. "\">";
               }
            echo "</div>
         </div>

         <hr width=\"90%\" />

         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Betreff:</div>
            <div style=\"text-align: left; margin-left: 27%;\">";
               if ($_GET['subject'] == "")
               {
                   echo "<input type=\"text\" id=\"subject\" name=\"subject\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $form_values['subject']. "\">";
               }
               else
               {
                   echo "<input class=\"readonly\" readonly type=\"text\" name=\"subject\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $_GET['subject']. "\">";
               }
            echo "</div>
         </div>
         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Nachricht:</div>
            <div style=\"text-align: left; margin-left: 27%;\">";
               if ($form_values['body'] != "")
               {
                   echo "<textarea name=\"body\" style=\"width: 350px;\" rows=\"10\" cols=\"45\">". $form_values['body']. "</textarea>";
               }
               else
               {
                   echo "<textarea name=\"body\" style=\"width: 350px;\" rows=\"10\" cols=\"45\">". $_GET['body']. "</textarea>";
               }
            echo "</div>
         </div>";



         // Nur eingeloggte User duerfen Attachments mit max 3MB anhaengen...
         if (($g_session_valid) && ($g_preferences['max_email_attachment_size'] > 0) && (ini_get('file_uploads') == '1'))
         {
             echo "
             <div style=\"margin-top: 8px;\">
                 <div style=\"text-align: right; width: 25%; float: left;\">Anhang:</div>
                 <div style=\"text-align: left; margin-left: 27%;\">
                     <input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" . ($g_preferences['max_email_attachment_size'] * 1024) . "\">
                     <input name=\"userfile\" size=\"40\" type=\"file\">
                 </div>
             </div>";
         }

         echo "
         <div style=\"margin-top: 8px;\">
            <div style=\"text-align: left; margin-left: 27%;\">
               <input type=\"checkbox\" id=\"kopie\" name=\"kopie\" value=\"1\" ";
               if ($_GET['kopie'] == 1)
               {
                   echo " checked=\"checked\" ";
               }
               echo "> <label for=\"kopie\">Kopie der E-Mail an mich senden</label>
            </div>
         </div>";

         // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
         // falls es in den Orgaeinstellungen aktiviert wurde...
         if (!$g_session_valid && $g_preferences['enable_mail_captcha'] == 1)
         {
             echo "

             <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: left; margin-left: 27%;\">
                     <img src=\"$g_root_path/adm_program/system/captcha_class.php\" border=\"0\" alt=\"Captcha\" />
                 </div>
             </div>

             <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 25%; float: left;\">Best&auml;tigungscode:*</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"captcha\" name=\"captcha\" style=\"width: 200px;\" maxlength=\"8\" value=\"\">
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                             onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\">
                    </div>
             </div>";
         }

         echo "
         <hr width=\"90%\" />

         <div style=\"margin-top: 8px;\">";
             if(isset($_GET['usr_id']) || isset($_GET['rol_id']))
             {
                echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
                   <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                   &nbsp;Zur&uuml;ck</button>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
             }
            echo "<button name=\"abschicken\" type=\"submit\" value=\"abschicken\">
               <img src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Abschicken\">
               &nbsp;Abschicken</button>
         </div>
      </div>
   </form>

   </div>";

    // Focus auf das erste Eingabefeld setzen
    if (!array_key_exists("usr_id", $_GET)
     && !array_key_exists("rol_id", $_GET)
     && !array_key_exists("rolle",  $_GET))
    {
        $focus_field = "rol_id";
    }
    else if($g_current_user->id == 0)
    {
        $focus_field = "name";
    }
    else
    {
        $focus_field = "subject";
    }

    echo "<script type=\"text/javascript\"><!--
        document.getElementById('$focus_field').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>