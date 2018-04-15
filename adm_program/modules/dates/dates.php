<?php
/**
 ***********************************************************************************************
 * Show a list of all events
 *
 * @copyright 2004-2018 The Admidio Team
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
 * date_from - set the minimum date of the events that should be shown
 *             if this parameter is not set than the actual date is set
 * date_to   - set the maximum date of the events that should be shown
 *             if this parameter is not set than this date is set to 31.12.9999
 * view_mode - Content output in 'html' or 'print' view
 * view      - Content output in different views like 'detail', 'list'
 *             (Default: according to preferences)
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
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
$getView     = admFuncVariableIsValid($_GET, 'view',      'string', array('defaultValue' => $gSettingsManager->getString('dates_view'), 'validValues' => array('detail', 'compact', 'room', 'participants', 'description')));
$participateModalForm      = false;
$participationPossible     = true;

// check if module is active
if((int) $gSettingsManager->get('enable_dates_module') === 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif((int) $gSettingsManager->get('enable_dates_module') === 2)
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
if($gSettingsManager->getInt('dates_per_page') > 0 && $getViewMode === 'html')
{
    $datesPerPage = $gSettingsManager->getInt('dates_per_page');
}
else
{
    $datesPerPage = $dates->getDataSetCount();
}

// read relevant events from database
$datesResult = $dates->getDataSet($getStart, $datesPerPage);

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

    if($gSettingsManager->getBool('enable_rss') && (int) $gSettingsManager->get('enable_dates_module') === 1)
    {
        $page->addRssFile(
            safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/rss_dates.php', array('headline' => $getHeadline)),
            $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $getHeadline))
        );
    }

    $page->addJavascript('
        $("#sel_change_view").change(function() {
            self.location.href = "'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('mode' => $getMode, 'headline' => $getHeadline, 'date_from' => $dates->getParameter('dateStartFormatAdmidio'), 'date_to' => $dates->getParameter('dateEndFormatAdmidio'), 'cat_id' => $getCatId)) . '&view=" + $("#sel_change_view").val();
        });

        $("#menu_item_print_view").click(function() {
            window.open("'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('view_mode' => 'print', 'view' => $getView, 'mode' => $getMode, 'headline' => $getHeadline, 'cat_id' => $getCatId, 'id' => $getId, 'date_from' => $dates->getParameter('dateStartFormatEnglish'), 'date_to' => $dates->getParameter('dateEndFormatEnglish'))) . '", "_blank");
        });', true);

    // get module menu
    $datesMenu = $page->getMenu();

    // If default view mode is set to compact we need a back navigation if one date is selected for detail view
    if($getId > 0)
    {
        // add back link to module menu
        $datesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
        $datesMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');
    }

    // Add new event
    if(count($gCurrentUser->getAllEditableCategories('DAT')) > 0 && $getId === 0)
    {
        $datesMenu->addItem(
            'admMenuItemAdd', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('headline' => $getHeadline)),
            $gL10n->get('SYS_CREATE_VAR', array($getHeadline)), 'add.png'
        );
    }

    if($getId === 0)
    {
        $form = new HtmlForm('navbar_change_view_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        if($gSettingsManager->getBool('dates_show_rooms'))
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
        $form->addSelectBox(
            'sel_change_view', $gL10n->get('SYS_VIEW'), $selectBoxEntries,
            array('defaultValue' => $getView, 'showContextDependentFirstEntry' => false)
        );
        $datesMenu->addForm($form->show());

        // show print button
        $datesMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');

        if($gSettingsManager->getBool('enable_dates_ical') || $gCurrentUser->isAdministrator() || $gCurrentUser->editDates())
        {
            $datesMenu->addItem('menu_item_extras', '', $gL10n->get('SYS_MORE_FEATURES'), '', 'right');
        }

        // ical Download
        if($gSettingsManager->getBool('enable_dates_ical'))
        {
            $datesMenu->addItem(
                'admMenuItemICal', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/ical_dates.php', array('headline' => $getHeadline, 'cat_id' => $getCatId, 'date_from' => $getDateFrom, 'date_to' => $getDateTo)),
                $gL10n->get('DAT_EXPORT_ICAL'), 'database_out.png', 'right', 'menu_item_extras'
            );
        }

        if($gCurrentUser->editDates())
        {
            // if no calendar selectbox is shown, then show link to edit calendars
            $datesMenu->addItem(
                'admMenuItemCategories', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'DAT', 'title' => $gL10n->get('DAT_CALENDAR'))),
                $gL10n->get('DAT_MANAGE_CALENDARS'), 'application_view_tile.png', 'right', 'menu_item_extras'
            );
        }

        if($gCurrentUser->isAdministrator())
        {
            // show link to system preferences of weblinks
            $datesMenu->addItem(
                'admMenuItemPreferencesLinks', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'events')),
                $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras'
            );
        }
    }

    // create filter menu with elements for calendar and start-/enddate
    $filterNavbar = new HtmlNavbar('menu_dates_filter', null, null, 'filter');
    $form = new HtmlForm('navbar_filter_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('headline' => $getHeadline, 'view' => $getView)), $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addSelectBoxForCategories(
        'cat_id', $gL10n->get('DAT_CALENDAR'), $gDb, 'DAT', HtmlForm::SELECT_BOX_MODUS_FILTER,
        array('defaultValue' => $dates->getParameter('cat_id'))
    );
    $form->addInput(
        'date_from', $gL10n->get('SYS_START'), $dates->getParameter('dateStartFormatAdmidio'),
        array('type' => 'date', 'maxLength' => 10)
    );
    $form->addInput(
        'date_to', $gL10n->get('SYS_END'), $dates->getParameter('dateEndFormatAdmidio'),
        array('type' => 'date', 'maxLength' => 10)
    );
    $form->addInput('view', '', $getView, array('property' => HtmlForm::FIELD_HIDDEN));
    $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
    $filterNavbar->addForm($form->show());
    $page->addHtml($filterNavbar->show());
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
        $page->addHtml('<h3>' . $gL10n->get('DAT_PERIOD_FROM_TO', array($dates->getParameter('dateStartFormatAdmidio'), $dates->getParameter('dateEndFormatAdmidio'))) . '</h3>');
    }
}

if($datesResult['totalCount'] === 0)
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
        if ((int) $date->getValue('dat_allow_comments') === 1 || (int) $date->getValue('dat_additional_guests') === 1)
        {
            $participateModalForm = true;
        }

        // set end date of event
        if($date->getValue('dat_begin', $gSettingsManager->getString('system_date')) !== $date->getValue('dat_end', $gSettingsManager->getString('system_date')))
        {
            $outputEndDate = ' - ' . $date->getValue('dat_end', $gSettingsManager->getString('system_date'));
        }

        if($getViewMode === 'html')
        {
            // ical Download
            if($gSettingsManager->getBool('enable_dates_ical'))
            {
                $outputButtonIcal = '
                    <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('dat_id' => $dateId, 'mode' => 6)).'">
                        <img src="'.THEME_URL.'/icons/database_out.png" alt="' . $gL10n->get('DAT_EXPORT_ICAL') . '" title="' . $gL10n->get('DAT_EXPORT_ICAL') . '" /></a>';
            }

            // change and delete is only for users with additional rights
            if ($date->isEditable())
            {
                $outputButtonCopy = '
                    <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('dat_id' => $dateId, 'copy' => 1, 'headline' => $getHeadline)) . '">
                        <img src="'.THEME_URL.'/icons/application_double.png" alt="' . $gL10n->get('SYS_COPY') . '" title="' . $gL10n->get('SYS_COPY') . '" /></a>';
                $outputButtonEdit = '
                    <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('dat_id' => $dateId, 'headline' => $getHeadline)) . '">
                        <img src="'.THEME_URL.'/icons/edit.png" alt="' . $gL10n->get('SYS_EDIT') . '" title="' . $gL10n->get('SYS_EDIT') . '" /></a>';
                $outputButtonDelete = '
                    <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'dat', 'element_id' => 'dat_' . $dateId,
                        'name' => $date->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ' . $dateHeadline, 'database_id' => $dateId)) . '">
                        <img src="'.THEME_URL.'/icons/delete.png" alt="' . $gL10n->get('SYS_DELETE') . '" title="' . $gL10n->get('SYS_DELETE') . '" /></a>';
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

            if($gSettingsManager->getBool('dates_show_map_link') && $countLocationWords > 1 && $getViewMode === 'html')
            {
                $urlParam = $dateLocation;

                $dateCountry = $date->getValue('dat_country');
                if($dateCountry)
                {
                    // Better results with additional country information
                    $urlParam .= ', ' . $dateCountry;
                }
                $locationUrl = safeUrl('https://www.google.com/maps/search/', array('api' => 1, 'query' => $urlParam));

                $outputLinkLocation = '
                    <a href="' . $locationUrl . '" target="_blank" title="' . $gL10n->get('DAT_SHOW_ON_MAP') . '"/>
                        <strong>' . $dateLocation . '</strong>
                    </a>';

                // if valid login and enough information about address exist - calculate the route
                if($gValidLogin && $gCurrentUser->getValue('STREET') !== ''
                && ($gCurrentUser->getValue('POSTCODE') !== '' || $gCurrentUser->getValue('CITY') !== ''))
                {
                    $routeOriginParam = array($gCurrentUser->getValue('STREET'));

                    if($gCurrentUser->getValue('POSTCODE') !== '')
                    {
                        $routeOriginParam[] = $gCurrentUser->getValue('POSTCODE');
                    }
                    if($gCurrentUser->getValue('CITY') !== '')
                    {
                        $routeOriginParam[] = $gCurrentUser->getValue('CITY');
                    }
                    if($gCurrentUser->getValue('COUNTRY') !== '')
                    {
                        $routeOriginParam[] = $gCurrentUser->getValue('COUNTRY');
                    }

                    $routeUrl = safeUrl('https://www.google.com/maps/dir/', array('api' => 1, 'origin' => implode(', ', $routeOriginParam), 'destination' => $urlParam));

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
                $roomLink = safeUrl(ADMIDIO_URL. '/adm_program/system/msg_window.php', array('message_id' => 'room_detail', 'message_title' => 'DAT_ROOM_INFORMATIONS', 'message_var1' => $dateRoomId, 'inline' => 'true'));
                $outputLinkRoom = '<strong><a data-toggle="modal" data-target="#admidio_modal" href="' . $roomLink . '">' . $room->getValue('room_name') . '</a></strong>';
            }
            else // $getViewMode = 'print'
            {
                $outputLinkRoom = $room->getValue('room_name');
            }
        }

        // if current user is allowed to participate then show buttons for participation
        if($date->allowedToParticipate())
        {
            $participants = new Participants($gDb, $dateRolId);
            $outputNumberMembers = $participants->getCount();
            $outputNumberLeaders = $participants->getNumLeaders();
            $participantsArray   = $participants->getParticipantsArray($dateRolId);

            if($date->getValue('dat_deadline') !== null)
            {
                $outputDeadline = $date->getValue('dat_deadline', $gSettingsManager->getString('system_date'). ' ' . $gSettingsManager->getString('system_time'));
            }

            // Links for the participation only in html mode
            if($getViewMode === 'html')
            {
                // If user is invited to the event then the approval state is not initialized and has value "null" in data table
                if($row['member_date_role'] > 0 && $row['member_approval_state'] == null)
                {
                    $row['member_approval_state'] = ModuleDates::MEMBER_APPROVAL_STATE_INVITED;
                }

                switch($row['member_approval_state'])
                {
                    case ModuleDates::MEMBER_APPROVAL_STATE_INVITED:
                        $buttonText = $gL10n->get('DAT_USER_INVITED');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/warning.png" alt="' . $gL10n->get('DAT_USER_INVITED') . '" title="' . $gL10n->get('DAT_USER_INVITED') . '"/>';
                        break;
                    case ModuleDates::MEMBER_APPROVAL_STATE_TENTATIVE:
                        $buttonText = $gL10n->get('DAT_USER_TENTATIVE');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/help_violett.png" alt="' . $gL10n->get('DAT_USER_TENTATIVE') . '" title="' . $gL10n->get('DAT_USER_TENTATIVE') . '"/>';
                        break;
                    case ModuleDates::MEMBER_APPROVAL_STATE_ATTEND:
                        $buttonText = $gL10n->get('DAT_USER_ATTEND');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/ok.png" alt="' . $gL10n->get('DAT_USER_ATTEND') . '" title="' . $gL10n->get('DAT_USER_ATTEND') . '"/>';
                        break;
                    case ModuleDates::MEMBER_APPROVAL_STATE_REFUSED:
                        $buttonText = $gL10n->get('DAT_USER_REFUSED');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/no.png" alt="' . $gL10n->get('DAT_USER_REFUSED') . '" title="' . $gL10n->get('DAT_USER_REFUSED') . '"/>';
                        break;
                    default:
                        $buttonText = $gL10n->get('DAT_ATTEND');
                        $iconParticipationStatus = '<img src="'.THEME_URL.'/icons/edit.png" alt="' . $gL10n->get('DAT_ATTEND') . '" title="' . $gL10n->get('DAT_ATTEND') . '"/>';
                        break;
                }

                if ($getView !== 'detail')
                {
                    // Status text only in detail view
                    $buttonText = '';
                }

                $usrId = (int) $gCurrentUser->getValue('usr_id');

                // Check limit of participants
                if ($date->getValue('dat_max_members') > 0 && $outputNumberMembers >= $date->getValue('dat_max_members'))
                {
                    // No further members allowed
                    $participationPossible = false;

                    // Check current user. If user is member of the event role then get his current approval status and set the options
                    if (in_array($usrId, $participantsArray, true))
                    {
                        switch ($participantsArray[$usrId]['approved'])
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
                                        <a class="btn" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('mode' => '3', 'dat_id' => $dateId)) . '"' . $disableStatusAttend . '>
                                            <img src="'.THEME_URL.'/icons/ok.png" alt="' . $gL10n->get('DAT_ATTEND') . '" title="' . $gL10n->get('DAT_ATTEND') . '"/>' . $gL10n->get('DAT_ATTEND') . '
                                        </a>
                                    </li>
                                    <li>
                                        <a class="btn" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('mode' => '7', 'dat_id' => $dateId)) . '"' . $disableStatusTentative . '>
                                            <img src="'.THEME_URL.'/icons/help_violett.png" alt="' . $gL10n->get('DAT_USER_TENTATIVE') . '" title="' . $gL10n->get('DAT_USER_TENTATIVE') . '"/>' . $gL10n->get('DAT_USER_TENTATIVE') . '
                                        </a>
                                    </li>
                                    <li>
                                        <a class="btn" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('mode' => '4', 'dat_id' => $dateId)) . '">
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
                                <button class="btn btn-default" data-toggle="modal" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/popup_participation.php', array('dat_id' => $dateId)) . '" data-target="#admidio_modal">' . $iconParticipationStatus . $buttonText . '
                            </div>';
                    }
                    // Reset flags and parameters
                    $disableStatusAttend    = '';
                    $disableStatusTentative = '';
                    $participateModalForm   = false;
                }
                else
                {
                    // Show warning for member of the date role if deadline is exceeded and now no changes are possible anymore
                    if ($participants->isMemberOfEvent($usrId))
                    {
                        $attentionDeadline = '
                            <div class="alert alert-warning" role="alert">
                                <strong>' .$gL10n->get('DAT_DEADLINE') . '! </strong>' . $gL10n->get('DAT_DEADLINE_ATTENTION') . '
                            </div>';
                    }
                }

                if ($participationPossible === false)
                {
                    // Check participation of current user. If user is member of the event role, he/she should also be able to change to possible states.
                    if (!$participants->isMemberOfEvent($usrId) && $date->getValue('dat_max_members') > 0)
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
                        $buttonURL = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php', array('mode' => 'html', 'rol_ids' => $dateRolId));

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
                        $buttonURL = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('rol_id' => $dateRolId));

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
                    $buttonURL = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php', array('rol_id' => $dateRolId));

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
                $dateElements[] = array($gL10n->get('SYS_START'), '<strong>' . $date->getValue('dat_begin', $gSettingsManager->getString('system_time')) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
                // Write end in array
                $dateElements[] = array($gL10n->get('SYS_END'), '<strong>' . $date->getValue('dat_end', $gSettingsManager->getString('system_time')) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
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
                            $date->getValue('dat_begin', $gSettingsManager->getString('system_date')) . $outputEndDate . ' ' . $dateHeadline . '
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
            $dateBegin = $date->getValue('dat_begin', $gSettingsManager->getString('system_date'));
            $timeBegin = $date->getValue('dat_begin', $gSettingsManager->getString('system_time'));

            // date beginn
            $dateEnd = $date->getValue('dat_end', $gSettingsManager->getString('system_date'));
            $timeEnd = $date->getValue('dat_end', $gSettingsManager->getString('system_time'));

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
                    $objDateBegin = new \DateTime($row['dat_begin']);
                    $objDateEnd = new \DateTime($row['dat_end']);
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
                if (strlen($date->getValue('dat_deadline')) > 0)
                {
                    $columnValues[] = '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('id' => $dateId, 'view_mode' => 'html', 'view' => 'detail', 'headline' => $dateHeadline)) . '">' . $dateHeadline . '<br />' . $gL10n->get('DAT_DEADLINE') . ': ' . $outputDeadline . '</a>';
                }
                else
                {
                    $columnValues[] = '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('id' => $dateId, 'view_mode' => 'html', 'view' => 'detail', 'headline' => $dateHeadline)) . '">' . $dateHeadline . '</a>';
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
$baseUrl = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('view' => $getView, 'mode' => $getMode, 'headline' => $getHeadline, 'cat_id' => $getCatId, 'date_from' => $dates->getParameter('dateStartFormatEnglish'), 'date_to' => $dates->getParameter('dateEndFormatEnglish'), 'view_mode' => $getViewMode));
$page->addHtml(admFuncGeneratePagination($baseUrl, $datesResult['totalCount'], $datesResult['limit'], $getStart));
$page->show();
