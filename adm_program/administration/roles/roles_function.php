<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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
require("../../system/role_class.php");

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_rol_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET["mode"]) == false
|| is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 6)
{
    $g_message->show("invalid");
}

if(isset($_GET["rol_id"]))
{
    if(is_numeric($_GET["rol_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_rol_id = $_GET["rol_id"];
}

// Rollenobjekt anlegen
$role = new Role($g_adm_con);

if($req_rol_id > 0)
{
    $role->getRole($req_rol_id);
    
    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue("rol_org_shortname") != $g_organization)
    {
        $g_message->show("norights");
    }
}

$_SESSION['roles_request'] = $_REQUEST;
$msg_code = "";

if($_GET["mode"] == 1)
{
    echo "
    <!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
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
            <div class=\"formHead\" style=\"width: 400px\">Rolle l&ouml;schen</div>

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

    if(strlen(trim($_POST['rol_name'])) == 0)
    {
        // es sind nicht alle Felder gefuellt
        $g_message->show("feld", "Name");
    }
        
    if($req_rol_id == 0)
    {
        // Schauen, ob die Rolle bereits existiert
        $sql    = "SELECT COUNT(*) FROM ". TBL_ROLES. "
                    WHERE rol_org_shortname LIKE '$g_organization'
                      AND rol_name          LIKE {0}
                      AND rol_cat_id        =    {1} ";
        $sql    = prepareSQL($sql, array($_POST['rol_name'], $_POST['rol_cat_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $row = mysql_fetch_array($result);

        if($row[0] > 0)
        {
            $g_message->show("role_exist");
        }
    }
        
    // bei der Rolle "Webmaster" muessen bestimmte Flags gesetzt sein
    if(strcmp($_POST['rol_name'], "Webmaster") == 0)
    {
        $_POST['rol_moderation']  = 1;
        $_POST['rol_mail_logout'] = 1;
        $_POST['rol_mail_login']  = 1;
    }
    
    if(isset($_POST['rol_moderation']) == false)
    {
        $_POST['rol_moderation'] = 0;
    }
    if(isset($_POST['rol_announcements']) == false)
    {
        $_POST['rol_announcements'] = 0;
    }
    if(isset($_POST['rol_dates']) == false)
    {
        $_POST['rol_dates'] = 0;
    }
    if(isset($_POST['rol_photo']) == false)
    {
        $_POST['rol_photo'] = 0;
    }
    if(isset($_POST['rol_download']) == false)
    {
        $_POST['rol_download'] = 0;
    }
    if(isset($_POST['rol_guestbook']) == false)
    {
        $_POST['rol_guestbook'] = 0;
    }
    if(isset($_POST['rol_guestbook_comments']) == false)
    {
        $_POST['rol_guestbook_comments'] = 0;
    }
    if(isset($_POST['rol_edit_user']) == false)
    {
        $_POST['rol_edit_user'] = 0;
    }
    if(isset($_POST['rol_mail_logout']) == false)
    {
        $_POST['rol_mail_logout'] = 0;
    }
    if(isset($_POST['rol_mail_login']) == false)
    {
        $_POST['rol_mail_login'] = 0;
    }
    if(isset($_POST['rol_weblinks']) == false)
    {
        $_POST['rol_weblinks'] = 0;
    }
    if(isset($_POST['rol_profile']) == false)
    {
        $_POST['rol_profile'] = 0;
    }
    if(isset($_POST['rol_locked']) == false)
    {
        $_POST['rol_locked'] = 0;
    }
    
    // Zeitraum von/bis auf Gueltigkeit pruefen

    if(strlen($_POST['rol_start_date']) > 0)
    {
        if(dtCheckDate($_POST['rol_start_date']))
        {
            $_POST['rol_start_date'] = dtFormatDate($_POST['rol_start_date'], "Y-m-d");

            if(strlen($_POST['rol_end_date']) > 0)
            {
                if(dtCheckDate($_POST['rol_end_date']))
                {
                    $_POST['rol_end_date'] = dtFormatDate($_POST['rol_end_date'], "Y-m-d");
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

    if(strlen($_POST['rol_start_time']) > 0)
    {
        if(dtCheckTime($_POST['rol_start_time']))
        {
            $_POST['rol_start_time'] = dtFormatTime($_POST['rol_start_time'], "H:i:s");
        }
        else
        {
            $g_message->show("uhrzeit");
        }

        if(strlen($_POST['rol_end_time']) > 0)
        {
            if(dtCheckTime($_POST['rol_end_time']))
            {
                $_POST['rol_end_time'] = dtFormatTime($_POST['rol_end_time'], "H:i:s");
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

    // Kontrollieren ob bei nachtraeglicher Senkung der maximalen Mitgliederzahl diese nicht bereits ueberschritten wurde

    if($req_rol_id > 0
    && $_POST['rol_max_members'] < $role->getValue('rol_max_members'))
    {
        // Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
        $num_free_places = $role->countVacancies();

        if($num_free_places == 0)
        {
            $g_message->show("max_members_roles_change");
        }
    }
    
    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "rol_") === 0)
        {
            $role->setValue($key, $value);
        }
    }

    // Daten in Datenbank schreiben
    if($req_rol_id > 0)
    {
        $return_code = $role->update($g_current_user->id);
    }
    else
    {
        $return_code = $role->insert($g_current_user->id);
    }

    if($return_code < 0)
    {
        $g_message->show("norights");
    }

    // holt die Role ID des letzten Insert Statements
    if($req_rol_id == 0)
    {
        $req_rol_id = $role->getValue("rol_id");
    }

    //Reset des Rechtecache in der UserKlasse für den aendernen User
    $g_current_user->clearRights();

    //Rollenabhaengigkeiten setzten
    if(array_key_exists("ChildRoles", $_POST))
    {

        $sentChildRoles = $_POST['ChildRoles'];

        $roleDep = new RoleDependency($g_adm_con);

        // holt eine Liste der ausgewählten Rolen
        $DBChildRoles = RoleDependency::getChildRoles($g_adm_con,$req_rol_id);

        //entferne alle Rollen die nicht mehr ausgewählt sind
        if($DBChildRoles != -1)
        {
            foreach ($DBChildRoles as $DBChildRole)
            {
                if(in_array($DBChildRole,$sentChildRoles))
                    continue;
                else
                {

                    $roleDep->get($DBChildRole,$req_rol_id);
                    $roleDep->delete();
                }
            }
        }
        //fuege alle neuen Rollen hinzu
        foreach ($sentChildRoles as $sentChildRole)
        {
            if((-1 == $DBChildRoles) || in_array($sentChildRole,$DBChildRoles))
                continue;
            else
            {
                $roleDep->clear();
                $roleDep->setChild($sentChildRole);
                $roleDep->setParent($req_rol_id);
                $roleDep->insert($g_current_user->id);

                //füge alle Mitglieder der ChildRole der ParentRole zu
                $roleDep->updateMembership();

            }

        }

    }
    else
    {
        RoleDependency::removeChildRoles($g_adm_con,$req_rol_id);
    }

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['roles_request']);

    $msg_code = "save";
}
elseif($_GET["mode"] == 3)
{
    // Rolle zur inaktiven Rolle machen
    $return_code = $role->setInactive();
    
    if($return_code < 0)
    {
        $g_message->show("norights");
    }

    $msg_code = "role_inactive";
    $g_message->addVariableContent(utf8_encode($role->getValue("rol_name")));
}
elseif($_GET["mode"] == 4)
{
    // Rolle aus der DB loeschens
    $return_code = $role->delete();
    
    if($return_code < 0)
    {
        $g_message->show("norights");
    }

    $msg_code = "delete";
}
elseif($_GET["mode"] == 5)
{
    // Rolle wieder aktiv setzen
    $return_code = $role->setActive();

    if($return_code < 0)
    {
        $g_message->show("norights");
    }

    $msg_code = "role_active";
    $g_message->addVariableContent(utf8_encode($role->getValue("rol_name")));
}
elseif($_GET["mode"] == 6)
{
    // Fragen, ob die inaktive Rolle geloescht werden soll
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$req_rol_id&amp;mode=4");
    $g_message->show("delete_role", utf8_encode($role->getValue("rol_name")), "Löschen");
}

$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($msg_code);
?>