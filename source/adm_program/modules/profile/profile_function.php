<?php
/******************************************************************************
 * verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode   :  1 - Export vCard of user
 *           2 - Cancel membership of role
 *           3 - Remove former membership of role
 *           4 - reload Role Memberships
 *           5 - reload former role memberships
 *           6 - reload future role memberships
 *           7 - save membership data
 * user_id : Id of the user to be edited
 * mem_id  : Id of role membership to should be edited
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('roles_functions.php');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'user_id', 'numeric');
$getMemberId = admFuncVariableIsValid($_GET, 'mem_id', 'numeric');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', 0);
	
// create user object
$user = new User($gDb, $gProfileFields, $getUserId);

if($getMode == 1)
{
	// Export vCard of user

    $filename = $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME');
    
    // for IE the filename must have special chars in hexadecimal 
    if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
    {
        $filename = urlencode($filename);
    }
    
    header('Content-Type: text/x-vcard; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename="'.$filename.'.vcf"');
    
    // neccessary for IE, because without it the download with SSL has problems
	header('Cache-Control: private');
	header('Pragma: public');

	// create vcard and check if user is allowed to edit profile, so he can see more data
    echo $user->getVCard($gCurrentUser->editProfile($user));
}
elseif($getMode == 2)
{
    // Cancel membership of role
	$member = new TableMembers($gDb, $getMemberId);
	$role   = new TableRoles($gDb, $member->getValue('mem_rol_id'));

	// if user has the right then cancel membership
	if($role->allowedToAssignMembers($gCurrentUser))
    {
        $member->stopMembership();

        // Beendigung erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($getMode == 3)
{
    // Remove former membership of role
    if($gCurrentUser->isWebmaster())
    {
        $member = new TableMembers($gDb, $getMemberId);
        $member->delete();

        // Entfernen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($getMode == 4) 
{
	// reload role memberships
	$count_show_roles 	= 0;
	$result_role 		= getRolesFromDatabase($getUserId);
	$count_role  		= $gDb->num_rows($result_role);
	getRoleMemberships('role_list',$user,$result_role,$count_role,true);
}
elseif($getMode == 5) 
{
	// reload former role memberships
	$count_show_roles 	= 0;
	$result_role 		= getFormerRolesFromDatabase($getUserId);
	$count_role  		= $gDb->num_rows($result_role);
	getRoleMemberships('former_role_list',$user,$result_role,$count_role,true);

	if($count_role == 0)
	{
		echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'none\' })</script>';
	}
	else
	{
		echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'block\' })</script>';
	}
}
elseif($getMode == 6)
{
	// reload future role memberships
	$count_show_roles 	= 0;
	$result_role 		= getFutureRolesFromDatabase($getUserId);
	$count_role  		= $gDb->num_rows($result_role);
	getRoleMemberships('future_role_list',$user,$result_role,$count_role,true);

	if($count_role == 0)
	{
		echo '<script type="text/javascript">$("#profile_future_roles_box").css({ \'display\':\'none\' })</script>';
	}
	else
	{
		echo '<script type="text/javascript">$("#profile_future_roles_box").css({ \'display\':\'block\' })</script>';
	}
}
elseif($getMode == 7)
{
	// save membership date changes
	$member = new TableMembers($gDb, $getMemberId);
	$role   = new TableRoles($gDb, $member->getValue('mem_rol_id'));

	// check if user has the right to edit this membership
	if($role->allowedToAssignMembers($gCurrentUser) == false)
	{
		die($gL10n->get('SYS_NO_RIGHTS'));
	}
	
	$formatedStartDate = '';
	$formatedEndDate   = '';
	
	//Check das Beginn Datum
	$startDate = new DateTimeExtended($_GET['rol_begin'], $gPreferences['system_date'], 'date');
	if($startDate->valid())
	{
		// Datum formatiert zurueckschreiben
		$formatedStartDate = $startDate->format('Y-m-d');
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
			$formatedEndDate = $endDate->format('Y-m-d');
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
		$formatedEndDate = '9999-12-31';
	}
	
	// save role membership
	$user->editRoleMembership($getMemberId, $formatedStartDate, $formatedEndDate);

	echo $gL10n->get('SYS_SAVE_DATA')."<SAVED/>";;
}

?>