<?php
/******************************************************************************
 * Allgemeine String-Funktionen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
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

// nach jedem Buchstaben in dem uebergebenen String wird ein Space gesetzt
// geeignet, um den Text optisch ansprechender zu gestalten
//
// Bsp: "Teststring"  ->  "T e s t s t r i n g"

function strspace($srcString, $count = 1)
{
   // Html-Entitaeten durch Sonderzeichen ersetzen
   $srcString = html_entity_decode($srcString);
   for($i = 0; $i < strlen($srcString); $i++)
      $strArray[$i] = substr($srcString, $i, 1);
         
   switch ($count)
   {   
      case 1:
         return implode("&nbsp;", $strArray);
         break;
         
      case 2:
         return implode("&nbsp;&nbsp;", $strArray);
         break;
         
      default:
         return implode("", $strArray);
         break;
   }
}


// alle Sonderzeichen werden in Html-Standard übersetzt
// Diese Funktion unterscheidet sich von htmlentities(), da HTML-Tags
// hierdurch immer noch funktionieren
//
// z.B.: ü -> &uuml;  ä -> &auml;  ö -> &ouml;  ß -> &szlig;

function specialChars2Html($srcString)
{
   $srcString = htmlentities($srcString, ENT_NOQUOTES);
   $srcString = str_replace("&lt;", "<", $srcString);
   $srcString = str_replace("&gt;", ">", $srcString);

   return $srcString;
}


// sind die Nachkommastellen 0, dann werden sie unterdrückt
//
// Bsp:   153.00 -> 153   153.50 -> 153.5   153.54 -> 153.54

function numWithoutZero($number)
{
   $pos = strpos($number, ".");
   
   if($pos === false)
      return $number;
   else
   {
      $divideNum = explode(".", $number);
   
      if(substr($divideNum[1], 1, 1) == 0)
      {
         if(substr($divideNum[1], 0, 1) == 0)
            return $divideNum[0];
         
         return $divideNum[0]. ".". substr($divideNum[1], 0, 1);
      }
      
      return $number;
   }
}

// ermittelt den vorherigen oder nächsten Buchstaben im Alphabet
// mode = 0  -> naechster Buchstabe
// mode = 1  -> vorheriger Buchstabe
//
// Bsp.:   g -> h      g -> f

function strNextLetter($letter, $mode = 0)
{
   $ascii  = ord($letter);
   $aSmall = ord("a");
   $zSmall = ord("z");
   $aBig   = ord("A");
   $zBig   = ord("Z");

   if ($ascii == $aSmall || $ascii == $zSmall || $ascii == $aBig || $ascii == $zBig)
   {
      if (($ascii == $aSmall || $ascii == $aBig) && $mode == 0)
         $ascii++;

      if (($ascii == $zSmall || $ascii == $zBig) && $mode == 1)
         $ascii--;
   }
   else
   {
      if ($mode == 1)
         $ascii--;
      else
         $ascii++;
   }
   
   return chr($ascii);
}

// Tests if an email address is valid
function isValidEmailAddress($emailAddress)
{
   // If the email address was not empty
   if(strlen(trim($emailAddress)) > 0)
   {
      // nur gueltige Zeichen zulassen
      $anz = strspn($emailAddress, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@.-_+");
      
      if($anz == strlen($emailAddress))
      {
         // Aufbau der E-Mail-Adresse pruefen
         return preg_match("/^[^@]+@[^@]+\.[^@]{2,}$/", trim($emailAddress));
      }
      else
         return false;
   }
   else
   {
      return false;
   }
}

// prueft, ob der Dateiname gueltig ist
// check_ext = true : prueft, ob die Dateiextension fuer den Downloadbereich gueltig ist
// Rueckgabe 0 : Dateiname ist gueltig
//          -1 : kein Dateinamen uebergeben
//          -2 : ungueltige Zeichen
//          -3 : keine gueltige Dateiextension

function isValidFileName($file_name, $check_ext = false)
{
   // If the email address was not empty
   if(strlen(trim($file_name)) > 0)
   {
      // nur gueltige Zeichen zulassen
      $anz = strspn($file_name, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@.-_+");
      
      if($anz == strlen($file_name))
      {
         if($check_ext)
         {
            // auf gueltige Endungen pruefen
            $arr_invalid_ext = array("php", "php3", "php4", "php5", "html", "htm", "htaccess", "htpasswd", "pl");
            $file_ext  = substr($file_name, strrpos($file_name, ".")+1);
            
            if(in_array($file_ext, $arr_invalid_ext))
               return -3;
            else
               return 0;
         }
         return 0;
      }
      else
         return -2;
   }
   else
   {
      return -1;
   }
}

// wie die PHP-Funktion str_split, aber schon für PHP4

function strsplit($string)
{
   for($i = 0; $i < strlen($string); $i++)
   {
      $new_arr[$i] = substr($string, $i, 1);
   }

   return $new_arr;
}

?>