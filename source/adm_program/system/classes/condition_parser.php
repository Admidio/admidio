<?php
/*****************************************************************************/
/** @class ConditionParser
 *  @brief Creates from a custom condition syntax a sql condition
 *
 *  The user can write a condition in a special syntax. This class will parse 
 *  that condition and creates a valid SQL statement which can be used in 
 *  another SQL statement to select data with these conditions.
 *  This class uses AdmExceptions when an error occured. Make sure you catch these 
 *  exeptions when using the class.
 *  @par Examples
 *  @code // create a valid SQL condition out of the special syntax
 *  $parser = new ConditionParser;
 *  $sqlCondition = $parser->makeSqlStatement('> 5 AND <= 100', 'usd_value', 'int');
 *  $sql = 'SELECT * FROM '.TBL_USER_DATA.' WHERE usd_id > 0 AND '.$sqlCondition; @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/datetime_extended.php');
 
class ConditionParser
{
    private $mSrcCond;				///< The source condition with the user specific condition
    private $mDestCond;				///< The destination string with the valid sql statement
    private $mSrcCondArray;			///< An array from the string @b mSrcCond where every char is one array element
    private $mCount;     			///< Actual index in array @b mSrcCondArray
	private $mNotExistsSql = '';	///< Stores the sql statement if a record should not exists when user wants to exclude a column

	/** creates a valid date format @b YYYY-MM-DD for the SQL statement
	 *  @param $date The unformated date from user input e.g. @b 12.04.2012
	 *  @return String with a SQL valid date format @b YYYY-MM-DD
	 */
    private function getFormatDate($date)
    {
        global $gPreferences;
        $formatDate = '';

        // bastwe: check if user searches for age
        $last = substr($date, -1);
        $last = admStrToUpper($last);
        if( $last == 'J' || $last == 'Y') 
        {
            $age = substr($date, 0, -1);
            $nowYear= date('Y');
            $nowDay = date('d');
            $nowMonth = date('m');
            $ret = date('Y-m-d', mktime(0,0,0, $nowMonth, $nowDay, $nowYear - $age));
            return '\''. $ret. '\'';
        }
        
        // Datum validieren und im MySQL-Format ausgeben
        if(strlen($date) > 0)
        {
            $date = new DateTimeExtended($date, $gPreferences['system_date'], 'date');
            if($date->valid())
            {
                $formatDate = $date->format('Y-m-d');
            }
        }
        return $formatDate;
    }

	/** Replace different user conditions with predefined chars that 
	 *  represents a special condition e.g. @b ! represents @b != and @b <>
	 *  @param $sourceCondition The user condition string
	 *  @return String with the predefined chars for conditions
	 */
    public function makeStandardCondition($sourceCondition)
    {
		global $gL10n;
	
        $this->mSrcCond = admStrToUpper(trim($sourceCondition));
        $this->mSrcCond = strtr($this->mSrcCond, '*', '%');

		// valid 'not empty' is '#'
        $this->mSrcCond = str_replace(admStrToUpper($gL10n->get('SYS_NOT_EMPTY')), ' # ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' NOT NULL ', ' # ', $this->mSrcCond);
		
		// valid 'empty' is '_'
        $this->mSrcCond = str_replace(admStrToUpper($gL10n->get('SYS_EMPTY')), ' _ ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' NULL ', ' _ ', $this->mSrcCond);

        // gueltiges 'ungleich' ist '!'
        $this->mSrcCond = str_replace('{}', ' ! ', $this->mSrcCond);
        $this->mSrcCond = str_replace('!=', ' ! ', $this->mSrcCond);

        // gueltiges 'gleich' ist '='
        $this->mSrcCond = str_replace('==',     ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' LIKE ', ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' IS ',   ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' IST ',  ' = ', $this->mSrcCond);

        // gueltiges 'kleiner gleich' is '['
        $this->mSrcCond = str_replace('{=',   ' [ ', $this->mSrcCond);
        $this->mSrcCond = str_replace('={',   ' [ ', $this->mSrcCond);

        // gueltiges 'groesser gleich' is ']'
        $this->mSrcCond = str_replace('}=',   ' ] ', $this->mSrcCond);
        $this->mSrcCond = str_replace('=}',   ' ] ', $this->mSrcCond);

        // gueltiges 'und' ist '&'
        $this->mSrcCond = str_replace(' AND ', ' & ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' UND ', ' & ', $this->mSrcCond);
        $this->mSrcCond = str_replace('&&',    ' & ', $this->mSrcCond);
        $this->mSrcCond = str_replace('+',     ' & ', $this->mSrcCond);

        // gueltiges 'oder' ist '|'
        $this->mSrcCond = str_replace(' OR ',   ' | ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' ODER ', ' | ', $this->mSrcCond);
        $this->mSrcCond = str_replace('||',     ' | ', $this->mSrcCond);

        return $this->mSrcCond;
    }
   
	/** Creates from a user defined condition a valid SQL condition 
	 *  @param $sourceCondition The user condition string
	 *  @param $columnName 		The name of the database column for which the condition should be created
	 *  @param $columnType 		The type of the column. Valid types are @b string, @b int, @b date and @b checkbox
	 *  @return Returns a valid SQL string with the condition for that column
	 */
    public function makeSqlStatement($sourceCondition, $columnName, $columnType)
    {
        $bStartCondition = true;   // gibt an, dass eine neue Bedingung angefangen wurde
        $bNewCondition   = true;   // in Stringfeldern wird nach einem neuen Wort gesucht -> neue Bedingung
        $bStartOperand   = false;  // gibt an, ob bei num. oder Datumsfeldern schon <>= angegeben wurde
        $bOpenQuotes     = false;  // set to true if quotes for conditions are open
        $date            = '';     // Variable speichert bei Datumsfeldern das gesamte Datum
        $this->mDestCond    = '';

        if(strlen($sourceCondition) > 0 && strlen($columnName) > 0 && strlen($columnType) > 0)
        {
            $this->mSrcCond     = $this->makeStandardCondition($sourceCondition);
            $this->mSrcCondArray = str_split($this->mSrcCond);
			error_log($this->mSrcCond);
    
            // Bedingungen fuer das Feld immer mit UND starten
            if($columnType == 'string')
            {
				$this->mDestCond = ' AND ( UPPER('.$columnName.') ';
            }
            elseif($columnType == 'checkbox')
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
            for($this->mCount = 0; $this->mCount < strlen($this->mSrcCond); $this->mCount++)
            {
                if($this->mSrcCondArray[$this->mCount] == '&'
                || $this->mSrcCondArray[$this->mCount] == '|' )
                {
                    if($bNewCondition)
                    {
                        // neue Bedingung, also Verknuepfen
                        if($this->mSrcCondArray[$this->mCount] == '&')
                        {
                            $this->mDestCond = $this->mDestCond. ' AND ';
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '|')
                        {
                            $this->mDestCond = $this->mDestCond. ' OR ';
                        }
    
                        // Feldname noch dahinter
                        if($columnType == 'string')
                        {
                            $this->mDestCond = $this->mDestCond. ' UPPER('.$columnName.') ';
                        }
                        else
                        {
                            $this->mDestCond = $this->mDestCond. ' '.$columnName.' ';
                        }
    
                        $bStartCondition = true;
                    }
                }
                else
                {
                    // Verleich der Werte wird hier verarbeitet
                    if($this->mSrcCondArray[$this->mCount] == '='
                    || $this->mSrcCondArray[$this->mCount] == '!'
                    || $this->mSrcCondArray[$this->mCount] == '_'
                    || $this->mSrcCondArray[$this->mCount] == '#'
                    || $this->mSrcCondArray[$this->mCount] == '{'
                    || $this->mSrcCondArray[$this->mCount] == '}'
                    || $this->mSrcCondArray[$this->mCount] == '['
                    || $this->mSrcCondArray[$this->mCount] == ']' )
                    {
                        if(!$bStartCondition)
                        {
                            $this->mDestCond = $this->mDestCond. ' AND '.$columnName.' ';
                            $bStartCondition = true;
                        }
    
                        if($this->mSrcCondArray[$this->mCount] == '=')
                        {
                            if ($columnType == 'string') 
							{
								$this->mDestCond = $this->mDestCond. ' LIKE ';
							} 
							else 
							{
								$this->mDestCond = $this->mDestCond. ' = ';
							}
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '!')
                        {
                            if ($columnType == 'string') 
							{
								$this->mDestCond = $this->mDestCond. ' NOT LIKE ';
							} 
							else 
							{
								$this->mDestCond = $this->mDestCond. ' <> ';
							}
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '_')
                        {
                            $this->mDestCond = $this->mDestCond. ' IS NULL ';
							if(strlen($this->mNotExistsSql) > 0)
							{
								$this->mDestCond = $this->mDestCond. ' OR NOT EXISTS ('.$this->mNotExistsSql.') ';
							}
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '#')
                        {
                            $this->mDestCond = $this->mDestCond. ' IS NOT NULL ';
							if(strlen($this->mNotExistsSql) > 0)
							{
								$this->mDestCond = $this->mDestCond. ' OR EXISTS ('.$this->mNotExistsSql.') ';
							}
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '{')
                        {
                            // bastwe: invert condition on age search
                            if( $columnType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE )
                            {
                                $this->mDestCond = $this->mDestCond. ' > ';
                            } 
                            else 
                            { 
                                $this->mDestCond = $this->mDestCond. ' < ';
                            }
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '}')
                        {
                            // bastwe: invert condition on age search
                            if( $columnType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE ) 
                            {
                                $this->mDestCond = $this->mDestCond. ' < ';
                            } 
                            else 
                            { 
                                $this->mDestCond = $this->mDestCond. ' > ';
                            }
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '[')
                        {
                            // bastwe: invert condition on age search
                            if( $columnType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE ) 
                            {
                                $this->mDestCond = $this->mDestCond. ' >= ';
                            } 
                            else 
                            {
                                $this->mDestCond = $this->mDestCond. ' <= ';
                            }
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == ']')
                        {
                            // bastwe: invert condition on age search
                            if( $columnType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE ) 
                            {
                                $this->mDestCond = $this->mDestCond. ' <= ';
                            } 
                            else 
                            {
                                $this->mDestCond = $this->mDestCond. ' >= ';
                            }
                        }
                        else
                        {
                            $this->mDestCond = $this->mDestCond. $this->mSrcCondArray[$this->mCount];
                        }
    
                        if($this->mSrcCondArray[$this->mCount] != '_'
                        && $this->mSrcCondArray[$this->mCount] != '#')
                        {
                            // allways set quote marks for a value because some fields are a varchar in db
                            // but should only filled with integer
                            $this->mDestCond = $this->mDestCond. ' \'';
                            $bOpenQuotes = true;
                            $bStartOperand = true;
                        }
                    }
                    else
                    {
                        // pruefen, ob ein neues Wort anfaengt
                        if($this->mSrcCondArray[$this->mCount] == ' '
                        && $bNewCondition == false )
                        {
                            if($bOpenQuotes == true)
                            {
                                // allways set quote marks for a value because some fields are a varchar in db
                                // but should only filled with integer
                                $this->mDestCond = $this->mDestCond. '\' ';
                                $bOpenQuotes = false;
                            }

							if($columnType == 'date')
                            {
                                if(strlen($this->getFormatDate($date)) > 0)
                                {
                                    $this->mDestCond = $this->mDestCond. $this->getFormatDate($date);
                                }
                                else
                                {
									throw new AdmException('LST_NOT_VALID_DATE_FORMAT');
                                }
                                $date = '';
                            }
                            $bNewCondition = true;
                        }
                        elseif($this->mSrcCondArray[$this->mCount] != ' ')
                        {
                            // neues Suchwort, aber noch keine Bedingung
                            
                            if($bNewCondition && !$bStartCondition)
                            {
                                if($columnType == 'string')
                                {
                                    $this->mDestCond = $this->mDestCond. ' AND UPPER('.$columnName.') ';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond. ' AND '.$columnName.' = ';
                                }
                                $bOpenQuotes = false;
                            }
                            elseif($bNewCondition && !$bStartOperand)
                            {
								// first condition of these column
								if($columnType == 'string')
								{
									$this->mDestCond = $this->mDestCond. ' LIKE \'';
								}
								else
								{
									$this->mDestCond = $this->mDestCond. ' = \'';
								}
								$bOpenQuotes = true;
                            }
    
                            // Zeichen an Zielstring dranhaengen
                            if($columnType == 'date')
                            {
                                $date = $date. $this->mSrcCondArray[$this->mCount];
                            }
							elseif($columnType == 'int' && is_numeric($this->mSrcCondArray[$this->mCount]) == false)
							{
								// if numeric field than only numeric characters are allowed
								throw new AdmException('LST_NOT_NUMERIC');
							}
                            else
                            {
                                $this->mDestCond = $this->mDestCond. $this->mSrcCondArray[$this->mCount];
                            }
    
                            $bNewCondition   = false;
                            $bStartCondition = false;
                        }
                    }
                }
            }
    
            // Falls als letztes ein Datum verglichen wurde, dann dieses noch einbauen
            if($columnType == 'date')
            {
                if(strlen($this->getFormatDate($date)) > 0)
                {
                    $this->mDestCond = $this->mDestCond. $this->getFormatDate($date);
                }
                else
                {
					throw new AdmException('LST_NOT_VALID_DATE_FORMAT');
                }
            }

            if($bOpenQuotes == true)
            {
                // allways set quote marks for a value because some fields are a varchar in db
                // but should only filled with integer
                $this->mDestCond = $this->mDestCond. ' \'';
            }
            
            $this->mDestCond = $this->mDestCond. ' ) ';
        }

        return $this->mDestCond;
    }
	
	/** Stores an sql statement that checks if a record in a table does exists or not exists.
	 *  This must bei a full subselect that starts with SELECT. The statement is used if 
	 *  a condition with EMTPY or NOT EMPTY is used.
	 *  @param $sqlStatement String with the full subselect
	 *  @par Examples
	 *  @code $parser->setNotExistsStatement('SELECT 1 FROM adm_user_data WHERE usd_usr_id = 1 AND usd_usf_id = 9'); @endcode
	 */
	public function setNotExistsStatement($sqlStatement)
	{
		$this->mNotExistsSql = $sqlStatement;
	}
}
?>