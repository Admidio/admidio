<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_list_columns
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Listenspaltenobjekt zu erstellen. 
 * Eine Listenspalte kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung.
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableListColumns extends TableAccess
{
    // Konstruktor
    function TableListColumns(&$db, $lsc_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_LIST_COLUMNS;
        $this->column_praefix = 'lsc';
        
        if($lsc_id > 0)
        {
            $this->readData($lsc_id);
        }
        else
        {
            $this->clear();
        }
    }
}
?>