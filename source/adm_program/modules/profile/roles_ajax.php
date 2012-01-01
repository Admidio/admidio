<?php
/******************************************************************************
 * Roles Ajax
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id: UserId of the user to be edited
 * rol_id : RoleId of the role to be edited
 * action :  0 ... reload Role Memberships
 *           1 ... former reload Role Memberships
 *           2 ... Daten speichern
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_members.php');
require_once('roles_functions.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getAction = admFuncVariableIsValid($_GET, 'action', 'numeric', 0);

// User auslesen
$user = new User($gDb, $gProfileFields, $getUserId);

switch($getAction)
{
    case 0: // reload Role Memberships
        $count_show_roles 	= 0;
        $result_role 		= getRolesFromDatabase($gDb,$getUserId,$gCurrentOrganization);
        $count_role  		= $gDb->num_rows($result_role);
        getRoleMemberships($gDb,$gCurrentUser,$user,$result_role,$count_role,true,$gL10n);
    break;

    case 1: // former reload Role Memberships
        $count_show_roles 	= 0;
        $result_role 		= getFormerRolesFromDatabase($gDb,$getUserId,$gCurrentOrganization);
        $count_role  		= $gDb->num_rows($result_role);
        getFormerRoleMemberships($gDb,$gCurrentUser,$user,$result_role,$count_role,true,$gL10n);
        if($count_role == 0)
        {
            echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'none\' })</script>';
        }
        else
        {
            echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'block\' })</script>';
        }
    break;

    case 2: // save Date changes
        if(!$gCurrentUser->assignRoles())
        {
            die($gL10n->get('SYS_NO_RIGHTS'));
        }

        //Einlesen der Mitgliedsdaten
        $mem = new TableMembers($gDb);
        $mem->readData(array('rol_id' => $getRoleId, 'usr_id' => $getUserId));
         
        //Check das Beginn Datum
        $startDate = new DateTimeExtended($_GET['rol_begin'], $gPreferences['system_date'], 'date');
        if($startDate->valid())
        {
            // Datum formatiert zurueckschreiben
            $mem->setValue('mem_begin', $startDate->format('Y-m-d'));
        }
        else
        {
            die($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
        }

        //Falls gesetzt wird das Enddatum gecheckt
        if(strlen($_GET['rol_end']) > 0) 
        {
            $endDate = new DateTimeExtended($_GET['rol_end'], $gPreferences['system_date'], 'date');
            if($endDate->valid())
            {
                // Datum formatiert zurueckschreiben
                $mem->setValue('mem_end', $endDate->format('Y-m-d'));
            }
            else
            {
                die($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_END'), $gPreferences['system_date']));
            }

            // Enddatum muss groesser oder gleich dem Startdatum sein (timestamp dann umgekehrt kleiner)
            if ($startDate->getTimestamp() > $endDate->getTimestamp()) 
            {
                die($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
            }
        }
        else 
        {
            $mem->setValue('mem_end', '9999-12-31');
        }
        
        $mem->save();

        echo $gL10n->get('SYS_SAVE_DATA')."<SAVED/>";;
    break;
}
?>