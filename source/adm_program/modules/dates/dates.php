<?php
/******************************************************************************
 * Show a list of all events
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
 * start              - Position of query recordset where the visual output should start
 * headline           - Headline shown over events
 *                      (Default) Dates
 * cat_id             - show all events of calendar with this id
 * id                 - Show only one event
 * date               - All events for a date are listed
 *                      Format: YYYYMMDD
 * calendar-selection - 1: The box is shown
 *                      0: The box is not shown
 *
 * date_from          - is set to actual date,
 *                      if no date information is delivered
 * date_to            - is set to 31.12.9999,
 *                      if no date information is delivered
 * view_mode          - content output in 'html', 'compact' or 'print' view
 *                      (Default: according to preferences)
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/html_table.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/module_dates.php');
require_once('../../system/classes/module_menu.php');
require_once('../../system/classes/participants.php');
require_once('../../system/classes/table_category.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_rooms.php');
unset($_SESSION['dates_request']);


// check if module is active
if($gPreferences['enable_dates_module'] == 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // module only for valid Users
    require_once('../../system/login_valid.php');
}
// create object
$dates = new ModuleDates();

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
$getViewMode = admFuncVariableIsValid($_GET, 'view_mode', 'string', $gPreferences['dates_viewmode'], false, $dates->getViewModes());

// if exact date is set then convert it to our new syntax with dateFrom and dateTo
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

// Fill input fields only if user values exist
$dateFromHtmlOutput = $dates->getFormValue($getDateFrom, DATE_NOW);
$dateToHtmlOutput = $dates->getFormValue($getDateTo, '9999-12-31');

if($getCatId > 0)
{
    $calendar = new TableCategory($gDb, $getCatId);
}

// Navigation starts here
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

// Number of events each page for default view 'html' or 'compact' view
if($gPreferences['dates_per_page'] > 0 && ( $getViewMode == 'html' || $getViewMode == 'compact'))
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

if($getViewMode == 'html'  || $getViewMode == 'compact')
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

    $gLayout['header'] .= '
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
    //--></script>';

    require(SERVER_PATH. '/adm_program/system/overall_header.php');

    // Html Output

    echo '<h1 class="moduleHeadline">'. $gLayout['title']. '</h1>';

    //Check if box must be shown, when more dates available
    if((($getCalendarSelection == 1) && ($getDateId == 0)) || $gCurrentUser->editDates())
    {
        // create module menu
        $DatesMenu = new ModuleMenu('admMenuDates');


        //Add new event
        if($gCurrentUser->editDates())
        {
            $DatesMenu->addItem('admMenuItemAdd', $g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$htmlHeadline,
                                $gL10n->get('SYS_CREATE_VAR', $htmlHeadline), 'add.png' );
        }

        if($getDateId == 0)
        {
            if($getCalendarSelection == 1)
            {
                // show selectbox with all calendars
                $DatesMenu->addCategoryItem('admMenuItemCategory', 'DAT', $getCatId, 'dates.php?headline='.$getHeadline.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat.'&cat_id=',
                                    $gL10n->get('DAT_CALENDAR'), $gCurrentUser->editDates());
            }
            elseif($gCurrentUser->editDates())
            {
                // if no calendar selectbox is shown, then show link to edit calendars
                $DatesMenu->addItem('admMenuItemCategories', '/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR'),
                                    $gL10n->get('DAT_MANAGE_CALENDARS'), 'edit.png');
            }


            //ical Download
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $DatesMenu->addItem('admMenuItemICal', $g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$htmlHeadline.'&amp;cat_id='.$getCatId,
                                $gL10n->get('DAT_EXPORT_ICAL'), 'database_out.png' );
            }

            // If valid login show print button
            if($gValidLogin)
            {
                $DatesMenu->addItem('admMenuItemPrint', '',
                                $gL10n->get('LST_PRINT_PREVIEW'), 'print.png', 'window.open(\''.$g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$htmlHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getDateFrom().'&date_to='.$dates->getDateTo().'&view_mode=print\', \'_blank\')' );
            }

            if($gCurrentUser->isWebmaster())
            {
                // show link to system preferences of weblinks
                $DatesMenu->addItem('admMenuItemPreferencesLinks', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=DAT_DATES',
                                    $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
            }

            $DatesMenu->show();
        }

         //Input for Startdate and Enddate
        echo '
        <div class="navigationPath">
            <form name="Formular" action="'.$g_root_path.'/adm_program/modules/dates/dates.php" onsubmit="return Datefilter()">
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

                <span style="margin-left: 5px;">&nbsp;</span>
                <input type="hidden" id="cat_id" name="cat_id" value="'.$getCatId.'">
                <input type="submit" value="OK">
                <span style="margin-left: 5px;">&nbsp;</span>
                <input type="button" onclick="window.location.href = \''.$g_root_path.'/adm_program/modules/dates/dates.php\'" value="'.$gL10n->get('SYS_DELETE').'">
            </form>
        </div>';
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
        // Output table header for compact view
        if ($getViewMode == 'compact')
        {
            $compactTable = new htmlTable();
            $compactTable->setAttribute('class', 'tableList', 'table')
                         ->setAttribute('style', 'width: 100%;', 'table')
                         ->setAttribute('cellspacing', '0', 'table')
                         ->setElement('thead')
                         ->setElement('tr')
                         ->setElement('th', '&nbsp;')
                         ->setElement('th')
                         ->setAttribute('colspan', '2', 'th')
                         ->setData($gL10n->get('SYS_START'))
                         ->setElement('th', $gL10n->get('DAT_DATE'))
                         ->setElement('th')
                         ->setAttribute('colspan', '2', 'th')
                         ->setData($gL10n->get('SYS_PARTICIPANTS'))
                         ->setElement('th', $gL10n->get('DAT_LOCATION'))
                         ->setElement('tbody');
        }
        foreach($datesResult['dates'] as $row)
        {
            // Initialize object and write new data
            $date->readDataById($row['dat_id']);

            $endDate='';
            if($date->getValue('dat_begin', $gPreferences['system_date']) != $date->getValue('dat_end', $gPreferences['system_date']))
            {
                $endDate=$date->getValue('dat_end', $gPreferences['system_date']);
            }

            //ical Download
            $icalIcon="";
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $icalIcon = '<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='. $date->getValue('dat_id'). '&amp;mode=6"><img
                src="'. THEME_PATH. '/icons/database_out.png" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'" /></a>';
            }

            // change and delete is only for users with additional rights
            $copyIcon = '';
            $editIcon = '';
            $deleteIcon = '';
            if ($gCurrentUser->editDates())
            {
                if($date->editRight() == true)
                {
                    $copyIcon = '
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$htmlHeadline.'"><img
                        src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>';
                    $editIcon = '
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;headline='.$htmlHeadline.'"><img
                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
                }

                // Deleting events is only allowed for group members
                if($date->getValue('cat_org_id') == $gCurrentOrganization->getValue('org_id'))
                {
                    $deleteIcon = '
                    <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=dat&amp;element_id=dat_'.
                        $date->getValue('dat_id').'&amp;name='.urlencode($date->getValue('dat_begin', $gPreferences['system_date']).' '.$date->getValue('dat_headline')).'&amp;database_id='.$date->getValue('dat_id').'"><img
                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                }
            }

            // Initialize variables
            $registerIcon='&nbsp;';
            $registerText='';
            $participantIcon='';
            $participantText='';
            $mgrpartIcon='';
            $mgrpartText='';
            $dateElements = array();
            $firstElement = true;
            $maxMembers='';
            $numMembers='';
            $leadersHtml='';
            $locationHtml='';

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
                // Show map link, when at least 2 words available
                // having more than 3 characters each
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

                    // if valid login and enough information about address exist - calculate the route
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
                            // With information about country Google finds the location much better
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

                $numMembers = $row['dat_num_members'];
                if($date->getValue('dat_max_members')!=0)
                {
                    $participantsHtml = '<strong>'.$row['dat_num_members'].'</strong>';
                    $leadersHtml = $row['dat_num_leaders'];
                    $maxMembers = $date->getValue('dat_max_members');
                }
                else
                {
                    $participantsHtml = '<strong>'.$gL10n->get('SYS_UNLIMITED').'</strong>';
                    $leadersHtml = '0';
                    $maxMembers = '&infin;';
                }
                $dateElements[] = array($gL10n->get('SYS_LEADER'), '<strong>'.$leadersHtml.'</strong>');
                $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), $participantsHtml);
            }

            if($date->getValue('dat_rol_id') > 0)
            {
                // Link for the Agreement in Array

                if($date->getValue('dat_rol_id') > 0)
                {
                    if($row['member_date_role'] > 0)
                    {
                        $buttonURL = $g_root_path.'/adm_program/modules/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id');
                        $registerIcon = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" /></a>';
                        $registerText = '<a href="'.$buttonURL.'">'.$gL10n->get('DAT_CANCEL').'</a>';
                    }
                    else
                    {
                        $available_signin = true;
                        $non_available_rols = array();
                        if($date->getValue('dat_max_members'))
                        {
                            // Check limit of participants
                            if($participants->getCount($date->getValue('dat_rol_id')) >= $date->getValue('dat_max_members'))
                            {
                                $available_signin = false;
                            }
                        }

                        if($available_signin)
                        {
                            $buttonURL = $g_root_path.'/adm_program/modules/dates/dates_function.php?mode=3&amp;dat_id='.$date->getValue('dat_id');
                            $registerIcon = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'" /></a>';
                            $registerText = '<a href="'.$buttonURL.'">'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'</a>';
                        }
                        else
                        {
                            $registerText = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                        }
                    }

                    // Link to participants list
                    if($gValidLogin)
                    {
                        if ($leadersHtml + $numMembers > 0)
                        {
                            $buttonURL = $g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id');
                            $participantIcon = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" /></a>';
                            $participantText = '<a href="'.$buttonURL.'">'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'</a>';
                        }
                    }

                    // Link for managing new participants
                    if($row['mem_leader'] == 1)
                    {
                        $buttonURL = $g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$date->getValue('dat_rol_id');
                        $mgrpartIcon = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" /></a>';
                        $mgrpartText = '<a href="'.$buttonURL.'">'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'</a>';
                    }
                }
            }

            if ($getViewMode == 'html')
            {
                // Change css if date is highlighted
                $cssClass = ($row['dat_highlight'] == 1) ? 'boxHeadHighlighted' : 'boxHead';

                // Output of elements
                // always 2 then line break
                $eventDetails = new htmlTable();
                $eventDetails->setAttribute('style', 'width: 100%,')
                             ->setAttribute('border-width', '0px;');

                foreach($dateElements as $element)
                {
                    if($firstElement)
                    {
                        $eventDetails->setElement('tr');
                    }
                    $eventDetails->setElement('td')
                                 ->setAttribute('style', 'width: 15%;')
                                 ->setData($element[0].':')
                                 ->setElement('td')
                                 ->setAttribute('style', 'width: 35%;')
                                 ->setData($element[1]);

                    if($firstElement)
                    {
                        $firstElement = false;
                    }
                    else
                    {
                        $firstElement = true;
                    }
                }
                $tableEventDetails = $eventDetails->getHtmlTable();

                echo '
                <div class="boxLayout" id="dat_'.$date->getValue('dat_id').'">
                    <div class="'.$cssClass.'">
                        <div class="boxHeadLeft">
                            <img src="'. THEME_PATH. '/icons/dates.png" alt="'. $date->getValue('dat_headline'). '" />' .
                            $date->getValue('dat_begin', $gPreferences['system_date']);
                            echo $endDate ? ' - ' : '';
                            echo $endDate . ' ' . $date->getValue('dat_headline') . '
                        </div>
                        <div class="boxHeadRight">' .
                            $icalIcon . $copyIcon . $editIcon . $deleteIcon . '
                        </div>
                    </div>

                    <div class="boxBody">
                    ' . $tableEventDetails . '
                        <div class="date_description" style="clear: left;">' . $date->getValue('dat_description') . '</div>
                    <div>';
                        if ($registerText)
                        {
                            echo '
                            <span class="iconTextLink">
                                ' . $registerIcon .' '. $registerText . '
                            </span>&nbsp;';
                        }
                        if ($participantText)
                        {
                            echo '
                            <span class="iconTextLink">
                                ' . $participantIcon .' '. $participantText . '
                            </span>&nbsp;';
                        }
                        if ($mgrpartText)
                        {
                            echo '
                            <span class="iconTextLink">
                                ' . $mgrpartIcon .' '. $mgrpartText . '
                            </span>';
                        }
                        echo '</div>';
                        // show information about user who created the recordset and changed it
                        echo admFuncShowCreateChangeInfoByName($row['create_name'], $date->getValue('dat_timestamp_create'),
                           $row['change_name'], $date->getValue('dat_timestamp_change'), $date->getValue('dat_usr_id_create'), $date->getValue('dat_usr_id_change')).'
                    </div>
                </div>';
            }
            else
            {
                // Change css if date is highlighted
                $cssWeight = ($row['dat_highlight'] == 1) ? 'bold' : 'normal';

                $objDateBegin = new DateTime ($row['dat_begin']);
                $dateBegin = $objDateBegin->format($gPreferences['system_date']);
                $timeBegin = '';
                if ($date->getValue('dat_all_day') == 0)
                {
                    $timeBegin = $date->getValue('dat_begin', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK');
                }
                else
                {
                    $timeBegin = '&nbsp;';
                }


                $compactTable->setElement('tr')
                             ->setAttribute('class', 'tableMouseOver')
                             ->setElement('td', $registerIcon)
                             ->setElement('td', $dateBegin)
                             ->setElement('td', $timeBegin)
                             ->setElement('td')
                             ->setAttribute('style', 'font-weight:'. $cssWeight .'')
                             ->setData('<a href="'.$g_root_path.'/adm_program/modules/dates/dates.php?id='.$date->getValue('dat_id').'&amp;headline='.$date->getValue('dat_headline').'">'.$date->getValue('dat_headline').'</a>')
                             ->setElement('td', ($leadersHtml > 0 ? $leadersHtml .'+' : '' ) .$numMembers .'/'. $maxMembers)
                             ->setElement('td', ($leadersHtml+$numMembers > 0 ? $participantIcon : '&nbsp;'))
                             ->setElement('td', $locationHtml);
            }
        }  // End foreach

        // Output table bottom for compact view
        if ($getViewMode == 'compact')
        {
            $htmlCompactTable = $compactTable->getHtmlTable();
            echo $htmlCompactTable;
        }
    }

    // If neccessary show links to navigate to next and previous recordsets of the query
    $base_url = $g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$htmlHeadline.'&cat_id='.$getCatId.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat.'&view_mode='.$getViewMode;
    echo admFuncGeneratePagination($base_url, $datesResult['totalCount'], $datesResult['limit'], $getStart, TRUE);

    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}
else
{
    // create print output in a new window and set view_mode back to default 'html' for back navigation in main window
    $gNavigation->addUrl($g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dateFromSystemFormat.'&date_to='.$dateToSystemFormat);

    $calendar = new TableCategory($gDb, $getCatId);
    // Get a copy of date results
    $dateElements = $datesResult['dates'];
    // Define options for selectbox
    $selectBoxEntries = array($gL10n->get('SYS_OVERVIEW'), $gL10n->get('SYS_DESCRIPTION'), $gL10n->get('SYS_PARTICIPANTS'));
    // Define header and footer content for the html table
    $tableHead = '<h1>'. $gCurrentOrganization->getValue('org_longname'). ' - ' .$gL10n->get('DAT_DATES'). ' ' . $calendar->getValue('cat_name') . '</h1>
                    <h3>'.$gL10n->get('SYS_START').':&nbsp;'.$dateFromSystemFormat. ' - ' .$gL10n->get('SYS_END').':&nbsp;'.$dateToSystemFormat.
                        '<span class="form" style="margin-left: 40px;">'.
                        // Selectbox for table content
                        FormElements::generateDynamicSelectBox($selectBoxEntries, $defaultEntry = '0', $fieldId = 'admSelectBox', $createFirstEntry = false).'
                        </span>
                    </h3>';

    $tableFooter = '<i>provided by Admidio</i>
                    <i style="font-size: 0.6em;">'.date($gPreferences['system_date'].' '.$gPreferences['system_time']).'</i>';

    // Define columns headlines for body elements
    $bodyHeadline_1 = array($gL10n->get('SYS_START'),
                            $gL10n->get('SYS_END'),
                            $gL10n->get('SYS_TIME_FROM'),
                            $gL10n->get('SYS_TIME_TO'),
                            $gL10n->get('DAT_DATE'),
                            $gL10n->get('SYS_LOCATION'),
                            $gL10n->get('DAT_ROOM_INFORMATIONS'),
                            $gL10n->get('SYS_LEADER'),
                            $gL10n->get('SYS_PARTICIPANTS')
                            );

    $bodyHeadline_2 = array($gL10n->get('SYS_START'),
                            $gL10n->get('SYS_END'),
                            $gL10n->get('SYS_TIME_FROM'),
                            $gL10n->get('SYS_TIME_TO'),
                            $gL10n->get('DAT_DATE'),
                            $gL10n->get('SYS_DESCRIPTION'));

    $bodyHeadline_3 = array($gL10n->get('SYS_START'),
                            $gL10n->get('SYS_END'),
                            $gL10n->get('SYS_TIME_FROM'),
                            $gL10n->get('SYS_TIME_TO'),
                            $gL10n->get('DAT_DATE'),
                            $gL10n->get('SYS_PARTICIPANTS'));

    $body_1 = array();
    $body_2 = array();
    $body_3 = array();

    // Get dates and  configure table bodies
    $numElement = 1;
    foreach($dateElements as $row)
    {
        $buffer = array();

        //Convert dates to system format
        $objDateBegin = new DateTime ($row['dat_begin']);
        $dateBegin = $objDateBegin->format($gPreferences['system_date']);
        $dateStartTime = $objDateBegin->format($gPreferences['system_time']);

        $objDateEnd = new DateTime ($row['dat_end']);
        $dateEnd = $objDateEnd->format($gPreferences['system_date']);
        $dateEndTime = $objDateEnd->format($gPreferences['system_time']);

        // Write formated date parameter in buffer
        $buffer['dat_highlight']      = ($row['dat_highlight'] == 1) ? '1' : '0';
        $buffer['dat_begin']          = $dateBegin;
        $buffer['dat_end']            = $dateEnd;
        $buffer['dat_starttime']      = $dateStartTime;
        $buffer['dat_endtime']        = $dateEndTime;
        $buffer['dat_headline']       = $row['dat_headline'];
        $buffer['dat_description']    = preg_replace('/<[^>]*>/', '', $row['dat_description']);
        $buffer['dat_location']       = $row['dat_location'];
        $buffer['room_name']          = (isset($row['room_name'])) ? $row['room_name'] : '';
        $buffer['dat_num_leaders']    = (isset($row['dat_num_leaders']) && $row['dat_num_leaders']!= 0) ? $row['dat_num_leaders'] : '';
        $buffer['dat_participation']  = '';

        // Show number of participants of date
        if(isset($row['dat_num_members']) && $row['dat_max_members'] == 0)
        {
            $buffer['dat_participation'] = $row['dat_num_members'];
        }
        // If date has limit for assignment also show the value
        if(isset($row['dat_num_members']) && $row['dat_max_members'] != 0)
        {
            $buffer['dat_participation'] = $row['dat_num_members'].' '.'('.$row['dat_max_members'].')';
        }

        // If date has participation and patricipants are assigned
        if($row['dat_rol_id'] != null && isset($row['dat_num_members']))
        {
            $dateParticipation = new htmlTable();
            $dateParticipation->setAttribute('class', 'dateParticipation', 'table')
                              ->setAttribute('cellspacing', '2', 'table')
                              ->setAttribute('cellpadding', '2', 'table')
                              ->setElement('tr');
            // Linebreak after 5 entries
            $memberCount = 1;
            $totalMemberCount = count($memberElements[$row['dat_rol_id']]);

            foreach(($memberElements[$row['dat_rol_id']]) as $memberDate)
            {
                // If last entry close table row
                if($memberCount < $totalMemberCount)
                {
                    $line_break = (($memberCount % 6) == 0) ? 'tr' : 'td';
                    $dateParticipation->setElement($line_break);
                }
                // Leaders are shown highlighted
                if($memberDate['leader'] != 0)
                {
                    $dateParticipation->setAttribute('class', 'left', 'td')
                                      ->setData('<strong>'.$memberDate['surname'].' '.$memberDate['firstname'].'</strong>'.';');
                }
                else
                {
                    $dateParticipation->setAttribute('class', 'left', 'td')
                                      ->setData($memberDate['surname'].' '.$memberDate['firstname'].';');
                }
                $memberCount++;
            }

            $tableParticipants = $dateParticipation->getHtmlTable();
        }
        else
        {
            $tableParticipants = '';
        }

        // Configure table body contents
        $body_1[$numElement]['dat_highlight']   = $buffer['dat_highlight'];
        $body_1[$numElement]['dat_details']     = array($buffer['dat_begin'],
                                                        $buffer['dat_end'],
                                                        $buffer['dat_starttime'],
                                                        $buffer['dat_endtime'],
                                                        $buffer['dat_headline'],
                                                        $buffer['dat_location'],
                                                        $buffer['room_name'],
                                                        $buffer['dat_num_leaders'],
                                                        $buffer['dat_participation']);

        $body_2[$numElement]['dat_highlight']   = $buffer['dat_highlight'];
        $body_2[$numElement]['dat_details']     = array($buffer['dat_begin'],
                                                        $buffer['dat_end'],
                                                        $buffer['dat_starttime'],
                                                        $buffer['dat_endtime'],
                                                        $buffer['dat_headline'],
                                                        $buffer['dat_description']);

        $body_3[$numElement]['dat_highlight']   = $buffer['dat_highlight'];
        $body_3[$numElement]['dat_details']     = array($buffer['dat_begin'],
                                                        $buffer['dat_end'],
                                                        $buffer['dat_starttime'],
                                                        $buffer['dat_endtime'],
                                                        $buffer['dat_headline'],
                                                        $tableParticipants);
        $numElement++;
        unset ($buffer);
    }  // end foreach

    // Create table object
    $datePrint = new htmlTable();
    $datePrint->setAttribute('id', 'PrintViewDates', 'table')
              ->setAttribute('class', 'tableDateList', 'table')
              ->setAttribute('border', '1', 'table')
              ->setAttribute('cellpadding', '3', 'table')
              ->setAttribute('summary', 'Printview of dates', 'table');
    // Define thead
    $datePrint->setElement('thead')
              ->setElement('tr')
              ->setElement('td')
              ->setAttribute('colspan', '10', 'td')
              ->setData($tableHead);
    // Define tfoot
    $datePrint->setElement('tfoot')
              ->setElement('tr')
              ->setElement('td')
              ->setAttribute('colspan', '9', 'td')
              ->setAttribute('style', 'text-align: right;', 'td')
              ->setData($tableFooter);

    // Define tbody
    $datePrint->setElement('tbody')
              ->setAttribute('id', 'style0', 'tbody')
              ->setElement('th', $bodyHeadline_1);
    if(count($dateElements) == 0)
    {
        // No events found
        if($getDateId > 0)
        {
            $datePrint->setElement('tr', $gL10n->get('SYS_NO_ENTRY'));
        }
        else
        {
            $datePrint->setElement('tr', $gL10n->get('SYS_NO_ENTRIES'));
        }
    }
    else
    {
        // Write first body content
        $numDateElements = 1;
        foreach($body_1 as $row)
        {
            if($row['dat_highlight'] != 1)
            {
                $className = (($numDateElements % 2) == 0) ? 'even' : 'odd';
            }
            else
            {
                $className = (($numDateElements % 2) == 0) ? 'evenHighlight' : 'oddHighlight';
            }
            $datePrint->setElement('tr')
                      ->setAttribute('class', $className)
                      ->setData($row['dat_details']);
            $numDateElements ++;
        }

        // Write second body content
        $datePrint->setElement('tbody')
                  ->setAttribute('id', 'style1')
                  ->setElement('th', $bodyHeadline_2);
        $numDateElements = 1;
        foreach($body_2 as $row)
        {
            if($row['dat_highlight'] != 1)
            {
                $className = (($numDateElements % 2) == 0) ? 'even' : 'odd';
            }
            else
            {
                $className = (($numDateElements % 2) == 0) ? 'evenHighlight' : 'oddHighlight';
            }
            $datePrint->setElement('tr')
                      ->setAttribute('class', $className)
                      ->setData($row['dat_details']);
            $numDateElements ++;
        }

        // Write third body content
        $datePrint->setElement('tbody')
                  ->setAttribute('id', 'style2')
                  ->setElement('th', $bodyHeadline_3);;
        $numDateElements = 1;
        foreach($body_3 as $row)
        {
            if($row['dat_highlight'] != 1)
            {
                $className = (($numDateElements % 2) == 0) ? 'even' : 'odd';
            }
            else
            {
                $className = (($numDateElements % 2) == 0) ? 'evenHighlight' : 'oddHighlight';
            }
            $datePrint->setElement('tr')
                      ->setAttribute('class', $className)
                      ->setData($row['dat_details']);
            $numDateElements ++;
        }
        // Create table
        $tableDatePrint = $datePrint->getHtmlTable();
    }
echo'
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
    <head>
        <!-- (c) 2004 - 2013 The Admidio Team - http://www.admidio.org -->
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
    </head>
        <body>
            ' .$tableDatePrint. '
        </body>
    </html>';
}
?>