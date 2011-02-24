<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based on backupDB Version 1.2.5a-200806190803 
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/

if (!function_exists('getmicrotime')) 
{
	function getmicrotime() {
		list($usec, $sec) = explode(' ', microtime());
		return ((float) $usec + (float) $sec);
	}
}

function FormattedTimeRemaining($seconds, $precision=1) 
{
    global $g_l10n;

	if ($seconds > 86400) {
		return $g_l10n->get('BAC_DAYS_VAR', number_format($seconds / 86400, $precision));
	} elseif ($seconds > 3600) {
		return $g_l10n->get('BAC_HOURS_VAR', number_format($seconds / 3600, $precision));
	} elseif ($seconds > 60) {
		return $g_l10n->get('BAC_MINUTES_VAR', number_format($seconds / 60, $precision));
	}
	return $g_l10n->get('BAC_SECONDS_VAR', number_format($seconds, $precision));
}

function FileSizeNiceDisplay($filesize, $precision=2) 
{
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

function OutputInformation($id, $dhtml, $text='') 
{
		if (!is_null($dhtml)) {
			if ($id) {
				echo '<script type="text/javascript"><!--
                    if (document.getElementById("'.$id.'")) 
                    {
                        document.getElementById("'.$id.'").innerHTML="'.$dhtml.'";
                    }
                //--></script>';
			} else {
				echo $dhtml;
			}
			flush();
		}
	return true;
}
?>