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
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'participate', 'participate_cancel', 'participate_maybe', 'export')));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));
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

    if ($getMode !== 'export' || (int)$gSettingsManager->get('events_module_enabled') === 2) {
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
        // Create a new event or edit an existing event

        // save the country only together with the location
        if (strlen($_POST['dat_location']) === 0) {
            $_POST['dat_country'] = null;
        }

        // check form field input and sanitized it from malicious content
        $eventEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $eventEditForm->validate($_POST);

        if ($formValues['event_participation_possible'] == 1
            && (!isset($formValues['adm_event_participation_right']) || array_count_values($formValues['adm_event_participation_right']) == 0)) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_REGISTRATION_POSSIBLE_FOR'));
        }

        $calendar = new Category($gDb);
        $calendar->readDataByUuid($formValues['cat_uuid']);
        $formValues['dat_cat_id'] = $calendar->getValue('cat_id');

        if ($formValues['dat_all_day'] === '1') {
            $formValues['event_from_time'] = '00:00';
            $formValues['event_to_time'] = '00:00';
        }

        // ------------------------------------------------
        // Check valid format of date and time input
        // ------------------------------------------------

        $startDateTime = DateTime::createFromFormat('Y-m-d H:i', $formValues['event_from'] . ' ' . $formValues['event_from_time']);
        if (!$startDateTime) {
            // Error: now check if date format or time format was wrong and show message
            $startDateTime = DateTime::createFromFormat('Y-m-d', $formValues['event_from']);

            if (!$startDateTime) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_START', 'YYYY-MM-DD'));
            } else {
                throw new Exception('SYS_TIME_INVALID', array($gL10n->get('SYS_TIME') . ' ' . $gL10n->get('SYS_START'), 'HH:ii'));
            }
        } else {
            // now write date and time with database format to date object
            $formValues['dat_begin'] = $formValues['event_from'] . ' ' . $formValues['event_from_time'];
        }

        // if date-to is not filled then take date-from
        if (strlen($formValues['event_to']) === 0) {
            $formValues['event_to'] = $formValues['event_from'];
        }
        if (strlen($formValues['event_to_time']) === 0) {
            $formValues['event_to_time'] = $formValues['event_from_time'];
        }

        $endDateTime = DateTime::createFromFormat('Y-m-d H:i', $formValues['event_to'] . ' ' . $formValues['event_to_time']);

        if (!$endDateTime) {
            // Error: now check if date format or time format was wrong and show message
            $endDateTime = DateTime::createFromFormat('Y-m-d', $formValues['event_to']);

            if (!$endDateTime) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_END', 'YYYY-MM-DD'));
            } else {
                throw new Exception('SYS_TIME_INVALID', array($gL10n->get('SYS_TIME') . ' ' . $gL10n->get('SYS_END'), 'HH:ii'));
            }
        } else {
            // now write date and time with database format to date object
            $formValues['dat_end'] = $formValues['event_to'] . ' ' . $formValues['event_to_time'];
        }

        // DateTo should be greater than DateFrom (Timestamp must be less)
        if ($startDateTime > $endDateTime) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }

        if (!isset($formValues['dat_room_id'])) {
            $formValues['dat_room_id'] = 0;
        }

        if (!is_numeric($formValues['dat_max_members'])) {
            $formValues['dat_max_members'] = 0;
        } else {
            // First check the current participants to prevent invalid reduction of the limit
            $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));
            $totalMembers = $participants->getCount();

            if ($formValues['dat_max_members'] < $totalMembers && $formValues['dat_max_members'] > 0) {
                // minimum value must fit to current number of participants
                $formValues['dat_max_members'] = $totalMembers;
            }
        }

        if ($formValues['event_participation_possible'] == 1 && (string)$formValues['event_deadline'] !== '') {
            $formValues['dat_deadline'] = $formValues['event_deadline'] . ' ' . ((string)$formValues['event_deadline_time'] === '' ? '00:00' : $formValues['event_deadline_time']);
        } else {
            $formValues['dat_deadline'] = null;
        }

        if (isset($formValues['adm_event_participation_right'])) {
            // save changed roles rights of the category
            $rightCategoryView = new RolesRights($gDb, 'category_view', (int)$calendar->getValue('cat_id'));

            // if roles for visibility are assigned to the category than check if the assigned roles of event participation
            // are within the visibility roles set otherwise show error
            if (count($rightCategoryView->getRolesIds()) > 0
                && count(array_intersect(array_map('intval', $formValues['adm_event_participation_right']), $rightCategoryView->getRolesIds())) !== count($formValues['adm_event_participation_right'])) {
                throw new Exception('SYS_EVENT_CATEGORIES_ROLES_DIFFERENT', array(implode(', ', $rightCategoryView->getRolesNames())));
            }
        }

        // ------------------------------------------------
        // Check if the selected room is already reserved for the appointment
        // ------------------------------------------------

        if ($gSettingsManager->getBool('events_rooms_enabled')) {
            $eventRoomId = (int)$formValues['dat_room_id'];

            if ($eventRoomId > 0) {
                $sql = 'SELECT COUNT(*) AS count
                      FROM ' . TBL_EVENTS . '
                     WHERE dat_begin  <= ? -- $endDateTime->format(\'Y-m-d H:i:s\')
                       AND dat_end    >= ? -- $startDateTime->format(\'Y-m-d H:i:s\')
                       AND dat_room_id = ? -- $datRoomId
                       AND dat_uuid    <> ? -- $getDateUuid';
                $queryParams = array(
                    $endDateTime->format('Y-m-d H:i:s'),
                    $startDateTime->format('Y-m-d H:i:s'),
                    $eventRoomId,
                    $getEventUuid
                );
                $eventsStatement = $gDb->queryPrepared($sql, $queryParams);

                if ($eventsStatement->fetchColumn()) {
                    throw new Exception('SYS_ROOM_RESERVED');
                }

                $event->setValue('dat_room_id', $eventRoomId);
                $room = new Room($gDb);
                $room->readDataById($eventRoomId);
                $number = (int)$room->getValue('room_capacity') + (int)$room->getValue('room_overhang');
                $event->setValue('dat_max_members', $number);
                $eventMaxMembers = (int)$formValues['dat_max_members'];

                if ($eventMaxMembers > 0 && $eventMaxMembers < $number) {
                    $event->setValue('dat_max_members', $eventMaxMembers);
                }
                // Raumname für Benachrichtigung
                $room = $room->getValue('room_name');
            }
        }

        // write form values into the event object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'dat_')) {
                $event->setValue($key, $value);
            }
        }

        $gDb->startTransaction();
        if ($event->save()) {
            // Notification an email for new or changed entries to all members of the notification role
            $event->sendNotification();
        }

        // save changed roles rights of the category
        if (isset($formValues['adm_event_participation_right'])) {
            $eventParticipationRoles = array_map('intval', $formValues['adm_event_participation_right']);
        } else {
            $eventParticipationRoles = array();
        }

        $rightEventParticipation = new RolesRights($gDb, 'event_participation', (int)$event->getValue('dat_id'));
        $rightEventParticipation->saveRoles($eventParticipationRoles);

        // ----------------------------------------------
        // if necessary write away role for participation
        // ----------------------------------------------

        if ($formValues['event_participation_possible'] == 1) {
            if ($event->getValue('dat_rol_id') > 0) {
                // if event exists, and you could register to this event then we must check
                // if the data of the role must be changed
                $role = new Role($gDb, (int)$event->getValue('dat_rol_id'));

                $role->setValue('rol_name', $event->getDateTimePeriod(false) . ' ' . $event->getValue('dat_headline'));
                $role->setValue('rol_description', substr($event->getValue('dat_description'), 0, 3999));
                // role members are allowed to view lists
                $role->setValue('rol_view_memberships', ($formValues['event_right_list_view']) ? Role::VIEW_ROLE_MEMBERS : Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT);
                // role members are allowed to send mail to this role
                $role->setValue('rol_mail_this_role', ($formValues['event_right_send_mail']) ? Role::VIEW_ROLE_MEMBERS : Role::VIEW_NOBODY);
                $role->setValue('rol_max_members', (int)$event->getValue('dat_max_members'));

                $role->save();
            } else {
                // create role for participation
                if ($getCopy) {
                    // copy original role with their settings
                    $sql = 'SELECT dat_rol_id
                              FROM ' . TBL_EVENTS . '
                             WHERE dat_uuid = ?';
                    $pdoStatement = $gDb->queryPrepared($sql, array($originalEventUuid));

                    $role = new Role($gDb, (int)$pdoStatement->fetchColumn());
                    $role->setNewRecord();
                } else {
                    // Read category for event participation
                    $sql = 'SELECT cat_id
                              FROM ' . TBL_CATEGORIES . '
                             WHERE cat_name_intern = \'EVENTS\'
                               AND cat_org_id = ?';
                    $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
                    if(!$row = $pdoStatement->fetch()) {
                        throw new Exception('No category found for event participation!');
                    }
                    $role = new Role($gDb);
                    $role->setType(Role::ROLE_EVENT);

                    // these are the default settings for a event role
                    $role->setValue('rol_cat_id', (int) $row['cat_id']);
                    // role members are allowed to view lists
                    $role->setValue('rol_view_memberships', ($formValues['event_right_list_view']) ? Role::VIEW_ROLE_MEMBERS : Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT);
                    // role members are allowed to send mail to this role
                    $role->setValue('rol_mail_this_role', ($formValues['event_right_send_mail']) ? Role::VIEW_ROLE_MEMBERS : Role::VIEW_NOBODY);
                    $role->setValue('rol_leader_rights', Role::ROLE_LEADER_MEMBERS_ASSIGN);    // leaders are allowed to add or remove participants
                    $role->setValue('rol_max_members', (int)$formValues['dat_max_members']);
                }

                $role->setValue('rol_name', $event->getDateTimePeriod(false) . ' ' . $event->getValue('dat_headline', 'database'));
                $role->setValue('rol_description', substr($event->getValue('dat_description', 'database'), 0, 3999));

                $role->save();

                // match dat_rol_id (reference between event and role)
                $event->setValue('dat_rol_id', (int)$role->getValue('rol_id'));
                $event->save();
            }

            // check if flag is set that current user wants to participate as leader to the event
            if (isset($formValues['event_current_user_assigned']) && $formValues['event_current_user_assigned'] == 1
                && !$gCurrentUser->isLeaderOfRole((int)$event->getValue('dat_rol_id'))) {
                // user wants to participate -> add him to event and set approval state to 2 ( user attend )
                $role->startMembership($user->getValue('usr_id'), true);
            } elseif (!isset($formValues['event_current_user_assigned'])
                && $gCurrentUser->isMemberOfRole((int)$event->getValue('dat_rol_id'))) {
                // user doesn't want to participate as leader -> remove his participation as leader from the event,
                // don't remove the participation itself!
                $member = new Membership($gDb);
                $member->readDataByColumns(array('mem_rol_id' => (int)$role->getValue('rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
                $member->setValue('mem_leader', 0);
                $member->save();
            }
        } else {
            if ($event->getValue('dat_rol_id') > 0) {
                // event participation was deselected -> delete flag in event and than delete role
                $role = new Role($gDb, (int)$event->getValue('dat_rol_id'));
                $event->setValue('dat_rol_id', '');
                $event->save();
                $role->delete();
            }
        }

        $gDb->endTransaction();
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
    } elseif ($getMode === 'export') {  // export event in iCal format
        // If iCal enabled and module is public
        if (!$gSettingsManager->getBool('events_ical_export_enabled')) {
            throw new Exception('SYS_ICAL_DISABLED');
        }

        if ($getDateFrom === '') {
            $getDateFrom = (new DateTime)->sub(new DateInterval('P6M'))->format('Y-m-d');
            $getDateTo = DATE_MAX;
        }

        $events = new ModuleEvents();
        if ($getEventUuid !== '') {
            $filename = FileSystemUtils::getSanitizedPathEntry($event->getValue('dat_headline', 'database')) . '.ics';
            $events->setParameter('dat_uuid', $getEventUuid);
        } else {
            $filename = FileSystemUtils::getSanitizedPathEntry($gCurrentOrganization->getValue('org_longname')) . '.ics';
            $events->setDateRange($getDateFrom, $getDateTo);

            if ($getCatUuid !== '') {
                $calendar = new Category($gDb);
                $events->setParameter('cat_uuid', $getCatUuid);
                $calendar->readDataByUuid($getCatUuid);
                $filename .= '-'.$calendar->getValue('cat_name');
            }
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

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

                if ($totalMembers + ($formValues['additional_guests'] - (int)$member->getValue('mem_count_guests')) < $event->getValue('dat_max_members')) {
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
    if ($getMode === 'export') {
        $gMessage->show($e->getMessage());
    } else {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
