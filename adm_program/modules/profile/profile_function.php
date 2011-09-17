<?php
/******************************************************************************
 * verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode   :  1 - User als vCard exportieren
 *           2 - Mitgliedschaft bei einer Rolle entfernen
 *           3 - Ehemalige Rollenzuordnung entfernen
 * user_id: Id des Users, der bearbeitet werden soll
 * rol_id : Rollen-ID der Rolle, die geloescht werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_members.php');

// Initialize and check the parameters
$get_usr_id = admFuncVariableIsValid($_GET, 'user_id', 'numeric');
$get_rol_id = admFuncVariableIsValid($_GET, 'rol_id', 'numeric');
$get_mode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', 0);

if($get_mode == 1)
{
    // Userdaten aus Datenbank holen
    $user = new User($gDb, $gUserFields, $get_usr_id);

    header('Content-Type: text/x-vcard; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename="'. urlencode($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME')). '.vcf"');
	// noetig fuer IE, da ansonsten der Download mit SSL nicht funktioniert
	header('Cache-Control: private');
	header('Pragma: public');

    echo $user->getVCard();
}
elseif($get_mode == 2)
{
    // Mitgliedschaft bei einer aktuellen Rolle beenden
    if($gCurrentUser->assignRoles())
    {
        $member = new TableMembers($gDb);
        $member->stopMembership($get_rol_id, $get_usr_id);

        // Beendigung erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo "done";
    }
}
elseif($get_mode == 3)
{
    // Ehemalige Rollenzuordnung entfernen
    if($gCurrentUser->isWebmaster())
    {
        $member = new TableMembers($gDb);
        $member->readData(array('rol_id' => $get_rol_id, 'usr_id' => $get_usr_id));
        $member->delete();

        // Entfernen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo "done";
    }
}

?>