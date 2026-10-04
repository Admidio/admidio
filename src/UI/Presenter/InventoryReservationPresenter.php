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
                    dat_uuid, dat_headline, ind_value AS item_name
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

        $html = '<div class="table-responsive"><table id="adm_inventory_reservations_table" class="table table-condensed table-hover" style="max-width: 100%;"><thead><tr><th>'
            . $gL10n->get('SYS_INVENTORY_ITEMNAME') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTER') . '</th><th>'
            . $gL10n->get('SYS_INVENTORY_RESERVATION_REQUESTED_AT') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_PERIOD_FROM') . '</th><th>'
            . $gL10n->get('SYS_INVENTORY_RESERVATION_PERIOD_TO') . '</th><th>' . $gL10n->get('SYS_INVENTORY_RESERVATION_ORIGIN') . '</th><th>'
            . $gL10n->get('SYS_COMMENT') . '</th><th></th></tr></thead><tbody>';
        $user = new User($gDb, $gProfileFields);
        foreach ($rows as $row) {
            if ((int)$row['ivr_usr_id'] > 0 && $user->readDataById((int)$row['ivr_usr_id'])) {
                $requester = SecurityUtils::encodeHTML($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
                $email = (string)$user->getValue('EMAIL');
                if ($email !== '') {
                    $requester .= '<br><small>' . SecurityUtils::encodeHTML($email) . '</small>';
                    if ($gSettingsManager->getInt('mail_module_enabled') > 0) {
                        $requester .= ' <a class="admidio-icon-link" href="'
                            . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $user->getValue('usr_uuid')))
                            . '"><i class="bi bi-envelope" data-bs-toggle="tooltip" title="'
                            . SecurityUtils::encodeHTML($gL10n->get('SYS_SEND_EMAIL_TO', array($email))) . '"></i></a>';
                    }
                }
            } else {
                $requester = SecurityUtils::encodeHTML($row['ivr_guest_name']);
                if ($row['ivr_guest_email'] !== '') {
                    $email = (string)$row['ivr_guest_email'];
                    $requester .= '<br><small>' . SecurityUtils::encodeHTML($email) . '</small>';
                    if ($gSettingsManager->getInt('mail_module_enabled') > 0) {
                        $requester .= ' <a class="admidio-icon-link" href="'
                            . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('reservation_uuid' => $row['ivr_uuid']))
                            . '"><i class="bi bi-envelope" data-bs-toggle="tooltip" title="'
                            . SecurityUtils::encodeHTML($gL10n->get('SYS_SEND_EMAIL_TO', array($email))) . '"></i></a>';
                    }
                }
            }
            if ($row['dat_uuid'] !== null) {
                $origin = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('mode' => 'cards', 'dat_uuid' => $row['dat_uuid'])) . '">'
                    . SecurityUtils::encodeHTML($row['dat_headline']) . '</a>';
            } else {
                $origin = SecurityUtils::encodeHTML($reservationOrigins[(int)$row['ivr_usr_id'] > 0 ? 'member' : 'guest']);
            }
            $reservationRowId = 'adm_inventory_reservation_' . $row['ivr_uuid'];
            $html .= '<tr id="' . $reservationRowId . '" data-reservation-status="' . SecurityUtils::encodeHTML($row['ivr_status']) . '"><td>' . SecurityUtils::encodeHTML($row['item_name']) . '</td><td>' . $requester . '</td><td>'
                . SecurityUtils::encodeHTML($row['ivr_timestamp_create']) . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_begin'])
                . '</td><td>' . SecurityUtils::encodeHTML($row['ivr_end']) . '</td><td>' . $origin . '</td><td>';

            if (trim((string)$row['ivr_comment']) !== '') {
                $html .= '<a class="admidio-icon-link admidio-inventory-reservation-comment" href="javascript:void(0);" data-comment="'
                    . SecurityUtils::encodeHTML($row['ivr_comment']) . '"><i class="bi bi-chat-left-text-fill" data-bs-toggle="tooltip" title="'
                    . SecurityUtils::encodeHTML($gL10n->get('SYS_COMMENT')) . '"></i></a>';
            }
            $html .= '</td><td>';

            $actions = array();
            $canManageReservation = $isManager && InventoryAccessService::canManageReservationItem((int)$row['ivr_ini_id']);
            if ($canManageReservation && in_array($row['ivr_status'], array(Reservation::STATUS_REQUESTED, Reservation::STATUS_REJECTED, Reservation::STATUS_CANCELLED), true)) {
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_approve', 'reservation_uuid' => $row['ivr_uuid']));
                $actions[] = array('url' => $url, 'icon' => 'bi-check-circle-fill text-success', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_APPROVE'));
                if ($row['ivr_status'] === Reservation::STATUS_REQUESTED) {
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'reservation_reject', 'reservation_uuid' => $row['ivr_uuid']));
                    $actions[] = array('url' => $url, 'icon' => 'bi-x-circle-fill text-danger', 'label' => $gL10n->get('SYS_INVENTORY_RESERVATION_REJECT'));
                }
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
            if (count($actions) > 0) {
                $html .= '<div class="btn-group admidio-inventory-reservation-action" role="group">'
                    . '<button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
                    . '<i class="bi ' . $buttonIcon . '"></i>' . $statusLabels[$row['ivr_status']] . '</button><ul class="dropdown-menu">';
                foreach ($actions as $action) {
                    $html .= '<li><a class="icon-link dropdown-item" href="javascript:void(0)" data-url="'
                        . $action['url'] . '" data-row-id="' . $reservationRowId . '">'
                        . '<i class="bi ' . $action['icon'] . '"></i>' . $action['label'] . '</a></li>';
                }
                $html .= '</ul></div>';
            } else {
                $html .= '<span class="admidio-inventory-reservation-action"><i class="bi ' . $buttonIcon . '"></i>'
                    . $statusLabels[$row['ivr_status']] . '</span>';
            }
            $html .= '</td></tr>';
        }
        $this->addHtml($html . '</tbody></table></div><div id="adm_inventory_reservations_alert" class="alert alert-danger form-alert mt-3" style="display: none;"></div>');

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
