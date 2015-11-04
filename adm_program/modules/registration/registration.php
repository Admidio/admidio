<?php
/******************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * Copyright    : (c) 2004 - 2014 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');

// check if module is active
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// if there is no login then show a profile form where the user can register himself
if(!$gValidLogin)
{
    header('Location: '.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=2');
    exit();
}

// Only Users with the right "approve users" can confirm registrations. Otherwise exit.
if(!$gCurrentUser->approveUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
$headline = $gL10n->get('NWU_NEW_REGISTRATIONS');

// Navigation in module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// Select new Members of the group
$sql = 'SELECT usr_id, usr_login_name, reg_timestamp, last_name.usd_value as last_name,
               first_name.usd_value as first_name, email.usd_value as email
          FROM '. TBL_REGISTRATIONS. ', '. TBL_USERS. '
          LEFT JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
         WHERE usr_valid = 0
           AND reg_usr_id = usr_id
           AND reg_org_id = '.$gCurrentOrganization->getValue('org_id').'
         ORDER BY last_name, first_name ';
$usr_result   = $gDb->query($sql);
$members_found = $gDb->num_rows($usr_result);

if ($members_found === 0)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('NWU_NO_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
}

// create html page object
$page = new HtmlPage($headline);

if($gCurrentUser->isWebmaster())
{
    // get module menu
    $registrationMenu = $page->getMenu();

    // show link to system preferences of announcements
    $registrationMenu->addItem('menu_item_preferences',
                               $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=registration',
                               $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$table = new HtmlTable('new_user_table', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_REGISTRATION'),
    $gL10n->get('SYS_USERNAME'),
    $gL10n->get('SYS_EMAIL'),
    $gL10n->get('SYS_FEATURES')
);
$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$table->setDatatablesOrderColumns(1);
$table->addRowHeadingByArray($columnHeading);

while($row = $gDb->fetch_array($usr_result))
{
    $timestampCreate = new DateTimeExtended($row['reg_timestamp'], 'Y-m-d H:i:s');
    $datetimeCreate  = $timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']);

    if($gPreferences['enable_mail_module'] == 1)
    {
        $mailLink = '<a href="'.$g_root_path.'/adm_program/modules/messages/messages_write.php?usr_id='.$row['usr_id'].'">'.$row['email'].'</a>';
    }
    else
    {
        $mailLink  = '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a>',
        $datetimeCreate,
        $row['usr_login_name'],
        $mailLink,
        '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/registration/registration_assign.php?new_user_id='.$row['usr_id'].'"><img
                            src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" title="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" /></a>
        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
            href="'.$g_root_path.'/adm_program/system/popup_message.php?type=nwu&amp;element_id=row_user_'.
            $row['usr_id'].'&amp;name='.urlencode($row['first_name'].' '.$row['last_name']).'&amp;database_id='.$row['usr_id'].'"><img
            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');

    $table->addRowByArray($columnValues, 'row_user_'.$row['usr_id']);
}

$page->addHtml($table->show(false));
$page->show();
