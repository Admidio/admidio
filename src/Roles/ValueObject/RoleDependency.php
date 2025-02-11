<?php
namespace Admidio\Roles\ValueObject;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Roles\Entity\RolesDependencies;

/**
 * @brief Class to manage dependencies between different roles.
 * 
 * The RoleDependency DB record differs from all other records, since
 * there is no auto-increment ID, but a primary key consisting of parent and child role.
 * 
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class RoleDependency
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;
    /**
     * @var int
     */
    protected int $roleIdParent = 0;
    /**
     * @var int
     */
    protected int $roleIdChild  = 0;
    /**
     * @var string
     */
    protected string $comment = '';
    /**
     * @var int
     */
    protected int $usrId = 0;
    /**
     * @var string
     */
    protected string $timestamp = '';
    /**
     * @var int
     */
    protected int $roleIdParentOrig = 0;
    /**
     * @var int
     */
    protected int $roleIdChildOrig = 0;
    /**
     * @var bool
     */
    protected bool $persisted = false;

    /**
     * Constructor that will create an object of a recordset of the specified table.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(Database $database)
    {
        $this->db =& $database;
    }

    /**
     * Initializes all class parameters and deletes all read data.
     */
    public function clear()
    {
        $this->roleIdParent     = 0;
        $this->roleIdChild      = 0;
        $this->comment          = '';
        $this->usrId            = 0;
        $this->timestamp        = '';
        $this->roleIdParentOrig = 0;
        $this->roleIdChildOrig  = 0;
        $this->persisted        = false;
    }

    /**
     * Delete current role dependency
     * @throws Exception
     */
    public function delete()
    {
        $dep = new RolesDependencies($this->db);
        $found = $dep->readDataByColumns(['rld_rol_id_parent' => $this->roleIdParentOrig, 'rld_rol_id_child' => $this->roleIdChildOrig]);
        if ($found) {
            $dep->delete();
        }
        $this->clear();
    }

    /**
     * Read role dependency from the database
     * @param int $childRoleId
     * @param int $parentRoleId
     * @return bool
     * @throws Exception
     */
    public function get(int $childRoleId, int $parentRoleId): bool
    {
        $this->clear();

        if ($childRoleId === 0 || $parentRoleId === 0) {
            return false;
        }

        $sql = 'SELECT *
                  FROM '. TBL_ROLE_DEPENDENCIES.'
                 WHERE rld_rol_id_child  = ? -- $childRoleId
                   AND rld_rol_id_parent = ? -- $parentRoleId';
        $roleDependenciesStatement = $this->db->queryPrepared($sql, array($childRoleId, $parentRoleId));

        $row = $roleDependenciesStatement->fetch();
        if ($row) {
            $this->roleIdParent     = $row['rld_rol_id_parent'];
            $this->roleIdChild      = $row['rld_rol_id_child'];
            $this->comment          = $row['rld_comment'];
            $this->timestamp        = $row['rld_timestamp'];
            $this->usrId            = $row['rld_usr_id'];
            $this->roleIdParentOrig = $row['rld_rol_id_parent'];
            $this->roleIdChildOrig  = $row['rld_rol_id_child'];

            return true;
        }

        return false;
    }

    /**
     * @param Database $database
     * @param int $childId
     * @return array<int,int>
     * @throws Exception
     */
    public static function getParentRoles(Database $database, int $childId): array
    {
        $allParentIds = array();

        if ($childId > 0) {
            $sql = 'SELECT rld_rol_id_parent
                      FROM '.TBL_ROLE_DEPENDENCIES.'
                     WHERE rld_rol_id_child = ? -- $childId';
            $pdoStatement = $database->queryPrepared($sql, array($childId));

            if ($pdoStatement->rowCount() > 0) {
                while ($roleParentId = $pdoStatement->fetchColumn()) {
                    $allParentIds[] = (int) $roleParentId;
                }
            }
        }

        return $allParentIds;
    }

    /**
     * @param Database $database
     * @param int $parentId
     * @return array<int,int>
     * @throws Exception
     */
    public static function getChildRoles(Database $database, int $parentId): array
    {
        $allChildIds = array();

        if ($parentId > 0) {
            $sql = 'SELECT rld_rol_id_child
                      FROM '.TBL_ROLE_DEPENDENCIES.'
                     WHERE rld_rol_id_parent = ? -- $parentId';
            $pdoStatement = $database->queryPrepared($sql, array($parentId));

            if ($pdoStatement->rowCount() > 0) {
                while ($roleChildId = $pdoStatement->fetchColumn()) {
                    $allChildIds[] = (int) $roleChildId;
                }
            }
        }

        return $allChildIds;
    }

    /**
     * Check if roleIdParent and roleIdChild is 0
     * @return bool Returns true if roleIdParent and roleIdChild is 0
     */
    public function isEmpty(): bool
    {
        return $this->roleIdParent === 0 && $this->roleIdChild === 0;
    }

    /**
     * @param int $loginUserId
     * @return bool
     * @throws Exception
     */
    public function insert(int $loginUserId): bool
    {
        if ($loginUserId > 0 && !$this->isEmpty()) {
            $dep = new RolesDependencies($this->db);
            $dep->setValue('rld_rol_id_parent', $this->roleIdParent);
            $dep->setValue('rld_rol_id_child', $this->roleIdChild);
            $dep->setValue('rld_comment', $this->comment);
            $dep->setValue('rld_usr_id', $loginUserId);
            $dep->setValue('rld_timestamp', DATETIME_NOW);
            $dep->save();
            $this->persisted = true;

            return true;
        }

        return false;
    }

    /**
     * @param Database $database
     * @param int $parentId
     * @return bool
     * @throws Exception
     */
    public static function removeChildRoles(Database $database, int $parentId): bool
    {
        if ($parentId > 0) {
            $children = self::getChildRoles($database, $parentId);
            foreach ($children as $child) {
                $dep = new RolesDependencies($database);
                $found = $dep->readDataByColumns(['rld_rol_id_parent' => $parentId, 'rld_rol_id_child' => $child]);
                if ($found) {
                    $dep->delete();
                }
            }
            return true;
        }

        return false;
    }

    /**
     * @param int $parentId
     * @return bool
     */
    public function setParent(int $parentId): bool
    {
        if ($parentId > 0) {
            $this->roleIdParent = $parentId;
            $this->persisted = false;

            return true;
        }

        return false;
    }

    /**
     * @param int $childId
     * @return bool
     */
    public function setChild(int $childId): bool
    {
        if ($childId > 0) {
            $this->roleIdChild = $childId;
            $this->persisted = false;

            return true;
        }

        return false;
    }

    /**
     * The ID of the logged-in user must be transferred so that the change can be logged
     * @param int $loginUserId
     * @return bool
     * @throws Exception
     */
    public function update(int $loginUserId): bool
    {
        if ($loginUserId > 0 && !$this->isEmpty()) {
            // TODO_RK
            $sql = 'UPDATE '.TBL_ROLE_DEPENDENCIES.'
                       SET rld_rol_id_parent = ? -- $this->roleIdParent
                         , rld_rol_id_child  = ? -- $this->roleIdChild
                         , rld_comment       = ? -- $this->comment
                         , rld_timestamp     = ? -- DATETIME_NOW
                         , rld_usr_id        = ? -- $loginUserId
                     WHERE rld_rol_id_parent = ? -- $this->roleIdParentOrig
                       AND rld_rol_id_child  = ? -- $this->roleIdChildOrig';
            $queryParams = array(
                $this->roleIdParent,
                $this->roleIdChild,
                $this->comment,
                DATETIME_NOW,
                $loginUserId,
                $this->roleIdParentOrig,
                $this->roleIdChildOrig
            );
            $this->db->queryPrepared($sql, $queryParams);
            $this->persisted = true;

            return true;
        }

        return false;
    }

    /**
     * Adds all active memberships of the child role to the parent role.
     * If a membership still exists than start date will not be changed. Only
     * the end date will be set to 31.12.9999.
     * @return bool Returns false if no parent or child row exists
     * @throws Exception
     */
    public function updateMembership(): bool
    {
        if ($this->roleIdParent === 0 || $this->roleIdChild === 0) {
            return false;
        }

        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $this->roleIdChild
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW';
        $membershipStatement = $this->db->queryPrepared($sql, array($this->roleIdChild, DATE_NOW, DATE_NOW));

        if ($membershipStatement->rowCount() > 0) {
            $parentRole = new Role($this->db, $this->roleIdParent);

            while ($memberUserId = $membershipStatement->fetchColumn()) {
                $parentRole->startMembership((int) $memberUserId);
            }
        }

        return true;
    }
}
