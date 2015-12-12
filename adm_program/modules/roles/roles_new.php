<?php
/**
 ***********************************************************************************************
 * Create and edit roles
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * rol_id: ID of role, that should be edited
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric');

// Initialize local parameters
$showSystemCategory = false;

// only users with the special right are allowed to manage roles
if(!$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if($getRoleId > 0)
{
    $headline = $gL10n->get('ROL_EDIT_ROLE');
}
else
{
    $headline = $gL10n->get('SYS_CREATE_ROLE');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

if($getRoleId > 0)
{
    $role->readDataById($getRoleId);

    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
    if($role->getValue('rol_webmaster') == 1 && !$gCurrentUser->isWebmaster())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // hidden roles can also see hidden categories
    if($role->getValue('cat_system') == 1)
    {
        $showSystemCategory = true;
    }
}
else
{
    $role->setValue('rol_this_list_view', '1');
    $role->setValue('rol_mail_this_role', '2');
}

if(isset($_SESSION['roles_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $role->setArray($_SESSION['roles_request']);
    unset($_SESSION['roles_request']);
}

// holt eine Liste der ausgewaehlten abhaengigen Rolen
$childRoles = RoleDependency::getChildRoles($gDb, $getRoleId);

$childRoleObjects = array();

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
    checkMaxMemberCount();
    $("#rol_assign_roles").change(function() {
        markRoleRight("rol_assign_roles", "rol_all_lists_view", true);
    });
    $("#rol_all_lists_view").change(function() {
        markRoleRight(\'rol_all_lists_view\', \'rol_assign_roles\', false);
    });
    $("#rol_max_members").change(function() {
        checkMaxMemberCount();
    });',
    true
);

$page->addJavascript('
    // show/hide role dependencies if max count members will be changed
    function checkMaxMemberCount() {
        // Wenn eine Maximale Mitgliederzahl angeben wurde, duerfen keine Rollenabhaengigkeiten bestehen
        if($("#rol_max_members").val() > 0) {
            // Die Box zum konfigurieren der Rollenabhängig wird ausgeblendet
            $("#gb_dependencies").hide();

            // Alle Abhängigen Rollen werden markiert und auf unabhängig gesetzt
            $("#dependent_roles").val("");
        } else {
            // Die Box zum konfigurieren der Rollenabhängigkeit wird wieder eingeblendet
            $("#gb_dependencies").show();
        }
    }

    // Set dependent role right if another role right changed
    // srcRight  - ID des Rechts, welches das Ereignis ausloest
    // destRight - ID des Rechts, welches angepasst werden soll
    // checked   - true destRight wird auf checked gesetzt
    //             false destRight wird auf unchecked gesetzt
    function markRoleRight(srcRight, destRight, checked) {
        if (document.getElementById(srcRight).checked && checked) {
            document.getElementById(destRight).checked = true;
        }
        if (!document.getElementById(srcRight).checked && !checked) {
            document.getElementById(destRight).checked = false;
        }
    }'
);

// add back link to module menu
$rolesEditMenu = $page->getMenu();
$rolesEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('roles_edit_form', $g_root_path.'/adm_program/modules/roles/roles_function.php?rol_id='.$getRoleId.'&amp;mode=2', $page);
$form->openGroupBox('gb_name_category', $gL10n->get('SYS_NAME').' & '.$gL10n->get('SYS_CATEGORY'));
if($role->getValue('rol_webmaster') == 1)
{
    $form->addInput('rol_name', $gL10n->get('SYS_NAME'), $role->getValue('rol_name'),
                    array('maxLength' => 100, 'property' => FIELD_READONLY));
}
else
{
    $form->addInput('rol_name', $gL10n->get('SYS_NAME'), $role->getValue('rol_name'),
                    array('maxLength' => 100, 'property' => FIELD_REQUIRED));
}
$form->addMultilineTextInput('rol_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'), 3,
                             array('maxLength' => 4000));
$form->addSelectBoxForCategories('rol_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', 'EDIT_CATEGORIES',
                                 array('property' => FIELD_REQUIRED, 'defaultValue' => $role->getValue('rol_cat_id')));
$form->closeGroupBox();
$form->openGroupBox('gb_properties', $gL10n->get('SYS_PROPERTIES'));
if($gPreferences['enable_mail_module'])
{
    $selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('ROL_ONLY_ROLE_MEMBERS'), 2 => $gL10n->get('ROL_ALL_MEMBERS'), 3 => $gL10n->get('ROL_ALL_GUESTS'));
    $form->addSelectBox('rol_mail_this_role', $gL10n->get('ROL_SEND_MAILS'), $selectBoxEntries,
                        array('defaultValue' => $role->getValue('rol_mail_this_role'), 'showContextDependentFirstEntry' => false, 'helpTextIdLabel' => array('ROL_RIGHT_MAIL_THIS_ROLE_DESC', $gL10n->get('ROL_RIGHT_MAIL_TO_ALL'))));
}
$selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('ROL_ONLY_ROLE_MEMBERS'), 2 => $gL10n->get('ROL_ALL_MEMBERS'));
$form->addSelectBox('rol_this_list_view', $gL10n->get('ROL_SEE_ROLE_MEMBERSHIP'), $selectBoxEntries,
                    array('defaultValue' => $role->getValue('rol_this_list_view'), 'showContextDependentFirstEntry' => false, 'helpTextIdLabel' => array('ROL_RIGHT_THIS_LIST_VIEW_DESC', $gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW'))));
$selectBoxEntries = array(0 => $gL10n->get('ROL_NO_ADDITIONAL_RIGHTS'), 1 => $gL10n->get('SYS_ASSIGN_MEMBERS'), 2 => $gL10n->get('SYS_EDIT_MEMBERS'), 3 => $gL10n->get('ROL_ASSIGN_EDIT_MEMBERS'));
$form->addSelectBox('rol_leader_rights', $gL10n->get('SYS_LEADER'), $selectBoxEntries,
                    array('defaultValue' => $role->getValue('rol_leader_rights'), 'showContextDependentFirstEntry' => false, 'helpTextIdLabel' => 'ROL_LEADER_RIGHTS_DESC'));

$selectBoxEntries = array(0 => $gL10n->get('ROL_SYSTEM_DEFAULT_LIST'));
// SQL-Statement fuer alle Listenkonfigurationen vorbereiten, die angezeigt werdne sollen
$sql = 'SELECT lst_id, lst_name FROM '. TBL_LISTS. '
         WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id'). '
           AND lst_global = 1
           AND lst_name IS NOT NULL
         ORDER BY lst_global ASC, lst_name ASC';
$pdoStatement = $gDb->query($sql);

while($row = $pdoStatement->fetch())
{
    $selectBoxEntries[$row['lst_id']] = $row['lst_name'];
}
$form->addSelectBox('rol_lst_id', $gL10n->get('ROL_DEFAULT_LIST'), $selectBoxEntries,
                    array('defaultValue' => $role->getValue('rol_lst_id'), 'showContextDependentFirstEntry' => false, 'helpTextIdLabel' => 'ROL_DEFAULT_LIST_DESC'));
$form->addCheckbox('rol_default_registration', $gL10n->get('ROL_DEFAULT_REGISTRATION'), $role->getValue('rol_default_registration'),
                   array('helpTextIdLabel' => 'ROL_DEFAULT_REGISTRATION_DESC'));
$form->addInput('rol_max_members', $gL10n->get('SYS_MAX_PARTICIPANTS').'<br />('.$gL10n->get('ROL_WITHOUT_LEADER').')', $role->getValue('rol_max_members'),
                array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999));
$form->addInput('rol_cost', $gL10n->get('SYS_CONTRIBUTION').' '.$gPreferences['system_currency'], $role->getValue('rol_cost'),
                array('maxLength' => 6, 'class' => 'form-control-small'));
$form->addSelectBox('rol_cost_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), $role->getCostPeriods(),
                    array('defaultValue' => $role->getValue('rol_cost_period')));
$form->closeGroupBox();
$form->openGroupBox('gb_authorization', $gL10n->get('SYS_AUTHORIZATION'));
$form->addCheckbox('rol_assign_roles', $gL10n->get('ROL_RIGHT_ASSIGN_ROLES'), $role->getValue('rol_assign_roles'),
                   array('helpTextIdLabel' => 'ROL_RIGHT_ASSIGN_ROLES_DESC', 'icon' => 'roles.png'));
$form->addCheckbox('rol_all_lists_view', $gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW'), $role->getValue('rol_all_lists_view'),
                   array('icon' => 'lists.png'));
$form->addCheckbox('rol_approve_users', $gL10n->get('ROL_RIGHT_APPROVE_USERS'), $role->getValue('rol_approve_users'),
                   array('icon' => 'new_registrations.png'));
$form->addCheckbox('rol_edit_user', $gL10n->get('ROL_RIGHT_EDIT_USER'), $role->getValue('rol_edit_user'),
                   array('helpTextIdLabel' => 'ROL_RIGHT_EDIT_USER_DESC', 'icon' => 'group.png'));
if($gPreferences['enable_mail_module'] > 0)
{
    $form->addCheckbox('rol_mail_to_all', $gL10n->get('ROL_RIGHT_MAIL_TO_ALL'), $role->getValue('rol_mail_to_all'),
                       array('icon' => 'email.png'));
}
$form->addCheckbox('rol_profile', $gL10n->get('ROL_RIGHT_PROFILE'), $role->getValue('rol_profile'),
                   array('icon' => 'profile.png'));
if($gPreferences['enable_announcements_module'] > 0)
{
    $form->addCheckbox('rol_announcements', $gL10n->get('ROL_RIGHT_ANNOUNCEMENTS'), $role->getValue('rol_announcements'),
                       array('icon' => 'announcements.png'));
}
if($gPreferences['enable_dates_module'] > 0)
{
    $form->addCheckbox('rol_dates', $gL10n->get('ROL_RIGHT_DATES'), $role->getValue('rol_dates'),
                       array('icon' => 'dates.png'));
}
if($gPreferences['enable_photo_module'] > 0)
{
    $form->addCheckbox('rol_photo', $gL10n->get('ROL_RIGHT_PHOTO'), $role->getValue('rol_photo'),
                       array('icon' => 'photo.png'));
}
if($gPreferences['enable_download_module'] > 0)
{
    $form->addCheckbox('rol_download', $gL10n->get('ROL_RIGHT_DOWNLOAD'), $role->getValue('rol_download'),
                       array('icon' => 'download.png'));
}
if($gPreferences['enable_guestbook_module'] > 0)
{
    $form->addCheckbox('rol_guestbook', $gL10n->get('ROL_RIGHT_GUESTBOOK'), $role->getValue('rol_guestbook'),
                       array('icon' => 'guestbook.png'));
    // if not registered users can set comments than there is no need to set a role dependent right
    if(!$gPreferences['enable_gbook_comments4all'])
    {
        $form->addCheckbox('rol_guestbook_comments', $gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS'), $role->getValue('rol_guestbook_comments'),
                           array('icon' => 'comment.png'));
    }
}
if($gPreferences['enable_weblinks_module'] > 0)
{
    $form->addCheckbox('rol_weblinks', $gL10n->get('ROL_RIGHT_WEBLINKS'), $role->getValue('rol_weblinks'),
                       array('icon' => 'weblinks.png'));
}
/*if($gPreferences['enable_inventory_module'] > 0)
{
    $form->addCheckbox('rol_inventory', $gL10n->get('ROL_RIGHT_INVENTORY'), $role->getValue('rol_inventory'), array('icon' => 'inventory.png'));
}*/
$form->closeGroupBox();
$form->openGroupBox('gb_dates_meetings', $gL10n->get('DAT_DATES').' / '.$gL10n->get('ROL_MEETINGS').'&nbsp;&nbsp;('.$gL10n->get('SYS_OPTIONAL').')');
$form->addInput('rol_start_date', $gL10n->get('ROL_VALID_FROM'), $role->getValue('rol_start_date'), array('type' => 'date'));
$form->addInput('rol_end_date', $gL10n->get('ROL_VALID_TO'), $role->getValue('rol_end_date'), array('type' => 'date'));
$form->addInput('rol_start_time', $gL10n->get('SYS_TIME_FROM'), $role->getValue('rol_start_time'), array('type' => 'time'));
$form->addInput('rol_end_time', $gL10n->get('SYS_TIME_TO'), $role->getValue('rol_end_time'), array('type' => 'time'));
$form->addSelectBox('rol_weekday', $gL10n->get('ROL_WEEKDAY'), DateTimeExtended::getWeekdays(), array('defaultValue' => $role->getValue('rol_weekday')));
$form->addInput('rol_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'), array('maxLength' => 100));
$form->closeGroupBox();

$form->openGroupBox('gb_dependencies', $gL10n->get('ROL_DEPENDENCIES').'&nbsp;&nbsp;('.$gL10n->get('SYS_OPTIONAL').')');
$rolename_var = $gL10n->get('ROL_NEW_ROLE');
if($role->getValue('rol_name') !== '')
{
    $rolename_var = $gL10n->get('SYS_ROLE').' <b>'.$role->getValue('rol_name').'</b>';
}
$form->addHtml('<p>'.$gL10n->get('ROL_ROLE_DEPENDENCIES', $rolename_var).'</p>');

//  list all roles that the user is allowed to see
$sqlAllRoles = '
    SELECT rol_id, rol_name, cat_name
      FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
     WHERE rol_valid   = 1
       AND rol_visible = 1
       AND rol_cat_id  = cat_id
       AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
           OR cat_org_id IS NULL )
     ORDER BY cat_sequence, rol_name ';

$form->addSelectBoxFromSql('dependent_roles', $gL10n->get('ROL_DEPENDENT'), $gDb, $sqlAllRoles,
                           array('defaultValue' => $childRoles, 'multiselect' => true));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($role->getValue('rol_usr_id_create'), $role->getValue('rol_timestamp_create'), $role->getValue('rol_usr_id_change'), $role->getValue('rol_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
