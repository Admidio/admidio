<?php
/******************************************************************************
 * Allgemeine Datums-Funktionen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
// Arrays fuer die Monatsnamen und Wochentage

$arrMonth    = array('Monat', 'Januar', 'Februar', 'M&auml;rz', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');
$arrDay      = array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag');
$arrDayShort = array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So');

// wandelt ein DateTime-Feld einer MySql-Datenbank in einen beliebigen String um
// Folgende Zeichen des Strings werden ersetzt:
// y = Jahr; m = Monat; d = Tag; h = Stunde; i = Minute; s = Sekunde
//
// Bsp: MySql-Datenfeld    "2001-05-11 15:30:15"
//      Übergabe           "h:i d.m.y"      
//      Rückgabe           "15:30 11.05.2001"

function mysqldatetime($dateFormat, $dateTime)
{
   $destStr = "";
   $dateArray = split("[- :]", $dateTime);
   
   if($dateTime != NULL)
   {
      if($dateArray[0] == "0000")
      {
         // wenn das Jahr 0 ist, dann ist das Feld nicht gefüllt
         $destStr = "";
      }
      else
      {
         for($i = 0; $i < strlen($dateFormat); $i++)
         {
            switch($dateFormat[$i])
            {
               case 'y':
                  $destStr = $destStr. $dateArray[0];
                  break;

               case 'm':
                  $destStr = $destStr. $dateArray[1];
                  break;

               case 'd':
                  $destStr = $destStr. $dateArray[2];
                  break;

               case 'h':
                  $destStr = $destStr. $dateArray[3];
                  break;

               case 'i':
                  $destStr = $destStr. $dateArray[4];
                  break;

               case 's':
                  $destStr = $destStr. $dateArray[5];
                  break;

               case ' ':
                  $destStr = $destStr. "&nbsp;";
                  break;

               default:
                  $destStr = $destStr. $dateFormat[$i];
                  break;
            }
         }
      }
   }
   
   return $destStr;
}

// wandelt ein Date-Feld einer MySql-Datenbank in einen beliebigen String um
// Folgende Zeichen des Strings werden ersetzt:
// y = Jahr; m = Monat; d = Tag
//
// Bsp: MySql-Datenfeld    "2001-05-11"
//      Übergabe           "d.m.y"
//      Rückgabe           "11.05.2001"

function mysqldate($dateFormat, $date)
{
   $destStr = "";
   $dateArray = split("[- :]", $date);

   if($date != NULL)
   {
      if($dateArray[0] == "0000")
      {
         // wenn das Jahr 0 ist, dann ist das Feld nicht gefüllt
         $destStr = "";
      }
      else
      {
         for($i = 0; $i < strlen($dateFormat); $i++)
         {
            switch($dateFormat[$i])
            {
               case 'y':
                  $destStr = $destStr. $dateArray[0];
                  break;

               case 'm':
                  $destStr = $destStr. $dateArray[1];
                  break;

               case 'd':
                  $destStr = $destStr. $dateArray[2];
                  break;

               case ' ':
                  $destStr = $destStr. "&nbsp;";
                  break;

               default:
                  $destStr = $destStr. $dateFormat[$i];
                  break;
            }
         }
      }
   }

   return $destStr;
}

// wandelt ein Time-Feld einer MySql-Datenbank in einen beliebigen String um
// Folgende Zeichen des Strings werden ersetzt:
// h = Stunde; i = Minute; s = Sekunde
//
// Bsp: MySql-Datenfeld    "15:30:15"
//      Übergabe           "h:i"
//      Rückgabe           "15:30"

function mysqltime($dateFormat, $time)
{
   $destStr = "";
   $dateArray = split("[- :]", $time);

   if($time != NULL)
   {
      for($i = 0; $i < strlen($dateFormat); $i++)
      {
         switch($dateFormat[$i])
         {
            case 'h':
               $destStr = $destStr. $dateArray[0];
               break;

            case 'i':
               $destStr = $destStr. $dateArray[1];
               break;

            case 's':
               $destStr = $destStr. $dateArray[2];
               break;

            case ' ':
               $destStr = $destStr. "&nbsp;";
               break;

            default:
               $destStr = $destStr. $dateFormat[$i];
               break;
         }
      }
   }

   return $destStr;
}

// wandelt ein DateTime- oder Date-Feld einer MySql-Datenbank in einen Timestamp um

function mysqlmaketimestamp($dateTime)
{
    if(strlen($dateTime) > 10)
    {
        // Datetime
        list($year, $month, $day, $hour, $minute, $second) = split("[- :]", $dateTime);
    }
    else
    {
        // Date
        list($year, $month, $day) = split("[- :]", $dateTime);
        $hour   = 0;
        $minute = 0;
        $second = 0;
    }
    return mktime($hour, $minute, $second, $month, $day, $year);
}

// prueft ein Datum auf Gueltigkeit
// das Datum wird in d.m.y Format erwartet

function dtCheckDate($date)
{
    $formatDate = dtFormatDate($date);
    $arrDate    = explode(".", $formatDate);

    if(count($arrDate) == 3)
    {
        return checkdate($arrDate[1],$arrDate[0],$arrDate[2]);
    }
    else
    {
        return false;
    }
}

// formatiert ein Datum
// gibt normalerweise deutsches Format aus
// Beispiel:  1.1.05 -> 01.01.2005
// ansonsten kann aber auch über $format jedes beliebige Format angenommen werden
// Es gelten dieselben Formatzeichen wie bei der Funktion date()

function dtFormatDate($date, $format = "")
{
    $day   = "";
    $month = "";
    $year  = "";

    // String auf gueltige Zeichen pruefen
    $anz = strspn($date, "0123456789. ");
    if($anz == strlen($date))
    {
        $arrDate = explode(".", $date);
        
        // wenn Jahr nicht angegeben wurde, dann aktuelles Jahr nehmen
        if(count($arrDate) == 2 || strlen($arrDate[2]) == 0)
            $arrDate[2] = date('Y', time());

        if(count($arrDate) == 3)
        {
            // Tag
            if($arrDate[0] < 10 && strlen($arrDate[0]) == 1)
                $day = "0". $arrDate[0];
            else
                $day = $arrDate[0];
            // Monat
            if($arrDate[1] < 10 && strlen($arrDate[1]) == 1)
                $month = "0". $arrDate[1];
            else
                $month = $arrDate[1];
            // Jahr
            if($arrDate[2] < 100)
            {
                if(strlen($arrDate[2]) == 0)
                    $year = date("Y");
                else
                {
                    if($arrDate[2] > 30)
                        $year = "19". $arrDate[2];
                    else
                        $year = "20". $arrDate[2];
                }
            }
            else
                $year = $arrDate[2];

            if(strlen($format) > 0)
            {
                // Datum wird jetzt formatiert zurueckgegeben, bisher ist aber nur Y m d als Format moeglich
                $return_date = "";
                for($i = 0; $i < strlen($format); $i++)
                {
                    switch($format[$i])
                    {
                        case 'Y':
                            $return_date = $return_date. sprintf("%04d", $year);
                            break;

                        case 'm':
                            $return_date = $return_date. sprintf("%02d", $month);
                            break;

                        case 'd':
                            $return_date = $return_date. sprintf("%02d", $day);
                            break;

                        default:
                            $return_date = $return_date. $format[$i];
                            break;
                    }
                }
                
                return $return_date;
            }
            else
            {
                return "$day.$month.$year";
            }
        }
        else
            return "";
    }
    else
        return "";
}

// prueft eine Uhrzeit auf Gueltigkeit
// die Uhrzeit wird in h:m:s oder h:m Format erwartet

function dtCheckTime($time)
{
   $formatTime = dtFormatTime($time);

    if(strlen($formatTime) > 0)
    {
        $hour   = (int) substr($formatTime, 0, 2);
        $minute = (int) substr($formatTime, 3, 2);
        $second = (int) substr($formatTime, 6, 2);

        $valid = true;
        if($hour < 0 || $hour > 24)
            $valid = false;
        if($minute < 0 || $minute > 60)
            $valid = false;
        if($second < 0 || $second > 60)
            $valid = false;
    }
    else
        $valid = false;
        
    return $valid;
}

// formatiert eine Uhrzeit
// gibt normalerweise deutsches Format aus
// Beispiel:  4:35:11 -> 04:35:11 oder 14:04 -> 14:04:00
// ansonsten kann aber auch über $format jedes beliebige Format angenommen werden
// Es gelten dieselben Formatzeichen wie bei der Funktion date()

function dtFormatTime($time, $format = "")
{
   $hour   = "";
   $minute = "";
   $second = "";
   
   // String auf gueltige Zeichen pruefen
    $anz = strspn($time, "0123456789: ");
    if($anz == strlen($time))
    {
        $arrTime = explode(":", $time);

        // Stunde
        if(count($arrTime) > 0)
        {
            if($arrTime[0] < 10 && strlen($arrTime[0]) == 1)
                $hour = "0". $arrTime[0];
            else
                $hour = $arrTime[0];
        }
        else
            $hour = "00";
        // Minute
        if(count($arrTime) > 1)
        {
            if($arrTime[1] < 10 && strlen($arrTime[1]) == 1)
                $minute = "0". $arrTime[1];
            else
                $minute = $arrTime[1];
        }
        else
            $minute = "00";
        // Sekunde
        if(count($arrTime) > 2)
            $second = $arrTime[2];
        else
            $second = "00";
            
        if(strlen($format) > 0)
        {
            // Zeit wird jetzt formatiert zurueckgegeben, bisher ist aber nur H i s als Format moeglich
            $return_time = "";
            for($i = 0; $i < strlen($format); $i++)
            {
                switch($format[$i])
                {
                    case 'H':
                        $return_time = $return_time. sprintf("%02d", $hour);
                        break;

                    case 'i':
                        $return_time = $return_time. sprintf("%02d", $minute);
                        break;

                    case 's':
                        $return_time = $return_time. sprintf("%02d", $second);
                        break;

                    default:
                        $return_time = $return_time. $format[$i];
                        break;
                }
            }
            return $return_time;
        }
        else
        {
            return "$hour:$minute:$second";
        }
    }
    else
    {
        return "";
    }
}

// Funktion berechnet aus dem Datum das Alter einer Person
// Das Datum muss im Format "YYYY-MM-DD" (DB-Format) uebergeben werden

function dtGetAge($date)
{
    // Alter berechnen
    // Hier muss man aufpassen, da viele PHP-Funkionen nicht mit einem Datum vor 1970 umgehen koennen !!!
    $act_date  = getDate(time());
    $geb_day   = mysqldatetime("d", $date);
    $geb_month = mysqldatetime("m", $date);
    $geb_year  = mysqldatetime("y", $date);
    $birthday  = false;

    if($act_date['mon'] >= $geb_month)
    {
        if($act_date['mon'] == $geb_month)
        {
            if($act_date['mday'] >= $geb_day)
            {
                $birthday = true;
            }
        }
        else
        {
            $birthday = true;
        }
    }
    $age = $act_date['year'] - $geb_year;
    if($birthday == false)
    {
        $age--;
    }
    return $age;
}

?>