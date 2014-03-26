<?php
/******************************************************************************
 * Show history of profile field changes
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * start     : Number of query recordset where the visual output should start
 * usr_id    : If set only show the profile field history of that user
 * date_from : is set to actual date, 
 *             if no date information is delivered
 * date_to   : is set to 31.12.9999, 
 *             if no date information is delivered
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date', '1970-01-01', false);
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date', DATE_NOW, false);

// Initialize local parameteres
$membersPerPage = 25; // Number of recordsets that will be shown per page
$sqlConditions  = '';

// create a user object from the user parameter
$user = new User($gDb, $gProfileFields, $getUserId);

// if profile log is activated and current user is allowed to edit users 
// then the profile field history will be shown otherwise show error
if ($gPreferences['profile_log_edit_fields'] == 0
    || ($getUserId == 0 && $gCurrentUser->editUsers() == false)
    || ($getUserId > 0  && $gCurrentUser->editProfile($user) == false))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// add page to navigation history
$gNavigation->addUrl(CURRENT_URL);

// date_from and date_to can have differnt formats 
// now we try to get a default format for intern use and html output
$objDateFrom = new DateTimeExtended($getDateFrom, 'Y-m-d', 'date');
if($objDateFrom->valid() == false)
{
	// check if date has system format
	$objDateFrom = new DateTimeExtended($getDateFrom, $gPreferences['system_date'], 'date');
	if($objDateFrom->valid() == false)
	{
		$objDateFrom->setDateTime('1970-01-01', $gPreferences['system_date']);
	}
}

$objDateTo = new DateTimeExtended($getDateTo, 'Y-m-d', 'date');
if($objDateTo->valid() == false)
{
	// check if date has system format
	$objDateTo = new DateTimeExtended($getDateTo, $gPreferences['system_date'], 'date');
	if($objDateTo->valid() == false)
	{
		$objDateTo->setDateTime('1970-01-01', $gPreferences['system_date']);
	}
}

$dateFromIntern = $objDateFrom->format('Y-m-d');
$dateFromHtml   = $objDateFrom->format($gPreferences['system_date']);
$dateToIntern   = $objDateTo->format('Y-m-d');
$dateToHtml     = $objDateTo->format($gPreferences['system_date']);

// create sql conditions
if($getUserId > 0)
{
	$sqlConditions .= ' AND usl_usr_id = '.$getUserId;
}

// get total count of relevant profile field changes
$sql = 'SELECT COUNT(1) as count
          FROM '.TBL_USER_LOG.'
         WHERE 1 = 1 '.
		       $sqlConditions;
$gDb->query($sql);
$row = $gDb->fetch_array();
$countChanges = $row['count'];

// create select statement with all neccessary data
$sql = 'SELECT usl_usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, usl_usf_id, usl_value_old, usl_value_new, 
               usl_usr_id_create, create_last_name.usd_value as create_last_name, create_first_name.usd_value as create_first_name, usl_timestamp_create
          FROM '.TBL_USER_LOG.'
          JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usl_usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usl_usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          JOIN '. TBL_USER_DATA. ' as create_last_name
            ON create_last_name.usd_usr_id = usl_usr_id_create
           AND create_last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          JOIN '. TBL_USER_DATA. ' as create_first_name
            ON create_first_name.usd_usr_id = usl_usr_id_create
           AND create_first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
		 WHERE usl_timestamp_create BETWEEN \''.$dateFromIntern.' 00:00:00\' AND \''.$dateToIntern.' 23:59:59\' '.
		       $sqlConditions.'
	     ORDER BY usl_timestamp_create DESC 
		 LIMIT '.$membersPerPage.' OFFSET '.$getStart;
$resultFieldHistory = $gDb->query($sql);

if($gDb->num_rows($resultFieldHistory) == 0)
{
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();
    
    // show message if there were no changes for users
    if($getUserId > 0)
    {
        $gMessage->show($gL10n->get('MEM_NO_CHANGES_PROFIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }
    else
    {
        $gMessage->show($gL10n->get('MEM_NO_CHANGES'));        
    }
}

$gLayout['header'] = ' 
<script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
<link rel="stylesheet" href="'.THEME_PATH. '/css/calendar.css" type="text/css" />

<script type="text/javascript"><!--
	function send()
	{
		document.getElementById("admDateForm").action = "'.$g_root_path.'/adm_program/administration/members/profile_field_history.php?usr_id='.$getUserId.'&start='.$getStart.'&date_from=" + $("#admDateFrom").val() + "&date_to=" + $("#admDateTo").val();
		document.getElementById("admDateForm").submit();
		//$("#admDateForm").action = "'.$g_root_path.'/adm_program/administration/members/profile_field_history.php?usr_id='.$getUserId.'&start='.$getStart.'&date_from=" + $("#admDateFrom").val() + "&date_to=" + $("#admDateTo").val();
		//$("#admDateForm").submit();
	}
				
	function Datefilter () 
	{ 
		var field_error = "'.$gL10n->get('ECA_FIELD_ERROR').'";
		
		if (document.Formular.date_from.value == "" 
			|| document.Formular.date_to.value == "") 
		{
			alert(field_error);
			document.Formular.date_from.focus();
			return false;
		} 
	}

	var calPopup = new CalendarPopup("calendardiv");
	calPopup.setCssPrefix("calendar"); 
//--></script>';

// show html header
require(SERVER_PATH. '/adm_program/system/overall_header.php');

if($getUserId > 0)
{
	echo '<h1 class="moduleHeadline">'.$gL10n->get('MEM_CHANGE_HISTORY_OF', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')).'</h1>';
}
else
{
	echo '<h1 class="moduleHeadline">'.$gL10n->get('MEM_CHANGE_HISTORY').'</h1>';
}

 //Input for Startdate and Enddate
	
echo '
<div class="navigationPath">
	<form id="admDateForm" method="post">
		<label for="admDateFrom" style="margin-left: 10px;">'.$gL10n->get('SYS_START').':</label>
		<input type="text" id="admDateFrom" name="admDateFrom" size="10" maxlength="10" value="'.$dateFromHtml.'" />
		<a class="iconLink" id="anchor_date_from" href="javascript:calPopup.select(document.getElementById(\'admDateFrom\'),\'anchor_date_from\',\''.$gPreferences['system_date'].'\',\'admDateFrom\',\'admDateTo\');"><img
			src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
		<span id="calendardiv" style="position: absolute; visibility: hidden;"></span>

			&nbsp;&nbsp;

		<label for="admDateTo">'.$gL10n->get('SYS_END').':</label>
		<input type="text" id="admDateTo" name="admDateTo" size="10" maxlength="10" value="'.$dateToHtml.'" />
		<a class="iconLink" id="anchor_date_to" href="javascript:calPopup.select(document.getElementById(\'admDateTo\'),\'anchor_date_to\',\''.$gPreferences['system_date'].'\',\'admDateFrom\',\'admDateTo\');"><img
			src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
			
			&nbsp;&nbsp;
			
		<input type="button" onclick="send()" value="OK"> 
	</form> 
</div>

<table class="tableList" cellspacing="0">
    <thead>
        <tr>';
			if($getUserId == 0)
			{
				echo '<th>'.$gL10n->get('SYS_NAME').'</th>';
			}
            echo '
			<th>'.$gL10n->get('SYS_FIELD').'</th>
            <th>'.$gL10n->get('SYS_NEW_VALUE').'</th>
            <th>'.$gL10n->get('SYS_PREVIOUS_VALUE').'</th>
            <th>'.$gL10n->get('SYS_EDITED_BY').'</th>
            <th>'.$gL10n->get('SYS_CHANGED_AT').'</th>
        </tr>
    </thead>';
	
    while($row = $gDb->fetch_array($resultFieldHistory))
    {
		$timestampCreate = new DateTimeExtended($row['usl_timestamp_create'], 'Y-m-d H:i:s');

		echo '
		<tr class="tableMouseOver">';
			if($getUserId == 0)
			{
				echo '<td>'.$row['last_name'].', '.$row['first_name'].'</td>';
			}

			echo '
            <td>'.$gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name').'</td>
            <td>'.$gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_new'], 'html').'</td>
            <td>'.$gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_old'], 'html').'</td>
            <td>'.$row['create_last_name'].', '.$row['create_first_name'].'</td>
            <td>'.$timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']).'</td>
		</tr>';
	}
echo '</table>';

// If neccessary show links to navigate to next and previous recordsets of the query
$base_url = $g_root_path.'/adm_program/administration/members/profile_field_history.php?start='.$getStart.'&usr_id='.$getUserId.'&date_from='.$getDateFrom.'&date_to='.$getDateTo;
echo admFuncGeneratePagination($base_url, $countChanges, $membersPerPage, $getStart, TRUE);

echo '
<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>