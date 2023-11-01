<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class SecurityUtils
{
    /**
     * Encodes all HTML special characters
     * If $encodeAll is false, this method is only secure if encoding is not UTF-7
     * @param string|array<mixed,string> $input     The input string
     * @param bool   $encodeAll Set true too encode really all HTML special characters
     * @param string $encoding  Define character encoding to use
     * @return string|array<mixed,string> Encoded string
     */
    public static function encodeHTML($input, bool $encodeAll = false, string $encoding = 'UTF-8')
    {
        if (is_array($input)) {
            // call function for every array element
            if ($encodeAll) {
                // Encodes: all special HTML characters
                function myHtmlentities($value) {
                    if (is_array($value)) {
                        return array_map('myHtmlentities', $value);
                    }
                    return htmlentities($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $input = array_map('myHtmlentities', $input);
            } else {
                // Encodes: &, ", ', <, >
                function myHtmlspecialchars($value) {
                    if (is_array($value)) {
                        return array_map('myHtmlspecialchars', $value);
                    }
                    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $input = array_map('myHtmlspecialchars', $input);
            }
        } else {
            if ($encodeAll) {
                // Encodes: all special HTML characters
                $input = htmlentities($input, ENT_QUOTES | ENT_HTML5, $encoding);
            } else {
                // Encodes: &, ", ', <, >
                $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, $encoding);
            }
        }

        return $input;
    }

    /**
     * Build URL with query-string and anker and optional encodes all HTML special characters
     * @param string              $path   The URL path
     * @param array<string,mixed> $params The query-params
     * @param string              $anchor The Url-anker
     * @param bool                $encode Set true to also encode all HTML special characters
     * @return string Encoded URL
     */
    public static function encodeUrl($path, array $params = array(), $anchor = '', $encode = false)
    {
        $paramsText = '';
        if (count($params) > 0) {
            $paramsText = '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $anchorText = '';
        if ($anchor !== '') {
            $anchorText = '#' . rawurlencode($anchor);
        }

        $url = $path . $paramsText . $anchorText;

        if ($encode) {
            return self::encodeHTML($url);
        }

        return $url;
    }

    /**
     * Generate an insecure pseudo-random integer
     * @param int               $min                     The min of the range (inclusive)
     * @param int               $max                     The max of the range (inclusive)
     * @param bool              $exceptionOnInsecurePRNG Could be set to true to get an Exception if no secure PRN could be generated.
     * @param \Error|\Exception $exception               The thrown Error or Exception object.
     * @param string            $exceptionMessage        The Admidio Exception-Message.
     * @throws AdmException SYS_GEN_RANDOM_ERROR, SYS_GEN_RANDOM_EXCEPTION
     * @return int Returns an insecure pseudo-random integer
     */
    private static function getRandomIntFallback($min, $max, $exceptionOnInsecurePRNG, $exception, $exceptionMessage)
    {
        global $gLogger;

        $gLogger->warning('SECURITY: Could not generate secure pseudo-random number!', array('code' => $exception->getCode(), 'message' => $exception->getMessage()));

        if ($exceptionOnInsecurePRNG) {
            throw new AdmException($exceptionMessage, array($exception->getCode(), $exception->getMessage()));
        }

        // as a fallback we use the mt_rand method
        return mt_rand($min, $max);
    }

    /**
     * Generate a cryptographically secure pseudo-random integer
     * @param int  $min                     The min of the range (inclusive)
     * @param int  $max                     The max of the range (inclusive)
     * @param bool $exceptionOnInsecurePRNG Could be set to true to get an Exception if no secure PRN could be generated.
     * @throws AdmException SYS_GEN_RANDOM_ERROR, SYS_GEN_RANDOM_EXCEPTION
     * @return int Returns a cryptographically secure pseudo-random integer
     */
    public static function getRandomInt($min, $max, $exceptionOnInsecurePRNG = false)
    {
        try {
            $int = \random_int($min, $max);
        } catch (\Error $e) {
            $int = self::getRandomIntFallback($min, $max, $exceptionOnInsecurePRNG, $e, 'SYS_GEN_RANDOM_ERROR');
        } catch (\Exception $e) {
            $int = self::getRandomIntFallback($min, $max, $exceptionOnInsecurePRNG, $e, 'SYS_GEN_RANDOM_EXCEPTION');
        }

        return $int;
    }

    /**
     * Generate a cryptographically secure pseudo-random string
     * @param int    $length  The length of the generated string (default = 16)
     * @param string $charset A string of all possible characters to choose from (default = [0-9a-zA-z])
     * @throws \RuntimeException Min-length is 4.
     * @throws \UnexpectedValueException Charset contains duplicate chars.
     * @throws \UnexpectedValueException Charset must contain at least 2 unique chars.
     * @throws AdmException SYS_GEN_RANDOM_ERROR, SYS_GEN_RANDOM_EXCEPTION
     * @return string Returns a cryptographically secure pseudo-random string
     * @see https://paragonie.com/b/JvICXzh_jhLyt4y3
     */
    public static function getRandomString($length = 16, $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if ($length < 4) {
            throw new \RuntimeException('Min-length is 4.');
        }

        $charsetLength = strlen($charset);

        // Check for duplicate chars in charset
        if ($charsetLength !== strlen(implode('', array_unique(str_split($charset))))) {
            throw new \UnexpectedValueException('Charset contains duplicate chars.');
        }

        // Check for a minimum of 2 unique chars
        if ($charsetLength < 2) {
            throw new \UnexpectedValueException('Charset must contain at least 2 unique chars.');
        }

        $randomString = '';
        $charsetMaxIndex = $charsetLength - 1;
        for ($i = 0; $i < $length; ++$i) {
            $randomInt = self::getRandomInt(0, $charsetMaxIndex);
            $randomString .= $charset[$randomInt];
        }

        return $randomString;
    }

    /**
     * Method will check the CSRF token from the parameter against the CSRF token of the
     * current session. If these tokens doesn't match an exception will be thrown.
     * @param string $csrfToken The CSRF token that should be validated.
     * @throws AdmException Tokens doesn't match.
     */
    public static function validateCsrfToken($csrfToken)
    {
        global $gCurrentSession;

        if ($csrfToken !== $gCurrentSession->getCsrfToken()) {
            throw new AdmException('Invalid or missing CSRF token!');
        }
    }
}
