<?php
/******************************************************************************
 * Klasse erweitert das PHP-DateTime-Objekt um einige nuetzliche Funktionen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * In dieser Klasse werden hauptsaechlich Funktionen eingebaut, die erst ab
 * PHP 5.3.0 zur Verfuegung stehen. So kann ein beliebig formatiertes Datum
 * im Konstruktor uebergeben werden und daraus wird ein Datetime-Objekt erzeugt.
 *
 * The following functions are available:
 *
 * getAge() - berechnet aus dem Datum das Alter einer Person
 * getDateTimeEnglish()
 *          - liefert das gesetzte DateTime im Format 'Y-m-d H:i:s' zurueck
 * getTimestamp() - gibt den Unix-Timestamp zurueck
 * setDateTime($date, $format)
 *          - setzt das Datum und die Uhrzeit fuer das aktuelle Objekt
 * valid()  - gibt true oder false zurueck, je nachdem ob DateTime gueltig ist
 *
 *****************************************************************************/

class DateTimeExtended extends DateTime
{
    private $errorCode;
    private $year, $month, $day, $hour, $minute, $second;

    // es muss das Datum und das dazugehoerige Format uebergeben werden
    // date : String mit dem Datum
    // format : das zum Datum passende Format (Schreibweise aus date())
    // type   : 'datetime', 'date' oder 'time'
    public function __construct($date, $format, $type = 'datetime')
    {
        $this->year   = 0;
        $this->month  = 0;
        $this->day    = 0;
        $this->hour   = 0;
        $this->minute = 0;
        $this->second = 0;
        
        // je nach Type das Format erweitern, da nur Datetime verarbeitet werden kann
        if($type == 'date')
        {
            $this->setDateTime($date.' 01:00:00', $format.' h:i:s');
        }
        elseif($type == 'time')
        {
            $this->setDateTime('2000-01-01 '.$date, 'Y-m-d '.$format);
        }
        else
        {
            $this->setDateTime($date, $format);
        }
        parent::__construct($this->getDateTimeEnglish());
    }
    
    // berechnet aus dem Datum das Alter einer Person
    public function getAge()
    {
        // Alter berechnen
        // Hier muss man aufpassen, da viele PHP-Funkionen nicht mit einem Datum vor 1970 umgehen koennen !!!
        $act_date  = getDate(time());
        $birthday  = false;
    
        if($act_date['mon'] >= $this->month)
        {
            if($act_date['mon'] == $this->month)
            {
                if($act_date['mday'] >= $this->day)
                {
                    $birthday = true;
                }
            }
            else
            {
                $birthday = true;
            }
        }
        $age = $act_date['year'] - $this->year;
        if($birthday == false)
        {
            $age--;
        }
        return $age;
    }

    // liefert das gesetzte DateTime im Format 'Y-m-d H:i:s' zurueck
    public function getDateTimeEnglish()
    {
        return $this->year.'-'.$this->month.'-'.$this->day.' '.$this->hour.':'.$this->minute.':'.$this->second;
    }
    
    // gibt den Unix-Timestamp zurueck
    public function getTimestamp()
    {
        return $this->format('U');
    }

    // setzt das Datum und die Uhrzeit fuer das aktuelle Objekt
    // erwartet wird ein String und das dazugehoerige Format aehnlich date()
    public function setDateTime($date, $format)
    {
        $this->year   = 0;
        $this->month  = 0;
        $this->day    = 0;
        $this->hour   = 0;
        $this->minute = 0;
        $this->second = 0;
        $this->errorCode = 0;      
        $formatPatterns = array(
            'a' => '(?P<a>am|pm)',
            'A' => '(?P<A>AM|PM)',
            'B' => '(?P<B>[0-9]{3})',
        //    'c' => '(?P<c>)',
            'd' => '(?P<d>0[1-9]|[12][0-9]|3[01])',
            'D' => '(?P<D>Mon|Tue|Wed|Thu|Fri|Sat|Sun)',
            'F' => '(?P<F>January|February|March|April|May|June|July|August|September|October|November|December)',
            'g' => '(?P<g>[1-9]|1[0-2])',
            'G' => '(?P<G>[0-9]|1[0-9]|2[0-3])',
            'h' => '(?P<h>0[1-9]|1[0-2])',
            'H' => '(?P<H>[01][0-9]|2[0-3])',
            'i' => '(?P<i>[0-4][0-9]|5[0-9])',
            'I' => '(?P<I>[01])',
            'j' => '(?P<j>[1-9]|[12][0-9]|3[01])',
            'l' => '(?P<l>Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)',
            'L' => '(?P<L>[01])',
            'm' => '(?P<m>0[1-9]|1[0-2])',
            'M' => '(?P<M>Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)',
            'n' => '(?P<n>[1-9]|1[0-2])',
            'O' => '(?P<O>[+\-][0-9]{4})',
        //    'r' => '(?P<r>)',
            's' => '(?P<s>[0-4][0-9]|5[0-9])',
            'S' => '(?P<S>st|nd|rd|th)',
            't' => '(?P<t>28|29|30|31)',
            'T' => '(?P<T>[A-Z]{3})',
            'U' => '(?P<U>[1-9][0-9]*)',
            'w' => '(?P<w>[0-6])',
            'W' => '(?P<W>[1-9]|[1-4][0-9]|5[0-3])',
            'Y' => '(?P<Y>[0-9]{4})',
            'y' => '(?P<y>[0-9]{2})',
            'z' => '(?P<z>[0-9]|[12][0-9][0-9]|3(?:[0-5][0-9]|6[0-5]))',
            'Z' => '(?P<Z>-?(?:[0-9]|[1-9][0-9]{3}|[1-3][0-9]{4}|4(?:[0-2][0-9]{3}|3[01][0-9]{2}|3200)))',
        );

        $regexp = preg_replace('/[a-zA-Z]/e', 'isset($formatPatterns["$0"])?$formatPatterns["$0"]:"$0"', $format);
        if (preg_match('/^'.$regexp.'$/', trim($date), $match))
        {
            foreach ($match as $format => $value) 
            {
                if ($format == 'g' || $format == 'G' || $format == 'h' || $format == 'H') {
                    $this->hour = $value;
                } else if ($format == 'i') {
                    $this->minute = $value;
                } else if ($format == 's') {
                    $this->second = $value;
                } else if ($format == 'm' || $format == 'n') {
                    $this->month = $value;
                } else if ($format == 'd' || $format == 'j') {
                    $this->day = $value;
                } else if ($format == 'Y' || $format == 'y') {
                    $this->year = $value;
                }
            }
        }
        
        // Datum validieren
        if($this->month == 0 || $this->day == 0)
        {
            $this->errorCode = 1;
        }
    }
    
    // gibt true oder false zurueck, je nachdem ob DateTime gueltig ist
    public function valid()
    {
        if($this->errorCode == 0)
        {
            return true;
        }
        return false;
    }
}
?>