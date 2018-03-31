<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
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
        if (function_exists('mb_strtolower'))
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
    public static function strToUpper($string)
    {
        if (function_exists('mb_strtoupper'))
        {
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
        if ($caseSensitive)
        {
            return strpos($string, $contains) !== false;
        }

        return stripos($string, $contains) !== false;
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
        if ($caseSensitive)
        {
            return strpos($string, $start) === 0;
        }

        return stripos($string, $start) === 0;
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
        if ($caseSensitive)
        {
            return strrpos($string, $end) === strlen($string) - strlen($end);
        }

        return strripos($string, $end) === strlen($string) - strlen($end);
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
}
