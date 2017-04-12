<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ProfileFields
 * @brief Reads the user fields structure out of database and give access to it
 *
 * When an object is created than the actual profile fields structure will
 * be read. In addition to this structure you can read the user values for
 * all fields if you call @c readUserData . If you read field values than
 * you will get the formated output. It's also possible to set user data and
 * save this data to the database
 */
class ProfileFields
{
    public $mProfileFields = array();   ///< Array with all user fields objects
    public $mUserData = array();        ///< Array with all user data objects

    protected $mUserId;                 ///< UserId of the current user of this object
    protected $mDb;                     ///< An object of the class Database for communication with the database
    protected $noValueCheck;            ///< if true, than no value will be checked if method setValue is called
    public $columnsValueChanged;        ///< flag if a value of one field had changed

    /**
     * constructor that will initialize variables and read the profile field structure
     * @param \Database $database       Database object (should be @b $gDb)
     * @param int       $organizationId The id of the organization for which the profile field structure should be read
     */
    public function __construct(&$database, $organizationId)
    {
        $this->mDb =& $database;
        $this->readProfileFields($organizationId);
        $this->mUserId = 0;
        $this->noValueCheck = false;
        $this->columnsValueChanged = false;
    }

    /**
     * Set the database object for communication with the database of this class.
     * @param \Database $database An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase(&$database)
    {
        $this->mDb =& $database;
    }

    /**
     * Called on serialization of this object. The database object could not
     * be serialized and should be ignored.
     * @return string[] Returns all class variables that should be serialized.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('mDb'));
    }

    /**
     * user data of all profile fields will be initialized
     * the fields array will not be renewed
     */
    public function clearUserData()
    {
        $this->mUserData = array();
        $this->mUserId = 0;
        $this->columnsValueChanged = false;
    }

    /**
     * returns for a fieldname intern (usf_name_intern) the value of the column from table adm_user_fields
     * @param string $fieldNameIntern Expects the @b usf_name_intern of table @b adm_user_fields
     * @param string $column          The column name of @b adm_user_field for which you want the value
     * @param string $format          Optional the format (is necessary for timestamps)
     * @return mixed
     */
    public function getProperty($fieldNameIntern, $column, $format = '')
    {
        if (array_key_exists($fieldNameIntern, $this->mProfileFields))
        {
            return $this->mProfileFields[$fieldNameIntern]->getValue($column, $format);
        }

        // if id-field not exists then return zero
        if (strpos($column, '_id') > 0)
        {
            return 0;
        }
        return '';
    }

    /**
     * returns for field id (usf_id) the value of the column from table adm_user_fields
     * @param int    $fieldId Expects the @b usf_id of table @b adm_user_fields
     * @param string $column  The column name of @b adm_user_field for which you want the value
     * @param string $format  Optional the format (is necessary for timestamps)
     * @return string
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach ($this->mProfileFields as $field)
        {
            if ((int) $field->getValue('usf_id') === (int) $fieldId)
            {
                return $field->getValue($column, $format);
            }
        }

        return '';
    }

    /**
     * Returns the value of the field in html format with consideration of all layout parameters
     * @param string     $fieldNameIntern Internal profile field name of the field that should be html formated
     * @param string|int $value           The value that should be formated must be commited so that layout
     *                                    is also possible for values that aren't stored in database
     * @param int        $value2          An optional parameter that is necessary for some special fields like email to commit the user id
     * @return string Returns an html formated string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value, $value2 = null)
    {
        global $gPreferences, $gL10n;

        if (!array_key_exists($fieldNameIntern, $this->mProfileFields))
        {
            return $value;
        }

        // if value is empty or null, then do nothing
        if ($value != '')
        {
            // create html for each field type
            $htmlValue = $value;

            $usfType = $this->mProfileFields[$fieldNameIntern]->getValue('usf_type');
            switch ($usfType)
            {
                case 'CHECKBOX':
                    if ($value == 1)
                    {
                        $htmlValue = '<img src="' . THEME_URL . '/icons/checkbox_checked.gif" alt="on" />';
                    }
                    else
                    {
                        $htmlValue = '<img src="' . THEME_URL . '/icons/checkbox.gif" alt="off" />';
                    }
                    break;
                case 'DATE':
                    if ($value !== '')
                    {
                        // date must be formated
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof \DateTime)
                        {
                            $htmlValue = $date->format($gPreferences['system_date']);
                        }
                    }
                    break;
                case 'EMAIL':
                    // the value in db is only the position, now search for the text
                    if ($value !== '')
                    {
                        if ($gPreferences['enable_mail_module'] != 1)
                        {
                            $emailLink = 'mailto:' . $value;
                        }
                        else
                        {
                            // set value2 to user id because we need a second parameter in the link to mail module
                            if ($value2 === null)
                            {
                                $value2 = $this->mUserId;
                            }

                            $emailLink = ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php?usr_id=' . $value2;
                        }
                        if (strlen($value) > 30)
                        {
                            $htmlValue = '<a href="' . $emailLink . '" title="' . $value . '">' . substr($value, 0, 30) . '...</a>';
                        }
                        else
                        {
                            $htmlValue = '<a href="' . $emailLink . '" title="' . $value . '" style="overflow: visible; display: inline;">' . $value . '</a>';
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                    // first replace windows new line with unix new line and then create an array
                    $valueFormated = str_replace("\r\n", "\n", $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list', 'database'));
                    $arrListValues = explode("\n", $valueFormated);

                    foreach ($arrListValues as $index => $listValue)
                    {
                        // if value is imagefile or imageurl then show image
                        if ($usfType === 'RADIO_BUTTON'
                        && (strpos(admStrToLower($listValue), '.png') > 0 || strpos(admStrToLower($listValue), '.jpg') > 0))
                        {
                            // if there is imagefile and text separated by | then explode them
                            if (strpos($listValue, '|') > 0)
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
                            if (strpos($listValueText, '_') === 3)
                            {
                                $listValueText = $gL10n->get(admStrToUpper($listValueText));
                            }

                            try
                            {
                                // create html for optionbox entry
                                if (strValidCharacters($listValueImage, 'url') && strpos(admStrToLower($listValueImage), 'http') === 0)
                                {
                                    $listValue = '<img class="admidio-icon-info" src="' . $listValueImage . '" title="' . $listValueText . '" alt="' . $listValueText . '" />';
                                }
                                elseif (admStrIsValidFileName($listValueImage, true))
                                {
                                    $listValue = '<img class="admidio-icon-info" src="' . THEME_URL . '/icons/' . $listValueImage . '" title="' . $listValueText . '" alt="' . $listValueText . '" />';
                                }
                            }
                            catch (AdmException $e)
                            {
                                $e->showText();
                                // => EXIT
                            }
                        }

                        // if text is a translation-id then translate it
                        if (strpos($listValue, '_') === 3)
                        {
                            $listValue = $gL10n->get(admStrToUpper($listValue));
                        }

                        // save values in new array that starts with key = 1
                        $arrListValuesWithKeys[++$index] = $listValue;
                    }

                    $htmlValue = $arrListValuesWithKeys[$value];
                    break;
                case 'PHONE':
                    if ($value !== '')
                    {
                        $htmlValue = '<a href="tel:' . str_replace(array('-', '/', ' ', '(', ')'), '', $value) . '">' . $value . '</a>';
                    }
                    break;
                case 'URL':
                    if ($value !== '')
                    {
                        $displayValue = $value;

                        // trim "http://", "https://", "//"
                        if (strpos($displayValue, '//') !== false)
                        {
                            $displayValue = substr($displayValue, strpos($displayValue, '//') + 2);
                        }
                        // trim after the 35th char
                        if (strlen($value) > 35)
                        {
                            $displayValue = substr($displayValue, 0, 35) . '...';
                        }
                        $htmlValue = '<a href="' . $value . '" target="_blank" title="' . $value . '">' . $displayValue . '</a>';
                    }
                    break;
                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
            }

            // if field has url then create a link
            $usfUrl = $this->mProfileFields[$fieldNameIntern]->getValue('usf_url');
            if ($usfUrl !== '')
            {
                if ($fieldNameIntern === 'FACEBOOK' && is_numeric($value))
                {
                    // facebook has two different profile urls (id and facebook name),
                    // we could only store one way in database (facebook name) and the other (id) is defined here
                    $htmlValue = '<a href="https://www.facebook.com/profile.php?id=' . $value . '" target="_blank">' . $htmlValue . '</a>';
                }
                else
                {
                    $htmlValue = '<a href="' . $usfUrl . '" target="_blank">' . $htmlValue . '</a>';
                }

                // replace a variable in url with user value
                if (strpos($usfUrl, '#user_content#') !== false)
                {
                    $htmlValue = preg_replace('/#user_content#/', $value, $htmlValue);
                }
            }
            $value = $htmlValue;
        }
        // special case for type CHECKBOX and no value is there, then show unchecked checkbox
        else
        {
            if ($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') === 'CHECKBOX')
            {
                $value = '<img src="' . THEME_URL . '/icons/checkbox.gif" alt="off" />';

                // if field has url then create a link
                $usfUrl = $this->mProfileFields[$fieldNameIntern]->getValue('usf_url');
                if ($usfUrl !== '')
                {
                    $value = '<a href="' . $usfUrl . '" target="_blank">' . $value . '</a>';
                }
            }
        }

        return $value;
    }

    /**
     * Returns the user value for this column @n
     * format = 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function @n
     * format = 'html'  : returns the value in html-format if this is necessary for that field type @n
     * format = 'database' : returns the value that is stored in database with no format applied
     * @param string $fieldNameIntern Expects the @b usf_name_intern of table @b adm_user_fields
     * @param string $format          Returns the field value in a special format @b text, @b html, @b database
     *                                or datetime (detailed description in method description)
     * @return string|int|bool Returns the value for the column.
     */
    public function getValue($fieldNameIntern, $format = '')
    {
        global $gL10n, $gPreferences;

        $value = '';

        // exists a profile field with that name ?
        // then check if user has a data object for this field and then read value of this object
        if (array_key_exists($fieldNameIntern, $this->mProfileFields)
        &&  array_key_exists($this->mProfileFields[$fieldNameIntern]->getValue('usf_id'), $this->mUserData))
        {
            $value = $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->getValue('usd_value', $format);

            if ($format === 'database')
            {
                return $value;
            }

            if ($fieldNameIntern === 'COUNTRY')
            {
                if ($value !== '')
                {
                    // read the language name of the country
                    $value = $gL10n->getCountryByCode($value);
                }
            }
            else
            {
                switch ($this->mProfileFields[$fieldNameIntern]->getValue('usf_type'))
                {
                    case 'DATE':
                        if ($value !== '')
                        {
                            // if date field then the current date format must be used
                            $date = DateTime::createFromFormat('Y-m-d', $value);
                            if ($date === false)
                            {
                                return $value;
                            }

                            // if no format or html is set then show date format from Admidio settings
                            if ($format === '' || $format === 'html')
                            {
                                $value = $date->format($gPreferences['system_date']);
                            }
                            else
                            {
                                $value = $date->format($format);
                            }
                        }
                        break;
                    case 'DROPDOWN':
                    case 'RADIO_BUTTON':
                        // the value in db is only the position, now search for the text
                        if ($value > 0 && $format !== 'html')
                        {
                            $arrListValues = $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list', $format);
                            $value = $arrListValues[$value];
                        }
                        break;
                }
            }
        }

        // get html output for that field type and value
        if ($format === 'html')
        {
            $value = $this->getHtmlValue($fieldNameIntern, $value);
        }

        return $value;
    }

    /**
     * If this method is called than all further calls of method @b setValue will not check the values.
     * The values will be stored in database without any inspections !
     */
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }

    /**
     * Reads the profile fields structure out of database table @b adm_user_fields
     * and adds an object for each field structure to the @b mProfileFields array.
     * @param int $organizationId The id of the organization for which the profile fields
     *                            structure should be read.
     */
    public function readProfileFields($organizationId)
    {
        // first initialize existing data
        $this->mProfileFields = array();
        $this->clearUserData();

        // read all user fields and belonging category data of organization
        $sql = 'SELECT *
                  FROM '.TBL_USER_FIELDS.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = usf_cat_id
                 WHERE cat_org_id IS NULL
                    OR cat_org_id = '.$organizationId.'
              ORDER BY cat_sequence ASC, usf_sequence ASC';
        $userFieldsStatement = $this->mDb->query($sql);

        while ($row = $userFieldsStatement->fetch())
        {
            if (!array_key_exists($row['usf_name_intern'], $this->mProfileFields))
            {
                $this->mProfileFields[$row['usf_name_intern']] = new TableUserField($this->mDb);
            }
            $this->mProfileFields[$row['usf_name_intern']]->setArray($row);
        }
    }

    /**
     * Reads the user data of all profile fields out of database table @b adm_user_data
     * and adds an object for each field data to the @b mUserData array.
     * If profile fields structure wasn't read, this will be done before.
     * @param int $userId         The id of the user for which the user data should be read.
     * @param int $organizationId The id of the organization for which the profile fields
     *                            structure should be read if necessary.
     */
    public function readUserData($userId, $organizationId)
    {
        if (count($this->mProfileFields) === 0)
        {
            $this->readProfileFields($organizationId);
        }

        if ($userId > 0)
        {
            // remember the user
            $this->mUserId = $userId;

            // read all user data of user
            $sql = 'SELECT *
                      FROM '.TBL_USER_DATA.'
                INNER JOIN '.TBL_USER_FIELDS.'
                        ON usf_id = usd_usf_id
                     WHERE usd_usr_id = '.$userId;
            $userDataStatement = $this->mDb->query($sql);

            while ($row = $userDataStatement->fetch())
            {
                if (!array_key_exists($row['usd_usf_id'], $this->mUserData))
                {
                    $this->mUserData[$row['usd_usf_id']] = new TableAccess($this->mDb, TBL_USER_DATA, 'usd');
                }
                $this->mUserData[$row['usd_usf_id']]->setArray($row);
            }
        }
    }

    /**
     * save data of every user field
     * @param int $userId id is necessary if new user, that id was not known before
     */
    public function saveUserData($userId)
    {
        $this->mDb->startTransaction();

        foreach ($this->mUserData as $value)
        {
            // if new user than set user id
            if ($this->mUserId === 0)
            {
                $value->setValue('usd_usr_id', $userId);
            }

            // if value exists and new value is empty then delete entry
            if ($value->getValue('usd_id') > 0 && $value->getValue('usd_value') === '')
            {
                $value->delete();
            }
            else
            {
                $value->save();
            }
        }

        $this->columnsValueChanged = false;
        $this->mUserId = $userId;

        $this->mDb->endTransaction();
    }

    /**
     * set value for column usd_value of field
     * @param string $fieldNameIntern
     * @param mixed  $fieldValue
     * @return bool
     */
    public function setValue($fieldNameIntern, $fieldValue)
    {
        global $gPreferences;

        if (!array_key_exists($fieldNameIntern, $this->mProfileFields))
        {
            return false;
        }

        if ($fieldValue !== '')
        {
            switch ($this->mProfileFields[$fieldNameIntern]->getValue('usf_type'))
            {
                case 'CHECKBOX':
                    // Checkbox darf nur 0 oder 1 haben
                    if (!$this->noValueCheck && $fieldValue !== '0' && $fieldValue !== '1')
                    {
                        return false;
                    }
                    break;
                case 'DATE':
                    // Datum muss gueltig sein und formatiert werden
                    $date = DateTime::createFromFormat($gPreferences['system_date'], $fieldValue);
                    if ($date === false)
                    {
                        $date = DateTime::createFromFormat('Y-m-d', $fieldValue);
                        if ($date === false && !$this->noValueCheck)
                        {
                            return false;
                        }
                    }
                    else
                    {
                        $fieldValue = $date->format('Y-m-d');
                    }
                    break;
                case 'EMAIL':
                    // Email darf nur gueltige Zeichen enthalten und muss einem festen Schema entsprechen
                    if (!$this->noValueCheck && !strValidCharacters(admStrToLower($fieldValue), 'email'))
                    {
                        return false;
                    }
                    break;
                case 'NUMBER':
                    // A number must be numeric
                    if (!$this->noValueCheck && !is_numeric($fieldValue))
                    {
                        return false;
                    }

                    // numbers don't have leading zero
                    $fieldValue = preg_replace('/^0*(\d+)$/', '${1}', $fieldValue);
                    break;
                case 'DECIMAL':
                    // A decimal must be numeric
                    if (!$this->noValueCheck && !is_numeric(str_replace(',', '.', $fieldValue)))
                    {
                        return false;
                    }

                    // decimals don't have leading zero
                    $fieldValue = preg_replace('/^0*(\d+([,.]\d*)?)$/', '${1}', $fieldValue);
                    break;
                case 'PHONE':
                    // check phone number for valid characters
                    if (!$this->noValueCheck && !strValidCharacters($fieldValue, 'phone'))
                    {
                        return false;
                    }
                    break;
                case 'URL':
                    if (!$this->noValueCheck && admFuncCheckUrl($fieldValue) === false)
                    {
                        return false;
                    }
                    break;
            }
        }

        $usfId = $this->mProfileFields[$fieldNameIntern]->getValue('usf_id');

        if (!array_key_exists($usfId, $this->mUserData) && $fieldValue !== '')
        {
            $this->mUserData[$usfId] = new TableAccess($this->mDb, TBL_USER_DATA, 'usd');
            $this->mUserData[$usfId]->setValue('usd_usf_id', $usfId);
            $this->mUserData[$usfId]->setValue('usd_usr_id', $this->mUserId);
        }

        // first check if user has a data object for this field and then set value of this user field
        if (array_key_exists($usfId, $this->mUserData))
        {
            $valueChanged = $this->mUserData[$usfId]->setValue('usd_value', $fieldValue);

            if ($valueChanged && $this->mUserData[$usfId]->hasColumnsValueChanged())
            {
                $this->columnsValueChanged = true;

                return true;
            }
        }

        return false;
    }
}
