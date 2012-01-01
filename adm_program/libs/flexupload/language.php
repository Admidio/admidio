<?php
/******************************************************************************
 * Photoalben
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *Übersetzt unser Languagefile für FLEX -Uploader 
 ******************************************************************************/
require_once('../../system/common.php');
 
echo'
<?xml version="1.0" encoding="utf-8"?>
<locale>
	<!-- error messages -->
	<error>'.$gL10n->get('SYS_ERROR').'</error>
	<io_error>'.$gL10n->get('SYS_IO_ERROR').'</io_error>
	<sec_error>'.$gL10n->get('SYS_SECURITY_ERROR').'</sec_error>
	<error_browse>'.$gL10n->get('SYS_BROWSE_ERROR').'</error_browse>
	<error_upload>'.$gL10n->get('SYS_UPLOAD_ERROR').'</error_upload>
	<error_http>'.$gL10n->get('SYS_HTTP_ERROR').'</error_http>
		
	<!-- warnings -->					
	<warning>'.$gL10n->get('SYS_WARNING').'</warning>
	<warning_tooManyFiles>'.$gL10n->get('SYS_TO_MANY_FILES').'</warning_tooManyFiles>
	<warning_filesize>'.$gL10n->get('SYS_FILE_TO_LARGE').'</warning_filesize>
		
	<!-- labels of the progressbars -->
	<progressFile>'.$gL10n->get('SYS_FILE_PROGRESS').'</progressFile>
	<progressTotal>'.$gL10n->get('SYS_TOTAL_PROGRESS').'</progressTotal>
	
	<!-- headlines of the grid -->
	<file>'.$gL10n->get('SYS_FILE').'</file>
	<type>'.$gL10n->get('SYS_TYPE').'</type>
	<size>'.$gL10n->get('SYS_SIZE').'</size>
	<status>'.$gL10n->get('SYS_STATUS').'</status>
	
	<!-- buttons -->
	<browse>'.$gL10n->get('SYS_BROWSE').'</browse>
	<remove>'.$gL10n->get('SYS_REMOVE').'</remove>
	<upload>'.$gL10n->get('SYS_UPLOAD').'</upload>
	<stop>'.$gL10n->get('SYS_STOP').'</stop>
</locale>';