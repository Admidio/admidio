<?php 
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
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

require_once(SERVER_PATH. '/adm_program/system/classes/table_organizations.php');

class Organization extends TableOrganizations
{
    protected $b_check_childs;        // Flag, ob schon nach Kinderorganisationen gesucht wurde
    protected $child_orgas = array(); // Array mit allen Kinderorganisationen
    
    // Konstruktor
    public function __construct(&$db, $organization = '')
    {
        parent::__construct($db, $organization);
    }
    
    // interne Funktion, die spezielle Daten des Organizationobjekts loescht
    public function clear()
    {
        parent::clear();

        $this->b_check_childs = false;
        $this->child_orgas    = array();
    }
        
    // gibt ein Array mit allen organisationsspezifischen Einstellungen
    // aus adm_preferences zurueck
    public function getPreferences()
    {
        $sql    = 'SELECT * FROM '. TBL_PREFERENCES. '
                    WHERE prf_org_id = '. $this->getValue('org_id');
        $result = $this->db->query($sql);

        $preferences = array();
        while($prf_row = $this->db->fetch_array($result))
        {
            $preferences[$prf_row['prf_name']] = $prf_row['prf_value'];
        }
        
        return $preferences;
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
    
    // prueft, ob die uebergebene Orga (ID oder Shortname) ein Kind
    // der aktuellen Orga ist
    public function isChildOrganization($organization)
    {
        if($this->b_check_childs == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->child_orgas = $this->getReferenceOrganizations(true, false);
            $this->b_check_childs = true;
        }
        
        if(is_numeric($organization))
        {
            // org_id wurde uebergeben
            $ret_code = array_key_exists($organization, $this->child_orgas);
        }
        else
        {
            // org_shortname wurde uebergeben
            $ret_code = in_array($organization, $this->child_orgas);
        }
        return $ret_code;
    }
    
    // prueft, ob die Orga Kinderorganisationen besitzt
    public function hasChildOrganizations()
    {
        if($this->b_check_childs == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->child_orgas = $this->getReferenceOrganizations(true, false);
            $this->b_check_childs = true;
        }

        if(count($this->child_orgas) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
?>