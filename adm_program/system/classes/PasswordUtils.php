<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class provides static functions for different tasks for passwords and hashing
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
final class PasswordUtils
{
    const HASH_ALGORITHM_DEFAULT = 'DEFAULT';
    const HASH_ALGORITHM_BCRYPT = 'BCRYPT';
    const HASH_ALGORITHM_SHA512 = 'SHA512';

    const HASH_COST_BCRYPT_DEFAULT = 12;
    const HASH_COST_BCRYPT_MIN = 10;
    const HASH_COST_BCRYPT_MAX = 31;
    const HASH_COST_BCRYPT_INCREMENT = 1;
    const HASH_COST_SHA512_DEFAULT = 100000;
    const HASH_COST_SHA512_MIN = 50000;
    const HASH_COST_SHA512_MAX = 999999999;
    const HASH_COST_SHA512_INCREMENT = 50000;

    const HASH_LENGTH_BCRYPT = 60;
    const HASH_LENGTH_SHA512 = 110;
    const HASH_LENGTH_PORTABLE = 34;
    const HASH_LENGTH_MD5 = 32;

    const HASH_INDICATOR_BCRYPT = '$2y$';
    const HASH_INDICATOR_SHA512 = '$6$';
    const HASH_INDICATOR_PORTABLE = '$P$';

    /**
     * Run a benchmark to get the best fitting cost value. The cost value can vary from 4 to 31.
     * @param float               $maxTime   The maximum time the hashing process should take in seconds
     * @param string              $password  The password to test
     * @param string              $algorithm The algorithm to test
     * @param array<string,mixed> $options   The options to test
     * @return array<string,int|float> Returns an array with the maximum tested cost with the required time
     */
    public static function costBenchmark($maxTime = 0.35, $password = 'password', $algorithm = self::HASH_ALGORITHM_DEFAULT, array $options = array('cost' => null))
    {
        global $gLogger;

        $options['cost'] = self::getPreparedCost($algorithm, $options);

        if ($algorithm === self::HASH_ALGORITHM_SHA512)
        {
            $maxCost       = self::HASH_COST_SHA512_MAX;
            $costIncrement = self::HASH_COST_SHA512_INCREMENT;
        }
        else
        {
            $maxCost       = self::HASH_COST_BCRYPT_MAX;
            $costIncrement = self::HASH_COST_BCRYPT_INCREMENT;
        }

        $results = null;

        // loop through the cost value until the needed hashing time reaches the maximum set time
        do
        {
            $start = microtime(true);
            self::hash($password, $algorithm, $options);
            $end = microtime(true);

            $time = $end - $start;

            if ($results === null || $time <= $maxTime)
            {
                $results = array('cost' => $options['cost'], 'time' => $time);
            }
            $options['cost'] += $costIncrement;
        }
        while ($time <= $maxTime && $options['cost'] <= $maxCost);

        $gLogger->notice('Benchmark: Password-hashing results.', $results);

        return $results;
    }

    /**
     * Hash the given password with the given options. The default algorithm uses the password_* methods,
     * otherwise the builtin helper for SHA-512 crypt hashes from the operating system. Minimum cost is 10.
     * @param string              $password  The password string
     * @param string              $algorithm The hash-algorithm method. Possible values are 'DEFAULT', 'BCRYPT' or 'SHA512'.
     * @param array<string,mixed> $options   The hash-options array
     * @return string|false Returns the hashed password or false if an error occurs
     */
    public static function hash($password, $algorithm = self::HASH_ALGORITHM_DEFAULT, array $options = array())
    {
        $options['cost'] = self::getPreparedCost($algorithm, $options);

        if ($algorithm === self::HASH_ALGORITHM_SHA512)
        {
            $salt = self::genRandomPassword(8, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ./');
            return crypt($password, '$6$rounds=' . $options['cost'] . '$' . $salt . '$');
        }
        elseif ($algorithm === self::HASH_ALGORITHM_BCRYPT)
        {
            $algorithmPhpConstant = PASSWORD_BCRYPT;
        }
        else
        {
            $algorithmPhpConstant = PASSWORD_DEFAULT;
        }

        return password_hash($password, $algorithmPhpConstant, $options);
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
     * Prepares the cost value
     * @param string              $algorithm The hash-algorithm method. Possible values are 'DEFAULT', 'BCRYPT' or 'SHA512'.
     * @param array<string,mixed> $options   The hash-options array
     * @return int
     */
    private static function getPreparedCost($algorithm, array $options)
    {
        if ($algorithm === self::HASH_ALGORITHM_SHA512)
        {
            $defaultCost = self::HASH_COST_SHA512_DEFAULT;
            $minCost     = self::HASH_COST_SHA512_MIN;
        }
        else
        {
            $defaultCost = self::HASH_COST_BCRYPT_DEFAULT;
            $minCost     = self::HASH_COST_BCRYPT_MIN;
        }

        if (!array_key_exists('cost', $options) || !is_int($options['cost']))
        {
            $options['cost'] = $defaultCost;
        }
        elseif ($options['cost'] < $minCost) // https://paragonie.com/blog/2016/02/how-safely-store-password-in-2016
        {
            $options['cost'] = $minCost;
        }

        return $options['cost'];
    }

    /**
     * Provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
     * @param string $hash The hash you want the get infos about
     * @return string|array<string,mixed> Returns an array or string with infos about the given hash
     */
    public static function hashInfo($hash)
    {
        $hashLength = strlen($hash);
        if ($hashLength === self::HASH_LENGTH_BCRYPT && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_BCRYPT))
        {
            return password_get_info($hash);
        }
        elseif ($hashLength >= self::HASH_LENGTH_SHA512 && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_SHA512))
        {
            return 'SHA512';
        }
        elseif ($hashLength === self::HASH_LENGTH_PORTABLE && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_PORTABLE))
        {
            return 'PRIVATE/PORTABLE_HASH';
        }
        // MD5 Hashes are 32 chars long and consists out of HEX values (digits and a-f)
        elseif ($hashLength === self::HASH_LENGTH_MD5 && preg_match('/^[\dA-Fa-f]+$/', $hash))
        {
            return 'MD5';
        }

        return 'UNKNOWN';
    }

    /**
     * Checks if the given hash is generated from the given options. The default algorithm uses the
     * password_* methods, otherwise the builtin helper for SHA-512 crypt hashes from the operating system.
     * @param string              $hash      The hash string that should checked
     * @param string              $algorithm The hash-algorithm the hash should match to
     * @param array<string,mixed> $options   The hash-options the hash should match to
     * @return bool Returns false if the hash match the given options and false if not
     */
    public static function needsRehash($hash, $algorithm = self::HASH_ALGORITHM_DEFAULT, array $options = array())
    {
        $options['cost'] = self::getPreparedCost($algorithm, $options);
        $hashLength = strlen($hash);

        if ($algorithm === self::HASH_ALGORITHM_SHA512 && $hashLength >= self::HASH_LENGTH_SHA512 && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_SHA512))
        {
            $hashParts = explode('$', $hash);
            $cost = (int) substr($hashParts[2], 7);

            return $cost !== $options['cost'];
        }
        elseif ($algorithm === self::HASH_ALGORITHM_BCRYPT && $hashLength === self::HASH_LENGTH_BCRYPT && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_BCRYPT))
        {
            $algorithmPhpConstant = PASSWORD_BCRYPT;
        }
        elseif ($algorithm === self::HASH_ALGORITHM_DEFAULT)
        {
            $algorithmPhpConstant = PASSWORD_DEFAULT;
        }
        else
        {
            return true; // TODO
        }

        return password_needs_rehash($hash, $algorithmPhpConstant, $options);
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
        if (preg_match('/\W/', $password) === 1 || StringUtils::strContains($password, '_')) // Note: \W = ![0-9a-zA-Z_]
        {
            $passwordInfo['symbol'] = true;
        }

        return $passwordInfo;
    }

    /**
     * Calculates the strength of a given password from 0-4.
     * @param string            $password The password to check
     * @param array<int,string> $userData An array of strings for dictionary attacks
     * @return int Returns the score of the password
     */
    public static function passwordStrength($password, array $userData = array())
    {
        $zxcvbn = new \ZxcvbnPhp\Zxcvbn();
        $strength = $zxcvbn->passwordStrength($password, $userData);

        return $strength['score'];
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
        if ($hashLength === self::HASH_LENGTH_BCRYPT && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_BCRYPT))
        {
            return password_verify($password, $hash);
        }
        elseif ($hashLength >= self::HASH_LENGTH_SHA512 && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_SHA512))
        {
            $passwordHash = crypt($password, $hash);
            return hash_equals($passwordHash, $hash);
        }
        elseif ($hashLength === self::HASH_LENGTH_PORTABLE && StringUtils::strStartsWith($hash, self::HASH_INDICATOR_PORTABLE))
        {
            $passwordHasher = new PasswordHash(9, true);
            return $passwordHasher->CheckPassword($password, $hash);
        }
        // MD5 Hashes are 32 chars long and consists out of HEX values (digits and a-f)
        elseif ($hashLength === self::HASH_LENGTH_MD5 && preg_match('/^[\dA-Fa-f]+$/', $hash))
        {
            return md5($password) === $hash;
        }

        return false;
    }
}
