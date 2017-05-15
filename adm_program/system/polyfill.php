<?php
/**
 ***********************************************************************************************
 * Includes the different polyfills
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// provide forward compatibility with the password_* functions that ship with PHP 5.5
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/password_compat/password.php');
// provide forward compatibility with the random_* functions that ship with PHP 7.0
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/random_compat/lib/random.php');
// provide forward compatibility with the hash_equals function that ship with PHP 5.6
if (!function_exists('hash_equals'))
{
    /**
     * @param string $knownString
     * @param string $userInput
     * @return bool
     */
    function hash_equals($knownString, $userInput)
    {
        if (!is_string($knownString))
        {
            trigger_error('Expected known_string to be a string, ' . gettype($knownString) . ' given', E_USER_WARNING);
            return false;
        }
        if (!is_string($userInput))
        {
            trigger_error('Expected user_input to be a string, ' . gettype($userInput) . ' given', E_USER_WARNING);
            return false;
        }
        $knownLen = strlen($knownString);
        $userLen = strlen($userInput);
        if ($knownLen !== $userLen)
        {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < $knownLen; ++$i)
        {
            $result |= ord($knownString[$i]) ^ ord($userInput[$i]);
        }
        return 0 === $result;
    }
}
