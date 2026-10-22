<?php
namespace Admidio\Events\Service;

use Admidio\Events\ValueObject\EventOccurrence;
use Admidio\Events\ValueObject\EventRecurrenceRule;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Generates concrete event occurrences from a recurrence rule.
 *
 * Recurrences without an explicit end are capped at two years and 500 occurrences
 * to prevent unbounded generation.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventOccurrenceGenerator
{
    public const MAX_NEVER_YEARS = 2;
    public const MAX_NEVER_OCCURRENCES = 500;
    private const MAX_OCCURRENCES_SAFETY_LIMIT = 10000;

    /**
     * @return array<int,EventOccurrence>
     */
    public function generate(
        DateTimeImmutable $begin,
        DateTimeImmutable $end,
        EventRecurrenceRule $rule,
        DateTimeZone|string|null $timezone = null,
        bool $allDay = false
    ): array {
        if ($end <= $begin) {
            throw new InvalidArgumentException('The event occurrence end must be after the begin.');
        }

        $timezone = $this->createTimezone($timezone);
        if ($timezone !== null) {
            $begin = $begin->setTimezone($timezone);
            $end = $end->setTimezone($timezone);
        }

        $duration = $begin->diff($end);

        return match ($rule->getFrequency()) {
            EventRecurrenceRule::FREQUENCY_DAILY => $this->generateDaily($begin, $duration, $rule, $allDay),
            EventRecurrenceRule::FREQUENCY_WEEKLY => $this->generateWeekly($begin, $duration, $rule, $allDay),
            EventRecurrenceRule::FREQUENCY_MONTHLY => $this->generateMonthly($begin, $duration, $rule, $allDay),
            EventRecurrenceRule::FREQUENCY_YEARLY => $this->generateYearly($begin, $duration, $rule, $allDay),
        };
    }

    /**
     * @return array<int,EventOccurrence>
     */
    private function generateDaily(DateTimeImmutable $begin, DateInterval $duration, EventRecurrenceRule $rule, bool $allDay): array
    {
        $occurrences = array();
        $candidate = $begin;

        while ($this->canContinue($occurrences, $candidate, $begin, $rule)) {
            $this->appendOccurrence($occurrences, $candidate, $duration, $allDay);
            $candidate = $candidate->modify('+' . $rule->getInterval() . ' days');
        }

        return $occurrences;
    }

    /**
     * @return array<int,EventOccurrence>
     */
    private function generateWeekly(DateTimeImmutable $begin, DateInterval $duration, EventRecurrenceRule $rule, bool $allDay): array
    {
        $occurrences = array();
        $weekdays = $rule->getByDay();

        if (count($weekdays) === 0) {
            $weekdays = array($this->weekdayFromDate($begin));
        }

        usort($weekdays, array($this, 'compareWeekdays'));

        $weekStart = $begin->modify('monday this week')->setTime(0, 0);

        while ($this->canContinue($occurrences, $weekStart, $begin, $rule)) {
            foreach ($weekdays as $weekday) {
                $candidate = $this->moveToWeekday($weekStart, $weekday, $begin);

                if ($candidate < $begin) {
                    continue;
                }
                if (!$this->canContinue($occurrences, $candidate, $begin, $rule)) {
                    break 2;
                }

                $this->appendOccurrence($occurrences, $candidate, $duration, $allDay);
            }

            $weekStart = $weekStart->modify('+' . $rule->getInterval() . ' weeks');
        }

        return $occurrences;
    }

    /**
     * @return array<int,EventOccurrence>
     */
    private function generateMonthly(DateTimeImmutable $begin, DateInterval $duration, EventRecurrenceRule $rule, bool $allDay): array
    {
        $occurrences = array();
        $monthOffset = 0;
        $monthDay = $rule->getByMonthDay() ?? (int)$begin->format('j');

        do {
            $candidate = $this->createMonthCandidate($begin, $monthOffset, $monthDay);

            if ($candidate >= $begin && $this->canContinue($occurrences, $candidate, $begin, $rule)) {
                $this->appendOccurrence($occurrences, $candidate, $duration, $allDay);
            }

            $monthOffset += $rule->getInterval();
        } while ($this->canContinue($occurrences, $this->createMonthCandidate($begin, $monthOffset, $monthDay), $begin, $rule));

        return $occurrences;
    }

    /**
     * @return array<int,EventOccurrence>
     */
    private function generateYearly(DateTimeImmutable $begin, DateInterval $duration, EventRecurrenceRule $rule, bool $allDay): array
    {
        $occurrences = array();
        $yearOffset = 0;

        do {
            $candidate = $this->createYearCandidate($begin, $yearOffset);

            if ($candidate >= $begin && $this->canContinue($occurrences, $candidate, $begin, $rule)) {
                $this->appendOccurrence($occurrences, $candidate, $duration, $allDay);
            }

            $yearOffset += $rule->getInterval();
        } while ($this->canContinue($occurrences, $this->createYearCandidate($begin, $yearOffset), $begin, $rule));

        return $occurrences;
    }

    /**
     * @param array<int,EventOccurrence> $occurrences
     */
    private function appendOccurrence(array &$occurrences, DateTimeImmutable $begin, DateInterval $duration, bool $allDay): void
    {
        $occurrences[] = new EventOccurrence($begin, $begin->add($duration), $allDay);
    }

    /**
     * @param array<int,EventOccurrence> $occurrences
     */
    private function canContinue(array $occurrences, DateTimeImmutable $candidate, DateTimeImmutable $seriesBegin, EventRecurrenceRule $rule): bool
    {
        if (count($occurrences) >= self::MAX_OCCURRENCES_SAFETY_LIMIT) {
            return false;
        }

        if ($rule->getEndType() === EventRecurrenceRule::END_TYPE_COUNT) {
            return count($occurrences) < $rule->getCount();
        }

        if ($rule->getEndType() === EventRecurrenceRule::END_TYPE_UNTIL) {
            return $candidate <= $rule->getUntil();
        }

        if (count($occurrences) >= self::MAX_NEVER_OCCURRENCES) {
            return false;
        }

        return $candidate <= $seriesBegin->modify('+' . self::MAX_NEVER_YEARS . ' years');
    }

    private function createTimezone(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        if ($timezone instanceof DateTimeZone || $timezone === null) {
            return $timezone;
        }

        return new DateTimeZone($timezone);
    }

    private function weekdayFromDate(DateTimeImmutable $date): string
    {
        return EventRecurrenceRule::WEEKDAYS[((int)$date->format('N')) - 1];
    }

    private function compareWeekdays(string $left, string $right): int
    {
        return array_search($left, EventRecurrenceRule::WEEKDAYS, true) <=> array_search($right, EventRecurrenceRule::WEEKDAYS, true);
    }

    private function moveToWeekday(DateTimeImmutable $weekStart, string $weekday, DateTimeImmutable $timeSource): DateTimeImmutable
    {
        $dayOffset = array_search($weekday, EventRecurrenceRule::WEEKDAYS, true);
        $candidate = $weekStart->modify('+' . $dayOffset . ' days');

        return $candidate->setTime(
            (int)$timeSource->format('H'),
            (int)$timeSource->format('i'),
            (int)$timeSource->format('s'),
            (int)$timeSource->format('u')
        );
    }

    private function createMonthCandidate(DateTimeImmutable $source, int $monthOffset, int $monthDay): DateTimeImmutable
    {
        $sourceMonth = ((int)$source->format('Y') * 12) + ((int)$source->format('n')) - 1 + $monthOffset;
        $year = intdiv($sourceMonth, 12);
        $month = ($sourceMonth % 12) + 1;
        $day = min($monthDay, $this->getLastDayOfMonth($year, $month, $source->getTimezone()));

        return $this->createDateWithSourceTime($source, $year, $month, $day);
    }

    private function createYearCandidate(DateTimeImmutable $source, int $yearOffset): DateTimeImmutable
    {
        $year = (int)$source->format('Y') + $yearOffset;
        $month = (int)$source->format('n');
        $day = min((int)$source->format('j'), $this->getLastDayOfMonth($year, $month, $source->getTimezone()));

        return $this->createDateWithSourceTime($source, $year, $month, $day);
    }

    private function getLastDayOfMonth(int $year, int $month, DateTimeZone $timezone): int
    {
        return (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $timezone))->format('t');
    }

    private function createDateWithSourceTime(DateTimeImmutable $source, int $year, int $month, int $day): DateTimeImmutable
    {
        $candidate = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %s', $year, $month, $day, $source->format('H:i:s.u')),
            $source->getTimezone()
        );

        return $candidate;
    }
}
