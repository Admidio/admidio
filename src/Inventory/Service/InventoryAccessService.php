<?php

namespace Admidio\Inventory\Service;

/**
 * Central authorization rules for inventory items.
 *
 * Item services, data objects and presenters all need the same answer to the question whether
 * the current user may edit an item. Keeping that rule here prevents their checks from drifting.
 */
class InventoryAccessService
{
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
}
