<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_role_dependencies
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

class RoleDependency
{
    var $db_connection;

    var $role_id_parent;
    var $role_id_child;
    var $comment;
    var $usr_id;
    var $timestamp;

    var $role_id_parent_orig;
    var $role_id_child_orig;   
   
   
    // Konstruktor
    function RoleDependency($connection)
    {
        $this->db_connection = $connection;
        $this->clear();
    }

    // Rollenabhängigkeit aus der Datenbank auslesen
    function get($childRoleId,$parentRoleId)
    {
        $sql = "SELECT * FROM ". TBL_ROLE_DEPENDENCIES. 
               " WHERE rld_role_id_child = $childRoleId 
                   AND rld_role_id_parent = $parentRoleId";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        if($row = mysql_fetch_object($result))
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

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        $this->role_id_parent      = 0;
        $this->role_id_child       = 0;
        $this->comment             = "";
        $this->usr_id              = 0;
        $this->timestamp           = "";

        $this->role_id_parent_orig = 0;
        $this->role_id_child_orig  = 0;
    }

    // aktuelle Userdaten in der Datenbank updaten
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    function update($login_user_id)
    {
        if($this->id > 0 && $login_user_id > 0)
        {
            $act_date = date("Y-m-d H:i:s", time());

            $sql = "UPDATE ". TBL_ROLE_DEPENDENCIES. " SET rld_role_id_parent = '$this->role_id_parent'
                                                         , rld_role_id_child  = '$this->role_id_child'
                                                         , rld_comment        = '$this->comment'
                                                         , rld_timestamp      = '$act_date'
                                                         , rld_usr_id         = $login_user_id " .
                    "WHERE rld_rol_id_parent = '$this->role_id_parent_orig'" .
                      "AND rld_rol_id_child  = '$this->role_id_child_orig'";

            $result = mysql_query($sql, $this->db_connection);
            db_error($result);
            return 0;
        }
        return -1;
    }

    // aktuelle Rollenabhaengigkeit loeschen   
    function delete()
    {
        $sql    = "DELETE FROM ". TBL_ROLE_DEPENDENCIES. 
                   "WHERE rld_rol_id_child = '$this->role_id_child_orig' " .
                     "AND rld_rol_id_parent = '$this->role_id_parent_orig'";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $this->clear();
    }

    function getParentRoles($dbConnection,$childId)
    {
        $allParentIds = array();

        $sql = "SELECT rld_rol_id_parent FROM ". TBL_ROLE_DEPENDENCIES. 
               " WHERE rld_rol_id_child = $childId ";
        $result = mysql_query($sql, $dbConnection);
        db_error($result);

        $num_rows = mysql_num_rows($result);
        if ($num_rows)
        {
            while ($row = mysql_fetch_object($result))
            {
                $allParentIds[] = $row->rld_rol_id_parent;                      
            }
        }

        return  $allParentIds;
    }
}
?>