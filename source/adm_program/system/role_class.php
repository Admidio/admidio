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
 * update($login_user_id) - Rolle wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben
 * insert($login_user_id) - Eine neue Rolle wird in die Datenbank geschrieben
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

class Role
{
    var $db_connection;
    var $db_fields = array();

    // Konstruktor
    function Role($connection, $role = 0)
    {
        $this->db_connection = $connection;
        if(strlen($role) > 0)
        {
            $this->getRole($role);
        }
        else
        {
            $this->clear();
        }
    }

    // Rolle mit der uebergebenen ID aus der Datenbank auslesen
    function getRole($role)
    {
        global $g_current_organization;
        
        $this->clear();
        
        if(is_numeric($role))
        {
            $sql = "SELECT * 
                      FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. " 
                     WHERE rol_cat_id = cat_id
                       AND rol_id     = $role
                       AND cat_org_id = $g_current_organization->id ";
        }
        else
        {
            $role = addslashes($role);
            $sql = "SELECT * 
                      FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. " 
                     WHERE rol_cat_id = cat_id
                       AND rol_name   LIKE '$role'
                       AND cat_org_id = $g_current_organization->id ";
        }
        
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        if($row = mysql_fetch_array($result, MYSQL_ASSOC))
        {
            // Daten in das Klassenarray schieben
            foreach($row as $key => $value)
            {
                $this->db_fields[$key] = $value;
            }
        }
    }

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                $this->db_fields[$key] = null;
            }
        }
        else
        {
            // alle Spalten der Tabelle adm_roles ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_ROLES;
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            while ($row = mysql_fetch_array($result))
            {
                $this->db_fields[$row['Field']] = null;
            }
        }
    }
    
    // Funktion uebernimmt alle Werte eines Arrays in das Field-Array
    function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->db_fields[$field] = $value;
        }
    }
    
    // Funktion setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        
        if(strlen($field_value) == 0)
        {
            $field_value = null;
        }
        
        // Plausibilitaetspruefungen
        switch($field_name)
        {
            case "rol_id":
            case "rol_cat_id":
                if(is_numeric($field_value) == false
                || $field_value == 0)
                {
                    $field_value = null;
                }
                break;

            case "rol_weekday":
            case "rol_max_members":
            case "rol_usr_id_change":
                if(is_numeric($field_value) == false)
                {
                    $field_value = null;
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
                
        $this->db_fields[$field_name] = $field_value;
    }

    // Funktion gibt den Wert eines Feldes zurueck
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getValue($field_name)
    {
        return $this->db_fields[$field_name];
    }
    
    // aktuelle Rollendaten in der Datenbank updaten
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    function update($login_user_id)
    {
        if(count($this->db_fields)    > 0
        && $this->db_fields['rol_id'] > 0 
        && $login_user_id             > 0 
        && is_numeric($this->db_fields['rol_id'])
        && is_numeric($login_user_id))
        {
            $act_date = date("Y-m-d H:i:s", time());

            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->db_fields as $key => $value)
            {
                // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                if($key != "rol_id" && strpos($key, "rol_") === 0) 
                {
                    // jetzt noch Spezialfaelle abhandeln
                    switch($key)
                    {
                        case "rol_last_change":
                            $sql_field_list = $sql_field_list. " $item_connection $key = '$act_date' ";
                            break;

                        case "rol_usr_id_change":
                            $sql_field_list = $sql_field_list. " $item_connection $key = $login_user_id ";
                            break;

                        default:
                            $sql_field_list = $sql_field_list. " $item_connection $key = \{$key} ";
                            break;
                    }

                    if(strlen($item_connection) == 0)
                    {
                        $item_connection = ",";
                    }
                }
            }

            $sql = "UPDATE ". TBL_ROLES. " SET $sql_field_list WHERE rol_id = {rol_id} ";
            $sql = prepareSQL($sql, $this->db_fields);
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            return 0;
        }
        return -1;
    }

    // aktuelle Rollendaten neu in der Datenbank schreiben
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    function insert($login_user_id)
    {
        if($login_user_id > 0 
        && is_numeric($login_user_id)
        && (  isset($this->db_fields['rol_id']) == false
           || $this->db_fields['rol_id']        == 0 ))
        {
            $act_date = date("Y-m-d H:i:s", time());

            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";
            $sql_value_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Insert hinzufuegen 
            foreach($this->db_fields as $key => $value)
            {
                // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                if($key != "rol_id" && strlen($value) > 0 && strpos($key, "rol_") === 0) 
                {
                    $sql_field_list = $sql_field_list. " $item_connection $key ";
                    if(is_numeric($value))
                    {
                        $sql_value_list = $sql_value_list. " $item_connection $value ";
                    }
                    else
                    {
                        $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                    }

                    if(strlen($item_connection) == 0)
                    {
                        $item_connection = ",";
                    }
                }
            }

            // Felder hinzufuegen, die zwingend erforderlich sind
            if(isset($this->db_fields['rol_last_change']) == false)
            {
                $sql_field_list = $sql_field_list. ", rol_last_change ";
                $sql_value_list = $sql_value_list. ", '$act_date' ";
            }
            if(isset($this->db_fields['rol_usr_id_change']) == false)
            {
                $sql_field_list = $sql_field_list. ", rol_usr_id_change ";
                $sql_value_list = $sql_value_list. ", $login_user_id ";
            }
   
            $sql = "INSERT INTO ". TBL_ROLES. " ($sql_field_list) VALUES ($sql_value_list) ";            
            $sql = prepareSQL($sql, $this->db_fields);
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            $this->db_fields['rol_id'] = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }

    // aktuelle Rolle loeschen
    function delete()
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

            $sql    = "DELETE FROM ". TBL_ROLES. " 
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $this->clear();
            return 0;
        }
        return -1;
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