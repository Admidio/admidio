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
 * mode :  1 - MsgBox, in der erklaert wird, welche Auswirkungen das Loeschen hat
 *         2 - Rolle anlegen oder updaten
 *         3 - Rolle zur inaktiven Rolle machen
 *         4 - Rolle loeschen
 *         5 - Rolle wieder aktiv setzen
 *         6 - Frage, ob inaktive Rolle geloescht werden soll
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
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["mode"]) == false
|| is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 6)
{
    $g_message->show("invalid");
}

if(isset($_GET["rol_id"]) && is_numeric($_GET["rol_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $rol_id = $_GET['rol_id'];
}

if($rol_id > 0)
{
    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    $sql    = "SELECT * FROM ". TBL_ROLES. "
                WHERE rol_id            = {0}
                  AND rol_org_shortname = '$g_organization' ";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) == 0)
    {
        $g_message->show("invalid");
    }
}

$_SESSION['roles_request'] = $_REQUEST;
$err_code = "";
$err_text = "";

if($_GET["mode"] == 1)
{
    echo "
    <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
    <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
    <html>
    <head>
        <title>$g_current_organization->longname - Messagebox</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

        <!--[if lt IE 7]>
        <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->";

        require("../../../adm_config/header.php");
    echo "</head>";

    require("../../../adm_config/body_top.php");
        echo "<div align=\"center\"><br /><br /><br />
            <div class=\"formHead\" style=\"width: 400px\">". strspace("Rolle l&ouml;schen"). "</div>

            <div class=\"formBody\" style=\"width: 400px\">
                <p align=\"left\">
                    <img src=\"$g_root_path/adm_program/images/wand_gray.png\" style=\"vertical-align: bottom;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Inaktive Rolle\">
                    Du kannst die Rolle zu einer <b>inaktiven Rolle</b> machen. Dies hat den Vorteil, dass die Daten
                    (Mitgliederzuordnung) erhalten bleiben und du sp&auml;ter immer wieder sehen kannst, welche Personen dieser Rolle
                    zugeordnet waren. Allerdings erscheint die Rolle nicht mehr in den &uuml;blichen &Uuml;bersichten.
                </p>
                <p align=\"left\">
                    <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"vertical-align: bottom;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Rolle l&ouml;schen\">
                    Wenn du <b>L&ouml;schen</b> ausw&auml;hlst, wird die Rolle und alle Mitgliedszuordnungen entg&uuml;ltig aus der Datenbank
                    entfernt und es ist sp&auml;ter nicht mehr m&ouml;glich Daten dieser Rolle einzusehen.
                </p>
                <button name=\"back\" type=\"button\" value=\"back\"
                    onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <button name=\"delete\" type=\"button\" value=\"delete\"
                    onclick=\"self.location.href='$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=". $_GET['rol_id']. "&mode=4'\">
                    <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Rolle l&ouml;schen\">
                    &nbsp;L&ouml;schen</button>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <button name=\"inactive\" type=\"button\" value=\"inactive\"
                    onclick=\"self.location.href='$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=". $_GET['rol_id']. "&mode=3'\">
                    <img src=\"$g_root_path/adm_program/images/wand_gray.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Inaktive Rolle\">
                    &nbsp;Inaktive Rolle</button>
            </div>
        </div>";

        require("../../../adm_config/body_bottom.php");
    echo "</body></html>";
    exit();
}
elseif($_GET["mode"] == 2)
{
    // Rolle anlegen oder updaten

    if(strlen(trim($_POST['name'])) > 0)
    {
        if($rol_id == 0)
        {
            // Schauen, ob die Rolle bereits existiert
            $sql    = "SELECT COUNT(*) FROM ". TBL_ROLES. "
                        WHERE rol_org_shortname LIKE '$g_organization'
                          AND rol_name          LIKE {0}
                          AND rol_cat_id        =    {1} ";
            $sql    = prepareSQL($sql, array($_POST['name'], $_POST['category']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $row = mysql_fetch_array($result);

            if($row[0] > 0)
            {
                $g_message->show("role_exist");
            }
        }

        // Zeitraum von/bis auf Gueltigkeit pruefen

        $d_datum_von = null;
        $d_datum_bis = null;

        if(strlen($_POST['start_date']) > 0)
        {
            if(dtCheckDate($_POST['start_date']))
            {
                $d_datum_von = dtFormatDate($_POST['start_date'], "Y-m-d");

                if(strlen($_POST['end_date']) > 0)
                {
                    if(dtCheckDate($_POST['end_date']))
                    {
                        $d_datum_bis = dtFormatDate($_POST['end_date'], "Y-m-d");
                    }
                    else
                    {
                        $g_message->show("datum", "Zeitraum bis");
                    }
                }
                else
                {
                    $g_message->show("feld", "Zeitraum bis");
                }
            }
            else
            {
                $g_message->show("datum", "Zeitraum von");
            }
        }

        // Uhrzeit von/bis auf Gueltigkeit pruefen

        $t_uhrzeit_von = null;
        $t_uhrzeit_bis = null;

        if(strlen($err_code) == 0)
        {
            if(strlen($_POST['start_time']) > 0)
            {
                if(dtCheckTime($_POST['start_time']))
                {
                    $t_uhrzeit_von = dtFormatTime($_POST['start_time'], "H:i:s");
                }
                else
                {
                    $g_message->show("uhrzeit");
                }

                if(strlen($_POST['end_time']) > 0)
                {
                    if(dtCheckTime($_POST['end_time']))
                    {
                        $t_uhrzeit_bis = dtFormatTime($_POST['end_time'], "H:i:s");
                    }
                    else
                    {
                        $g_message->show("uhrzeit");
                    }
                }
                else
                {
                    $g_message->show("feld", "Uhrzeit bis");
                }
            }
        }

        if(strlen($err_code) == 0)
        {
            if(strcmp($_POST['name'], "Webmaster") == 0)
            {
                $moderation = 1;
            }
            else
            {
                if(array_key_exists("moderation", $_POST))
                {
                    $moderation = 1;
                }
                else
                {
                    $moderation = 0;
                }
            }

            if(array_key_exists("announcements", $_POST))
            {
                $announcements = 1;
            }
            else
            {
                $announcements = 0;
            }

            if(array_key_exists("dates", $_POST))
            {
                $termine = 1;
            }
            else
            {
                $termine = 0;
            }

            if(array_key_exists("photos", $_POST))
            {
                $foto = 1;
            }
            else
            {
                $foto = 0;
            }

            if(array_key_exists("downloads", $_POST))
            {
                $download = 1;
            }
            else
            {
                $download = 0;
            }

            if(array_key_exists("guestbook", $_POST))
            {
                $guestbook = 1;
            }
            else
            {
                $guestbook = 0;
            }

            if(array_key_exists("guestbook_comments", $_POST))
            {
                $guestbook_comments = 1;
            }
            else
            {
                $guestbook_comments = 0;
            }

            if(array_key_exists("users", $_POST))
            {
                $user = 1;
            }
            else
            {
                $user = 0;
            }

            if(array_key_exists("mail_logout", $_POST))
            {
                $mail_logout = 1;
            }
            else
            {
                $mail_logout = 0;
            }

            if(array_key_exists("mail_login", $_POST))
            {
                $mail_login = 1;
            }
            else
            {
                $mail_login = 0;
            }

            if(array_key_exists("links", $_POST))
            {
                $weblinks = 1;
            }
            else
            {
                $weblinks = 0;
            }

            if(array_key_exists("profile", $_POST))
            {
                $profile = 1;
            }
            else
            {
                $profile = 0;
            }

            if(array_key_exists("locked", $_POST))
            {
                $locked = 1;
            }
            else
            {
                $locked = 0;
            }

            if(!array_key_exists("max_members", $_POST)
            || strlen($_POST['max_members']) == 0)
            {
                $_POST['max_members'] = "NULL";
            }
            /* Fasse: erkenne im Moment den Sinn nicht mehr :-(
            elseif($_POST['max_members'] == 0)
            {
                $_POST['max_members'] = "0";
            }*/

            // Kontrollieren ob bei nachtraeglicher Aenderung der maximalen Mitgliederzahl diese nicht bereits ueberschritten wurde

            // Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
            if($rol_id > 0)
            {
                $sql    = "SELECT COUNT(*) FROM ". TBL_MEMBERS. "
                            WHERE mem_rol_id= {0}
                              AND mem_leader = 0
                              AND mem_valid  = 1";
                $sql    = prepareSQL($sql, array($rol_id));
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                $role_members = mysql_fetch_array($result);

                if($_POST['max_members']!= 0 && ($role_members[0] > $_POST['max_members']))
                {
                    $g_message->show("max_members_roles_change");
                }
            }

            if(!array_key_exists("cost", $_POST)
            || strlen($_POST['cost']) == 0)
            {
                $_POST['cost'] = "NULL";
            }
            /* Fasse: erkenne im Moment den Sinn nicht mehr :-(
            elseif($_POST['cost'] == 0)
            {
                $_POST['cost'] = "0";
            }*/

            if($rol_id > 0)
            {
                $act_date = date("Y.m.d G:i:s", time());
                $sql = "UPDATE ". TBL_ROLES. "  SET rol_name    = {0}
                                                  , rol_description   = {1}
                                                  , rol_cat_id        = {2}
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
                                                  , rol_profile       = $profile
                                                  , rol_locked        = $locked
                                                  , rol_start_date    = {3}
                                                  , rol_start_time    = {4}
                                                  , rol_end_date      = {5}
                                                  , rol_end_time      = {6}
                                                  , rol_weekday       = {7}
                                                  , rol_location      = {8}
                                                  , rol_max_members   = {9}
                                                  , rol_cost          = {10}
                                                  , rol_last_change   = '$act_date'
                                                  , rol_usr_id_change = $g_current_user->id
                        WHERE rol_id = {11} ";
            }
            else
            {
                // Rolle in Datenbank hinzufuegen
                $sql    = "INSERT INTO ". TBL_ROLES. " (rol_org_shortname, rol_name, rol_description, rol_cat_id,
                                                       rol_moderation, rol_announcements, rol_dates, rol_photo, rol_download,
                                                       rol_edit_user, rol_guestbook, rol_guestbook_comments, rol_mail_logout,
                                                       rol_mail_login, rol_weblinks, rol_profile,  rol_locked, rol_start_date, rol_start_time,
                                                       rol_end_date, rol_end_time, rol_weekday, rol_location,
                                                       rol_max_members, rol_cost, rol_valid)
                                                VALUES ('$g_organization', {0}, {1}, {2},
                                                       $moderation, $announcements, $termine, $foto, $download,
                                                       $user, $guestbook, $guestbook_comments, $mail_logout,
                                                       $mail_login, $weblinks, $profile, $locked, {3}, {4},
                                                       {5},{6}, {7}, {8},
                                                       {9}, {10}, 1) ";
            }
            $sql    = prepareSQL($sql, array(trim($_POST['name']), trim($_POST['description']), $_POST['category'],
                            $d_datum_von, $t_uhrzeit_von, $d_datum_bis, $t_uhrzeit_bis,  $_POST['weekday'],
                            trim($_POST['location']), $_POST['max_members'], $_POST['cost'], $rol_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);

            //Reset des Rechtecache in der UserKlasse für den aendernen User
            $g_current_user->clearRights();

            //Rollenabhaengigkeiten setzten
            if(array_key_exists("ChildRoles", $_POST))
            {

                $sentChildRoles = $_POST['ChildRoles'];

                $roleDep = new RoleDependency($g_adm_con);

                // holt eine Liste der ausgewählten Rolen
                $DBChildRoles = RoleDependency::getChildRoles($g_adm_con,$rol_id);

				//entferne alle Rollen die nicht mehr ausgewählt sind
                foreach ($DBChildRoles as $DBChildRole)
                {
                    if(in_array($DBChildRole,$sentChildRoles))
                        continue;
                    else
                    {

						$roleDep->get($DBChildRole,$rol_id);
                        $roleDep->delete();
                    }
                }
                //fuege alle neuen Rollen hinzu
                foreach ($sentChildRoles as $sentChildRole)
                {
                    if(in_array($sentChildRole,$DBChildRoles))
                        continue;
                    else
                    {
                        $roleDep->clear();
                        $roleDep->setChild($sentChildRole);
                        $roleDep->setParent($rol_id);
                        $roleDep->insert($g_current_user->id);

                        //füge alle Mitglieder der ChildRole der ParentRole zu
                        $roleDep->updateMembership();

                    }

                }

            }
            else
            {
            	RoleDependency::removeChildRoles($g_adm_con,$rol_id);
            }

            $_SESSION['navigation']->deleteLastUrl();
            unset($_SESSION['roles_request']);
        }
    }
    else
    {
        // es sind nicht alle Felder gefuellt
        $g_message->show("feld", "Rolle");
    }

    $err_code = "save";
}
elseif($_GET["mode"] == 3)
{
    // Rolle zur inaktiven Rolle machen

    $sql = "SELECT rol_name FROM ". TBL_ROLES. "
             WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    $row = mysql_fetch_array($result);

    if($row[0] == "Webmaster")
    {
        $g_message->show("norights");
    }

    // Rolle ungueltig machen

    $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0
                                          , mem_end   = SYSDATE()
                WHERE mem_rol_id = {0}
                  AND mem_valid  = 1 ";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 0
                WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    $err_code = "role_inactive";
}
elseif($_GET["mode"] == 4)
{
    // Rolle aus der DB loeschens
    $sql    = "DELETE FROM ". TBL_MEMBERS. "
                WHERE mem_rol_id = {0} ";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    $sql    = "DELETE FROM ". TBL_ROLES. "
                WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    $err_code = "delete";
}
elseif($_GET["mode"] == 5)
{
    // Rolle wieder aktiv setzen
    $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 1
                                          , mem_end   = NULL
                WHERE mem_rol_id = {0} ";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 1
                WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    // Name der Rolle auslesen
    $sql = "SELECT rol_name FROM ". TBL_ROLES. "
             WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);

    $err_code = "role_active";
    $g_message->addVariableContent(utf8_encode($row[0]));
}
elseif($_GET["mode"] == 6)
{
    // Fragen, ob die inaktive Rolle geloescht werden soll
    $sql = "SELECT rol_name FROM ". TBL_ROLES. "
             WHERE rol_id = {0}";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);

    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$rol_id&amp;mode=4&amp;inactive=1");
    $g_message->show("delete_role", utf8_encode($row[0]), "Löschen");
}

$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code);
?>