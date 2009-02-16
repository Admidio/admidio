<?php
/******************************************************************************
 * verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode   :  1 - User als vCard exportieren
 *           2 - Mitgliedschaft bei einer Rolle entfernen
 *           3 - Ehemalige Rollenzuordnung entfernen
 * user_id: Id des Users, der bearbeitet werden soll
 * rol_id : Rollen-ID der Rolle, die geloescht werden soll
 *
 *****************************************************************************/

require_once("../../system/common.php");
require_once("../../system/login_valid.php");
require_once("../../system/classes/table_members.php");

// Uebergabevariablen pruefen

if(isset($_REQUEST['user_id']) && is_numeric($_REQUEST['user_id']) == false)
{
    $g_message->show("invalid");
}

if(isset($_REQUEST['rol_id']) && is_numeric($_REQUEST['rol_id']) == false)
{
    $g_message->show("invalid");
}

if(is_numeric($_REQUEST['mode']) == false
|| $_REQUEST['mode'] < 1 || $_REQUEST['mode'] > 3)
{
    $g_message->show("invalid");
}

if($_REQUEST['mode'] == 1)
{
    // Userdaten aus Datenbank holen
    $user = new User($g_db, $_REQUEST['user_id']);

    header('Content-Type: text/x-vcard; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename="'. $user->getValue("Vorname"). ' '. $user->getValue("Nachname"). '.vcf"');

    echo $user->getVCard();
}
elseif($_REQUEST['mode'] == 2)
{
    // Mitgliedschaft bei einer aktuellen Rolle beenden
    if($g_current_user->assignRoles())
    {
        $member = new TableMembers($g_db);
        $member->stopMembership($_REQUEST['rol_id'], $_REQUEST['user_id']);

        // Beendigung erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo "done";
    }
}
elseif($_REQUEST['mode'] == 3)
{
    // Ehemalige Rollenzuordnung entfernen
    if($g_current_user->isWebmaster())
    {
        $member = new TableMembers($g_db);
        $member->readData($_REQUEST['rol_id'], $_REQUEST['user_id']);
        $member->delete();

        // Entfernen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo "done";
    }
}

?>