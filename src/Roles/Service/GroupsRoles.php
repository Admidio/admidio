<?php
namespace Admidio\Roles\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use DateTime;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use RoleDependency;
use Role;
use User;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class GroupsRoles
{
    protected Role $roleRessource;
    protected Database $db;
    protected string $UUID;
    protected bool $eventRole = false;

    /**
     * Constructor that will create an object of a recordset of the table adm_lists.
     * If the id is set than the specific list will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $roleUUID UUID if the menu ressource that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $roleUUID = '')
    {
        $this->db = $database;
        $this->UUID = $roleUUID;
        $this->roleRessource = new Role($database);

        if ($roleUUID !== '') {
            $this->roleRessource->readDataByUuid($roleUUID);
            $this->eventRole = $this->roleRessource->getValue('cat_name_intern') === 'EVENTS';
        }
    }

    /**
     * Export every member of a role into one vCard file.
     * @throws Exception
     */
    public function export()
    {
        global $gCurrentUser, $gCurrentOrganization, $gProfileFields;

        $role = new Role($this->db);
        $role->readDataByUuid($this->UUID);

        if (!$gCurrentUser->hasRightViewProfiles($role->getValue('rol_id'))) {
            throw new Exception('SYS_NO_RIGHTS');
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
        $pdoStatement = $this->db->queryPrepared($sql, array($role->getValue('rol_id'), DATE_NOW, DATE_NOW));

        while ($memberUserId = $pdoStatement->fetchColumn()) {
            $user = new User($this->db, $gProfileFields, (int)$memberUserId);
            // create vcard and check if user is allowed to edit profile, so he can see more data
            echo $user->getVCard();
        }
    }

    /**
     * Save data from the menu form into the database.
     * @throws Exception
     */
    public function save()
    {
        global $gCurrentOrgId, $gCurrentSession, $gCurrentUserId;

        // check form field input and sanitized it from malicious content
        $groupsRolesEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $groupsRolesEditForm->validate($_POST);

        if ($this->roleRessource->getValue('rol_name') !== $_POST['rol_name']) {
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
                $this->roleRessource->getValue('rol_id'),
                $gCurrentOrgId
            );
            $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

            if ($pdoStatement->fetchColumn() > 0) {
                throw new Exception('SYS_ROLE_NAME_EXISTS');
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
                throw new Exception('SYS_DATE_INVALID', array('SYS_VALID_FROM', 'YYYY-MM-DD'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_start_date'] = $validFromDate->format('Y-m-d');
            }
        }

        if (isset($_POST['rol_end_date']) && strlen($_POST['rol_end_date']) > 0) {
            $validToDate = DateTime::createFromFormat('Y-m-d', $_POST['rol_end_date']);
            if (!$validToDate) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_VALID_TO', 'YYYY-MM-DD'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_end_date'] = $validToDate->format('Y-m-d');
            }
        }

        // DateTo should be greater than DateFrom (Timestamp must be less)
        if (isset($_POST['rol_start_date']) && strlen($_POST['rol_start_date']) > 0 && strlen($_POST['rol_end_date']) > 0) {
            if ($validFromDate > $validToDate) {
                throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
            }
        }

        // ------------------------------------------------
        // Check valid format of time input
        // ------------------------------------------------

        if (isset($_POST['rol_start_time']) && strlen($_POST['rol_start_time']) > 0) {
            $validFromTime = DateTime::createFromFormat('Y-m-d H:i', DATE_NOW . ' ' . $_POST['rol_start_time']);
            if (!$validFromTime) {
                throw new Exception('SYS_TIME_INVALID', array('SYS_TIME_FROM', 'HH:ii'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_start_time'] = $validFromTime->format('H:i:s');
            }
        }

        if (isset($_POST['rol_end_time']) && strlen($_POST['rol_end_time']) > 0) {
            $validToTime = DateTime::createFromFormat('Y-m-d H:i', DATE_NOW . ' ' . $_POST['rol_end_time']);
            if (!$validToTime) {
                throw new Exception('SYS_TIME_INVALID', array('SYS_TIME_TO', 'HH:ii'));
            } else {
                // now write date and time with database format to date object
                $formValues['rol_end_time'] = $validToTime->format('H:i:s');
            }
        }

        // Check whether the maximum number of members has already been exceeded in the event , also if the maximum number of members was reduced.
        if (isset($_POST['rol_max_members']) && $this->UUID !== '' && (int)$_POST['rol_max_members'] !== (int)$this->roleRessource->getValue('rol_max_members')) {
            // Count how many people already have the role, without leaders
            $this->roleRessource->setValue('rol_max_members', (int)$_POST['rol_max_members']);
            $numFreePlaces = $this->roleRessource->countVacancies();

            if ($numFreePlaces < 0) {
                throw new Exception('SYS_ROLE_MAX_MEMBERS', array($this->roleRessource->getValue('rol_name')));
            }
        }

        // write POST parameters in roles object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'rol_')) {
                $this->roleRessource->setValue($key, $value);
            }
        }

        $this->db->startTransaction();
        $this->roleRessource->save();

        // save role dependencies in database
        if (array_key_exists('dependent_roles', $_POST) && !$this->eventRole) {
            $sentChildRoles = array_map('intval', $_POST['dependent_roles']);

            $roleDep = new RoleDependency($this->db);

            // Fetches a list of the selected dependent roles
            $dbChildRoles = RoleDependency::getChildRoles($this->db, $this->roleRessource->getValue('rol_id'));

            // remove all roles that are no longer selected
            if (count($dbChildRoles) > 0) {
                foreach ($dbChildRoles as $dbChildRole) {
                    if (!in_array($dbChildRole, $sentChildRoles, true)) {
                        $roleDep->get($dbChildRole, $this->roleRessource->getValue('rol_id'));
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
                        $roleDep->setParent($this->roleRessource->getValue('rol_id'));
                        $roleDep->insert($gCurrentUserId);

                        // add all members of the ChildRole to the ParentRole
                        $roleDep->updateMembership();
                    }
                }
            }
        } else {
            RoleDependency::removeChildRoles($this->db, $this->roleRessource->getValue('rol_id'));
        }

        $this->db->endTransaction();
    }
}
