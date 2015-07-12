<?php

// provide forward compatibility with the password_* functions that ship with PHP 5.5
require_once(SERVER_PATH.'/adm_program/libs/password_compat/password.php');
// old phpass password hashing lib for backward compatibility
require_once(SERVER_PATH.'/adm_program/libs/phpass/passwordhash.php');

/**
 * Class PasswordHashing
 */
class PasswordHashing
{
    /**
     * @param  string       $hash
     * @return array|string
     */
    public static function info($hash)
    {
        if (strlen($hash) === 60)
        {
            if (substr($hash, 0, 4) === '$2y$')
            {
                return password_get_info($hash);
            }
            elseif (substr($hash, 0, 4) === '$2a$' || substr($hash, 0, 4) === '$2x$')
            {
                return 'CRYPT_BLOWFISH_INSECURE';
            }
        }
        elseif (strlen($hash) === 34)
        {
            if (substr($hash, 0, 3) === '$P$' || substr($hash, 0, 3) === '$H$')
            {
                return 'PRIVATE/PORTABLE_HASH';
            }
        }
        elseif (strlen($hash) === 20)
        {
            if (substr($hash, 0, 1) === '_')
            {
                return 'CRYPT_EXT_DES';
            }
        }
        elseif (strlen($hash) === 32)
        {
            return 'MD5';
        }
        elseif (substr($hash, 0, 1) === '$')
        {
            return $hash;
        }

        return 'UNKNOWN';
    }

    /**
     * @param  string       $password
     * @param  int          $algorithm
     * @param  array        $options
     * @return string|false
     */
    public static function hash($password, $algorithm = PASSWORD_DEFAULT, $options = array())
    {
        return password_hash($password, $algorithm, $options);
    }

    /**
     * @param  string $password
     * @param  string $hash
     * @return bool
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
     * @param  string $hash
     * @param  int    $algorithm
     * @param  array  $options
     * @return bool
     */
    public static function needsRehash($hash, $algorithm = PASSWORD_DEFAULT, $options = array())
    {
        return password_needs_rehash($hash, $algorithm, $options);
    }

    /**
     * @param  int    $length
     * @return string
     */
    public static function genRandomPassword($length)
    {
        return substr(bin2hex(openssl_random_pseudo_bytes(ceil($length/2))), 0, $length);
    }

    /**
     * @param  string $password
     * @return array
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
}

?>
