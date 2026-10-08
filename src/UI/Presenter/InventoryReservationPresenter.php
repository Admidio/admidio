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
        global $gCurrentSession, $gCurrentOrgId, $gCurrentUser, $gCurrentUserId, $gDb, $gL10n, $gProfileFields, $gSettingsManager, $gValidLogin;

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
        ChangelogService::displayHistoryButton($this, 'inventory_reservations', 'inventory_reservations', $gCurrentUser->isAdministratorInventory());

        $requesterCondition = $isManager ? '' : ' AND ivr_usr_id = ?';
        $keeperJoin = '';
        $keeperCondition = '';
        $queryParameters = array();
        if ($isManager && !$gCurrentUser->isAdministratorInventory()) {
            $keeperJoin = '
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' AS keeper_data ON keeper_data.ind_ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' AS keeper_field ON keeper_field.inf_id = keeper_data.ind_inf_id
                AND keeper_field.inf_name_intern = \'KEEPER\'
                AND (keeper_field.inf_org_id = ? OR keeper_field.inf_org_id IS NULL)';
            $keeperCondition = ' AND keeper_data.ind_value = ?';
            $queryParameters[] = $gCurrentOrgId;
        }
        $queryParameters[] = $gCurrentOrgId;
        if (!$isManager) {
            $queryParameters[] = $gCurrentUserId;
        } elseif (!$gCurrentUser->isAdministratorInventory()) {
            $queryParameters[] = $gCurrentUserId;
        }

        $statement = $gDb->queryPrepared(
            'SELECT ivr_uuid, ivr_ini_id, ivr_usr_id, ivr_guest_name, ivr_guest_email, ivr_timestamp_create, ivr_begin, ivr_end, ivr_status, ivr_comment,
                    dat_uuid, dat_headline, ' . TBL_INVENTORY_ITEM_DATA . '.ind_value AS item_name
               FROM ' . TBL_INVENTORY_RESERVATIONS . '
         INNER JOIN ' . TBL_INVENTORY_ITEMS . ' ON ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ivr_ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
          LEFT JOIN ' . TBL_EVENTS . ' ON dat_id = ivr_dat_id
                    ' . $keeperJoin . '
              WHERE ini_org_id = ?' . $requesterCondition . $keeperCondition . '
           ORDER BY ivr_timestamp_create DESC',
            $queryParameters
        );

        $statusLabels = array(
            Reservation::STATUS_REQUESTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REQUESTED'),
            Reservation::STATUS_APPROVED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_APPROVED'),
            Reservation::STATUS_REJECTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REJECTED'),
            Reservation::STATUS_CANCELLED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_CANCELLED'),
            Reservation::STATUS_BORROWED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_BORROWED'),
            Reservation::STATUS_RETURNED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_RETURNED')
        );
        $reservationOrigins = array(
            'member' => $gL10n->get('SYS_INVENTORY_RESERVATION_ORIGIN_MEMBER_REQUEST'),
            'guest' => $gL10n->get('SYS_INVENTORY_RESERVATION_ORIGIN_GUEST_REQUEST')
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

        $templateData = array('headers' => array(
            $gL10n->get('SYS_INVENTORY_ITEMNAME'), $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTER'),
            $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTED_AT'), $gL10n->get('SYS_INVENTORY_RESERVATION_PERIOD_FROM'),
            $gL10n->get('SYS_INVENTORY_RESERVATION_PERIOD_TO'), $gL10n->get('SYS_INVENTORY_RESERVATION_ORIGIN'),
            $gL10n->get('SYS_COMMENT'), ''
        ), 'rows' => array());
        $user = new User($gDb, $gProfileFields);
        foreach ($rows as $row) {
            if ((int)$row['ivr_usr_id'] > 0 && $user->readDataById((int)$row['ivr_usr_id'])) {
                $requester = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
                $email = (string)$user->getValue('EMAIL');
                if ($email !== '') {
                    if ($gSettingsManager->getInt('mail_module_enabled') > 0) {
                        $mailUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $user->getValue('usr_uuid')));
                    }
                }
            } else {
                $requester = $row['ivr_guest_name'];
                if ($row['ivr_guest_email'] !== '') {
                    $email = (string)$row['ivr_guest_email'];
                    if ($gSettingsManager->getInt('mail_module_enabled') > 0) {
                        $mailUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('reservation_uuid' => $row['ivr_uuid']));
                    }
                }
            }
            if ($row['dat_uuid'] !== null) {
                $origin = $row['dat_headline'];
                $originUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'cards', 'dat_uuid' => $row['dat_uuid']));
            } else {
                $origin = $reservationOrigins[(int)$row['ivr_usr_id'] > 0 ? 'member' : 'guest'];
            }
            $reservationRowId = 'adm_inventory_reservation_' . $row['ivr_uuid'];
            $mailUrl ??= '';
            $originUrl ??= '';

            $actions = array();
            $canManageReservation = $isManager && InventoryAccessService::canManageReservationItem((int)$row['ivr_ini_id']);
            if ($canManageReservation && $row['ivr_status'] === Reservation::STATUS_REQUESTED) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_approve', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-check-circle-fill text-success', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_APPROVE'));
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_reject', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_REJECT'));
            } elseif ($canManageReservation && $row['ivr_status'] === Reservation::STATUS_APPROVED) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_cancel', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_CANCEL'));
            }
            if (!$isManager && in_array($row['ivr_status'], array(Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED), true)) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_withdraw', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_WITHDRAW'));
            }
            $buttonIcon = match ($row['ivr_status']) {
                    Reservation::STATUS_APPROVED => 'bi-check-circle-fill text-success',
                    Reservation::STATUS_REQUESTED => 'bi-hourglass-split text-secondary',
                    Reservation::STATUS_BORROWED => 'bi-box-arrow-up-right text-primary',
                    Reservation::STATUS_RETURNED => 'bi-box-arrow-in-down-left text-success',
                    default => 'bi-x-circle-fill text-danger'
                };
            $templateData['rows'][] = array('id' => $reservationRowId, 'status' => $row['ivr_status'], 'itemName' => $row['item_name'], 'requester' => $requester, 'email' => $email ?? '', 'mailUrl' => $mailUrl, 'timestamp' => $row['ivr_timestamp_create'], 'begin' => $row['ivr_begin'], 'end' => $row['ivr_end'], 'origin' => $origin, 'originUrl' => $originUrl, 'comment' => $row['ivr_comment'], 'actions' => $actions, 'statusIcon' => $buttonIcon, 'statusLabel' => $statusLabels[$row['ivr_status']]);
        }
        $this->assignSmartyVariable('reservationList', $templateData);
        $this->addHtmlByTemplate('modules/inventory.reservations.list.tpl');

        $dataTables = new DataTables($this, 'adm_inventory_reservations_table');
        $dataTables->disableColumnsSort(array(7, 8));
        $dataTables->setColumnsNotHideResponsive(array(1, 8));
        $dataTables->setRowsPerPage($gSettingsManager->getInt('inventory_items_per_page'));
        $dataTables->createJavascript(max(count($rows), 11), 8);
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
            $("#adm_inventory_reservations_table").one("init.dt", function() {
                reservationTable.draw();
            });
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
            function reloadReservationTable() {
                $.get(window.location.href, function(html) {
                    var rows = $(html).find("#adm_inventory_reservations_table tbody tr").toArray();
                    reservationTable.clear().rows.add(rows).draw(false);
                    reservationTable.columns.adjust().responsive.recalc();
                }).fail(function(xhr) {
                    var response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (error) {
                        response = {};
                    }
                    showReservationActionError(response.message);
                });
            }
            $("#adm_inventory_reservations_table").on("click", ".admidio-inventory-reservation-comment", function(event) {
                event.preventDefault();
                messageBox("", "' . $gL10n->get('SYS_COMMENT') . '");
                $("#adm_modal_messagebox .modal-body").empty().append(
                    $("<p>", {class: "mb-0 text-break"}).css("white-space", "pre-wrap").text($(this).attr("data-comment"))
                );
            });
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
                        reloadReservationTable();
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
