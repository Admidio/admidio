<?php

namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\Service\InventoryAccessService;
use Admidio\UI\Component\DataTables;
use Admidio\Users\Entity\User;

/** Displays the reservation queue for inventory administrators. */
class InventoryReservationPresenter extends PagePresenter
{
    public function createList(): void
    {
        global $gCurrentSession, $gCurrentOrgId, $gCurrentUserId, $gDb, $gL10n, $gProfileFields, $gValidLogin;

        $isManager = InventoryAccessService::canManageReservations();
        if (!$isManager && (!$gValidLogin || !InventoryAccessService::canRequestReservation())) {
            throw new \Admidio\Infrastructure\Exception('SYS_NO_RIGHTS');
        }

        if (InventoryAccessService::canRequestReservation()) {
            $this->addPageFunctionsMenuItem(
                'menu_item_inventory_reservation_request',
                $gL10n->get('SYS_INVENTORY_RESERVATION_REQUEST'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_request')),
                'bi-calendar-plus'
            );
        }
        ChangelogService::displayHistoryButton($this, 'inventory_reservations', 'inventory_reservations', $isManager);

        $requesterCondition = $isManager ? '' : ' AND ivr_usr_id = ?';
        $queryParameters = array($gCurrentOrgId);
        if (!$isManager) {
            $queryParameters[] = $gCurrentUserId;
        }
        $queryParameters[] = Reservation::STATUS_REQUESTED;

        $statement = $gDb->queryPrepared(
            'SELECT ivr_uuid, ivr_usr_id, ivr_guest_name, ivr_guest_email, ivr_timestamp_create, ivr_begin, ivr_end, ivr_status,
                    ind_value AS item_name
               FROM ' . TBL_INVENTORY_RESERVATIONS . '
         INNER JOIN ' . TBL_INVENTORY_ITEMS . ' ON ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
              WHERE ini_org_id = ?' . $requesterCondition . '
           ORDER BY CASE WHEN ivr_status = ? THEN 0 ELSE 1 END, ivr_begin',
            $queryParameters
        );

        $statusLabels = array(
            Reservation::STATUS_REQUESTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REQUESTED'),
            Reservation::STATUS_APPROVED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_APPROVED'),
            Reservation::STATUS_REJECTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REJECTED'),
            Reservation::STATUS_CANCELLED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_CANCELLED')
        );
        $itemOptions = array();
        $html = '<div class="row g-3 mb-3"><div class="col-md-4"><label class="form-label" for="reservation_filter_item">'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</label><select class="form-select" id="reservation_filter_item"><option value="">'
            . $gL10n->get('SYS_ALL') . '</option>';
        $rows = array();
        while ($row = $statement->fetch()) {
            $itemOptions[$row['item_name']] = $row['item_name'];
            $rows[] = $row;
        }
        foreach ($itemOptions as $itemName) {
            $html .= '<option value="' . SecurityUtils::encodeHTML($itemName) . '">' . SecurityUtils::encodeHTML($itemName) . '</option>';
        }
        $html .= '</select></div><div class="col-md-4"><label class="form-label" for="reservation_filter_date">'
            . $gL10n->get('SYS_DATE') . '</label><input class="form-control" id="reservation_filter_date" type="date"></div>'
            . '<div class="col-md-4"><label class="form-label" for="reservation_filter_status">'
            . $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS') . '</label><select class="form-select" id="reservation_filter_status"><option value="">'
            . $gL10n->get('SYS_ALL') . '</option>';
        foreach ($statusLabels as $statusLabel) {
            $html .= '<option value="' . SecurityUtils::encodeHTML($statusLabel) . '">' . SecurityUtils::encodeHTML($statusLabel) . '</option>';
        }
        $html .= '</select></div></div><div class="table-responsive"><table id="adm_inventory_reservations_table" class="table table-hover"><thead><tr><th>'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTER') . '</th><th>'
            . $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTED_AT') . '</th><th>' . $gL10n->get('SYS_START') . '</th><th>'
            . $gL10n->get('SYS_END') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS') . '</th><th></th></tr></thead><tbody>';
        $user = new User($gDb, $gProfileFields);
        foreach ($rows as $row) {
            if ((int)$row['ivr_usr_id'] > 0 && $user->readDataById((int)$row['ivr_usr_id'])) {
                $requester = SecurityUtils::encodeHTML($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
            } else {
                $requester = SecurityUtils::encodeHTML($row['ivr_guest_name']);
                if ($row['ivr_guest_email'] !== '') {
                    $requester .= '<br><small>' . SecurityUtils::encodeHTML($row['ivr_guest_email']) . '</small>';
                }
            }
            $html .= '<tr><td>' . SecurityUtils::encodeHTML($row['item_name']) . '</td><td>' . $requester . '</td><td>'
                . SecurityUtils::encodeHTML($row['ivr_timestamp_create']) . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_begin'])
                . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_end']) . '</td><td>'
                . SecurityUtils::encodeHTML($statusLabels[$row['ivr_status']] ?? $row['ivr_status']) . '</td><td>';
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
            if (!$isManager && in_array($row['ivr_status'], array(Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED), true)) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_withdraw', 'reservation_uuid' => $row['ivr_uuid']));
                $html .= '<button class="btn btn-secondary btn-sm" onclick="callUrl(\'' . $url . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'window.location.reload()\'); return false;">'
                    . $gL10n->get('SYS_INVENTORY_RESERVATION_WITHDRAW') . '</button>';
            }
            $html .= '</td></tr>';
        }
        $this->addHtml($html . '</tbody></table></div>');

        $dataTables = new DataTables($this, 'adm_inventory_reservations_table');
        $dataTables->disableColumnsSort(array(7));
        $dataTables->createJavascript(count($rows), 7);
        $this->addJavascript('
            var reservationTable = $("#adm_inventory_reservations_table").DataTable();
            $.fn.dataTable.ext.search.push(function(settings, data) {
                if (settings.nTable.id !== "adm_inventory_reservations_table") {
                    return true;
                }
                var selectedDate = $("#reservation_filter_date").val();
                if (!selectedDate) {
                    return true;
                }
                return data[3].substring(0, 10) <= selectedDate && selectedDate <= data[4].substring(0, 10);
            });
            $("#reservation_filter_item").on("change", function() {
                reservationTable.column(0).search("^" + $.fn.dataTable.util.escapeRegex(this.value) + "$", true, false).draw();
            });
            $("#reservation_filter_date").on("change", function() {
                reservationTable.draw();
            });
            $("#reservation_filter_status").on("change", function() {
                reservationTable.column(5).search("^" + $.fn.dataTable.util.escapeRegex(this.value) + "$", true, false).draw();
            });
        ', true);
    }
}
