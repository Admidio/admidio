<?php
namespace Admidio\Events\Service;

use Admidio\Categories\Entity\Category;
use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\EventRecurrence;
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Events\ValueObject\Participants;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Membership;
use Admidio\Users\Entity\User;
use DateInterval;
use DateTime;

/**
 * Service for event actions that are triggered by the events module router.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventService
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Delete a single event or cancel recurring event instances according to the selected recurrence scope.
     * @throws Exception
     */
    public function deleteEvent(string $eventUUID, string $recurrenceScope = 'this'): void
    {
        if ($eventUUID === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        $event = new Event($this->database);
        if (!$event->readDataByUuid($eventUUID)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if (!$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if ($this->isRecurringEvent($event)) {
            if ($recurrenceScope === 'series') {
                $this->cancelFutureUnmodifiedEventSeries($event);
            } else {
                // Recurring occurrences are cancelled, not physically deleted. The participation role stays untouched
                // so existing participation data remains available for a later series management workflow.
                $event->setValue('dat_recurrence_status', 'cancelled');
                $event->setValue('dat_recurrence_scope', 'this');
                $event->save();
            }
        } else {
            $event->delete();
        }
    }

    /**
     * Change the participation status of a user for an event.
     * @return array<string,string>
     * @throws Exception
     */
    public function changeParticipation(string $eventUUID, string $mode, string $userUUID = ''): array
    {
        global $gCurrentSession, $gCurrentUser, $gCurrentUserId, $gL10n, $gNavigation, $gProfileFields, $gSettingsManager;

        if ($eventUUID === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $event = new Event($this->database);
        if (!$event->readDataByUuid($eventUUID)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $formValues = array();
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

        $user = new User($this->database, $gProfileFields);
        if ($userUUID === '') {
            $userUUID = $gCurrentUser->getValue('usr_uuid');
        }
        $user->readDataByUuid($userUUID);

        $member = new Membership($this->database);
        $participants = new Participants($this->database, (int)$event->getValue('dat_rol_id'));
        $participationPossible = true;
        $outputMessage = '';

        // if user is no leader of the event then only allow to handle their own participation
        if (!$participants->isLeader($gCurrentUserId)) {
            $user->readDataByUuid($gCurrentUser->getValue('usr_uuid'));
        }

        // if current user is allowed to participate or user could edit this event then update user inputs
        if ($event->possibleToParticipate() || $participants->isLeader($gCurrentUserId)) {
            $member->readDataByColumns(array('mem_rol_id' => (int)$event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
            $member->setValue('mem_comment', $formValues['dat_comment']);

            if ($member->isNewRecord()) {
                $member->setValue('mem_begin', DATE_NOW);
            }

            if ($event->getValue('dat_max_members') > 0) {
                $totalMembers = $participants->getCount();

                if ($totalMembers + ((int)$formValues['additional_guests'] - (int)$member->getValue('mem_count_guests')) < (int)$event->getValue('dat_max_members')) {
                    $member->setValue('mem_count_guests', $formValues['additional_guests']);
                } else {
                    $participationPossible = false;
                }

                $outputMessage = $gL10n->get('SYS_ROLE_MAX_MEMBERS', array($event->getValue('dat_headline')));

                if ($event->getValue('dat_max_members') === $totalMembers && !$participants->isMemberOfEvent($user->getValue('usr_id'))) {
                    $participationPossible = false;
                }

                if ($event->getValue('dat_max_members') > 0) {
                    $outputMessage .= '<br />' . $gL10n->get('SYS_MAX_PARTICIPANTS') . ':&nbsp;' . (int)$event->getValue('dat_max_members');
                }
            } else {
                $member->setValue('mem_count_guests', $formValues['additional_guests']);
            }

            $member->save();

            if ($participationPossible || $mode === 'participate_cancel') {
                switch ($mode) {
                    case 'participate':
                        $member->startMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_YES);
                        $outputMessage = $gL10n->get('SYS_ATTEND_EVENT', array($event->getValue('dat_headline'), $event->getValue('dat_begin')));
                        break;

                    case 'participate_cancel':
                        if ($gSettingsManager->getBool('events_save_cancellations')) {
                            $member->startMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_NO);
                        } else {
                            $member->deleteMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'));
                        }

                        $outputMessage = $gL10n->get('SYS_CANCEL_EVENT', array($event->getValue('dat_headline'), $event->getValue('dat_begin')));
                        break;

                    case 'participate_maybe':
                        $member->startMembership((int)$event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_MAYBE);
                        $outputMessage = $gL10n->get('SYS_ATTEND_POSSIBLY', array($event->getValue('dat_headline'), $event->getValue('dat_begin')));
                        break;
                }
            }
        } else {
            throw new Exception('SYS_PARTICIPATE_NO_RIGHTS');
        }

        return array(
            'status' => 'success',
            'message' => $outputMessage,
            'url' => $gNavigation->getUrl()
        );
    }

    /**
     * Save event form data.
     * @return array<string,string>
     * @throws Exception
     */
    public function saveEvent(string $eventUUID = '', bool $copy = false, string $recurrenceScope = 'this'): array
    {
        $eventSaveService = new EventSaveService($this->database);

        return $eventSaveService->saveEvent($eventUUID, $copy, $recurrenceScope);
    }

    /**
     * Export matching events as iCal and send the file to the browser.
     * @throws Exception
     */
    public function exportICal(string $eventUUID = '', string $categoryUUID = '', string $dateFrom = '', string $dateTo = ''): void
    {
        global $gCurrentOrganization, $gSettingsManager;

        if (!$gSettingsManager->getBool('events_ical_export_enabled')) {
            throw new Exception('SYS_ICAL_DISABLED');
        }

        if ($dateFrom === '') {
            $dateFrom = (new DateTime())->sub(new DateInterval('P6M'))->format('Y-m-d');
            $dateTo = DATE_MAX;
        }

        $events = new \ModuleEvents();
        if ($eventUUID !== '') {
            $event = new Event($this->database);
            $event->readDataByUuid($eventUUID);

            $filename = FileSystemUtils::getSanitizedPathEntry($event->getValue('dat_headline', 'database')) . '.ics';
            $events->setParameter('dat_uuid', $eventUUID);
        } else {
            $filename = FileSystemUtils::getSanitizedPathEntry($gCurrentOrganization->getValue('org_longname')) . '.ics';
            $events->setDateRange($dateFrom, $dateTo);

            if ($categoryUUID !== '') {
                $calendar = new Category($this->database);
                $events->setParameter('cat_uuid', $categoryUUID);
                $calendar->readDataByUuid($categoryUUID);
                $filename .= '-' . $calendar->getValue('cat_name');
            }
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        echo $events->getICalContent();
    }

    /**
     * Check if the event is part of a recurrence and can be cancelled without deleting its data.
     * @throws Exception
     */
    private function isRecurringEvent(Event $event): bool
    {
        $recurrenceStatus = (string)$event->getValue('dat_recurrence_status', 'database');

        return (int)$event->getValue('dat_rer_id') > 0
            || in_array($recurrenceStatus, array('master', 'generated', 'modified'), true);
    }

    /**
     * Return the recurrence id of an event, also if the event is the master and has no dat_rer_id yet.
     * @throws Exception
     */
    private function getEventRecurrenceId(Event $event): int
    {
        $recurrenceId = (int)$event->getValue('dat_rer_id');
        if ($recurrenceId > 0) {
            return $recurrenceId;
        }

        $recurrenceRepository = new EventRecurrenceRepository($this->database);
        $recurrence = $recurrenceRepository->readByMasterEventId((int)$event->getValue('dat_id'));

        return $recurrence === null ? 0 : (int)$recurrence->getValue('rer_id');
    }

    /**
     * Cancel all future visible occurrences of a recurrence.
     * @throws Exception
     */
    private function cancelFutureUnmodifiedEventSeries(Event $event): void
    {
        global $gCurrentUserId;

        $recurrenceId = $this->getEventRecurrenceId($event);
        if ($recurrenceId <= 0) {
            throw new Exception('SYS_RECURRENCE_NOT_FOUND');
        }

        $recurrence = new EventRecurrence($this->database, $recurrenceId);
        $masterEventId = (int)$recurrence->getValue('rer_dat_id_master');
        if ($masterEventId > 0 && $masterEventId !== (int)$event->getValue('dat_id')) {
            $masterEvent = new Event($this->database, $masterEventId);
            if (!$masterEvent->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        $seriesCancelFrom = DATE_NOW . ' 00:00:00';

        $this->database->startTransaction();

        $sql = 'UPDATE ' . TBL_EVENTS . '
                   SET dat_recurrence_status = ?
                     , dat_recurrence_scope  = ?
                     , dat_usr_id_change     = ?
                     , dat_timestamp_change  = ?
                 WHERE (dat_rer_id = ? OR dat_id = ?)
                   AND dat_begin >= ?
                   AND dat_recurrence_status IN (?, ?, ?)';
        $this->database->queryPrepared($sql, array(
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

        $this->database->endTransaction();
    }
}
