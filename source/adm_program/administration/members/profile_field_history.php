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
 * usr_id    : If set only show the profile field history of that user
 * filter_date_from : is set to actual date, 
 *             if no date information is delivered
 * filter_date_to   : is set to 31.12.9999, 
 *             if no date information is delivered
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', '1970-01-01', false);
$getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', DATE_NOW, false);

// create a user object from the user parameter
$user = new User($gDb, $gProfileFields, $getUserId);

// set headline of the script
if($getUserId > 0)
{
	$headline = $gL10n->get('MEM_CHANGE_HISTORY_OF', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'));
}
else
{
	$headline = $gL10n->get('MEM_CHANGE_HISTORY');
}

// Initialize local parameteres
$sqlConditions  = '';

// if profile log is activated and current user is allowed to edit users 
// then the profile field history will be shown otherwise show error
if ($gPreferences['profile_log_edit_fields'] == 0
    || ($getUserId == 0 && $gCurrentUser->editUsers() == false)
    || ($getUserId > 0  && $gCurrentUser->editProfile($user) == false))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// add page to navigation history
$gNavigation->addUrl(CURRENT_URL, $headline);

// filter_date_from and filter_date_to can have differnt formats 
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
	     ORDER BY usl_timestamp_create DESC ';
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

// create html page object
$page = new HtmlPage();

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// show headline of module
$page->addHeadline($headline);

// Input elements for Startdate and Enddate
$page->addHtml('
<nav class="navbar navbar-default" role="navigation">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-filter-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#">'.$gL10n->get('SYS_FILTER').'</a>
        </div>
        
        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-filter-navbar-collapse-1">');
            $form = new HtmlForm('navbar_filter_form', $g_root_path.'/adm_program/administration/members/profile_field_history.php?usr_id='.$getUserId, $page, 'filter', false, 'navbar-form navbar-left');
            $form->addTextInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, 10, FIELD_DEFAULT, 'date');
            $form->addTextInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, 10, FIELD_DEFAULT, 'date');
            $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
            $page->addHtml($form->show(false));
        $page->addHtml('</div>
    </div>
</nav>');

$table = new HtmlTable('profile_field_history_table', $page, true, true);

$columnHeading = array();

if($getUserId == 0)
{
    $table->setDatatablesOrderColumns(array(array(6, 'desc')));
    $columnHeading[] = $gL10n->get('SYS_NAME');
}
else
{
    $table->setDatatablesOrderColumns(array(array(5, 'desc')));
}

$columnHeading[] = $gL10n->get('SYS_FIELD');
$columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
$columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
$columnHeading[] = $gL10n->get('SYS_EDITED_BY');
$columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

$table->addRowHeadingByArray($columnHeading);

while($row = $gDb->fetch_array($resultFieldHistory))
{
	$timestampCreate = new DateTimeExtended($row['usl_timestamp_create'], 'Y-m-d H:i:s');
    $columnValues    = array();
	
	if($getUserId == 0)
	{
        $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usl_usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a>';
	}
	
	$columnValues[] = $gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name');
    if(strlen($gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_new'], 'html')) > 0)
    {
        $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_new'], 'html');
    }
    else
    {
        $columnValues[] = '&nbsp;';
    }

    if(strlen($gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_old'], 'html')) > 0)
    {
        $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_old'], 'html');
    }
    else
    {
        $columnValues[] = '&nbsp;';
    }
    
    $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usl_usr_id_create'].'">'.$row['create_last_name'].', '.$row['create_first_name'].'</a>';
	$columnValues[] = $timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']);
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show(false));
$page->show();
?>