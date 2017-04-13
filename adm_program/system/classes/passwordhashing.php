<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// provide forward compatibility with the password_* functions that ship with PHP 5.5
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/password_compat/password.php');
// provide forward compatibility with the random_* functions that ship with PHP 7.0
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/random_compat/lib/random.php');
// old phpass password hashing lib for backward compatibility
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/phpass/passwordhash.php');

/**
 * @class PasswordHashing
 *
 * This class provides static functions for different tasks for passwords and hashing
 * It used the "password_compat" lib to provide forward compatibility with the password_* functions that ship with PHP 5.5
 * It used the "random_compat" lib to provide forward compatibility with the random_* functions that ship with PHP 7.0
 * It used the "phpass" lib to provide backward compatibility to the old password hashing way
 *
 * Functions:
 * hash()               hash the given password with the given options
 * verify()             verify if the given password belongs to the given hash
 * needsRehash()        checks if the given hash is generated from the given options
 * genRandomPassword()  generate a cryptographically strong random password
 * genRandomInt()       generate a cryptographically strong random int
 * passwordInfo()       provides infos about the given password (length, number, lowerCase, upperCase, symbol)
 * hashInfo()           provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
 * costBenchmark()      run a benchmark to get the best fitting cost value
 */
class PasswordHashing
{
    /**
     * Hash the given password with the given options. The default algorithm uses the password_* methods,
     * otherwise the builtin helper for SHA-512 crypt hashes from the operating system. Minimum cost is 10.
     * @param string $password  The password string
     * @param string $algorithm The hash-algorithm method. Possible values are 'DEFAULT', 'BCRYPT' or 'SHA512'.
     * @param array  $options   The hash-options array
     * @return string|false Returns the hashed password or false if an error occurs
     */
    public static function hash($password, $algorithm = 'DEFAULT', array $options = array())
    {
        if ($algorithm === 'SHA512')
        {
            if (!array_key_exists('cost', $options))
            {
                $options['cost'] = 100000;
            }

            $salt = self::genRandomPassword(8, './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
            return crypt($password, '$6$rounds=' . $options['cost'] . '$' . $salt . '$');
        }
        elseif ($algorithm === 'BCRYPT')
        {
            $algorithmPhpConstant = PASSWORD_BCRYPT;
        }
        else
        {
            $algorithmPhpConstant = PASSWORD_DEFAULT;
        }

        if (!array_key_exists('cost', $options))
        {
            $options['cost'] = 12;
        }
        // https://paragonie.com/blog/2016/02/how-safely-store-password-in-2016
        if ($options['cost'] < 10)
        {
            $options['cost'] = 10;
        }

        return password_hash($password, $algorithmPhpConstant, $options);
    }

    /**
     * Verify if the given password belongs to the given hash
     * @param string $password The password string to check
     * @param string $hash     The hash string to check
     * @return bool Returns true if the password belongs to the hash and false if not
     */
    public static function verify($password, $hash)
    {
        if (strlen($hash) === 60 && strpos($hash, '$2y$') === 0)
        {
            return password_verify($password, $hash);
        }
        elseif (strlen($hash) >= 110 && strpos($hash, '$6$') === 0)
        {
            $passwordHash = crypt($password, $hash);

            if (function_exists('hash_equals'))
            {
                return hash_equals($passwordHash, $hash);
            }

            $status = 0;
            for ($i = 0, $iMax = strlen($passwordHash); $i < $iMax; $i++) {
                $status |= (ord($passwordHash[$i]) ^ ord($hash[$i]));
            }

            return $status === 0;
        }
        elseif (strlen($hash) === 34 && strpos($hash, '$P$') === 0)
        {
            $passwordHasher = new PasswordHash(9, true);
            return $passwordHasher->CheckPassword($password, $hash);
        }
        elseif (strlen($hash) === 32)
        {
            return md5($password) === $hash;
        }

        return false;
    }

    /**
     * Checks if the given hash is generated from the given options. The default algorithm uses the
     * password_* methods, otherwise the builtin helper for SHA-512 crypt hashes from the operating system.
     * @param string $hash      The hash string that should checked
     * @param string $algorithm The hash-algorithm the hash should match to
     * @param array  $options   The hash-options the hash should match to
     * @return bool Returns false if the hash match the given options and false if not
     */
    public static function needsRehash($hash, $algorithm = 'DEFAULT', array $options = array())
    {
        if ($algorithm === 'SHA512')
        {
            $hashParts = explode('$', $hash);
            $cost = (int) substr($hashParts[2], 7);

            return strlen($hash) < 110 || strpos($hash, '$6$') !== 0 || $cost !== $options['cost'];
        }
        elseif ($algorithm === 'BCRYPT')
        {
            $algorithmPhpConstant = PASSWORD_BCRYPT;
        }
        else
        {
            $algorithmPhpConstant = PASSWORD_DEFAULT;
        }

        return password_needs_rehash($hash, $algorithmPhpConstant, $options);
    }

    /**
     * Generate a cryptographically strong random password
     * @param int    $length  The length of the generated password (default = 16)
     * @param string $charset A string of all possible characters to choose from (default = [0-9a-zA-z])
     * @throws AdmException SYS_GEN_RANDOM_TWO_DISTINCT_CHARS
     * @return string Returns a cryptographically strong random password string
     * @link https://paragonie.com/b/JvICXzh_jhLyt4y3
     */
    public static function genRandomPassword($length = 16, $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if ($length < 1)
        {
            // Just return an empty string. Any value < 1 is meaningless.
            return '';
        }

        // Remove duplicate characters from $charset
        $split = str_split($charset);
        $charset = implode('', array_unique($split));

        // This is the maximum index for all of the characters in the string $charset
        $charsetMax = strlen($charset) - 1;
        if ($charsetMax < 1)
        {
            // Avoid letting users do: randomString($int, 'a'); -> 'aaaaa...'
            throw new AdmException('SYS_GEN_RANDOM_TWO_DISTINCT_CHARS');
        }

        $randomString = '';
        for ($i = 0; $i < $length; ++$i)
        {
            $r = self::genRandomInt(0, $charsetMax);
            $randomString .= $charset[$r];
        }

        return $randomString;
    }

    /**
     * Generate a cryptographically strong random integer
     * @param int  $min                     The min of the range (inclusive)
     * @param int  $max                     The max of the range (inclusive)
     * @param bool $exceptionOnInsecurePRNG Could be set to true to get an Exception if no secure PRN could be generated.
     * @throws AdmException
     * @return int Returns a cryptographically strong random integer
     */
    public static function genRandomInt($min, $max, $exceptionOnInsecurePRNG = false)
    {
        global $gLogger;

        try
        {
            $int = random_int($min, $max);
        }
        catch (Error $e)
        {
            $gLogger->warning('SECURITY: Could not generate secure pseudo-random number!', array('code' => $e->getCode(), 'message' => $e->getMessage()));

            if ($exceptionOnInsecurePRNG)
            {
                throw new AdmException('SYS_GEN_RANDOM_ERROR', $e->getCode(), $e->getMessage());
            }

            // as a fallback we use the mt_rand method
            $int = mt_rand($min, $max);
        }
        catch (Exception $e)
        {
            $gLogger->warning('SECURITY: Could not generate secure pseudo-random number!', array('code' => $e->getCode(), 'message' => $e->getMessage()));

            if ($exceptionOnInsecurePRNG)
            {
                throw new AdmException('SYS_GEN_RANDOM_EXCEPTION', $e->getCode(), $e->getMessage());
            }

            // as a fallback we use the mt_rand method
            $int = mt_rand($min, $max);
        }

        return $int;
    }

    /**
     * Provides infos about the given password (length, number, lowerCase, upperCase, symbol)
     * @param string $password The password you want the get infos about
     * @return array<string,int|bool> Returns an array with infos about the given password
     */
    public static function passwordInfo($password)
    {
        $passwordInfo = array(
            'length'    => 0,
            'number'    => false,
            'lowerCase' => false,
            'upperCase' => false,
            'symbol'    => false
        );

        $passwordInfo['length'] = strlen($password);

        if (preg_match('/\d/', $password) === 1)
        {
            $passwordInfo['number'] = true;
        }
        if (preg_match('/[a-z]/', $password) === 1)
        {
            $passwordInfo['lowerCase'] = true;
        }
        if (preg_match('/[A-Z]/', $password) === 1)
        {
            $passwordInfo['upperCase'] = true;
        }
        if (preg_match('/\W/', $password) === 1 || strpos($password, '_') !== false) // Note: \W = ![0-9a-zA-Z_]
        {
            $passwordInfo['symbol'] = true;
        }

        return $passwordInfo;
    }

    /**
     * Calculates the strength of a given password from 0-4.
     * @param string   $password The password to check
     * @param string[] $userData An array of strings for dictionary attacks
     * @return int Returns the score of the password
     */
    public static function passwordStrength($password, array $userData = array())
    {
        $zxcvbn = new \ZxcvbnPhp\Zxcvbn();
        $strength = $zxcvbn->passwordStrength($password, $userData);
        return $strength['score'];
    }

    /**
     * Provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
     * @param string $hash The hash you want the get infos about
     * @return array|string Returns an array or string with infos about the given hash
     */
    public static function hashInfo($hash)
    {
        if (strlen($hash) === 60 && strpos($hash, '$2y$') === 0)
        {
            return password_get_info($hash);
        }
        elseif (strlen($hash) >= 110 && strpos($hash, '$6$') === 0)
        {
            return 'SHA512';
        }
        elseif (strlen($hash) === 34 && strpos($hash, '$P$') === 0)
        {
            return 'PRIVATE/PORTABLE_HASH';
        }
        elseif (strlen($hash) === 32)
        {
            return 'MD5';
        }

        return 'UNKNOWN';
    }

    /**
     * Run a benchmark to get the best fitting cost value. The cost value can vary from 4 to 31.
     * @param float  $maxTime   The maximum time the hashing process should take in seconds
     * @param string $password  The password to test
     * @param string $algorithm The algorithm to test
     * @param array  $options   The options to test
     * @return array Returns an array with the maximum tested cost with the required time
     */
    public static function costBenchmark($maxTime = 0.5, $password = 'password', $algorithm = 'DEFAULT', array $options = array('cost' => 10))
    {
        global $gLogger;

        $time = 0;
        $results = array();
        $cost = $options['cost'];

        if ($algorithm === 'SHA512')
        {
            $maxCost = 999999999;
            $costIncrement = 50000;
            if ($cost < 1000)
            {
                $cost = 1000;
            }
        }
        else
        {
            $maxCost = 31;
            $costIncrement = 1;
            if ($cost < 4)
            {
                $cost = 4;
            }
        }

        // loop through the cost value until the needed hashing time reaches the maximum set time
        while ($time <= $maxTime && $cost <= $maxCost)
        {
            $options['cost'] = $cost;

            $start = microtime(true);
            self::hash($password, $algorithm, $options);
            $end = microtime(true);

            $time = $end - $start;

            $results = array('cost' => $cost, 'time' => $time);
            $cost += $costIncrement;
        }

        $gLogger->notice('Benchmark: Password-hashing results.', $results);

        return $results;
    }
}
