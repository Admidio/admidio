<?php
/*******************************************************************************/
/** @class ModuleDates
 *  @brief Validates all parmeter required in date-module.
 *
 *  This class is designed to handle transferd parameters and do all logical settings,
 *  like output of headline, values for date range, input fields and also sql queries needed for validation of the content.
 *  It returns arrays for possible modes and viewmodes. Dates are checked to references and formated for database queries and system format.
 *  
 */
/*******************************************************************************
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 *******************************************************************************/
  
class ModuleDates
{
    private $mode;          ///< Returns setting for "mode", regarding to date settings(@b actual,@b old,@b all,@b day and @b period are possible)
    private $catId;         ///< Value of the current Category ID
    private $dateId;        ///< Id of the current date
    private $dateFrom;      ///< First date value (start) to be checked and formated to database format ("Y-m-d")
    private $dateTo;        ///< Second date value (end) to be checked and formated to database format ("Y-m-d")
    private $order;         ///< optional sorting value for SQL query (array: ASC,DESC  dafault:ASC)
    private $headline;      ///< The headline is set to date, or old dates regarding to given date parameters. Optional user text can be set.

    /** Constuctor that will create an object of a recordset of the specified dates.
     *  Initialize parameters 
     */
    public function __construct()
    {
        $this->setMode();
        $this->catId = '';
        $this->headline = '';
    }
    
    /** Returns current headline regarding the defined date range if an empty string is passed in $getHeadline
     *  Date values are validated and formated by class method
     * 
     *  @param $getHeadline String for the headline of the result list  
     *  @param $start Date value for the first date of period
     *  @param $end Date value for the second date of period
     *  @return Headline of current result list
     *  @par Example
     *  @code 
     *  // Get the dates for January 2001 with customer headline for example
     *  $dates->getHeadline('My_headline', '01.01.2001', '31.01.2001'); @endcode
     */ 
    public function getHeadline($getHeadline, $start, $end)
    {   
        if (!isset($start) || !isset($end))
        {
            return FALSE;
        } 
        
        if (strlen($getHeadline) == 0)
        {
            $checkedDate = $this->formatDate($start);
            if($checkedDate == FALSE)
            {
                return FALSE;
            }
            
            $checkedDate = $this->formatDate($end);
            if($checkedDate == FALSE)
            {
                return FALSE;
            }

            $headline = $this->setHeadline($start, $end);
        }
        else
        {
            $this->headline = $getHeadline;
        }
        return $this->headline;
    }
    
    /** Private method setting HTML headline relative to date period
     * @param $start Value for the first date of period
     * @param $end Value for the second date of period
     */
    private function setHeadline($start, $end)
    {
        global $gL10n;

        If ($start < DATE_NOW && $end < DATE_NOW)
        {
            $getHeadline =  $gL10n->get('DAT_PREVIOUS_DATES',' ');
            $getHeadline.= $gL10n->get('DAT_DATES');
            $this->headline = $getHeadline;
        }
        else
        {
            $getHeadline =  $gL10n->get('DAT_DATES');
            $this->headline = $getHeadline;
        }
        return $this;
    }
    
    /** Returns valid view modes for dates as array
     *  @return Array ('html', 'print')
     */
    public function getViewModes()
    {
        return array('html', 'compact', 'print');
    }
    
    /** Returns valid modes for dates as array
     *  @return Array('actual', 'old', 'all', 'period', 'day')
     */ 
    public function getModes()
    {
        return array('actual', 'old', 'all', 'period', 'day');
    }
        
    /** Returns current mode defined by paramters
     *  @return The current mode.
     */ 
    public function getMode()
    {
        return $this->mode;
    }
    
    /** Set current mode.
     *  This function defines the mode of the current instance. If no parameters are defined the @b default @b mode is @b 'actual'
     *  This method checks valid mode value and validates the date values. If necessary the date values are to be formated by internal function.
     *  @param $mode String with valid mode defined in Array getModes (default: 'actual') 
     *  @param $var1 First date value ( dafault: '')
     *  @param $var2 Second date value ( dafault: '')
     */ 
    public function setMode($mode='actual', $var1='', $var2='')
    {    
        //check if mode is valid
        if(in_array($mode, $this->getModes()))
        {
            //check dates for validty if necessary
            if(($mode == 'period' || $mode == 'day') && (!isset($var1) || $this->formatDate($var1)==FALSE))
            {
                return FALSE;
            }     
            if($mode == 'period' && (!isset($var1) || $this->formatDate($var2)==FALSE))
            {
                return FALSE;
            }  
            
            $this->mode = $mode;
            
            //set $dateFrom and $dateTo regarding to $mode
            switch($this->mode)
            {
                case 'actual':
                    $this->setDateFrom();
                    $this->setDateTo();
                    $this->setDateId();
                    $this->setOrder();
                    break;
                case 'old':
                    $this->setDateFrom('1970-01-01');
                    $this->setDateTo(DATE_NOW);
                    $this->setDateId();
                    $this->setOrder('DESC');
                    break;
                case 'all':
                    $this->setDateFrom('1970-01-01');
                    $this->setDateTo();
                    $this->setDateId();
                    $this->setOrder();
                    break;
                case 'period':
                    $this->setDateFrom($var1);
                    $this->setDateTo($var2);
                    $this->setDateId();
                    $this->setOrder();
                    break;
                case 'day':
                    $this->setDateFrom($var1);
                    $this->setDateTo($var1);
                    $this->setDateId();
                    $this->setOrder();
                    break;            
            }            
            return TRUE;  
        }
        else
        {
            return FALSE;    
        }
    }
        
    /** 
     *  Validate startdate and set it in class variable 
     */
    private function setDateFrom($date=DATE_NOW)
    {
        $checkedDate = $this->formatDate($date);
        if($checkedDate != FALSE)
        {
            $this->dateFrom = $checkedDate;
            return TRUE;    
        }
        else
        {
            return FALSE;    
        }
        
    }
    
    /**
     *  Returns dateFrom of current object.
     */ 
    public function getDateFrom()
    {
        return $this->dateFrom;
    }
    
    /**
     *  Validate enddate and set it in class variable
     */
    private function setDateTo($date='9999-12-31')
    {
        $checkedDate = $this->formatDate($date);
        if($checkedDate != FALSE)
        {
            $this->dateTo = $checkedDate;
            return TRUE;    
        }
        else
        {
            return FALSE;    
        }
    }
    
    /**
     *  Returns dateTo of current object.
     */
    public function getDateTo()
    {
        return $this->dateTo;
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
    
    /**Method validates all date inputs and formats them to date format 'Y-m-d' needed for database queries
     * @param $date Date to be validated and formated if needed 
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
        
    /** Set current Category.
     *  @param $Id Current Category ID (default:0)
     */ 
    public function setCatId($id=0)
    {
        if(is_numeric($id))
        {
            $this->catId=$id;
            return TRUE;    
        }
        else
        {
            return FALSE;
        }
    }
        
    /**
     *  Set current Date.
     */
    public function setDateId($id=0)
    {        
        if(is_numeric($id))
        {
            $this->dateId=$id;
            return TRUE;    
        }
        else
        {
            return FALSE;
        }
    }
        
    /** Sets current Order.
     *  @param $order (Default: @b ASC)
     */ 
    public function setOrder($order='ASC')
    {        
        if(in_array($order, array('ASC','DESC')))
        {
            $this->order=$order;
            return TRUE;    
        }
        else
        {
            return FALSE;
        }
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
        if($this->dateId > 0)
        {
            $sqlConditions .= ' AND dat_id = '.$this->dateId;
        }
        //...otherwise get all additional events for a group
        else
        {
            // add 1 second to end date because full time events to until next day
            $sqlConditions .= ' AND (  dat_begin BETWEEN \''.$this->dateFrom.' 00:00:00\' AND \''.$this->dateTo.' 23:59:59\'
                                    OR dat_end   BETWEEN \''.$this->dateFrom.' 00:00:01\' AND \''.$this->dateTo.' 23:59:59\')';
        
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

    /**
     *  Get number of available dates.
     */
    public function getDatesCount()
    {            
        if($this->dateId == 0)
        {     
            global $gCurrentOrganization;
            global $gDb;
            
            $sql = 'SELECT COUNT(DISTINCT dat_id) as count
                      FROM '.TBL_DATE_ROLE.', '. TBL_DATES. ', '. TBL_CATEGORIES. '
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
    
    /** SQL query returns an array with avaible dates.
     *  @param $startelement Defines the offset of the query (default: 0)
     *  @param $limit Limit of query rows (default: 0)
     *  @return Array with all dates and properties 
     */ 
    public function getDates($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gCurrentUser;
        global $gProfileFields;
        global $gDb;
        global $gPreferences;
        
        if($limit == NULL)
        {
            $limit = $gPreferences['dates_per_page'];
        }
        
        if($gPreferences['system_show_create_edit'] == 1)
        {
            // show firstname and lastname of create and last change user
            $additionalFields = '
                cre_firstname.usd_value || \' \' || cre_surname.usd_value as create_name,
                cha_firstname.usd_value || \' \' || cha_surname.usd_value as change_name ';
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
            // show username of create and last change user
            $additionalFields = ' cre_username.usr_login_name as create_name,
                                  cha_username.usr_login_name as change_name ';
            $additionalTables = '
              LEFT JOIN '. TBL_USERS .' cre_username
                ON cre_username.usr_id = dat_usr_id_create
              LEFT JOIN '. TBL_USERS .' cha_username
                ON cha_username.usr_id = dat_usr_id_change ';
        }        
                       
        //read dates from database
        $sql = 'SELECT DISTINCT cat.*, dat.*, mem.mem_usr_id as member_date_role, mem.mem_leader,'.$additionalFields.'
                  FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
                       '.$additionalTables.'
                  LEFT JOIN '. TBL_MEMBERS. ' mem
                    ON mem.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                   AND mem.mem_rol_id = dat_rol_id
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
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
            $sql .= ' OFFSET '.$startElement;
        }         
        
        $result = $gDb->query($sql);

        //array for results       
        $dates= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'startElement'=>$startElement, 'totalCount'=>$this->getDatesCount(), 'dates'=>array());
       
        //push results to array
        while($row = $gDb->fetch_array($result))
        {           
            $dates['dates'][] = $row; 
        }
       
        return $dates;
    }
}  
?>