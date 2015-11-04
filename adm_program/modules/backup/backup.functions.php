<?php
/******************************************************************************
 * backupDB() - Support Functions
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 * Based on backupDB Version 1.2.7-201104261502
 * by James Heinrich <info@silisoftware.com>
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/

if (!function_exists('getmicrotime')) {
    function getmicrotime() {
        list($usec, $sec) = explode(' ', microtime());
        return ((float) $usec + (float) $sec);
    }
}

// begin: (from phpthumb.functions.php)
function FunctionIsDisabled($function) {
    static $DisabledFunctions = null;
    if ($DisabledFunctions === null) {
        $disable_functions_local  = explode(',',     @ini_get('disable_functions'));
        $disable_functions_global = explode(',', @get_cfg_var('disable_functions'));
        foreach ($disable_functions_local as $key => $value) {
            $DisabledFunctions[$value] = 'local';
        }
        foreach ($disable_functions_global as $key => $value) {
            $DisabledFunctions[$value] = 'global';
        }
        if (@ini_get('safe_mode')) {
            $DisabledFunctions['shell_exec'] = 'local';
        }
    }
    return isset($DisabledFunctions[$function]);
}

function SafeExec($command) {
    static $AllowedExecFunctions = array();
    if (empty($AllowedExecFunctions)) {
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
                break;
        }
        return $returnvalue;
    }
    return false;
}

function version_compare_replacement_sub($version1, $version2, $operator='') {
    // If you specify the third optional operator argument, you can test for a particular relationship.
    // The possible operators are: <, lt, <=, le, >, gt, >=, ge, ==, =, eq, !=, <>, ne respectively.
    // Using this argument, the function will return 1 if the relationship is the one specified by the operator, 0 otherwise.

    // If a part contains special version strings these are handled in the following order: dev < (alpha = a) < (beta = b) < RC < pl
    static $versiontype_lookup = array();
    if (empty($versiontype_lookup)) {
        $versiontype_lookup['dev']   = 10001;
        $versiontype_lookup['a']     = 10002;
        $versiontype_lookup['alpha'] = 10002;
        $versiontype_lookup['b']     = 10003;
        $versiontype_lookup['beta']  = 10003;
        $versiontype_lookup['RC']    = 10004;
        $versiontype_lookup['pl']    = 10005;
    }
    if (isset($versiontype_lookup[$version1])) {
        $version1 = $versiontype_lookup[$version1];
    }
    if (isset($versiontype_lookup[$version2])) {
        $version2 = $versiontype_lookup[$version2];
    }

    switch ($operator) {
        case '<':
        case 'lt':
            return intval($version1 < $version2);
            break;
        case '<=':
        case 'le':
            return intval($version1 <= $version2);
            break;
        case '>':
        case 'gt':
            return intval($version1 > $version2);
            break;
        case '>=':
        case 'ge':
            return intval($version1 >= $version2);
            break;
        case '==':
        case '=':
        case 'eq':
            return intval($version1 == $version2);
            break;
        case '!=':
        case '<>':
        case 'ne':
            return intval($version1 != $version2);
            break;
    }
    if ($version1 == $version2) {
        return 0;
    } elseif ($version1 < $version2) {
        return -1;
    }
    return 1;
}

function version_compare_replacement($version1, $version2, $operator='') {
    if (function_exists('version_compare')) {
        // built into PHP v4.1.0+
        return version_compare($version1, $version2, $operator);
    }

    // The function first replaces _, - and + with a dot . in the version strings
    $version1 = strtr($version1, '_-+', '...');
    $version2 = strtr($version2, '_-+', '...');

    // and also inserts dots . before and after any non number so that for example '4.3.2RC1' becomes '4.3.2.RC.1'.
    // Then it splits the results like if you were using explode('.',$ver). Then it compares the parts starting from left to right.
    $version1 = preg_replace('#([0-9]+)([A-Z]+)([0-9]+)#i', '\\1.\\2.\\3', $version1);
    $version2 = preg_replace('#([0-9]+)([A-Z]+)([0-9]+)#i', '\\1.\\2.\\3', $version2);

    $parts1 = explode('.', $version1);
    $parts2 = explode('.', $version1);
    $parts_count = max(count($parts1), count($parts2));
    for ($i = 0; $i < $parts_count; $i++) {
        $comparison = phpthumb_functions::version_compare_replacement_sub($version1, $version2, $operator);
        if ($comparison != 0) {
            return $comparison;
        }
    }
    return 0;
}
// end: (from phpthumb.functions.php)

function MySQLdumpVersion() {
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

function gzipVersion() {
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

function bzip2Version() {
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
function FormattedTimeRemaining($seconds, $precision=1)
{
    global $gL10n;

    if ($seconds > 86400) {
        return $gL10n->get('BAC_DAYS_VAR', number_format($seconds / 86400, $precision));
    } elseif ($seconds > 3600) {
        return $gL10n->get('BAC_HOURS_VAR', number_format($seconds / 3600, $precision));
    } elseif ($seconds > 60) {
        return $gL10n->get('BAC_MINUTES_VAR', number_format($seconds / 60, $precision));
    }
    return $gL10n->get('BAC_SECONDS_VAR', number_format($seconds, $precision));
}
// Ende : MFA

function FileSizeNiceDisplay($filesize, $precision=2) {
    if ($filesize < 1000) {
        $sizeunit  = 'bytes';
        $precision = 0;
    } else {
        $filesize /= 1024;
        $sizeunit = 'kB';
    }
    if ($filesize >= 1000) {
        $filesize /= 1024;
        $sizeunit = 'MB';
    }
    if ($filesize >= 1000) {
        $filesize /= 1024;
        $sizeunit = 'GB';
    }
    return number_format($filesize, $precision).' '.$sizeunit;
}

function OutputInformation($id, $dhtml, $text='') {
    global $DHTMLenabled;
    if ($DHTMLenabled) {
        if (!$dhtml === null) {
            if ($id) {
                echo '<script type="text/javascript">if (document.getElementById("'.$id.'")) document.getElementById("'.$id.'").innerHTML="'.str_replace('</', '<\\/', $dhtml).'"</script>';
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

function EmailAttachment($from, $to, $subject, $textbody, &$attachmentdata, $attachmentfilename) {
    $boundary = '_NextPart_'.time().'_'.md5($attachmentdata).'_';

    $textheaders  = '--'.$boundary."\r\n";
    $textheaders .= 'Content-Type: text/plain; format=flowed; charset="iso-8859-1"'."\r\n";
    $textheaders .= 'Content-Transfer-Encoding: 7bit'."\r\n\r\n";

    $attachmentheaders  = '--'.$boundary."\r\n";
    $attachmentheaders .= 'Content-Type: application/octet-stream; name="'.$attachmentfilename.'"'."\r\n";
    $attachmentheaders .= 'Content-Transfer-Encoding: base64'."\r\n";
    $attachmentheaders .= 'Content-Disposition: attachment; filename="'.$attachmentfilename.'"'."\r\n\r\n";

    $headers[] = 'From: '.$from;
    $headers[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';

    return mail($to, $subject, $textheaders.preg_replace("#[\x80-\xFF]#", '?', $textbody)."\r\n\r\n".$attachmentheaders.wordwrap(base64_encode($attachmentdata), 76, "\r\n", true)."\r\n\r\n".'--'.$boundary."--\r\n\r\n", implode("\r\n", $headers));
}

/////////////////////////////////////////////////////////////////////
///////////////////     END SUPPORT FUNCTIONS     ///////////////////
/////////////////////////////////////////////////////////////////////
