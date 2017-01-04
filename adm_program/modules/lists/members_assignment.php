<?php
/**
 ***********************************************************************************************
 * Assign or remove members to role
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode        - html   : Default mode to show a html list with all users to add them to the role
 *               assign : Add membership of a specific user to the role.
 * rol_id               : Id of role to which members should be assigned or removed
 * usr_id               : Id of the user whose membership should be assigned or removed
 * filter_rol_id        : If set only users from this role will be shown in list.
 * mem_show_all - true  : (Default) Show active and inactive members of all organizations in database
 *                false : Show only active members of the current organization
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

if(isset($_GET['mode']) && $_GET['mode'] === 'assign')
{
    // ajax mode then only show text if error occurs
    $gMessage->showTextOnly(true);
}

// Initialize and check the parameters
$getMode           = admFuncVariableIsValid($_GET, 'mode',          'string', array('defaultValue' => 'html', 'validValues' => array('html', 'assign')));
$getRoleId         = admFuncVariableIsValid($_GET, 'rol_id',        'int',    array('requireValue' => true, 'directOutput' => true));
$getUserId         = admFuncVariableIsValid($_GET, 'usr_id',        'int',    array('directOutput' => true));
$getFilterRoleId   = admFuncVariableIsValid($_GET, 'filter_rol_id', 'int');
$getMembersShowAll = admFuncVariableIsValid($_GET, 'mem_show_all',  'bool',   array('defaultValue' => false));

$_SESSION['set_rol_id'] = $getRoleId;

// create object of the commited role
$role = new TableRoles($gDb, $getRoleId);

// roles of other organizations can't be edited
if((int) $role->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// check if user is allowed to assign members to this role
if(!$role->allowedToAssignMembers($gCurrentUser))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if($getMembersShowAll)
{
    $getFilterRoleId = 0;
}

if($getFilterRoleId > 0 && !$gCurrentUser->hasRightViewRole($getFilterRoleId))
{
    $gMessage->show($gL10n->get('LST_NO_RIGHTS_VIEW_LIST'));
    // => EXIT
}

if($getMode === 'assign')
{
    // change membership of that user
    // this must be called as ajax request

    try
    {
        $membership = false;
        $leadership = false;

        if(isset($_POST['member_'.$getUserId]) && $_POST['member_'.$getUserId] === 'true')
        {
            $membership = true;
        }
        if(isset($_POST['leader_'.$getUserId]) && $_POST['leader_'.$getUserId] === 'true')
        {
            $membership = true;
            $leadership = true;
        }

        // Member
        $member = new TableMembers($gDb);

        // Datensatzupdate
        $mem_count = $role->countMembers($getUserId);

        // Wenn Rolle weniger mitglieder hätte als zugelassen oder Leiter hinzugefügt werden soll
        if($leadership || (!$leadership && $membership && ($role->getValue('rol_max_members') > $mem_count || (int) $role->getValue('rol_max_members') === 0)))
        {
            $member->startMembership((int) $role->getValue('rol_id'), $getUserId, $leadership);

            // find the parent roles and assign user to parent roles
            $dependencies = RoleDependency::getParentRoles($gDb, $role->getValue('rol_id'));
            $parentRoles  = array();

            foreach($dependencies as $tmpRole)
            {
                $member->startMembership($tmpRole, $getUserId);
            }
            echo 'success';
        }
        elseif(!$leadership && !$membership)
        {
            $member->stopMembership($role->getValue('rol_id'), $getUserId);
            echo 'success';
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', $role->getValue('rol_name')));
            // => EXIT
        }
    }
    catch(AdmException $e)
    {
        $e->showText();
        // => EXIT
    }
}
else
{
    // show html list with all users and their membership to this role

    // set headline of the script
    $headline = $gL10n->get('LST_MEMBER_ASSIGNMENT').' - '. $role->getValue('rol_name');

    // add current url to navigation stack if last url was not the same page
    if(strpos($gNavigation->getUrl(), 'members_assignment.php') === false)
    {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }

    // create html page object
    $page = new HtmlPage($headline);
    $page->enableModal();

    $javascriptCode = '';

    if($getMembersShowAll)
    {
        $javascriptCode .= '$("#mem_show_all").prop("checked", true);';
    }

    $javascriptCode .= '
        $("#menu_item_create_user").attr("data-toggle", "modal");
        $("#menu_item_create_user").attr("data-target", "#admidio_modal");

        // change mode of users that should be shown
        $("#filter_rol_id").change(function() {
            window.location.replace("'.ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$getRoleId.'&filter_rol_id=" + $("#filter_rol_id").val() + "&mem_show_all=0");
        });

        // change mode of users that should be shown
        $("#mem_show_all").click(function() {
            if ($("#mem_show_all").is(":checked")) {
                window.location.replace("'.ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$getRoleId.'&mem_show_all=1");
            } else {
                window.location.replace("'.ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$getRoleId.'&mem_show_all=0");
            }
        });

        // if checkbox of user is clicked then change membership
        $("#tbl_assign_role_membership").on("click", "input[type=checkbox].memlist_checkbox", function() {
            var checkbox = $(this);
            // get user id
            var row_id = $(this).attr("id");
            var pos = row_id.search("_");
            var userid = row_id.substring(pos+1);

            var member_checked = $("input[type=checkbox]#member_"+userid).prop("checked");
            var leader_checked = $("input[type=checkbox]#leader_"+userid).prop("checked");

            // Bei Leiter Checkbox setzten, muss Member mit gesetzt werden
            if (checkbox.hasClass("memlist_leader") && leader_checked) {
                $("input[type=checkbox]#member_"+userid).prop("checked", true);
                member_checked = true;
            }

            // Bei entfernen der Mitgliedschaft endet auch das Leiterdasein
            if (checkbox.hasClass("memlist_member") && member_checked == false) {
                $("input[type=checkbox]#leader_"+userid).prop("checked", false);
                leader_checked = false;
            }

            // change data in database
            $.post("'.ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?mode=assign&rol_id='.$getRoleId.'&usr_id="+userid,
                "member_"+userid+"="+member_checked+"&leader_"+userid+"="+leader_checked,
                function(data) {
                    // check if error occurs
                    if (data !== "success") {
                        // reset checkbox status
                        if (checkbox.prop("checked")) {
                            checkbox.prop("checked", false);
                            if (checkbox.hasClass("memlist_leader")) {
                                $("input[type=checkbox]#member_"+userid).prop("checked", false);
                            }
                        } else {
                            checkbox.prop("checked", true);
                        }

                        alert(data);
                        return false;
                    }
                    return true;
                }
            );
        });';

    $page->addJavascript($javascriptCode, true);

    // get module menu
    $membersAssignmentMenu = $page->getMenu();
    $membersAssignmentMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    if ($gCurrentUser->editUsers())
    {
        $membersAssignmentMenu->addItem('menu_item_create_user', ADMIDIO_URL.FOLDER_MODULES.'/members/members_new.php', $gL10n->get('MEM_CREATE_USER'), 'add.png');
    }
    $navbarForm = new HtmlForm('navbar_show_all_users_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
    $sql = 'SELECT rol_id, rol_name, cat_name
              FROM '.TBL_ROLES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid   = 1
               AND rol_visible = 1
               AND (  cat_org_id  = '.$gCurrentOrganization->getValue('org_id').'
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, rol_name';
    $navbarForm->addSelectBoxFromSql(
        'filter_rol_id', $gL10n->get('SYS_ROLE'), $gDb, $sql,
        array('defaultValue' => $getFilterRoleId, 'firstEntry' => $gL10n->get('SYS_ALL'))
    );
    $navbarForm->addCheckbox('mem_show_all', $gL10n->get('MEM_SHOW_ALL_USERS'), false, array('helpTextIdLabel' => 'MEM_SHOW_USERS_DESC'));
    $membersAssignmentMenu->addForm($navbarForm->show(false));

    // create table object
    $table = new HtmlTable('tbl_assign_role_membership', $page, true, true, 'table table-condensed');
    $table->setMessageIfNoRowsFound('SYS_NO_ENTRIES_FOUND');

    // create column header to assign role leaders
    $htmlLeaderColumn = $gL10n->get('SYS_LEADER');

    // show icon that leaders have no additional rights
    if($role->getValue('rol_leader_rights') == ROLE_LEADER_NO_RIGHTS)
    {
        $htmlLeaderColumn .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/info.png"
            alt="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" title="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" />';
    }

    // show icon with edit user right if leader has this right
    if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_EDIT
    || $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
    {
        $htmlLeaderColumn .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/profile_edit.png"
            alt="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" />';
    }

    // show icon with assign role right if leader has this right
    if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN
    || $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
    {
        $htmlLeaderColumn .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/roles.png"
            alt="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" />';
    }

    // create array with all column heading values
    $columnHeading = array(
        '<img class="admidio-icon-info"
            src="'. THEME_URL. '/icons/profile.png" alt="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname')).'"
            title="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname')).'" />',
        $gL10n->get('SYS_MEMBER'),
        $gL10n->get('SYS_LASTNAME'),
        $gL10n->get('SYS_FIRSTNAME'),
        '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/map.png"
            alt="'.$gL10n->get('SYS_ADDRESS').'" title="'.$gL10n->get('SYS_ADDRESS').'" />',
        $gL10n->get('SYS_BIRTHDAY'),
        $htmlLeaderColumn);

    $table->setServerSideProcessing(ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment_data.php?rol_id='.$getRoleId.'&filter_rol_id='.$getFilterRoleId.'&mem_show_all='.$getMembersShowAll);
    $table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'left', 'left', 'left'));
    $table->addRowHeadingByArray($columnHeading);

    $page->addHtml($table->show());
    $page->addHtml('<p>'.$gL10n->get('SYS_CHECKBOX_AUTOSAVE').'</p>');

    $page->show();
}
