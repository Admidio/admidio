<?php

namespace Admidio\Inventory\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\Reservation;
use DateTimeInterface;

/**
 * Handles reservation lifecycle and availability checks for inventory items.
 */
class ReservationService
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /** Return the number of pending requests visible to reservation managers. */
    public static function countPendingReservations(Database $database): int
    {
        global $gCurrentOrgId;

        if (!InventoryAccessService::canManageReservations()) {
            return 0;
        }

        return (int)$database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . '
             INNER JOIN ' . TBL_INVENTORY_ITEMS . ' ON ini_id = ivr_ini_id
                   WHERE ini_org_id = ? AND ivr_status = ?',
            array($gCurrentOrgId, Reservation::STATUS_REQUESTED)
        )->fetchColumn();
    }

    /**
     * Creates a request only when no confirmed reservation overlaps the requested period.
     */
    public function request(
        int $itemId,
        DateTimeInterface $begin,
        DateTimeInterface $end,
        string $guestName = '',
        string $guestEmail = '',
        ?int $eventId = null,
        string $comment = ''
    ): Reservation {
        global $gCurrentUser, $gValidLogin, $gSettingsManager;

        if (!InventoryAccessService::canRequestReservation()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $this->assertValidPeriod($begin, $end);

        $item = new Item($this->database, null, $itemId);
        if (!$item->readDataById($itemId) || $item->isRetired()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if (!$this->isAvailable($itemId, $begin, $end)) {
            throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
        }
        $isAutomatic = $gSettingsManager->getString('inventory_reservation_approval') === 'automatic';

        if (!$gValidLogin && ($guestName === '' || $guestEmail === '')) {
            throw new Exception('SYS_FIELD_EMPTY');
        }

        $reservation = new Reservation($this->database);
        $reservation->setValue('ivr_ini_id', $itemId);
        $reservation->setValue('ivr_dat_id', $eventId);
        $reservation->setValue('ivr_usr_id', $gValidLogin ? (int)$gCurrentUser->getValue('usr_id') : null);
        $reservation->setValue('ivr_guest_name', $gValidLogin ? null : $guestName);
        $reservation->setValue('ivr_guest_email', $gValidLogin ? null : $guestEmail);
        $reservation->setValue('ivr_comment', $comment);
        $reservation->setValue('ivr_begin', $begin->format('Y-m-d H:i:s'));
        $reservation->setValue('ivr_end', $end->format('Y-m-d H:i:s'));
        $reservation->setValue('ivr_status', $isAutomatic ? Reservation::STATUS_APPROVED : Reservation::STATUS_REQUESTED);
        $reservation->save();
        $this->refreshMenuBadge();

        return $reservation;
    }

    /** Approve a pending request after an availability re-check. */
    public function approve(Reservation $reservation): void
    {
        if (!InventoryAccessService::canManageReservations()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if (!$this->isAvailable(
            (int)$reservation->getValue('ivr_ini_id'),
            new \DateTimeImmutable((string)$reservation->getValue('ivr_begin')),
            new \DateTimeImmutable((string)$reservation->getValue('ivr_end')),
            (int)$reservation->getValue('ivr_id')
        )) {
            throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
        }

        $reservation->setValue('ivr_status', Reservation::STATUS_APPROVED);
        $reservation->save();
        $this->refreshMenuBadge();
    }

    /** Reject or cancel a reservation from the administrator queue. */
    public function changeStatus(Reservation $reservation, string $status): void
    {
        if (!InventoryAccessService::canManageReservations()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if (!in_array($status, array(Reservation::STATUS_REJECTED, Reservation::STATUS_CANCELLED), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $reservation->setValue('ivr_status', $status);
        $reservation->save();
        $this->refreshMenuBadge();
    }

    /** Mark an approved reservation as physically handed over. */
    public function startBorrowing(Reservation $reservation): void
    {
        if (!InventoryAccessService::canManageReservations()
            || $reservation->getValue('ivr_status') !== Reservation::STATUS_APPROVED) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $reservation->setValue('ivr_status', Reservation::STATUS_BORROWED);
        $reservation->save();
    }

    /** Mark a handed-over reservation as returned. */
    public function finishBorrowing(Reservation $reservation): void
    {
        if (!InventoryAccessService::canManageReservations()
            || $reservation->getValue('ivr_status') !== Reservation::STATUS_BORROWED) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $reservation->setValue('ivr_status', Reservation::STATUS_RETURNED);
        $reservation->save();
    }

    /** Allow the signed-in requester to withdraw an open or approved reservation. */
    public function withdraw(Reservation $reservation): void
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin || (int)$reservation->getValue('ivr_usr_id') !== (int)$gCurrentUser->getValue('usr_id')) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if (!in_array($reservation->getValue('ivr_status'), array(Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $reservation->setValue('ivr_status', Reservation::STATUS_CANCELLED);
        $reservation->save();
        $this->refreshMenuBadge();
    }

    /**
     * Checks confirmed reservations and the existing active physical borrowing record.
     * The end is exclusive, so a return and a new reservation may meet at the same instant.
     */
    public function isAvailable(int $itemId, DateTimeInterface $begin, DateTimeInterface $end, int $ignoreReservationId = 0): bool
    {
        $this->assertValidPeriod($begin, $end);

        $activeBorrowing = (int)$this->database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . '
              WHERE inb_ini_id = ? AND inb_last_receiver IS NOT NULL AND inb_last_receiver <> \'\'
                AND inb_borrow_date IS NOT NULL AND inb_return_date IS NULL',
            array($itemId)
        )->fetchColumn();
        if ($activeBorrowing > 0) {
            return false;
        }

        $conflicts = (int)$this->database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . '
              WHERE ivr_ini_id = ? AND ivr_status = ? AND ivr_id <> ?
                AND ivr_begin < ? AND ivr_end > ?',
            array($itemId, Reservation::STATUS_APPROVED, $ignoreReservationId, $end->format('Y-m-d H:i:s'), $begin->format('Y-m-d H:i:s'))
        )->fetchColumn();

        return $conflicts === 0;
    }

    /**
     * Replace the reservation requests owned by one event.
     *
     * The event form uses the same approval workflow as the inventory request form: with
     * manual approval, the reservations remain requested until an inventory administrator
     * approves them. Call this inside the event save transaction.
     *
     * @param array<int,mixed> $itemIds
     */
    public function syncEventReservations(int $eventId, array $itemIds, DateTimeInterface $begin, DateTimeInterface $end): void
    {
        global $gCurrentUser, $gSettingsManager, $gValidLogin;

        $this->assertValidPeriod($begin, $end);
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static function (int $itemId): bool {
            return $itemId > 0;
        })));
        $isAutomatic = $gSettingsManager->getString('inventory_reservation_approval') === 'automatic';

        foreach ($itemIds as $itemId) {
            if ($itemId <= 0) {
                throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
            }

            $item = new Item($this->database, null, $itemId);
            if (!$item->readDataById($itemId) || $item->isRetired()) {
                throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
            }

            if (!$this->isAvailableForEvent($itemId, $begin, $end, $eventId)) {
                throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
            }
        }

        $existingReservations = array();
        $statement = $this->database->queryPrepared(
            'SELECT ivr_id, ivr_ini_id, ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . '
              WHERE ivr_dat_id = ? AND ivr_status IN (?, ?, ?)',
            array($eventId, Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED, Reservation::STATUS_BORROWED)
        );
        while ($existingReservation = $statement->fetch()) {
            $existingReservations[(int)$existingReservation['ivr_ini_id']] = array(
                'id' => (int)$existingReservation['ivr_id'],
                'status' => (string)$existingReservation['ivr_status']
            );
        }

        foreach ($itemIds as $itemId) {
            if (isset($existingReservations[$itemId])) {
                $reservation = new Reservation($this->database, $existingReservations[$itemId]['id']);
                $reservation->setValue('ivr_begin', $begin->format('Y-m-d H:i:s'));
                $reservation->setValue('ivr_end', $end->format('Y-m-d H:i:s'));
                $reservation->save();
                unset($existingReservations[$itemId]);
                continue;
            }

            $reservation = new Reservation($this->database);
            $reservation->setValue('ivr_ini_id', $itemId);
            $reservation->setValue('ivr_dat_id', $eventId);
            $reservation->setValue('ivr_usr_id', $gValidLogin ? (int)$gCurrentUser->getValue('usr_id') : null);
            $reservation->setValue('ivr_begin', $begin->format('Y-m-d H:i:s'));
            $reservation->setValue('ivr_end', $end->format('Y-m-d H:i:s'));
            $reservation->setValue('ivr_status', $isAutomatic ? Reservation::STATUS_APPROVED : Reservation::STATUS_REQUESTED);
            $reservation->save();
        }

        foreach ($existingReservations as $existingReservation) {
            if ($existingReservation['status'] === Reservation::STATUS_BORROWED) {
                continue;
            }
            $reservation = new Reservation($this->database, $existingReservation['id']);
            $reservation->setValue('ivr_status', Reservation::STATUS_CANCELLED);
            $reservation->save();
        }
        $this->refreshMenuBadge();
    }

    private function isAvailableForEvent(int $itemId, DateTimeInterface $begin, DateTimeInterface $end, int $eventId): bool
    {
        $activeBorrowing = (int)$this->database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . '
              WHERE inb_ini_id = ? AND inb_last_receiver IS NOT NULL AND inb_last_receiver <> \'\'
                AND inb_borrow_date IS NOT NULL AND inb_return_date IS NULL',
            array($itemId)
        )->fetchColumn();
        if ($activeBorrowing > 0) {
            return (int)$this->database->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . '
                  WHERE ivr_ini_id = ? AND ivr_dat_id = ? AND ivr_status = ?',
                array($itemId, $eventId, Reservation::STATUS_BORROWED)
            )->fetchColumn() > 0;
        }

        return (int)$this->database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . '
              WHERE ivr_ini_id = ? AND ivr_status = ? AND (ivr_dat_id IS NULL OR ivr_dat_id <> ?)
                AND ivr_begin < ? AND ivr_end > ?',
            array($itemId, Reservation::STATUS_APPROVED, $eventId, $end->format('Y-m-d H:i:s'), $begin->format('Y-m-d H:i:s'))
        )->fetchColumn() === 0;
    }

    private function assertValidPeriod(DateTimeInterface $begin, DateTimeInterface $end): void
    {
        if ($begin >= $end) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }
    }

    /** Ensure the session-cached main menu reloads its pending reservation badge. */
    private function refreshMenuBadge(): void
    {
        global $gMenu;

        if (isset($gMenu)) {
            $gMenu->initialize();
        }
    }
}
