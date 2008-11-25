<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_user_fields
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Benutzerdefiniertes Feldobjekt zu erstellen.
 * Eine Benutzerdefiniertes Feldobjekt kann ueber diese Klasse in der Datenbank 
 * verwaltet werden
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class TableUserField extends TableAccess
{
    // Konstruktor
    function TableUserField(&$db, $usf_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USER_FIELDS;
        $this->column_praefix = "usf";
        
        if($usf_id > 0)
        {
            $this->readData($usf_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Benutzerdefiniertes Feld mit der uebergebenen ID aus der Datenbank auslesen
    function readData($usf_id)
    {
        if(is_numeric($usf_id))
        {
            $tables    = TBL_CATEGORIES;
            $condition = "       usf_cat_id = cat_id
                             AND usf_id     = $usf_id ";
            parent::readData($usf_id, $condition, $tables);
        }
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function setValue($field_name, $field_value)
    {
        if($field_name == "usf_cat_id"
        && $this->getValue($field_name) != $field_value)
        {
            // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = "SELECT COUNT(*) as count FROM ". TBL_USER_FIELDS. "
                     WHERE usf_cat_id = $field_value";
            $this->db->query($sql);

            $row = $this->db->fetch_array();

            $this->setValue("usf_sequence", $row['count'] + 1);
        }     
        parent::setValue($field_name, $field_value);
    }

    // Methode wird erst nach dem Speichern der Profilfelder aufgerufen
    function save()
    {
        global $g_current_session;
        $fields_changed = $this->columnsValueChanged;
        
        parent::save();
        
        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, 
            // da Aenderungen in den Profilfeldern vorgenommen wurden 
            $g_current_session->renewUserObject();
        }
    }
    
    // interne Funktion, die die Referenzen bearbeitet, wenn die Kategorie geloescht wird
    // die Funktion wird innerhalb von delete() aufgerufen
    function delete()
    {
        global $g_current_session;
        
        // Luecke in der Reihenfolge schliessen
        $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_sequence = usf_sequence - 1 
                 WHERE usf_cat_id   = ". $this->getValue("usf_cat_id"). "
                   AND usf_sequence > ". $this->getValue("usf_sequence");
        $this->db->query($sql);

        // Abhaenigigkeiten loeschen
        $sql    = "DELETE FROM ". TBL_USER_DATA. "
                    WHERE usd_usf_id = ". $this->getValue("usf_id");
        $this->db->query($sql);

        // einlesen aller Userobjekte der angemeldeten User anstossen, 
        // da Aenderungen in den Profilfeldern vorgenommen wurden 
        $g_current_session->renewUserObject();
            
        return parent::delete();
    }    
}
?>