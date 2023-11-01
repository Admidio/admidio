<?php
/**
 ***********************************************************************************************
 * Show a list of all events
 *
 * @copyright 2004-2023 The Admidio Team
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
 * cat_uuid  - show all events of calendar with this UUID
 * dat_uuid  - UUID of a single event that should be shown
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
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old', 'all')));
$getStart    = admFuncVariableIsValid($_GET, 'start', 'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
$getDateUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'string');
$getShow     = admFuncVariableIsValid($_GET, 'show', 'string', array('defaultValue' => 'all', 'validValues' => array('all', 'maybe_participate', 'only_participate')));
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');
$getViewMode = admFuncVariableIsValid($_GET, 'view_mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'print')));
$getView     = admFuncVariableIsValid($_GET, 'view', 'string', array('defaultValue' => $gSettingsManager->getString('dates_view'), 'validValues' => array('detail', 'compact', 'room', 'participants', 'description')));

// check if module is active
if ((int) $gSettingsManager->get('enable_dates_module') === 0) {
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_dates_module') === 2) {
    // module only for valid Users
    require(__DIR__ . '/../../system/login_valid.php');
}

// create object and get recordset of available dates

$calendar = new TableCategory($gDb);

if ($getCatUuid !== '') {
    $calendar->readDataByUuid($getCatUuid);
}

try {
    $dates = new ModuleDates();
    $dates->setParameter('mode', $getMode);
    $dates->setParameter('cat_id', $calendar->getValue('cat_id'));
    $dates->setParameter('dat_uuid', $getDateUuid);
    $dates->setParameter('show', $getShow);
    $dates->setParameter('view_mode', $getViewMode);
    $dates->setDateRange($getDateFrom, $getDateTo);
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

// Number of events each page for default view 'html' or 'compact' view
if ($getViewMode === 'html' && $getView === 'detail') {
    $datesPerPage = $gSettingsManager->getInt('dates_per_page');
} else {
    $datesPerPage = $dates->getDataSetCount();
}

// read relevant events from database
$datesResult = $dates->getDataSet($getStart, $datesPerPage);

if ($getViewMode === 'html') {
    if ($getDateUuid !== '') {
        $gNavigation->addUrl(CURRENT_URL, $dates->getHeadline($getHeadline));
    } else {
        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $dates->getHeadline($getHeadline), 'fa-calendar-alt');
    }
}

// create html page object
$page = new HtmlPage('admidio-events', $dates->getHeadline($getHeadline));

if ($getViewMode === 'html') {
    $datatable  = true;
    $hoverRows  = true;
    $classTable = 'table';

    if ($gSettingsManager->getBool('enable_rss') && (int) $gSettingsManager->get('enable_dates_module') === 1) {
        $page->addRssFile(
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/rss_dates.php', array('headline' => $getHeadline)),
            $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $getHeadline))
        );
    }

    $page->addJavascript('
        $("#sel_change_view").change(function() {
            self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('mode' => $getMode, 'headline' => $getHeadline, 'date_from' => $dates->getParameter('dateStartFormatAdmidio'), 'date_to' => $dates->getParameter('dateEndFormatAdmidio'), 'cat_uuid' => $getCatUuid)) . '&view=" + $("#sel_change_view").val();
        });

        $("#menu_item_event_print_view").click(function() {
            window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('view_mode' => 'print', 'view' => $getView, 'mode' => $getMode, 'headline' => $getHeadline, 'cat_uuid' => $getCatUuid, 'dat_uuid' => $getDateUuid, 'date_from' => $dates->getParameter('dateStartFormatEnglish'), 'date_to' => $dates->getParameter('dateEndFormatEnglish'))) . '", "_blank");
        });', true);

    // If default view mode is set to compact we need a back navigation if one date is selected for detail view
    if ($getDateUuid !== '') {
        // add back link to module menu
        $page->addPageFunctionsMenuItem('menu_item_event_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');
    }

    // Add new event
    if (count($gCurrentUser->getAllEditableCategories('DAT')) > 0 && $getDateUuid === '') {
        $page->addPageFunctionsMenuItem(
            'menu_item_event_add',
            $gL10n->get('SYS_CREATE_VAR', array($getHeadline)),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('headline' => $getHeadline)),
            'fa-plus-circle'
        );
    }

    if ($getDateUuid === '') {
        // show print button
        $page->addPageFunctionsMenuItem('menu_item_event_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');

        // iCal Download
        if ($gSettingsManager->getBool('enable_dates_ical')) {
            $page->addPageFunctionsMenuItem(
                'menu_item_event_ical',
                $gL10n->get('DAT_EXPORT_ICAL'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/ical_dates.php', array('cat_uuid' => $getCatUuid, 'date_from' => $getDateFrom, 'date_to' => $getDateTo)),
                'fa-file-export'
            );
        }

        if ($gCurrentUser->editDates()) {
            // if no calendar select box is shown, then show link to edit calendars
            $page->addPageFunctionsMenuItem(
                'menu_item_event_categories',
                $gL10n->get('SYS_EDIT_CALENDARS'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'DAT')),
                'fa-th-large'
            );
        }

        // create filter menu with elements for calendar and start/end date
        $filterNavbar = new HtmlNavbar('menu_dates_filter', null, null, 'filter');
        $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', $page, array('type' => 'navbar', 'setFocus' => false));
        $form->addInput('headline', 'headline', $getHeadline, array('property' => HtmlForm::FIELD_HIDDEN));
        if ($gSettingsManager->getBool('dates_show_rooms')) {
            $selectBoxEntries = array(
                'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
                'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
                'room'         => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'),
                'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
                'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
            );
        } else {
            $selectBoxEntries = array(
                'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
                'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
                'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
                'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
            );
        }
        $form->addSelectBox(
            'sel_change_view',
            $gL10n->get('SYS_VIEW'),
            $selectBoxEntries,
            array('defaultValue' => $getView, 'showContextDependentFirstEntry' => false)
        );
        $form->addSelectBoxForCategories(
            'cat_uuid',
            $gL10n->get('DAT_CALENDAR'),
            $gDb,
            'DAT',
            HtmlForm::SELECT_BOX_MODUS_FILTER,
            array('defaultValue' => $getCatUuid)
        );
        $form->addInput(
            'date_from',
            $gL10n->get('SYS_START'),
            $dates->getParameter('dateStartFormatAdmidio'),
            array('type' => 'date', 'maxLength' => 10)
        );
        $form->addInput(
            'date_to',
            $gL10n->get('SYS_END'),
            $dates->getParameter('dateEndFormatAdmidio'),
            array('type' => 'date', 'maxLength' => 10)
        );
        $form->addInput('view', '', $getView, array('property' => HtmlForm::FIELD_HIDDEN));
        $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
        $filterNavbar->addForm($form->show());
        $page->addHtml($filterNavbar->show());
    }
} else { // $getViewMode = 'print'
    $datatable  = false;
    $hoverRows  = false;
    $classTable = 'table table-condensed table-striped';

    // create html page object without the custom theme files
    $page->setPrintMode();

    if ($getDateUuid === '') {
        $page->addHtml('<h3>' . $gL10n->get('DAT_PERIOD_FROM_TO', array($dates->getParameter('dateStartFormatAdmidio'), $dates->getParameter('dateEndFormatAdmidio'))) . '</h3>');
    }
}

if ($datesResult['totalCount'] === 0) {
    // No events found
    if ($getDateUuid !== '') {
        $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRY') . '</p>');
    } else {
        $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
    }
} else {
    // Output table header for compact view
    if ($getView !== 'detail') { // $getView = 'compact' or 'room' or 'participants' or 'description'
        $compactTable = new HtmlTable('events_compact_table', $page, $hoverRows, $datatable, $classTable);
        $compactTable->setDatatablesRowsPerPage($gSettingsManager->getInt('dates_per_page'));

        $columnHeading = array();
        $columnAlign   = array();

        switch ($getView) {
            case 'compact':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_PARTICIPANTS'), $gL10n->get('DAT_LOCATION'));
                $columnAlign   = array('center', 'left', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(6));
                $compactTable->setDatatablesColumnsNotHideResponsive(array(6));
                break;
            case 'room':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_ROOM'), $gL10n->get('SYS_LEADERS'), $gL10n->get('SYS_PARTICIPANTS'));
                $columnAlign   = array('center', 'left', 'left', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(7));
                $compactTable->setDatatablesColumnsNotHideResponsive(array(7));
                break;
            case 'participants':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_PARTICIPANTS'));
                $columnAlign   = array('center', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(5));
                $compactTable->setDatatablesColumnsNotHideResponsive(array(5));
                $compactTable->setColumnWidth(4, '35%');
                break;
            case 'description':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_DESCRIPTION'));
                $columnAlign   = array('center', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(array(5));
                $compactTable->setDatatablesColumnsNotHideResponsive(array(5));
                $compactTable->setColumnWidth(4, '35%');
                break;
        }

        if ($getViewMode === 'html') {
            $columnHeading[] = '&nbsp;';
            $columnAlign[]   = 'right';
        }

        $compactTable->setColumnAlignByArray($columnAlign);
        $compactTable->addRowHeadingByArray($columnHeading);
    }

    // create dummy date object
    $date = new TableDate($gDb);

    foreach ($datesResult['recordset'] as $row) {
        // write of current event data to date object
        $date->clear();
        $date->setArray($row);

        $dateUuid     = $date->getValue('dat_uuid');
        $dateRolId    = $date->getValue('dat_rol_id');
        $dateHeadline = $date->getValue('dat_headline');

        // initialize all output elements
        $attentionDeadline  = '';
        $outputEndDate      = '';
        $outputButtonICal   = '';
        $outputButtonEdit   = '';
        $outputButtonDelete = '';
        $outputButtonCopy   = '';
        $outputButtonParticipation      = '';
        $outputButtonParticipants       = '';
        $outputButtonParticipantsEmail  = '';
        $outputButtonParticipantsAssign = '';
        $outputLinkLocation    = '';
        $outputLinkRoom        = '';
        $outputNumberMembers   = '';
        $outputNumberLeaders   = '';
        $outputDeadline        = '';
        $dateElements          = array();
        $participantsArray     = array();
        $participateModalForm  = false;

        // If extended options for participation are allowed then use a modal form instead the dropdown button
        if ((int) $date->getValue('dat_allow_comments') === 1 || (int) $date->getValue('dat_additional_guests') === 1) {
            $participateModalForm = true;
        }

        // set end date of event
        if ($date->getValue('dat_begin', $gSettingsManager->getString('system_date')) !== $date->getValue('dat_end', $gSettingsManager->getString('system_date'))) {
            $outputEndDate = ' - ' . $date->getValue('dat_end', $gSettingsManager->getString('system_date'));
        }

        if ($getViewMode === 'html') {
            // iCal Download
            if ($gSettingsManager->getBool('enable_dates_ical')) {
                $outputButtonICal = '
                    <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('dat_uuid' => $dateUuid, 'mode' => 6)).'">
                        <i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('DAT_EXPORT_ICAL').'"></i></a>';
            }

            // change and delete is only for users with additional rights
            if ($date->isEditable()) {
                $outputButtonCopy = '
                    <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('dat_uuid' => $dateUuid, 'copy' => 1, 'headline' => $getHeadline)) . '">
                        <i class="fas fa-clone" data-toggle="tooltip" title="'.$gL10n->get('SYS_COPY').'"></i></a>';
                $outputButtonEdit = '
                    <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('dat_uuid' => $dateUuid, 'headline' => $getHeadline)) . '">
                        <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
                $outputButtonDelete = '
                    <a class="openPopup" href="javascript:void(0);"
                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'dat', 'element_id' => 'dat_' . $dateUuid,
                        'name' => $date->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ' . $dateHeadline, 'database_id' => $dateUuid)) . '">
                        <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
            }
        }

        $dateLocation = (string) $date->getValue('dat_location');
        if ($dateLocation !== '') {
            // Show map link, when at least 2 words available
            // having more than 3 characters each
            $countLocationWords = 0;
            foreach (preg_split('/[,; ]/', $dateLocation) as $value) {
                if (strlen($value) > 3) {
                    ++$countLocationWords;
                }
            }

            if ($gSettingsManager->getBool('dates_show_map_link') && $countLocationWords > 1 && $getViewMode === 'html') {
                $urlParam = $dateLocation;

                $dateCountry = $date->getValue('dat_country');
                if ($dateCountry) {
                    // Better results with additional country information
                    $urlParam .= ', ' . $dateCountry;
                }
                $locationUrl = SecurityUtils::encodeUrl('https://www.google.com/maps/search/', array('api' => 1, 'query' => $urlParam));

                $outputLinkLocation = '
                    <a href="' . $locationUrl . '" target="_blank" title="' . $gL10n->get('DAT_SHOW_ON_MAP') . '">' . $dateLocation . '</a>';

                // if valid login and enough information about address exist - calculate the route
                if ($gValidLogin && $gCurrentUser->getValue('STREET') !== ''
                && ($gCurrentUser->getValue('POSTCODE') !== '' || $gCurrentUser->getValue('CITY') !== '')) {
                    $routeOriginParam = array($gCurrentUser->getValue('STREET'));

                    if ($gCurrentUser->getValue('POSTCODE') !== '') {
                        $routeOriginParam[] = $gCurrentUser->getValue('POSTCODE');
                    }
                    if ($gCurrentUser->getValue('CITY') !== '') {
                        $routeOriginParam[] = $gCurrentUser->getValue('CITY');
                    }
                    if ($gCurrentUser->getValue('COUNTRY') !== '') {
                        $routeOriginParam[] = $gCurrentUser->getValue('COUNTRY');
                    }

                    $routeUrl = SecurityUtils::encodeUrl('https://www.google.com/maps/dir/', array('api' => 1, 'origin' => implode(', ', $routeOriginParam), 'destination' => $urlParam));

                    $outputLinkLocation .= '
                        <a class="admidio-icon-link" href="' . $routeUrl . '" target="_blank">
                            <i class="fas fa-map" data-toggle="tooltip" title="'.$gL10n->get('SYS_SHOW_ROUTE').'"></i>
                        </a>';
                }
            } else {
                $outputLinkLocation = $dateLocation;
            }
        }

        // if active, then show room information
        $dateRoomId = $date->getValue('dat_room_id');
        if ($dateRoomId > 0) {
            $room = new TableRooms($gDb, $dateRoomId);

            if ($getViewMode === 'html') {
                $roomLink = SecurityUtils::encodeUrl(ADMIDIO_URL. '/adm_program/system/msg_window.php', array('message_id' => 'room_detail', 'message_title' => 'DAT_ROOM_INFORMATIONS', 'message_var1' => $dateRoomId, 'inline' => 'true'));
                $outputLinkRoom = '<a class="openPopup" href="javascript:void(0);" data-href="' . $roomLink . '">' . $room->getValue('room_name') . '</a>';
            } else { // $getViewMode = 'print'
                $outputLinkRoom = $room->getValue('room_name');
            }
        }

        if ($dateRolId > 0) {
            $participants = new Participants($gDb, $dateRolId);

            // check the rights if the user is allowed to view the participants, or he is allowed to participate
            if ($gCurrentUser->hasRightViewRole((int)$date->getValue('dat_rol_id'))
                || $row['mem_leader'] == 1
                || $gCurrentUser->editDates()
                || $date->allowedToParticipate()) {
                $outputNumberMembers = $participants->getCount();
                $outputNumberLeaders = $participants->getNumLeaders();
                $participantsArray = $participants->getParticipantsArray();
            }

            // If user is invited to the event then the approval state is not initialized and has value "null" in data table
            if ($row['member_date_role'] > 0 && $row['member_approval_state'] == null) {
                $row['member_approval_state'] = ModuleDates::MEMBER_APPROVAL_STATE_INVITED;
            }

            // set status of participation
            switch ($row['member_approval_state']) {
                case ModuleDates::MEMBER_APPROVAL_STATE_INVITED:
                    $buttonText = $gL10n->get('SYS_PARTICIPATE_QUESTION');
                    $buttonClass = '';
                    $iconParticipationStatus = '<i class="fas fa-user-plus"></i>';
                    break;
                case ModuleDates::MEMBER_APPROVAL_STATE_ATTEND:
                    $buttonText = $gL10n->get('DAT_USER_ATTEND');
                    $buttonClass = 'admidio-event-approval-state-attend';
                    $iconParticipationStatus = '<i class="fas fa-check-circle"></i>';
                    break;
                case ModuleDates::MEMBER_APPROVAL_STATE_TENTATIVE:
                    $buttonText = $gL10n->get('DAT_USER_TENTATIVE');
                    $buttonClass = 'admidio-event-approval-state-tentative';
                    $iconParticipationStatus = '<i class="fas fa-question-circle"></i>';
                    break;
                case ModuleDates::MEMBER_APPROVAL_STATE_REFUSED:
                    $buttonText = $gL10n->get('DAT_USER_REFUSED');
                    $buttonClass = 'admidio-event-approval-state-cancel';
                    $iconParticipationStatus = '<i class="fas fa-times-circle"></i>';
                    break;
                default:
                    $buttonText = $gL10n->get('SYS_PARTICIPATE');
                    $buttonClass = '';
                    $iconParticipationStatus = '<i class="fas fa-edit"></i>';
            }

            if ($getView !== 'detail') {
                // Status text only in detail view
                $buttonText = '';
            }

            // show notice that no new participation could be assigned to the event because the deadline exceeded or
            // the max number of participants is reached.
            if ($date->deadlineExceeded() || $date->participantLimitReached()) {
                // Show warning for member of the date role if deadline is exceeded and now no changes are possible anymore
                if ($participants->isMemberOfEvent($gCurrentUserId)) {
                    if ($getView !== 'detail') {
                        $outputButtonParticipation = '<span class="' . $buttonClass . '">' . $iconParticipationStatus . '</span>';
                    }
                    $attentionDeadline = '
                            <div class="alert alert-info" role="alert">
                                <span class="' . $buttonClass . '">' . $iconParticipationStatus . ' ' . $buttonText . '</span>
                                <i class="fas fa-info-circle admidio-info-icon" data-toggle="popover"
                                    data-html="true" data-trigger="hover click" data-placement="auto"
                                    title="' . $gL10n->get('SYS_NOTE') . '" data-content="' . SecurityUtils::encodeHTML($gL10n->get('DAT_DEADLINE_ATTENTION')) . '"></i>
                            </div>';
                } elseif ($date->allowedToParticipate()) {
                    $attentionDeadline = '<div class="alert alert-info" role="alert">' . $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE') . '</div>';
                    $iconParticipationStatus = '';
                }
            }

            // if current user is allowed to participate or user could edit this event then show buttons for participation
            if ($date->possibleToParticipate() || $gCurrentUser->editDates() || $participants->isLeader($gCurrentUserId)) {
                if ($date->getValue('dat_deadline') !== null) {
                    $outputDeadline = $date->getValue('dat_deadline', $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
                }

                // Links for the participation only in html mode
                if ($getViewMode === 'html') {
                    if($date->possibleToParticipate()) {


                        $disableStatusAttend = '';
                        $disableStatusTentative = '';

                        // Check limit of participants
                        if ($date->participantLimitReached()) {
                            // Check current user. If user is member of the event role then get his current approval status and set the options
                            if (array_key_exists($gCurrentUserId, $participantsArray)) {
                                switch ($participantsArray[$gCurrentUserId]['approved']) {
                                    case Participants::PARTICIPATION_MAYBE:
                                        $disableStatusTentative = 'disabled';
                                        break;
                                    case Participants::PARTICIPATION_YES:
                                        $disableStatusAttend = 'disabled';
                                        break;
                                    case Participants::PARTICIPATION_NO:
                                        $disableStatusAttend = 'disabled';
                                        $disableStatusTentative = 'disabled';
                                        break;
                                }
                            }
                        }

                        if ($participateModalForm === false) {
                            $outputButtonParticipation = '
                            <div class="btn-group" role="group">
                                <button class="btn btn-secondary dropdown-toggle ' . $buttonClass . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . $iconParticipationStatus . $buttonText . '</button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="btn admidio-event-approval-state-attend ' . $disableStatusAttend . '" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates_function.php', array('mode' => '3', 'dat_uuid' => $dateUuid)) . '">
                                            <i class="fas fa-check-circle" data-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i>' . $gL10n->get('SYS_PARTICIPATE') . '
                                        </a>
                                    </li>';
                            if ($gSettingsManager->getBool('dates_may_take_part')) {
                                $outputButtonParticipation .= '<li>
                                            <a class="btn admidio-event-approval-state-tentative ' . $disableStatusTentative . '" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates_function.php', array('mode' => '7', 'dat_uuid' => $dateUuid)) . '">
                                                <i class="fas fa-question-circle" data-toggle="tooltip" title="' . $gL10n->get('DAT_USER_TENTATIVE') . '"></i>' . $gL10n->get('DAT_USER_TENTATIVE') . '
                                            </a>
                                        </li>';
                            }
                            $outputButtonParticipation .= '<li>
                                        <a class="btn admidio-event-approval-state-cancel" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates_function.php', array('mode' => '4', 'dat_uuid' => $dateUuid)) . '">
                                            <i class="fas fa-times-circle" data-toggle="tooltip" title="' . $gL10n->get('DAT_CANCEL') . '"></i>' . $gL10n->get('DAT_CANCEL') . '
                                        </a>
                                    </li>
                                </ul>
                            </div>';
                        } else {
                            $outputButtonParticipation = '
                            <div class="btn-group" role="group">
                                <button class="btn btn-secondary openPopup ' . $buttonClass . '" href="javascript:void(0);"
                                    data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/popup_participation.php', array('dat_uuid' => $dateUuid)) . '">' . $iconParticipationStatus . $buttonText . '
                            </div>';
                        }
                    }

                    // Link to participants list
                    if ($gCurrentUser->hasRightViewRole($dateRolId)) {
                        if ($outputNumberMembers > 0 || $outputNumberLeaders > 0) {
                            $buttonURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('mode' => 'html', 'rol_ids' => $dateRolId));

                            if ($getView === 'detail') {
                                $outputButtonParticipants = '
                                    <button class="btn btn-secondary" onclick="window.location.href=\'' . $buttonURL . '\'">
                                        <i class="fas fa-list"></i>' . $gL10n->get('DAT_SHOW_PARTICIPANTS') . '</button>';
                            } else {
                                $outputButtonParticipants = '
                                    <a class="admidio-icon-link" href="' . $buttonURL . '">
                                        <i class="fas fa-list" data-toggle="tooltip" title="' . $gL10n->get('DAT_SHOW_PARTICIPANTS') . '"></i></a>';
                            }
                        }
                    }

                    // Link to send email to participants
                    if ($gCurrentUser->hasRightSendMailToRole($dateRolId)) {
                        if ($outputNumberMembers > 0 || $outputNumberLeaders > 0) {
                            $buttonURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('role_uuid' => $date->getValue('rol_uuid')));

                            if ($getView === 'detail') {
                                $outputButtonParticipantsEmail = '
                                    <button class="btn btn-secondary" onclick="window.location.href=\'' . $buttonURL . '\'">
                                        <i class="fas fa-envelope"></i>' . $gL10n->get('SYS_WRITE_EMAIL') . '</button>';
                            } else {
                                $outputButtonParticipantsEmail = '
                                    <a class="admidio-icon-link" href="' . $buttonURL . '">
                                        <i class="fas fa-envelope" data-toggle="tooltip" title="' . $gL10n->get('SYS_WRITE_EMAIL') . '"></i></a>';
                            }
                        }
                    }

                    // Link for managing new participants
                    if ($participants->isLeader($gCurrentUserId)) {
                        $buttonURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $date->getValue('rol_uuid')));

                        if ($getView === 'detail') {
                            $outputButtonParticipantsAssign = '
                                <button class="btn btn-secondary" onclick="window.location.href=\'' . $buttonURL . '\'">
                                    <i class="fas fa-user-plus"></i>' . $gL10n->get('DAT_ASSIGN_PARTICIPANTS') . '</button>';
                        } else {
                            $outputButtonParticipantsAssign = '
                                <a class="admidio-icon-link" href="' . $buttonURL . '">
                                    <i class="fas fa-user-plus" data-toggle="tooltip" title="' . $gL10n->get('DAT_ASSIGN_PARTICIPANTS') . '"></i></a>';
                        }
                    }
                }
            }
        }

        if ($getView === 'detail') {
            if (!$date->getValue('dat_all_day')) {
                // Write start in array
                $dateElements[] = array($gL10n->get('SYS_START'), '<strong>' . $date->getValue('dat_begin', $gSettingsManager->getString('system_time')) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
                // Write end in array
                $dateElements[] = array($gL10n->get('SYS_END'), '<strong>' . $date->getValue('dat_end', $gSettingsManager->getString('system_time')) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
            }

            $dateElements[] = array($gL10n->get('DAT_CALENDAR'), '<strong>' . $date->getValue('cat_name') . '</strong>');
            if ($outputLinkLocation !== '') {
                $dateElements[] = array($gL10n->get('DAT_LOCATION'), $outputLinkLocation);
            }
            if ($outputLinkRoom !== '') {
                $dateElements[] = array($gL10n->get('SYS_ROOM'), $outputLinkRoom);
            }
            if ($outputDeadline !== '') {
                $dateElements[] = array($gL10n->get('DAT_DEADLINE'), '<strong>'.$outputDeadline.'</strong>');
            }

            if ($outputNumberLeaders !== '') {
                $dateElements[] = array($gL10n->get('SYS_LEADERS'), '<strong>' . $outputNumberLeaders . '</strong>');
            }
            if ($outputNumberMembers !== '') {
                $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), '<strong>' . $outputNumberMembers . '</strong>');
            }

            // show panel view of events

            // Output of elements
            // always 2 then line break
            $firstElement = true;
            $htmlDateElements = '';

            foreach ($dateElements as $element) {
                if ($element[1] !== '') {
                    if ($firstElement) {
                        $htmlDateElements .= '<div class="row">';
                    }

                    $htmlDateElements .= '<div class="col-sm-2 col-4">' . $element[0] . '</div>
                        <div class="col-sm-4 col-8">' . $element[1] . '</div>';

                    if ($firstElement) {
                        $firstElement = false;
                    } else {
                        $htmlDateElements .= '</div>';
                        $firstElement = true;
                    }
                }
            }

            if (!$firstElement) {
                $htmlDateElements .= '</div>';
            }

            $page->addHtml('
                <div class="card admidio-blog ' . ($row['dat_highlight'] ? 'admidio-event-highlight' : '') . '" id="dat_' . $dateUuid . '">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt"></i>' .
                        $date->getValue('dat_begin', $gSettingsManager->getString('system_date')) . $outputEndDate . ' ' . $dateHeadline);

            if ($date->isEditable() || $gSettingsManager->getBool('enable_dates_ical')) {
                $page->addHtml('
                            <div class="dropdown float-right">
                                <a class="" href="#" role="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-chevron-circle-down" data-toggle="tooltip"></i></a>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">');
                // iCal Download
                if ($gSettingsManager->getBool('enable_dates_ical')) {
                    $page->addHtml('
                                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('dat_uuid' => $dateUuid, 'mode' => 6)).'">
                                                <i class="fas fa-file-export" data-toggle="tooltip"></i> '.$gL10n->get('DAT_EXPORT_ICAL').'</a>');
                }

                // change and delete is only for users with additional rights
                if ($date->isEditable()) {
                    $page->addHtml('
                                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('dat_uuid' => $dateUuid, 'copy' => 1, 'headline' => $getHeadline)) . '">
                                                <i class="fas fa-clone" data-toggle="tooltip"></i> '.$gL10n->get('SYS_COPY').'</a>
                                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_new.php', array('dat_uuid' => $dateUuid, 'headline' => $getHeadline)) . '">
                                                <i class="fas fa-edit" data-toggle="tooltip"></i> '.$gL10n->get('SYS_EDIT').'</a>
                                            <a class="dropdown-item btn openPopup" href="javascript:void(0);"
                                                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'dat', 'element_id' => 'dat_' . $dateUuid,
                                                'name' => $date->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ' . $dateHeadline, 'database_id' => $dateUuid)) . '">
                                                <i class="fas fa-trash-alt" data-toggle="tooltip"></i> '.$gL10n->get('SYS_DELETE').'</a>');
                }
                $page->addHtml('</div>
                            </div>');
            }
            $page->addHtml('</div>

                    <div class="card-body">
                        ' . $htmlDateElements . '<br />
                        <p>' . $date->getValue('dat_description') . '</p>' .$attentionDeadline);

            if ($outputButtonParticipation !== '' || $outputButtonParticipants !== ''
            || $outputButtonParticipantsEmail !== '' || $outputButtonParticipantsAssign !== '') {
                $page->addHtml('<div class="btn-group">' . $outputButtonParticipation . $outputButtonParticipants . $outputButtonParticipantsEmail . $outputButtonParticipantsAssign . '</div>');
            }
            $page->addHtml('
                </div>
                <div class="card-footer">'.
                    // show information about user who created the recordset and changed it
                    admFuncShowCreateChangeInfoByName(
                        $row['create_name'],
                        $date->getValue('dat_timestamp_create'),
                        $row['change_name'],
                        $date->getValue('dat_timestamp_change'),
                        $row['create_uuid'],
                        $row['change_uuid']
                    ).'
                    </div>
                </div>');
        } else { // $getView = 'compact' or 'room' or 'participants' or 'description'
            // show table view of events

            // Change css class if date is highlighted
            $cssClass = '';
            if ($row['dat_highlight']) {
                $cssClass = 'admidio-event-highlight';
            }

            $dateBegin = $date->getValue('dat_begin', $gSettingsManager->getString('system_date'));
            $timeBegin = $date->getValue('dat_begin', $gSettingsManager->getString('system_time'));
            $dateEnd = $date->getValue('dat_end', $gSettingsManager->getString('system_date'));
            $timeEnd = $date->getValue('dat_end', $gSettingsManager->getString('system_time'));

            $columnValues = array();

            if ($outputButtonParticipation !== '') {
                $columnValues[] = $outputButtonParticipation;
            } else {
                $columnValues[] = '';
            }

            $columnValues[] = $date->getDateTimePeriod();

            if ($getViewMode === 'html') {
                if ($outputDeadline !== '') {
                    $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('dat_uuid' => $dateUuid, 'view_mode' => 'html', 'view' => 'detail', 'headline' => $dateHeadline)) . '">' . $dateHeadline . '<br />' . $gL10n->get('DAT_DEADLINE') . ': ' . $outputDeadline . '</a>';
                } else {
                    $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php', array('dat_uuid' => $dateUuid, 'view_mode' => 'html', 'view' => 'detail', 'headline' => $dateHeadline)) . '">' . $dateHeadline . '</a>';
                }
            } else {
                $columnValues[] = $dateHeadline;
            }

            if ($getView === 'room') {
                $columnValues[] = $outputLinkRoom;
                $columnValues[] = $outputNumberLeaders;
            }

            switch ($getView) {
                case 'compact': // fallthrough
                case 'room':
                    if ($dateRolId > 0) {
                        if ($date->getValue('dat_max_members') > 0) {
                            $htmlParticipants = $outputNumberMembers . ' / ' . $date->getValue('dat_max_members');
                        } else {
                            $htmlParticipants = $outputNumberMembers . '&nbsp;';
                        }

                        if ($outputNumberMembers > 0) {
                            $htmlParticipants .= $outputButtonParticipants . $outputButtonParticipantsEmail;
                        }

                        $columnValues[] = $htmlParticipants;
                    } else {
                        $columnValues[] = '';
                    }
                    break;
                case 'participants':
                    $columnValue = array();

                    if (is_array($participantsArray)) {
                        // Only show participants if user has right to view the list, is leader or has permission to create/edit events
                        if ($gCurrentUser->hasRightViewRole((int) $date->getValue('dat_rol_id'))
                            || $row['mem_leader'] == 1
                            || $gCurrentUser->editDates()) {
                            foreach ($participantsArray as $participant) {
                                if($participant['approved'] === Participants::PARTICIPATION_YES) {
                                    $columnValue[] = $participant['firstname'] . ' ' . $participant['surname'];
                                } elseif($participant['approved'] === Participants::PARTICIPATION_MAYBE) {
                                    $columnValue[] = $participant['firstname'] . ' ' . $participant['surname'] . ' (' . $gL10n->get('DAT_USER_TENTATIVE') . ')';
                                }
                            }
                        }
                    }
                    $columnValues[] = implode(', ', $columnValue);
                    break;
                case 'description':
                    $columnValues[] = $date->getValue('dat_description');
                    break;
            }

            if ($getView === 'compact') {
                if ($outputLinkLocation !== '') {
                    $columnValues[] = $outputLinkLocation;
                } else {
                    $columnValues[] = '';
                }
            }

            if ($getViewMode === 'html') {
                $columnValues[] = $outputButtonICal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete;
            }

            $compactTable->addRowByArray($columnValues, 'dat_' . $date->getValue('dat_uuid'), array('class' => $cssClass));
        }
    }  // End foreach

    // Output table bottom for compact view
    if ($getView !== 'detail') { // $getView = 'compact' or 'room' or 'participants' or 'description'
        $page->addHtml($compactTable->show());
    }
}

if ($getView === 'detail') {
    // If necessary show links to navigate to next and previous recordset of the query
    $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php', array('view' => $getView, 'mode' => $getMode, 'headline' => $getHeadline, 'cat_uuid' => $getCatUuid, 'date_from' => $dates->getParameter('dateStartFormatEnglish'), 'date_to' => $dates->getParameter('dateEndFormatEnglish'), 'view_mode' => $getViewMode));
    $page->addHtml(admFuncGeneratePagination($baseUrl, $datesResult['totalCount'], $datesResult['limit'], $getStart));
}
$page->show();
