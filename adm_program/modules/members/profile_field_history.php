<?php
/**
 ***********************************************************************************************
 * Show history of profile field changes
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id    : If set only show the profile field history of that user
 * filter_date_from : is set to actual date,
 *             if no date information is delivered
 * filter_date_to   : is set to 31.12.9999,
 *             if no date information is delivered
 ***********************************************************************************************
 */

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// calculate default date from which the profile fields history should be shown
$filterDateFrom = new DateTimeExtended(DATE_NOW, 'Y-m-d');
$filterDateFrom->modify('-'.$gPreferences['members_days_field_history'].' day');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id', 'numeric');
$getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gPreferences['system_date'])));
$getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

// create a user object from the user parameter
$user = new User($gDb, $gProfileFields, $getUserId);

// set headline of the script
if($getUserId > 0)
{
    $headline = $gL10n->get('MEM_CHANGE_HISTORY_OF', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'));
}
else
{
    $headline = $gL10n->get('MEM_CHANGE_HISTORY');
}

// Initialize local parameteres
$sqlConditions  = '';

// if profile log is activated and current user is allowed to edit users
// then the profile field history will be shown otherwise show error
if ($gPreferences['profile_log_edit_fields'] == 0
    || ($getUserId == 0 && $gCurrentUser->editUsers() == false)
    || ($getUserId > 0  && $gCurrentUser->hasRightEditProfile($user) == false))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// add page to navigation history
$gNavigation->addUrl(CURRENT_URL, $headline);

// filter_date_from and filter_date_to can have different formats
// now we try to get a default format for intern use and html output
$objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
if($objDateFrom === false)
{
    // check if date has system format
    $objDateFrom = DateTime::createFromFormat($gPreferences['system_date'], $getDateFrom);
    if($objDateFrom === false)
    {
        $objDateFrom = DateTime::createFromFormat($gPreferences['system_date'], '1970-01-01');
    }
}

$objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
if($objDateTo === false)
{
    // check if date has system format
    $objDateTo = DateTime::createFromFormat($gPreferences['system_date'], $getDateTo);
    if($objDateTo === false)
    {
        $objDateTo = DateTime::createFromFormat($gPreferences['system_date'], '1970-01-01');
    }
}

// DateTo should be greater than DateFrom (Timestamp must be less)
if($objDateFrom < $objDateTo)
{
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
}

$dateFromIntern = $objDateFrom->format('Y-m-d');
$dateFromHtml   = $objDateFrom->format($gPreferences['system_date']);
$dateToIntern   = $objDateTo->format('Y-m-d');
$dateToHtml     = $objDateTo->format($gPreferences['system_date']);

// create sql conditions
if($getUserId > 0)
{
    $sqlConditions .= ' AND usl_usr_id = '.$getUserId;
}

// get total count of relevant profile field changes
$sql = 'SELECT COUNT(1) as count
          FROM '.TBL_USER_LOG.'
         WHERE 1 = 1 '.
               $sqlConditions;
$pdoStatement = $gDb->query($sql);
$row = $pdoStatement->fetch();
$countChanges = $row['count'];

// create select statement with all necessary data
$sql = 'SELECT usl_usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, usl_usf_id, usl_value_old, usl_value_new,
               usl_usr_id_create, create_last_name.usd_value as create_last_name, create_first_name.usd_value as create_first_name, usl_timestamp_create
          FROM '.TBL_USER_LOG.'
          JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usl_usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usl_usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          JOIN '. TBL_USER_DATA. ' as create_last_name
            ON create_last_name.usd_usr_id = usl_usr_id_create
           AND create_last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          JOIN '. TBL_USER_DATA. ' as create_first_name
            ON create_first_name.usd_usr_id = usl_usr_id_create
           AND create_first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
         WHERE usl_timestamp_create BETWEEN \''.$dateFromIntern.' 00:00:00\' AND \''.$dateToIntern.' 23:59:59\' '.
               $sqlConditions.'
         ORDER BY usl_timestamp_create DESC ';
$resultFieldHistory = $gDb->query($sql);

if($gDb->num_rows($resultFieldHistory) == 0)
{
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();

    // show message if there were no changes for users
    if($getUserId > 0)
    {
        $gMessage->show($gL10n->get('MEM_NO_CHANGES_PROFIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }
    else
    {
        $gMessage->show($gL10n->get('MEM_NO_CHANGES'));
    }
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$profileFieldHistoryMenu = $page->getMenu();
$profileFieldHistoryMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// create filter menu with input elements for Startdate and Enddate
$FilterNavbar = new HtmlNavbar('menu_profile_field_history_filter', null, null, 'filter');
$form = new HtmlForm('navbar_filter_form', $g_root_path.'/adm_program/modules/members/profile_field_history.php?usr_id='.$getUserId, $page, array('type' => 'navbar', 'setFocus' => false));
$form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
$form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
$FilterNavbar->addForm($form->show(false));
$page->addHtml($FilterNavbar->show(false));

$table = new HtmlTable('profile_field_history_table', $page, true, true);

$columnHeading = array();

if($getUserId == 0)
{
    $table->setDatatablesOrderColumns(array(array(6, 'desc')));
    $columnHeading[] = $gL10n->get('SYS_NAME');
}
else
{
    $table->setDatatablesOrderColumns(array(array(5, 'desc')));
}

$columnHeading[] = $gL10n->get('SYS_FIELD');
$columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
$columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
$columnHeading[] = $gL10n->get('SYS_EDITED_BY');
$columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

$table->addRowHeadingByArray($columnHeading);

while($row = $gDb->fetch_array($resultFieldHistory))
{
    $timestampCreate = new DateTimeExtended($row['usl_timestamp_create'], 'Y-m-d H:i:s');
    $columnValues    = array();

    if($getUserId == 0)
    {
        $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usl_usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a>';
    }

    $columnValues[] = $gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name');
    if(strlen($gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_new'], 'html')) > 0)
    {
        $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_new'], 'html');
    }
    else
    {
        $columnValues[] = '&nbsp;';
    }

    if(strlen($gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_old'], 'html')) > 0)
    {
        $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_old'], 'html');
    }
    else
    {
        $columnValues[] = '&nbsp;';
    }

    $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usl_usr_id_create'].'">'.$row['create_last_name'].', '.$row['create_first_name'].'</a>';
    $columnValues[] = $timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']);
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show(false));
$page->show();
