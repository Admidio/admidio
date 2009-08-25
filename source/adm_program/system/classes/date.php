<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableDates, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getRepeatedTypeText($type)   - gibt den Beschreibungstext für einen bestimmten Serientermintyp zurück
 * getTypesArray()              - gibt das Array mit den Serientermintypen zurück
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_date.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_roles.php');
class Date extends TableDate
{
    // Array mit den Keys für die Datenbank und den Typbeschreibungen
     var $types = array(
                    0 => '- kein -',
                    10 => 'täglich',
                    20 => 'werktags',
                    30 => 'wöchentlich',
                    40 => 'vierzehntägig',
                    50 => 'monatlich'
                );
    // Array mit Keys für Sichtbarkeit der Termine
    
    var $visibility = array(
                    '0' => 'Gäste'
                );
    
    function Date(&$db, $date_id = 0)
    {
        $this->TableDate($db, $date_id);
        
        $sql = 'SELECT rol_id, rol_name FROM '.TBL_ROLES.' WHERE rol_id NOT IN(SELECT rol_id FROM '.TBL_ROLES.', '.TBL_DATES.' WHERE rol_id = dat_rol_id)';
        $result = $db->query($sql);
        while($row = $db->fetch_array($result))
        {
            $this->visibility[$row['rol_id']]=$row['rol_name'];
        }
    }
    
    function getVisibilityMode($mode)
    {
        return $this->visibility[$mode];
    }
    
    function getVisibilityArray()
    {
        return $this->visibility;
    }
    function delete()
    {
        if($this->getValue('dat_rol_id') > 0)
        {
            $sql = 'DELETE FROM '.TBL_MEMBERS.' WHERE mem_rol_id = "'.$this->getValue('dat_rol_id').'"';
            $this->db->query($sql);
            
            $role = new TableRoles($this->db);
            $role->readData($this->getValue('dat_rol_id'));
            $role->delete();
        }
        
        $sql = 'DELETE FROM '.TBL_DATE_ROLE.' WHERE dat_id = "'.$this->getValue('dat_id').'"';
        $result = $this->db->query($sql);
        
        $sql = 'DELETE FROM '.TBL_DATE_MAX_MEMBERS.' WHERE dat_id="'.$this->getValue('dat_id').'"';
        $this->db->query($sql);
        
        parent::delete();
    }
}
?>