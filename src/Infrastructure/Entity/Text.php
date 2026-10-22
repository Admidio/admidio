<?php
namespace Admidio\Infrastructure\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;

/**
 * @brief Class manages access to database table adm_texts
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Text extends Entity
{
    public const SYSTEM_MAIL_DEFAULTS = array(
        'SYSMAIL_REGISTRATION_CONFIRMATION' => 'SYS_SYSMAIL_REGISTRATION_CONFIRMATION',
        'SYSMAIL_REGISTRATION_NEW' => 'SYS_SYSMAIL_REGISTRATION_ADMINISTRATOR',
        'SYSMAIL_REGISTRATION_APPROVED' => 'SYS_SYSMAIL_REGISTRATION_USER',
        'SYSMAIL_REGISTRATION_REFUSED' => 'SYS_SYSMAIL_REFUSE_REGISTRATION',
        'SYSMAIL_LOGIN_INFORMATION' => 'SYS_SYSMAIL_LOGIN_INFORMATION',
        'SYSMAIL_PASSWORD_RESET' => 'SYS_SYSMAIL_PASSWORD_RESET',
    );

    /** Null means that no custom version has been saved yet; an empty version is intentional. */
    public function getSystemMailCustomText(): ?string
    {
        $active = $this->getValue('txt_text', 'database');
        return Language::isTranslationStringId($active) ? $this->dbColumns['txt_custom_text'] : $active;
    }

    /** Switch the active mail template without discarding the administrator's own text. */
    public function setSystemMailTemplate(bool $useDefault, ?string $customText = null): void
    {
        $name = $this->getValue('txt_name', 'database');
        if (!isset(self::SYSTEM_MAIL_DEFAULTS[$name])) {
            throw new \InvalidArgumentException('Unknown system mail template: ' . $name);
        }
        $id = self::SYSTEM_MAIL_DEFAULTS[$name];
        $active = $this->getValue('txt_text', 'database');

        // Also preserve existing custom templates created before the switch was introduced.
        if (!Language::isTranslationStringId($active)) {
            $this->setValue('txt_custom_text', $active);
        }
        if ($customText !== null && Language::isTranslationStringId(trim($customText))) {
            // Entering a translation ID restores the standard without replacing the backup.
            $useDefault = true;
        } elseif ($customText !== null) {
            $this->setValue('txt_custom_text', $customText);
        }

        if ($useDefault) {
            $this->setValue('txt_text', $id);
        } else {
            $custom = $this->dbColumns['txt_custom_text'];
            if ($custom === null) {
                $custom = self::translateColumnValue('txt_text', $id);
                $this->setValue('txt_custom_text', $custom);
            }
            // An explicit custom mode must store text even when it equals today's default.
            parent::setValue('txt_text', null, false);
            $this->setValue('txt_text', $custom);
        }
    }

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

    /** The text format resolves a template ID without HTML-encoding the mail body. */
    public function getValue(string $columnName, string $format = ''): mixed
    {
        if ($columnName === 'txt_text' && $format === 'text') {
            return self::translateColumnValue($columnName, parent::getValue($columnName, 'database'));
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
    public function setValue(string $columnName, mixed $newValue, bool $checkValue = true): bool
    {
        if (in_array($columnName, array('txt_text', 'txt_custom_text'), true) && $newValue !== null) {
            // convert <br /> to a normal line feed
            $newValue = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13) . chr(10), $newValue);
        }

        if ($columnName === 'txt_custom_text' && $newValue === '') {
            // Preserve an intentionally empty custom version separately from "not created yet".
            return parent::setValue($columnName, '', false);
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
            $name = array_key_exists($textLabel, $textLabels) ? $textLabels[$textLabel] : $textLabel;
        } else {
            $name = $this->dbColumns[$this->keyColumnName];
        }

        return $this->filterReadableName($name);
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
