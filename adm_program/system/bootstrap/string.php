<?php
/**
 ***********************************************************************************************
 * Common functions that manipulate strings
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'string.php')
{
    exit('This page may not be called directly!');
}

/**
 * removes html, php code and blancs at beginning and end
 * of string or all elements of array without ckeditor variables !!!
 * @param array<string,string|array<mixed,string>> $srcArray
 * @return array<string,string|array<mixed,string>>
 */
function admStrStripTagsSpecial(array $srcArray)
{
    // "ecard_message" => ckeditor-variable
    $specialKeys = array(
        'ecard_message', 'ann_description', 'dat_description', 'gbc_text', 'gbo_text', 'lnk_description',
        'msg_body', 'plugin_CKEditor', 'room_description', 'usf_description', 'mail_smtp_password'
    );

    foreach ($srcArray as $key => $value)
    {
        if (!in_array($key, $specialKeys, true))
        {
            $srcArray[$key] = strStripTags($value);
        }
    }

    return $srcArray;
}

/**
 * removes html, php code and whitespaces at beginning and end of string or all elements of array
 * @param string|array<mixed,string> $value
 * @return string|array<mixed,string>
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
 * @param string|array<mixed,string|array> $value
 * @return string|array<mixed,string|array>
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
 * @param string|array<mixed,string|array> $value
 * @return string|array<mixed,string|array>
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
 * @param string $string    The string that should be checked.
 * @param string $checkType The type **noSpecialChar**, **email**, **file**, **url** or **phone** that will be checked.
 *                          Each type has a different valid character list.
 * @return bool Returns **true** if all characters of **string** match the internal character list.
 */
function strValidCharacters($string, $checkType)
{
    if (trim($string) === '')
    {
        return false;
    }

    switch ($checkType)
    {
        case 'noSpecialChar': // a simple e-mail address should still be possible (like username)
            $validRegex = '/^[\w.@+-]+$/i';
            break;
        case 'email':
            $validRegex = '/^[\wáàâåäæçéèêîñóòôöõøœúùûüß.@+-]+$/i';
            break;
        case 'file':
            $validRegex = '/^[\wáàâåäæçéèêîñóòôöõøœúùûüß$&!?() .@+-]+$/i';
            break;
        case 'url':
            $validRegex = '/^[\wáàâåäæçéèêîñóòôöõøœúùûüß$&!?() \/%=#:~.@+-]+$/i';
            break;
        case 'phone':
            $validRegex = '/^[\d() \/+-]+$/i';
            break;
        default:
            return false;
    }

    // check if string contains only valid characters
    if (!preg_match($validRegex, $string))
    {
        return false;
    }

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

/**
 * Check if a filename contains invalid characters. The characters will be checked with strValidCharacters.
 * In addition the function checks if the name contains .. or a . at the beginning.
 * @param string $filename     Name of the file that should be checked.
 * @param bool $checkExtension If set to **true** then the extension will be checked against a blacklist of extensions:
 *                             php, php3, php4, php5, html, htm, htaccess, htpasswd, pl, js, vbs, asp, cgi, ssi
 * @throws AdmException SYS_FILENAME_EMPTY : Filename was empty
 *                      SYS_FILENAME_INVALID : Filename contains invalid characters
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
    if (basename($filename) !== $filename || !strValidCharacters($filename, 'file') || StringUtils::strStartsWith($filename, '.'))
    {
        throw new AdmException('SYS_FILENAME_INVALID', array($filename));
    }

    if ($checkExtension)
    {
        // check if the extension is not blacklisted
        $extensionBlacklist = array('php', 'php3', 'php4', 'php5', 'html', 'htm', 'htaccess', 'htpasswd', 'pl',
                                    'js', 'vbs', 'asp', 'cgi', 'ssi');
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $extensionBlacklist, true))
        {
            throw new AdmException('DOW_FILE_EXTENSION_INVALID');
        }
    }

    return true;
}
