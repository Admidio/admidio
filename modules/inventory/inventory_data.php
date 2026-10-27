<?php
/**
 ***********************************************************************************************
 * Server side script for Datatables to return the requested inventory list
 *
 * This script is modeled after modules/messages/messages_data.php and returns
 * JSON data for the inventory DataTable. It relies on the InventoryPresenter to
 * prepare the full table data and then performs ordering, searching and paging
 * on that dataset.
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Inventory\Service\InventoryAccessService;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\Users\Entity\User;

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getDraw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
    $getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
    $getSearch = trim(admFuncVariableIsValid($_GET['search'], 'value', 'string'));

    // additional filters from navbar
    $filterString = admFuncVariableIsValid($_GET, 'items_filter_string', 'string', array('defaultValue' => ''));
    $filterCategory = admFuncVariableIsValid($_GET, 'items_filter_category', 'string', array('defaultValue' => ''));
    $filterKeeper = admFuncVariableIsValid($_GET, 'items_filter_keeper', 'int', array('defaultValue' => 0));
    $filterLastReceiver = admFuncVariableIsValid($_GET, 'items_filter_last_receiver', 'string', array('defaultValue' => ''));
    $filterStatus = admFuncVariableIsValid($_GET, 'items_filter_status', 'int', array('defaultValue' => 1));

    $jsonArray = array('draw' => $getDraw);
    header('Content-Type: application/json');

    global $gDb, $gCurrentOrgId, $gSettingsManager, $gProfileFields, $gCurrentUser, $gCurrentSession, $gL10n, $gValidLogin;

    // Apply the same module access restriction as the inventory page before reading any items.
    InventoryPresenter::checkModuleAccess();
    $anonymizeUserNames = !$gValidLogin && $gSettingsManager->getBool('inventory_anonymize_user_names_for_guests');
    if ($anonymizeUserNames) {
        // Do not let crafted data-table requests reveal keeper or borrower identities through
        // matching result counts or result ordering.
        $filterKeeper = 0;
        $filterLastReceiver = '';
    }

    // read item fields to construct column order mapping (same as prepareData())
    $itemsData = new Admidio\Inventory\ValueObjects\ItemsData($gDb, $gCurrentOrgId);
    $itemFields = $itemsData->getItemFields();
    $canUseBulkActions = $gCurrentUser->isAdministratorInventory()
        || InventoryPresenter::isKeeperAuthorizedToEdit((int) $gCurrentUser->getValue('usr_id'));
    $hasActionsColumn = $canUseBulkActions || InventoryAccessService::canRequestReservation();

    // build headerColumns array to map DataTables column index to internal field names
    $headerColumns = array();
    $colIndex = 0;
    if ($canUseBulkActions) {
        $headerColumns[$colIndex++] = 'select';
    }
    // photo column if enabled
    if ($gSettingsManager->GetBool('inventory_item_picture_enabled')) {
        $headerColumns[$colIndex++] = 'photo';
    }
    foreach ($itemFields as $itemField) {
        $infNameIntern = $itemField->getValue('inf_name_intern');
        if ($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($infNameIntern, $itemsData->borrowFieldNames)) {
            continue;
        }
        $headerColumns[$colIndex++] = $infNameIntern;
    }
    if ($hasActionsColumn) {
        $headerColumns[$colIndex++] = 'actions';
    }

    // determine status filter
    // Behavior: items_filter_status == 0 => show all; otherwise show only items with ini_status == items_filter_status
    $sqlWhereStatus = '';
    if ($filterStatus !== 0) {
        $sqlWhereStatus = ' AND i.ini_status = ? ';
    }

    // basic FROM and WHERE parts
    $baseFromTables = ' FROM ' . TBL_INVENTORY_ITEMS . ' i ';
    $baseWhere = ' WHERE (i.ini_org_id IS NULL OR i.ini_org_id = ?) ' . $sqlWhereStatus
        . ' AND EXISTS (SELECT 1 FROM ' . TBL_INVENTORY_ITEM_DATA . ' d WHERE d.ind_ini_id = i.ini_id)';
    $baseParams = array($gCurrentOrgId);
    if ($filterStatus !== 0) {
        // append status parameter corresponding to the placeholder in $sqlWhereStatus
        $baseParams[] = $filterStatus;
    }

    // apply filters (category, keeper, last receiver)
    if ($filterCategory !== '') {
        $baseWhere .= ' AND i.ini_cat_id = (SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_uuid = ? LIMIT 1)';
        $baseParams[] = $filterCategory;
    }
    if ($filterKeeper !== 0) {
        $keeperFieldId = (int) $itemsData->getProperty('KEEPER', 'inf_id');
        if ($keeperFieldId > 0) {
            $baseWhere .= ' AND EXISTS (SELECT 1 FROM ' . TBL_INVENTORY_ITEM_DATA . ' kd WHERE kd.ind_ini_id = i.ini_id AND kd.ind_inf_id = ' . $keeperFieldId . ' AND kd.ind_value = ?)';
            $baseParams[] = (string)$filterKeeper;
        }
    }
    if ($filterLastReceiver !== '') {
        $baseWhere .= ' AND EXISTS (SELECT 1 FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' bd WHERE bd.inb_ini_id = i.ini_id AND bd.inb_last_receiver = ?)';
        $baseParams[] = $filterLastReceiver;
    }

    // count total records (without global search)
    $sqlCount = 'SELECT COUNT(*) as count ' . $baseFromTables . $baseWhere . ';';
    $countStmt = $gDb->queryPrepared($sqlCount, $baseParams);
    $jsonArray['recordsTotal'] = (int)$countStmt->fetchColumn();

    // The borrow table has a unique index on inb_ini_id, so the join keeps one row per item.
    $borrowLatestJoin = ' LEFT JOIN ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' lb ON lb.inb_ini_id = i.ini_id';

    $sqlSelect = 'SELECT i.ini_id, i.ini_uuid, i.ini_cat_id, i.ini_status, lb.inb_borrow_date AS inb_borrow_date, lb.inb_return_date AS inb_return_date, lb.inb_last_receiver AS inb_last_receiver ';

    $searchCondition = $itemsData->getSqlSearchCondition($getSearch, 'i.ini_id');
    $searchWhere = $searchCondition['sql'];
    $queryParams = array_merge($baseParams, $searchCondition['params']);

    // ordering - build an array of SQL expressions mapped to DataTables column indexes
    $orderCondition = '';
    $orderColumns = array();
    $numericOrderColumns = array();
    $sortJoins = array();
    $requestedOrderColumns = array();
    foreach (isset($_GET['order']) && is_array($_GET['order']) ? $_GET['order'] : [] as $order) {
        if (isset($order['column']) && is_numeric($order['column'])) {
            $requestedOrderColumns[(int) $order['column']] = true;
        }
    }
    // When using the joined latest-borrow table, its columns are projected as inb_borrow_date etc.
    foreach ($headerColumns as $idx => $colName) {
        // default: no ordering
        $orderColumns[$idx] = '';
        if (!isset($requestedOrderColumns[$idx])) {
            continue;
        }
        // use case-insensitive matching for special column names
        $colUpper = strtoupper((string)$colName);
        if ($anonymizeUserNames && in_array($colUpper, ['KEEPER', 'LAST_RECEIVER'], true)) {
            continue;
        }
        switch ($colUpper) {
            case 'SELECT':
            case 'PHOTO':
            case 'ACTIONS':
                // not sortable
                break;
            case 'CATEGORY':
                $orderColumns[$idx] = '(SELECT cat_name FROM ' . TBL_CATEGORIES . ' WHERE cat_id = i.ini_cat_id)';
                break;
            case 'STATUS':
                $orderColumns[$idx] = 'ini_status';
                break;
            default:
                // try to find inventory field id for this internal name and order by its value
                // Prefer using the already-read $itemFields to detect field id and type so we can
                // produce DB-specific ordering expressions (dates, numbers) instead of plain strings.
                $infId = 0;
                $infType = '';
                if (array_key_exists($colName, $itemFields)) {
                    $infId = (int)$itemFields[$colName]->getValue('inf_id');
                    $infType = $itemFields[$colName]->getValue('inf_type');
                } elseif (array_key_exists($colUpper, $itemFields)) {
                    $infId = (int)$itemFields[$colUpper]->getValue('inf_id');
                    $infType = $itemFields[$colUpper]->getValue('inf_type');
                } else {
                    // fallback to DB lookup if not present in $itemFields (robustness). The lookup
                    // ignores the case, so the upper case variant needs no second query.
                    $stmtF = $gDb->queryPrepared('SELECT inf_id, inf_type FROM ' . TBL_INVENTORY_FIELDS . ' WHERE UPPER(inf_name_intern) = UPPER(?) AND (inf_org_id = ? OR inf_org_id IS NULL) LIMIT 1', array($colName, $gCurrentOrgId));
                    $rowF = $stmtF->fetch();
                    if ($rowF) {
                        $infId = (int)$rowF['inf_id'];
                        $infType = $rowF['inf_type'];
                    }
                }

                if ($infId > 0) {
                    // If this is a borrow-related field (LAST_RECEIVER, BORROW_DATE, RETURN_DATE)
                    // the values are stored in the borrow table, not in inventory_item_data.
                    $colUpperName = strtoupper((string)$colName);
                    if (in_array($colUpperName, $itemsData->borrowFieldNames, true) || in_array($colName, $itemsData->borrowFieldNames, true)) {
                        // Use the joined latest-borrow table columns (projected as inb_borrow_date etc.)
                        switch ($colUpperName) {
                            case 'BORROW_DATE':
                                $subSel = 'lb.inb_borrow_date';
                                break;
                            case 'RETURN_DATE':
                                $subSel = 'lb.inb_return_date';
                                break;
                            case 'LAST_RECEIVER':
                                $subSel = 'lb.inb_last_receiver';
                                break;
                        }
                    } elseif (in_array($infType, array('NUMBER', 'DECIMAL'), true)) {
                        // The unique (ind_inf_id, ind_ini_id) index keeps one row per item.
                        // Numeric ordering uses the value more than once; read it with one join.
                        $sortAlias = 'sort_data_' . $idx;
                        $sortJoins[] = ' LEFT JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ' . $sortAlias
                            . ' ON ' . $sortAlias . '.ind_ini_id = i.ini_id AND ' . $sortAlias . '.ind_inf_id = ' . $infId;
                        $subSel = $sortAlias . '.ind_value';
                    } else {
                        $subSel = '(SELECT od.ind_value FROM ' . TBL_INVENTORY_ITEM_DATA . ' od WHERE od.ind_ini_id = i.ini_id AND od.ind_inf_id = ' . $infId . ' LIMIT 1)';
                    }
                    // type-aware ordering: numbers should be cast to numeric types so DB sorts them correctly
                    if (in_array($infType, array('NUMBER', 'DECIMAL'), true)) {
                        // Changing a text field to a numeric field can leave non-numeric values.
                        // Only cast numbers, and avoid rounding to four decimal places on MySQL/MariaDB.
                        $numericOperator = DB_TYPE === Database::PDO_ENGINE_PGSQL ? '~' : 'REGEXP';
                        $numericType = DB_TYPE === Database::PDO_ENGINE_PGSQL ? 'NUMERIC' : 'DECIMAL(65,30)';
                        $numericPattern = "'^[+-]?([0-9]+([.][0-9]*)?|[.][0-9]+)([eE][+-]?[0-9]+)?$'";
                        $orderColumns[$idx] = 'CASE WHEN TRIM(' . $subSel . ') ' . $numericOperator . ' ' . $numericPattern
                            . ' THEN CAST(' . $subSel . ' AS ' . $numericType . ') ELSE NULL END';
                        $numericOrderColumns[$idx] = true;
                    } else {
                        // default:ORDER BY raw string value
                        $orderColumns[$idx] = $subSel;
                    }
                }
                break;
        }
    }

    // build ORDER BY statement from DataTables order array
    if (array_key_exists('order', $_GET) && is_array($_GET['order'])) {
        foreach ($_GET['order'] as $order) {
            if (isset($order['column']) && is_numeric($order['column'])) {
                $col = (int)$order['column'];
                $dir = (isset($order['dir']) && strtolower($order['dir']) === 'asc') ? 'ASC' : 'DESC';
                if (array_key_exists($col, $orderColumns) && $orderColumns[$col] !== '') {
                    // MariaDB/MySQL and PostgreSQL have different default ordering of NULLs.
                    $columnOrder = isset($numericOrderColumns[$col]) ? '(' . $orderColumns[$col] . ' IS NULL) ASC, ' : '';
                    $columnOrder .= $orderColumns[$col] . ' ' . $dir;
                    if ($orderCondition === '') {
                        $orderCondition = ' ORDER BY ' . $columnOrder;
                    } else {
                        $orderCondition .= ', ' . $columnOrder;
                    }
                }
            }
        }
    }

    if ($orderCondition === '') {
        $orderCondition = ' ORDER BY i.ini_id ASC ';
    } else {
        // Keep page boundaries stable when the requested columns contain equal values.
        $orderCondition .= ', i.ini_id ASC';
    }

    // limit
    $limitCondition = '';
    if ($getLength > 0) {
        $limitCondition = ' LIMIT ' . (int)$getLength . ' OFFSET ' . (int)$getStart;
    }

    // final SQL
    $sqlMain = $sqlSelect . $baseFromTables . $borrowLatestJoin . implode('', $sortJoins) . $baseWhere . $searchWhere;
    $sql = $sqlMain . $orderCondition . $limitCondition;

    $statement = $gDb->queryPrepared($sql, $queryParams);

    // Create a user object for later use
    $user = new User($gDb, $gProfileFields);

    $itemRows = $statement->fetchAll();
    $itemsData->preloadItemData(array_column($itemRows, 'ini_uuid'));

    $data = array();
    foreach ($itemRows as $row) {
        // Activates the preloaded data without additional queries for this row.
        $itemsData->readItemData($row['ini_uuid']);
        // build row cells same as prepareData('html') for a single item
        $rowValues = array();
        // selection checkbox
        if ($canUseBulkActions) {
            $rowValues[] = ($itemsData->isEditable()) ? '<input type="checkbox" />' : '';
        }
        // photo
        if ($gSettingsManager->GetBool('inventory_item_picture_enabled')) {
            $itemPhotoUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_show', 'item_uuid' => $row['ini_uuid']));
            $itemPhotoModalUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_show_modal', 'item_uuid' => $row['ini_uuid']));
            $rowValues[] = '<a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="' . $itemPhotoModalUrl . '"><img id="adm_inventory_item_picture" class="rounded" style="max-height: 24px; max-width: 24px;" src="' . $itemPhotoUrl . '" alt="" /></a>';
        }

        // add all item field values
        foreach ($itemFields as $itemField) {
            $infNameIntern = $itemField->getValue('inf_name_intern');
            if ($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($infNameIntern, $itemsData->borrowFieldNames)) {
                continue;
            }

            $content = InventoryPresenter::formatItemFieldValue($itemsData, $itemField);

            // Process ITEMNAME column
            if ($infNameIntern === 'ITEMNAME' && !empty($content)) {
                if (($gCurrentUser->isAdministratorInventory() || InventoryPresenter::isKeeperAuthorizedToEdit((int)$itemsData->getValue('KEEPER', 'database'))) && !$itemsData->isRetired()) {
                    $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_edit', 'item_uuid' => $row['ini_uuid'], 'item_retired' => $itemsData->isRetired())) . '">' . SecurityUtils::encodeHTML($content) . '</a>';
                } else {
                    $content = SecurityUtils::encodeHTML($content);
                }
            }

            // Do not reveal names of keepers or borrowers to anonymous guests when configured.
            if (($infNameIntern === 'KEEPER' || $infNameIntern === 'LAST_RECEIVER') && $content !== '') {
                if ($anonymizeUserNames && $content !== '-1') {
                    $content = SecurityUtils::encodeHTML($gL10n->get('SYS_MEMBER'));
                } elseif (is_numeric($content)) {
                    $found = $user->readDataById($content);
                    if (!$found) {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname') . '"';
                        $content = '<i>' . SecurityUtils::encodeHTML(StringUtils::strStripTags($gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', [$orgName]))) . '</i>';
                    } else {
                        $content = '<a href="' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                            ['user_uuid' => $user->getValue('usr_uuid')],
                        ) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                    }
                }
            }

            // If the item is retired then show the field value as struck-through
            if ($itemsData->isRetired()) {
                $content = '<s>' . $content . '</s>';
            }
            $rowValues[] = $content;
        }

        // actions
        $actionsHtml = '';

        // history button (displayHistoryButtonTable returns an array with url/icon/tooltip)
        $historyButton = ChangelogService::displayHistoryButtonTable('inventory_items,inventory_item_data,inventory_item_borrow_data', $gCurrentUser->isAdministratorInventory(), array('uuid' => $row['ini_uuid']));
        if (is_array($historyButton) && !empty($historyButton)) {
            $actionsHtml .= '<a class="admidio-icon-link" href="' . $historyButton['url'] . '"><i class="' . $historyButton['icon'] . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($historyButton['tooltip'], ENT_QUOTES | ENT_HTML5) . '"></i></a>';
        }

        if (!$itemsData->isRetired() && InventoryAccessService::canRequestReservation()) {
            $actionsHtml .= '<a class="admidio-icon-link" aria-label="' . SecurityUtils::encodeHTML($gL10n->get('SYS_RESERVATION_REQUEST')) . '" href="' . SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                ['mode' => 'reservation_request', 'item_uuid' => $row['ini_uuid']],
            ) . '"><i class="bi bi-calendar-plus" data-bs-toggle="tooltip" title="'
                . htmlspecialchars($gL10n->get('SYS_RESERVATION_REQUEST'), ENT_QUOTES | ENT_HTML5) . '"></i></a>';
        }

        $keeperDbId = (int)$itemsData->getValue('KEEPER', 'database');
        $isKeeperAuthorized = InventoryPresenter::isKeeperAuthorizedToEdit($keeperDbId);

        if ($itemsData->isEditable()) {
            if (!$itemsData->isRetired()) {
                // edit action
                $actionsHtml .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_edit', 'item_uuid' => $row['ini_uuid'], 'item_retired' => $itemsData->isRetired())) . '"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . htmlspecialchars($gL10n->get('SYS_INVENTORY_ITEM_EDIT'), ENT_QUOTES | ENT_HTML5) . '"></i></a>';

                // borrow / return action (if borrowing not disabled)
                if (!$gSettingsManager->GetBool('inventory_items_disable_borrowing')) {
                    if (!$itemsData->isBorrowed()) {
                        $item_borrowed = false;
                        $icon = 'bi bi-box-arrow-right';
                        $tooltip = $gL10n->get('SYS_INVENTORY_ITEM_BORROW');
                    } else {
                        $item_borrowed = true;
                        $icon = 'bi bi-box-arrow-in-left';
                        $tooltip = $gL10n->get('SYS_INVENTORY_ITEM_RETURN');
                    }
                    $actionsHtml .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_edit_borrow', 'item_uuid' => $row['ini_uuid'], 'item_borrowed' => $item_borrowed)) . '"><i class="' . $icon . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($tooltip, ENT_QUOTES | ENT_HTML5) . '"></i></a>';
                }

                // copy action
                $actionsHtml .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_edit', 'item_uuid' => $row['ini_uuid'], 'copy' => true)) . '"><i class="bi bi-file-earmark-plus" data-bs-toggle="tooltip" title="' . htmlspecialchars($gL10n->get('SYS_INVENTORY_ITEM_COPY'), ENT_QUOTES | ENT_HTML5) . '"></i></a>';
            } else {
                // reinstate action for retired item
                $dataMessage = ($isKeeperAuthorized) ? $gL10n->get('SYS_INVENTORY_KEEPER_ITEM_REINSTATE_DESC', array('SYS_INVENTORY_KEEPER_ITEM_DELETE_DESC', 'SYS_INVENTORY_ITEM_REINSTATE_CONFIRM')) : $gL10n->get('SYS_INVENTORY_ITEM_REINSTATE_CONFIRM');
                $actionsHtml .= '<a class="admidio-icon-link" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_reinstate', 'item_uuid' => $row['ini_uuid'], 'item_retired' => $itemsData->isRetired())) . '" data-message="' . htmlspecialchars($dataMessage, ENT_QUOTES | ENT_HTML5) . '"><i class="bi bi-eye" data-bs-toggle="tooltip" title="' . htmlspecialchars($gL10n->get('SYS_INVENTORY_ITEM_REINSTATE'), ENT_QUOTES | ENT_HTML5) . '"></i></a>';
            }
        }

        // retire/delete actions depending on rights
        if (!$gCurrentUser->isAdministratorInventory() && $isKeeperAuthorized) {
            if (!$itemsData->isRetired()) {
                // keeper retire action (popup)
                $actionsHtml .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_delete_keeper_explain_msg', 'item_uuid' => $row['ini_uuid'])) . '"><i class="bi bi-trash" data-bs-toggle="tooltip" title="' . htmlspecialchars($gL10n->get('SYS_INVENTORY_ITEM_DELETE'), ENT_QUOTES | ENT_HTML5) . '"></i></a>';
            }
        } elseif ($gCurrentUser->isAdministratorInventory()) {
            // admin delete/retire action
            $actionsHtml .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_delete_explain_msg', 'items_filter_status' => isset($_GET['items_filter_status']) ? $_GET['items_filter_status'] : '', 'item_uuid' => $row['ini_uuid'], 'item_retired' => $itemsData->isRetired())) . '"><i class="bi bi-trash" data-bs-toggle="tooltip" title="' . htmlspecialchars($gL10n->get('SYS_INVENTORY_ITEM_DELETE'), ENT_QUOTES | ENT_HTML5) . '"></i></a>';
        }

        if ($hasActionsColumn) {
            $rowValues[] = $actionsHtml;
        }

        // Build associative row including DT_RowId so DataTables will keep the TR id
        // and client-side scripts can restore selection across paging.
        $rowAssoc = array('DT_RowId' => 'adm_inventory_item_' . $row['ini_uuid']);
        foreach ($rowValues as $colIndex => $colValue) {
            $rowAssoc[(string)$colIndex] = $colValue;
        }

        $data[] = $rowAssoc;
    }

    $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
    // if search was applied, count filtered rows
    if ($getSearch !== '') {
        $countSql = 'SELECT COUNT(*)' . $baseFromTables . $baseWhere . $searchWhere;
        $countStmt = $gDb->queryPrepared($countSql, $queryParams);
        $jsonArray['recordsFiltered'] = (int)$countStmt->fetchColumn();
    }

    $jsonArray['data'] = $data;

    echo json_encode($jsonArray);
} catch (Throwable $e) {
    $jsonArray['error'] = $e->getMessage();
    echo json_encode($jsonArray);
    exit();
}
