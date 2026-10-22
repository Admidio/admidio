<?php
namespace Admidio\Events\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Value object that describes the recurrence rule of an event.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventRecurrenceRule
{
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_YEARLY = 'yearly';

    public const END_TYPE_NEVER = 'never';
    public const END_TYPE_UNTIL = 'until';
    public const END_TYPE_COUNT = 'count';

    public const WEEKDAYS = array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU');

    /**
     * @param array<int,string> $byDay Weekdays using iCalendar abbreviations: MO, TU, WE, TH, FR, SA, SU
     */
    public function __construct(
        private readonly string $frequency,
        private readonly int $interval = 1,
        private readonly array $byDay = array(),
        private readonly ?int $byMonthDay = null,
        private readonly ?string $monthlyMode = null,
        private readonly string $endType = self::END_TYPE_NEVER,
        private readonly ?DateTimeImmutable $until = null,
        private readonly ?int $count = null
    ) {
        $this->validate();
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * @return array<int,string>
     */
    public function getByDay(): array
    {
        return $this->byDay;
    }

    public function getByMonthDay(): ?int
    {
        return $this->byMonthDay;
    }

    public function getMonthlyMode(): ?string
    {
        return $this->monthlyMode;
    }

    public function getEndType(): string
    {
        return $this->endType;
    }

    public function getUntil(): ?DateTimeImmutable
    {
        return $this->until;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function toRRule(): string
    {
        $parts = array(
            'FREQ=' . strtoupper($this->frequency),
            'INTERVAL=' . $this->interval
        );

        if ($this->frequency === self::FREQUENCY_WEEKLY && count($this->byDay) > 0) {
            $parts[] = 'BYDAY=' . implode(',', $this->byDay);
        }

        if ($this->frequency === self::FREQUENCY_MONTHLY && $this->byMonthDay !== null) {
            $parts[] = 'BYMONTHDAY=' . $this->byMonthDay;
        }

        if ($this->endType === self::END_TYPE_UNTIL && $this->until !== null) {
            $parts[] = 'UNTIL=' . $this->until->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        } elseif ($this->endType === self::END_TYPE_COUNT && $this->count !== null) {
            $parts[] = 'COUNT=' . $this->count;
        }

        return implode(';', $parts);
    }

    private function validate(): void
    {
        if (!in_array($this->frequency, array(self::FREQUENCY_DAILY, self::FREQUENCY_WEEKLY, self::FREQUENCY_MONTHLY, self::FREQUENCY_YEARLY), true)) {
            throw new InvalidArgumentException('Invalid event recurrence frequency.');
        }

        if ($this->interval < 1) {
            throw new InvalidArgumentException('The event recurrence interval must be greater than or equal to 1.');
        }

        foreach ($this->byDay as $weekday) {
            if (!in_array($weekday, self::WEEKDAYS, true)) {
                throw new InvalidArgumentException('Invalid event recurrence weekday.');
            }
        }

        if ($this->byMonthDay !== null && ($this->byMonthDay < 1 || $this->byMonthDay > 31)) {
            throw new InvalidArgumentException('Invalid event recurrence month day.');
        }

        if (!in_array($this->endType, array(self::END_TYPE_NEVER, self::END_TYPE_UNTIL, self::END_TYPE_COUNT), true)) {
            throw new InvalidArgumentException('Invalid event recurrence end type.');
        }

        if ($this->endType === self::END_TYPE_UNTIL && $this->until === null) {
            throw new InvalidArgumentException('The event recurrence end date is required.');
        }

        if ($this->endType === self::END_TYPE_COUNT && ($this->count === null || $this->count < 1)) {
            throw new InvalidArgumentException('The event recurrence count must be greater than or equal to 1.');
        }
    }
}
