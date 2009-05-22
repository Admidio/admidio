<?php
/******************************************************************************
 * Klasse um aus User-Bedingungen Sql-Statements zu machen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/date.php');
 
class ConditionParser
{
    var $m_src;
    var $m_dest;
    var $m_str_arr;   // m_src aufgesplittet in ein Array
    var $m_count;     // aktueller interne Position in m_str_arr -Array
    var $m_error;     // enthaelt den Fehlercode, ansonsten 0

    // Ersetzt alle Bedingungen der User-Eingabe durch eine Standardbedingung
    // str_src = String mit der Bedingung, die der User eingegeben hat

    function makeStandardSrc($str_src)
    {
        $this->m_src = admStrToUpper(trim($str_src));
        $this->m_src = strtr($this->m_src, '*', '%');

        // gueltiges 'ungleich' ist '!'
        $this->m_src = str_replace('{}', ' ! ', $this->m_src);
        $this->m_src = str_replace('!=', ' ! ', $this->m_src);

        // gueltiges 'gleich' ist '='
        $this->m_src = str_replace('==',     ' = ',   $this->m_src);
        $this->m_src = str_replace(' LIKE ', ' = ', $this->m_src);
        $this->m_src = str_replace(' IS ',   ' = ', $this->m_src);
        $this->m_src = str_replace(' IST ',  ' = ', $this->m_src);

        // gueltiges 'nicht' ist '/'
        $this->m_src = str_replace(' NOT ',   ' # ', $this->m_src);
        $this->m_src = str_replace(' NICHT ', ' # ', $this->m_src);

        // gueltiges 'kleiner gleich' is '['
        $this->m_src = str_replace('{=',   ' [ ', $this->m_src);
        $this->m_src = str_replace('={',   ' [ ', $this->m_src);

        // gueltiges 'groesser gleich' is ']'
        $this->m_src = str_replace('}=',   ' ] ', $this->m_src);
        $this->m_src = str_replace('=}',   ' ] ', $this->m_src);

        // gueltiges 'und' ist '&'
        $this->m_src = str_replace(' AND ', ' & ', $this->m_src);
        $this->m_src = str_replace(' UND ', ' & ', $this->m_src);
        $this->m_src = str_replace('&&',    ' & ',   $this->m_src);
        $this->m_src = str_replace('+',     ' & ',   $this->m_src);

        // gueltiges 'oder' ist '|'
        $this->m_src = str_replace(' OR ',   ' | ', $this->m_src);
        $this->m_src = str_replace(' ODER ', ' | ', $this->m_src);
        $this->m_src = str_replace('||',     ' | ',   $this->m_src);

        return $this->m_src;
    }
   
    // Erstellt aus der User-Bedingung ein SQL-Statement
    // str_src = String mit der Bedingung, die der User eingegeben hat
    // field_type = Typ des Feldes, auf die sich die Bedingung bezieht (string, int, date)

    function makeSqlStatement($str_src, $field_name, $field_type)
    {
        $b_cond_start = true;   // gibt an, dass eine neue Bedingung angefangen wurde
        $b_new_cond   = true;   // in Stringfeldern wird nach einem neuen Wort gesucht -> neue Bedingung
        $b_math_start = false;  // gibt an, ob bei num. oder Datumsfeldern schon <>= angegeben wurde
        $date         = '';     // Variable speichert bei Datumsfeldern das gesamte Datum
        $m_error      = 0;
        $this->m_dest = '';

        if(strlen($str_src) > 0 && strlen($field_name) > 0 && strlen($field_type) > 0)
        {
            $this->m_src     = $this->makeStandardSrc($str_src);
            $this->m_str_arr = strsplit($this->m_src);
    
            // Bedingungen fuer das Feld immer mit UND starten
            if($field_type == 'string')
            {
                $this->m_dest = ' AND ( UPPER('.$field_name.') LIKE "';
            }
            elseif($field_type == 'checkbox')
            {
                // !!! Sonderfall !!!
                // bei einer Checkbox kann es nur 1 oder 0 geben und keine komplizierten Verknuepfungen
                if($str_src == 1)
                {
                    $this->m_dest = ' AND '.$field_name.' = "1" ';
                }
                else
                {
                    $this->m_dest = ' AND ('.$field_name.' IS NULL OR '.$field_name.' = "0") ';
                }
                return $this->m_dest;
            }
            else
            {
                $this->m_dest = ' AND ( '.$field_name.' ';
            }
    
            // Zeichen fuer Zeichen aus dem Bedingungsstring wird hier verarbeitet
            for($this->m_count = 0; $this->m_count < strlen($this->m_src); $this->m_count++)
            {
                if($this->m_str_arr[$this->m_count] == '&'
                || $this->m_str_arr[$this->m_count] == '|' )
                {
                    if($b_new_cond)
                    {
                        // neue Bedingung, also Verknuepfen
                        if($this->m_str_arr[$this->m_count] == '&')
                        {
                            $this->m_dest = $this->m_dest. ' AND ';
                        }
                        elseif($this->m_str_arr[$this->m_count] == '|')
                        {
                            $this->m_dest = $this->m_dest. ' OR ';
                        }
    
                        // Feldname noch dahinter
                        if($field_type == 'string')
                        {
                            $this->m_dest = $this->m_dest. ' UPPER('.$field_name.') LIKE "';
                        }
                        else
                        {
                            $this->m_dest = $this->m_dest. ' '.$field_name.' ';
                        }
    
                        $b_cond_start = true;
                    }
                }
                else
                {
                    // Verleich der Werte wird hier verarbeitet
                    if($this->m_str_arr[$this->m_count] == '!'
                    || $this->m_str_arr[$this->m_count] == '='
                    || $this->m_str_arr[$this->m_count] == '{'
                    || $this->m_str_arr[$this->m_count] == '}'
                    || $this->m_str_arr[$this->m_count] == '['
                    || $this->m_str_arr[$this->m_count] == ']' )
                    {
                        if(!$b_cond_start)
                        {
                            $this->m_dest = $this->m_dest. ' AND '.$field_name.' ';
                            $b_cond_start = true;
                        }
    
                        if($this->m_str_arr[$this->m_count] == '!')
                        {
                            $this->m_dest = $this->m_dest. ' <> ';
                        }
                        elseif($this->m_str_arr[$this->m_count] == '{')
                        {
                            // bastwe: invert condition on age search
                            if( $field_type == 'date' && strstr(admStrToUpper($str_src), 'J') != FALSE )
                            {
                                $this->m_dest = $this->m_dest. ' > ';
                            } 
                            else 
                            { 
                                $this->m_dest = $this->m_dest. ' < ';
                            }
                        }
                        elseif($this->m_str_arr[$this->m_count] == '}')
                        {
                            // bastwe: invert condition on age search
                            if( $field_type == 'date' && strstr(admStrToUpper($str_src), 'J') != FALSE ) 
                            {
                                $this->m_dest = $this->m_dest. ' < ';
                            } 
                            else 
                            { 
                                $this->m_dest = $this->m_dest. ' > ';
                            }
                        }
                        elseif($this->m_str_arr[$this->m_count] == '[')
                        {
                            // bastwe: invert condition on age search
                            if( $field_type == 'date' && strstr(admStrToUpper($str_src), 'J') != FALSE ) 
                            {
                                $this->m_dest = $this->m_dest. ' >= ';
                            } 
                            else 
                            {
                                $this->m_dest = $this->m_dest. ' <= ';
                            }
                        }
                        elseif($this->m_str_arr[$this->m_count] == ']')
                        {
                            // bastwe: invert condition on age search
                            if( $field_type == 'date' && strstr(admStrToUpper($str_src), 'J') != FALSE ) 
                            {
                                $this->m_dest = $this->m_dest. ' <= ';
                            } 
                            else 
                            {
                                $this->m_dest = $this->m_dest. ' >= ';
                            }
                        }
                        else
                        {
                            $this->m_dest = $this->m_dest. $this->m_str_arr[$this->m_count];
                        }
    
                        $b_math_start = true;
                    }
                    else
                    {
                        // pruefen, ob ein neues Wort anfaengt
                        if($this->m_str_arr[$this->m_count] == ' '
                        && $b_new_cond == false )
                        {
                            if($field_type == 'string')
                            {
                                $this->m_dest = $this->m_dest. '" ';
                            }
                            elseif($field_type == 'date')
                            {
                                if(strlen($this->getFormatDate($date)) > 0)
                                {
                                    $this->m_dest = $this->m_dest. $this->getFormatDate($date);
                                }
                                else
                                {
                                    $this->m_error = -1;
                                }
                                $date = '';
                            }
                            $b_new_cond = true;
                        }
                        elseif($this->m_str_arr[$this->m_count] != ' ')
                        {
                            // neues Suchwort, aber noch keine Bedingung
                            if($b_new_cond && !$b_cond_start)
                            {
                                if($field_type == 'string')
                                {
                                    $this->m_dest = $this->m_dest. ' AND UPPER('.$field_name.') LIKE "';
                                }
                                else
                                {
                                    $this->m_dest = $this->m_dest. ' AND '.$field_name.' = ';
                                }
                            }
                            elseif($b_new_cond && !$b_math_start && $field_type != 'string')
                            {
                                // erste Bedingung bei numerischem Feld
                                $this->m_dest = $this->m_dest. ' = ';
                            }
    
                            // Zeichen an Zielstring dranhaengen
                            if($field_type == 'date')
                            {
                                $date = $date. $this->m_str_arr[$this->m_count];
                            }
                            else
                            {
                                $this->m_dest = $this->m_dest. $this->m_str_arr[$this->m_count];
                            }
    
                            // $this->m_str_arr[$this->m_count] hat keine besonderen Zeichen mehr
                            $b_new_cond   = false;
                            $b_cond_start = false;
                        }
                    }
                }
            }
    
            // Falls als letztes ein Datum verglichen wurde, dann dieses noch einbauen
            if($field_type == 'date')
            {
                if(strlen($this->getFormatDate($date)) > 0)
                {
                    $this->m_dest = $this->m_dest. $this->getFormatDate($date);
                }
                else
                {
                    $this->m_error = -1;
                }
            }
            //echo $this->m_dest; exit();
    
            if($field_type == 'string')
            {
                $this->m_dest = $this->m_dest. '" ';
            }
    
            // Anfangsklammer wieder schliessen
            $this->m_dest = $this->m_dest. ') ';
        }

        return $this->m_dest;
    }
    
    // liefert das Datum fertig formatiert fuer das SQL-Statement 'YYYY-MM-DD'
    function getFormatDate($date)
    {
        $format_date = '';

        // bastwe: check if user searches for age
        $last = substr($date, -1);
        $last = admStrToUpper($last);
        if( $last == 'J' ) 
        {
            $age = substr($date, 0, -1);
            $now_year= date('Y');
            $now_day = date('d');
            $now_month = date('m');
            $ret = date('Y-m-d', mktime(0,0,0, $now_month, $now_day, $now_year - $age));
            return '"'. $ret. '"';
        }
        
        if(strlen($date) <= 10 && strlen($date) >= 6)
        {
            $dateArray    = split('[- :.]', $date);
            // zweistelliges Jahr in 4 stelliges Jahr umwandeln
            if(strlen($dateArray[2]) == 2)
            {
                if($dateArray[2] < 30)
                {
                    $dateArray[2] = '20'. $dateArray[2];
                }
                else
                {
                    $dateArray[2] = '19'. $dateArray[2];
                }
            }
            if(dtCheckDate($dateArray[0]. '.'. $dateArray[1]. '.'. $dateArray[2]))
            {
                $format_date = date('Y-m-d', strtotime($dateArray[2].'-'.$dateArray[1].'-'.$dateArray[0]));
            }
        }
        return '"'. $format_date. '"';
    }
    
    function error()
    {
        return $this->m_error;
    }
}
?>