<?php
/******************************************************************************
 * Class manages access to database table adm_lists
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Listenobjekt zu erstellen. 
 * Eine Liste kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * setDefault()       - Aktuelle Liste wird zur Default-Liste der Organisation
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableLists extends TableAccess
{
    // Konstruktor
    public function __construct(&$db, $lst_id = 0)
    {
        parent::__construct($db, TBL_LISTS, 'lst', $lst_id);
    }

    // Liste samt Abhaengigkeiten loeschen
    public function delete()
    {
		$this->db->startTransaction();
		
        // alle Spalten der Liste loeschen
        $sql = 'DELETE FROM '. TBL_LIST_COLUMNS. ' WHERE lsc_lst_id = '. $this->getValue('lst_id');
        $result = $this->db->query($sql);
        
        $return = parent::delete();

		$this->db->endTransaction();
		return $return;
    } 

    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentUser;
        
        // Standardfelder fuellen
        if($this->new_record)
        {
            $this->setValue('lst_timestamp', DATETIME_NOW);
            $this->setValue('lst_usr_id', $gCurrentUser->getValue('usr_id'));
            $this->setValue('lst_org_id', $gCurrentOrganization->getValue('org_id'));
        }
        else
        {
            $this->setValue('lst_timestamp', DATETIME_NOW);
            $this->setValue('lst_usr_id', $gCurrentUser->getValue('usr_id'));
        }
        
        // falls nicht explizit auf global = 1 gesetzt wurde, immer auf 0 setzen
        if($this->getValue('lst_global') <> 1)
        {
            $this->setValue('lst_global', 0);
        }
        
        parent::save($updateFingerPrint);
    }
        
    // Aktuelle Liste wird zur Default-Liste der Organisation
    public function setDefault()
    {
        global $gCurrentOrganization;
		$this->db->startTransaction();
        
        // erst die bisherige Default-Liste zuruecksetzen
        $sql = 'UPDATE '. TBL_LISTS. ' SET lst_default = 0
                 WHERE lst_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                   AND lst_default = 1 ';
        $this->db->query($sql);

        // jetzt die aktuelle Liste zur Default-Liste machen
        $sql = 'UPDATE '. TBL_LISTS. ' SET lst_default = 1
                 WHERE lst_id = '. $this->getValue('lst_id');
        $this->db->query($sql);
		
		$this->db->endTransaction();
    }
}
?>