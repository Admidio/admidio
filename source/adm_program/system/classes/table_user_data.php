<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_user_data
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Userdatenobjekt zu erstellen.
 * Eine Userdaten koennen ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Userdaten sowie der zugehoerige Feldkonfigurationen
 * ausgelesen. Geschrieben werden aber nur die Userdaten
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class TableUserData extends TableAccess
{
    // Konstruktor
    function TableUserData(&$db)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USER_DATA;
        $this->column_praefix = "usd";
        
        $this->clear();
    }

    // Userdaten mit den Profilfeldinformationen aus der Datenbank auslesen
    function readData($usr_id, $usf_id)
    {
        if(is_numeric($usr_id) && is_numeric($usf_id))
        {
            $tables    = TBL_USER_FIELDS;
            $condition = $condition. " AND usd_usr_id = ". $usr_id. "
                                       AND usd_usf_id = ". $usf_id. "
                                       AND usd_usf_id = usf_id ";
            parent::readData(0, $condition, $tables);
            
            $this->setValue("usd_usr_id", $usr_id);
            $this->setValue("usd_usf_id", $usf_id);
        }
    }
    
    // es werden nur die Daten der Tabelle adm_user_data entfernt
    // die Kategorie und adm_user_field bleiben erhalten
    function clearFieldData()
    {
    	foreach($this->dbColumns as $name => $value)
    	{
    		if(strpos($name, 'usd') !== false)
    		{
    			$this->dbColumns[$name] = '';
    			$this->columnsInfos[$name]['changed'] = false;
    			$this->new_record = false;
    		}
    	}
    }
}
?>