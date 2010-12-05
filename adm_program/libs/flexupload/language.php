<?php
/******************************************************************************
 * Photoalben
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *Übersetzt unser Languagefile für FLEX -Uploader 
 ******************************************************************************/
require_once('../../system/common.php');
 
echo'
<?xml version="1.0" encoding="utf-8"?>
<locale>
	<!-- error messages -->
	<error>'.$g_l10n->get('SYS_ERROR').'</error>
	<io_error>'.$g_l10n->get('SYS_IO_ERROR').'</io_error>
	<sec_error>'.$g_l10n->get('SYS_SECURITY_ERROR').'</sec_error>
	<error_browse>'.$g_l10n->get('SYS_BROWSE_ERROR').'</error_browse>
	<error_upload>'.$g_l10n->get('SYS_UPLOAD_ERROR').'</error_upload>
	<error_http>'.$g_l10n->get('SYS_HTTP_ERROR').'</error_http>
		
	<!-- warnings -->					
	<warning>'.$g_l10n->get('SYS_WARNING').'</warning>
	<warning_tooManyFiles>'.$g_l10n->get('SYS_TO_MANY_FILES').'</warning_tooManyFiles>
	<warning_filesize>'.$g_l10n->get('SYS_FILEZIZE').'</warning_filesize>
		
	<!-- labels of the progressbars -->
	<progressFile>'.$g_l10n->get('SYS_FILE_PROGRESS').'</progressFile>
	<progressTotal>'.$g_l10n->get('SYS_TOTAL_PROGRESS').'</progressTotal>
	
	<!-- headlines of the grid -->
	<file>'.$g_l10n->get('SYS_FILE').'</file>
	<type>'.$g_l10n->get('SYS_TYPE').'</type>
	<size>'.$g_l10n->get('SYS_SIZE').'</size>
	<status>'.$g_l10n->get('SYS_STATUS').'</status>
	
	<!-- buttons -->
	<browse>'.$g_l10n->get('SYS_BROWSE').'</browse>
	<remove>'.$g_l10n->get('SYS_REMOVE').'</remove>
	<upload>'.$g_l10n->get('SYS_UPLOAD').'</upload>
	<stop>'.$g_l10n->get('SYS_STOP').'</stop>
</locale>';