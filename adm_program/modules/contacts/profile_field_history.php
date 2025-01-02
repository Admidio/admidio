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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// calculate default date from which the profile fields history should be shown
$filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
$filterDateFrom->modify('-'.$gSettingsManager->getInt('contacts_field_history_days').' day');

// Initialize and check the parameters
$getTable = admFuncVariableIsValid($_GET, 'table','string');
$getTables = ($getTable !== null && $getTable != "") ? explode(",", $getTable) : [];
$getUuid = admFuncVariableIsValid($_GET, 'uuid', 'string');
$getId = admFuncVariableIsValid($_GET, 'id', 'int');
$getRelatedId = admFuncVariableIsValid($_GET, 'related_id', 'int');
$getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
$getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));





// create a user object. Will fill it later if we encounter a user id
$user = new User($gDb, $gProfileFields);

// set headline of the script
if (in_array("users", $getTables)) {
    if ($getUuid) {
        $user->readDataByUuid($getUuid);
    } elseif ($getId) {
        $user->readDataById($getId);
    }
    if ($user->getValue('usr_id')) {
        $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    } else {
        $headline = $gL10n->get('SYS_CHANGE_HISTORY');
    }
// } elseif ($getUuid !== '') {
//     $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
// } elseif ($getRoleId > 0) {
//     $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
} else {
    // TODO_RK: Implement Titles for other types of history
    $headline = $gL10n->get('SYS_CHANGE_HISTORY');  
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
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    // => EXIT
}

$dateFromIntern = $objDateFrom->format('Y-m-d');
$dateFromHtml   = $objDateFrom->format($gSettingsManager->getString('system_date'));
$dateToIntern   = $objDateTo->format('Y-m-d');
$dateToHtml     = $objDateTo->format($gSettingsManager->getString('system_date'));

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





// TODO: Snippet for Role link:
    // if ($gCurrentUser->hasRightViewRole((int) $member->getValue('mem_rol_id'))) {
    //     $roleMemHTML .= '<a href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => (int) $member->getValue('mem_rol_id'))). '" title="'. $role->getValue('rol_description'). '">'. $role->getValue('rol_name'). '</a>';
    // } else {
    //     $roleMemHTML .= $role->getValue('rol_name');
    // }


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
    ORDER BY `timestamp` DESC';

$queryParams = [
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $dateFromIntern . ' 00:00:00',
    $dateToIntern . ' 23:59:59',
];


function createLink(string $text, string $module, int $id, string $uuid) {
    $url = '';
    switch ($module) {
        case 'users': // Fall through
        case 'user_data':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $uuid)); break;
        case 'announcements':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('ann_uuid' => $uuid)); break;
        case 'categories' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('cat_uuid' => $uuid, 'type' => '{TYPE}')); break; // TODO_RK: Implement type!
        case 'category_report' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences.php'); break;
        case 'events' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/events/events_new.php', array('dat_uuid' => $uuid)); break;
        case 'files' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/documents-files/rename.php', array('file_uuid' => $uuid)); break;
        case 'folders' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/documents-files/folder_new.php', array('folder_uuid' => $uuid)); break;
        case 'guestbook' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_new.php', array('gbo_uuid' => $uuid)); break;
        case 'guestbook_comments' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php', array('gbc_uuid' => $uuid)); break;
        case 'links' :
            $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php', array('link_uuid' => $uuid)); break;
        case 'lists' :
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', array('active_role' => 1, 'list_uuid' => $uuid)); break;
        case 'list_columns':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', array('active_role' => 1, 'list_uuid' => $uuid)); break;
        case 'members':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => $id)); break;
        case 'menu':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/menu/menu_new.php', array('menu_uuid' => $uuid)); break;
        // case 'messages': 
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'messages_attachments':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'messages_content':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'messages_recipients':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'organizations':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'photos':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'preferences':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'registrations':
        //     $url = SecurityUtils::encodeUrl(); break;
        case 'roles':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => $id)); break;
        case 'roles_rights':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $uuid)); break;
        case 'roles_rights_data':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $uuid)); break;
        case 'role_dependencies':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $uuid)); break;
        // case 'rooms':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'texts':
        //     $url = SecurityUtils::encodeUrl(); break;
        case 'user_fields':
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php', array('usf_uuid' => $uuid)); break;
        // case 'user_relations':
        //     $url = SecurityUtils::encodeUrl(); break;
        // case 'user_relation_types':
        //     $url = SecurityUtils::encodeUrl(); break;
    }
    if ($url != '') {
        return '<a href="'.$url.'">'.$text.'</a>';
    } else {
        return $text;
    }
}



// print_r("<pre>SQL: 
// $sql
// </pre><pre>PARAMS:
// ");
// print_r(array_merge($queryParams, $queryParamsConditions));
// print_r('</pre>');

$fieldHistoryStatement = $gDb->queryPrepared($sql, array_merge($queryParams, $queryParamsConditions));

if ($fieldHistoryStatement->rowCount() === 0) {
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();

    // show message if there were no changes
    $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED'));
}

// create html page object
$page = new HtmlPage('admidio-history', $headline);

// create filter menu with input elements for start date and end date
$filterNavbar = new HtmlNavbar('menu_history_filter', '', null, 'filter');
$form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/changelog/changelog.php', $page, array('type' => 'navbar', 'setFocus' => false));
$form->addInput('table', '', $getTable, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addInput('uuid', '', $getUuid, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addInput('id', '', $getId, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addInput('related_id', '', $getRelatedId, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
$form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
$filterNavbar->addForm($form->show());
$page->addHtml($filterNavbar->show());

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
$columnHeading[] = $gL10n->get('SYS_TABLE');
$columnHeading[] = $gL10n->get('SYS_NAME');
$columnHeading[] = $gL10n->get('SYS_RELATED_TO');
$columnHeading[] = $gL10n->get('SYS_FIELD');
// TODO_RK: Shall we use / show the log_action column in a separate output column?
$columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
$columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
$columnHeading[] = $gL10n->get('SYS_EDITED_BY');
$columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

$table->addRowHeadingByArray($columnHeading);

while ($row = $fieldHistoryStatement->fetch()) {
    $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
    $columnValues    = array();

    $columnValues[] = $row['table_name'];
    // if ($getUserUuid === '') {
    //     $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr'])).'">'.$row['last_name'].', '.$row['first_name'].'</a>';
    // }


    $columnValues[] = createLink($row['name'], ($row['table_name']!='members')? $row['table_name'] : 'users', ($row['link_id']>0)?$row['link_id']:$row['record_id'], $row['uuid']); // TODO_RK: Use record_id and/or record_uuid and/or link_id to link to the record

    $columnValues[] = ($row['related_name'] != '') ? createLink($row['related_name'], ($row['table_name'] == 'members')?"roles":$row['table_name'], ($row['related_id'] > 0)?$row['related_id']:0, $row['uuid']) : ''; // TODO_RK: Use related_id to link to the related record

    if ($row['action'] == "DELETED") {
        $columnValues[] = '<em>['.$gL10n->get('SYS_DELETED').']</em>';
    } elseif ($row['action'] == 'CREATED') {
        $columnValues[] = '<em>['.$gL10n->get('SYS_CREATED').']</em>';
    } elseif ($row['field_name'] != '') {
        // Note: Even for user fields, we don't want to use the current user field name from the database, but the name stored in the log table from the time the change was done!.
        $columnValues[] = $gL10n->get($row['field_name']); // TODO_RK: Use field_id to link to the field
    } else {
        $columnValues[] = '&nbsp;';
    }
    if ($row['table_name'] == 'user_data') {
        // Format the values depending on the user field type:
        $valueNew = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $row['value_new']);
        $valueOld = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $row['value_old']);
    } else {
        $valueNew = $row['value_new'];
        $valueOld = $row['value_old'];
    }

    if ($valueNew !== '') {
        $columnValues[] = $valueNew;
    } else {
        $columnValues[] = '&nbsp;';
    }

    if ($valueOld !== '') {
        $columnValues[] = $valueOld;
    } else {
        $columnValues[] = '&nbsp;';
    }

    $columnValues[] = createLink($row['create_last_name'].', '.$row['create_first_name'], 'users', 0, $row['uuid_usr_create']);
    // $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr_create'])).'">'..'</a>';
    $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show());
$page->show();
