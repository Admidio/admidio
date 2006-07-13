<?php
/******************************************************************************
 * Rollen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rol_id: ID der Rolle, die bearbeitet werden soll
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

// nur Moderatoren duerfen Rollen anlegen und verwalten
if(!isModerator())
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

$rolle          = "";
$beschreibung   = "";
$rlc_id         = 0;
$r_moderation   = 0;
$r_announcements= 0;
$r_dates        = 0;
$r_download     = 0;
$r_guestbook    = 0;
$r_guestbook_comments = 0;
$r_mail_logout  = 0;
$r_mail_login   = 0;
$r_photo        = 0;
$r_weblinks     = 0;
$r_user         = 0;
$r_locked       = 0;
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

if ($_GET['rol_id'] != 0)
{
    $sql    = "SELECT * FROM ". TBL_ROLES. " WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['rol_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) > 0)
    {
        $row_ar = mysql_fetch_object($result);

        // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
        if($row_ar->rol_name == "Webmaster" && !hasRole("Webmaster"))
        {
            if($g_current_user->id != $row_ar->rol_usr_id)
            {
                $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
                header($location);
                exit();
            }
        }

        $rolle           = $row_ar->rol_name;
        $beschreibung    = $row_ar->rol_description;
        $act_rlc_id      = $row_ar->rol_rlc_id;
        $r_moderation    = $row_ar->rol_moderation;
        $r_announcements = $row_ar->rol_announcements;
        $r_dates         = $row_ar->rol_dates;
        $r_download      = $row_ar->rol_download;
        $r_guestbook     = $row_ar->rol_guestbook;
        $r_guestbook_comments = $row_ar->rol_guestbook_comments;        
        $r_mail_logout   = $row_ar->rol_mail_logout;
        $r_mail_login    = $row_ar->rol_mail_login;
        $r_photo         = $row_ar->rol_photo;
        $r_weblinks      = $row_ar->rol_weblinks;
        $r_user          = $row_ar->rol_edit_user;
        $r_locked        = $row_ar->rol_locked;

        $datum_von      = mysqldate("d.m.y", $row_ar->rol_start_date);
        $uhrzeit_von    = mysqltime("h:i",   $row_ar->rol_start_time);
        $datum_bis      = mysqldate("d.m.y", $row_ar->rol_end_date);
        $uhrzeit_bis    = mysqltime("h:i",   $row_ar->rol_end_time);
        if ($uhrzeit_von == "00:00") 
        {
            $uhrzeit_von = "";
        }
        if ($uhrzeit_bis == "00:00") 
        {
            $uhrzeit_bis = "";
        }
        $wochentag      = $row_ar->rol_weekday;
        $ort            = $row_ar->rol_location;
        $max_mitglieder = $row_ar->rol_max_members;
        $beitrag        = $row_ar->rol_cost;
    }
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Rolle</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

   <form action=\"roles_function.php?rol_id=". $_GET['rol_id']. "&amp;mode=2\" method=\"post\" name=\"TerminAnlegen\">
      <div class=\"formHead\">";
         if($_GET['rol_id'] > 0)
            echo strspace("Rolle ändern", 2);
         else
            echo strspace("Rolle anlegen", 2);
      echo "</div>
      <div class=\"formBody\">
         <div>
            <div style=\"text-align: right; width: 28%; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input type=\"text\" id=\"name\" name=\"name\" ";
               // bei bestimmte Rollen darf der Name nicht geaendert werden
               if(strcmp($rolle, "Webmaster") == 0)
                     echo " class=\"readonly\" readonly ";

               echo " style=\"width: 330px;\" maxlength=\"50\" value=\"". htmlspecialchars($rolle, ENT_QUOTES). "\">
            </div>
         </div>
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:</div>
            <div style=\"text-align: left; margin-left: 30%;\">
               <input type=\"text\" name=\"beschreibung\" style=\"width: 330px;\" maxlength=\"255\" value=\"". htmlspecialchars($beschreibung, ENT_QUOTES). "\">
            </div>
         </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 28%; float: left;\">Kategorie:</div>
                <div style=\"text-align: left; margin-left: 30%;\">
                    <select size=\"1\" name=\"category\">";
                        $sql = "SELECT * FROM ". TBL_ROLE_CATEGORIES. "
                                    WHERE rlc_org_shortname LIKE '$g_organization' 
                                   ORDER BY rlc_name ASC ";
                        $result = mysql_query($sql, $g_adm_con);
                        db_error($result);
                        
                        while($row = mysql_fetch_object($result))
                        {
                            echo "<option value=\"$row->rlc_id\"";
                                if($act_rlc_id == $row->rlc_id
                                || ($act_rlc_id == 0 && $row->rlc_name == 'Allgemein'))
                                    echo " selected ";
                            echo ">$row->rlc_name</option>";
                        }
                    echo "</select>
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 28%; float: left;\">
                    <label for=\"locked\"><img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur für Moderatoren sichtbar\"></label>
                </div>
                <div style=\"text-align: left; margin-left: 30%;\">
                    <input type=\"checkbox\" id=\"locked\" name=\"locked\" ";
                        if($r_locked == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    <label for=\"locked\">Rolle nur für Moderatoren sichtbar&nbsp;</label>
                    <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_locked','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                </div>
            </div>

            <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
                <div class=\"groupBoxHeadline\">Berechtigungen</div>

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"moderation\" name=\"moderation\" ";
                        if($r_moderation == 1)
                            echo " checked ";
                        if(strcmp($rolle, "Webmaster") == 0)
                            echo " disabled ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"moderation\"><img src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"moderation\">Moderation (Benutzer &amp; Rollen verwalten uvm.)&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_moderation','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"user\" name=\"user\" ";
                        if($r_user == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"user\"><img src=\"$g_root_path/adm_program/images/user.png\" alt=\"Daten aller Benutzer bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"user\">Daten aller Benutzer bearbeiten&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_benutzer','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"announcements\" name=\"announcements\" ";
                        if($r_announcements == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"announcements\"><img src=\"$g_root_path/adm_program/images/note.png\" alt=\"Ank&uuml;ndigungen anlegen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"announcements\">Ank&uuml;ndigungen anlegen und bearbeiten&nbsp;</label>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"dates\" name=\"dates\" ";
                        if($r_dates == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"dates\"><img src=\"$g_root_path/adm_program/images/date.png\" alt=\"Termine anlegen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"dates\">Termine anlegen und bearbeiten&nbsp;</label>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"photo\" name=\"photo\" ";
                        if($r_photo == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"photo\"><img src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Fotos hochladen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"photo\">Fotos hochladen und bearbeiten&nbsp;</label>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"download\" name=\"download\" ";
                        if($r_download == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"download\"><img src=\"$g_root_path/adm_program/images/folder_down.png\" alt=\"Downloads hochladen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"download\">Downloads hochladen und bearbeiten&nbsp;</label>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"guestbook\" name=\"guestbook\" ";
                        if($r_guestbook == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"guestbook\"><img src=\"$g_root_path/adm_program/images/comment.png\" alt=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"guestbook\">G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen&nbsp;</label>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"guestbook_comments\" name=\"guestbook_comments\" ";
                        if($r_guestbook_comments == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"guestbook_comments\"><img src=\"$g_root_path/adm_program/images/comments.png\" alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"guestbook_comments\">Kommentare zu G&auml;stebucheintr&auml;gen anlegen&nbsp;</label>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"mail_logout\" name=\"mail_logout\" ";
                        if($r_mail_logout == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"mail_logout\"><img src=\"$g_root_path/adm_program/images/mail.png\" alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"mail_logout\">Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_logout','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"mail_login\" name=\"mail_login\" ";
                        if($r_mail_login == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"mail_login\"><img src=\"$g_root_path/adm_program/images/mail_key.png\" alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"mail_login\">Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_login','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left;\">
                        <input type=\"checkbox\" id=\"weblinks\" name=\"weblinks\" ";
                        if($r_weblinks == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"weblinks\"><img src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Weblinks anlegen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 12%;\">
                        <label for=\"weblinks\">Weblinks anlegen und bearbeiten&nbsp;</label>
                    </div>
                </div>                
            </div>

            <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
                <div class=\"groupBoxHeadline\">Eigenschaften&nbsp;&nbsp;(optional)</div>

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 33%; float: left;\">Anzahl Mitglieder:</div>
                    <div style=\"text-align: left; margin-left: 35%;\">
                        <input type=\"text\" name=\"max_mitglieder\" size=\"3\" maxlength=\"3\" value=\""; if($max_mitglieder > 0) echo $max_mitglieder; echo "\">&nbsp;(inkl. Leiter)</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 33%; float: left;\">G&uuml;ltig von:</div>
                    <div style=\"text-align: left; margin-left: 35%;\">
                        <input type=\"text\" name=\"datum_von\" size=\"10\" maxlength=\"10\" value=\"$datum_von\">
                        bis
                        <input type=\"text\" name=\"datum_bis\" size=\"10\" maxlength=\"10\" value=\"$datum_bis\">&nbsp;(Datum)
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 33%; float: left;\">Uhrzeit:</div>
                    <div style=\"text-align: left; margin-left: 35%;\">
                        <input type=\"text\" name=\"uhrzeit_von\" size=\"5\" maxlength=\"5\" value=\"$uhrzeit_von\">
                        bis
                        <input type=\"text\" name=\"uhrzeit_bis\" size=\"5\" maxlength=\"5\" value=\"$uhrzeit_bis\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 33%; float: left;\">Wochentag:</div>
                    <div style=\"text-align: left; margin-left: 35%;\">
                        <select size=\"1\" name=\"wochentag\">
                        <option value=\"0\""; if($wochentag == 0) echo " selected=\"selected\""; echo ">&nbsp;</option>\n";
                        for($i = 1; $i < 8; $i++)
                        {
                            echo "<option value=\"$i\""; if($wochentag == $i) echo " selected=\"selected\""; echo ">". $arrDay[$i-1]. "</option>\n";
                        }
                        echo "</select>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 33%; float: left;\">Ort:</div>
                    <div style=\"text-align: left; margin-left: 35%;\">
                        <input type=\"text\" name=\"ort\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($ort, ENT_QUOTES). "\"></div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 33%; float: left;\">Beitrag:</div>
                    <div style=\"text-align: left; margin-left: 35%;\">
                        <input type=\"text\" name=\"beitrag\" size=\"6\" maxlength=\"6\" value=\"$beitrag\"> &euro;</div>
                </div>
            </div>

         <div style=\"margin-top: 15px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;            
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
            &nbsp;Speichern</button>
         </div>";
         if($row_ar->rol_usr_id_change > 0)
         {
            // Angabe ueber die letzten Aenderungen
            $sql    = "SELECT usr_first_name, usr_last_name
                         FROM ". TBL_USERS. "
                        WHERE usr_id = $row_ar->rol_usr_id_change ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $row = mysql_fetch_array($result);

            echo "<div style=\"margin-top: 6px;\">
               <span style=\"font-size: 10pt\">
               Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $row_ar->rol_last_change).
               " durch $row[0] $row[1]
               </span>
            </div>";
         }
      echo "</div>
    </form>

    </div>
    <script type=\"text/javascript\"><!--\n
        document.getElementById('name').focus();
    \n--></script>";    

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>