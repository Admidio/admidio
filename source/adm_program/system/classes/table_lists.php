<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_lists
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Listenobjekt zu erstellen. 
 * Eine Liste kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * setDefault()       - Aktuelle Liste wird zur Default-Liste der Organisation
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class TableLists extends TableAccess
{
    // Konstruktor
    function TableLists(&$db, $lst_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_LISTS;
        $this->column_praefix = "lst";
        
        if($lst_id > 0)
        {
            $this->readData($lst_id);
        }
        else
        {
            $this->clear();
        }
    }
    
    // Aktuelle Liste wird zur Default-Liste der Organisation
    function setDefault()
    {
        global $g_current_organization;
        
        // erst die bisherige Default-Liste zuruecksetzen
        $sql = "UPDATE ". TBL_LISTS. " SET lst_default = 0
                 WHERE lst_org_id  = ". $g_current_organization->getValue("org_id"). "
                   AND lst_default = 1 ";
        $this->db->query($sql);

        // jetzt die aktuelle Liste zur Default-Liste machen
        $sql = "UPDATE ". TBL_LISTS. " SET lst_default = 1
                 WHERE lst_id = ". $this->getValue("lst_id");
        $this->db->query($sql);
    }

    function save()
    {
        global $g_current_organization, $g_current_user;
        
        // Standardfelder fuellen
        if($this->new_record)
        {
            $this->setValue("lst_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("lst_usr_id", $g_current_user->getValue("usr_id"));
            $this->setValue("lst_org_id", $g_current_organization->getValue("org_id"));
        }
        else
        {
            $this->setValue("lst_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("lst_usr_id", $g_current_user->getValue("usr_id"));
        }
        
        // falls nicht explizit auf global = 1 gesetzt wurde, immer auf 0 setzen
        if($this->getValue("lst_global") <> 1)
        {
            $this->setValue("lst_global", 0);
        }
        
        parent::save();
    }

    // Liste samt Abhaengigkeiten loeschen
    function delete()
    {
        // alle Spalten der Liste loeschen
        $sql = "DELETE FROM ". TBL_LIST_COLUMNS. " WHERE lsc_lst_id = ". $this->getValue("lst_id");
        $result = $this->db->query($sql);
        
        return parent::delete();
    } 
}
?>