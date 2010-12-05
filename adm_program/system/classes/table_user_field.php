<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_user_fields
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableUserField extends TableAccess
{
    // Konstruktor
    public function __construct(&$db, $usf_id = 0)
    {
        parent::__construct($db, TBL_USER_FIELDS, 'usf', $usf_id);
    }
    
    // interne Funktion, die die Referenzen bearbeitet, wenn die Kategorie geloescht wird
    // die Funktion wird innerhalb von delete() aufgerufen
    public function delete()
    {
        global $g_current_session;
        
        // Luecke in der Reihenfolge schliessen
        $sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_sequence = usf_sequence - 1 
                 WHERE usf_cat_id   = '. $this->getValue('usf_cat_id'). '
                   AND usf_sequence > '. $this->getValue('usf_sequence');
        $this->db->query($sql);

        // Feldreihenfolge bei gespeicherten Listen anpassen
        $sql = 'SELECT lsc_lst_id, lsc_number FROM '. TBL_LIST_COLUMNS. ' 
                 WHERE lsc_usf_id = '.$this->getValue('usf_id');
        $result_lst = $this->db->query($sql);
        
        while($row_lst = $this->db->fetch_array($result_lst))
        {
            $sql = 'UPDATE '. TBL_LIST_COLUMNS. ' SET lsc_number = lsc_number - 1 
                     WHERE lsc_lst_id = '. $row_lst['lsc_lst_id']. '
                       AND lsc_number > '. $row_lst['lsc_number'];
            $this->db->query($sql);
        }

        // Abhaenigigkeiten loeschen
        $sql    = 'DELETE FROM '. TBL_USER_DATA. '
                    WHERE usd_usf_id = '. $this->getValue('usf_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_LIST_COLUMNS. ' 
                    WHERE lsc_usf_id = '. $this->getValue('usf_id');
        $this->db->query($sql);

        // einlesen aller Userobjekte der angemeldeten User anstossen, 
        // da Aenderungen in den Profilfeldern vorgenommen wurden 
        $g_current_session->renewUserObject();

        return parent::delete();
    }

    // diese rekursive Methode ermittelt fuer den uebergebenen Namen einen eindeutigen Namen
    // dieser bildet sich aus dem Namen in Grossbuchstaben und der naechsten freien Nummer (index)
    // Beispiel: 'Mitgliedsnummer' => 'MITGLIEDSNUMMER_2'
    private function getNewNameIntern($name, $index)
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));
        if($index > 1)
        {
            $newNameIntern = $newNameIntern.'_'.$index;
        }
        $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name_intern = "'.$newNameIntern.'"';
        $this->db->query($sql);
        
        if($this->db->num_rows() > 0)
        {
            $index++;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }
        return $newNameIntern;
    }
    
    // das Feld wird um eine Position in der Reihenfolge verschoben
    public function moveSequence($mode)
    {
        global $g_current_organization;

        // die Kategorie wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if(admStrToUpper($mode) == 'UP')
        {
            $sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_sequence = '.$this->getValue('usf_sequence').'
                     WHERE usf_cat_id   = '.$this->getValue('usf_cat_id').'
                       AND usf_sequence = '.$this->getValue('usf_sequence').' - 1 ';
            $this->db->query($sql);
            $this->setValue('usf_sequence', $this->getValue('usf_sequence')-1);
            $this->save();
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif(admStrToUpper($mode) == 'DOWN')
        {
            $sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_sequence = '.$this->getValue('usf_sequence').'
                     WHERE usf_cat_id   = '.$this->getValue('usf_cat_id').'
                       AND usf_sequence = '.$this->getValue('usf_sequence').' + 1 ';
            $this->db->query($sql);
            $this->setValue('usf_sequence', $this->getValue('usf_sequence')+1);
            $this->save();
        }
    }    

    // Benutzerdefiniertes Feld mit der uebergebenen ID aus der Datenbank auslesen
    public function readData($usf_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        if(is_numeric($usf_id))
        {
            $sql_additional_tables .= TBL_CATEGORIES;
            $sql_where_condition   .= '    usf_cat_id = cat_id
                                       AND usf_id     = '.$usf_id;
            return parent::readData($usf_id, $sql_where_condition, $sql_additional_tables);
        }
        return false;
    }

    // Methode wird erst nach dem Speichern der Profilfelder aufgerufen
    public function save()
    {
        global $g_current_session;
        $fields_changed = $this->columnsValueChanged;
        
        // wurde der Name veraendert, dann nach einem neuen eindeutigen internen Namen suchen
        if($this->columnsInfos['usf_name']['changed'])
        {
            $this->setValue('usf_name_intern', $this->getNewNameIntern($this->getValue('usf_name'), 1));
        }
        
        parent::save();
        
        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, 
            // da Aenderungen in den Profilfeldern vorgenommen wurden 
            $g_current_session->renewUserObject();
        }
    }


    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    public function setValue($field_name, $field_value)
    {
        if($field_name == 'usf_cat_id'
        && $this->getValue($field_name) != $field_value)
        {
            // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) as count FROM '. TBL_USER_FIELDS. '
                     WHERE usf_cat_id = '.$field_value;
            $this->db->query($sql);

            $row = $this->db->fetch_array();

            $this->setValue('usf_sequence', $row['count'] + 1);
        }     
        return parent::setValue($field_name, $field_value);
    }
}
?>