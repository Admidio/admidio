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
        $recurrence->setValue('evr_dat_id_master', $masterEventId);
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
        if ((string)$recurrence->getValue('evr_until', 'database') !== '') {
            $timezone = null;
            if ((string)$recurrence->getValue('evr_timezone', 'database') !== '') {
                $timezone = new DateTimeZone($recurrence->getValue('evr_timezone', 'database'));
            }
            $until = new DateTimeImmutable($recurrence->getValue('evr_until', 'Y-m-d H:i:s'), $timezone);
        }

        $byDay = array();
        if ((string)$recurrence->getValue('evr_byday', 'database') !== '') {
            $byDay = explode(',', $recurrence->getValue('evr_byday', 'database'));
        }

        $byMonthDay = null;
        if ((string)$recurrence->getValue('evr_bymonthday', 'database') !== '') {
            $byMonthDay = (int)$recurrence->getValue('evr_bymonthday');
        }

        $count = null;
        if ((string)$recurrence->getValue('evr_count', 'database') !== '') {
            $count = (int)$recurrence->getValue('evr_count');
        }

        return new EventRecurrenceRule(
            $recurrence->getValue('evr_frequency', 'database'),
            (int)$recurrence->getValue('evr_interval'),
            $byDay,
            $byMonthDay,
            $recurrence->getValue('evr_monthly_mode', 'database') ?: null,
            $recurrence->getValue('evr_end_type', 'database'),
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
        $recurrence->setValue('evr_frequency', $rule->getFrequency());
        $recurrence->setValue('evr_interval', $rule->getInterval());
        $recurrence->setValue('evr_byday', count($rule->getByDay()) > 0 ? implode(',', $rule->getByDay()) : null);
        $recurrence->setValue('evr_bymonthday', $rule->getByMonthDay());
        $recurrence->setValue('evr_monthly_mode', $rule->getMonthlyMode());
        $recurrence->setValue('evr_end_type', $rule->getEndType());
        $recurrence->setValue('evr_until', $rule->getUntil()?->format('Y-m-d H:i:s'));
        $recurrence->setValue('evr_count', $rule->getCount());
        $recurrence->setValue('evr_timezone', $timezone);
        $recurrence->setValue('evr_generated_until', $generatedUntil?->format('Y-m-d H:i:s'));
    }
}
