<?php
/**
 ***********************************************************************************************
 * Various functions for events
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * dat_uuid  : UUID of the event that should be edited
 * mode      : 1 - Create or edit an event
 *             2 - Delete the event
 *             3 - User attends to the event
 *             4 - User cancel the event
 *             6 - Export event in ical format
 *             7 - User may participate in the event
 * user_uuid : UUID of the user membership to an event should be edited
 * copy      : true - The event of the dat_id will be copied and the base for this new event
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

if ($_GET['mode'] == 2) {
    $gMessage->showHtmlTextOnly(true);
}

// Initialize and check the parameters
$getDateUuid          = admFuncVariableIsValid($_GET, 'dat_uuid', 'string');
$getMode              = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));
$getCopy              = admFuncVariableIsValid($_GET, 'copy', 'bool');
$getUserUuid          = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));
$postAdditionalGuests = admFuncVariableIsValid($_POST, 'additional_guests', 'int');
$postUserComment      = admFuncVariableIsValid($_POST, 'dat_comment', 'text');

$participationPossible = true;
$originalDateUuid      = 0;

// check if module is active
if ((int) $gSettingsManager->get('enable_dates_module') === 0) {
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if ($getMode !== 6 || (int) $gSettingsManager->get('enable_dates_module') === 2) {
    // All functions, except export and login, are only available for logged-in users.
    require(__DIR__ . '/../../system/login_valid.php');
}

if ($getCopy) {
    $originalDateUuid = $getDateUuid;
    $getDateUuid      = 0;
}

// create event object
$date = new TableDate($gDb);
$date->readDataByUuid($getDateUuid);

// read user data
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if (in_array($getMode, array(1, 2), true)) {
    if ($getDateUuid !== '') {
        // check if the current user has the right to edit this event
        if (!$date->isEditable()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('DAT')) === 0) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
}

if ($getMode === 1) {  // Create a new event or edit an existing event
    $_SESSION['dates_request'] = $_POST;
    $dateIsNew = $date->isNewRecord();

    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    // ------------------------------------------------
    // check if all necessary fields are filled
    // ------------------------------------------------

    if (!isset($_POST['date_participation_possible'])) {
        $_POST['date_participation_possible'] = 0;
    }
    if ($_POST['date_participation_possible'] == 1
    && (!isset($_POST['adm_event_participation_right']) || array_count_values($_POST['adm_event_participation_right']) == 0)) {
        $_SESSION['dates_request']['adm_event_participation_right'] = '';
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('DAT_REGISTRATION_POSSIBLE_FOR'))));
        // => EXIT
    }

    if (strlen($_POST['dat_headline']) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TITLE'))));
        // => EXIT
    }
    if (strlen($_POST['date_from']) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_START'))));
        // => EXIT
    }
    if (strlen($_POST['date_to']) === 0 && $_POST['dat_repeat_type'] == 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_END'))));
        // => EXIT
    }
    if (strlen($_POST['date_from_time']) === 0 && !isset($_POST['dat_all_day'])) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_START'))));
        // => EXIT
    }
    if (strlen($_POST['date_to_time']) === 0 && !isset($_POST['dat_all_day'])) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_END'))));
        // => EXIT
    }
    if (strlen($_POST['cat_uuid']) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('DAT_CALENDAR'))));
        // => EXIT
    } else {
        $calendar = new TableCategory($gDb);
        $calendar->readDataByUuid($_POST['cat_uuid']);
        $_POST['dat_cat_id'] = $calendar->getValue('cat_id');
    }

    if (isset($_POST['dat_all_day'])) {
        $_POST['date_from_time']        = '00:00';
        $_POST['date_to_time']          = '00:00';
        $date->setValue('dat_all_day', 1);
    } else {
        $date->setValue('dat_all_day', 0);
    }

    // save the country only together with the location
    if (strlen($_POST['dat_location']) === 0) {
        $_POST['dat_country'] = null;
    }

    // ------------------------------------------------
    // Check valid format of date and time input
    // ------------------------------------------------

    $startDateTime = DateTime::createFromFormat('Y-m-d H:i', $_POST['date_from'].' '.$_POST['date_from_time']);
    if (!$startDateTime) {
        // Error: now check if date format or time format was wrong and show message
        $startDateTime = DateTime::createFromFormat('Y-m-d', $_POST['date_from']);

        if (!$startDateTime) {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_START'), 'YYYY-MM-DD')));
        // => EXIT
        } else {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', array($gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_START'), 'HH:ii')));
            // => EXIT
        }
    } else {
        // now write date and time with database format to date object
        $_POST['dat_begin'] = $_POST['date_from'].' '.$_POST['date_from_time'];
    }

    // if date-to is not filled then take date-from
    if (strlen($_POST['date_to']) === 0) {
        $_POST['date_to'] = $_POST['date_from'];
    }
    if (strlen($_POST['date_to_time']) === 0) {
        $_POST['date_to_time'] = $_POST['date_from_time'];
    }

    $endDateTime = DateTime::createFromFormat('Y-m-d H:i', $_POST['date_to'].' '.$_POST['date_to_time']);

    if (!$endDateTime) {
        // Error: now check if date format or time format was wrong and show message
        $endDateTime = DateTime::createFromFormat('Y-m-d', $_POST['date_to']);

        if (!$endDateTime) {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_END'), 'YYYY-MM-DD')));
        // => EXIT
        } else {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', array($gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_END'), 'HH:ii')));
            // => EXIT
        }
    } else {
        // now write date and time with database format to date object
        $_POST['dat_end'] = $_POST['date_to'].' '.$_POST['date_to_time'];
    }

    // DateTo should be greater than DateFrom (Timestamp must be less)
    if ($startDateTime > $endDateTime) {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        // => EXIT
    }

    if (!isset($_POST['dat_highlight'])) {
        $_POST['dat_highlight'] = 0;
    }
    if (!isset($_POST['dat_all_day'])) {
        $_POST['dat_all_day'] = 0;
    }
    if (!isset($_POST['dat_room_id'])) {
        $_POST['dat_room_id'] = 0;
    }

    if (!is_numeric($_POST['dat_max_members'])) {
        $_POST['dat_max_members'] = 0;
    } else {
        // First check the current participants to prevent invalid reduction of the limit
        $participants = new Participants($gDb, (int) $date->getValue('dat_rol_id'));
        $totalMembers = $participants->getCount();

        if ($_POST['dat_max_members'] < $totalMembers && $_POST['dat_max_members'] > 0) {
            // minimum value must fit to current number of participants
            $_POST['dat_max_members'] = $totalMembers;
        }
    }
    if (!isset($_POST['dat_allow_comments'])) {
        $_POST['dat_allow_comments'] = 0;
    }
    if (!isset($_POST['dat_additional_guests'])) {
        $_POST['dat_additional_guests'] = 0;
    }

    if ($_POST['date_participation_possible'] == 1 && (string) $_POST['date_deadline'] !== '') {
        $_POST['dat_deadline'] = $_POST['date_deadline'].' '.((string) $_POST['date_deadline_time'] === '' ? '00:00' : $_POST['date_deadline_time']);
    } else {
        $_POST['dat_deadline'] = null;
    }

    if (isset($_POST['adm_event_participation_right'])) {
        // save changed roles rights of the category
        $rightCategoryView = new RolesRights($gDb, 'category_view', (int) $calendar->getValue('cat_id'));

        // if roles for visibility are assigned to the category than check if the assigned roles of event participation
        // are within the visibility roles set otherwise show error
        if (count($rightCategoryView->getRolesIds()) > 0
        && count(array_intersect(array_map('intval', $_POST['adm_event_participation_right']), $rightCategoryView->getRolesIds())) !== count($_POST['adm_event_participation_right'])) {
            $gMessage->show($gL10n->get('DAT_ROLES_DIFFERENT', array(implode(', ', $rightCategoryView->getRolesNames()))));
            // => EXIT
        }
    }

    // make html in description secure
    $_POST['dat_description'] = admFuncVariableIsValid($_POST, 'dat_description', 'html');

    // ------------------------------------------------
    // Check if the selected room is already reserved for the appointment
    // ------------------------------------------------

    if ($gSettingsManager->getBool('dates_show_rooms')) {
        $datRoomId = (int) $_POST['dat_room_id'];

        if ($datRoomId > 0) {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_DATES.'
                     WHERE dat_begin  <= ? -- $endDateTime->format(\'Y-m-d H:i:s\')
                       AND dat_end    >= ? -- $startDateTime->format(\'Y-m-d H:i:s\')
                       AND dat_room_id = ? -- $datRoomId
                       AND dat_uuid    <> ? -- $getDateUuid';
            $queryParams = array(
                $endDateTime->format('Y-m-d H:i:s'),
                $startDateTime->format('Y-m-d H:i:s'),
                $datRoomId,
                $getDateUuid
            );
            $datesStatement = $gDb->queryPrepared($sql, $queryParams);

            if ($datesStatement->fetchColumn()) {
                $gMessage->show($gL10n->get('DAT_ROOM_RESERVED'));
                // => EXIT
            }

            $date->setValue('dat_room_id', $datRoomId);
            $room = new TableRooms($gDb);
            $room->readDataById($datRoomId);
            $number = (int) $room->getValue('room_capacity') + (int) $room->getValue('room_overhang');
            $date->setValue('dat_max_members', $number);
            $datMaxMembers = (int) $_POST['dat_max_members'];

            if ($datMaxMembers > 0 && $datMaxMembers < $number) {
                $date->setValue('dat_max_members', $datMaxMembers);
            }
            // Raumname für Benachrichtigung
            $room = $room->getValue('room_name');
        }
    }

    try {
        // write all POST parameters into the date object
        foreach ($_POST as $key => $value) { // TODO possible security issue
            if (str_starts_with($key, 'dat_')) {
                $date->setValue($key, $value);
            }
        }

        $gDb->startTransaction();
        $returnCode = $date->save();
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $datId = (int) $date->getValue('dat_id');

    // save changed roles rights of the category
    if(isset($_POST['adm_event_participation_right'])) {
        $eventParticipationRoles = array_map('intval', $_POST['adm_event_participation_right']);
    } else {
        $eventParticipationRoles = array();
    }

    $rightEventParticipation = new RolesRights($gDb, 'event_participation', $datId);
    $rightEventParticipation->saveRoles($eventParticipationRoles);

    if ($returnCode === true && $gSettingsManager->getBool('system_notifications_new_entries')) {
        // Notification email for new entries

        if (strlen($_POST['dat_location']) > 0) {
            $location = $_POST['dat_location'];
        } else {
            $location = 'n/a';
        }

        if ($_POST['dat_room_id'] == 0) {
            $room = 'n/a';
        }

        if (strlen($_POST['dat_max_members']) > 0) {
            $participants = $_POST['dat_max_members'];
        } else {
            $participants = 'n/a';
        }

        try {
            $notification = new Email();

            if ($dateIsNew) {
                $messageTitleText = 'SYS_EMAIL_CREATE_EVENT_NOTIFICATION_TITLE';
                $messageUserText = 'SYS_CREATED_BY';
                $messageDateText = 'SYS_CREATED_AT';
            } else {
                $messageTitleText = 'SYS_EMAIL_CHANGE_EVENT_NOTIFICATION_TITLE';
                $messageUserText = 'SYS_CHANGED_BY';
                $messageDateText = 'SYS_CHANGED_AT';
            }
            $message = $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))) . '<br /><br />'
                . $gL10n->get('SYS_TITLE') . ': ' . $_POST['dat_headline'] . '<br />'
                . $gL10n->get('SYS_DATE') . ': ' . $date->getDateTimePeriod() . '<br />'
                . $gL10n->get('DAT_CALENDAR') . ': ' . $calendar->getValue('cat_name') . '<br />'
                . $gL10n->get('DAT_LOCATION') . ': ' . $location . '<br />'
                . $gL10n->get('SYS_ROOM') . ': ' . $room . '<br />'
                . $gL10n->get('SYS_PARTICIPANTS') . ': ' . $participants . '<br />'
                . $gL10n->get($messageUserText) . ': ' . $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME') . '<br />'
                . $gL10n->get($messageDateText) . ': ' . date($gSettingsManager->getString('system_date')) . '<br />';
            $notification->sendNotification(
                $gL10n->get($messageTitleText, array($gCurrentOrganization->getValue('org_longname'))),
                $message
            );
        } catch (AdmException $e) {
            $e->showHtml();
        }
    }

    // ----------------------------------------------
    // if necessary write away role for participation
    // ----------------------------------------------

    try {
        if($_POST['date_participation_possible'] == 1) {
            if ($date->getValue('dat_rol_id') > 0) {
                // if event exists, and you could register to this event then we must check
                // if the data of the role must be changed
                $role = new TableRoles($gDb, (int)$date->getValue('dat_rol_id'));

                $role->setValue('rol_name', $date->getDateTimePeriod(false) . ' ' . $date->getValue('dat_headline'));
                $role->setValue('rol_description', substr($date->getValue('dat_description'), 0, 3999));
                // role members are allowed to view lists
                $role->setValue('rol_view_memberships', isset($_POST['date_right_list_view']) ? TableRoles::VIEW_ROLE_MEMBERS : TableRoles::VIEW_NOBODY);
                // role members are allowed to send mail to this role
                $role->setValue('rol_mail_this_role', isset($_POST['date_right_send_mail']) ? TableRoles::VIEW_ROLE_MEMBERS : TableRoles::VIEW_NOBODY);
                $role->setValue('rol_max_members', (int)$date->getValue('dat_max_members'));

                $role->save();
            } else {
                // create role for participation
                if ($getCopy) {
                    // copy original role with their settings
                    $sql = 'SELECT dat_rol_id
                      FROM ' . TBL_DATES . '
                     WHERE dat_uuid = ?';
                    $pdoStatement = $gDb->queryPrepared($sql, array($originalDateUuid));

                    $role = new TableRoles($gDb, (int)$pdoStatement->fetchColumn());
                    $role->setValue('rol_id', '0');
                } else {
                    // Read category for event participation
                    $sql = 'SELECT cat_id
                      FROM ' . TBL_CATEGORIES . '
                     WHERE cat_name_intern = \'EVENTS\'
                       AND cat_org_id = ?';
                    $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
                    $role = new TableRoles($gDb);
                    $role->setType(TableRoles::ROLE_EVENT);

                    // these are the default settings for a date role
                    $role->setValue('rol_cat_id', (int)$pdoStatement->fetchColumn());
                    // role members are allowed to view lists
                    $role->setValue('rol_view_memberships', isset($_POST['date_right_list_view']) ? 1 : 3);
                    // role members are allowed to send mail to this role
                    $role->setValue('rol_mail_this_role', isset($_POST['date_right_send_mail']) ? 1 : 0);
                    $role->setValue('rol_leader_rights', ROLE_LEADER_MEMBERS_ASSIGN);    // leaders are allowed to add or remove participations
                    $role->setValue('rol_max_members', (int)$_POST['dat_max_members']);
                }

                $role->setValue('rol_name', $date->getDateTimePeriod(false) . ' ' . $date->getValue('dat_headline', 'database'));
                $role->setValue('rol_description', substr($date->getValue('dat_description', 'database'), 0, 3999));

                $role->save();

                // match dat_rol_id (reference between event and role)
                $date->setValue('dat_rol_id', (int)$role->getValue('rol_id'));
                $date->save();
            }

            // check if flag is set that current user wants to participate as leader to the date
            if (isset($_POST['date_current_user_assigned']) && $_POST['date_current_user_assigned'] == 1
                && !$gCurrentUser->isLeaderOfRole((int)$date->getValue('dat_rol_id'))) {
                // user wants to participate -> add him to date and set approval state to 2 ( user attend )
                $member = new TableMembers($gDb);
                $member->startMembership((int)$role->getValue('rol_id'), $user->getValue('usr_id'), true, 2);
            } elseif (!isset($_POST['date_current_user_assigned'])
                && $gCurrentUser->isMemberOfRole((int)$date->getValue('dat_rol_id'))) {
                // user doesn't want to participate as leader -> remove his participation as leader from the event,
                // don't remove the participation itself!
                $member = new TableMembers($gDb);
                $member->readDataByColumns(array('mem_rol_id' => (int)$role->getValue('rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
                $member->setValue('mem_leader', 0);
                $member->save();
            }
        } else {
            if($date->getValue('dat_rol_id') > 0) {
                // date participation was deselected -> delete flag in event and than delete role
                $role = new TableRoles($gDb, (int)$date->getValue('dat_rol_id'));
                $date->setValue('dat_rol_id', '');
                $date->save();
                $role->delete();
            }
        }
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $gDb->endTransaction();

    unset($_SESSION['dates_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
// => EXIT
} elseif ($getMode === 2) {
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showText();
        // => EXIT
    }

    // delete current announcements, right checks were done before
    $date->delete();

    // Delete successful -> Return for XMLHttpRequest
    echo 'done';
} elseif ($getMode === 6) {  // export event in ical format
    $filename = FileSystemUtils::getSanitizedPathEntry($date->getValue('dat_headline')) . '.ics';

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="'. $filename. '"');

    // necessary for IE, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');

    echo $date->getIcal(DOMAIN);
    exit();
}
// If participation mode: Set status and write optional parameter from user and show current status message
if (in_array($getMode, array(3, 4, 7), true)) {
    try {
        if ($postAdditionalGuests > 0 || $postUserComment !== '') {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
        }
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    $member = new TableMembers($gDb);
    $participants = new Participants($gDb, (int) $date->getValue('dat_rol_id'));

    // if current user is allowed to participate or user could edit this event then update user inputs
    if ($date->possibleToParticipate() || $participants->isLeader($gCurrentUserId)) {
        $member->readDataByColumns(array('mem_rol_id' => (int) $date->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
        $member->setValue('mem_comment', $postUserComment); // Comments will be saved in any case. Maybe it is a documentation afterwards by a leader or admin

        if ($member->isNewRecord()) {
            $member->setValue('mem_begin', DATE_NOW);
        }

        // Now check participants limit and save guests if possible
        if ($date->getValue('dat_max_members') > 0) {
            $totalMembers = $participants->getCount();

            if ($totalMembers + ($postAdditionalGuests - (int) $member->getValue('mem_count_guests')) < $date->getValue('dat_max_members')) {
                $member->setValue('mem_count_guests', $postAdditionalGuests);
            } else {
                $participationPossible = false;
            }

            $outputMessage = $gL10n->get('SYS_ROLE_MAX_MEMBERS', array($date->getValue('dat_headline')));

            if ($date->getValue('dat_max_members') === $totalMembers && !$participants->isMemberOfEvent($user->getValue('usr_id'))) {
                $participationPossible = false; // Participation Limit exceeded and user refused
            }

            if ($date->getValue('dat_max_members') > 0) {
                $outputMessage .= '<br />' . $gL10n->get('SYS_MAX_PARTICIPANTS') . ':&nbsp;' . (int) $date->getValue('dat_max_members');
            }
        } else {
            $member->setValue('mem_count_guests', $postAdditionalGuests);
        }

        $member->save();

        // change the participation status, it's always possible to cancel the participation
        if ($participationPossible || $getMode === 4) {
            switch ($getMode) {
                case 3:  // User attends to the event
                    $member->startMembership((int) $date->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_YES);
                    $outputMessage = $gL10n->get('DAT_ATTEND_DATE', array($date->getValue('dat_headline'), $date->getValue('dat_begin')));
                    // => EXIT
                    break;

                case 4:  // User cancel the event
                    if ($gSettingsManager->getBool('dates_save_all_confirmations')) {
                        // Set user status to refused
                        $member->startMembership((int) $date->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_NO);
                    } else {
                        // Delete entry
                        $member->deleteMembership((int) $date->getValue('dat_rol_id'), $user->getValue('usr_id'));
                    }

                    $outputMessage = $gL10n->get('DAT_CANCEL_DATE', array($date->getValue('dat_headline'), $date->getValue('dat_begin')));
                    // => EXIT
                    break;

                case 7:  // User may participate in the event
                    $member->startMembership((int) $date->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_MAYBE);
                    $outputMessage = $gL10n->get('DAT_ATTEND_POSSIBLY', array($date->getValue('dat_headline'), $date->getValue('dat_begin')));
                    // => EXIT
                    break;
            }
        }
    } else {
        $outputMessage = $gL10n->get('DAT_PARTICIPATE_NO_RIGHTS');
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($outputMessage, $gL10n->get('SYS_PARTICIPATE'));
}
