<?php
/******************************************************************************
 * Class manages access to database table adm_categories
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getNewNameIntern($name, $index) - diese rekursive Methode ermittelt fuer den 
 *                       uebergebenen Namen einen eindeutigen Namen dieser bildet sich 
 *                       aus dem Namen in Grossbuchstaben und der naechsten freien Nummer
 * getNumberElements() - number of child recordsets
 * moveSequence($mode) - Kategorie wird um eine Position in der Reihenfolge verschoben
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableCategory extends TableAccess
{
	protected $elementTable;
	protected $elementColumn;

    // Konstruktor
    public function __construct(&$db, $cat_id = 0)
    {
        parent::__construct($db, TBL_CATEGORIES, 'cat', $cat_id);
    }

    // Methode bearbeitet die Referenzen, wenn die Kategorie geloescht wird
    // Rueckgabe ist true, wenn das Loeschen erfolgreich war und false, falls es nicht durchgefuehrt werden konnte
    public function delete()
    {
        global $gCurrentSession;
        
        // pruefen, ob noch mind. eine Kategorie fuer diesen Typ existiert, ansonsten das Loeschen nicht erlauben
        $sql = 'SELECT count(1) AS anzahl FROM '. TBL_CATEGORIES. '
                 WHERE (  cat_org_id = '. $gCurrentSession->getValue('ses_org_id'). '
                       OR cat_org_id IS NULL )
                   AND cat_type     = \''. $this->getValue('cat_type'). '\'';
        $result = $this->db->query($sql);
        
        $row = $this->db->fetch_array($result);

        if($row['anzahl'] > 1)
        {
			$this->db->startTransaction();

            // Luecke in der Reihenfolge schliessen
            $sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_sequence = cat_sequence - 1
                     WHERE (  cat_org_id = '. $gCurrentSession->getValue('ses_org_id'). '
                           OR cat_org_id IS NULL )
                       AND cat_sequence > '. $this->getValue('cat_sequence'). '
                       AND cat_type     = \''. $this->getValue('cat_type'). '\'';
            $this->db->query($sql);
    
            // Abhaenigigkeiten loeschen
            if($this->getValue('cat_type') == 'DAT')
            {
				require_once(SERVER_PATH. '/adm_program/system/classes/table_date.php');
				$object = new TableDate($this->db);
            }
            elseif($this->getValue('cat_type') == 'LNK')
            {
				require_once(SERVER_PATH. '/adm_program/system/classes/table_weblink.php');
				$object = new TableWeblink($this->db);
            }
            elseif($this->getValue('cat_type') == 'ROL')
            {
				require_once(SERVER_PATH. '/adm_program/system/classes/table_roles.php');
				$object = new TableRoles($this->db);
            }
            elseif($this->getValue('cat_type') == 'USF')
            {
				require_once(SERVER_PATH. '/adm_program/system/classes/table_user_field.php');
				$object = new TableUserField($this->db);
            }
			
			// alle zugehoerigen abhaengigen Objekte suchen und mit weiteren Abhaengigkeiten loeschen
			$sql    = 'SELECT * FROM '.$this->elementTable.'
						WHERE '.$this->elementColumn.' = '. $this->getValue('cat_id');
			$resultRecordsets = $this->db->query($sql);
			
			while($row = $this->db->fetch_array($resultRecordsets))
			{
				$object->clear();
				$object->setArray($row);
				$object->delete();
			}

            $return = parent::delete();

			$this->db->endTransaction();
			return $return;
        }
        else
        {
            // die letzte Kategorie darf nicht geloescht werden
            return false;
        }
    }

    // diese rekursive Methode ermittelt fuer den uebergebenen Namen einen eindeutigen Namen
    // dieser bildet sich aus dem Namen in Grossbuchstaben und der naechsten freien Nummer (index)
    // Beispiel: 'Gruppen' => 'GRUPPEN_2'
    private function getNewNameIntern($name, $index)
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));
        if($index > 1)
        {
            $newNameIntern = $newNameIntern.'_'.$index;
        }
        $sql = 'SELECT cat_id FROM '.TBL_CATEGORIES.' WHERE cat_name_intern = \''.$newNameIntern.'\'';
        $this->db->query($sql);
        
        if($this->db->num_rows() > 0)
        {
            $index++;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }
        return $newNameIntern;
    }
	
	// number of child recordsets
	public function getNumberElements()
	{
		$sql    = 'SELECT COUNT(1) FROM '.$this->elementTable.'
					WHERE '.$this->elementColumn.' = '. $this->getValue('cat_id');
		$this->db->query($sql);
		$row = $this->db->fetch_array();
		return $row[0];
	}
	
	// returns the value of database column $field_name
	// for column usf_value_list the following format is accepted
	// 'plain' -> returns database value of usf_value_list
    public function getValue($field_name, $format = '')
    {
		global $gL10n;

		if($field_name == 'cat_name_intern')
		{
			// internal name should be read with no conversion
			$value = parent::getValue($field_name, 'plain');
		}
        else
		{		
			$value = parent::getValue($field_name, $format);
		}

		if($field_name == 'cat_name' && $format != 'plain')
		{
			// if text is a translation-id then translate it
			if(strpos($value, '_') == 3)
			{
				$value = $gL10n->get(admStrToUpper($value));
			}
		}

        return $value;
    }

    // die Kategorie wird um eine Position in der Reihenfolge verschoben
    public function moveSequence($mode)
    {
        global $gCurrentOrganization;

        // Anzahl orgaunabhaengige ermitteln, da diese nicht mit den abhaengigen vermischt werden duerfen
        $sql = 'SELECT COUNT(1) as count FROM '. TBL_CATEGORIES. '
                 WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                   AND cat_org_id IS NULL ';
        $this->db->query($sql);
        $row = $this->db->fetch_array();

        // die Kategorie wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if(admStrToUpper($mode) == 'UP')
        {
            if($this->getValue('cat_org_id') == 0
            || $this->getValue('cat_sequence') > $row['count']+1)
            {
                $sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_sequence = '.$this->getValue('cat_sequence').'
                         WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                                          OR cat_org_id IS NULL )
                           AND cat_sequence = '.$this->getValue('cat_sequence').' - 1 ';
                $this->db->query($sql);
                $this->setValue('cat_sequence', $this->getValue('cat_sequence')-1);
                $this->save();
            }
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif(admStrToUpper($mode) == 'DOWN')
        {
            if($this->getValue('cat_org_id') > 0
            || $this->getValue('cat_sequence') < $row['count'])
            {
                $sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_sequence = '.$this->getValue('cat_sequence').'
                         WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                                          OR cat_org_id IS NULL )
                           AND cat_sequence = '.$this->getValue('cat_sequence').' + 1 ';
                $this->db->query($sql);
                $this->setValue('cat_sequence', $this->getValue('cat_sequence')+1);
                $this->save();
            }
        }
    }

    public function readData($cat_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        $returnValue = parent::readData($cat_id, $sql_where_condition, $sql_additional_tables);

		if($this->getValue('cat_type') == 'ROL')
		{
			$this->elementTable = TBL_ROLES;
			$this->elementColumn = 'rol_cat_id';
		}
		elseif($this->getValue('cat_type') == 'LNK')
		{
			$this->elementTable = TBL_LINKS;
			$this->elementColumn = 'lnk_cat_id';
		}
		elseif($this->getValue('cat_type') == 'USF')
		{
			$this->elementTable = TBL_USER_FIELDS;
			$this->elementColumn = 'usf_cat_id';
		}
		elseif($this->getValue('cat_type') == 'DAT')
		{
			$this->elementTable = TBL_DATES;
			$this->elementColumn = 'dat_cat_id';
		}
		
        return $returnValue;
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentSession;
        $fields_changed = $this->columnsValueChanged;
		$this->db->startTransaction();

        if($this->new_record)
        {
            if($this->getValue('cat_org_id') > 0)
            {
                $org_condition = ' AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                                       OR cat_org_id IS NULL ) ';
            }
            else
            {
               $org_condition = ' AND cat_org_id IS NULL ';
            }
            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) as count FROM '. TBL_CATEGORIES. '
                     WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           '.$org_condition;
            $this->db->query($sql);

            $row = $this->db->fetch_array();

            $this->setValue('cat_sequence', $row['count'] + 1);

            if($this->getValue('cat_org_id') == 0)
            {
                // eine Orga-uebergreifende Kategorie ist immer am Anfang, also Kategorien anderer Orgas nach hinten schieben
                $sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_sequence = cat_sequence + 1
                         WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           AND cat_org_id IS NOT NULL ';
                $this->db->query($sql);
            }
        }
        
		// if new category than generate new name intern, otherwise no change will be made
		if($this->new_record == true)
        {
            $this->setValue('cat_name_intern', $this->getNewNameIntern($this->getValue('cat_name'), 1));
        }

        parent::save($updateFingerPrint);

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if($fields_changed && $this->getValue('cat_type') == 'USF' && is_object($gCurrentSession))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen,
            // da Aenderungen in den Profilfeldern vorgenommen wurden
            $gCurrentSession->renewUserObject();
        }
		
		$this->db->endTransaction();
    }

    // validates the value and adapts it if necessary
    public function setValue($field_name, $field_value, $check_value = true)
    {
		global $gCurrentOrganization;

        // Systemkategorien duerfen nicht umbenannt werden
        if($field_name == 'cat_name' && $this->getValue('cat_system') == 1)
        {
            return false;
        }
		elseif($field_name == 'cat_default' && $field_value == '1')
		{
			// es darf immer nur eine Default-Kategorie je Bereich geben
			$sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_default = 0
					 WHERE cat_type = \''. $this->getValue('cat_type'). '\'
					   AND (  cat_org_id IS NOT NULL 
					       OR cat_org_id = '.$gCurrentOrganization->getValue('org_id').')';
			$this->db->query($sql);
		}

        return parent::setValue($field_name, $field_value);
    }
}
?>