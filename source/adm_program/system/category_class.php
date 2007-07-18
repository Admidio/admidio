<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_categories
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $category = new Category($g_adm_con);
 *
 * Mit der Funktion getCategory($cat_id) kann das gewuenschte Feld ausgelesen
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

class Category
{
    var $db_connection;
    var $db_fields = array();

    // Konstruktor
    function Category($connection, $cat_id = 0)
    {
        $this->db_connection = $connection;
        if($cat_id > 0)
        {
            $this->getCategory($cat_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Benutzerdefiniertes Feld mit der uebergebenen ID aus der Datenbank auslesen
    function getCategory($cat_id)
    {
        $this->clear();
        
        if($cat_id > 0 && is_numeric($cat_id))
        {
            $sql = "SELECT * 
                      FROM ". TBL_CATEGORIES. " 
                     WHERE cat_id     = $cat_id";
                     error_log($sql);
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
            $sql = "SHOW COLUMNS FROM ". TBL_CATEGORIES;
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
            case "cat_id":
            case "cat_org_id":
                if(is_numeric($field_value) == false 
                || $field_value == 0)
                {
                    $field_value = null;
                }
                break;
            
            case "cat_system":
            case "cat_hidden":
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
        && $this->db_fields['cat_id'] > 0 
        && is_numeric($this->db_fields['cat_id']))
        {
            $act_date = date("Y-m-d H:i:s", time());

            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->db_fields as $key => $value)
            {
                // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                if($key != "cat_id" && strpos($key, "cat_") === 0) 
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

            $sql = "UPDATE ". TBL_CATEGORIES. " SET $sql_field_list WHERE cat_id = ". $this->db_fields['cat_id'];
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
        
        if(isset($this->db_fields['cat_id']) == false
        || $this->db_fields['cat_id']        == 0 )
        {
            $act_date = date("Y-m-d H:i:s", time());

            // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = "SELECT COUNT(*) as count FROM ". TBL_CATEGORIES. "
                     WHERE (  cat_org_id  = ". $g_current_organization->getValue("org_id"). "
                           OR cat_org_id IS NULL )
                       AND cat_type = '". $this->db_fields['cat_type']. "'";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $row = mysql_fetch_array($result);

            $this->db_fields['cat_sequence'] = $row['count'] + 1;
            
            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";
            $sql_value_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Insert hinzufuegen 
            foreach($this->db_fields as $key => $value)
            {
                // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                if($key != "cat_id" && strlen($value) > 0 && strpos($key, "cat_") === 0) 
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
                        
            $sql = "INSERT INTO ". TBL_CATEGORIES. " ($sql_field_list) VALUES ($sql_value_list) ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            $this->db_fields['cat_id'] = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }

    // aktuelles Feld loeschen
    function delete()
    {
        // erst einmal zugehoerige Daten loeschen
        if($this->db_fields['cat_type'] == 'ROL')
        {
            $sql    = "DELETE FROM ". TBL_ROLES. "
                        WHERE rol_cat_id = ". $this->db_fields['cat_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
        }
        elseif($this->db_fields['cat_type'] == 'LNK')
        {
            $sql    = "DELETE FROM ". TBL_LINKS. "
                        WHERE lnk_cat_id = ". $this->db_fields['cat_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
        }
        elseif($this->db_fields['cat_type'] == 'USF')
        {
            $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
                        WHERE usf_cat_id = ". $this->db_fields['cat_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
        }

        // Feld loeschen
        $sql    = "DELETE FROM ". TBL_CATEGORIES. "
                    WHERE cat_id = ". $this->db_fields['cat_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $this->clear();
        return 0;
    }
}
?>