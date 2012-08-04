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
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableOrganizations extends TableAccess
{
	/** Constuctor that will create an object of a recordset of the table adm_organizations. 
	 *  If the id is set than the specific organization will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $organization The recordset of the organization with this id will be loaded. The organization can be the table id or the organization shortname. If id isn't set than an empty object of the table is created.
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
	
    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName The name of the database column whose value should get a new value
     *  @param $newValue The new value that should be stored in the database field
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