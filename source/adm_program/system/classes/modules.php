<?php
/*****************************************************************************/
/** @class Modules
 *  @brief  This @b abstract @b class defines a parameter set for modules
 *
 *  This abstract class sets the parameters used in Admidio modules.
 *  The class gets a copy of the $_GET Array and validates the values 
 *  with Admidio function admFuncVariableIsValid();
 *  Values are set to default if no parameters are submitted.
 *  The class provides methods to return single Variables or returns an Array with all setted parameters
 *  The array contains following settings:
 *  @par Return parameter array:
 *  @code
 *  array('headline'    => 'string',
 *        'mode'        => 'string',
 *        'view_mode'   => 'string',
 *        'daterange'   =>  array(
 *                               [english] (date_from => 'string', date_to => 'string'),
 *                               [sytem] (date_from => 'string', date_to => 'string'))
 *                               );
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
    const HEADLINE = '';        ///< Constant for language parameter set in modul classes
    
    public    $arrParameter;    ///< Array with validated parameters 
    protected $headline;        ///< String with headline expression
    protected $daterange;       ///< Array with date settings in English format and system format
    protected $mode;            ///< String with current mode ( Deafault: "Default" )
    private   $properties;      ///< Array Clone of $_GET Array
    protected $validModes;      ///< Array with valid modes ( Deafault: "Default" )
    protected $viewMode;        ///< Array with valid view modes ( Deafault: "Default" )
    
    abstract public function getDataSet($startElement=0, $limit=NULL);
    abstract public function getDataSetCount();
    
    /** Constuctor that will create an object of a parameter set needed in modules to get the recordsets.
     *  Initialize parameters
     */
    public function __construct()
    {
        $this->arrParameters    = array();
        $this->daterange        = array();
        $this->headline         = '';
        $this->mode             = 'Default';
        $this->properties       = $_GET;
        $this->validModes       = array('Default');
        $this->viewMode         = 'Default';
        
        // Set parameters
        $this->setDaterange();
        $this->setHeadline();
        $this->setMode();
        $this->setViewMode();
        
        // Set Array
        $this->arrParameter['headline']     = $this->headline;
        $this->arrParameter['mode']         = $this->mode;
        $this->arrParameter['view_mode']    = $this->viewMode;
        $this->arrParameter['daterange']    = $this->daterange;
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
        return $this->Headline;
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
        return $this->arrParameter;
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
     *  Set mode 
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as mode in the modules. Otherwise mode is set to default
     */
    protected function setMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->headline = admFuncVariableIsValid($this->properties, 'mode', 'string', $this->mode, false, $this->validModes);
    }
    
    /**
     *  Set view mode
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as view mode in the modules. Otherwise mode is set to default
     */
    protected function setViewMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->headline = admFuncVariableIsValid($this->properties, 'viewMode', 'string', $this->viewMode);
    }
}
?>