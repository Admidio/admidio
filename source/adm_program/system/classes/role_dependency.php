<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_role_dependencies
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class RoleDependency
{
    var $db;

    var $role_id_parent;
    var $role_id_child;
    var $comment;
    var $usr_id;
    var $timestamp;

    var $role_id_parent_orig;
    var $role_id_child_orig;

    var $persisted;


    // Konstruktor
    public function __construct(&$db)
    {
        $this->db =& $db;
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
                $this->role_id_parent      = $row->rld_rol_id_parent;
                $this->role_id_child       = $row->rld_rol_id_child;
                $this->comment             = $row->rld_comment;
                $this->timestamp           = $row->rld_timestamp;
                $this->usr_id              = $row->rld_usr_id;

                $this->role_id_parent_orig = $row->rld_rol_id_parent;
                $this->role_id_child_orig  = $row->rld_rol_id_child;
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

    // alle Klassenvariablen wieder zuruecksetzen
    public function clear()
    {
        $this->role_id_parent      = 0;
        $this->role_id_child       = 0;
        $this->comment             = '';
        $this->usr_id              = 0;
        $this->timestamp           = '';

        $this->role_id_parent_orig = 0;
        $this->role_id_child_orig  = 0;

        $persisted = false;
    }

    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    public function update($login_user_id)
    {
        if(!isEmpty() && $login_user_id > 0 && is_numeric($login_user_id))
        {
            $sql = 'UPDATE '. TBL_ROLE_DEPENDENCIES. ' SET rld_rol_id_parent = \''.$this->role_id_parent.'\'
                                                         , rld_rol_id_child  = \''.$this->role_id_child.'\'
                                                         , rld_comment       = \''.$this->comment.'\'
                                                         , rld_timestamp     = \''.DATETIME_NOW.'\'
                                                         , rld_usr_id        = '.$login_user_id.'
                     WHERE rld_rol_id_parent = '.$this->role_id_parent_orig.'
                       AND rld_rol_id_child  = '.$this->role_id_child_orig;
            $this->db->query($sql);
            $persisted = true;
            return 0;
        }
        return -1;
    }

    public function insert($login_user_id)
    {
        if(!$this->isEmpty() && $login_user_id > 0 && is_numeric($login_user_id))
        {
            $sql = 'INSERT INTO '. TBL_ROLE_DEPENDENCIES. ' 
                                (rld_rol_id_parent,rld_rol_id_child,rld_comment,rld_usr_id,rld_timestamp)
                         VALUES ('.$this->role_id_parent.', '.$this->role_id_child.', \''.$this->comment.'\', '.$login_user_id.', \''.DATETIME_NOW.'\') ';
            $this->db->query($sql);
            $persisted = true;
            return 0;
        }
        return -1;
    }

    public function isEmpty()
    {
        if ($this->role_id_parent == 0 && $this->role_id_child == 0)
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }

    // aktuelle Rollenabhaengigkeit loeschen
    public function delete()
    {
        $sql    = 'DELETE FROM '. TBL_ROLE_DEPENDENCIES.
                  ' WHERE rld_rol_id_child  = '.$this->role_id_child_orig.
                    ' AND rld_rol_id_parent = '.$this->role_id_parent_orig;
        $this->db->query($sql);

        $this->clear();
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

    public function setParent($parentId)
    {
        if($parentId > 0 && is_numeric($parentId))
        {
            $this->role_id_parent = $parentId;
            $persisted = false;
            return 0;
        }
        return -1;
    }

    public function setChild($childId)
    {
        if($childId > 0 && is_numeric($childId))
        {
            $this->role_id_child = $childId;
            $persisted = false;
            return 0;
        }
        return -1;
    }

    public function updateMembership()
    {
        if(0 != $this->role_id_parent and 0 != $this->role_id_child )
        {
            $sql = 'SELECT mem_usr_id FROM '. TBL_MEMBERS.
                   ' WHERE mem_rol_id = '.$this->role_id_child.'
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'';
            $result = $this->db->query($sql);

            $num_rows = $this->db->num_rows($result);
            if ($num_rows)
            {
                $sql = 'INSERT IGNORE INTO '. TBL_MEMBERS. ' (mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader) VALUES ';

                while ($row = $this->db->fetch_object($result))
                {
                    $sql .= '('.$this->role_id_parent.', '.$row->mem_usr_id.', \''.DATE_NOW.'\', \'9999-12-31\', 0),';
                }
                //Das letzte Komma wieder wegschneiden
                $sql = substr($sql,0,-1);
                
                $this->db->query($sql);
            }
            return 0;
        }
        return -1;
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

}
?>