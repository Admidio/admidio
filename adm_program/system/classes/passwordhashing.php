<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

define('HASH_COST_BCRYPT_DEFAULT', 12);
define('HASH_COST_BCRYPT_MIN', 10);
define('HASH_COST_BCRYPT_MAX', 31);
define('HASH_COST_SHA512_DEFAULT', 100000);
define('HASH_COST_SHA512_MIN', 10000);
define('HASH_COST_SHA512_MAX', 999999999);

define('HASH_LENGTH_BCRYPT', 60);
define('HASH_LENGTH_SHA512', 110);
define('HASH_LENGTH_PORTABLE', 34);
define('HASH_LENGTH_MD5', 32);

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
 * passwordStrength()   shows the strength of the given password
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
                $options['cost'] = HASH_COST_SHA512_DEFAULT;
            }
            if ($options['cost'] < HASH_COST_SHA512_MIN)
            {
                $options['cost'] = HASH_COST_SHA512_MIN;
            }

            $salt = self::genRandomPassword(8, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ./');
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
            $options['cost'] = HASH_COST_BCRYPT_DEFAULT;
        }
        // https://paragonie.com/blog/2016/02/how-safely-store-password-in-2016
        if ($options['cost'] < HASH_COST_BCRYPT_MIN)
        {
            $options['cost'] = HASH_COST_BCRYPT_MIN;
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
        $hashLength = strlen($hash);
        if ($hashLength === HASH_LENGTH_BCRYPT && strpos($hash, '$2y$') === 0)
        {
            return password_verify($password, $hash);
        }
        elseif ($hashLength >= HASH_LENGTH_SHA512 && strpos($hash, '$6$') === 0)
        {
            $passwordHash = crypt($password, $hash);
            return hash_equals($passwordHash, $hash);
        }
        elseif ($hashLength === HASH_LENGTH_PORTABLE && strpos($hash, '$P$') === 0)
        {
            $passwordHasher = new PasswordHash(9, true);
            return $passwordHasher->CheckPassword($password, $hash);
        }
        // MD5 Hashes are 32 chars long and consists out of HEX values (digits and a-f)
        elseif ($hashLength === HASH_LENGTH_MD5 && preg_match('/^[\dA-Fa-f]{32,32}$/', $hash))
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
        $hashLength = strlen($hash);
        if ($algorithm === 'SHA512' && $hashLength >= HASH_LENGTH_SHA512 && strpos($hash, '$6$') === 0)
        {
            if (!array_key_exists('cost', $options))
            {
                $options['cost'] = HASH_COST_SHA512_DEFAULT;
            }
            if ($options['cost'] < HASH_COST_SHA512_MIN)
            {
                $options['cost'] = HASH_COST_SHA512_MIN;
            }

            $hashParts = explode('$', $hash);
            $cost = (int) substr($hashParts[2], 7);

            return $cost !== $options['cost'];
        }
        elseif ($algorithm === 'BCRYPT' && $hashLength === HASH_LENGTH_BCRYPT && strpos($hash, '$2y$') === 0)
        {
            $algorithmPhpConstant = PASSWORD_BCRYPT;
        }
        elseif ($algorithm === 'DEFAULT')
        {
            $algorithmPhpConstant = PASSWORD_DEFAULT;
        }
        else
        {
            return true; // TODO
        }

        if (!array_key_exists('cost', $options))
        {
            $options['cost'] = HASH_COST_BCRYPT_DEFAULT;
        }
        // https://paragonie.com/blog/2016/02/how-safely-store-password-in-2016
        if ($options['cost'] < HASH_COST_BCRYPT_MIN)
        {
            $options['cost'] = HASH_COST_BCRYPT_MIN;
        }

        return password_needs_rehash($hash, $algorithmPhpConstant, $options);
    }

    /**
     * Generate a cryptographically strong random password
     * @param int    $length  The length of the generated password (default = 16)
     * @param string $charset A string of all possible characters to choose from (default = [0-9a-zA-z])
     * @throws AdmException SYS_GEN_RANDOM_TWO_DISTINCT_CHARS
     * @return string Returns a cryptographically strong random password string
     * @see https://paragonie.com/b/JvICXzh_jhLyt4y3
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
            $randomInt = self::genRandomInt(0, $charsetMax);
            $randomString .= $charset[$randomInt];
        }

        return $randomString;
    }

    /**
     * Generate an insecure random integer
     * @param int             $min                     The min of the range (inclusive)
     * @param int             $max                     The max of the range (inclusive)
     * @param bool            $exceptionOnInsecurePRNG Could be set to true to get an Exception if no secure PRN could be generated.
     * @param Error|Exception $exception               The thrown Error or Exception object.
     * @param string          $exceptionMessage        The Admidio Exception-Message.
     * @throws AdmException SYS_GEN_RANDOM_ERROR, SYS_GEN_RANDOM_EXCEPTION
     * @return int Returns an insecure random integer
     */
    private static function genRandomIntFallback($min, $max, $exceptionOnInsecurePRNG, $exception, $exceptionMessage)
    {
        global $gLogger;

        $gLogger->warning('SECURITY: Could not generate secure pseudo-random number!', array('code' => $exception->getCode(), 'message' => $exception->getMessage()));

        if ($exceptionOnInsecurePRNG)
        {
            throw new AdmException($exceptionMessage, $exception->getCode(), $exception->getMessage());
        }

        // as a fallback we use the mt_rand method
        return mt_rand($min, $max);
    }

    /**
     * Generate a cryptographically strong random integer
     * @param int  $min                     The min of the range (inclusive)
     * @param int  $max                     The max of the range (inclusive)
     * @param bool $exceptionOnInsecurePRNG Could be set to true to get an Exception if no secure PRN could be generated.
     * @throws AdmException SYS_GEN_RANDOM_ERROR, SYS_GEN_RANDOM_EXCEPTION
     * @return int Returns a cryptographically strong random integer
     */
    public static function genRandomInt($min, $max, $exceptionOnInsecurePRNG = false)
    {
        try
        {
            $int = random_int($min, $max);
        }
        catch (Error $e)
        {
            $int = self::genRandomIntFallback($min, $max, $exceptionOnInsecurePRNG, $e, 'SYS_GEN_RANDOM_ERROR');
        }
        catch (Exception $e)
        {
            $int = self::genRandomIntFallback($min, $max, $exceptionOnInsecurePRNG, $e, 'SYS_GEN_RANDOM_EXCEPTION');
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
     * Provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
     * @param string $hash The hash you want the get infos about
     * @return array|string Returns an array or string with infos about the given hash
     */
    public static function hashInfo($hash)
    {
        $hashLength = strlen($hash);
        if ($hashLength === HASH_LENGTH_BCRYPT && strpos($hash, '$2y$') === 0)
        {
            return password_get_info($hash);
        }
        elseif ($hashLength >= HASH_LENGTH_SHA512 && strpos($hash, '$6$') === 0)
        {
            return 'SHA512';
        }
        elseif ($hashLength === HASH_LENGTH_PORTABLE && strpos($hash, '$P$') === 0)
        {
            return 'PRIVATE/PORTABLE_HASH';
        }
        // MD5 Hashes are 32 chars long and consists out of HEX values (digits and a-f)
        elseif ($hashLength === HASH_LENGTH_MD5 && preg_match('/^[\dA-Fa-f]{32,32}$/', $hash))
        {
            return 'MD5';
        }

        return 'UNKNOWN';
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
     * Run a benchmark to get the best fitting cost value. The cost value can vary from 4 to 31.
     * @param float  $maxTime   The maximum time the hashing process should take in seconds
     * @param string $password  The password to test
     * @param string $algorithm The algorithm to test
     * @param array  $options   The options to test
     * @return array Returns an array with the maximum tested cost with the required time
     */
    public static function costBenchmark($maxTime = 0.35, $password = 'password', $algorithm = 'DEFAULT', array $options = array())
    {
        global $gLogger;

        $cost = $options['cost'];

        if ($algorithm === 'SHA512')
        {
            $maxCost = HASH_COST_SHA512_MAX;
            $costIncrement = 50000;

            if (!is_int($cost))
            {
                $cost = HASH_COST_SHA512_DEFAULT;
            }
            if ($cost < HASH_COST_SHA512_MIN)
            {
                $cost = HASH_COST_SHA512_MIN;
            }
        }
        else
        {
            $maxCost = HASH_COST_BCRYPT_MAX;
            $costIncrement = 1;

            if (!is_int($cost))
            {
                $cost = HASH_COST_BCRYPT_DEFAULT;
            }
            if ($cost < HASH_COST_BCRYPT_MIN)
            {
                $cost = HASH_COST_BCRYPT_MIN;
            }
        }

        $time = 0;
        $results = array();

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
