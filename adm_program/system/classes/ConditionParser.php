<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates from a custom condition syntax a sql condition
 *
 * The user can write a condition in a special syntax. This class will parse
 * that condition and creates a valid SQL statement which can be used in
 * another SQL statement to select data with these conditions.
 * This class uses AdmExceptions when an error occurred. Make sure you catch these
 * exceptions when using the class.
 *
 * **Code example**
 * ```
 * // create a valid SQL condition out of the special syntax
 * $parser = new ConditionParser();
 * $sqlCondition = $parser->makeSqlStatement('> 5 AND <= 100', 'usd_value', 'int');
 * $sql = 'SELECT * FROM '.TBL_USER_DATA.' WHERE usd_id > 0 AND '.$sqlCondition;
 * ```
 */
class ConditionParser
{
    /**
     * @var string The source condition with the user specific condition
     */
    private $srcCond = '';
    /**
     * @var string The destination string with the valid sql statement
     */
    private $destCond = '';
    /**
     * @var string Stores the sql statement if a record should not exist when user wants to exclude a column
     */
    private $notExistsSql = '';
    /**
     * @var bool Flag if there is an open quote in this condition that must be closed before the next condition will be parsed
     */
    private $openQuotes = false;

    /**
     * constructor that will initialize variables
     */
    public function __construct()
    {
    }

    /**
     * Ends the "DestCondition"
     */
    private function endDestCond()
    {
        if ($this->openQuotes) {
            // always set quote marks for a value because some fields are a varchar in db
            // but should only be filled with integer
            $this->destCond .= '\' ';
        }

        $this->destCond .= ' ) ';
    }

    /**
     * Creates a valid date format **YYYY-MM-DD** for the SQL statement
     * @param string $date The not formatted date from user input e.g. **12.04.2012**
     * @param string $operator The actual operator for the **date** parameter
     * @return string String with a SQL valid date format **YYYY-MM-DD** or empty string
     * @throws Exception
     */
    private function getFormatDate(string $date, string $operator): string
    {
        global $gSettingsManager;

        // if last char is Y or J then user searches for age
        $lastDateChar = strtoupper(substr($date, -1));

        if ($lastDateChar === 'J' || $lastDateChar === 'Y') {
            $ageCondition = '';
            $dateObj = new DateTime();
            $years   = new DateInterval('P' . substr($date, 0, -1) . 'Y');
            $oneYear = new DateInterval('P1Y');
            $oneDay  = new DateInterval('P1D');
            $dateObj->sub($years);

            switch ($operator) {
                case '=':
                    // first remove = from destination condition
                    $this->destCond = substr($this->destCond, 0, -4);

                    // now compute the dates for a valid birthday with that age
                    $dateTo = $dateObj->format('Y-m-d');

                    $dateObj->sub($oneYear)->add($oneDay);
                    $dateFrom = $dateObj->format('Y-m-d');

                    $ageCondition = ' BETWEEN \'' . $dateFrom . '\' AND \'' . $dateTo . '\'';
                    $this->openQuotes = false;
                    break;
                case '}':
                    // search for dates that are older than the age
                    // because the age itself takes 1 year we must subtract 1 year to age
                    $dateObj->sub($oneYear)->add($oneDay);
                    $ageCondition = $dateObj->format('Y-m-d');
                    break;
                case '{':
                    // search for dates that are younger than the age
                    // we must add 1 day to the date because the day itself belongs to the age
                    $dateObj->add($oneDay);
                    $ageCondition = $dateObj->format('Y-m-d');
                    break;
                case ']':
                    // search for dates that are older or equal than the age
                    $ageCondition = $dateObj->format('Y-m-d');
                    break;
                case '[':
                    // search for dates that are younger or equal than the age
                    // because the age itself takes 1 year we must subtract another 1 year but the day itself must be ignored to age
                    $dateObj->sub($oneYear)->add($oneDay);
                    $ageCondition = $dateObj->format('Y-m-d');
                    break;
            }

            return $ageCondition;
        }

        // validate date and return it in database format
        if ($date !== '') {
            $dateObject = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $date);
            if ($dateObject !== false) {
                return $dateObject->format('Y-m-d');
            }
        }

        return '';
    }

    /**
     * @param string $columnType
     * @param string $sourceCondition
     * @return bool Returns true if date search and false if age search
     */
    private static function isDateSearch(string $columnType, string $sourceCondition): bool
    {
        return $columnType === 'date' && (StringUtils::strContains($sourceCondition, 'J', false) || StringUtils::strContains($sourceCondition, 'Y', false));
    }

    /**
     * Stores a sql statement that checks if a record in a table does exist or not exist.
     * This must bei a full subselect that starts with SELECT. The statement is used if
     * a condition with EMPTY or NOT EMPTY is used.
     * @param string $sqlStatement String with the full subselect
     * **Code example:**
     * ```
     * $parser->setNotExistsStatement('SELECT 1 FROM adm_user_data WHERE usd_usr_id = 1 AND usd_usf_id = 9');
     * ```
     */
    public function setNotExistsStatement(string $sqlStatement)
    {
        $this->notExistsSql = $sqlStatement;
    }

    /**
     * Creates from a user defined condition a valid SQL condition
     * @param string $sourceCondition The user condition string
     * @param string $columnName The name of the database column for which the condition should be created
     * @param string $columnType The type of the column. Valid types are **string**, **int**, **date** and **checkbox**
     * @param string $fieldName The name of the profile field. This is used for error output to the end user
     * @return string Returns a valid SQL string with the condition for that column
     * @throws AdmException
     * @throws Exception
     */
    public function makeSqlStatement(string $sourceCondition, string $columnName, string $columnType, string $fieldName): string
    {
        $conditionComplete = $this->startDestCond($columnType, $columnName, $sourceCondition);
        if ($conditionComplete) {
            return $this->destCond;
        }

        $this->openQuotes = false;    // set to true if quotes for conditions are open
        $startCondition   = true;     // Indicates that a new condition has been started
        $newCondition     = true;     // a new word is searched for in text fields -> new condition
        $startOperand     = false;    // Indicates whether <>= has already been specified for numeric or date fields
        $date             = '';       // Variable stores the entire date for date fields
        $operator         = '=';      // saves the actual operator, if no operator is set then = will be default

        $this->makeStandardCondition($sourceCondition);
        $srcCondArray = str_split($this->srcCond);

        // Character for character from the condition string is processed here
        foreach ($srcCondArray as $character) {
            if ($character === '&' || $character === '|') {
                if ($newCondition) {
                    // new condition, i.e. linking
                    if ($character === '&') {
                        $this->destCond .= ' AND ';
                    } else {
                        $this->destCond .= ' OR ';
                    }

                    // Field name still behind it
                    if ($columnType === 'string') {
                        $this->destCond .= ' UPPER(' . $columnName . ') ';
                    } else {
                        $this->destCond .= ' ' . $columnName . ' ';
                    }

                    $startCondition = true;
                }
            }
            // Comparison of the values is processed here
            elseif (in_array($character, array('=', '!', '_', '#', '{', '}', '[', ']'), true)) {
                // save actual operator for later use
                $operator = $character;

                if (!$startCondition) {
                    $this->destCond .= ' AND ' . $columnName . ' ';
                    $startCondition = true;
                }

                switch ($character) {
                    case '=':
                        if ($columnType === 'string') {
                            $this->destCond .= ' LIKE ';
                        } else {
                            $this->destCond .= ' = ';
                        }
                        break;
                    case '!':
                        if ($columnType === 'string') {
                            $this->destCond .= ' NOT LIKE ';
                        } else {
                            $this->destCond .= ' <> ';
                        }
                        break;
                    case '_':
                        $this->destCond .= ' IS NULL ';
                        if ($this->notExistsSql !== '') {
                            $this->destCond .= ' OR NOT EXISTS (' . $this->notExistsSql . ') ';
                        }
                        break;
                    case '#':
                        $this->destCond .= ' IS NOT NULL ';
                        if ($this->notExistsSql !== '') {
                            $this->destCond .= ' OR EXISTS (' . $this->notExistsSql . ') ';
                        }
                        break;
                    case '{':
                        // invert condition on age search
                        if (self::isDateSearch($columnType, $sourceCondition)) {
                            $this->destCond .= ' > ';
                        } else {
                            $this->destCond .= ' < ';
                        }
                        break;
                    case '}':
                        // invert condition on age search
                        if (self::isDateSearch($columnType, $sourceCondition)) {
                            $this->destCond .= ' < ';
                        } else {
                            $this->destCond .= ' > ';
                        }
                        break;
                    case '[':
                        // invert condition on age search
                        if (self::isDateSearch($columnType, $sourceCondition)) {
                            $this->destCond .= ' >= ';
                        } else {
                            $this->destCond .= ' <= ';
                        }
                        break;
                    case ']':
                        // invert condition on age search
                        if (self::isDateSearch($columnType, $sourceCondition)) {
                            $this->destCond .= ' <= ';
                        } else {
                            $this->destCond .= ' >= ';
                        }
                        break;
                    default:
                        $this->destCond .= $character;
                }

                if ($character !== '_' && $character !== '#') {
                    // always set quote marks for a value because some fields are a varchar in db
                    // but should only be filled with integer
                    $this->destCond  .= ' \'';
                    $this->openQuotes = true;
                    $startOperand     = true;
                }
            } elseif ($character === ' ') {
                // check whether a new word begins
                if (!$newCondition) {
                    // if date column than the date will be saved in $date.
                    // This variable must then be parsed and changed in a valid database format
                    if ($columnType === 'date' && $date !== '') {
                        $formatDate = $this->getFormatDate($date, $operator);
                        if ($formatDate !== '') {
                            $this->destCond .= $formatDate;
                        } else {
                            throw new AdmException('SYS_NOT_VALID_DATE_FORMAT', array($fieldName));
                        }
                        $date = '';
                    }

                    if ($this->openQuotes) {
                        // always set quote marks for a value because some fields are a varchar in db
                        // but should only be filled with integer
                        $this->destCond  .= '\' ';
                        $this->openQuotes = false;
                    }

                    $newCondition = true;
                }
            } else {
                // neues Suchwort, aber noch keine Bedingung

                if ($newCondition && !$startCondition) {
                    if ($columnType === 'string') {
                        $this->destCond .= ' AND UPPER(' . $columnName . ') ';
                    } else {
                        $this->destCond .= ' AND ' . $columnName . ' = ';
                    }
                    $this->openQuotes = false;
                } elseif ($newCondition && !$startOperand) {
                    // first condition of this column
                    if ($columnType === 'string') {
                        $this->destCond .= ' LIKE \'';
                    } else {
                        $this->destCond .= ' = \'';
                    }
                    $this->openQuotes = true;
                }

                // Append character to target string
                if ($columnType === 'date') {
                    $date .= $character;
                } elseif ($columnType === 'int' && !is_numeric($character)) {
                    // if numeric field than only numeric characters are allowed
                    throw new AdmException('SYS_NOT_NUMERIC', array($fieldName));
                } else {
                    $this->destCond .= $character;
                }

                $newCondition   = false;
                $startCondition = false;
            }
        }

        // if date column than the date will be saved in $date.
        // This variable must then be parsed and changed in a valid database format
        if ($columnType === 'date' && $date !== '') {
            $formatDate = $this->getFormatDate($date, $operator);
            if ($formatDate !== '') {
                $this->destCond .= $formatDate;
            } else {
                throw new AdmException('SYS_NOT_VALID_DATE_FORMAT', array($fieldName));
            }
        }

        $this->endDestCond();

        return $this->destCond;
    }

    /**
     * Replace different user conditions with predefined chars that
     * represents a special condition e.g. **!** represents **!=** and **<>**
     * @param string $sourceCondition The user condition string
     * @return string String with the predefined chars for conditions
     */
    public function makeStandardCondition(string $sourceCondition): string
    {
        global $gL10n;

        $this->srcCond = StringUtils::strToUpper(trim($sourceCondition));

        $replaces = array(
            '*' => '%',
            // valid 'not null' is '#'
            StringUtils::strToUpper($gL10n->get('SYS_NOT_EMPTY')) => ' # ',
            ' NOT NULL '                                          => ' # ',
            // valid 'null' is '_'
            StringUtils::strToUpper($gL10n->get('SYS_EMPTY')) => ' _ ',
            ' NULL '                                          => ' _ ',
            // valid 'is not' is '!'
            '{}'     => ' ! ',
            '!='     => ' ! ',
            // valid 'is' is '='
            '=='     => ' = ',
            ' LIKE ' => ' = ',
            ' IS '   => ' = ',
            ' IST '  => ' = ',
            // valid 'less than' is '['
            '{='     => ' [ ',
            '={'     => ' [ ',
            // valid 'greater than' is ']'
            '}='     => ' ] ',
            '=}'     => ' ] ',
            // valid 'and' is '&'
            ' AND '  => ' & ',
            ' UND '  => ' & ',
            '&&'     => ' & ',
            '+'      => ' & ',
            // valid 'or' is '|'
            ' OR '   => ' | ',
            ' ODER ' => ' | ',
            '||'     => ' | '
        );
        $this->srcCond = StringUtils::strMultiReplace($this->srcCond, $replaces);

        return $this->srcCond;
    }

    /**
     * Starts the "DestCondition"
     * @param string $columnType      The type of the column. Valid types are **string**, **int**, **date** and **checkbox**
     * @param string $columnName      The name of the database column for which the condition should be created
     * @param string $sourceCondition The user condition string
     * @return bool Returns true if "mDestCondition" is complete
     */
    private function startDestCond(string $columnType, string $columnName, string $sourceCondition): bool
    {
        $this->destCond = ' AND ';  // Conditions for the field always start with AND

        if ($columnType === 'string') {
            $this->destCond .= '( UPPER(' . $columnName . ') ';
        } elseif ($columnType === 'checkbox') {
            // Special case !
            // a checkbox can only have 1 or 0 and no complicated links
            if ($sourceCondition === '1') {
                $this->destCond .= $columnName . ' = 1 ';
            } else {
                $this->destCond .= '(' . $columnName . ' IS NULL OR ' . $columnName . ' = 0) ';
            }

            return true;
        }
        // $columnType = "int" or "date"
        else {
            $this->destCond .= '( ' . $columnName . ' ';
        }

        return false;
    }
}
