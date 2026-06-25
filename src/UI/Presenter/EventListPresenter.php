<?php

namespace Admidio\UI\Presenter;

use Admidio\Categories\Entity\Category;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Events\ValueObject\Participants;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\DataTables;
use ModuleEvents;

/**
 * Presenter for event list, detail and print views.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventListPresenter extends PagePresenter
{
    /**
     * Create detailed event cards.
     * @throws Exception
     */
    public function createCards(string $dateMode, int $start, string $categoryUUID, string $eventUUID, string $show, string $dateFrom, string $dateTo): void
    {
        $this->createView('cards', $dateMode, $start, $categoryUUID, $eventUUID, $show, $dateFrom, $dateTo, 'html', 'detail');
    }

    /**
     * Create compact event table view.
     * @throws Exception
     */
    public function createCompactList(string $dateMode, int $start, string $categoryUUID, string $eventUUID, string $show, string $dateFrom, string $dateTo, string $view = 'compact'): void
    {
        $this->createView($this->getModeForView($view), $dateMode, $start, $categoryUUID, $eventUUID, $show, $dateFrom, $dateTo, 'html', $view);
    }

    /**
     * Create print output for the selected event view.
     * @throws Exception
     */
    public function createPrintView(string $dateMode, int $start, string $categoryUUID, string $eventUUID, string $show, string $dateFrom, string $dateTo, string $view): void
    {
        $this->createView($this->getPrintModeForView($view), $dateMode, $start, $categoryUUID, $eventUUID, $show, $dateFrom, $dateTo, 'print', $view);
    }

    /**
     * Create the configured event list/detail view.
     * @throws Exception
     */
    public function createView(string $getMode, string $dateMode, int $getStart, string $getCatUuid, string $getEventUuid, string $getShow, string $getDateFrom, string $getDateTo, string $outputMode, string $getView): void
    {
        global $gDb, $gL10n, $gSettingsManager, $gNavigation, $gCurrentOrganization, $gCurrentUser, $gCurrentSession, $gValidLogin, $gCurrentUserId;

        // create object and get recordset of available events

        $calendar = new Category($gDb);

        if ($getCatUuid !== '') {
            $calendar->readDataByUuid($getCatUuid);
        }

        $events = new ModuleEvents();
        $events->setParameter('mode', $dateMode);
        $events->setParameter('cat_id', $calendar->getValue('cat_id'));
        $events->setParameter('dat_uuid', $getEventUuid);
        $events->setParameter('show', $getShow);
        $events->setParameter('view_mode', $outputMode);
        $events->setDateRange($getDateFrom, $getDateTo);

        // Number of events each page for default view 'html' or 'compact' view
        if ($outputMode === 'html' && $getView === 'detail') {
            $eventsPerPage = $gSettingsManager->getInt('events_per_page');
        } else {
            $eventsPerPage = $events->getDataSetCount();
        }

        // read relevant events from database
        $eventsResult = $events->getDataSet($getStart, $eventsPerPage);

        if ($outputMode === 'html') {
            if ($getEventUuid !== '') {
                $gNavigation->addUrl(CURRENT_URL, $events->getHeadline($gL10n->get('SYS_EVENTS')));
            } else {
                // Navigation of the module starts here
                $gNavigation->addStartUrl(CURRENT_URL, $events->getHeadline($gL10n->get('SYS_EVENTS')), 'bi-calendar-week-fill');
            }
        }

        // create an HTML page object
        $page = $this;
        $page->setHtmlID('admidio-events');
        $page->setHeadline($events->getHeadline($gL10n->get('SYS_EVENTS')));

        // data array
        $data = array('headers' => array(), 'rows' => array(), 'column_align' => array(), 'column_width' => array());

        if ($outputMode === 'html') {
            $page->assignSmartyVariable('classTable', 'table table-condensed table-hover');

            if ($gSettingsManager->getBool('enable_rss') && (int)$gSettingsManager->get('events_module_enabled') === 1) {
                $page->addRssFile(ADMIDIO_URL . '/rss/events.php?organization_short_name=' . $gCurrentOrganization->getValue('org_shortname'), $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $gL10n->get('SYS_EVENTS'))));
            }

            $page->addJavascript('
                $(".clamp-text").each(function(){
                    var clampHeight = this.offsetHeight;
                    var fullHeight  = this.scrollHeight;

                    if (fullHeight < clampHeight + 1) {
                        $(this).next(".clamp-button").hide();
                    } else {
                        $(this).next(".clamp-button").show();
                    }
                });

                $("#mode").change(function() {
                    self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('date_mode' => $dateMode, 'date_from' => $events->getParameter('dateStartFormatAdmidio'), 'date_to' => $events->getParameter('dateEndFormatAdmidio'), 'cat_uuid' => $getCatUuid)) . '&mode=" + $("#mode").val();
                });

                $("#menu_item_event_print_view").click(function() {
                    window.open("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => $this->getPrintModeForView($getView), 'date_mode' => $dateMode, 'cat_uuid' => $getCatUuid, 'dat_uuid' => $getEventUuid, 'date_from' => $events->getParameter('dateStartFormatEnglish'), 'date_to' => $events->getParameter('dateEndFormatEnglish'))) . '", "_blank");
                });

                $(".admidio-event-approval a").click(function() {
                    $approvalStateElement = $(this);
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/events.php?mode=" + $(this).data("mode") + "&dat_uuid=" + $(this).data("id"),
                        {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"},
                        function(data) {
                            var returnData = JSON.parse(data);
                            var groupID = "btn_group_" + $approvalStateElement.data("id");
                            if (returnData.status === "success") {
                                ' . ($getView === 'detail' ? '$("#" + groupID + " .dropdown-toggle").html($approvalStateElement.html());' : '$("#" + groupID + " .dropdown-toggle").html($approvalStateElement.children("i").clone());') . '
                                messageBox(returnData.message);
                            } else {
                                messageBox(returnData.message, "' . $gL10n->get('SYS_ERROR') . '", "error");
                            }
                        }
                    );
                    $(".admidio-event-approval").show();
                });
            ', true);

            // If the default view mode is set to compact, we need a back navigation if one date is selected for detail view
            if ($getEventUuid !== '') {
                // add backlink to module menu
                $page->addPageFunctionsMenuItem('menu_item_event_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'bi-printer-fill');
            }

            // Add new event
            if (count($gCurrentUser->getAllEditableCategories('EVT')) > 0 && $getEventUuid === '') {
                $page->addPageFunctionsMenuItem('menu_item_event_add', $gL10n->get('SYS_CREATE_EVENT'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'new')), 'bi-plus-circle-fill');
            }

            ChangelogService::displayHistoryButton($page, 'events', 'events');

            if ($getEventUuid === '') {
                // show print button
                $page->addPageFunctionsMenuItem('menu_item_event_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'bi-printer-fill');

                // iCal Download
                if ($gSettingsManager->getBool('events_ical_export_enabled')) {
                    $param = array('mode' => 'export');
                    ($getCatUuid !== '' ? $param['cat_uuid'] = $getCatUuid : '');
                    ($getDateFrom !== '' ? $param['date_from'] = $getDateFrom : '');
                    ($getDateTo !== '' ? $param['date_to'] = $getDateTo : '');

                    $page->addPageFunctionsMenuItem('menu_item_event_ical', $gL10n->get('SYS_DOWNLOAD_ICAL'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', $param), 'bi-download');
                }

                if ($gCurrentUser->isAdministratorEvents()) {
                    // if no calendar select box is shown, then show a link to edit calendars
                    $page->addPageFunctionsMenuItem('menu_item_event_categories', $gL10n->get('SYS_EDIT_CALENDARS'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'EVT')), 'bi-hdd-stack-fill');
                }

                // create a filter menu with elements for calendar and start/end date
                $form = new FormPresenter('adm_navbar_filter_form', 'modules/events.filter.tpl', ADMIDIO_URL . FOLDER_MODULES . '/events.php', $page, array('type' => 'navbar', 'setFocus' => false));
                if ($gSettingsManager->getBool('events_rooms_enabled')) {
                    $selectBoxEntries = array('cards' => $gL10n->get('SYS_DETAILED'), 'list_compact' => $gL10n->get('SYS_COMPACT'), 'list_room' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_ROOM'), 'list_participants' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_PARTICIPANTS'), 'list_description' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_DESCRIPTION'));
                } else {
                    $selectBoxEntries = array('cards' => $gL10n->get('SYS_DETAILED'), 'list_compact' => $gL10n->get('SYS_COMPACT'), 'list_participants' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_PARTICIPANTS'), 'list_description' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_DESCRIPTION'));
                }
                $form->addSelectBox('mode', $gL10n->get('SYS_VIEW'), $selectBoxEntries, array('defaultValue' => $getMode, 'showContextDependentFirstEntry' => false));
                $form->addSelectBoxForCategories('cat_uuid', $gL10n->get('SYS_CALENDAR'), $gDb, 'EVT', FormPresenter::SELECT_BOX_MODUS_FILTER, array('defaultValue' => $getCatUuid));
                $form->addInput('date_from', $gL10n->get('SYS_START'), $events->getParameter('dateStartFormatEnglish'), array('type' => 'date', 'maxLength' => 10));
                $form->addInput('date_to', $gL10n->get('SYS_END'), $events->getParameter('dateEndFormatEnglish'), array('type' => 'date', 'maxLength' => 10));
                $form->addInput('date_mode', '', $dateMode, array('property' => FormPresenter::FIELD_HIDDEN));
                $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_OK'));
                $form->addToHtmlPage();
            }
        } else { // $outputMode = 'print'
            $page->assignSmartyVariable('classTable', 'table table-condensed table-striped');

            // create an HTML page object without the custom theme files
            $page->setPrintMode();

            if ($getEventUuid === '') {
                $page->addHtml('<h3>' . $gL10n->get('SYS_PERIOD_FROM_TO', array($events->getParameter('dateStartFormatAdmidio'), $events->getParameter('dateEndFormatAdmidio'))) . '</h3>');
            }
        }

        if ($eventsResult['totalCount'] === 0) {
            // No events found
            if ($getEventUuid !== '') {
                $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRY') . '</p>');
            } else {
                $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
            }
        } else {
            // Output table header for compact view
            if ($getView !== 'detail') { // $getView = 'compact' or 'room' or 'participants' or 'description'
                $page->setContentFullWidth();
                $compactTable = new DataTables($page, 'adm_events_table');
                $compactTable->setRowsPerPage($gSettingsManager->getInt('events_per_page'));

                $columnHeading = array();
                $columnAlign = array();

                switch ($getView) {
                    case 'compact':
                        $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_EVENT'), $gL10n->get('SYS_PARTICIPANTS'), $gL10n->get('SYS_VENUE'), $gL10n->get('SYS_CALENDAR'));
                        $columnAlign = array('center', 'left', 'left', 'left', 'left', 'left');
                        $compactTable->disableColumnsSort(array(6));
                        $compactTable->setColumnsNotHideResponsive(array(6));
                        break;
                    case 'room':
                        $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_EVENT'), $gL10n->get('SYS_ROOM'), $gL10n->get('SYS_LEADERS'), $gL10n->get('SYS_PARTICIPANTS'), $gL10n->get('SYS_CALENDAR'));
                        $columnAlign = array('center', 'left', 'left', 'left', 'left', 'left', 'left');
                        $compactTable->disableColumnsSort(array(7));
                        $compactTable->setColumnsNotHideResponsive(array(7));
                        break;
                    case 'participants':
                        $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_EVENT'), $gL10n->get('SYS_PARTICIPANTS'), $gL10n->get('SYS_CALENDAR'));
                        $columnAlign = array('center', 'left', 'left', 'left', 'left');
                        $compactTable->disableColumnsSort(array(5));
                        $compactTable->setColumnsNotHideResponsive(array(5));
                        $data['column_width'] = array('', '', '', '35%');
                        break;
                    case 'description':
                        $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_EVENT'), $gL10n->get('SYS_DESCRIPTION'), $gL10n->get('SYS_CALENDAR'));
                        $columnAlign = array('center', 'left', 'left', 'left', 'left');
                        $compactTable->disableColumnsSort(array(5));
                        $compactTable->setColumnsNotHideResponsive(array(5));
                        $data['column_width'] = array('', '', '', '35%');
                        break;
                }

                if ($outputMode === 'html') {
                    $columnHeading[] = '&nbsp;';
                    $columnAlign[] = 'right';
                    $data['column_width'][] = '';
                }

                $data['headers'] = $columnHeading;
                $data['column_align'] = $columnAlign;
            }

            // create a fake event object
            $event = new Event($gDb);
            $recurrenceEventUuidsById = array();

            foreach ($eventsResult['recordset'] as $row) {
                if ((int)$row['dat_rer_id'] > 0) {
                    $recurrenceEventUuidsById[(int)$row['dat_rer_id']][] = $row['dat_uuid'];
                }
            }

            foreach ($eventsResult['recordset'] as $row) {
                // write of current event data to an event object
                $event->clear();
                $event->setArray($row);

                $eventUUID = $event->getValue('dat_uuid');
                $eventRolId = $event->getValue('dat_rol_id');
                $eventRoleUUID = $row['rol_uuid'];
                $dateHeadline = $event->getValue('dat_headline');
                $recurrenceBadge = $this->getEventRecurrenceBadge($event);
                $eventDeleteMessage = $gL10n->get('SYS_WANT_DELETE_ENTRY', array($event->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ' . $dateHeadline));
                $eventDeleteLabel = $gL10n->get('SYS_DELETE');
                $eventDeleteUrlParams = array('mode' => 'delete', 'dat_uuid' => $eventUUID);
                $eventEditUrlParams = array('mode' => 'edit', 'dat_uuid' => $eventUUID);
                if ($recurrenceBadge !== '') {
                    $eventDeleteMessage = $gL10n->get('SYS_RECURRENCE_DELETE_THIS_CONFIRM', array($event->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ' . $dateHeadline));
                    $eventDeleteLabel = $gL10n->get('SYS_RECURRENCE_DELETE_THIS');
                    $eventDeleteUrlParams['recurrence_scope'] = 'this';
                    $eventEditUrlParams['recurrence_scope'] = 'this';
                }

                // initialize all output elements
                $attentionDeadline = '';
                $outputEndDate = '';
                $outputButtonICal = '';
                $outputButtonEdit = '';
                $outputButtonDelete = '';
                $outputButtonCopy = '';
                $outputButtonParticipation = '';
                $outputButtonParticipants = '';
                $outputButtonParticipantsEmail = '';
                $outputButtonParticipantsAssign = '';
                $outputLinkLocation = '';
                $outputLinkRoom = '';
                $outputNumberMembers = '';
                $outputNumberLeaders = '';
                $outputDeadline = '';
                $eventElements = array();
                $participantsArray = array();
                $participateModalForm = false;

                // If extended options for participation are allowed, then use a modal form instead the dropdown button
                if ((int)$event->getValue('dat_allow_comments') === 1 || (int)$event->getValue('dat_additional_guests') === 1) {
                    $participateModalForm = true;
                }

                // set the end date of event
                if ($event->getValue('dat_begin', $gSettingsManager->getString('system_date')) !== $event->getValue('dat_end', $gSettingsManager->getString('system_date'))) {
                    $outputEndDate = ' - ' . $event->getValue('dat_end', $gSettingsManager->getString('system_date'));
                }

                if ($outputMode === 'html') {
                    // iCal Download
                    if ($gSettingsManager->getBool('events_ical_export_enabled')) {
                        $outputButtonICal = '
                        <a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('dat_uuid' => $eventUUID, 'mode' => 'export')) . '">
                            <i class="bi bi-download" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DOWNLOAD_ICAL') . '"></i></a>';
                    }

                    // change and delete is only for users with additional rights
                    if ($event->isEditable()) {
                        $outputButtonCopy = '
                        <a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'edit', 'dat_uuid' => $eventUUID, 'copy' => 1)) . '">
                            <i class="bi bi-copy" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_COPY') . '"></i></a>';
                        $outputButtonEdit = '
                        <a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', $eventEditUrlParams) . '">
                            <i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i></a>';
                        $outputButtonDelete = '
                        <a class="admidio-messagebox" href="javascript:void(0);"  data-message="' . $eventDeleteMessage . '" data-buttons="yes-no"
                            data-href="callUrlHideElement(\'evt_' . $eventUUID . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', $eventDeleteUrlParams) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $eventDeleteLabel . '"></i></a>';

                        if ($recurrenceBadge !== '') {
                            $outputButtonEdit = '
                            <a class="openPopup" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'recurrence_scope_form', 'dat_uuid' => $eventUUID, 'recurrence_action' => 'edit')) . '">
                                <i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i></a>';
                            $outputButtonDelete = '
                            <a class="openPopup" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'recurrence_scope_form', 'dat_uuid' => $eventUUID, 'recurrence_action' => 'delete')) . '">
                                <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i></a>';
                        }
                    }
                }

                $eventLocation = (string)$event->getValue('dat_location', 'database');
                if ($eventLocation !== '') {
                    // Show a map link, when at least 2 words available
                    // having more than 3 characters each
                    $countLocationWords = 0;
                    foreach (preg_split('/[,; ]/', $eventLocation) as $value) {
                        if (strlen($value) > 3) {
                            ++$countLocationWords;
                        }
                    }

                    if ($gSettingsManager->getBool('events_show_map_link') && $countLocationWords > 1 && $outputMode === 'html') {
                        $urlParam = $eventLocation;

                        $eventCountry = $event->getValue('dat_country');
                        if ($eventCountry) {
                            // Better results with additional country information
                            $urlParam .= ', ' . $eventCountry;
                        }
                        $locationUrl = SecurityUtils::encodeUrl('https://www.google.com/maps/search/', array('api' => 1, 'query' => $urlParam));

                        $outputLinkLocation = '
                        <a href="' . $locationUrl . '" target="_blank" title="' . $gL10n->get('SYS_SHOW_MAP') . '">' . $eventLocation . '</a>';

                        // if valid login and enough information about address exist - calculate the route
                        if ($gValidLogin && $gCurrentUser->getValue('STREET') !== '' && ($gCurrentUser->getValue('POSTCODE') !== '' || $gCurrentUser->getValue('CITY') !== '')) {
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
                                <i class="bi bi-sign-turn-right-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SHOW_ROUTE') . '"></i>
                            </a>';
                        }
                    } else {
                        $outputLinkLocation = $eventLocation;
                    }
                }

                // if active, then show room information
                $eventRoomId = $event->getValue('dat_room_id');
                if ($eventRoomId > 0) {
                    $room = new Room($gDb, $eventRoomId);

                    if ($outputMode === 'html') {
                        $roomLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/msg_window.php', array('message_id' => 'room_detail', 'message_title' => 'SYS_ROOM_INFORMATION', 'message_var1' => $eventRoomId, 'inline' => 'true'));
                        $outputLinkRoom = '<a class="openPopup" href="javascript:void(0);" data-href="' . $roomLink . '">' . $room->getValue('room_name') . '</a>';
                    } else { // $outputMode = 'print'
                        $outputLinkRoom = $room->getValue('room_name');
                    }
                }

                if ($eventRolId > 0) {
                    $participants = new Participants($gDb, $eventRolId);

                    // check the rights if the user is allowed to view the participants, or he is allowed to participate
                    if ($gCurrentUser->hasRightViewRole((int)$event->getValue('dat_rol_id')) || $row['mem_leader'] == 1 || $gCurrentUser->isAdministratorEvents() || $event->allowedToParticipate()) {
                        $outputNumberMembers = $participants->getCount();
                        $outputNumberLeaders = $participants->getNumLeaders();
                        $participantsArray = $participants->getParticipantsArray();
                    }

                    // If a user is invited to the event, then the approval state is not initialized and has value "null" in the data table
                    if ($row['member_date_role'] > 0 && $row['member_approval_state'] == null) {
                        $row['member_approval_state'] = ModuleEvents::MEMBER_APPROVAL_STATE_INVITED;
                    }

                    // set status of participation
                    switch ($row['member_approval_state']) {
                        case ModuleEvents::MEMBER_APPROVAL_STATE_INVITED:
                            $buttonText = $gL10n->get('SYS_PARTICIPATE_QUESTION');
                            $iconParticipationStatus = '<i class="bi bi-person-plus-fill"></i>';
                            break;
                        case ModuleEvents::MEMBER_APPROVAL_STATE_ATTEND:
                            $buttonText = $gL10n->get('SYS_EVENT_PARTICIPATION_ATTEND');
                            $iconParticipationStatus = '<i class="bi bi-check-circle-fill admidio-event-approval-state-attend"></i>';
                            break;
                        case ModuleEvents::MEMBER_APPROVAL_STATE_TENTATIVE:
                            $buttonText = $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE');
                            $iconParticipationStatus = '<i class="bi bi-question-circle-fill admidio-event-approval-state-tentative"></i>';
                            break;
                        case ModuleEvents::MEMBER_APPROVAL_STATE_REFUSED:
                            $buttonText = $gL10n->get('SYS_EVENT_PARTICIPATION_CANCELED');
                            $iconParticipationStatus = '<i class="bi bi-x-circle-fill admidio-event-approval-state-cancel"></i>';
                            break;
                        default:
                            $buttonText = $gL10n->get('SYS_PARTICIPATE');
                            $iconParticipationStatus = '<i class="bi bi-pencil-square"></i>';
                    }

                    if ($getView !== 'detail') {
                        // Status text only in detail view
                        $buttonText = '';
                    }

                    // show notice that no new participation could be assigned to the event because the deadline exceeded or
                    // the max number of participants is reached.
                    if ($event->deadlineExceeded() || $event->participantLimitReached()) {
                        // Show warning for member of the date role if the deadline is exceeded and now no changes are possible anymore
                        if ($participants->isMemberOfEvent($gCurrentUserId)) {
                            if ($getView !== 'detail') {
                                $outputButtonParticipation = $iconParticipationStatus;
                            }
                            $attentionDeadline = '
                                <div class="alert alert-info" role="alert">
                                    ' . $iconParticipationStatus . ' ' . $buttonText . '
                                    <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                        data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                        title="' . $gL10n->get('SYS_NOTE') . '" data-bs-content="' . SecurityUtils::encodeHTML($gL10n->get('SYS_DEADLINE_ATTENTION')) . '"></i>
                                </div>';
                        } elseif ($event->allowedToParticipate()) {
                            $attentionDeadline = '<div class="alert alert-info" role="alert">' . $gL10n->get('SYS_REGISTRATION_NOT_POSSIBLE') . '</div>';
                            $iconParticipationStatus = '';
                        }
                    }

                    // if the current user is allowed to participate or user could edit this event, then show buttons for participation
                    if ($event->possibleToParticipate() || $gCurrentUser->isAdministratorEvents() || $participants->isLeader($gCurrentUserId)) {
                        if ($event->getValue('dat_deadline') !== null) {
                            $outputDeadline = $event->getValue('dat_deadline', $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
                        }

                        // Links for the participation only in HTML mode
                        if ($outputMode === 'html') {
                            if ($event->possibleToParticipate()) {


                                $disableStatusAttend = '';
                                $disableStatusTentative = '';

                                // Check the limit of participants
                                if ($event->participantLimitReached()) {
                                    // Check current user. If a user is a member of the event role, then get their current approval status and set the options
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
                                <div class="btn-group admidio-event-approval" role="group">
                                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . $iconParticipationStatus . $buttonText . '</button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="icon-link dropdown-item ' . $disableStatusAttend . '" href="javascript:void(0)" data-id="' . $eventUUID . '" data-mode="participate">
                                                <i class="bi bi-check-circle-fill admidio-event-approval-state-attend" data-bs-toggle="tooltip"></i>' . $gL10n->get('SYS_PARTICIPATE') . '
                                            </a>
                                        </li>';
                                    if ($gSettingsManager->getBool('events_may_take_part')) {
                                        $outputButtonParticipation .= '<li>
                                                <a class="icon-link dropdown-item ' . $disableStatusTentative . '" href="javascript:void(0)" data-id="' . $eventUUID . '" data-mode="participate_maybe">
                                                    <i class="bi bi-question-circle-fill admidio-event-approval-state-tentative" data-bs-toggle="tooltip"></i>' . $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE') . '
                                                </a>
                                            </li>';
                                    }
                                    $outputButtonParticipation .= '<li>
                                            <a class="icon-link dropdown-item " href="javascript:void(0)" data-id="' . $eventUUID . '" data-mode="participate_cancel">
                                                <i class="bi bi-x-circle-fill admidio-event-approval-state-cancel" data-bs-toggle="tooltip"></i>' . $gL10n->get('SYS_CANCEL') . '
                                            </a>
                                        </li>
                                    </ul>
                                </div>';
                                } else {
                                    $outputButtonParticipation = '
                                <div class="btn-group" role="group">
                                    <button class="btn btn-primary openPopup"
                                        data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'participation_form', 'dat_uuid' => $eventUUID)) . '">' . $iconParticipationStatus . $buttonText . '
                                </div>';
                                }
                            }

                            // Link to a participant list
                            if ($gCurrentUser->hasRightViewRole($eventRolId)) {
                                if ($outputNumberMembers > 0 || $outputNumberLeaders > 0) {
                                    $buttonURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('mode' => 'html', 'role_list' => $eventRoleUUID));

                                    if ($getView === 'detail') {
                                        $outputButtonParticipants = '
                                        <button class="btn btn-primary" onclick="window.location.href=\'' . $buttonURL . '\'">
                                            <i class="bi bi-list-task"></i>' . $gL10n->get('SYS_SHOW_PARTICIPANTS') . '</button>';
                                    } else {
                                        $outputButtonParticipants = '
                                        <a class="admidio-icon-link" href="' . $buttonURL . '">
                                            <i class="bi bi-list-task" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SHOW_PARTICIPANTS') . '"></i></a>';
                                    }
                                }
                            }

                            // Link to send email to participants
                            if ($gCurrentUser->hasRightSendMailToRole($eventRolId)) {
                                if ($outputNumberMembers > 0 || $outputNumberLeaders > 0) {
                                    $buttonURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('role_uuid' => $event->getValue('rol_uuid')));

                                    if ($getView === 'detail') {
                                        $outputButtonParticipantsEmail = '
                                        <button class="btn btn-primary" onclick="window.location.href=\'' . $buttonURL . '\'">
                                            <i class="bi bi-envelope-fill"></i>' . $gL10n->get('SYS_WRITE_EMAIL') . '</button>';
                                    } else {
                                        $outputButtonParticipantsEmail = '
                                        <a class="admidio-icon-link" href="' . $buttonURL . '">
                                            <i class="bi bi-envelope-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_WRITE_EMAIL') . '"></i></a>';
                                    }
                                }
                            }

                            // Link for managing new participants
                            if ($participants->isLeader($gCurrentUserId)) {
                                $buttonURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $event->getValue('rol_uuid')));

                                if ($getView === 'detail') {
                                    $outputButtonParticipantsAssign = '
                                    <button class="btn btn-primary" onclick="window.location.href=\'' . $buttonURL . '\'">
                                        <i class="bi bi-person-plus-fill"></i>' . $gL10n->get('SYS_ASSIGN_PARTICIPANTS') . '</button>';
                                } else {
                                    $outputButtonParticipantsAssign = '
                                    <a class="admidio-icon-link" href="' . $buttonURL . '">
                                        <i class="bi bi-person-plus-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_ASSIGN_PARTICIPANTS') . '"></i></a>';
                                }
                            }
                        }
                    }
                }

                if ($getView === 'detail') {
                    if (!$event->getValue('dat_all_day')) {
                        // Write start in an array
                        $eventElements[] = array($gL10n->get('SYS_START'), '<strong>' . $event->getValue('dat_begin', $gSettingsManager->getString('system_time')) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
                        // Write the end in an array
                        $eventElements[] = array($gL10n->get('SYS_END'), '<strong>' . $event->getValue('dat_end', $gSettingsManager->getString('system_time')) . '</strong> ' . $gL10n->get('SYS_CLOCK'));
                    }

                    $eventElements[] = array($gL10n->get('SYS_CALENDAR'), '<strong>' . $event->getValue('cat_name') . '</strong>');
                    if ($outputLinkLocation !== '') {
                        $eventElements[] = array($gL10n->get('SYS_VENUE'), $outputLinkLocation);
                    }
                    if ($outputLinkRoom !== '') {
                        $eventElements[] = array($gL10n->get('SYS_ROOM'), $outputLinkRoom);
                    }
                    if ($outputDeadline !== '') {
                        $eventElements[] = array($gL10n->get('SYS_DEADLINE'), '<strong>' . $outputDeadline . '</strong>');
                    }

                    if ($outputNumberLeaders !== '') {
                        $eventElements[] = array($gL10n->get('SYS_LEADERS'), '<strong>' . $outputNumberLeaders . '</strong>');
                    }
                    if ($outputNumberMembers !== '') {
                        $eventElements[] = array($gL10n->get('SYS_PARTICIPANTS'), '<strong>' . $outputNumberMembers . '</strong>');
                    }

                    // show panel view of events

                    // Output of elements
                    // always 2 then line break
                    $firstElement = true;
                    $htmlDateElements = '';

                    foreach ($eventElements as $element) {
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
                    <div class="card admidio-blog ' . ($row['dat_highlight'] ? 'admidio-event-highlight' : '') . '" id="evt_' . $eventUUID . '">
                        <div class="card-header">
                            <i class="bi bi-calendar-week-fill"></i>' . $event->getValue('dat_begin', $gSettingsManager->getString('system_date')) . $outputEndDate . ' ' . $dateHeadline . $recurrenceBadge);

                    if ($event->isEditable() || $gSettingsManager->getBool('events_ical_export_enabled')) {
                        $page->addHtml('
                                <div class="dropdown float-end">
                                    <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">');
                        // iCal Download
                        if ($gSettingsManager->getBool('events_ical_export_enabled')) {
                            $page->addHtml('
                                                <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('dat_uuid' => $eventUUID, 'mode' => 'export')) . '">
                                                        <i class="bi bi-download" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_DOWNLOAD_ICAL') . '</a>
                                                </li>');
                        }

                        // change and delete is only for users with additional rights
                        if ($event->isEditable()) {
                            $eventEditAttributes = 'class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', $eventEditUrlParams) . '"';
                            $eventDeleteLink = '
                                                <li><a class="dropdown-item admidio-messagebox" href="javascript:void(0);"  data-message="' . $eventDeleteMessage . '" data-buttons="yes-no"
                                                    data-href="callUrlHideElement(\'evt_' . $eventUUID . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', $eventDeleteUrlParams) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                                                    <i class="bi bi-trash" data-bs-toggle="tooltip"></i> ' . $eventDeleteLabel . '</a>
                                                </li>';

                            if ($recurrenceBadge !== '') {
                                $eventEditAttributes = 'class="dropdown-item openPopup" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'recurrence_scope_form', 'dat_uuid' => $eventUUID, 'recurrence_action' => 'edit')) . '"';
                                $eventDeleteLink = '
                                                <li><a class="dropdown-item openPopup" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'recurrence_scope_form', 'dat_uuid' => $eventUUID, 'recurrence_action' => 'delete')) . '">
                                                    <i class="bi bi-trash" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_DELETE') . '</a>
                                                </li>';
                            }

                            $page->addHtml('
                                                <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'edit', 'dat_uuid' => $eventUUID, 'copy' => 1)) . '">
                                                    <i class="bi bi-copy" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_COPY') . '</a>
                                                </li>
                                                <li><a ' . $eventEditAttributes . '>
                                                    <i class="bi bi-pencil-square" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_EDIT') . '</a>
                                                </li>' . $eventDeleteLink);
                        }
                        $page->addHtml('</ul>
                                </div>');
                    }

                    $page->addHtml('
                        </div>
                            <div class="card-body">
                                ' . $htmlDateElements . '<br />');

                    if ($event->getValue('dat_description') !== '') {
                        // Show description only if it is not empty
                        if ($gSettingsManager->getInt('events_clamp_text_lines') > 0) {
                            $page->addHtml('
                                <div id="event_description_' . $eventUUID . '" class="clamp-text" style="--admidio-clamp-text-lines: ' . $gSettingsManager->getInt('events_clamp_text_lines') . ';">' . $event->getValue('dat_description') . '</div>
                                <div class="clamp-button">
                                    <a id="event_caret_description_' . $eventUUID . '" onclick="showHideMoreText($(this), [\'' . $gL10n->get('SYS_SHOW_MORE') . '\', \'' . $gL10n->get('SYS_SHOW_LESS') . '\']);" role="button" class="admidio-more-less-button" data-target="event_description_' . $eventUUID . '">
                                        ' . $gL10n->get('SYS_SHOW_MORE') . '
                                    </a>
                                </div>' . $attentionDeadline . '</br>');
                        } else {
                            $page->addHtml('
                                <p>' . $event->getValue('dat_description') . '</p>' . $attentionDeadline);
                        }
                    }

                    if ($outputButtonParticipation !== '' || $outputButtonParticipants !== '' || $outputButtonParticipantsEmail !== '' || $outputButtonParticipantsAssign !== '') {
                        $page->addHtml('<div id="btn_group_' . $eventUUID . '" class="btn-group">' . $outputButtonParticipation . $outputButtonParticipants . $outputButtonParticipantsEmail . $outputButtonParticipantsAssign . '</div>');
                    }
                    $page->addHtml('
                    </div>
                    <div class="card-footer">
                        <div class="admidio-info-created-edited">
                            <span class="admidio-info-created">' . $gL10n->get('SYS_CREATED_BY_AND_AT', array($event->getNameOfCreatingUser(), $event->getValue('dat_timestamp_create'))) . '</span>');

                    if ($event->getNameOfLastEditingUser() !== '') {
                        $page->addHtml('<span class="admidio-info-created">' . $gL10n->get('SYS_LAST_EDITED_BY', array($event->getNameOfLastEditingUser(), $event->getValue('dat_timestamp_change'))) . '</span>');
                    }
                    $page->addHtml('
                            </div>
                        </div>
                    </div>');
                } else { // $getView = 'compact' or 'room' or 'participants' or 'description'
                    // show table view of events

                    // Change CSS class if event is highlighted
                    $cssClass = '';
                    if ($row['dat_highlight']) {
                        $cssClass = 'admidio-event-highlight';
                    }

                    $dateBegin = $event->getValue('dat_begin', $gSettingsManager->getString('system_date'));
                    $timeBegin = $event->getValue('dat_begin', $gSettingsManager->getString('system_time'));
                    $dateEnd = $event->getValue('dat_end', $gSettingsManager->getString('system_date'));
                    $timeEnd = $event->getValue('dat_end', $gSettingsManager->getString('system_time'));

                    $columnValues = array();

                    if ($outputButtonParticipation !== '') {
                        $columnValues[] = $outputButtonParticipation;
                    } else {
                        $columnValues[] = '';
                    }

                    $columnValues[] = $event->getDateTimePeriod();

                    if ($outputMode === 'html') {
                        if ($outputDeadline !== '') {
                            $columnValues[] = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('dat_uuid' => $eventUUID, 'mode' => 'cards', 'headline' => $dateHeadline)) . '">' . $dateHeadline . '</a>' . $recurrenceBadge . '<br />' . $gL10n->get('SYS_DEADLINE') . ': ' . $outputDeadline;
                        } else {
                            $columnValues[] = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('dat_uuid' => $eventUUID, 'mode' => 'cards', 'headline' => $dateHeadline)) . '">' . $dateHeadline . '</a>' . $recurrenceBadge;
                        }
                    } else {
                        $columnValues[] = $dateHeadline . ($recurrenceBadge !== '' ? ' ' . $this->getEventRecurrenceHint($event) : '');
                    }

                    if ($getView === 'room') {
                        $columnValues[] = $outputLinkRoom;
                        $columnValues[] = $outputNumberLeaders;
                    }

                    switch ($getView) {
                        case 'compact': // fallthrough
                        case 'room':
                            if ($eventRolId > 0) {
                                if ($event->getValue('dat_max_members') > 0) {
                                    $htmlParticipants = $outputNumberMembers . ' / ' . $event->getValue('dat_max_members');
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
                                // Only show participants if user has the right to view the list, is leader or has permission to create/edit events
                                if ($gCurrentUser->hasRightViewRole((int)$event->getValue('dat_rol_id')) || $row['mem_leader'] == 1 || $gCurrentUser->isAdministratorEvents()) {
                                    foreach ($participantsArray as $participant) {
                                        if ($participant['approved'] === Participants::PARTICIPATION_YES) {
                                            $columnValue[] = $participant['firstname'] . ' ' . $participant['surname'];
                                        } elseif ($participant['approved'] === Participants::PARTICIPATION_MAYBE) {
                                            $columnValue[] = $participant['firstname'] . ' ' . $participant['surname'] . ' (' . $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE') . ')';
                                        }
                                    }
                                }
                            }
                            $columnValues[] = implode(', ', $columnValue);
                            break;
                        case 'description':
                            if ($gSettingsManager->getInt('events_clamp_text_lines') > 0) {
                                $descContent = '
                                    <div id="event_description_' . $eventUUID . '" class="clamp-text" style="--admidio-clamp-text-lines: ' . $gSettingsManager->getInt('events_clamp_text_lines') . ';">' . $event->getValue('dat_description') . '</div>
                                    <div class="clamp-button">
                                        <a id=event_caret_description_' . $eventUUID . '"" onclick="showHideMoreText($(this), ["' . $gL10n->get('SYS_SHOW_MORE') . '", "' . $gL10n->get('SYS_SHOW_LESS') . '"]);" role="button" class="admidio-more-less-button" data-target="event_description_' . $eventUUID . '">
                                            ' . $gL10n->get('SYS_SHOW_MORE') . '
                                        </a>
                                    </div>';
                            } else {
                                $descContent = $event->getValue('dat_description');
                            }
                            $columnValues[] = $descContent;
                            break;
                    }

                    if ($getView === 'compact') {
                        if ($outputLinkLocation !== '') {
                            $columnValues[] = $outputLinkLocation;
                        } else {
                            $columnValues[] = '';
                        }
                    }

                    $columnValues[] = Language::translateIfTranslationStrId($row['category_name']);

                    if ($outputMode === 'html') {
                        $columnValues[] = $outputButtonICal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete;
                    }

                    $data['rows'][] = array('id' => 'evt_' . $event->getValue('dat_uuid'), 'class' => $cssClass, 'data' => $columnValues);
                }
            }  // End foreach

            // Output table bottom for compact view
            if ($getView !== 'detail') { // $getView = 'compact' or 'room' or 'participants' or 'description'
                $compactTable->setColumnAlignByArray($columnAlign);
                $compactTable->createJavascript(count($data['rows']), count($data['headers']));

                $page->assignSmartyVariable('columnAlign', $data['column_align']);
                $page->assignSmartyVariable('columnWidth', $data['column_width']);
                $page->assignSmartyVariable('headers', $data['headers']);
                $page->assignSmartyVariable('rows', $data['rows']);

                $page->addHtmlByTemplate('modules/events.list.tpl');
            }
        }

        if ($getView === 'detail') {
            // If necessary, show links to navigate to the next and previous recordset of the query
            $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => $getMode, 'date_mode' => $dateMode, 'cat_uuid' => $getCatUuid, 'date_from' => $events->getParameter('dateStartFormatEnglish'), 'date_to' => $events->getParameter('dateEndFormatEnglish')));
            $page->addHtml(admFuncGeneratePagination($baseUrl, $eventsResult['totalCount'], $eventsResult['limit'], $getStart));
        }
    }

    private function getModeForView(string $view): string
    {
        switch ($view) {
            case 'room':
                return 'list_room';

            case 'participants':
                return 'list_participants';

            case 'description':
                return 'list_description';

            default:
                return 'list_compact';
        }
    }

    private function getPrintModeForView(string $view): string
    {
        switch ($view) {
            case 'room':
                return 'print_list_room';

            case 'participants':
                return 'print_list_participants';

            case 'description':
                return 'print_list_description';

            case 'compact':
                return 'print_list_compact';

            default:
                return 'print_cards';
        }
    }

    /**
     * Return a small recurrence hint for recurring events.
     * @throws Exception
     */
    private function getEventRecurrenceHint(Event $event): string
    {
        global $gL10n;

        $recurrenceStatus = (string)$event->getValue('dat_recurrence_status', 'database');

        if ($recurrenceStatus === 'master') {
            return $gL10n->get('SYS_RECURRENCE_REPEATS');
        }

        if ((int)$event->getValue('dat_rer_id') > 0 || in_array($recurrenceStatus, array('generated', 'modified'), true)) {
            return $gL10n->get('SYS_RECURRENCE_PART_OF_SERIES');
        }

        return '';
    }

    /**
     * Return the recurrence hint formatted as a compact badge.
     * @throws Exception
     */
    private function getEventRecurrenceBadge(Event $event): string
    {
        $recurrenceHint = $this->getEventRecurrenceHint($event);

        if ($recurrenceHint === '') {
            return '';
        }

        return ' <span class="badge bg-secondary">' . $recurrenceHint . '</span>';
    }
}
