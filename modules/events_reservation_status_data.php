<?php
/**
 ***********************************************************************************************
 * Server-side data source for the inventory reservation status table of an event.
 *
 * @copyright The Admidio Team
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Events\Entity\Event;
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\Service\InventoryAccessService;

require_once(__DIR__ . '/../system/common.php');
require(__DIR__ . '/../system/login_valid.php');

try {
    $getDraw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
    $getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
    $getSearch = admFuncVariableIsValid($_GET['search'], 'value', 'string');
    $getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'uuid', array('requireValue' => true));
    $getRecurrenceScope = admFuncVariableIsValid($_GET, 'recurrence_scope', 'string', array('defaultValue' => 'this', 'validValues' => array('this', 'series')));

    header('Content-Type: application/json');
    $jsonArray = array('draw' => $getDraw, 'data' => array());

    if (!$gSettingsManager->getBool('inventory_reservations_enabled')
        || !$gSettingsManager->getBool('inventory_reservations_events_enabled')
        || !InventoryAccessService::canRequestReservation()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $event = new Event($gDb);
    if (!$event->readDataByUuid($getEventUuid) || !$event->isEditable()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $eventIds = array((int)$event->getValue('dat_id'));
    if ($getRecurrenceScope === 'series') {
        $recurrenceRepository = new EventRecurrenceRepository($gDb);
        $recurrence = (int)$event->getValue('dat_evr_id') > 0
            ? $recurrenceRepository->readById((int)$event->getValue('dat_evr_id'))
            : $recurrenceRepository->readByMasterEventId((int)$event->getValue('dat_id'));

        if ($recurrence !== null) {
            $eventIds = array();
            $eventStatement = $gDb->queryPrepared(
                'SELECT dat_id
                   FROM ' . TBL_EVENTS . '
                  WHERE dat_evr_id = ?
                     OR dat_id = ?
               ORDER BY dat_begin',
                array((int)$recurrence->getValue('evr_id'), (int)$recurrence->getValue('evr_dat_id_master'))
            );
            while ($eventRow = $eventStatement->fetch()) {
                $eventIds[] = (int)$eventRow['dat_id'];
            }
        }
    }

    $eventIds = array_values(array_unique($eventIds));
    $params = $eventIds;
    $whereSearch = '';
    if ($getSearch !== '') {
        $whereSearch = ' AND (dat_headline LIKE ? OR ind_value LIKE ? OR ivr_status LIKE ?)';
        $searchValue = '%' . htmlspecialchars_decode($getSearch, ENT_QUOTES | ENT_HTML5) . '%';
        array_push($params, $searchValue, $searchValue, $searchValue);
    }

    $sqlFrom = ' FROM ' . TBL_INVENTORY_RESERVATIONS . '
             INNER JOIN ' . TBL_EVENTS . ' ON dat_id = ivr_dat_id
             INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ivr_ini_id
             INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id AND inf_name_intern = \'ITEMNAME\'
            WHERE ivr_dat_id IN (' . Database::getQmForValues($eventIds) . ')' . $whereSearch;

    $jsonArray['recordsTotal'] = (int)$gDb->queryPrepared(
        'SELECT COUNT(*)' . str_replace($whereSearch, '', $sqlFrom),
        $eventIds
    )->fetchColumn();
    $jsonArray['recordsFiltered'] = (int)$gDb->queryPrepared('SELECT COUNT(*)' . $sqlFrom, $params)->fetchColumn();

    $orderColumns = array('dat_begin', 'ind_value', 'ivr_status');
    $orderColumn = 'COALESCE(ivr_timestamp_change, ivr_timestamp_create)';
    $orderDirection = 'DESC';
    if (isset($_GET['order'][0]) && isset($_GET['order'][0]['column'], $_GET['order'][0]['dir'])
        && isset($orderColumns[(int)$_GET['order'][0]['column']])) {
        $orderColumn = $orderColumns[(int)$_GET['order'][0]['column']];
        $orderDirection = strtoupper($_GET['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
    }

    $sql = 'SELECT ivr_id, ivr_dat_id, ivr_ini_id, ind_value, ivr_status, dat_headline, dat_begin, dat_end
              ' . $sqlFrom . '
          ORDER BY ' . $orderColumn . ' ' . $orderDirection . ', ivr_id DESC';
    if ($getLength !== -1) {
        $sql .= ' LIMIT ' . max($getLength, 0) . ' OFFSET ' . max($getStart, 0);
    }

    $statusLabels = array(
        Reservation::STATUS_REQUESTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REQUESTED'),
        Reservation::STATUS_APPROVED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_APPROVED'),
        Reservation::STATUS_REJECTED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_REJECTED'),
        Reservation::STATUS_CANCELLED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_CANCELLED'),
        Reservation::STATUS_BORROWED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_BORROWED'),
        Reservation::STATUS_RETURNED => $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_RETURNED')
    );
    $statusIcons = array(
        Reservation::STATUS_REQUESTED => 'bi-hourglass-split text-secondary',
        Reservation::STATUS_APPROVED => 'bi-check-circle-fill text-success',
        Reservation::STATUS_REJECTED => 'bi-x-circle-fill text-danger',
        Reservation::STATUS_CANCELLED => 'bi-x-circle-fill text-danger',
        Reservation::STATUS_BORROWED => 'bi-box-arrow-up-right text-primary',
        Reservation::STATUS_RETURNED => 'bi-box-arrow-in-down-left text-success'
    );

    $latestReservationIds = array();
    $latestStatement = $gDb->queryPrepared(
        'SELECT ivr_id, ivr_ini_id
           FROM ' . TBL_INVENTORY_RESERVATIONS . '
          WHERE ivr_dat_id = ?
       ORDER BY COALESCE(ivr_timestamp_change, ivr_timestamp_create) DESC, ivr_id DESC',
        array((int)$event->getValue('dat_id'))
    );
    while ($latestReservation = $latestStatement->fetch()) {
        if (!isset($latestReservationIds[(int)$latestReservation['ivr_ini_id']])) {
            $latestReservationIds[(int)$latestReservation['ivr_ini_id']] = (int)$latestReservation['ivr_id'];
        }
    }

    foreach ($gDb->queryPrepared($sql, $params)->fetchAll() as $reservation) {
        $status = $reservation['ivr_status'];
        $eventDate = new Event($gDb, (int)$reservation['ivr_dat_id']);
        $action = '';
        if ((int)$reservation['ivr_dat_id'] === (int)$event->getValue('dat_id')
            && ($latestReservationIds[(int)$reservation['ivr_ini_id']] ?? 0) === (int)$reservation['ivr_id']
            && in_array($status, array(Reservation::STATUS_REJECTED, Reservation::STATUS_CANCELLED), true)) {
            $requestAgainUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array(
                'mode' => 'reservation_request_again',
                'dat_uuid' => $getEventUuid,
                'reservation_item_id' => (int)$reservation['ivr_ini_id']
            ));
            $action = '<button type="button" class="btn btn-sm btn-outline-primary event-inventory-reservation-request-again" data-url="'
                . SecurityUtils::encodeHTML($requestAgainUrl) . '" data-item-id="' . (int)$reservation['ivr_ini_id'] . '">'
                . SecurityUtils::encodeHTML($gL10n->get('SYS_INVENTORY_RESERVATION_REQUEST_AGAIN')) . '</button>';
        }

        $jsonArray['data'][] = array(
            SecurityUtils::encodeHTML($reservation['dat_headline']) . '<br><small>' . SecurityUtils::encodeHTML($eventDate->getDateTimePeriod()) . '</small>',
            SecurityUtils::encodeHTML($reservation['ind_value']),
            '<span class="event-inventory-reservation-status"><i class="bi ' . ($statusIcons[$status] ?? 'bi-question-circle-fill text-secondary') . ' me-1"></i>'
                . SecurityUtils::encodeHTML($statusLabels[$status] ?? $status) . '</span>',
            $action
        );
    }

    echo json_encode($jsonArray);
} catch (Throwable $e) {
    handleException($e, true);
}
