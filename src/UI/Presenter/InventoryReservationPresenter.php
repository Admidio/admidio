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
        global $gCurrentSession, $gCurrentOrgId, $gCurrentUserId, $gDb, $gL10n, $gProfileFields, $gSettingsManager, $gValidLogin;

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
        $rows = array();
        while ($row = $statement->fetch()) {
            $itemOptions[$row['item_name']] = $row['item_name'];
            $rows[] = $row;
        }

        $filterForm = new FormPresenter(
            'adm_inventory_reservations_filter_form',
            'sys-template-parts/form.filter.tpl',
            '',
            $this,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $filterForm->addSelectBox(
            'reservation_filter_item',
            $gL10n->get('SYS_INVENTORY_ITEMNAME'),
            array('' => $gL10n->get('SYS_ALL')) + $itemOptions,
            array('showContextDependentFirstEntry' => false)
        );
        $filterForm->addInput('reservation_filter_date', $gL10n->get('SYS_DATE'), '', array('type' => 'date'));
        $filterForm->addSelectBox(
            'reservation_filter_status',
            $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS'),
            array('' => $gL10n->get('SYS_ALL')) + $statusLabels,
            array('defaultValue' => Reservation::STATUS_REQUESTED, 'showContextDependentFirstEntry' => false)
        );
        $filterForm->addToHtmlPage();

        $html = '<div class="table-responsive"><table id="adm_inventory_reservations_table" class="table table-condensed table-hover" style="max-width: 100%;"><thead><tr><th>'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTER') . '</th><th>'
            . $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTED_AT') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_PERIOD_FROM') . '</th><th>'
            . $gL10n->get('SYS_INVENTORY_RESERVATION_PERIOD_TO') . '</th><th></th></tr></thead><tbody>';
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
            $reservationRowId = 'adm_inventory_reservation_' . $row['ivr_uuid'];
            $html .= '<tr id="' . $reservationRowId . '" data-reservation-status="' . SecurityUtils::encodeHTML($row['ivr_status']) . '"><td>' . SecurityUtils::encodeHTML($row['item_name']) . '</td><td>' . $requester . '</td><td>'
                . SecurityUtils::encodeHTML($row['ivr_timestamp_create']) . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_begin'])
                . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_end']) . '</td><td>';

            $actions = array();
            if ($isManager && in_array($row['ivr_status'], array(Reservation::STATUS_REQUESTED, Reservation::STATUS_REJECTED, Reservation::STATUS_CANCELLED), true)) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_approve', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-check-circle-fill text-success', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_APPROVE'));
                if ($row['ivr_status'] === Reservation::STATUS_REQUESTED) {
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_reject', 'reservation_uuid' => $row['ivr_uuid']));
                    $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_REJECT'));
                }
            } elseif ($isManager && $row['ivr_status'] === Reservation::STATUS_APPROVED) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_cancel', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_CANCEL'));
            }
            if (!$isManager && in_array($row['ivr_status'], array(Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED), true)) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_withdraw', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_WITHDRAW'));
            }
            if (count($actions) > 0) {
                $buttonIcon = match ($row['ivr_status']) {
                    Reservation::STATUS_APPROVED => 'bi-check-circle-fill text-success',
                    Reservation::STATUS_REQUESTED => 'bi-hourglass-split text-secondary',
                    default => 'bi-x-circle-fill text-danger'
                };
                $html .= '<div class="btn-group admidio-inventory-reservation-action" role="group">'
                    . '<button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
                    . '<i class="bi ' . $buttonIcon . '"></i>' . $statusLabels[$row['ivr_status']] . '</button><ul class="dropdown-menu">';
                foreach ($actions as $action) {
                    $html .= '<li><a class="icon-link dropdown-item" href="javascript:void(0)" data-url="'
                        . $action['url'] . '" data-row-id="' . $reservationRowId . '">'
                        . '<i class="bi ' . $action['icon'] . '"></i>' . $action['label'] . '</a></li>';
                }
                $html .= '</ul></div>';
            }
            $html .= '</td></tr>';
        }
        $this->addHtml($html . '</tbody></table></div><div id="adm_inventory_reservations_alert" class="alert alert-danger form-alert mt-3" style="display: none;"></div>');

        $dataTables = new DataTables($this, 'adm_inventory_reservations_table');
        $dataTables->disableColumnsSort(array(6));
        $dataTables->setColumnsNotHideResponsive(array(1, 6));
        $dataTables->setRowsPerPage($gSettingsManager->getInt('inventory_items_per_page'));
        $dataTables->createJavascript(max(count($rows), 11), 6);
        $this->addJavascript('
            var reservationTable = $("#adm_inventory_reservations_table").DataTable();
            var reservationActionErrorTimeout;
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== "adm_inventory_reservations_table") {
                    return true;
                }
                var selectedDate = $("#reservation_filter_date").val();
                var dateMatches = !selectedDate || (data[3].substring(0, 10) <= selectedDate && selectedDate <= data[4].substring(0, 10));
                var selectedStatus = $("#reservation_filter_status").val();
                var reservationRow = settings.aoData[dataIndex].nTr;
                var statusMatches = !selectedStatus || reservationRow.getAttribute("data-reservation-status") === selectedStatus;

                return dateMatches && statusMatches;
            });
            $("#reservation_filter_item").on("change", function() {
                reservationTable.column(0).search("^" + $.fn.dataTable.util.escapeRegex(this.value) + "$", true, false).draw();
            });
            $("#reservation_filter_date").on("change", function() {
                reservationTable.draw();
            });
            $("#reservation_filter_status").on("change", function() {
                reservationTable.draw();
            });
            reservationTable.draw();
            function showReservationActionError(message) {
                var errorAlert = $("#adm_inventory_reservations_alert");
                errorAlert.empty().append(
                    $("<i>", {class: "bi bi-exclamation-circle-fill"}),
                    document.createTextNode(message || "' . $gL10n->get('SYS_ERROR') . '")
                ).show();
                clearTimeout(reservationActionErrorTimeout);
                reservationActionErrorTimeout = setTimeout(function() {
                    errorAlert.fadeOut();
                }, 7000);
            }
            $("#adm_inventory_reservations_table").on("click", ".admidio-inventory-reservation-action .dropdown-item", function(event) {
                event.preventDefault();

                var action = $(this);
                $.post(action.data("url"), {
                    adm_csrf_token: "' . $gCurrentSession->getCsrfToken() . '"
                }, function(data) {
                    var response;
                    try {
                        response = typeof data === "string" ? JSON.parse(data) : data;
                    } catch (error) {
                        response = {status: "error"};
                    }

                    if (response.status === "success") {
                        window.location.reload();
                        return;
                    }

                    showReservationActionError(response.message);
                }).fail(function(xhr) {
                    var response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (error) {
                        response = {};
                    }
                    showReservationActionError(response.message);
                });
            });
        ', true);
    }
}
