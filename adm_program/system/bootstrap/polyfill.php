<?php
/**
 ***********************************************************************************************
 * Includes the different polyfills
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'polyfill.php') {
    exit('This page may not be called directly!');
}

// For PHP <7.2
if (!defined('PASSWORD_ARGON2I')) {
    define('PASSWORD_ARGON2I', 2); // PHP 7.4: "argon2i"
    define('PASSWORD_ARGON2_DEFAULT_MEMORY_COST', 65536);
    define('PASSWORD_ARGON2_DEFAULT_TIME_COST', 4);
    define('PASSWORD_ARGON2_DEFAULT_THREADS', 1);
}
// For PHP <7.3
if (!defined('PASSWORD_ARGON2ID')) {
    define('PASSWORD_ARGON2ID', 3); // PHP 7.4: "argon2id"
}

// For PHP <8.0
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || \strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return \strncmp($haystack, $needle, \strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return $needle === '' || ($haystack !== '' && \substr_compare($haystack, $needle, -\strlen($needle)) === 0);
    }
}
