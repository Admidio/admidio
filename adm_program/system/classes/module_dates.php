<?php
/******************************************************************************
 * Show a list of all events
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

/// This class handles all parmeter required in date-module. 

/**
 *  This function is designed to handle transferd parameters and do all logical settings,
 *  like output of headline, values for datefilter, input fields and also sql queries for the content.
 *  It returns arrays for possible modes and viewmodes. Dates are checked to references and formated for sql and system format.
 *  @param $mode Returns setting for "mode" regarding to date settings(actual,old,all,day,period are possible)
 *  @param $CatId The current Category ID
 *  @param $datId The current Date ID
 *  @param $dateFrom The first date (startdate) is checked and formated to "Y-m-d" for SQL query 
 *  @param $dateTo The second date (enddate) - same conditions like first date
 *  @param $order optional sorting for SQL query (array: ASC,DESC  dafault:ASC)
 *  @param $headline The headline is set to date, or old dates regarding to given date parameters. 
 *         Optional user text can be set.
 */ 
class dates
{
    private $mode;
    private $catId;
    private $dateId;
    private $dateFrom;
    private $dateTo;
    private $order;
    private $headline;

    /**
     *  setMode()
     * 
     *  Initialize parameters
     * 
     *  @param catID
     *  @param headline
     */
    public function __construct()
    {
        $this->setMode();
        $this->catId = '';
        $this->headline = '';
    }
    
    /**
     * Returns current headline. If no parameter is given, the headline is set by method regarding to given dates.
     * Returns \b FALSE if one \b paramter is \b missing calling the method!
     * @param $getHeadline Optinal headline for content else must be initialized !
     * @param $start Value for the first date of period
     * @param $end Value for the second date of period
     * @par Example
     * @code $dates->getHeadline($getHeadline, $start, $end); @endcode
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
            if($checkedDate != FALSE)
            {
                $this->dateFrom = $checkedDate;
            }
            else
            {
                return FALSE;
            }
            
            $checkedDate = $this->formatDate($end);
            if($checkedDate != FALSE)
            {
                $this->dateTo = $checkedDate;
            }
            else
            {
                return FALSE;
            }  
            
            $headline = $this->setHeadline($this->dateFrom, $this->dateTo);
        }
        else
        {
            $this->headline = $getHeadline;
        }
        return $this->headline;
    }
    
    /// Private method setting HTML headline relative to date period
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
    
    /**
     *  Provides possible view modes for dates as array
     *  @param html
     *  @param print
     */
    public function getViewModes()
    {
        return array('html', 'print');
    }
    
    /**
     *  Provides possible modes for dates as array
     *  @param actual
     *  @param old
     *  @param all
     *  @param period
     *  @param day
     */ 
    public function getModes()
    {
        return array('actual', 'old', 'all', 'period', 'day');
    }
        
    /**
     *  Returns current mode.
     */ 
    public function getMode()
    {
        return $this->mode;
    }
    
    /**
     *  Sets current mode. 
     *  This method checks valid mode value and validates the date values if necessary.
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
        
    //sets $dateFrom
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
     *  Returns date From.
     */ 
    public function getDateFrom()
    {
        return $this->dateFrom;
    }
    
    //sets $dateTo
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
     *  Returns date To.
     */
    public function getDateTo()
    {
        return $this->dateTo;
    }
    
    /** 
     *  Check date value to reference and set html output.   
     *  If value matches to reference, value is cleared.
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

    /**
     *  Returns value for form field. 
     *  This method checks the date value to reference and set the html output.
     *  If value matches the reference, the output value is cleared.
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
    
    //checks date
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
            $objDate->setDateTime($date, $gPreferences['system_date']);
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
        
    /**
     *  Sets current catId.
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
     *  Sets current DatId.
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
        
    /**
     * Sets current Order.
     * Default: ASC
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
    
    //returns SQL conditions
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
     *  get number of available dates.
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
    
    /**
     *  SQL query and returns array with avaible dates.
     *  @param $startelement Defines the offset of the query (default: 0)
     *  @param $limit Limit of query rows (default: 0) 
     */ 
    public function getDates($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gCurrentUser;
        global $gProfileFields;
        global $gDb;
                       
        //read dates from database
        $sql = 'SELECT DISTINCT cat.*, dat.*, mem.mem_usr_id as member_date_role, mem.mem_leader,
                       cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
                       cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
                  FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
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
                   AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
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
        if($limit != NULL)
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