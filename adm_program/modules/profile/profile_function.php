<?php
/**
 ***********************************************************************************************
 * verschiedene Funktionen fuer das Profil
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
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
 *           8 - Export vCard of role
 * user_uuid   : UUID of the user to be edited
 * member_uuid : UUID of role membership that should be edited
 * role_uuid   : UUID of role from which the user vcards should be exported
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/roles_functions.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserUuid   = admFuncVariableIsValid($_GET, 'user_uuid',  'string');
$getRoleUuid   = admFuncVariableIsValid($_GET, 'role_uuid',  'string');
$getMemberUuid = admFuncVariableIsValid($_GET, 'member_uuid','string');
$getMode       = admFuncVariableIsValid($_GET, 'mode',       'int');

if(in_array($getMode, array(2, 3, 7))) {
    try {
        // in ajax mode only return simple text on error
        $gMessage->showHtmlTextOnly(true);

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showText();
        // => EXIT
    }
}

// create user object
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if($getMode === 1)
{
    // Export vCard of user

    $filename = $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME');

    $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.vcf';

    header('Content-Type: text/x-vcard; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // necessary for IE, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');

    // create vcard and check if user is allowed to edit profile, so he can see more data
    echo $user->getVCard();
}
elseif($getMode === 2)
{
    // Cancel membership of role
    $member = new TableMembers($gDb);
    $member->readDataByUuid($getMemberUuid);
    $role   = new TableRoles($gDb, (int) $member->getValue('mem_rol_id'));

    // if user has the right then cancel membership
    if($role->allowedToAssignMembers($gCurrentUser))
    {
        try
        {
            $member->stopMembership();
        }
        catch(AdmException $e)
        {
            $e->showText();
            // => EXIT
        }

        // Beendigung erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    else
    {
        echo $gL10n->get('SYS_NO_RIGHTS');
    }
}
elseif($getMode === 3)
{
    // Remove former membership of role
    if($gCurrentUser->isAdministrator())
    {
        $member = new TableMembers($gDb);
        $member->readDataByUuid($getMemberUuid);
        $member->delete();

        // Entfernen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($getMode === 4)
{
    // reload role memberships
    $roleStatement  = getRolesFromDatabase($user->getValue('usr_id'));
    $countRole      = $roleStatement->rowCount();
    echo getRoleMemberships('role_list', $user, $roleStatement);
}
elseif($getMode === 5)
{
    // reload former role memberships
    $roleStatement  = getFormerRolesFromDatabase($user->getValue('usr_id'));
    $countRole      = $roleStatement->rowCount();
    echo getRoleMemberships('former_role_list', $user, $roleStatement);

    if($countRole === 0)
    {
        echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'none\' })</script>';
    }
    else
    {
        echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'block\' })</script>';
    }
}
elseif($getMode === 6)
{
    // reload future role memberships
    $roleStatement  = getFutureRolesFromDatabase($user->getValue('usr_id'));
    $countRole      = $roleStatement->rowCount();
    echo getRoleMemberships('future_role_list', $user, $roleStatement);

    if($countRole === 0)
    {
        echo '<script type="text/javascript">$("#profile_future_roles_box").css({ \'display\':\'none\' })</script>';
    }
    else
    {
        echo '<script type="text/javascript">$("#profile_future_roles_box").css({ \'display\':\'block\' })</script>';
    }
}
elseif($getMode === 7)
{
    // save membership date changes
    $postMembershipStart = admFuncVariableIsValid($_POST, 'membership_start_date_'.$getMemberUuid, 'date', array('requireValue' => true));
    $postMembershipEnd   = admFuncVariableIsValid($_POST, 'membership_end_date_'.$getMemberUuid,   'date', array('requireValue' => true));

    $member = new TableMembers($gDb);
    $member->readDataByUuid($getMemberUuid);
    $role   = new TableRoles($gDb, (int) $member->getValue('mem_rol_id'));

    // check if user has the right to edit this membership
    if(!$role->allowedToAssignMembers($gCurrentUser))
    {
        exit($gL10n->get('SYS_NO_RIGHTS'));
    }

    $formatedStartDate = '';
    $formatedEndDate   = '';

    // Check das Beginn Datum
    $startDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $postMembershipStart);
    if($startDate === false)
    {
        exit($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_START'), $gSettingsManager->getString('system_date'))));
    }
    else
    {
        // Datum formatiert zurueckschreiben
        $formatedStartDate = $startDate->format('Y-m-d');
    }

    // Falls gesetzt wird das Enddatum gecheckt
    if($postMembershipEnd !== '')
    {
        $endDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $postMembershipEnd);
        if($endDate === false)
        {
            exit($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_END'), $gSettingsManager->getString('system_date'))));
        }
        else
        {
            // Datum formatiert zurueckschreiben
            $formatedEndDate = $endDate->format('Y-m-d');
        }

        // If start-date is later/bigger or on same day than end-date we show an error
        if ($formatedStartDate > $formatedEndDate)
        {
            exit($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        }
    }
    else
    {
        $formatedEndDate = DATE_MAX;
    }

    // save role membership
    $user->editRoleMembership($member->getValue('mem_id'), $formatedStartDate, $formatedEndDate);

    echo 'success';
}
elseif ($getMode === 8)
{
    // Export every member of a role into one vCard file

    $role = new TableRoles($gDb);
    $role->readDataByUuid($getRoleUuid);

    if($gCurrentUser->hasRightViewRole($role->getValue('rol_id')))
    {
        // create filename of organization name and role name
        $filename = $gCurrentOrganization->getValue('org_shortname'). '-'. str_replace('.', '', $role->getValue('rol_name')). '.vcf';

        $filename = FileSystemUtils::getSanitizedPathEntry($filename);

        header('Content-Type: text/x-vcard; charset=iso-8859-1');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $role->getValue(\'rol_id\')
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW';
        $pdoStatement = $gDb->queryPrepared($sql, array($role->getValue('rol_id'), DATE_NOW, DATE_NOW));

        while($memberUserId = $pdoStatement->fetchColumn())
        {
            // create user object
            $user = new User($gDb, $gProfileFields, (int) $memberUserId);
            // create vcard and check if user is allowed to edit profile, so he can see more data
            echo $user->getVCard();
        }
    }
}
