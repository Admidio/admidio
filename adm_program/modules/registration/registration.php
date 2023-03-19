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

$moduleRegistration = new ModuleRegistration();

$registrations = $moduleRegistration->getRegistrationsArray();

if (count($registrations) === 0) {
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

foreach($registrations as $registrationUser) {
    $timestampCreate = \DateTime::createFromFormat('Y-m-d H:i:s', $registrationUser['registrationTimestamp']);
    $datetimeCreate  = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));

    if ($gSettingsManager->getBool('enable_mail_module')) {
        $mailLink = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('user_uuid' => $registrationUser['userUUID'])).'">'.$registrationUser['email'].'</a>';
    } else {
        $mailLink  = '<a href="mailto:'.$registrationUser['email'].'">'.$registrationUser['email'].'</a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $registrationUser['userUUID'])).'">'.$registrationUser['lastName'].', '.$registrationUser['firstName'].'</a>',
        $datetimeCreate,
        $registrationUser['loginName'],
        $mailLink,
        '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_assign.php', array('new_user_uuid' => $registrationUser['userUUID'])).'">
            <i class="fas fa-user-plus" data-toggle="tooltip" title="'.$gL10n->get('SYS_ASSIGN_REGISTRATION').'"></i></a>
        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'nwu', 'element_id' => 'row_user_'.$registrationUser['userUUID'], 'name' => $registrationUser['firstName'].' '.$registrationUser['lastName'], 'database_id' => $registrationUser['userUUID'])).'">
            <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>');

    $table->addRowByArray($columnValues, 'row_user_'.$registrationUser['userUUID']);
}

$page->addHtml($table->show());
$page->show();
