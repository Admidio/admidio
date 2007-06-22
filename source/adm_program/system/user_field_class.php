<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_user_fields
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Benutzerdefiniertes Feldobjekt zu erstellen.
 * Eine Benutzerdefiniertes Feldobjekt kann ueber diese Klasse in der Datenbank 
 * verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $user_field = new UserField($g_adm_con);
 *
 * Mit der Funktion getUserField($user_id) kann das gewuenschte Feld ausgelesen
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

class UserField
{
    var $db_connection;
    var $db_fields = array();

    // Konstruktor
    function UserField($connection, $usf_id = 0)
    {
        $this->db_connection = $connection;
        if($usf_id > 0)
        {
            $this->getUserField($usf_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Benutzerdefiniertes Feld mit der uebergebenen ID aus der Datenbank auslesen
    function getUserField($usf_id)
    {
        $this->clear();
        
        if($usf_id > 0 && is_numeric($usf_id))
        {
            $sql = "SELECT * 
                      FROM ". TBL_USER_FIELDS. ", ". TBL_CATEGORIES. " 
                     WHERE usf_cat_id = cat_id
                       AND usf_id     = $usf_id";
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
            $sql = "SHOW COLUMNS FROM ". TBL_USER_FIELDS;
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
        $field_name  = strStripTags($field_name, true);
        $field_value = strStripTags($field_value);
        $field_name  = stripSlashes($field_name);
        $field_value = stripSlashes($field_value);
        
        if(strlen($field_value) == 0)
        {
            $field_value = null;
        }
        
        // Plausibilitaetspruefungen
        switch($field_name)
        {
            case "usf_id":
            case "usf_cat_id":
                if(is_numeric($field_value) == false 
                || $field_value == 0)
                {
                    $field_value = null;
                }
                
                if($field_name == "usf_cat_id"
                && $this->db_fields[$field_name] != $field_value)
                {
                    // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
                    $sql = "SELECT COUNT(*) as count FROM ". TBL_USER_FIELDS. "
                             WHERE usf_cat_id = $field_value";
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);

                    $row = mysql_fetch_array($result);

                    $this->db_fields['usf_sequence'] = $row['count'] + 1;
                }
                break;
            
            case "usf_system":
            case "usf_disabled":
            case "usf_hidden":
            case "usf_mandatory":
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
    
    // aktuelle Felddaten in der Datenbank updaten
    function update()
    {
        if(count($this->db_fields)    > 0
        && $this->db_fields['usf_id'] > 0 
        && is_numeric($this->db_fields['usf_id']))
        {
            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->db_fields as $key => $value)
            {
                // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                if($key != "usf_id" && strpos($key, "usf_") === 0) 
                {
                    if(strlen($value) == 0)
                    {
                        $sql_field_list = $sql_field_list. " $item_connection $key = NULL ";
                    }
                    elseif(is_numeric($value))
                    {
                        $sql_field_list = $sql_field_list. " $item_connection $key = $value ";
                    }
                    else
                    {
                        $value = addSlashes($value);
                        $sql_field_list = $sql_field_list. " $item_connection $key = '$value' ";
                    }

                    if(strlen($item_connection) == 0)
                    {
                        $item_connection = ",";
                    }
                }
            }

            $sql = "UPDATE ". TBL_USER_FIELDS. " SET $sql_field_list WHERE usf_id = ". $this->db_fields['usf_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            return 0;
        }
        return -1;
    }

    // aktuelle Felddaten neu in der Datenbank schreiben
    function insert()
    {
        global $g_current_organization;
        
        if(isset($this->db_fields['usf_id']) == false
        || $this->db_fields['usf_id']        == 0 )
        {
            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";
            $sql_value_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Insert hinzufuegen 
            foreach($this->db_fields as $key => $value)
            {
                // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                if($key != "usf_id" && strlen($value) > 0 && strpos($key, "usf_") === 0) 
                {
                    $sql_field_list = $sql_field_list. " $item_connection $key ";
                    if(is_numeric($value))
                    {
                        $sql_value_list = $sql_value_list. " $item_connection $value ";
                    }
                    else
                    {
                        $value = addSlashes($value);
                        $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                    }

                    if(strlen($item_connection) == 0)
                    {
                        $item_connection = ",";
                    }
                }
            }
                        
            $sql = "INSERT INTO ". TBL_USER_FIELDS. " ($sql_field_list) VALUES ($sql_value_list) ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            $this->db_fields['usf_id'] = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }

    // aktuelles Feld loeschen
    function delete()
    {
        $sql    = "DELETE FROM ". TBL_USER_DATA. "
                    WHERE usd_usf_id = ". $this->db_fields['usf_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
                    WHERE usf_id = ". $this->db_fields['usf_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $this->clear();
        return 0;
    }
}
?>