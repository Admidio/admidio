<?php
namespace Admidio\Events\Repository;

use Admidio\Events\Entity\EventRecurrence;
use Admidio\Events\ValueObject\EventRecurrenceRule;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Repository for event recurrence rules.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventRecurrenceRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Save a new recurrence rule for a master event.
     * @throws Exception
     */
    public function save(
        int $masterEventId,
        EventRecurrenceRule $rule,
        ?string $timezone = null,
        ?DateTimeImmutable $generatedUntil = null
    ): EventRecurrence {
        if ($masterEventId <= 0) {
            throw new Exception('A recurrence rule can only be saved for a valid master event.');
        }

        $recurrence = new EventRecurrence($this->database);
        $recurrence->setValue('rer_dat_id_master', $masterEventId);
        $this->writeRuleToEntity($recurrence, $rule, $timezone, $generatedUntil);
        $recurrence->save();

        return $recurrence;
    }

    /**
     * Load a recurrence rule by its technical id.
     * @throws Exception
     */
    public function readById(int $recurrenceId): ?EventRecurrence
    {
        $recurrence = new EventRecurrence($this->database);

        if (!$recurrence->readDataById($recurrenceId)) {
            return null;
        }

        return $recurrence;
    }

    /**
     * Load a recurrence rule by its master event id.
     * @throws Exception
     */
    public function readByMasterEventId(int $masterEventId): ?EventRecurrence
    {
        $recurrence = new EventRecurrence($this->database);

        if (!$recurrence->readDataByMasterEventId($masterEventId)) {
            return null;
        }

        return $recurrence;
    }

    /**
     * Update an existing recurrence rule.
     * @throws Exception
     */
    public function update(
        int $recurrenceId,
        EventRecurrenceRule $rule,
        ?string $timezone = null,
        ?DateTimeImmutable $generatedUntil = null
    ): EventRecurrence {
        $recurrence = $this->readById($recurrenceId);

        if ($recurrence === null) {
            throw new Exception('No recurrence rule with the given id was found in the database.');
        }

        $this->writeRuleToEntity($recurrence, $rule, $timezone, $generatedUntil);
        $recurrence->save();

        return $recurrence;
    }

    /**
     * Delete a recurrence rule by its technical id.
     * @throws Exception
     */
    public function delete(int $recurrenceId): bool
    {
        $recurrence = new EventRecurrence($this->database);

        if (!$recurrence->readDataById($recurrenceId)) {
            return false;
        }

        return $recurrence->delete();
    }

    /**
     * Delete a recurrence rule by its master event id.
     * @throws Exception
     */
    public function deleteByMasterEventId(int $masterEventId): bool
    {
        $recurrence = new EventRecurrence($this->database);

        if (!$recurrence->readDataByMasterEventId($masterEventId)) {
            return false;
        }

        return $recurrence->delete();
    }

    /**
     * Convert a stored recurrence entity into a domain rule.
     * @throws \Exception
     */
    public function toRule(EventRecurrence $recurrence): EventRecurrenceRule
    {
        $until = null;
        if ((string)$recurrence->getValue('rer_until', 'database') !== '') {
            $timezone = null;
            if ((string)$recurrence->getValue('rer_timezone', 'database') !== '') {
                $timezone = new DateTimeZone($recurrence->getValue('rer_timezone', 'database'));
            }
            $until = new DateTimeImmutable($recurrence->getValue('rer_until', 'Y-m-d H:i:s'), $timezone);
        }

        $byDay = array();
        if ((string)$recurrence->getValue('rer_byday', 'database') !== '') {
            $byDay = explode(',', $recurrence->getValue('rer_byday', 'database'));
        }

        $byMonthDay = null;
        if ((string)$recurrence->getValue('rer_bymonthday', 'database') !== '') {
            $byMonthDay = (int)$recurrence->getValue('rer_bymonthday');
        }

        $count = null;
        if ((string)$recurrence->getValue('rer_count', 'database') !== '') {
            $count = (int)$recurrence->getValue('rer_count');
        }

        return new EventRecurrenceRule(
            $recurrence->getValue('rer_frequency', 'database'),
            (int)$recurrence->getValue('rer_interval'),
            $byDay,
            $byMonthDay,
            $recurrence->getValue('rer_monthly_mode', 'database') ?: null,
            $recurrence->getValue('rer_end_type', 'database'),
            $until,
            $count
        );
    }

    /**
     * Write a domain rule into a recurrence entity without saving it.
     * @throws Exception
     */
    public function writeRuleToEntity(
        EventRecurrence $recurrence,
        EventRecurrenceRule $rule,
        ?string $timezone = null,
        ?DateTimeImmutable $generatedUntil = null
    ): void {
        $recurrence->setValue('rer_frequency', $rule->getFrequency());
        $recurrence->setValue('rer_interval', $rule->getInterval());
        $recurrence->setValue('rer_byday', count($rule->getByDay()) > 0 ? implode(',', $rule->getByDay()) : null);
        $recurrence->setValue('rer_bymonthday', $rule->getByMonthDay());
        $recurrence->setValue('rer_monthly_mode', $rule->getMonthlyMode());
        $recurrence->setValue('rer_end_type', $rule->getEndType());
        $recurrence->setValue('rer_until', $rule->getUntil()?->format('Y-m-d H:i:s'));
        $recurrence->setValue('rer_count', $rule->getCount());
        $recurrence->setValue('rer_timezone', $timezone);
        $recurrence->setValue('rer_rrule', $rule->toRRule());
        $recurrence->setValue('rer_generated_until', $generatedUntil?->format('Y-m-d H:i:s'));
    }
}
