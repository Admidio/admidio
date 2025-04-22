<?php
namespace Admidio\Weblinks\Entity;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * @brief Class manages access to database table adm_links
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Weblink extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_links.
     * If the id is set than the specific weblink will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $lnkId The recordset of the weblink with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $lnkId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'lnk_cat_id');

        parent::__construct($database, TBL_LINKS, 'lnk', $lnkId);
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
     * This method checks if the current user is allowed to edit this weblink. Therefore,
     * the weblink must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage weblinks.
     * Global weblinks could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this weblink
     * @throws Exception
     */
    public function isEditable(): bool
    {
        global $gCurrentOrganization, $gCurrentUser, $gCurrentOrgId;

        if ($gCurrentUser->isAdministratorWeblinks()
            || (in_array((int)$this->getValue('cat_id'), $gCurrentUser->getAllEditableCategories('LNK'), true)
                && $gCurrentUser->getValue('usr_id') === $this->getValue('lnk_usr_id_create'))
        ) {
            // if category belongs to current organization than weblinks are editable
            if ($this->getValue('cat_org_id') > 0
            && (int) $this->getValue('cat_org_id') === $gCurrentOrgId) {
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
     * This method checks if the current user is allowed to view this weblink. Therefore,
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this weblink
     * @throws Exception
     */
    public function isVisible(): bool
    {
        global $gCurrentUser;

        // check if the current user could view the category of the announcement
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('LNK'), true);
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For new records the organization and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentUser;

        if ($this->newRecord) {
            // For new link set counter to 0
            $this->setValue('lnk_counter', 0);
        }

        if (!$this->saveChangesWithoutRights && !in_array((int) $this->getValue('lnk_cat_id'), $gCurrentUser->getAllEditableCategories('LNK'), true)) {
            throw new Exception('Weblink could not be saved because you are not allowed to edit weblinks of this category.');
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Send a notification email that a new weblink was created or an existing weblink was changed
     * to all members of the notification role. This role is configured within the global preference
     * **system_notifications_role**. The email contains the weblink name, address, the name of the current user,
     * the timestamp and the url to this weblink.
     * @return bool Returns **true** if the notification was sent
     * @throws Exception 'SYS_EMAIL_NOT_SEND'
     * @throws Exception
     */
    public function sendNotification(): bool
    {
        global $gCurrentOrganization, $gCurrentUser, $gSettingsManager, $gL10n;

        if ($gSettingsManager->getBool('system_notifications_new_entries')) {
            $notification = new Email();

            if ($this->isNewRecord()) {
                $messageTitleText = 'SYS_LINK_CREATED_TITLE';
                $messageUserText = 'SYS_CREATED_BY';
                $messageDateText = 'SYS_CREATED_AT';
            } else {
                $messageTitleText = 'SYS_LINK_CHANGED_TITLE';
                $messageUserText = 'SYS_CHANGED_BY';
                $messageDateText = 'SYS_CHANGED_AT';
            }

            $message = $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))) . '<br /><br />'
                . $gL10n->get('SYS_LINK_NAME') . ': ' . $this->getValue('lnk_name') . '<br />'
                . $gL10n->get('SYS_LINK_ADDRESS') . ': ' . $this->getValue('lnk_url') . '<br />'
                . $gL10n->get($messageUserText) . ': ' . $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') . '<br />'
                . $gL10n->get($messageDateText) . ': ' . date($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />'
                . $gL10n->get('SYS_URL') . ': ' . ADMIDIO_URL . FOLDER_MODULES . '/links/links.php?link_uuid=' . $this->getValue('lnk_uuid') . '<br />';
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
        global $gL10n;

        if ($checkValue) {
            if ($columnName === 'lnk_description') {
                // don't check value because it contains expected html tags
                $checkValue = false;
            } elseif ($columnName === 'lnk_cat_id') {
                $category = new Category($this->db);
                if(is_int($newValue)) {
                    if(!$category->readDataById($newValue)) {
                        throw new Exception('No Category with the given id '. $newValue. ' was found in the database.');
                    }
                } else {
                    if(!$category->readDataByUuid($newValue)) {
                        throw new Exception('No Category with the given uuid '. $newValue. ' was found in the database.');
                    }
                    $newValue = $category->getValue('cat_id');
                }
            } elseif ($columnName === 'lnk_url' && $newValue !== '') {
                $newValue = admFuncCheckUrl($newValue);

                if ($newValue === false) {
                    throw new Exception('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_WEBSITE')));
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
