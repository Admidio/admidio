<?php
/******************************************************************************
 * Roles Ajax
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id: zeigt das Profil der uebergebenen user_id an
 *          (wird keine user_id uebergeben, dann Profil des eingeloggten Users anzeigen)
 * action:  0 ... reload Role Memberships
 *          1 ... former reload Role Memberships
 *          2 ... Daten speichern
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once("../../system/classes/table_members.php");
require_once('roles_functions.php');

$action = 0;

// Uebergabevariablen pruefen
if(isset($_GET['user_id']))
{
    if(is_numeric($_GET['user_id']) == false)
    {
        die($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    // Daten des uebergebenen Users anzeigen
    $a_user_id = $_GET['user_id'];
}
else
{
    // wenn nichts uebergeben wurde, dann eigene Daten anzeigen
    $a_user_id = $g_current_user->getValue('usr_id');
}

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($a_user_id))
{
    die($g_l1n0->get('SYS_PHR_NO_RIGHTS'));
}

if(isset($_GET['action']))
    {
    if(is_numeric($_GET['action']) == false)
    {
        die($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    else
    {
        $action = $_GET['action'];
    }
}

// User auslesen
$user = new User($g_db, $a_user_id);

switch($action)
{
    case 0: // reload Role Memberships
        $count_show_roles 	= 0;
        $result_role 		= getRolesFromDatabase($g_db,$a_user_id,$g_current_organization);
        $count_role  		= $g_db->num_rows($result_role);
        getRoleMemberships($g_db,$g_current_user,$user,$result_role,$count_role,true,$g_l10n);
    break;

    case 1: // former reload Role Memberships
        $count_show_roles 	= 0;
        $result_role 		= getFormerRolesFromDatabase($g_db,$a_user_id,$g_current_organization);
        $count_role  		= $g_db->num_rows($result_role);
        getFormerRoleMemberships($g_db,$g_current_user,$user,$result_role,$count_role,true,$g_l10n);
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
        if(!$g_current_user->assignRoles())
        {
            die($g_l10n->get('SYS_PHR_NO_RIGHTS'));
        }
        // Uebergabevariablen pruefen
        if(isset($_GET['usr_id']) && is_numeric($_GET['usr_id']) == false)
        {
            die($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }
        
        if(isset($_GET['rol_id']) && is_numeric($_GET['rol_id']) == false)
        {
            die($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }

        //Einlesen der Mitgliedsdaten
        $mem = new TableMembers($g_db);
        $mem->readData(array('rol_id' => $_GET['rol_id'], 'usr_id' => $_GET['usr_id']));
         
        //Check das Beginn Datum
        $startDate = new DateTimeExtended($_GET['rol_begin'], $g_preferences['system_date'], 'date');
        if($startDate->valid())
        {
            // Datum formatiert zurueckschreiben
            $mem->setValue('mem_begin', $startDate->format('Y-m-d'));
        }
        else
        {
            die($g_l10n->get('SYS_PHR_DATE_INVALID', $g_l10n->get('SYS_START'), $g_preferences['system_date']));
        }

        //Falls gesetzt wird das Enddatum gecheckt
        if(strlen($_GET['rol_end']) > 0) 
        {
            $endDate = new DateTimeExtended($_GET['rol_end'], $g_preferences['system_date'], 'date');
            if($endDate->valid())
            {
                // Datum formatiert zurueckschreiben
                $mem->setValue('mem_end', $endDate->format('Y-m-d'));
            }
            else
            {
                die($g_l10n->get('SYS_PHR_DATE_INVALID', $g_l10n->get('SYS_END'), $g_preferences['system_date']));
            }

            // Enddatum muss groesser oder gleich dem Startdatum sein (timestamp dann umgekehrt kleiner)
            if ($startDate->getTimestamp() > $endDate->getTimestamp()) 
            {
                die($g_l10n->get('SYS_PHR_DATE_END_BEFORE_BEGIN'));
            }
        }
        else 
        {
            $mem->setValue('mem_end', '9999-12-31');
        }
        
        $mem->save();

        echo $g_l10n->get('SYS_PHR_SAVE')."<SAVED/>";;
    break;
}
?>