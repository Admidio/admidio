<?php
/*****************************************************************************/
/** @class Modules
 *  @brief  This @b abstract @b class defines a parameter set for modules
 *
 *  This abstract class sets the parameters used in Admidio modules.
 *  The class gets a copy of the $_GET Array and validates the values 
 *  with Admidio function admFuncVariableIsValid();
 *  Values are set to default if no parameters are submitted.
 *  The class also defines a daterange and returns the daterange as array with English format and current System format.
 *  If no values are available the daterange is set by default: date_from = DATE_NOW; date_to = 9999-12-31 
 *  The class provides methods to return all single Variables and arrays or returns an Array with all setted parameters
 *  The returned array contains following settings:
 *  @par Return parameter array:
 *  @code
 *  array('active_role'         => '1',
 *        'headline'            => 'string',
 *        'category-selection'  => '0',
 *        'cat_id'              => '0',
 *        'calendar-selection'  => '1',
 *        'id'                  => 'integer',
 *        'mode'                => 'string',
 *        'order'               => 'ASC'
 *        'startelement'        => 0
 *        'view_mode'           => 'string',
 *        'date'                => 'string',
 *        'daterange'           =>  array(
 *                                          [english] (date_from => 'string', date_to => 'string'),
 *                                          [sytem] (date_from => 'string', date_to => 'string'))
 *                                       );
 *  @endcode                               
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Author       : Thomas-RCV
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
abstract class Modules
{
    const HEADLINE = '';            ///< Constant for language parameter set in modul classes
    
    protected $activeRole;          ///< Boolean 0/1 for active role
    public    $arrParameter;        ///< Array with validated parameters 
    protected $headline;            ///< String with headline expression
    protected $calendarSelection;   ///< Boolean 0/1 to show calendar selection
    protected $categorySelection;   ///< Boolean 0/1 to show category selection
    protected $catId;               ///< ID as integer for choosen category
    protected $id;                  ///< ID as integer to choose record
    protected $date;                ///< String with date value
    protected $daterange;           ///< Array with date settings in English format and system format
    protected $mode;                ///< String with current mode ( Default: "Default" )
    protected $order;               ///< String with order ASC/DESC( Default: "ASC" )
    protected $start;               ///< Integer for start element
    protected $properties;          ///< Array Clone of $_GET Array
    protected $validModes;          ///< Array with valid modes ( Deafault: "Default" )
    protected $validViewModes;      ///< Array with valid view modes ( Deafault: "Default" )
    protected $viewMode;            ///< String with view mode ( Default: "Default" )
    
    abstract public function getDataSet($startElement=0, $limit=NULL);
    abstract public function getDataSetCount();
    
    /** Constuctor that will create an object of a parameter set needed in modules to get the recordsets.
     *  Initialize parameters
     */
    public function __construct()
    {
        $this->activeRole           = '';
        $this->arrParameters        = array();
        $this->calendarSelection    = '';
        $this->catId                = 0;
        $this->categorySelection    = '';
        $this->date                 = '';
        $this->daterange            = array();
        $this->headline             = '';
        $this->id                   = 0;
        $this->mode                 = 'Default';
        $this->order                = '';
        $this->properties           = $_GET;
        $this->start                = '';
        $this->validModes           = array('Default');
        $this->viewMode             = 'Default';
        $this->validViewModes       = array('Default');
        
        // Set parameters
        $this->setActiveRole();
        $this->setCalendarSelection();
        $this->setCatId();
        $this->setCategorySelection();
        $this->setDate();
        $this->setDaterange();
        $this->setHeadline();
        $this->setId();
        $this->setMode();
        $this->setOrder();
        $this->setStartElement();
        $this->setViewMode();
    }
    
    /**
     *  Return boolean for active role
     *  @return Returns boolean for active role
     */
    public function getActiveRole()
    {
        return $this->activeRole;
    }
    
    /**
     *  Return Calendar Selection
     *  @return Returns boolean calendar selection
     */
    public function getCalendarSelection()
    {
        return $this->calendarSelection;
    }
    
    /**
     *  Return category ID
     *  @return Returns the category ID 
     */
    public function getCatId()
    {
        return $this->catId;
    }
    
    /**
     *  Return Category Selection
     *  @return Returns boolean for category selection
     */
    public function getCategorySelection()
    {
        return $this->categorySelection;
    }
    
    /**
     *  Return Date
     *  @return Returns the explicit date in English format
     */
    public function getDate()
    {
        return $this->date;
    }
    
    /**
     *  Return the daterange
     *  @return Returns daterange as array with English format and system format
     */
    public function getDaterange()
    {
        return $this->daterange;
    }
    
    /**
     *  Return Headline
     *  @return Returns headline as string
     */
    public function getHeadline()
    {
        return $this->headline;
    }
    
    /**
     *  Return ID
     *  @return Returns the ID of the record
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     *  Return mode
     *  @return Returns mode as string
     */
    public function getMode()
    {
        return $this->mode;
    }
    
    /**
     *  Return mode
     *  @return Returns order as string
     */
    public function getOrder()
    {
        return $this->order;
    }
    
    /**
     *  Return start element
     *  @return Returns Integer value for the start element
     */
    public function getStartElement()
    {
        return $this->start;
    }
    
    /**
     *  Return view mode
     *  @return Returns view mode as string
     */
    public function getViewMode()
    {
        return $this->viewMode;
    }
    
    /**
     *  Return parameter set as Array
     *  @return Returns an Array with all needed parameters as Key/Value pair 
     */
    public function getParameter()
    {    
        // Set Array
        $this->arrParameter['active_role']          = $this->activeRole;
        $this->arrParameter['calendar-selection']   = $this->calendarSelection;
        $this->arrParameter['cat_id']               = $this->catId;
        $this->arrParameter['category-selection']   = $this->categorySelection;
        $this->arrParameter['date']                 = $this->date;
        $this->arrParameter['daterange']            = $this->daterange;
        $this->arrParameter['headline']             = $this->headline;
        $this->arrParameter['id']                   = $this->id;
        $this->arrParameter['mode']                 = $this->mode;
        $this->arrParameter['order']                = $this->order;
        $this->arrParameter['startelement']         = $this->start;
        $this->arrParameter['view_mode']            = $this->viewMode;
        return $this->arrParameter;
    }
    
    /**
     *  Set active role
     *
     *  Set boolean for active role. Default 1 for active
     */
    protected function setActiveRole()
    {
        $this->activeRole = admFuncVariableIsValid($this->properties, 'active_role', 'boolean', 1);
    }
    
    /**
     *  Set calendar selection
     * 
     *  Set Calendar selection boolean 0/1. Default $gPreferences 
     */
    protected function setCalendarSelection()
    {
        global $gPreferences;
        $this->calendarSelection = admFuncVariableIsValid($this->properties, 'calendar-selection', 'boolean', $gPreferences['dates_show_calendar_select']);
    }
     
    /**
     *  Set category ID
     *
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as category ID in the modules. Otherwise the category is set default to "0"
     *
     */
    protected function setCatId()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->catId = admFuncVariableIsValid($this->properties, 'cat_id', 'numeric', 0);
    }
    
    /**
     *  Set category selection
     *
     *  Set category selection boolean 0/1. Default 1
     */
    protected function setCategorySelection()
    {
        $this->categorySelection = admFuncVariableIsValid($this->properties, 'category-selection', 'boolean', 1);
    }
    
    /**
     *  Set date value and convert in English format if necessary
     */
    protected function setDate()
    {
        global $gPreferences;
        $date = '';
        
        // check optional user parameter and make secure. Otherwise set default value
        $date = admFuncVariableIsValid($this->properties, 'date', 'date', '', false);
        
        // Create date object and format date in English format 
        $objDate = new DateTimeExtended($date, 'Y-m-d', 'date');
        
        if($objDate->valid())
        {
            $this->date = substr($objDate->getDateTimeEnglish(), 0, 10);
        }
        else
        {
            // check if date has system format then convert it in English format
            $objDate = new DateTimeExtended($date, $gPreferences['system_date'], 'date');
            if($objDate->valid())
            {
                $this->date = substr($objDate->getDateTimeEnglish(), 0, 10);
            }
        }
    }
    
    /**
     *  Set daterange in an array with values for English format and system format
     *  @return Returns false if invald date format is submitted 
     */
    protected function setDaterange()
    {
        global $gPreferences;
        $start  = '';
        $end    = '';
        
        // check optional user parameter and make secure. Otherwise set default value
        $start = admFuncVariableIsValid($this->properties, 'date_from', 'date', DATE_NOW, false);
        
        // Create date object and format date_from in English format and sytem format and push to daterange array
        $objDate = new DateTimeExtended($start, 'Y-m-d', 'date');
        if($objDate->valid())
        {
            $this->daterange['english']['start_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
            $this->daterange['system']['start_date'] = $objDate->format($gPreferences['system_date']);
        }                                             
        else
        {
            // check if date_from  has system format
            $objDate = new DateTimeExtended($start, $gPreferences['system_date'], 'date');

            if($objDate->valid())
            {
                $this->daterange['english']['start_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
                $this->daterange['system']['start_date'] = $objDate->format($gPreferences['system_date']);
            }
            else
            {
                return false;
            }
        }

        // check optional user parameter and make secure. Otherwise set default value
        $end = admFuncVariableIsValid($this->properties, 'date_to', 'date', '9999-12-31', false);

        // Create date object and format date_to in English format and sytem format and push to daterange array
        $objDate = new DateTimeExtended($end, 'Y-m-d', 'date');
        if($objDate->valid())
        {
            $this->daterange['english']['end_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
            $this->daterange['system']['end_date'] = $objDate->format($gPreferences['system_date']);
        }
        else
        {
            // check if date_from  has system format
            $objDate = new DateTimeExtended($end, $gPreferences['system_date'], 'date');

            if($objDate->valid())
            {
                $this->daterange['english']['end_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
                $this->daterange['system']['end_date'] = $objDate->format($gPreferences['system_date']);
            }
            else
            {
                return false;
            }
        }
        
    }
    
    /**
     *  Set headline
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as headline in the modules. Otherwise the headline is set from language file
     *  
     */
    protected function setHeadline()
    {   
        // check optional user parameter and make secure. Otherwise set default value
        $this->headline = admFuncVariableIsValid($this->properties, 'headline', 'string', HEADLINE);   
    }
    
    /**
     *  Set ID
     */
    protected function setId()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->id = admFuncVariableIsValid($this->properties, 'id', 'numeric', 0);
    }
    
    /**
     *  Set mode 
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as mode in the modules. Otherwise mode is set to default
     */
    protected function setMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->mode = admFuncVariableIsValid($this->properties, 'mode', 'string', $this->validModes[0], false, $this->validModes);
    }
    
    /**
     *  Set order
     *
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as order for the results in the modules. Otherwise mode is set to default "ASC"
     */
    protected function setOrder()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->order = admFuncVariableIsValid($this->properties, 'order', 'string', 'ASC', false, array('ASC', 'DESC'));
    }
    
    /**
     *  Set startelement
     *
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as startelement in the modules. Otherwise startelement is set to 0
     */
    protected function setStartElement()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->start = admFuncVariableIsValid($this->properties, 'start', 'numeric', 0);
    }
    
    /**
     *  Set viewmode
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as viewmode in the modules. Otherwise mode is set to default
     */
    protected function setViewMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->viewMode = admFuncVariableIsValid($this->properties, 'view_mode', 'string', $this->validViewModes[0], false, $this->validViewModes);
    }
}
?>