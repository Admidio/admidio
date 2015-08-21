<?php
/*****************************************************************************/
/** @class InventoryFields
 *  @brief Reads the Inventory fields structure out of database and give access to it
 *
 *  When an object is created than the actual inventory fields structure will
 *  be read. In addition to this structure you can read the item values for
 *  all fields if you call @c readItemData . If you read field values than
 *  you will get the formated output. It's also possible to set item data and
 *  save this data to the database
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class InventoryFields
{
    public $mInventoryFields = array(); ///< Array with all inventory fields objects
    public $mInventoryData = array();   ///< Array with all inventory data objects

    protected $mItemId;                 ///< ItemId of the current item of this object
    public $mDb;                        ///< db object must public because of session handling
    protected $noValueCheck;            ///< if true, than no value will be checked if method setValue is called
    public $columnsValueChanged;        ///< flag if a value of one field had changed

    /** constructor that will initialize variables and read the inventory field structure
     *  @param $db Database object (should be @b $gDb)
     *  @param $organizationId The id of the organization for which the
     *                         profile field structure should be read
     */
    public function __construct(&$db, $organizationId)
    {
        $this->mDb =& $db;
        $this->readInventoryFields($organizationId);
        $this->mItemId = 0;
        $this->noValueCheck = false;
        $this->columnsValueChanged = false;
    }

    /** item data of all inventory fields will be initialized
     *  the fields array will not be renewed
     */
    public function clearInventoryData()
    {
        $this->mInventoryData = array();
        $this->mItemId = 0;
        $this->columnsValueChanged = false;
    }

    /** returns for a fieldname intern (inf_name_intern) the value of the column from table adm_user_fields
     *  @param $fieldNameIntern Expects the @b inf_name_intern of table @b adm_user_fields
     *  @param $column The column name of @b adm_user_field for which you want the value
     *  @param $format Optional the format (is necessary for timestamps)
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

    /** returns for field id (usf_id) the value of the column from table adm_user_fields
     *  @param $fieldId Expects the @b usf_id of table @b adm_user_fields
     *  @param $column The column name of @b adm_user_field for which you want the value
     *  @param $format Optional the format (is necessary for timestamps)
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach($this->mInventoryFields as $field)
        {
            if($field->getValue('inf_id') == $fieldId)
            {
                return $field->getValue($column, $format);
            }
        }
        return null;
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
        && array_key_exists($fieldNameIntern, $this->mInventoryFields) == true)
        {
            // create html for each field type
            $htmlValue = $value;

            if($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'CHECKBOX')
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
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'EMAIL')
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
                            $value2 = $this->mItemId;
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
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'DROPDOWN'
            || $this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'RADIO_BUTTON')
            {
                $arrListValues = explode("\r\n", $this->mInventoryFields[$fieldNameIntern]->getValue('inf_value_list', 'database'));
                $arrListValuesWithKeys = array();     // array with list values and keys that represents the internal value

                foreach($arrListValues as $key => &$listValue)
                {
                    if($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'RADIO_BUTTON')
                    {
                        // if value is imagefile or imageurl then show image
                        if(strpos(mb_strtolower($listValue), '.png') > 0 || strpos(mb_strtolower($listValue), '.jpg') > 0)
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
                            if(strpos($listValueText, '_') == 3)
                            {
                                $listValueText = $gL10n->get(mb_strtoupper($listValueText));
                            }

                            try
                            {
                                // create html for optionbox entry
                                if(strpos(mb_strtolower($listValueImage), 'http') === 0 && strValidCharacters($listValueImage, 'url'))
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
                        $listValue = $gL10n->get(mb_strtoupper($listValue));
                    }

                    // save values in new array that starts with key = 1
                    $arrListValuesWithKeys[++$key] = $listValue;
                }
                $htmlValue = $arrListValuesWithKeys[$value];
            }
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'URL')
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
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'TEXT_BIG')
            {
                $htmlValue = nl2br($value);
            }

            // if field has url then create a link
            if(strlen($this->mInventoryFields[$fieldNameIntern]->getValue('inf_url')))
            {
                if($fieldNameIntern == 'FACEBOOK' && is_numeric($value))
                {
                    // facebook has two different profile urls (id and facebook name),
                    // we could only store one way in database (facebook name) and the other (id) is defined here :)
                    $htmlValue = '<a href="http://www.facebook.com/profile.php?id='.$value.'" target="_blank">'.$htmlValue.'</a>';
                }
                else
                {
                    $htmlValue = '<a href="'.$this->mInventoryFields[$fieldNameIntern]->getValue('inf_url').'" target="_blank">'.$htmlValue.'</a>';
                }

                // replace a variable in url with user value
                if(strpos($this->mInventoryFields[$fieldNameIntern]->getValue('inf_url'), '%user_content%') !== false)
                {
                    $htmlValue = preg_replace('/%user_content%/', $value, $htmlValue);

                }
            }
            $value = $htmlValue;
        }
        else
        {
            // special case for type CHECKBOX and no value is there, then show unchecked checkbox
            if(array_key_exists($fieldNameIntern, $this->mInventoryFields) == true
            && $this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'CHECKBOX')
            {
                $value = '<img src="'.THEME_PATH.'/icons/checkbox.gif" alt="off" />';
            }
        }
        return $value;
    }

    /** Returns the user value for this column @n
     *  format = 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function @n
     *  format = 'html'  : returns the value in html-format if this is necessary for that field type @n
     *  format = 'database' : returns the value that is stored in database with no format applied
     *  @param $fieldNameIntern Expects the @b inf_name_intern of table @b adm_user_fields
     *  @param $format Returns the field value in a special format @b text, @b html, @b database or datetime (detailed description in method description)
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
            $value = $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->getValue('ind_value', $format);

            if($format != 'database')
            {
                if($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'DATE' && $value !== '')
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
                elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'DROPDOWN'
                    || $this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'RADIO_BUTTON')
                {
                    // the value in db is only the position, now search for the text
                    if($value > 0 && $format != 'html')
                    {
                        $arrListValues = $this->mInventoryFields[$fieldNameIntern]->getValue('inf_value_list');
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
     *  and adds an object for each field structure to the @b mInventoryFields array.
     *  @param $organizationId The id of the organization for which the profile fields
     *                         structure should be read.
     */
    public function readInventoryFields($organizationId)
    {
        // first initialize existing data
        $this->mInventoryFields = array();
        $this->clearInventoryData();

        // read all user fields and belonging category data of organization
        $sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_INVENT_FIELDS. '
                 WHERE inf_cat_id = cat_id
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = '.$organizationId.' )
                 ORDER BY cat_sequence ASC, inf_sequence ASC ';
        $usfResult = $this->mDb->query($sql);

        while($row = $this->mDb->fetch_array($usfResult))
        {
            if(isset($this->mInventoryFields[$row['inf_name_intern']]) == false)
            {
                $this->mInventoryFields[$row['inf_name_intern']] = new TableInventoryField($this->mDb);
            }
            $this->mInventoryFields[$row['inf_name_intern']]->setArray($row);
        }
    }

    /** Reads the user data of all profile fields out of database table @b adm_user_data
     *  and adds an object for each field data to the @b mInventoryData array.
     *  If profile fields structure wasn't read, this will be done before.
     *  @param $itemId         The id of the user for which the user data should be read.
     *  @param $organizationId The id of the organization for which the profile fields
     *                         structure should be read if necessary.
     */
    public function readInventoryData($itemId, $organizationId)
    {
        if(count($this->mInventoryFields) == 0)
        {
            $this->readInventoryFields($organizationId);
        }

        if($itemId > 0)
        {
            // remember the user
            $this->mItemId = $itemId;

            // read all user data of user
            $sql = 'SELECT * FROM '.TBL_INVENT_DATA.', '. TBL_INVENT_FIELDS. '
                     WHERE ind_inf_id = inf_id
                       AND ind_itm_id = '.$itemId;
            $usdResult = $this->mDb->query($sql);

            while($row = $this->mDb->fetch_array($usdResult))
            {
                if(isset($this->mInventoryData[$row['ind_inf_id']]) == false)
                {
                    $this->mInventoryData[$row['ind_inf_id']] = new TableAccess($this->mDb, TBL_INVENT_DATA, 'ind');
                }
                $this->mInventoryData[$row['ind_inf_id']]->setArray($row);
            }
        }
    }

    // save data of every user field
    // itemId : id is necessary if new user, that id was not known before
    public function saveInventoryData($itemId)
    {
        $this->mDb->startTransaction();

        foreach($this->mInventoryData as $key => $value)
        {
            // if new user than set user id
            if($this->mItemId == 0)
            {
                $this->mInventoryData[$key]->setValue('ind_itm_id', $itemId);
            }

            // if value exists and new value is empty then delete entry
            if($this->mInventoryData[$key]->getValue('ind_id') > 0
            && strlen($this->mInventoryData[$key]->getValue('ind_value')) == 0)
            {
                $this->mInventoryData[$key]->delete();
            }
            else
            {
                $this->mInventoryData[$key]->save();
            }
        }

        $this->columnsValueChanged = false;
        $this->mItemId = $itemId;
        $this->mDb->endTransaction();
    }

    // set value for column usd_value of field
    public function setValue($fieldNameIntern, $fieldValue)
    {
        global $gPreferences;
        $returnCode = false;

        if($fieldValue !== '')
        {
            if($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'CHECKBOX')
            {
                // Checkbox darf nur 1 oder 0 haben
                if($fieldValue != 0 && $fieldValue != 1 && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'DATE')
            {
                // Datum muss gueltig sein und formatiert werden
                $date = new DateTimeExtended($fieldValue, $gPreferences['system_date']);
                if(!$date->isValid())
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
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'EMAIL')
            {
                // Email darf nur gueltige Zeichen enthalten und muss einem festen Schema entsprechen
                $fieldValue = mb_strtolower($fieldValue);
                if (!strValidCharacters($fieldValue, 'email') && $this->noValueCheck != true)
                {
                    return false;
                }
            }
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'NUMBER')
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
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'DECIMAL')
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
            elseif($this->mInventoryFields[$fieldNameIntern]->getValue('inf_type') == 'URL')
            {
                // Homepage darf nur gueltige Zeichen enthalten
                if (!strValidCharacters($fieldValue, 'url') && $this->noValueCheck != true)
                {
                    return false;
                }
                // Homepage noch mit http vorbelegen
                if(strpos(mb_strtolower($fieldValue), 'http://')  === false
                && strpos(mb_strtolower($fieldValue), 'https://') === false)
                {
                    $fieldValue = 'http://'. $fieldValue;
                }
            }

        }

        // first check if user has a data object for this field and then set value of this user field
        if(array_key_exists($this->mInventoryFields[$fieldNameIntern]->getValue('inf_id'), $this->mInventoryData))
        {
            $returnCode = $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->setValue('ind_value', $fieldValue);
        }
        elseif(isset($this->mInventoryFields[$fieldNameIntern]) == true && $fieldValue !== '')
        {
            $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')] = new TableAccess($this->mDb, TBL_INVENT_DATA, 'ind');
            $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->setValue('ind_inf_id', $this->mInventoryFields[$fieldNameIntern]->getValue('inf_id'));
            $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->setValue('ind_itm_id', $this->mItemId);
            $returnCode = $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->setValue('ind_value', $fieldValue);
        }

        if($returnCode && $this->mInventoryData[$this->mInventoryFields[$fieldNameIntern]->getValue('inf_id')]->hasColumnsValueChanged())
        {
            $this->columnsValueChanged = true;
        }

        return $returnCode;
    }
}
?>
