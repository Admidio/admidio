<?php
/**
 ***********************************************************************************************
 * Show history of profile field changes
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid        : If set only show the profile field history of that user
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
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
$getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
$getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

// create a user object from the user parameter
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

// set headline of the script
if ($getUserUuid !== '') {
    $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
} else {
    $headline = $gL10n->get('SYS_CHANGE_HISTORY');
}

// if profile log is activated and current user is allowed to edit users
// then the profile field history will be shown otherwise show error
if (!$gSettingsManager->getBool('profile_log_edit_fields')
    || ($getUserUuid === '' && !$gCurrentUser->editUsers())
    || ($getUserUuid !== '' && !$gCurrentUser->hasRightEditProfile($user))) {
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
if ($getUserUuid !== '') {
    $sqlConditions = ' AND usr_log.usr_id = :userID -- $user->getValue(\'usr_id\')';
    $queryParamsConditions = array('userID' => $user->getValue('usr_id'));
}


// create select statement with all necessary data
// First, join thw TBL_USER_LOG and TBL_USERS_PROFILE_LOG
// Then extract the name for the given user IDs from the user data fields
$sql = 'SELECT usr_log.usr_id as usr_id, usr.usr_uuid as uuid_usr, last_name.usd_value AS last_name, first_name.usd_value AS first_name, field,
               value_old, value_new, usr_id_create, usr_create.usr_uuid AS uuid_usr_create, create_last_name.usd_value AS create_last_name,
               create_first_name.usd_value AS create_first_name, timestamp, type
FROM (
    SELECT
        usl_usr_id AS usr_id,
        usl_usf_id AS field,
        usl_value_old AS value_old,
        usl_value_new AS value_new,
        usl_usr_id_create as usr_id_create,
        usl_timestamp_create as timestamp,
        \'Field\' as type
    FROM '.TBL_USER_LOG.'
    WHERE
            usl_timestamp_create BETWEEN :uslTimeFrom AND :uslTimeUntil
        AND usl_usf_id in (' .  implode(',', $gProfileFields->getVisibleArray(true)) . ')
UNION
    SELECT
        upl_usr_id AS usr_id,
        upl_profile_field AS field,
        upl_value_old AS value_old,
        upl_value_new AS value_new,
        upl_usr_id_create AS usr_id_create,
        upl_timestamp_create as timestamp,
        \'Profile\' as type
    FROM '.TBL_USERS_PROFILE_LOG.'
    WHERE
            upl_timestamp_create BETWEEN :uplTimeFrom AND :uplTimeUntil
) AS usr_log
    INNER JOIN '.TBL_USERS.' usr_create ON usr_create.usr_id = usr_log.usr_id_create
    INNER JOIN '.TBL_USERS.' usr ON usr.usr_id = usr_log.usr_id
    INNER JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_log.usr_id
           AND last_name.usd_usf_id = :lastName
    INNER JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_log.usr_id
           AND first_name.usd_usf_id = :firstName
    INNER JOIN '.TBL_USER_DATA.' AS create_last_name
            ON create_last_name.usd_usr_id = usr_log.usr_id_create
           AND create_last_name.usd_usf_id = :createLastName
    INNER JOIN '.TBL_USER_DATA.' AS create_first_name
            ON create_first_name.usd_usr_id = usr_log.usr_id_create
           AND create_first_name.usd_usf_id = :createFirstName
WHERE
    1 = 1
    '.$sqlConditions.'

ORDER BY timestamp DESC;
';

// Unfortunately, a named param cannot be used multiple times in prepared queries, so we need to duplicate some params with unique names
$queryParams = [
    'lastName' => $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    'firstName' => $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    'createLastName' => $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    'createFirstName' => $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    'uslTimeFrom' => $dateFromIntern . ' 00:00:00',
    'uslTimeUntil' => $dateToIntern . ' 23:59:59',
    'uplTimeFrom' => $dateFromIntern . ' 00:00:00',
    'uplTimeUntil' => $dateToIntern . ' 23:59:59'
];

$fieldHistoryStatement = $gDb->queryPrepared($sql, array_merge($queryParams, $queryParamsConditions));

if ($fieldHistoryStatement->rowCount() === 0) {
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();

    // show message if there were no changes for users
    if ($getUserUuid !== '') {
        $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED_PROFIL', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'))));
    // => EXIT
    } else {
        $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED'));
        // => EXIT
    }
}

// create html page object
$page = new HtmlPage('admidio-profile-fields-history', $headline);

// create filter menu with input elements for start date and end date
$filterNavbar = new HtmlNavbar('menu_profile_field_history_filter', '', null, 'filter');
$form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/contacts/profile_field_history.php', $page, array('type' => 'navbar', 'setFocus' => false));
$form->addInput('user_uuid', '', $getUserUuid, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
$form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
$filterNavbar->addForm($form->show());
$page->addHtml($filterNavbar->show());

$table = new HtmlTable('profile_field_history_table', $page, true, true);

$columnHeading = array();

if ($getUserUuid === '') {
    $table->setDatatablesOrderColumns(array(array(6, 'desc')));
    $columnHeading[] = $gL10n->get('SYS_NAME');
} else {
    $table->setDatatablesOrderColumns(array(array(5, 'desc')));
}

$columnHeading[] = $gL10n->get('SYS_FIELD');
$columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
$columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
$columnHeading[] = $gL10n->get('SYS_EDITED_BY');
$columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

$table->addRowHeadingByArray($columnHeading);

while ($row = $fieldHistoryStatement->fetch()) {
    $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
    $columnValues    = array();

    if ($getUserUuid === '') {
        $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr'])).'">'.$row['last_name'].', '.$row['first_name'].'</a>';
    }

    if ($row['type'] == 'Field') {
        $columnValues[] = $gProfileFields->getPropertyById((int) $row['field'], 'usf_name');
        $uslValueNew = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $row['value_new']);
        $uslValueOld = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $row['value_old']);
    } else {
        // Profile fields have hardcoded column names stored in the log
        $columnValues[] = $gL10n->get($row['field']);
        $uslValueNew = $row['value_new'];
        $uslValueOld = $row['value_old'];
    }

    if ($uslValueNew !== '') {
        $columnValues[] = $uslValueNew;
    } else {
        $columnValues[] = '&nbsp;';
    }

    if ($uslValueOld !== '') {
        $columnValues[] = $uslValueOld;
    } else {
        $columnValues[] = '&nbsp;';
    }

    $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr_create'])).'">'.$row['create_last_name'].', '.$row['create_first_name'].'</a>';
    $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show());
$page->show();
