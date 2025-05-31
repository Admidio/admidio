<?php

namespace Admidio\Forum\Entity;

use Admidio\Categories\Entity\Category;
use Admidio\Categories\Service\CategoryService;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_guestbook
 *
 * With the given id a guestbook entry object is created from the data in the database table **adm_guestbook**.
 * The class will handle the communication with the database and give easy access to the data. New
 * guestbook entries could be created or existing guestbook entries could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Topic extends Entity
{
    /**
     * @var Post Object of the initial post of this topic.
     */
    protected Post $firstPost;

    /**
     * Constructor that will create an object of a recordset of the table adm_guestbook.
     * If the id is set than the specific guestbook will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $fotID The recordset of the guestbook with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $fotID = 0)
    {
        $this->firstPost = new Post($database);

        // read also data of assigned first post
        $this->connectAdditionalTable(TBL_FORUM_POSTS, 'fop_id', 'fot_fop_id_first_post');
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'fot_cat_id');

        parent::__construct($database, TBL_FORUM_TOPICS, 'fot', $fotID);
    }

    /**
     * Deletes the selected guestbook entry and all comments.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        $this->db->startTransaction();

        // delete reference to first post
        $this->setValue('fot_fop_id_first_post', 0);
        $this->save();

        // Delete all available posts to this forum entry
        $sql = 'DELETE FROM ' . TBL_FORUM_POSTS . '
                      WHERE fop_fot_id = ? -- $this->getValue(\'fot_id\')';
        $this->db->queryPrepared($sql, array((int)$this->getValue('fot_id')));

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * Get the last offset of the topic. The last offset is first post of the last post of the topic.
     * @Return int Returns the last offset of the topic.
     * @throws Exception
     */
    public function getLastOffset(): int
    {
        global $gSettingsManager;

        (float) $lastPage = ($this->getPostCount() + 1) / $gSettingsManager->getInt('forum_posts_per_page');

        if (fmod($lastPage, 1) == 0) {
            $lastPage = $lastPage - 1;
        } else {
            $lastPage = (int) $lastPage;
        }

        return ($lastPage * $gSettingsManager->getInt('forum_posts_per_page'));
    }

    /**
     * Get number of available posts of this topic in the database.
     * @Return int Returns the total count of posts of this topic.
     * @throws Exception
     */
    public function getPostCount(): int
    {
        global $gDb;

        $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_FORUM_POSTS . '
                    ON fop_fot_id = fot_id
                 WHERE fot_uuid = ? -- $this->getValue(\'fot_uuid\') ';

        $pdoStatement = $gDb->queryPrepared($sql, array($this->getValue('fot_uuid')));

        return (int)$pdoStatement->fetchColumn();
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
    public function getValue(string $columnName, string $format = ''): bool|int|string
    {
        if (str_starts_with($columnName, 'fop_')) {
            return $this->firstPost->getValue($columnName, $format);
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * This method checks if the current user is allowed to edit this topic. Therefore,
     * the topic must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage topic.
     * Global topic could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this topic
     * @throws Exception
     */
    public function isEditable(): bool
    {
        global $gCurrentOrganization, $gCurrentUser, $gCurrentOrgId;

        // check if the current user could edit the category of the topic
        if ($gCurrentUser->isAdministratorForum()
            || in_array((int)$this->getValue('cat_id'), $gCurrentUser->getAllEditableCategories('FOT'), true)) {
            // if category belongs to current organization than topic are editable
            if ($this->getValue('cat_org_id') > 0
                && (int)$this->getValue('cat_org_id') === $gCurrentOrgId) {
                return true;
            }

            // if category belongs to all organizations, child organization couldn't edit it
            if ((int)$this->getValue('cat_org_id') === 0 && !$gCurrentOrganization->isChildOrganization()) {
                return true;
            }
        }

        return false;
    }

    /**
     * This method checks if the current user is allowed to view this topic. Therefore,
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this topic
     * @throws Exception
     */
    public function isVisible(): bool
    {
        global $gCurrentUser;

        // check if the current user could view the category of the topic
        return in_array((int)$this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('FOT'), true);
    }

    /**
     * Send a notification email that a new guestbook entry was created or an existing guestbook entry was changed
     * to all members of the notification role. This role is configured within the global preference
     * **system_notifications_role**. The email contains the guestbook entry text, the name of the user,
     * the timestamp and the url to this guestbook entry.
     * @return bool Returns **true** if the notification was sent
     * @throws Exception 'SYS_EMAIL_NOT_SEND'
     * @throws Exception
     */
    public function sendNotification(): bool
    {
        global $gCurrentOrganization, $gSettingsManager, $gL10n, $gCurrentUser;

        if ($gSettingsManager->getBool('system_notifications_new_entries')) {
            $notification = new Email();

            if ($this->isNewRecord()) {
                $messageTitleText = 'SYS_FORUM_TOPIC_CREATED_TITLE';
                $messageUserText = 'SYS_CREATED_BY';
                $messageDateText = 'SYS_CREATED_AT';
            } else {
                $messageTitleText = 'SYS_FORUM_TOPIC_CHANGED_TITLE';
                $messageUserText = 'SYS_CHANGED_BY';
                $messageDateText = 'SYS_CHANGED_AT';
            }

            $message = $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))) . '<br /><br />'
                . $gL10n->get('SYS_TITLE') . ': ' . $this->getValue('fot_title') . '<br />'
                . $gL10n->get('SYS_TEXT') . ': ' . $this->getValue('fop_text') . '<br />'
                . $gL10n->get($messageUserText) . ': ' . $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') . '<br />'
                . $gL10n->get($messageDateText) . ': ' . date($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />'
                . $gL10n->get('SYS_URL') . ': ' .  SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('topic_uuid' => $this->getValue('fot_uuid'))) . '<br />';
            return $notification->sendNotification(
                $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))),
                $message
            );
        }
        return false;
    }

    /**
     * Reads a record out of the table in database selected by the unique id column in the table.
     * Per default all columns of the default table will be read and stored in the object.
     * @param int $id Unique id of id column of the table.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataByUuid
     */
    public function readDataById(int $id): bool
    {
        $returnValue = parent::readDataById($id);

        if ($returnValue) {
            $this->firstPost->readDataById($this->getValue('fot_fop_id_first_post'));
        }

        return $returnValue;
    }

    /**
     * Reads a record out of the table in database selected by the unique uuid column in the table.
     * The name of the column must have the syntax table_prefix, underscore and uuid. E.g. usr_uuid.
     * Per default all columns of the default table will be read and stored in the object.
     * Not every Admidio table has a UUID. Please check the database structure before you use this method.
     * @param string $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataById
     */
    public function readDataByUuid(string $uuid): bool
    {
        $returnValue = parent::readDataByUuid($uuid);

        if ($returnValue) {
            $this->firstPost->readDataById($this->getValue('fot_fop_id_first_post'));
        }

        return $returnValue;
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
        if ($this->newRecord && empty($this->getValue('fot_cat_id'))) {
            // if only one category is available, then set this category as default
            $categoryServices = new CategoryService($this->db, 'FOT');
            $categories = $categoryServices->getVisibleCategories();
            if (count($categories) === 1) {
                $this->setValue('fot_cat_id', $categories[0]['cat_id']);
            }
        }

        $this->db->startTransaction();
        $returnCode = parent::save($updateFingerPrint);

        if ($this->newRecord) {
            $this->firstPost->setValue('fop_fot_id', $this->getValue('fot_id'));
            $this->firstPost->save();
            $this->setValue('fot_fop_id_first_post', $this->firstPost->getValue('fop_id'));
            $returnCode = parent::save($updateFingerPrint);
        } else {
            $this->firstPost->save();
        }

        $this->db->endTransaction();
        return $returnCode;
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
        global $gCurrentUser;

        if (str_starts_with($columnName, 'fop_')) {
            return $this->firstPost->setValue($columnName, $newValue, $checkValue);
        } elseif ($columnName === 'fot_cat_id') {
            if (is_int($newValue)) {
                if (!in_array($newValue, $gCurrentUser->getAllEditableCategories('FOT'))) {
                    throw new Exception('You are not allowed to create a post in this category.');
                }
            } else {
                if (!in_array($newValue, $gCurrentUser->getAllEditableCategories('FOT', 'uuid'))) {
                    throw new Exception('You are not allowed to create a post in this category.');
                }
                $category = new Category($this->db);
                $category->readDataByUuid($newValue);
                $newValue = $category->getValue('cat_id');
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * In addition to the default ignored columns, don't log fot_views
     *
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(),
        [$this->columnPrefix . '_views'],
        ($this->newRecord)?[$this->columnPrefix.'_title']:[]);
    }
    /**
     * Adjust the changelog entry for this db record: Add the first forum post as a related object
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        $fotEntry = new Post($this->db, (int)$this->getValue('fot_fop_id_first_post'));
        $logEntry->setLogRelated($fotEntry->getValue('fop_uuid'), $fotEntry->getValue('fop_text'));
    }
}
