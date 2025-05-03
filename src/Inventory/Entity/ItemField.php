<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_files
 *
 * With the given ID a file object is created from the data in the database table **adm_files**.
 * The class will handle the communication with the database and give easy access to the data. New
 * file could be created or existing file could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ItemField extends Entity
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $mDb;

    /**
     * Constructor that will create an object of a recordset of the table adm_files.
     * If the id is set than the specific files will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $filId The recordset of the files with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $filId = 0)
    {
        $this->mDb = $database;

        // read also data of assigned folder
        parent::__construct($database, TBL_INVENTORY_FIELDS, 'inf', $filId);
    }

    /**
     * Deletes the selected field and all references in other tables.
     * Also, the gap in sequence will be closed. After that the class will be initialized.
     * @return true true if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        global $gCurrentOrgId;

        if ($this->getValue('inf_system') == 1) {
            // System fields could not be deleted
            throw new Exception('Item fields with the flag "system" could not be deleted.');
        }

        $this->db->startTransaction();

        // close gap in sequence
        $sql = 'UPDATE ' . TBL_INVENTORY_FIELDS . '
                   SET inf_sequence = inf_sequence - 1
                 WHERE inf_org_id   = ? -- $gCurrentOrgId
                   AND inf_sequence > ? -- $this->getValue(\'inf_sequence\')';
        $this->db->queryPrepared($sql, array($gCurrentOrgId, (int)$this->getValue('inf_sequence')));

        $infId = (int)$this->getValue('inf_id');
        $sql = 'DELETE FROM ' . TBL_INVENTORY_DATA . '
                 WHERE ind_inf_id = ? -- $usfId';
        $this->db->queryPrepared($sql, array($infId));

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * This recursive method creates from the parameter name a unique name that only have
     * capital letters followed by the next free number (index)
     * Example: 'Membership' => 'MEMBERSHIP_2'
     * @param string $name The name from which the unique name should be created
     * @param int $index The index of the name. Should be startet with 1
     * @return string Returns the unique name with capital letters and number
     * @throws Exception
     */
    private function getNewNameIntern(string $name, int $index): string
    {
        $newNameIntern = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', $name)));

        if ($index > 1) {
            $newNameIntern = $newNameIntern . '_' . $index;
        }

        $sql = 'SELECT inf_id
                  FROM ' . TBL_INVENTORY_FIELDS . '
                 WHERE inf_name_intern = ? -- $newNameIntern';
        $userFieldsStatement = $this->db->queryPrepared($sql, array($newNameIntern));

        if ($userFieldsStatement->rowCount() > 0) {
            ++$index;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }

        return $newNameIntern;
    }

    /**
     * Returns the item value for this column
     * 
     * format = 'html'  :               returns the value in html-format if this is necessary for that field type
     * format = 'database' :            returns the value that is stored in database with no format applied
     * @param string $fieldNameIntern   Expects the @b inf_name_intern of table @b adm_inventory_fields
     * @param string $format            Returns the field value in a special format @b text, @b html, @b database
     *                                  or datetime (detailed description in method description)
     * @return mixed                    Returns the value for the column
     */
    public function getValue($fieldNameIntern, $format = ''): mixed
    {
        if ($fieldNameIntern === 'inf_description') {
            if (!isset($this->dbColumns['inf_description'])) {
                $value = '';
            } elseif ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['inf_description']), ENT_QUOTES, 'UTF-8');
            } else {
                $value = $this->dbColumns['inf_description'];
            }
        } elseif ($fieldNameIntern === 'inf_name_intern') {
            // internal name should be read with no conversion
            $value = parent::getValue($fieldNameIntern, 'database');
        } else {
            $value = parent::getValue($fieldNameIntern, $format);
        }

        if (strlen((string) $value) === 0 || $value === null) {
            return '';
        }

        if ($format !== 'database') {
            switch ($fieldNameIntern) {
                case 'inf_name':
                    // if text is a translation-id then translate it
                    $value = Language::translateIfTranslationStrId($value);
                    break;

                case 'inf_value_list':
                    if ($this->dbColumns['inf_type'] === 'DROPDOWN' || $this->dbColumns['inf_type'] === 'RADIO_BUTTON') {
                        $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                        // first replace windows new line with unix new line and then create an array
                        $valueFormatted = str_replace("\r\n", "\n", $value);
                        $arrListValues = explode("\n", $valueFormatted);

                        foreach ($arrListValues as $key => &$listValue) {
                            if ($this->dbColumns['inf_type'] === 'RADIO_BUTTON') {
                                // if value is bootstrap icon or icon separated from text
                                if (Image::isBootstrapIcon($listValue) || str_contains($listValue, '|')) {
                                    // if there is bootstrap icon and text separated by | then explode them
                                    if (str_contains($listValue, '|')) {
                                        list($listValueImage, $listValueText) = explode('|', $listValue);
                                    } else {
                                        $listValueImage = $listValue;
                                        $listValueText = '';
                                    }

                                    // if text is a translation-id then translate it
                                    $listValueText = Language::translateIfTranslationStrId($listValueText);

                                    if ($format === 'html') {
                                        $listValue = Image::getIconHtml($listValueImage, $listValueText) . ' ' . $listValueText;
                                    } else {
                                        // if no image is wanted then return the text part or only the position of the entry
                                        if (str_contains($listValue, '|')) {
                                            $listValue = $listValueText;
                                        } else {
                                            $listValue = $key + 1;
                                        }
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
                default:
                    // do nothing
            }
        }

        return $value;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the name intern will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentUser, $gCurrentOrgId;

        // only administrators can edit item fields
        if (!$gCurrentUser->isAdministrator() && !$this->saveChangesWithoutRights) {
            throw new Exception('Item field could not be saved because only administrators are allowed to edit item fields.');
            // => EXIT
        }

        $fieldsChanged = $this->columnsValueChanged;

        // if new field than generate new name intern, otherwise no change will be made
        if ($this->newRecord && $this->getValue('inf_name_intern') === '') {
            $this->setValue('inf_name_intern', $this->getNewNameIntern($this->getValue('inf_name', 'database'), 1));
        }

        // if new field than generate new sequence, otherwise no change will be made
        if ($this->newRecord && $this->getValue('inf_sequence') === '') {
            $sql = 'SELECT COUNT(*) AS count
                FROM ' . TBL_INVENTORY_FIELDS . '
                WHERE inf_org_id = ? -- $newValue';
            $pdoStatement = $this->db->queryPrepared($sql, array($gCurrentOrgId));

            $this->setValue('inf_sequence', $pdoStatement->fetchColumn());
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        global $gL10n;

        if ($newValue !== parent::getValue($columnName)) {
            if ($checkValue) {
                if ($columnName === 'usf_description') {
                    // don't check value because it contains expected html tags
                    $checkValue = false;
                }

                // name, category and type couldn't be edited if it's a system field
                if (in_array($columnName, array('usf_type'), true) && (int)$this->getValue('inf_system') === 1) {
                    throw new Exception('The item field ' . $this->getValue('inf_name_intern') . ' is a system field. You could
                        not change the type.');
                }
            }

            return parent::setValue($columnName, $newValue, $checkValue);
        }
        return false;
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns.
     * The guestbook table also contains gbc_org_id and gbc_ip_address columns,
     * which we don't want to log.
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(),
            ['inf_id', 'inf_org_id', 'inf_name_intern', 'inf_system']/* ,
            ($this->newRecord)?[$this->columnPrefix.'_text']:[] */
        );
    }

    /**
     * Adjust the changelog entry for this db record: Add the first forum post as a related object
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
/*         $fotEntry = new ItemField($this->db, (int)$this->getValue('fot_fop_id_first_post'));
        $logEntry->setLogRelated($fotEntry->getValue('fop_uuid'), $fotEntry->getValue('fop_text'));
 */    }
}
