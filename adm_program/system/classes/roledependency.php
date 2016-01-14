<?php
/**
 ***********************************************************************************************
 * Class to manage dependencies between different roles.
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class RoleDependency
 */
class RoleDependency
{
    protected $db;          ///< An object of the class Database for communication with the database

    public $roleIdParent;
    public $roleIdChild;
    public $comment;
    public $usr_id;
    public $timestamp;

    public $roleIdParentOrig;
    public $roleIdChildOrig;

    public $persisted;

    /**
     * Constructor that will create an object of a recordset of the specified table.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     */
    public function __construct(&$database)
    {
        $this->db =& $database;
        $this->clear();
    }

    /**
     * Initializes all class parameters and deletes all read data.
     */
    public function clear()
    {
        $this->roleIdParent     = 0;
        $this->roleIdChild      = 0;
        $this->comment          = '';
        $this->usr_id           = 0;
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
        $sql = 'DELETE FROM '. TBL_ROLE_DEPENDENCIES.
               ' WHERE rld_rol_id_child  = '.$this->roleIdChildOrig.
                 ' AND rld_rol_id_parent = '.$this->roleIdParentOrig;
        $this->db->query($sql);

        $this->clear();
    }

    /**
     * Rollenabhaengigkeit aus der Datenbank auslesen
     * @param int $childRoleId
     * @param int $parentRoleId
     */
    public function get($childRoleId, $parentRoleId)
    {
        $this->clear();

        if(is_numeric($childRoleId) && is_numeric($parentRoleId) && $childRoleId > 0 && $parentRoleId > 0)
        {
            $sql = 'SELECT *
                      FROM '. TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_child  = '.$childRoleId.'
                       AND rld_rol_id_parent = '.$parentRoleId;
            $roleDependenciesStatement = $this->db->query($sql);

            $row = $roleDependenciesStatement->fetchObject();
            if($row)
            {
                $this->roleIdParent     = $row->rld_rol_id_parent;
                $this->roleIdChild      = $row->rld_rol_id_child;
                $this->comment          = $row->rld_comment;
                $this->timestamp        = $row->rld_timestamp;
                $this->usr_id           = $row->rld_usr_id;
                $this->roleIdParentOrig = $row->rld_rol_id_parent;
                $this->roleIdChildOrig  = $row->rld_rol_id_child;
            }
            else
            {
                $this->clear();
            }
        }
        else
        {
            $this->clear();
        }
    }

    /**
     * @param object $db
     * @param int    $parentId
     * @return array
     */
    public static function getChildRoles(&$db, $parentId)
    {
        $allChildIds = array();

        if(is_numeric($parentId) && $parentId > 0)
        {
            $sql = 'SELECT rld_rol_id_child
                      FROM '. TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_parent = '.$parentId;
            $pdoStatement = $db->query($sql);

            $numRows = $pdoStatement->rowCount();
            if ($numRows)
            {
                while ($row = $pdoStatement->fetchObject())
                {
                    $allChildIds[] = $row->rld_rol_id_child;
                }
            }
        }

        return $allChildIds;
    }

    /**
     * @param object $db
     * @param int    $childId
     * @return array
     */
    public static function getParentRoles(&$db, $childId)
    {
        $allParentIds = array();

        if(is_numeric($childId) && $childId > 0)
        {
            $sql = 'SELECT rld_rol_id_parent
                      FROM '.TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_child = '.$childId;
            $pdoStatement = $db->query($sql);

            $numRows = $pdoStatement->rowCount();
            if ($numRows)
            {
                while ($row = $pdoStatement->fetchObject())
                {
                    $allParentIds[] = $row->rld_rol_id_parent;
                }
            }
        }

        return $allParentIds;
    }

    /**
     * @param int $login_user_id
     * @return
     */
    public function insert($login_user_id)
    {
        if(!$this->isEmpty() && is_numeric($login_user_id) && $login_user_id > 0)
        {
            $sql = 'INSERT INTO '.TBL_ROLE_DEPENDENCIES.'
                                (rld_rol_id_parent,rld_rol_id_child,rld_comment,rld_usr_id,rld_timestamp)
                         VALUES ('.$this->roleIdParent.', '.$this->roleIdChild.', \''.$this->comment.'\', '.$login_user_id.', \''.DATETIME_NOW.'\') ';
            $this->db->query($sql);
            $this->persisted = true;

            return 0;
        }

        return -1;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        if ($this->roleIdParent === 0 && $this->roleIdChild === 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @param object $db
     * @param int    $parentId
     * @return
     */
    public static function removeChildRoles(&$db, $parentId)
    {
        if(is_numeric($parentId) && $parentId > 0)
        {
            $sql = 'DELETE FROM '.TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_parent = '.$parentId;
            $db->query($sql);

            return 0;
        }

        return -1;
    }

    /**
     * @param int $parentId
     * @return
     */
    public function setParent($parentId)
    {
        if(is_numeric($parentId) && $parentId > 0)
        {
            $this->roleIdParent = $parentId;
            $this->persisted = false;

            return 0;
        }

        return -1;
    }

    /**
     * @param int $childId
     * @return
     */
    public function setChild($childId)
    {
        if(is_numeric($childId) && $childId > 0)
        {
            $this->roleIdChild = $childId;
            $this->persisted = false;

            return 0;
        }

        return -1;
    }

    /**
     * Es muss die ID des eingeloggten Users uebergeben werden, damit die Aenderung protokolliert werden kann
     * @param int $login_user_id
     * @return
     */
    public function update($login_user_id)
    {
        if(!$this->isEmpty() && is_numeric($login_user_id) && $login_user_id > 0)
        {
            $sql = 'UPDATE '.TBL_ROLE_DEPENDENCIES.' SET rld_rol_id_parent = \''.$this->roleIdParent.'\'
                                                       , rld_rol_id_child  = \''.$this->roleIdChild.'\'
                                                       , rld_comment       = \''.$this->comment.'\'
                                                       , rld_timestamp     = \''.DATETIME_NOW.'\'
                                                       , rld_usr_id        = '.$login_user_id.'
                     WHERE rld_rol_id_parent = '.$this->roleIdParentOrig.'
                       AND rld_rol_id_child  = '.$this->roleIdChildOrig;
            $this->db->query($sql);
            $this->persisted = true;

            return 0;
        }

        return -1;
    }

    /**
     * Adds all active memberships of the child role to the parent role.
     * If a membership still exists than start date will not be changed. Only
     * the end date will be set to 31.12.9999.
     * @return Returns -1 if no parent or child row exists
     */
    public function updateMembership()
    {
        if($this->roleIdParent > 0 && $this->roleIdChild > 0)
        {
            $sql = 'SELECT mem_usr_id
                      FROM '.TBL_MEMBERS.
                   ' WHERE mem_rol_id = '.$this->roleIdChild.'
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'';
            $membershipStatement = $this->db->query($sql);

            if ($membershipStatement->rowCount())
            {
                $member = new TableMembers($this->db);

                while ($row = $membershipStatement->fetch())
                {
                    $member->startMembership($this->roleIdParent, $row['mem_usr_id']);
                }
            }

            return 0;
        }

        return -1;
    }
}
