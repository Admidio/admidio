<?php
/**
 ***********************************************************************************************
 * Various functions for events
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * dat_uuid  : UUID of the event that should be edited
 * mode      : edit - Create or edit an event
 *             delete - Delete the event
 *             export - Export event in iCal format
 *             participate - User attends to the event
 *             participate_cancel - User cancel participation of the event
 *             participate_maybe - User may participate in the event
 *             subscribe - Subscribe to events in iCal format
 * user_uuid : UUID of the user membership to an event should be edited
 * copy      : true - The event of the dat_id will be copied and the base for this new event
 * cat_uuid  : show all events of calendar with this UUID
 * date_from : set the minimum date of the events that should be shown
 *             if this parameter is not set than the actual date is set
 * date_to   : set the maximum date of the events that should be shown
 *             if this parameter is not set than this date is set to 31.12.9999
 ***********************************************************************************************
 */

use Admidio\Categories\Entity\Category;
use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Events\Service\EventService;
use Admidio\Events\ValueObject\Participants;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'participate', 'participate_cancel', 'participate_maybe', 'export', 'subscribe')));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', $gValidLogin ? array('defaultValue' => $gCurrentUser->getValue('usr_uuid')) : []);
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
    $getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date');

    $participationPossible = true;
    $originalEventUuid = 0;

    // check if module is active
    if ((int)$gSettingsManager->get('events_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if (!in_array($getMode, array('export', 'subscribe'), true) || (int)$gSettingsManager->get('events_module_enabled') === 2) {
        // All functions, except export, are only available for logged-in users.
        require(__DIR__ . '/../../system/login_valid.php');
    }

    if ($getCopy) {
        $originalEventUuid = $getEventUuid;
        $getEventUuid = 0;
    }

    // create event object
    $event = new Event($gDb);
    $event->readDataByUuid($getEventUuid);

    // read user data
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    if (in_array($getMode, array('edit', 'delete'), true)) {
        if ($getEventUuid !== '') {
            // check if the current user has the right to edit this event
            if (!$event->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        } else {
            // check if the user has the right to edit at least one category
            if (count($gCurrentUser->getAllEditableCategories('EVT')) === 0) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }
    }

    if ($getMode === 'edit') {
        // save the country only together with the location
        if (strlen($_POST['dat_location']) === 0) {
            $_POST['dat_country'] = null;
        }

        // check form field input and sanitized it from malicious content
        $eventEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $eventEditForm->validate($_POST);

        $eventService = new EventService($gDb);
        $eventService->saveData($getCopy ? (string)$originalEventUuid : $getEventUuid, $formValues, $getUserUuid, $getCopy);

        $gNavigation->deleteLastUrl();

        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete current announcements, right checks were done before
        $event->delete();

        echo json_encode(array('status' => 'success'));
        exit();
    } elseif (in_array($getMode, array('export', 'subscribe'), true)) { // export event in iCal format
        // If iCal enabled and module is public
        if (!$gSettingsManager->getBool('events_ical_export_enabled')) {
            throw new Exception('SYS_ICAL_DISABLED');
        }

        if ($getDateFrom === '') {
            $getDateFrom = (new DateTime)->sub(new DateInterval('P6M'))->format('Y-m-d');
            $getDateTo = DATE_MAX;
        }

        if ($getMode === 'subscribe') {
            $contentDisposition = 'inline';
        } else {
            $contentDisposition = 'attachment';
        }

        $events = new ModuleEvents();
        if ($getEventUuid !== '') {
            $filename = FileSystemUtils::getSanitizedPathEntry($event->getValue('dat_headline', 'database'));
            $events->setParameter('dat_uuid', $getEventUuid);
        } else {
            $filename = FileSystemUtils::getSanitizedPathEntry($gCurrentOrganization->getValue('org_longname'));
            $events->setDateRange($getDateFrom, $getDateTo);

            if ($getCatUuid !== '') {
                $calendar = new Category($gDb);
                $events->setParameter('cat_uuid', $getCatUuid);
                $calendar->readDataByUuid($getCatUuid);
                $filename .= '-' . $calendar->getValue('cat_name');
            }
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: ' . $contentDisposition . '; filename="' . $filename . '.ics"');
        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        echo $events->getICalContent();
        exit();
    }
    // If participation mode: Set status and write optional parameter from user and show current status message
    if (in_array($getMode, array('participate', 'participate_cancel', 'participate_maybe'), true)) {
        try {
            // check form field input and sanitized it from malicious content
            $eventsParticipationEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        } catch (Exception $e) {
            // call was done directly through ajax then check session csrf token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
            $formValues['dat_comment'] = '';
            $formValues['additional_guests'] = '';
        }

        if (isset($eventsParticipationEditForm)) {
            $formValues = $eventsParticipationEditForm->validate($_POST);
        }

        $member = new Membership($gDb);
        $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));

        // if user is no leader of the event then only allow to handle their own participation
        if (!$participants->isLeader($gCurrentUserId)) {
            $getUserUuid = $gCurrentUser->getValue('usr_uuid');
            $user->readDataByUuid($getUserUuid);
        }

        // if current user is allowed to participate or user could edit this event then update user inputs
        if ($event->possibleToParticipate() || $participants->isLeader($gCurrentUserId)) {
            $member->readDataByColumns(array('mem_rol_id' => (int)$event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
            $member->setValue('mem_comment', $formValues['dat_comment']); // Comments will be saved in any case. Maybe it is a documentation afterward by a leader or admin

            if ($member->isNewRecord()) {
                $member->setValue('mem_begin', DATE_NOW);
            }

            // Now check participants limit and save guests if possible
            if ($event->getValue('dat_max_members') > 0) {
                $totalMembers = $participants->getCount();

                if ($totalMembers + ((int)$formValues['additional_guests'] - (int)$member->getValue('mem_count_guests')) < (int)$event->getValue('dat_max_members')) {
                    $member->setValue('mem_count_guests', $formValues['additional_guests']);
                } else {
                    $participationPossible = false;
                }

                $outputMessage = $gL10n->get('SYS_ROLE_MAX_MEMBERS', array($event->getValue('dat_headline')));

                if ($event->getValue('dat_max_members') === $totalMembers && !$participants->isMemberOfEvent($user->getValue('usr_id'))) {
                    $participationPossible = false; // Participation Limit exceeded and user refused
                }

                if ($event->getValue('dat_max_members') > 0) {
                    $outputMessage .= '<br />' . $gL10n->get('SYS_MAX_PARTICIPANTS') . ':&nbsp;' . (int)$event->getValue('dat_max_members');
                }
            } else {
                $member->setValue('mem_count_guests', $formValues['additional_guests']);
            }

            $member->save();

            // change the participation status, it's always possible to cancel the participation
            if ($participationPossible || $getMode === 'participate_cancel') {
                switch ($getMode) {
                    case 'participate':  // User attends to the event
                        $member->startMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_YES);
                        $outputMessage = $gL10n->get('SYS_ATTEND_EVENT', array($event->getValue('dat_headline'), $event->getValue('dat_begin')));
                        // => EXIT
                        break;

                    case 'participate_cancel':  // User cancel the event
                        if ($gSettingsManager->getBool('events_save_cancellations')) {
                            // Set user status to refused
                            $member->startMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_NO);
                        } else {
                            // Delete entry
                            $member->deleteMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'));
                        }

                        $outputMessage = $gL10n->get('SYS_CANCEL_EVENT', array($event->getValue('dat_headline'), $event->getValue('dat_begin')));
                        // => EXIT
                        break;

                    case 'participate_maybe':  // User may participate in the event
                        $member->startMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_MAYBE);
                        $outputMessage = $gL10n->get('SYS_ATTEND_POSSIBLY', array($event->getValue('dat_headline'), $event->getValue('dat_begin')));
                        // => EXIT
                        break;
                }
            }
        } else {
            throw new Exception('SYS_PARTICIPATE_NO_RIGHTS');
        }

        echo json_encode(array(
            'status' => 'success',
            'message' => $outputMessage,
            'url' => $gNavigation->getUrl()));
        exit();
    }
} catch (Throwable $e) {
    handleException($e, !($getMode === 'export'));
}
