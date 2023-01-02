<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class reads date recordsets from database
 *
 * This class reads all available recordsets from table dates.
 * and returns an Array with results, recordsets and validated parameters from $_GET Array.
 *
 * **Returned array:**
 * ```
 * array(
 *         [numResults] => 1
 *         [limit] => 10
 *         [totalCount] => 1
 *         [recordset] => Array
 *         (
 *             [0] => Array
 *                 (
 *                     [0] => 10
 *                     [cat_id] => 10
 *                     [1] => 1
 *                     [cat_org_id] => 1
 *                     [2] => DAT
 *                     [cat_type] => DAT
 *                     [3] => COMMON
 *                     [cat_name_intern] => COMMON
 *                     [4] => Allgemein
 *                     [cat_name] => Allgemein
 *                     [6] => 0
 *                     [cat_system] => 0
 *                     [7] => 0
 *                     [cat_default] => 0
 *                     [8] => 1
 *                     [cat_sequence] => 1
 *                     [9] => 1
 *                     [cat_usr_id_create] => 1
 *                     [10] => 2012-01-08 11:12:05
 *                     [cat_timestamp_create] => 2012-01-08 11:12:05
 *                     [11] =>
 *                     [cat_usr_id_change] =>
 *                     [12] =>
 *                     [cat_timestamp_change] =>
 *                     [13] => 9
 *                     [dat_id] => 9
 *                     [14] => 10
 *                     [dat_cat_id] => 10
 *                     [15] =>
 *                     [dat_rol_id] =>
 *                     [16] =>
 *                     [dat_room_id] =>
 *                     [18] => 2013-09-21 21:00:00
 *                     [dat_begin] => 2013-09-21 21:00:00
 *                     [19] => 2013-09-21 22:00:00
 *                     [dat_end] => 2013-09-21 22:00:00
 *                     [20] => 0
 *                     [dat_all_day] => 0
 *                     [21] => 0
 *                     [dat_highlight] => 0
 *                     [22] =>
 *                     [dat_description] =>
 *                     [23] =>
 *                     [dat_location] =>
 *                     [24] =>
 *                     [dat_country] =>
 *                     [25] => eet
 *                     [dat_headline] => eet
 *                     [26] => 0
 *                     [dat_max_members] => 0
 *                     [27] => 1
 *                     [dat_usr_id_create] => 1
 *                     [28] => 2013-09-20 21:56:23
 *                     [dat_timestamp_create] => 2013-09-20 21:56:23
 *                     [29] =>
 *                     [dat_usr_id_change] =>
 *                     [30] =>
 *                     [dat_timestamp_change] =>
 *                     [31] =>
 *                     [member_date_role] =>
 *                     [32] =>
 *                     [mem_leader] =>
 *                     [33] => Paul Smith
 *                     [create_name] => Paul Smith
 *                     [34] =>
 *                     [change_name] =>
 *                 )
 *
 *         )
 *
 *     [parameter] => Array
 *         (
 *             [active_role] => 1
 *             [calendar-selection] => 1
 *             [cat_id] => 0
 *             [category-selection] => 0,
 *             [date] =>
 *             [daterange] => Array
 *                 (
 *                     [english] => Array
 *                         (
 *                             [start_date] => 2013-09-21
 *                             [end_date] => 9999-12-31
 *                         )
 *
 *                     [system] => Array
 *                         (
 *                             [start_date] => 21.09.2013
 *                             [end_date] => 31.12.9999
 *                         )
 *
 *                 )
 *
 *             [headline] => Termine
 *             [id] => 0
 *             [mode] => actual
 *             [order] => ASC
 *             [startelement] => 0
 *             [view_mode] => html
 *         )
 *
 * )
 * ```
 */
class ModuleDates extends Modules
{
    public const MEMBER_APPROVAL_STATE_INVITED   = 0;
    public const MEMBER_APPROVAL_STATE_TENTATIVE = 1;
    public const MEMBER_APPROVAL_STATE_ATTEND    = 2;
    public const MEMBER_APPROVAL_STATE_REFUSED   = 3;

    /**
     * @var array An array with all names of the calendars whose events should be shown
     */
    protected $calendarNames = array();

    /**
     * Constructor that will create an object of a parameter set needed in modules to get the recordsets.
     * Initialize parameters
     */
    public function __construct()
    {
        parent::__construct();

        $this->setParameter('mode', 'actual');
    }

    /**
     * SQL query returns an array with available dates.
     * @param int $startElement Defines the offset of the query (default: 0)
     * @param int $limit        Limit of query rows (default: 0)
     * @return array<string,mixed> Array with all results, dates and parameters.
     */
    public function getDataSet($startElement = 0, $limit = null)
    {
        global $gDb, $gSettingsManager, $gCurrentUser;

        if ($limit === null) {
            $limit = $gSettingsManager->getInt('dates_per_page');
        }

        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('DAT'));
        $additional = $this->sqlGetAdditional();
        $sqlConditions = $this->getSqlConditions();

        // read dates from database
        $sql = 'SELECT DISTINCT cat.*, dat.*, rol_uuid, mem.mem_usr_id AS member_date_role, mem.mem_approved AS member_approval_state,
                       mem.mem_leader, mem.mem_comment AS comment, mem.mem_count_guests AS additional_guests,' . $additional['fields'] . '
                  FROM ' . TBL_DATES . ' AS dat
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat_id = dat_cat_id
             LEFT JOIN ' . TBL_ROLES . ' AS rol
                    ON rol_id = dat_rol_id
                       ' . $additional['tables'] . '
             LEFT JOIN ' . TBL_MEMBERS . ' AS mem
                    ON mem.mem_rol_id = dat_rol_id
                   AND mem.mem_usr_id = ? -- $gCurrentUserId
                   AND mem.mem_begin <= ? -- DATE_NOW
                   AND mem.mem_end    > ? -- DATE_NOW
                 WHERE cat_id IN ('.Database::getQmForValues($catIdParams).')
                       ' . $sqlConditions['sql'] . '
              ORDER BY dat_begin ' . $this->order;

        // Parameter
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($startElement > 0) {
            $sql .= ' OFFSET ' . $startElement;
        }

        $queryParams = array_merge(
            $additional['params'],
            array(
                $GLOBALS['gCurrentUserId'],
                DATE_NOW,
                DATE_NOW
            ),
            $catIdParams,
            $sqlConditions['params']
        );
        $pdoStatement = $gDb->queryPrepared($sql, $queryParams); // TODO add more params

        // array for results
        return array(
            'recordset'  => $pdoStatement->fetchAll(),
            'numResults' => $pdoStatement->rowCount(),
            'limit'      => $limit,
            'totalCount' => $this->getDataSetCount()
        );
    }

    /**
     * Get number of available dates.
     * @return int
     */
    public function getDataSetCount()
    {
        global $gDb, $gCurrentUser;

        if ($this->id > 0) {
            return 1;
        }

        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('DAT'));
        $sqlConditions = $this->getSqlConditions();

        $sql = 'SELECT COUNT(DISTINCT dat_id) AS count
                  FROM ' . TBL_DATES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = dat_cat_id
                 WHERE cat_id IN ('.Database::getQmForValues($catIdParams).')
                       '. $sqlConditions['sql'];

        $statement = $gDb->queryPrepared($sql, array_merge($catIdParams, $sqlConditions['params']));

        return (int) $statement->fetchColumn();
    }

    /**
     * Returns a module specific headline
     * @param string $headline The initial headline of the module.
     * @return string Returns the full headline of the module
     */
    public function getHeadline($headline)
    {
        global $gDb, $gL10n, $gCurrentOrganization;

        // set headline with category name
        if ($this->getParameter('cat_id') > 0) {
            $category  = new TableCategory($gDb, $this->getParameter('cat_id'));
            $headline .= ' - ' . $category->getValue('cat_name');
        }

        // check time period if old dates are chosen, then set headline to previous dates
        // Define a prefix
        if ($this->getParameter('mode') === 'old'
        ||    ($this->getParameter('dateStartFormatEnglish') < DATE_NOW
            && $this->getParameter('dateEndFormatEnglish')   < DATE_NOW)) {
            $headline = $gL10n->get('DAT_PREVIOUS_DATES', array('')) . $headline;
        }

        if ($this->getParameter('view_mode') === 'print') {
            $headline = $gCurrentOrganization->getValue('org_longname') . ' - ' . $headline;
        }

        return $headline;
    }

    /**
     * Add several conditions to an SQL string that could later be used as additional conditions in other SQL queries.
     * @return array<string,string|array<int,mixed>> Returns an array of a SQL string with additional conditions and it's query params.
     */
    private function getSqlConditions()
    {
        global $gCurrentUser;

        $sqlConditions = '';
        $params = array();

        $uuid = $this->getParameter('dat_uuid');
        // In case ID was permitted and user has rights
        if (!empty($uuid)) {
            $sqlConditions .= ' AND dat_uuid = ? '; // $id
            $params[] = $uuid;
        }
        // ...otherwise get all additional events for a group
        else {
            if (!$this->getParameter('dateStartFormatEnglish')) {
                $this->setDateRange(); // TODO Exception handling
            }

            // add 1 second to end date because full time events to until next day
            $sqlConditions .= ' AND dat_begin <= ? AND dat_end >= ? '; // $this->getParameter('dateEndFormatEnglish') . ' 23:59:59' AND $this->getParameter('dateStartFormatEnglish') . ' 00:00:00'
            $params[] = $this->getParameter('dateEndFormatEnglish')   . ' 23:59:59';
            $params[] = $this->getParameter('dateStartFormatEnglish') . ' 00:00:00';

            $catId = (int) $this->getParameter('cat_id');
            // show all events from category
            if ($catId > 0) {
                $sqlConditions .= ' AND cat_id = ? '; // $catId
                $params[] = $catId;
            }
        }

        // add conditions for role permission
        if ($GLOBALS['gCurrentUserId'] > 0) {
            switch ($this->getParameter('show')) {
                case 'maybe_participate':
                    $roleMemberships = $gCurrentUser->getRoleMemberships();
                    $sqlConditions .= '
                        AND dat_rol_id IS NOT NULL
                        AND EXISTS (SELECT 1
                                      FROM '. TBL_ROLES_RIGHTS .'
                                INNER JOIN '. TBL_ROLES_RIGHTS_DATA .'
                                        ON rrd_ror_id = ror_id
                                     WHERE ror_name_intern = \'event_participation\'
                                       AND rrd_object_id = dat_id
                                       AND rrd_rol_id IN ('.Database::getQmForValues($roleMemberships).')) ';
                    $params = array_merge($params, $roleMemberships);
                    break;

                case 'only_participate':
                    $sqlConditions .= '
                        AND dat_rol_id IS NOT NULL
                        AND dat_rol_id IN (SELECT mem_rol_id
                                             FROM ' . TBL_MEMBERS . ' AS mem2
                                            WHERE mem2.mem_usr_id = ? -- $GLOBALS[\'gCurrentUserId\']
                                              AND mem2.mem_begin <= dat_begin
                                              AND mem2.mem_end   >= dat_end) ';
                    $params[] = $GLOBALS['gCurrentUserId'];
                    break;
            }
        }

        // add valid calendars
        if (count($this->calendarNames) > 0) {
            $sqlConditions .= ' AND cat_name IN (\''. implode('', $this->calendarNames) . '\')';
        }

        return array(
            'sql'    => $sqlConditions,
            'params' => $params
        );
    }

    /**
     * Method will set an array with all names of the calendars whose events should be shown
     * @param array $arrCalendarNames An array with all names of the calendars whose events should be shown
     */
    public function setCalendarNames($arrCalendarNames)
    {
        $this->calendarNames = $arrCalendarNames;
    }

    /**
     * Set a date range in which the dates should be searched. The method will fill
     * 4 parameters **dateStartFormatEnglish**, **dateStartFormatEnglish**,
     * **dateEndFormatEnglish** and **dateEndFormatAdmidio** that could be read with
     * getParameter and could be used in the script.
     * @param string $dateRangeStart A date in english or Admidio format that will be the start date of the range.
     * @param string $dateRangeEnd   A date in english or Admidio format that will be the end date of the range.
     * @throws AdmException SYS_DATE_END_BEFORE_BEGIN
     * @return bool Returns false if invalid date format is submitted
     */
    public function setDateRange($dateRangeStart = '', $dateRangeEnd = '')
    {
        global $gSettingsManager;

        if ($dateRangeStart === '') {
            $dateStart = '1970-01-01';
            $dateEnd   = (date('Y') + 10) . '-12-31';

            // set date_from and date_to regarding to current mode
            switch ($this->mode) {
                case 'actual':
                    $dateRangeStart = DATE_NOW;
                    $dateRangeEnd   = $dateEnd;
                    break;
                case 'old':
                    $dateRangeStart = $dateStart;
                    $dateRangeEnd   = DATE_NOW;
                    break;
                case 'all':
                    $dateRangeStart = $dateStart;
                    $dateRangeEnd   = $dateEnd;
                    break;
            }
        }
        // If mode=old then we want to have the events in reverse order ('DESC')
        if ($this->mode === 'old') {
            $this->order = 'DESC';
        }

        // Create date object and format date_from in English format and system format and push to daterange array
        $objDateFrom = \DateTime::createFromFormat('Y-m-d', $dateRangeStart);

        if ($objDateFrom === false) {
            // check if date_from has system format
            $objDateFrom = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $dateRangeStart);
        }

        if ($objDateFrom === false) {
            return false;
        }

        $this->setParameter('dateStartFormatEnglish', $objDateFrom->format('Y-m-d'));
        $this->setParameter('dateStartFormatAdmidio', $objDateFrom->format($gSettingsManager->getString('system_date')));

        // Create date object and format date_to in English format and system format and push to daterange array
        $objDateTo = \DateTime::createFromFormat('Y-m-d', $dateRangeEnd);

        if ($objDateTo === false) {
            // check if date_from  has system format
            $objDateTo = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $dateRangeEnd);
        }

        if ($objDateTo === false) {
            return false;
        }

        $this->setParameter('dateEndFormatEnglish', $objDateTo->format('Y-m-d'));
        $this->setParameter('dateEndFormatAdmidio', $objDateTo->format($gSettingsManager->getString('system_date')));

        // DateTo should be greater than DateFrom (Timestamp must be less)
        if ($objDateFrom->getTimestamp() > $objDateTo->getTimestamp()) {
            throw new AdmException('SYS_DATE_END_BEFORE_BEGIN');
        }

        return true;
    }

    /**
     * Get additional tables for sql statement
     * @return array<string,string|array<int,int>> Returns an array of a SQL string with the necessary joins and it's query params.
     */
    private function sqlGetAdditional()
    {
        global $gSettingsManager, $gProfileFields;

        if ((int) $gSettingsManager->get('system_show_create_edit') === 1) {
            $lastNameUsfId  = (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id');
            $firstNameUsfId = (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id');

            // show firstname and lastname of create and last change user
            $additionalFields = '
                cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name,
                cha_firstname.usd_value || \' \' || cha_surname.usd_value AS change_name,
                cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid ';
            $additionalTables = '
                LEFT JOIN ' . TBL_USERS . ' AS cre_user
                       ON cre_user.usr_id = dat_usr_id_create
                LEFT JOIN '.TBL_USER_DATA.' AS cre_surname
                       ON cre_surname.usd_usr_id = dat_usr_id_create
                      AND cre_surname.usd_usf_id = ? -- $lastNameUsfId
                LEFT JOIN '.TBL_USER_DATA.' AS cre_firstname
                       ON cre_firstname.usd_usr_id = dat_usr_id_create
                      AND cre_firstname.usd_usf_id = ? -- $firstNameUsfId
                LEFT JOIN ' . TBL_USERS . ' AS cha_user
                       ON cha_user.usr_id = dat_usr_id_change
                LEFT JOIN '.TBL_USER_DATA.' AS cha_surname
                       ON cha_surname.usd_usr_id = dat_usr_id_change
                      AND cha_surname.usd_usf_id = ? -- $lastNameUsfId
                LEFT JOIN '.TBL_USER_DATA.' AS cha_firstname
                       ON cha_firstname.usd_usr_id = dat_usr_id_change
                      AND cha_firstname.usd_usf_id = ? -- $firstNameUsfId';
            $additionalParams = array($lastNameUsfId, $firstNameUsfId, $lastNameUsfId, $firstNameUsfId);
        } else {
            // show username of create and last change user
            $additionalFields = '
                cre_user.usr_login_name AS create_name,
                cha_user.usr_login_name AS change_name,
                cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid ';
            $additionalTables = '
                LEFT JOIN '.TBL_USERS.' AS cre_user
                       ON cre_user.usr_id = dat_usr_id_create
                LEFT JOIN '.TBL_USERS.' AS cha_user
                       ON cha_user.usr_id = dat_usr_id_change ';
            $additionalParams = array();
        }

        return array(
            'fields' => $additionalFields,
            'tables' => $additionalTables,
            'params' => $additionalParams
        );
    }
}
