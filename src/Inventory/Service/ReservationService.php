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
        global $gCurrentOrgId, $gCurrentUser, $gCurrentUserId;

        if (!InventoryAccessService::canManageReservations()) {
            return 0;
        }

        $keeperJoin = '';
        $queryParameters = array();
        if (!$gCurrentUser->isAdministratorInventory()) {
            $keeperJoin = '
             INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' AS keeper_data ON keeper_data.ind_ini_id = ini_id
             INNER JOIN ' . TBL_INVENTORY_FIELDS . ' AS keeper_field ON keeper_field.inf_id = keeper_data.ind_inf_id
                    AND keeper_field.inf_name_intern = \'KEEPER\'
                    AND (keeper_field.inf_org_id = ? OR keeper_field.inf_org_id IS NULL)';
            $queryParameters[] = $gCurrentOrgId;
        }
        $queryParameters[] = $gCurrentOrgId;
        $queryParameters[] = Reservation::STATUS_REQUESTED;
        if (!$gCurrentUser->isAdministratorInventory()) {
            $queryParameters[] = $gCurrentUserId;
        }

        return (int)$database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . '
             INNER JOIN ' . TBL_INVENTORY_ITEMS . ' ON ini_id = ivr_ini_id
                    ' . $keeperJoin . '
                   WHERE ini_org_id = ? AND ivr_status = ?'
                    . (!$gCurrentUser->isAdministratorInventory() ? ' AND keeper_data.ind_value = ?' : ''),
            $queryParameters
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
        if ($eventId !== null) {
            $this->assertEventInCurrentOrganization($eventId);
        }

        return $this->withinReservationTransaction(function () use ($itemId, $begin, $end, $guestName, $guestEmail, $eventId, $comment, $gCurrentUser, $gSettingsManager, $gValidLogin): Reservation {
            $item = $this->getItemInCurrentOrganization($itemId);
            if ($item->isRetired()) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }
            $this->lockItemForReservation($itemId);

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
        });
    }

    /** Approve a pending request after an availability re-check. */
    public function approve(Reservation $reservation): void
    {
        $itemId = (int)$reservation->getValue('ivr_ini_id');
        $this->getItemInCurrentOrganization($itemId);
        if (!InventoryAccessService::canManageReservationItem($itemId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $this->withinReservationTransaction(function () use ($reservation, $itemId): void {
            $this->lockItemForReservation($itemId);
            if (!$this->isAvailable(
                $itemId,
                new \DateTimeImmutable((string)$reservation->getValue('ivr_begin')),
                new \DateTimeImmutable((string)$reservation->getValue('ivr_end')),
                (int)$reservation->getValue('ivr_id')
            )) {
                throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
            }

            $this->transition($reservation, Reservation::STATUS_APPROVED);
        });
        $this->refreshMenuBadge();
    }

    /** Reject or cancel a reservation from the administrator queue. */
    public function changeStatus(Reservation $reservation, string $status): void
    {
        $itemId = (int)$reservation->getValue('ivr_ini_id');
        $this->getItemInCurrentOrganization($itemId);
        if (!InventoryAccessService::canManageReservationItem($itemId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if (!in_array($status, array(Reservation::STATUS_REJECTED, Reservation::STATUS_CANCELLED), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $this->transition($reservation, $status);
        $this->refreshMenuBadge();
    }

    /** Mark an approved reservation as physically handed over. */
    public function startBorrowing(Reservation $reservation): void
    {
        $itemId = (int)$reservation->getValue('ivr_ini_id');
        $this->getItemInCurrentOrganization($itemId);
        if (!InventoryAccessService::canManageReservationItem($itemId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->transition($reservation, Reservation::STATUS_BORROWED);
    }

    /** Mark a handed-over reservation as returned. */
    public function finishBorrowing(Reservation $reservation): void
    {
        $itemId = (int)$reservation->getValue('ivr_ini_id');
        $this->getItemInCurrentOrganization($itemId);
        if (!InventoryAccessService::canManageReservationItem($itemId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->transition($reservation, Reservation::STATUS_RETURNED);
    }

    /** Allow the signed-in requester to withdraw an open or approved reservation. */
    public function withdraw(Reservation $reservation): void
    {
        global $gCurrentUser, $gValidLogin;

        $this->getItemInCurrentOrganization((int)$reservation->getValue('ivr_ini_id'));
        if (!$gValidLogin || (int)$reservation->getValue('ivr_usr_id') !== (int)$gCurrentUser->getValue('usr_id')) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->transition($reservation, Reservation::STATUS_CANCELLED);
        $this->refreshMenuBadge();
    }

    /**
     * Checks confirmed reservations and the existing active physical borrowing record.
     * The end is exclusive, so a return and a new reservation may meet at the same instant.
     */
    public function isAvailable(int $itemId, DateTimeInterface $begin, DateTimeInterface $end, int $ignoreReservationId = 0): bool
    {
        $this->assertValidPeriod($begin, $end);
        $this->getItemInCurrentOrganization($itemId);

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
        $this->assertEventInCurrentOrganization($eventId);
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static function (int $itemId): bool {
            return $itemId > 0;
        })));
        $isAutomatic = $gSettingsManager->getString('inventory_reservation_approval') === 'automatic';

        foreach ($itemIds as $itemId) {
            if ($itemId <= 0) {
                throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
            }

            $item = $this->getItemInCurrentOrganization($itemId);
            if ($item->isRetired()) {
                throw new Exception('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE');
            }
            $this->lockItemForReservation($itemId);

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

    /** Serialize reservation changes for one inventory item. */
    private function lockItemForReservation(int $itemId): void
    {
        $this->database->queryPrepared(
            'SELECT ini_id FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ? FOR UPDATE',
            array($itemId)
        );
    }

    /** Run a reservation mutation in a transaction without disturbing an enclosing event save. */
    private function withinReservationTransaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->database->isInTransaction();
        if ($ownsTransaction) {
            $this->database->startTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->database->endTransaction();
            }
            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->database->rollback();
            }
            throw $exception;
        }
    }

    /**
     * Read an inventory item and reject records outside the active organization.
     * Item::readDataById() deliberately bypasses its generic organization guard, so service
     * entry points must apply the established inventory organization rule themselves.
     */
    private function getItemInCurrentOrganization(int $itemId): Item
    {
        global $gCurrentOrgId;

        $organizationId = $this->database->queryPrepared(
            'SELECT ini_org_id FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?',
            array($itemId)
        )->fetchColumn();
        if ($organizationId === false
            || ((int)$organizationId > 0 && (int)$organizationId !== (int)$gCurrentOrgId)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $item = new Item($this->database, null, $itemId);
        if (!$item->readDataById($itemId)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return $item;
    }

    /** Reject event IDs that do not belong to the active organization. */
    private function assertEventInCurrentOrganization(int $eventId): void
    {
        global $gCurrentOrgId;

        $eventExists = (int)$this->database->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_EVENTS . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = dat_cat_id
             WHERE dat_id = ? AND cat_org_id = ?',
            array($eventId, $gCurrentOrgId)
        )->fetchColumn();
        if ($eventExists !== 1) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }

    /** Persist only transitions that belong to the reservation lifecycle. */
    private function transition(Reservation $reservation, string $targetStatus): void
    {
        $allowedTransitions = array(
            Reservation::STATUS_REQUESTED => array(
                Reservation::STATUS_APPROVED,
                Reservation::STATUS_REJECTED,
                Reservation::STATUS_CANCELLED
            ),
            Reservation::STATUS_APPROVED => array(
                Reservation::STATUS_BORROWED,
                Reservation::STATUS_CANCELLED
            ),
            Reservation::STATUS_BORROWED => array(Reservation::STATUS_RETURNED)
        );
        $currentStatus = (string)$reservation->getValue('ivr_status');
        if (!in_array($targetStatus, $allowedTransitions[$currentStatus] ?? array(), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $reservation->setValue('ivr_status', $targetStatus);
        $reservation->save();
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
