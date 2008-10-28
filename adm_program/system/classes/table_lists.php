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
        $sql = "DELETE FROM ". TBL_LIST_COLUMNS. " WHERE lsc_lst_id = ". $this->db_fields['lst_id'];
        $result = $this->db->query($sql);
        
        return parent::delete();
    } 
}
?>