<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates an event object from the database table adm_dates
 *
 * With the given id an event object is created from the data in the database table **adm_dates**.
 * The class will handle the communication with the database and give easy access to the data. New
 * event could be created or existing event could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * **Code examples**
 * ```
 * // get data from an existing event
 * $event       = new TableDate($gDb, $dateId);
 * $headline    = $event->getValue('dat_headline');
 * $description = $event->getValue('dat_description');
 *
 * // change existing event
 * $event = new TableDate($gDb, $dateId);
 * $event->setValue('dat_headline', 'My new headling');
 * $event->setValue('dat_description', 'This is the new description.');
 * $event->save();
 *
 * // create new event
 * $event = new TableDate($gDb);
 * $event->setValue('dat_headline', 'My new headling');
 * $event->setValue('dat_description', 'This is the new description.');
 * $event->save();
 * ```
 */
class TableDate extends TableAccess
{
    /**
     * @var Participants object to handle all participants of this event
     */
    private $mParticipants;

    /**
     * Constructor that will create an object of a recordset of the table adm_dates.
     * If the id is set than the specific date will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $datId    The recordset of the date with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $datId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');

        parent::__construct($database, TBL_DATES, 'dat', $datId);
    }

    /**
     * Check if the current user is allowed to participate in this event.
     * Therefore, we check if the user is member of a role that is assigned to
     * the right event_participation. This method will also return **true** if the deadline is exceeded
     * and a further participation isn't possible.
     * @return bool Return true if the current user is allowed to participate in the event.
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
     */
    public function deadlineExceeded()
    {
        return $this->getValidDeadline() < DATETIME_NOW;
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        $datId     = (int) $this->getValue('dat_id');
        $datRoleId = (int) $this->getValue('dat_rol_id');

        $this->db->startTransaction();

        // delete all roles assignments that could participate to the event
        $eventParticipationRoles = new RolesRights($this->db, 'event_participation', $datId);
        $eventParticipationRoles->delete();

        // if date has participants then the role with their memberships must be deleted
        if ($datRoleId > 0) {
            $sql = 'UPDATE '.TBL_DATES.'
                       SET dat_rol_id = NULL
                     WHERE dat_id = ? -- $datId';
            $this->db->queryPrepared($sql, array($datId));

            $dateRole = new TableRoles($this->db, $datRoleId);
            $dateRole->delete(); // TODO Exception handling
        }

        // now delete event
        parent::delete();

        return $this->db->endTransaction();
    }

    /**
     * @param string $text
     * @return string
     */
    private function escapeIcalText($text)
    {
        $replaces = array(
            '\\' => '\\\\',
            ';'  => '\;',
            ','  => '\,',
            "\n" => '\n',
            "\r" => '',
            '<br />' => '\n' // workaround
        );

        return trim(StringUtils::strMultiReplace($text, $replaces));
    }

    /**
     * This Method will return a string with the date and time period of the current event.
     * If the begin and end of the date is at the same day than the date will only included once.
     * Also the all-day flag will be considered.
     * @return string Returns a formated date and time string corresponding to the event settings.
     */
    public function getDateTimePeriod($showPeriodEnd = true)
    {
        global $gSettingsManager;

        $beginDate = $this->getValue('dat_begin', $gSettingsManager->getString('system_date')). ' ';
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
     * gibt einen Termin im iCal-Format zurueck
     * @param string $domain
     * @return string
     */
    public function getIcal($domain)
    {
        $iCal = $this->getIcalHeader().
                $this->getIcalVEvent($domain).
                $this->getIcalFooter();

        return $iCal;
    }

    /**
     * gibt den Kopf eines iCalCalenders aus
     * @return string
     */
    public function getIcalHeader()
    {
        $defaultTimezone = date_default_timezone_get();

        $icalHeader = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//www.admidio.org//Admidio' . ADMIDIO_VERSION . '//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-TIMEZONE:' . $defaultTimezone,
            'BEGIN:VTIMEZONE',
            'TZID:' . $defaultTimezone,
            'X-LIC-LOCATION:' . $defaultTimezone,
            'BEGIN:STANDARD',
            'DTSTART:19701025T030000',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10',
            'END:STANDARD',
            'BEGIN:DAYLIGHT',
            'DTSTART:19700329T020000',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3',
            'END:DAYLIGHT',
            'END:VTIMEZONE'
        );

        return implode("\r\n", $icalHeader) . "\r\n";
    }

    /**
     * gibt den Fuß eines iCalCalenders aus
     * @return string
     */
    public function getIcalFooter()
    {
        return 'END:VCALENDAR';
    }

    /**
     * gibt einen einzelnen Termin im iCal-Format zurück
     * @param string $domain
     * @return string
     */
    public function getIcalVEvent($domain)
    {
        $dateTimeFormat = 'Ymd\THis';

        $iCalVEvent = array(
            'BEGIN:VEVENT',
            'CREATED:' . $this->getValue('dat_timestamp_create', $dateTimeFormat)
        );

        if ((string) $this->getValue('dat_timestamp_change') === '') {
            $iCalVEvent[] = 'LAST-MODIFIED:' . $this->getValue('dat_timestamp_create', $dateTimeFormat);
        }  else {
            $iCalVEvent[] = 'LAST-MODIFIED:' . $this->getValue('dat_timestamp_change', $dateTimeFormat);
        }

        // Semicolons herausfiltern
        $iCalVEvent[] = 'UID:' . $this->getValue('dat_timestamp_create', $dateTimeFormat) . '+' . (int) $this->getValue('dat_usr_id_create') . '@' . $domain;
        $iCalVEvent[] = 'SUMMARY:' . $this->escapeIcalText($this->getValue('dat_headline'));
        $iCalVEvent[] = 'DESCRIPTION:' . StringUtils::strStripTags($this->escapeIcalText(html_entity_decode($this->getValue('dat_description'), ENT_QUOTES, 'UTF-8')));
        $iCalVEvent[] = 'DTSTAMP:' . date($dateTimeFormat);
        $iCalVEvent[] = 'LOCATION:' . $this->escapeIcalText($this->getValue('dat_location'));

        if ((int) $this->getValue('dat_all_day') === 1) {
            // das Ende-Datum bei mehrtaegigen Terminen muss im iCal auch + 1 Tag sein
            // Outlook und Co. zeigen es erst dann korrekt an
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $this->getValue('dat_end', 'Y-m-d H:i:s'));
            $oneDayOffset = new \DateInterval('P1D');

            $iCalVEvent[] = 'DTSTART;VALUE=DATE:' . $this->getValue('dat_begin', 'Ymd');
            $iCalVEvent[] = 'DTEND;VALUE=DATE:' . $dateTime->add($oneDayOffset)->format('Ymd');
        } else {
            $iCalVEvent[] = 'DTSTART;TZID=' . date_default_timezone_get() . ':' . $this->getValue('dat_begin', $dateTimeFormat);
            $iCalVEvent[] = 'DTEND;TZID='   . date_default_timezone_get() . ':' . $this->getValue('dat_end', $dateTimeFormat);
        }

        $iCalVEvent[] = 'END:VEVENT';

        return implode("\r\n", $iCalVEvent) . "\r\n";
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be
     *                           the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return
     *                           the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
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
     * This function reads the deadline for participation. If no deadline is set as default the the startdate of the event will be set.
     * return string $dateDeadline Returns a string with formated date and time
     */
    public function getValidDeadline()
    {
        global $gSettingsManager;

        if ((string) $this->getValue('dat_deadline') === '') {
            $validDeadline = $this->getValue('dat_begin');
        } else {
            $validDeadline = $this->getValue('dat_deadline');
        }

        $objDateDeadline = \DateTime::createFromFormat($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'), $validDeadline);

        return $objDateDeadline->format('Y-m-d H:i:s');
    }

    /**
     * This method checks if the current user is allowed to edit this event. Therefore
     * the event must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage events.
     * Global events could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this event
     */
    public function isEditable()
    {
        global $gCurrentOrganization, $gCurrentUser;

        if ($gCurrentUser->editDates()
        || in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllEditableCategories('DAT'), true)) {
            // if category belongs to current organization than events are editable
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
     * This method checks if the current user is allowed to view this event. Therefore
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this event
     */
    public function isVisible()
    {
        global $gCurrentUser;

        // check if the current user could view the category of the event
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('DAT'), true);
    }

    /**
     * Method will return true if the event has a maximum count of participants set and this limit
     * is reached.
     * @return bool Return **true** if the limit of participants is reached.
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

    /* Read an event that has the given role has stored as participant role.
     * @param $roleId Id of the participants role of the event.
     */
    public function readDataByRoleId($roleId)
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

        if (!$this->saveChangesWithoutRights && !in_array((int) $this->getValue('dat_cat_id'), $gCurrentUser->getAllEditableCategories('DAT'), true)) {
            throw new AdmException('Event could not be saved because you are not allowed to edit events of this category.');
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
     * @throws AdmException
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gL10n;

        if ($checkValue) {
            if ($columnName === 'dat_description') {
                return parent::setValue($columnName, $newValue, false);
            } elseif ($columnName === 'dat_cat_id') {
                $category = new TableCategory($this->db);
                if(!$category->readDataById($newValue)) {
                    throw new AdmException('No Category with the given id '. $newValue. ' was found in the database.');
                }
            } elseif ($columnName === 'dat_deadline' && (string) $newValue !== '') {
                if(!DateTime::createFromFormat('Y-m-d H:i', $newValue)) {
                    throw new AdmException($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('DAT_DEADLINE'), 'YYYY-MM-DD')));
                } elseif (strtotime($newValue) > strtotime($this->getValue('dat_begin'))) {
                    throw new AdmException('SYS_DEADLINE_AFTER_START');
                }
            }
        }

        if ($columnName === 'dat_end' && (int) $this->getValue('dat_all_day') === 1) {
            // for full day appointments, the end date must be the last second of the day
            $dateTime = DateTime::createFromFormat('Y-m-d H:i', $newValue);
            $newValue = $dateTime->format('Y-m-d') . ' 23:59:59';
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
