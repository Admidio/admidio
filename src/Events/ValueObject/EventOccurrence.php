<?php
namespace Admidio\Events\ValueObject;

use DateTimeImmutable;

/**
 * Value object for one concrete occurrence of a recurring event.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventOccurrence
{
    public function __construct(
        private readonly DateTimeImmutable $begin,
        private readonly DateTimeImmutable $end,
        private readonly bool $allDay = false
    ) {
    }

    public function getBegin(): DateTimeImmutable
    {
        return $this->begin;
    }

    public function getEnd(): DateTimeImmutable
    {
        return $this->end;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }
}
