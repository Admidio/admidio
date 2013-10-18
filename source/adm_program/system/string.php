<?php
/******************************************************************************
 * Allgemeine String-Funktionen
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// da die Multibyte-Funktionen nicht bei allen Installationen zur Verfuegung 
// stehen, wird hier eine Fallunterscheidung gemacht
// WICHTIG: wird die Multibyte-Funktion nicht genutzt, funktioniert die Umwandlung von Umlauten nicht !!!
function admStrToLower($string)
{
    if(function_exists('mb_strtolower'))
    {
        return mb_strtolower($string, 'UTF-8');
    }
    else
    {
        return strtolower($string);
    }
}
 
// da die Multibyte-Funktionen nicht bei allen Installationen zur Verfuegung 
// stehen, wird hier eine Fallunterscheidung gemacht
// WICHTIG: wird die Multibyte-Funktion nicht genutzt, funktioniert die Umwandlung von Umlauten nicht !!!
function admStrToUpper($string)
{
    if(function_exists('mb_strtolower'))
    {
        return mb_strtoupper($string, 'UTF-8');
    }
    else
    {
        return strtoupper($string);
    }
}

// da die Multibyte-Funktionen nicht bei allen Installationen zur Verfuegung 
// stehen, wird hier eine Fallunterscheidung gemacht
// WICHTIG: wird die Multibyte-Funktion nicht genutzt, funktioniert die Umwandlung von Umlauten nicht !!!
function admEncodeMimeheader($string)
{
    if(function_exists('mb_encode_mimeheader'))
    {
    	mb_internal_encoding('UTF-8');
        return mb_encode_mimeheader(stripslashes($string), 'UTF-8', 'Q');
    }
    else
    {
        return utf8_decode(stripslashes($string));
    }
}

// removes html, php code and blancs at beginning and end 
// of string or all elements of array without ckeditor variables !!!
function admStrStripTagsSpecial($srcArray)
{
    foreach($srcArray as $key => $value)
    {
        if($key != 'admEcardMessage' // ckeditor-variable
        && $key != 'ann_description'
        && $key != 'dat_description'
        && $key != 'gbc_text'
        && $key != 'gbo_text'
        && $key != 'lnk_description'
        && $key != 'mail_body'
        && $key != 'plugin_CKEditor'
        && $key != 'room_description'
        && $key != 'usf_description')
        {
            $srcArray[$key] = strStripTags($value);
        }
    }
    return $srcArray;
}
 
// removes html, php code and blancs at beginning and end 
// of string or all elements of array
function strStripTags($srcString)
{
    if(is_array($srcString))
    {
        // call function for every array element
        $srcString = array_map('strStripTags', $srcString);
    }
    else
    {
        // remove blancs at beginning and end
        $srcString = trim($srcString);
        // removes html and php code
        $srcString = strip_tags($srcString);
    }

    return $srcString;
}

// fuegt Quotes einem mittels addslashes() gequoteten Array und String hinzu
function strAddSlashesDeep($value)
{
    if(is_array($value))
    {
        $value = array_map('strAddSlashesDeep', $value);
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
        $value = array_map('strStripSlashesDeep', $value);
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
    $aSmall = ord('a');
    $zSmall = ord('z');
    $aBig   = ord('A');
    $zBig   = ord('Z');

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

/** Check if a string contains only valid characters. Therefore the string is
 *  compared with a hard coded list of valid characters for each datatype. 
 *  @param $string    The string that should be checked.
 *  @param $checkType The type @b email, @b file, @b noSpecialChar or @b url that
 *                    will be checked. Each type has a different valid character list.
 *  @return Returns @b true if all characters of @b string match the internal character list.
 */
function strValidCharacters($string, $checkType)
{
    if(strlen(trim($string)) > 0)
	{
		switch($checkType)
		{
			case 'email':
				$validChars = 'abcdefghijklmnopqrstuvwxyz0123456789áàâåäæcccçéèeênnñóòôöõøœúùûüß.-_@';
				break;
			case 'file':
				$validChars = 'abcdefghijklmnopqrstuvwxyz0123456789áàâåäæcccçéèeênnñóòôöõøœúùûüß$&!?.-_+ ';
				break;
			case 'noSpecialChar': // eine einfache E-Mail-Adresse sollte dennoch moeglich sein (Benutzername)
				$validChars = 'abcdefghijklmnopqrstuvwxyz0123456789.-_+@';
				break;
			case 'url':
				$validChars = 'abcdefghijklmnopqrstuvwxyz0123456789áàâåäæcccçéèeênnñóòôöõøœúùûüß.-_:/#?=%&!';
				break;
		}
		
        // check if string contains only valid characters
        if(strspn(admStrToLower($string), $validChars) == strlen($string))
        {
			if($checkType == 'email')
			{
				// check structure of email address
				return preg_match('/^[^@]+@[^@]+\.[^@]{2,}$/', trim($string));
			}
			return true;
        }
	}
	return false;
}

/** Check if a filename contains invalid characters. The characters will be checked with strValidCharacters.
 *  In addition the function checks if the name contains .. or a . at the beginning.
 *  @param $filename       Name of the file that should be checked.
 *  @param $checkExtension If set to @b true then the extension will be checked against a blacklist of extensions:
 *                         php, php3, php4, php5, html, htm, htaccess, htpasswd, pl, js, vbs, asp, cgi, ssi
 *  @return Returns @true if filename contains valid characters. Otherwise an exception is thrown with the 
 *          following values:
 *          SYS_FILENAME_EMPTY : Filename was empty
 *          BAC_FILE_NAME_INVALID : Filename contains invalid characters
 *          DOW_FILE_EXTENSION_INVALID : Filename contains invalid extension
 */
function admStrIsValidFileName($filename, $checkExtension = false)
{
    // If the filename was not empty
    if(strlen(trim($filename)) > 0)
    {
		// filename should only contains valid characters
        if(strValidCharacters($filename, 'file')
		&& strpos($filename, '..') === false
		&& substr($filename, 0, 1) != '.')
		{
			if($checkExtension)
			{
				// check if the extension is not blacklisted
				$extensionBlacklist = array('php', 'php3', 'php4', 'php5', 'html', 'htm', 'htaccess', 'htpasswd', 'pl',
										 'js', 'vbs', 'asp', 'cgi', 'ssi');
				$fileExtension  = substr($filename, strrpos($filename, '.')+1);

				if(in_array(strtolower($fileExtension), $extensionBlacklist))
				{
					throw new AdmException('DOW_FILE_EXTENSION_INVALID');
				}
			}
			return 1;
		}
		else
		{
			throw new AdmException('BAC_FILE_NAME_INVALID');
		}
    }
    else
    {
		throw new AdmException('SYS_FILENAME_EMPTY');
    }
}

?>