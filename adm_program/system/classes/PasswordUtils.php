<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class provides static functions for different tasks for passwords and hashing
 *
 * Functions:
 * hash()               hash the given password with the given options
 * verify()             verify if the given password belongs to the given hash
 * needsRehash()        checks if the given hash is generated from the given options
 * passwordInfo()       provides infos about the given password (length, number, lowerCase, upperCase, symbol)
 * hashInfo()           provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
 * passwordStrength()   shows the strength of the given password
 * costBenchmark()      run a benchmark to get the best fitting cost value
 */
final class PasswordUtils
{
    public const HASH_ALGORITHM_DEFAULT = 'DEFAULT';
    public const HASH_ALGORITHM_ARGON2ID = 'ARGON2ID';
    public const HASH_ALGORITHM_ARGON2I = 'ARGON2I';
    public const HASH_ALGORITHM_BCRYPT = 'BCRYPT';
    public const HASH_ALGORITHM_SHA512 = 'SHA512';

    public const HASH_COST_BCRYPT_DEFAULT = PASSWORD_BCRYPT_DEFAULT_COST;
    public const HASH_COST_BCRYPT_MIN = 8;
    public const HASH_COST_BCRYPT_MAX = 31;
    public const HASH_COST_SHA512_DEFAULT = 100000;
    public const HASH_COST_SHA512_MIN = 25000;
    public const HASH_COST_SHA512_MAX = 999999999;

    public const HASH_INDICATOR_ARGON2ID = '$argon2id$';
    public const HASH_INDICATOR_ARGON2I = '$argon2i$';
    public const HASH_INDICATOR_BCRYPT = '$2y$';
    public const HASH_INDICATOR_SHA512 = '$6$';
    public const HASH_INDICATOR_PORTABLE = '$P$';

    /**
     * Run a benchmark to get the best fitting cost value.
     * @param string            $algorithm The algorithm to test
     * @param array<string,int> $options   The options to test
     * @param float             $maxTime   The maximum time the hashing process should take in seconds
     * @param string            $password  The password to test
     * @return array<string,int|float|array<string,int>> Returns an array with the maximum tested cost with the required time
     */
    public static function costBenchmark($algorithm = self::HASH_ALGORITHM_DEFAULT, array $options = array(), $maxTime = 0.2, $password = '123456abcdef_-#:')
    {
        global $gLogger;

        $options = self::getPreparedOptions($algorithm, $options);

        if ($algorithm === self::HASH_ALGORITHM_SHA512) {
            $maxCost = self::HASH_COST_SHA512_MAX;
        } elseif ($algorithm === self::HASH_ALGORITHM_BCRYPT || ($algorithm === self::HASH_ALGORITHM_DEFAULT && PASSWORD_DEFAULT === PASSWORD_BCRYPT)) {
            $maxCost = self::HASH_COST_BCRYPT_MAX;
        } else {
            return array('options' => $options, 'time' => null);
        }

        $result = null;

        // increase the cost value until the hashing time reaches the maximum configured time
        do {
            $start = microtime(true);
            self::hash($password, $algorithm, $options);
            $end = microtime(true);

            $time = $end - $start;

            if ($result === null || $time <= $maxTime) {
                $result = array('options' => $options, 'time' => $time);
            }
            if ($algorithm === self::HASH_ALGORITHM_SHA512) {
                $options['cost'] *= 2;
            } else {
                $options['cost'] += 1;
            }
        } while ($time <= $maxTime && $options['cost'] <= $maxCost);

        $gLogger->notice('Benchmark: Password-hashing result.', $result);

        return $result;
    }

    /**
     * Hash the given password with the given options. The default algorithm uses the password_* methods,
     * otherwise the builtin helper for SHA-512 crypt hashes from the operating system. Minimum cost is 10.
     * @param string            $password  The password string
     * @param string            $algorithm The hash-algorithm method. Possible values are 'DEFAULT', 'ARGON2ID', 'ARGON2I', 'BCRYPT' or 'SHA512'.
     * @param array<string,int> $options   The hash-options array
     * @return string|false Returns the hashed password or false if an error occurs
     */
    public static function hash($password, $algorithm = self::HASH_ALGORITHM_DEFAULT, array $options = array())
    {
        $options = self::getPreparedOptions($algorithm, $options);

        switch ($algorithm) {
            case self::HASH_ALGORITHM_DEFAULT:
                $algorithmPhpConstant = PASSWORD_DEFAULT;
                break;
            case self::HASH_ALGORITHM_ARGON2ID:
                $algorithmPhpConstant = PASSWORD_ARGON2ID;
                break;
            case self::HASH_ALGORITHM_ARGON2I:
                $algorithmPhpConstant = PASSWORD_ARGON2I;
                break;
            case self::HASH_ALGORITHM_BCRYPT:
                $algorithmPhpConstant = PASSWORD_BCRYPT;
                break;
            case self::HASH_ALGORITHM_DEFAULT:
                $algorithmPhpConstant = PASSWORD_DEFAULT;
                break;
            case self::HASH_ALGORITHM_SHA512:
                $salt = SecurityUtils::getRandomString(8, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ./');
                return crypt($password, self::HASH_INDICATOR_SHA512 . 'rounds=' . $options['cost'] . '$' . $salt . '$');
            default:
                $algorithmPhpConstant = PASSWORD_DEFAULT;
        }

        return password_hash($password, $algorithmPhpConstant, $options);
    }

    /**
     * Prepares the options values
     * @param string            $algorithm The hash-algorithm method. Possible values are 'DEFAULT', 'ARGON2ID', 'ARGON2I', 'BCRYPT' or 'SHA512'.
     * @param array<string,int> $options   The hash-options array
     * @return array<string,int>
     */
    private static function getPreparedOptions($algorithm, array $options)
    {
        if ($algorithm === self::HASH_ALGORITHM_SHA512) {
            $defaultCost = self::HASH_COST_SHA512_DEFAULT;
            $minCost     = self::HASH_COST_SHA512_MIN;
        } elseif ($algorithm === self::HASH_ALGORITHM_BCRYPT || ($algorithm === self::HASH_ALGORITHM_DEFAULT && PASSWORD_DEFAULT === PASSWORD_BCRYPT)) {
            $defaultCost = self::HASH_COST_BCRYPT_DEFAULT;
            $minCost     = self::HASH_COST_BCRYPT_MIN;
        } else {
            $options['cost'] = null;
            return $options;
        }

        if (!array_key_exists('cost', $options) || !is_int($options['cost'])) {
            global $gSettingsManager;
            if (isset($gSettingsManager) && $gSettingsManager->has('system_hashing_cost')) {
                $options['cost'] = $gSettingsManager->getInt('system_hashing_cost');
            } else {
                $options['cost'] = $defaultCost;
            }
        } elseif ($options['cost'] < $minCost) { // https://paragonie.com/blog/2016/02/how-safely-store-password-in-2016
            $options['cost'] = $minCost;
        }

        return $options;
    }

    /**
     * Provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
     * @param string $hash The hash you want the get infos about
     * @return string|array<string,mixed> Returns an array or string with infos about the given hash
     */
    public static function hashInfo($hash)
    {
        if (str_starts_with($hash, self::HASH_INDICATOR_ARGON2ID) || str_starts_with($hash, self::HASH_INDICATOR_ARGON2I) || str_starts_with($hash, self::HASH_INDICATOR_BCRYPT)) {
            return password_get_info($hash);
        } elseif (str_starts_with($hash, self::HASH_INDICATOR_SHA512)) {
            return 'SHA512';
        } elseif (str_starts_with($hash, self::HASH_INDICATOR_PORTABLE)) {
            return 'PRIVATE/PORTABLE_HASH';
        }
        // MD5 Hashes are 32 chars long and consists out of HEX values (digits and a-f)
        elseif (preg_match('/^[\dA-Fa-f]{32}$/', $hash)) {
            return 'MD5';
        }

        return 'UNKNOWN';
    }

    /**
     * Checks if the given hash is generated from the given options. The default algorithm uses the
     * password_* methods, otherwise the builtin helper for SHA-512 crypt hashes from the operating system.
     * @param string            $hash      The hash string that should checked
     * @param string            $algorithm The hash-algorithm the hash should match to. Possible values are 'DEFAULT', 'ARGON2ID', 'ARGON2I', 'BCRYPT' or 'SHA512'.
     * @param array<string,int> $options   The hash-options the hash should match to
     * @return bool Returns false if the hash match the given options and false if not
     */
    public static function needsRehash($hash, $algorithm = self::HASH_ALGORITHM_DEFAULT, array $options = array())
    {
        $options = self::getPreparedOptions($algorithm, $options);

        if ($algorithm === self::HASH_ALGORITHM_DEFAULT) {
            $algorithmPhpConstant = PASSWORD_DEFAULT;
        } elseif ($algorithm === self::HASH_ALGORITHM_ARGON2ID && str_starts_with($hash, self::HASH_INDICATOR_ARGON2ID)) {
            $algorithmPhpConstant = PASSWORD_ARGON2ID;
        } elseif ($algorithm === self::HASH_ALGORITHM_ARGON2I && str_starts_with($hash, self::HASH_INDICATOR_ARGON2I)) {
            $algorithmPhpConstant = PASSWORD_ARGON2I;
        } elseif ($algorithm === self::HASH_ALGORITHM_BCRYPT && str_starts_with($hash, self::HASH_INDICATOR_BCRYPT)) {
            $algorithmPhpConstant = PASSWORD_BCRYPT;
        } elseif ($algorithm === self::HASH_ALGORITHM_SHA512 && str_starts_with($hash, self::HASH_INDICATOR_SHA512)) {
            $hashParts = explode('$', $hash);
            $cost = (int) substr($hashParts[2], 7);

            return $cost !== $options['cost'];
        } else {
            return true;
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

        if (preg_match('/\d/', $password) === 1) {
            $passwordInfo['number'] = true;
        }
        if (preg_match('/[a-z]/', $password) === 1) {
            $passwordInfo['lowerCase'] = true;
        }
        if (preg_match('/[A-Z]/', $password) === 1) {
            $passwordInfo['upperCase'] = true;
        }
        if (preg_match('/\W/', $password) === 1 || str_contains($password, '_')) { // Note: \W = ![\da-zA-Z_]
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
        if (str_starts_with($hash, self::HASH_INDICATOR_ARGON2ID) || str_starts_with($hash, self::HASH_INDICATOR_ARGON2I) || str_starts_with($hash, self::HASH_INDICATOR_BCRYPT)) {
            return password_verify($password, $hash);
        } elseif (str_starts_with($hash, self::HASH_INDICATOR_SHA512)) {
            $passwordHash = crypt($password, $hash);
            return hash_equals($passwordHash, $hash);
        }
        // MD5 Hashes are 32 chars long and consists out of HEX values (digits and a-f)
        elseif (preg_match('/^[\dA-Fa-f]{32}$/', $hash)) {
            return md5($password) === $hash;
        }

        return false;
    }
}
