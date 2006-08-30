<?php
/******************************************************************************
 * Profil anzeigen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id: zeigt das Profil der uebergebenen user_id an
 *          (wird keine user_id uebergeben, dann Profil des eingeloggten Users anzeigen)
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

require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
{
    $url = urlencode($_GET['url']);
}
else
{
    $url = urlencode(getHttpReferer());
}

if(!array_key_exists('user_id', $_GET)
|| $_GET['user_id'] == $g_current_user->id)
{
    // wenn nichts uebergeben wurde, dann eigene Daten anzeigen
    $a_user_id = $g_current_user->id;
    $edit_user = true;
}
else
{
    // Daten eines anderen Users anzeigen und pruefen, ob editiert werden darf
    $a_user_id = $_GET['user_id'];
    if(editUser())
    {
        // jetzt noch schauen, ob User ueberhaupt Mitglied in der Gliedgemeinschaft ist

        if(isMember($a_user_id) == true)
        {
            $edit_user = true;
        }
        else
        {
            $edit_user = false;
        }
    }
    else
    {
        $edit_user = false;
    }
}

// User auslesen
if($a_user_id > 0)
{
    $user = new User($g_adm_con);
    $user->GetUser($a_user_id);
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Profil</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

        <div class=\"formHead\">";
            if($a_user_id == $g_current_user->id)
            {
                echo strspace("Mein Profil", 2);
            }
            else
            {
                echo strspace("Profil von ". $user->first_name. " ". $user->last_name, 1);
            }
        echo "</div>

        <div class=\"formBody\">";

            // *******************************************************************************
            // Userdaten-Block
            // *******************************************************************************

            echo "<div style=\"width: 66%; margin-right: 10px; float: left;\">
                <div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
                    <div class=\"groupBoxHeadline\">$user->first_name $user->last_name&nbsp;&nbsp;";
                        if($user->gender == 1)
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/male.png\" title=\"m&auml;nnlich\" alt=\"m&auml;nnlich\">";
                        }
                        elseif($user->gender == 2)
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/female.png\" title=\"weiblich\" alt=\"weiblich\">";
                        }
                    echo "</div>

                    <div style=\"float: left; width: 30%; text-align: left\">Adresse:";
                        if(strlen($user->zip_code) > 0 || strlen($user->city) > 0)
                            echo "<br />&nbsp;";
                        if(strlen($user->country) > 0)
                            echo "<br />&nbsp;";
                        if(strlen($user->address) > 0
                        && (  strlen($user->zip_code)  > 0
                        || strlen($user->city)  > 0 ))
                            echo "<br /><span style=\"font-size: 8pt;\">&nbsp;</span>";
                    echo "</div>

                    <div style=\"margin-left: 30%; text-align: left\">";
                        if(strlen($user->address) == 0 && strlen($user->zip_code) == 0 && strlen($user->city) == 0)
                            echo "<i>keine Daten vorhanden</i>";
                        if(strlen($user->address) > 0)
                            echo $user->address;
                        if(strlen($user->zip_code) > 0 || strlen($user->city) > 0)
                        {
                            echo "<br />";
                            if(strlen($user->zip_code) > 0)
                                echo $user->zip_code. " ";
                            if(strlen($user->city) > 0)
                                echo $user->city;
                        }
                        if(strlen($user->country) > 0)
                            echo "<br />". $user->country;

                        if(strlen($user->address) > 0
                        && (  strlen($user->zip_code)  > 0
                        || strlen($user->city)  > 0 ))
                        {
                            // Button mit Karte anzeigen
                            $map_url = "http://maps.google.com/?q=". urlencode($user->address);
                            if(strlen($user->zip_code)  > 0)
                            {
                                $map_url .= ",%20$user->zip_code";
                            }
                            if(strlen($user->city)  > 0)
                            {
                                $map_url .= ",%20$user->city";
                            }
                            if(strlen($user->country)  > 0)
                            {
                                $map_url .= ",%20$user->country";
                            }

                            echo "<br />
                            <span style=\"font-size: 8pt;\">( <a href=\"$map_url\" target=\"_blank\">Stadtplan</a>";

                            if($g_current_user->id != $a_user_id)
                            {
                                $own_user = new User($g_adm_con);
                                $own_user->GetUser($g_current_user->id);

                                if(strlen($own_user->address) > 0
                                && (  strlen($own_user->zip_code)  > 0
                                || strlen($own_user->city)  > 0 ))
                                {
                                    // Link fuer die Routenplanung
                                    $route_url = "http://maps.google.com/?f=d&saddr=". urlencode($own_user->address);
                                    if(strlen($own_user->zip_code)  > 0)
                                    {
                                        $route_url .= ",%20$own_user->zip_code";
                                    }
                                    if(strlen($own_user->city)  > 0)
                                    {
                                        $route_url .= ",%20$own_user->city";
                                    }
                                    if(strlen($own_user->country)  > 0)
                                    {
                                        $route_url .= ",%20$own_user->country";
                                    }
                                    
                                    $route_url .= "&daddr=". urlencode($user->address);
                                    if(strlen($user->zip_code)  > 0)
                                    {
                                        $route_url .= ",%20$user->zip_code";
                                    }
                                    if(strlen($user->city)  > 0)
                                    {
                                        $route_url .= ",%20$user->city";
                                    }
                                    if(strlen($user->country)  > 0)
                                    {
                                        $route_url .= ",%20$user->country";
                                    }
                                    echo " - <a href=\"$route_url\" target=\"_blank\">Route berechnen</a>";
                                }
                            }
                            echo " )</span>";
                        }
                    echo "</div>

                    <div style=\"float: left; margin-top: 10px; width: 30%; text-align: left\">Telefon:</div>
                    <div style=\"margin-top: 10px; margin-left: 30%; text-align: left\">$user->phone&nbsp;</div>";

                    echo "<div style=\"float: left; width: 30%; text-align: left\">Handy:</div>
                    <div style=\"margin-left: 30%; text-align: left\">$user->mobile&nbsp;</div>";

                    echo "<div style=\"float: left; width: 30%; text-align: left\">Fax:</div>
                    <div style=\"margin-left: 30%; text-align: left\">$user->fax&nbsp;</div>";

                    // Block Geburtstag, Geschlecht und Benutzer

                    echo "<div style=\"float: left; margin-top: 10px; width: 30%; text-align: left\">Geburtstag:</div>
                    <div style=\"margin-top: 10px; margin-left: 30%; text-align: left\">";
                        if(strlen($user->birthday) > 0 && strcmp($user->birthday, "0000-00-00") != 0)
                        {
                            echo mysqldatetime('d.m.y', $user->birthday);
                            // Alter berechnen
                            $act_date = getDate(time());
                            $geb_date = getDate(mysqlmaketimestamp($user->birthday));
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
                    <div style=\"float: left; width: 30%; text-align: left\">Benutzer:</div>
                    <div style=\"margin-left: 30%; text-align: left\">$user->login_name&nbsp;</div>";

                    // Block E-Mail und Homepage

                    echo "<div style=\"float: left; margin-top: 10px; width: 30%; text-align: left\">E-Mail:</div>
                    <div style=\"margin-top: 10px; margin-left: 30%; text-align: left\">";
                        if(strlen($user->email) > 0)
                        {
                            if($g_preferences['send_email_extern'] == 1)
                                $mail_link = "mailto:$user->email";
                            else
                                $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?usr_id=$user->id";
                            echo "<a href=\"$mail_link\">
                            <img src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle;\" alt=\"E-Mail an $user->email schreiben\"
                            title=\"E-Mail an $user->email schreiben\" border=\"0\"></a>
                            <a href=\"$mail_link\" style=\" overflow: visible; display: inline;\">";
                            if(strlen($user->email) > 25)
                            {
                                echo "<span style=\"font-size: 8pt;\">$user->email</span>";
                            }
                            else
                            {
                                echo "$user->email";
                            }
                            echo "</a>";
                        }
                        else
                            echo "&nbsp;";
                    echo "</div>
                    <div style=\"float: left; width: 30%; text-align: left\">Homepage:</div>
                    <div style=\"margin-left: 30%; text-align: left\">";
                        if(strlen($user->homepage) > 0)
                        {
                            $user->homepage = stripslashes($user->homepage);
                            $user->homepage = str_replace ("http://", "", $user->homepage);
                            echo "
                            <a href=\"http://$user->homepage\" target=\"_blank\">
                            <img src=\"$g_root_path/adm_program/images/globe.png\" style=\"vertical-align: middle;\" alt=\"Gehe zu $user->homepage\"
                            title=\"Gehe zu $user->homepage\" border=\"0\"></a>
                            <a href=\"http://$user->homepage\" target=\"_blank\">";
                            if(strlen($user->homepage) > 25)
                            {
                                echo "<span style=\"font-size: 8pt;\">$user->homepage</span>";
                            }
                            else
                            {
                                echo "$user->homepage";
                            }
                            echo "</a>";
                        }
                        else
                            echo "&nbsp;";
                    echo "</div>
                </div>
            </div>";

            echo "<div style=\"width: 32%; float: left\">";

                // *******************************************************************************
                // Bild-Block
                // *******************************************************************************
                
                //Nachsehen ob fuer den User ein Photo gespeichert wurde
                $sql =" SELECT usr_photo
                        FROM ".TBL_USERS."
                        WHERE usr_id = '$a_user_id'";
                $result_photo = mysql_query($sql, $g_adm_con);
                db_error($result_photo);
                
                echo"
                <div style=\"margin-top: 4px; text-align: center;\">
                    <div class=\"groupBox\">";
                    
                        //Falls vorhanden Bild ausgeben
                        if(mysql_result($result_photo,0,"usr_photo")!=NULL)
                        {
                            echo"<img src=\"profile_photo_show.php?usr_id=$a_user_id\">";
                        }
                        //wenn nicht Schattenkopf
                        else
                        {
                            echo"<img src=\"$g_root_path/adm_program/images/no_profile_pic.png\">";
                        }
                    echo"</div>";
                    echo"
                    <div style=\"margin-top: 12px;\">
                        <span class=\"iconLink\">
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id=$user->id\"><img
                             class=\"iconLink\" src=\"$g_root_path/adm_program/images/vcard.png\" style=\"vertical-align: middle;\" border=\"0\" title=\"Benutzer als vCard exportieren\" alt=\"Benutzer als vCard exportieren\"></a>
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id=$user->id\">vCard exportieren</a>
                        </span>
                    </div>";

                    // Moderatoren & Gruppenleiter duerfen neue Rollen zuordnen
                    if(isModerator() || isGroupLeader() || editUser())
                    {
                        echo "<div style=\"margin-top: 10px;\">
                            <span class=\"iconLink\">
                                <a class=\"iconLink\" href=\"roles.php?user_id=$a_user_id&amp;url=". urlencode("$g_root_path/adm_program/modules/profile/profile.php?user_id=$a_user_id&url=$url"). "\"><img
                                 class=\"iconLink\" src=\"$g_root_path/adm_program/images/wand.png\" style=\"vertical-align: middle;\" border=\"0\" title=\"Rollen &auml;ndern\" alt=\"Rollen &auml;ndern\"></a>
                                <a class=\"iconLink\" href=\"roles.php?user_id=$a_user_id&amp;url=". urlencode("$g_root_path/adm_program/modules/profile/profile.php?user_id=$a_user_id&url=$url"). "\">Rollen &auml;ndern</a>
                            </span>
                        </div>";
                    }
                echo "</div>
            </div>

            <div style=\"clear: left;\"><br /></div>

            <div style=\"width: 66%; margin-right: 10px; float: left;\">";

                // *******************************************************************************
                // zusaetzliche Daten-Block
                // *******************************************************************************

                // alle zugeordneten Messengerdaten einlesen
                $sql = "SELECT usf_name, usf_description, usd_value
                          FROM ". TBL_USER_DATA. ", ". TBL_USER_FIELDS. "
                         WHERE usd_usr_id        = $user->id
                           AND usd_usf_id       = usf_id
                           AND usf_org_shortname IS NULL
                           AND usf_type         = 'MESSENGER'
                         ORDER BY usf_name ASC ";
                $result_msg = mysql_query($sql, $g_adm_con);
                db_error($result_msg, true);
                $count_msg = mysql_num_rows($result_msg);

                // alle gruppierungsspezifischen Felder auslesen
                $sql = "SELECT *
                          FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                            ON usd_usf_id = usf_id
                           AND usd_usr_id        = $user->id
                         WHERE usf_org_shortname = '$g_organization' ";
                if(!isModerator())
                {
                    $sql = $sql. " AND usf_locked = 0 ";
                }
                $sql = $sql. " ORDER BY usf_name ASC ";
                $result_field = mysql_query($sql, $g_adm_con);
                db_error($result_field, true);
                $count_field = mysql_num_rows($result_field);

                // wenn Daten vorhanden, dann diese anzeigen
                if($count_field > 0 || $count_msg > 0)
                {
                    echo "<div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
                        <div class=\"groupBoxHeadline\">Zus&auml;tzliche Daten</div>";

                        if($count_msg > 0)
                        {
                            // Messengerdaten anzeigen
                            mysql_data_seek($result_msg, 0);
                            $i = 1;

                            while($row = mysql_fetch_object($result_msg))
                            {
                                echo "<div style=\"float: left; width: 30%; text-align: left\">";
                                if($i == 1)
                                    echo "Messenger:";
                                else
                                    echo "&nbsp;";
                                echo "</div>
                                <div style=\"margin-left: 30%; text-align: left\">";

                                if($row->usf_name == 'ICQ')
                                {
                                    // Sonderzeichen aus der ICQ-Nummer entfernen (damit kommt www.icq.com nicht zurecht)
                                    preg_match_all("/\d+/", $row->usd_value, $matches);
                                    $icq_number = implode("", reset($matches));
                                    
                                    // ICQ Onlinestatus anzeigen
                                    echo "<a href=\"http://www.icq.com/whitepages/cmd.php?uin=$icq_number&amp;action=add\"  class=\"wpaction\">
                                    <img border=\"0\" src=\"http://status.icq.com/online.gif?icq=$icq_number&img=5\"
                                    style=\"vertical-align: middle;\" alt=\"$row->usd_value zu $row->usf_description hinzuf&uuml;gen\" title=\"$row->usd_value zu $row->usf_description hinzuf&uuml;gen\" /></a>&nbsp;";
                                }
                                elseif($row->usf_name == 'Skype')
                                {
                                    // Skype Onlinestatus anzeigen
                                    echo "<script type=\"text/javascript\" src=\"http://download.skype.com/share/skypebuttons/js/skypeCheck.js\"></script>
                                    <a href=\"skype:$row->usd_value?add\"><img src=\"http://mystatus.skype.com/smallicon/$row->usd_value\"
                                    style=\"border: none; vertical-align: middle;\" width=\"16\" height=\"16\" title=\"$row->usd_value zu $row->usf_description hinzuf&uuml;gen\" alt=\"$row->usd_value zu $row->usf_description hinzuf&uuml;gen\" /></a>&nbsp;&nbsp;";
                                }
                                else
                                {
                                    echo "<img src=\"$g_root_path/adm_program/images/";
                                    if($row->usf_name == 'AIM')
                                    {
                                        echo "aim.png";
                                    }
                                    elseif($row->usf_name == 'Google Talk')
                                    {
                                        echo "google.gif";
                                    }
                                    elseif($row->usf_name == 'MSN')
                                    {
                                        echo "msn.png";
                                    }
                                    elseif($row->usf_name == 'Yahoo')
                                    {
                                        echo "yahoo.png";
                                    }
                                    echo "\" style=\"vertical-align: middle;\" alt=\"$row->usf_description\" title=\"$row->usf_description\" />&nbsp;&nbsp;";
                                };
                                if(strlen($row->usd_value) > 25)
                                {
                                    echo "<span style=\"font-size: 8pt;\">$row->usd_value</span>";
                                }
                                else
                                {
                                    echo "$row->usd_value";
                                }
                                echo "</div>";
                                $i++;
                            }
                        }

                        if($count_field > 0)
                        {
                            // gruppierungsspezifische Felder anzeigen
                            $i = 1;

                            while($row_field = mysql_fetch_object($result_field))
                            {
                                echo "<div style=\"float: left; width: 30%; text-align: left\">
                                    $row_field->usf_name:</div>
                                <div style=\"margin-left: 30%; text-align: left\">";

                                // Feldinhalt ausgeben
                                if($row_field->usf_type == 'CHECKBOX')
                                {
                                    if($row_field->usd_value == 1)
                                    {
                                        echo "&nbsp;<img src=\"$g_root_path/adm_program/images/checkbox_checked.gif\" style=\"vertical-align: middle;\">";
                                    }
                                    else
                                    {
                                        echo "&nbsp;<img src=\"$g_root_path/adm_program/images/checkbox.gif\" style=\"vertical-align: middle;\">";
                                    }
                                }
                                else
                                {
                                    echo "$row_field->usd_value&nbsp;";
                                }

                                echo "</div>";
                                $i++;
                            }

                        }
                    echo "</div>";
                }

            echo "</div>

            <div style=\"width: 32%; float: left;\">";

                // *******************************************************************************
                // Rollen-Block
                // *******************************************************************************

                // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
                if(isModerator())
                {
                   // auch gesperrte Rollen, aber nur von dieser Gruppierung anzeigen
                   $sql    = "SELECT rol_name, rol_org_shortname, mem_leader
                                FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                               WHERE mem_rol_id = rol_id
                                 AND mem_valid = 1
                                 AND mem_usr_id = $a_user_id
                                 AND rol_valid = 1
                                 AND (  rol_org_shortname LIKE '$g_organization'
                                     OR (   rol_org_shortname NOT LIKE '$g_organization'
                                        AND rol_locked = 0 ))
                               ORDER BY rol_org_shortname, rol_name ";
                }
                else
                {
                   // kein Moderator, dann keine gesperrten Rollen anzeigen
                   $sql    = "SELECT rol_name, rol_org_shortname, mem_leader
                                FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                               WHERE mem_rol_id    = rol_id
                                 AND mem_valid    = 1
                                 AND mem_usr_id    = $a_user_id
                                 AND rol_valid    = 1
                                 AND rol_locked = 0
                               ORDER BY rol_org_shortname, rol_name";
                }
                $result_role = mysql_query($sql, $g_adm_con);
                db_error($result_role, true);
                $count_role = mysql_num_rows($result_role);

                if($count_role > 0)
                {
                    $sql = "SELECT org_shortname FROM ". TBL_ORGANIZATIONS. "";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);

                    $count_grp = mysql_num_rows($result);
                    $i = 0;

                    echo "<div class=\"groupBox\" style=\"margin-top: 4px; text-align: left; height: 100%;\">
                        <div class=\"groupBoxHeadline\">Rollen</div>";

                        while($row = mysql_fetch_object($result_role))
                        {
                            // jede einzelne Rolle anzeigen
                            if($i > 0)
                            {
                                echo "<br />";
                            }

                            if($count_grp > 1)
                            {
                                echo "$row->rol_org_shortname - ";
                            }
                            echo $row->rol_name;
                            if($row->mem_leader == 1)
                            {
                                echo " - Leiter";
                            }
                            $i++;
                        }
                    echo "</div>";
                }
            echo "</div>

            <div style=\"clear: left;\"><br /></div>

            <div>
                <span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"javascript:self.location.href='". urldecode($url). "'\"><img
                     class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck\"></a>
                    <a class=\"iconLink\" href=\"javascript:self.location.href='". urldecode($url). "'\">Zur&uuml;ck</a>
                </span>";
                if($edit_user)
                {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?usr_id=$a_user_id&amp;url=$url\"><img
                         class=\"iconLink\" src=\"$g_root_path/adm_program/images/photo.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Profildaten &auml;ndern\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?usr_id=$a_user_id&amp;url=$url\">Profilfoto &auml;ndern</a>
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;                    
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$a_user_id&amp;url=$url\"><img
                         class=\"iconLink\" src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Profildaten &auml;ndern\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$a_user_id&amp;url=$url\">Profildaten &auml;ndern</a>
                    </span>";
                }                
            echo "</div>
        </div>
    </div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";

?>