<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_dates
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableDate
 * Diese Klasse dient dazu ein Terminobjekt zu erstellen.
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 */
class TableDate extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_dates.
     * If the id is set than the specific date will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int      $datId    The recordset of the date with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $datId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');

        parent::__construct($database, TBL_DATES, 'dat', $datId);
    }

    /**
     * Check if the current user is allowed to participate to this event.
     * Therefore we check if the user is member of a role that is assigned to
     * the right event_participation.
     * @return bool Return true if the current user is allowed to participate to the event.
     */
    public function allowedToParticipate()
    {
        global $gCurrentUser;

        if($this->getValue('dat_rol_id') > 0)
        {
            $eventParticipationRoles = new RolesRights($this->db, 'event_participation', $this->getValue('dat_id'));

            if(count(array_intersect($gCurrentUser->getRoleMemberships(), $eventParticipationRoles->getRolesIds())) > 0)
            {
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
     * @return bool @b true if no error occurred
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
        if ($datRoleId > 0)
        {
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
     * This method checks if the current user is allowed to edit this event. Therefore
     * the event must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage events.
     * Global events could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this event
     */
    public function editable()
    {
        global $gCurrentOrganization, $gCurrentUser;

        if($this->visible() && $gCurrentUser->editDates())
        {
            if ($gCurrentOrganization->countAllRecords() === 1)
            {
                return true;
            }

            // parent organizations could edit global events,
            // child organizations could only edit their own events
            if ($gCurrentOrganization->isParentOrganization()
            || ($gCurrentOrganization->isChildOrganization() && (int) $gCurrentOrganization->getValue('org_id') == (int) $this->getValue('cat_org_id')))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $text
     * @return string
     */
    private function escapeIcalText($text)
    {
        $searchReplace = array(
            '\\'   => '\\\\',
            ','    => '\,',
            ';'    => '\;',
            "\r\n" => "\n"
        );

        return trim(str_replace(array_keys($searchReplace), array_values($searchReplace), $text));
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

        if ($this->getValue('dat_timestamp_change') !== null)
        {
            $iCalVEvent[] = 'LAST-MODIFIED:' . $this->getValue('dat_timestamp_change', $dateTimeFormat);
        }

        // Semicolons herausfiltern
        $iCalVEvent[] = 'UID:' . $this->getValue('dat_timestamp_create', $dateTimeFormat) . '+' . $this->getValue('dat_usr_id_create') . '@' . $domain;
        $iCalVEvent[] = 'SUMMARY:' . $this->escapeIcalText($this->getValue('dat_headline'));
        $iCalVEvent[] = 'DESCRIPTION:' . $this->escapeIcalText($this->getValue('dat_description', 'database'));
        $iCalVEvent[] = 'DTSTAMP:' . date($dateTimeFormat);
        $iCalVEvent[] = 'LOCATION:' . $this->escapeIcalText($this->getValue('dat_location'));

        if ((int) $this->getValue('dat_all_day') === 1)
        {
            // das Ende-Datum bei mehrtaegigen Terminen muss im iCal auch + 1 Tag sein
            // Outlook und Co. zeigen es erst dann korrekt an
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $this->getValue('dat_end', 'Y-m-d H:i:s'));
            $oneDayOffset = new DateInterval('P1D');

            $iCalVEvent[] = 'DTSTART;VALUE=DATE:' . $this->getValue('dat_begin', 'Ymd');
            $iCalVEvent[] = 'DTEND;VALUE=DATE:' . $dateTime->add($oneDayOffset)->format('Ymd');
        }
        else
        {
            $iCalVEvent[] = 'DTSTART;TZID=' . date_default_timezone_get() . ':' . $this->getValue('dat_begin', $dateTimeFormat);
            $iCalVEvent[] = 'DTEND;TZID='   . date_default_timezone_get() . ':' . $this->getValue('dat_end',   $dateTimeFormat);
        }

        $iCalVEvent[] = 'END:VEVENT';

        return implode("\r\n", $iCalVEvent) . "\r\n";
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be
     *                           the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return
     *                           the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if ($columnName === 'dat_end' && (int) $this->dbColumns['dat_all_day'] === 1)
        {
            if ($format === '')
            {
                $format = 'Y-m-d';
            }

            // bei ganztaegigen Terminen wird das Enddatum immer 1 Tag zurueckgesetzt
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $this->dbColumns['dat_end']);
            $oneDayOffset = new DateInterval('P1D');
            $value = $dateTime->sub($oneDayOffset)->format($format);
        }
        elseif ($columnName === 'dat_description')
        {
            if (!isset($this->dbColumns['dat_description']))
            {
                $value = '';
            }
            elseif ($format === 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['dat_description']), ENT_QUOTES, 'UTF-8');
            }
            else
            {
                $value = $this->dbColumns['dat_description'];
            }
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        if ($format !== 'database')
        {
            if ($columnName === 'dat_country' && $value !== '')
            {
                // beim Land die sprachabhaengige Bezeichnung auslesen
                $value = $gL10n->getCountryByCode($value);
            }
            elseif ($columnName === 'cat_name')
            {
                // if text is a translation-id then translate it
                if (strpos($value, '_') === 3)
                {
                    $value = $gL10n->get(admStrToUpper($value));
                }
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
        global $gPreferences;

        if ($this->getValue('dat_deadline') == null)
        {
            $validDeadline = $this->getValue('dat_begin');
        }
        else
        {
            $validDeadline = $this->getValue('dat_deadline');
        }

        $objDateDeadline = DateTime::createFromFormat($gPreferences['system_date'].' '.$gPreferences['system_time'], $validDeadline);

        return $objDateDeadline->format('Y-m-d H:i:s');
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if ($columnName === 'dat_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }

        if ($columnName === 'dat_end' && (int) $this->getValue('dat_all_day') === 1)
        {
            // hier muss bei ganztaegigen Terminen das bis-Datum um einen Tag hochgesetzt werden
            // damit der Termin bei SQL-Abfragen richtig beruecksichtigt wird
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $newValue);
            $oneDayOffset = new DateInterval('P1D');
            $newValue = $dateTime->add($oneDayOffset)->format('Y-m-d H:i:s');
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * This method checks if the current user is allowed to view this event. Therefore
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this event
     */
    public function visible()
    {
        global $gCurrentUser;

        // check if the current user could view the category of the event
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('DAT'), true);
    }
}
