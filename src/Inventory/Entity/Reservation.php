<?php

namespace Admidio\Inventory\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

/**
 * A time-bound request or confirmed reservation for an inventory item.
 *
 * This deliberately has a lifecycle separate from ItemBorrowData: borrowing describes the
 * current physical handover, while reservations can represent multiple future periods.
 */
class Reservation extends Entity
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(Database $database, int $reservationId = 0)
    {
        parent::__construct($database, TBL_INVENTORY_RESERVATIONS, 'ivr', $reservationId);
    }

    public function getHookId(): ?string
    {
        return 'inventory_reservations';
    }

    public function getIgnoredLogColumns(): array
    {
        return array_merge(
            array_diff(parent::getIgnoredLogColumns(), array('ivr_usr_id', 'ivr_timestamp_create')),
            array('ivr_id', 'ivr_ini_id', 'ivr_dat_id')
        );
    }

    /** Always persist the system request time, including for guest requests. */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->isNewRecord() && (string)$this->getValue('ivr_timestamp_create') === '') {
            $this->setValue('ivr_timestamp_create', DATETIME_NOW);
        }

        return parent::save($updateFingerPrint);
    }

    /** Name the history entry after its item instead of its numeric record id. */
    public function readableName(): string
    {
        $item = new Item($this->db, null, (int)$this->getValue('ivr_ini_id'));

        return $this->filterReadableName($item->readableName());
    }
}
