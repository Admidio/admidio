<?php
/******************************************************************************
 * Allgemeine Datums-Funktionen
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

// wandelt ein DateTime-Feld einer MySql-Datenbank in einen Timestamp um

function mysqlmaketimestamp($dateTime)
{
   $dateArray = split("[- :]", $dateTime);
   
   return mktime($dateArray[3], $dateArray[4], $dateArray[5], $dateArray[1], $dateArray[2], $dateArray[0]);
}

// prueft ein Datum auf Gueltigkeit
// das Datum wird in d.m.y Format erwartet

function dtCheckDate($date)
{
   $formatDate = dtFormatDate($date);
   $arrDate    = explode(".", $formatDate);

   return checkdate($arrDate[1],$arrDate[0],$arrDate[2]);
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
   $arrDate = explode(".", $date);

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
      return date($format, mktime(0, 0, 0, $month, $day, $year));
   else
      return "$day.$month.$year";
}

// prueft eine Uhrzeit auf Gueltigkeit
// die Uhrzeit wird in h:m:s oder h:m Format erwartet

function dtCheckTime($time)
{
   $formatTime = dtFormatDate($time);

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
      return date($format, mktime($hour, $minute, $second));
   else
      return "$hour:$minute:$second";
}

?>