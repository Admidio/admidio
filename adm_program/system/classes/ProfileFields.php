<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Reads the user fields structure out of database and give access to it
 *
 * When an object is created than the actual profile fields structure will
 * be read. In addition to this structure you can read the user values for
 * all fields if you call @c readUserData . If you read field values than
 * you will get the formatted output. It's also possible to set user data and
 * save this data to the database
 */
class ProfileFields
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected $db;
    /**
     * @var array<string,TableUserField> Array with all user fields objects
     */
    protected $mProfileFields = array();
    /**
     * @var array<int,TableAccess> Array with all user data objects
     */
    protected $mUserData = array();
    /**
     * @var int UserId of the current user of this object
     */
    protected $mUserId = 0;
    /**
     * @var string UUID of the current user of this object
     */
    protected $mUserUuid = '';
    /**
     * @var bool if true, no value will be checked if method setValue is called
     */
    protected $noValueCheck = false;
    /**
     * @var bool flag if a value of one field had changed
     */
    protected $columnsValueChanged = false;

    /**
     * constructor that will initialize variables and read the profile field structure
     * @param Database $database       Database object (should be **$gDb**)
     * @param int      $organizationId The id of the organization for which the profile field structure should be read
     */
    public function __construct(Database $database, int $organizationId)
    {
        $this->db =& $database;
        $this->readProfileFields($organizationId);
    }

    /**
     * A wakeup add the current database object to this class
     */
    public function __wakeup()
    {
        global $gDb;

        if ($gDb instanceof Database) {
            $this->db = $gDb;
        }
    }

    /**
     * user data of all profile fields will be initialized
     * the fields array will not be renewed
     */
    public function clearUserData()
    {
        $this->mUserData = array();
        $this->mUserId = 0;
        $this->mUserUuid = '';
        $this->columnsValueChanged = false;
    }

    /**
     * Delete all data of the user in table adm_user_data
     * @return void
     */
    public function deleteUserData()
    {
        $this->db->startTransaction();

        // delete every entry from adm_users_data
        foreach ($this->mUserData as $field) {
            $field->delete();
        }

        $this->mUserData = array();

        $this->db->endTransaction();
    }

    /**
     * Create an array with all usf_id of profile fields that are editable to the current user in consideration
     * if the current user is allowed to edit the profile or not.
     * @param bool $allowedToEditProfile Flag if the user is allowed to edit the profile.
     * @return array Returns an array with all usf_id of profile fields that are editable to the current user.
     */
    public function getEditableArray(bool $allowedToEditProfile = false)
    {
        $editableFields = array();

        foreach ($this->mProfileFields as $field) {
            if($this->isEditable($field->getValue('usf_name_intern'), $allowedToEditProfile)) {
                $editableFields[] = $field->getValue('usf_id');
            }
        }

        return $editableFields;
    }

    /**
     * @return array<string,TableUserField>
     */
    public function getProfileFields()
    {
        return $this->mProfileFields;
    }

    /**
     * Returns for a field name intern (usf_name_intern) the value of the column from table adm_user_fields.
     * Optional a format could be set.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of table **adm_user_fields**
     * @param string $column          The column name of **adm_user_field** for which you want the value
     * @param string $format    For column **usf_value_list** the following format is accepted:
     *                           * **database** returns database value of **usf_value_list** without any transformations
     *                           * **text** extract only text from **usf_value_list**, image infos will be ignored
     *                           * For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**
     * @return string Returns for the profile field with the given uuid the value.
     */
    public function getProperty(string $fieldNameIntern, string $column, string $format = '')
    {
        if (array_key_exists($fieldNameIntern, $this->mProfileFields)) {
            return $this->mProfileFields[$fieldNameIntern]->getValue($column, $format);
        }

        // if id-field not exists then return zero
        if (str_contains($column, '_id')) {
            return 0;
        }
        return '';
    }

    /**
     * Returns for field id (usf_id) the value of the column from table adm_user_fields.
     * Optional a format could be set.
     * @param int    $fieldId Expects the **usf_id** of table **adm_user_fields**
     * @param string $column  The column name of **adm_user_field** for which you want the value
     * @param string $format    For column **usf_value_list** the following format is accepted:
     *                           * **database** returns database value of **usf_value_list** without any transformations
     *                           * **text** extract only text from **usf_value_list**, image infos will be ignored
     *                           * For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**
     * @return string Returns for the profile field with the given uuid the value.
     */
    public function getPropertyById(int $fieldId, string $column, string $format = '')
    {
        foreach ($this->mProfileFields as $field) {
            if ((int) $field->getValue('usf_id') === $fieldId) {
                return $field->getValue($column, $format);
            }
        }

        return '';
    }

    /**
     * Returns for field uuid (usf_uuid) the value of the column from table adm_user_fields.
     * Optional a format could be set.
     * @param string $fieldUuid Expects the **usf_id** of table **adm_user_fields**
     * @param string $column    The column name of **adm_user_field** for which you want the value
     * @param string $format    For column **usf_value_list** the following format is accepted:
     *                           * **database** returns database value of **usf_value_list** without any transformations
     *                           * **text** extract only text from **usf_value_list**, image infos will be ignored
     *                           * For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**
     * @return string Returns for the profile field with the given uuid the value.
     */
    public function getPropertyByUuid(string $fieldUuid, string $column, string $format = '')
    {
        foreach ($this->mProfileFields as $field) {
            if ($field->getValue('usf_uuid') === $fieldUuid) {
                return $field->getValue($column, $format);
            }
        }

        return '';
    }

    /**
     * Returns the value of the field in html format with consideration of all layout parameters
     * @param string     $fieldNameIntern Internal profile field name of the field that should be html formatted
     * @param string|int $value           The value that should be formatted must be committed so that layout
     *                                    is also possible for values that aren't stored in database
     * @param string     $value2          An optional parameter that is necessary for some special fields like email to commit the user uuid
     * @return string Returns a html formatted string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value, $value2 = '')
    {
        global $gSettingsManager, $gL10n;

        if (!array_key_exists($fieldNameIntern, $this->mProfileFields)) {
            return $value;
        }

        // if value is empty or null, then do nothing
        if ($value != '') {
            // create html for each field type
            $value = SecurityUtils::encodeHTML(StringUtils::strStripTags($value));
            $htmlValue = $value;

            $usfType = $this->mProfileFields[$fieldNameIntern]->getValue('usf_type');
            switch ($usfType) {
                case 'CHECKBOX':
                    if ($value == 1) {
                        $htmlValue = '<span class="fa-stack">
                            <i class="fas fa-square-full fa-stack-1x"></i>
                            <i class="fas fa-check-square fa-stack-1x fa-inverse"></i>
                        </span>';
                    } else {
                        $htmlValue = '<span class="fa-stack">
                            <i class="fas fa-square-full fa-stack-1x"></i>
                            <i class="fas fa-square fa-stack-1x fa-inverse"></i>
                        </span>';
                    }
                    break;
                case 'DATE':
                    if ($value !== '') {
                        // date must be formatted
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof DateTime) {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                    break;
                case 'EMAIL':
                    // the value in db is only the position, now search for the text
                    if ($value !== '') {
                        if (!$gSettingsManager->getBool('enable_mail_module')) {
                            $emailLink = 'mailto:' . $value;
                        } else {
                            // set value2 to user id because we need a second parameter in the link to mail module
                            if ($value2 === '') {
                                $value2 = $this->mUserUuid;
                            }

                            $emailLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $value2));
                        }
                        if (strlen($value) > 30) {
                            $htmlValue = '<a href="' . $emailLink . '" title="' . $value . '">' . substr($value, 0, 30) . '...</a>';
                        } else {
                            $htmlValue = '<a href="' . $emailLink . '" title="' . $value . '" style="overflow: visible; display: inline;">' . $value . '</a>';
                        }
                    }
                    break;
                case 'DROPDOWN': // fallthrough
                case 'RADIO_BUTTON':
                    $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                    // first replace windows new line with unix new line and then create an array
                    $valueFormatted = str_replace("\r\n", "\n", $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list', 'database'));
                    $arrListValues = explode("\n", $valueFormatted);

                    foreach ($arrListValues as $index => $listValue) {
                        // if value is imagefile or imageurl then show image
                        if ($usfType === 'RADIO_BUTTON'
                            && (Image::isFontAwesomeIcon($listValue)
                                || StringUtils::strContains($listValue, '.png', false)
                                || StringUtils::strContains($listValue, '.jpg', false))) { // TODO: simplify check for images
                            // if there is imagefile and text separated by | then explode them
                            if (str_contains($listValue, '|')) {
                                list($listValueImage, $listValueText) = explode('|', $listValue);
                            } else {
                                $listValueImage = $listValue;
                                $listValueText  = $this->getValue('usf_name');
                            }

                            // if text is a translation-id then translate it
                            $listValueText = Language::translateIfTranslationStrId($listValueText);

                            // get html snippet with image tag
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }

                        // if text is a translation-id then translate it
                        $listValue = Language::translateIfTranslationStrId($listValue);

                        // save values in new array that starts with key = 1
                        $arrListValuesWithKeys[++$index] = $listValue;
                    }

                    if(count($arrListValuesWithKeys) > 0 && !empty($value)) {
                        if(array_key_exists($value, $arrListValuesWithKeys)) {
                            $htmlValue = $arrListValuesWithKeys[$value];
                        } else {
                            $htmlValue = '<i>'.$gL10n->get('SYS_DELETED_ENTRY').'</i>';
                        }
                    } else {
                        $htmlValue = '';
                    }
                    break;
                case 'PHONE':
                    if ($value !== '') {
                        $htmlValue = '<a href="tel:' . str_replace(array('-', '/', ' ', '(', ')'), '', $value) . '">' . $value . '</a>';
                    }
                    break;
                case 'URL':
                    if ($value !== '') {
                        $displayValue = $value;

                        // trim "http://", "https://", "//"
                        if (str_contains($displayValue, '//')) {
                            $displayValue = substr($displayValue, strpos($displayValue, '//') + 2);
                        }
                        // trim after the 35th char
                        if (strlen($value) > 35) {
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
            if ($usfUrl !== '') {
                if ($fieldNameIntern === 'FACEBOOK' && is_numeric($value)) {
                    // facebook has two different profile urls (id and facebook name),
                    // we could only store one way in database (facebook name) and the other (id) is defined here
                    $htmlValue = '<a href="' . SecurityUtils::encodeUrl('https://www.facebook.com/profile.php', array('id' => $value)) . '" target="_blank">' . $htmlValue . '</a>';
                } else {
                    $htmlValue = '<a href="' . $usfUrl . '" target="_blank">' . $htmlValue . '</a>';
                }

                // replace a variable in url with user value
                if (str_contains($usfUrl, '#user_content#')) {
                    $htmlValue = str_replace('#user_content#', $value, $htmlValue);
                }
            }
            $value = $htmlValue;
        }
        // special case for type CHECKBOX and no value is there, then show unchecked checkbox
        else {
            if ($this->mProfileFields[$fieldNameIntern]->getValue('usf_type') === 'CHECKBOX') {
                $value = '<span class="fa-stack">
                    <i class="fas fa-square-full fa-stack-1x"></i>
                    <i class="fas fa-square fa-stack-1x fa-inverse"></i>
                </span>';

                // if field has url then create a link
                $usfUrl = $this->mProfileFields[$fieldNameIntern]->getValue('usf_url');
                if ($usfUrl !== '') {
                    $value = '<a href="' . $usfUrl . '" target="_blank">' . $value . '</a>';
                }
            }
        }

        return $value;
    }

    /**
     * Returns the user value for this column. Within a dropdown or radio button field the format could be set to **text** so
     * an icon will not be shown.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field whose value should be read
     * @param string $format          Returns the field value in a special format e.g. **html**, **database**
     *                                or datetime (detailed description in method description)
     *                                * 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function
     *                                * 'html'  : returns the value in html-format if this is necessary for that field type.
     *                                * 'database' : returns the value that is stored in database with no format applied
     * @return string|int|bool Returns the value for the column.
     */
    public function getValue(string $fieldNameIntern, string $format = '')
    {
        $value = '';

        // exists a profile field with that name ?
        // then check if user has a data object for this field and then read value of this object
        if (array_key_exists($fieldNameIntern, $this->mProfileFields)
        &&  array_key_exists($this->mProfileFields[$fieldNameIntern]->getValue('usf_id'), $this->mUserData)) {
            $value = $this->mUserData[$this->mProfileFields[$fieldNameIntern]->getValue('usf_id')]->getValue('usd_value', $format);

            if ($format === 'database') {
                return $value;
            }

            if ($fieldNameIntern === 'COUNTRY') {
                if ($value !== '') {
                    // read the language name of the country
                    $value = $GLOBALS['gL10n']->getCountryName($value);
                }
            } else {
                switch ($this->mProfileFields[$fieldNameIntern]->getValue('usf_type')) {
                    case 'DATE':
                        if ($value !== '') {
                            // if date field then the current date format must be used
                            $date = DateTime::createFromFormat('Y-m-d', $value);
                            if ($date === false) {
                                return $value;
                            }

                            // if no format or html is set then show date format from Admidio settings
                            if ($format === '' || $format === 'html') {
                                $value = $date->format($GLOBALS['gSettingsManager']->getString('system_date'));
                            } else {
                                $value = $date->format($format);
                            }
                        }
                        break;
                    case 'DROPDOWN': // fallthrough
                    case 'RADIO_BUTTON':
                        // the value in db is only the position, now search for the text
                        if ($value > 0 && $format !== 'html') {
                            $arrListValues = $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list', $format);
                            $value = $arrListValues[$value];
                        }
                        break;
                }
            }
        }

        // get html output for that field type and value
        if ($format === 'html') {
            // decode html special chars because getHtmlValue encodes them again
            $value = $this->getHtmlValue($fieldNameIntern, htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5));
        }

        return $value;
    }

    /**
     * Create an array with all usf_id of profile fields that are visible to the current user in consideration
     * if the current user is allowed to edit the profile or not.
     * @param bool $allowedToEditProfile Flag if the user is allowed to edit the profile.
     * @return array Returns an array with all usf_id of profile fields that are visible to the current user.
     */
    public function getVisibleArray(bool $allowedToEditProfile = false): array
    {
        $visibleFields = array();

        foreach ($this->mProfileFields as $field) {
            if($this->isVisible($field->getValue('usf_name_intern'), $allowedToEditProfile)) {
                $visibleFields[] = $field->getValue('usf_id');
            }
        }

        return $visibleFields;
    }

    /**
     * returns true if a column of user table or profile fields has changed
     * @return bool
     */
    public function hasColumnsValueChanged(): bool
    {
        return $this->columnsValueChanged;
    }

    /**
     * Checks if a profile field must have a value. This check is done against the configuration of that
     * profile field. It is possible that the input is always required or only in a registration form or only in
     * the own profile. Another case is always a required value except within a registration form.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @param int $userId Optional the ID of the user for which the required profile field should be checked.
     * @param bool $registration Set to **true** if the check should be done for a registration form. The default is **false**
     * @return bool Returns true if the profile field has a required input.
     */
    public function hasRequiredInput(string $fieldNameIntern, int $userId = 0, bool $registration = false): bool
    {
        return $this->mProfileFields[$fieldNameIntern]->hasRequiredInput($userId, $registration);
    }

    /**
     * This method checks if the current user is allowed to edit this profile field of $fieldNameIntern
     * within the context of the user in this object.
     * !!! NOTE that this method ONLY checks if it could be possible to edit this field. There MUST be
     * another check if the current user is allowed to edit the user profile generally.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @param bool   $allowedToEditProfile Set to **true** if the current user has the right to edit the profile
     *                                    in which context the right should be checked. This param must not be
     *                                    set if you are not in a user context.
     * @return bool Return true if the current user is allowed to view this profile field
     */
    public function isEditable(string $fieldNameIntern, bool $allowedToEditProfile): bool
    {
        return $this->isVisible($fieldNameIntern, $allowedToEditProfile)
        && ($GLOBALS['gCurrentUser']->editUsers() || $this->mProfileFields[$fieldNameIntern]->getValue('usf_disabled') == 0);
    }

    /**
     * This method checks if the current user is allowed to view this profile field of $fieldNameIntern
     * within the context of the user in this object. If no context is set than we only check if the
     * current user has the right to view the category of the profile field.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @param bool   $allowedToEditProfile Set to **true** if the current user has the right to edit the profile
     *                                    in which context the right should be checked. This param must not be
     *                                    set if you are not in a user context.
     * @return bool Return true if the current user is allowed to view this profile field
     */
    public function isVisible(string $fieldNameIntern, bool $allowedToEditProfile = false): bool
    {
        if(!array_key_exists($fieldNameIntern, $this->mProfileFields)) {
            return false;
        }

        // check a special case where the field is only visible for users who can edit the profile but must therefore
        // have the right to edit all users
        if (!$GLOBALS['gCurrentUser']->editUsers()
        && $this->mProfileFields[$fieldNameIntern]->getValue('usf_disabled') == 1
        && $this->mProfileFields[$fieldNameIntern]->getValue('usf_hidden') == 1) {
            return false;
        }

        // check if the current user could view the category of the profile field
        // if it's the own profile than we check if user could edit his profile and if so he could view all fields
        // check if the profile field is only visible for users that could edit this
        return ($this->mProfileFields[$fieldNameIntern]->isVisible() || $GLOBALS['gCurrentUserId'] === $this->mUserId)
        && ($allowedToEditProfile || $this->mProfileFields[$fieldNameIntern]->getValue('usf_hidden') == 0);
    }

    /**
     * If this method is called than all further calls of method **setValue** will not check the values.
     * The values will be stored in database without any inspections !
     */
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }

    /**
     * Reads the profile fields structure out of database table **adm_user_fields**
     * and adds an object for each field structure to the **mProfileFields** array.
     * @param int $organizationId The id of the organization for which the profile fields
     *                            structure should be read.
     */
    public function readProfileFields(int $organizationId)
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
                    OR cat_org_id = ? -- $organizationId
              ORDER BY cat_sequence, usf_sequence';
        $userFieldsStatement = $this->db->queryPrepared($sql, array($organizationId));

        while ($row = $userFieldsStatement->fetch()) {
            if (!array_key_exists($row['usf_name_intern'], $this->mProfileFields)) {
                $this->mProfileFields[$row['usf_name_intern']] = new TableUserField($this->db);
            }
            $this->mProfileFields[$row['usf_name_intern']]->setArray($row);
        }
    }

    /**
     * Reads the user data of all profile fields out of database table **adm_user_data**
     * and adds an object for each field data to the **mUserData** array.
     * If profile fields structure wasn't read, this will be done before.
     * @param int $userId         The id of the user for which the user data should be read.
     * @param int $organizationId The id of the organization for which the profile fields
     *                            structure should be read if necessary.
     */
    public function readUserData(int $userId, int $organizationId)
    {
        if (count($this->mProfileFields) === 0) {
            $this->readProfileFields($organizationId);
        }

        if ($userId > 0) {
            // remember the user
            $this->mUserId = $userId;

            // read all user data of user
            $sql = 'SELECT *
                      FROM '.TBL_USERS.'
                INNER JOIN '.TBL_USER_DATA.'
                        ON usd_usr_id = usr_id
                INNER JOIN '.TBL_USER_FIELDS.'
                        ON usf_id = usd_usf_id
                     WHERE usr_id = ? -- $userId';
            $userDataStatement = $this->db->queryPrepared($sql, array($userId));

            while ($row = $userDataStatement->fetch()) {
                if (!array_key_exists($row['usd_usf_id'], $this->mUserData)) {
                    $this->mUserData[$row['usd_usf_id']] = new TableAccess($this->db, TBL_USER_DATA, 'usd');
                }
                $this->mUserData[$row['usd_usf_id']]->setArray($row);
                if (isset($row['usr_uuid'])) {
                    $this->mUserUuid = $row['usr_uuid'];
                }
            }
        }
    }

    /**
     * save data of every user field
     * @param int $userId id is necessary if new user, that id was not known before
     */
    public function saveUserData(int $userId)
    {
        $this->db->startTransaction();

        foreach ($this->mUserData as $value) {
            // if new user than set user id
            if ($this->mUserId === 0) {
                $value->setValue('usd_usr_id', $userId);
            }

            // if value exists and new value is empty then delete entry
            if ($value->getValue('usd_id') > 0 && $value->getValue('usd_value') === '') {
                $value->delete();
            } else {
                $value->save();
            }
        }

        $this->columnsValueChanged = false;
        $this->mUserId = $userId;

        $this->db->endTransaction();
    }

    /**
     * Set a value for a profile field. The value will be checked against typical conditions of the data type and
     * also against the custom regex if this is set. If an invalid value is set an AdmException will be thrown.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should get a new value.
     * @param mixed  $fieldValue      The new value that should be stored in the profile field.
     * @param bool   $checkValue      The value will be checked if it's valid. If set to **false** than the value will
     *                                not be checked.
     * @throws AdmException If an invalid value should be set.
     *                      exception->text contains a string with the reason why the login failed.
     * @return bool Return true if the value is valid and would be accepted otherwise return false or an exception.
     */
    public function setValue(string $fieldNameIntern, $fieldValue, $checkValue = true): bool
    {
        global $gSettingsManager, $gL10n;

        if (!array_key_exists($fieldNameIntern, $this->mProfileFields)) {
            throw new AdmException('Profile field ' . $fieldNameIntern . ' doesn\'t exists!');
        }

        if (!empty($fieldValue) && $checkValue) {
            switch ($this->mProfileFields[$fieldNameIntern]->getValue('usf_type')) {
                case 'CHECKBOX':
                    // Checkbox may only have 0 or 1
                    if (!$this->noValueCheck && $fieldValue !== '0' && $fieldValue !== '1') {
                        throw new AdmException($gL10n->get('SYS_FIELD_INVALID_INPUT', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                    }
                    break;
                case 'DATE':
                    // Date must be valid and formatted
                    $date = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $fieldValue);
                    if ($date === false) {
                        $date = DateTime::createFromFormat('Y-m-d', $fieldValue);
                        if ($date === false && !$this->noValueCheck) {
                            throw new AdmException($gL10n->get('SYS_DATE_INVALID', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'), $gSettingsManager->getString('system_date'))));
                        }
                    } else {
                        $fieldValue = $date->format('Y-m-d');
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    if ($fieldValue !== 0) { // 0 is the empty value for radio button
                        if (!$this->noValueCheck && !is_numeric($fieldValue)) {
                            throw new AdmException($gL10n->get('SYS_FIELD_INVALID_INPUT', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                        } elseif (!array_key_exists($fieldValue, $this->mProfileFields[$fieldNameIntern]->getValue('usf_value_list'))) {
                            throw new AdmException($gL10n->get('SYS_FIELD_INVALID_INPUT', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                        }
                    }
                    break;
                case 'EMAIL':
                    // Email may only contain valid characters and must conform to a fixed scheme
                    if (!$this->noValueCheck && !StringUtils::strValidCharacters($fieldValue, 'email')) {
                        throw new AdmException($gL10n->get('SYS_EMAIL_INVALID', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                    }
                    break;
                case 'NUMBER':
                    // A number must be numeric
                    if (!$this->noValueCheck && !is_numeric($fieldValue)) {
                        throw new AdmException($gL10n->get('PRO_FIELD_NUMERIC', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                    }

                    // numbers don't have leading zero
                    $fieldValue = preg_replace('/^0*(\d+)$/', '${1}', $fieldValue);
                    break;
                case 'DECIMAL':
                    // A decimal must be numeric
                    if (!$this->noValueCheck && !is_numeric(str_replace(',', '.', $fieldValue))) {
                        throw new AdmException($gL10n->get('PRO_FIELD_NUMERIC', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                    }

                    // decimals don't have leading zero
                    $fieldValue = preg_replace('/^0*(\d+([,.]\d*)?)$/', '${1}', $fieldValue);
                    break;
                case 'PHONE':
                    // check phone number for valid characters
                    if (!$this->noValueCheck && !StringUtils::strValidCharacters($fieldValue, 'phone')) {
                        throw new AdmException($gL10n->get('SYS_PHONE_INVALID_CHAR', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                    }
                    break;
                case 'URL':
                    $fieldValue = admFuncCheckUrl($fieldValue);

                    if (!$this->noValueCheck && $fieldValue === false) {
                        throw new AdmException($gL10n->get('SYS_PHONE_INVALID_CHAR', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
                    }
                    break;
            }

            if($this->mProfileFields[$fieldNameIntern]->getValue('usf_regex') !== ''
            && preg_match('/'.$this->mProfileFields[$fieldNameIntern]->getValue('usf_regex').'/', $fieldValue) === 0) {
                throw new AdmException($gL10n->get('SYS_FIELD_INVALID_REGEX', array($this->mProfileFields[$fieldNameIntern]->getValue('usf_name'))));
            }
        }

        if ($fieldNameIntern === 'COUNTRY') {
            $gL10n->getCountryName($fieldValue);
        }

        $usfId = (int) $this->mProfileFields[$fieldNameIntern]->getValue('usf_id');

        if (!array_key_exists($usfId, $this->mUserData) && $fieldValue !== '') {
            $this->mUserData[$usfId] = new TableAccess($this->db, TBL_USER_DATA, 'usd');
            $this->mUserData[$usfId]->setValue('usd_usf_id', $usfId);
            $this->mUserData[$usfId]->setValue('usd_usr_id', $this->mUserId);
        }

        // first check if user has a data object for this field and then set value of this user field
        if (array_key_exists($usfId, $this->mUserData)) {
            $valueChanged = $this->mUserData[$usfId]->setValue('usd_value', $fieldValue);

            if ($valueChanged && $this->mUserData[$usfId]->hasColumnsValueChanged()) {
                $this->columnsValueChanged = true;

                return true;
            }
        }

        return false;
    }
}
