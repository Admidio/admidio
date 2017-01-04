<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class InventoryFields
 * @brief Reads the Inventory fields structure out of database and give access to it
 *
 * When an object is created than the actual inventory fields structure will
 * be read. In addition to this structure you can read the item values for
 * all fields if you call @c readItemData . If you read field values than
 * you will get the formated output. It's also possible to set item data and
 * save this data to the database
 */
class InventoryFields
{
    public $mInventoryFields = array(); ///< Array with all inventory fields objects
    public $mInventoryData = array();   ///< Array with all inventory data objects

    protected $mItemId;                 ///< ItemId of the current item of this object
    public $mDb;                        ///< db object must public because of session handling
    protected $noValueCheck;            ///< if true, than no value will be checked if method setValue is called
    public $columnsValueChanged;        ///< flag if a value of one field had changed

    /**
     * constructor that will initialize variables and read the inventory field structure
     * @param \Database $database       Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $organizationId The id of the organization for which the
     *                                  profile field structure should be read
     */
    public function __construct(&$database, $organizationId)
    {
        $this->mDb =& $database;
        $this->readInventoryFields($organizationId);
        $this->mItemId = 0;
        $this->noValueCheck = false;
        $this->columnsValueChanged = false;
    }

    /**
     * item data of all inventory fields will be initialized the fields array will not be renewed
     */
    public function clearInventoryData()
    {
        $this->mInventoryData = array();
        $this->mItemId = 0;
        $this->columnsValueChanged = false;
    }

    /**
     * returns for a fieldname intern (inf_name_intern) the value of the column from table adm_user_fields
     * @param string $fieldNameIntern Expects the @b inf_name_intern of table @b adm_user_fields
     * @param string $column The column name of @b adm_user_field for which you want the value
     * @param string $format Optional the format (is necessary for timestamps)
     * @return
     */
    public function getProperty($fieldNameIntern, $column, $format = '')
    {
        if(array_key_exists($fieldNameIntern, $this->mInventoryFields))
        {
            return $this->mInventoryFields[$fieldNameIntern]->getValue($column, $format);
        }

        // if id-field not exists then return zero
        if(strpos($column, '_id') > 0)
        {
            return 0;
        }
        return null;
    }

    /**
     * returns for field id (usf_id) the value of the column from table adm_user_fields
     * @param int    $fieldId Expects the @b usf_id of table @b adm_user_fields
     * @param string $column The column name of @b adm_user_field for which you want the value
     * @param string $format Optional the format (is necessary for timestamps)
     * @return
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach($this->mInventoryFields as $field)
        {
            if((int) $field->getValue('inf_id') === $fieldId)
            {
                return $field->getValue($column, $format);
            }
        }
        return null;
    }

    /**
     * Returns the value of the field in html format with consideration of all layout parameters
     * @param string     $fieldNameIntern Internal profile field name of the field that should be html formated
     * @param string|int $value           The value that should be formated must be commited so that layout is also possible for values that aren't stored in database
     * @param int        $value2          An optional parameter that is necessary for some special fields like email to commit the user id
     * @return string Returns an html formated string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value, $value2 = null)
    {
        global $gPreferences, $gL10n;

        if($value !== '' && array_key_exists($fieldNameIntern, $this->mInventoryFields))
        {
            // create html for each field type
            $htmlValue = $value;

            $infType = $this->mInventoryFields[$fieldNameIntern]->getValue('inf_type');

            switch ($infType)
            {
                case 'CHECKBOX':
                    if($value == '1')
                    {
                        $htmlValue = '<img src="'.THEME_URL.'/icons/checkbox_checked.gif" alt="on" />';
                    }
                    else
                    {
                        $htmlValue = '<img src="'.THEME_URL.'/icons/checkbox.gif" alt="off" />';
                    }
                    break;

                case 'EMAIL':
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
                            if($value2 === null)
                            {
                                $value2 = $this->mItemId;
                            }

                            $emailLink = ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?usr_id='. $value2;
                        }
                        if(strlen($value) > 30)
                        {
                            $htmlValue = '<a href="'.$emailLink.'" title="'.$value.'">'.substr($value, 0, 30).'...</a>';
                        }
                        else
                        {
                            $htmlValue = '<a href="'.$emailLink.'" title="'.$value.'" style="overflow: visible; display: inline;">'.$value.'</a>';
                        }
                    }
                    break;

                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    $arrListValues = explode("\r\n", $this->mInventoryFields[$fieldNameIntern]->getValue('inf_value_list', 'database'));
                    $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                    foreach($arrListValues as $key => &$listValue)
                    {
                        if($infType === 'RADIO_BUTTON')
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
                                    $listValueText  = $this->getValue('inf_name');
                                }

                                // if text is a translation-id then translate it
                                if(strpos($listValueText, '_') === 3)
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
                                        $listValue = '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/'.$listValueImage.'" title="'.$listValueText.'" alt="'.$listValueText.'" />';
                                    }
                                }
                                catch(AdmException $e)
                                {
                                    $e->showText();
                                    // => EXIT
                                }
                            }
                        }

                        // if text is a translation-id then translate it
                        if(strpos($listValue, '_') === 3)
                        {
                            $listValue = $gL10n->get(admStrToUpper($listValue));
                        }

                        // save values in new array that starts with key = 1
                        $arrListValuesWithKeys[++$key] = $listValue;
                    }
                    unset($listValue);
                    $htmlValue = $arrListValuesWithKeys[$value];
                    break;

                case 'URL':
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
                    break;

                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
            }

            // if field has url then create a link
            $infUrl = $this->mInventoryFields[$fieldNameIntern]->getValue('inf_url');
            if(strlen($infUrl) > 0)
            {
                if($fieldNameIntern === 'FACEBOOK' && is_numeric($value))
                {
                    // facebook has two different profile urls (id and facebook name),
                    // we could only store one way in database (facebook name) and the other (id) is defined here :)
                    $htmlValue = '<a href="https://www.facebook.com/profile.php?id='.$value.'" target="_blank">'.$htmlValue.'</a>';
                }
                else
                {
                    $htmlValue = '<a href="'.$infUrl.'" target="_blank">'.$htmlValue.'</a>';
                }

                // replace a variable in url with user value
                if(strpos($infUrl, '#user_content#') !== false)
                {
                    $htmlValue = preg_replace('/#user_content#/', $value, $htmlValue);
                }
            }
            $value = $htmlValue;
        }
        else
        {
            // special case for type CHECKBOX and no value is there, then show unchecked checkbox
            if(array_key_exists($fieldNameIntern, $this->mInventoryFields)
            && $this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') === 'CHECKBOX')
            {
                $value = '<img src="'.THEME_URL.'/icons/checkbox.gif" alt="off" />';
            }
        }
        return $value;
    }

    /**
     * Returns the user value for this column @n
     * format = 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function @n
     * format = 'html'  : returns the value in html-format if this is necessary for that field type @n
     * format = 'database' : returns the value that is stored in database with no format applied
     * @param string $fieldNameIntern Expects the @b inf_name_intern of table @b adm_user_fields
     * @param string $format Returns the field value in a special format @b text, @b html, @b database or datetime (detailed description in method description)
     * @return mixed
     */
    public function getValue($fieldNameIntern, $format = '')
    {
        global $gL10n, $gPreferences;
        $value = '';

        // exists a profile field with that name ?
        // then check if user has a data object for this field and then read value of this object
        if(array_key_exists($fieldNameIntern, $this->mInventoryFields)
        && array_key_exists($this->mInventoryFields[$fieldNameIntern]->getValue('inf_id'), $this->mInventoryData))
        {
            $invType = $this->mInventoryFields[$fieldNameIntern]->getValue('inf_type');

            $value = $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->getValue('ind_value', $format);

            if($format !== 'database')
            {
                if($invType === 'DATE' && $value !== '')
                {
                    // if no format or html is set then show date format from Admidio settings
                    if($format === '' || $format === 'html')
                    {
                        $dateFormat = $gPreferences['system_date'];
                    }
                    else
                    {
                        $dateFormat = $format;
                    }

                    // if date field then the current date format must be used
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if($date === false)
                    {
                        return $value;
                    }
                    $value = $date->format($dateFormat);
                }
                elseif($invType === 'DROPDOWN' || $invType === 'RADIO_BUTTON')
                {
                    // the value in db is only the position, now search for the text
                    if($value > 0 && $format !== 'html')
                    {
                        $arrListValues = $this->mInventoryFields[$fieldNameIntern]->getValue('inf_value_list');
                        $value = $arrListValues[$value];

                    }
                }
                elseif($fieldNameIntern === 'COUNTRY' && $value !== '')
                {
                    // read the language name of the country
                    $value = $gL10n->getCountryByCode($value);
                }
            }
        }

        // get html output for that field type and value
        if($format === 'html')
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
     * and adds an object for each field structure to the @b mInventoryFields array.
     * @param int $organizationId The id of the organization for which the profile fields
     *                            structure should be read.
     */
    public function readInventoryFields($organizationId)
    {
        // first initialize existing data
        $this->mInventoryFields = array();
        $this->clearInventoryData();

        // read all user fields and belonging category data of organization
        $sql = 'SELECT *
                  FROM '.TBL_INVENT_FIELDS.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = inf_cat_id
                 WHERE (  cat_org_id IS NULL
                       OR cat_org_id  = '.$organizationId.' )
              ORDER BY cat_sequence ASC, inf_sequence ASC';
        $usfStatement = $this->mDb->query($sql);

        while($row = $usfStatement->fetch())
        {
            if(!isset($this->mInventoryFields[$row['inf_name_intern']]))
            {
                $this->mInventoryFields[$row['inf_name_intern']] = new TableInventoryField($this->mDb);
            }
            $this->mInventoryFields[$row['inf_name_intern']]->setArray($row);
        }
    }

    /**
     * Reads the user data of all profile fields out of database table @b adm_user_data
     * and adds an object for each field data to the @b mInventoryData array.
     * If profile fields structure wasn't read, this will be done before.
     * @param int $itemId         The id of the user for which the user data should be read.
     * @param int $organizationId The id of the organization for which the profile fields
     *                            structure should be read if necessary.
     */
    public function readInventoryData($itemId, $organizationId)
    {
        if(count($this->mInventoryFields) === 0)
        {
            $this->readInventoryFields($organizationId);
        }

        if($itemId > 0)
        {
            // remember the user
            $this->mItemId = $itemId;

            // read all user data of user
            $sql = 'SELECT *
                      FROM '.TBL_INVENT_DATA.'
                INNER JOIN '.TBL_INVENT_FIELDS.'
                        ON inf_id = ind_inf_id
                     WHERE ind_itm_id = '.$itemId;
            $usdStatement = $this->mDb->query($sql);

            while($row = $usdStatement->fetch())
            {
                if(!isset($this->mInventoryData[$row['ind_inf_id']]))
                {
                    $this->mInventoryData[$row['ind_inf_id']] = new TableAccess($this->mDb, TBL_INVENT_DATA, 'ind');
                }
                $this->mInventoryData[$row['ind_inf_id']]->setArray($row);
            }
        }
    }

    /**
     * save data of every user field
     * @param int $itemId id is necessary if new user, that id was not known before
     */
    public function saveInventoryData($itemId)
    {
        $this->mDb->startTransaction();

        foreach($this->mInventoryData as $value)
        {
            // if new user than set user id
            if($this->mItemId === 0)
            {
                $value->setValue('ind_itm_id', $itemId);
            }

            // if value exists and new value is empty then delete entry
            if($value->getValue('ind_id') > 0 && $value->getValue('ind_value') === '')
            {
                $value->delete();
            }
            else
            {
                $value->save();
            }
        }

        $this->columnsValueChanged = false;
        $this->mItemId = $itemId;
        $this->mDb->endTransaction();
    }

    /**
     * set value for column usd_value of field
     * @param string $fieldNameIntern
     * @param        $fieldValue
     * @return bool
     */
    public function setValue($fieldNameIntern, $fieldValue)
    {
        global $gPreferences;
        $returnCode = false;

        if($fieldValue !== '')
        {
            switch ($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type'))
            {
                case 'CHECKBOX':
                    // Checkbox darf nur 1 oder 0 haben
                    if($fieldValue != 0 && $fieldValue != 1 && !$this->noValueCheck)
                    {
                        return false;
                    }
                    break;

                case 'DATE':
                    // Datum muss gueltig sein und formatiert werden
                    $date = DateTime::createFromFormat($gPreferences['system_date'], $fieldValue);
                    if($date === false)
                    {
                        if(!$this->noValueCheck)
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
                    $fieldValue = admStrToLower($fieldValue);
                    if (!strValidCharacters($fieldValue, 'email') && !$this->noValueCheck)
                    {
                        return false;
                    }
                    break;

                case 'NUMBER':
                    // A number must be numeric
                    if(!is_numeric($fieldValue) && !$this->noValueCheck)
                    {
                        return false;
                    }

                    // numbers don't have leading zero
                    $fieldValue = ltrim($fieldValue, '0');
                    break;

                case 'DECIMAL':
                    // A number must be numeric
                    if(!is_numeric(strtr($fieldValue, ',.', '00')) && !$this->noValueCheck)
                    {
                        return false;
                    }

                    // numbers don't have leading zero
                    $fieldValue = ltrim($fieldValue, '0');
                    break;

                case 'URL':
                    $fieldValue = admFuncCheckUrl($fieldValue);

                    if (!$this->noValueCheck && $fieldValue === false)
                    {
                        return false;
                    }
                    break;
            }
        }

        $infId = $this->mInventoryFields[$fieldNameIntern]->getValue('inf_id');

        // first check if user has a data object for this field and then set value of this user field
        if(array_key_exists($infId, $this->mInventoryData))
        {
            $returnCode = $this->mInventoryData[$infId]->setValue('ind_value', $fieldValue);
        }
        elseif(isset($this->mInventoryFields[$fieldNameIntern]) && $fieldValue !== '')
        {
            $this->mInventoryData[$infId] = new TableAccess($this->mDb, TBL_INVENT_DATA, 'ind');
            $this->mInventoryData[$infId]->setValue('ind_inf_id', $this->mInventoryFields[$fieldNameIntern]->getValue('inf_id'));
            $this->mInventoryData[$infId]->setValue('ind_itm_id', $this->mItemId);
            $returnCode = $this->mInventoryData[$infId]->setValue('ind_value', $fieldValue);
        }

        if($returnCode && $this->mInventoryData[$infId]->hasColumnsValueChanged())
        {
            $this->columnsValueChanged = true;
        }

        return $returnCode;
    }
}
