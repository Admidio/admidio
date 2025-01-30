<?php
namespace Admidio\UI\View;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\ValueObject\RoleDependency;
use Admidio\Roles\Service\RoleService;
use Admidio\UI\Component\Form;
use HtmlDataTables;
use HtmlPage;
use Admidio\Changelog\Service\ChangelogService;

/**
 * @brief Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the groups and roles module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleGroupsRoles('admidio-groups-roles', $headline);
 * $page->createRegistrationList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class GroupsRoles extends HtmlPage
{
    /**
     * @var array Array with all read groups and roles
     */
    protected array $data = array();
    /**
     * @var int Type of the role e.g. ROLE_TYPE_INACTIVE, ROLE_TYPE_ACTIVE, ROLE_TYPE_EVENT_PARTICIPATION
     */
    public const ROLE_TYPE_INACTIVE = 0;
    public const ROLE_TYPE_ACTIVE = 1;
    public const ROLE_TYPE_EVENT_PARTICIPATION = 2;
    protected int $roleType;

    /**
     * Returns the number of roles that where read in this class.
     * @return int Returns the number of roles
     */
    public function countRoles(): int
    {
        return count($this->data);
    }

    /**
     * Show all roles of the organization in card view. The roles must be read before with the method readData.
     * The cards will show various functions like activate, deactivate, vcard export, edit or delete. Also, the
     * role information e.g. description, start and end date, number of active and former members. A button with
     * the link to the default list will be shown.
     * @param string $categoryUUID UUID of the category for which the roles should be shown.
     * @param string $roleType The type of roles that should be shown within this page.
     *                         0 - inactive roles
     *                         1 - active roles
     *                         2 - event participation roles
     * @throws \Smarty\Exception|Exception
     * @throws Exception
     */
    public function createCards(string $categoryUUID, string $roleType)
    {
        global $gSettingsManager, $gCurrentUser, $gCurrentSession, $gL10n, $gDb;

        $this->createSharedHeader($categoryUUID, $roleType, 'card');

        $categoryUUID = '';
        $categoryName = '';
        $templateDataCategory = array();
        $templateDataRoles = array();

        foreach ($this->data as $row) {
            $role = new Role($gDb);
            $role->setArray($row);

            if ($categoryUUID !== $row['cat_uuid']) {
                if ($categoryUUID !== '') {
                    $templateDataCategory[] = array(
                        'uuid' => $categoryUUID,
                        'name' => $categoryName,
                        'entries' => $templateDataRoles
                    );
                }
                $categoryUUID = $row['cat_uuid'];
                $categoryName = $row['cat_name'];
                $templateDataRoles = array();
            }

            $templateRow = array();
            $templateRow['category'] = $role->getValue('cat_name');
            $templateRow['id'] = 'role_' . $role->getValue('rol_uuid');
            $templateRow['title'] = $role->getValue('rol_name');

            // send a mail to all role members
            if ($gCurrentUser->hasRightSendMailToRole($row['rol_id']) && $gSettingsManager->getBool('enable_mail_module')) {
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('role_uuid' => $row['rol_uuid'])),
                    'icon' => 'bi bi-envelope',
                    'tooltip' => $gL10n->get('SYS_EMAIL_TO_MEMBERS')
                );
            }

            // show link to export vCard if user is allowed to see the profiles of members and the role has members
            if ($gCurrentUser->hasRightViewProfiles($row['rol_id'])
                && ($row['num_members'] > 0 || $row['num_leader'] > 0)) {
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles.php', array('mode' => 'export', 'role_uuid' => $row['rol_uuid'])),
                    'icon' => 'bi bi-download',
                    'tooltip' => $gL10n->get('SYS_EXPORT_VCARD_FROM_VAR', array($row['rol_name']))
                );
            }

            // link to assign or remove members if you are allowed to do it
            if ($role->allowedToAssignMembers($gCurrentUser)) {
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $row['rol_uuid'])),
                    'icon' => 'bi bi-person-plus',
                    'tooltip' => $gL10n->get('SYS_ASSIGN_MEMBERS')
                );
            }

            if ($gCurrentUser->manageRoles()) {
                // set role active or inactive
                if ($this->roleType === GroupsRoles::ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'role_' . $row['rol_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/groups-roles/groups_roles.php', array('mode' => 'activate', 'role_uuid' => $row['rol_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_ACTIVATE_ROLE_DESC', array($row['rol_name'])),
                        'icon' => 'bi bi-eye',
                        'tooltip' => $gL10n->get('SYS_ACTIVATE_ROLE')
                    );
                } elseif ($this->roleType === GroupsRoles::ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'role_' . $row['rol_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/groups-roles/groups_roles.php', array('mode' => 'deactivate', 'role_uuid' => $row['rol_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DEACTIVATE_ROLE_DESC', array($row['rol_name'])),
                        'icon' => 'bi bi-eye-slash',
                        'tooltip' => $gL10n->get('SYS_DEACTIVATE_ROLE')
                    );
                }

                // edit roles of you are allowed to assign roles
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles.php', array('mode' => 'edit', 'role_uuid' => $row['rol_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT_ROLE')
                );
                if (!$role->getValue('rol_administrator')) {
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'role_' . $row['rol_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/groups-roles/groups_roles.php', array('mode' => 'delete', 'role_uuid' => $row['rol_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($row['rol_name'])),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE_ROLE')
                    );
                }
            }

            if (!empty($role->getValue('rol_description'))) {
                $roleDescription = strip_tags($role->getValue('rol_description'));

                if (strlen($roleDescription) > 200) {
                    // read first 200 chars of text, then search for last space and cut the text there. After that add a "more" link
                    $textPrev = substr($roleDescription, 0, 200);
                    $maxPosPrev = strrpos($textPrev, ' ');
                    $roleDescription = substr($textPrev, 0, $maxPosPrev) .
                        ' <span class="collapse" id="viewdetails-' . $row['rol_uuid'] . '">' . substr($roleDescription, $maxPosPrev) . '.
                                </span> <a class="admidio-icon-link" data-bs-toggle="collapse" data-bs-target="#viewdetails-' . $row['rol_uuid'] . '">»</a>';
                }

                $templateRow['information'][] = $roleDescription;
            }

            // block with information about events and meeting-point
            if (!empty($role->getValue('rol_start_date')) || $role->getValue('rol_weekday') > 0
                || !empty($role->getValue('rol_start_time')) || !empty($role->getValue('rol_location'))) {
                $html = '<h6>' . $gL10n->get('SYS_APPOINTMENTS') . ' / ' . $gL10n->get('SYS_MEETINGS') . '</h6>';
                if ($role->getValue('rol_start_date') !== '') {
                    $html .= '<span class="d-block">' . $gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))) . '</span>';
                }

                if ($role->getValue('rol_weekday') > 0 || !empty($role->getValue('rol_start_time'))) {
                    if ($role->getValue('rol_weekday') > 0) {
                        $html .= RoleService::getWeekdays($role->getValue('rol_weekday')) . ' ';
                    }
                    if (!empty($role->getValue('rol_start_time'))) {
                        $html .= $gL10n->get('SYS_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
                    }
                    $html = '<span class="d-block">' . $html . '</span>';
                }

                // Meeting point
                if (!empty($role->getValue('rol_location'))) {
                    $html .= '<span class="d-block">' . $gL10n->get('SYS_MEETING_POINT') . ' ' . $role->getValue('rol_location') . '</span>';
                }
                $templateRow['information'][] = $html;
            }

            // show members fee
            if (!empty($role->getValue('rol_cost')) || $role->getValue('rol_cost_period') > 0) {
                $html = '';

                // Member fee
                if (!empty($role->getValue('rol_cost'))) {
                    $html .= (float)$role->getValue('rol_cost') . ' ' . $gSettingsManager->getString('system_currency');
                }

                // Contributory period
                if (!empty($role->getValue('rol_cost_period')) && $role->getValue('rol_cost_period') != 0) {
                    $html .= ' - ' . Role::getCostPeriods($role->getValue('rol_cost_period'));
                }

                $templateRow['information'][] = '<h6>' . $gL10n->get('SYS_CONTRIBUTION') . '</h6><span class="d-block">' . $html . '</span></li>';
            }

            // show count of members and leaders of this role
            $html = '';
            $htmlLeader = '';

            if ($role->getValue('rol_max_members') > 0) {
                $html .= $gL10n->get('SYS_MAX_PARTICIPANTS_OF_ROLE', array((int)$row['num_members'], (int)$role->getValue('rol_max_members')));
            } else {
                $html .= $row['num_members'] . ' ' . $gL10n->get('SYS_PARTICIPANTS');
            }

            if ($gCurrentUser->hasRightViewFormerRolesMembers($row['rol_id']) && $this->roleType === $this::ROLE_TYPE_ACTIVE && $row['num_former'] > 0) {
                // show former members
                if ($row['num_former'] == 1) {
                    $textFormerMembers = $gL10n->get('SYS_FORMER');
                } else {
                    $textFormerMembers = $gL10n->get('SYS_FORMER_PL');
                }

                $html .= '&nbsp;&nbsp;(<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('role_list' => $row['rol_uuid'], 'show_former_members' => 1)) . '">' . $row['num_former'] . ' ' . $textFormerMembers . '</a>) ';
            }

            if ($row['num_leader'] > 0) {
                $htmlLeader = '<span class="d-block">' . $row['num_leader'] . ' ' . $gL10n->get('SYS_LEADERS') . '</span>';
            }
            $templateRow['information'][] = '<span class="d-block">' . $html . '</span>' . $htmlLeader;

            $templateRow['buttons'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('role_list' => $row['rol_uuid'])),
                'name' => $gL10n->get('SYS_SHOW_MEMBER_LIST')
            );

            $templateDataRoles[] = $templateRow;
        }
        $templateDataCategory[] = array(
            'uuid' => $categoryUUID,
            'name' => $categoryName,
            'entries' => $templateDataRoles
        );
        $this->smarty->assign('cards', $templateDataCategory);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/groups-roles.cards.tpl');
    }

    /**
     * Create the data for the edit form of a role.
     * @param string $roleUUID UUID of the role that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $roleUUID = '')
    {
        global $gCurrentOrgId, $gCurrentUser, $gCurrentSession, $gDb, $gL10n, $gSettingsManager;

        // Initialize local parameters
        $showSystemCategory = false;
        $eventRole = false;

        // create role object
        $role = new Role($gDb);

        if ($roleUUID !== '') {
            $role->readDataByUuid($roleUUID);
            $eventRole = $role->getValue('cat_name_intern') === 'EVENTS';

            // check if the role belongs to the current organization
            if ((int)$role->getValue('cat_org_id') !== $gCurrentOrgId && $role->getValue('cat_org_id') > 0) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            // administrator role could only be created or edited by administrators
            if ($role->getValue('rol_administrator') == 1 && !$gCurrentUser->isAdministrator()) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            // hidden roles can also see hidden categories
            if ($role->getValue('cat_system') == 1) {
                $showSystemCategory = true;
            }
        }

        // get all dependent roles of this role
        $childRoles = RoleDependency::getChildRoles($gDb, $role->getValue('rol_id'));

        $childRoleObjects = array();

        $this->addJavascript('
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

        $this->addJavascript('
            /**
             * show/hide role dependencies if max count members will be changed
             */
            function checkMaxMemberCount() {
                // If a maximum number of members has been specified, no role dependencies may exist
                if ($("#rol_max_members").val() > 0) {
                    $("#gb_dependencies").hide();

                    // All dependent roles are marked and set to independent
                    $("#dependent_roles").val("");
                } else {
                    $("#gb_dependencies").show();
                }
            }

            /**
             * Set dependent role right if another role right changed
             * @param {string} srcRight  ID of the right that triggers the event
             * @param {string} destRight ID of the right that is to be adapted
             * @param {bool}   checked   true destRight is set to checked
             *                           false destRight is set to unchecked
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

        ChangelogService::displayHistoryButton($this, 'roles', 'roles', !empty($getAnnroleUUIDUuid), array('uuid' => $roleUUID));

        $form = new Form(
            'adm_roles_edit_form',
            'modules/groups-roles.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles.php', array('role_uuid' => $roleUUID, 'mode' => 'save')),
            $this
        );

        if ($role->getValue('rol_administrator') === 1 || $eventRole) {
            $fieldProperty = Form::FIELD_READONLY;
        } else {
            $fieldProperty = Form::FIELD_REQUIRED;
        }
        $form->addInput(
            'rol_name',
            $gL10n->get('SYS_NAME'),
            $role->getValue('rol_name'),
            array('maxLength' => 100, 'property' => $fieldProperty)
        );
        $form->addMultilineTextInput(
            'rol_description',
            $gL10n->get('SYS_DESCRIPTION'),
            $role->getValue('rol_description'),
            3,
            array('property' => ($eventRole ? Form::FIELD_READONLY : Form::FIELD_DEFAULT), 'maxLength' => 4000)
        );
        $form->addSelectBoxForCategories(
            'rol_cat_id',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            ($eventRole ? 'ROL_EVENT' : 'ROL'),
            Form::SELECT_BOX_MODUS_EDIT,
            array('property' => ($eventRole ? Form::FIELD_READONLY : Form::FIELD_REQUIRED), 'defaultValue' => $role->getValue('cat_uuid'))
        );
        if ($gSettingsManager->getBool('enable_mail_module')) {
            $selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'), 3 => $gL10n->get('SYS_ALSO_VISITORS'));
            $form->addSelectBox(
                'rol_mail_this_role',
                $gL10n->get('SYS_SEND_MAILS'),
                $selectBoxEntries,
                array(
                    'defaultValue' => $role->getValue('rol_mail_this_role'),
                    'showContextDependentFirstEntry' => false,
                    'helpTextId' => $gL10n->get('SYS_RIGHT_MAIL_THIS_ROLE_DESC', array('SYS_RIGHT_MAIL_TO_ALL'))
                )
            );
        }
        $selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 3 => $gL10n->get('SYS_LEADERS'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'));
        $form->addSelectBox(
            'rol_view_memberships',
            $gL10n->get('SYS_VIEW_ROLE_MEMBERSHIPS'),
            $selectBoxEntries,
            array(
                'defaultValue' => $role->getValue('rol_view_memberships'),
                'showContextDependentFirstEntry' => false,
                'helpTextId' => $gL10n->get('SYS_VIEW_ROLE_MEMBERSHIPS_DESC', array('SYS_RIGHT_ALL_LISTS_VIEW'))
            )
        );
        $form->addSelectBox(
            'rol_view_members_profiles',
            $gL10n->get('SYS_VIEW_PROFILES_OF_ROLE_MEMBERS'),
            $selectBoxEntries,
            array(
                'defaultValue' => $role->getValue('rol_view_members_profiles'),
                'showContextDependentFirstEntry' => false,
                'helpTextId' => $gL10n->get('SYS_VIEW_PROFILES_OF_ROLE_MEMBERS_DESC', array('SYS_RIGHT_ALL_LISTS_VIEW'))
            )
        );
        $selectBoxEntries = array(0 => $gL10n->get('SYS_NO_ADDITIONAL_RIGHTS'), 1 => $gL10n->get('SYS_ASSIGN_MEMBERS'), 2 => $gL10n->get('SYS_EDIT_MEMBERS'), 3 => $gL10n->get('SYS_ASSIGN_EDIT_MEMBERS'));
        $form->addSelectBox(
            'rol_leader_rights',
            $gL10n->get('SYS_LEADER'),
            $selectBoxEntries,
            array(
                'defaultValue' => $role->getValue('rol_leader_rights'),
                'showContextDependentFirstEntry' => false,
                'helpTextId' => 'SYS_LEADER_RIGHTS_DESC'
            )
        );

        $selectBoxEntries = array(0 => $gL10n->get('SYS_SYSTEM_DEFAULT_LIST'));
        // Prepare SQL statement for all list configurations to be displayed
        $sql = 'SELECT lst_id, lst_name
          FROM ' . TBL_LISTS . '
         WHERE lst_org_id = ? -- $gCurrentOrgId
           AND lst_global = true
           AND lst_name IS NOT NULL
      ORDER BY lst_global, lst_name';
        $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        while ($row = $pdoStatement->fetch()) {
            $selectBoxEntries[$row['lst_id']] = $row['lst_name'];
        }
        $form->addSelectBox(
            'rol_lst_id',
            $gL10n->get('SYS_DEFAULT_LIST'),
            $selectBoxEntries,
            array('defaultValue' => (int)$role->getValue('rol_lst_id'), 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_LIST_DESC')
        );

        if (!$eventRole) {
            $form->addCheckbox(
                'rol_default_registration',
                $gL10n->get('SYS_DEFAULT_ASSIGNMENT_REGISTRATION'),
                (bool)$role->getValue('rol_default_registration'),
                array('helpTextId' => 'SYS_DEFAULT_ASSIGNMENT_REGISTRATION_DESC')
            );
            $form->addInput(
                'rol_max_members',
                $gL10n->get('SYS_MAX_PARTICIPANTS') . '<br />(' . $gL10n->get('SYS_NO_LEADER') . ')',
                (int)$role->getValue('rol_max_members'),
                array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1)
            );
            $form->addInput(
                'rol_cost',
                $gL10n->get('SYS_CONTRIBUTION') . ' ' . $gSettingsManager->getString('system_currency'),
                (string)$role->getValue('rol_cost'),
                array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => '0.01')
            );
            $form->addSelectBox(
                'rol_cost_period',
                $gL10n->get('SYS_CONTRIBUTION_PERIOD'),
                Role::getCostPeriods(),
                array('defaultValue' => $role->getValue('rol_cost_period'), 'class' => 'form-control-small')
            );
        }

        // event roles should not set rights, events meetings and dependencies
        if (!$eventRole) {
            $form->addCheckbox(
                'rol_assign_roles',
                $gL10n->get('SYS_RIGHT_ASSIGN_ROLES'),
                (bool)$role->getValue('rol_assign_roles'),
                array('helpTextId' => 'SYS_RIGHT_ASSIGN_ROLES_DESC', 'icon' => 'bi-people-fill')
            );
            $form->addCheckbox(
                'rol_all_lists_view',
                $gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'),
                (bool)$role->getValue('rol_all_lists_view'),
                array('icon' => 'bi-list-task')
            );
            $form->addCheckbox(
                'rol_approve_users',
                $gL10n->get('SYS_RIGHT_APPROVE_USERS'),
                (bool)$role->getValue('rol_approve_users'),
                array('icon' => 'bi-card-checklist')
            );
            if ($gSettingsManager->getBool('enable_mail_module')) {
                $form->addCheckbox(
                    'rol_mail_to_all',
                    $gL10n->get('SYS_RIGHT_MAIL_TO_ALL'),
                    (bool)$role->getValue('rol_mail_to_all'),
                    array('icon' => 'bi-envelope-fill')
                );
            }
            $form->addCheckbox(
                'rol_edit_user',
                $gL10n->get('SYS_RIGHT_EDIT_USER'),
                (bool)$role->getValue('rol_edit_user'),
                array('helpTextId' => 'SYS_RIGHT_EDIT_USER_DESC', 'icon' => 'bi-person-fill-gear')
            );
            $form->addCheckbox(
                'rol_profile',
                $gL10n->get('SYS_RIGHT_PROFILE'),
                (bool)$role->getValue('rol_profile'),
                array('icon' => 'bi-person-fill')
            );
            if ((int)$gSettingsManager->get('announcements_module_enabled') > 0) {
                $form->addCheckbox(
                    'rol_announcements',
                    $gL10n->get('SYS_RIGHT_ANNOUNCEMENTS'),
                    (bool)$role->getValue('rol_announcements'),
                    array('helpTextId' => 'SYS_ROLES_MODULE_ADMINISTRATORS_DESC', 'icon' => 'bi-newspaper')
                );
            }
            if ((int)$gSettingsManager->get('events_module_enabled') > 0) {
                $form->addCheckbox(
                    'rol_events',
                    $gL10n->get('SYS_RIGHT_DATES'),
                    (bool)$role->getValue('rol_events'),
                    array('helpTextId' => 'SYS_ROLES_MODULE_ADMINISTRATORS_DESC', 'icon' => 'bi-calendar-week-fill')
                );
            }
            if ((int)$gSettingsManager->get('photo_module_enabled') > 0) {
                $form->addCheckbox(
                    'rol_photo',
                    $gL10n->get('SYS_RIGHT_PHOTOS'),
                    (bool)$role->getValue('rol_photo'),
                    array('icon' => 'bi-image-fill')
                );
            }
            if ($gSettingsManager->getBool('documents_files_module_enabled')) {
                $form->addCheckbox(
                    'rol_documents_files',
                    $gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'),
                    (bool)$role->getValue('rol_documents_files'),
                    array('helpTextId' => 'SYS_RIGHT_DOCUMENTS_FILES_DESC', 'icon' => 'bi-file-earmark-arrow-down-fill')
                );
            }
            if ((int)$gSettingsManager->get('enable_guestbook_module') > 0) {
                $form->addCheckbox(
                    'rol_guestbook',
                    $gL10n->get('SYS_RIGHT_GUESTBOOK'),
                    (bool)$role->getValue('rol_guestbook'),
                    array('icon' => 'bi-book-half')
                );
                // if not registered users can set comments than there is no need to set a role dependent right
                if (!$gSettingsManager->getBool('enable_gbook_comments4all')) {
                    $form->addCheckbox(
                        'rol_guestbook_comments',
                        $gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS'),
                        (bool)$role->getValue('rol_guestbook_comments'),
                        array('icon' => 'bi-chat-fill')
                    );
                }
            }
            if ((int)$gSettingsManager->get('enable_weblinks_module') > 0) {
                $form->addCheckbox(
                    'rol_weblinks',
                    $gL10n->get('SYS_RIGHT_WEBLINKS'),
                    (bool)$role->getValue('rol_weblinks'),
                    array('helpTextId' => 'SYS_ROLES_MODULE_ADMINISTRATORS_DESC', 'icon' => 'bi-link-45deg')
                );
            }
            $form->addInput('rol_start_date', $gL10n->get('SYS_VALID_FROM'), $role->getValue('rol_start_date'), array('type' => 'date'));
            $form->addInput('rol_end_date', $gL10n->get('SYS_VALID_TO'), $role->getValue('rol_end_date'), array('type' => 'date'));
            $form->addInput('rol_start_time', $gL10n->get('SYS_TIME_FROM'), $role->getValue('rol_start_time'), array('type' => 'time'));
            $form->addInput('rol_end_time', $gL10n->get('SYS_TIME_TO'), $role->getValue('rol_end_time'), array('type' => 'time'));
            $form->addSelectBox('rol_weekday', $gL10n->get('SYS_WEEKDAY'), RoleService::getWeekdays(), array('defaultValue' => $role->getValue('rol_weekday'), 'class' => 'form-control-small'));
            $form->addInput('rol_location', $gL10n->get('SYS_MEETING_POINT'), $role->getValue('rol_location'), array('maxLength' => 100));

            $roleName = $gL10n->get('SYS_NEW_ROLE');
            if ($role->getValue('rol_name') !== '') {
                $roleName = $gL10n->get('SYS_ROLE') . ' <strong>' . $role->getValue('rol_name') . '</strong>';
            }

            //  list all roles that the user is allowed to see
            $sqlData['query'] = 'SELECT rol_id, rol_name, cat_name
                           FROM ' . TBL_ROLES . '
                     INNER JOIN ' . TBL_CATEGORIES . '
                             ON cat_id = rol_cat_id
                          WHERE rol_valid   = true
                            AND cat_name_intern <> \'EVENTS\'
                            AND (  cat_org_id  = ? -- $gCurrentOrgId
                                OR cat_org_id IS NULL )
                       ORDER BY cat_sequence, rol_name';
            $sqlData['params'] = array($gCurrentOrgId);

            $form->addSelectBoxFromSql(
                'dependent_roles',
                $gL10n->get('SYS_DEPENDENT'),
                $gDb,
                $sqlData,
                array('defaultValue' => $childRoles, 'multiselect' => true)
            );
        }

        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('eventRole', $eventRole);
        $this->assignSmartyVariable('roleName', $roleName);
        $this->assignSmartyVariable('nameUserCreated', $role->getNameOfCreatingUser());
        $this->assignSmartyVariable('timestampUserCreated', $role->getValue('rol_timestamp_create'));
        $this->assignSmartyVariable('nameLastUserEdited', $role->getNameOfLastEditingUser());
        $this->assignSmartyVariable('timestampLastUserEdited', $role->getValue('rol_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Show all roles of the organization in card view. The roles must be read before with the method readData.
     * The cards will show various functions like activate, deactivate, vcard export, edit or delete. Also, the
     * role information e.g. description, start and end date, number of active and former members. A button with
     * the link to the default list will be shown.
     * @param string $categoryUUID UUID of the category for which the roles should be shown.
     * @param string $roleType The type of roles that should be shown within this page.
     *                         0 - inactive roles
     *                         1 - active roles
     *                         2 - event participation roles
     * @throws \Smarty\Exception|Exception
     * @throws Exception
     */
    public function createPermissionsList(string $categoryUUID, string $roleType)
    {
        global $gSettingsManager, $gL10n, $gDb, $gCurrentSession;
        $this->createSharedHeader($categoryUUID, $roleType, 'permissions');

        $templateData = array();

        foreach ($this->data as $row) {
            $role = new Role($gDb);
            $role->setArray($row);

            $templateRow = array();
            $templateRow['category'] = $role->getValue('cat_name');
            $templateRow['categoryOrder'] = $role->getValue('cat_sequence');
            $templateRow['role'] = $role->getValue('rol_name');
            $templateRow['roleUUID'] = $role->getValue('rol_uuid');
            $templateRow['roleUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles.php', array('mode' => 'edit', 'role_uuid' => $row['rol_uuid']));
            $templateRow['roleRights'] = array();
            if ($role->getValue('rol_assign_roles') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-people-fill', 'title' => $gL10n->get('SYS_RIGHT_ASSIGN_ROLES'));
            }
            if ($role->getValue('rol_all_lists_view') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-list-task', 'title' => $gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'));
            }
            if ($role->getValue('rol_approve_users') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-card-checklist', 'title' => $gL10n->get('SYS_RIGHT_APPROVE_USERS'));
            }
            if ($role->getValue('rol_mail_to_all') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-envelope-fill', 'title' => $gL10n->get('SYS_RIGHT_MAIL_TO_ALL'));
            }
            if ($role->getValue('rol_edit_user') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-person-fill-gear', 'title' => $gL10n->get('SYS_RIGHT_EDIT_USER'));
            }
            if ($role->getValue('rol_profile') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-person-fill', 'title' => $gL10n->get('SYS_RIGHT_PROFILE'));
            }
            if ($role->getValue('rol_announcements') == 1 && (int)$gSettingsManager->get('announcements_module_enabled') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-newspaper', 'title' => $gL10n->get('SYS_RIGHT_ANNOUNCEMENTS'));
            }
            if ($role->getValue('rol_events') == 1 && (int)$gSettingsManager->get('events_module_enabled') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-calendar-week-fill', 'title' => $gL10n->get('SYS_RIGHT_DATES'));
            }
            if ($role->getValue('rol_photo') == 1 && (int)$gSettingsManager->get('photo_module_enabled') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-image-fill', 'title' => $gL10n->get('SYS_RIGHT_PHOTOS'));
            }
            if ($role->getValue('rol_documents_files') == 1 && (int)$gSettingsManager->getBool('documents_files_module_enabled')) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-file-earmark-arrow-down-fill', 'title' => $gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'));
            }
            if ($role->getValue('rol_guestbook') == 1 && (int)$gSettingsManager->get('enable_guestbook_module') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-book-half', 'title' => $gL10n->get('SYS_RIGHT_GUESTBOOK'));
            }
            if ($role->getValue('rol_guestbook_comments') == 1 && (int)$gSettingsManager->get('enable_guestbook_module') > 0 && !$gSettingsManager->getBool('enable_gbook_comments4all')) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-chat-fill', 'title' => $gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS'));
            }
            if ($role->getValue('rol_weblinks') == 1 && (int)$gSettingsManager->get('enable_weblinks_module') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'bi bi-link-45deg', 'title' => $gL10n->get('SYS_RIGHT_WEBLINKS'));
            }

            switch ($role->getValue('rol_mail_this_role')) {
                case 0:
                    $templateRow['emailToThisRole'] = $gL10n->get('SYS_NOBODY');
                    break;
                case 1:
                    $templateRow['emailToThisRole'] = $gL10n->get('SYS_ROLE_MEMBERS');
                    break;
                case 2:
                    $templateRow['emailToThisRole'] = $gL10n->get('ORG_REGISTERED_USERS');
                    break;
                case 3:
                    $templateRow['emailToThisRole'] = $gL10n->get('SYS_ALSO_VISITORS');
                    break;
            }

            switch ($role->getValue('rol_view_memberships')) {
                case 0:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_NOBODY');
                    break;
                case 1:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_ROLE_MEMBERS');
                    break;
                case 2:
                    $templateRow['viewMembership'] = $gL10n->get('ORG_REGISTERED_USERS');
                    break;
                case 3:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_LEADERS');
                    break;
            }

            switch ($role->getValue('rol_view_members_profiles')) {
                case 0:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('SYS_NOBODY');
                    break;
                case 1:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('SYS_ROLE_MEMBERS');
                    break;
                case 2:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('ORG_REGISTERED_USERS');
                    break;
                case 3:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('SYS_LEADERS');
                    break;
            }

            switch ($role->getValue('rol_leader_rights')) {
                case 0:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_NO_ADDITIONAL_RIGHTS');
                    break;
                case 1:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_ASSIGN_MEMBERS');
                    break;
                case 2:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_EDIT_MEMBERS');
                    break;
                case 3:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_ASSIGN_EDIT_MEMBERS');
                    break;
            }

            $templateRow['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('mode' => 'html', 'role_list' => $row['rol_uuid'])),
                'icon' => 'bi bi-card-list',
                'tooltip' => $gL10n->get('SYS_SHOW_ROLE_MEMBERSHIP')
            );
            if ($this->roleType === $this::ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'role_' . $row['rol_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/groups-roles/groups_roles.php', array('mode' => 'activate', 'role_uuid' => $row['rol_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_ACTIVATE_ROLE_DESC', array($row['rol_name'])),
                    'icon' => 'bi bi-eye',
                    'tooltip' => $gL10n->get('SYS_ACTIVATE_ROLE')
                );
            } elseif ($this->roleType === $this::ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'role_' . $row['rol_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/groups-roles/groups_roles.php', array('mode' => 'deactivate', 'role_uuid' => $row['rol_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DEACTIVATE_ROLE_DESC', array($row['rol_name'])),
                    'icon' => 'bi bi-eye-slash',
                    'tooltip' => $gL10n->get('SYS_DEACTIVATE_ROLE')
                );
            }
            if (!$role->getValue('rol_administrator')) {
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'role_' . $row['rol_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/groups-roles/groups_roles.php', array('mode' => 'delete', 'role_uuid' => $row['rol_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($row['rol_name'])),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE_ROLE')
                );
            }
            $templateData[] = $templateRow;
        }

        // initialize and set the parameter for DataTables
        $dataTables = new HtmlDataTables($this, 'adm_role_permissions_table');
        $dataTables->setGroupColumn(1);
        $dataTables->disableColumnsSort(array(3, 8));
        $dataTables->setColumnsNotHideResponsive(array(8));
        $dataTables->createJavascript(count($this->data), 7);

        $this->smarty->assign('list', $templateData);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/groups-roles.permissions-list.tpl');
    }

    /**
     * Create content that is used on several pages and could be called in other methods. It will
     * create a functions menu and a filter navbar.
     * @param string $categoryUUID UUID of the category for which the roles should be shown.
     * @param string $roleType The type of roles that should be shown within this page.
     *                         0 - inactive roles
     *                         1 - active roles
     *                         2 - event participation roles
     * @param string $mode The purpose of the current page. One of: 'card', 'permissions', 'edit'
     * 
     * @return void
     * @throws Exception
     */
    protected function createSharedHeader(string $categoryUUID, string $roleType, string $mode = 'card')
    {
        global $gCurrentUser, $gSettingsManager, $gL10n, $gDb;

        if ($gCurrentUser->manageRoles()) {
            // show link to create new role
            $this->addPageFunctionsMenuItem(
                'menu_item_groups_roles_add',
                $gL10n->get('SYS_CREATE_ROLE'),
                ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php?mode=edit',
                'bi-plus-circle-fill'
            );

            if ($mode == 'card') {
                // show permissions of all roles
                $this->addPageFunctionsMenuItem(
                    'menu_item_groups_roles_show_permissions',
                    $gL10n->get('SYS_SHOW_PERMISSIONS'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('mode' => 'permissions', 'cat_uuid' => $categoryUUID, 'role_type' => $roleType)),
                    'bi-shield-lock-fill'
                );
            }

            $logShowTable = 'roles';
            if ($mode == 'card') {
                $logShowTable = 'members';
            } elseif ($mode == 'permissions') {
                $logShowTable = 'roles_rights_data';
            }
            ChangelogService::displayHistoryButton($this, 'members', $logShowTable);

            // show link to maintain categories
            $this->addPageFunctionsMenuItem(
                'menu_item_groups_roles_maintain_categories',
                $gL10n->get('SYS_EDIT_CATEGORIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories.php', array('type' => 'ROL')),
                'bi-hdd-stack-fill'
            );
        }

        // show link to create own list
        if ($gSettingsManager->getInt('groups_roles_edit_lists') === 1 // everyone
            || ($gSettingsManager->getInt('groups_roles_edit_lists') === 2 && $gCurrentUser->checkRolesRight('rol_edit_user')) // users with the right to edit all profiles
            || ($gSettingsManager->getInt('groups_roles_edit_lists') === 3 && $gCurrentUser->isAdministrator())) {
            $this->addPageFunctionsMenuItem(
                'menu_item_groups_own_list',
                $gL10n->get('SYS_CONFIGURE_LISTS'),
                ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php',
                'bi-card-list'
            );
        }

        // add filter navbar
        $this->addJavascript('
            $("#cat_uuid").change(function() {
                $("#adm_navbar_filter_form").submit();
            });
            $("#role_type").change(function() {
                $("#adm_navbar_filter_form").submit();
            });',
            true
        );

        // create filter menu with elements for category
        $form = new Form(
            'adm_navbar_filter_form',
            'sys-template-parts/form.filter.tpl',
            ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php',
            $this,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addInput('mode', '', ($mode), array('property' => Form::FIELD_HIDDEN));
        $form->addSelectBoxForCategories(
            'cat_uuid',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'ROL',
            Form::SELECT_BOX_MODUS_FILTER,
            array('defaultValue' => $categoryUUID)
        );
        if ($gCurrentUser->manageRoles()) {
            $form->addSelectBox(
                'role_type',
                $gL10n->get('SYS_ROLE_TYPES'),
                array(0 => $gL10n->get('SYS_INACTIVE_GROUPS_ROLES'), 1 => $gL10n->get('SYS_ACTIVE_GROUPS_ROLES'), 2 => $gL10n->get('SYS_ROLES_CONFIRMATION_OF_PARTICIPATION')),
                array('defaultValue' => $roleType)
            );
        }
        $form->addToHtmlPage();
    }

    /**
     * Creates an array with all available groups and roles.
     * @param int $roleType The type of groups and roles that should be read. This could be active, inactive
     *                             or event roles.
     * @param string $categoryUUID The UUID of the category whose groups and roles should be read.
     * @throws Exception
     */
    public function readData(int $roleType = GroupsRoles::ROLE_TYPE_ACTIVE, string $categoryUUID = '')
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser;

        $this->roleType = $roleType;

        $sql = 'SELECT rol.*, cat.*,
                       COALESCE((SELECT COUNT(*) + SUM(mem_count_guests) AS count
                          FROM ' . TBL_MEMBERS . ' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem.mem_begin  <= ? -- DATE_NOW
                           AND mem.mem_end     > ? -- DATE_NOW
                           AND (mem.mem_approved IS NULL
                            OR mem.mem_approved < 3)
                           AND mem.mem_leader = false), 0) AS num_members,
                       COALESCE((SELECT COUNT(*) AS count
                          FROM ' . TBL_MEMBERS . ' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem.mem_begin  <= ? -- DATE_NOW
                           AND mem.mem_end     > ? -- DATE_NOW
                           AND mem.mem_leader = true), 0) AS num_leader,
                       COALESCE((SELECT COUNT(*) AS count
                          FROM ' . TBL_MEMBERS . ' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem_end < ?  -- DATE_NOW
                           AND NOT EXISTS (
                               SELECT 1
                                 FROM ' . TBL_MEMBERS . ' AS act
                                WHERE act.mem_rol_id = mem.mem_rol_id
                                  AND act.mem_usr_id = mem.mem_usr_id
                                  AND ? BETWEEN act.mem_begin AND act.mem_end -- DATE_NOW
                           )), 0) AS num_former -- DATE_NOW
                  FROM ' . TBL_ROLES . ' AS rol
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat_id = rol_cat_id
                       ' . (strlen($categoryUUID) > 1 ? ' AND cat_uuid = \'' . $categoryUUID . '\'' : '') . '
             LEFT JOIN ' . TBL_EVENTS . ' ON dat_rol_id = rol_id
                 WHERE (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )';

        switch ($this->roleType) {
            case GroupsRoles::ROLE_TYPE_INACTIVE:
                $sql .= ' AND rol_valid   = false
                         AND cat_name_intern <> \'EVENTS\' ';
                break;

            case GroupsRoles::ROLE_TYPE_ACTIVE:
                $sql .= ' AND rol_valid   = true
                         AND cat_name_intern <> \'EVENTS\' ';
                break;

            case GroupsRoles::ROLE_TYPE_EVENT_PARTICIPATION:
                $sql .= ' AND cat_name_intern = \'EVENTS\' ';
                break;
        }

        if ($this->roleType == GroupsRoles::ROLE_TYPE_INACTIVE && $gCurrentUser->isAdministrator()) {
            // if inactive roles should be shown, then show all of them to administrator
            $sql .= '';
        } else {
            // create a list with all role IDs that the user is allowed to view
            $visibleRoles = '\'' . implode('\', \'', $gCurrentUser->getRolesViewMemberships()) . '\'';
            if ($visibleRoles !== '') {
                $sql .= ' AND rol_uuid IN (' . $visibleRoles . ')';
            } else {
                $sql .= ' AND rol_uuid IS NULL ';
            }
        }

        if ($this->roleType === GroupsRoles::ROLE_TYPE_EVENT_PARTICIPATION) {
            $sql .= ' ORDER BY cat_sequence, dat_begin DESC, rol_name ';
        } else {
            $sql .= ' ORDER BY cat_sequence, rol_name ';
        }

        $queryParameters = array(
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            $gCurrentOrgId
        );

        $this->data = $gDb->getArrayFromSql($sql, $queryParameters);
    }
}
