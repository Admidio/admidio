<?php
/******************************************************************************
 * Allgemeine String-Funktionen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// entfernt Html-, PHP-Codes und Spaces am Anfang und Ende
// eines Strings oder aller Elemente eines Arrays

function strStripTags($srcString, $checkChar = 0)
{
    if(is_array($srcString))
    {
        // Jedes Funktion fuer jedes Arrayelement aufrufen
        $srcString = array_map("strStripTags", $srcString);
    }
    else
    {
        // Spaces vorne und hinten entfernen
        $srcString = trim($srcString);
        // HTML und PHP Tags entfernen
        $srcString = strip_tags($srcString);

        if($checkChar)
        {
            $anz = strspn($srcString, "abcdefghijklmnopqrstuvwxyzäöüßABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÜ0123456789.-_+ ");
            if($anz != strlen($srcString))
            {
                $srcString = "";
            }
        }
    }

    return $srcString;
}

// fuegt Quotes einem mittels addslashes() gequoteten Array und String hinzu
function strAddSlashesDeep($value)
{
    if(is_array($value))
    {
        $value = array_map("strAddSlashesDeep", $value);
    }
    else
    {
        $value = addslashes($value);
    }
    return $value;
}

// Entfernt Quotes aus einem mittels addslashes() gequoteten Array und String
function strStripSlashesDeep($value)
{
    if(is_array($value))
    {
        $value = array_map("strStripSlashesDeep", $value);
    }
    else
    {
        $value = stripslashes($value);
    }
    return $value;
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
        {
            $ascii++;
        }

        if (($ascii == $zSmall || $ascii == $zBig) && $mode == 1)
        {
            $ascii--;
        }
    }
    else
    {
        if ($mode == 1)
        {
            $ascii--;
        }
        else
        {
            $ascii++;
        }
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
        {
            return false;
        }
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
    // If the filename was not empty
    if(strlen(trim($file_name)) > 0)
    {
        // nur ungueltige Zeichen pruefen (gueltige Pruefung hat zu Problemen bei Sonderzeichen gefuehrt
        $anz = strspn($file_name, "/\\!\"§$%&<>|");

        if($anz == 0)
        {
            if(strlen($file_name) == strlen(strip_tags($file_name))
            && strpos($file_name, "..") === false
            && strpos($file_name, ":/") === false)
            {
                if (substr($file_name, 0, 1) == ".") {
                    return -2;
                }


                if($check_ext)
                {
                    // auf gueltige Endungen pruefen
                    $arr_invalid_ext = array("php", "php3", "php4", "php5", "html", "htm", "htaccess", "htpasswd", "pl");
                    $file_ext  = substr($file_name, strrpos($file_name, ".")+1);

                    if(in_array($file_ext, $arr_invalid_ext))
                    {
                        return -3;
                    }
                    else
                    {
                        return 0;
                    }
                }
                return 0;
            }
            else
            {
                return -2;
            }
        }
        else
        {
            return -2;
        }
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