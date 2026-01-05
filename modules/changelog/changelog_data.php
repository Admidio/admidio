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
 * This script will read all requested change history fecords from the database. It is optimized to
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
use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User;
use Admidio\Changelog\Service\ChangelogService;





try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // calculate default date from which the profile fields history should be shown
    $filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
    $filterDateFrom->modify('-' . $gSettingsManager->getInt('contacts_field_history_days') . ' day');


    // Initialize and check the parameters
    $getTable = admFuncVariableIsValid($_GET, 'table','string');
    $getTables = ($getTable !== null && $getTable != "") ? array_map('trim', explode(",", $getTable)) : [];
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'string');
    $getId = admFuncVariableIsValid($_GET, 'id', 'int');
    $getRelatedId = admFuncVariableIsValid($_GET, 'related_id', 'string');
    $getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
    $getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

    # Datatables parameters
    $getDraw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
    $getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
    $getSearch = admFuncVariableIsValid($_GET['search'], 'value', 'string');


    $jsonArray = array('draw' => (int)$getDraw);

    header('Content-Type: application/json');




    $haveID = !empty($getId) || !empty($getUuid);

    // named array of permission flag (true/false/"user-specific" per table)
    $tablesPermitted = ChangelogService::getPermittedTables($gCurrentUser);
    if ($gSettingsManager->getInt('changelog_module_enabled') == 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }
    if ($gSettingsManager->getInt('changelog_module_enabled') == 2 && !$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }
    $accessAll = $gCurrentUser->isAdministrator() ||
        (!empty($getTables) && empty(array_diff($getTables, $tablesPermitted)));

    // create a user object. Will fill it later if we encounter a user id
    $user = new User($gDb, $gProfileFields);
    $userUuid = null;
    // User log contains at most four tables: User, user_data, user_relations and members -> they have many more permissions than other tables!
    $isUserLog = (!empty($getTables) && empty(array_diff($getTables, ['users', 'user_data', 'user_relations', 'members'])));
    if ($isUserLog) {
        if (!empty($getUuid)) {
            $user->readDataByUuid($getUuid);
        } elseif (!empty($getId)) {
            $user->readDataById($getId);
        }
        if (!$user->isNewRecord()) {
            $userUuid = $user->getValue('usr_uuid');
        }
    }

    // Access permissions:
    // Special case: Access to profile history on a per-user basis: Either admin or at least edit user rights are required, or explicit access to the desired user:
    if (!$accessAll &&
            !(!empty($getTables) && empty(array_diff($getTables, $tablesPermitted))) &&
            $isUserLog) {
        // If a user UUID is given, we need access to that particular user
        // if no UUID is given, isAdministratorUsers permissions are required
        if (($userUuid === '' && !$gCurrentUser->isAdministratorUsers())
            || ($userUuid !== '' && !$gCurrentUser->hasRightEditProfile($user))) {
//                throw new Exception('SYS_NO_RIGHTS');
                $gMessage->show(content: $gL10n->get('SYS_NO_RIGHTS'));
       }
    }



    // filter_date_from and filter_date_to can have different formats
    // now we try to get a default format for intern use and html output
    $objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
    if ($objDateFrom === false) {
        // check if date has system format
        $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom);
        if ($objDateFrom === false) {
            $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
        }
    }

    $objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
    if ($objDateTo === false) {
        // check if date has system format
        $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo);
        if ($objDateTo === false) {
            $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
        }
    }

    // DateTo should be greater than DateFrom
    if ($objDateFrom > $objDateTo) {
        throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
    }

    $dateFromIntern = $objDateFrom->format('Y-m-d');
    $dateFromHtml = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $dateToIntern = $objDateTo->format('Y-m-d');
    $dateToHtml = $objDateTo->format($gSettingsManager->getString('system_date'));


    // create order statement
    $orderCondition = '';
    // $orderColumns = array_merge(array('no', 'member_this_orga'), $contactsListConfig->getColumnNamesSql());

    // if (array_key_exists('order', $_GET)) {
    //     foreach ($_GET['order'] as $order) {
    //         if (is_numeric($order['column'])) {
    //             if ($orderCondition === '') {
    //                 $orderCondition = ' ORDER BY ';
    //             } else {
    //                 $orderCondition .= ', ';
    //             }

    //             if (strtoupper($order['dir']) === 'ASC') {
    //                 $orderCondition .= $orderColumns[$order['column']] . ' ASC ';
    //             } else {
    //                 $orderCondition .= $orderColumns[$order['column']] . ' DESC ';
    //             }
    //         }
    //     }
    // } else {
    // }

    // create search conditions
    $searchCondition = '';
    $queryParamsSearch = array();
    // $searchColumns = array('log_table', 'log_record_name', 'log_related_name', 'log_field', 'log_field_name', 'log_action', 'log_value_old', 'log_value_new', 'create_first_name', 'create_last_name');
    $searchColumns = array('table_name', 'name', 'related_name', 'field', 'field_name', 'action', 'value_old', 'value_new', 'create_first_name', 'create_last_name');

    if ($getSearch !== '' && count($searchColumns) > 0) {
        $searchString = explode(' ', $getSearch);

        if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
            $searchValue = ' ?::text ';
        } else {
            // mysql
            $searchValue = ' ? ';
        }

        foreach ($searchString as $searchWord) {
            $searchCondition .= ' AND CONCAT(' . implode(', \' \', ', array_map(fn($col) => "COALESCE($col, '')", $searchColumns)) . ') LIKE LOWER(CONCAT(\'%\', ' . $searchValue . ', \'%\')) ';
            $queryParamsSearch[] = htmlspecialchars_decode($searchWord, ENT_QUOTES | ENT_HTML5);
        }

        $searchCondition = ' WHERE ' . substr($searchCondition, 4);
    }



    // create sql conditions
    $sqlConditions = '';
    $queryParamsConditions = array();

    if (!is_null($getTables) && count($getTables) > 0) {
        // Add each table as a separate condition, joined by OR:
        $sqlConditions .= ' AND ( ' .  implode(' OR ', array_map(fn($tbl) => 'log_table = ?', $getTables)) . ' ) ';
        $queryParamsConditions = array_merge($queryParamsConditions, $getTables);
    }

    if (!is_null($getId) && $getId > 0) {
        $sqlConditions .= ' AND (log_record_id = ? )';
        $queryParamsConditions[] = $getId;
    }
    if (!is_null($getUuid) && $getUuid) {
        $sqlConditions .= ' AND (log_record_uuid = ? )';
        $queryParamsConditions[] = $getUuid;
    }
    if (!is_null($getRelatedId) && $getRelatedId > 0) {
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
        INNER JOIN '.TBL_USERS.' usr_create
                ON usr_create.usr_id = log_usr_id_create
        INNER JOIN '.TBL_USER_DATA.' AS create_last_name
                ON create_last_name.usd_usr_id = log_usr_id_create
               AND create_last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN '.TBL_USER_DATA.' AS create_first_name
                ON create_first_name.usd_usr_id = log_usr_id_create
               AND create_first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
        WHERE
               log_timestamp_create BETWEEN ? AND ? -- $dateFromIntern and $dateToIntern
        ' . $sqlConditions . '
        ORDER BY log_id DESC';

    $queryParams = array_merge([
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $dateFromIntern . ' 00:00:00',
        $dateToIntern . ' 23:59:59',
    ], $queryParamsConditions);

    $limitCondition = '';
    if ($getLength > 0) {
        $limitCondition = ' LIMIT ' . $getLength . ' OFFSET ' . $getStart;
    }

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
    $logStatement = $gDb->queryPrepared($sql, $queryParamsMain); // TODO add more params

    $rowNumber = $getStart; // count for every row

    // get count of all members and store into json
    $countSql = 'SELECT COUNT(*) AS count_total FROM (' . $mainSql . ') as entries ';
    $countTotalStatement = $gDb->queryPrepared($countSql, $queryParams); // TODO add more params
    $jsonArray['recordsTotal'] = (int)$countTotalStatement->fetchColumn();

    $jsonArray['data'] = array();





    $fieldHistoryStatement = $gDb->queryPrepared($sql, $queryParamsMain);

    // Logic for hiding certain columns:
    // If we have only one table name given, hide the table column
    // If we view the user profile field changes page, hide the column, too
    $showTableColumn = true;
    if (count($getTables) == 1) {
        $showTableColumn = false;
    }
    // If none of the related-to values is set, hide the related_to column
    $showRelatedColumn = true;
    $noShowRelatedTables = ['user_fields', 'user_field_select_options', 'users', 'user_data'];


    $fieldStrings = ChangelogService::getFieldTranslations();
    $recordsHidden = 0;

    while ($row = $fieldHistoryStatement->fetch(PDO::FETCH_BOTH)) {
        ++$rowNumber;
        $rowTable = $row['table_name'];

        $allowRecordAccess = false;
        // First step: Check view permissions to that particular log entry:
        if ($accessAll || in_array($rowTable, $tablesPermitted)) {
            $allowRecordAccess = true;
        } else {
            // no global access permissions to that particular data/table
            // Some objects have more fine-grained permissions (e.g. each group can have access permissions
            // based on the user's role -> the calling user might have access to one particular role, but not in general)
            if (in_array($rowTable, ['users', 'user_data', 'user_relations', 'members'])) {
                // user UUID is available as uuid; current user has no general access to profile data, but might have permissions to this specific user (due to fole permissions)
                $rowUser = new User($gDb, $gProfileFields);
                $rowUser->readDataByUuid($row['uuid']);
                if ($gCurrentUser->hasRightEditProfile($rowUser)) {
                    $allowRecordAccess = true;
                }
            }
            // NO access to this record allowed -> Set flag to show warning about records being
            // hidden due to insufficient permissions
            if (!$allowRecordAccess) {
                ++$recordsHidden;
                continue;
            }
        }

        $fieldInfo = $row['field_name'];
        $fieldInfo = array_key_exists($fieldInfo, $fieldStrings) ? $fieldStrings[$fieldInfo] : $fieldInfo;


        $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
        $columnValues    = array('DT_RowId' => 'row_log_' . $row['id'], '0' => $rowNumber);
        $columnNumber    = 1;

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
                    // do nothing
                    $columnValues[] = $relatedName;
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
            $columnValues[] = Language::translateIfTranslationStrId($fieldName);
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
        } elseif (is_array($fieldInfo) && isset($fieldInfo['type'])) {
            $valueNew = ChangelogService::formatValue($valueNew, $fieldInfo['type'], $fieldInfo['entries']??[]);
            $valueOld = ChangelogService::formatValue($valueOld, $fieldInfo['type'], $fieldInfo['entries']??[]);
        }

        $columnValues[] = (!empty($valueNew)) ? $valueNew : '&nbsp;';
        $columnValues[] = (!empty($valueOld)) ? $valueOld : '&nbsp;';

        // 6. User and date of the change
        $columnValues[] = ChangelogService::createLink($row['create_last_name'].', '.$row['create_first_name'], 'users', 0, $row['uuid_usr_create']);
        // $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr_create'])).'">'..'</a>';
        $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date') . ' ' .$gSettingsManager->getString('system_time'));
        $jsonArray['data'][] = $columnValues;
    }

    // set count of filtered records
    if ($getSearch !== '') {
        if ($rowNumber < $getStart + $getLength) {
            $jsonArray['recordsFiltered'] = $rowNumber;
        } else {
            // read count of all filtered records without limit and offset
            $sql = 'SELECT COUNT(*) AS count
                  FROM (' . $mainSql . ') AS members
                       ' . $searchCondition;
            $countFilteredStatement = $gDb->queryPrepared($sql, $queryParams);

            $jsonArray['recordsFiltered'] = (int)$countFilteredStatement->fetchColumn();
        }
    } else {
        $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
    }

    if ($recordsHidden > 0) {
        $jsonArray['notice']['DT_notice'] = '<i class="bi bi-exclamation-circle-fill"></i>' .
            $gL10n->get('SYS_LOG_RECORDS_HIDDEN', [$recordsHidden]);
    } else {
        // Make sure the notice is hidden!
        $jsonArray['notice']['DT_notice'] = '';
    }

    echo json_encode($jsonArray);
} catch (Throwable $e) {
    // NOTE: DataTables expects the form {'error' => 'message'}, so we can't use the default handleException($e, true); call!
    $jsonArray['error'] = $e->getMessage();
    echo json_encode($jsonArray);
    exit();
}
