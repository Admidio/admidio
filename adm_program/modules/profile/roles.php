<?php
/**
 ***********************************************************************************************
 * Show a list with all roles where the user can assign or remove membership
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * usr_id   : ID of the user whose roles should be edited
 * new_user : 0 - (Default) Edit roles of an existing user
 *            1 - Edit roles of a new user
 *            2 - (not relevant)
 *            3 - Edit roles of a registration
 * inline   : false - wird als eigene Seite angezeigt
 *            true  - nur "body" HTML Code
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id',   'int');
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'int');
$getInline  = admFuncVariableIsValid($_GET, 'inline',   'bool');

$html = '';

// if user is allowed to assign at least one role then allow access
if(!$gCurrentUser->assignRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$user = new User($gDb, $gProfileFields, $getUserId);

// set headline of the script
$headline = $gL10n->get('ROL_ROLE_ASSIGNMENT', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));

if(!$getInline)
{
    $gNavigation->addUrl(CURRENT_URL, $headline);
}
// Testen ob Feste Rolle gesetzt ist
if(isset($_SESSION['set_rol_id']))
{
    $setRoleId = $_SESSION['set_rol_id'];
    unset($_SESSION['set_rol_id']);
}
else
{
    $setRoleId = null;
}

if($getInline)
{
    header('Content-type: text/html; charset=utf-8');

    $html .= '<script type="text/javascript"><!--
    $(document).ready(function() {
        $(".admidio-group-heading").click(function() { showHideBlock($(this).attr("id")); });

        $("#roles_assignment_form").submit(function(event) {
            var action = $(this).attr("action");
            $("#roles_assignment_form .form-alert").hide();

            // disable default form submit
            event.preventDefault();

            $.ajax({
                type:    "POST",
                url:     action,
                data:    $(this).serialize(),
                success: function(data) {
                    if(data === "success") {
                        $("#roles_assignment_form .form-alert").attr("class", "alert alert-success form-alert");
                        $("#roles_assignment_form .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                        $("#roles_assignment_form .form-alert").fadeIn("slow");
                        setTimeout("$(\"#admidio_modal\").modal(\"hide\");",2000);

                        profileJS.reloadRoleMemberships();
                        profileJS.reloadFormerRoleMemberships();
                        profileJS.reloadFutureRoleMemberships();
                    } else {
                        $("#roles_assignment_form .form-alert").attr("class", "alert alert-danger form-alert");
                        $("#roles_assignment_form .form-alert").fadeIn();
                        $("#roles_assignment_form .form-alert").html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>"+data);
                    }
                }
            });
        });
    });
    --></script>

    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">'.$headline.'</h4>
    </div>
    <div class="modal-body">';

}
else
{
    // create html page object
    $page = new HtmlPage($headline);
    $page->addJavascriptFile($g_root_path.'/adm_program/modules/profile/profile.js');

    $page->addJavascript('var profileJS = new profileJSClass();');

    // add back link to module menu
    $rolesMenu = $page->getMenu();
    $rolesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}

// show headline of module
$html .= '<form id="roles_assignment_form" action="'.$g_root_path.'/adm_program/modules/profile/roles_save.php?usr_id='.$getUserId.'&amp;new_user='.$getNewUser.'&amp;inline='.$getInline.'" method="post">';

// Create table
$table = new HtmlTable('role_assignment_table');
$columnHeading = array(
    '&nbsp;',
    $gL10n->get('ROL_ROLE'),
    $gL10n->get('SYS_DESCRIPTION'),
    $gL10n->get('SYS_LEADER'));
$table->addRowHeadingByArray($columnHeading);
$table->setColumnAlignByArray(array('center', 'left', 'left', 'left'));

if($gCurrentUser->manageRoles())
{
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    $sql = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_visible, rol_leader_rights,
                     mem_rol_id, mem_usr_id, mem_leader
              FROM '.TBL_CATEGORIES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.'
                ON rol_id      = mem_rol_id
               AND mem_usr_id  = '.$getUserId.'
               AND mem_begin  <= \''.DATE_NOW.'\'
               AND mem_end     > \''.DATE_NOW.'\'
             WHERE rol_valid   = 1
               AND rol_visible = 1
               AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
             ORDER BY cat_sequence, cat_id, rol_name';
}
else
{
    // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
    $sql = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_visible, rol_leader_rights,
                     mgl.mem_rol_id as mem_rol_id, mgl.mem_usr_id as mem_usr_id, mgl.mem_leader as mem_leader
              FROM '.TBL_MEMBERS.' bm
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = bm.mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.' mgl
                ON rol_id         = mgl.mem_rol_id
               AND mgl.mem_usr_id = '.$getUserId.'
               AND mgl.mem_begin <= \''.DATE_NOW.'\'
               AND mgl.mem_end    > \''.DATE_NOW.'\'
             WHERE bm.mem_usr_id  = '. $gCurrentUser->getValue('usr_id'). '
               AND bm.mem_begin  <= \''.DATE_NOW.'\'
               AND bm.mem_end     > \''.DATE_NOW.'\'
               AND bm.mem_leader  = 1
               AND rol_leader_rights IN ('.ROLE_LEADER_MEMBERS_ASSIGN.','.ROLE_LEADER_MEMBERS_ASSIGN_EDIT.')
               AND rol_valid      = 1
               AND rol_visible    = 1
               AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
             ORDER BY cat_sequence, cat_id, rol_name';
}
$statement = $gDb->query($sql);
$category  = '';
$role      = new TableRoles($gDb);

while($row = $statement->fetch())
{
    $columnValues   = array();
    $memberChecked  = '';
    $memberDisabled = '';
    $leaderChecked  = '';
    $leaderDisabled = '';
    $role->setArray($row);

    if($role->getValue('rol_visible') == 1)
    {
        // if user is assigned to this role
        // or if user is created in members.php of list module
        if($row['mem_usr_id'] > 0 || $role->getValue('rol_id') == $setRoleId)
        {
            $memberChecked = ' checked="checked" ';
        }

        // if role is webmaster than only webmaster can add new user,
        // but don't change their own membership, because there must be at least one webmaster
        if($role->getValue('rol_webmaster') == 1
        && (!$gCurrentUser->isWebmaster()
        || ($gCurrentUser->isWebmaster() && $getUserId == $gCurrentUser->getValue('usr_id'))))
        {
            $memberDisabled = ' disabled="disabled" ';
        }

        // if user is flagged as leader than check the ckeckbox ;)
        if($row['mem_leader'] > 0)
        {
            $leaderChecked = ' checked="checked" ';
        }

        // the leader of webmaster role can only be set by a webmaster
        if($role->getValue('rol_webmaster') == 1 && !$gCurrentUser->isWebmaster())
        {
            $leaderDisabled = ' disabled="disabled" ';
        }

        $columnValues = array(
            '<input type="checkbox" id="role-'.$role->getValue('rol_id').'" name="role-'.$role->getValue('rol_id').'" '.
                $memberChecked.$memberDisabled.' onclick="javascript:profileJS.unMarkLeader(this);" value="1" />',
            '<label for="role-'.$role->getValue('rol_id').'">'.$role->getValue('rol_name').'</label>',
            $role->getValue('rol_description'));

        // if new category than display a category header
        if($category != $role->getValue('cat_id'))
        {
            $block_id = 'admCategory'.$role->getValue('cat_id');

            $table->addTableBody();
            $table->addRow('', array('class' => 'admidio-group-heading', 'id' => 'group_'.$block_id));
            $table->addColumn();
            $table->addAttribute('colspan', '4', 'td');
            $table->addData('<span id="caret_'.$block_id.'" class="caret"></span>'.$role->getValue('cat_name'));
            $table->addTableBody('id', $block_id);

            $category = $role->getValue('cat_id');
        }

        $leaderRights = '<input type="checkbox" id="leader-'.$role->getValue('rol_id').'" name="leader-'.$role->getValue('rol_id').'" '.
                           $leaderChecked.$leaderDisabled.' onclick="javascript:profileJS.markLeader(this);" value="1" />';

        // show icon that leaders have no additional rights
        if($role->getValue('rol_leader_rights') == ROLE_LEADER_NO_RIGHTS)
        {
            $leaderRights .= '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/info.png"
                                 alt="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" title="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" />
                                     <img class="admidio-icon-link" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
        }

        // show icon with edit user right if leader has this right
        if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_EDIT
        || $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
        {
            $leaderRights .= '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/profile_edit.png"
                                 alt="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" />';
        }

        // show icon with assign role right if leader has this right
        if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN
        || $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
        {
            $leaderRights .= '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/roles.png"
                                 alt="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" />';
        }

        // show dummy icon if leader has not all rights
        if($role->getValue('rol_leader_rights') != ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
        {
            $leaderRights .= '<img class="admidio-icon-link" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
        }
        $columnValues[] = $leaderRights;

        $table->addRowByArray($columnValues);
    }
}
$html .= $table->show(false);

$html .= '
    <button class="btn-primary btn" id="btn_save" type="submit"><img src="'.THEME_PATH.'/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>';

if($getInline)
{
    echo $html.'</div>';
}
else
{
    $page->addHtml($html);
    $page->show();
}
