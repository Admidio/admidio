<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ModuleAnnouncements
 * @brief This class reads announcement recordsets from database
 *
 * This class reads all available recordsets from table announcements
 * and returns an Array with results, recordsets and validated parameters from $_GET Array.
 * @par Returned Array
 * @code
 * Array
 * (
 *     [numResults] => 3
 *     [limit] => 10
 *     [totalCount] => 3
 *     [recordset] => Array
 *         (
 *             [0] => Array
 *                 (
 *                     [0] => 3
 *                     [ann_id] => 3
 *                     [1] => DEMO
 *                     [ann_cat_id] => 1
 *                     [2] => 1
 *                     [ann_global] => 1
 *                     [3] => Willkommen im Demobereich
 *                     [ann_headline] => Willkommen im Demobereich
 *                     [4] => <p>In diesem Bereich kannst du mit Admidio herumspielen und schauen, ....</p>
 *                     [ann_description] => <p>In diesem Bereich kannst du mit Admidio herumspielen und schauen, ....</p>
 *                     [5] => 1
 *                     [ann_usr_id_create] => 1
 *                     [6] => 2013-07-18 00:00:00
 *                     [ann_timestamp_create] => 2013-07-18 00:00:00
 *                     [7] =>
 *                     [ann_usr_id_change] =>
 *                     [8] =>
 *                     [ann_timestamp_change] =>
 *                     [9] => Paul Webmaster
 *                     [create_name] => Paul Webmaster
 *                     [10] =>
 *                     [change_name] =>
 *                 )
 *         )
 *     [parameter] => Array
 *         (
 *             [active_role] => 1
 *             [calendar-selection] => 1
 *             [cat_id] => 0
 *             [category-selection] => 0,
 *             [date] => ''
 *             [daterange] => Array
 *                 (
 *                     [english] => Array
 *                         (
 *                             [start_date] => 2013-09-16 // current date
 *                             [end_date] => 9999-12-31
 *                         )
 *
 *                     [system] => Array
 *                         (
 *                             [start_date] => 16.09.2013 // current date
 *                             [end_date] => 31.12.9999
 *                         )
 *                 )
 *             [headline] => Ankündigungen
 *             [id] => 0
 *             [mode] => Default
 *             [order] => 'ASC'
 *             [startelement] => 0
 *             [view_mode] => Default
 *         )
 * )
 * @endcode
 */
class ModuleAnnouncements extends Modules
{
    /**
     * Get number of available announcements
     * @Return int Returns the total count and push it in the array
     */
    public function getDataSetCount()
    {
        global $gCurrentOrganization, $gDb;

        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_ANNOUNCEMENTS.'
                  JOIN '.TBL_CATEGORIES.' ON cat_id = ann_cat_id
                 WHERE (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR (   ann_global = 1
                          AND cat_org_id IN ('.$gCurrentOrganization->getFamilySQL().') ))
                       '.$this->getSqlConditions();
        $pdoStatement = $gDb->query($sql);

        return (int) $pdoStatement->fetchColumn();
    }

    /**
     * Get all records and push it to the array
     * @param int $startElement
     * @param int $limit
     * @return array Returns the Array with results, recordsets and validated parameters from $_GET Array
     */
    public function getDataSet($startElement = 0, $limit = null)
    {
        global $gCurrentOrganization, $gPreferences, $gProfileFields, $gDb;

        if ($gPreferences['system_show_create_edit'] == 1)
        {
            // show firstname and lastname of create and last change user
            $additionalFields = '
                cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name,
                cha_firstname.usd_value || \' \' || cha_surname.usd_value AS change_name ';
            $additionalTables = '
                LEFT JOIN '.TBL_USER_DATA.' cre_surname
                       ON cre_surname.usd_usr_id = ann_usr_id_create
                      AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                LEFT JOIN '.TBL_USER_DATA.' cre_firstname
                       ON cre_firstname.usd_usr_id = ann_usr_id_create
                      AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                LEFT JOIN '.TBL_USER_DATA.' cha_surname
                       ON cha_surname.usd_usr_id = ann_usr_id_change
                      AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                LEFT JOIN '.TBL_USER_DATA.' cha_firstname
                       ON cha_firstname.usd_usr_id = ann_usr_id_change
                      AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
        }
        else
        {
            // show username of create and last change user
            $additionalFields = '
                cre_username.usr_login_name AS create_name,
                cha_username.usr_login_name AS change_name ';
            $additionalTables = '
                LEFT JOIN '.TBL_USERS.' cre_username
                       ON cre_username.usr_id = ann_usr_id_create
                LEFT JOIN '.TBL_USERS.' cha_username
                       ON cha_username.usr_id = ann_usr_id_change ';
        }

        // read announcements from database
        $sql = 'SELECT cat.*, ann.*, '.$additionalFields.'
                  FROM '.TBL_ANNOUNCEMENTS.' ann
                  JOIN '.TBL_CATEGORIES.' cat ON cat_id = ann_cat_id
                       '.$additionalTables.'
                 WHERE (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR (   ann_global = 1
                          AND cat_org_id IN ('.$gCurrentOrganization->getFamilySQL().') ))
                       '.$this->getSqlConditions().'
              ORDER BY ann_timestamp_create DESC';

        // Check if limit was set
        if ($limit > 0)
        {
            $sql .= ' LIMIT '.$limit;
        }
        if ($startElement > 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }

        $announcementsStatement = $gDb->query($sql);

        // array for results
        return array(
            'recordset'  => $announcementsStatement->fetchAll(),
            'numResults' => $announcementsStatement->rowCount(),
            'limit'      => $limit,
            'totalCount' => $this->getDataSetCount(),
            'parameter'  => $this->getParameters()
        );
    }

    /**
     * Prepare SQL Statement.
     * @return string
     */
    private function getSqlConditions()
    {
        global $gValidLogin, $gCurrentUser;

        $sqlConditions = '';

        // if user isn't logged in, then don't show hidden categories
        if (!$gValidLogin)
        {
            $sqlConditions .= ' AND cat_hidden = 0 ';
        }

        $id = $this->getParameter('id');
        // In case ID was permitted and user has rights
        if ($id > 0)
        {
            $sqlConditions .= ' AND ann_id = ' . $this->getParameter('id');
        }
        // ...otherwise get all additional announcements for a group
        else
        {
            // show all events from category
            if ($this->getParameter('cat_id') > 0)
            {
                // show all events from category
                $sqlConditions .= ' AND cat_id = ' . $this->getParameter('cat_id');
            }

            // Search announcements to date
            if ($this->getParameter('dateStartFormatEnglish'))
            {
                $sqlConditions = 'AND ann_timestamp_create BETWEEN \''.$this->getParameter('dateStartFormatEnglish').' 00:00:00\' AND \''.$this->getParameter('dateEndFormatEnglish').' 23:59:59\'';
            }
        }

        return $sqlConditions;
    }

    /**
     * Set a date range in which the dates should be searched. The method will fill
     * 4 parameters @b dateStartFormatEnglish, @b dateStartFormatEnglish,
     * @b dateEndFormatEnglish and @b dateEndFormatAdmidio that could be read with
     * getParameter and could be used in the script.
     * @param string $dateRangeStart A date in english or Admidio format that will be the start date of the range.
     * @param string $dateRangeEnd   A date in english or Admidio format that will be the end date of the range.
     * @return bool Returns false if invalid date format is submitted
     */
    public function setDateRange($dateRangeStart = '1970-01-01', $dateRangeEnd = DATE_NOW)
    {
        global $gPreferences;

        if (!$this->setDateRangeParams($dateRangeStart, 'Start', 'Y-m-d'))
        {
            if (!$this->setDateRangeParams($dateRangeStart, 'Start', $gPreferences['system_date']))
            {
                return false;
            }
        }

        if (!$this->setDateRangeParams($dateRangeEnd, 'End', 'Y-m-d'))
        {
            if (!$this->setDateRangeParams($dateRangeEnd, 'End', $gPreferences['system_date']))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $dateRange
     * @param string $dateRangePoint
     * @param string $dateFormat
     * @return bool
     */
    private function setDateRangeParams($dateRange, $dateRangePoint, $dateFormat)
    {
        global $gPreferences;

        $objDate = DateTime::createFromFormat($dateFormat, $dateRange);

        if ($objDate === false)
        {
            return false;
        }

        $this->setParameter('date' . $dateRangePoint . 'FormatEnglish', $objDate->format('Y-m-d'));
        $this->setParameter('date' . $dateRangePoint . 'FormatAdmidio', $objDate->format($gPreferences['system_date']));

        return true;
    }
}
