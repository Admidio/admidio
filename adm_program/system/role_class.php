<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_roles
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Rollenobjekt zu erstellen.
 * Eine Rolle kann ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Informationen der Rolle sowie der zugehoerigen Kategorie
 * ausgelesen. Geschrieben werden aber nur die Rollendaten
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $role = new Role($g_adm_con);
 *
 * Mit der Funktion getRole($user_id) kann die gewuenschte Rolle ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen weiter zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save($login_user_id)   - Rolle wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Die gewaehlte Rolle wird aus der Datenbank geloescht
 * setInactive()          - setzt die Rolle auf inaktiv
 * setActive()            - setzt die Rolle wieder auf aktiv
 * countVacancies($count_leaders = false) - gibt die freien Plaetze der Rolle zurueck
 *                          dies ist interessant, wenn rol_max_members gesetzt wurde
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class Role extends TableAccess
{
    // Konstruktor
    function Role($connection, $role = "")
    {
        $this->db_connection  = $connection;
        $this->table_name     = TBL_ROLES;
        $this->column_praefix = "rol";
        $this->key_name       = "rol_id";
        
        if(strlen($role) > 0)
        {
            $this->getRole($role);
        }
        else
        {
            $this->clear();
        }
    }

    // Rolle mit der uebergebenen ID oder dem Rollennamen aus der Datenbank auslesen
    function getRole($role)
    {
        global $g_current_organization;

        if(is_numeric($role))
        {
        	$condition = " rol_id = $role ";
        }
        else
        {
            $role = addslashes($role);
            $condition = " rol_name LIKE '$role' ";
        }
        
    	$tables    = TBL_CATEGORIES;
    	$condition = $condition. " AND rol_cat_id = cat_id
                                   AND cat_org_id = ". $g_current_organization->getValue("org_id");
    	$this->readData($role, $condition, $tables);
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
	// und ungueltige Werte auf leer setzt
	// die Funktion wird innerhalb von setValue() aufgerufen
    function checkValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "rol_id":
            case "rol_cat_id":
                if(is_numeric($field_value) == false
                || $field_value == 0)
                {
                    $field_value = "";
                    return false;
                }
                break;

            case "rol_weekday":
            case "rol_max_members":
            case "rol_usr_id_change":
                if(is_numeric($field_value) == false)
                {
                    $field_value = "";
                    return false;
                }
                break;

            case "rol_approve_users":
            case "rol_assign_roles":
            case "rol_announcements":
            case "rol_dates":
            case "rol_download":
            case "rol_edit_user":
            case "rol_guestbook":
            case "rol_guestbook_comments":
            case "rol_mail_logout":
            case "rol_mail_login":
            case "rol_photo":
            case "rol_profile":
            case "rol_weblinks":
            case "rol_locked":
            case "rol_valid":
            case "rol_system":
                if($field_value != 1)
                {
                    $field_value = 0;
                }
                break;
        }    	
        return true;
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
	// die Funktion wird innerhalb von save() aufgerufen
    function initializeFields()
    {
    	global $g_current_user;
    	
        $this->db_fields['rol_last_change']   = date("Y-m-d H:i:s", time());
        $this->db_fields['rol_usr_id_change'] = $g_current_user->getValue("usr_id");
    }

    // interne Funktion, die die Fotoveranstaltung in Datenbank und File-System loeschen
	// die Funktion wird innerhalb von delete() aufgerufen
    function deleteReferences()
    {
        // die Rolle "Webmaster" darf nicht geloescht werden
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "DELETE FROM ". TBL_ROLE_DEPENDENCIES. " 
                        WHERE rld_rol_id_parent = ". $this->db_fields['rol_id']. "
                           OR rld_rol_id_child  = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "DELETE FROM ". TBL_MEMBERS. " 
                        WHERE mem_rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            return true;
        }
        else
        {
        	return false;
        } 	
    }
    
    // aktuelle Rolle wird auf inaktiv gesetzt
    function setInactive()
    {
        // die Rolle "Webmaster" darf nicht auf inaktiv gesetzt werden
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0
                                                  , mem_end   = SYSDATE()
                        WHERE mem_rol_id = ". $this->db_fields['rol_id']. "
                          AND mem_valid  = 1 ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 0
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            return 0;
        }
        return -1;
    }

    // aktuelle Rolle wird auf aktiv gesetzt
    function setActive()
    {
        // die Rolle "Webmaster" ist immer aktiv
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 1
                                                  , mem_end   = NULL
                        WHERE mem_rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 1
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            return 0;
        }
        return -1;
    }
    
    // die Funktion gibt die Anzahl freier Plaetze zurueck
    // ist rol_max_members nicht gesetzt so wird immer 999 zurueckgegeben
    function countVacancies($count_leaders = false)
    {
        if($this->db_fields['rol_max_members'] > 0)
        {
            $sql    = "SELECT mem_usr_id FROM ". TBL_MEMBERS. "
                        WHERE mem_rol_id = ". $this->db_fields['rol_id']. "
                          AND mem_valid  = 1";
            if($count_leaders == false)
            {
                $sql = $sql. " AND mem_leader = 0 ";
            }
            $sql    = prepareSQL($sql, array($req_rol_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
            
            $num_members = mysql_num_rows($result);            
            return $this->db_fields['rol_max_members'] - $num_members;
        }
        return 999;
    }
}
?>