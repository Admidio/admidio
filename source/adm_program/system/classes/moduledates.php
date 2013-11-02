<?php
/*******************************************************************************/
/** @class ModuleDates
 *  @brief This class reads date recordsets from database
 *
 *  This class reads all available recordsets from table dates.
 *  and returns an Array with results, recordsets and validated parameters from $_GET Array.
 *  @par Returned Array
 *  @code
 *  array(
 *          [numResults] => 1
 *          [limit] => 10
 *          [totalCount] => 1
 *          [recordset] => Array
 *          (
 *              [0] => Array
 *                  (
 *                      [0] => 10
 *                      [cat_id] => 10
 *                      [1] => 1
 *                      [cat_org_id] => 1
 *                      [2] => DAT
 *                      [cat_type] => DAT
 *                      [3] => COMMON
 *                      [cat_name_intern] => COMMON
 *                      [4] => Allgemein
 *                      [cat_name] => Allgemein
 *                      [5] => 0
 *                      [cat_hidden] => 0
 *                      [6] => 0
 *                      [cat_system] => 0
 *                      [7] => 0
 *                      [cat_default] => 0
 *                      [8] => 1
 *                      [cat_sequence] => 1
 *                      [9] => 1
 *                      [cat_usr_id_create] => 1
 *                      [10] => 2012-01-08 11:12:05
 *                      [cat_timestamp_create] => 2012-01-08 11:12:05
 *                      [11] => 
 *                      [cat_usr_id_change] => 
 *                      [12] => 
 *                      [cat_timestamp_change] => 
 *                      [13] => 9
 *                      [dat_id] => 9
 *                      [14] => 10
 *                      [dat_cat_id] => 10
 *                      [15] => 
 *                      [dat_rol_id] => 
 *                      [16] => 
 *                      [dat_room_id] => 
 *                      [17] => 0
 *                      [dat_global] => 0
 *                      [18] => 2013-09-21 21:00:00
 *                      [dat_begin] => 2013-09-21 21:00:00
 *                      [19] => 2013-09-21 22:00:00
 *                      [dat_end] => 2013-09-21 22:00:00
 *                      [20] => 0
 *                      [dat_all_day] => 0
 *                      [21] => 0
 *                      [dat_highlight] => 0
 *                      [22] => 
 *                      [dat_description] => 
 *                      [23] => 
 *                      [dat_location] => 
 *                      [24] => 
 *                      [dat_country] => 
 *                      [25] => eet
 *                      [dat_headline] => eet
 *                      [26] => 0
 *                      [dat_max_members] => 0
 *                      [27] => 1
 *                      [dat_usr_id_create] => 1
 *                      [28] => 2013-09-20 21:56:23
 *                      [dat_timestamp_create] => 2013-09-20 21:56:23
 *                      [29] => 
 *                      [dat_usr_id_change] => 
 *                      [30] => 
 *                      [dat_timestamp_change] => 
 *                      [31] => 
 *                      [member_date_role] => 
 *                      [32] => 
 *                      [mem_leader] => 
 *                      [33] => Paul Webmaster
 *                      [create_name] => Paul Webmaster
 *                      [34] => 
 *                      [change_name] => 
 *                  )
 *  
 *          )
 *  
 *      [parameter] => Array
 *          (
 *              [active_role] => 1
 *              [calendar-selection] => 1
 *              [cat_id] => 0
 *              [category-selection] => 0,
 *              [date] => 
 *              [daterange] => Array
 *                  (
 *                      [english] => Array
 *                          (
 *                              [start_date] => 2013-09-21
 *                              [end_date] => 9999-12-31
 *                          )
 *  
 *                      [system] => Array
 *                          (
 *                              [start_date] => 21.09.2013
 *                              [end_date] => 31.12.9999
 *                          )
 *  
 *                  )
 *  
 *              [headline] => Termine
 *              [id] => 0
 *              [mode] => actual
 *              [order] => ASC
 *              [startelement] => 0
 *              [view_mode] => html
 *          )
 * 
 *  )
 *  @endcode
 */
/*******************************************************************************
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 *******************************************************************************/
  
class ModuleDates extends Modules
{    
    /** Constuctor that will create an object of a recordset of the specified dates.
     *  Initialize parameters 
     */
    public function __construct()
    {
        global $gL10n;
        // define constant for headline
        define('HEADLINE', $gL10n->get('DAT_DATES')); 
        
        // get parent instance with all parameters from $_GET Array
        parent::__construct();
        // call class methods different to main class
        $this->setHeadline();
        $this->setMode();
        $this->setViewMode(); 
    }
    
    /** Method validates all date inputs and formats them to date format 'Y-m-d' needed for database queries
     *  @param $date Date to be validated and formated if needed
     */
    private function formatDate($date)
    {
        global $gPreferences;

        $objDate = new DateTimeExtended($date, 'Y-m-d', 'date');
        if($objDate->valid())
        {
            return $date;
        }
        else
        {
            // check if date has system format
            $objDate = new DateTimeExtended($date, $gPreferences['system_date'], 'date');

            if($objDate->valid())
            {
                return  substr($objDate->getDateTimeEnglish(), 0, 10);
            }
            else
            {
                FALSE;
            }
        }
    }
    
    /** SQL query returns an array with available dates.
     *  @param $startelement Defines the offset of the query (default: 0)
     *  @param $limit Limit of query rows (default: 0)
     *  @return Array with all results, dates and parameters.
     */
    public function getDataSet($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gCurrentUser;
        global $gProfileFields;
        global $gDb;
        global $gPreferences;

        if($limit === NULL)
        {
            $limit = $gPreferences['dates_per_page'];
        }

        if($gPreferences['system_show_create_edit'] == 1)
        {
            // show firstname and lastname of create and last change user
            $additionalFields = '
                cre_firstname.usd_value || \' \' || cre_surname.usd_value as create_name,
                cha_firstname.usd_value || \' \' || cha_surname.usd_value as change_name ';
        }
        else
        {
            // show username of create and last change user
            $additionalFields = ' cre_username.usr_login_name as create_name,
                                  cha_username.usr_login_name as change_name ';
        }

        //read dates from database
        $sql = 'SELECT DISTINCT cat.*, dat.*, mem.mem_usr_id as member_date_role, mem.mem_leader,'.$additionalFields.'
                  FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
                       '.$this->sqlAdditionalTablesGet('data').'
                  LEFT JOIN '. TBL_MEMBERS. ' mem
                    ON mem.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                   AND mem.mem_rol_id = dat_rol_id
                   AND mem.mem_begin <= \''.DATE_NOW.'\'
                   AND mem.mem_end    > \''.DATE_NOW.'\'
                 WHERE dat_cat_id = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR (   dat_global   = 1
                          AND cat_org_id IN ('.$gCurrentOrganization->getFamilySQL().') ))
                   AND dat_id = dtr_dat_id
                       '.$this->sqlConditionsGet()
                        . ' ORDER BY dat_begin '.$this->order;
         //Parameter
        if($limit > 0)
        {
            $sql .= ' LIMIT '.$limit;
        }
        if($startElement != 0)
        {
            $sql .= ' OFFSET '.$this->start;
        }

        $result = $gDb->query($sql);

        //array for results
        $dates= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'totalCount'=>$this->getDataSetCount());

        //push results to array
        while($row = $gDb->fetch_array($result))
        {
            $dates['recordset'][] = $row;
        }
        // Push parameter to array
        $dates['parameter'] = $this->getParameter();
        return $dates;
    }
    
    /**
     *  Get number of available dates.
     */
    public function getDataSetCount()
    {
        if($this->id == 0)
        {
            global $gCurrentOrganization;
            global $gDb;

            $sql = 'SELECT COUNT(DISTINCT dat_id) as count
                      FROM '.TBL_DATE_ROLE.', '. TBL_DATES. ', '. TBL_CATEGORIES. '
                      '.$this->sqlAdditionalTablesGet('count') .'
                     WHERE dat_cat_id = cat_id
                       AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                           OR (   dat_global   = 1
                              AND cat_org_id IN ('.$gCurrentOrganization->getFamilySQL().')
                              )
                           )
                       AND dat_id = dtr_dat_id'
                       .$this->sqlConditionsGet();
            $result = $gDb->query($sql);
            $row    = $gDb->fetch_array($result);
            return $row['count'];
        }
        else
        {
            return 1;
        }
    }
    
    /** Returns value for form field.
     *  This method compares a date value to a reference value and to date '1970-01-01'.
     *  Html output will be set regarding the parameters.
     *  If value matches the reference or date('1970-01-01'), the output value is cleared to get an empty string.
     *  This method can be used to fill a html form
     *  @param $date Date is to be checked to reference and default date '1970-01-01'.
     *  @param $reference Reference date
     *  @return String with date value, or an empty string, if $date is '1970-01-01' or reference date
     */
    public function getFormValue($date, $reference)
    {
        if(!isset($date) || !isset($reference))
        {
            return FALSE;
        }
        else
        {
            $checkedDate = $this->setFormValue($date, $reference);
            return $checkedDate;
        }
    }
    
    /** Check date value to reference and set html output.
     *  If value matches to reference, value is cleared to get an empty string.
     */
    private function setFormValue ($date, $reference)
    {
        $checkedDate = $this->formatDate($date);
        if($checkedDate == $reference || $checkedDate == '1970-01-01')
        {
            $date = '';
        }
        else
        {
            $this->date = $date;
        }
        return $date;
    }
    
    /** Set current mode.
     *  This function defines the mode of the current instance. 
     *  This method checks valid mode value and validates the date values.
     */ 
    protected function setMode()
    {   
            $this->mode = '';
            $this->validModes       = array('actual', 'old', 'all', 'period', 'day');
        
            parent::setMode();  
            //set date_from and date_to regarding to current mode
            switch($this->mode)
            {
                case 'actual':
                    $this->properties['date_from']  = DATE_NOW;
                    $this->properties['date_to']    = '9999-12-31';
                    break;
                case 'old':
                    $this->properties['date_from']  = '1970-01-01';
                    $this->properties['date_to']    = DATE_NOW;
                    $this->order                    = 'DESC';
                    $this->setDaterange();
                    break;
                case 'all':
                    $this->properties['date_from']  = '1970-01-01';
                    $this->properties['date_to']    = '9999-12-31';
                    $this->setDaterange();
                    break;
                case 'period':
                    break;
                case 'day':
                    $this->properties['date_from']  = DATE_NOW;
                    $this->properties['date_to']    = DATE_NOW;
                    $this->setDaterange();
                    break;
                    return TRUE;            
            }
            return FALSE;    
    }
    
    /** 
     *  Set current view mode.
     *  This method checks valid valid mode value and sets the current view mode.
     */
    protected function setViewMode()
    {
        global $gPreferences;
        
        $this->viewMode         = '';
        $this->validViewModes   = array($gPreferences['dates_viewmode'], 'html', 'compact', 'print');
        
        parent::setViewMode();
    }
    
    /**
     *  Get additional tables for sql statement
     *  @param $type of sql statement: @b data is joining tables to get more data from them
     *                                 @b count is joining tables only to get the correct number of records
     *                                 (default: 'data')
     *  @return String with the necessary joins
     */

    public function sqlAdditionalTablesGet($type='data')
    {
        global $gPreferences;
        global $gProfileFields;

        $additionalTables='';

        if ($type=='data')
        {
            if($gPreferences['system_show_create_edit'] == 1)
            {
                // Tables for showing firstname and lastname of create and last change user
                $additionalTables = '
                  LEFT JOIN '. TBL_USER_DATA .' cre_surname
                    ON cre_surname.usd_usr_id = dat_usr_id_create
                   AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA .' cre_firstname
                    ON cre_firstname.usd_usr_id = dat_usr_id_create
                   AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA .' cha_surname
                    ON cha_surname.usd_usr_id = dat_usr_id_change
                   AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA .' cha_firstname
                    ON cha_firstname.usd_usr_id = dat_usr_id_change
                   AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
            }
            else
            {
                // Tables for showing username of create and last change user
                $additionalTables = '
                  LEFT JOIN '. TBL_USERS .' cre_username
                    ON cre_username.usr_id = dat_usr_id_create
                  LEFT JOIN '. TBL_USERS .' cha_username
                    ON cha_username.usr_id = dat_usr_id_change ';
            }
        }

        return $additionalTables;
    }
    
    /** 
     *  Prepare SQL Statement.
     */
    private function sqlConditionsGet()
    {
        global $gValidLogin;
        global $gCurrentUser;
        
        $sqlConditions ='';
        
        // if user isn't logged in, then don't show hidden categories
        if ($gValidLogin == false)
        {
            $sqlConditions .= ' AND cat_hidden = 0 ';
        }

        // In case ID was permitted and user has rights
        if($this->id > 0)
        {
            $sqlConditions .= ' AND dat_id = '.$this->id;
        }
        //...otherwise get all additional events for a group
        else
        {
            // add 1 second to end date because full time events to until next day
            $sqlConditions .= ' AND (  dat_begin BETWEEN \''.$this->daterange['english']['start_date'].' 00:00:00\' AND \''.$this->daterange['english']['end_date'].' 23:59:59\'
                                    OR dat_end   BETWEEN \''.$this->daterange['english']['start_date'].' 00:00:01\' AND \''.$this->daterange['english']['end_date'].' 23:59:59\')';
        
            // show all events from category                
            if($this->catId > 0)
            {                 
                // show all events from category
                $sqlConditions .= ' AND cat_id  = '.$this->catId;
            }
        }

        // add conditions for role permission
        if($gCurrentUser->getValue('usr_id') > 0)
        {
            $sqlConditions .= '
            AND (  dtr_rol_id IS NULL 
                OR dtr_rol_id IN (SELECT mem_rol_id 
                                    FROM '.TBL_MEMBERS.' mem2
                                   WHERE mem2.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                     AND mem2.mem_begin  <= dat_begin
                                     AND mem2.mem_end    >= dat_end) ) ';
        }
        else
        {
            $sqlConditions .= ' AND dtr_rol_id IS NULL ';
        }
        
        return $sqlConditions;        
    }       
}  
?>