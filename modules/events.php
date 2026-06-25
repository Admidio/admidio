<?php
/**
 ***********************************************************************************************
 * Show event pages and handle event view routing.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode      - cards        : (Default) shows events in detailed card view
 *             list_compact : shows compact event list
 *             list_room    : shows compact event list with room column
 *             list_participants : shows compact event list with participants column
 *             list_description  : shows compact event list with description column
 *             print_cards  : shows printable event card view
 *             print_list_compact : shows printable compact event list
 *             print_list_room    : shows printable compact event list with room column
 *             print_list_participants : shows printable compact event list with participants column
 *             print_list_description  : shows printable compact event list with description column
 *             new          : show form to create an event
 *             edit         : show form to edit an event
 *             save         : save event form data
 *             delete       : delete or cancel an event
 *             export       : export event data in iCal format
 *             recurrence_scope_form: show modal form to choose recurrence edit/delete scope
 *             participation_form: show the modal form to edit participation details
 *             participate  : user attends to the event
 *             participate_cancel - user cancels participation of the event
 *             participate_maybe  - user may participate in the event
 * start     - Position of query recordset where the visual output should start
 * cat_uuid  - show all events of calendar with this UUID
 * dat_uuid  - UUID of a single event that should be shown
 * show      - all               : (Default) show all events
 *           - maybe_participate : Show only events where the current user participates or could participate
 *           - only_participate  : Show only events where the current user participates
 * date_mode - actual      : (Default) shows actual events and all events in future
 *             old         : shows events in the past
 *             all         : shows all events in past and future
 * date_from - set the minimum date of the events that should be shown
 * date_to   - set the maximum date of the events that should be shown
 * recurrence_action - edit  : choose scope for editing a recurring event
 *                     delete: choose scope for deleting a recurring event
 ***********************************************************************************************
 */

use Admidio\Events\Service\EventService;
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\EventFormPresenter;
use Admidio\UI\Presenter\EventListPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'cards', 'validValues' => array('cards', 'list_compact', 'list_room', 'list_participants', 'list_description', 'print_cards', 'print_list_compact', 'print_list_room', 'print_list_participants', 'print_list_description', 'new', 'edit', 'save', 'delete', 'export', 'recurrence_scope_form', 'participation_form', 'participate', 'participate_cancel', 'participate_maybe')));
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'uuid');
    $getShow = admFuncVariableIsValid($_GET, 'show', 'string', array('defaultValue' => 'all', 'validValues' => array('all', 'maybe_participate', 'only_participate')));
    $getDateMode = admFuncVariableIsValid($_GET, 'date_mode', 'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old', 'all')));
    $getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
    $getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date');
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');
    $getRecurrenceScope = admFuncVariableIsValid($_GET, 'recurrence_scope', 'string', array('defaultValue' => 'this', 'validValues' => array('this', 'series')));
    $getRecurrenceAction = admFuncVariableIsValid($_GET, 'recurrence_action', 'string', array('defaultValue' => 'edit', 'validValues' => array('edit', 'delete')));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', $gValidLogin ? array('defaultValue' => $gCurrentUser->getValue('usr_uuid')) : array());

    // check if module is active
    if ((int)$gSettingsManager->get('events_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('events_module_enabled') === 2) {
        // module only for valid Users
        require(__DIR__ . '/../system/login_valid.php');
    }

    $eventPresenter = new EventListPresenter($getEventUuid);
    $eventFormPresenter = new EventFormPresenter($getEventUuid);

    switch ($getMode) {
        case 'cards':
            $eventPresenter->createCards($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo);
            $eventPresenter->show();
            break;

        case 'list_compact':
            $eventPresenter->createCompactList($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'compact');
            $eventPresenter->show();
            break;

        case 'list_room':
            $eventPresenter->createCompactList($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'room');
            $eventPresenter->show();
            break;

        case 'list_participants':
            $eventPresenter->createCompactList($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'participants');
            $eventPresenter->show();
            break;

        case 'list_description':
            $eventPresenter->createCompactList($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'description');
            $eventPresenter->show();
            break;

        case 'print_cards':
            $eventPresenter->createPrintView($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'detail');
            $eventPresenter->show();
            break;

        case 'print_list_compact':
            $eventPresenter->createPrintView($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'compact');
            $eventPresenter->show();
            break;

        case 'print_list_room':
            $eventPresenter->createPrintView($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'room');
            $eventPresenter->show();
            break;

        case 'print_list_participants':
            $eventPresenter->createPrintView($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'participants');
            $eventPresenter->show();
            break;

        case 'print_list_description':
            $eventPresenter->createPrintView($getDateMode, $getStart, $getCatUuid, $getEventUuid, $getShow, $getDateFrom, $getDateTo, 'description');
            $eventPresenter->show();
            break;

        case 'new':
        case 'edit':
            require(__DIR__ . '/../system/login_valid.php');
            $eventFormPresenter->createEditForm($getEventUuid, $getCopy, $getRecurrenceScope);
            $eventFormPresenter->show();
            break;

        case 'save':
            require(__DIR__ . '/../system/login_valid.php');
            $eventService = new EventService($gDb);
            echo json_encode($eventService->saveEvent($getEventUuid, $getCopy, $getRecurrenceScope));
            break;

        case 'export':
            $eventService = new EventService($gDb);
            $eventService->exportICal($getEventUuid, $getCatUuid, $getDateFrom, $getDateTo);
            break;

        case 'delete':
            require(__DIR__ . '/../system/login_valid.php');
            $eventService = new EventService($gDb);
            $eventService->deleteEvent($getEventUuid, $getRecurrenceScope);
            echo json_encode(array('status' => 'success'));
            break;

        case 'recurrence_scope_form':
            require(__DIR__ . '/../system/login_valid.php');
            header('Content-type: text/html; charset=utf-8');
            echo $eventFormPresenter->createRecurrenceScopeForm($getEventUuid, $getRecurrenceAction);
            break;

        case 'participation_form':
            require(__DIR__ . '/../system/login_valid.php');
            header('Content-type: text/html; charset=utf-8');
            echo $eventFormPresenter->createParticipationForm($getEventUuid, $getUserUuid);
            break;

        case 'participate':
        case 'participate_cancel':
        case 'participate_maybe':
            require(__DIR__ . '/../system/login_valid.php');
            $eventService = new EventService($gDb);
            echo json_encode($eventService->changeParticipation($getEventUuid, $getMode, $getUserUuid));
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('participation_form', 'recurrence_scope_form'), true)) {
        $gMessage->showInModalWindow();
        handleException($e);
    } else {
        handleException($e, in_array($getMode, array('save', 'delete', 'export', 'participate', 'participate_cancel', 'participate_maybe'), true));
    }
}
