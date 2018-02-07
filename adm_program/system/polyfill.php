<?php
/**
 ***********************************************************************************************
 * Includes the different polyfills
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'polyfill.php')
{
    exit('This page may not be called directly!');
}

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
        return $result === 0;
    }
}

// provide forward compatibility with the http_response_code function that ship with PHP 5.4
if (!function_exists('http_response_code')) {
    /**
     * @param int|null $code
     * @return int
     */
    function http_response_code($code = null)
    {
        static $defaultCode = 200;

        if ($code !== null)
        {
            switch ($code)
            {
                case 100: $text = 'Continue'; break;                        // RFC2616
                case 101: $text = 'Switching Protocols'; break;             // RFC2616
                case 102: $text = 'Processing'; break;                      // RFC2518
                case 200: $text = 'OK'; break;                              // RFC2616
                case 201: $text = 'Created'; break;                         // RFC2616
                case 202: $text = 'Accepted'; break;                        // RFC2616
                case 203: $text = 'Non-Authoritative Information'; break;   // RFC2616
                case 204: $text = 'No Content'; break;                      // RFC2616
                case 205: $text = 'Reset Content'; break;                   // RFC2616
                case 206: $text = 'Partial Content'; break;                 // RFC2616
                case 207: $text = 'Multi-Status'; break;                    // RFC4918
                case 208: $text = 'Already Reported'; break;                // RFC5842
                case 226: $text = 'IM Used'; break;                         // RFC3229
                case 300: $text = 'Multiple Choices'; break;                // RFC2616
                case 301: $text = 'Moved Permanently'; break;               // RFC2616
                case 302: $text = 'Found'; break;                           // RFC2616
                case 303: $text = 'See Other'; break;                       // RFC2616
                case 304: $text = 'Not Modified'; break;                    // RFC2616
                case 305: $text = 'Use Proxy'; break;                       // RFC2616
                case 306: $text = 'Reserved'; break;                        // RFC2616
                case 307: $text = 'Temporary Redirect'; break;              // RFC2616
                case 308: $text = 'Permanent Redirect'; break;              // RFC-reschke-http-status-308-07
                case 400: $text = 'Bad Request'; break;                     // RFC2616
                case 401: $text = 'Unauthorized'; break;                    // RFC2616
                case 402: $text = 'Payment Required'; break;                // RFC2616
                case 403: $text = 'Forbidden'; break;                       // RFC2616
                case 404: $text = 'Not Found'; break;                       // RFC2616
                case 405: $text = 'Method Not Allowed'; break;              // RFC2616
                case 406: $text = 'Not Acceptable'; break;                  // RFC2616
                case 407: $text = 'Proxy Authentication Required'; break;   // RFC2616
                case 408: $text = 'Request Timeout'; break;                 // RFC2616
                case 409: $text = 'Conflict'; break;                        // RFC2616
                case 410: $text = 'Gone'; break;                            // RFC2616
                case 411: $text = 'Length Required'; break;                 // RFC2616
                case 412: $text = 'Precondition Failed'; break;             // RFC2616
                case 413: $text = 'Request Entity Too Large'; break;        // RFC2616
                case 414: $text = 'Request-URI Too Long'; break;            // RFC2616
                case 415: $text = 'Unsupported Media Type'; break;          // RFC2616
                case 416: $text = 'Requested Range Not Satisfiable'; break; // RFC2616
                case 417: $text = 'Expectation Failed'; break;              // RFC2616
                case 422: $text = 'Unprocessable Entity'; break;            // RFC4918
                case 423: $text = 'Locked'; break;                          // RFC4918
                case 424: $text = 'Failed Dependency'; break;               // RFC4918
                case 426: $text = 'Upgrade Required'; break;                // RFC2817
                case 428: $text = 'Precondition Required'; break;           // RFC6585
                case 429: $text = 'Too Many Requests'; break;               // RFC6585
                case 431: $text = 'Request Header Fields Too Large'; break; // RFC6585
                case 451: $text = 'Unavailable For Legal Reasons'; break;   // RFC7725
                case 500: $text = 'Internal Server Error'; break;           // RFC2616
                case 501: $text = 'Not Implemented'; break;                 // RFC2616
                case 502: $text = 'Bad Gateway'; break;                     // RFC2616
                case 503: $text = 'Service Unavailable'; break;             // RFC2616
                case 504: $text = 'Gateway Timeout'; break;                 // RFC2616
                case 505: $text = 'HTTP Version Not Supported'; break;      // RFC2616
                case 506: $text = 'Variant Also Negotiates'; break;         // RFC2295
                case 507: $text = 'Insufficient Storage'; break;            // RFC4918
                case 508: $text = 'Loop Detected'; break;                   // RFC5842
                case 510: $text = 'Not Extended'; break;                    // RFC2774
                case 511: $text = 'Network Authentication Required'; break; // RFC6585
                default:
                    $code = 500;
                    $text = 'Internal Server Error';
            }

            $defaultCode = $code;
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
        }

        return $defaultCode;
    }
}
