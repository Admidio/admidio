<?php
/**
 ***********************************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// check if module is active
if (!$gSettingsManager->getBool('registration_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// if there is no login then show a profile form where the user can register himself
if (!$gValidLogin) {
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '2')));
    // => EXIT
}

// Only Users with the right "approve users" can confirm registrations. Otherwise exit.
if (!$gCurrentUser->approveUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('SYS_NEW_REGISTRATIONS');

// Navigation in module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-address-card');

// Select new Members of the group
$sql = 'SELECT usr_id, usr_uuid, usr_login_name, reg_timestamp, last_name.usd_value AS last_name,
               first_name.usd_value AS first_name, email.usd_value AS email
          FROM '.TBL_REGISTRATIONS.'
    INNER JOIN '.TBL_USERS.'
            ON usr_id = reg_usr_id
     LEFT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
         WHERE usr_valid = false
           AND reg_org_id = ? -- $gCurrentOrgId
      ORDER BY last_name, first_name';
$queryParams = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $gProfileFields->getProperty('EMAIL', 'usf_id'),
    $gCurrentOrgId
);
$usrStatement = $gDb->queryPrepared($sql, $queryParams);

if ($usrStatement->rowCount() === 0) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_NO_NEW_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
    // => EXIT
}

// create html page object
$page = new HtmlPage('admidio-registration', $headline);

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
$table->addRowHeadingByArray($columnHeading);

while ($row = $usrStatement->fetch()) {
    $timestampCreate = \DateTime::createFromFormat('Y-m-d H:i:s', $row['reg_timestamp']);
    $datetimeCreate  = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));

    if ($gSettingsManager->getBool('enable_mail_module')) {
        $mailLink = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('user_uuid' => $row['usr_uuid'])).'">'.$row['email'].'</a>';
    } else {
        $mailLink  = '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['usr_uuid'])).'">'.$row['last_name'].', '.$row['first_name'].'</a>',
        $datetimeCreate,
        $row['usr_login_name'],
        $mailLink,
        '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_assign.php', array('new_user_uuid' => $row['usr_uuid'])).'">
            <i class="fas fa-user-plus" data-toggle="tooltip" title="'.$gL10n->get('SYS_ASSIGN_REGISTRATION').'"></i></a>
        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'nwu', 'element_id' => 'row_user_'.$row['usr_id'], 'name' => $row['first_name'].' '.$row['last_name'], 'database_id' => $row['usr_uuid'])).'">
            <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>');

    $table->addRowByArray($columnValues, 'row_user_'.$row['usr_id']);
}

$page->addHtml($table->show());
$page->show();
