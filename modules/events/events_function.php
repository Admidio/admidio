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
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Events\Service\EventOccurrenceGenerator;
use Admidio\Events\ValueObject\EventOccurrence;
use Admidio\Events\ValueObject\Participants;
use Admidio\Events\ValueObject\EventRecurrenceRule;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Users\Entity\User;

/**
 * Create a recurrence rule from the event form values.
 * @param array<string,mixed> $formValues
 * @throws Exception
 */
function createEventRecurrenceRuleFromFormValues(array $formValues, DateTime $startDateTime): ?EventRecurrenceRule
{
    $frequency = (string)($formValues['event_recurrence_frequency'] ?? 'none');

    if ($frequency === 'none') {
        return null;
    }

    if (!in_array($frequency, array(
        EventRecurrenceRule::FREQUENCY_DAILY,
        EventRecurrenceRule::FREQUENCY_WEEKLY,
        EventRecurrenceRule::FREQUENCY_MONTHLY,
        EventRecurrenceRule::FREQUENCY_YEARLY
    ), true)) {
        throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_REPEAT'));
    }

    $interval = (int)($formValues['event_recurrence_interval'] ?? 1);
    if ($interval < 1) {
        throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_RECURRENCE_INTERVAL'));
    }

    $byDay = array();
    if ($frequency === EventRecurrenceRule::FREQUENCY_WEEKLY) {
        $byDay = $formValues['event_recurrence_weekdays'] ?? array();
        if (!is_array($byDay)) {
            $byDay = array($byDay);
        }
        $byDay = array_values(array_filter($byDay));

        if (count($byDay) === 0) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_WEEKDAY'));
        }
    }

    $endType = (string)($formValues['event_recurrence_end_type'] ?? EventRecurrenceRule::END_TYPE_NEVER);
    if (!in_array($endType, array(
        EventRecurrenceRule::END_TYPE_NEVER,
        EventRecurrenceRule::END_TYPE_COUNT,
        EventRecurrenceRule::END_TYPE_UNTIL
    ), true)) {
        throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_RECURRENCE_END'));
    }

    $until = null;
    $count = null;

    if ($endType === EventRecurrenceRule::END_TYPE_COUNT) {
        $count = (int)($formValues['event_recurrence_count'] ?? 0);
        if ($count < 1) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_RECURRENCE_COUNT'));
        }
        if ($count > EventOccurrenceGenerator::MAX_NEVER_OCCURRENCES) {
            throw new Exception('SYS_RECURRENCE_TOO_MANY_OCCURRENCES', array(EventOccurrenceGenerator::MAX_NEVER_OCCURRENCES));
        }
    } elseif ($endType === EventRecurrenceRule::END_TYPE_UNTIL) {
        $untilDate = (string)($formValues['event_recurrence_until'] ?? '');
        $until = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $untilDate . ' 23:59:59');
        if (!$until) {
            throw new Exception('SYS_DATE_INVALID', array('SYS_RECURRENCE_UNTIL', 'YYYY-MM-DD'));
        }

        if ($until < DateTimeImmutable::createFromMutable($startDateTime)->setTime(0, 0)) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }
    }

    try {
        return new EventRecurrenceRule($frequency, $interval, $byDay, null, null, $endType, $until, $count);
    } catch (InvalidArgumentException $exception) {
        throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_REPEAT'));
    }
}

/**
 * Find room reservations that overlap with the given event period.
 * @return array<int,array<string,mixed>>
 */
function findEventRoomConflicts(
    DateTimeInterface $begin,
    DateTimeInterface $end,
    int $roomId,
    string $ignoredEventUuid = '',
    ?int $ignoredRecurrenceId = null
): array
{
    global $gDb;

    if ($roomId <= 0) {
        return array();
    }

    $sql = 'SELECT dat_begin, dat_end, dat_headline
              FROM ' . TBL_EVENTS . '
             WHERE dat_begin  <= ?
               AND dat_end    >= ?
               AND dat_room_id = ?
               AND dat_uuid    <> ?
               AND (dat_recurrence_status IS NULL OR dat_recurrence_status <> ?)';
    $queryParams = array(
        $end->format('Y-m-d H:i:s'),
        $begin->format('Y-m-d H:i:s'),
        $roomId,
        $ignoredEventUuid,
        'cancelled'
    );

    if ($ignoredRecurrenceId !== null) {
        $sql .= ' AND (dat_rer_id IS NULL
                    OR dat_rer_id <> ?
                    OR dat_recurrence_status IS NULL
                    OR dat_recurrence_status NOT IN (?, ?))';
        $queryParams[] = $ignoredRecurrenceId;
        $queryParams[] = 'master';
        $queryParams[] = 'generated';
    }

    $eventsStatement = $gDb->queryPrepared($sql, $queryParams);

    return $eventsStatement->fetchAll();
}

/**
 * Check if a room is already reserved for the given event period.
 * @throws Exception
 */
function checkEventRoomAvailable(DateTimeInterface $begin, DateTimeInterface $end, int $roomId, string $ignoredEventUuid = '', ?int $ignoredRecurrenceId = null): void
{
    if (count(findEventRoomConflicts($begin, $end, $roomId, $ignoredEventUuid, $ignoredRecurrenceId)) > 0) {
        throw new Exception('SYS_ROOM_RESERVED');
    }
}

/**
 * Check a complete recurrence before materializing it. Own recurrence instances can be ignored for future updates.
 * @param array<int,EventOccurrence> $occurrences
 * @throws Exception
 */
function checkEventRoomOccurrencesAvailable(array $occurrences, int $roomId, string $ignoredEventUuid = '', ?int $ignoredRecurrenceId = null): void
{
    if ($roomId <= 0) {
        return;
    }

    $conflictPeriods = array();

    foreach ($occurrences as $occurrence) {
        if (count(findEventRoomConflicts($occurrence->getBegin(), $occurrence->getEnd(), $roomId, $ignoredEventUuid, $ignoredRecurrenceId)) > 0) {
            $conflictPeriods[] = formatEventRoomConflictPeriod($occurrence->getBegin(), $occurrence->getEnd());
        }
    }

    $occurrenceCount = count($occurrences);
    for ($i = 0; $i < $occurrenceCount; ++$i) {
        for ($j = $i + 1; $j < $occurrenceCount; ++$j) {
            if ($occurrences[$i]->getBegin() <= $occurrences[$j]->getEnd()
                && $occurrences[$i]->getEnd() >= $occurrences[$j]->getBegin()) {
                $conflictPeriods[] = formatEventRoomConflictPeriod($occurrences[$j]->getBegin(), $occurrences[$j]->getEnd());
            }
        }
    }

    $conflictPeriods = array_values(array_unique($conflictPeriods));

    if (count($conflictPeriods) > 0) {
        $shownConflictPeriods = array_slice($conflictPeriods, 0, 10);
        if (count($conflictPeriods) > count($shownConflictPeriods)) {
            $shownConflictPeriods[] = '...';
        }

        throw new Exception('SYS_RECURRENCE_ROOM_RESERVED', array(implode(', ', $shownConflictPeriods)));
    }
}

/**
 * Format one occurrence for recurrence room conflict messages.
 */
function formatEventRoomConflictPeriod(DateTimeInterface $begin, DateTimeInterface $end): string
{
    global $gSettingsManager;

    $dateTimeFormat = $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time');

    return $begin->format($dateTimeFormat) . ' - ' . $end->format($dateTimeFormat);
}

/**
 * Save participation rights and optionally the dedicated event role.
 * @param array<string,mixed> $formValues
 * @param array<int,int> $eventParticipationRoles
 * @throws Exception
 */
function saveEventParticipation(
    Event $event,
    array $formValues,
    array $eventParticipationRoles,
    User $user,
    bool $copyParticipationRole = false,
    string $originalEventUuid = ''
): void {
    global $gCurrentOrgId, $gCurrentUser, $gDb;

    $rightEventParticipation = new RolesRights($gDb, 'event_participation', (int)$event->getValue('dat_id'));
    $rightEventParticipation->saveRoles($eventParticipationRoles);

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
            if ($copyParticipationRole && $originalEventUuid !== '') {
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
                if (!$row = $pdoStatement->fetch()) {
                    throw new Exception('No category found for event participation!');
                }
                $role = new Role($gDb);
                $role->setType(Role::ROLE_EVENT);

                // these are the default settings for an event role
                $role->setValue('rol_cat_id', (int)$row['cat_id']);
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
            // event participation was deselected -> delete flag in event and then delete role
            $role = new Role($gDb, (int)$event->getValue('dat_rol_id'));
            $event->setValue('dat_rol_id', '');
            $event->save();
            $role->delete();
        }
    }
}

/**
 * Fill a generated event from the submitted master event values.
 * @param array<string,mixed> $formValues
 * @throws Exception
 */
function setGeneratedEventValues(Event $event, array $formValues, EventOccurrence $occurrence, int $recurrenceId, ?int $deadlineOffsetSeconds): void
{
    foreach ($formValues as $key => $value) {
        if (str_starts_with($key, 'dat_') && !in_array($key, array('dat_id', 'dat_uuid', 'dat_begin', 'dat_end', 'dat_deadline', 'dat_rol_id'), true)) {
            $event->setValue($key, $value);
        }
    }

    $event->setValue('dat_begin', $occurrence->getBegin()->format('Y-m-d H:i'));
    $event->setValue('dat_end', $occurrence->getEnd()->format('Y-m-d H:i'));
    $event->setValue('dat_deadline', $deadlineOffsetSeconds === null ? null : $occurrence->getBegin()->modify('-' . $deadlineOffsetSeconds . ' seconds')->format('Y-m-d H:i'));
    $event->setValue('dat_rer_id', $recurrenceId);
    $event->setValue('dat_recurrence_original_begin', $occurrence->getBegin()->format('Y-m-d H:i:s'));
    $event->setValue('dat_recurrence_status', 'generated');
}

/**
 * Fill the master event from the submitted series values.
 * @param array<string,mixed> $formValues
 * @throws Exception
 */
function setMasterEventValues(Event $event, array $formValues, DateTimeInterface $begin, DateTimeInterface $end, int $recurrenceId): void
{
    foreach ($formValues as $key => $value) {
        if (str_starts_with($key, 'dat_') && !in_array($key, array('dat_id', 'dat_uuid', 'dat_begin', 'dat_end', 'dat_rol_id'), true)) {
            $event->setValue($key, $value);
        }
    }

    $event->setValue('dat_begin', $begin->format('Y-m-d H:i'));
    $event->setValue('dat_end', $end->format('Y-m-d H:i'));
    $event->setValue('dat_rer_id', $recurrenceId);
    $event->setValue('dat_recurrence_original_begin', $begin->format('Y-m-d H:i:s'));
    $event->setValue('dat_recurrence_status', 'master');
    $event->setValue('dat_recurrence_scope', 'series');
}

/**
 * Read future manually handled occurrences of a recurrence.
 * @return array<string,string>
 * @throws Exception
 */
function getFutureManualRecurrenceStatuses(int $recurrenceId, string $seriesUpdateFrom): array
{
    global $gDb;

    $manualStatuses = array();
    $sql = 'SELECT dat_recurrence_original_begin, dat_recurrence_status
              FROM ' . TBL_EVENTS . '
             WHERE dat_rer_id = ?
               AND dat_begin >= ?
               AND dat_recurrence_status IN (?, ?)';
    $eventStatement = $gDb->queryPrepared($sql, array($recurrenceId, $seriesUpdateFrom, 'modified', 'cancelled'));

    while ($row = $eventStatement->fetch()) {
        $manualStatuses[(string)$row['dat_recurrence_original_begin']] = (string)$row['dat_recurrence_status'];
    }

    return $manualStatuses;
}

/**
 * Load a generated future occurrence that may be updated by a series edit.
 * @throws Exception
 */
function readFutureGeneratedOccurrence(int $recurrenceId, string $originalBegin, string $seriesUpdateFrom): ?Event
{
    global $gDb;

    $sql = 'SELECT dat_id
              FROM ' . TBL_EVENTS . '
             WHERE dat_rer_id = ?
               AND dat_recurrence_original_begin = ?
               AND dat_begin >= ?
               AND dat_recurrence_status = ?';
    $eventStatement = $gDb->queryPrepared($sql, array($recurrenceId, $originalBegin, $seriesUpdateFrom, 'generated'));
    $eventId = (int)$eventStatement->fetchColumn();

    if ($eventId === 0) {
        return null;
    }

    return new Event($gDb, $eventId);
}

/**
 * Update a complete recurrence with the submitted master data.
 * Future modified and cancelled instances are preserved and stale generated instances are cancelled.
 * @param array<string,mixed> $formValues
 * @param array<int,EventOccurrence> $occurrences
 * @param array<int,int> $eventParticipationRoles
 * @throws Exception
 */
function updateFutureUnmodifiedEventSeries(
    Event $editedEvent,
    array $formValues,
    EventRecurrenceRule $recurrenceRule,
    array $occurrences,
    string $eventTimezone,
    ?int $deadlineOffsetSeconds,
    DateTimeImmutable $recurrenceBeginDateTime,
    DateTimeImmutable $recurrenceEndDateTime,
    array $eventParticipationRoles,
    User $user
): void {
    global $gCurrentUserId, $gDb;

    $recurrenceId = getEventRecurrenceId($editedEvent);
    if ($recurrenceId <= 0) {
        throw new Exception('SYS_RECURRENCE_NOT_FOUND');
    }

    $recurrenceRepository = new EventRecurrenceRepository($gDb);
    $recurrence = $recurrenceRepository->readById($recurrenceId);
    if ($recurrence === null) {
        throw new Exception('SYS_RECURRENCE_NOT_FOUND');
    }

    $masterEventId = (int)$recurrence->getValue('rer_dat_id_master');
    if ($masterEventId <= 0) {
        throw new Exception('SYS_RECURRENCE_NOT_FOUND');
    }

    $masterEvent = $masterEventId === (int)$editedEvent->getValue('dat_id') ? $editedEvent : new Event($gDb, $masterEventId);
    if (!$masterEvent->isEditable()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $seriesUpdateFrom = DATE_NOW . ' 00:00:00';
    $manualStatuses = getFutureManualRecurrenceStatuses($recurrenceId, $seriesUpdateFrom);
    $processedOriginalBegins = array();
    $generatedUntil = null;

    $gDb->startTransaction();

    setMasterEventValues($masterEvent, $formValues, $recurrenceBeginDateTime, $recurrenceEndDateTime, $recurrenceId);
    if ($masterEvent->save()) {
        $masterEvent->sendNotification();
    }
    saveEventParticipation($masterEvent, $formValues, $eventParticipationRoles, $user);

    foreach ($occurrences as $occurrence) {
        $originalBegin = $occurrence->getBegin()->format('Y-m-d H:i:s');
        $generatedUntil = $occurrence->getBegin();

        if ($originalBegin === $recurrenceBeginDateTime->format('Y-m-d H:i:s')) {
            $processedOriginalBegins[] = $originalBegin;
            continue;
        }

        if ($originalBegin < $seriesUpdateFrom) {
            continue;
        }

        if (isset($manualStatuses[$originalBegin])) {
            $processedOriginalBegins[] = $originalBegin;
            continue;
        }

        $generatedEvent = readFutureGeneratedOccurrence($recurrenceId, $originalBegin, $seriesUpdateFrom);
        if ($generatedEvent === null) {
            $generatedEvent = new Event($gDb);
        }

        setGeneratedEventValues($generatedEvent, $formValues, $occurrence, $recurrenceId, $deadlineOffsetSeconds);
        $generatedEvent->setValue('dat_recurrence_scope', 'series');
        $generatedEvent->save();

        saveEventParticipation($generatedEvent, $formValues, $eventParticipationRoles, $user);
        $processedOriginalBegins[] = $originalBegin;
    }

    $cancelParams = array(
        'cancelled',
        'series',
        $gCurrentUserId,
        DATETIME_NOW,
        $recurrenceId,
        $seriesUpdateFrom,
        'generated'
    );
    $processedCondition = '';
    if (count($processedOriginalBegins) > 0) {
        $processedCondition = '
               AND dat_recurrence_original_begin NOT IN (' . Database::getQmForValues($processedOriginalBegins) . ')';
        $cancelParams = array_merge($cancelParams, $processedOriginalBegins);
    }

    $sql = 'UPDATE ' . TBL_EVENTS . '
               SET dat_recurrence_status = ?
                 , dat_recurrence_scope  = ?
                 , dat_usr_id_change     = ?
                 , dat_timestamp_change  = ?
             WHERE dat_rer_id = ?
               AND dat_begin >= ?
               AND dat_recurrence_status = ?' . $processedCondition;
    $gDb->queryPrepared($sql, $cancelParams);

    $recurrenceRepository->update($recurrenceId, $recurrenceRule, $eventTimezone, $generatedUntil);

    $gDb->endTransaction();
}

/**
 * Check if the event is part of a recurrence and can be cancelled without deleting its data.
 * @throws Exception
 */
function isRecurringEvent(Event $event): bool
{
    $recurrenceStatus = (string)$event->getValue('dat_recurrence_status', 'database');

    return (int)$event->getValue('dat_rer_id') > 0
        || in_array($recurrenceStatus, array('master', 'generated', 'modified'), true);
}

/**
 * Return the recurrence id of an event, also if the event is the master and has no dat_rer_id yet.
 * @throws Exception
 */
function getEventRecurrenceId(Event $event): int
{
    global $gDb;

    $recurrenceId = (int)$event->getValue('dat_rer_id');
    if ($recurrenceId > 0) {
        return $recurrenceId;
    }

    $recurrenceRepository = new EventRecurrenceRepository($gDb);
    $recurrence = $recurrenceRepository->readByMasterEventId((int)$event->getValue('dat_id'));

    return $recurrence === null ? 0 : (int)$recurrence->getValue('rer_id');
}

/**
 * Cancel all future visible occurrences of a recurrence.
 * @throws Exception
 */
function cancelFutureUnmodifiedEventSeries(Event $event): void
{
    global $gCurrentUserId, $gDb;

    $recurrenceId = getEventRecurrenceId($event);
    if ($recurrenceId <= 0) {
        throw new Exception('SYS_RECURRENCE_NOT_FOUND');
    }

    $recurrence = new \Admidio\Events\Entity\EventRecurrence($gDb, $recurrenceId);
    $masterEventId = (int)$recurrence->getValue('rer_dat_id_master');
    if ($masterEventId > 0 && $masterEventId !== (int)$event->getValue('dat_id')) {
        $masterEvent = new Event($gDb, $masterEventId);
        if (!$masterEvent->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    $seriesCancelFrom = DATE_NOW . ' 00:00:00';

    $gDb->startTransaction();

    $sql = 'UPDATE ' . TBL_EVENTS . '
               SET dat_recurrence_status = ?
                 , dat_recurrence_scope  = ?
                 , dat_usr_id_change     = ?
                 , dat_timestamp_change  = ?
             WHERE (dat_rer_id = ? OR dat_id = ?)
               AND dat_begin >= ?
               AND dat_recurrence_status IN (?, ?, ?)';
    $gDb->queryPrepared($sql, array(
        'cancelled',
        'series',
        $gCurrentUserId,
        DATETIME_NOW,
        $recurrenceId,
        $masterEventId,
        $seriesCancelFrom,
        'master',
        'generated',
        'modified'
    ));

    // There is no dedicated active flag yet. Limit the rule to the current generation horizon
    // so later series management can treat the recurrence as stopped without deleting history.
    $recurrence->setValue('rer_generated_until', DATETIME_NOW);
    $recurrence->save();

    $gDb->endTransaction();
}

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'participate', 'participate_cancel', 'participate_maybe', 'export')));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', $gValidLogin ? array('defaultValue' => $gCurrentUser->getValue('usr_uuid')) : []);
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
    $getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date');
    $getRecurrenceScope = admFuncVariableIsValid($_GET, 'recurrence_scope', 'string', array('defaultValue' => 'this', 'validValues' => array('this', 'series')));

    $participationPossible = true;
    $originalEventUuid = '';

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
        $getEventUuid = '';

        $originalEvent = new Event($gDb);
        if ($originalEventUuid === '' || !$originalEvent->readDataByUuid($originalEventUuid) || !$originalEvent->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    // create event object
    $event = new Event($gDb);
    if ($getEventUuid !== '' && !$event->readDataByUuid($getEventUuid)) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if ($getMode === 'delete' && $getEventUuid === '') {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // read user data
    $user = new User($gDb, $gProfileFields);
    if (in_array($getMode, array('participate', 'participate_cancel', 'participate_maybe'), true)) {
        $user->readDataByUuid($getUserUuid);
    } elseif ($gValidLogin) {
        // Event edit forms use "current user assigned" semantics. Do not allow a crafted user_uuid
        // parameter to assign or unassign another user while saving events or recurrence instances.
        $user->readDataByUuid($gCurrentUser->getValue('usr_uuid'));
    }

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

        $isExistingRecurringEvent = $getEventUuid !== '' && isRecurringEvent($event);
        $isSeriesUpdate = $isExistingRecurringEvent && $getRecurrenceScope === 'series';
        $recurrenceRule = $isExistingRecurringEvent && !$isSeriesUpdate ? null : createEventRecurrenceRuleFromFormValues($formValues, $startDateTime);
        $recurrenceOccurrences = array();
        $eventTimezone = $gTimezone;
        $deadlineOffsetSeconds = null;
        $recurrenceOriginalBegin = (string)$event->getValue('dat_recurrence_original_begin', 'database');
        if ($recurrenceOriginalBegin === '') {
            $recurrenceOriginalBegin = (string)$event->getValue('dat_begin', 'Y-m-d H:i:s');
        }

        if ($isSeriesUpdate && $recurrenceRule === null) {
            throw new Exception('SYS_RECURRENCE_EDIT_SERIES_NOT_SUPPORTED');
        }

        if ($recurrenceRule !== null) {
            $recurrenceBeginDateTime = DateTimeImmutable::createFromMutable($startDateTime);
            $recurrenceEndDateTime = DateTimeImmutable::createFromMutable($endDateTime);

            if ($formValues['dat_all_day'] === '1') {
                $recurrenceEndDateTime = $recurrenceEndDateTime->setTime(23, 59, 59);
            }

            if ((string)$formValues['dat_deadline'] !== '') {
                $deadlineDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $formValues['dat_deadline']);
                if ($deadlineDateTime instanceof DateTimeImmutable) {
                    $deadlineOffsetSeconds = $recurrenceBeginDateTime->getTimestamp() - $deadlineDateTime->getTimestamp();
                }
            }

            $eventOccurrenceGenerator = new EventOccurrenceGenerator();
            $recurrenceOccurrences = $eventOccurrenceGenerator->generate(
                $recurrenceBeginDateTime,
                $recurrenceEndDateTime,
                $recurrenceRule,
                $eventTimezone,
                $formValues['dat_all_day'] === '1'
            );

            if (count($recurrenceOccurrences) > EventOccurrenceGenerator::MAX_NEVER_OCCURRENCES) {
                throw new Exception('SYS_RECURRENCE_TOO_MANY_OCCURRENCES', array(EventOccurrenceGenerator::MAX_NEVER_OCCURRENCES));
            }
        }

        // ------------------------------------------------
        // Check if the selected room is already reserved for the appointment
        // ------------------------------------------------

        $roomCheckEndDateTime = clone $endDateTime;
        if ($formValues['dat_all_day'] === '1') {
            $roomCheckEndDateTime->setTime(23, 59, 59);
        }

        if ($gSettingsManager->getBool('events_rooms_enabled')) {
            $eventRoomId = (int)$formValues['dat_room_id'];

            if ($eventRoomId > 0) {
                if ($recurrenceRule !== null) {
                    $ignoredRecurrenceId = $isSeriesUpdate ? getEventRecurrenceId($event) : null;
                    checkEventRoomOccurrencesAvailable($recurrenceOccurrences, $eventRoomId, $getEventUuid, $ignoredRecurrenceId);
                } else {
                    checkEventRoomAvailable($startDateTime, $roomCheckEndDateTime, $eventRoomId, $getEventUuid);
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

        // save changed roles rights of the category
        if (isset($formValues['adm_event_participation_right'])) {
            $eventParticipationRoles = array_map('intval', $formValues['adm_event_participation_right']);
        } else {
            $eventParticipationRoles = array();
        }

        if ($isSeriesUpdate) {
            updateFutureUnmodifiedEventSeries(
                $event,
                $formValues,
                $recurrenceRule,
                $recurrenceOccurrences,
                $eventTimezone,
                $deadlineOffsetSeconds,
                $recurrenceBeginDateTime,
                $recurrenceEndDateTime,
                $eventParticipationRoles,
                $user
            );

            $gNavigation->deleteLastUrl();

            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            exit();
        }

        // write form values into the event object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'dat_')) {
                $event->setValue($key, $value);
            }
        }

        if ($recurrenceRule !== null) {
            // The saved master event is the first materialized occurrence. Further generated events start after it.
            $event->setValue('dat_recurrence_original_begin', $formValues['dat_begin']);
            $event->setValue('dat_recurrence_status', 'master');
        } elseif ($isExistingRecurringEvent) {
            // Editing a recurring occurrence changes only this event row. The recurrence rule and original begin stay unchanged.
            $event->setValue('dat_recurrence_original_begin', $recurrenceOriginalBegin);
            $event->setValue('dat_recurrence_status', 'modified');
            $event->setValue('dat_recurrence_scope', 'this');
        }

        $gDb->startTransaction();
        if ($event->save()) {
            // Notification an email for new or changed entries to all members of the notification role
            $event->sendNotification();
        }

        saveEventParticipation($event, $formValues, $eventParticipationRoles, $user, $getCopy, (string)$originalEventUuid);

        if ($recurrenceRule !== null) {
            $generatedUntil = null;
            if (count($recurrenceOccurrences) > 0) {
                $generatedUntil = $recurrenceOccurrences[array_key_last($recurrenceOccurrences)]->getBegin();
            }

            $recurrenceRepository = new EventRecurrenceRepository($gDb);
            $recurrence = $recurrenceRepository->save((int)$event->getValue('dat_id'), $recurrenceRule, $eventTimezone, $generatedUntil);
            $recurrenceId = (int)$recurrence->getValue('rer_id');

            $event->setValue('dat_rer_id', $recurrenceId);
            $event->save();

            foreach ($recurrenceOccurrences as $occurrence) {
                if ($occurrence->getBegin()->format('Y-m-d H:i:s') === $recurrenceBeginDateTime->format('Y-m-d H:i:s')) {
                    continue;
                }

                $generatedEvent = new Event($gDb);
                setGeneratedEventValues($generatedEvent, $formValues, $occurrence, $recurrenceId, $deadlineOffsetSeconds);
                $generatedEvent->save();

                saveEventParticipation($generatedEvent, $formValues, $eventParticipationRoles, $user);
            }
        }

        $gDb->endTransaction();
        $gNavigation->deleteLastUrl();

        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        if (isRecurringEvent($event)) {
            if ($getRecurrenceScope === 'series') {
                cancelFutureUnmodifiedEventSeries($event);
            } else {
                // Recurring occurrences are cancelled, not physically deleted. The participation role stays untouched
                // so existing participation data remains available for a later series management workflow.
                $event->setValue('dat_recurrence_status', 'cancelled');
                $event->setValue('dat_recurrence_scope', 'this');
                $event->save();
            }
        } else {
            // delete current announcements, right checks were done before
            $event->delete();
        }

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
                $filename .= '-' . $calendar->getValue('cat_name');
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
