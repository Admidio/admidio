<?php
/******************************************************************************
 * Zeigt eine Liste mit moeglichen Zuordnungen an
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * new_user_id: ID des Users, der angezeigt werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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

// nur Webmaster duerfen User zuordnen, ansonsten Seite verlassen
if(!hasRole("Webmaster"))
{
   $g_message->show("norights");
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

if(isset($_GET['new_user_id']) == false || is_numeric($_GET['new_user_id']) == false)
{
    $g_message->show("invalid");
}

// neuen User erst einmal als Objekt erzeugen
$new_user = new User($g_adm_con);
$new_user->getUser($_GET['new_user_id']);

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = "SELECT * " .
       "  FROM ". TBL_USERS. 
       " WHERE UPPER(usr_last_name)  LIKE UPPER('$new_user->last_name')" .
       "   AND UPPER(usr_first_name) LIKE UPPER('$new_user->first_name') " .
       "   AND usr_valid      = 1 ";
$result_usr   = mysql_query($sql, $g_adm_con);
$member_found = mysql_num_rows($result_usr);

if($member_found == 0)
{
    // kein User mit dem Namen gefunden, dann direkt neuen User erzeugen und dieses Script verlassen
    header("Location: $g_root_path/adm_program/modules/profile/profile_new.php?user_id=". $_GET['new_user_id']. "&new_user=3");
    exit();
}

$_SESSION['navigation']->addUrl($g_current_url);

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org - Version: ". ADMIDIO_VERSION. " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Neuen Benutzer zuordnen</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if lt IE 7]>
   <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
echo "
<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
    <div class=\"formHead\" style=\"width: 400px;\">Anmeldung zuordnen</div>
    <div class=\"formBody\" style=\"width: 400px;\">
        Es wurde bereits ein Benutzer unter dem Namen <b>$new_user->first_name $new_user->last_name</b> 
        in der Datenbank gefunden.<br>
        <div class=\"groupBox\" style=\"margin-top: 10px; text-align: left;\">
            <div class=\"groupBoxHeadline\">Gefundene Benutzer</div>";
            $i = 0;
            while($row = mysql_fetch_object($result_usr))
            {
                if($i > 0)
                {
                    echo "<hr width=\"85%\">";
                }
                echo "<div style=\"margin-left: 20px;\">
                    <i>$row->usr_last_name, $row->usr_first_name</i><br>
                    $row->usr_address<br>
                    $row->usr_zip_code $row->usr_city<br>";
                    if($g_preferences['enable_mail_module'] == 1)
                    {
                        echo "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?usr_id=$row->usr_id\">$row->usr_email</a><br>";
                    }
                    else
                    {
                        echo "<a href=\"mailto:$row->usr_email\">$row->usr_email</a><br>";
                    }
                    
                    if(isMember($row->usr_id) == false && strlen($row->usr_login_name) == 0)
                    {
                        // kein Mitlgied dieser Orga und auch keine Logindaten vorhanden
                        echo "<br>Dieser Benutzer ist noch kein Mitglied der Organisation $g_organization und 
                        besitzt auch keine Logindaten.<br><br>
                        <span class=\"iconLink\">
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=2\"><img
                             class=\"iconLink\" src=\"$g_root_path/adm_program/images/properties.png\" style=\"vertical-align: middle;\" border=\"0\" title=\"Rollen und Logindaten diesem Benutzer zuordnen\" alt=\"Rollen und Logindaten diesem Benutzer zuordnen\"></a>
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=2\">Mitgliedschaft und Logindaten diesem Benutzer zuordnen</a>
                        </span>";
                    }               
                    elseif(isMember($row->usr_id) == false && strlen($row->usr_login_name) > 0)
                    {
                        // kein Mitlgied dieser Orga und Logindaten sind bereits vorhanden
                        echo "<br>Dieser Benutzer ist noch kein Mitglied der Organisation $g_organization, besitzt aber bereits Logindaten.<br><br>
                        <span class=\"iconLink\">
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=2\"><img
                             class=\"iconLink\" src=\"$g_root_path/adm_program/images/properties.png\" style=\"vertical-align: middle;\" border=\"0\" title=\"Mitgliedschaft zuweisen\" alt=\"Mitgliedschaft zuweisen\"></a>
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=2\">Mitgliedschaft zuweisen</a>
                        </span>";
                    }               
                    else
                    {
                        if(isMember($row->usr_id) == true)
                        {
                            // der Benutzer ist bereits Mitglied dieser Orga, also nur Logindaten neu zuschicken                    
                            echo "<br>Dieser Benutzer besitzt schon ein g&uuml;ltiges Login. 
                                M&ouml;chtest du ihm seinen Loginnamen mit Passwort als Erinnerung zuschicken ?<br>
                            <div style=\"margin-top: 5px;\">
                                <span class=\"iconLink\">
                                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=6\"><img
                                     class=\"iconLink\" src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle;\" border=\"0\" title=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\" alt=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"></a>
                                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;user_id=$row->usr_id&amp;mode=6\">Zugangsdaten zuschicken</a>
                                </span>
                            </div>";
                        }
                    }
                echo "</div>";
                $i++;
            }
        echo "</div>
        <div class=\"groupBox\" style=\"margin-top: 10px; text-align: left;\">
            <div class=\"groupBoxHeadline\">Neuen Benutzer anlegen</div>
            <div style=\"margin-left: 20px;\">
                Falls der neue Benutzer nicht bei den oben aufgelisteten Benutzern dabei ist, 
                kannst du auch einen neuen Benutzer anlegen.<br>
                <div style=\"margin-top: 5px;\">
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=". $_GET['new_user_id']. "&new_user=3\"><img
                         class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" title=\"Neuen Benutzer anlegen\" alt=\"Neuen Benutzer anlegen\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=". $_GET['new_user_id']. "&new_user=3\">Benutzer anlegen</a>
                    </span>
                </div>
            </div>
        </div>
        <div style=\"margin-top: 20px;\">
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\"><img
                 class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
            </span>
        </div>
    </div>
</div>";

require("../../../adm_config/body_bottom.php");
echo "</body></html>";
?>