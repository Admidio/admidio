<?php
namespace Admidio\Events\Service;

use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Parameter;
use Eluceo\iCal\Presentation\Component\Property\Value;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Factory\EventFactory;
use Generator;

/**
 * Adds recurrence properties to the iCal VEVENT components created by eluceo/iCal.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventRecurrenceICalEventFactory extends EventFactory
{
    /**
     * @param array<string,array<string,mixed>> $recurrencePropertiesByEventHash
     */
    public function __construct(private readonly array $recurrencePropertiesByEventHash)
    {
        parent::__construct();
    }

    /**
     * @return Generator<Property>
     */
    protected function getProperties(Event $event): Generator
    {
        yield from parent::getProperties($event);

        $recurrenceProperties = $this->recurrencePropertiesByEventHash[spl_object_hash($event)] ?? array();

        if (isset($recurrenceProperties['rrule']) && (string)$recurrenceProperties['rrule'] !== '') {
            yield new Property('RRULE', new ICalRawValue((string)$recurrenceProperties['rrule']));
        }

        if (isset($recurrenceProperties['recurrenceId'])) {
            yield $this->createRecurrenceDateProperty('RECURRENCE-ID', $recurrenceProperties['recurrenceId']);
        }

        if (isset($recurrenceProperties['exdates']) && count($recurrenceProperties['exdates']) > 0) {
            yield $this->createRecurrenceDateProperty('EXDATE', $recurrenceProperties['exdates']);
        }
    }

    /**
     * @param array<string,mixed>|array<int,array<string,mixed>> $dateValues
     */
    private function createRecurrenceDateProperty(string $name, array $dateValues): Property
    {
        $firstDateValue = array_is_list($dateValues) ? $dateValues[0] : $dateValues;
        $parameters = array();

        if (!empty($firstDateValue['allDay'])) {
            $parameters[] = new Parameter('VALUE', new TextValue('DATE'));
        } elseif (!empty($firstDateValue['timezone'])) {
            $parameters[] = new Parameter('TZID', new TextValue((string)$firstDateValue['timezone']));
        }

        if (array_is_list($dateValues)) {
            $value = implode(',', array_map(array($this, 'formatRecurrenceDateValue'), $dateValues));
        } else {
            $value = $this->formatRecurrenceDateValue($dateValues);
        }

        return new Property($name, new ICalRawValue($value), $parameters);
    }

    /**
     * @param array<string,mixed> $dateValue
     */
    private function formatRecurrenceDateValue(array $dateValue): string
    {
        if (!empty($dateValue['allDay'])) {
            return $dateValue['date'];
        }

        return $dateValue['dateTime'];
    }
}

/**
 * Raw iCalendar value for properties that must not be escaped as TEXT values.
 */
class ICalRawValue extends Value
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
