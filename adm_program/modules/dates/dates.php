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
require_once('../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
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
    require_once('../../system/login_valid.php');
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

if($getViewMode !== 'print' && $getId === 0)
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
        $page->addRssFile(ADMIDIO_URL.FOLDER_MODULES.'/dates/rss_dates.php?headline='.$getHeadline,
                          $gL10n->get('SYS_RSS_FEED_FOR_VAR',
                          $gCurrentOrganization->getValue('org_longname').' - '.$getHeadline));
    }

    $page->addJavascript('
        $("#sel_change_view").change(function () {
            self.location.href = "dates.php?view=" + $("#sel_change_view").val() + "&mode='.$getMode.'&headline='.$getHeadline.'&date_from='.$dates->getParameter('dateStartFormatAdmidio').'&date_to='.$dates->getParameter('dateEndFormatAdmidio').'&cat_id='.$getCatId.'";
        });

        $("#menu_item_print_view").click(function () {
            window.open("'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?view_mode=print&view='.$getView.'&mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish').'", "_blank");
        });', true);

    // If default view mode is set to compact we need a back navigation if one date is selected for detail view
    if($getId > 0)
    {
        // add back link to module menu
        $datesMenu = $page->getMenu();
        $datesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    }

    // get module menu
    $DatesMenu = $page->getMenu();

    // Add new event
    if($gCurrentUser->editDates() && $getId === 0)
    {
        $DatesMenu->addItem('admMenuItemAdd',
                            ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php?headline='.$getHeadline,
                            $gL10n->get('SYS_CREATE_VAR', $getHeadline), 'add.png');
    }

    if($getId === 0)
    {
        $form = new HtmlForm('navbar_change_view_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        if($gPreferences['dates_show_rooms'])
        {
            $selectBoxEntries = array('detail' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'), 'room' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'), 'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'), 'description' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION'));
        }
        else
        {
            $selectBoxEntries = array('detail' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'), 'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'), 'description' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION'));
        }
        $form->addSelectBox('sel_change_view', $gL10n->get('SYS_VIEW'), $selectBoxEntries, array('defaultValue' => $getView, 'showContextDependentFirstEntry' => false));
        $DatesMenu->addForm($form->show(false));

        // show print button
        $DatesMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');

        if($gPreferences['enable_dates_ical'] == 1 || $gCurrentUser->isAdministrator() || $gCurrentUser->editDates())
        {
            $DatesMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');
        }

        // ical Download
        if($gPreferences['enable_dates_ical'] == 1)
        {
            $DatesMenu->addItem('admMenuItemICal',
                                ADMIDIO_URL.FOLDER_MODULES.'/dates/ical_dates.php?headline='.$getHeadline.'&amp;cat_id='.$getCatId,
                                $gL10n->get('DAT_EXPORT_ICAL'), 'database_out.png', 'right', 'menu_item_extras');
        }

        if($gCurrentUser->isAdministrator())
        {
            // show link to system preferences of weblinks
            $DatesMenu->addItem('admMenuItemPreferencesLinks',
                                ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=events',
                                $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras');
        }
        elseif($gCurrentUser->editDates())
        {
            // if no calendar selectbox is shown, then show link to edit calendars
            $DatesMenu->addItem('admMenuItemCategories',
                                FOLDER_MODULES.'/categories/categories.php?type=DAT&title='.$gL10n->get('DAT_CALENDAR'),
                                $gL10n->get('DAT_MANAGE_CALENDARS'), 'application_view_tile.png', 'right', 'menu_item_extras');
        }
    }

    // create filter menu with elements for calendar and start-/enddate
    $FilterNavbar = new HtmlNavbar('menu_dates_filter', null, null, 'filter');
    $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?headline='.$getHeadline.'&view='.$getView, $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addSelectBoxForCategories('cat_id', $gL10n->get('DAT_CALENDAR'), $gDb, 'DAT', 'FILTER_CATEGORIES', array('defaultValue' => $dates->getParameter('cat_id')));
    $form->addInput('date_from', $gL10n->get('SYS_START'), $dates->getParameter('dateStartFormatAdmidio'), array('type' => 'date', 'maxLength' => 10));
    $form->addInput('date_to', $gL10n->get('SYS_END'), $dates->getParameter('dateEndFormatAdmidio'), array('type' => 'date', 'maxLength' => 10));
    $form->addInput('view', '', $getView, array('property' => FIELD_HIDDEN));
    $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
    $FilterNavbar->addForm($form->show(false));
    $page->addHtml($FilterNavbar->show());
}
elseif($getViewMode === 'print')
{
    $datatable  = false;
    $hoverRows  = false;
    $classTable = 'table table-condensed table-striped';

    // create html page object without the custom theme files
    $page->hideThemeHtml();
    $page->hideMenu();
    $page->setPrintMode();
    $page->addHtml('<h3>'.$gL10n->get('DAT_PERIOD_FROM_TO', $dates->getParameter('dateStartFormatAdmidio'), $dates->getParameter('dateEndFormatAdmidio')).'</h3>');
}

if($datesTotalCount === 0)
{
    // No events found
    if($getId > 0)
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
    if ($getView === 'compact' || $getView === 'room' || $getView === 'participants' || $getView === 'description')
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

        // initialize all output elements
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
        $dateElements        = array();
        $participantsArray   = array();

        // set end date of event
        if($date->getValue('dat_begin', $gPreferences['system_date']) !== $date->getValue('dat_end', $gPreferences['system_date']))
        {
            $outputEndDate = ' - '.$date->getValue('dat_end', $gPreferences['system_date']);
        }

        if($getViewMode === 'html')
        {
            // ical Download
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $outputButtonIcal = '
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?dat_id='.$date->getValue('dat_id'). '&amp;mode=6">
                        <img src="'.THEME_URL.'/icons/database_out.png" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'" /></a>';
            }

            // change and delete is only for users with additional rights
            if ($gCurrentUser->editDates())
            {
                if($date->editRight())
                {
                    $outputButtonCopy = '
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php?dat_id='.$date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$getHeadline.'">
                            <img src="'.THEME_URL.'/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>';
                    $outputButtonEdit = '
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php?dat_id='.$date->getValue('dat_id'). '&amp;headline='.$getHeadline.'">
                            <img src="'.THEME_URL.'/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
                }

                // Deleting events is only allowed for group members
                if((int) $date->getValue('cat_org_id') === (int) $gCurrentOrganization->getValue('org_id'))
                {
                    $outputButtonDelete = '
                        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                            href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=dat&amp;element_id=dat_'.
                            $date->getValue('dat_id').'&amp;name='.
                            urlencode($date->getValue('dat_begin', $gPreferences['system_date']).' '.
                            $date->getValue('dat_headline')).'&amp;database_id='.$date->getValue('dat_id').'">
                            <img src="'.THEME_URL.'/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                }
            }
        }

        if ($date->getValue('dat_location') !== '')
        {
            // Show map link, when at least 2 words available
            // having more than 3 characters each
            $countLocationWords = 0;
            foreach(preg_split('/[,; ]/', $date->getValue('dat_location')) as $key => $value)
            {
                if(strlen($value) > 3)
                {
                    ++$countLocationWords;
                }
            }

            if($gPreferences['dates_show_map_link'] && $countLocationWords > 1 && $getViewMode === 'html')
            {
                // Create Google-Maps-Link for location
                $location_url = 'https://maps.google.com/?q='.$date->getValue('dat_location');

                if($date->getValue('dat_country') !== '')
                {
                    // Better results with additional country information
                    $location_url .= ',%20'.$date->getValue('dat_country');
                }

                $outputLinkLocation = '
                    <a href="'.$location_url.'" target="_blank" title="'.$gL10n->get('DAT_SHOW_ON_MAP').'"/>
                        <strong>'.$date->getValue('dat_location').'</strong>
                    </a>';

                // if valid login and enough information about address exist - calculate the route
                if($gValidLogin && $gCurrentUser->getValue('ADDRESS') !== ''
                && ($gCurrentUser->getValue('POSTCODE') !== '' || $gCurrentUser->getValue('CITY') !== ''))
                {
                    $route_url = 'https://maps.google.com/?f=d&amp;saddr='.urlencode($gCurrentUser->getValue('ADDRESS'));

                    if($gCurrentUser->getValue('POSTCODE') !== '')
                    {
                        $route_url .= ',%20'.urlencode($gCurrentUser->getValue('POSTCODE'));
                    }
                    if($gCurrentUser->getValue('CITY') !== '')
                    {
                        $route_url .= ',%20'.urlencode($gCurrentUser->getValue('CITY'));
                    }
                    if($gCurrentUser->getValue('COUNTRY') !== '')
                    {
                        $route_url .= ',%20'.urlencode($gCurrentUser->getValue('COUNTRY'));
                    }

                    $route_url .= '&amp;daddr='.urlencode($date->getValue('dat_location'));

                    if($date->getValue('dat_country') !== '')
                    {
                        // With information about country Google finds the location much better
                        $route_url .= ',%20'.$date->getValue('dat_country');
                    }

                    $outputLinkLocation .= '
                        <a class="admidio-icon-link" href="'.$route_url.'" target="_blank">
                            <img src="'.THEME_URL.'/icons/map.png" alt="'.$gL10n->get('SYS_SHOW_ROUTE').'" title="'.$gL10n->get('SYS_SHOW_ROUTE').'" />
                        </a>';
                }
            }
            else
            {
                $outputLinkLocation = $date->getValue('dat_location');
            }
        }

        // if active, then show room information
        if($date->getValue('dat_room_id') > 0)
        {
            $room = new TableRooms($gDb, $date->getValue('dat_room_id'));

            if($getViewMode === 'html')
            {
                $roomLink = ADMIDIO_URL. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                $outputLinkRoom = '<strong><a data-toggle="modal" data-target="#admidio_modal" href="'.$roomLink.'">'.$room->getValue('room_name').'</a></strong>';
            }
            else
            {
                $outputLinkRoom = $room->getValue('room_name');
            }
        }

        // count participants of the date
        if($date->getValue('dat_rol_id') > 0)
        {
            $participants = new Participants($gDb, $date->getValue('dat_rol_id'));
            $outputNumberMembers = $participants->getCount();
            $outputNumberLeaders = $participants->getNumLeaders();

            if($getView === 'participants')
            {
                $participantsArray = $participants->getParticipantsArray($date->getValue('dat_rol_id'));
            }
        }

        // Links for the participation only in html mode
        if($date->getValue('dat_rol_id') > 0 && $getViewMode === 'html')
        {
            if($row['member_date_role'] > 0)
            {
                $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id');

                if ($getView === 'detail')
                {
                    $outputButtonParticipation = '
                        <button class="btn btn-default" onclick="window.location.href=\''.$buttonURL.'\'">
                            <img src="'.THEME_URL.'/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" />'.$gL10n->get('DAT_CANCEL').'</button>';
                }
                else
                {
                    $outputButtonParticipation = '
                        <a class="admidio-icon-link" href="'.$buttonURL.'">
                            <img src="'.THEME_URL.'/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" title="'.$gL10n->get('DAT_CANCEL').'" /></a>';
                }
            }
            else
            {
                $participationPossible = true;

                if($date->getValue('dat_max_members'))
                {
                    // Check limit of participants
                    if($participants->getCount($date->getValue('dat_rol_id')) >= $date->getValue('dat_max_members'))
                    {
                        $participationPossible = false;
                    }
                }

                if($participationPossible)
                {
                    $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?mode=3&amp;dat_id='.$date->getValue('dat_id');

                    if ($getView === 'detail')
                    {
                        $outputButtonParticipation = '
                            <button class="btn btn-default" onclick="window.location.href=\''.$buttonURL.'\'">
                                <img src="'.THEME_URL.'/icons/ok.png" alt="'.$gL10n->get('DAT_ATTEND').'" />'.$gL10n->get('DAT_ATTEND').'</button>';
                    }
                    else
                    {
                        $outputButtonParticipation = '
                            <a class="admidio-icon-link" href="'.$buttonURL.'">
                                <img src="'.THEME_URL.'/icons/ok.png" alt="'.$gL10n->get('DAT_ATTEND').'" title="'.$gL10n->get('DAT_ATTEND').'" /></a>';
                    }
                }
                else
                {
                    $outputButtonParticipation = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                }
            }

            // Link to participants list
            if($gValidLogin && $gCurrentUser->hasRightViewRole($date->getValue('dat_rol_id')))
            {
                if($outputNumberMembers > 0 || $outputNumberLeaders > 0)
                {
                    $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?mode=html&amp;rol_ids='.$date->getValue('dat_rol_id');

                    if ($getView === 'detail')
                    {
                        $outputButtonParticipants = '
                            <button class="btn btn-default" onclick="window.location.href=\''.$buttonURL.'\'">
                                <img src="'.THEME_URL.'/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" />'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'</button>';
                    }
                    else
                    {
                        $outputButtonParticipants = '
                            <a class="admidio-icon-link" href="'.$buttonURL.'">
                                <img src="'.THEME_URL.'/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" title="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" /></a>';
                    }
                }
            }

            // Link to send email to participants
            if($gValidLogin && $gCurrentUser->hasRightSendMailToRole($date->getValue('dat_rol_id')))
            {
                if($outputNumberMembers > 0 || $outputNumberLeaders > 0)
                {
                    $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?rol_id='.$date->getValue('dat_rol_id');

                    if ($getView === 'detail')
                    {
                        $outputButtonParticipantsEmail = '
                            <button class="btn btn-default" onclick="window.location.href=\''.$buttonURL.'\'">
                                <img src="'.THEME_URL.'/icons/email.png" alt="'.$gL10n->get('MAI_SEND_EMAIL').'" />'.$gL10n->get('MAI_SEND_EMAIL').'
                            </button>';
                    }
                    else
                    {
                        $outputButtonParticipantsEmail = '
                            <a class="admidio-icon-link" href="'.$buttonURL.'">
                                <img src="'.THEME_URL.'/icons/email.png" alt="'.$gL10n->get('MAI_SEND_EMAIL').'" title="'.$gL10n->get('MAI_SEND_EMAIL').'" />
                            </a>';
                    }
                }
            }

            // Link for managing new participants
            if($row['mem_leader'] == 1)
            {
                $buttonURL = ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$date->getValue('dat_rol_id');

                if ($getView === 'detail')
                {
                    $outputButtonParticipantsAssign = '
                        <button class="btn btn-default" onclick="window.location.href=\''.$buttonURL.'\'">
                            <img src="'.THEME_URL.'/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" />'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'
                        </button>';
                }
                else
                {
                    $outputButtonParticipantsAssign = '
                        <a class="admidio-icon-link" href="'.$buttonURL.'">
                            <img src="'.THEME_URL.'/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" title="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" />
                        </a>';
                }
            }
        }

        if($getView === 'detail')
        {
            if ($date->getValue('dat_all_day') == 0)
            {
                // Write start in array
                $dateElements[] = array($gL10n->get('SYS_START'), '<strong>'.$date->getValue('dat_begin', $gPreferences['system_time']).'</strong> '.$gL10n->get('SYS_CLOCK'));
                // Write end in array
                $dateElements[] = array($gL10n->get('SYS_END'), '<strong>'.$date->getValue('dat_end', $gPreferences['system_time']).'</strong> '.$gL10n->get('SYS_CLOCK'));
            }

            $dateElements[] = array($gL10n->get('DAT_CALENDAR'), '<strong>'.$date->getValue('cat_name').'</strong>');
            if($outputLinkLocation !== '')
            {
                $dateElements[] = array($gL10n->get('DAT_LOCATION'), $outputLinkLocation);
            }
            if($outputLinkRoom !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_ROOM'), $outputLinkRoom);
            }
            if($outputNumberLeaders !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_LEADERS'), '<strong>'.$outputNumberLeaders.'</strong>');
            }
            if($outputNumberMembers !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), '<strong>'.$outputNumberMembers.'</strong>');
            }

            // show panel view of events

            $cssClassHighlight = '';

            // Change css if date is highlighted
            if($row['dat_highlight'] == 1)
            {
                $cssClassHighlight = ' admidio-event-highlight';
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
            }

            if(!$firstElement)
            {
                $htmlDateElements .= '</div>';

            }

            $page->addHtml('
                <div class="panel panel-primary'.$cssClassHighlight.'" id="dat_'.$date->getValue('dat_id').'">
                    <div class="panel-heading">
                        <div class="pull-left">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/dates.png" alt="'.$date->getValue('dat_headline').'" />' .
                            $date->getValue('dat_begin', $gPreferences['system_date']).$outputEndDate.' '.$date->getValue('dat_headline') . '
                        </div>
                        <div class="pull-right text-right">' .
                            $outputButtonIcal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete . '
                        </div>
                    </div>
                    <div class="panel-body">
                        ' . $htmlDateElements . '<br />
                        <p>' . $date->getValue('dat_description') . '</p>');

            if($outputButtonParticipation !== '' || $outputButtonParticipants !== ''
            || $outputButtonParticipantsEmail !== '' || $outputButtonParticipantsAssign !== '')
            {
                $page->addHtml('<div class="btn-group">'.$outputButtonParticipation.$outputButtonParticipants.$outputButtonParticipantsEmail.$outputButtonParticipantsAssign.'</div>');
            }
            $page->addHtml('
                </div>
                <div class="panel-footer">'.
                    // show information about user who created the recordset and changed it
                    admFuncShowCreateChangeInfoByName($row['create_name'],
                                                      $date->getValue('dat_timestamp_create'),
                                                      $row['change_name'],
                                                      $date->getValue('dat_timestamp_change'),
                                                      $date->getValue('dat_usr_id_create'),
                                                      $date->getValue('dat_usr_id_change')).'
                    </div>
                </div>');
        }
        else
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
                if ($date->getValue('dat_all_day') == 1)
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
                $columnValues[] = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?id='.$date->getValue('dat_id').'&amp;view_mode=html&view=detail&amp;headline='.$date->getValue('dat_headline').'">'.$date->getValue('dat_headline').'</a>';
            }
            else
            {
                $columnValues[] = $date->getValue('dat_headline');
            }

            if($getView === 'room')
            {
                $columnValues[] = $outputLinkRoom;
                $columnValues[] = $outputNumberLeaders;
            }

            if($getView === 'compact' || $getView === 'room')
            {
                if($date->getValue('dat_rol_id') > 0)
                {
                    if($date->getValue('dat_max_members') > 0)
                    {
                        $htmlParticipants = $outputNumberMembers.' / '.$date->getValue('dat_max_members');
                    }
                    else
                    {
                        $htmlParticipants = $outputNumberMembers.'&nbsp;';
                    }

                    if($outputNumberMembers > 0)
                    {
                        $htmlParticipants .= $outputButtonParticipants.$outputButtonParticipantsEmail;
                    }

                    $columnValues[] = $htmlParticipants;
                }
                else
                {
                    $columnValues[] = '';
                }
            }

            if($getView === 'compact')
            {
                if($outputLinkLocation !== '')
                {
                    $columnValues[] = $outputLinkLocation;
                }
                else
                {
                    $columnValues[] = '';
                }
            }
            elseif($getView === 'participants')
            {
                $columnValue = '';

                if(is_array($participantsArray))
                {
                    // Only show participants if user has right to view the list, is leader or has permission to create/edit events
                    if ($gCurrentUser->hasRightViewRole($date->getValue('dat_rol_id')) 
                        || $row['mem_leader'] == 1 
                        || $gCurrentUser->editDates())
                    {
                        foreach($participantsArray as $participant)
                        {
                            $columnValue .= $participant['firstname']. ' '. $participant['surname']. ', ';
                        }
                    }
                }
                $columnValues[] = substr($columnValue, 0, -2);
            }
            elseif($getView === 'description')
            {
                $columnValues[] = $date->getValue('dat_description');
            }

            if($getViewMode === 'html')
            {
                $columnValues[] = $outputButtonIcal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete;
            }

            $compactTable->addRowByArray($columnValues, null, array('class' => $cssClass));
        }
    }  // End foreach

    // Output table bottom for compact view
    if ($getView === 'compact' || $getView === 'room' || $getView === 'participants' || $getView === 'description')
    {
        $page->addHtml($compactTable->show());
    }
}

// If necessary show links to navigate to next and previous recordsets of the query
$base_url = ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?view='.$getView.'&mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish').'&view_mode='.$getViewMode;
$page->addHtml(admFuncGeneratePagination($base_url, $datesTotalCount, $datesResult['limit'], $getStart, true));
$page->show();
