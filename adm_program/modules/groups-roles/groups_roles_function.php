<?php
/**
 ***********************************************************************************************
 * Various functions for roles handling
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * role_uuid : UUID of role, that should be edited
 * mode :  edit       - create or edit role
 *         delete     - delete role
 *         export     - Export vCard of role
 *         activate   - set role active
 *         deactivate - set role inactive
 *
 *****************************************************************************/

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'export', 'activate', 'deactivate')));

    if ($getMode !== 'export') {
        // only members who are allowed to create and edit roles should have access to these functions
        if (!$gCurrentUser->manageRoles()) {
            throw new AdmException('SYS_NO_RIGHTS');
        }

        if(in_array($getMode, array('delete', 'activate', 'deactivate'))) {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
        }
    }

    $eventRole = false;
    $role = new TableRoles($gDb);

    if ($getRoleUuid !== '') {
        $role->readDataByUuid($getRoleUuid);
        $eventRole = $role->getValue('cat_name_intern') === 'EVENTS';

        // Check if the role belongs to the current organization
        if ((int)$role->getValue('cat_org_id') !== $gCurrentOrgId && $role->getValue('cat_org_id') > 0) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    }

    if ($getMode === 'edit') {
        // create or edit role

        if (isset($_SESSION['groupsRolesEditForm'])) {
            $groupsRolesEditForm = $_SESSION['groupsRolesEditForm'];
            $formValues = $groupsRolesEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        if ($role->getValue('rol_name') !== $_POST['rol_name']) {
            // check if the role already exists
            $sql = 'SELECT COUNT(*) AS count
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_name   = ? -- $_POST[\'rol_name\']
                       AND rol_cat_id = ? -- $_POST[\'rol_cat_id\']
                       AND rol_id    <> ? -- $role->getValue(\'rol_id\')
                       AND (  cat_org_id = ? -- $gCurrentOrgId
                           OR cat_org_id IS NULL )';
            $queryParams = array(
                $_POST['rol_name'],
                (int)$_POST['rol_cat_id'],
                $role->getValue('rol_id'),
                $gCurrentOrgId
            );
            $pdoStatement = $gDb->queryPrepared($sql, $queryParams);

            if ($pdoStatement->fetchColumn() > 0) {
                throw new AdmException('SYS_ROLE_NAME_EXISTS');
            }
        }

        // ------------------------------------------------
        // Check valid format of date input
        // ------------------------------------------------

        $validFromDate = '';
        $validToDate = '';

        if (isset($_POST['rol_start_date']) && strlen($_POST['rol_start_date']) > 0) {
            $validFromDate = DateTime::createFromFormat('Y-m-d', $_POST['rol_start_date']);
            if (!$validFromDate) {
                throw new AdmException('SYS_DATE_INVALID', array('SYS_VALID_FROM', 'YYYY-MM-DD'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_start_date'] = $validFromDate->format('Y-m-d');
            }
        }

        if (isset($_POST['rol_end_date']) && strlen($_POST['rol_end_date']) > 0) {
            $validToDate = DateTime::createFromFormat('Y-m-d', $_POST['rol_end_date']);
            if (!$validToDate) {
                throw new AdmException('SYS_DATE_INVALID', array('SYS_VALID_TO', 'YYYY-MM-DD'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_end_date'] = $validToDate->format('Y-m-d');
            }
        }

        // DateTo should be greater than DateFrom (Timestamp must be less)
        if (isset($_POST['rol_start_date']) && strlen($_POST['rol_start_date']) > 0 && strlen($_POST['rol_end_date']) > 0) {
            if ($validFromDate > $validToDate) {
                throw new AdmException('SYS_DATE_END_BEFORE_BEGIN');
            }
        }

        // ------------------------------------------------
        // Check valid format of time input
        // ------------------------------------------------

        if (isset($_POST['rol_start_time']) && strlen($_POST['rol_start_time']) > 0) {
            $validFromTime = DateTime::createFromFormat('Y-m-d H:i', DATE_NOW . ' ' . $_POST['rol_start_time']);
            if (!$validFromTime) {
                throw new AdmException('SYS_TIME_INVALID', array('SYS_TIME_FROM', 'HH:ii'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_start_time'] = $validFromTime->format('H:i:s');
            }
        }

        if (isset($_POST['rol_end_time']) && strlen($_POST['rol_end_time']) > 0) {
            $validToTime = DateTime::createFromFormat('Y-m-d H:i', DATE_NOW . ' ' . $_POST['rol_end_time']);
            if (!$validToTime) {
                throw new AdmException('SYS_TIME_INVALID', array('SYS_TIME_TO', 'HH:ii'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_end_time'] = $validToTime->format('H:i:s');
            }
        }

        // Check whether the maximum number of members has already been exceeded in the event , also if the maximum number of members was reduced.
        if (isset($_POST['rol_max_members']) && $getRoleUuid !== '' && (int)$_POST['rol_max_members'] !== (int)$role->getValue('rol_max_members')) {
            // Count how many people already have the role, without leaders
            $role->setValue('rol_max_members', (int)$_POST['rol_max_members']);
            $numFreePlaces = $role->countVacancies();

            if ($numFreePlaces < 0) {
                throw new AdmException('SYS_ROLE_MAX_MEMBERS', array($role->getValue('rol_name')));
            }
        }

        // write POST parameters in roles object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'rol_')) {
                $role->setValue($key, $value);
            }
        }

        $gDb->startTransaction();
        $role->save();

        // save role dependencies in database
        if (array_key_exists('dependent_roles', $_POST) && !$eventRole) {
            $sentChildRoles = array_map('intval', $_POST['dependent_roles']);

            $roleDep = new RoleDependency($gDb);

            // Fetches a list of the selected dependent roles
            $dbChildRoles = RoleDependency::getChildRoles($gDb, $role->getValue('rol_id'));

            // remove all roles that are no longer selected
            if (count($dbChildRoles) > 0) {
                foreach ($dbChildRoles as $dbChildRole) {
                    if (!in_array($dbChildRole, $sentChildRoles, true)) {
                        $roleDep->get($dbChildRole, $role->getValue('rol_id'));
                        $roleDep->delete();
                    }
                }
            }

            // add all new role dependencies to database
            if (count($sentChildRoles) > 0) {
                foreach ($sentChildRoles as $sentChildRole) {
                    if ($sentChildRole > 0 && !in_array($sentChildRole, $dbChildRoles, true)) {
                        $roleDep->clear();
                        $roleDep->setChild($sentChildRole);
                        $roleDep->setParent($role->getValue('rol_id'));
                        $roleDep->insert($gCurrentUserId);

                        // add all members of the ChildRole to the ParentRole
                        $roleDep->updateMembership();
                    }
                }
            }
        } else {
            RoleDependency::removeChildRoles($gDb, $role->getValue('rol_id'));
        }

        $gDb->endTransaction();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'deactivate') {
        // set role inactive
        // event roles and administrator cannot be set inactive
        $role->deactivate();
        echo 'done';
        exit();
    } elseif ($getMode === 'delete') {
        // delete role from database
        if ($role->delete()) {
            echo json_encode(array('status' => 'success'));
        }
        exit();
    } elseif ($getMode === 'activate') {
        // set role active
        $role->activate();
        echo 'done';
        exit();
    } elseif ($getMode === 'export') {
        // Export every member of a role into one vCard file

        $role = new TableRoles($gDb);
        $role->readDataByUuid($getRoleUuid);

        if (!$gCurrentUser->hasRightViewProfiles($role->getValue('rol_id'))) {
            throw new AdmException('SYS_NO_RIGHTS');
        }

        // create filename of organization name and role name
        $filename = $gCurrentOrganization->getValue('org_shortname') . '-' . str_replace('.', '', $role->getValue('rol_name')) . '.vcf';

        $filename = FileSystemUtils::getSanitizedPathEntry($filename);

        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        $sql = 'SELECT mem_usr_id
                      FROM ' . TBL_MEMBERS . '
                     WHERE mem_rol_id = ? -- $role->getValue(\'rol_id\')
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW';
        $pdoStatement = $gDb->queryPrepared($sql, array($role->getValue('rol_id'), DATE_NOW, DATE_NOW));

        while ($memberUserId = $pdoStatement->fetchColumn()) {
            $user = new User($gDb, $gProfileFields, (int)$memberUserId);
            // create vcard and check if user is allowed to edit profile, so he can see more data
            echo $user->getVCard();
        }
    }
} catch (AdmException|Exception $e) {
    if (in_array($getMode, array('activate', 'deactivate'))) {
        echo $e->getMessage();
    } elseif (in_array($getMode, array('edit', 'delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
