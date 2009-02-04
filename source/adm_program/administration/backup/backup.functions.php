<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based on backupDB Version 1.2.5a-200806190803 
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

function FormattedTimeRemaining($seconds, $precision=1) {
	if ($seconds > 86400) {
		return number_format($seconds / 86400, $precision).' Tagen';
	} elseif ($seconds > 3600) {
		return number_format($seconds / 3600, $precision).' Stunden';
	} elseif ($seconds > 60) {
		return number_format($seconds / 60, $precision).' Minuten';
	}
	return number_format($seconds, $precision).' Sekunden';
}

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