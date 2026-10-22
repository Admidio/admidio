<?php

namespace Admidio\Inventory\Service;

use Admidio\UI\Presenter\InventoryPresenter;

/**
 * Central authorization rules for inventory items.
 *
 * Item services, data objects and presenters all need the same answer to the question whether
 * the current user may edit an item. Keeping that rule here prevents their checks from drifting.
 */
class InventoryAccessService
{
    /**
     * Whether the current visitor may view the inventory module.
     *
     * This is deliberately non-throwing so embedding contexts, such as the user profile, can
     * hide their inventory section instead of failing the entire page.
     */
    public static function canViewModule(): bool
    {
        global $gSettingsManager, $gCurrentUser, $gValidLogin;

        $level = $gSettingsManager->getInt('inventory_module_enabled');

        return match ($level) {
            1 => true,
            2 => $gValidLogin,
            3 => $gCurrentUser->isAdministratorInventory(),
            4 => $gCurrentUser->isAdministratorInventory() || InventoryPresenter::isCurrentUserKeeper(),
            5 => $gCurrentUser->isAdministratorInventory() || $gCurrentUser->isAllowedToViewInventory(),
            default => false
        };
    }

    /**
     * Whether the current user may edit an item administered by the given keeper.
     *
     * Inventory administrators can edit every item. A keeper may edit only their own item, only
     * when the module is not restricted to administrators, and only when that preference is on.
     */
    public static function canEditItem(?int $keeperId): bool
    {
        global $gCurrentUser, $gSettingsManager;

        if ($gCurrentUser->isAdministratorInventory()) {
            return true;
        }

        return $keeperId !== null
            && $keeperId > 0
            && $gSettingsManager->getInt('inventory_module_enabled') !== 3
            && $gSettingsManager->getBool('inventory_allow_keeper_edit')
            && $keeperId === (int) $gCurrentUser->getValue('usr_id');
    }

    /**
     * Whether the current user is the item's keeper and may use keeper-level actions.
     *
     * This intentionally preserves the existing presenter behaviour for the administrator-only
     * module mode: an administrator who is also the keeper is still identified as that keeper.
     */
    public static function canEditAsKeeper(?int $keeperId): bool
    {
        global $gCurrentUser, $gSettingsManager;

        if ($keeperId === null || $keeperId <= 0 || $keeperId !== (int) $gCurrentUser->getValue('usr_id')) {
            return false;
        }

        return ($gSettingsManager->getInt('inventory_module_enabled') !== 3
                && $gSettingsManager->getBool('inventory_allow_keeper_edit'))
            || ($gSettingsManager->getInt('inventory_module_enabled') === 3
                && $gCurrentUser->isAdministratorInventory());
    }

    /** Whether the current visitor may submit a reservation request. */
    public static function canRequestReservation(): bool
    {
        global $gCurrentUser, $gSettingsManager, $gValidLogin;

        if (!$gSettingsManager->getBool('inventory_reservations_enabled')) {
            return false;
        }

        if ($gCurrentUser->isAdministratorInventory()) {
            return true;
        }

        return match ($gSettingsManager->getString('inventory_reservation_requesters')) {
            'guests' => true,
            'members' => $gValidLogin,
            'roles' => $gValidLogin && self::isMemberOfConfiguredReservationRole($gCurrentUser),
            default => false
        };
    }

    /**
     * Administrators always manage the shared reservation queue. Item keepers may do so when
     * the organization explicitly enables this additional permission.
     */
    public static function canManageReservations(): bool
    {
        global $gCurrentUser, $gSettingsManager;

        if (!$gSettingsManager->getBool('inventory_reservations_enabled')) {
            return false;
        }

        return $gCurrentUser->isAdministratorInventory()
            || ($gSettingsManager->getInt('inventory_module_enabled') !== 3
                && $gSettingsManager->getBool('inventory_allow_keeper_edit')
                && $gSettingsManager->getBool('inventory_reservation_keepers_manage')
                && InventoryPresenter::isCurrentUserKeeper());
    }

    /** Whether the current user may manage reservations for one specific item. */
    public static function canManageReservationItem(int $itemId): bool
    {
        global $gCurrentUser;

        if (!self::canManageReservations()) {
            return false;
        }

        return $gCurrentUser->isAdministratorInventory()
            || (self::canManageReservations() && InventoryPresenter::isCurrentUserKeeper($itemId));
    }

    /**
     * Whether the current user may process regular borrowings.
     * Inventory administrators may process all items. Keepers retain the same scope as the
     * established item borrowing action and may process only items assigned to them.
     */
    public static function canManageBorrowings(): bool
    {
        global $gCurrentUser, $gSettingsManager;

        return $gCurrentUser->isAdministratorInventory()
            || ($gSettingsManager->getInt('inventory_module_enabled') !== 3
                && $gSettingsManager->getBool('inventory_allow_keeper_edit')
                && InventoryPresenter::isCurrentUserKeeper());
    }

    private static function isMemberOfConfiguredReservationRole(object $user): bool
    {
        global $gSettingsManager;

        foreach (explode(',', $gSettingsManager->getString('inventory_reservation_requester_roles')) as $roleId) {
            if (is_numeric($roleId) && $user->isMemberOfRole((int)$roleId)) {
                return true;
            }
        }
        return false;
    }
}
