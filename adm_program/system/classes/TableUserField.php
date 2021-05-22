<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_fields
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Diese Klasse dient dazu einen Benutzerdefiniertes Feldobjekt zu erstellen.
 * Eine Benutzerdefiniertes Feldobjekt kann ueber diese Klasse in der Datenbank
 * verwaltet werden
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung
 */
class TableUserField extends TableAccess
{
    const MOVE_UP   = 'UP';
    const MOVE_DOWN = 'DOWN';

    /**
     * @var bool|null Flag if the current user could view this user
     */
    protected $mViewUserField;
    /**
     * @var int|null Flag with the user id of which user the view property was saved
     */
    protected $mViewUserFieldUserId;

    /**
     * Constructor that will create an object of a recordset of the table adm_user_fields.
     * If the id is set than the specific user field will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $usfId    The recordset of the user field with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $usfId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'usf_cat_id');

        parent::__construct($database, TBL_USER_FIELDS, 'usf', $usfId);
    }

    /**
     * Additional to the parent method visible roles array and flag will be initialized.
     */
    public function clear()
    {
        parent::clear();

        $this->mViewUserField = null;
        $this->mViewUserFieldUserId = null;
    }

    /**
     * Deletes the selected field and all references in other tables.
     * Also the gap in sequence will be closed. After that the class will be initialize.
     * @return true true if no error occurred
     */
    public function delete()
    {
        global $gCurrentSession;

        $this->db->startTransaction();

        // close gap in sequence
        $sql = 'UPDATE '.TBL_USER_FIELDS.'
                   SET usf_sequence = usf_sequence - 1
                 WHERE usf_cat_id   = ? -- $this->getValue(\'usf_cat_id\')
                   AND usf_sequence > ? -- $this->getValue(\'usf_sequence\')';
        $this->db->queryPrepared($sql, array((int) $this->getValue('usf_cat_id'), (int) $this->getValue('usf_sequence')));

        $usfId = (int) $this->getValue('usf_id');

        // close gap in sequence of saved lists
        $sql = 'SELECT lsc_lst_id, lsc_number
                  FROM '.TBL_LIST_COLUMNS.'
                 WHERE lsc_usf_id = ? -- $usfId';
        $listsStatement = $this->db->queryPrepared($sql, array($usfId));

        while($rowLst = $listsStatement->fetch())
        {
            $sql = 'UPDATE '.TBL_LIST_COLUMNS.'
                       SET lsc_number = lsc_number - 1
                     WHERE lsc_lst_id = ? -- $rowLst[\'lsc_lst_id\']
                       AND lsc_number > ? -- $rowLst[\'lsc_number\']';
            $this->db->queryPrepared($sql, array($rowLst['lsc_lst_id'], $rowLst['lsc_number']));
        }

        // delete all dependencies in other tables
        $sql = 'DELETE FROM '.TBL_USER_LOG.'
                 WHERE usl_usf_id = ? -- $usfId';
        $this->db->queryPrepared($sql, array($usfId));

        $sql = 'DELETE FROM '.TBL_USER_DATA.'
                 WHERE usd_usf_id = ? -- $usfId';
        $this->db->queryPrepared($sql, array($usfId));

        $sql = 'DELETE FROM '.TBL_LIST_COLUMNS.'
                 WHERE lsc_usf_id = ? -- $usfId';
        $this->db->queryPrepared($sql, array($usfId));

        if(is_object($gCurrentSession))
        {
            // all active users must renew their user data because the user field structure has been changed
            $gCurrentSession->renewUserObject();
        }

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * This recursive method creates from the parameter name a unique name that only have
     * capital letters followed by the next free number (index)
     * Example: 'Membership' => 'MEMBERSHIP_2'
     * @param string $name  The name from which the unique name should be created
     * @param int    $index The index of the name. Should be startet with 1
     * @return string Returns the unique name with capital letters and number
     */
    private function getNewNameIntern($name, $index)
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));

        if ($index > 1)
        {
            $newNameIntern = $newNameIntern . '_' . $index;
        }

        $sql = 'SELECT usf_id
                  FROM '.TBL_USER_FIELDS.'
                 WHERE usf_name_intern = ? -- $newNameIntern';
        $userFieldsStatement = $this->db->queryPrepared($sql, array($newNameIntern));

        if ($userFieldsStatement->rowCount() > 0)
        {
            ++$index;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }

        return $newNameIntern;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For column **usf_value_list** the following format is accepted:
     *                           * **database** returns database value of **usf_value_list** without any transformations
     *                           * **text** extract only text from **usf_value_list**, image infos will be ignored
     *                           * For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**
     * @return mixed Returns the value of the database column.
     *               If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        if ($columnName === 'usf_description')
        {
            if (!isset($this->dbColumns['usf_description']))
            {
                $value = '';
            }
            elseif ($format === 'database')
            {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['usf_description']), ENT_QUOTES, 'UTF-8');
            }
            else
            {
                $value = $this->dbColumns['usf_description'];
            }
        }
        elseif ($columnName === 'usf_name_intern')
        {
            // internal name should be read with no conversion
            $value = parent::getValue($columnName, 'database');
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        if ($value === null)
        {
            return '';
        }

        if ($format !== 'database')
        {
            switch ($columnName)
            {
                case 'usf_name': // fallthrough
                case 'cat_name':
                    // if text is a translation-id then translate it
                    $value = Language::translateIfTranslationStrId($value);

                    break;
                case 'usf_value_list':
                    if ($this->dbColumns['usf_type'] === 'DROPDOWN' || $this->dbColumns['usf_type'] === 'RADIO_BUTTON')
                    {
                        $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                        // first replace windows new line with unix new line and then create an array
                        $valueFormated = str_replace("\r\n", "\n", $value);
                        $arrListValues = explode("\n", $valueFormated);

                        foreach ($arrListValues as $key => &$listValue)
                        {
                            if ($this->dbColumns['usf_type'] === 'RADIO_BUTTON')
                            {
                                // if value is imagefile or imageurl then show image
                                if (Image::isFontAwesomeIcon($listValue)
                                || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false)) // TODO: simplify check for images
                                {
                                    // if there is imagefile and text separated by | then explode them
                                    if (str_contains($listValue, '|'))
                                    {
                                        list($listValueImage, $listValueText) = explode('|', $listValue);
                                    }
                                    else
                                    {
                                        $listValueImage = $listValue;
                                        $listValueText  = $this->getValue('usf_name');
                                    }

                                    // if text is a translation-id then translate it
                                    $listValueText = Language::translateIfTranslationStrId($listValueText);

                                    if ($format === 'text')
                                    {
                                        // if no image is wanted then return the text part or only the position of the entry
                                        if (str_contains($listValue, '|'))
                                        {
                                            $listValue = $listValueText;
                                        }
                                        else
                                        {
                                            $listValue = $key + 1;
                                        }
                                    }
                                    else
                                    {
                                        $listValue = Image::getIconHtml($listValueImage, $listValueText);
                                    }
                                }
                            }

                            // if text is a translation-id then translate it
                            $listValue = Language::translateIfTranslationStrId($listValue);

                            // save values in new array that starts with key = 1
                            $arrListValuesWithKeys[++$key] = $listValue;
                        }
                        unset($listValue);
                        $value = $arrListValuesWithKeys;
                    }

                    break;
                case 'usf_icon':
                    // if value is font awesome icon or imagefile or imageurl then show image
                    $value = Image::getIconHtml($value, $this->getValue('usf_name'));

                    break;
                default:
                    // do nothing
            }
        }

        return $value;
    }

    /**
     * This method checks if the current user is allowed to view this profile field. Therefore
     * the visibility of the category is checked. This method will not check the context if
     * the user is allowed to view the field because he has the right to edit the profile.
     * @return bool Return true if the current user is allowed to view this profile field
     */
    public function isVisible()
    {
        global $gCurrentUser;

        $usrId = (int) $gCurrentUser->getValue('usr_id');

        if ($this->mViewUserField === null || $this->mViewUserFieldUserId !== $usrId)
        {
            $this->mViewUserFieldUserId = $usrId;

            // check if the current user could view the category of the profile field
            $this->mViewUserField = in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('USF'), true);
        }

        return $this->mViewUserField;
    }

    /**
     * Profile field will change the sequence one step up or one step down.
     * @param string $mode mode if the profile field move up or down, values are TableUserField::MOVE_UP, TableUserField::MOVE_DOWN
     * @throws AdmException
     */
    public function moveSequence($mode)
    {
        $usfSequence = (int) $this->getValue('usf_sequence');
        $usfCatId    = (int) $this->getValue('usf_cat_id');
        $sql = 'UPDATE '.TBL_USER_FIELDS.'
                   SET usf_sequence = ? -- $usfSequence
                 WHERE usf_cat_id   = ? -- $usfCatId
                   AND usf_sequence = ? -- $usfSequence -/+ 1';

        // profile field will get one number lower and therefore move a position up in the list
        if ($mode === self::MOVE_UP)
        {
            $newSequence = $usfSequence - 1;
        }
        // profile field will get one number higher and therefore move a position down in the list
        elseif ($mode === self::MOVE_DOWN)
        {
            $newSequence = $usfSequence + 1;
        }

        // update the existing entry with the sequence of the field that should get the new sequence
        $this->db->queryPrepared($sql, array($usfSequence, $usfCatId, $newSequence));

        $this->setValue('usf_sequence', $newSequence);
        $this->save();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the name intern will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @throws AdmException
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;

        $fieldsChanged = $this->columnsValueChanged;

        // if new field than generate new name intern, otherwise no change will be made
        if ($this->newRecord)
        {
            $this->setValue('usf_name_intern', $this->getNewNameIntern($this->getValue('usf_name', 'database'), 1));
        }

        $returnValue = parent::save($updateFingerPrint);

        if ($fieldsChanged && $gCurrentSession instanceof Session)
        {
            // all active users must renew their user data because the user field structure has been changed
            $gCurrentSession->renewUserObject();
        }

        return $returnValue;
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @throws AdmException
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gL10n;

        if($newValue !== parent::getValue($columnName))
        {
            if($checkValue)
            {
                if ($columnName === 'usf_description')
                {
                    return parent::setValue($columnName, $newValue, false);
                }
                elseif ($columnName === 'usf_cat_id')
                {
                    $category = new TableCategory($this->db, $newValue);

                    if (!$category->isVisible() || $category->getValue('cat_type') !== 'USF')
                    {
                        throw new AdmException('Category of the user field '. $this->getValue('dat_name'). ' could not be set
                            because the category is not visible to the current user and current organization.');
                    }
                }
                elseif ($columnName === 'usf_url' && $newValue !== '')
                {
                    $newValue = admFuncCheckUrl($newValue);

                    if ($newValue === false)
                    {
                        throw new AdmException('SYS_URL_INVALID_CHAR', array($gL10n->get('ORG_URL')));
                    }
                }

                // name, category and type couldn't be edited if it's a system field
                if (in_array($columnName, array('usf_cat_id', 'usf_type', 'usf_name'), true) && (int) $this->getValue('usf_system') === 1)
                {
                    throw new AdmException('The user field ' . $this->getValue('usf_name_intern') . ' as a system field. You could
                        not change the category, type or name.');
                }
            }

            if ($columnName === 'usf_cat_id' && (int) $this->getValue($columnName) !== (int) $newValue)
            {
                // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_USER_FIELDS.'
                         WHERE usf_cat_id = ? -- $newValue';
                $pdoStatement = $this->db->queryPrepared($sql, array($newValue));

                $this->setValue('usf_sequence', $pdoStatement->fetchColumn() + 1);
            }

            return parent::setValue($columnName, $newValue, $checkValue);
        }
        return false;
    }
}
