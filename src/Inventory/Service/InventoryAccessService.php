<?php

namespace Admidio\Inventory\Service;

use Admidio\UI\Presenter\InventoryPresenter;

/**
 * Central authorization rules for inventory items.
 */
class InventoryAccessService
{
    /**
     * Whether the current visitor may view the inventory module.
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
