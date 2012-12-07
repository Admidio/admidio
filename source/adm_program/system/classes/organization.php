<?php 
/******************************************************************************
 * Class manages access to database table adm_organizations
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Objekt einer Organisation zu erstellen. 
 * Eine Organisation kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Folgende Methoden stehen neben den Standardmethoden aus der table_access_class zur Verfuegung:
 *
 * getPreferences()       - gibt ein Array mit allen organisationsspezifischen Einstellungen
 *                          aus adm_preferences zurueck
 * setPreferences($preferences, $update = true)
 *                        - schreibt alle Parameter aus dem uebergebenen Array
 *                          zurueck in die Datenbank, dabei werden nur die veraenderten oder
 *                          neuen Parameter geschrieben
 * getReferenceOrganizations($child = true, $parent = true)
 *                        - Gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
 * isChildOrganization($organization)
 *                        - prueft ob die uebergebene Orga Kind der aktuellen Orga ist
 * hasChildOrganizations()- prueft, ob die Orga Kinderorganisationen besitzt
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class Organization extends TableAccess
{
    protected $bCheckChildOrganizations;     ///< Flag will be set if the class had already search for child organizations
    protected $childOrganizations = array(); ///< Array with all child organizations of this organization
	protected $preferences = array();		 ///< Array with all preferences of this organization. Array key is the column @b prf_name and array value is the column @b prf_value.
    
	/** Constuctor that will create an object of a recordset of the table adm_organizations. 
	 *  If the id is set than the specific organization will be loaded.
	 *  @param $db           Object of the class database. This should be the default object $gDb.
	 *  @param $organization The recordset of the organization with this id will be loaded. 
	 *                       The organization can be the table id or the organization shortname. 
	 *                       If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $organization = '')
    {
        parent::__construct($db, TBL_ORGANIZATIONS, 'org');
		
		if(is_numeric($organization))
		{
			$this->readDataById($organization);
		}
		else
		{
			$this->readDataByColumns(array('org_shortname' => $organization));
		}
    }
    
    // interne Funktion, die spezielle Daten des Organizationobjekts loescht
    public function clear()
    {
        parent::clear();

        $this->bCheckChildOrganizations = false;
        $this->childOrganizations       = array();
		$this->preferences              = array();
    }
    
	/** Create a comma separated list with all organization ids of children, 
	 *  parent and this organization that is prepared for use in SQL
	 *  @param $shortname If set to true then a list of all shortnames will be returned
	 *  @return Returns a string with a comma separated list of all organization 
	 *          ids that are parents or children and the own id
	 */
    public function getFamilySQL($shortname = false)
    {
        $organizationsId = '';
		$organizationsShortname = '';
        $arr_ref_orgas = $this->getReferenceOrganizations(true, true);

        foreach($arr_ref_orgas as $key => $value)
        {
            $organizationsShortname .= '\''.$value.'\',';
			$organizationsId .= $key.',';
        }

        $organizationsShortname .= '\''. $this->getValue('org_shortname'). '\'';
		$organizationsId .= $this->getValue('org_id');

		if($shortname)
		{
			return $organizationsShortname;
		}
		else
		{
			return $organizationsId;
		}
    }
    
    // gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
    // Ueber die Variablen $child und $parent kann die ermittlen der 
    // Eltern bzw. Kinderorgas deaktiviert werden
    //
    // org_id ist der Schluessel und org_shortname der Wert des Arrays
    // falls $longname = true gesetzt ist, ist org_longname der Wert des Arrays
    public function getReferenceOrganizations($child = true, $parent = true, $longname = false)
    {
        $arr_child_orgas = array();
    
        $sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. '
                 WHERE ';
        if($child == true)
        {
            $sql .= ' org_org_id_parent = '. $this->getValue('org_id');
        }
        if($parent == true
        && $this->getValue('org_org_id_parent') > 0)
        {
            if($child == true)
            {
                $sql .= ' OR ';
            }
            $sql .= ' org_id = '. $this->getValue('org_org_id_parent');
        }
        $this->db->query($sql);
        
        while($row = $this->db->fetch_array())
        {
            if($longname == true)
            {
                $arr_child_orgas[$row['org_id']] = $row['org_longname'];
            }
            else
            {
                $arr_child_orgas[$row['org_id']] = $row['org_shortname'];
            }
        }
        return $arr_child_orgas;
    }
        
	/** Reads all preferences of the current organization out of the database
	 *  table adm_preferences. If the object has read the preferences than
	 *  the method will return the stored values of the object.
	 *  @return Returns an array with all preferences of this organization. 
	 *          Array key is the column @b prf_name and array value is the column @b prf_value.
	 */
    public function getPreferences()
    {
		if(count($this->preferences) == 0)
		{
			$sql    = 'SELECT * FROM '. TBL_PREFERENCES. '
						WHERE prf_org_id = '. $this->getValue('org_id');
			$result = $this->db->query($sql);

			while($prf_row = $this->db->fetch_array($result))
			{
				$this->preferences[$prf_row['prf_name']] = $prf_row['prf_value'];
			}
		}
        
        return $this->preferences;
    }
    
    // prueft, ob die Orga Kinderorganisationen besitzt
    public function hasChildOrganizations()
    {
        if($this->bCheckChildOrganizations == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->childOrganizations = $this->getReferenceOrganizations(true, false);
            $this->bCheckChildOrganizations = true;
        }

        if(count($this->childOrganizations) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
    // prueft, ob die uebergebene Orga (ID oder Shortname) ein Kind
    // der aktuellen Orga ist
    public function isChildOrganization($organization)
    {
        if($this->bCheckChildOrganizations == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->childOrganizations = $this->getReferenceOrganizations(true, false);
            $this->bCheckChildOrganizations = true;
        }
        
        if(is_numeric($organization))
        {
            // org_id wurde uebergeben
            $ret_code = array_key_exists($organization, $this->childOrganizations);
        }
        else
        {
            // org_shortname wurde uebergeben
            $ret_code = in_array($organization, $this->childOrganizations);
        }
        return $ret_code;
    }
    
    // die Methode schreibt alle Parameter aus dem uebergebenen Array
    // zurueck in die Datenbank, dabei werden nur die veraenderten oder
    // neuen Parameter geschrieben
    // $update : bestimmt, ob vorhandene Werte aktualisiert werden
    public function setPreferences($preferences, $update = true)
    {
		$this->db->startTransaction();
        $db_preferences = $this->getPreferences();

        foreach($preferences as $key => $value)
        {
            if(array_key_exists($key, $db_preferences))
            {
                if($update == true
                && $value  != $db_preferences[$key])
                {
                    // Pref existiert in DB, aber Wert hat sich geaendert
                    $sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = \''.$value.'\'
                             WHERE prf_org_id = '. $this->getValue('org_id'). '
                               AND prf_name   = \''.$key.'\' ';
                    $this->db->query($sql);
                }
            }
            else
            {
                // Parameter existiert noch nicht in DB
                $sql = 'INSERT INTO '. TBL_PREFERENCES. ' (prf_org_id, prf_name, prf_value)
                        VALUES   ('. $this->getValue('org_id'). ', \''.$key.'\', \''.$value.'\') ';
                $this->db->query($sql);
            }
        }
		$this->db->endTransaction();
    }
    
    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName The name of the database column whose value should get a new value
     *  @param $newValue   The new value that should be stored in the database field
     *  @param $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.  
     *  @return Returns @b true if the value is stored in the current object and @b false if a check failed
     */ 
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        // org_shortname shouldn't be edited
        if($columnName == 'org_shortname')
        {
            return false;
        }
        elseif($columnName == 'org_homepage' && strlen($newValue) > 0)
        {
			// Homepage darf nur gueltige Zeichen enthalten
			if (!strValidCharacters($newValue, 'url'))
			{
				return false;
			}
			// Homepage noch mit http vorbelegen
			if(strpos(admStrToLower($newValue), 'http://')  === false
			&& strpos(admStrToLower($newValue), 'https://') === false )
			{
				$newValue = 'http://'. $newValue;
			}
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
?>