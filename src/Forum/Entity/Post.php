<?php
namespace Admidio\Forum\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_guestbook_comments
 *
 * With the given id a guestbook comment object is created from the data in the database table **adm_guestbook_comments**.
 * The class will handle the communication with the database and give easy access to the data. New
 * guestbook comments could be created or existing guestbook comments could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Post extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_guestbook_comments.
     * If the id is set than the specific guestbook comment will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $gbcId The recordset of the guestbook comment with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $gbcId = 0)
    {
        // read also data of assigned guestbook entry
        $this->connectAdditionalTable(TBL_GUESTBOOK, 'gbo_id', 'gbc_gbo_id');

        parent::__construct($database, TBL_GUESTBOOK_COMMENTS, 'gbc', $gbcId);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
    {
        if ($columnName === 'gbc_text') {
            if (!isset($this->dbColumns['gbc_text'])) {
                $value = '';
            } elseif ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['gbc_text']));
            } else {
                $value = $this->dbColumns['gbc_text'];
            }

            return $value;
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * guestbook entry will be published, if moderate mode is set
     * @throws Exception
     */
    public function moderate()
    {
        // unlock entry
        $this->setValue('gbc_locked', '0');
        $this->save();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->newRecord) {
            $this->setValue('gbc_ip_address', $_SERVER['REMOTE_ADDR']);
        }

        $returnCode = parent::save($updateFingerPrint);

        // read data to fill folder information to the object
        if ($this->newRecord) {
            $this->readDataById((int) $this->getValue('gbc_gbo_id'));
            $this->newRecord = true;
        }

        return $returnCode;
    }

    /**
     * Send a notification email that a new guestbook comment was created or an existing guestbook comment was changed
     * to all members of the notification role. This role is configured within the global preference
     * **system_notifications_role**. The email contains the guestbook comment text, the name of the user,
     * the timestamp and the url to this guestbook comment.
     * @return bool Returns **true** if the notification was sent
     * @throws Exception 'SYS_EMAIL_NOT_SEND'
     * @throws Exception
     */
    public function sendNotification(): bool
    {
        global $gCurrentOrganization, $gSettingsManager, $gL10n;

        if ($gSettingsManager->getBool('system_notifications_new_entries')) {
            $notification = new Email();

            if ($this->isNewRecord()) {
                $messageTitleText = 'SYS_GUESTBOOK_COMMENT_CREATED_TITLE';
                $messageUserText = 'SYS_CREATED_BY';
                $messageDateText = 'SYS_CREATED_AT';
            } else {
                $messageTitleText = 'SYS_GUESTBOOK_COMMENT_CHANGED_TITLE';
                $messageUserText = 'SYS_CHANGED_BY';
                $messageDateText = 'SYS_CHANGED_AT';
            }

            $message = $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))) . '<br /><br />'
                . $gL10n->get('SYS_TEXT') . ': ' . $this->getValue('gbc_text') . '<br />'
                . $gL10n->get($messageUserText) . ': ' . $this->getValue('gbc_name') . '<br />'
                . $gL10n->get($messageDateText) . ': ' . date($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />'
                . $gL10n->get('SYS_URL') . ': ' . ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php?gbo_uuid=' . $this->getValue('gbo_uuid') . '<br />';
            return $notification->sendNotification(
                $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))),
                $message
            );
        }
        return false;
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
        if ($checkValue) {
            if ($columnName === 'gbc_text') {
                return parent::setValue($columnName, $newValue, false);
            }

            if ($columnName === 'gbc_email' && $newValue !== '') {
                // If Email has an invalid format, it won't be set
                if (!StringUtils::strValidCharacters($newValue, 'email')) {
                    return false;
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns.
     * The guestbook table also contains gbc_org_id and gbc_ip_address columns, 
     * which we don't want to log.
     *
     * @return true Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), ['gbc_ip_address', 'gbc_gbo_id']);
    }

    /**
     * Adjust the changelog entry for this db record: Add the parent guestbook entry as a related object
     * 
     * @param LogChanges $logEntry The log entry to adjust
     * 
     * @return void
     */
    protected function adjustLogEntry(LogChanges $logEntry) {
        $gboEntry = new Topic($this->db, $this->getValue('gbc_gbo_id'));
        $logEntry->setLogRelated($gboEntry->getValue('gbo_uuid'), $gboEntry->getValue('gbo_name'));
    }
}
