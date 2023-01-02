<?php
/**
 ***********************************************************************************************
 * backupDB() - Support Functions
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Based on backupDB Version 1.2.7-201104261502
 * by James Heinrich <info@silisoftware.com>
 * available at http://www.silisoftware.com
 *****************************************************************************/

/////////////////////////////////////////////////////////////////////
///////////////////       SUPPORT FUNCTIONS       ///////////////////
/////////////////////////////////////////////////////////////////////

if (!function_exists('getmicrotime')) {
    /**
     * @return float
     */
    function getmicrotime()
    {
        list($usec, $sec) = explode(' ', microtime());
        return (float) $usec + (float) $sec;
    }
}

// begin: (from phpthumb.functions.php)
/**
 * @param string $function
 * @return bool
 */
function FunctionIsDisabled($function)
{
    global $gLogger;

    static $DisabledFunctions = null;
    if ($DisabledFunctions === null) {
        $disable_functions_local  = explode(',', @ini_get('disable_functions'));
        $disable_functions_global = explode(',', @get_cfg_var('disable_functions'));
        foreach ($disable_functions_local as $key => $value) {
            $DisabledFunctions[$value] = 'local';
        }
        foreach ($disable_functions_global as $key => $value) {
            $DisabledFunctions[$value] = 'global';
        }
    }
    return isset($DisabledFunctions[$function]);
}

/**
 * @param string $command
 * @return bool|string
 */
function SafeExec($command)
{
    static $AllowedExecFunctions = array();
    if (count($AllowedExecFunctions) === 0) {
        $AllowedExecFunctions = array('shell_exec' => true, 'passthru' => true, 'system' => true, 'exec' => true);
        foreach ($AllowedExecFunctions as $key => $value) {
            $AllowedExecFunctions[$key] = !FunctionIsDisabled($key);
        }
    }
    foreach ($AllowedExecFunctions as $execfunction => $is_allowed) {
        if (!$is_allowed) {
            continue;
        }
        switch ($execfunction) {
            case 'passthru':
                ob_start();
                $execfunction($command);
                $returnvalue = ob_get_contents();
                ob_end_clean();
                break;

            case 'shell_exec':
            case 'system':
            case 'exec':
            default:
                //ob_start();
                $returnvalue = $execfunction($command);
                //ob_end_clean();
        }
        return $returnvalue;
    }
    return false;
}
// end: (from phpthumb.functions.php)

/**
 * @return bool|null|string
 */
function MySQLdumpVersion()
{
    static $version = null;
    if ($version === null) {
        $version = false;
        $execdversion = SafeExec('mysqldump --version');
        if (preg_match('#^mysqldump +Ver ([0-9\\.]+)#i', $execdversion, $matches)) {
            $version = $matches[1];
        }
    }
    return $version;
}

/**
 * @return bool|null|string
 */
function gzipVersion()
{
    static $version = null;
    if ($version === null) {
        $version = false;
        $execdversion = SafeExec('gzip --version');
        if (preg_match('#^gzip ([0-9\\.]+)#i', $execdversion, $matches)) {
            $version = $matches[1];
        }
    }
    return $version;
}

/**
 * @return bool|null|string
 */
function bzip2Version()
{
    static $version = null;
    if ($version === null) {
        $version = false;
        $execdversion = SafeExec('bzip2 --version 2>&1');
        if (preg_match('#^bzip2(.*) Version ([0-9\\.]+)#i', $execdversion, $matches)) {
            $version = $matches[2];
        } elseif (preg_match('#^bzip2:#i', $execdversion, $matches)) {
            $version = 'installed_unknown_version';
        }
    }
    return $version;
}

// MFA Anpassungen
/**
 * @param float $seconds
 * @param int   $precision
 * @return string
 */
function FormattedTimeRemaining($seconds, $precision = 1)
{
    global $gL10n;

    if ($seconds > 86400) {
        return $gL10n->get('SYS_DAYS_VAR', array(number_format($seconds / 86400, $precision)));
    } elseif ($seconds > 3600) {
        return $gL10n->get('SYS_HOURS_VAR', array(number_format($seconds / 3600, $precision)));
    } elseif ($seconds > 60) {
        return $gL10n->get('SYS_MINUTES_VAR', array(number_format($seconds / 60, $precision)));
    }
    return $gL10n->get('SYS_SECONDS_VAR', array(number_format($seconds, $precision)));
}
// Ende : MFA

/**
 * @param int $filesize
 * @param int $precision
 * @return string
 */
function FileSizeNiceDisplay($filesize, $precision = 2)
{
    if ($filesize < 1000) {
        $sizeunit  = 'bytes';
        $precision = 0;
    } else {
        $filesize /= 1024;
        $sizeunit  = 'kB';
    }
    if ($filesize >= 1000) {
        $filesize /= 1024;
        $sizeunit  = 'MB';
    }
    if ($filesize >= 1000) {
        $filesize /= 1024;
        $sizeunit  = 'GB';
    }
    return number_format($filesize, $precision).' '.$sizeunit;
}

/**
 * @param string $id
 * @param string $dhtml
 * @param string $text
 * @return true
 */
function OutputInformation($id, $dhtml, $text = '')
{
    global $DHTMLenabled;
    if ($DHTMLenabled) {
        if (!is_null($dhtml)) {
            if ($id) {
                echo '<script type="text/javascript">
                    var element = document.getElementById("'.$id.'");
                    if (element) {
                        element.innerHTML = "' . str_replace('</', '<\\/', $dhtml) . '";
                    }
                </script>';
            } else {
                echo $dhtml;
            }
            //flush();
        }
    } else {
        if ($text) {
            echo $text;
            //flush();
        }
    }
    return true;
}

/**
 * @param string $from
 * @param string $to
 * @param string $subject
 * @param string $textbody
 * @param string $attachmentdata
 * @param string $attachmentfilename
 * @return bool
 */
function EmailAttachment($from, $to, $subject, $textbody, &$attachmentdata, $attachmentfilename)
{
    $boundary = '_NextPart_'.time().'_'.md5($attachmentdata).'_';

    $textheaders  = '--'.$boundary."\r\n";
    $textheaders .= 'Content-Type: text/plain; format=flowed; charset="iso-8859-1"'."\r\n";
    $textheaders .= 'Content-Transfer-Encoding: 7bit'."\r\n\r\n";

    $attachmentheaders  = '--'.$boundary."\r\n";
    $attachmentheaders .= 'Content-Type: application/octet-stream; name="'.$attachmentfilename.'"'."\r\n";
    $attachmentheaders .= 'Content-Transfer-Encoding: base64'."\r\n";
    $attachmentheaders .= 'Content-Disposition: attachment; filename="'.$attachmentfilename.'"'."\r\n\r\n";

    $headers = array();
    $headers[] = 'From: '.$from;
    $headers[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';

    return mail($to, $subject, $textheaders.preg_replace("#[\x80-\xFF]#", '?', $textbody)."\r\n\r\n".$attachmentheaders.wordwrap(base64_encode($attachmentdata), 76, "\r\n", true)."\r\n\r\n".'--'.$boundary."--\r\n\r\n", implode("\r\n", $headers));
}

/////////////////////////////////////////////////////////////////////
///////////////////     END SUPPORT FUNCTIONS     ///////////////////
/////////////////////////////////////////////////////////////////////
