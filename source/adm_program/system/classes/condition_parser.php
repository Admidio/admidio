<?php
/******************************************************************************
 * Klasse um aus User-Bedingungen Sql-Statements zu machen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/datetime_extended.php');
 
class ConditionParser
{
    private $mSrcCond;
    private $mDestCond;
    private $mSrcCondArray;   // mSrcCond aufgesplittet in ein Array
    private $mCount;     // aktueller interne Position in mSrcCondArray -Array
    private $mError;     // enthaelt den Fehlercode, ansonsten 0

    public function error()
    {
        return $this->mError;
    }

    // liefert das Datum fertig formatiert fuer das SQL-Statement 'YYYY-MM-DD'
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
        return '\''. $formatDate. '\'';
    }

    // Ersetzt alle Bedingungen der User-Eingabe durch eine Standardbedingung
    // str_src = String mit der Bedingung, die der User eingegeben hat

    private function makeStandardCondition($sourceCondition)
    {
        $this->mSrcCond = admStrToUpper(trim($sourceCondition));
        $this->mSrcCond = strtr($this->mSrcCond, '*', '%');

        // gueltiges 'ungleich' ist '!'
        $this->mSrcCond = str_replace('{}', ' ! ', $this->mSrcCond);
        $this->mSrcCond = str_replace('!=', ' ! ', $this->mSrcCond);

        // gueltiges 'gleich' ist '='
        $this->mSrcCond = str_replace('==',     ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' LIKE ', ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' IS ',   ' = ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' IST ',  ' = ', $this->mSrcCond);

        // gueltiges 'nicht' ist '/'
        $this->mSrcCond = str_replace(' NOT ',   ' # ', $this->mSrcCond);
        $this->mSrcCond = str_replace(' NICHT ', ' # ', $this->mSrcCond);

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
   
    // Erstellt aus der User-Bedingung ein SQL-Statement
    // str_src = String mit der Bedingung, die der User eingegeben hat
    // field_type = Typ des Feldes, auf die sich die Bedingung bezieht (string, int, date)

    public function makeSqlStatement($sourceCondition, $fieldName, $fieldType)
    {
        $bStartCondition = true;   // gibt an, dass eine neue Bedingung angefangen wurde
        $bNewCondition   = true;   // in Stringfeldern wird nach einem neuen Wort gesucht -> neue Bedingung
        $bStartOperand   = false;  // gibt an, ob bei num. oder Datumsfeldern schon <>= angegeben wurde
        $date            = '';     // Variable speichert bei Datumsfeldern das gesamte Datum
        $this->mError   = 0;
        $this->mDestCond    = '';

        if(strlen($sourceCondition) > 0 && strlen($fieldName) > 0 && strlen($fieldType) > 0)
        {
            $this->mSrcCond     = $this->makeStandardCondition($sourceCondition);
            $this->mSrcCondArray = str_split($this->mSrcCond);
    
            // Bedingungen fuer das Feld immer mit UND starten
            if($fieldType == 'string')
            {
                $this->mDestCond = ' AND ( UPPER('.$fieldName.') LIKE \'';
            }
            elseif($fieldType == 'checkbox')
            {
                // Sonderfall !!!
                // bei einer Checkbox kann es nur 1 oder 0 geben und keine komplizierten Verknuepfungen
                if($sourceCondition == 1)
                {
                    $this->mDestCond = ' AND '.$fieldName.' = 1 ';
                }
                else
                {
                    $this->mDestCond = ' AND ('.$fieldName.' IS NULL OR '.$fieldName.' = 0) ';
                }
                return $this->mDestCond;
            }
            else
            {
                $this->mDestCond = ' AND ( '.$fieldName.' ';
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
                        if($fieldType == 'string')
                        {
                            $this->mDestCond = $this->mDestCond. ' UPPER('.$fieldName.') LIKE \'';
                        }
                        else
                        {
                            $this->mDestCond = $this->mDestCond. ' '.$fieldName.' ';
                        }
    
                        $bStartCondition = true;
                    }
                }
                else
                {
                    // Verleich der Werte wird hier verarbeitet
                    if($this->mSrcCondArray[$this->mCount] == '!'
                    || $this->mSrcCondArray[$this->mCount] == '='
                    || $this->mSrcCondArray[$this->mCount] == '{'
                    || $this->mSrcCondArray[$this->mCount] == '}'
                    || $this->mSrcCondArray[$this->mCount] == '['
                    || $this->mSrcCondArray[$this->mCount] == ']' )
                    {
                        if(!$bStartCondition)
                        {
                            $this->mDestCond = $this->mDestCond. ' AND '.$fieldName.' ';
                            $bStartCondition = true;
                        }
    
                        if($this->mSrcCondArray[$this->mCount] == '!')
                        {
                            $this->mDestCond = $this->mDestCond. ' <> ';
                        }
                        elseif($this->mSrcCondArray[$this->mCount] == '{')
                        {
                            // bastwe: invert condition on age search
                            if( $fieldType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE )
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
                            if( $fieldType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE ) 
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
                            if( $fieldType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE ) 
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
                            if( $fieldType == 'date' && strstr(admStrToUpper($sourceCondition), 'J') != FALSE ) 
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
    
                        $bStartOperand = true;
                    }
                    else
                    {
                        // pruefen, ob ein neues Wort anfaengt
                        if($this->mSrcCondArray[$this->mCount] == ' '
                        && $bNewCondition == false )
                        {
                            if($fieldType == 'string')
                            {
                                $this->mDestCond = $this->mDestCond. '\' ';
                            }
                            elseif($fieldType == 'date')
                            {
                                if(strlen($this->getFormatDate($date)) > 0)
                                {
                                    $this->mDestCond = $this->mDestCond. $this->getFormatDate($date);
                                }
                                else
                                {
                                    $this->mError = -1;
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
                                if($fieldType == 'string')
                                {
                                    $this->mDestCond = $this->mDestCond. ' AND UPPER('.$fieldName.') LIKE \'';
                                }
                                else
                                {
                                    $this->mDestCond = $this->mDestCond. ' AND '.$fieldName.' = ';
                                }
                            }
                            elseif($bNewCondition && !$bStartOperand && $fieldType != 'string')
                            {
                                // erste Bedingung bei numerischem Feld
                                $this->mDestCond = $this->mDestCond. ' = ';
                            }
    
                            // Zeichen an Zielstring dranhaengen
                            if($fieldType == 'date')
                            {
                                $date = $date. $this->mSrcCondArray[$this->mCount];
                            }
							elseif($fieldType == 'int' && is_numeric($this->mSrcCondArray[$this->mCount]) == false)
							{
								// if numeric field than only numeric characters are allowed
								$this->mError = -1;
							}
                            else
                            {
                                $this->mDestCond = $this->mDestCond. $this->mSrcCondArray[$this->mCount];
                            }
    
                            // $this->mSrcCondArray[$this->mCount] hat keine besonderen Zeichen mehr
                            $bNewCondition   = false;
                            $bStartCondition = false;
                        }
                    }
                }
            }
    
            // Falls als letztes ein Datum verglichen wurde, dann dieses noch einbauen
            if($fieldType == 'date')
            {
                if(strlen($this->getFormatDate($date)) > 0)
                {
                    $this->mDestCond = $this->mDestCond. $this->getFormatDate($date);
                }
                else
                {
                    $this->mError = -1;
                }
            }
            //echo $this->mDestCond; exit();
    
            if($fieldType == 'string')
            {
                $this->mDestCond = $this->mDestCond. '\' ';
            }
    
            // Anfangsklammer wieder schliessen
            $this->mDestCond = $this->mDestCond. ') ';
        }

        return $this->mDestCond;
    }
}
?>