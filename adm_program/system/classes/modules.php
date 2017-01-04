<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Modules
 * @brief This @b abstract @b class defines a parameter set for modules
 *
 * This abstract class sets the parameters used in Admidio modules.
 * The class gets a copy of the $_GET Array and validates the values
 * with Admidio function admFuncVariableIsValid();
 * Values are set to default if no parameters are submitted.
 * The class also defines a daterange and returns the daterange as array with English format and current System format.
 * If no values are available the daterange is set by default: date_from = DATE_NOW; date_to = 9999-12-31
 * The class provides methods to return all single Variables and arrays or returns an Array with all setted parameters
 * The returned array contains following settings:
 * @par Return parameter array:
 * @code
 * array('active_role'         => '1',
 *       'headline'            => 'string',
 *       'category-selection'  => '0',
 *       'cat_id'              => '0',
 *       'calendar-selection'  => '1',
 *       'id'                  => 'integer',
 *       'mode'                => 'string',
 *       'order'               => 'ASC'
 *       'startelement'        => 0
 *       'view_mode'           => 'string',
 *       'daterange'           =>  array(
 *                                         [english] (date_from => 'string', date_to => 'string'),
 *                                         [sytem] (date_from => 'string', date_to => 'string'))
 *                                      );
 * @endcode
 */
abstract class Modules
{
    const HEADLINE = '';            ///< Constant for language parameter set in module classes

    protected $activeRole;          ///< Boolean false/true for active role
    protected $headline;            ///< String with headline expression
    protected $catId;               ///< ID as integer for chosen category
    protected $id;                  ///< ID as integer to choose record
    protected $daterange;           ///< Array with date settings in English format and system format
    protected $mode;                ///< String with current mode ( Default: "Default" )
    protected $order;               ///< String with order ASC/DESC ( Default: "ASC" )
    protected $start;               ///< Integer for start element
    protected $properties;          ///< Array Clone of $_GET Array
    protected $validModes;          ///< Array with valid modes ( Default: "Default" )
    protected $parameters;          ///< Array with all parameters of the module that were added to this class.
    public $arrParameter;           ///< Array with validated parameters

    /**
     * @param int $startElement
     * @param int $limit
     * @return array
     */
    abstract public function getDataSet($startElement = 0, $limit = null);

    /**
     * @return mixed
     */
    abstract public function getDataSetCount();

    /**
     * Constructor that will create an object of a parameter set needed in modules to get the recordsets.
     * Initialize parameters
     */
    public function __construct()
    {
        $this->activeRole   = false;
        $this->headline     = '';
        $this->catId        = 0;
        $this->id           = 0;
        $this->daterange    = array();
        $this->mode         = 'Default';
        $this->order        = '';
        $this->start        = '';
        $this->properties   = $_GET;
        $this->validModes   = array('Default');
        $this->parameters   = array();
        $this->arrParameter = array();

        // Set parameters
        $this->setId();
        $this->setMode();
        $this->setOrder();
        $this->setStartElement();
    }

    /**
     * Return ID
     * @return int Returns the ID of the record
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return the daterange
     * @return array[] Returns daterange as array with English format and system format
     */
    public function getDaterange()
    {
        return $this->daterange;
    }

    /**
     * Return mode
     * @return string Returns mode as string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Return mode
     * @return string Returns order as string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Return start element
     * @return int Returns Integer value for the start element
     */
    public function getStartElement()
    {
        return $this->start;
    }

    /**
     * Returns a module parameter from the class
     * @param string $parameterName The name of the parameter whose value should be returned.
     * @return mixed|null Returns the parameter value or null if parameter didn't exists
     */
    public function getParameter($parameterName)
    {
        if ($parameterName !== '' && array_key_exists($parameterName, $this->parameters))
        {
            return $this->parameters[$parameterName];
        }

        return null;
    }

    /**
     * Return parameter set as Array
     * @return array Returns an Array with all needed parameters as Key/Value pair
     */
    public function getParameters()
    {
        // Set Array
        $this->arrParameter['active_role']  = $this->activeRole;
        $this->arrParameter['cat_id']       = $this->catId;
        $this->arrParameter['daterange']    = $this->daterange;
        $this->arrParameter['headline']     = $this->headline;
        $this->arrParameter['id']           = $this->id;
        $this->arrParameter['mode']         = $this->mode;
        $this->arrParameter['order']        = $this->order;
        $this->arrParameter['startelement'] = $this->start;

        return $this->arrParameter;
    }

    /**
     * Set ID
     */
    protected function setId()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->id = admFuncVariableIsValid($this->properties, 'id', 'int');
    }

    /**
     * Set mode
     * @par If user string is set in $_GET Array the string is validated by Admidio function
     * and set as mode in the modules. Otherwise mode is set to default
     */
    protected function setMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        //$this->mode = admFuncVariableIsValid($this->properties, 'mode', 'string', $this->validModes[0], false, $this->validModes);
    }

    /**
     * Set order
     * @par If user string is set in $_GET Array the string is validated by Admidio function
     * and set as order for the results in the modules. Otherwise mode is set to default "ASC"
     */
    protected function setOrder()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->order = admFuncVariableIsValid($this->properties, 'order', 'string',
                                              array('defaultValue' => 'ASC', 'validValues' => array('ASC', 'DESC')));
    }

    /**
     * Set startelement
     * @par If user string is set in $_GET Array the string is validated by Admidio function
     * and set as startelement in the modules. Otherwise startelement is set to 0
     */
    protected function setStartElement()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->start = admFuncVariableIsValid($this->properties, 'start', 'int');
    }

    /**
     * add a module parameter to the class
     * @param string $parameterName  The name of the parameter that should be added.
     * @param mixed  $parameterValue The value of the parameter that should be added.
     */
    public function setParameter($parameterName, $parameterValue)
    {
        if ($parameterName !== '')
        {
            $this->parameters[$parameterName] = $parameterValue;

            if ($parameterName === 'cat_id')
            {
                $this->catId = (int) $parameterValue;
            }
            elseif ($parameterName === 'active_role')
            {
                $this->activeRole = (bool) $parameterValue;
            }
            elseif ($parameterName === 'mode')
            {
                $this->mode = $parameterValue;
            }
        }
    }
}
