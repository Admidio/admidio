<?php
/**
 * @brief This **abstract class** defines a parameter set for modules
 *
 * This abstract class sets the parameters used in Admidio modules.
 * The class gets a copy of the $_GET Array and validates the values
 * with Admidio function admFuncVariableIsValid();
 * Values are set to default if no parameters are submitted.
 * The class also defines a date range and returns the date range as array with English format and current System format.
 * If no values are available the date range is set by default: date_from = DATE_NOW; date_to = 9999-12-31
 * The class provides methods to return all single Variables and arrays or returns an Array with all set parameters
 * The returned array contains following settings:
 *
 * **Return parameter array:**
 * ```
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
 *                                         [system] (date_from => 'string', date_to => 'string'))
 *                                      );
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
abstract class Modules
{
    /**
     * @var int Integer Active, inactive or event participation roles
     */
    protected int $roleType = 1;
    /**
     * @var string String with headline expression
     */
    protected string $headline = '';
    /**
     * @var int ID as integer for chosen category
     */
    protected int $catId = 0;
    /**
     * @var int ID as integer to choose record
     */
    protected int $id = 0;
    /**
     * @var array Array with date settings in English format and system format
     */
    protected array $daterange = array();
    /**
     * @var string String with current mode ( Default: "Default" )
     */
    protected string $mode = 'Default';
    /**
     * @var string String with order ASC/DESC ( Default: "ASC" )
     */
    protected string $order = '';
    /**
     * @var int Integer for start element
     */
    protected int $start = 0;
    /**
     * @var array<string,mixed> Array with all parameters of the module that were added to this class.
     */
    protected array $parameters = array();
    /**
     * @var array Array Clone of $_GET Array
     */
    protected array $properties;
    /**
     * @var array Array with validated parameters
     */
    protected array $arrParameter = array();

    /**
     * @param int $startElement
     * @param int $limit
     * @return array
     */
    abstract public function getDataSet(int $startElement = 0, int $limit = 0): array;

    /**
     * @return mixed
     */
    abstract public function getDataSetCount();

    /**
     * Constructor that will create an object of a parameter set needed in modules to get the recordset.
     * Initialize parameters
     * @throws \Admidio\Exception
     */
    public function __construct()
    {
        $this->properties = $_GET;

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
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Return mode
     * @return string Returns mode as string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Return start element
     * @return int Returns Integer value for the start element
     */
    public function getStartElement(): int
    {
        return $this->start;
    }

    /**
     * Returns a module parameter from the class
     * @param string $parameterName The name of the parameter whose value should be returned.
     * @return mixed|null Returns the parameter value or null if parameter didn't exists
     */
    public function getParameter(string $parameterName)
    {
        if ($parameterName !== '' && array_key_exists($parameterName, $this->parameters)) {
            return $this->parameters[$parameterName];
        }

        return null;
    }

    /**
     * Return parameter set as Array
     * @return array<string,bool|int|string|array> Returns an Array with all needed parameters as Key/Value pair
     */
    public function getParameters(): array
    {
        // Set Array
        $this->arrParameter['role_type']    = $this->roleType;
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
     * @throws \Admidio\Exception
     */
    protected function setId()
    {
        // check optional user parameter and make secure. Otherwise, set default value
        $this->id = admFuncVariableIsValid($this->properties, 'id', 'int');
    }

    /**
     * Set mode
     * @par If user string is set in $_GET Array the string is validated by Admidio function
     * and set as mode in the modules. Otherwise, mode is set to default
     */
    protected function setMode()
    {
        // check optional user parameter and make secure. Otherwise, set default value
        //$this->mode = admFuncVariableIsValid($this->properties, 'mode', 'string', $this->validModes[0], false, $this->validModes);
    }

    /**
     * Set order
     * @par If user string is set in $_GET Array the string is validated by Admidio function
     * and set as order for the results in the modules. Otherwise, mode is set to default "ASC"
     * @throws \Admidio\Exception
     */
    protected function setOrder()
    {
        // check optional user parameter and make secure. Otherwise, set default value
        $this->order = admFuncVariableIsValid(
            $this->properties,
            'order',
            'string',
            array('defaultValue' => 'ASC', 'validValues' => array('ASC', 'DESC'))
        );
    }

    /**
     * Set startelement
     * @par If user string is set in $_GET Array the string is validated by Admidio function
     * and set as startelement in the modules. Otherwise, startelement is set to 0
     * @throws \Admidio\Exception
     */
    protected function setStartElement()
    {
        // check optional user parameter and make secure. Otherwise, set default value
        $this->start = admFuncVariableIsValid($this->properties, 'start', 'int');
    }

    /**
     * add a module parameter to the class
     * @param string $parameterName  The name of the parameter that should be added.
     * @param mixed  $parameterValue The value of the parameter that should be added.
     */
    public function setParameter(string $parameterName, $parameterValue)
    {
        if ($parameterName !== '') {
            $this->parameters[$parameterName] = $parameterValue;

            if ($parameterName === 'cat_id') {
                $this->catId = (int) $parameterValue;
            } elseif ($parameterName === 'role_type') {
                $this->roleType = (int) $parameterValue;
            } elseif ($parameterName === 'mode') {
                $this->mode = $parameterValue;
            }
        }
    }
}
