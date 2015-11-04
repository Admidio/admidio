<?php
/*****************************************************************************/
/** @class ProfileFields
 *  @brief Reads the user fields structure out of database and give access to it
 *
 *  When an object is created than the actual profile fields structure will
 *  be read. In addition to this structure you can read the user values for
 *  all fields if you call @c readUserData . If you read field values than
 *  you will get the formated output. It's also possible to set user data and
 *  save this data to the database
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class ProfileFields
{
    public $mProfileFields = array();   ///< Array with all user fields objects
    public $mUserData = array();        ///< Array with all user data objects

    protected $mUserId;                 ///< UserId of the current user of this object
    protected $mDb;                     ///< An object of the class Database for communication with the database
    protected $noValueCheck;            ///< if true, than no value will be checked if method setValue is called
    public $columnsValueChanged;        ///< flag if a value of one field had changed

    /** constructor that will initialize variables and read the profile field structure
     *  @param object $database       Database object (should be @b $gDb)
     *  @param int    $organizationId The id of the organization for which the profile field structure should be read
     */
    public function __construct(&$database, $organizationId)
    {
        $this->setDatabase($database);

        $this->readProfileFields($organizationId);
        $this->mUserId = 0;
        $this->noValueCheck = false;
        $this->columnsValueChanged = false;
    }

    /**
     *  Called on serialization of this object. The database object could not
     *  be serialized and should be ignored.
     *  @return Returns all class variables that should be serialized.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('mDb'));
    }

    /** user data of all profile fields will be initialized
     *  the fields array will not be renewed
     */
    public function clearUserData()
    {
        $this->mUserData = array();
        $this->mUserId = 0;
        $this->columnsValueChanged = false;
    }

    /**
     * returns for a fieldname intern (usf_name_intern) the value of the column from table adm_user_fields
     * @param  string $fieldNameIntern Expects the @b usf_name_intern of table @b adm_user_fields
     * @param  string $column          The column name of @b adm_user_field for which you want the value
     * @param  string $format          Optional the format (is necessary for timestamps)
     * @return mixed
     */
    public function getProperty($fieldNameIntern, $column, $format = '')
    {
        if($column == 'usf_icon')
        {
            $value =$this->mProfileFields[$fieldNameIntern]->getValue($column, $format);
        }

        if(array_key_exists($fieldNameIntern, $this->mProfileFields))
        {
            return $this->mProfileFields[$fieldNameIntern]->getValue($column, $format);
        }

        // if id-field not exists then return zero
        if(strpos($column, '_id') > 0)
        {
            return 0;
        }
        return '';
    }

    /** returns for field id (usf_id) the value of the column from table adm_user_fields
     *  @param $fieldId Expects the @b usf_id of table @b adm_user_fields
     *  @param $column The column name of @b adm_user_field for which you want the value
     *  @param $format Optional the format (is necessary for timestamps)
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach($this->mProfileFields as $field)
        {
            if($field->getValue('usf_id') == $fieldId)
            {
                return $field->getValue($column, $format);
            }
        }
        return '';
    }

    /** Returns the value of the field in html format with consideration of all layout parameters
     *  @param $fieldNameIntern Internal profile field name of the field that should be html formated
     *  @param $value The value that should be formated must be commited so that layout is also possible for values that aren't stored in database
     *  @param $value2 An optional parameter that is necessary for some special fields like email to commit the user id
     *  @return Returns an html formated string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value, $value2 = '')
    {
        global $gPreferences, $g_root_path, $gL10n;

        if($value !== ''
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
                if($value !== '')
                {
                    if($gPreferences['enable_mail_module'] != 1)
                    {
                        $emailLink = 'mailto:'.$value;
                    }
                    else
                    {
                        // set value2 to user id because we need a second parameter in the link to mail module
                        if($value2 === '')
                        {
                            $value2 = $this->mUserId;
                        }

                        $emailLink = $g_root_path.'/adm_program/modules/messages/messages_write.php?usr_id='. $value2;
                    }
                    if(strlen($value) > 30)
                    {
                        $htmlValue = '<a href="'.$emailLink.'" title="'.$value.'">'.substr($value, 0, 30).'...</a>';
                    }
                    else
                    {
                        $htmlValue = '<a href="'.$emailLink.'" style="overflow: visible; display: inline;" title="'.$value.'">'.$value.'</a>';
                    }
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DROPDOWN'
            || $this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'RADIO_BUTTON')
            {
                $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                // first replace windows new line with unix new line and then create an array
                $valueFormated = str_replace("\r\n", "\n", $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list', 'database'));
                $arrListValues = explode("\n", $valueFormated);

                foreach($arrListValues as $key => &$listValue)
                {
                    if($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'RADIO_BUTTON')
                    {
                        // if value is imagefile or imageurl then show image
                        if(strpos(admStrToLower($listValue), '.png') > 0 || strpos(admStrToLower($listValue), '.jpg') > 0)
                        {
                            // if there is imagefile and text separated by | then explode them
                            if(strpos($listValue, '|') > 0)
                            {
                                $listValueImage = substr($listValue, 0, strpos($listValue, '|'));
                                $listValueText  = substr($listValue, strpos($listValue, '|') + 1);
                            }
                            else
                            {
                                $listValueImage = $listValue;
                                $listValueText  = $this->getValue('usf_name');
                            }

                            // if text is a translation-id then translate it
                            if(strpos($listValueText, '_') == 3)
                            {
                                $listValueText = $gL10n->get(admStrToUpper($listValueText));
                            }

                            try
                            {
                                // create html for optionbox entry
                                if(strpos(admStrToLower($listValueImage), 'http') === 0 && strValidCharacters($listValueImage, 'url'))
                                {
                                    $listValue = '<img class="admidio-icon-info" src="'.$listValueImage.'" title="'.$listValueText.'" alt="'.$listValueText.'" />';
                                }
                                elseif(admStrIsValidFileName($listValueImage, true))
                                {
                                    $listValue = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/'.$listValueImage.'" title="'.$listValueText.'" alt="'.$listValueText.'" />';
                                }
                            }
                            catch(AdmException $e)
                            {
                                $e->showText();
                            }
                        }
                    }

                    // if text is a translation-id then translate it
                    if(strpos($listValue, '_') == 3)
                    {
                        $listValue = $gL10n->get(admStrToUpper($listValue));
                    }

                    // save values in new array that starts with key = 1
                    $arrListValuesWithKeys[++$key] = $listValue;
                }
                $htmlValue = $arrListValuesWithKeys[$value];
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'PHONE')
            {
                if($value !== '')
                {
                    $htmlValue = '<a href="tel:'. str_replace(array('-', '/', ' ', '(', ')'), '', $value).'">'. $value. '</a>';
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'URL')
            {
                if($value !== '')
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
                if($fieldNameIntern == 'FACEBOOK' && is_numeric($value))
                {
                    // facebook has two different profile urls (id and facebook name),
                    // we could only store one way in database (facebook name) and the other (id) is defined here :)
                    $htmlValue = '<a href="http://www.facebook.com/profile.php?id='.$value.'" target="_blank">'.$htmlValue.'</a>';
                }
                else
                {
                    $htmlValue = '<a href="'.$this->mProfileFields[$fieldNameIntern]->getValue('usf_url').'" target="_blank">'.$htmlValue.'</a>';
                }

                // replace a variable in url with user value
                if(strpos($this->mProfileFields[$fieldNameIntern]->getValue('usf_url'), '%user_content%') !== false)
                {
                    $htmlValue = preg_replace('/%user_content%/', $value, $htmlValue);
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

                // if field has url then create a link
                if(strlen($this->mProfileFields[$fieldNameIntern]->getValue('usf_url')))
                {
                    $value = '<a href="'.$this->mProfileFields[$fieldNameIntern]->getValue('usf_url').'" target="_blank">'.$value.'</a>';
                }
            }

        }
        return $value;
    }

    /** Returns the user value for this column @n
     *  format = 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function @n
     *  format = 'html'  : returns the value in html-format if this is necessary for that field type @n
     *  format = 'database' : returns the value that is stored in database with no format applied
     *  @param $fieldNameIntern Expects the @b usf_name_intern of table @b adm_user_fields
     *  @param $format Returns the field value in a special format @b text, @b html, @b database or datetime (detailed description in method description)
     */
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

            if($format != 'database')
            {
                if($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DATE' && $value !== '')
                {
                    // if no format or html is set then show date format from Admidio settings
                    if($format === '' || $format == 'html')
                    {
                        $dateFormat = $gPreferences['system_date'];
                    }
                    else
                    {
                        $dateFormat = $format;
                    }

                    // if date field then the current date format must be used
                    $date = new DateTimeExtended($value, 'Y-m-d');
                    if(!$date->isValid())
                    {
                        return $value;
                    }
                    $value = $date->format($dateFormat);
                }
                elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DROPDOWN'
                    || $this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'RADIO_BUTTON')
                {
                    // the value in db is only the position, now search for the text
                    if($value > 0 && $format != 'html')
                    {
                        $arrListValues = $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list');
                        $value = $arrListValues[$value];

                    }
                }
                elseif($fieldNameIntern == 'COUNTRY' && $value !== '')
                {
                    // read the language name of the country
                    $value = $gL10n->getCountryByCode($value);
                }
            }
        }

        // get html output for that field type and value
        if($format == 'html')
        {
            $value = $this->getHtmlValue($fieldNameIntern, $value);
        }

        return $value;
    }

    /** If this method is called than all further calls of method @b setValue will not check the values.
     *  The values will be stored in database without any inspections !
     */
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }


    /** Reads the profile fields structure out of database table @b adm_user_fields
     *  and adds an object for each field structure to the @b mProfileFields array.
     *  @param $organizationId The id of the organization for which the profile fields
     *                         structure should be read.
     */
    public function readProfileFields($organizationId)
    {
        // first initialize existing data
        $this->mProfileFields = array();
        $this->clearUserData();

        // read all user fields and belonging category data of organization
        $sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
                 WHERE usf_cat_id = cat_id
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = '.$organizationId.' )
                 ORDER BY cat_sequence ASC, usf_sequence ASC ';
        $userFieldsStatement = $this->mDb->query($sql);

        while($row = $userFieldsStatement->fetch())
        {
            if(isset($this->mProfileFields[$row['usf_name_intern']]) == false)
            {
                $this->mProfileFields[$row['usf_name_intern']] = new TableUserField($this->mDb);
            }
            $this->mProfileFields[$row['usf_name_intern']]->setArray($row);
        }
    }

    /** Reads the user data of all profile fields out of database table @b adm_user_data
     *  and adds an object for each field data to the @b mUserData array.
     *  If profile fields structure wasn't read, this will be done before.
     *  @param $userId         The id of the user for which the user data should be read.
     *  @param $organizationId The id of the organization for which the profile fields
     *                         structure should be read if necessary.
     */
    public function readUserData($userId, $organizationId)
    {
        if(count($this->mProfileFields) == 0)
        {
            $this->readProfileFields($organizationId);
        }

        if($userId > 0)
        {
            // remember the user
            $this->mUserId = $userId;

            // read all user data of user
            $sql = 'SELECT * FROM '.TBL_USER_DATA.', '. TBL_USER_FIELDS. '
                     WHERE usd_usf_id = usf_id
                       AND usd_usr_id = '.$userId;
            $userDataStatement = $this->mDb->query($sql);

            while($row = $userDataStatement->fetch())
            {
                if(isset($this->mUserData[$row['usd_usf_id']]) == false)
                {
                    $this->mUserData[$row['usd_usf_id']] = new TableAccess($this->mDb, TBL_USER_DATA, 'usd');
                }
                $this->mUserData[$row['usd_usf_id']]->setArray($row);
            }
        }
    }

    // save data of every user field
    // userId : id is necessary if new user, that id was not known before
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

        $this->columnsValueChanged = false;
        $this->mUserId = $userId;
        $this->mDb->endTransaction();
    }

    /**
     *  Set the database object for communication with the database of this class.
     *  @param object $database An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase(&$database)
    {
        if(is_object($database))
        {
            $this->mDb =& $database;
        }
    }

    // set value for column usd_value of field
    public function setValue($fieldNameIntern, $fieldValue)
    {
        global $gPreferences;
        $returnCode = false;

        if($fieldValue !== '')
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
                $date = DateTime::createFromFormat($gPreferences['system_date'], $fieldValue);
                if($date == false)
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
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'NUMBER')
            {
                // A number must be numeric
                if(is_numeric($fieldValue) == false && $this->noValueCheck != true)
                {
                    return false;
                }
                else
                {
                    // numbers don't have leading zero
                    $fieldValue = ltrim($fieldValue, '0');
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'DECIMAL')
            {
                // A number must be numeric
                if(is_numeric(strtr($fieldValue, ',.', '00')) == false && $this->noValueCheck != true)
                {
                    return false;
                }
                else
                {
                    // numbers don't have leading zero
                    $fieldValue = ltrim($fieldValue, '0');
                }
            }
            elseif($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') == 'PHONE')
            {
                // Homepage darf nur gueltige Zeichen enthalten
                if (!strValidCharacters($fieldValue, 'phone') && $this->noValueCheck != true)
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
                && strpos(admStrToLower($fieldValue), 'https://') === false)
                {
                    $fieldValue = 'http://'. $fieldValue;
                }
            }

        }

        // first check if user has a data object for this field and then set value of this user field
        if(array_key_exists($this->mProfileFields[$fieldNameIntern]->getValue('usf_id'), $this->mUserData))
        {
            $returnCode = $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_value', $fieldValue);
        }
        elseif(isset($this->mProfileFields[$fieldNameIntern]) == true && $fieldValue !== '')
        {
            $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')] = new TableAccess($this->mDb, TBL_USER_DATA, 'usd');
            $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_usf_id', $this->mProfileFields[$fieldNameIntern]->getValue('usf_id'));
            $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_usr_id', $this->mUserId);
            $returnCode = $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->setValue('usd_value', $fieldValue);
        }

        if($returnCode && $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->hasColumnsValueChanged())
        {
            $this->columnsValueChanged = true;
        }

        return $returnCode;
    }
}
