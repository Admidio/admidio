<?php
/******************************************************************************
 * Class reads the user fields structure and give access to it
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_user_field.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_user_data.php');

class UserFields
{
    protected $mUserFields = array();   // Array with all user fields objects
    protected $mUserData = array();		// Array with all user data objects
	protected $mOrganization;
	public 	  $mDb;						// db object must public because of session handling

    // Constructor
    public function __construct(&$db, &$organization)
    {
		$this->mDb =& $db;
		$this->mOrganization = $organization;
		$this->readUserFields();
    }
	
	function getValue($fieldNameIntern, $format)
	{
		$value = '';
		
		// first check if user has a data object for this field and then read value of this object
		if(array_key_exists($this->mUserFields[$fieldNameIntern]->getValue('usf_id'), $this->mUserData))
		{
			$value = $this->mUserData[$this->mUserFields[$fieldNameIntern]->getValue('usf_id')]->getValue('usd_value', $format);
		}
		
		return $value;
	}
	
	// build an array with the data of all user fields
	public function readUserData($userId)
	{
		if(count($this->mUserFields) == 0)
		{
			$this->readUserFields();
		}

		if($userId > 0)
		{
			// read all user data of user
			$sql = 'SELECT * FROM '.TBL_USER_DATA.'
					 WHERE usd_usr_id = '.$userId;
			$usdResult = $this->mDb->query($sql);

			while($row = $this->mDb->fetch_array($usdResult))
			{
				if(isset($this->mUserData[$row['usd_usf_id']]) == false)
				{
					$this->mUserData[$row['usd_usf_id']] = new TableUserData($this->mDb);
				}
				$this->mUserData[$row['usd_usf_id']]->setArray($row);
			}
		}
	}

	// build an array with the structure of all user fields
	public function readUserFields()
	{
		$this->mUserFields = array();

		// read all user fields and belonging category data of organization
		$sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
                 WHERE usf_cat_id = cat_id
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = '.$this->mOrganization->getValue('org_id').' )
                 ORDER BY cat_sequence ASC, usf_sequence ASC ';
        $usfResult = $this->mDb->query($sql);

        while($row = $this->mDb->fetch_array($usfResult))
        {
            if(isset($this->mUserFields[$row['usf_name_intern']]) == false)
            {
                $this->mUserFields[$row['usf_name_intern']] = new TableUserField($this->mDb);
            }
            $this->mUserFields[$row['usf_name_intern']]->setArray($row);
        }
	}
	
	public function saveUserData()
	{
		foreach($this->mUserData as $key => &$UserData)
		{
			$UserData->save();
		}
	}
}
?>