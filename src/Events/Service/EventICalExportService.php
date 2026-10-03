<?php
namespace Admidio\Events\Service;

use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\EventRecurrence;
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event as ICalEvent;
use Eluceo\iCal\Domain\Entity\TimeZone as ICalTimeZone;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTime as ICalDateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Presentation\Component;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

/**
 * Creates an iCalendar document from event database records.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class EventICalExportService
{
    public function __construct(
        private readonly Database $database,
        private readonly string $timezone
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $eventRecords
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function createCalendar(array $eventRecords): Component
    {
        $iCalEvents = array();
        $iCalEventUids = array();
        $iCalEventRecurrenceProperties = array();
        $exportedRecurrenceIds = array();
        $iCalMinDateTime = '';
        $iCalMaxDateTime = '';
        $timeZone = new DateTimeZone($this->timezone);
        $recurrences = $this->readRecurrences($eventRecords);
        $cancelledRecurrenceOriginalBegins = $this->readCancelledRecurrenceOriginalBegins(array_keys($recurrences));

        foreach ($eventRecords as $eventRecord) {
            $recurrenceId = (int)($eventRecord['dat_evr_id'] ?? 0);
            $recurrenceStatus = (string)($eventRecord['dat_recurrence_status'] ?? '');

            if ($recurrenceId > 0 && isset($recurrences[$recurrenceId]) && !isset($exportedRecurrenceIds[$recurrenceId])) {
                $masterRecord = $recurrences[$recurrenceId]['masterRecord'];
                $masterUid = (string)$masterRecord['dat_uuid'];
                $masterEvent = $this->createEvent($masterRecord, $masterUid);
                $iCalEventKey = $masterUid . '|';

                if ($masterUid !== '' && !isset($iCalEventUids[$iCalEventKey])) {
                    $iCalEventUids[$iCalEventKey] = true;
                    $this->updateDateRange($masterRecord, $iCalMinDateTime, $iCalMaxDateTime);
                    $iCalEventRecurrenceProperties[spl_object_hash($masterEvent)] = array(
                        'rrule' => $recurrences[$recurrenceId]['rrule'],
                        'exdates' => $cancelledRecurrenceOriginalBegins[$recurrenceId] ?? array()
                    );
                    $iCalEvents[] = $masterEvent;
                }

                $exportedRecurrenceIds[$recurrenceId] = true;
            }

            if ($recurrenceId > 0 && $recurrenceStatus === 'generated') {
                continue;
            }

            $iCalUid = (string)$eventRecord['dat_uuid'];
            $recurrenceProperties = array();

            $recurrenceOriginalBegin = (string)($eventRecord['dat_recurrence_original_begin'] ?? '');
            if ($recurrenceId > 0 && $recurrenceStatus === 'modified' && isset($recurrences[$recurrenceId]) && $recurrenceOriginalBegin !== '') {
                $iCalUid = (string)$recurrences[$recurrenceId]['masterRecord']['dat_uuid'];
                $recurrenceProperties['recurrenceId'] = $this->createRecurrenceDateValue(
                    $recurrenceOriginalBegin,
                    (bool)$eventRecord['dat_all_day']
                );
            }

            $iCalEventKey = $iCalUid . '|' . ($recurrenceProperties['recurrenceId']['dateTime'] ?? $recurrenceProperties['recurrenceId']['date'] ?? '');
            if ($iCalUid === '' || isset($iCalEventUids[$iCalEventKey])) {
                continue;
            }
            $iCalEventUids[$iCalEventKey] = true;

            $iCalEvent = $this->createEvent($eventRecord, $iCalUid);
            $this->updateDateRange($eventRecord, $iCalMinDateTime, $iCalMaxDateTime);

            if (count($recurrenceProperties) > 0) {
                $iCalEventRecurrenceProperties[spl_object_hash($iCalEvent)] = $recurrenceProperties;
            }

            $iCalEvents[] = $iCalEvent;
        }

        $calendar = new Calendar($iCalEvents);
        if (count($iCalEvents) > 0) {
            $calendar->addTimeZone(ICalTimeZone::createFromPhpDateTimeZone(
                $timeZone,
                new DateTimeImmutable($iCalMinDateTime, $timeZone),
                new DateTimeImmutable($iCalMaxDateTime, $timeZone)
            ));
        }

        $componentFactory = new CalendarFactory(new EventRecurrenceICalEventFactory($iCalEventRecurrenceProperties));
        return $componentFactory->createCalendar($calendar);
    }

    /**
     * @param array<string,mixed> $eventRecord
     * @throws DateMalformedStringException
     */
    private function createEvent(array $eventRecord, string $iCalUid): ICalEvent
    {
        $event = new Event($this->database);
        $event->setArray($eventRecord);

        $iCalEvent = new ICalEvent(new UniqueIdentifier($iCalUid));
        $iCalEvent->setSummary($eventRecord['dat_headline']);
        $iCalEvent->setDescription((string)$eventRecord['dat_description']);
        $iCalEvent->setLocation(new Location((string)$eventRecord['dat_location']));

        if ((string)$eventRecord['dat_timestamp_change'] === '') {
            $iCalEvent->touch(new Timestamp(new DateTimeImmutable($event->getValue('dat_timestamp_create', 'Y-m-d H:i:s'))));
        } else {
            $iCalEvent->touch(new Timestamp(new DateTimeImmutable($event->getValue('dat_timestamp_change', 'Y-m-d H:i:s'))));
        }

        if ((bool)$eventRecord['dat_all_day']) {
            if ($event->getValue('dat_begin', 'Y-m-d') === $event->getValue('dat_end', 'Y-m-d')) {
                $iCalEvent->setOccurrence(new SingleDay(
                    new Date(new DateTimeImmutable($event->getValue('dat_begin', 'Y-m-d')))
                ));
            } else {
                $iCalEvent->setOccurrence(new MultiDay(
                    new Date(new DateTimeImmutable($event->getValue('dat_begin', 'Y-m-d'))),
                    new Date(new DateTimeImmutable($event->getValue('dat_end', 'Y-m-d')))
                ));
            }
        } else {
            $iCalEvent->setOccurrence(new TimeSpan(
                new ICalDateTime(new DateTimeImmutable($event->getValue('dat_begin', 'Y-m-d H:i:s')), false),
                new ICalDateTime(new DateTimeImmutable($event->getValue('dat_end', 'Y-m-d H:i:s')), false)
            ));
        }

        return $iCalEvent;
    }

    /**
     * @param array<string,mixed> $eventRecord
     */
    private function updateDateRange(array $eventRecord, string &$iCalMinDateTime, string &$iCalMaxDateTime): void
    {
        $eventBegin = (string)$eventRecord['dat_begin'];
        $eventEnd = (string)$eventRecord['dat_end'];

        if ($iCalMinDateTime === '' || $eventBegin < $iCalMinDateTime) {
            $iCalMinDateTime = $eventBegin;
        }

        if ($iCalMaxDateTime === '' || $eventEnd > $iCalMaxDateTime) {
            $iCalMaxDateTime = $eventEnd;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $eventRecords
     * @return array<int,array<string,mixed>>
     * @throws Exception
     */
    private function readRecurrences(array $eventRecords): array
    {
        $recurrenceIds = array();
        foreach ($eventRecords as $eventRecord) {
            $recurrenceId = (int)($eventRecord['dat_evr_id'] ?? 0);
            if ($recurrenceId > 0) {
                $recurrenceIds[$recurrenceId] = $recurrenceId;
            }
        }

        if (count($recurrenceIds) === 0) {
            return array();
        }

        $sql = 'SELECT dat.*, evr.*
                  FROM ' . TBL_EVENT_RECURRENCES . ' AS evr
            INNER JOIN ' . TBL_EVENTS . ' AS dat
                    ON dat.dat_id = evr.evr_dat_id_master
                 WHERE evr.evr_id IN (' . Database::getQmForValues($recurrenceIds) . ')';
        $statement = $this->database->queryPrepared($sql, array_values($recurrenceIds));

        $recurrenceRepository = new EventRecurrenceRepository($this->database);
        $recurrences = array();
        while ($row = $statement->fetch()) {
            $recurrence = new EventRecurrence($this->database);
            $recurrence->setArray($row);
            $recurrences[(int)$row['evr_id']] = array(
                'masterRecord' => $row,
                'rrule' => $recurrenceRepository->toRule($recurrence)->toRRule()
            );
        }

        return $recurrences;
    }

    /**
     * @param array<int,int> $recurrenceIds
     * @return array<int,array<int,array<string,mixed>>>
     * @throws Exception
     */
    private function readCancelledRecurrenceOriginalBegins(array $recurrenceIds): array
    {
        if (count($recurrenceIds) === 0) {
            return array();
        }

        $sql = 'SELECT dat_evr_id, dat_recurrence_original_begin, dat_all_day
                  FROM ' . TBL_EVENTS . '
                 WHERE dat_evr_id IN (' . Database::getQmForValues($recurrenceIds) . ')
                   AND dat_recurrence_status = ?
                   AND dat_recurrence_original_begin IS NOT NULL
                   AND dat_recurrence_original_begin <> ?';
        $statement = $this->database->queryPrepared($sql, array_merge(array_values($recurrenceIds), array('cancelled', '')));

        $cancelledOriginalBegins = array();
        while ($row = $statement->fetch()) {
            $recurrenceId = (int)$row['dat_evr_id'];
            $cancelledOriginalBegins[$recurrenceId][] = $this->createRecurrenceDateValue(
                (string)$row['dat_recurrence_original_begin'],
                (bool)$row['dat_all_day']
            );
        }

        return $cancelledOriginalBegins;
    }

    /**
     * @return array<string,mixed>
     * @throws DateMalformedStringException
     */
    private function createRecurrenceDateValue(string $dateTime, bool $allDay): array
    {
        $recurrenceDate = new DateTimeImmutable($dateTime);

        return array(
            'allDay' => $allDay,
            'timezone' => '',
            'date' => $recurrenceDate->format('Ymd'),
            'dateTime' => $recurrenceDate->format('Ymd\THis')
        );
    }
}
