<?php
/******************************************************************************
 * Klasse um aus User-Bedingungen Sql-Statements zu machen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

class CParser
{
    var $m_src;
    var $m_dest;
    var $m_str_arr;   // m_src aufgesplittet in ein Array
    var $m_count;     // aktueller interne Position in m_str_arr -Array

    // Ersetzt alle Bedingungen der User-Eingabe durch eine Standardbedingung
    // str_src = String mit der Bedingung, die der User eingegeben hat

    function makeStandardSrc($str_src)
    {
        $this->m_src = strtoupper(trim($str_src));
        $this->m_src = strtr($this->m_src, "*", "%");

        // gueltiges "ungleich" ist "!"
        $this->m_src = str_replace("<>", "!", $this->m_src);
        $this->m_src = str_replace("!=", "!", $this->m_src);

        // gueltiges "gleich" ist "="
        $this->m_src = str_replace("==",     "=",   $this->m_src);
        $this->m_src = str_replace(" LIKE ", " = ", $this->m_src);
        $this->m_src = str_replace(" IS ",   " = ", $this->m_src);
        $this->m_src = str_replace(" IST ",  " = ", $this->m_src);

        // gueltiges "nicht" ist "/"
        $this->m_src = str_replace(" NOT ",   " # ", $this->m_src);
        $this->m_src = str_replace(" NICHT ", " # ", $this->m_src);

        // gueltiges "und" ist "&"
        $this->m_src = str_replace(" AND ", " & ", $this->m_src);
        $this->m_src = str_replace(" UND ", " & ", $this->m_src);
        $this->m_src = str_replace("&&",    "&",   $this->m_src);
        $this->m_src = str_replace("+",     "&",   $this->m_src);

        // gueltiges "oder" ist "|"
        $this->m_src = str_replace(" OR ",   " | ", $this->m_src);
        $this->m_src = str_replace(" ODER ", " | ", $this->m_src);
        $this->m_src = str_replace("||",     "|",   $this->m_src);

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
        $date         = "";     // Variable speichert bei Datumsfeldern das gesamte Datum

        $this->m_src     = $this->makeStandardSrc($str_src);
        $this->m_str_arr = strsplit($this->m_src);

        // Bedingungen fuer das Feld immer mit UND starten
        if($field_type == "string")
        {
            $this->m_dest = " AND ( UPPER($field_name) LIKE '";
        }
        elseif($field_type != "checkbox")
        {
            // !!! Sonderfall !!!
            // bei einer Checkbox kann es nur 1 oder 0 geben und keine komplizierten Verknuepfungen
            if($str_src == 1)
            {
                 $this->m_dest = " AND $field_name = '1' ";
            }
            else
            {
                $this->m_dest = " AND $field_name IS NULL ";
            }
            return $this->m_dest;
        }
        else
        {
            $this->m_dest = " AND ( $field_name ";
        }

        for($this->m_count = 0; $this->m_count < strlen($this->m_src); $this->m_count++)
        {
            if($this->m_str_arr[$this->m_count] == "&"
            || $this->m_str_arr[$this->m_count] == "|" )
            {
                if($b_new_cond)
                {
                    // neue Bedingung, also Verknuepfen
                    if($this->m_str_arr[$this->m_count] == "&")
                    {
                        $this->m_dest = $this->m_dest. " AND ";
                    }
                    elseif($this->m_str_arr[$this->m_count] == "|")
                    {
                        $this->m_dest = $this->m_dest. " OR ";
                    }

                    // Feldname noch dahinter
                    if($field_type == "string")
                    {
                        $this->m_dest = $this->m_dest. " UPPER($field_name) LIKE '";
                    }
                    else
                    {
                        $this->m_dest = $this->m_dest. " $field_name ";
                    }

                    $b_cond_start = true;
                }
            }
            else
            {
                if($this->m_str_arr[$this->m_count] == "<"
                || $this->m_str_arr[$this->m_count] == ">"
                || $this->m_str_arr[$this->m_count] == "!"
                || $this->m_str_arr[$this->m_count] == "=" )
                {
                    if(!$b_cond_start)
                    {
                        $this->m_dest = $this->m_dest. " AND $field_name ";
                        $b_cond_start = true;
                    }

                    if($this->m_str_arr[$this->m_count] == "!")
                    {
                        $this->m_dest = $this->m_dest. " <> ";
                    }
                    elseif($this->m_str_arr[$this->m_count] == "=")
                    {
                        $this->m_dest = $this->m_dest. " <> ";
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
                    if($this->m_str_arr[$this->m_count] == " "
                    && $b_new_cond == false )
                    {
                        if($field_type == "string")
                        {
                            $this->m_dest = $this->m_dest. "' ";
                        }
                        elseif($field_type == "date"
                        &&     strlen($date) >= 8 )
                        {
                            $dateArray    = split("[- :.]", $date);
                            $this->m_dest = $this->m_dest. "'$dateArray[2]-$dateArray[1]-$dateArray[0]'";
                            $date = "";
                        }
                        $b_new_cond = true;
                    }
                    elseif($this->m_str_arr[$this->m_count] != " ")
                    {
                        // neues Suchwort, aber noch keine Bedingung
                        if($b_new_cond && !$b_cond_start)
                        {
                            if($field_type == "string")
                            {
                                $this->m_dest = $this->m_dest. " AND UPPER($field_name) LIKE '";
                            }
                            else
                            {
                                $this->m_dest = $this->m_dest. " AND $field_name = ";
                            }
                        }
                        elseif($b_new_cond && !$b_math_start && $field_type != "string")
                        {
                            // erste Bedingung bei numerischem Feld
                            $this->m_dest = $this->m_dest. " = ";
                        }

                        // Zeichen an Zielstring dranhaengen
                        if($field_type == "date")
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
        if($field_type == "date"
        && strlen($date) >= 8 )
        {
            $dateArray    = split("[- :.]", $date);
            $this->m_dest = $this->m_dest. "'$dateArray[2]-$dateArray[1]-$dateArray[0]'";
        }

        if($field_type == "string")
        {
            $this->m_dest = $this->m_dest. "' ";
        }

        // Anfangsklammer wieder schliessen
        $this->m_dest = $this->m_dest. ") ";

        return $this->m_dest;
    }
}
?>