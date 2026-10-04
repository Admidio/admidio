<?php

namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\Service\InventoryAccessService;
use Admidio\UI\Component\DataTables;
use Admidio\Users\Entity\User;

/** Displays the operational inventory borrowing workspace. */
class InventoryBorrowPresenter extends PagePresenter
{
    /** @throws Exception */
    public function createList(): void
    {
        global $gCurrentOrgId, $gDb, $gL10n, $gProfileFields, $gSettingsManager;

        if (!InventoryAccessService::canManageReservations() || $gSettingsManager->getBool('inventory_items_disable_borrowing')) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $viewForm = new FormPresenter(
            'adm_inventory_borrow_view_form',
            'sys-template-parts/form.filter.tpl',
            '',
            $this,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $viewForm->addSelectBox('inventory_view', $gL10n->get('SYS_VIEW'), array(
            'overview' => $gL10n->get('SYS_OVERVIEW'),
            'borrowing' => $gL10n->get('SYS_INVENTORY_BORROWINGS')
        ), array('defaultValue' => 'borrowing', 'showContextDependentFirstEntry' => false));
        $viewForm->addToHtmlPage();
        $this->addJavascript('$("#inventory_view").on("change", function() {
            window.location.href = ' . json_encode(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php')) . ';
        });', true);

        $readyReservations = $gDb->queryPrepared(
            'SELECT ivr_uuid, ivr_begin, ivr_end, dat_headline, ini_uuid, ind_value AS item_name
               FROM ' . TBL_INVENTORY_RESERVATIONS . '
         INNER JOIN ' . TBL_INVENTORY_ITEMS . ' ON ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
          LEFT JOIN ' . TBL_EVENTS . ' ON dat_id = ivr_dat_id
          LEFT JOIN ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' ON inb_ini_id = ini_id
              WHERE ini_org_id = ?
                AND ivr_status = ?
                AND ivr_end >= ?
                AND (inb_last_receiver IS NULL OR inb_last_receiver = \'\' OR inb_return_date IS NOT NULL)
           ORDER BY ivr_begin',
            array($gCurrentOrgId, Reservation::STATUS_APPROVED, DATETIME_NOW)
        )->fetchAll();

        $readyHtml = '<div class="table-responsive"><table id="adm_inventory_borrow_ready_table" class="table table-condensed table-hover"><thead><tr><th>'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</th><th>' . $gL10n->get('SYS_PERIOD') . '</th><th>'
            . $gL10n->get('SYS_EVENT') . '</th><th></th></tr></thead><tbody>';
        foreach ($readyReservations as $reservation) {
            $readyHtml .= '<tr><td>' . SecurityUtils::encodeHTML($reservation['item_name']) . '</td><td>'
                . SecurityUtils::encodeHTML($reservation['ivr_begin']) . ' - ' . SecurityUtils::encodeHTML($reservation['ivr_end']) . '</td><td>'
                . SecurityUtils::encodeHTML($reservation['dat_headline'] ?? '') . '</td><td><a class="btn btn-primary" href="'
                . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                    'mode' => 'item_edit_borrow',
                    'item_uuid' => $reservation['ini_uuid'],
                    'reservation_uuid' => $reservation['ivr_uuid']
                )) . '"><i class="bi bi-box-arrow-up-right"></i> '
                . SecurityUtils::encodeHTML($gL10n->get('SYS_INVENTORY_START_BORROWING')) . '</a></td></tr>';
        }
        $this->addHtml('<div class="card admidio-field-group"><div class="card-header">' . SecurityUtils::encodeHTML($gL10n->get('SYS_INVENTORY_BORROWINGS_READY'))
            . '</div><div class="card-body">' . $readyHtml . '</tbody></table></div></div></div>');

        $activeBorrowings = $gDb->queryPrepared(
            'SELECT ini_uuid, ind_value AS item_name, inb_last_receiver, inb_borrow_date, ivr_uuid
               FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . '
         INNER JOIN ' . TBL_INVENTORY_ITEMS . ' ON ini_id = inb_ini_id
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
          LEFT JOIN ' . TBL_INVENTORY_RESERVATIONS . ' ON ivr_ini_id = ini_id AND ivr_status = \'borrowed\'
              WHERE ini_org_id = ?
                AND inb_last_receiver IS NOT NULL AND inb_last_receiver <> \'\'
                AND inb_borrow_date IS NOT NULL AND inb_return_date IS NULL
           ORDER BY inb_borrow_date',
            array($gCurrentOrgId)
        )->fetchAll();

        $activeHtml = '<div class="table-responsive"><table id="adm_inventory_borrow_active_table" class="table table-condensed table-hover"><thead><tr><th>'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</th><th>' . $gL10n->get('SYS_INVENTORY_LAST_RECEIVER') . '</th><th>'
            . $gL10n->get('SYS_INVENTORY_BORROW_DATE') . '</th><th></th></tr></thead><tbody>';
        $user = new User($gDb, $gProfileFields);
        foreach ($activeBorrowings as $borrowing) {
            $receiver = (string)$borrowing['inb_last_receiver'];
            if (is_numeric($receiver) && $user->readDataById((int)$receiver)) {
                $receiver = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
            }
            $activeHtml .= '<tr><td>' . SecurityUtils::encodeHTML($borrowing['item_name']) . '</td><td>'
                . SecurityUtils::encodeHTML($receiver) . '</td><td>'
                . SecurityUtils::encodeHTML($borrowing['inb_borrow_date']) . '</td><td><a class="btn btn-primary" href="'
                . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array_filter(array(
                    'mode' => 'item_edit_borrow',
                    'item_uuid' => $borrowing['ini_uuid'],
                    'item_borrowed' => 1,
                    'reservation_uuid' => $borrowing['ivr_uuid']
                ))) . '"><i class="bi bi-box-arrow-in-down-left"></i> '
                . SecurityUtils::encodeHTML($gL10n->get('SYS_INVENTORY_RECORD_RETURN')) . '</a></td></tr>';
        }
        $this->addHtml('<div class="card admidio-field-group"><div class="card-header">' . SecurityUtils::encodeHTML($gL10n->get('SYS_INVENTORY_BORROWINGS_ACTIVE'))
            . '</div><div class="card-body">' . $activeHtml . '</tbody></table></div></div></div>');

        $readyTable = new DataTables($this, 'adm_inventory_borrow_ready_table');
        $readyTable->disableColumnsSort(array(4));
        $readyTable->setColumnsNotHideResponsive(array(1, 4));
        $readyTable->createJavascript(count($readyReservations), 4);
        $activeTable = new DataTables($this, 'adm_inventory_borrow_active_table');
        $activeTable->disableColumnsSort(array(4));
        $activeTable->setColumnsNotHideResponsive(array(1, 4));
        $activeTable->createJavascript(count($activeBorrowings), 4);
    }
}
