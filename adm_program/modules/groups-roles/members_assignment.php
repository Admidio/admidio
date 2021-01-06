<?php
/**
 ***********************************************************************************************
 * Assign or remove members to role
 *
 * @copyright 2004-2021 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

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
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS_VIEW_LIST'));
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
        $memberApproved = null;

        // if its an event the user must attend to the event
        if($role->getValue('cat_name_intern') === 'EVENTS')
        {
            $memberApproved = 2;
        }

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
        $memCount = $role->countMembers($getUserId);

        // Wenn Rolle weniger mitglieder hätte als zugelassen oder Leiter hinzugefügt werden soll
        if($leadership || (!$leadership && $membership && ($role->getValue('rol_max_members') > $memCount || (int) $role->getValue('rol_max_members') === 0)))
        {
            $member->startMembership((int) $role->getValue('rol_id'), $getUserId, $leadership, $memberApproved);

            // find the parent roles and assign user to parent roles
            $dependencies = RoleDependency::getParentRoles($gDb, (int) $role->getValue('rol_id'));
            $parentRoles  = array();

            foreach($dependencies as $tmpRole)
            {
                $member->startMembership($tmpRole, $getUserId, null, $memberApproved);
            }
            echo 'success';
        }
        elseif(!$leadership && !$membership)
        {
            $member->stopMembership((int) $role->getValue('rol_id'), $getUserId);
            echo 'success';
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', array($role->getValue('rol_name'))));
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
    $headline = $gL10n->get('SYS_MEMBER_ASSIGNMENT').' - '. $role->getValue('rol_name');

    // add current url to navigation stack if last url was not the same page
    if(!str_contains($gNavigation->getUrl(), 'members_assignment.php'))
    {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }

    // create html page object
    $page = new HtmlPage('admidio-members-assignement', $headline);

    $javascriptCode = '';

    if($getMembersShowAll)
    {
        $javascriptCode .= '$("#mem_show_all").prop("checked", true);';
    }

    $javascriptCode .= '
        $("#menu_item_members_assign_create_user").attr("href", "javascript:void(0);");
        $("#menu_item_members_assign_create_user").attr("data-href", "'.ADMIDIO_URL.FOLDER_MODULES.'/members/members_new.php");
        $("#menu_item_members_assign_create_user").attr("class", "nav-link openPopup");

        // change mode of users that should be shown
        $("#filter_rol_id").change(function() {
            window.location.replace("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('rol_id' => $getRoleId, 'mem_show_all' => 0)).'&filter_rol_id=" + $("#filter_rol_id").val());
        });

        // change mode of users that should be shown
        $("#mem_show_all").click(function() {
            if ($("#mem_show_all").is(":checked")) {
                window.location.replace("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('rol_id' => $getRoleId, 'mem_show_all' => 1)).'");
            } else {
                window.location.replace("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('rol_id' => $getRoleId, 'mem_show_all' => 0)).'");
            }
        });

        // if checkbox of user is clicked then change membership
        $("#tbl_assign_role_membership").on("click", "input[type=checkbox].memlist_checkbox", function() {
            var checkbox = $(this);
            // get user id
            var rowId = $(this).attr("id");
            var pos = rowId.search("_");
            var userId = rowId.substring(pos + 1);

            var memberChecked = $("input[type=checkbox]#member_" + userId).prop("checked");
            var leaderChecked = $("input[type=checkbox]#leader_" + userId).prop("checked");

            // Bei Leiter Checkbox setzten, muss Member mit gesetzt werden
            if (checkbox.hasClass("memlist_leader") && leaderChecked) {
                $("input[type=checkbox]#member_" + userId).prop("checked", true);
                memberChecked = true;
            }

            // Bei entfernen der Mitgliedschaft endet auch das Leiterdasein
            if (checkbox.hasClass("memlist_member") && !memberChecked) {
                $("input[type=checkbox]#leader_" + userId).prop("checked", false);
                leaderChecked = false;
            }

            // change data in database
            $.post("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('mode' => 'assign', 'rol_id' => $getRoleId)).'&usr_id=" + userId,
                "member_" + userId + "=" + memberChecked + "&leader_" + userId + "=" + leaderChecked,
                function(data) {
                    // check if error occurs
                    if (data !== "success") {
                        // reset checkbox status
                        if (checkbox.prop("checked")) {
                            checkbox.prop("checked", false);
                            if (checkbox.hasClass("memlist_leader")) {
                                $("input[type=checkbox]#member_" + userId).prop("checked", false);
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

    if ($gCurrentUser->editUsers())
    {
        $page->addPageFunctionsMenuItem('menu_item_members_assign_create_user', $gL10n->get('MEM_CREATE_USER'),
            ADMIDIO_URL.FOLDER_MODULES.'/members/members_new.php', 'fa-plus-circle');
    }

    $sqlData['query'] = 'SELECT rol_id, rol_name, cat_name
                           FROM '.TBL_ROLES.'
                     INNER JOIN '.TBL_CATEGORIES.'
                             ON cat_id = rol_cat_id
                          WHERE rol_valid   = 1
                            AND cat_name_intern <> \'EVENTS\'
                            AND (  cat_org_id  = ? -- $gCurrentOrganization->getValue(\'org_id\')
                                OR cat_org_id IS NULL )
                       ORDER BY cat_sequence, rol_name';
    $sqlData['params'] = array((int) $gCurrentOrganization->getValue('org_id'));

    // create filter menu with elements for role
    $filterNavbar = new HtmlNavbar('navbar_filter', null, null, 'filter');
    $form = new HtmlForm('navbar_filter_form_roles', '', $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addSelectBoxFromSql(
        'filter_rol_id', $gL10n->get('SYS_ROLE'), $gDb, $sqlData,
        array('defaultValue' => $getFilterRoleId, 'firstEntry' => $gL10n->get('SYS_ALL'))
    );
    $form->addCheckbox('mem_show_all', $gL10n->get('MEM_SHOW_ALL_USERS'), false, array('helpTextIdLabel' => 'MEM_SHOW_USERS_DESC'));
    $filterNavbar->addForm($form->show());
    $page->addHtml($filterNavbar->show());

    // create table object
    $table = new HtmlTable('tbl_assign_role_membership', $page, true, true, 'table table-condensed');
    $table->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

    // create column header to assign role leaders
    $htmlLeaderColumn = $gL10n->get('SYS_LEADER');
    $htmlLeaderText   = '';

    // show icon that leaders have no additional rights
    if((int) $role->getValue('rol_leader_rights') === ROLE_LEADER_NO_RIGHTS)
    {
        $htmlLeaderText .= $gL10n->get('SYS_LEADER_NO_ADDITIONAL_RIGHTS');
    }

    // show icon with edit user right if leader has this right
    if((int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_EDIT
    || (int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
    {
        $htmlLeaderText .= $gL10n->get('SYS_LEADER_EDIT_MEMBERS');
    }

    // show icon with assign role right if leader has this right
    if((int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_ASSIGN
    || (int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
    {
        $htmlLeaderText .= $gL10n->get('SYS_LEADER_ASSIGN_MEMBERS');
    }

    $htmlLeaderColumn .= '<i class="fas fa-info-circle admidio-info-icon" data-toggle="popover" data-html="true" data-trigger="hover click" data-placement="auto"
            title="'.$gL10n->get('SYS_NOTE').'" data-content="' . SecurityUtils::encodeHTML($htmlLeaderText) . '"></i>';

    // create array with all column heading values
    $columnHeading = array(
        '<i class="fas fa-user" data-toggle="tooltip" title="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname'))).'"></i>',
        $gL10n->get('SYS_MEMBER'));
    $columnAlignment = array('left', 'left');

    if($gProfileFields->isVisible('LAST_NAME', $gCurrentUser->editUsers()))
    {
        $columnHeading[] = $gL10n->get('SYS_LASTNAME');
        $columnAlignment[] = 'left';
    }
    if($gProfileFields->isVisible('FIRST_NAME', $gCurrentUser->editUsers()))
    {
        $columnHeading[] = $gL10n->get('SYS_FIRSTNAME');
        $columnAlignment[] = 'left';
    }
    if($gProfileFields->isVisible('STREET', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('POSTCODE', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('CITY', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('COUNTRY', $gCurrentUser->editUsers()))
    {
        $columnHeading[] = '<i class="fas fa-map-marker-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_ADDRESS').'"></i>';
        $columnAlignment[] = 'left';
    }
    if($gProfileFields->isVisible('BIRTHDAY', $gCurrentUser->editUsers()))
    {
        $columnHeading[] = $gL10n->get('SYS_BIRTHDAY');
        $columnAlignment[] = 'left';
    }
    $columnHeading[] = $htmlLeaderColumn;
    $columnAlignment[] = 'left';

    $table->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment_data.php', array('rol_id' => $getRoleId, 'filter_rol_id' => $getFilterRoleId, 'mem_show_all' => $getMembersShowAll)));
    $table->setColumnAlignByArray($columnAlignment);
    $table->addRowHeadingByArray($columnHeading);

    $page->addHtml($table->show());
    $page->addHtml('<p>'.$gL10n->get('SYS_CHECKBOX_AUTOSAVE').'</p>');

    $page->show();
}
