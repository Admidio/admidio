<?php
/******************************************************************************
 * Klasse fuer den Zugriff auf die Datenbanktabelle adm_user_data
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
    protected $noValueCheck;    // = true, dann werden bei setValue die Werte nicht auf Gueltigkeit geprueft
    
    // Konstruktor
    public function __construct(&$db)
    {
        $this->noValueCheck = false;
        parent::__construct($db, TBL_USER_DATA, 'usd');
    }
    
    // Userdaten mit den Profilfeldinformationen aus der Datenbank auslesen
    // ids : Array mit den Schlüsseln usr_id und usf_id
    // sql_where_condition : optional eine individuelle WHERE-Bedinugung fuer das SQL-Statement
    // sql_additioinal_tables : wird nicht verwendet (benötigt wegen Vererbung)
    public function readData($ids, $sql_where_condition = '', $sql_additional_tables = '')
    {
        $returnCode = false;

        if(is_array($ids) && is_numeric($ids['usr_id']) && is_numeric($ids['usf_id']))
        {
            $tables = TBL_USER_FIELDS;
            if(strlen($sql_where_condition) > 0)
            {
                $sql_where_condition = $sql_where_condition . ' AND ';
            }
            $sql_where_condition .= ' AND usd_usr_id = '. $ids['usr_id']. '
                                       AND usd_usf_id = '. $ids['usf_id']. '
                                       AND usd_usf_id = usf_id ';
            $returnCode = parent::readData(0, $sql_where_condition, $tables);
            
            $this->setValue('usd_usr_id', $ids['usr_id']);
            $this->setValue('usd_usf_id', $ids['usf_id']);
        }
        $this->noValueCheck = false;
        return $returnCode;
    }
    
    // es werden nur die Daten der Tabelle adm_user_data entfernt
    // die Kategorie und adm_user_field bleiben erhalten
    public function clearFieldData()
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
    
    // Methode formatiert bei Datumsfeldern in das eingestellte Datumsformat
    public function getValue($field_name, $format = '')
    {
        $value = parent::getValue($field_name, $format);

        if($field_name == 'usd_value')
        {
            if($this->dbColumns['usf_type'] == 'DATE' && strlen($format) > 0 && strlen($value) > 0)
            {
                // ist das Feld ein Datumsfeld, dann das Datum formatieren
                $date = new DateTimeExtended($value, 'Y-m-d', 'date');
                if($date->valid() == false)
                {
                    return $value;
                }
                $value = $date->format($format);
            }
        	elseif($this->dbColumns['usf_type'] == 'DROPDOWN'
        	    || $this->dbColumns['usf_type'] == 'RADIO_BUTTON')
        	{
        		if($value > 0)
        		{
        			$arrListValues = explode("\r\n", $this->getValue('usf_value_list'));
					$value = $arrListValues[$value-1];
				}
        	}
            elseif($this->dbColumns['usf_name_intern'] == 'COUNTRY' && strlen($value) > 0)
            {
                // beim Land die sprachabhaengige Bezeichnung auslesen
                global $g_l10n;
                $value = $g_l10n->getCountryByCode($value);
            }
        }
        return $value;
    }

    // bei setValue werden die Werte nicht auf Gueltigkeit geprueft
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }

    // prueft die Gueltigkeit der uebergebenen Werte und nimmt ggf. Anpassungen vor
    public function setValue($field_name, $field_value)
    {
        global $g_preferences;

        if($field_name == 'usd_value' && strlen($field_value) > 0)
        {
            if($this->dbColumns['usf_type'] == 'CHECKBOX')
            {
                // Checkbox darf nur 1 oder 0 haben
                if($field_value != 0 && $field_value != 1 && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->dbColumns['usf_type'] == 'DATE')
            {
                // Datum muss gueltig sein und formatiert werden
                $date = new DateTimeExtended($field_value, $g_preferences['system_date'], 'date');
                if($date->valid() == false)
                {
                    if($this->noValueCheck != true)
                    {                        
                        return false;
                    }
                }
                else
                {
                    $field_value = $date->format('Y-m-d');
                }
            }
            elseif($this->dbColumns['usf_type'] == 'EMAIL')
            {
                // Email darf nur gueltige Zeichen enthalten und muss einem festen Schema entsprechen
                $field_value = admStrToLower($field_value);
                if (!strValidCharacters($field_value, 'email') && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->dbColumns['usf_type'] == 'NUMERIC')
            {
                // Zahl muss numerisch sein
                if(is_numeric(strtr($field_value, ',.', '00')) == false && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->dbColumns['usf_type'] == 'URL')
            {
                // Homepage darf nur gueltige Zeichen enthalten
                if (!strValidCharacters($field_value, 'url') && $this->noValueCheck != true)
                {
                    return false;
                }
                // Homepage noch mit http vorbelegen
                if(strpos(admStrToLower($field_value), 'http://')  === false
                && strpos(admStrToLower($field_value), 'https://') === false )
                {
                    $field_value = 'http://'. $field_value;
                }
            }

        }
        return parent::setValue($field_name, $field_value);
    } 
}
?>