<?php
/******************************************************************************
 * verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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

require("../../system/common.php");
require("../../system/login_valid.php");

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

    header('Content-Type: text/x-vcard');
    header('Content-Disposition: attachment; filename="'. $user->getValue("Vorname"). ' '. $user->getValue("Nachname"). '.vcf"');

    echo $user->getVCard();
}
elseif($_REQUEST['mode'] == 2)
{
    // Mitgliedschaft bei einer aktuellen Rolle beenden
    if($g_current_user->assignRoles() || $g_current_user->editUser())
    {
        $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0 
                                           , mem_end   = NOW()
                 WHERE mem_usr_id = ". $_REQUEST['user_id']. "
                   AND mem_rol_id = ". $_REQUEST['rol_id'];
        $g_db->query($sql);
    }
}
elseif($_REQUEST['mode'] == 3)
{
    // Ehemalige Rollenzuordnung entfernen
    if($g_current_user->isWebmaster())
    {
        $sql = "DELETE FROM ". TBL_MEMBERS. "
                 WHERE mem_usr_id = ". $_REQUEST['user_id']. "
                   AND mem_rol_id = ". $_REQUEST['rol_id'];
        $g_db->query($sql);
    }
}

?>