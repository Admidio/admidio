<?php
/**
 ***********************************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// check if module is active
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// if there is no login then show a profile form where the user can register himself
if(!$gValidLogin)
{
    admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php?new_user=2');
    // => EXIT
}

// Only Users with the right "approve users" can confirm registrations. Otherwise exit.
if(!$gCurrentUser->approveUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('NWU_NEW_REGISTRATIONS');

// Navigation in module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// Select new Members of the group
$sql = 'SELECT usr_id, usr_login_name, reg_timestamp, last_name.usd_value AS last_name,
               first_name.usd_value AS first_name, email.usd_value AS email
          FROM '.TBL_REGISTRATIONS.'
    INNER JOIN '.TBL_USERS.'
            ON usr_id = reg_usr_id
     LEFT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
         WHERE usr_valid = 0
           AND reg_org_id = '.$gCurrentOrganization->getValue('org_id').'
      ORDER BY last_name, first_name';
$usrStatement = $gDb->query($sql);

if ($usrStatement->rowCount() === 0)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('NWU_NO_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
    // => EXIT
}

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

if($gCurrentUser->isAdministrator())
{
    // get module menu
    $registrationMenu = $page->getMenu();

    // show link to system preferences of announcements
    $registrationMenu->addItem('menu_item_preferences',
                               ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=registration',
                               $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$table = new HtmlTable('new_user_table', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_REGISTRATION'),
    $gL10n->get('SYS_USERNAME'),
    $gL10n->get('SYS_EMAIL'),
    '&nbsp;'
);
$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$table->setDatatablesOrderColumns(array(1));
$table->addRowHeadingByArray($columnHeading);

while($row = $usrStatement->fetch())
{
    $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['reg_timestamp']);
    $datetimeCreate  = $timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']);

    if($gPreferences['enable_mail_module'] == 1)
    {
        $mailLink = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?usr_id='.$row['usr_id'].'">'.$row['email'].'</a>';
    }
    else
    {
        $mailLink  = '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php?user_id='.$row['usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a>',
        $datetimeCreate,
        $row['usr_login_name'],
        $mailLink,
        '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_assign.php?new_user_id='.$row['usr_id'].'"><img
            src="'. THEME_URL. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" title="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" /></a>
        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
            href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=nwu&amp;element_id=row_user_'.
            $row['usr_id'].'&amp;name='.urlencode($row['first_name'].' '.$row['last_name']).'&amp;database_id='.$row['usr_id'].'"><img
            src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');

    $table->addRowByArray($columnValues, 'row_user_'.$row['usr_id']);
}

$page->addHtml($table->show());
$page->show();
