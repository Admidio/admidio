<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ConditionParser
 * @brief Creates from a custom condition syntax a sql condition
 *
 * The user can write a condition in a special syntax. This class will parse
 * that condition and creates a valid SQL statement which can be used in
 * another SQL statement to select data with these conditions.
 * This class uses AdmExceptions when an error occurred. Make sure you catch these
 * exceptions when using the class.
 * @par Examples
 * @code // create a valid SQL condition out of the special syntax
 * $parser = new ConditionParser;
 * $sqlCondition = $parser->makeSqlStatement('> 5 AND <= 100', 'usd_value', 'int');
 * $sql = 'SELECT * FROM '.TBL_USER_DATA.' WHERE usd_id > 0 AND '.$sqlCondition; @endcode
 */
class ConditionParser
{
    private $mSrcCond;              ///< The source condition with the user specific condition
    private $mDestCond;             ///< The destination string with the valid sql statement
    private $mSrcCondArray;         ///< An array from the string @b mSrcCond where every char is one array element
    private $mNotExistsSql = '';    ///< Stores the sql statement if a record should not exists when user wants to exclude a column
    private $mOpenQuotes = false;   ///< Flag if there is a open quote in this condition that must be closed before the next condition will be parsed

    /**
     * Creates a valid date format @b YYYY-MM-DD for the SQL statement
     * @param string $date     The unformated date from user input e.g. @b 12.04.2012
     * @param string $operator The actual operator for the @b date parameter
     * @return string String with a SQL valid date format @b YYYY-MM-DD
     */
    private function getFormatDate($date, $operator)
    {
        global $gPreferences;
        $formatDate = '';

        // if last char is Y or J then user searches for age
        $last = substr($date, -1);
        $last = admStrToUpper($last);

        if($last === 'J' || $last === 'Y')
        {
            $age  = (int) substr($date, 0, -1);
            $date = new DateTimeExtended(date('Y').'-'.date('m').'-'.date('d'), 'Y-m-d');
            $ageCondition = '';

            switch ($operator)
            {
                case '=':
                    // first remove = from destination condition
                    $this->mDestCond = substr($this->mDestCond, 0, strlen($this->mDestCond) - 4);

                    // now compute the dates for a valid birthday with that age
                    $date->modify('-'.$age.' years');
                    $dateTo = $date->format('Y-m-d');
                    $date->modify('-1 year');
                    $date->modify('+1 day');
                    $dateFrom = $date->format('Y-m-d');

                    $ageCondition = ' BETWEEN \''.$dateFrom.'\' AND \''.$dateTo.'\'';
                    $this->mOpenQuotes = false;
                    break;
                case '}':
                    // search for dates that are older than the age
                    // because the age itself takes 1 year we must add 1 year and 1 day to age
                    $date->modify('-'.($age+1).' years');
                    $date->modify('+1 day');
                    $ageCondition = $date->format('Y-m-d');
                    break;
                case '{':
                    // search for dates that are younger than the age
                    // we must add 1 day to the date because the day itself belongs to the age
                    $date->modify('-'.$age.' years');
                    $date->modify('+1 day');
                    $ageCondition = $date->format('Y-m-d');
                    break;
            }

            return $ageCondition;
        }

        // validate date and return it in database format
        if($date !== '')
        {
            $dateObject = DateTime::createFromFormat($gPreferences['system_date'], $date);

            if($dateObject !== false)
            {
                $formatDate = $dateObject->format('Y-m-d');
            }
        }

        return $formatDate;
    }

    /**
     * Replace different user conditions with predefined chars that
     * represents a special condition e.g. @b ! represents @b != and @b <>
     * @param string $sourceCondition The user condition string
     * @return string String with the predefined chars for conditions
     */
    public function makeStandardCondition($sourceCondition)
    {
        global $gL10n;

        $this->mSrcCond = admStrToUpper(trim($sourceCondition));
        $this->mSrcCond = strtr($this->mSrcCond, '*', '%');

        // valid 'not null' is '#'
        $this->mSrcCond = str_replace(admStrToUpper($gL10n->get('SYS_NOT_EMPTY')), ' # ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' NOT NULL ', ' # ', $this->mSrcCond);

        // valid 'null' is '_'
        $this->mSrcCond = str_replace(admStrToUpper($gL10n->get('SYS_EMPTY')), ' _ ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' NULL ', ' _ ', $this->mSrcCond);

        // valid 'is not' is '!'
        $this->mSrcCond = str_replace('{}',     ' ! ', $this->mSrcCond);
        $this->mSrcCond = str_replace('!=',     ' ! ', $this->mSrcCond);

        // valid 'is' is '='
        $this->mSrcCond = str_replace('==',     ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' LIKE ', ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' IS ',   ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' IST ',  ' = ', $this->mSrcCond);

        // valid 'less than' is '['
        $this->mSrcCond = str_replace('{=',     ' [ ', $this->mSrcCond);
        $this->mSrcCond = str_replace('={',     ' [ ', $this->mSrcCond);

        // valid 'greater than' is ']'
        $this->mSrcCond = str_replace('}=',     ' ] ', $this->mSrcCond);
        $this->mSrcCond = str_replace('=}',     ' ] ', $this->mSrcCond);

        // valid 'and' is '&'
        $this->mSrcCond = str_replace(' AND ',  ' & ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' UND ',  ' & ', $this->mSrcCond);
        $this->mSrcCond = str_replace('&&',     ' & ', $this->mSrcCond);
        $this->mSrcCond = str_replace('+',      ' & ', $this->mSrcCond);

        // valid 'or' is '|'
        $this->mSrcCond = str_replace(' OR ',   ' | ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' ODER ', ' | ', $this->mSrcCond);
        $this->mSrcCond = str_replace('||',     ' | ', $this->mSrcCond);

        return $this->mSrcCond;
    }

    /**
     * Creates from a user defined condition a valid SQL condition
     * @param string $sourceCondition The user condition string
     * @param string $columnName      The name of the database column for which the condition should be created
     * @param string $columnType      The type of the column. Valid types are @b string, @b int, @b date and @b checkbox
     * @param string $fieldName       The name of the profile field. This is used for error output to the end user
     * @throws AdmException LST_NOT_VALID_DATE_FORMAT
     *                      LST_NOT_NUMERIC
     * @return string Returns a valid SQL string with the condition for that column
     */
    public function makeSqlStatement($sourceCondition, $columnName, $columnType, $fieldName)
    {
        $bStartCondition   = true;     // gibt an, dass eine neue Bedingung angefangen wurde
        $bNewCondition     = true;     // in Stringfeldern wird nach einem neuen Wort gesucht -> neue Bedingung
        $bStartOperand     = false;    // gibt an, ob bei num. oder Datumsfeldern schon <>= angegeben wurde
        $this->mOpenQuotes = false;    // set to true if quotes for conditions are open
        $date              = '';       // Variable speichert bei Datumsfeldern das gesamte Datum
        $operator          = '=';      // saves the actual operator, if no operator is set then = will be default
        $this->mDestCond   = '';

        if($sourceCondition !== '' && $columnName !== '' && $columnType !== '')
        {
            $this->mSrcCond      = $this->makeStandardCondition($sourceCondition);
            $this->mSrcCondArray = str_split($this->mSrcCond);

            // Bedingungen fuer das Feld immer mit UND starten
            if($columnType === 'string')
            {
                $this->mDestCond = ' AND ( UPPER('.$columnName.') ';
            }
            elseif($columnType === 'checkbox')
            {
                // Sonderfall !!!
                // bei einer Checkbox kann es nur 1 oder 0 geben und keine komplizierten Verknuepfungen
                if($sourceCondition == 1)
                {
                    $this->mDestCond = ' AND '.$columnName.' = 1 ';
                }
                else
                {
                    $this->mDestCond = ' AND ('.$columnName.' IS NULL OR '.$columnName.' = 0) ';
                }
                return $this->mDestCond;
            }
            else
            {
                $this->mDestCond = ' AND ( '.$columnName.' ';
            }

            // Zeichen fuer Zeichen aus dem Bedingungsstring wird hier verarbeitet
            for($mCount = 0; $mCount < strlen($this->mSrcCond); ++$mCount)
            {
                $character = $this->mSrcCondArray[$mCount];

                if($character === '&' || $character === '|')
                {
                    if($bNewCondition)
                    {
                        // neue Bedingung, also Verknuepfen
                        if($character === '&')
                        {
                            $this->mDestCond = $this->mDestCond.' AND ';
                        }
                        elseif($character === '|')
                        {
                            $this->mDestCond = $this->mDestCond.' OR ';
                        }

                        // Feldname noch dahinter
                        if($columnType === 'string')
                        {
                            $this->mDestCond = $this->mDestCond.' UPPER('.$columnName.') ';
                        }
                        else
                        {
                            $this->mDestCond = $this->mDestCond.' '.$columnName.' ';
                        }

                        $bStartCondition = true;
                    }
                }
                else
                {
                    // Verleich der Werte wird hier verarbeitet
                    if($character === '='
                    || $character === '!'
                    || $character === '_'
                    || $character === '#'
                    || $character === '{'
                    || $character === '}'
                    || $character === '['
                    || $character === ']')
                    {
                        // save actual operator for later use
                        $operator = $character;

                        if(!$bStartCondition)
                        {
                            $this->mDestCond = $this->mDestCond.' AND '.$columnName.' ';
                            $bStartCondition = true;
                        }

                        switch ($character)
                        {
                            case '=':
                                if ($columnType === 'string')
                                {
                                    $this->mDestCond = $this->mDestCond.' LIKE ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' = ';
                                }
                                break;
                            case '!':
                                if ($columnType === 'string')
                                {
                                    $this->mDestCond = $this->mDestCond.' NOT LIKE ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' <> ';
                                }
                                break;
                            case '_':
                                $this->mDestCond = $this->mDestCond.' IS NULL ';
                                if($this->mNotExistsSql !== '')
                                {
                                    $this->mDestCond = $this->mDestCond.' OR NOT EXISTS ('.$this->mNotExistsSql.') ';
                                }
                                break;
                            case '#':
                                $this->mDestCond = $this->mDestCond.' IS NOT NULL ';
                                if($this->mNotExistsSql !== '')
                                {
                                    $this->mDestCond = $this->mDestCond.' OR EXISTS ('.$this->mNotExistsSql.') ';
                                }
                                break;
                            case '{':
                                // bastwe: invert condition on age search
                                if($columnType === 'date'
                                    && (strstr(admStrToUpper($sourceCondition), 'J') !== false
                                        || strstr(admStrToUpper($sourceCondition), 'Y') !== false))
                                {
                                    $this->mDestCond = $this->mDestCond.' > ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' < ';
                                }
                                break;
                            case '}':
                                // bastwe: invert condition on age search
                                if($columnType === 'date'
                                    && (strstr(admStrToUpper($sourceCondition), 'J') !== false
                                        || strstr(admStrToUpper($sourceCondition), 'Y') !== false))
                                {
                                    $this->mDestCond = $this->mDestCond.' < ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' > ';
                                }
                                break;
                            case '[':
                                // bastwe: invert condition on age search
                                if($columnType === 'date'
                                    && (strstr(admStrToUpper($sourceCondition), 'J') !== false
                                        || strstr(admStrToUpper($sourceCondition), 'Y') !== false))
                                {
                                    $this->mDestCond = $this->mDestCond.' >= ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' <= ';
                                }
                                break;
                            case ']':
                                // bastwe: invert condition on age search
                                if($columnType === 'date'
                                    && (strstr(admStrToUpper($sourceCondition), 'J') !== false
                                        || strstr(admStrToUpper($sourceCondition), 'Y') !== false))
                                {
                                    $this->mDestCond = $this->mDestCond.' <= ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' >= ';
                                }
                                break;
                            default:
                                $this->mDestCond = $this->mDestCond.$character;
                        }

                        if($character !== '_' && $character !== '#')
                        {
                            // allways set quote marks for a value because some fields are a varchar in db
                            // but should only filled with integer
                            $this->mDestCond   = $this->mDestCond.' \'';
                            $this->mOpenQuotes = true;
                            $bStartOperand     = true;
                        }
                    }
                    else
                    {
                        // pruefen, ob ein neues Wort anfaengt
                        if($character === ' ' && !$bNewCondition)
                        {
                            // if date column than the date will be saved in $date.
                            // This variable must then be parsed and changed in a valid database format
                            if($columnType === 'date' && $date !== '')
                            {
                                if($this->getFormatDate($date, $operator) !== '')
                                {
                                    $this->mDestCond = $this->mDestCond.$this->getFormatDate($date, $operator);
                                }
                                else
                                {
                                    throw new AdmException('LST_NOT_VALID_DATE_FORMAT', $fieldName);
                                }
                                $date = '';
                            }

                            if($this->mOpenQuotes)
                            {
                                // allways set quote marks for a value because some fields are a varchar in db
                                // but should only filled with integer
                                $this->mDestCond   = $this->mDestCond.'\' ';
                                $this->mOpenQuotes = false;
                            }

                            $bNewCondition = true;
                        }
                        elseif($character !== ' ')
                        {
                            // neues Suchwort, aber noch keine Bedingung

                            if($bNewCondition && !$bStartCondition)
                            {
                                if($columnType === 'string')
                                {
                                    $this->mDestCond = $this->mDestCond.' AND UPPER('.$columnName.') ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' AND '.$columnName.' = ';
                                }
                                $this->mOpenQuotes = false;
                            }
                            elseif($bNewCondition && !$bStartOperand)
                            {
                                // first condition of these column
                                if($columnType === 'string')
                                {
                                    $this->mDestCond = $this->mDestCond.' LIKE \'';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond.' = \'';
                                }
                                $this->mOpenQuotes = true;
                            }

                            // Zeichen an Zielstring dranhaengen
                            if($columnType === 'date')
                            {
                                $date = $date.$character;
                            }
                            elseif($columnType === 'int' && !is_numeric($character))
                            {
                                // if numeric field than only numeric characters are allowed
                                throw new AdmException('LST_NOT_NUMERIC', $fieldName);
                            }
                            else
                            {
                                $this->mDestCond = $this->mDestCond.$character;
                            }

                            $bNewCondition   = false;
                            $bStartCondition = false;
                        }
                    }
                }
            }

            // if date column than the date will be saved in $date.
            // This variable must then be parsed and changed in a valid database format
            if($columnType === 'date' && $date !== '')
            {
                if($this->getFormatDate($date, $operator) !== '')
                {
                    $this->mDestCond = $this->mDestCond.$this->getFormatDate($date, $operator);
                }
                else
                {
                    throw new AdmException('LST_NOT_VALID_DATE_FORMAT', $fieldName);
                }
            }

            if($this->mOpenQuotes)
            {
                // allways set quote marks for a value because some fields are a varchar in db
                // but should only filled with integer
                $this->mDestCond = $this->mDestCond.'\' ';
            }

            $this->mDestCond = $this->mDestCond.' ) ';
        }

        return $this->mDestCond;
    }

    /**
     * Stores an sql statement that checks if a record in a table does exists or not exists.
     * This must bei a full subselect that starts with SELECT. The statement is used if
     * a condition with EMPTY or NOT EMPTY is used.
     * @param string $sqlStatement String with the full subselect
     * @par Examples
     * @code $parser->setNotExistsStatement('SELECT 1 FROM adm_user_data WHERE usd_usr_id = 1 AND usd_usf_id = 9'); @endcode
     */
    public function setNotExistsStatement($sqlStatement)
    {
        $this->mNotExistsSql = $sqlStatement;
    }
}
