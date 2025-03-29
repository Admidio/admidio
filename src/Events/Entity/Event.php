<?php
namespace Admidio\Events\Entity;

use Admidio\Categories\Entity\Category;
use Admidio\Events\ValueObject\Participants;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Language;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * Creates an event object from the database table adm_events
 *
 * With the given id an event object is created from the data in the database table **adm_events**.
 * The class will handle the communication with the database and give easy access to the data. New
 * event could be created or existing event could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * **Code examples**
 * ```
 * // get data from an existing event
 * $event       = new Event($gDb, $dateId);
 * $headline    = $event->getValue('dat_headline');
 * $description = $event->getValue('dat_description');
 *
 * // change existing event
 * $event = new Event($gDb, $dateId);
 * $event->setValue('dat_headline', 'My new headline');
 * $event->setValue('dat_description', 'This is the new description.');
 * $event->save();
 *
 * // create new event
 * $event = new Event($gDb);
 * $event->setValue('dat_headline', 'My new headline');
 * $event->setValue('dat_description', 'This is the new description.');
 * $event->save();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Event extends Entity
{
    /**
     * @var Participants|null object to handle all participants of this event
     */
    private ?Participants $mParticipants;

    /**
     * Constructor that will create an object of a recordset of the table adm_events.
     * If the id is set than the specific event will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $datId The recordset of the event with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, $datId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');

        parent::__construct($database, TBL_EVENTS, 'dat', $datId);
    }

    /**
     * Check if the current user is allowed to participate in this event.
     * Therefore, we check if the user is member of a role that is assigned to
     * the right event_participation. This method will also return **true** if the deadline is exceeded
     * and a further participation isn't possible.
     * @return bool Return true if the current user is allowed to participate in the event.
     * @throws Exception
     */
    public function allowedToParticipate(): bool
    {
        global $gCurrentUser;

        if ($this->getValue('dat_rol_id') > 0) {
            $eventParticipationRoles = new RolesRights($this->db, 'event_participation', (int) $this->getValue('dat_id'));

            if (count(array_intersect($gCurrentUser->getRoleMemberships(), $eventParticipationRoles->getRolesIds())) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calls clear() Method of parent class and initialize child class specific parameters
     * @throws Exception
     */
    public function clear()
    {
        parent::clear();

        // initialize class members
        $this->mParticipants = null;
    }

    /**
     * Check if it's possible for the current user to participate in this event.
     * Therefore, we check if the user is allowed to participate and if the deadline of the event isn't exceeded.
     * There should be no participants limit or the limit is not reached or the current user is already member
     * of the event. If the user is already a member of the event, then this method will return true, if the
     * deadline is not reached.
     * @return bool Return true if it's possible for the current user to participate in the event.
     * @throws Exception
     */
    public function possibleToParticipate(): bool
    {
        global $gCurrentUserId;

        if(!$this->deadlineExceeded()) {
            if(!is_object($this->mParticipants)) {
                $this->mParticipants = new Participants($this->db, $this->getValue('dat_rol_id'));
            }

            if ($this->mParticipants->isMemberOfEvent($gCurrentUserId)) {
                return true;
            } elseif ($this->allowedToParticipate()
                && ((int) $this->getValue('dat_max_members') === 0
                    || $this->mParticipants->getCount() < (int) $this->getValue('dat_max_members'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the deadline is in the future than return false or
     * if the deadline is in the past than return true.
     * @return bool Return true if the deadline is exceeded.
     * @throws Exception
     */
    public function deadlineExceeded(): bool
    {
        return $this->getValidDeadline() < DATETIME_NOW;
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        $datId     = (int) $this->getValue('dat_id');
        $datRoleId = (int) $this->getValue('dat_rol_id');

        $this->db->startTransaction();

        // delete all roles assignments that could participate in the event
        $eventParticipationRoles = new RolesRights($this->db, 'event_participation', $datId);
        $eventParticipationRoles->delete();

        // if event has participants then the role with their memberships must be deleted
        if ($datRoleId > 0) {
            $sql = 'UPDATE '.TBL_EVENTS.'
                       SET dat_rol_id = NULL
                     WHERE dat_id = ? -- $datId';
            $this->db->queryPrepared($sql, array($datId));

            $eventRole = new Role($this->db, $datRoleId);
            $eventRole->delete(); // TODO Exception handling
        }

        // now delete event
        parent::delete();

        return $this->db->endTransaction();
    }

    /**
     * This Method will return a string with the date and time period of the current event.
     * If the start and end of the event is at the same day then the date will only include once.
     * Also, the all-day flag will be considered.
     * @return string Returns a formatted date and time string corresponding to the event settings.
     * @throws Exception
     */
    public function getDateTimePeriod($showPeriodEnd = true): string
    {
        global $gSettingsManager;

        $beginDate = $this->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ';
        $endDate   = '';

        if ($this->getValue('dat_all_day') != 1) {
            $beginDate .= $this->getValue('dat_begin', $gSettingsManager->getString('system_time'));
        }

        if ($showPeriodEnd) {
            // Show date end and time
            if ($this->getValue('dat_begin', $gSettingsManager->getString('system_date')) !== $this->getValue('dat_end', $gSettingsManager->getString('system_date'))) {
                $endDate .= $this->getValue('dat_end', $gSettingsManager->getString('system_date'));
            }
            if ($this->getValue('dat_all_day') != 1) {
                $endDate .= ' '. $this->getValue('dat_end', $gSettingsManager->getString('system_time'));
            }
            if ($endDate !== '') {
                $endDate = ' - '. $endDate;
            }
        }

        return $beginDate . $endDate;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns the format should be
     *                           the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return
     *                           the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
    {
        global $gL10n;

        if ($columnName === 'dat_description') {
            if (!isset($this->dbColumns['dat_description'])) {
                $value = '';
            } elseif ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['dat_description']), ENT_QUOTES, 'UTF-8');
            } else {
                $value = $this->dbColumns['dat_description'];
            }
        } else {
            $value = parent::getValue($columnName, $format);
        }

        if ($format !== 'database') {
            if ($columnName === 'dat_country' && $value) {
                // read out the language-dependent designation for the country
                $value = $gL10n->getCountryName($value);
            } elseif ($columnName === 'cat_name') {
                // if text is a translation-id then translate it
                $value = Language::translateIfTranslationStrId($value);
            }
        }

        return $value;
    }

    /**
     * This method reads the deadline for participation. If no deadline is set as default the start date of the event will be set.
     * @return string Returns a string with formatted date and time
     * @throws Exception
     */
    public function getValidDeadline(): string
    {
        global $gSettingsManager;

        if ((string) $this->getValue('dat_deadline') === '') {
            $validDeadline = $this->getValue('dat_begin');
        } else {
            $validDeadline = $this->getValue('dat_deadline');
        }

        $objDateDeadline = \DateTime::createFromFormat($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'), $validDeadline);

        return $objDateDeadline->format('Y-m-d H:i:s');
    }

    /**
     * This method checks if the current user is allowed to edit this event. Therefore, the event
     * must be of the current organization. The user must have the right to administrate events or
     * must be a member of at least one role that have the right to manage events and the event
     * was created by the current user. Global events could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this event
     * @throws Exception
     */
    public function isEditable(): bool
    {
        global $gCurrentOrganization, $gCurrentUser, $gCurrentOrgId;

        if ($gCurrentUser->isAdministratorEvents()
            || (in_array((int)$this->getValue('cat_id'), $gCurrentUser->getAllEditableCategories('EVT'), true)
                && $gCurrentUser->getValue('usr_id') === $this->getValue('dat_usr_id_create'))
        ) {
            // if category belongs to current organization than events are editable
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
     * This method checks if the current user is allowed to view this event. Therefore,
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this event
     * @throws Exception
     */
    public function isVisible(): bool
    {
        global $gCurrentUser;

        // check if the current user could view the category of the event
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('EVT'), true);
    }

    /**
     * Method will return true if the event has a maximum count of participants set and this limit
     * is reached.
     * @return bool Return **true** if the limit of participants is reached.
     * @throws Exception
     */
    public function participantLimitReached(): bool
    {
        if(!is_object($this->mParticipants)) {
            $this->mParticipants = new Participants($this->db, $this->getValue('dat_rol_id'));
        }

        if ((int) $this->getValue('dat_max_members') > 0
            && (int) $this->getValue('dat_max_members') <= $this->mParticipants->getCount()) {
            return true;
        }
        return false;
    }

    /**
     * Read an event that has the given role has stored as participant role.
     * @param int $roleId ID of the participants role of the event.
     * @throws Exception
     */
    public function readDataByRoleId(int $roleId): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        // add id to sql condition
        if ($roleId > 0) {
            // call method to read data out of database
            return $this->readData(' AND dat_rol_id = ? ', array($roleId));
        }

        return false;
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

        if (!$this->saveChangesWithoutRights && !in_array((int) $this->getValue('dat_cat_id'), $gCurrentUser->getAllEditableCategories('EVT'), true)) {
            throw new Exception('Event could not be saved because you are not allowed to edit events of this category.');
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Send a notification email that a new event was created or an existing event was changed
     * to all members of the notification role. This role is configured within the global preference
     * **system_notifications_role**. The email contains the event title, date, time, location, room,
     * the name of the current user, timestamp and the url to this event.
     * @return bool Returns **true** if the notification was sent
     * @throws Exception 'SYS_EMAIL_NOT_SEND'
     * @throws Exception
     */
    public function sendNotification(): bool
    {
        global $gCurrentOrganization, $gCurrentUser, $gSettingsManager, $gL10n, $gDb;

        if ($gSettingsManager->getBool('system_notifications_new_entries')) {
            // read calendar information because new events doesn't have them in this object
            $sqlCal = 'SELECT cat_name
                         FROM '.TBL_CATEGORIES.'
                        WHERE cat_id = ?';
            $pdoStatement = $gDb->queryPrepared($sqlCal, array((int) $this->getValue('dat_cat_id')));
            $calendar = $pdoStatement->fetchColumn();
            if (Language::isTranslationStringId($calendar)) {
                $calendar = $gL10n->get($calendar);
            }

            if ((string) $this->getValue('dat_location') !== '') {
                $location = $this->getValue('dat_location');
            } else {
                $location = 'n/a';
            }

            if((int) $this->getValue('dat_room_id') > 0) {
                // read room name from database
                $sqlCal = 'SELECT room_name
                             FROM ' . TBL_ROOMS . '
                            WHERE room_id = ?';
                $pdoStatement = $gDb->queryPrepared($sqlCal, array((int) $this->getValue('dat_room_id')));
                $room = $pdoStatement->fetchColumn();
            } else {
                $room = 'n/a';
            }

            if ((string) $this->getValue('dat_max_members') !== '') {
                $participants = $this->getValue('dat_max_members');
            } else {
                $participants = 'n/a';
            }

            $notification = new Email();

            if ($this->isNewRecord()) {
                $messageTitleText = 'SYS_EVENT_CREATED_TITLE';
                $messageUserText = 'SYS_CREATED_BY';
                $messageDateText = 'SYS_CREATED_AT';
            } else {
                $messageTitleText = 'SYS_EVENT_CHANGED_TITLE';
                $messageUserText = 'SYS_CHANGED_BY';
                $messageDateText = 'SYS_CHANGED_AT';
            }
            $message = $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))) . '<br /><br />'
                . $gL10n->get('SYS_TITLE') . ': ' . $_POST['dat_headline'] . '<br />'
                . $gL10n->get('SYS_DATE') . ': ' . $this->getDateTimePeriod() . '<br />'
                . $gL10n->get('SYS_CALENDAR') . ': ' . $calendar . '<br />'
                . $gL10n->get('SYS_VENUE') . ': ' . $location . '<br />'
                . $gL10n->get('SYS_ROOM') . ': ' . $room . '<br />'
                . $gL10n->get('SYS_PARTICIPANTS') . ': ' . $participants . '<br />'
                . $gL10n->get($messageUserText) . ': ' . $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME') . '<br />'
                . $gL10n->get($messageDateText) . ': ' . date($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />'
                . $gL10n->get('SYS_URL') . ': ' . ADMIDIO_URL . FOLDER_MODULES . '/events/events.php?dat_uuid=' . $this->getValue('dat_uuid') . '<br />';
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
            if ($columnName === 'dat_description') {
                return parent::setValue($columnName, $newValue, false);
            } elseif ($columnName === 'dat_cat_id') {
                $category = new Category($this->db);
                if(!$category->readDataById($newValue)) {
                    throw new Exception('No Category with the given id '. $newValue. ' was found in the database.');
                }
            } elseif ($columnName === 'dat_deadline' && (string) $newValue !== '') {
                if(!\DateTime::createFromFormat('Y-m-d H:i', $newValue)) {
                    throw new Exception('SYS_DATE_INVALID', array($gL10n->get('SYS_DEADLINE'), 'YYYY-MM-DD'));
                } elseif (strtotime($newValue) > strtotime($this->getValue('dat_begin'))) {
                    throw new Exception('SYS_DEADLINE_AFTER_START');
                }
            }
        }

        if ($columnName === 'dat_end' && (int) $this->getValue('dat_all_day') === 1) {
            // for full day appointments, the end date must be the last second of the day
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $newValue);
            $newValue = $dateTime->format('Y-m-d') . ' 23:59:59';
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
