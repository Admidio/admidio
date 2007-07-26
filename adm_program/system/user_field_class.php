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
 * $user_field = new UserField($g_db);
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
 * save()                 - Feld wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben oder angelegt
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

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class UserField extends TableAccess
{
    // Konstruktor
    function UserField(&$db, $usf_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USER_FIELDS;
        $this->column_praefix = "usf";
        $this->key_name       = "usf_id";
        
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
        if(is_numeric($usf_id))
        {
            $tables    = TBL_CATEGORIES;
            $condition = "       usf_cat_id = cat_id
                             AND usf_id     = $usf_id ";
            $this->readData($usf_id, $condition, $tables);
        }
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function checkValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "usf_id":
            case "usf_cat_id":
                if(is_numeric($field_value) == false 
                || $field_value == 0)
                {
                    $field_value = null;
                    return false;
                }
                
                if($field_name == "usf_cat_id"
                && $this->db_fields[$field_name] != $field_value)
                {
                    // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
                    $sql = "SELECT COUNT(*) as count FROM ". TBL_USER_FIELDS. "
                             WHERE usf_cat_id = $field_value";
                    $this->db->query($sql);

                    $row = $this->db->fetch_array();

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
                    return false;
                }
                break;
        }       
        return true;
    }
    
    // interne Funktion, die die Referenzen bearbeitet, wenn die Kategorie geloescht wird
    // die Funktion wird innerhalb von delete() aufgerufen
    function deleteReferences()
    {
        $sql    = "DELETE FROM ". TBL_USER_DATA. "
                    WHERE usd_usf_id = ". $this->db_fields['usf_id'];
        $this->db->query($sql);

        return true;
    }    
}
?>