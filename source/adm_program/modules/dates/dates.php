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
 * mode: actual       - (Default) shows actual dates and all events in future
 *       old          - shows events in the past
 *       period       - shows all events in a specified period (date_from/date_to)
 *       day          - shows all events of a specified day (date_from)
 *       all          - shows all events in past and future
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
 * view_mode          - content output in 'html' or 'print' view
 *                      (Default: 'html')
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_category.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_rooms.php');
require_once('../../system/classes/module_dates.php');
require_once('../../system/classes/participants.php');
unset($_SESSION['dates_request']);


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
//Object erzeugen
$dates = new Dates();

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', 'actual', false, $dates->getModes());
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string');
$getDateId   = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$getDate     = admFuncVariableIsValid($_GET, 'date', 'numeric');
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getCalendarSelection = admFuncVariableIsValid($_GET, 'calendar-selection', 'boolean', $gPreferences['dates_show_calendar_select']);
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date', DATE_NOW, false);
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date', '9999-12-31', false);
$getViewMode = admFuncVariableIsValid($_GET, 'view_mode', 'string', 'html', false, $dates->getViewModes());

 
// if exact date is set than convert it to our new syntax with dateFrom and dateTo
if(strlen($getDate) > 0)
{
    $getDateFrom = substr($getDate,0,4). '-'. substr($getDate,4,2). '-'. substr($getDate,6,2);
    $getDateTo   = $getDateFrom;
}

//autoset mode
if($getMode=='actual')
{
    if($getDateFrom==$getDateTo)
    {
        $getMode='day';
    }
    elseif($getDateFrom!=DATE_NOW && $getDateTo!='9999-12-31')
    {
        $getMode='period';
    }
}

//select dates
if($getDateId > 0)
{
    $dates->setDateId($getDateId);
}
else
{
    $dates->setMode($getMode, $getDateFrom, $getDateTo);
    
    if($getCatId > 0)
    {
        $dates->setCatId($getCatId);
    }   
}

//Convert dates to system format
$objDate = new DateTimeExtended($dates->getDateFrom(), 'Y-m-d', 'date');
$dateFromSystemFormat = $objDate->format($gPreferences['system_date']);

$objDate = new DateTimeExtended($dates->getDateTo(), 'Y-m-d', 'date');
$dateToSystemFormat = $objDate->format($gPreferences['system_date']);

// get headline of dates relative to date values
$htmlHeadline = $dates->getHeadline($getHeadline, $getDateFrom, $getDateTo);

// Fill input fields only if user values exists
$dateFromHtmlOutput = $dates->getFormValue($getDateFrom, DATE_NOW);
$dateToHtmlOutput = $dates->getFormValue($getDateTo, '9999-12-31');

if($getCatId > 0)
{
    $calendar = new TableCategory($gDb, $getCatId);
}

// Navigation starts here
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

// Number of events each page for default view 'html'
if($gPreferences['dates_per_page'] > 0 && $getViewMode == 'html')
{
    $dates_per_page = $gPreferences['dates_per_page'];
}
else
{
    $dates_per_page = $dates->getDatesCount();
}

// read all events for output
$datesResult = $dates->getDates($getStart, $dates_per_page);

if($datesResult['totalCount'] != 0)
{   
    // Initialize counter and object instances
    $count = 0;
    $date = new TableDate($gDb);
    $participants = new Participants ($gDb);
    
    // New array for the participants of a date
    $memberElements = array();
    
    // Loop date array and add further information in right position of each date.
    foreach($datesResult['dates'] as $row)
    {
        $date->readDataById($row['dat_id']);

        // get avaiable room information
        if($date->getValue('dat_room_id') > 0)
        {
            $room = new TableRooms($gDb, $date->getValue('dat_room_id'));
            $datesResult['dates'][''.$count.'']['room_name'] = $room->getValue('room_name');
        }
        
        // count members and leaders of the date role and push the result to the array
        if($date->getValue('dat_rol_id')!= null)
        {   
            $datesResult['dates'][''.$count.'']['dat_num_members'] = $participants->getCount($date->getValue('dat_rol_id'));
            $datesResult['dates'][''.$count.'']['dat_num_leaders'] = $participants->getNumLeaders($date->getValue('dat_rol_id'));
        }
        
        // For print view also read the participants and push the result to the array with index 'dat_rol_id'
        if($getViewMode == 'print')
            {    
                if($date->getValue('dat_rol_id') > 0)
                {
                    $memberElements[$date->getValue('dat_rol_id')] = $participants->getParticipantsArray($date->getValue('dat_rol_id'));
                }
            }

        $count++;        
    }
}

If($getViewMode == 'html')
{
    // Html-Head output
    if($getCatId > 0)
    {
        $gLayout['title'] = $htmlHeadline. ' - '. $calendar->getValue('cat_name');
    }
    else
    {
        $gLayout['title'] = $htmlHeadline;
    }
    if($getMode == 'old')
    {
        $gLayout['title'] = $gL10n->get('DAT_PREVIOUS_DATES', ' '.$gLayout['title']);
    }
    
    if($gPreferences['enable_rss'] == 1 && $gPreferences['enable_dates_module'] == 1)
    {
        $gLayout['header'] =  '<link rel="alternate" type="application/rss+xml" title="'.$gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$htmlHeadline).'"
            href="'.$g_root_path.'/adm_program/modules/dates/rss_dates.php?headline='.$htmlHeadline.'" />';
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
                if ($("#admCalendar").selectedIndex != 0) {
                    var calendarId = $("#admCalendar").val();
                } 
                self.location.href = "dates.php?mode='.$getMode.'&headline='.$htmlHeadline.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat.'&cat_id=" + calendarId;
            });
        });
    
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
    
    //--></script>  
    
    <h1 class="moduleHeadline">'. $gLayout['title']. '</h1>';  
    
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
                    <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$htmlHeadline.'"><img
                    src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_VAR', $htmlHeadline).'" /></a>
                    <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$htmlHeadline.'">'.$gL10n->get('SYS_CREATE_VAR', $htmlHeadline).'</a>
                </span>
            </li>';
        }
    
        if(($getCalendarSelection == 1) && ($getDateId == 0))   
        {
            //ical Download
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $topNavigation .= '<li><span class="iconTextLink">
                    <a href="'.$g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$htmlHeadline.'&amp;cat_id='.$getCatId.'"><img
                        src="'. THEME_PATH. '/icons/database_out.png" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'"/></a>
                    <a href="'.$g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$htmlHeadline.'&amp;cat_id='.$getCatId.'">'.$gL10n->get('DAT_EXPORT_ICAL').'</a>
                </span></li>';
            }
                        
            // If valid login show print button
            if($gValidLogin)
            {
                $topNavigation .= '<li><span class="iconTextLink">
                    <a href="#" onclick="window.open(\''.$g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$htmlHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getDateFrom().'&date_to='.$dates->getDateTo().'&view_mode=print\', \'_blank\')"><img
                        src="'. THEME_PATH. '/icons/print.png" alt="'.$gL10n->get('LST_PRINT_PREVIEW').'" title="'.$gL10n->get('LST_PRINT_PREVIEW').'" /></a>
                    <a href="#" onclick="window.open(\''.$g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$htmlHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getDateFrom().'&date_to='.$dates->getDateTo().'&view_mode=print\', \'_blank\')">'.$gL10n->get('LST_PRINT_PREVIEW').'</a></span>
                    </li><li><dt><br /></dt></li>';
            }
            
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
        }
        
         //Input for Startdate and Enddate
            
           $topNavigation .= '
    
                <li>
                    <dl> 
                        <dt><br />
                                <span>
                                    <form name="Formular" action="'.$g_root_path.'/adm_program/modules/dates/dates.php"onsubmit="return Datefilter()">
                                        <label for="date_from" style="margin-left: 10px;">'.$gL10n->get('SYS_START').':</label>
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
       
        if($datesResult['totalCount'] == 0)
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
        // List events
        if($datesResult['numResults'] > 0)
        {
            foreach($datesResult['dates'] as $row)
            {
                // Initialize object and write new data
                //$date->clear();
                //$date->setArray($row);
                $date->readDataById($row['dat_id']);
                
                // Change css if date is hightlighted
                $cssClass = ($row['dat_highlight'] == 1) ? 'boxHeadHighlighted' : 'boxHead';

                echo '
                <div class="boxLayout" id="dat_'.$date->getValue('dat_id').'">
                    <div class="'.$cssClass.'">
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
                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$htmlHeadline.'"><img
                                        src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>
                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;headline='.$htmlHeadline.'"><img
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
                            $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                            $locationHtml = '<strong><a rel="colorboxHelp" href="'.$roomLink.'">'.$room->getValue('room_name').'</a></strong>';
                            $dateElements[] = array($gL10n->get('DAT_LOCATION'), $locationHtml);
                        }

                        // count participants of the date
                        if($date->getValue('dat_rol_id') > 0)
                        {
                            $leadersHtml = '-';

                            if($date->getValue('dat_max_members')!=0)
                            {
                                $participantsHtml = '<strong>'.$row['dat_num_members'].'</strong>';
                                $leadersHtml = '<strong>'.$row['dat_num_leaders'].'</strong>';
                            }
                            else
                            {
                                $participantsHtml = '<strong>'.$gL10n->get('SYS_UNLIMITED').'</strong>';
                                $leadersHtml = '0';
                            }
                            $dateElements[] = array($gL10n->get('SYS_LEADER'), $leadersHtml);
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
                                        // Check limit of participants
                                        if($participants->getLimit($date->getValue('dat_rol_id')) >= $date->getValue('dat_max_members'))
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

                        // show informations about user who creates the recordset and changed it
                        echo admFuncShowCreateChangeInfoByName($row['create_firstname']. ' '. $row['create_surname'], $date->getValue('dat_timestamp_create'), $row['change_firstname']. ' '. $row['change_surname'], $date->getValue('dat_timestamp_change')).'
                    </div>
                </div>';
            }  // End foreach
        
    }

    // Navigation with forward and backwards buttons
    $base_url = $g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$htmlHeadline.'&cat_id='.$getCatId.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat;
    echo admFuncGeneratePagination($base_url, $datesResult['totalCount'], $datesResult['limit'], $getStart, TRUE);

    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}
else
{
    // create print output in a new window and set view_mode back to default 'html' for back navigation in main window
    $gNavigation->addUrl($g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat);
    
    $calendar = new TableCategory($gDb, $getCatId);
    
    echo'
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
    <head>
        <!-- (c) 2004 - 2012 The Admidio Team - http://www.admidio.org -->
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <title>'. $gCurrentOrganization->getValue('org_longname'). ' - Terminliste </title>
        <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.js"></script>
        <link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/print.css" />
        
    <script type="text/javascript">
        $(document).ready(function(){
            $("#admSelectBox").change(function(){
                $("#" + "style" + this.value).show().siblings("tbody").hide();
            });
            <!-- Trigger -->
            $("#admSelectBox").change();
        })
    </script>
    </head>';
    
    // Get a copy of date results
    $dateElements = $datesResult['dates'];
    // Define options for selectbox
    $selectBoxEntries = array($gL10n->get('SYS_OVERVIEW'), $gL10n->get('SYS_DESCRIPTION'), $gL10n->get('SYS_PARTICIPANTS'));
    
    // define header and footer of the table
    echo' 
    <body>
        <div align="center">
            <table class="tableDateList" border="1" cellpadding="3" cellspacing="0" summary="">
                <thead>
                    <tr>
                        <td colspan="10">
                            <h1>'. $gCurrentOrganization->getValue('org_longname'). ' - ' .$gL10n->get('DAT_DATES'). ' ' . $calendar->getValue('cat_name') . '</h1>
                            <h3>'.$gL10n->get('SYS_START').':&nbsp;'.$dateFromSystemFormat. ' - ' .$gL10n->get('SYS_END').':&nbsp;'.$dateToSystemFormat.
                                '<span class="form"; style="margin-left: 40px;">'.
                                // Selectbox for table content
                                FormElements::generateDynamicSelectBox($selectBoxEntries, $defaultEntry = '0', $fieldId = 'admSelectBox', $createFirstEntry = false).
                                '</span>
                            </h3>
                        </td>

                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <td colspan="9" style="text-align: right;">
                            <i>provided by Admidio</i>
                            <i style="font-size: 0.6em;">'.date($gPreferences['system_date'].' '.$gPreferences['system_time']).'</i>
                        </td>
                    </tr>
                </tfoot>
        <tbody id="style0">';
        if(count($dateElements) == 0)
        {
            // No events found
            if($getDateId > 0)
            {
                echo '<tr><td width="800px">'.$gL10n->get('SYS_NO_ENTRY').'</td></tr>';
            }
            else
            {
                echo '<tr><td width="800px">'.$gL10n->get('SYS_NO_ENTRIES').'</td></tr>';
            }
        }
        else
        {  
            // define header of columns
            echo'<tr>
                    <th>' . $gL10n->get('SYS_START'). '</th>
                    <th>' . $gL10n->get('SYS_END'). '</th>
                    <th>' . $gL10n->get('SYS_TIME_FROM'). '</th>
                    <th>' . $gL10n->get('SYS_TIME_TO'). '</th>
                    <th>' . $gL10n->get('DAT_DATE'). '</th>
                    <th>' . $gL10n->get('SYS_LOCATION'). '</th>
                    <th>' . $gL10n->get('DAT_ROOM_INFORMATIONS'). '</th>
                    <th>' . $gL10n->get('SYS_LEADER'). '</th>
                    <th>' . $gL10n->get('SYS_PARTICIPANTS'). '</th>
                </tr>';
         
            // Write data to table body
            $numElement = 1;
            foreach($dateElements as $row)
            {
                //Convert dates to system format
                $objDateBegin = new DateTime ($row['dat_begin']);
                $dateBegin = $objDateBegin->format($gPreferences['system_date']);
                $dateStartTime = $objDateBegin->format($gPreferences['system_time']);
                
                $objDateEnd = new DateTime ($row['dat_end']);
                $dateEnd = $objDateEnd->format($gPreferences['system_date']);
                $dateEndTime = $objDateEnd->format($gPreferences['system_time']);
                
                // Change colors of each second row for visibilty
                // Change css if date is hightlighted
                if($row['dat_highlight'] != 1)
                {
                    $classValue = (($numElement % 2) == 0) ? 'even' : 'odd';
                }
                else
                {
                    $classValue = (($numElement % 2) == 0) ? 'evenHighlight' : 'oddHighlight';
                }
                
                echo '<tr class="'.$classValue.'">
                        <td>' . $dateBegin . '</td>
                        <td>' . $dateEnd . '</td>
                        <td>' . $dateStartTime . '</td>
                        <td>' . $dateEndTime . '</td>
                        <td style="text-align: left;">' . $row['dat_headline'] . '</td>
                        <td style="text-align: left;">' . $row['dat_location'] . '</td>
                        <td>';
                                if(isset($row['room_name']))
                                {
                                    echo $row['room_name'];
                                }
                echo'</td>  
                        <td style="text-align: center;">';
                                if(isset($row['dat_num_leaders']) && $row['dat_num_leaders']!= 0)
                                {
                                    echo $row['dat_num_leaders'];
                                }
                echo'</td>
                        <td style="text-align: center;">';
                                // Show number of participants of date
                                if(isset($row['dat_num_members']) && $row['dat_max_members'] == 0)
                                {
                                    echo $row['dat_num_members'];
                                }
                                // If date has limit for assignment also show the value    
                                if(isset($row['dat_num_members']) && $row['dat_max_members'] != 0)
                                {
                                    echo $row['dat_num_members'].' '.'('.$row['dat_max_members'].')';
                                }

                echo'</td>';
                $numElement++;
                
            }   // end forech
        
        echo'</tbody>
           
            <tbody id="style1">';
            
                echo'<tr>
                        <th>' . $gL10n->get('SYS_START'). '</th>
                        <th>' . $gL10n->get('SYS_END'). '</th>
                        <th>' . $gL10n->get('SYS_TIME_FROM'). '</th>
                        <th>' . $gL10n->get('SYS_TIME_TO'). '</th>
                        <th>' . $gL10n->get('DAT_DATE'). '</th>
                        <th>' . $gL10n->get('SYS_DESCRIPTION'). '</th>
                    </tr>';
                    
                $numElement = 1;
                foreach($dateElements as $row)
                {
                    //Convert dates to system format
                    $objDateBegin = new DateTime ($row['dat_begin']);
                    $dateBegin = $objDateBegin->format($gPreferences['system_date']);
                    $dateStartTime = $objDateBegin->format($gPreferences['system_time']);
        
                    $objDateEnd = new DateTime ($row['dat_end']);
                    $dateEnd = $objDateEnd->format($gPreferences['system_date']);
                    $dateEndTime = $objDateEnd->format($gPreferences['system_time']);
        
                    // Change colors of each second row for visibilty
                    // Change css if date is hightlighted
                    if($row['dat_highlight'] != 1)
                    {
                        $classValue = (($numElement % 2) == 0) ? 'even' : 'odd';
                    }
                    else
                    {
                        $classValue = (($numElement % 2) == 0) ? 'evenHighlight' : 'oddHighlight';
                    }

                    echo '<tr class="'.$classValue.'">
                            <td>' . $dateBegin . '</td>
                            <td>' . $dateEnd . '</td>
                            <td>' . $dateStartTime . '</td>
                            <td>' . $dateEndTime . '</td>
                            <td style="text-align: left;">' . $row['dat_headline'] . '</td>
                            <td style="text-align: left;">'.  $row['dat_description'] = preg_replace('/<[^>]*>/', '', $row['dat_description']) .'</td>
                        </tr>';
                   $numElement++; 
                } //end foreach
            
            echo'</tbody>';
            
            echo'<tbody id="style2">
                    <tr>
                        <th style="text-align: left;">' . $gL10n->get('SYS_START'). '<br />
                            ' . $gL10n->get('SYS_END'). '</th>
                        <th style="text-align: left;">' . $gL10n->get('SYS_TIME_FROM'). '<br />
                            ' . $gL10n->get('SYS_TIME_TO'). '</th>
                        <th>' . $gL10n->get('DAT_DATE'). '</th>
                        <th>' . $gL10n->get('SYS_PARTICIPANTS'). '</th>
                    </tr>';
                    
                    $numElement = 1;
                    foreach($dateElements as $row)
                    {
                        //Convert dates to system format
                        $objDateBegin = new DateTime ($row['dat_begin']);
                        $dateBegin = $objDateBegin->format($gPreferences['system_date']);
                        $dateStartTime = $objDateBegin->format($gPreferences['system_time']);
        
                        $objDateEnd = new DateTime ($row['dat_end']);
                        $dateEnd = $objDateEnd->format($gPreferences['system_date']);
                        $dateEndTime = $objDateEnd->format($gPreferences['system_time']);
        
                        // Change colors of each second row for visibilty
                        // Change css if date is hightlighted
                        if($row['dat_highlight'] != 1)
                        {
                            $classValue = (($numElement % 2) == 0) ? 'even' : 'odd';
                        }
                        else
                        {
                            $classValue = (($numElement % 2) == 0) ? 'evenHighlight' : 'oddHighlight';
                        }

                        echo '<tr class="'.$classValue.'">
                                <td>' . $dateBegin . '<br />
                                    ' . $dateEnd . '</td>
                                <td>' . $dateStartTime . '<br />
                                    ' . $dateEndTime . '</td>
                                <td style="text-align: left;">' . $row['dat_headline'] . '</td>
                                <td>';
    
                                        // If date has participation and patricipants are assigned
                                        if($row['dat_rol_id'] != null && isset($row['dat_num_members'])) 
                                        {
                                            echo '<table cellspacing="2" cellpadding="2"><tr>';
                                            
                                            // Linebreak after 5 entries
                                            $memberCount = 1;
                                            $totalMemberCount = count($memberElements[$row['dat_rol_id']]);
                                            
                                            foreach(($memberElements[$row['dat_rol_id']]) as $memberDate)
                                            {
                                                // If last entry close table row
                                                if($memberCount < $totalMemberCount)
                                                {
                                                    $line_break = (($memberCount % 5) == 0) ? '</td></tr><tr>' : '</td>';
                                                }
                                                else
                                                {   
                                                    $line_break = '</td></tr>';
                                                }
                                                    
                                                // Leaders are shown highlighted
                                                if($memberDate['leader'] != 0)
                                                {
                                                    echo '<td style="text-align: left;"><strong>'.$memberDate['surname'].' '.$memberDate['firstname'].'</strong>'.';'.$line_break.'';
                                                }
                                                else
                                                {
                                                        echo '<td style="text-align: left;">', $memberDate['surname'],' ',$memberDate['firstname'],';', $line_break.'';
                                                }
                                                    $memberCount++;
                                            }
                                            
                                            echo'</table>';
                                        }
                                        
                        echo'</td></tr>';
                        $numElement++;
                    }           
            echo'</tbody>';
        }
        echo'</table>
            </div>
    </body>';
}
?>