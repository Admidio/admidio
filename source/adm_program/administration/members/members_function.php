<?php
/******************************************************************************
 * User-Funktionen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode: 1 - MsgBox, in der erklaert wird, welche Auswirkungen das Loeschen hat
 *       2 - User NUR aus der Gliedgemeinschaft entfernen
 *       3 - User aus der Datenbank loeschen
 *       4 - User E-Mail mit neuen Zugangsdaten schicken
 *       5 - Frage, ob Zugangsdaten geschickt werden soll
 *       6 - Frage, ob Mitglied geloescht werden soll
 * user_id - Id des Benutzers, der bearbeitet werden soll
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/system_mail.php");
require("../../system/classes/table_members.php");

$err_code = ""; 
$err_text = "";

// nur berechtigte User duerfen Funktionen aufrufen
if(!$g_current_user->editUsers())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 6)
{
    $g_message->show("invalid");
}

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

// nun erst einmal allgemein pruefen, ob der User zur aktuellen Orga gehoert
if(isMember($_GET["user_id"]) == true)
{
    $this_orga = true;
}
else
{
    $this_orga = false;
}

if($_GET["mode"] != 1)
{
    // pruefen, ob der User noch in anderen Organisationen aktiv ist
    $sql    = "SELECT rol_id
                 FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_CATEGORIES. "
                WHERE rol_valid   = 1
                  AND rol_cat_id  = cat_id
                  AND cat_org_id <> ". $g_current_organization->getValue("org_id"). "
                  AND mem_rol_id  = rol_id
                  AND mem_begin  <= '".DATE_NOW."'
                  AND mem_end     > '".DATE_NOW."'
                  AND mem_usr_id  = ". $_GET['user_id'];
    $result = $g_db->query($sql);
    $other_orga = $g_db->num_rows($result);

    // User-Objekt anlegen
    $user = new User($g_db, $_GET['user_id']);
}

if($_GET["mode"] == 1)
{
    // Html-Kopf ausgeben
    $g_layout['title'] = "Messagebox";
    require(THEME_SERVER_PATH. "/overall_header.php");

    // Html des Modules ausgeben
    echo "<br /><br /><br />
    <div class=\"formLayout\" id=\"user_delete_message_form\" style=\"width: 400px\">
        <div class=\"formHead\">Mitglied l&ouml;schen</div>
        <div class=\"formBody\">
            <p align=\"left\">
                <img src=\"". THEME_PATH. "/icons/profile.png\" alt=\"Ehemaliger\" />
                Du kannst den Benutzer zu einem <b>Ehemaligen</b> machen. Dies hat den Vorteil, dass die Daten
                erhalten bleiben und du sp&auml;ter immer wieder sehen kannst, welchen Rollen diese Person
                zugeordnet war.
            </p>
            <p align=\"left\">
                <img src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Benutzer l&ouml;schen\" />
                Wenn du <b>L&ouml;schen</b> ausw&auml;hlst, wird der Datensatz entg&uuml;ltig aus der Datenbank
                entfernt und es ist sp&auml;ter nicht mehr m&ouml;glich Daten dieser Person einzusehen.
            </p>
            <button name=\"back\" type=\"button\" value=\"back\" onclick=\"history.back()\"><img src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" />&nbsp;Zurück</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"delete\" type=\"button\" value=\"delete\" onclick=\"self.location.href='$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET['user_id']. "&mode=3'\"><img src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Benutzer löschen\" />&nbsp;Löschen</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"former\" type=\"button\" value=\"former\" onclick=\"self.location.href='$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET['user_id']. "&mode=2'\"><img src=\"". THEME_PATH. "/icons/profile.png\" alt=\"Ehemaliger\" />&nbsp;Ehemaliger</button>
        </div>
    </div>";

    require(THEME_SERVER_PATH. "/overall_footer.php");
    exit();
}
elseif($_GET["mode"] == 2)
{
    // User NUR aus der aktuellen Organisation entfernen

    // Es duerfen keine Webmaster entfernt werden
    if($g_current_user->isWebmaster() == false
    && $user->isWebmaster()           == true)
    {
        $g_message->show("norights");
    }

    // User muss zur aktuellen Orga dazugehoeren
    // kein Suizid ermoeglichen
    if($this_orga == false
    || $g_current_user->getValue("usr_id") == $_GET['user_id'])
    {
        $g_message->show("norights");
    }
    
    $member = new TableMembers($g_db);

    $sql = "SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_valid, mem_leader
              FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. "
             WHERE rol_valid  = 1
               AND rol_cat_id = cat_id
               AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
               AND mem_rol_id = rol_id
               AND mem_begin <= '".DATE_NOW."'
               AND mem_end    > '".DATE_NOW."'
               AND mem_usr_id = ". $_GET['user_id'];
    $result_mgl = $g_db->query($sql);

    while($row = $g_db->fetch_array($result_mgl))
    {
        // alle Rollen der aktuellen Gliedgemeinschaft auf ungueltig setzen
        $member->setArray($row);
        $member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
    }

    $err_code = "remove_member_ok";
    $err_text = $g_current_organization->getValue("org_longname");
}
elseif($_GET["mode"] == 3)
{
    // User aus der Datenbank loeschen
    
    // nur Webmaster duerfen dies
    if($g_current_user->isWebmaster() == false)
    {
        $g_message->show("norights");
    }
    
    // User darf in keiner anderen Orga aktiv sein
    // kein Suizid ermoeglichen
    if($other_orga > 0
    || $g_current_user->getValue("usr_id") == $_GET['user_id'])
    {
        $g_message->show("norights");
    }

    // Paralell im Forum loeschen, wenn g_forum gesetzt ist
    if($g_preferences['enable_forum_interface'])
    {
        $g_forum->userDelete($user->getValue("usr_login_name"));
        
        $err_code = "delete_forum_user";
    }
    else
    {
        $err_code = "delete";
    }
    
    // User aus der Admidio Datenbank loeschen
    $user->delete();
}
elseif($_GET["mode"] == 4)
{
    // nur Webmaster duerfen User neue Zugangsdaten zuschicken
    // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
    // nur an Mitglieder der eigenen Organisation schicken
    if($g_current_user->isWebmaster() == false
    || $g_preferences['enable_system_mails'] != 1
    || $this_orga == false)
    {
        $g_message->show("norights");
    }

    if($g_preferences['enable_system_mails'] == 1)
    {
        // neues Passwort generieren und abspeichern
        $password = substr(md5(time()), 0, 8);
        $user->setValue("usr_password", $password);
        $user->save();

        // Mail an den User mit den Loginaten schicken
        $sysmail = new SystemMail($g_db);
        $sysmail->addRecipient($user->getValue("E-Mail"), $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
        $sysmail->setVariable(1, $user->real_password);
        if($sysmail->sendSystemMail("SYSMAIL_NEW_PASSWORD", $user) == true)
        {
            $err_code = "mail_send";
            $err_text = $user->getValue("E-Mail");
        }
        else
        {
            $err_code = "mail_not_send";
            $err_text = $user->getValue("E-Mail");
        }
    }
}
elseif($_GET["mode"] == 5)
{
    // Fragen, ob Zugangsdaten verschickt werden sollen
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=4");
    $g_message->show("send_new_login", $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
}
elseif($_GET["mode"] == 6)
{
    if($this_orga == true && $other_orga == 0 && $g_current_user->isWebmaster())
    {
        // nur Webmaster duerfen dies
        // User ist NUR Mitglied der aktuellen Orga -> dann fragen, ob Ehemaliger oder ganz loeschen
        header("Location: $g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=1");
        exit();
    }
    elseif($this_orga == false && $other_orga == 0 && $g_current_user->isWebmaster())
    {
        // nur Webmaster duerfen dies
        // User ist in keiner Orga mehr Mitglied -> kann komplett geloescht werden
        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=3");
        $g_message->addVariableContent($user->getValue("Vorname"). " ". $user->getValue("Nachname"));
        $g_message->addVariableContent($g_current_organization->getValue("org_longname"));
        $g_message->show("delete_user", "", "Löschen");
    }
    else
    {
        // User kann nur aus dieser Orga entfernt werden
        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=2");
        $g_message->addVariableContent($user->getValue("Vorname"). " ". $user->getValue("Nachname"));
        $g_message->addVariableContent($g_current_organization->getValue("org_longname"));
        $g_message->show("remove_member", "", "Entfernen");
    }
}

$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code, $err_text);
?>