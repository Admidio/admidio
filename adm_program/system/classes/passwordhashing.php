<?php

// provide forward compatibility with the password_* functions that ship with PHP 5.5
require_once(SERVER_PATH.'/adm_program/libs/password_compat/password.php');
// old phpass password hashing lib for backward compatibility
require_once(SERVER_PATH.'/adm_program/libs/phpass/passwordhash.php');

/**
 * Class PasswordHashing
 *
 * This class provides static functions for different tasks for passwords and hashing
 * It used the "password_compat" lib to provide forward compatibility with the password_* functions that ship with PHP 5.5
 * It used the "phpass" lib to provide backward compatibility to the old password hashing way
 *
 * Functions:
 * hash()               hash the given password with the given options
 * verify()             verify if the given password belongs to the given hash
 * needsRehash()        checks if the given hash is generated from the given options
 * genRandomPassword()  generate a cryptographically strong random password
 * passwordInfo()       provides infos about the given password (length, number, lowerCase, upperCase, symbol)
 * hashInfo()           provides infos about the given hash (Algorithm & Options, PRIVATE/PORTABLE_HASH, MD5, UNKNOWN)
 * costBenchmark()      run a benchmark to get the best fitting cost value
 */
class PasswordHashing
{
    /**
     * Hash the given password with the given options
     * @param  string       $password  The password string
     * @param  int          $algorithm The hash-algorithm constant
     * @param  array        $options   The hash-options array
     * @return string|false Returns the hashed password or false if an error occurs
     */
    public static function hash($password, $algorithm = PASSWORD_DEFAULT, $options = array())
    {
        return password_hash($password, $algorithm, $options);
    }

    /**
     * Verify if the given password belongs to the given hash
     * @param  string $password The password string to check
     * @param  string $hash     The hash string to check
     * @return bool   Returns true if the password belongs to the hash and false if not
     */
    public static function verify($password, $hash)
    {
        if (strlen($hash) === 60)
        {
            if (substr($hash, 0, 4) === '$2y$')
            {
                return password_verify($password, $hash);
            }
            elseif (substr($hash, 0, 4) === '$2a$' || substr($hash, 0, 4) === '$2x$')
            {
                $hashParts = explode('$', $hash);
                $passwordHasher = new PasswordHash($hashParts[1], false);
                return $passwordHasher->CheckPassword($password, $hash);
            }
        }
        elseif (strlen($hash) === 34)
        {
            if (substr($hash, 0, 3) === '$P$' || substr($hash, 0, 3) === '$H$')
            {
                $passwordHasher = new PasswordHash(9, true);
                return $passwordHasher->CheckPassword($password, $hash);
            }
        }
        elseif (strlen($hash) === 20)
        {
            if (substr($hash, 0, 1) === '_')
            {
                $passwordHasher = new PasswordHash(9, false);
                return $passwordHasher->CheckPassword($password, $hash);
            }
        }
        elseif (strlen($hash) === 32)
        {
            return md5($password) === $hash;
        }

        return false;
    }

    /**
     * Checks if the given hash is generated from the given options
     * @param  string $hash      The hash string that should checked
     * @param  int    $algorithm The hash-algorithm the hash should match to
     * @param  array  $options   The hash-options the hash should match to
     * @return bool   Returns false if the hash match the given options and false if not
     */
    public static function needsRehash($hash, $algorithm = PASSWORD_DEFAULT, $options = array())
    {
        return password_needs_rehash($hash, $algorithm, $options);
    }

    /**
     * Generate a cryptographically strong random password
     * @param  int    $length The length of the generated password
     * @return string Returns a cryptographically strong random password string
     */
    public static function genRandomPassword($length)
    {
        return substr(bin2hex(openssl_random_pseudo_bytes(ceil($length/2))), 0, $length);
    }

    /**
     * Provides infos about the given password (length, number, lowerCase, upperCase, symbol)
     * @param  string $password The password you want the get infos about
     * @return array Returns an array with infos about the given password
     */
    public static function passwordInfo($password)
    {
        $passwordInfo = array(
            'length' => 0,
            'number' => false,
            'lowerCase' => false,
            'upperCase' => false,
            'symbol' => false,
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
     * @param  string       $hash The hash you want the get infos about
     * @return array|string Returns an array or string with infos about the given hash
     */
    public static function hashInfo($hash)
    {
        if (strlen($hash) === 60 && substr($hash, 0, 4) === '$2y$')
        {
            return password_get_info($hash);
        }
        elseif (strlen($hash) === 34 && substr($hash, 0, 3) === '$P$')
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
     * Run a benchmark to get the best fitting cost value
     * @param  float  $maxTime   The maximum time the hashing process should take in seconds
     * @param  string $password  The password to test
     * @param  int    $algorithm The algorithm to test
     * @param  array  $options   The options to test
     * @return array  Returns an array with the tested costs with their required time
     */
    public static function costBenchmark($maxTime = 0.5, $password = 'password', $algorithm = PASSWORD_DEFAULT, $options = array('cost' => 8))
    {
        $time = 0;
        $results = array();

        while ($time <= $maxTime) {
            $options['cost']++;

            $start = microtime(true);

            PasswordHashing::hash($password, $algorithm, $options);

            $end = microtime(true);

            $time = $end - $start;

            $results[] = array('cost' => $options['cost'], 'time' => $time);
        }

        array_pop($results);

        return $results;
    }
}

?>
