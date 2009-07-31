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
 * Userdaten koennen ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Userdaten sowie der zugehoerige Feldkonfigurationen
 * ausgelesen. Geschrieben werden aber nur die Userdaten
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * clearFieldData() - es werden nur die Daten der Tabelle adm_user_data entfernt
 *                    die Kategorie und adm_user_field bleiben erhalten
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableUserData extends TableAccess
{
    // Konstruktor
    function TableUserData(&$db)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USER_DATA;
        $this->column_praefix = 'usd';
        
        $this->clear();
    }
    
    // Userdaten mit den Profilfeldinformationen aus der Datenbank auslesen
    // ids : Array mit den Schlüsseln usr_id und usf_id
    // sql_where_condition : optional eine individuelle WHERE-Bedinugung fuer das SQL-Statement
    // sql_additioinal_tables : wird nicht verwendet (benötigt wegen Vererbung)
    function readData($ids, $sql_where_condition = '', $sql_additional_tables = '')
    {
        if(is_array($ids) && is_numeric($ids['usr_id']) && is_numeric($ids['usf_id']))
        {
            $tables    = TBL_USER_FIELDS;
            if(strlen($sql_where_condition) > 0)
            {
                $sql_where_condition = $sql_where_condition . ' AND ';
            }
            $sql_where_condition .= ' AND usd_usr_id = '. $ids['usr_id']. '
                                       AND usd_usf_id = '. $ids['usf_id']. '
                                       AND usd_usf_id = usf_id ';
            parent::readData(0, $sql_where_condition, $tables);
            
            $this->setValue('usd_usr_id', $ids['usr_id']);
            $this->setValue('usd_usf_id', $ids['usf_id']);
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