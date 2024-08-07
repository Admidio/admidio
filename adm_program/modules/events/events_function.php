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
    $postAdditionalGuests = admFuncVariableIsValid($_POST, 'additional_guests', 'int');
    $postUserComment = admFuncVariableIsValid($_POST, 'dat_comment', 'text');

    $participationPossible = true;
    $originalEventUuid = 0;

    // check if module is active
    if ((int)$gSettingsManager->get('events_module_enabled') === 0) {
        throw new AdmException('SYS_MODULE_DISABLED');
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
    $event = new TableEvent($gDb);
    $event->readDataByUuid($getEventUuid);

    // read user data
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    if (in_array($getMode, array('edit', 'delete'), true)) {
        if ($getEventUuid !== '') {
            // check if the current user has the right to edit this event
            if (!$event->isEditable()) {
                throw new AdmException('SYS_NO_RIGHTS');
            }
        } else {
            // check if the user has the right to edit at least one category
            if (count($gCurrentUser->getAllEditableCategories('EVT')) === 0) {
                throw new AdmException('SYS_NO_RIGHTS');
            }
        }
    }

    if ($getMode === 'edit') {
        // Create a new event or edit an existing event

        if (isset($_SESSION['events_edit_form'])) {
            $eventEditForm = $_SESSION['events_edit_form'];
            $eventEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        if ($_POST['event_participation_possible'] == 1
            && (!isset($_POST['adm_event_participation_right']) || array_count_values($_POST['adm_event_participation_right']) == 0)) {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_REGISTRATION_POSSIBLE_FOR'));
        }

        $calendar = new TableCategory($gDb);
        $calendar->readDataByUuid($_POST['cat_uuid']);
        $_POST['dat_cat_id'] = $calendar->getValue('cat_id');

        if (isset($_POST['dat_all_day'])) {
            $_POST['event_from_time'] = '00:00';
            $_POST['event_to_time'] = '00:00';
            $event->setValue('dat_all_day', 1);
        } else {
            $event->setValue('dat_all_day', 0);
        }

        // save the country only together with the location
        if (strlen($_POST['dat_location']) === 0) {
            $_POST['dat_country'] = null;
        }

        // ------------------------------------------------
        // Check valid format of date and time input
        // ------------------------------------------------

        $startDateTime = DateTime::createFromFormat('Y-m-d H:i', $_POST['event_from'] . ' ' . $_POST['event_from_time']);
        if (!$startDateTime) {
            // Error: now check if date format or time format was wrong and show message
            $startDateTime = DateTime::createFromFormat('Y-m-d', $_POST['event_from']);

            if (!$startDateTime) {
                throw new AdmException('SYS_DATE_INVALID', array('SYS_START', 'YYYY-MM-DD'));
            } else {
                throw new AdmException('SYS_TIME_INVALID', array($gL10n->get('SYS_TIME') . ' ' . $gL10n->get('SYS_START'), 'HH:ii'));
            }
        } else {
            // now write date and time with database format to date object
            $_POST['dat_begin'] = $_POST['event_from'] . ' ' . $_POST['event_from_time'];
        }

        // if date-to is not filled then take date-from
        if (strlen($_POST['event_to']) === 0) {
            $_POST['event_to'] = $_POST['event_from'];
        }
        if (strlen($_POST['event_to_time']) === 0) {
            $_POST['event_to_time'] = $_POST['event_from_time'];
        }

        $endDateTime = DateTime::createFromFormat('Y-m-d H:i', $_POST['event_to'] . ' ' . $_POST['event_to_time']);

        if (!$endDateTime) {
            // Error: now check if date format or time format was wrong and show message
            $endDateTime = DateTime::createFromFormat('Y-m-d', $_POST['event_to']);

            if (!$endDateTime) {
                throw new AdmException('SYS_DATE_INVALID', array('SYS_END', 'YYYY-MM-DD'));
            } else {
                throw new AdmException('SYS_TIME_INVALID', array($gL10n->get('SYS_TIME') . ' ' . $gL10n->get('SYS_END'), 'HH:ii'));
            }
        } else {
            // now write date and time with database format to date object
            $_POST['dat_end'] = $_POST['event_to'] . ' ' . $_POST['event_to_time'];
        }

        // DateTo should be greater than DateFrom (Timestamp must be less)
        if ($startDateTime > $endDateTime) {
            throw new AdmException('SYS_DATE_END_BEFORE_BEGIN');
        }

        if (!isset($_POST['dat_room_id'])) {
            $_POST['dat_room_id'] = 0;
        }

        if (!is_numeric($_POST['dat_max_members'])) {
            $_POST['dat_max_members'] = 0;
        } else {
            // First check the current participants to prevent invalid reduction of the limit
            $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));
            $totalMembers = $participants->getCount();

            if ($_POST['dat_max_members'] < $totalMembers && $_POST['dat_max_members'] > 0) {
                // minimum value must fit to current number of participants
                $_POST['dat_max_members'] = $totalMembers;
            }
        }

        if ($_POST['event_participation_possible'] == 1 && (string)$_POST['event_deadline'] !== '') {
            $_POST['dat_deadline'] = $_POST['event_deadline'] . ' ' . ((string)$_POST['event_deadline_time'] === '' ? '00:00' : $_POST['event_deadline_time']);
        } else {
            $_POST['dat_deadline'] = null;
        }

        if (isset($_POST['adm_event_participation_right'])) {
            // save changed roles rights of the category
            $rightCategoryView = new RolesRights($gDb, 'category_view', (int)$calendar->getValue('cat_id'));

            // if roles for visibility are assigned to the category than check if the assigned roles of event participation
            // are within the visibility roles set otherwise show error
            if (count($rightCategoryView->getRolesIds()) > 0
                && count(array_intersect(array_map('intval', $_POST['adm_event_participation_right']), $rightCategoryView->getRolesIds())) !== count($_POST['adm_event_participation_right'])) {
                throw new AdmException('SYS_EVENT_CATEGORIES_ROLES_DIFFERENT', array(implode(', ', $rightCategoryView->getRolesNames())));
            }
        }

        // ------------------------------------------------
        // Check if the selected room is already reserved for the appointment
        // ------------------------------------------------

        if ($gSettingsManager->getBool('events_rooms_enabled')) {
            $eventRoomId = (int)$_POST['dat_room_id'];

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
                    throw new AdmException('SYS_ROOM_RESERVED');
                }

                $event->setValue('dat_room_id', $eventRoomId);
                $room = new TableRooms($gDb);
                $room->readDataById($eventRoomId);
                $number = (int)$room->getValue('room_capacity') + (int)$room->getValue('room_overhang');
                $event->setValue('dat_max_members', $number);
                $eventMaxMembers = (int)$_POST['dat_max_members'];

                if ($eventMaxMembers > 0 && $eventMaxMembers < $number) {
                    $event->setValue('dat_max_members', $eventMaxMembers);
                }
                // Raumname für Benachrichtigung
                $room = $room->getValue('room_name');
            }
        }

        // write all POST parameters into the date object
        foreach ($_POST as $key => $value) { // TODO possible security issue
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
        if (isset($_POST['adm_event_participation_right'])) {
            $eventParticipationRoles = array_map('intval', $_POST['adm_event_participation_right']);
        } else {
            $eventParticipationRoles = array();
        }

        $rightEventParticipation = new RolesRights($gDb, 'event_participation', (int)$event->getValue('dat_id'));
        $rightEventParticipation->saveRoles($eventParticipationRoles);

        // ----------------------------------------------
        // if necessary write away role for participation
        // ----------------------------------------------

        if ($_POST['event_participation_possible'] == 1) {
            if ($event->getValue('dat_rol_id') > 0) {
                // if event exists, and you could register to this event then we must check
                // if the data of the role must be changed
                $role = new TableRoles($gDb, (int)$event->getValue('dat_rol_id'));

                $role->setValue('rol_name', $event->getDateTimePeriod(false) . ' ' . $event->getValue('dat_headline'));
                $role->setValue('rol_description', substr($event->getValue('dat_description'), 0, 3999));
                // role members are allowed to view lists
                $role->setValue('rol_view_memberships', isset($_POST['event_right_list_view']) ? TableRoles::VIEW_ROLE_MEMBERS : TableRoles::VIEW_NOBODY);
                // role members are allowed to send mail to this role
                $role->setValue('rol_mail_this_role', isset($_POST['event_right_send_mail']) ? TableRoles::VIEW_ROLE_MEMBERS : TableRoles::VIEW_NOBODY);
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

                    $role = new TableRoles($gDb, (int)$pdoStatement->fetchColumn());
                    $role->setNewRecord();
                } else {
                    // Read category for event participation
                    $sql = 'SELECT cat_id
                      FROM ' . TBL_CATEGORIES . '
                     WHERE cat_name_intern = \'EVENTS\'
                       AND cat_org_id = ?';
                    $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
                    $role = new TableRoles($gDb);
                    $role->setType(TableRoles::ROLE_EVENT);

                    // these are the default settings for an event role
                    $role->setValue('rol_cat_id', (int)$pdoStatement->fetchColumn());
                    // role members are allowed to view lists
                    $role->setValue('rol_view_memberships', isset($_POST['event_right_list_view']) ? 1 : 3);
                    // role members are allowed to send mail to this role
                    $role->setValue('rol_mail_this_role', isset($_POST['event_right_send_mail']) ? 1 : 0);
                    $role->setValue('rol_leader_rights', TableRoles::ROLE_LEADER_MEMBERS_ASSIGN);    // leaders are allowed to add or remove participants
                    $role->setValue('rol_max_members', (int)$_POST['dat_max_members']);
                }

                $role->setValue('rol_name', $event->getDateTimePeriod(false) . ' ' . $event->getValue('dat_headline', 'database'));
                $role->setValue('rol_description', substr($event->getValue('dat_description', 'database'), 0, 3999));

                $role->save();

                // match dat_rol_id (reference between event and role)
                $event->setValue('dat_rol_id', (int)$role->getValue('rol_id'));
                $event->save();
            }

            // check if flag is set that current user wants to participate as leader to the event
            if (isset($_POST['event_current_user_assigned']) && $_POST['event_current_user_assigned'] == 1
                && !$gCurrentUser->isLeaderOfRole((int)$event->getValue('dat_rol_id'))) {
                // user wants to participate -> add him to event and set approval state to 2 ( user attend )
                $role->startMembership($user->getValue('usr_id'), true);
            } elseif (!isset($_POST['event_current_user_assigned'])
                && $gCurrentUser->isMemberOfRole((int)$event->getValue('dat_rol_id'))) {
                // user doesn't want to participate as leader -> remove his participation as leader from the event,
                // don't remove the participation itself!
                $member = new TableMembers($gDb);
                $member->readDataByColumns(array('mem_rol_id' => (int)$role->getValue('rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
                $member->setValue('mem_leader', 0);
                $member->save();
            }
        } else {
            if ($event->getValue('dat_rol_id') > 0) {
                // event participation was deselected -> delete flag in event and than delete role
                $role = new TableRoles($gDb, (int)$event->getValue('dat_rol_id'));
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
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        // delete current announcements, right checks were done before
        $event->delete();

        echo json_encode(array('status' => 'success'));
        exit();
    } elseif ($getMode === 'export') {  // export event in iCal format
        // If iCal enabled and module is public
        if (!$gSettingsManager->getBool('events_ical_export_enabled')) {
            throw new AdmException('SYS_ICAL_DISABLED');
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
            $filename = FileSystemUtils::getSanitizedPathEntry($gCurrentOrganization->getValue('org_longname'));
            $events->setDateRange($getDateFrom, $getDateTo);

            if ($getCatUuid !== '') {
                $calendar = new TableCategory($gDb);
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
        if ($postAdditionalGuests > 0 || $postUserComment !== '') {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
        }

        $member = new TableMembers($gDb);
        $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));

        // if current user is allowed to participate or user could edit this event then update user inputs
        if ($event->possibleToParticipate() || $participants->isLeader($gCurrentUserId)) {
            $member->readDataByColumns(array('mem_rol_id' => (int)$event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
            $member->setValue('mem_comment', $postUserComment); // Comments will be saved in any case. Maybe it is a documentation afterward by a leader or admin

            if ($member->isNewRecord()) {
                $member->setValue('mem_begin', DATE_NOW);
            }

            // Now check participants limit and save guests if possible
            if ($event->getValue('dat_max_members') > 0) {
                $totalMembers = $participants->getCount();

                if ($totalMembers + ($postAdditionalGuests - (int)$member->getValue('mem_count_guests')) < $event->getValue('dat_max_members')) {
                    $member->setValue('mem_count_guests', $postAdditionalGuests);
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
                $member->setValue('mem_count_guests', $postAdditionalGuests);
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
            $outputMessage = $gL10n->get('SYS_PARTICIPATE_NO_RIGHTS');
        }

        $gMessage->setForwardUrl($gNavigation->getUrl());
        $gMessage->show($outputMessage, $gL10n->get('SYS_PARTICIPATE'));
    }
} catch (AdmException|Exception $e) {
    if (in_array($getMode, array('edit', 'delete'), true)) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
