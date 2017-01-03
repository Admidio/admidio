<?php
/**
 ***********************************************************************************************
 * Common functions that manipulate strings
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * In case the multibyte functions are not supported, we fallback to a no-multibyte function
 * IMPORTANT: If the fallback is used, the conversion of umlauts not work!
 * admStrToLower\(([\w$\[\]()]+)\) -> mb_strtolower($1, 'UTF-8')
 * @param string $string
 * @return string
 */
function admStrToLower($string)
{
    if(function_exists('mb_strtolower'))
    {
        return mb_strtolower($string, 'UTF-8');
    }

    return strtolower($string);
}

/**
 * In case the multibyte functions are not supported, we fallback to a no-multibyte function
 * IMPORTANT: If the fallback is used, the conversion of umlauts not work!
 * @param string $string
 * @return string
 */
function admStrToUpper($string)
{
    if(function_exists('mb_strtoupper'))
    {
        return mb_strtoupper($string, 'UTF-8');
    }

    return strtoupper($string);
}

/**
 * removes html, php code and blancs at beginning and end
 * of string or all elements of array without ckeditor variables !!!
 * @param string[] $srcArray
 * @return string[]
 */
function admStrStripTagsSpecial(array $srcArray)
{
    foreach ($srcArray as $key => $value)
    {
        // "ecard_message" => ckeditor-variable
        $specialKeys = array(
            'ecard_message', 'ann_description', 'dat_description', 'gbc_text', 'gbo_text', 'lnk_description',
            'msg_body', 'plugin_CKEditor', 'room_description', 'usf_description', 'mail_smtp_password'
        );
        if (!in_array($key, $specialKeys, true))
        {
            $srcArray[$key] = strStripTags($value);
        }
    }

    return $srcArray;
}

/**
 * removes html, php code and whitespaces at beginning and end of string or all elements of array
 * @param string|string[] $value
 * @return string|string[]
 */
function strStripTags($value)
{
    if(is_array($value))
    {
        // call function for every array element
        $value = array_map('strStripTags', $value);
    }
    else
    {
        // remove whitespaces at beginning and end
        $value = trim($value);
        // removes html and php code
        $value = strip_tags($value);
    }

    return $value;
}

/**
 * fuegt Quotes einem mittels addslashes() gequoteten Array und String hinzu
 * @param string|string[] $value
 * @return string|string[]
 */
function strAddSlashesDeep($value)
{
    if(is_array($value))
    {
        // call function for every array element
        $value = array_map('strAddSlashesDeep', $value);
    }
    else
    {
        $value = addslashes($value);
    }

    return $value;
}

/**
 * Entfernt Quotes aus einem mittels addslashes() gequoteten Array und String
 * @param string|string[] $value
 * @return string|string[]
 */
function strStripSlashesDeep($value)
{
    if(is_array($value))
    {
        // call function for every array element
        $value = array_map('strStripSlashesDeep', $value);
    }
    else
    {
        $value = stripslashes($value);
    }

    return $value;
}

/**
 * Determines the previous or next letter in the alphabet
 *
 * reverse = false -> naechster Buchstabe
 * reverse = true -> vorheriger Buchstabe
 * Example:   g -> h      g -> f
 *
 * @param string $letter
 * @param bool $reverse
 * @return string
 */
function strNextLetter($letter, $reverse = false)
{
    $ascii      = ord($letter);
    $aLowerCase = ord('a');
    $zLowerCase = ord('z');
    $aUpperCase = ord('A');
    $zUpperCase = ord('Z');

    if ($ascii === $aLowerCase || $ascii === $zLowerCase || $ascii === $aUpperCase || $ascii === $zUpperCase)
    {
        if (!$reverse && ($ascii === $aLowerCase || $ascii === $aUpperCase))
        {
            ++$ascii;
        }

        if ($reverse && ($ascii === $zLowerCase || $ascii === $zUpperCase))
        {
            --$ascii;
        }
    }
    else
    {
        if ($reverse)
        {
            --$ascii;
        }
        else
        {
            ++$ascii;
        }
    }

    return chr($ascii);
}

/**
 * Check if a string contains only valid characters. Therefore the string is
 * compared with a hard coded list of valid characters for each datatype.
 * @param string $string              The string that should be checked.
 * @param string $checkType           The type @b email, @b file, @b noSpecialChar, @b phone or @b url that will be checked.
 *                                    Each type has a different valid character list.
 * @return bool Returns @b true if all characters of @b string match the internal character list.
 */
function strValidCharacters($string, $checkType)
{
    if(trim($string) !== '')
    {
        switch ($checkType)
        {
            case 'email':
                $validRegex = '/^[áàâåäæcccçéèeênnñóòôöõøœúùûüß\w\.@+-]+$/';
                break;
            case 'file':
                $validRegex = '/^[áàâåäæcccçéèeênnñóòôöõøœúùûüß\w\.@$&!?() +-]+$/';
                break;
            case 'noSpecialChar': // eine einfache E-Mail-Adresse sollte dennoch moeglich sein (Benutzername)
                $validRegex = '/^[\w\.@+-]+$/';
                break;
            case 'phone':
                $validRegex = '/^[\d\/() +-]+$/';
                break;
            case 'url':
                $validRegex = '/^[áàâåäæcccçéèeênnñóòôöõøœúùûüß\w\.\/@$&!?%=#:() +-~]+$/';
                break;
            default:
                return false;
        }

        // check if string contains only valid characters
        if(preg_match($validRegex, admStrToLower($string)))
        {
            switch ($checkType)
            {
                case 'email':
                    return filter_var(trim($string), FILTER_VALIDATE_EMAIL) !== false;
                case 'url':
                    return filter_var(trim($string), FILTER_VALIDATE_URL) !== false;
                default:
                    return true;
            }
        }
    }
    return false;
}

/**
 * Check if a filename contains invalid characters. The characters will be checked with strValidCharacters.
 * In addition the function checks if the name contains .. or a . at the beginning.
 * @param string $filename     Name of the file that should be checked.
 * @param bool $checkExtension If set to @b true then the extension will be checked against a blacklist of extensions:
 *                             php, php3, php4, php5, html, htm, htaccess, htpasswd, pl, js, vbs, asp, cgi, ssi
 * @throws AdmException SYS_FILENAME_EMPTY : Filename was empty
 *                      BAC_FILE_NAME_INVALID : Filename contains invalid characters
 *                      DOW_FILE_EXTENSION_INVALID : Filename contains invalid extension
 * @return true Returns @true if filename contains only valid characters. Otherwise an AdmException is thrown
 */
function admStrIsValidFileName($filename, $checkExtension = false)
{
    // If the filename was not empty
    if (trim($filename) === '')
    {
        throw new AdmException('SYS_FILENAME_EMPTY');
    }

    // filename should only contains valid characters and don't start with a dot
    if (basename($filename) !== $filename || !strValidCharacters($filename, 'file') || strpos($filename, '.') === 0)
    {
        throw new AdmException('BAC_FILE_NAME_INVALID');
    }

    if ($checkExtension)
    {
        // check if the extension is not blacklisted
        $extensionBlacklist = array('php', 'php3', 'php4', 'php5', 'html', 'htm', 'htaccess', 'htpasswd', 'pl',
                                    'js', 'vbs', 'asp', 'cgi', 'ssi');
        $fileExtension = substr($filename, strrpos($filename, '.') + 1);

        if (in_array(strtolower($fileExtension), $extensionBlacklist, true))
        {
            throw new AdmException('DOW_FILE_EXTENSION_INVALID');
        }
    }

    return true;
}
