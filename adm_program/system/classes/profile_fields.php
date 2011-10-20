<?php
/******************************************************************************
 * Class reads the user fields structure and give access to it
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * getHtmlValue($fieldNameIntern, $value)
 *              - returns the value of that field in html format with consideration 
 *                of all layout parameters
 *              - 'value' must be commited so that layout is also possible for 
 *                values that aren't stored in database
 * getProperty($fieldNameIntern, $column, $format = '')
 *              - returns for a fieldname intern (usf_name_intern) the value of 
 *				  the column from table adm_user_fields
 * getPropertyById($field_id, $column, $format = '')
 *              - returns for field id (usf_id) the value of the column from table adm_user_fields
 * getValue($fieldNameIntern, $format = '')
 *              - returns the value of column usd_value for fieldname intern
 * noValueCheck()
 *              - will not check any value if method setValue is called
 * readUserData($userId)
 *              - build an array with the data of all user fields
 * readProfileFields()
 *              - build an array with the structure of all user fields
 * saveUserData($userId)
 *              - save data of every user field
 * setValue($fieldNameIntern, $fieldValue)
 *              - set value for column usd_value of field
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_user_field.php');

class ProfileFields
{
    public    $mProfileFields = array();// Array with all user fields objects
    public    $mUserData = array();		// Array with all user data objects

	protected $mOrganization;
	protected $mUserId;					// UserId of the current user of this object
	public 	  $mDb;						// db object must public because of session handling
    protected $noValueCheck;    		// if true, than no value will be checked if method setValue is called

    // Constructor
    public function __construct(&$db, &$organization)
    {
		$this->mDb =& $db;
		$this->mOrganization = $organization;
		$this->readProfileFields();
		$this->mUserId = 0;
		$this->noValueCheck = false;
    }
	
	public function clearUserData()
	{
		$this->mUserData = array();
		$this->mUserId = 0;
	}
	
	// returns for a fieldname intern (usf_name_intern) the value of the column from table adm_user_fields
	public function getProperty($fieldNameIntern, $column, $format = '')
	{
		if(array_key_exists($fieldNameIntern, $this->mProfileFields))
		{
			return $this->mProfileFields[$fieldNameIntern]->getValue($column, $format);
		}

		// if id-field not exists then return zero
		if(strpos($column, '_id') > 0)
		{
			return 0;
		}
        return null;
	}
	
    // returns for field id (usf_id) the value of the column from table adm_user_fields
    public function getPropertyById($field_id, $column, $format = '')
    {
        foreach($this->mProfileFields as $field)
        {
            if($field->getValue('usf_id') == $field_id)
            {
                return $field->getValue($column, $format);
            }
        }
        return null;
    }

	// returns the value of that field in html format with consideration of all layout parameters
	// 'value' must be commited so that layout is also possible for values that aren't stored in database
	public function getHtmlValue($fieldNameIntern, $value)
	{
		global $gPreferences, $g_root_path;

		if(strlen($value) > 0
		&& array_key_exists($fieldNameIntern, $this->mProfileFields) == true)
		{
			// create html for each field type
			$htmlValue = $value;

			if($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'CHECKBOX')
			{
				if($value == 1)
				{
					$htmlValue = '<img src="'.THEME_PATH.'/icons/checkbox_checked.gif" alt="on" />';
				}
				else
				{
					$htmlValue = '<img src="'.THEME_PATH.'/icons/checkbox.gif" alt="off" />';
				}
			}
			elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'EMAIL')
			{
				// the value in db is only the position, now search for the text
				if(strlen($value) > 0)
				{
					if($gPreferences['enable_mail_module'] != 1)
					{
						$emailLink = 'mailto:'.$value;
					}
					else
					{
						$emailLink = $g_root_path.'/adm_program/modules/mail/mail.php?usr_id='. $this->mUserId;
					}
					if(strlen($value) > 30)
					{
						$htmlValue = '<a href="'.$emailLink.'" title="'.$value.'">'.substr($value, 0, 30).'...</a>';
					}
					else
					{
						$htmlValue = '<a href="'.$emailLink.'" style="overflow: visible; display: inline;" title="'.$value.'">'.$value.'</a>';;
					}
				}
			}
			elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'URL')
			{
				if(strlen($value) > 0)
				{
					if(strlen($value) > 35)
					{
						$htmlValue = '<a href="'. $value.'" target="_blank" title="'. $value.'">'. substr($value, strpos($value, '//') + 2, 35). '...</a>';
					}
					else
					{
						$htmlValue = '<a href="'. $value.'" target="_blank" title="'. $value.'">'. substr($value, strpos($value, '//') + 2). '</a>';
					}
				}
			}
			elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'TEXT_BIG')
			{
				$htmlValue = nl2br($value);
			}
		
			// if field has url then create a link
			if(strlen($this->mProfileFields[$fieldNameIntern]->getValue('usf_url')))
			{
				$htmlValue = '<a href="'.$this->mProfileFields[$fieldNameIntern]->getValue('usf_url').'" target="_blank">'.$htmlValue.'</a>';
				
				// replace a variable in url with user value
				if(strpos($this->mProfileFields[$fieldNameIntern]->getValue('usf_url'), '%user_content%') !== false)
				{
					$htmlValue = preg_replace ('/%user_content%/', $value,  $htmlValue);

				}
			}
			$value = $htmlValue;
		}
		else
		{
			// special case for type CHECKBOX and no value is there, then show unchecked checkbox
			if(array_key_exists($fieldNameIntern, $this->mProfileFields) == true
			&& $this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'CHECKBOX')
			{
				$value = '<img src="'.THEME_PATH.'/icons/checkbox.gif" alt="off" />';
			}
		}
		return $value;
	}

	// returns the user value for this field
	// format = 'html' : returns the value in html-format if this is necessary for that field type
	public function getValue($fieldNameIntern, $format = '')
	{
		global $gL10n, $gPreferences;
		$value = '';

		// exists a profile field with that name ?
		// then check if user has a data object for this field and then read value of this object
		if(array_key_exists($fieldNameIntern, $this->mProfileFields)
		&& array_key_exists($this->mProfileFields[$fieldNameIntern]->getValue('usf_id'), $this->mUserData)) 
		{
			$value = $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->getValue('usd_value', $format);

			if($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DATE' && strlen($value) > 0)
			{
				if(strlen($format) == 0 || $format == 'html')
				{
					$dateFormat = $gPreferences['system_date'];
				}
				else
				{
					$dateFormat = $format;
				}
				
				// if date field then the current date format must be used
				$date = new DateTimeExtended($value, 'Y-m-d', 'date');
				if($date->valid() == false)
				{
					return $value;
				}
				$value = $date->format($dateFormat);
			}
			elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DROPDOWN'
				|| $this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'RADIO_BUTTON')
			{
				// the value in db is only the position, now search for the text
				if($value > 0)
				{
					$arrListValues = $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list');
					$value = $arrListValues[$value-1];
					
				}
			}
			elseif($fieldNameIntern == 'COUNTRY' && strlen($value) > 0)
			{
				// read the language name of the country
				$value = $gL10n->getCountryByCode($value);
			}
		}
		
		// get html output for that field type and value
		if($format == 'html')
		{
			$value = $this->getHtmlValue($fieldNameIntern, $value);
		}

		return $value;
	}

    // will not check any value if method setValue is called
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }

	// build an array with the data of all user fields
	// userId : read data from this user
	public function readUserData($userId)
	{
		if(count($this->mProfileFields) == 0)
		{
			$this->readProfileFields();
		}

		if($userId > 0)
		{
			// remember the user
			$this->mUserId = $userId;
			
			// read all user data of user
			$sql = 'SELECT * FROM '.TBL_USER_DATA.', '. TBL_USER_FIELDS. '
					 WHERE usd_usf_id = usf_id
					   AND usd_usr_id = '.$userId;
			$usdResult = $this->mDb->query($sql);

			while($row = $this->mDb->fetch_array($usdResult))
			{
				if(isset($this->mUserData[$row['usd_usf_id']]) == false)
				{
					$this->mUserData[$row['usd_usf_id']] = new TableAccess($this->mDb, TBL_USER_DATA, 'usd');
				}
				$this->mUserData[$row['usd_usf_id']]->setArray($row);
			}
		}
	}

	// build an array with the structure of all user fields
	public function readProfileFields()
	{
		// first initialize existing data
		$this->mProfileFields = array();
		$this->mUserData   = array();

		// read all user fields and belonging category data of organization
		$sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
                 WHERE usf_cat_id = cat_id
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = '.$this->mOrganization->getValue('org_id').' )
                 ORDER BY cat_sequence ASC, usf_sequence ASC ';
        $usfResult = $this->mDb->query($sql);

        while($row = $this->mDb->fetch_array($usfResult))
        {
            if(isset($this->mProfileFields[$row['usf_name_intern']]) == false)
            {
                $this->mProfileFields[$row['usf_name_intern']] = new TableUserField($this->mDb);
            }
            $this->mProfileFields[$row['usf_name_intern']]->setArray($row);
        }
	}

	// save data of every user field
	// userId : id is neccessary if new user, that id was not known before
	public function saveUserData($userId)
	{
		$this->mDb->startTransaction();

		foreach($this->mUserData as $key => $value)
		{
			// if new user than set user id
			if($this->mUserId == 0)
			{
				$this->mUserData[$key]->setValue('usd_usr_id', $userId);
			}

			// if value exists and new value is empty then delete entry
			if($this->mUserData[$key]->getValue('usd_id') > 0
			&& strlen($this->mUserData[$key]->getValue('usd_value')) == 0)
			{
				$this->mUserData[$key]->delete();
			}
			else
			{
				$this->mUserData[$key]->save();
			}
		}

		$this->mUserId = $userId;
		$this->mDb->endTransaction();
	}
	
	// set value for column usd_value of field
    public function setValue($fieldNameIntern, $fieldValue)
    {
        global $gPreferences;

        if(strlen($fieldValue) > 0)
        {
			if($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'CHECKBOX')
            {
                // Checkbox darf nur 1 oder 0 haben
                if($fieldValue != 0 && $fieldValue != 1 && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DATE')
            {
                // Datum muss gueltig sein und formatiert werden
                $date = new DateTimeExtended($fieldValue, $gPreferences['system_date'], 'date');
                if($date->valid() == false)
                {
                    if($this->noValueCheck != true)
                    {                        
                        return false;
                    }
                }
                else
                {
                    $fieldValue = $date->format('Y-m-d');
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'EMAIL')
            {
                // Email darf nur gueltige Zeichen enthalten und muss einem festen Schema entsprechen
                $fieldValue = admStrToLower($fieldValue);
                if (!strValidCharacters($fieldValue, 'email') && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'NUMERIC')
            {
                // Zahl muss numerisch sein
                if(is_numeric(strtr($fieldValue, ',.', '00')) == false && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'URL')
            {
                // Homepage darf nur gueltige Zeichen enthalten
                if (!strValidCharacters($fieldValue, 'url') && $this->noValueCheck != true)
                {
                    return false;
                }
                // Homepage noch mit http vorbelegen
                if(strpos(admStrToLower($fieldValue), 'http://')  === false
                && strpos(admStrToLower($fieldValue), 'https://') === false )
                {
                    $fieldValue = 'http://'. $fieldValue;
                }
            }

        }

		// first check if user has a data object for this field and then set value of this user field
		if(array_key_exists($this->mProfileFields[$fieldNameIntern]->getValue('usf_id'), $this->mUserData))
		{
			return $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_value', $fieldValue);
		}
		elseif(isset($this->mProfileFields[$fieldNameIntern]) == true && strlen($fieldValue) > 0)
		{
			$this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')] = new TableAccess($this->mDb, TBL_USER_DATA, 'usd');
			$this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_usf_id', $this->mProfileFields[$fieldNameIntern]->getValue('usf_id'));
			$this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_usr_id', $this->mUserId);
			return $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_value', $fieldValue);
		}

		return false;
    }
}
?>