<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Server side script for Datatables to return the requested messages list
 *
 * This script will read all necessary messages and their data from the database. It is optimized to
 * work with the javascript DataTables and will return the data in json format.
 *
 * **Code example**
 * ```
 * // the returned json data string
 * {
 *    "draw":1,
 *    "recordsTotal":"147",
 *    "data": [  [ 1,
 *                 "Link to profile",
 *                 "Smith, Heinz",
 *                 "Admin",
 *                 "Gender",
 *                 "16.06.1991",
 *                 "14.02.2009 15:24",
 *                 "Functions"],
 *                [ ... ],
 *             ],
 *    "recordsFiltered":"147"
 * }
 * ```
 *
 * Parameters:
 *
 * draw          - Number to validate the right inquiry from DataTables.
 * start         - Paging first record indicator. This is the start point in the current data set
 *                 (0 index based - i.e. 0 is the first record).
 * length        - Number of records that the table can display in the current draw. It is expected that
 *                 the number of records returned will be equal to this number, unless the server has
 *                 fewer records to return. Note that this can be -1 to indicate that all records should
 *                 be returned (although that negates any benefits of server-side processing!)
 * search[value] - Global search value.
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getDraw   = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
$getStart  = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
$getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
$getSearch = admFuncVariableIsValid($_GET['search'], 'value', 'string');

$jsonArray = array('draw' => $getDraw);

header('Content-Type: application/json');

// create order statement
$orderCondition = '';
$orderColumns = array('msg_type', 'msg_subject', 'recipients', 'attachments', 'msg_timestamp');

if (array_key_exists('order', $_GET)) {
    foreach ($_GET['order'] as $order) {
        if (is_numeric($order['column'])) {
            if ($orderCondition === '') {
                $orderCondition = ' ORDER BY ';
            } else {
                $orderCondition .= ', ';
            }

            if (strtoupper($order['dir']) === 'ASC') {
                $orderCondition .= $orderColumns[$order['column']]. ' ASC ';
            } else {
                $orderCondition .= $orderColumns[$order['column']]. ' DESC ';
            }
        }
    }
} else {
    $orderCondition = ' ORDER BY msg_timestamp DESC ';
}

// create search conditions
$searchCondition = '';
$queryParamsSearch = array();
$searchColumns = array(
    'COALESCE(msg_subject, \' \')'
);

if ($getSearch !== '' && count($searchColumns) > 0) {
    $searchString = explode(' ', $getSearch);

    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
        $searchValue = ' ?::text ';
    } else {
        // mysql
        $searchValue = ' ? ';
    }

    foreach ($searchString as $searchWord) {
        $searchCondition .= ' AND concat(' . implode(', ', $searchColumns) . ') LIKE CONCAT(\'%\', '.$searchValue.', \'%\') ';
        $queryParamsSearch[] = htmlspecialchars_decode($searchWord, ENT_QUOTES | ENT_HTML5);
    }

    $searchCondition = ' WHERE ' . substr($searchCondition, 4);
}

// get count of all found users
$sql = 'SELECT COUNT(*)
          FROM ' . TBL_MESSAGES . '
         WHERE (  msg_usr_id_sender = ? -- $gCurrentUserId
                  OR EXISTS (
                      SELECT 1
                        FROM ' . TBL_MESSAGES_RECIPIENTS . '
                       WHERE msr_msg_id = msg_id
                         AND msg_type   = \'PM\'
                         AND msr_usr_id = ? -- $gCurrentUserId
                  )
                )';
$queryParams = array(
    $gCurrentUserId,
    $gCurrentUserId
);
$countTotalStatement = $gDb->queryPrepared($sql, $queryParams); // TODO add more params

$jsonArray['recordsTotal'] = (int) $countTotalStatement->fetchColumn();

 // SQL-Statement zusammensetzen
$mainSql = 'SELECT msg_id, msg_uuid, msg_type, msg_subject, msg_usr_id_sender, msg_timestamp, msg_read,
                    (SELECT count(1) FROM ' . TBL_MESSAGES_ATTACHMENTS . ' WHERE msa_msg_id = msg_id) AS attachments
              FROM ' . TBL_MESSAGES . '
             WHERE (  msg_usr_id_sender = ? -- $gCurrentUserId
                   OR EXISTS (
                      SELECT 1
                        FROM ' . TBL_MESSAGES_RECIPIENTS . '
                       WHERE msr_msg_id = msg_id
                         AND msg_type   = \'PM\'
                         AND msr_usr_id = ? -- $gCurrentUserId
                      )
                   )';
$queryParamsMain = array(
    $gCurrentUserId,
    $gCurrentUserId
);

$limitCondition = '';
if ($getLength > 0) {
    $limitCondition = ' LIMIT ' . $getLength . ' OFFSET ' . $getStart;
}

if ($getSearch === '') {
    // no search condition entered then return all records in dependence of order, limit and offset
    $sql = $mainSql . $orderCondition . $limitCondition;
} else {
    $sql = 'SELECT msg_id, msg_uuid, msg_type, msg_subject, attachments, msg_timestamp
              FROM ('.$mainSql.') AS members
               '.$searchCondition
                .$orderCondition
                .$limitCondition;
}
$messageStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch)); // TODO add more params

$rowNumber = $getStart; // count for every row

// show rows with all organization users
while ($message = $messageStatement->fetch()) {
    ++$rowNumber;
    $arrContent = array();
    $cssClass   = 'font-weight-normal';
    $iconAttachments = '';

    $messageObject = new TableMessage($gDb);
    $messageObject->setArray($message);

    // Icon fuer Orgamitglied und Nichtmitglied auswaehlen
    if ($message['msg_type'] === TableMessage::MESSAGE_TYPE_EMAIL) {
        $icon = 'fa-envelope';
        $iconText = $gL10n->get('SYS_EMAIL');
        $links = '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('msg_uuid' => $message['msg_uuid'], 'forward' => '1')) . '">
                    <i class="fas fa-share" data-toggle="tooltip" title="'.$gL10n->get('SYS_FORWARD').'"></i></a>';
    } else {
        $icon = 'fa-comment-alt';
        $iconText = $gL10n->get('SYS_PRIVATE_MESSAGES');
        $links = '';
    }

    if ($messageObject->isUnread()) {
        $cssClass = 'font-weight-bold';
    }

    if ((int) $message['attachments'] === 1) {
        $iconAttachments = '<i class="fas fa-paperclip" data-toggle="tooltip" title="' . $gL10n->get('SYS_ATTACHMENT_ONE') . '"></i>';
    } elseif ($message['attachments'] > 1) {
        $iconAttachments = '<i class="fas fa-paperclip" data-toggle="tooltip" title="' . $gL10n->get('SYS_ATTACHMENTS_VAR', array($message['attachments'])) . '"></i>';
    }

    $arrContent['DT_RowId'] = 'row_message_' . $message['msg_uuid'];
    $arrContent['DT_RowClass'] = $cssClass;
    $arrContent['0'] = '<i class="fas ' . $icon . '" data-toggle="tooltip" title="' . $iconText . '"></i>';
    $arrContent['1'] = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_uuid' => $message['msg_uuid'])) . '">' . $messageObject->getValue('msg_subject') . '</a>';
    $arrContent['2'] = $messageObject->getRecipientsNamesString();
    $arrContent['3'] = $iconAttachments;
    $arrContent['4'] = $messageObject->getValue('msg_timestamp');
    $arrContent['5'] = $links . '
        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
            data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/system/popup_message.php', array('type' => 'msg', 'element_id' => 'row_message_' . $message['msg_uuid'], 'name' => $messageObject->getValue('msg_subject'), 'database_id' => $message['msg_uuid'])) . '">
            <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_REMOVE_MESSAGE').'"></i>
        </a>';

    // create array with all column values and add it to the json array
    $jsonArray['data'][] = $arrContent;
}

// set count of filtered records
if ($getSearch !== '') {
    if ($rowNumber < $getStart + $getLength) {
        $jsonArray['recordsFiltered'] = $rowNumber;
    } else {
        // read count of all filtered records without limit and offset
        $sql = 'SELECT COUNT(*) AS count
                  FROM ('.$mainSql.') AS members
                       '.$searchCondition;
        $countFilteredStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch));
        $jsonArray['recordsFiltered'] = (int) $countFilteredStatement->fetchColumn();
    }
} else {
    $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
}

// add empty data element if no rows where found
if (!array_key_exists('data', $jsonArray)) {
    $jsonArray['data'] = array();
}

echo json_encode($jsonArray);
