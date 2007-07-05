<?php
/******************************************************************************
 * Profil anzeigen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id: zeigt das Profil der uebergebenen user_id an
 *          (wird keine user_id uebergeben, dann Profil des eingeloggten Users anzeigen)
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

// Uebergabevariablen pruefen

if(isset($_GET['user_id']))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    // Daten des uebergebenen Users anzeigen
    $a_user_id = $_GET['user_id'];
}
else
{
    // wenn nichts uebergeben wurde, dann eigene Daten anzeigen
    $a_user_id = $g_current_user->getValue("usr_id");
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($field, $user_id)
{
    global $g_preferences, $g_root_path, $g_current_user;
    $value = "";
    
    if($g_current_user->editProfile($user_id) == false && $field['usf_hidden'] == 1)
    {
        return "";
    }
    
    switch($field['usf_type'])
    {
        case "CHECKBOX":
            if($field['usd_value'] == 1)
            {
                $value = "<img src=\"$g_root_path/adm_program/images/checkbox_checked.gif\" alt=\"on\">";
            }
            else
            {
                $value = "<img src=\"$g_root_path/adm_program/images/checkbox.gif\" alt=\"off\">";
            }
            break;
            
        case "DATE":
            if(strlen($field['usd_value']) > 0)
            {
                $value = mysqldate('d.m.y', $field['usd_value']);
                if($field['usf_name'] == "Geburtstag")
                {
                    // Alter mit ausgeben
                    $value = $value. '&nbsp;&nbsp;&nbsp;('. dtGetAge($field['usd_value']). ' Jahre)';
                }
            }
            break;
            
        case "EMAIL":
            // E-Mail als Link darstellen
            if(strlen($field['usd_value']) > 0)
            {
                if($g_preferences['enable_mail_module'] != 1)
                {
                    $mail_link = "mailto:". $field['usd_value'];
                }
                else
                {
                    $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?usr_id=$user_id";
                }
                $value = '<a href="'. $mail_link. '" style="overflow: visible; display: inline;">'. $field['usd_value']. '</a>';
            }
            break;

        case "URL":
            // Homepage als Link darstellen
            if(strlen($field['usd_value']) > 0)
            {
                $value = '<a href="'. $field['usd_value']. '" target="_blank">'. substr($field['usd_value'], 7). '</a>';
            }
            break;
                                
        case "TEXT":
        case "TEXT_BIG":
            $value = $field['usd_value'];
            break;
    }
    
    if($field['cat_name'] == "Stammdaten")
    {
        if(strlen($field['usd_value']) > 25)
        {
            $value = '<span class="smallFontSize">'. $value. '</span>';
        }
    }
    elseif($field['cat_name'] == "Messenger")
    {
        // Icons der Messenger anzeigen
        if($field['usf_name'] == 'ICQ')
        {
            // Sonderzeichen aus der ICQ-Nummer entfernen (damit kommt www.icq.com nicht zurecht)
            preg_match_all("/\d+/", $field['usd_value'], $matches);
            $icq_number = implode("", reset($matches));

            // ICQ Onlinestatus anzeigen
            $value = "<a href=\"http://www.icq.com/whitepages/cmd.php?uin=$icq_number&amp;action=add\"  class=\"wpaction\">
            <img border=\"0\" src=\"http://status.icq.com/online.gif?icq=$icq_number&amp;img=5\"
            style=\"vertical-align: middle;\" alt=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzuf&uuml;gen\" 
            title=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzuf&uuml;gen\" /></a>&nbsp;$value";
        }
        elseif($field['usf_name'] == 'Skype')
        {
            // Skype Onlinestatus anzeigen
            $value = "<script type=\"text/javascript\" src=\"http://download.skype.com/share/skypebuttons/js/skypeCheck.js\"></script>
            <a href=\"skype:". $field['usd_value']. "?add\"><img src=\"http://mystatus.skype.com/smallicon/". $field['usd_value']. "\"
            style=\"border: none; vertical-align: middle;\" width=\"16\" height=\"16\" 
            title=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzuf&uuml;gen\" 
            alt=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzuf&uuml;gen\" /></a>&nbsp;&nbsp;$value";
        }
        else
        {
            $image = "";
            if($field['usf_name'] == 'AIM')
            {
                $image = "aim.png";
            }
            elseif($field['usf_name'] == 'Google Talk')
            {
                $image = "google.gif";
            }
            elseif($field['usf_name'] == 'MSN')
            {
                $image = "msn.png";
            }
            elseif($field['usf_name'] == 'Yahoo')
            {
                $image = "yahoo.png";
            }
            if(strlen($image) > 0)
            {
                $value = "<img src=\"$g_root_path/adm_program/images/$image\" style=\"vertical-align: middle;\" 
                    alt=\"". $field['usf_name']. "\" title=\"". $field['usf_name']. "\" />&nbsp;&nbsp;$value";
            }
        };
    }
    
    $html = '<div style="margin-top: 3px;">
        <div style="float: left; width: 30%; text-align: left;">'. $field['usf_name']. ':</div>
        <div style="text-align: left;">'. $value. '&nbsp;</div>
        </div>';
             
    return $html;
}

// User auslesen
$user = new User($g_adm_con, $a_user_id);

unset($_SESSION['profile_request']);
// Seiten fuer Zuruecknavigation merken
if($a_user_id != $g_current_user->getValue("usr_id") && isset($_GET['user_id']) == false)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl($g_current_url);

// Html-Kopf ausgeben
$g_layout['title'] = "Profil";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<div class=\"formHead\">";
    if($a_user_id == $g_current_user->getValue("usr_id"))
    {
        echo "Mein Profil";
    }
    else
    {
        echo "Profil von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
    }
echo "</div>

<div class=\"formBody\">
    <div>";
        // *******************************************************************************
        // Userdaten-Block
        // *******************************************************************************

        echo "<div style=\"width: 66%; margin-right: 10px; float: left;\">
            <div class=\"groupBox\" style=\"margin-top: 4px; text-align: left;\">
                <div class=\"groupBoxHeadline\">
                    <div style=\"width: 60%; float: left;\">
                        ". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). "&nbsp;&nbsp;";
                        if($user->getValue("Geschlecht") == 1)
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/male.png\" style=\"vertical-align: top;\" title=\"m&auml;nnlich\" alt=\"m&auml;nnlich\">";
                        }
                        elseif($user->getValue("Geschlecht") == 2)
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/female.png\" style=\"vertical-align: top;\" title=\"weiblich\" alt=\"weiblich\">";
                        }
                    echo "</div>
                    <div style=\"text-align: right;\">&nbsp;";
                        // Nur berechtigte User duerfen ein Profil editieren
                        if($g_current_user->editProfile($a_user_id) == true)
                        {
                            echo "<a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$a_user_id\"><img
                             src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: top;\" border=\"0\" alt=\"Daten bearbeiten\"></a>
                            <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$a_user_id\">Bearbeiten</a>";
                        }
                    echo "</div>
                </div>
                <div class=\"groupBoxBody\">
                    <div>
                        <div style=\"float: left; margin-bottom: 5px; width: 30%; text-align: left\">Benutzername:</div>
                        <div style=\"margin-bottom: 5px; margin-left: 30%; text-align: left\"><i>". $user->getValue("usr_login_name"). "&nbsp;</i></div>
                    </div>";

                    // Schleife ueber alle Felder der Stammdaten

                    foreach($user->db_user_fields as $key => $value)
                    {
                        // nur Felder der Stammdaten anzeigen
                        if($value['cat_name'] == "Stammdaten")
                        {
                            switch($value['usf_name'])
                            {
                                case "Nachname":
                                case "Vorname":
                                case "PLZ":
                                case "Ort":
                                case "Land":
                                case "Geschlecht":
                                    // diese Felder werden nicht einzeln dargestellt
                                    break;

                                case "Adresse":
                                    if($value['usf_name'] == "Adresse")   // nur 1x bei Adresse schreiben
                                    {
                                        echo "<div style=\"margin-top: 3px;\">
                                        <div style=\"float: left; width: 30%; text-align: left;\">Adresse:";
                                            if(strlen($user->getValue("PLZ")) > 0 || strlen($user->getValue("Ort")) > 0)
                                                echo "<br />&nbsp;";
                                            if(strlen($user->getValue("Land")) > 0)
                                                echo "<br />&nbsp;";
                                            if(strlen($user->getValue("Adresse")) > 0
                                            && (  strlen($user->getValue("PLZ"))  > 0
                                            || strlen($user->getValue("Ort"))  > 0 ))
                                                echo "<br /><span class=\"smallFontSize\">&nbsp;</span>";
                                        echo "</div>

                                        <div style=\"text-align: left;\">";
                                            if(strlen($user->getValue("Adresse")) == 0 && strlen($user->getValue("PLZ")) == 0 && strlen($user->getValue("Ort")) == 0)
                                                echo "<i>keine Daten vorhanden</i>";
                                            if(strlen($user->getValue("Adresse")) > 0)
                                                echo $user->getValue("Adresse");
                                            if(strlen($user->getValue("PLZ")) > 0 || strlen($user->getValue("Ort")) > 0)
                                            {
                                                echo "<br />";
                                                if(strlen($user->getValue("PLZ")) > 0)
                                                    echo $user->getValue("PLZ"). " ";
                                                if(strlen($user->getValue("Ort")) > 0)
                                                    echo $user->getValue("Ort");
                                            }
                                            if(strlen($user->getValue("Land")) > 0)
                                                echo "<br />". $user->getValue("Land");

                                            if(strlen($user->getValue("Adresse")) > 0
                                            && (  strlen($user->getValue("PLZ"))  > 0
                                            || strlen($user->getValue("Ort"))  > 0 ))
                                            {
                                                // Button mit Karte anzeigen
                                                $map_url = "http://maps.google.com/?q=". urlencode($user->getValue("Adresse"));
                                                if(strlen($user->getValue("PLZ"))  > 0)
                                                {
                                                    $map_url .= ",%20". $user->getValue("PLZ");
                                                }
                                                if(strlen($user->getValue("Ort"))  > 0)
                                                {
                                                    $map_url .= ",%20". $user->getValue("Ort");
                                                }
                                                if(strlen($user->getValue("Land"))  > 0)
                                                {
                                                    $map_url .= ",%20". $user->getValue("Land");
                                                }

                                                echo "<br />
                                                <span class=\"smallFontSize\">( <a href=\"$map_url\" target=\"_blank\">Stadtplan</a>";

                                                if($g_current_user->getValue("usr_id") != $a_user_id)
                                                {
                                                    if(strlen($g_current_user->getValue("Adresse")) > 0
                                                    && (  strlen($g_current_user->getValue("PLZ"))  > 0
                                                    || strlen($g_current_user->getValue("Ort"))  > 0 ))
                                                    {
                                                        // Link fuer die Routenplanung
                                                        $route_url = "http://maps.google.com/?f=d&saddr=". urlencode($g_current_user->getValue("Adresse"));
                                                        if(strlen($g_current_user->getValue("PLZ"))  > 0)
                                                        {
                                                            $route_url .= ",%20". $g_current_user->getValue("PLZ");
                                                        }
                                                        if(strlen($g_current_user->getValue("Ort"))  > 0)
                                                        {
                                                            $route_url .= ",%20". $g_current_user->getValue("Ort");
                                                        }
                                                        if(strlen($g_current_user->getValue("Land"))  > 0)
                                                        {
                                                            $route_url .= ",%20". $g_current_user->getValue("Land");
                                                        }

                                                        $route_url .= "&daddr=". urlencode($user->getValue("Adresse"));
                                                        if(strlen($user->getValue("PLZ"))  > 0)
                                                        {
                                                            $route_url .= ",%20". $user->getValue("PLZ");
                                                        }
                                                        if(strlen($user->getValue("Ort")) > 0)
                                                        {
                                                            $route_url .= ",%20". $user->getValue("Ort");
                                                        }
                                                        if(strlen($user->getValue("Land")) > 0)
                                                        {
                                                            $route_url .= ",%20". $user->getValue("Land");
                                                        }
                                                        echo " - <a href=\"$route_url\" target=\"_blank\">Route berechnen</a>";
                                                    }
                                                }
                                                echo " )</span>";
                                            }
                                        echo "</div>
                                        </div>";
                                    }
                                    break;

                                default:
                                    echo getFieldCode($value, $a_user_id);
                                    break;
                            }
                        }
                        else
                        {
                            // keine Stammdaten mehr also diese Schleife erst einmal abbrechen
                            break;
                        }
                    }
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
            db_error($result_photo,__FILE__,__LINE__);

            echo"
            <div style=\"margin-top: 4px; text-align: center;\">
                <div class=\"groupBox\">
                    <div class=\"groupBoxBody\">";
                        //Falls vorhanden Bild ausgeben
                        if(mysql_result($result_photo,0,"usr_photo")!=NULL)
                        {
                            echo"<img src=\"$g_root_path/adm_program/modules/profile/profile_photo_show.php?usr_id=$a_user_id&amp;id=". time(). "\" alt=\"Profilfoto\">";
                        }
                        //wenn nicht Schattenkopf
                        else
                        {
                            echo"<img src=\"$g_root_path/adm_program/images/no_profile_pic.png\" alt=\"Profilfoto\">";
                        }
                    echo"</div>
                </div>";
                
                // Nur berechtigte User duerfen ein Profil editieren
                if($g_current_user->editProfile($a_user_id) == true)
                {
                    echo "<div style=\"margin-top: 5px;\">
                        <span class=\"iconLink\">
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?usr_id=$a_user_id\"><img
                             class=\"iconLink\" src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Foto &auml;ndern\"></a>
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?usr_id=$a_user_id\">Foto &auml;ndern</a>
                        </span>
                    </div>";
                }
                echo"<div style=\"margin-top: 5px;\">
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id=". $user->getValue("usr_id"). "\"><img
                         class=\"iconLink\" src=\"$g_root_path/adm_program/images/vcard.png\" alt=\"Benutzer als vCard exportieren\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id=". $user->getValue("usr_id"). "\">vCard exportieren</a>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div style=\"clear: left; font-size: 1pt;\">&nbsp;</div>";

    // *******************************************************************************
    // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
    // *******************************************************************************

    $category = "";
    foreach($user->db_user_fields as $key => $value)
    {
        // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
        // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
        if($value['cat_name'] != "Stammdaten"
        && (  $g_current_user->editProfile($a_user_id) == true
           || ($g_current_user->editProfile($a_user_id) == false && $value['usf_hidden'] == 0 )))
        {
            // Kategorienwechsel den Kategorienheader anzeigen
            // Kategorie "Messenger" nur anzeigen, wenn auch Daten zugeordnet sind
            if($category != $value['cat_name'] 
            && (  $value['cat_name'] != "Messenger" 
               || ($value['cat_name'] == "Messenger" && strlen($value['usd_value']) > 0 )))
            {
                if(strlen($category) > 0)
                {
                    // div-Container groupBoxBody und groupBox schliessen
                    echo "</div></div>";
                }
                $category = $value['cat_name'];
                
                echo "<div class=\"groupBox\" style=\"margin-top: 10px; text-align: left;\">
                    <div class=\"groupBoxHeadline\">". $value['cat_name']. "</div>
                    <div class=\"groupBoxBody\">";
            }
            
            // Html des Feldes ausgeben
            // bei Kategorie "Messenger" nur anzeigen, wenn auch Daten zugeordnet sind
            if($value['cat_name'] != "Messenger" 
            || ($value['cat_name'] == "Messenger" && strlen($value['usd_value']) > 0 ))
            {
                echo getFieldCode($value, $a_user_id);
            }
        }
    }

    // div-Container groupBoxBody und groupBox schliessen
    echo "</div></div>

   <div style=\"margin-top: 5px;\">";

        // *******************************************************************************
        // Rollen-Block
        // *******************************************************************************

        // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
        if($g_current_user->assignRoles())
        {
           // auch gesperrte Rollen, aber nur von dieser Gruppierung anzeigen
           $sql    = "SELECT *
                        FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_ORGANIZATIONS. "
                       WHERE mem_rol_id = rol_id
                         AND mem_valid  = 1
                         AND mem_usr_id = $a_user_id
                         AND rol_valid  = 1
                         AND rol_cat_id = cat_id
                         AND cat_org_id = org_id
                         AND (  cat_org_id = $g_current_organization->id
                             OR (   cat_org_id <> $g_current_organization->id
                                AND rol_locked  = 0 ))
                       ORDER BY org_shortname, cat_sequence, rol_name ";
        }
        else
        {
           // kein Moderator, dann keine gesperrten Rollen anzeigen
           $sql    = "SELECT *
                        FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_ORGANIZATIONS. "
                       WHERE mem_rol_id = rol_id
                         AND mem_valid  = 1
                         AND mem_usr_id = $a_user_id
                         AND rol_valid  = 1
                         AND rol_locked = 0
                         AND rol_cat_id = cat_id
                         AND cat_org_id = org_id
                       ORDER BY org_shortname, cat_sequence, rol_name";
        }
        $result_role = mysql_query($sql, $g_adm_con);
        db_error($result_role,__FILE__,__LINE__);
        $count_role = mysql_num_rows($result_role);

        if($count_role > 0)
        {
            $sql = "SELECT org_shortname FROM ". TBL_ORGANIZATIONS;
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);

            $count_grp = mysql_num_rows($result);
            $i = 0;

            echo "<div class=\"groupBox\" style=\"margin-top: 10px; text-align: left; height: 100%;\">
                <div class=\"groupBoxHeadline\">
                    <div style=\"width: 70%; float: left;\">
                        Rollen und Berechtigungen&nbsp;";
                    echo "</div>
                    <div style=\"text-align: right;\">&nbsp;";
                        // Moderatoren & Gruppenleiter duerfen neue Rollen zuordnen
                        if(($g_current_user->assignRoles() || isGroupLeader() || $g_current_user->editUser())
                        && $user->getValue("usr_reg_org_shortname") != $g_current_organization->shortname)
                        {
                            echo "<a href=\"$g_root_path/adm_program/modules/profile/roles.php?user_id=$a_user_id\"><img
                            src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: top;\" 
                            border=\"0\" title=\"Rollen &auml;ndern\" alt=\"Rollen &auml;ndern\"></a>
                            <a href=\"$g_root_path/adm_program/modules/profile/roles.php?user_id=$a_user_id\">Bearbeiten</a>";
                        }
                    echo "</div>
                </div>
                <div class=\"groupBoxBody\">";
                    while($row = mysql_fetch_array($result_role))
                    {
                        // jede einzelne Rolle anzeigen
                        if($i > 0)
                        {
                            echo "<br />";
                        }

                        if($count_grp > 1)
                        {
                            echo $row['org_shortname']. " - ";
                        }
                        echo $row['cat_name']. " - ". $row['rol_name'];
                        if($row['mem_leader'] == 1)
                        {
                            echo " - Leiter";
                        }
                        if($row['org_shortname'] == $g_current_organization->shortname)
                        {
                            // nun fuer alle Rollenrechte die Icons anzeigen
                            echo "&nbsp;";
                            if($row['rol_assign_roles'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/wand.png\"
                                     alt=\"Rollen verwalten und zuordnen\" title=\"Rollen verwalten und zuordnen\">";
                            }
                            if($row['rol_edit_user'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/group.png\"
                                     alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" title=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\">";
                            }
                            if($row['rol_profile'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/user.png\"
                                     alt=\"Eigenes Profil bearbeiten\" title=\"Eigenes Profil bearbeiten\">";
                            }
                            if($row['rol_announcements'] == 1 && $g_preferences['enable_announcements_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/note.png\"
                                     alt=\"Ank&uuml;ndigungen anlegen und bearbeiten\" title=\"Ank&uuml;ndigungen anlegen und bearbeiten\">";
                            }
                            if($row['rol_dates'] == 1 && $g_preferences['enable_dates_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/date.png\"
                                     alt=\"Termine anlegen und bearbeiten\" title=\"Termine anlegen und bearbeiten\">";
                            }
                            if($row['rol_photo'] == 1 && $g_preferences['enable_photo_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/photo.png\"
                                     alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\">";
                            }
                            if($row['rol_download'] == 1 && $g_preferences['enable_download_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/folder_down.png\"
                                     alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\">";
                            }
                            if($row['rol_guestbook'] == 1 && $g_preferences['enable_guestbook_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/comment.png\"
                                     alt=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\" title=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\">";
                            }
                            if($row['rol_guestbook_comments'] == 1 && $g_preferences['enable_guestbook_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/comments.png\"
                                     alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" title=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\">";
                            }
                            if($row['rol_weblinks'] == 1 && $g_preferences['enable_weblinks_module'] == 1)
                            {
                                echo "&nbsp;<img style=\"cursor: help; vertical-align: top;\" src=\"$g_root_path/adm_program/images/globe.png\"
                                     alt=\"Weblinks anlegen und bearbeiten\" title=\"Weblinks anlegen und bearbeiten\">";
                            }
                        }
                        $i++;
                    }
                echo "</div>
            </div>";
        }
    echo "</div>";

    if($a_user_id != $g_current_user->getValue("usr_id") && isset($_GET['user_id']) == true)
    {
        echo "<div style=\"clear: left; font-size: 1pt;\">&nbsp;</div>
        <div style=\"margin-top: 5px;\">
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\"><img
                 class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
            </span>
        </div>";
    }
echo "</div>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>