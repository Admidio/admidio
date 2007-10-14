<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_categories
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $category = new Category($g_db);
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
 * save()                 - Kategorie wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben oder angelegt
 * delete()               - Die gewaehlte Rolle wird aus der Datenbank geloescht
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class Category extends TableAccess
{
    // Konstruktor
    function Category(&$db, $cat_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_CATEGORIES;
        $this->column_praefix = "cat";
        $this->key_name       = "cat_id";
        $this->auto_increment = true;
        
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
        $this->readData($cat_id);
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "cat_id":
            case "cat_org_id":
                if(is_numeric($field_value) == false 
                || $field_value == 0)
                {
                    $field_value = "";
                    return false;
                }
                break;
            
            case "cat_system":
            case "cat_hidden":
                if($field_value != 1)
                {
                    $field_value = 0;
                    return false;
                }
                break;  
        }       
        return true;
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        if($this->new_record)
        {
            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            global $g_current_organization;
            $sql = "SELECT COUNT(*) as count FROM ". TBL_CATEGORIES. "
                     WHERE (  cat_org_id  = ". $g_current_organization->getValue("org_id"). "
                           OR cat_org_id IS NULL )
                       AND cat_type = '". $this->db_fields['cat_type']. "'";
            $this->db->query($sql);

            $row = $this->db->fetch_array();

            $this->setValue("cat_sequence", $row['count'] + 1);
        }
    }
    
    // interne Funktion, die die Referenzen bearbeitet, wenn die Kategorie geloescht wird
    // die Funktion wird innerhalb von delete() aufgerufen
    function _delete()
    {
        if($this->db_fields['cat_type'] == 'ROL')
        {
            $sql    = "DELETE FROM ". TBL_ROLES. "
                        WHERE rol_cat_id = ". $this->db_fields['cat_id'];
            $this->db->query($sql);
        }
        elseif($this->db_fields['cat_type'] == 'LNK')
        {
            $sql    = "DELETE FROM ". TBL_LINKS. "
                        WHERE lnk_cat_id = ". $this->db_fields['cat_id'];
            $this->db->query($sql);
        }
        elseif($this->db_fields['cat_type'] == 'USF')
        {
            $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
                        WHERE usf_cat_id = ". $this->db_fields['cat_id'];
            $this->db->query($sql);
        }
        return true;    
    }
}
?>