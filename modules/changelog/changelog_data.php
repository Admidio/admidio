<?php
/**
 ***********************************************************************************************
 * Server side script for Datatables to return the requested the list of change history records
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 *
 *
 * This script will read all requested change history records from the database. It is optimized to
 * work with the javascript DataTables and will return the data in json format.
 *
 * **Code example**
 * ```
 * // the returned json data string
 * {
 *    "draw":1,
 *    "recordsTotal":"547",
 *    "data": [  [ 1,
 *                 "tablename",
 *                 "1",
 *                 "Lastname, Firstname",
 *                 "fd3e1942-1285-4fe0-b3c0-eb3c5cebfad0",
 *                 "",
 *                 "",
 *                 "field_id",
 *                 "field_name",
 *                 "value_old",
 *                 "value_new",
 *                [ ... ],
 *             ],
 *    "recordsFiltered":"147"
 * }
 * ```
 *
 * Parameters:
 *
 * table            : The type of changes to be listed (name of the DB table, excluding the prefix)
 * id...............: If set only show the change history of that database record
 * uuid             : If set only show the change history of that database record
 * related_id       : If set only show the change history of objects related to that id (e.g. membership of a role/group)
 * filter_date_from : is set to actual date,
 *                    if no date information is delivered
 * filter_date_to   : is set to 31.12.9999,
 *                    if no date information is delivered
 * draw    - Number to validate the right inquiry from DataTables.
 * start   - Paging first record indicator. This is the start point in the current data set
 *           (0 index based - i.e. 0 is the first record).
 * length  - Number of records that the table can display in the current draw. It is expected that
 *           the number of records returned will be equal to this number, unless the server has
 *           fewer records to return. Note that this can be -1 to indicate that all records should
 *           be returned (although that negates any benefits of server-side processing!)
 * search[value] - Global search value.
 *
 *
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Infrastructure\Utils\DateTimeUtils;


// This script always answers with JSON, even if an exception is thrown before the request
// parameters could be evaluated (e.g. while the system is still bootstrapping). Set up the
// response and the content type up front, so the catch block below can always emit a valid reply.
$jsonArray = array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array());
header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // calculate default date from which the history should be shown
    $filterDateFrom = ChangelogService::getDefaultFilterDateFrom();


    // Initialize and check the parameters
    $getTable = admFuncVariableIsValid($_GET, 'table','string');
    $getTables = ($getTable !== null && $getTable != "") ? array_map('trim', explode(",", $getTable)) : [];
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    $getId = admFuncVariableIsValid($_GET, 'id', 'int');
    $getRelatedId = admFuncVariableIsValid($_GET, 'related_id', 'string');
    $getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
    $getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

    # Datatables parameters
    $getDraw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
    $getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
    $getSearch = admFuncVariableIsValid($_GET['search'] ?? array(), 'value', 'string');

    // The changelog grows without any bound, so a request for all entries would read, format and
    // encode the complete log of the organization at once. The page length menu of the table
    // therefore does not offer that (see disableShowAllEntries() in changelog.php), and the page
    // length is limited here as well, so that a hand-crafted request cannot exceed it either.
    // A length of -1, which DataTables uses for "all entries", ends up at the same limit.
    $maxRowsPerRequest = 1000;
    $getStart = max(0, $getStart);
    if ($getLength < 1 || $getLength > $maxRowsPerRequest) {
        $getLength = $maxRowsPerRequest;
    }

    $jsonArray['draw'] = (int)$getDraw;



    if ($gSettingsManager->getInt('changelog_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // create a user object. Will be filled if the log of one particular user is requested.
    $user = new User($gDb, $gProfileFields);
    // User log contains at most four tables: User, user_data, user_relations and members -> they have many more permissions than other tables!
    $isUserLog = (!empty($getTables) && empty(array_diff($getTables, ChangelogService::$userTables)));
    if ($isUserLog) {
        if (!empty($getUuid)) {
            $user->readDataByUuid($getUuid);
        } elseif (!empty($getId)) {
            $user->readDataById($getId);
        }
        if (!$user->isNewRecord()) {
            // Address the user by uuid from here on: for the user_data table the record id is the
            // id of the data row and not of the user, so filtering by id would match the log
            // entries of a different user.
            $getUuid = $user->getValue('usr_uuid');
            $getId = 0;
        }
    }

    // All view permissions are evaluated in one place and are applied as SQL conditions below,
    // so that the record counts and the paging match exactly what is displayed.
    $subject = $user->isNewRecord() ? null : $user;
    $readableTables = ChangelogService::getReadableTables($gCurrentUser, $getTables, $subject);
    if (count($readableTables) === 0) {
        throw new Exception('SYS_NO_RIGHTS');
    }


    // filter_date_from and filter_date_to can use the internal ISO format
    // or the configured Admidio date format.
    $objDateFrom = DateTimeUtils::parseDate($getDateFrom, '1970-01-01');
    $objDateTo = DateTimeUtils::parseDate($getDateTo, DATE_NOW);

    // DateTo should be greater than DateFrom
    if ($objDateFrom > $objDateTo) {
        throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
    }

    $dateFromIntern = $objDateFrom->format('Y-m-d');
    $dateFromHtml = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $dateToIntern = $objDateTo->format('Y-m-d');
    $dateToHtml = $objDateTo->format($gSettingsManager->getString('system_date'));


    // Logic for hiding certain columns:
    // If we have only one table name given, hide the table column
    // If we view the user profile field changes page, hide the column, too
    $showTableColumn = (count($getTables) !== 1);
    // If none of the related-to values is set, hide the related_to column
    $showRelatedColumn = true;

    // Whitelist of the columns that can be sorted. The array index corresponds to the column
    // position that DataTables sends and must match the order in which the columns are written
    // to $columnValues further down.
    $orderColumns = array('id');
    if ($showTableColumn) {
        $orderColumns[] = 'table_name';
    }
    $orderColumns[] = 'name';
    if ($showRelatedColumn) {
        $orderColumns[] = 'related_name';
    }
    $orderColumns[] = 'field_name';
    $orderColumns[] = 'value_new';
    $orderColumns[] = 'value_old';
    $orderColumns[] = 'create_last_name';
    $orderColumns[] = 'timestamp';

    // create order statement. Only column names from the whitelist above are used, so the
    // request cannot inject anything into the ORDER BY clause.
    $orderCondition = ' ORDER BY id DESC ';
    if (isset($_GET['order']) && is_array($_GET['order'])) {
        $orderParts = array();
        foreach ($_GET['order'] as $order) {
            $columnIndex = (int)($order['column'] ?? -1);
            if (array_key_exists($columnIndex, $orderColumns)) {
                $orderParts[] = $orderColumns[$columnIndex]
                    . (strtoupper($order['dir'] ?? '') === 'ASC' ? ' ASC' : ' DESC');
            }
        }
        if (count($orderParts) > 0) {
            $orderCondition = ' ORDER BY ' . implode(', ', $orderParts) . ' ';
        }
    }

    // create search conditions
    $searchCondition = '';
    $queryParamsSearch = array();
    // $searchColumns = array('log_table', 'log_record_name', 'log_related_name', 'log_field', 'log_field_name', 'log_action', 'log_value_old', 'log_value_new', 'create_first_name', 'create_last_name');
    $searchColumns = array('table_name', 'name', 'related_name', 'field', 'field_name', 'action', 'value_old', 'value_new', 'create_first_name', 'create_last_name');

    if ($getSearch !== '' && count($searchColumns) > 0) {
        $searchString = explode(' ', $getSearch);

        if (DB_TYPE === Database::PDO_ENGINE_PGSQL) {
            $searchValue = ' ?::text ';
        } else {
            // mysql
            $searchValue = ' ? ';
        }

        foreach ($searchString as $searchWord) {
            // Both sides have to be lowered, otherwise the search is case-sensitive on PostgreSQL.
            $searchCondition .= ' AND LOWER(CONCAT(' . implode(', \' \', ', array_map(fn($col) => "COALESCE($col, '')", $searchColumns)) . ')) LIKE CONCAT(\'%\', LOWER(' . $searchValue . '), \'%\') ';
            $queryParamsSearch[] = htmlspecialchars_decode($searchWord, ENT_QUOTES | ENT_HTML5);
        }

        $searchCondition = ' WHERE ' . substr($searchCondition, 4);
    }



    // create sql conditions
    $sqlConditions = '';
    $queryParamsConditions = array();

    // adm_log_changes is a global table, so the entries have to be restricted to the current
    // organization. The user tables are shared between all organizations (a user can be a member
    // of several organizations), so their entries stay visible and are protected by the per-user
    // permission check instead. Entries without an organization stay visible, too, so that the
    // audit trail has no gaps.
    $sqlConditions .= ' AND (log_org_id = ? OR log_org_id IS NULL
                             OR log_table IN (\'users\', \'user_data\', \'user_relations\')) ';
    $queryParamsConditions[] = $gCurrentOrgId;

    // Restrict the result to the tables the current user may read. $readableTables is already the
    // intersection of the requested tables and the permitted ones, so this also applies the table
    // filter of the request. Administrators that did not request specific tables see everything,
    // including tables of third-party extensions that are unknown to getTableLabel().
    if (!ChangelogService::hasUnrestrictedAccess($gCurrentUser) || count($getTables) > 0) {
        $sqlConditions .= ' AND log_table IN (' . Database::getQmForValues($readableTables) . ') ';
        $queryParamsConditions = array_merge($queryParamsConditions, $readableTables);
    }

    // Access to the shared user tables is granted per user, so their entries have to be restricted
    // to the one user the current user is allowed to see. Without a restriction the user may read
    // the log of all users (administrators and user administrators).
    $userTableRestriction = ChangelogService::getUserTableRestriction($gCurrentUser, $subject);
    if ($userTableRestriction !== '') {
        $sqlConditions .= ' AND (log_table NOT IN (' . Database::getQmForValues(ChangelogService::$userTables) . ')
                                 OR log_record_uuid = ?) ';
        $queryParamsConditions = array_merge($queryParamsConditions, ChangelogService::$userTables);
        $queryParamsConditions[] = $userTableRestriction;
    }

    if (!is_null($getId) && $getId > 0) {
        $sqlConditions .= ' AND (log_record_id = ? )';
        $queryParamsConditions[] = $getId;
    }
    if (!is_null($getUuid) && $getUuid) {
        $sqlConditions .= ' AND (log_record_uuid = ? )';
        $queryParamsConditions[] = $getUuid;
    }
    if (!is_null($getRelatedId) && $getRelatedId !== '') {
        $sqlConditions .= ' AND (log_related_id = ? )';
        $queryParamsConditions[] = $getRelatedId;
    }



    $mainSql = 'SELECT log_id as id, log_table as table_name,
        log_record_id as record_id, log_record_uuid as uuid, log_record_name as name, log_record_linkid as link_id,
        log_related_id as related_id, log_related_name as related_name,
        log_field as field, log_field_name as field_name,
        log_action as action,
        log_value_new as value_new, log_value_old as value_old,
        log_usr_id_create as usr_id_create, usr_create.usr_uuid as uuid_usr_create, create_last_name.usd_value AS create_last_name, create_first_name.usd_value AS create_first_name,
        log_timestamp_create as timestamp
        FROM ' . TBL_LOG_CHANGES . '
        -- Extract data of the creating user...
        LEFT JOIN '.TBL_USERS.' usr_create
                ON usr_create.usr_id = log_usr_id_create
        LEFT JOIN '.TBL_USER_DATA.' AS create_last_name
                ON create_last_name.usd_usr_id = log_usr_id_create
               AND create_last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        LEFT JOIN '.TBL_USER_DATA.' AS create_first_name
                ON create_first_name.usd_usr_id = log_usr_id_create
               AND create_first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
        WHERE
               log_timestamp_create BETWEEN ? AND ? -- $dateFromIntern and $dateToIntern
        ' . $sqlConditions;

    $queryParams = array_merge([
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $dateFromIntern . ' 00:00:00',
        $dateToIntern . ' 23:59:59',
    ], $queryParamsConditions);

    // $getLength and $getStart are validated as integers and bounded above, so they can be
    // inlined into the statement.
    $limitCondition = ' LIMIT ' . $getLength . ' OFFSET ' . $getStart;

    if ($getSearch === '') {
        // no search condition entered then return all records in dependence of order, limit and offset
        $sql = $mainSql . $orderCondition . $limitCondition;
    } else {
        $sql = 'SELECT *
              FROM (' . $mainSql . ') AS entries
               ' . $searchCondition
            . $orderCondition
            . $limitCondition;
    }
    $queryParamsMain = array_merge($queryParams, $queryParamsSearch);

    $rowNumber = $getStart; // count for every row

    // All permission checks are part of the sql conditions above, so no rows are dropped while
    // building the output and both counts are exact.
    $countTotalStatement = $gDb->queryPrepared('SELECT COUNT(*) FROM (' . $mainSql . ') AS entries', $queryParams);
    $jsonArray['recordsTotal'] = (int)$countTotalStatement->fetchColumn();

    if ($getSearch === '') {
        $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
    } else {
        $countFilteredStatement = $gDb->queryPrepared(
            'SELECT COUNT(*) FROM (' . $mainSql . ') AS entries ' . $searchCondition,
            array_merge($queryParams, $queryParamsSearch)
        );
        $jsonArray['recordsFiltered'] = (int)$countFilteredStatement->fetchColumn();
    }

    $jsonArray['data'] = array();


    $fieldHistoryStatement = $gDb->queryPrepared($sql, $queryParamsMain);

    $fieldStrings = ChangelogService::getFieldTranslations();

    // Item fields of the inventory, only read if the changelog contains inventory item data
    $itemsData = null;

    while ($row = $fieldHistoryStatement->fetch(PDO::FETCH_BOTH)) {
        ++$rowNumber;

        $fieldInfo = $row['field_name'];
        $fieldInfo = array_key_exists($fieldInfo, $fieldStrings) ? $fieldStrings[$fieldInfo] : $fieldInfo;


        $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
        $columnValues    = array('DT_RowId' => 'row_log_' . $row['id'], '0' => $rowNumber);

        // 1. Column showing DB table name (only if more then one tables are shown; One table should be displayed in the headline!)
        if ($showTableColumn) {
            $columnValues[] = ChangelogService::getTableLabel($row['table_name']);
        }


        // 2. Name column: display name and optionally link it with the linkID or the recordID
        //    Some tables need special-casing, though
        $rowLinkId = ($row['link_id']>0) ? $row['link_id'] : $row['record_id'];
        $rowName = $row['name'] ?? '';
        $rowName = Language::translateIfTranslationStrId($rowName);
        if ($row['table_name'] == 'members') {
            $columnValues[] = ChangelogService::createLink($rowName, 'users', $rowLinkId, $row['uuid'] ?? '');
        } else {
            $columnValues[] = ChangelogService::createLink($rowName, $row['table_name'], $rowLinkId, $row['uuid'] ?? '');
        }

        // 3. Optional Related-To column, e.g. for group memberships, we show the user as main name and the group as related
        //    Similarly, files/folders, organizations, guestbook comments, etc. show their parent as related
        if ($showRelatedColumn) {
            $relatedName = $row['related_name'];
            if (!empty($relatedName)) {
                $relatedTable = ChangelogService::getRelatedTable($row['table_name'], $relatedName);
                $relID = 0;
                $relUUID = '';
                $rid = $row['related_id'];
                if (empty($rid)) {
                    // no related id -> no link, but the name still has to be encoded
                    $columnValues[] = SecurityUtils::encodeHTML($relatedName);
                } elseif (ctype_digit($rid)) { // numeric related_ID -> Interpret it as ID
                    $relID = (int)$row['related_id'];
                    $columnValues[] = ChangelogService::createLink($relatedName, $relatedTable, $relID, $relUUID);
                } else { // non-numeric related_ID -> Interpret it as UUID
                    $relUUID = $row['related_id'];
                    $columnValues[] = ChangelogService::createLink($relatedName, $relatedTable, $relID, $relUUID);
                }
            } else {
                $columnValues[] = '';
            }
        }

        // 4. The field that was changed. For record creation/deletion, show an indicator, too.
        if ($row['action'] == "DELETED") {
            $columnValues[] = '<em>['.$gL10n->get('SYS_DELETED').']</em>';
        } elseif ($row['action'] == 'CREATED') {
            $columnValues[] = '<em>['.$gL10n->get('SYS_CREATED').']</em>';
        } elseif (!empty($fieldInfo)) {
            // Note: Even for user fields, we don't want to use the current user field name from the database, but the name stored in the log table from the time the change was done!.
            $fieldName = (is_array($fieldInfo) && isset($fieldInfo['name'])) ? $fieldInfo['name'] : $fieldInfo;
            $columnValues[] = SecurityUtils::encodeHTML(Language::translateIfTranslationStrId($fieldName));
        } else {
            $columnValues[] = '';
        }


        // 5. Show new and old values; For some tables we know further details about formatting
        $valueNew = $row['value_new'];
        $valueOld = $row['value_old'];
        if ($row['table_name'] == 'user_data') {
            // Format the values depending on the user field type:
            $valueNew = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $valueNew);
            $valueOld = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $valueOld);
        } elseif ($row['table_name'] == 'inventory_item_data') {
            // Format the values depending on the item field type, just like the user fields above
            if ($itemsData === null) {
                $itemsData = new ItemsData($gDb, $gCurrentOrgId);
            }
            $valueNew = ChangelogService::formatInventoryItemValue($itemsData, (int) $row['field'], $valueNew);
            $valueOld = ChangelogService::formatInventoryItemValue($itemsData, (int) $row['field'], $valueOld);
        } elseif (is_array($fieldInfo) && isset($fieldInfo['type'])) {
            $valueNew = ChangelogService::formatValue($valueNew, $fieldInfo['type'], $fieldInfo['entries']??[]);
            $valueOld = ChangelogService::formatValue($valueOld, $fieldInfo['type'], $fieldInfo['entries']??[]);
        } else {
            // No type information for this field. The raw database value must never be sent to the
            // DataTable as HTML, so it is passed through formatValue() with an empty type, which
            // strips tags and encodes the value without applying any type specific formatting.
            $valueNew = ChangelogService::formatValue($valueNew, '');
            $valueOld = ChangelogService::formatValue($valueOld, '');
        }

        $columnValues[] = (!empty($valueNew)) ? $valueNew : '&nbsp;';
        $columnValues[] = (!empty($valueOld)) ? $valueOld : '&nbsp;';

        // 6. User and date of the change
        $actorName = ($row['create_last_name'] ?? '') . ', ' . ($row['create_first_name'] ?? '');
        if ($actorName === ', ') {
            $actorName = $gL10n->get('SYS_DELETED_USER');
        } else {
            $actorName = ChangelogService::createLink($actorName, 'users', 0, $row['uuid_usr_create']);
        }
        $columnValues[] = $actorName;
        $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date') . ' ' .$gSettingsManager->getString('system_time'));
        $jsonArray['data'][] = $columnValues;
    }

    // The record counts are determined together with the queries above, because all permission
    // checks are part of the sql conditions and no record can be dropped afterwards.

    // Make sure a notice of a previous draw is hidden!
    $jsonArray['notice']['DT_notice'] = '';

    echo json_encode($jsonArray);
} catch (Throwable $e) {
    // NOTE: DataTables expects the form {'error' => 'message'}, so we can't use the default handleException($e, true); call!
    // The message is inserted into the page by our DataTables error handler, and it can contain
    // data that originates from the request or from the database, so it has to be purified the
    // same way handleException() does. The purifier is only missing if the exception occurred
    // while common.php was still being loaded; in that case fall back to encoding everything.
    $jsonArray['error'] = isset($gHtmlPurifierFilter)
        ? $gHtmlPurifierFilter->purify($e->getMessage())
        : SecurityUtils::encodeHTML($e->getMessage());
    echo json_encode($jsonArray);
    exit();
}
