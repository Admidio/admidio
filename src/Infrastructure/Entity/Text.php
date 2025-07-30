<?php
namespace Admidio\Infrastructure\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;

/**
 * @brief Class manages access to database table adm_texts
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Text extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_texts.
     * If the id is set than the specific text will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $name The recordset of the text with this name will be loaded.
     *                           If name isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, string $name = '')
    {
        parent::__construct($database, TBL_TEXTS, 'txt', $name);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string Returns the value of the database column.
     *                    If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
    {
        if ($columnName === 'txt_text') {
            return $this->dbColumns['txt_text'];
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->newRecord && $this->getValue('txt_org_id') === '') {
            // Insert
            $this->setValue('txt_org_id', $GLOBALS['gCurrentOrgId']);
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
        if ($columnName === 'txt_text') {
            // convert <br /> to a normal line feed
            $newValue = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13) . chr(10), $newValue);
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Return a human-readable representation of this record.
     * If a column [prefix]_name exists, it is returned, otherwise the id.
     * This method can be overridden in child classes for custom behavior.
     * 
     * @return string The readable representation of the record (can also be a translatable identifier)
     */
    public function readableName(): string
    {
        $textLabels = array(
            'SYSMAIL_REGISTRATION_CONFIRMATION' => 'SYS_NOTIFICATION_REGISTRATION_CONFIRMATION',
            'SYSMAIL_REGISTRATION_NEW' => 'SYS_NOTIFICATION_NEW_REGISTRATION',
            'SYSMAIL_REGISTRATION_APPROVED' => 'SYS_NOTIFICATION_REGISTRATION_APPROVAL',
            'SYSMAIL_REGISTRATION_REFUSED' => 'ORG_REFUSE_REGISTRATION',
            'SYSMAIL_LOGIN_INFORMATION' => 'SYS_SEND_LOGIN_INFORMATION',
            'SYSMAIL_PASSWORD_RESET' => 'SYS_PASSWORD_FORGOTTEN',
        );
//        $textLabel = Language::translateIfTranslationStrId($textLabels[$row['name']]);
        if (array_key_exists($this->columnPrefix.'_name', $this->dbColumns)) {
            $textLabel = $this->dbColumns[$this->columnPrefix.'_name'];
            if (array_key_exists($textLabel, $textLabels)) {
                return $textLabels[$textLabel];
            } else {
                return $textLabel;
            }
        } else {
            return $this->dbColumns[$this->keyColumnName];
        }
    }
    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns.
     * The textx table also contains txt_org_id and txt_name, which we don't want to log.
     *
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), ['txt_name']);
    }
}
