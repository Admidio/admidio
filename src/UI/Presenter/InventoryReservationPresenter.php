<?php

namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\Service\InventoryAccessService;

/** Displays the reservation queue for inventory administrators. */
class InventoryReservationPresenter extends PagePresenter
{
    public function createList(): void
    {
        global $gCurrentSession, $gDb, $gL10n;

        if (!InventoryAccessService::canManageReservations()) {
            throw new \Admidio\Infrastructure\Exception('SYS_NO_RIGHTS');
        }

        $statement = $gDb->queryPrepared(
            'SELECT ivr_uuid, ivr_guest_name, ivr_guest_email, ivr_begin, ivr_end, ivr_status,
                    ind_value AS item_name
               FROM ' . TBL_INVENTORY_RESERVATIONS . '
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
           ORDER BY CASE WHEN ivr_status = ? THEN 0 ELSE 1 END, ivr_begin',
            array(Reservation::STATUS_REQUESTED)
        );

        $html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</th><th>' . $gL10n->get('SYS_START') . '</th><th>'
            . $gL10n->get('SYS_END') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS') . '</th><th></th></tr></thead><tbody>';
        while ($row = $statement->fetch()) {
            $html .= '<tr><td>' . SecurityUtils::encodeHTML($row['item_name']) . '</td><td>'
                . SecurityUtils::encodeHTML($row['ivr_begin']) . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_end'])
                . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_status']) . '</td><td>';
            if ($row['ivr_status'] === Reservation::STATUS_REQUESTED) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_approve', 'reservation_uuid' => $row['ivr_uuid']));
                $html .= '<button class="btn btn-primary btn-sm" onclick="callUrl(\'' . $url . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'window.location.reload()\'); return false;">'
                    . $gL10n->get('SYS_INVENTORY_RESERVATION_APPROVE') . '</button>';
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_reject', 'reservation_uuid' => $row['ivr_uuid']));
                $html .= ' <button class="btn btn-secondary btn-sm" onclick="callUrl(\'' . $url . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'window.location.reload()\'); return false;">'
                    . $gL10n->get('SYS_INVENTORY_RESERVATION_REJECT') . '</button>';
            } elseif ($row['ivr_status'] === Reservation::STATUS_APPROVED) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_cancel', 'reservation_uuid' => $row['ivr_uuid']));
                $html .= '<button class="btn btn-secondary btn-sm" onclick="callUrl(\'' . $url . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'window.location.reload()\'); return false;">'
                    . $gL10n->get('SYS_CANCEL') . '</button>';
            }
            $html .= '</td></tr>';
        }
        $this->addHtml($html . '</tbody></table></div>');
    }
}
