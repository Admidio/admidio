<?php
/**
 ***********************************************************************************************
 * Show history of generic database record changes
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
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
 ***********************************************************************************************
 */

 use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\UI\Component\Form;
use Admidio\Users\Entity\User;
use Admidio\UI\View\Changelog;
use Admidio\Roles\Entity\Role;





try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

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



    // create a user object. Will fill it later if we encounter a user id
    $user = new User($gDb, $gProfileFields);
    $isUserLog = ($getTables == ['users', 'user_data', 'members']);
    $haveID = !empty($getId) || !empty($getUuid);
    if (!empty($getUuid)) {
        $user->readDataByUuid($getUuid);
    } elseif (!empty($getId)) {
        $user->readDataById($getId);
    }

    // Page Headline: Depending on the tables and ID/UUID/RelatedIDs, we have different cases:
    //  * Userlog (tables users,user_data,members): Either "Change history of NAME" or "Change history of user data and memberships" (if no ID/UUID)
    //  * No object ID/UUIDs given: "Change history: Table description 1[, Table description 2, ...]" or "Change history"  (if no tables given)
    //  * Only one table (table column will be hidden): "Change history: OBJECTNAME (Table description)"
    //  * 
    $tableTitles = array_map([ChangeLog::class, 'getTableLabel'], $getTables);
    // set headline of the script
    if ($isUserLog && $haveID) {
        $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->readableName()));
    } elseif ($isUserLog) {
        $headline = $gL10n->get('SYS_CHANGE_HISTORY_USERDATA');
    } elseif (empty($getUuid) && empty($getId) && empty($getRelatedId)) {
        if (count($tableTitles) > 0) {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY_GENERIC', [implode(', ', $tableTitles)]);
        } else {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY');
        }
    } else {
        $objName = '';
        $useTable = $getTables[0]??'users';
        $object = Changelog::getObjectForTable($useTable);
        if ($useTable == 'members') {
            // Memberships are special-cased, as the membership Role UUID is stored as relatedID
            $object = new Role($gDb);
            $object->readDataByUuid($getRelatedId);
        }
        // We have an ID or UUID and/or a relatedID -> Object depends on the table(s)!
        if (!empty($object)) {
            if ($useTable == 'members') {
                // already handled
            } elseif (!empty($getUuid)) {
                $object->readDataByUuid($getUuid);
            } elseif (!empty($getId)) {
                $object->readDataById($getId);
            }
            $objName = $object->readableName();
        }
        if (count($getTables) == 0) {
            if (empty($objName)) {
                $headline = $gL10n->get('SYS_CHANGE_HISTORY');
            } else {
                $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', [$objName]);
            }
        } else {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY_GENERIC2', [$objName, implode(', ', $tableTitles)]);
        }
    }

    // if profile log is activated and current user is allowed to edit users
    // then the profile field history will be shown otherwise show error
    // TODO_RK: Which user shall be allowed to view the history (probably depending on the type the table)
    if (!$gSettingsManager->getBool('profile_log_edit_fields')
        || ($getUuid === '' && !$gCurrentUser->editUsers())
        || ($getUuid !== '' && !$gCurrentUser->hasRightEditProfile($user))) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }



    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

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


    // create sql conditions
    $sqlConditions = '';
    $queryParamsConditions = array();

    if (!is_null($getTables) && count($getTables) > 0) {
        // Add each table as a separate condition, joined by OR:
        $sqlConditions .= ' AND ( ' .  implode(' OR ', array_map(fn($tbl) => '`log_table` = ?', $getTables)) . ' ) ';
        $queryParamsConditions = array_merge($queryParamsConditions, $getTables);
    }

    if (!is_null($getId) && $getId > 0) {
        $sqlConditions .= ' AND (`log_record_id` = ? )';
        $queryParamsConditions[] = $getId;
    }
    if (!is_null($getUuid) && $getUuid) {
        $sqlConditions .= ' AND (`log_record_uuid` = ? )';
        $queryParamsConditions[] = $getUuid;
    }
    if (!is_null($getRelatedId) && $getRelatedId > 0) {
        $sqlConditions .= ' AND (`log_related_id` = ? )';
        $queryParamsConditions[] = $getRelatedId;
    }



    $sql = 'SELECT log_id as id, log_table as table_name, 
        log_record_id as record_id, log_record_uuid as uuid, log_record_name as name, log_record_linkid as link_id,
        log_related_id as related_id, log_related_name as related_name,
        log_field as field, log_field_name as field_name, 
        log_action as action,
        log_value_new as value_new, log_value_old as value_old, 
        log_usr_id_create as usr_id_create, usr_create.usr_uuid as uuid_usr_create, create_last_name.usd_value AS create_last_name, create_first_name.usd_value AS create_first_name, 
        log_timestamp_create as timestamp
        FROM ' . TBL_LOG . ' 
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
               `log_timestamp_create` BETWEEN ? AND ? -- $dateFromIntern and $dateToIntern
        ' . $sqlConditions . '
        ORDER BY `log_id` DESC';

    $queryParams = [
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $dateFromIntern . ' 00:00:00',
        $dateToIntern . ' 23:59:59',
    ];




    $fieldHistoryStatement = $gDb->queryPrepared($sql, array_merge($queryParams, $queryParamsConditions));

    if ($fieldHistoryStatement->rowCount() === 0) {
        // message is shown, so delete this page from navigation stack
        $gNavigation->deleteLastUrl();

        // show message if there were no changes
        $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED'));
    }

    // create html page object
    $page = new HtmlPage('admidio-history', $headline);

    // Logic for hiding certain columns:
    // If we have only one table name given, hide the table column
    // If we view the user profile field changes page, hide the column, too
    $showTableColumn = true;
    if (count($getTables) == 1) {
        $showTableColumn = false;
    }
    // If none of the related-to values is set, hide the related_to column
    $showRelatedColumn = true;
    $noShowRelatedTables = ['user_fields', 'users', 'user_data'];


    $form = new Form(
        'adm_navbar_filter_form',
        'sys-template-parts/form.filter.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/changelog.php',
        $page,
        array('type' => 'navbar', 'setFocus' => false)
    );

    // create filter menu with input elements for start date and end date
    $form->addInput('table', '', $getTable, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('uuid', '', $getUuid, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('id', '', $getId, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('related_id', '', $getRelatedId, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_OK'));
    $form->addToHtmlPage();

    $table = new HtmlTable('history_table', $page, true, true);


    /* For now, simply show all column of the changelog table. As time permits, we can improve this by hiding unneccessary columns and by better naming columns depending on the table.
     * 
     * Columns to be displayed / hidden:
     *   0. If there is only one value in the table column, hide it and display it in the title of the page.
     *   1. If there is a single ID or UUID, the record name is not displayed. It should be shown in the title of the page.
     *   2. If there is a single related-to ID, and the table is memberships, the role name should already be displayed in the title, so don't show it again.
     *   3. If none of the entries have a related ID, hide the related ID column.
     */
    $columnHeading = array();

    $table->setDatatablesOrderColumns(array(array(8, 'desc')));
    if ($showTableColumn) {
        $columnHeading[] = $gL10n->get('SYS_TABLE');
    }
    $columnHeading[] = $gL10n->get('SYS_NAME');
    if ($showRelatedColumn) {
        $columnHeading[] = $gL10n->get('SYS_RELATED_TO');
    }
    $columnHeading[] = $gL10n->get('SYS_FIELD');
    $columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
    $columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
    $columnHeading[] = $gL10n->get('SYS_EDITED_BY');
    $columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

    $table->addRowHeadingByArray($columnHeading);

    $fieldString = Changelog::getFieldTranslations();
    while ($row = $fieldHistoryStatement->fetch()) {
        $fieldInfo = $row['field_name'];
        $fieldInfo = array_key_exists($fieldInfo, $fieldString) ? $fieldString[$fieldInfo] : $fieldInfo;


        $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
        $columnValues    = array();

        // 1. Column showing DB table name (only if more then one tables are shown; One table should be displayed in the headline!)
        if ($showTableColumn) {
            $columnValues[] = Changelog::getTableLabel($row['table_name']);
        }


        // 2. Name column: display name and optionally link it with the linkID or the recordID 
        //    Some tables need special-casing, though
        $rowLinkId = ($row['link_id']>0) ? $row['link_id'] : $row['record_id'];
        $rowName = $row['name'] ?? '';
        $rowName = Language::translateIfTranslationStrId($rowName);
        if ($row['table_name'] == 'members') {
            $columnValues[] = Changelog::createLink($rowName, 'users', $rowLinkId, $row['uuid'] ?? '');
        } else {
            $columnValues[] = Changelog::createLink($rowName, $row['table_name'], $rowLinkId, $row['uuid'] ?? '');
        }

        // 3. Optional Related-To column, e.g. for group memberships, we show the user as main name and the group as related
        //    Similarly, files/folders, organizations, guestbook comments, etc. show their parent as related
        if ($showRelatedColumn) {
            $relatedName = $row['related_name'];
            $relatedTable = $row['table_name'];
            if ($row['table_name'] == 'members') {
                $relatedTable = 'roles';
            }
            if ($row['table_name'] == 'guestbook_comments') {
                $relatedTable = 'guestbook';
            }
            if ($row['table_name'] == 'files') {
                $relatedTable = 'folders';
            }
            if ($row['table_name'] == 'roles_rights_data') {
                $relatedTable = 'roles';
            }
            if ($row['table_name'] == 'list_columns') {
                // The related item is either a user field or a column name mem_ or usr_ -> in the latter case, convert it to a translatable string and translate
                if (!empty($relatedName) && (str_starts_with($relatedName, 'mem_') || str_starts_with($relatedName, 'usr_'))) {
                    $relatedName = $fieldString[$relatedName];
                    if (is_array($relatedName)) {
                        $relatedName = $relatedName['name'];
                    }
                    $relatedName = Language::translateIfTranslationStrId($relatedName);
                }
                $relatedTable = 'user_fields';
            }
            if (!empty($relatedName)) {
                $relID = 0;
                $relUUID = '';
                $rid = $row['related_id'];
                if (empty($rid)) {
                    // do nothing
                    $columnValues[] = $relatedName;
                } elseif (ctype_digit($rid)) { // numeric related_ID -> Interpret it as ID
                    $relID = (int)$row['related_id'];
                    $columnValues[] = Changelog::createLink($relatedName, $relatedTable, $relID, $relUUID);
                } else { // non-numeric related_ID -> Interpret it as UUID
                    $relUUID = $row['related_id'];
                    $columnValues[] = Changelog::createLink($relatedName, $relatedTable, $relID, $relUUID);
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
            $columnValues[] = Language::translateIfTranslationStrId($fieldName); // TODO_RK: Use field_id to link to the field -> Target depends on the table!!!!
        } else {
            $table->setDatatablesOrderColumns(array(array(5, 'desc')));
        }


        // 5. Show new and old values; For some tables we know further details about formatting
        $valueNew = $row['value_new'];
        $valueOld = $row['value_old'];
        if ($row['table_name'] == 'user_data') {
            // Format the values depending on the user field type:
            $valueNew = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $valueNew);
            $valueOld = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $valueOld);
        } elseif (is_array($fieldInfo) && isset($fieldInfo['type'])) {
            $valueNew = Changelog::formatValue($valueNew, $fieldInfo['type'], $fieldInfo['entries']??[]);
            $valueOld = Changelog::formatValue($valueOld, $fieldInfo['type'], $fieldInfo['entries']??[]);
        }

        $columnValues[] = (!empty($valueNew)) ? $valueNew : '&nbsp;';
        $columnValues[] = (!empty($valueOld)) ? $valueOld : '&nbsp;';

        // 6. User and date of the change
        $columnValues[] = Changelog::createLink($row['create_last_name'].', '.$row['create_first_name'], 'users', 0, $row['uuid_usr_create']);
        // $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr_create'])).'">'..'</a>';
        $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
        $table->addRowByArray($columnValues);
    }

    $page->addHtml($table->show());
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
