<?php
/**
 ***********************************************************************************************
 * Class to manage dependencies between different roles.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class RoleDependency
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected $db;
    /**
     * @var int
     */
    protected $roleIdParent = 0;
    /**
     * @var int
     */
    protected $roleIdChild  = 0;
    /**
     * @var string
     */
    protected $comment = '';
    /**
     * @var int
     */
    protected $usrId = 0;
    /**
     * @var string
     */
    protected $timestamp = '';
    /**
     * @var int
     */
    protected $roleIdParentOrig = 0;
    /**
     * @var int
     */
    protected $roleIdChildOrig = 0;
    /**
     * @var bool
     */
    protected $persisted = false;

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
     * aktuelle Rollenabhaengigkeit loeschen
     */
    public function delete()
    {
        $sql = 'DELETE FROM '.TBL_ROLE_DEPENDENCIES.'
                 WHERE rld_rol_id_child  = ? -- $this->roleIdChildOrig
                   AND rld_rol_id_parent = ? -- $this->roleIdParentOrig';
        $this->db->queryPrepared($sql, array($this->roleIdChildOrig, $this->roleIdParentOrig));

        $this->clear();
    }

    /**
     * Rollenabhaengigkeit aus der Datenbank auslesen
     * @param int $childRoleId
     * @param int $parentRoleId
     * @return bool
     */
    public function get($childRoleId, $parentRoleId)
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
     * @param int      $childId
     * @return array<int,int>
     */
    public static function getParentRoles(Database $database, $childId)
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
     * @param int      $parentId
     * @return array<int,int>
     */
    public static function getChildRoles(Database $database, $parentId)
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
    public function isEmpty()
    {
        return $this->roleIdParent === 0 && $this->roleIdChild === 0;
    }

    /**
     * @param int $loginUserId
     * @return bool
     */
    public function insert($loginUserId)
    {
        if ($loginUserId > 0 && !$this->isEmpty()) {
            $sql = 'INSERT INTO '.TBL_ROLE_DEPENDENCIES.'
                           (rld_rol_id_parent, rld_rol_id_child, rld_comment, rld_usr_id, rld_timestamp)
                    VALUES (?, ?, ?, ?, ?) -- $this->roleIdParent, $this->roleIdChild, $this->comment, $loginUserId, DATETIME_NOW';
            $queryParams = array($this->roleIdParent, $this->roleIdChild, $this->comment, $loginUserId, DATETIME_NOW);
            $this->db->queryPrepared($sql, $queryParams);
            $this->persisted = true;

            return true;
        }

        return false;
    }

    /**
     * @param Database $database
     * @param int      $parentId
     * @return bool
     */
    public static function removeChildRoles(Database $database, $parentId)
    {
        if ($parentId > 0) {
            $sql = 'DELETE FROM '.TBL_ROLE_DEPENDENCIES.'
                     WHERE rld_rol_id_parent = ? -- $parentId';
            $database->queryPrepared($sql, array($parentId));

            return true;
        }

        return false;
    }

    /**
     * @param int $parentId
     * @return bool
     */
    public function setParent($parentId)
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
    public function setChild($childId)
    {
        if ($childId > 0) {
            $this->roleIdChild = $childId;
            $this->persisted = false;

            return true;
        }

        return false;
    }

    /**
     * Es muss die ID des eingeloggten Users uebergeben werden, damit die Aenderung protokolliert werden kann
     * @param int $loginUserId
     * @return bool
     */
    public function update($loginUserId)
    {
        if ($loginUserId > 0 && !$this->isEmpty()) {
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
     */
    public function updateMembership()
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
            $member = new TableMembers($this->db);

            while ($memberUserId = $membershipStatement->fetchColumn()) {
                $member->startMembership($this->roleIdParent, (int) $memberUserId);
            }
        }

        return true;
    }
}
