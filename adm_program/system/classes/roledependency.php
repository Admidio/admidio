<?php
/******************************************************************************
 * Class to manage dependencies between different roles.
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class RoleDependency
{
    var $db;

    var $roleIdParent;
    var $roleIdChild;
    var $comment;
    var $usr_id;
    var $timestamp;

    var $roleIdParentOrig;
    var $roleIdChildOrig;

    var $persisted;


    // Konstruktor
    public function __construct(&$db)
    {
        $this->db =& $db;
        $this->clear();
    }

    // alle Klassenvariablen wieder zuruecksetzen
    public function clear()
    {
        $this->roleIdParent      = 0;
        $this->roleIdChild       = 0;
        $this->comment             = '';
        $this->usr_id              = 0;
        $this->timestamp           = '';

        $this->roleIdParentOrig = 0;
        $this->roleIdChildOrig  = 0;

        $persisted = false;
    }

    // aktuelle Rollenabhaengigkeit loeschen
    public function delete()
    {
        $sql    = 'DELETE FROM '. TBL_ROLE_DEPENDENCIES.
                  ' WHERE rld_rol_id_child  = '.$this->roleIdChildOrig.
                    ' AND rld_rol_id_parent = '.$this->roleIdParentOrig;
        $this->db->query($sql);

        $this->clear();
    }

    // Rollenabhaengigkeit aus der Datenbank auslesen
    public function get($childRoleId,$parentRoleId)
    {

        $this->clear();

        if($childRoleId > 0 && $parentRoleId > 0
        && is_numeric($childRoleId) && is_numeric($parentRoleId))
        {
            $sql = 'SELECT * FROM '. TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_child  = '.$childRoleId.'
                       AND rld_rol_id_parent = '.$parentRoleId;
            $this->db->query($sql);

            if($row = $this->db->fetch_object())
            {
                $this->roleIdParent      = $row->rld_rol_id_parent;
                $this->roleIdChild       = $row->rld_rol_id_child;
                $this->comment             = $row->rld_comment;
                $this->timestamp           = $row->rld_timestamp;
                $this->usr_id              = $row->rld_usr_id;

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
    
    static public function getChildRoles(&$db, $parentId)
    {
        $allChildIds = array();

        if($parentId > 0 && is_numeric($parentId))
        {
            $sql = 'SELECT rld_rol_id_child FROM '. TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_parent = '.$parentId;
            $db->query($sql);

            $num_rows = $db->num_rows();
            if ($num_rows)
            {
                while ($row = $db->fetch_object())
                {
                    $allChildIds[] = $row->rld_rol_id_child;
                }
            }
        }
        return $allChildIds;
    }
 
    static public function getParentRoles(&$db, $childId)
    {
        $allParentIds = array();

        if($childId > 0 && is_numeric($childId))
        {
            $sql = 'SELECT rld_rol_id_parent FROM '. TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_child = '.$childId;
            $db->query($sql);

            $num_rows = $db->num_rows();
            if ($num_rows)
            {
                while ($row = $db->fetch_object())
                {
                    $allParentIds[] = $row->rld_rol_id_parent;
                }
            }
        }
        return $allParentIds;
    }
 
    public function insert($login_user_id)
    {
        if(!$this->isEmpty() && $login_user_id > 0 && is_numeric($login_user_id))
        {
            $sql = 'INSERT INTO '. TBL_ROLE_DEPENDENCIES. ' 
                                (rld_rol_id_parent,rld_rol_id_child,rld_comment,rld_usr_id,rld_timestamp)
                         VALUES ('.$this->roleIdParent.', '.$this->roleIdChild.', \''.$this->comment.'\', '.$login_user_id.', \''.DATETIME_NOW.'\') ';
            $this->db->query($sql);
            $persisted = true;
            return 0;
        }
        return -1;
    }

    public function isEmpty()
    {
        if ($this->roleIdParent == 0 && $this->roleIdChild == 0)
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }

    static public function removeChildRoles(&$db, $parentId)
    {
        if($parentId > 0 && is_numeric($parentId))
        {
            $allChildIds = array();

            $sql = 'DELETE FROM '. TBL_ROLE_DEPENDENCIES.
                   ' WHERE rld_rol_id_parent = '.$parentId;
            $db->query($sql);

            return  0;
        }
        return -1;
    }

    public function setParent($parentId)
    {
        if($parentId > 0 && is_numeric($parentId))
        {
            $this->roleIdParent = $parentId;
            $persisted = false;
            return 0;
        }
        return -1;
    }

    public function setChild($childId)
    {
        if($childId > 0 && is_numeric($childId))
        {
            $this->roleIdChild = $childId;
            $persisted = false;
            return 0;
        }
        return -1;
    }
    
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    public function update($login_user_id)
    {
        if(!isEmpty() && $login_user_id > 0 && is_numeric($login_user_id))
        {
            $sql = 'UPDATE '. TBL_ROLE_DEPENDENCIES. ' SET rld_rol_id_parent = \''.$this->roleIdParent.'\'
                                                         , rld_rol_id_child  = \''.$this->roleIdChild.'\'
                                                         , rld_comment       = \''.$this->comment.'\'
                                                         , rld_timestamp     = \''.DATETIME_NOW.'\'
                                                         , rld_usr_id        = '.$login_user_id.'
                     WHERE rld_rol_id_parent = '.$this->roleIdParentOrig.'
                       AND rld_rol_id_child  = '.$this->roleIdChildOrig;
            $this->db->query($sql);
            $persisted = true;
            return 0;
        }
        return -1;
    }

    /* Adds all active memberships of the child role to the parent role.
     * If a membership still exists than start date will not be changed. Only
     * the end date will be set to 31.12.9999.
     * @return Returns -1 if no parent or child row exists
     */
    public function updateMembership()
    {
        if($this->roleIdParent != 0 and $this->roleIdChild != 0)
        {
            $sql = 'SELECT mem_usr_id FROM '. TBL_MEMBERS.
                   ' WHERE mem_rol_id = '.$this->roleIdChild.'
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'';
            $result = $this->db->query($sql);

            $num_rows = $this->db->num_rows($result);
            if ($num_rows)
            {
                $member = new TableMembers($this->db);

                while ($row = $this->db->fetch_object($result))
                {
                    $member->startMembership($this->roleIdParent, $row->mem_usr_id);
                }
            }
            return 0;
        }
        return -1;
    }
}
?>