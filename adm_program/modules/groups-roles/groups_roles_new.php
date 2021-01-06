<?php
/**
 ***********************************************************************************************
 * Create and edit roles
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * rol_id: ID of role, that should be edited
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'int');

// Initialize local parameters
$showSystemCategory = false;

// only users with the special right are allowed to manage roles
if(!$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if($getRoleId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_ROLE');
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
    if((int) $role->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // Administrator role could only be created or edited by administrators
    if($role->getValue('rol_administrator') == 1 && !$gCurrentUser->isAdministrator())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // hidden roles can also see hidden categories
    if($role->getValue('cat_system') == 1)
    {
        $showSystemCategory = true;
    }
}
else
{
    $role->setValue('rol_this_list_view', 1);
    $role->setValue('rol_mail_this_role', 2);
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
$page = new HtmlPage('admidio-groups-roles-edit', $headline);

$page->addJavascript('
    checkMaxMemberCount();
    $("#rol_assign_roles").change(function() {
        markRoleRight("rol_assign_roles", "rol_all_lists_view", true);
    });
    $("#rol_all_lists_view").change(function() {
        markRoleRight("rol_all_lists_view", "rol_assign_roles", false);
    });
    $("#rol_max_members").change(function() {
        checkMaxMemberCount();
    });',
    true
);

$page->addJavascript('
    /**
     * show/hide role dependencies if max count members will be changed
     */
    function checkMaxMemberCount() {
        // Wenn eine Maximale Mitgliederzahl angeben wurde, duerfen keine Rollenabhaengigkeiten bestehen
        if ($("#rol_max_members").val() > 0) {
            // Die Box zum konfigurieren der Rollenabhängig wird ausgeblendet
            $("#gb_dependencies").hide();

            // Alle Abhängigen Rollen werden markiert und auf unabhängig gesetzt
            $("#dependent_roles").val("");
        } else {
            // Die Box zum konfigurieren der Rollenabhängigkeit wird wieder eingeblendet
            $("#gb_dependencies").show();
        }
    }

    /**
     * Set dependent role right if another role right changed
     * @param {string} srcRight  ID des Rechts, welches das Ereignis ausloest
     * @param {string} destRight ID des Rechts, welches angepasst werden soll
     * @param {bool}   checked   true destRight wird auf checked gesetzt
     *                           false destRight wird auf unchecked gesetzt
     */
    function markRoleRight(srcRight, destRight, checked) {
        if (document.getElementById(srcRight).checked && checked) {
            document.getElementById(destRight).checked = true;
        }
        if (!document.getElementById(srcRight).checked && !checked) {
            document.getElementById(destRight).checked = false;
        }
    }
');

// show form
$form = new HtmlForm('roles_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_function.php', array('rol_id' => $getRoleId, 'mode' => '2')), $page);
$form->openGroupBox('gb_name_category', $gL10n->get('SYS_NAME').' & '.$gL10n->get('SYS_CATEGORY'));
if($role->getValue('rol_administrator') == 1)
{
    $form->addInput(
        'rol_name', $gL10n->get('SYS_NAME'), $role->getValue('rol_name'),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_READONLY)
    );
}
else
{
    $form->addInput(
        'rol_name', $gL10n->get('SYS_NAME'), $role->getValue('rol_name'),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
    );
}
$form->addMultilineTextInput(
    'rol_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'), 3,
    array('maxLength' => 4000)
);
$form->addSelectBoxForCategories(
    'rol_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => (int) $role->getValue('rol_cat_id'))
);
$form->closeGroupBox();
$form->openGroupBox('gb_properties', $gL10n->get('SYS_PROPERTIES'));
if($gSettingsManager->getBool('enable_mail_module'))
{
    $selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'), 3 => $gL10n->get('SYS_ALSO_VISITORS'));
    $form->addSelectBox(
        'rol_mail_this_role', $gL10n->get('SYS_SEND_MAILS'), $selectBoxEntries,
        array(
            'defaultValue'                   => $role->getValue('rol_mail_this_role'),
            'showContextDependentFirstEntry' => false,
            'helpTextIdLabel'                => $gL10n->get('SYS_RIGHT_MAIL_THIS_ROLE_DESC', array('SYS_RIGHT_MAIL_TO_ALL'))
        )
    );
}
$selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'));
$form->addSelectBox(
    'rol_this_list_view', $gL10n->get('SYS_DISPLAY_ROLE_MEMBERSHIP'), $selectBoxEntries,
    array(
        'defaultValue'                   => $role->getValue('rol_this_list_view'),
        'showContextDependentFirstEntry' => false,
        'helpTextIdLabel'                => $gL10n->get('SYS_RIGHT_THIS_LIST_VIEW_DESC', array('SYS_RIGHT_ALL_LISTS_VIEW'))
    )
);
$selectBoxEntries = array(0 => $gL10n->get('SYS_NO_ADDITIONAL_RIGHTS'), 1 => $gL10n->get('SYS_ASSIGN_MEMBERS'), 2 => $gL10n->get('SYS_EDIT_MEMBERS'), 3 => $gL10n->get('SYS_ASSIGN_EDIT_MEMBERS'));
$form->addSelectBox(
    'rol_leader_rights', $gL10n->get('SYS_LEADER'), $selectBoxEntries,
    array(
        'defaultValue'                   => $role->getValue('rol_leader_rights'),
        'showContextDependentFirstEntry' => false,
        'helpTextIdLabel'                => 'SYS_LEADER_RIGHTS_DESC'
    )
);

$selectBoxEntries = array(0 => $gL10n->get('SYS_SYSTEM_DEFAULT_LIST'));
// SQL-Statement fuer alle Listenkonfigurationen vorbereiten, die angezeigt werdne sollen
$sql = 'SELECT lst_id, lst_name
          FROM '.TBL_LISTS.'
         WHERE lst_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
           AND lst_global = 1
           AND lst_name IS NOT NULL
      ORDER BY lst_global ASC, lst_name ASC';
$pdoStatement = $gDb->queryPrepared($sql, array((int) $gCurrentOrganization->getValue('org_id')));

while($row = $pdoStatement->fetch())
{
    $selectBoxEntries[$row['lst_id']] = $row['lst_name'];
}
$form->addSelectBox(
    'rol_lst_id', $gL10n->get('SYS_DEFAULT_LIST'), $selectBoxEntries,
    array('defaultValue' => (int) $role->getValue('rol_lst_id'), 'showContextDependentFirstEntry' => false, 'helpTextIdLabel' => 'SYS_DEFAULT_LIST_DESC')
);

if($role->getValue('cat_name_intern') !== 'EVENTS')
{
    $form->addCheckbox(
        'rol_default_registration', $gL10n->get('SYS_DEFAULT_ASSIGNMENT_REGISTRATION'), (bool) $role->getValue('rol_default_registration'),
        array('helpTextIdLabel' => 'SYS_DEFAULT_ASSIGNMENT_REGISTRATION_DESC')
    );
    $form->addInput(
        'rol_max_members', $gL10n->get('SYS_MAX_PARTICIPANTS').'<br />('.$gL10n->get('SYS_NO_LEADER').')', (int) $role->getValue('rol_max_members'),
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1)
    );
    $form->addInput(
        'rol_cost', $gL10n->get('SYS_CONTRIBUTION').' '.$gSettingsManager->getString('system_currency'), $role->getValue('rol_cost'),
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1)
    );
    $form->addSelectBox(
        'rol_cost_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), TableRoles::getCostPeriods(),
        array('defaultValue' => $role->getValue('rol_cost_period'))
    );
}
$form->closeGroupBox();

// event roles should not set rights, dates meetings and dependencies
if($role->getValue('cat_name_intern') !== 'EVENTS')
{
    $form->openGroupBox('gb_authorization', $gL10n->get('SYS_PERMISSIONS'));
    $form->addCheckbox(
        'rol_assign_roles', $gL10n->get('SYS_RIGHT_ASSIGN_ROLES'), (bool) $role->getValue('rol_assign_roles'),
        array('helpTextIdLabel' => 'SYS_RIGHT_ASSIGN_ROLES_DESC', 'icon' => 'fa-users')
    );
    $form->addCheckbox(
        'rol_all_lists_view', $gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'), (bool) $role->getValue('rol_all_lists_view'),
        array('icon' => 'fa-list')
    );
    $form->addCheckbox(
        'rol_approve_users', $gL10n->get('SYS_RIGHT_APPROVE_USERS'), (bool) $role->getValue('rol_approve_users'),
        array('icon' => 'fa-address-card')
    );
    if($gSettingsManager->getBool('enable_mail_module'))
    {
        $form->addCheckbox(
            'rol_mail_to_all', $gL10n->get('SYS_RIGHT_MAIL_TO_ALL'), (bool) $role->getValue('rol_mail_to_all'),
            array('icon' => 'fa-envelope')
        );
    }
    $form->addCheckbox(
        'rol_edit_user', $gL10n->get('SYS_RIGHT_EDIT_USER'), (bool) $role->getValue('rol_edit_user'),
        array('helpTextIdLabel' => 'SYS_RIGHT_EDIT_USER_DESC', 'icon' => 'fa-users-cog')
    );
    $form->addCheckbox(
        'rol_profile', $gL10n->get('SYS_RIGHT_PROFILE'), (bool) $role->getValue('rol_profile'),
        array('icon' => 'fa-user')
    );
    if((int) $gSettingsManager->get('enable_announcements_module') > 0)
    {
        $form->addCheckbox(
            'rol_announcements', $gL10n->get('SYS_RIGHT_ANNOUNCEMENTS'), (bool) $role->getValue('rol_announcements'),
            array('helpTextIdLabel' => 'SYS_ROLES_MODULE_ADMINISTRATORS_DESC', 'icon' => 'fa-newspaper')
        );
    }
    if((int) $gSettingsManager->get('enable_dates_module') > 0)
    {
        $form->addCheckbox(
            'rol_dates', $gL10n->get('SYS_RIGHT_DATES'), (bool) $role->getValue('rol_dates'),
            array('helpTextIdLabel' => 'SYS_ROLES_MODULE_ADMINISTRATORS_DESC', 'icon' => 'fa-calendar-alt')
        );
    }
    if((int) $gSettingsManager->get('enable_photo_module') > 0)
    {
        $form->addCheckbox(
            'rol_photo', $gL10n->get('SYS_RIGHT_PHOTOS'), (bool) $role->getValue('rol_photo'),
            array('icon' => 'fa-image')
        );
    }
    if($gSettingsManager->getBool('documents_files_enable_module'))
    {
        $form->addCheckbox(
            'rol_documents_files', $gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'), (bool) $role->getValue('rol_documents_files'),
            array('helpTextIdLabel' => 'SYS_RIGHT_DOCUMENTS_FILES_DESC', 'icon' => 'fa-download')
        );
    }
    if((int) $gSettingsManager->get('enable_guestbook_module') > 0)
    {
        $form->addCheckbox(
            'rol_guestbook', $gL10n->get('SYS_RIGHT_GUESTBOOK'), (bool) $role->getValue('rol_guestbook'),
            array('icon' => 'fa-book')
        );
        // if not registered users can set comments than there is no need to set a role dependent right
        if(!$gSettingsManager->getBool('enable_gbook_comments4all'))
        {
            $form->addCheckbox(
                'rol_guestbook_comments', $gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS'), (bool) $role->getValue('rol_guestbook_comments'),
                array('icon' => 'fa-comment')
            );
        }
    }
    if((int) $gSettingsManager->get('enable_weblinks_module') > 0)
    {
        $form->addCheckbox(
            'rol_weblinks', $gL10n->get('SYS_RIGHT_WEBLINKS'), (bool) $role->getValue('rol_weblinks'),
            array('helpTextIdLabel' => 'SYS_ROLES_MODULE_ADMINISTRATORS_DESC', 'icon' => 'fa-link')
        );
    }
    $form->closeGroupBox();
    $form->openGroupBox('gb_dates_meetings', $gL10n->get('DAT_DATES').' / '.$gL10n->get('SYS_MEETINGS').'&nbsp;&nbsp;('.$gL10n->get('SYS_OPTIONAL').')');
    $form->addInput('rol_start_date', $gL10n->get('SYS_VALID_FROM'), $role->getValue('rol_start_date'), array('type' => 'date'));
    $form->addInput('rol_end_date', $gL10n->get('SYS_VALID_TO'), $role->getValue('rol_end_date'), array('type' => 'date'));
    $form->addInput('rol_start_time', $gL10n->get('SYS_TIME_FROM'), $role->getValue('rol_start_time'), array('type' => 'time'));
    $form->addInput('rol_end_time', $gL10n->get('SYS_TIME_TO'), $role->getValue('rol_end_time'), array('type' => 'time'));
    $form->addSelectBox('rol_weekday', $gL10n->get('SYS_WEEKDAY'), DateTimeExtended::getWeekdays(), array('defaultValue' => $role->getValue('rol_weekday')));
    $form->addInput('rol_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'), array('maxLength' => 100));
    $form->closeGroupBox();

    $form->openGroupBox('gb_dependencies', $gL10n->get('SYS_DEPENDENCIES').'&nbsp;&nbsp;('.$gL10n->get('SYS_OPTIONAL').')');
    $roleName = $gL10n->get('SYS_NEW_ROLE');
    if($role->getValue('rol_name') !== '')
    {
        $roleName = $gL10n->get('SYS_ROLE').' <strong>'.$role->getValue('rol_name').'</strong>';
    }
    $form->addHtml('<p>'.$gL10n->get('SYS_ROLE_DEPENDENCIES_DESC', array($roleName)).'</p>');

    //  list all roles that the user is allowed to see
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

    $form->addSelectBoxFromSql(
        'dependent_roles', $gL10n->get('SYS_DEPENDENT'), $gDb, $sqlData,
        array('defaultValue' => $childRoles, 'multiselect' => true)
    );
    $form->closeGroupBox();
}

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $role->getValue('rol_usr_id_create'), $role->getValue('rol_timestamp_create'),
    (int) $role->getValue('rol_usr_id_change'), $role->getValue('rol_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
