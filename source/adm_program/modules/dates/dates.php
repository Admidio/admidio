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
 * mode      - actual : (Default) shows actual dates and all events in future
 *             old    : shows events in the past
 *             all    : shows all events in past and future
 * start     - Position of query recordset where the visual output should start
 * headline  - Headline shown over events
 *             (Default) Dates
 * cat_id    - show all events of calendar with this id
 * id        - Show only one event
 * show      - all               : (Default) show all events
 *           - maybe_participate : Show only events where the current user participates or could participate
 *           - only_participate  : Show only events where the current user participates
 * date_from - is set to actual date,
 *             if no date information is delivered
 * date_to   - is set to 31.12.9999,
 *             if no date information is delivered
 * view_mode - content output in 'html', 'compact' or 'print' view
 *             (Default: according to preferences)
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['dates_request']);

$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', 'actual', false, array('actual', 'old', 'all'));
$getShow     = admFuncVariableIsValid($_GET, 'show', 'string', 'all', false, array('all', 'maybe_participate', 'only_participate'));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');

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

// create object and get recordset of available dates

$dates = new ModuleDates();
$dates->setParameter('mode', $getMode);
$dates->setParameter('cat_id', $getCatId);
$dates->setParameter('show', $getShow);
$dates->setDateRange($getDateFrom, $getDateTo);

// get parameter
$parameter = $dates->getParameters();

if($parameter['cat_id'] > 0)
{
    $calendar = new TableCategory($gDb, $parameter['cat_id']);
}

// set headline
if($dates->getParameter('cat_id') > 0)
{
    $headline = $parameter['headline']. ' - '. $calendar->getValue('cat_name');
}
else
{
    $headline = $parameter['headline'];
}

// check time period if old dates are choosen, then set headline to previous dates
// Define a prefix
if($dates->getParameter('dateStartFormatEnglish') < DATE_NOW 
    && $dates->getParameter('dateEndFormatEnglish') < DATE_NOW
    || $parameter['mode'] == 'old')
{
    $headline = $gL10n->get('DAT_PREVIOUS_DATES', ' ').$headline;
}

if($parameter['view_mode'] == 'print')
{
    $headline = $gCurrentOrganization->getValue('org_longname').' - '.$headline;
}

// read relevant events from database
$datesResult = $dates->getDataset();
$datesTotalCount = $dates->getDataSetCount();

if($parameter['id']  == 0 || $parameter['view_mode'] == 'compact' && $parameter['id'] > 0)
{
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline);
}

// Number of events each page for default view 'html' or 'compact' view
if($gPreferences['dates_per_page'] > 0 && ( $parameter['view_mode'] == 'html' || $parameter['view_mode'] == 'compact'))
{
    $dates_per_page = $gPreferences['dates_per_page'];
}
else
{
    $dates_per_page = $dates->getDataSetCount();
}

// read all events for output
if($datesTotalCount != 0)
{
    // Initialize counter and object instances
    $count = 0;
    $date = new TableDate($gDb);
    $participants = new Participants ($gDb);

    // New array for the participants of a date
    $memberElements = array();

    // Loop date array and add further information in right position of each date.
    foreach($datesResult['recordset'] as $row)
    {
        $date->readDataById($row['dat_id']);

        // get avaiable room information
        if($date->getValue('dat_room_id') > 0)
        {
            $room = new TableRooms($gDb, $date->getValue('dat_room_id'));
            $datesResult['recordset'][''.$count.'']['room_name'] = $room->getValue('room_name');
        }

        // count members and leaders of the date role and push the result to the array
        if($date->getValue('dat_rol_id')!= null)
        {
            $datesResult['recordset'][''.$count.'']['dat_num_members'] = $participants->getCount($date->getValue('dat_rol_id'));
            $datesResult['recordset'][''.$count.'']['dat_num_leaders'] = $participants->getNumLeaders($date->getValue('dat_rol_id'));
        }

        // For print view also read the participants and push the result to the array with index 'dat_rol_id'
        if($parameter['view_mode'] == 'print')
        {
            if($date->getValue('dat_rol_id') > 0)
            {
                $memberElements[$date->getValue('dat_rol_id')] = $participants->getParticipantsArray($date->getValue('dat_rol_id'));
            }
        }

        $count++;
    }
}

// create html page object
$page = new HtmlPage();

if($parameter['view_mode'] == 'html'  || $parameter['view_mode'] == 'compact')
{
    
    if($gPreferences['enable_rss'] == 1 && $gPreferences['enable_dates_module'] == 1)
    {
        $page->addRssFile($g_root_path.'/adm_program/modules/dates/rss_dates.php?headline='.$parameter['headline'], $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$parameter['headline']));
    };

    $page->addJavascript('
        $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});

        $("#admCalendar").change(function () {
            var calendarId = "";
            if ($("#admCalendar").selectedIndex != 0) {
                var calendarId = $("#admCalendar").val();
            }
            self.location.href = "dates.php?mode='.$parameter['mode'].'&headline='.$parameter['headline'].'&date_from='.$dates->getParameter('dateStartFormatAdmidio').'&date_to='.$dates->getParameter('dateEndFormatAdmidio').'&cat_id=" + calendarId;
        });', true);

    $page->addJavascript('
        function Datefilter() {
            var field_error = "'.$gL10n->get('ECA_FIELD_ERROR').'";

            if (document.Formular.date_from.value == ""
            ||  document.Formular.date_to.value   == "") {
                alert(field_error);
                document.Formular.date_from.focus();
                return false;
            }
        }');


    // If default view mode is set to compact we need a back navigation if one date is selected for detail view
    if($gPreferences['dates_viewmode'] == 'compact' && $parameter['view_mode'] == 'html' && $parameter['id'] > 0)
    {
        // show back link
        $page->addHtml($gNavigation->getHtmlBackButton());
    }

    // set headline
    $page->addHeadline($headline);

    //Check if box must be shown, when more dates available
    if($parameter['id'] == 0 || $gCurrentUser->editDates())
    {
        // create module menu
        $DatesMenu = new ModuleMenu('admMenuDates');


        //Add new event
        if($gCurrentUser->editDates())
        {
            $DatesMenu->addItem('admMenuItemAdd', $g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$parameter['headline'],
                                $gL10n->get('SYS_CREATE_VAR', $parameter['headline']), 'add.png' );
        }

        if($parameter['id'] == 0)
        {
            //ical Download
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $DatesMenu->addItem('admMenuItemICal', $g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$parameter['headline'].'&cat_id='.$parameter['cat_id'],
                                $gL10n->get('DAT_EXPORT_ICAL'), 'database_out.png' );
            }

            // show print button
            $DatesMenu->addItem('admMenuItemPrint', '',
                                $gL10n->get('LST_PRINT_PREVIEW'), 'print.png', 'window.open(\''.$g_root_path.'/adm_program/modules/dates/dates.php?mode='.$parameter['mode'].'&headline='.$parameter['headline'].'&cat_id='.$parameter['cat_id'].'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish').'&view_mode=print\', \'_blank\')' );


            if($gCurrentUser->isWebmaster())
            {
                // show link to system preferences of weblinks
                $DatesMenu->addItem('admMenuItemPreferencesLinks', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=events',
                                    $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
            }
            elseif($gCurrentUser->editDates())
            {
                // if no calendar selectbox is shown, then show link to edit calendars
                $DatesMenu->addItem('admMenuItemCategories', '/adm_program/administration/categories/categories.php?type=DAT&title='.$gL10n->get('DAT_CALENDAR'),
                                    $gL10n->get('DAT_MANAGE_CALENDARS'), 'application_view_tile.png');
            }

            $page->addHtml($DatesMenu->show(false));
        }

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
                error_log($dates->getParameter('cat_id').':::');
                    $form = new HtmlForm('navbar_filter_form', $g_root_path.'/adm_program/modules/dates/dates.php?headline='.$parameter['headline'], $page, 'navbar');
                    $form->addSelectBoxForCategories('cat_id', $gL10n->get('DAT_CALENDAR'), $gDb, 'DAT', 'FILTER_CATEGORIES', FIELD_DEFAULT, $dates->getParameter('cat_id'));
                    $form->addTextInput('date_from', $gL10n->get('SYS_START'), $dates->getParameter('dateStartFormatAdmidio'), 10, FIELD_DEFAULT, 'date');
                    $form->addTextInput('date_to', $gL10n->get('SYS_END'), $dates->getParameter('dateEndFormatAdmidio'), 10, FIELD_DEFAULT, 'date');
                    $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
                    $page->addHtml($form->show(false));
                $page->addHtml('</div>
            </div>
        </nav>');
/*
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
                <input type="hidden" id="cat_id" name="cat_id" value="'.$parameter['cat_id'].'">
                <input type="submit" value="OK">
                <span style="margin-left: 5px;">&nbsp;</span>
                <input type="button" onclick="window.location.href = \''.$g_root_path.'/adm_program/modules/dates/dates.php\'" value="'.$gL10n->get('SYS_DELETE').'">
            </form>
        </div>';*/
    }

    if($datesTotalCount == 0)
    {
        // No events found
        if($parameter['id'] > 0)
        {
            $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>');
        }
        else
        {
            $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
        }
    }
    else
    {
        // Output table header for compact view
        if ($parameter['view_mode'] == 'compact')
        {
            $compactTable = new HtmlTable('events_compact_table', $page, true, true);
            $columnHeading = array('&nbsp;', $gL10n->get('SYS_START'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_PARTICIPANTS'), $gL10n->get('DAT_LOCATION'));
            $compactTable->addRowHeadingByArray($columnHeading);
/*
            $compactTable->addTableHeader();
            $compactTable->addRow();
            $compactTable->addColumn('&nbsp;', null, 'th');
            $compactTable->addColumn($gL10n->get('SYS_START'), array('colspan' => '2'), 'th');
            $compactTable->addColumn($gL10n->get('DAT_DATE'), null, 'th');
            $compactTable->addColumn($gL10n->get('SYS_PARTICIPANTS'), array('colspan' => '2'), 'th');
            $compactTable->addColumn($gL10n->get('DAT_LOCATION'), null, 'th');
            $compactTable->addTableBody();*/
        }
        
        foreach($datesResult['recordset'] as $row)
        {
            // Initialize object and write new data
            $date->readDataById($row['dat_id']);

            $endDate = '';
            if($date->getValue('dat_begin', $gPreferences['system_date']) != $date->getValue('dat_end', $gPreferences['system_date']))
            {
                $endDate = ' - '.$date->getValue('dat_end', $gPreferences['system_date']);
            }

            //ical Download
            $icalIcon = '';
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
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$parameter['headline'].'"><img
                        src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>';
                    $editIcon = '
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;headline='.$parameter['headline'].'"><img
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
            $registerLink    = '';
            $participantLink = '';
            $mgrpartLink  = '';
            $dateElements = array();
            $maxMembers   = '';
            $numMembers   = '';
            $leadersHtml  = '';
            $locationHtml = '';

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
                        <a class="icon-link" href="'. $route_url. '" target="_blank"><img
                            src="'. THEME_PATH. '/icons/map.png" alt="'.$gL10n->get('SYS_SHOW_ROUTE').'" title="'.$gL10n->get('SYS_SHOW_ROUTE').'"/></a>';
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

                        if ($parameter['view_mode'] == 'html')
                        {
                            $registerLink = '<a class="btn btn-default icon-text-link" href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" />'.$gL10n->get('DAT_CANCEL').'</a>';
                        }
                        else
                        {
                            $registerLink = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" /></a>';
                        }                            
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

                            if ($parameter['view_mode'] == 'html')
                            {
                                $registerLink = '<a class="btn btn-default icon-text-link" href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DAT_ATTEND').'" />'.$gL10n->get('DAT_ATTEND').'</a>';
                            }
                            else
                            {
                                $registerLink = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DAT_ATTEND').'" /></a>';
                            }                            
                        }
                        else
                        {
                            $registerLink = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                        }
                    }

                    // Link to participants list
                    if($gValidLogin)
                    {
                        if ($leadersHtml + $numMembers > 0)
                        {
                            $buttonURL = $g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id');
                            
                            if ($parameter['view_mode'] == 'html')
                            {
                                $participantLink = '<a class="btn btn-default icon-text-link" href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" />'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'</a>';
                            }
                            else
                            {
                                $participantLink = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" /></a>';
                            }                            
                        }
                    }

                    // Link for managing new participants
                    if($row['mem_leader'] == 1)
                    {
                        $buttonURL = $g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$date->getValue('dat_rol_id');

                        if ($parameter['view_mode'] == 'html')
                        {
                            $mgrpartLink = '<a class="btn btn-default icon-text-link" href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" />'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'</a>';
                        }
                        else
                        {
                            $mgrpartLink = '<a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" /></a>';
                        }                            
                    }
                }
            }

            if ($parameter['view_mode'] == 'html')
            {
                $cssClassHighlight = '';

                // Change css if date is highlighted
                if($row['dat_highlight'] == 1)
                {
                    $cssClassHighlight = ' panel-highlight';
                }

                // Output of elements
                // always 2 then line break
                $firstElement = true;
                $htmlDateElements = '';

                foreach($dateElements as $element)
                {
                    if($firstElement)
                    {
                        $htmlDateElements .= '<div class="row">';
                    }
                    
                    $htmlDateElements .= '<div class="col-sm-2 col-xs-4">'.$element[0].'</div>
                        <div class="col-sm-4 col-xs-8">'.$element[1].'</div>';

                    if($firstElement)
                    {
                        $firstElement = false;
                    }
                    else
                    {
                        $htmlDateElements .= '</div>';
                        $firstElement = true;
                    }
                }
                
                if($firstElement == false)
                {
                    $htmlDateElements .= '</div>';
                    
                }

                $page->addHtml('
                <div class="panel panel-primary'.$cssClassHighlight.'" id="dat_'.$date->getValue('dat_id').'">
                    <div class="panel-heading">
                        <div class="pull-left">
                            <img class="panel-heading-icon" src="'. THEME_PATH. '/icons/dates.png" alt="'. $date->getValue('dat_headline'). '" />' .
                            $date->getValue('dat_begin', $gPreferences['system_date']).$endDate.' '.$date->getValue('dat_headline') . '
                        </div>
                        <div class="pull-right text-right">' .
                            $icalIcon . $copyIcon . $editIcon . $deleteIcon . '
                        </div>
                    </div>

                    <div class="panel-body">
                        ' . $htmlDateElements . '
                        <div class="date_description" style="clear: left;">' . $date->getValue('dat_description') . '</div>');
                                            
                        if (strlen($registerLink) > 0 || strlen($participantLink) > 0 || strlen($mgrpartLink) > 0)
                        {
                            $page->addHtml('<div class="btn-group">'.$registerLink.$participantLink.$mgrpartLink.'</div>');
                        }
                    $page->addHtml('</div>
                    <div class="panel-footer">'.
                        // show information about user who created the recordset and changed it
                        admFuncShowCreateChangeInfoByName($row['create_name'], $date->getValue('dat_timestamp_create'),
                           $row['change_name'], $date->getValue('dat_timestamp_change'), $date->getValue('dat_usr_id_create'), $date->getValue('dat_usr_id_change')).'
                    </div>
                </div>');
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
                
                $columnValues = array();
                
                if(strlen($registerLink) > 0)
                {
                    $columnValues[] = $registerLink;                    
                }
                else
                {
                    $columnValues[] = '&nbsp;';
                }

                $columnValues[] = $dateBegin.' '.$timeBegin;
                $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/dates/dates.php?id='.$date->getValue('dat_id').'&amp;view_mode=html&amp;headline='.$date->getValue('dat_headline').'">'.$date->getValue('dat_headline').'</a>';
                
                $participants = '';
                
                if($leadersHtml > 0)
                {
                    $participants = $leadersHtml.'+';
                }
                $participants = $numMembers .'/'. $maxMembers;                    
                if($leadersHtml+$numMembers)
                {
                    $participants = $participantLink;
                }
                $columnValues[] = $participants;

                if(strlen($locationHtml) > 0)
                {
                    $columnValues[] = $locationHtml;                    
                }
                else
                {
                    $columnValues[] = '&nbsp;';
                }

                $compactTable->addRowByArray($columnValues);
                                /*
                $compactTable->addRow('', array('class' => 'tableMouseOver'));
                $compactTable->addColumn($registerLink);
                $compactTable->addColumn($dateBegin);
                $compactTable->addColumn($timeBegin);
                $compactTable->addColumn('<a href="'.$g_root_path.'/adm_program/modules/dates/dates.php?id='.$date->getValue('dat_id').'&amp;view_mode=html&amp;headline='.$date->getValue('dat_headline').'">'.$date->getValue('dat_headline').'</a>', 'style', 'font-weight:'. $cssWeight .'');
                $compactTable->addColumn(($leadersHtml > 0 ? $leadersHtml .'+' : '' ) .$numMembers .'/'. $maxMembers);
                $compactTable->addColumn(($leadersHtml+$numMembers > 0 ? $participantLink : '&nbsp;'));
                $compactTable->addColumn($locationHtml);*/
            }
        }  // End foreach

        // Output table bottom for compact view
        if ($parameter['view_mode'] == 'compact')
        {
            $page->addHtml($compactTable->show(false));
        }
    }

    // If neccessary show links to navigate to next and previous recordsets of the query
    $base_url = $g_root_path.'/adm_program/modules/dates/dates.php?mode='.$parameter['mode'].'&headline='.$parameter['headline'].'&cat_id='.$parameter['cat_id'].'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish').'&view_mode='.$parameter['view_mode'];
    $page->addHtml(admFuncGeneratePagination($base_url, $datesTotalCount, $datesResult['limit'], $parameter['startelement'], TRUE));
    $page->show();
}
else
{
    // create print output in a new window and set view_mode back to default 'html' for back navigation in main window
    $gNavigation->addUrl($g_root_path.'/adm_program/modules/dates/dates.php?mode='.$parameter['mode'].'&headline='.$parameter['headline'].'&cat_id='.$parameter['cat_id'].'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish'));

    $tableDatePrint = '';
    
    $calendar = new TableCategory($gDb, $parameter['cat_id']);
    
    // Get a copy of date results if recordsets are found
    if($datesTotalCount > 0)
    {
        $dateElements = $datesResult['recordset'];
    }
    // Define options for selectbox
    if($gValidLogin)
    {
        $selectBoxEntries = array($gL10n->get('SYS_OVERVIEW'), $gL10n->get('SYS_DESCRIPTION'), $gL10n->get('SYS_PARTICIPANTS'));
    }
    else
    {
        $selectBoxEntries = array($gL10n->get('SYS_OVERVIEW'), $gL10n->get('SYS_DESCRIPTION'));
    }
    
    // Define header and footer content for the html table
    $tableHead = '<h1>'.$headline.'</h1>
                    <h3>'.$gL10n->get('SYS_START').':&nbsp;'.$dates->getParameter('dateStartFormatAdmidio'). ' - ' .$gL10n->get('SYS_END').':&nbsp;'.$dates->getParameter('dateEndFormatAdmidio').
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

    // Get dates and  configure table bodies if recordsets are found
    $numElement = 1;
    
    if($datesTotalCount > 0 )
    {
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
                $dateParticipation = new HtmlTableBasic('', 'dateParticipation');
                $dateParticipation->addAttribute('cellspacing', '2', 'table');
                $dateParticipation->addAttribute('cellpadding', '2', 'table');
                $dateParticipation->addRow();
                // Linebreak after 5 entries
                $memberCount = 1;
                $totalMemberCount = count($memberElements[$row['dat_rol_id']]);
    
                foreach(($memberElements[$row['dat_rol_id']]) as $memberDate)
                {
                    // If last entry close table row
                    if($memberCount < $totalMemberCount)
                    {
                        if(($memberCount % 6) == 0)
                        {
                            $dateParticipation->addRow();
                        }
                    }
                    // Leaders are shown highlighted
                    if($memberDate['leader'] != 0)
                    {
                        $dateParticipation->addColumn('<strong>'.$memberDate['surname'].' '.$memberDate['firstname'].'</strong>'.';', array('class' => 'left'), 'td');
                    }
                    else
                    {
                        $dateParticipation->addColumn($memberDate['surname'].' '.$memberDate['firstname'].';', array('class' => 'left'), 'td');
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
    }
    // Create table object
    $datePrint = new HtmlTableBasic('PrintViewDates', 'tableDateList', 1);
    $datePrint->addAttribute('cellpadding', '3', 'table');
    $datePrint->addAttribute('summary', 'Printview of dates', 'table');
    // Define thead
    $datePrint->addTableHeader();
    $datePrint->addRow();
    $datePrint->addColumn($tableHead, array('colspan' => '10'), 'td');
    // Define tfoot
    $datePrint->addTableFooter();
    $datePrint->addRow();
    $datePrint->addColumn();
    $datePrint->addAttribute('colspan', '9', 'td');
    $datePrint->addAttribute('style', 'text-align: right;', 'td');
    $datePrint->addData($tableFooter);

    // Define tbody
    $datePrint->addTableBody('id', 'style0', $bodyHeadline_1, 'th');

    if(isset($dateElements) && count($dateElements) == 0)
    {
        $datePrint->addRow();
        // No events found
        if($parameter['id'] > 0)
        {
            $datePrint->addColumn($gL10n->get('SYS_NO_ENTRY'), array('colspan' => '9'));
        }
        else
        {   
            $datePrint->addColumn($gL10n->get('SYS_NO_ENTRIES'), array('colspan' => '9'));
        }
    }
    else
    {
        // Define first body content
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
            
            $datePrint->addRow($row['dat_details'], array('class' => $className));
            $numDateElements ++;
        }

        // Define second body content
        $datePrint->addTableBody('id', 'style1', $bodyHeadline_2, 'th');
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
            $datePrint->addRow($row['dat_details'], array('class' => $className));
            $numDateElements ++;
        }

        // Define third body content for members only
        if($gValidLogin)
        {
            $datePrint->addTableBody('id', 'style2', $bodyHeadline_3, 'th');
    
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
                
                $datePrint->addRow($row['dat_details'], array('class' => $className));
                $numDateElements ++;
            }
        }
    }
    // Create table
    $tableDatePrint = $datePrint->getHtmlTable();
    
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