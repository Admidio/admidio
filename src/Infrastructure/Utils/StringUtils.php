<?php
namespace Admidio\Infrastructure\Utils;

use Admidio\Infrastructure\Exception;

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StringUtils
{
    /**
     * In case the multibyte functions are not supported, we do a fallback to a no-multibyte function
     * IMPORTANT: If the fallback is used, the conversion of umlauts not work!
     * StringUtils::strToLower\(([\w$\[\]()]+)\) -> mb_strtolower($1, 'UTF-8')
     * @param string $string
     * @return string
     */
    public static function strToLower(string $string): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($string, 'UTF-8');
        }

        return strtolower($string);
    }

    /**
     * In case the multibyte functions are not supported, we de a fallback to a no-multibyte function
     * IMPORTANT: If the fallback is used, the conversion of umlauts not work!
     * @param string $string
     * @return string
     */
    public static function strToUpper(string $string): string
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
     * @param bool $caseSensitive Flag to change between case-sensitive and case-insensitive
     * @return bool Returns true if the string contains the other string
     */
    public static function strContains(string $string, string $contains, bool $caseSensitive = true): bool
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
     * @param bool $caseSensitive Flag to change between case-sensitive and case-insensitive
     * @return bool Returns true if the string starts with the other string
     */
    public static function strStartsWith(string $string, string $start, bool $caseSensitive = true): bool
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
     * @param bool $caseSensitive Flag to change between case-sensitive and case-insensitive
     * @return bool Returns true if the string ends with the other string
     */
    public static function strEndsWith(string $string, string $end, bool $caseSensitive = true): bool
    {
        if ($caseSensitive) {
            return str_ends_with($string, $end);
        }

        return $end === '' || ($string !== '' && substr_compare($string, $end, -strlen($end), true) === 0);
    }

    /**
     * Easy way for multiple replacements in a string.
     * @param string $string   The string where to replace strings
     * @param array<string,string> $replaces An array with search and replace values
     * @return string The modified string
     */
    public static function strMultiReplace(string $string, array $replaces): string
    {
        return str_replace(array_keys($replaces), array_values($replaces), $string);
    }

    /**
     * removes html, php code and blancs at beginning and end
     * of string or all elements of array without ckeditor variables !!!
     * @param array<string,string|array<mixed,string>> $srcArray
     * @return array<string,string|array<mixed,string>>
     */
    public static function strStripSpecialTags(array $srcArray): array
    {
        // "ecard_message" => ckeditor-variable
        $specialKeys = array(
            'ecard_message', 'ann_description', 'dat_description', 'fop_text', 'lnk_description',
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
     * Check if a string contains only valid characters. Therefore, the string is
     * compared with a hard coded list of valid characters for each datatype.
     * @param string $string    The string that should be checked.
     * @param string $checkType The type **noSpecialChar**, **email**, **file**, **url** or **phone** that will be checked.
     *                          Each type has a different valid character list.
     * @return bool Returns **true** if all characters of **string** match the internal character list.
     */
    public static function strValidCharacters(string $string, string $checkType): bool
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
                $validRegex = '/^[\wáàâåäæçéèêîñóòôöõøœúùûüß$&!?(), \/%=#:~.@+-]+$/i';
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
     * In addition to the function checks if the name contains .. or a . at the beginning.
     * @param string $filename     Name of the file that should be checked.
     * @param bool $checkExtension If set to **true** then the extension will be checked against a block-list of extensions:
     *                             php, php3, php4, php5, html, htm, htaccess, htpasswd, pl, js, vbs, asp, cgi, ssi, phar
     * @return true Returns @true if filename contains only valid characters. Otherwise, an Exception is thrown
     * @throws Exception SYS_FILENAME_EMPTY : Filename was empty
     *                      SYS_FILENAME_INVALID : Filename contains invalid characters
     *                      SYS_FILE_EXTENSION_INVALID : Filename contains invalid extension
     */
    public static function strIsValidFileName(string $filename, bool $checkExtension = true): bool
    {
        $filename = urldecode($filename);

        // If the filename was not empty
        if (trim($filename) === '') {
            throw new Exception('SYS_FILENAME_EMPTY');
        }

        // filename should only contain valid characters and don't start with a dot
        if (
            basename($filename) !== $filename ||
            self::strStartsWith($filename, '.') ||
            self::strContains($filename, '//') ||
            self::strContains($filename, '\\') ||
            (!self::strValidCharacters($filename, 'file') && $checkExtension) ||
            (!self::strValidCharacters($filename, 'folder') && !$checkExtension)
        ) {
            throw new Exception('SYS_FILENAME_INVALID', array(SecurityUtils::encodeHTML(self::strStripTags($filename))));
        }

        if ($checkExtension) {
            // check if the extension is not listed as blocked
            $extensionBlocklist = array('php', 'php3', 'php4', 'php5', 'pht', 'html', 'htm', 'phtml',
                'shtml', 'htaccess', 'htpasswd', 'pl', 'js', 'vbs', 'asp',
                'asa', 'cer', 'asax', 'swf', 'xap', 'cgi', 'ssi', 'phar', 'svg');
            $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($fileExtension, $extensionBlocklist, true)) {
                throw new Exception('SYS_FILE_EXTENSION_INVALID');
            }
        }

        return true;
    }

    /**
     * Check if a filename contains invalid characters. The characters will be checked with StringUtils::strValidCharacters.
     * In addition to the function checks if the name contains .. or a . at the beginning.
     * @param string $filename     Name of the file that should be checked.
     * @return true Returns @true if filename contains only valid characters. Otherwise, an Exception is thrown
     * @throws Exception SYS_FILENAME_EMPTY : Filename was empty
     *                      SYS_FILENAME_INVALID : Filename contains invalid characters
     */
    public static function strIsValidFolderName(string $filename): bool
    {
        // If the filename was not empty
        if (trim($filename) === '') {
            throw new Exception('SYS_FOLDER_NAME_EMPTY');
        }

        // filename should only contain valid characters and don't start with a dot
        if (basename($filename) !== $filename || self::strStartsWith($filename, '.') || !self::strValidCharacters($filename, 'folder')) {
            throw new Exception('SYS_FOLDER_NAME_INVALID', array($filename));
        }

        return true;
    }
}
