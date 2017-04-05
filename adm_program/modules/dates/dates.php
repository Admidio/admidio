<?php
/**
 ***********************************************************************************************
 * Show a list of all events
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Parameters:
 *
 * mode      - actual : (Default) shows actual dates and all events in future
 *             old    : shows events in the past
 *             all    : shows all events in past and future
 * start     - Position of query recordset where the visual output should start
 * headline  - Headline shown over events
 *             (Default) Events
 * cat_id    - show all events of calendar with this id
 * id        - Show only one event
 * show      - all               : (Default) show all events
 *           - maybe_participate : Show only events where the current user participates or could participate
 *           - only_participate  : Show only events where the current user participates
 * date_from - is set to actual date,
 *             if no date information is delivered
 * date_to   - is set to 31.12.9999,
 *             if no date information is delivered
 * view_mode - Content output in 'html' or 'print' view
 * view      - Content output in different views like 'detail', 'list'
 *             (Default: according to preferences)
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
$disableAdditionalGuests   = 4;
$disableComments           = 4;
$disableStatusAttend       = '';
$disableStatusTentative    = '';
$getMode     = admFuncVariableIsValid($_GET, 'mode',      'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old', 'all')));
$getStart    = admFuncVariableIsValid($_GET, 'start',     'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline',  'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id',    'int');
$getId       = admFuncVariableIsValid($_GET, 'id',        'int');
$getShow     = admFuncVariableIsValid($_GET, 'show',      'string', array('defaultValue' => 'all', 'validValues' => array('all', 'maybe_participate', 'only_participate')));
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to',   'date');
$getViewMode = admFuncVariableIsValid($_GET, 'view_mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'print')));
$getView     = admFuncVariableIsValid($_GET, 'view',      'string', array('defaultValue' => $gPreferences['dates_view'], 'validValues' => array('detail', 'compact', 'room', 'participants', 'description')));
$participateModalForm      = false;
$participationPossible     = true;

// check if module is active
if($gPreferences['enable_dates_module'] == 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // module only for valid Users
    require(__DIR__ . '/../../system/login_valid.php');
}

// create object and get recordset of available dates

try
{
    $dates = new ModuleDates();
    $dates->setParameter('mode', $getMode);
    $dates->setParameter('cat_id', $getCatId);
    $dates->setParameter('id', $getId);
    $dates->setParameter('show', $getShow);
    $dates->setParameter('view_mode', $getViewMode);
    $dates->setDateRange($getDateFrom, $getDateTo);
}
catch(AdmException $e)
{
    $e->showHtml();
    // => EXIT
}

if($getCatId > 0)
{
    $calendar = new TableCategory($gDb, $getCatId);
}

// Number of events each page for default view 'html' or 'compact' view
if($gPreferences['dates_per_page'] > 0 && $getViewMode === 'html')
{
    $datesPerPage = (int) $gPreferences['dates_per_page'];
}
else
{
    $datesPerPage = $dates->getDataSetCount();
}

// read relevant events from database
$datesResult     = $dates->getDataSet($getStart, $datesPerPage);
$datesTotalCount = $dates->getDataSetCount();

if($getViewMode === 'html' && $getId === 0)
{
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $dates->getHeadline($getHeadline));
}

// create html page object
$page = new HtmlPage($dates->getHeadline($getHeadline));
$page->enableModal();

if($getViewMode === 'html')
{
    $datatable  = true;
    $hoverRows  = true;
    $classTable = 'table';

    if($gPreferences['enable_rss'] == 1 && $gPreferences['enable_dates_module'] == 1)
    {
        $page->addRssFile(ADMIDIO_URL.FOLDER_MODULES.'/dates/rss_dates.php?headline=' . $getHeadline,
                          $gL10n->get('SYS_RSS_FEED_FOR_VAR',
                          $gCurrentOrganization->getValue('org_longname') . ' - ' . $getHeadline));
    }

    $page->addJavascript('
        $("#sel_change_view").change(function() {
            self.location.href = "dates.php?view=" + $("#sel_change_view").val() + "&mode=' . $getMode . '&headline=' . $getHeadline . '&date_from=' . $dates->getParameter('dateStartFormatAdmidio') . '&date_to=' . $dates->getParameter('dateEndFormatAdmidio') . '&cat_id=' . $getCatId . '";
        });

        $("#menu_item_print_view").click(function() {
            window.open("'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?view_mode=print&view=' . $getView . '&mode=' . $getMode . '&headline=' . $getHeadline . '&cat_id=' . $getCatId . '&id=' . $getId . '&date_from=' . $dates->getParameter('dateStartFormatEnglish') . '&date_to=' . $dates->getParameter('dateEndFormatEnglish') . '", "_blank");
        });

        $("button[id^=btn_attend_]").click(function() {
            // Select current form and action attribute
            var submit_ParticipationForm = $(this).get(0).form;
            var form_action = $(submit_ParticipationForm).attr("action");

            // add value 3 to mode attribute in link for participation
            $(submit_ParticipationForm).attr("action", form_action + 3);
            submit_ParticipationForm.submit();
        });

        $("button[id^=btn_tentative_]").click(function() {
            var submit_ParticipationForm = $(this).get(0).form;
            var form_action = $(submit_ParticipationForm).attr("action");

            $(submit_ParticipationForm).attr("action", form_action + 7);
            submit_ParticipationForm.submit();
        });

        $("button[id^=btn_refuse_]").click(function() {
            var submit_ParticipationForm = $(this).get(0).form;
            var form_action = $(submit_ParticipationForm).attr("action");

            $(submit_ParticipationForm).attr("action", form_action + 4);
            submit_ParticipationForm.submit();
        });', true);

    // If default view mode is set to compact we need a back navigation if one date is selected for detail view
    if($getId > 0)
    {
        // add back link to module menu
        $datesMenu = $page->getMenu();
        $datesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
        $datesMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');
    }

    // get module menu
    $datesMenu = $page->getMenu();

    // Add new event
    if($gCurrentUser->editDates() && $getId === 0)
    {
        $datesMenu->addItem('admMenuItemAdd',
                            ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php?headline=' . $getHeadline,
                            $gL10n->get('SYS_CREATE_VAR', $getHeadline), 'add.png');
    }

    if($getId === 0)
    {
        $form = new HtmlForm('navbar_change_view_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        if($gPreferences['dates_show_rooms'])
        {
            $selectBoxEntries = array(
                'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
                'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
                'room'         => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'),
                'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
                'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
            );
        }
        else
        {
            $selectBoxEntries = array(
                'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
                'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
                'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
                'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
            );
        }
        $form->addSelectBox('sel_change_view', $gL10n->get('SYS_VIEW'), $selectBoxEntries, array('defaultValue' => $getView, 'showContextDependentFirstEntry' => false));
        $datesMenu->addForm($form->show(false));

        // show print button
        $datesMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');

        if($gPreferences['enable_dates_ical'] == 1 || $gCurrentUser->isAdministrator() || $gCurrentUser->editDates())
        {
            $datesMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');
        }

        // ical Download
        if($gPreferences['enable_dates_ical'] == 1)
        {
            $datesMenu->addItem('admMenuItemICal',
                                ADMIDIO_URL.FOLDER_MODULES.'/dates/ical_dates.php?headline=' . $getHeadline . '&amp;cat_id=' . $getCatId,
                                $gL10n->get('DAT_EXPORT_ICAL'), 'database_out.png', 'right', 'menu_item_extras');
        }

        if($gCurrentUser->isAdministrator())
        {
            // show link to system preferences of weblinks
            $datesMenu->addItem('admMenuItemPreferencesLinks',
                                ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=events',
                                $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras');
        }
        elseif($gCurrentUser->editDates())
        {
            // if no calendar selectbox is shown, then show link to edit calendars
            $datesMenu->addItem('admMenuItemCategories',
                                FOLDER_MODULES.'/categories/categories.php?type=DAT&title=' . $gL10n->get('DAT_CALENDAR'),
                                $gL10n->get('DAT_MANAGE_CALENDARS'), 'application_view_tile.png', 'right', 'menu_item_extras');
        }
    }

    // create filter menu with elements for calendar and start-/enddate
    $FilterNavbar = new HtmlNavbar('menu_dates_filter', null, null, 'filter');
    $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?headline=' . $getHeadline . '&view=' . $getView, $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addSelectBoxForCategories('cat_id', $gL10n->get('DAT_CALENDAR'), $gDb, 'DAT', 'FILTER_CATEGORIES', array('defaultValue' => $dates->getParameter('cat_id')));
    $form->addInput('date_from', $gL10n->get('SYS_START'), $dates->getParameter('dateStartFormatAdmidio'), array('type' => 'date', 'maxLength' => 10));
    $form->addInput('date_to', $gL10n->get('SYS_END'), $dates->getParameter('dateEndFormatAdmidio'), array('type' => 'date', 'maxLength' => 10));
    $form->addInput('view', '', $getView, array('property' => FIELD_HIDDEN));
    $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
    $FilterNavbar->addForm($form->show(false));
    $page->addHtml($FilterNavbar->show());
}
else // $getViewMode = 'print'
{
    $datatable  = false;
    $hoverRows  = false;
    $classTable = 'table table-condensed table-striped';

    // create html page object without the custom theme files
    $page->hideThemeHtml();
    $page->hideMenu();
    $page->setPrintMode();

    if($getId === 0)
    {
        $page->addHtml('<h3>' . $gL10n->get('DAT_PERIOD_FROM_TO', $dates->getParameter('dateStartFormatAdmidio'), $dates->getParameter('dateEndFormatAdmidio')) . '</h3>');
    }
}

if($datesTotalCount === 0)
{
    // No events found
    if($getId > 0)
    {
        $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRY') . '</p>');
    }
    else
    {
        $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
    }
}
else
{
    // Output table header for compact view
    if ($getView !== 'detail') // $getView = 'compact' or 'room' or 'participants' or 'description'
    {
        $compactTable = new HtmlTable('events_compact_table', $page, $hoverRows, $datatable, $classTable);

        $columnHeading = array();
        $columnAlign   = array();

        switch ($getView)
        {
            case 'compact':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_PARTICIPANTS'), $gL10n->get('DAT_LOCATION'));
                $columnAlign   = array('center', 'left', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(6));
                break;
            case 'room':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_ROOM'), $gL10n->get('SYS_LEADERS'), $gL10n->get('SYS_PARTICIPANTS'));
                $columnAlign   = array('center', 'left', 'left', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(7));
                break;
            case 'participants':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_PARTICIPANTS'));
                $columnAlign   = array('center', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(5));
                $compactTable->setColumnWidth(4, '35%');
                break;
            case 'description':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_DESCRIPTION'));
                $columnAlign   = array('center', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(5));
                $compactTable->setColumnWidth(4, '35%');
                break;
        }

        if($getViewMode === 'html')
        {
            $columnHeading[] = '&nbsp;';
            $columnAlign[]   = 'right';
        }

        $compactTable->setColumnAlignByArray($columnAlign);
        $compactTable->addRowHeadingByArray($columnHeading);
    }

    // create dummy date object
    $date = new TableDate($gDb);

    foreach($datesResult['recordset'] as $row)
    {
        // write of current event data to date object
        $date->setArray($row);

        $dateId       = (int) $date->getValue('dat_id');
        $dateRolId    = (int) $date->getValue('dat_rol_id');
        $dateHeadline = $date->getValue('dat_headline');

        // initialize all output elements
        $attentionDeadline  = '';
        $outputEndDate      = '';
        $outputButtonIcal   = '';
        $outputButtonEdit   = '';
        $outputButtonDelete = '';
        $outputButtonCopy   = '';
        $outputButtonParticipation      = '';
        $outputButtonParticipants       = '';
        $outputButtonParticipantsEmail  = '';
        $outputButtonParticipantsAssign = '';
        $outputLinkLocation  = '';
        $outputLinkRoom      = '';
        $outputNumberMembers = '';
        $outputNumberLeaders = '';
        $outputDeadline      = '';
        $dateElements        = array();
        $participantsArray   = array();

        // If extended options for participation are allowed then use a modal form instead the dropdown button
        if ((int) $date->getValue('dat_allow_comments') === 1 || (int) $date->getValue('dat_additional_guests') === 1 )
        {
            $participateModalForm = true;

            if ((int) $date->getValue('dat_allow_comments') === 1)
            {
                $disableComments = '';
            }
            if ((int) $date->getValue('dat_additional_guests') === 1)
            {
                $disableAdditionalGuests = '';
            }
        }

        // set end date of event
        if($date->getValue('dat_begin', $gPreferences['system_date']) !== $date->getValue('dat_end', $gPreferences['system_date']))
        {
            $outputEndDate = ' - ' . $date->getValue('dat_end', $gPreferences['system_date']);
        }

        if($getViewMode === 'html')
        {
            // ical Download
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $outputButtonIcal = '
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?dat_id=' . $dateId . '&amp;mode=6">
                        <img src="'.THEME_URL.'/icons/database_out.png" alt="' . $gL10n->get('DAT_EXPORT_ICAL') . '" title="' . $gL10n->get('DAT_EXPORT_ICAL') . '" /></a>';
            }

            // change and delete is only for users with additional rights
            if ($gCurrentUser->editDates())
            {
                if($date->editRight())
                {
                    $outputButtonCopy = '
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php?dat_id=' . $dateId . '&amp;copy=1&amp;headline=' . $getHeadline . '">
                            <img src="'.THEME_URL.'/icons/application_double.png" alt="' . $gL10n->get('SYS_COPY') . '" title="' . $gL10n->get('SYS_COPY') . '" /></a>';
                    $outputButtonEdit = '
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php?dat_id=' . $dateId . '&amp;headline=' . $getHeadline . '">
                            <img src="'.THEME_URL.'/icons/edit.png" alt="' . $gL10n->get('SYS_EDIT') . '" title="' . $gL10n->get('SYS_EDIT') . '" /></a>';
                }

                // Deleting events is only allowed for group members
                if((int) $date->getValue('cat_org_id') === (int) $gCurrentOrganization->getValue('org_id'))
                {
                    $outputButtonDelete = '
                        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                            href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=dat&amp;element_id=dat_' . $dateId .
                            '&amp;name=' . urlencode($date->getValue('dat_begin', $gPreferences['system_date']) . ' ' . $dateHeadline).
                            '&amp;database_id=' . $dateId . '">
                            <img src="'.THEME_URL.'/icons/delete.png" alt="' . $gL10n->get('SYS_DELETE') . '" title="' . $gL10n->get('SYS_DELETE') . '" /></a>';
                }
            }
        }

        $dateLocation = $date->getValue('dat_location');
        if ($dateLocation !== '')
        {
            // Show map link, when at least 2 words available
            // having more than 3 characters each
            $countLocationWords = 0;
            foreach(preg_split('/[,; ]/', $dateLocation) as $value)
            {
                if(strlen($value) > 3)
                {
                    ++$countLocationWords;
                }
            }

            if($gPreferences['dates_show_map_link'] && $countLocationWords > 1 && $getViewMode === 'html')
            {
                $dateCountry = $date->getValue('dat_country');

                // Create Google-Maps-Link for location
                $locationUrl = 'https://www.google.com/maps?q='.$dateLocation;

                if($dateCountry !== '')
                {
                    // Better results with additional country information
                    $locationUrl .= ',%20'.$dateCountry;
                }

                $outputLinkLocation = '
                    <a href="' . $locationUrl . '" target="_blank" title="' . $gL10n->get('DAT_SHOW_ON_MAP') . '"/>
                        <strong>' . $dateLocation . '</strong>
                    </a>';

                // if valid login and enough information about address exist - calculate the route
                if($gValidLogin && $gCurrentUser->getValue('STREET') !== ''
                && ($gCurrentUser->getValue('POSTCODE') !== '' || $gCurrentUser->getValue('CITY') !== ''))
                {
                    $routeUrl = 'https://www.google.com/maps?f=d&amp;saddr=' . urlencode($gCurrentUser->getValue('STREET'));

                    if($gCurrentUser->getValue('POSTCODE') !== '')
                    {
                        $routeUrl .= ',%20' . urlencode($gCurrentUser->getValue('POSTCODE'));
                    }
                    if($gCurrentUser->getValue('CITY') !== '')
                    {
                        $routeUrl .= ',%20' . urlencode($gCurrentUser->getValue('CITY'));
                    }
                    if($gCurrentUser->getValue('COUNTRY') !== '')
                    {
                        $routeUrl .= ',%20' . urlencode($gCurrentUser->getValue('COUNTRY'));
                    }

                    $routeUrl .= '&amp;daddr=' . urlencode($dateLocation);

                    if($dateCountry !== '')
                    {
                        // With information about country Google finds the location much better
                        $routeUrl .= ',%20' . $dateCountry;
                    }

                    $outputLinkLocation .= '
                        <a class="admidio-icon-link" href="' . $routeUrl . '" target="_blank">
                            <img src="'.THEME_URL.'/icons/map.png" alt="' . $gL10n->get('SYS_SHOW_ROUTE') . '" title="' . $gL10n->get('SYS_SHOW_ROUTE') . '" />
                        </a>';
                }
            }
            else
            {
                $outputLinkLocation = $dateLocation;
            }
        }

        // if active, then show room information
        $dateRoomId = (int) $date->getValue('dat_room_id');
        if($dateRoomId > 0)
        {
            $room = new TableRooms($gDb, $dateRoomId);

            if($getViewMode === 'html')
            {
                $roomLink = ADMIDIO_URL. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1=' . $dateRoomId . '&amp;inline=true';
                $outputLinkRoom = '<strong><a data-toggle="modal" data-target="#admidio_modal" href="' . $roomLink . '">' . $room->getValue('room_name') . '</a></strong>';
            }
            else // $getViewMode = 'print'
            {
                $outputLinkRoom = $room->getValue('room_name');
            }
        }

        // count participants of the date
        if($dateRolId > 0)
        {
            $participants = new Participants($gDb, $dateRolId);
            $outputNumberMembers = $participants->getCount();
            $outputNumberLeaders = $participants->getNumLeaders();
            $participantsArray   = $participants->getParticipantsArray($dateRolId);

        if($date->getValue('dat_deadline') !== null)
        {
            if ($date->getValue('dat_all_day') === 0)
            {
                 $outputDeadline = $date->getValue('dat_deadline', $gPreferences['system_date']. ' ' . $gPreferences['system_time']);
            }
            else
            {
                $outputDeadline = $date->getValue('dat_deadline', $gPreferences['system_date']);
            }
        }

            // Links for the participation only in html mode
            if($getViewMode === 'html')
            {
                // If user is invited to the event then the approval state is not initialized and has value "null" in data table
                if($row['member_date_role'] > 0 && $row['member_approval_state'] == null)
                {
                    $row['member_approval_state'] = '0';
                }

                switch($row['member_approval_state'])
                {
                    case '0':
                        $buttonText =  $gL10n->get('DAT_USER_INVITED');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/warning.png" alt="' . $gL10n->get('DAT_USER_INVITED') . '" title="' . $gL10n->get('DAT_USER_INVITED') . '"/>';
                        break;
                    case '1':
                        $buttonText =  $gL10n->get('DAT_USER_TENTATIVE');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/help_violett.png" alt="' . $gL10n->get('DAT_USER_MAYBE_PARTICPATE') . '" title="' . $gL10n->get('DAT_USER_MAYBE_PARTICPATE') . '"/>';
                        break;
                    case '2':
                        $buttonText =  $gL10n->get('DAT_USER_ATTEND');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/ok.png" alt="' . $gL10n->get('DAT_USER_ATTEND') . '" title="' . $gL10n->get('DAT_USER_ATTEND') . '"/>';
                        break;
                    case '3':
                        $buttonText =  $gL10n->get('DAT_USER_REFUSED');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/no.png" alt="' . $gL10n->get('DAT_USER_REFUSED') . '" title="' . $gL10n->get('DAT_USER_REFUSED') . '"/>';
                        break;
                    default:
                        $buttonText =  $gL10n->get('DAT_ATTEND');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/edit.png" alt="' . $gL10n->get('DAT_ATTEND') . '" title="' . $gL10n->get('DAT_ATTEND') . '"/>';
                        break;
                }

                if ($getView !== 'detail')
                {
                    // Status text only in detail view
                    $buttonText = '';
                }

                // Check limit of participants
                if ($date->getValue('dat_max_members') === 1 && $outputNumberMembers >= $date->getValue('dat_max_members'))
                {
                    // No further members allowed
                    $participationPossible = false;

                    // Check current user. If user is member of the event role then get his current approval status and set the options
                    if (!in_array((int) $gCurrentUser->getValue('usr_id'), array_column($participantsArray, 'usrId'), true))
                    {
                        switch ($participantsArray[$gCurrentUser->getValue('usr_id')]['approved'])
                        {
                            case 1:
                                $disableStatusTentative = 'disabled';
                                break;
                            case 2:
                                $disableStatusAttend    = 'disabled';
                                break;
                            case 3:
                                $disableStatusAttend    = 'disabled';
                                $disableStatusTentative = 'disabled';
                                break;
                        }
                    }
                }

                // Check participation deadline and show buttons if allowed
                if (!$date->deadlineExceeded())
                {
                    if (!$participateModalForm)
                    {
                        $outputButtonParticipation = '
                            <div class="btn-group" role="group">
                                <button class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.$iconParticipationStatus.$buttonText.'
                                    <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?mode=3&amp;dat_id=' . $dateId . '"' . $disableStatusAttend . '>
                                            <img src="'.THEME_URL.'/icons/ok.png" alt="' . $gL10n->get('DAT_ATTEND') . '" title="' . $gL10n->get('DAT_ATTEND') . '"/>' . $gL10n->get('DAT_ATTEND') . '
                                        </a>
                                    </li>
                                    <li>
                                        <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?mode=7&amp;dat_id=' . $dateId . '"' . $disableStatusTentative . '>
                                            <img src="'.THEME_URL.'/icons/help_violett.png" alt="' . $gL10n->get('DAT_USER_TENTATIVE') . '" title="' . $gL10n->get('DAT_USER_TENTATIVE') . '"/>' . $gL10n->get('DAT_USER_TENTATIVE') . '
                                        </a>
                                    </li>
                                    <li>
                                        <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?mode=4&amp;dat_id=' . $dateId . '">
                                            <img src="'.THEME_URL.'/icons/no.png" alt="' . $gL10n->get('DAT_CANCEL') . '" title="' . $gL10n->get('DAT_CANCEL') . '"/>' . $gL10n->get('DAT_CANCEL') . '
                                        </a>
                                    </li>
                                </ul>
                            </div>';
                    }
                    else
                    {
                        $outputButtonParticipation = '
                            <div class="btn-group" role="group">
                                <button class="btn btn-default" data-toggle="modal" data-target="#participate_modal_' . $dateId . '">' . $iconParticipationStatus . $buttonText . '
                            </div>';

                        $participationForm = new HtmlForm('participate_form_'. $dateId, 'dates_function.php?dat_id=' . $dateId . '&amp;mode=', $page, array('method' => 'post', 'setFocus' => false));
                        $participationForm->addHtml('<div id="participate_modal_' . $dateId . '" class="modal fade" role="dialog">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                                    <h4 class="modal-title">' .$gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION') . '</h4>
                                                                    <p>' .$date->getValue('dat_headline'). ': ' .$date->getValue('dat_begin') . ' - ' .$date->getValue('dat_end'). '</p>
                                                                </div>
                                                                <div class="modal-body">');
                        $participationForm->addMultilineTextInput('dat_comment', $gL10n->get('SYS_COMMENT'), $row['comment'], 6, array('class' => 'form-control', 'maxLength' => 1000, 'property' => $disableComments));
                        $participationForm->addInput('additonal_guests', $gL10n->get('LST_SEAT_AMOUNT'), $row['additional_guests'], array('class' => 'form-control', 'property' => $disableAdditionalGuests));
                        $participationForm->addHtml('</div><div class="modal-footer">');
                        $participationForm->openButtonGroup();
                        $participationForm->addButton('btn_attend_' . $dateId, $gL10n->get('DAT_ATTEND'), array('icon' => THEME_URL.'/icons/ok.png', 'class' => $disableStatusAttend));
                        $participationForm->addButton('btn_tentative_' . $dateId, $gL10n->get('DAT_USER_TENTATIVE'), array('icon' => THEME_URL.'/icons/help_violett.png', 'class' => $disableStatusTentative));
                        $participationForm->addButton('btn_refuse_' . $dateId, $gL10n->get('DAT_CANCEL'), array('icon' => THEME_URL.'/icons/no.png'));
                        $participationForm->closeButtonGroup();
                        $participationForm->addHtml('</div></div></div></div>');
                        $page->addHtml($participationForm->show(false));
                    }
                    // Reset flags and parameters
                    $disableAdditionalGuests    = 4;
                    $disableComments            = 4;
                    $disableStatusAttend        = '';
                    $disableStatusTentative     = '';
                    $participateModalForm       = false;
                }
                else
                {
                    // Show warning for member of the date role if deadline is exceeded and now no changes are possible anymore
                    if ($participants->isMemberOfEvent($gCurrentUser->getValue('usr_id')))
                    {
                        $attentionDeadline =    '<div class="alert alert-warning" role="alert">
                                                    <strong>' .$gL10n->get('DAT_DEADLINE') . '! </strong>' . $gL10n->get('DAT_DEADLINE_ATTENTION') . '
                                                </div>';
                    }
                }

                if ($participationPossible === false)
                {
                    // Check participation of current user. If user is member of the event role, he/she should also be able to change to possible states.
                    if (!$participants->isMemberOfEvent((int) $gCurrentUser->getValue('usr_id')))
                    {
                        $outputButtonParticipation = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                        $iconParticipationStatus = '';
                    }
                }

                // Link to participants list
                if($gValidLogin && $gCurrentUser->hasRightViewRole($dateRolId))
                {
                    if($outputNumberMembers > 0 || $outputNumberLeaders > 0)
                    {
                        $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?mode=html&amp;rol_ids=' . $dateRolId;

                        if ($getView === 'detail')
                        {
                            $outputButtonParticipants = '
                                <button class="btn btn-default" onclick="window.location.href=\'' . $buttonURL . '\'">
                                    <img src="'.THEME_URL.'/icons/list.png" alt="' . $gL10n->get('DAT_SHOW_PARTICIPANTS') . '" />' . $gL10n->get('DAT_SHOW_PARTICIPANTS') . '</button>';
                        }
                        else
                        {
                            $outputButtonParticipants = '
                                <a class="admidio-icon-link" href="' . $buttonURL . '">
                                    <img src="'.THEME_URL.'/icons/list.png" alt="' . $gL10n->get('DAT_SHOW_PARTICIPANTS') . '" title="' . $gL10n->get('DAT_SHOW_PARTICIPANTS') . '" /></a>';
                        }
                    }
                }

                // Link to send email to participants
                if($gValidLogin && $gCurrentUser->hasRightSendMailToRole($dateRolId))
                {
                    if($outputNumberMembers > 0 || $outputNumberLeaders > 0)
                    {
                        $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?rol_id=' . $dateRolId;

                        if ($getView === 'detail')
                        {
                            $outputButtonParticipantsEmail = '
                                <button class="btn btn-default" onclick="window.location.href=\'' . $buttonURL . '\'">
                                    <img src="'.THEME_URL.'/icons/email.png" alt="' . $gL10n->get('MAI_SEND_EMAIL') . '" />' . $gL10n->get('MAI_SEND_EMAIL') . '
                                </button>';
                        }
                        else
                        {
                            $outputButtonParticipantsEmail = '
                                <a class="admidio-icon-link" href="' . $buttonURL . '">
                                    <img src="'.THEME_URL.'/icons/email.png" alt="' . $gL10n->get('MAI_SEND_EMAIL') . '" title="' . $gL10n->get('MAI_SEND_EMAIL') . '" />
                                </a>';
                        }
                    }
                }

                // Link for managing new participants
                if($row['mem_leader'])
                {
                    $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id=' . $dateRolId;

                    if ($getView === 'detail')
                    {
                        $outputButtonParticipantsAssign = '
                            <button class="btn btn-default" onclick="window.location.href=\'' . $buttonURL . '\'">
                                <img src="'.THEME_URL.'/icons/add.png" alt="' . $gL10n->get('DAT_ASSIGN_PARTICIPANTS') . '" />' . $gL10n->get('DAT_ASSIGN_PARTICIPANTS') . '
                            </button>';
                    }
                    else
                    {
                        $outputButtonParticipantsAssign = '
                            <a class="admidio-icon-link" href="' . $buttonURL . '">
                                <img src="'.THEME_URL.'/icons/add.png" alt="' . $gL10n->get('DAT_ASSIGN_PARTICIPANTS') . '" title="' . $gL10n->get('DAT_ASSIGN_PARTICIPANTS') . '" />
                            </a>';
                    }
                }
            }
        }

        if($getView === 'detail')
        {
            if (!$date->getValue('dat_all_day'))
            {
                // Write start in array
                $dateElements[] = array($gL10n->get('SYS_START'), '<strong>' . $date->getValue('dat_begin', $gPreferences['system_time']) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
                // Write end in array
                $dateElements[] = array($gL10n->get('SYS_END'), '<strong>' . $date->getValue('dat_end', $gPreferences['system_time']) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
            }

            $dateElements[] = array($gL10n->get('DAT_CALENDAR'), '<strong>' . $date->getValue('cat_name') . '</strong>');
            if($outputLinkLocation !== '')
            {
                $dateElements[] = array($gL10n->get('DAT_LOCATION'), $outputLinkLocation);
            }
            if($outputLinkRoom !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_ROOM'), $outputLinkRoom);
            }
            if($outputDeadline !== '')
            {
                $dateElements[] = array($gL10n->get('DAT_DEADLINE'), '<strong>'.$outputDeadline.'</strong>');
            }

            if($outputNumberLeaders !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_LEADERS'), '<strong>' . $outputNumberLeaders . '</strong>');
            }
            if($outputNumberMembers !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), '<strong>' . $outputNumberMembers . '</strong>');
            }

            // show panel view of events

            $cssClassHighlight = '';

            // Change css if date is highlighted
            if($row['dat_highlight'])
            {
                $cssClassHighlight = 'admidio-event-highlight';
            }

            // Output of elements
            // always 2 then line break
            $firstElement = true;
            $htmlDateElements = '';

            foreach($dateElements as $element)
            {
                if($element[1] !== '')
                {
                    if($firstElement)
                    {
                        $htmlDateElements .= '<div class="row">';
                    }

                    $htmlDateElements .= '<div class="col-sm-2 col-xs-4">' . $element[0] . '</div>
                        <div class="col-sm-4 col-xs-8">' . $element[1] . '</div>';

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
            }

            if(!$firstElement)
            {
                $htmlDateElements .= '</div>';

            }

            $page->addHtml('
                <div class="panel panel-primary ' . $cssClassHighlight . '" id="dat_' . $dateId . '">
                    <div class="panel-heading">
                        <div class="pull-left">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/dates.png" alt="' . $dateHeadline . '" />' .
                            $date->getValue('dat_begin', $gPreferences['system_date']) . $outputEndDate . ' ' . $dateHeadline . '
                        </div>
                        <div class="pull-right text-right">' .
                            $outputButtonIcal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete . '
                        </div>
                    </div>
                    <div class="panel-body">
                        ' . $htmlDateElements . '<br />
                        <p>' . $date->getValue('dat_description') . '</p>' .$attentionDeadline);

            if($outputButtonParticipation !== '' || $outputButtonParticipants !== ''
            || $outputButtonParticipantsEmail !== '' || $outputButtonParticipantsAssign !== '')
            {
                $page->addHtml('<div class="btn-group">' . $outputButtonParticipation . $outputButtonParticipants . $outputButtonParticipantsEmail . $outputButtonParticipantsAssign . '</div>');
            }
            $page->addHtml('
                </div>
                <div class="panel-footer">'.
                    // show information about user who created the recordset and changed it
                    admFuncShowCreateChangeInfoByName(
                        $row['create_name'], $date->getValue('dat_timestamp_create'),
                        $row['change_name'], $date->getValue('dat_timestamp_change'),
                        (int) $date->getValue('dat_usr_id_create'), (int) $date->getValue('dat_usr_id_change')
                    ).'
                    </div>
                </div>');
        }
        else // $getView = 'compact' or 'room' or 'participants' or 'description'
        {
            // show table view of events

            // Change css class if date is highlighted
            $cssClass = '';
            if($row['dat_highlight'])
            {
                $cssClass = 'admidio-event-highlight';
            }

            // date beginn
            $dateBegin = $date->getValue('dat_begin', $gPreferences['system_date']);
            $timeBegin = $date->getValue('dat_begin', $gPreferences['system_time']);

            // date beginn
            $dateEnd = $date->getValue('dat_end', $gPreferences['system_date']);
            $timeEnd = $date->getValue('dat_end', $gPreferences['system_time']);

            $dateTimeValue = '';

            if($dateBegin === $dateEnd)
            {
                $dateTimeValue = $dateBegin. ' '. $timeBegin. ' - '. $timeEnd;
            }
            else
            {
                if ($date->getValue('dat_all_day'))
                {
                    // full-time event that only exists one day should only show the begin date
                    $objDateBegin = new DateTime($row['dat_begin']);
                    $objDateEnd = new DateTime($row['dat_end']);
                    $dateDiff = $objDateBegin->diff($objDateEnd);

                    if($dateDiff->d === 1)
                    {
                        $dateTimeValue = $dateBegin;
                    }
                    else
                    {
                        $dateTimeValue = $dateBegin. ' - '. $dateEnd;
                    }
                }
                else
                {
                    $dateTimeValue = $dateBegin. ' '. $timeBegin. ' - '. $dateEnd. ' '. $timeEnd;
                }
            }

            $columnValues = array();

            if($outputButtonParticipation !== '')
            {
                $columnValues[] = $outputButtonParticipation;
            }
            else
            {
                $columnValues[] = '';
            }

            $columnValues[] = $dateTimeValue;

            if($getViewMode === 'html')
            {
                if (strlen($date->getValue('dat_deadline') > 0))
                {
                    $columnValues[] = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?id=' . $dateId . '&amp;view_mode=html&view=detail&amp;headline=' . $dateHeadline . '">' . $dateHeadline . '<br />' . $gL10n->get('DAT_DEADLINE') . ': ' . $outputDeadline . '</a>';
                }
                else
                {
                    $columnValues[] = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?id=' . $dateId . '&amp;view_mode=html&view=detail&amp;headline=' . $dateHeadline . '">' . $dateHeadline . '</a>';
                }
            }
            else
            {
                $columnValues[] = $dateHeadline;
            }

            if ($getView === 'room')
            {
                $columnValues[] = $outputLinkRoom;
                $columnValues[] = $outputNumberLeaders;
            }

            switch ($getView)
            {
                case 'compact':
                case 'room':
                    if ($dateRolId > 0)
                    {
                        if ($date->getValue('dat_max_members') > 0)
                        {
                            $htmlParticipants = $outputNumberMembers . ' / ' . $date->getValue('dat_max_members');
                        }
                        else
                        {
                            $htmlParticipants = $outputNumberMembers . '&nbsp;';
                        }

                        if ($outputNumberMembers > 0)
                        {
                            $htmlParticipants .= $outputButtonParticipants . $outputButtonParticipantsEmail;
                        }

                        $columnValues[] = $htmlParticipants;
                    }
                    else
                    {
                        $columnValues[] = '';
                    }
                    break;
                case 'participants':
                    $columnValue = array();

                    if (is_array($participantsArray))
                    {
                        // Only show participants if user has right to view the list, is leader or has permission to create/edit events
                        if ($gCurrentUser->hasRightViewRole($date->getValue('dat_rol_id'))
                            || $row['mem_leader'] == 1
                            || $gCurrentUser->editDates())
                        {
                            foreach ($participantsArray as $participant)
                            {
                                $columnValue[] = $participant['firstname']. ' ' . $participant['surname'];
                            }
                        }
                    }
                    $columnValues[] = implode(', ', $columnValue);
                    break;
                case 'description':
                    $columnValues[] = $date->getValue('dat_description');
                    break;
            }

            if ($getView === 'compact')
            {
                if ($outputLinkLocation !== '')
                {
                    $columnValues[] = $outputLinkLocation;
                }
                else
                {
                    $columnValues[] = '';
                }
            }

            if($getViewMode === 'html')
            {
                $columnValues[] = $outputButtonIcal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete;
            }

            $compactTable->addRowByArray($columnValues, null, array('class' => $cssClass));
        }
    }  // End foreach

    // Output table bottom for compact view
    if ($getView !== 'detail') // $getView = 'compact' or 'room' or 'participants' or 'description'
    {
        $page->addHtml($compactTable->show());
    }
}
// If necessary show links to navigate to next and previous recordsets of the query
$baseUrl = ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?view=' . $getView . '&mode=' . $getMode . '&headline=' . $getHeadline . '&cat_id=' . $getCatId . '&date_from=' . $dates->getParameter('dateStartFormatEnglish') . '&date_to=' . $dates->getParameter('dateEndFormatEnglish') . '&view_mode=' . $getViewMode;
$page->addHtml(admFuncGeneratePagination($baseUrl, $datesTotalCount, $datesResult['limit'], $getStart, true));
$page->show();
