<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_links
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class creates objects of the database table links.
 * You can read, change and create weblinks in the database.
 */
class TableWeblink extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_links.
     * If the id is set than the specific weblink will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $lnkId    The recordset of the weblink with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $lnkId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'lnk_cat_id');

        parent::__construct($database, TBL_LINKS, 'lnk', $lnkId);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string Returns the value of the database column.
     *                    If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if ($columnName === 'lnk_description') {
            if (!isset($this->dbColumns['lnk_description'])) {
                $value = '';
            } elseif ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['lnk_description']));
            } else {
                $value = $this->dbColumns['lnk_description'];
            }
        } else {
            $value = parent::getValue($columnName, $format);
        }

        // if text is a translation-id then translate it
        if ($columnName === 'cat_name' && $format !== 'database' && Language::isTranslationStringId($value)) {
            $value = $gL10n->get($value);
        }

        return $value;
    }

    /**
     * This method checks if the current user is allowed to edit this weblink. Therefore
     * the weblink must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage weblinks.
     * Global weblinks could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this weblink
     */
    public function isEditable()
    {
        global $gCurrentOrganization, $gCurrentUser;

        if ($gCurrentUser->editDates()
        || in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllEditableCategories('LNK'), true)) {
            // if category belongs to current organization than weblinks are editable
            if ($this->getValue('cat_org_id') > 0
            && (int) $this->getValue('cat_org_id') === $GLOBALS['gCurrentOrgId']) {
                return true;
            }

            // if category belongs to all organizations, child organization couldn't edit it
            if ((int) $this->getValue('cat_org_id') === 0 && !$gCurrentOrganization->isChildOrganization()) {
                return true;
            }
        }

        return false;
    }

    /**
     * This method checks if the current user is allowed to view this weblink. Therefore
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this weblink
     */
    public function isVisible()
    {
        global $gCurrentUser;

        // check if the current user could view the category of the announcement
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('LNK'), true);
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For new records the organization and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentUser;

        if (!$this->saveChangesWithoutRights && !in_array((int) $this->getValue('lnk_cat_id'), $gCurrentUser->getAllEditableCategories('LNK'), true)) {
            throw new AdmException('Weblink could not be saved because you are not allowed to edit weblinks of this category.');
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gL10n;

        if ($checkValue) {
            if ($columnName === 'lnk_description') {
                // don't check value because it contains expected html tags
                $checkValue = false;
            } elseif ($columnName === 'lnk_cat_id') {
                $category = new TableCategory($this->db);
                if(is_int($newValue)) {
                    if(!$category->readDataById($newValue)) {
                        throw new AdmException('No Category with the given id '. $newValue. ' was found in the database.');
                    }
                } else {
                    if(!$category->readDataByUuid($newValue)) {
                        throw new AdmException('No Category with the given uuid '. $newValue. ' was found in the database.');
                    }
                    $newValue = $category->getValue('cat_id');
                }
            } elseif ($columnName === 'lnk_url' && $newValue !== '') {
                $newValue = admFuncCheckUrl($newValue);

                if ($newValue === false) {
                    throw new AdmException('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_WEBSITE')));
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
