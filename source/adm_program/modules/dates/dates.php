<?php
/******************************************************************************
 * Show a list of all events
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode: actual       - (Default) show actual dates and all events in future
 *       old          - show events in the past
 * start              - Informatiobn about the start of shown data records
 * headline           - Headline shown over events
 *                      (Default) Dates
 * cat_id             - show all events of calendar with this id
 * id                 - Show only one event
 * date               - All events for a date are listet
 *                      Format: YYYYMMDD
 * calendar-selection - 1: The box is shown
 *                      0: The box is not shown
 *
 * date_from          - is set to actual date, 
 *                      if no date information is delivered
 * date_to            - is set to 31.12.9999, 
 *                      if no date information is delivered
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_category.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_rooms.php');
unset($_SESSION['dates_request']);

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', 'actual', false, array('actual', 'old'));
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('DAT_DATES'));
$getDateId   = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$getDate     = admFuncVariableIsValid($_GET, 'date', 'numeric');
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getCalendarSelection = admFuncVariableIsValid($_GET, 'calendar-selection', 'boolean', $gPreferences['dates_show_calendar_select']);
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'string', DATE_NOW, false);
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'string', '9999-12-31', false);
    
// check if old events are required
// then read all events in the past
if($getMode == 'old')
{
	$getDateFrom = '1970-01-01';
	$getDateTo     = date('Y-m-d', time() - (24 * 60 * 60));
}

// if exact date is set than convert it to our new syntax with dateFrom and dateTo
if(strlen($getDate) > 0)
{
	$getDateFrom = substr($getDate,0,4). '-'. substr($getDate,4,2). '-'. substr($getDate,6,2);
	$getDateTo   = $getDateFrom;
}

// check if date has english format
$objDateFrom = new DateTimeExtended($getDateFrom, 'Y-m-d', 'date'); 

if($objDateFrom->valid())
{
	$dateFromEnglishFormat = $getDateFrom;
	$dateFromSystemFormat = $objDateFrom->format($gPreferences['system_date']);
}
else
{
	// check if date has system format
	$objDateFrom = new DateTimeExtended($getDateFrom, $gPreferences['system_date'], 'date');
    $objDateFrom->setDateTime($getDateFrom, $gPreferences['system_date']);
	if($objDateFrom->valid())
	{
		$dateFromEnglishFormat = substr($objDateFrom->getDateTimeEnglish(), 0, 10);
		$dateFromSystemFormat = $objDateFrom->format($gPreferences['system_date']);
	}
    else
    {
        $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
    }
}

// The same checks for the enddate
$objDateTo = new DateTimeExtended($getDateTo, 'Y-m-d', 'date');

if($objDateTo->valid())
{
	$dateToEnglishFormat = $getDateTo;
	$dateToSystemFormat = $objDateTo->format($gPreferences['system_date']);
}
else
{
    $objDateTo = new DateTimeExtended($getDateTo, $gPreferences['system_date'], 'date');
    $objDateTo->setDateTime($getDateTo, $gPreferences['system_date']);
	if($objDateTo->valid())
	{
		$dateToEnglishFormat = substr($objDateTo->getDateTimeEnglish(), 0, 10);
		$dateToSystemFormat = $objDateTo->format($gPreferences['system_date']);
		// Set mode to 'old' if nescessary
        if($getDateTo < date($gPreferences['system_date']))
        {
            $getMode = 'old';
        }

        else
        {
            $getMode = 'actual';
        }

		
	}
    else
    {
        $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
    }
}

// Fill input fields only if User requests exists
if ($getDateFrom == '1970-01-01')
{
    $dateFromHtmlOutput = '';
    $dateToHtmlOutput  = '';
}
elseif ($getDateTo == '9999-12-31') 
{
    $dateFromHtmlOutput = '';
    $dateToHtmlOutput  = '';
}
else
{
    $dateFromHtmlOutput = $dateFromSystemFormat;
    $dateToHtmlOutput  = $dateToSystemFormat;
}
  
// check if module is active
if($gPreferences['enable_dates_module'] == 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // module only for valid Useres
    require_once('../../system/login_valid.php');
}

if($getCatId > 0)
{
    $calendar = new TableCategory($gDb, $getCatId);
}

// Navigation starts here
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Head output
if($getCatId > 0)
{
    $gLayout['title'] = $getHeadline. ' - '. $calendar->getValue('cat_name');
}
else
{
    $gLayout['title'] = $getHeadline;
}
if($getMode == 'old')
{
    $gLayout['title'] = $gL10n->get('DAT_PREVIOUS_DATES', ' '.$gLayout['title']);
}

if($gPreferences['enable_rss'] == 1 && $gPreferences['enable_dates_module'] == 1)
{
    $gLayout['header'] =  '<link rel="alternate" type="application/rss+xml" title="'.$gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline).'"
        href="'.$g_root_path.'/adm_program/modules/dates/rss_dates.php?headline='.$getHeadline.'" />';
};

$gLayout['header'] .= '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
    <link rel="stylesheet" href="'.THEME_PATH. '/css/calendar.css" type="text/css" />';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html Output
echo ' 
<script type="text/javascript"><!--
    $(document).ready(function() 
    {
        $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
        
        $("#admCalendar").change(function () {
            var calendarId = "";
            if (document.getElementById("admCalendar").selectedIndex != 0)
            {
                var calendarId = document.getElementById("admCalendar").value;
            } 
            self.location.href = "dates.php?mode='.$getMode.'&headline='.$getHeadline.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat.'&cat_id=" + calendarId;
        });
    });
    
function Datefilter () 
{ 
    if (document.Formular.date_from.value == "") 
    {
        alert("Bitte Startdatum eingeben!");
        document.Formular.date_from.focus();
        return false;
    }
        
    if (document.Formular.date_to.value == "") 
    {
        alert("Bitte Enddatum eingeben!");
        document.Formular.date_to.focus();
        return false;
    }
  
}
        var calPopup = new CalendarPopup("calendardiv");
        calPopup.setCssPrefix("calendar"); 

//--></script>

<h1 class="moduleHeadline">'. $gLayout['title']. '</h1>';  

     
// find all groups, where organisation is head or sub organisatiob
$topNavigation = '';
$organizations = '';
$sqlConditions = '';
$sqlConditionCalendar = '';
$sqlConditionLogin = '';
$sqlOrderBy = '';
$arr_ref_orgas = $gCurrentOrganization->getReferenceOrganizations(true, true);

foreach($arr_ref_orgas as $org_id => $value)
{
    $organizations = $organizations. $org_id. ', ';
}
$organizations = $organizations. $gCurrentOrganization->getValue('org_id');

if ($gValidLogin == false)
{
    // if user isn't logged in, then don't show hidden categories
    $sqlConditions .= ' AND cat_hidden = 0 ';
}

// In case ID was permitted and user has rights
if($getDateId > 0)
{
    $sqlConditions .= ' AND dat_id = '.$getDateId;
}
//...otherwise get all additional events for a group
else
{
    if ($getCatId > 0)
    {
        // show all events from category
        $sqlConditionCalendar .= ' AND cat_id  = '.$getCatId;
    }
    // other events
    else
    {
		// add 1 second to end date because full time events to until next day
        $sqlConditions .= ' AND (  dat_begin BETWEEN \''.$dateFromEnglishFormat.' 00:00:00\' AND \''.$dateToEnglishFormat.' 23:59:59\'
                                OR dat_end   BETWEEN \''.$dateFromEnglishFormat.' 00:00:01\' AND \''.$dateToEnglishFormat.' 23:59:59\')';
        $sqlOrderBy .= ' ORDER BY dat_begin ASC ';
    }
}

if($getDateId == 0)
{
    // add conditions for role permission
    if($gCurrentUser->getValue('usr_id') > 0)
    {
        $sqlConditionLogin = '
        AND (  dtr_rol_id IS NULL 
            OR dtr_rol_id IN (SELECT mem_rol_id 
                                FROM '.TBL_MEMBERS.' mem2
                               WHERE mem2.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                 AND mem2.mem_begin  <= dat_begin
                                 AND mem2.mem_end    >= dat_end) ) ';
    }
    else
    {
        $sqlConditionLogin = ' AND dtr_rol_id IS NULL ';
    }
    
    // Check how many data records are found
    $sql = 'SELECT COUNT(DISTINCT dat_id) as count
              FROM '.TBL_DATE_ROLE.', '. TBL_DATES. ', '. TBL_CATEGORIES. '
             WHERE dat_cat_id = cat_id
               AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   OR (   dat_global   = 1
                      AND cat_org_id IN ('.$organizations.') 
                      )
                   )
               AND dat_id = dtr_dat_id
                   '.$sqlConditionLogin. $sqlConditions. $sqlConditionCalendar;

    $result = $gDb->query($sql);
    $row    = $gDb->fetch_array($result);
    $num_dates = $row['count'];
}
else
{
    $num_dates = 1;
}

// Number of events each page
if($gPreferences['dates_per_page'] > 0)
{
    $dates_per_page = $gPreferences['dates_per_page'];
}
else
{
    $dates_per_page = $num_dates;
}

// read the events for output
$sql = 'SELECT DISTINCT cat.*, dat.*, mem.mem_usr_id as member_date_role, mem.mem_leader,
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
          FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
          LEFT JOIN '. TBL_USER_DATA .' cre_surname 
            ON cre_surname.usd_usr_id = dat_usr_id_create
           AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname 
            ON cre_firstname.usd_usr_id = dat_usr_id_create
           AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = dat_usr_id_change
           AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = dat_usr_id_change
           AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_MEMBERS. ' mem
            ON mem.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
           AND mem.mem_rol_id = dat_rol_id
           AND mem_begin <= \''.DATE_NOW.'\'
           AND mem_end    > \''.DATE_NOW.'\'
         WHERE dat_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR (   dat_global   = 1
                  AND cat_org_id IN ('.$organizations.') ))
           AND dat_id = dtr_dat_id
               '.$sqlConditionLogin.'
               '.$sqlConditions. $sqlConditionCalendar. $sqlOrderBy. '
         LIMIT '.$dates_per_page.' OFFSET '.$getStart;
$dates_result = $gDb->query($sql);


//Check if box must be shown, when more dates avaiable
if((($getCalendarSelection == 1) && ($getDateId == 0)) || $gCurrentUser->editDates())
{
    $topNavigation = '';

    //Add new event
    if($gCurrentUser->editDates())
    {
        $topNavigation .= '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$getHeadline.'"><img
                src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_VAR', $getHeadline).'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$getHeadline.'">'.$gL10n->get('SYS_CREATE_VAR', $getHeadline).'</a>
            </span>
        </li>';
    }

    if(($getCalendarSelection == 1) && ($getDateId == 0))   
    {
        // create select box with all calendars that have dates
        $calendarSelectBox = FormElements::generateCategorySelectBox('DAT', $getCatId, 'admCalendar', $gL10n->get('SYS_ALL'), true);

        if(strlen($calendarSelectBox) > 0)
        {
            // show calendar select box with link to calendar preferences
            $topNavigation .= '<li>'.$gL10n->get('DAT_CALENDAR').':&nbsp;&nbsp;'.$calendarSelectBox;

            if($gCurrentUser->editDates())
            {
                $topNavigation .= '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'"><img
                     src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('DAT_MANAGE_CALENDARS').'" title="'.$gL10n->get('DAT_MANAGE_CALENDARS').'" /></a>';
            }
            $topNavigation .= '</li>';
        }
        elseif($gCurrentUser->editDates())
        {
            // show link to calendar preferences
            $topNavigation .= '
            <li><span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'"><img
                    src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('DAT_MANAGE_CALENDARS').'" title="'.$gL10n->get('DAT_MANAGE_CALENDARS').'"/></a>
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'">'.$gL10n->get('DAT_MANAGE_CALENDARS').'</a>
            </span></li>';
        }
        //ical Download
        if($gPreferences['enable_dates_ical'] == 1)
        {
            $topNavigation .= '<li class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$getHeadline.'&amp;cat_id='.$getCatId.'"><img
                    src="'. THEME_PATH. '/icons/database_out.png" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'"/></a>
                <a href="'.$g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$getHeadline.'&amp;cat_id='.$getCatId.'">'.$gL10n->get('DAT_EXPORT_ICAL').'</a>
            </li>';
        }
    }
    
     //Input for Startdate and Enddate
        
       $topNavigation .= '

            <li>
                <dl> 
                    <dt><br />
                            <span>
                                <form name="Formular" action="'.$g_root_path.'/adm_program/modules/dates/dates.php"onsubmit="return Datefilter()">
                                    <label for="date_from">'.$gL10n->get('SYS_START').':</label>
                                        <input type="text" id="date_from" name="date_from" onchange="javascript:setDateTo();" size="10" maxlength="10" value="'.$dateFromHtmlOutput.'" />
                                        <a class="iconLink" id="anchor_date_from" href="javascript:calPopup.select(document.getElementById(\'date_from\'),\'anchor_date_from\',\''.$gPreferences['system_date'].'\',\'date_from\',\'date_to\');"><img
                                        src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
                                        <span id="calendardiv" style="position: absolute; visibility: hidden;"></span>

                                        &nbsp;&nbsp;

                                    <label for="date_to">'.$gL10n->get('SYS_END').':</label>

                                        <input type="text" id="date_to" name="date_to" size="10" maxlength="10" value="'.$dateToHtmlOutput.'" />
                                        <a class="iconLink" id="anchor_date_to" href="javascript:calPopup.select(document.getElementById(\'date_to\'),\'anchor_date_to\',\''.$gPreferences['system_date'].'\',\'date_from\',\'date_to\');"><img
                                        src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
                                        
                                        &nbsp;&nbsp;
                                        
                                    <input type="submit" value="OK"> 
                                </form> 
                            </span>
                    </dt>
                </dl>
            </li>'; 
           
    if(strlen($topNavigation) > 0)
    {
        echo '<ul class="iconTextLinkList">'.$topNavigation.'</ul>';
    }
}

if($gDb->num_rows($dates_result) == 0)
{
    // No events found
    if($getDateId > 0)
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
    }
}
else
{
    $date = new TableDate($gDb);

    // List events
    while($row = $gDb->fetch_array($dates_result))
    {
        // Initialize object and write new data
        //$date->clear();
        //$date->setArray($row);
        $date->readData($row['dat_id']);

        echo '
        <div class="boxLayout" id="dat_'.$date->getValue('dat_id').'">
            <div class="boxHead">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/dates.png" alt="'. $date->getValue('dat_headline'). '" />'
                    . $date->getValue('dat_begin', $gPreferences['system_date']);
                    if($date->getValue('dat_begin', $gPreferences['system_date']) != $date->getValue('dat_end', $gPreferences['system_date']))
                    {
                        echo ' - '. $date->getValue('dat_end', $gPreferences['system_date']);
                    }
                    echo ' ' . $date->getValue('dat_headline'). '
                </div>
                <div class="boxHeadRight">';
                    //ical Download
                    if($gPreferences['enable_dates_ical'] == 1)
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='. $date->getValue('dat_id'). '&amp;mode=6"><img
                        src="'. THEME_PATH. '/icons/database_out.png" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'" /></a>';
                    }
                    
                    // change and delete is only for useres with additional rights
                    if ($gCurrentUser->editDates())
                    {
                        if($date->editRight() == true)
                        {
                            echo '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$getHeadline.'"><img
                                src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;headline='.$getHeadline.'"><img
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
                        }

                        // Deleting events is only allowed for group members
                        if($date->getValue('cat_org_id') == $gCurrentOrganization->getValue('org_id'))
                        {
                            echo '
                            <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=dat&amp;element_id=dat_'.
                                $date->getValue('dat_id').'&amp;name='.urlencode($date->getValue('dat_begin', $gPreferences['system_date']).' '.$date->getValue('dat_headline')).'&amp;database_id='.$date->getValue('dat_id').'"><img 
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                        }
                    }
                echo'</div>
            </div>

            <div class="boxBody">';
                $dateElements = array();
                $firstElement = true;

                if ($date->getValue('dat_all_day') == 0)
                {
                    // Write start in array
                    $dateElements[] = array($gL10n->get('SYS_START'), '<strong>'. $date->getValue('dat_begin', $gPreferences['system_time']). '</strong> '.$gL10n->get('SYS_CLOCK'));
                    // Write end in array
                    $dateElements[] = array($gL10n->get('SYS_END'), '<strong>'. $date->getValue('dat_end', $gPreferences['system_time']). '</strong> '.$gL10n->get('SYS_CLOCK'));
                }
                // write calendar in output array
                $dateElements[] = array($gL10n->get('DAT_CALENDAR'), '<strong>'. $date->getValue('cat_name'). '</strong>');

                if (strlen($date->getValue('dat_location')) > 0)
                {
                    // Show map link, when at leastt 2 words avaiable 
                    // having more then 3 characters each
                    $map_info_count = 0;
                    foreach(preg_split('/[,; ]/', $date->getValue('dat_location')) as $key => $value)
                    {
                        if(strlen($value) > 3)
                        {
                            $map_info_count++;
                        }
                    }

                    if($gPreferences['dates_show_map_link'] == true
                        && $map_info_count > 1)
                    {
                        // Create Google-Maps-Link for location
                        $location_url = 'http://maps.google.com/?q='. $date->getValue('dat_location');
                        if(strlen($date->getValue('dat_country')) > 0)
                        {
                            // Better results with additional country information
                            $location_url .= ',%20'. $date->getValue('dat_country');
                        }
                        $locationHtml = '<a href="'. $location_url. '" target="_blank" title="'.$gL10n->get('DAT_SHOW_ON_MAP').'"/><strong>'.$date->getValue("dat_location").'</strong></a>';

                        // if valid login and enough information about adress exists - calculate the route
                        if($gValidLogin && strlen($gCurrentUser->getValue('ADDRESS')) > 0
                        && (  strlen($gCurrentUser->getValue('POSTCODE'))  > 0 || strlen($gCurrentUser->getValue('CITY'))  > 0 ))
                        {
                            $route_url = 'http://maps.google.com/?f=d&amp;saddr='. urlencode($gCurrentUser->getValue('ADDRESS'));
                            if(strlen($gCurrentUser->getValue('POSTCODE'))  > 0)
                            {
                                $route_url .= ',%20'. urlencode($gCurrentUser->getValue('POSTCODE'));
                            }
                            if(strlen($gCurrentUser->getValue('CITY'))  > 0)
                            {
                                $route_url .= ',%20'. urlencode($gCurrentUser->getValue('CITY'));
                            }
                            if(strlen($gCurrentUser->getValue('COUNTRY'))  > 0)
                            {
                                $route_url .= ',%20'. urlencode($gCurrentUser->getValue('COUNTRY'));
                            }

                            $route_url .= '&amp;daddr='. urlencode($date->getValue('dat_location'));
                            if(strlen($date->getValue('dat_country')) > 0)
                            {
                                // With information about country Google finds  the location much better
                                $route_url .= ',%20'. $date->getValue('dat_country');
                            }
                            $locationHtml .= '
                                <span class="iconTextLink">&nbsp;&nbsp;<a href="'. $route_url. '" target="_blank"><img 
                                    src="'. THEME_PATH. '/icons/map.png" alt="'.$gL10n->get('SYS_SHOW_ROUTE').'" title="'.$gL10n->get('SYS_SHOW_ROUTE').'"/></a>
                                </span>';
                        }
                        // if active, then show room information
                        if($date->getValue('dat_room_id') > 0)
                        {
                            $room = new TableRooms($gDb, $date->getValue('dat_room_id'));
                            $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                            $locationHtml .= ' <strong>(<a rel="colorboxHelp" href="'.$roomLink.'">'.$room->getValue('room_name').'</a>)</strong>';
                        }
                    } 
                    else
                    {
                        $locationHtml = '<strong>'. $date->getValue('dat_location'). '</strong>';
                    }

                    $dateElements[] = array($gL10n->get('DAT_LOCATION'), $locationHtml);
                }
                elseif($date->getValue('dat_room_id') > 0)
                {
                    // if active, then show room information
                    $room = new TableRooms($gDb, $date->getValue('dat_room_id'));
                    $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                    $locationHtml = '<strong><a rel="colorboxHelp" href="'.$roomLink.'">'.$room->getValue('room_name').'</a></strong>';
                    $dateElements[] = array($gL10n->get('DAT_LOCATION'), $locationHtml);
                }

                // write participants in array
                if($date->getValue('dat_rol_id') > 0)
                {
                    if($date->getValue('dat_max_members')!=0)
                    {
                        $sql = 'SELECT DISTINCT mem_usr_id 
                                  FROM '.TBL_MEMBERS.' 
                                 WHERE mem_rol_id = '.$date->getValue('dat_rol_id').'
                                   AND mem_begin <= \''.DATE_NOW.'\'
                                   AND mem_end    > \''.DATE_NOW.'\'';
                        $result = $gDb->query($sql);
                        $row_count = $gDb->num_rows($result);
                                    
                        $participantsHtml = '<strong>'.$row_count.'</strong>';
                    }
                    else 
                    {
                        $participantsHtml = '<strong>'.$gL10n->get('SYS_UNLIMITED').'</strong>';
                    }
                    $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), $participantsHtml);
                }

                // Output of elements 
                // always 2 then line break
                echo '<table style="width: 100%; border-width: 0px;">';
                foreach($dateElements as $element)
                {
                    if($firstElement)
                    {
                        echo '<tr>';
                    }
                
                    echo '<td style="width: 15%">'.$element[0].':</td>
                    <td style="width: 35%">'.$element[1].'</td>';
                
                    if($firstElement)
                    {
                        $firstElement = false;
                    }
                    else
                    {
                        echo '</tr>';
                        $firstElement = true;
                    }
                }
                echo '</table>';

                // Show discription
                echo '<div class="date_description" style="clear: left;">'.$date->getValue('dat_description').'</div>';

                if($date->getValue('dat_rol_id') > 0)
                {
                    // Link for the Agreement in Array
                    
                    if($date->getValue('dat_rol_id') > 0)
                    {
                        if($row['member_date_role'] > 0)
                        {
                            $registrationHtml = '<span class="iconTextLink">
                                    <a href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id').'"><img 
                                        src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" /></a>
                                    <a href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id').'">'.$gL10n->get('DAT_CANCEL').'</a>
                                </span>';
                        }
                        else
                        {
                            $available_signin = true;
                            $non_available_rols = array();
                            if($date->getValue('dat_max_members'))
                            {
                                // Limit for participiants
                                $sql = 'SELECT DISTINCT mem_usr_id FROM '.TBL_MEMBERS.'
                                         WHERE mem_rol_id = '.$date->getValue('dat_rol_id').' 
										   AND mem_leader = 0';
                                $res_num = $gDb->query($sql);
                                $row_num = $gDb->num_rows($res_num);
                                if($row_num >= $date->getValue('dat_max_members'))
                                {
                                    $available_signin = false;
                                }
                            }

                            if($available_signin)
                            {
                                $buttonURL = $g_root_path.'/adm_program/modules/dates/dates_function.php?mode=3&amp;dat_id='.$date->getValue('dat_id');

                                $registrationHtml = '<span class="iconTextLink">
                                    <a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'" /></a>
                                    <a href="'.$buttonURL.'">'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'</a>
                                </span>';
                            }
                            else
                            {
                                $registrationHtml = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                            }
                        }
                        
						// Link to participiants list
                        if($gValidLogin)
                        {
                            $registrationHtml .= '&nbsp;
                            <span class="iconTextLink">
                                <a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id').'"><img 
                                    src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" /></a>
                                 <a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id').'">'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'</a>
                            </span>';
                        }

						// Link for managing new participiants
                        if($row['mem_leader'] == 1)
                        {
                            $registrationHtml .= '&nbsp;
                            <span class="iconTextLink">
                                <a href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$date->getValue('dat_rol_id').'"><img 
                                    src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" /></a>
                                 <a href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$date->getValue('dat_rol_id').'">'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'</a>
                            </span>';
                        }

                        echo '<div>'.$registrationHtml.'</div>';
                    }
                }

                // Show date of create and changes
                echo '<div class="editInformation">'.
                    $gL10n->get('SYS_CREATED_BY', $row['create_firstname']. ' '. $row['create_surname'], $date->getValue('dat_timestamp_create'));

                    if($date->getValue('dat_usr_id_change') > 0)
                    {
                        echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $row['change_firstname']. ' '. $row['change_surname'], $date->getValue('dat_timestamp_change'));
                    }
                echo '</div>
            </div>
        </div>';
    }  // End While-Schleife
}

// Navigation with forward and backwards buttons
$base_url = $g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat;
echo admFuncGeneratePagination($base_url, $num_dates, $dates_per_page, $getStart, TRUE);

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>