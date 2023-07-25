<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class StringUtils
{
    /**
     * In case the multibyte functions are not supported, we fallback to a no-multibyte function
     * IMPORTANT: If the fallback is used, the conversion of umlauts not work!
     * StringUtils::strToLower\(([\w$\[\]()]+)\) -> mb_strtolower($1, 'UTF-8')
     * @param string $string
     * @return string
     */
    public static function strToLower($string)
    {
        if (function_exists('mb_strtolower')) {
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
    public static function strToUpper($string)
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($string, 'UTF-8');
        }

        return strtoupper($string);
    }

    /**
     * Checks if a string contains another given string
     * @param string $string        The string to check
     * @param string $contains      The containing string pattern
     * @param bool   $caseSensitive Flag to change between case-sensitive and case-insensitive
     * @return bool Returns true if the string contains the other string
     */
    public static function strContains($string, $contains, $caseSensitive = true)
    {
        if ($caseSensitive) {
            return str_contains($string, $contains);
        }

        return $contains === '' || stripos($string, $contains) !== false;
    }

    /**
     * Checks if a string starts with another given string
     * @param string $string        The string to check
     * @param string $start         The starting string pattern
     * @param bool   $caseSensitive Flag to change between case-sensitive and case-insensitive
     * @return bool Returns true if the string starts with the other string
     */
    public static function strStartsWith($string, $start, $caseSensitive = true)
    {
        if ($caseSensitive) {
            return str_starts_with($string, $start);
        }

        return substr_compare($string, $start, 0, strlen($start), true) === 0;
    }

    /**
     * Checks if a string ends with another given string
     * @param string $string        The string to check
     * @param string $end           The ending string pattern
     * @param bool   $caseSensitive Flag to change between case-sensitive and case-insensitive
     * @return bool Returns true if the string ends with the other string
     */
    public static function strEndsWith($string, $end, $caseSensitive = true)
    {
        if ($caseSensitive) {
            return str_ends_with($string, $end);
        }

        return $end === '' || ($string !== '' && substr_compare($string, $end, -strlen($end), true) === 0);
    }

    /**
     * Easy way for multiple replacements in a string.
     * @param string               $string   The string where to replace strings
     * @param array<string,string> $replaces An array with search and replace values
     * @return string The modified string
     */
    public static function strMultiReplace($string, $replaces)
    {
        return str_replace(array_keys($replaces), array_values($replaces), $string);
    }

    /**
     * removes html, php code and blancs at beginning and end
     * of string or all elements of array without ckeditor variables !!!
     * @param array<string,string|array<mixed,string>> $srcArray
     * @return array<string,string|array<mixed,string>>
     */
    public static function strStripSpecialTags(array $srcArray)
    {
        // "ecard_message" => ckeditor-variable
        $specialKeys = array(
            'ecard_message', 'ann_description', 'dat_description', 'gbc_text', 'gbo_text', 'lnk_description',
            'msg_body', 'plugin_CKEditor', 'room_description', 'usf_description', 'mail_smtp_password'
        );

        foreach ($srcArray as $key => $value) {
            if (!in_array($key, $specialKeys, true)) {
                $srcArray[$key] = self::strStripTags($value);
            }
        }

        return $srcArray;
    }

    /**
     * removes html, php code and whitespaces at beginning and end of string or all elements of array
     * @param string|array<mixed,string> $value
     * @return string|array<mixed,string>
     */
    public static function strStripTags($value)
    {
        if (is_array($value)) {
            // call function for every array element
            $value = array_map(self::class . '::strStripTags', $value);
        } elseif ((string) $value !== '') {
            // remove whitespaces at beginning and end
            $value = trim($value);
            // removes html and php code
            $value = strip_tags($value);
        }

        return $value;
    }

    /**
     * Check if a string contains only valid characters. Therefore the string is
     * compared with a hard coded list of valid characters for each datatype.
     * @param string $string    The string that should be checked.
     * @param string $checkType The type **noSpecialChar**, **email**, **file**, **url** or **phone** that will be checked.
     *                          Each type has a different valid character list.
     * @return bool Returns **true** if all characters of **string** match the internal character list.
     */
    public static function strValidCharacters($string, $checkType)
    {
        if (trim($string) === '') {
            return false;
        }

        switch ($checkType) {
            case 'noSpecialChar': // a simple e-mail address should still be possible (like username)
                $validRegex = '/^[\w.@+-]+$/i';
                break;
            case 'email':
                $validRegex = '/^[\wáàâåäæçéèêîñóòôöõøœúùûüß.@+-]+$/i';
                break;
            case 'file':
                $validRegex = '=^[^/?*;:~<>|\"\\\\]+\.[^/?*;:~<>|‚\"\\\\]+$=';
                break;
            case 'folder':
                $validRegex = '=^[^/?*;:~<>|\"\\\\]+$=';
                break;
            case 'url':
                $validRegex = '/^[\wáàâåäæçéèêîñóòôöõøœúùûüß$&!?() \/%=#:~.@+-]+$/i';
                $validRegexValidUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i';
                break;
            case 'phone':
                $validRegex = '/^[\d() \/+-]+$/i';
                break;
            default:
                return false;
        }

        // check if string contains only valid characters
        if (!preg_match($validRegex, $string)) {
            return false;
        }

        // check url

        switch ($checkType) {
            case 'email':
                return filter_var(trim($string), FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                // url has a valid structure
                if (!preg_match($validRegexValidUrl, $string)) {
                    return false;
                }

                return filter_var(trim($string), FILTER_VALIDATE_URL) !== false;
            default:
                return true;
        }
    }

    /**
     * Check if a filename contains invalid characters. The characters will be checked with StringUtils::strValidCharacters.
     * In addition the function checks if the name contains .. or a . at the beginning.
     * @param string $filename     Name of the file that should be checked.
     * @param bool $checkExtension If set to **true** then the extension will be checked against a block-list of extensions:
     *                             php, php3, php4, php5, html, htm, htaccess, htpasswd, pl, js, vbs, asp, cgi, ssi, phar
     * @throws AdmException SYS_FILENAME_EMPTY : Filename was empty
     *                      SYS_FILENAME_INVALID : Filename contains invalid characters
     *                      SYS_FILE_EXTENSION_INVALID : Filename contains invalid extension
     * @return true Returns @true if filename contains only valid characters. Otherwise an AdmException is thrown
     */
    public static function strIsValidFileName($filename, $checkExtension = true)
    {
        $filename = urldecode($filename);

        // If the filename was not empty
        if (trim($filename) === '') {
            throw new AdmException('SYS_FILENAME_EMPTY');
        }

        // filename should only contains valid characters and don't start with a dot
        if (
            basename($filename) !== $filename ||
            self::strStartsWith($filename, '.') ||
            self::strContains($filename, '//') ||
            self::strContains($filename, '\\') ||
            (!self::strValidCharacters($filename, 'file') && $checkExtension) ||
            (!self::strValidCharacters($filename, 'folder') && !$checkExtension)
        ) {
            throw new AdmException('SYS_FILENAME_INVALID', array(SecurityUtils::encodeHTML(self::strStripTags($filename))));
        }

        if ($checkExtension) {
            // check if the extension is not listed as blocked
            $extensionBlocklist = array('php', 'php3', 'php4', 'php5', 'pht', 'html', 'htm', 'phtml',
                'shtml', 'htaccess', 'htpasswd', 'pl', 'js', 'vbs', 'asp',
                'asa', 'cer', 'asax', 'swf', 'xap', 'cgi', 'ssi', 'phar', 'svg');
            $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($fileExtension, $extensionBlocklist, true)) {
                throw new AdmException('SYS_FILE_EXTENSION_INVALID');
            }
        }

        return true;
    }

    /**
     * Check if a filename contains invalid characters. The characters will be checked with StringUtils::strValidCharacters.
     * In addition the function checks if the name contains .. or a . at the beginning.
     * @param string $filename     Name of the file that should be checked.
     * @throws AdmException SYS_FILENAME_EMPTY : Filename was empty
     *                      SYS_FILENAME_INVALID : Filename contains invalid characters
     * @return true Returns @true if filename contains only valid characters. Otherwise an AdmException is thrown
     */
    public static function strIsValidFolderName($filename)
    {
        // If the filename was not empty
        if (trim($filename) === '') {
            throw new AdmException('SYS_FOLDER_NAME_EMPTY');
        }

        // filename should only contains valid characters and don't start with a dot
        if (basename($filename) !== $filename || self::strStartsWith($filename, '.') || !self::strValidCharacters($filename, 'folder')) {
            throw new AdmException('SYS_FOLDER_NAME_INVALID', array($filename));
        }

        return true;
    }
}
