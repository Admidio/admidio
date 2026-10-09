<?php

namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Events\Entity\Event;
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Events\ValueObject\EventRecurrenceRule;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\Service\InventoryAccessService;
use Admidio\UI\Component\DataTables;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Users\Entity\User;
use DateInterval;
use DateTime;

/**
 * Presenter for event edit and participation forms.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventFormPresenter extends PagePresenter
{
    /**
     * Create a modal dialog to choose whether an event action affects one occurrence or the series.
     * @throws Exception
     */
    public function createRecurrenceScopeForm(string $eventUuid, string $action): string
    {
        global $gCurrentSession, $gDb, $gL10n, $gSettingsManager;

        if ($eventUuid === '' || !in_array($action, ['edit', 'delete'], true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $event = new Event($gDb);
        if (!$event->readDataByUuid($eventUuid)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if (!$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $recurrenceId = (int)$event->getValue('dat_evr_id');
        $recurrenceStatus = (string)$event->getValue('dat_recurrence_status', 'database');
        if ($recurrenceId === 0 && $recurrenceStatus !== 'master') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if ($action === 'edit') {
            $thisOnClick = 'window.location.href=\'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', ['mode' => 'edit', 'dat_uuid' => $eventUuid, 'recurrence_scope' => 'this']) . '\'';
            $seriesOnClick = 'window.location.href=\'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', ['mode' => 'edit', 'dat_uuid' => $eventUuid, 'recurrence_scope' => 'series']) . '\'';
            $header = $gL10n->get('SYS_EDIT');
            $icon = 'bi-pencil-square';
        } else {
            $eventUuids = [$eventUuid];
            if ($recurrenceId > 0) {
                $statement = $gDb->queryPrepared('SELECT dat_uuid
                       FROM ' . TBL_EVENTS . '
                      WHERE dat_evr_id = ?', [$recurrenceId]);
                $eventUuids = [];
                while ($row = $statement->fetch()) {
                    $eventUuids[] = $row['dat_uuid'];
                }
            }

            $thisOnClick = 'callUrlHideElement(\'evt_' . $eventUuid . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', ['mode' => 'delete', 'dat_uuid' => $eventUuid, 'recurrence_scope' => 'this']) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
            $seriesOnClick = 'callUrlHideElements(\'evt_\', [\'' . implode('\',\'', $eventUuids) . '\'], \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', ['mode' => 'delete', 'dat_uuid' => $eventUuid, 'recurrence_scope' => 'series']) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
            $header = $gL10n->get('SYS_DELETE');
            $icon = 'bi-trash';
        }

        return '
            <div class="modal-header">
                <h3 class="modal-title">' . $header . '</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><i class="' . $icon . '"></i>&nbsp;' . $gL10n->get('SYS_RECURRENCE_SCOPE') . '</p>
                <p>' . $event->getValue('dat_begin', $gSettingsManager->getString('system_date')) . ' ' . SecurityUtils::encodeHTML($event->getValue('dat_headline')) . '</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="' . $thisOnClick . '">' . $gL10n->get('SYS_ONLY_THIS_DATE') . '</button>
                <button class="btn btn-primary" onclick="' . $seriesOnClick . '">' . $gL10n->get('SYS_ENTIRE_SERIES') . '</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">' . $gL10n->get('SYS_CANCEL') . '</button>
                <div id="adm_status_message" class="mt-4 w-100"></div>
            </div>';
    }

    /**
     * Create the modal form to change the participation details of an event.
     * @throws Exception
     */
    public function createParticipationForm(string $eventUuid, string $userUuid): string
    {
        global $gCurrentSession, $gCurrentUser, $gDb, $gL10n, $gProfileFields, $gSettingsManager;

        if ($eventUuid === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $disableAdditionalGuests = FormPresenter::FIELD_HIDDEN;
        $disableComments = FormPresenter::FIELD_HIDDEN;

        $event = new Event($gDb);
        if (!$event->readDataByUuid($eventUuid)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if ($gCurrentUser->getValue('usr_uuid') === $userUuid) {
            if (!$event->allowedToParticipate()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        } elseif (!$gCurrentUser->isAdministrator() && !$gCurrentUser->isLeaderOfRole((int)$event->getValue('dat_rol_id'))) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if ((int)$event->getValue('dat_allow_comments') === 1 || (int)$event->getValue('dat_additional_guests') === 1) {
            if ((int)$event->getValue('dat_allow_comments') === 1) {
                $disableComments = FormPresenter::FIELD_DEFAULT;
            }
            if ((int)$event->getValue('dat_additional_guests') === 1) {
                $disableAdditionalGuests = FormPresenter::FIELD_DEFAULT;
            }
        }

        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($userUuid);

        $member = new Membership($gDb);
        $member->readDataByColumns(['mem_rol_id' => (int)$event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')]);

        $participationForm = new FormPresenter('adm_events_participation_edit_form', 'modules/events.participation.edit.tpl', '#');
        $participationForm->addMultilineTextInput('dat_comment', $gL10n->get('SYS_COMMENT'), $member->getValue('mem_comment'), 6, ['class' => 'form-control', 'maxLength' => 1000, 'property' => $disableComments]);
        $participationForm->addInput('additional_guests', $gL10n->get('SYS_SEAT_AMOUNT'), (int)$member->getValue('mem_count_guests'), ['class' => 'form-control', 'type' => 'number', 'property' => $disableAdditionalGuests]);
        $participationForm->addButton('adm_button_attend', $gL10n->get('SYS_PARTICIPATE'), ['icon' => 'bi-check-circle-fill admidio-event-approval-state-attend', 'class' => 'btn-primary']);

        if ($gSettingsManager->getBool('events_may_take_part')) {
            $participationForm->addButton('adm_button_tentative', $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE'), ['icon' => 'bi-question-circle-fill admidio-event-approval-state-tentative', 'class' => 'btn-primary']);
        }

        $participationForm->addButton('adm_button_refuse', $gL10n->get('SYS_CANCEL'), ['icon' => 'bi-x-circle-fill admidio-event-approval-state-cancel', 'class' => 'btn-primary']);

        $smarty = self::createSmartyObject();
        $smarty->assign('urlFormAction', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', ['dat_uuid' => $eventUuid, 'user_uuid' => $userUuid]));
        $smarty->assign('eventHeadline', $event->getValue('dat_headline'));
        $smarty->assign('eventPeriod', $event->getDateTimePeriod());
        $participationForm->addToSmarty($smarty);
        $gCurrentSession->addFormObject($participationForm);

        return $smarty->fetch('modules/events.participation.edit.tpl');
    }

    /**
     * Return the server-side data for the event inventory reservation status table.
     * @throws Exception
     */
    public function getReservationStatusData(
        string $eventUuid,
        string $recurrenceScope,
        int $draw,
        int $start,
        int $length,
        string $search,
        int $orderColumn,
        string $orderDirection
    ): array {
        global $gDb, $gL10n, $gSettingsManager;

        if (!$gSettingsManager->getBool('inventory_reservations_enabled')
            || !$gSettingsManager->getBool('inventory_reservations_events_enabled')
            || !InventoryAccessService::canRequestReservation()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $event = new Event($gDb);
        if (!$event->readDataByUuid($eventUuid) || !$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $eventIds = array((int)$event->getValue('dat_id'));
        if ($recurrenceScope === 'series') {
            $recurrenceRepository = new EventRecurrenceRepository($gDb);
            $recurrence = (int)$event->getValue('dat_evr_id') > 0
                ? $recurrenceRepository->readById((int)$event->getValue('dat_evr_id'))
                : $recurrenceRepository->readByMasterEventId((int)$event->getValue('dat_id'));

            if ($recurrence !== null) {
                $eventIds = array();
                $eventStatement = $gDb->queryPrepared(
                    'SELECT dat_id
                       FROM ' . TBL_EVENTS . '
                      WHERE dat_evr_id = ?
                         OR dat_id = ?
                   ORDER BY dat_begin',
                    array((int)$recurrence->getValue('evr_id'), (int)$recurrence->getValue('evr_dat_id_master'))
                );
                while ($eventRow = $eventStatement->fetch()) {
                    $eventIds[] = (int)$eventRow['dat_id'];
                }
            }
        }

        $eventIds = array_values(array_unique($eventIds));
        $params = $eventIds;
        $whereSearch = '';
        if ($search !== '') {
            $whereSearch = ' AND (dat_headline LIKE ? OR ind_value LIKE ? OR ivr_status LIKE ?)';
            $searchValue = '%' . htmlspecialchars_decode($search, ENT_QUOTES | ENT_HTML5) . '%';
            array_push($params, $searchValue, $searchValue, $searchValue);
        }

        $sqlFrom = ' FROM ' . TBL_INVENTORY_RESERVATIONS . '
             INNER JOIN ' . TBL_EVENTS . ' ON dat_id = ivr_dat_id
             INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ivr_ini_id
             INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
            WHERE ivr_dat_id IN (' . Database::getQmForValues($eventIds) . ')' . $whereSearch;

        $jsonArray = array(
            'draw' => $draw,
            'recordsTotal' => (int)$gDb->queryPrepared(
                'SELECT COUNT(*)' . str_replace($whereSearch, '', $sqlFrom),
                $eventIds
            )->fetchColumn(),
            'recordsFiltered' => (int)$gDb->queryPrepared('SELECT COUNT(*)' . $sqlFrom, $params)->fetchColumn(),
            'data' => array()
        );

        $orderColumns = array('dat_begin', 'ind_value', 'ivr_status');
        $orderBy = $orderColumns[$orderColumn] ?? 'COALESCE(ivr_timestamp_change, ivr_timestamp_create)';
        $orderDirection = $orderDirection === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT ivr_id, ivr_dat_id, ivr_ini_id, ind_value, ivr_status, dat_headline, dat_begin, dat_end
                  ' . $sqlFrom . '
              ORDER BY ' . $orderBy . ' ' . $orderDirection . ', ivr_id DESC';
        if ($length !== -1) {
            $sql .= ' LIMIT ' . max($length, 0) . ' OFFSET ' . max($start, 0);
        }

        $statusLabels = array(
            Reservation::STATUS_REQUESTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REQUESTED'),
            Reservation::STATUS_APPROVED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_APPROVED'),
            Reservation::STATUS_REJECTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REJECTED'),
            Reservation::STATUS_CANCELLED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_CANCELLED'),
            Reservation::STATUS_BORROWED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_BORROWED'),
            Reservation::STATUS_RETURNED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_RETURNED')
        );
        $statusIcons = array(
            Reservation::STATUS_REQUESTED => 'bi-hourglass-split text-secondary',
            Reservation::STATUS_APPROVED => 'bi-check-circle-fill text-success',
            Reservation::STATUS_REJECTED => 'bi-x-circle-fill text-danger',
            Reservation::STATUS_CANCELLED => 'bi-x-circle-fill text-danger',
            Reservation::STATUS_BORROWED => 'bi-box-arrow-up-right text-primary',
            Reservation::STATUS_RETURNED => 'bi-box-arrow-in-down-left text-success'
        );

        $latestReservationIds = array();
        $latestStatement = $gDb->queryPrepared(
            'SELECT ivr_id, ivr_ini_id
               FROM ' . TBL_INVENTORY_RESERVATIONS . '
              WHERE ivr_dat_id = ?
           ORDER BY COALESCE(ivr_timestamp_change, ivr_timestamp_create) DESC, ivr_id DESC',
            array((int)$event->getValue('dat_id'))
        );
        while ($latestReservation = $latestStatement->fetch()) {
            if (!isset($latestReservationIds[(int)$latestReservation['ivr_ini_id']])) {
                $latestReservationIds[(int)$latestReservation['ivr_ini_id']] = (int)$latestReservation['ivr_id'];
            }
        }

        foreach ($gDb->queryPrepared($sql, $params)->fetchAll() as $reservation) {
            $status = $reservation['ivr_status'];
            $eventDate = new Event($gDb, (int)$reservation['ivr_dat_id']);
            $action = '';
            if ((int)$reservation['ivr_dat_id'] === (int)$event->getValue('dat_id')
                && ($latestReservationIds[(int)$reservation['ivr_ini_id']] ?? 0) === (int)$reservation['ivr_id']
                && in_array($status, array(Reservation::STATUS_REJECTED, Reservation::STATUS_CANCELLED), true)) {
                $requestAgainUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array(
                    'mode' => 'reservation_request_again',
                    'dat_uuid' => $eventUuid,
                    'reservation_item_id' => (int)$reservation['ivr_ini_id']
                ));
                $action = '<button type="button" class="btn btn-sm btn-outline-primary event-inventory-reservation-request-again" data-url="'
                    . SecurityUtils::encodeHTML($requestAgainUrl) . '" data-item-id="' . (int)$reservation['ivr_ini_id'] . '">'
                    . SecurityUtils::encodeHTML($gL10n->get('SYS_INVENTORY_RESERVATION_REQUEST_AGAIN')) . '</button>';
            }

            $jsonArray['data'][] = array(
                SecurityUtils::encodeHTML($reservation['dat_headline']) . '<br><small>' . SecurityUtils::encodeHTML($eventDate->getDateTimePeriod()) . '</small>',
                SecurityUtils::encodeHTML($reservation['ind_value']),
                '<span class="event-inventory-reservation-status"><i class="bi ' . ($statusIcons[$status] ?? 'bi-question-circle-fill text-secondary') . ' me-1"></i>'
                    . SecurityUtils::encodeHTML($statusLabels[$status] ?? $status) . '</span>',
                $action
            );
        }

        return $jsonArray;
    }

    /**
     * Create the form to create, edit or copy an event.
     * @throws Exception
     */
    public function createEditForm(string $getEventUuid = '', bool $getCopy = false, string $getRecurrenceScope = 'this'): void
    {
        global $gDb, $gL10n, $gSettingsManager, $gNavigation, $gCurrentUser, $gCurrentOrgId, $gCurrentSession;

        // Initialize local parameters
        $eventParticipationPossible = false;
        $eventCurrentUserAssigned = true;
        $roleViewSet = [];
        $flagDateRightListView = false;
        $flagDateRightSendMail = false;
        $showRecurrenceFields = true;
        $recurrenceFrequency = 'none';
        $recurrenceInterval = 1;
        $recurrenceWeekdays = [];
        $recurrenceEndType = EventRecurrenceRule::END_TYPE_NEVER;
        $recurrenceCount = 10;
        $recurrenceUntil = '';

        // set headline of the script
        if ($getCopy) {
            $headline = $gL10n->get('SYS_COPY_VAR', [$gL10n->get('SYS_EVENT')]);
        } elseif ($getEventUuid !== '') {
            $headline = $gL10n->get('SYS_EDIT_EVENT');
        } else {
            $headline = $gL10n->get('SYS_CREATE_EVENT');
        }

        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create event object
        $event = new Event($gDb);

        if ($getEventUuid !== '') {
            // read data from database
            $event->readDataByUuid($getEventUuid);

            // get assigned roles of this event
            $eventParticipationRolesObject = new RolesRights($gDb, 'event_participation', (int)$event->getValue('dat_id'));
            $roleViewSet = $eventParticipationRolesObject->getRolesIds();

            // check if the current user could edit this event
            if (!$event->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            // check if a participation to this event is possible
            if ((int)$event->getValue('dat_rol_id') > 0) {
                $eventParticipationPossible = true;
                $role = new Role($gDb, (int)$event->getValue('dat_rol_id'));
                $flagDateRightListView = (int)$role->getValue('rol_view_memberships') === Role::VIEW_ROLE_MEMBERS;
                $flagDateRightSendMail = (int)$role->getValue('rol_mail_this_role') === Role::VIEW_ROLE_MEMBERS;
            }

            // check if current user is assigned to this event
            $eventCurrentUserAssigned = $gCurrentUser->isLeaderOfRole((int)$event->getValue('dat_rol_id'));

            $recurrenceRepository = new EventRecurrenceRepository($gDb);
            $recurrence = null;
            if ((int)$event->getValue('dat_evr_id') > 0) {
                $recurrence = $recurrenceRepository->readById((int)$event->getValue('dat_evr_id'));
            }
            if ($recurrence === null) {
                $recurrence = $recurrenceRepository->readByMasterEventId((int)$event->getValue('dat_id'));
            }
            if ($recurrence !== null) {
                $recurrenceRule = $recurrenceRepository->toRule($recurrence);
                $recurrenceFrequency = $recurrenceRule->getFrequency();
                $recurrenceInterval = $recurrenceRule->getInterval();
                $recurrenceWeekdays = $recurrenceRule->getByDay();
                $recurrenceEndType = $recurrenceRule->getEndType();
                $recurrenceCount = $recurrenceRule->getCount() ?? $recurrenceCount;
                $recurrenceUntil = $recurrenceRule->getUntil()?->format('Y-m-d') ?? '';
            }

            $showRecurrenceFields = $recurrence === null || $getRecurrenceScope === 'series';
        } else {
            // check if the user has the right to edit at least one category
            if (count($gCurrentUser->getAllEditableCategories('EVT')) === 0) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            // For new events preset event with current event
            $now = new DateTime();
            $oneHourOffset = new DateInterval('PT1H');
            $twoHourOffset = new DateInterval('PT2H');
            $beginDate = $now->add($oneHourOffset)->format('Y-m-d H:00:00');
            $endDate = $now->add($twoHourOffset)->format('Y-m-d H:00:00');
            $event->setValue('dat_begin', $beginDate);
            $event->setValue('dat_end', $endDate);
        }

        if ($recurrenceUntil === '') {
            $recurrenceUntil = (new DateTime($event->getValue('dat_begin')))->format('Y-m-d');
        }
        if (count($recurrenceWeekdays) === 0) {
            $weekdays = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];
            $recurrenceWeekdays = [$weekdays[(int)(new DateTime($event->getValue('dat_begin')))->format('N')]];
        }

        // create html page object
        $page = $this;
        $page->setHtmlID('admidio-events-edit');
        $page->setHeadline($headline);
        $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/bootstrap-tabs-x/css/bootstrap-tabs-x-admidio.css');

        $page->addJavascript('
        /**
         * Function hides/show date and time fields
         */
        function setAllDay() {
            if ($("#dat_all_day:checked").val() !== undefined) {
                if ($("#event_from_time").val() == undefined) {
                    $("#event_from_time").val("00:00");
                }
                $("#event_from_time").hide();
                if ($("#event_to_time").val() == undefined) {
                    $("#event_to_time").val("00:00");
                }
                $("#event_to_time").hide();
            } else {
                $("#event_from_time").show("slow");
                $("#event_to_time").show("slow");
            }
        }

        function getEventParticipationFields() {
            return $("#adm_event_participation_right_group, #event_current_user_assigned_group, " +
                "#dat_max_members_group, #event_right_list_view_group, #event_right_send_mail_group, " +
                "#dat_allow_comments_group, #dat_additional_guests_group, #event_deadline_group")
                .find("input, select");
        }

        function setEventParticipation() {
            var participationEnabled = $("#event_participation_possible").is(":checked");
            $("#adm_event_participation_right_group").toggleClass("admidio-form-group-required", participationEnabled);
            getEventParticipationFields().prop("disabled", !participationEnabled);
        }

        function setLocationCountry() {
            if ($("#dat_location").val().length > 0) {
                $("#dat_country_group").show();
                $("#dat_country").focus();
            } else {
                $("#dat_country_group").hide();
            }
        }

        function setRecurrenceFieldRequired(fieldId, isRequired, useNativeRequired) {
            $("#" + fieldId + "_group").toggleClass("admidio-form-group-required", isRequired);
            $("#" + fieldId).prop("required", isRequired && useNativeRequired);
            if (!isRequired || !useNativeRequired) {
                $("#" + fieldId).removeAttr("required");
            }
        }

        function setEventRecurrence() {
            if ($("#event_recurrence_frequency").length === 0) {
                return;
            }

            var recurrenceFrequency = $("#event_recurrence_frequency").val();
            var recurrenceEndType = $("#event_recurrence_end_type").val();
            var recurrenceSelected = recurrenceFrequency !== "none";

            $("#event_recurrence_interval, #event_recurrence_end_type").prop("disabled", !recurrenceSelected);
            $("#event_recurrence_interval_group, #event_recurrence_end_type_group").toggle(recurrenceSelected);

            setRecurrenceFieldRequired("event_recurrence_interval", recurrenceSelected, true);
            setRecurrenceFieldRequired("event_recurrence_end_type", recurrenceSelected, true);
            setRecurrenceFieldRequired("event_recurrence_count", recurrenceSelected && recurrenceEndType === "count", true);
            setRecurrenceFieldRequired("event_recurrence_until", recurrenceSelected && recurrenceEndType === "until", true);
            // Select2 hides the original multiselect. The visual required marker is enough here;
            // the actual weekly weekday requirement is validated on the server.
            setRecurrenceFieldRequired("event_recurrence_weekdays", recurrenceSelected && recurrenceFrequency === "weekly", false);

            if (recurrenceFrequency === "none") {
                $("#event_recurrence_weekdays_group").hide();
                $("#event_recurrence_count_group").hide();
                $("#event_recurrence_until_group").hide();
            } else {
                if (recurrenceFrequency === "weekly") {
                    $("#event_recurrence_weekdays_group").show("slow");
                } else {
                    $("#event_recurrence_weekdays_group").hide();
                }

                if (recurrenceEndType === "count") {
                    $("#event_recurrence_count_group").show("slow");
                    $("#event_recurrence_until_group").hide();
                } else if (recurrenceEndType === "until") {
                    $("#event_recurrence_count_group").hide();
                    $("#event_recurrence_until_group").show("slow");
                } else {
                    $("#event_recurrence_count_group").hide();
                    $("#event_recurrence_until_group").hide();
                }
            }
        }
        ');

        $page->addJavascript('
        var eventParticipationPossible = ' . ($eventParticipationPossible ? 1 : 0) . ';

        setAllDay();
        setEventParticipation();
        setLocationCountry();
        setEventRecurrence();

        // Disabled controls are omitted by FormData. Keep their settings in the save request.
        document.getElementById("adm_events_edit_form").addEventListener("formdata", function(event) {
            getEventParticipationFields().filter(":disabled").each(function() {
                if (!this.name || (this.type === "checkbox" && !this.checked)) {
                    return;
                }
                var values = $(this).val();
                if (values !== null) {
                    (Array.isArray(values) ? values : [values]).forEach(function(value) {
                        event.formData.append(this.name, value);
                    }, this);
                }
            });
        });

        // Reveal invalid fields before the browser tries to focus them.
        document.getElementById("adm_events_edit_form").addEventListener("invalid", function(event) {
            var pane = event.target.closest(".tab-pane");
            if (!pane || this.querySelector(":invalid") !== event.target) {
                return;
            }
            if (window.matchMedia("(min-width: 768px)").matches) {
                bootstrap.Tab.getOrCreateInstance(document.getElementById(pane.getAttribute("aria-labelledby"))).show();
            } else {
                var collapse = pane.querySelector(".accordion-collapse");
                collapse.addEventListener("shown.bs.collapse", function() {
                    event.target.focus();
                }, {once: true});
                bootstrap.Collapse.getOrCreateInstance(collapse, {toggle: false}).show();
            }
        }, true);

        $("#event_participation_possible").click(function() {
            setEventParticipation();
        });
        $("#dat_all_day").click(function() {
            setAllDay();
        });
        $("#dat_location").change(function() {
            setLocationCountry();
        });
        $("#event_recurrence_frequency").change(function() {
            setEventRecurrence();
        });
        $("#event_recurrence_end_type").change(function() {
            setEventRecurrence();
        });
        $("#event_from").change(function() {
            if ($("#event_from").val() > $("#event_to").val()) {
                $("#event_to").val($("#event_from").val());
            }
            if ($("#event_recurrence_until").length > 0 && $("#event_recurrence_until").val() < $("#event_from").val()) {
                $("#event_recurrence_until").val($("#event_from").val());
            }
        });

        // if event participation should be removed than ask user
        $("#event_participation_possible").change(function(event) {
            if (eventParticipationPossible === 1 && $("#event_participation_possible").is(":checked") === false) {
                var msg_result = confirm("' . $gL10n->get('SYS_REMOVE_EVENT_REGISTRATION') . '");
                if (!msg_result) {
                    $(this).prop("checked", true);
                    setEventParticipation();
                }
            }
        });', true);

        ChangelogService::displayHistoryButton($page, 'events', 'events', !empty($getEventUuid), ['uuid' => $getEventUuid]);

        // show form
        $form = new FormPresenter('adm_events_edit_form', 'modules/events.edit.tpl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', ['dat_uuid' => $getEventUuid, 'mode' => 'save', 'copy' => $getCopy, 'recurrence_scope' => $getRecurrenceScope]), $page);
        $form->addInput('dat_headline', $gL10n->get('SYS_TITLE'), $event->getValue('dat_headline'), ['maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED]);

        // if a map link should be shown in the event then show help text and a field where the user could choose the country
        if ($gSettingsManager->getBool('events_show_map_link')) {
            $form->addInput('dat_location', $gL10n->get('SYS_VENUE'), $event->getValue('dat_location'), ['maxLength' => 100, 'helpTextId' => 'SYS_VENUE_LINK']);

            if (!$event->getValue('dat_country') && $getEventUuid === '') {
                $event->setValue('dat_country', $gSettingsManager->getString('default_country'));
            }
            $form->addSelectBox('dat_country', $gL10n->get('SYS_COUNTRY'), $gL10n->getCountries(), ['defaultValue' => $event->getValue('dat_country', 'database')]);
        } else {
            $form->addInput('dat_location', $gL10n->get('SYS_VENUE'), $event->getValue('dat_location'), ['maxLength' => 100]);
        }

        // if room selection is activated then show a select box with all rooms
        if ($gSettingsManager->getBool('events_rooms_enabled')) {
            $sql = 'SELECT room_id, room_name, room_capacity, room_overhang
                      FROM ' . TBL_ROOMS . '
                  ORDER BY room_name';
            $rooms = array();
            foreach ($gDb->queryPrepared($sql)->fetchAll() as $room) {
                $rooms[$room['room_id']] = Language::translateIfTranslationStrId($room['room_name'])
                    . ' (' . $room['room_capacity'] . '+' . ($room['room_overhang'] ?? '0') . ')';
            }
            $form->addSelectBox('dat_room_id', $gL10n->get('SYS_ROOM'), $rooms, ['defaultValue' => (int)$event->getValue('dat_room_id')]);
        }

        $form->addCheckbox('dat_all_day', $gL10n->get('SYS_ALL_DAY'), (bool)$event->getValue('dat_all_day'));
        $form->addInput('event_from', $gL10n->get('SYS_START'), $event->getValue('dat_begin', $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')), ['type' => 'datetime', 'property' => FormPresenter::FIELD_REQUIRED]);
        $form->addInput('event_to', $gL10n->get('SYS_END'), $event->getValue('dat_end', $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')), ['type' => 'datetime', 'property' => FormPresenter::FIELD_REQUIRED]);
        $form->addSelectBoxForCategories('cat_uuid', $gL10n->get('SYS_CALENDAR'), $gDb, 'EVT', FormPresenter::SELECT_BOX_MODUS_EDIT, ['property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $event->getValue('cat_uuid')]);

        if ($gSettingsManager->getBool('inventory_reservations_enabled')
            && $gSettingsManager->getBool('inventory_reservations_events_enabled')
            && InventoryAccessService::canRequestReservation()) {
            $selectedItems = array();
            if ((int)$event->getValue('dat_id') > 0) {
                $eventReservations = $gDb->queryPrepared(
                    'SELECT ivr_ini_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_status IN (?, ?, ?)',
                    array((int)$event->getValue('dat_id'), 'requested', 'approved', 'borrowed')
                );
                while ($eventReservation = $eventReservations->fetch()) {
                    $selectedItems[] = (int)$eventReservation['ivr_ini_id'];
                }
            }
            $availableItems = $gDb->queryPrepared(
                'SELECT ini_id, ind_value
                   FROM ' . TBL_INVENTORY_ITEMS . '
             INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ini_id
             INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
             INNER JOIN ' . TBL_INVENTORY_FIELD_OPTIONS . ' ON ifo_id = ini_status
                  WHERE ini_org_id = ? AND ifo_value <> ?
               ORDER BY ind_value',
                array($gCurrentOrgId, 'SYS_INVENTORY_FILTER_RETIRED_ITEMS')
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            // Register the dynamic field with the form validator. The select itself is rendered in the reservation table.
            $form->addSelectBox('event_inventory_items', $gL10n->get('SYS_INVENTORY_RESERVATIONS'), $availableItems,
                array('defaultValue' => $selectedItems, 'multiselect' => true));
            $reservationItems = array();
            foreach ($availableItems as $itemId => $itemName) {
                $reservationItems[] = array('id' => (int)$itemId, 'name' => $itemName);
            }
            $form->addButton('event_inventory_reservation_add', $gL10n->get('SYS_INVENTORY_ADD_ITEM'),
                array('icon' => 'bi-plus-circle-fill', 'class' => 'btn-primary'));
            $page->assignSmartyVariable('eventInventoryReservationsEnabled', true);
            $page->assignSmartyVariable('eventInventoryReservationItems', $reservationItems);
            $page->assignSmartyVariable('eventInventoryReservationRows', array_map(
                static fn(int $itemId): array => array('itemId' => $itemId), $selectedItems
            ));
            $page->assignSmartyVariable('eventInventoryReservationDataJson', json_encode(array(
                'items' => $reservationItems,
                'deleteLabel' => $gL10n->get('SYS_DELETE'),
                'pleaseChoose' => $gL10n->get('SYS_PLEASE_CHOOSE')
            ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));

            if ((int)$event->getValue('dat_id') > 0) {
                $page->assignSmartyVariable('eventInventoryReservationStatusTable', true);
                $reservationStatusTable = new DataTables($page, 'adm_event_inventory_reservation_statuses');
                $reservationStatusTable->setServerSideProcessing(SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/events.php',
                    array('mode' => 'reservation_status_data', 'dat_uuid' => $getEventUuid, 'recurrence_scope' => $getRecurrenceScope)
                ));
                $reservationStatusTable->disableColumnsSort(array(4));
                $reservationStatusTable->setColumnsNotHideResponsive(array(2, 4));
                $reservationStatusTable->createJavascript(0, 4);
                $page->addJavascript('$(document).on("shown.bs.tab shown.bs.collapse", "#adm_event_reservations_tab, #adm_event_reservations_collapse", function() {
                    var table = $("#adm_event_inventory_reservation_statuses").DataTable();
                    table.columns.adjust().responsive.recalc();
                });', true);
                $page->addJavascript('$(document).on("click", ".event-inventory-reservation-request-again", function() {
                    var button = $(this);
                    button.prop("disabled", true);
                    $.post(button.data("url"), {adm_csrf_token: "' . $gCurrentSession->getCsrfToken() . '"}, function(data) {
                        var response;
                        try {
                            response = typeof data === "string" ? JSON.parse(data) : data;
                        } catch (error) {
                            response = {status: "error"};
                        }
                        if (response.status !== "success") {
                            button.prop("disabled", false);
                            messageBox(response.message || "' . $gL10n->get('SYS_ERROR') . '", "' . $gL10n->get('SYS_ERROR') . '", "error");
                            return;
                        }
                        var statusTable = $("#adm_event_inventory_reservation_statuses").DataTable();
                        statusTable.ajax.reload(null, false);
                        $(document).trigger("eventInventoryReservationRequestedAgain", [button.data("itemId")]);
                    }).fail(function(xhr) {
                        button.prop("disabled", false);
                        var response;
                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (error) {
                            response = {};
                        }
                        messageBox(response.message || "' . $gL10n->get('SYS_ERROR') . '", "' . $gL10n->get('SYS_ERROR') . '", "error");
                    });
                });', true);
            }
        }

        if ($showRecurrenceFields) {
            $form->addSelectBox('event_recurrence_frequency', $gL10n->get('SYS_REPEAT'), ['none' => 'SYS_NO_RECURRENCE', EventRecurrenceRule::FREQUENCY_DAILY => 'SYS_DAILY', EventRecurrenceRule::FREQUENCY_WEEKLY => 'SYS_WEEKLY', EventRecurrenceRule::FREQUENCY_MONTHLY => 'SYS_MONTHLY', EventRecurrenceRule::FREQUENCY_YEARLY => 'SYS_ANNUALLY'], ['property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $recurrenceFrequency, 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_RECURRENCE_FREQUENCY_DESC']);
            $form->addInput('event_recurrence_interval', $gL10n->get('SYS_INTERVAL'), (string)$recurrenceInterval, ['type' => 'number', 'minNumber' => 1, 'maxNumber' => 999, 'step' => 1, 'helpTextId' => 'SYS_RECURRENCE_INTERVAL_DESC']);
            $form->addSelectBox('event_recurrence_weekdays', $gL10n->get('SYS_WEEKDAY'), ['MO' => 'SYS_MONDAY', 'TU' => 'SYS_TUESDAY', 'WE' => 'SYS_WEDNESDAY', 'TH' => 'SYS_THURSDAY', 'FR' => 'SYS_FRIDAY', 'SA' => 'SYS_SATURDAY', 'SU' => 'SYS_SUNDAY'], ['defaultValue' => $recurrenceWeekdays, 'multiselect' => true, 'showContextDependentFirstEntry' => false, 'placeholder' => $gL10n->get('SYS_WEEKDAYS'), 'helpTextId' => 'SYS_RECURRENCE_WEEKDAYS_DESC']);
            $form->addSelectBox('event_recurrence_end_type', $gL10n->get('SYS_ENDS'), [EventRecurrenceRule::END_TYPE_NEVER => 'SYS_NEVER', EventRecurrenceRule::END_TYPE_COUNT => 'SYS_AFTER_NUMBER_OF_OCCURRENCES', EventRecurrenceRule::END_TYPE_UNTIL => 'SYS_ON_DATE'], ['defaultValue' => $recurrenceEndType, 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_RECURRENCE_END_DESC']);
            $form->addInput('event_recurrence_count', $gL10n->get('SYS_NUMBER_OF_OCCURRENCES'), (string)$recurrenceCount, ['type' => 'number', 'minNumber' => 1, 'maxNumber' => 999, 'step' => 1, 'helpTextId' => 'SYS_NUMBER_OF_OCCURRENCES_DESC']);
            $form->addInput('event_recurrence_until', $gL10n->get('SYS_END_DATE'), $recurrenceUntil, ['type' => 'date', 'helpTextId' => 'SYS_RECURRENCE_UNTIL_DESC']);
        }

        $form->addCheckbox('dat_highlight', $gL10n->get('SYS_HIGHLIGHT_EVENT'), (bool)$event->getValue('dat_highlight'));
        $form->addCheckbox('event_participation_possible', $gL10n->get('SYS_REGISTRATION_POSSIBLE'), $eventParticipationPossible, ['helpTextId' => 'SYS_ENABLE_EVENT_REGISTRATION']);

        // add a multi select box to the form where the user can choose all roles whose members could participate in this event
        // read all roles of the current organization
        $sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                       FROM ' . TBL_ROLES . '
                 INNER JOIN ' . TBL_CATEGORIES . '
                         ON cat_id = rol_cat_id
                      WHERE rol_valid  = true
                        AND rol_system = false
                        AND cat_name_intern <> \'EVENTS\'
                        AND cat_org_id = ? -- $gCurrentOrgId
                   ORDER BY cat_sequence, rol_name';
        $sqlDataView = ['query' => $sqlViewRoles, 'params' => [$gCurrentOrgId]];

        // show select box with all assigned roles
        $form->addSelectBoxFromSql('adm_event_participation_right', $gL10n->get('SYS_REGISTRATION_POSSIBLE_FOR'), $gDb, $sqlDataView, ['defaultValue' => $roleViewSet, 'multiselect' => true]);
        $form->addCheckbox('event_current_user_assigned', $gL10n->get('SYS_PARTICIPATE_AT_EVENT'), $eventCurrentUserAssigned, ['helpTextId' => 'SYS_PARTICIPATE_AT_EVENT_DESC']);
        $form->addCheckbox('dat_allow_comments', $gL10n->get('SYS_ALLOW_USER_COMMENTS'), (bool)$event->getValue('dat_allow_comments'), ['helpTextId' => 'SYS_ALLOW_USER_COMMENTS_DESC']);
        $form->addCheckbox('dat_additional_guests', $gL10n->get('SYS_ALLOW_ADDITIONAL_GUESTS'), (bool)$event->getValue('dat_additional_guests'), ['helpTextId' => 'SYS_ALLOW_ADDITIONAL_GUESTS_DESC']);
        $form->addInput('dat_max_members', $gL10n->get('SYS_PARTICIPANTS_LIMIT'), (int)$event->getValue('dat_max_members'), ['type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextId' => 'SYS_EVENT_MAX_MEMBERS']);
        $form->addInput('event_deadline', $gL10n->get('SYS_DEADLINE'), $event->getValue('dat_deadline', $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')), ['type' => 'datetime', 'helpTextId' => 'SYS_EVENT_DEADLINE_DESC']);
        $form->addCheckbox('event_right_list_view', $gL10n->get('SYS_RIGHT_VIEW_PARTICIPANTS'), $flagDateRightListView);
        $form->addCheckbox('event_right_send_mail', $gL10n->get('SYS_RIGHT_MAIL_PARTICIPANTS'), $flagDateRightSendMail);
        $form->addEditor('dat_description', '', $event->getValue('dat_description'));
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), ['icon' => 'bi-check-lg']);

        $page->assignSmartyVariable('userCreatedName', $event->getNameOfCreatingUser());
        $page->assignSmartyVariable('userCreatedTimestamp', $event->getValue('dat_timestamp_create'));
        $page->assignSmartyVariable('lastUserEditedName', $event->getNameOfLastEditingUser());
        $page->assignSmartyVariable('lastUserEditedTimestamp', $event->getValue('dat_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
