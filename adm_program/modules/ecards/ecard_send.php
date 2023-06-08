<?php
/**
 ***********************************************************************************************
 * Send ecard to users and show status message
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/ecard_function.php');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('enable_ecard_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
$postPhotoUuid    = admFuncVariableIsValid($_POST, 'photo_uuid', 'string', array('requireValue' => true));
$postPhotoNr      = admFuncVariableIsValid($_POST, 'photo_nr', 'int', array('requireValue' => true));
$postMessage      = admFuncVariableIsValid($_POST, 'ecard_message', 'html');

$funcClass       = new FunctionClass($gL10n);
$photoAlbum      = new TablePhotos($gDb);
$photoAlbum->readDataByUuid($postPhotoUuid);
$imageUrl        = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $postPhotoUuid, 'photo_nr' => $postPhotoNr, 'max_width' => $gSettingsManager->getInt('ecard_card_picture_width'), 'max_height' => $gSettingsManager->getInt('ecard_card_picture_height')));
$imageServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$photoAlbum->getValue('pho_id').'/'.$postPhotoNr.'.jpg';

$_SESSION['ecard_request'] = $_POST;

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

// check if user has right to view the album
if (!$photoAlbum->isVisible()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// the logged-in user has no valid mail address stored in his profile, which can be used as sender
if ($gValidLogin && $gCurrentUser->getValue('EMAIL') === '') {
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', array('<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php">', '</a>')));
    // => EXIT
}

$senderName  = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
$senderEmail = $gCurrentUser->getValue('EMAIL');

if (!isset($_POST['ecard_recipients']) || !is_array($_POST['ecard_recipients'])) {
    $_SESSION['ecard_request']['ecard_recipients'] = '';
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TO'))));
    // => EXIT
}

if ($postMessage === '') {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_MESSAGE'))));
    // => EXIT
}

// read template from file system
$ecardDataToParse = $funcClass->getEcardTemplate($postTemplateName);

// if template was not found then show error
if ($ecardDataToParse === null) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// check if user has right to send mail to selected roles and users
$arrayRoles = array();
$arrayUsers = array();
$receiverString = implode(' | ', $_POST['ecard_recipients']);
$sqlEmailField  = '';

foreach ($_POST['ecard_recipients'] as $value) {
    if (str_contains($value, 'groupID')) {
        $roleId = (int) substr($value, 9);
        if ($gCurrentUser->hasRightSendMailToRole($roleId)) {
            $arrayRoles[] = $roleId;
        }
    } else {
        $arrayUsers[] = $value;
    }
}

if (count($arrayRoles) === 0 && count($arrayUsers) === 0) {
    $ecardSendResult = false;
} else {
    $ecardSendResult = true;
}

// object to handle the current message in the database
$message = new TableMessage($gDb);
$message->setValue('msg_type', TableMessage::MESSAGE_TYPE_EMAIL);
$message->setValue('msg_subject', $gL10n->get('SYS_GREETING_CARD').': '.$gL10n->get('SYS_NEW_MESSAGE_RECEIVED'));
$message->setValue('msg_usr_id_sender', $gCurrentUserId);

// set condition if email should only send to the email address of the user field
// with the internal name 'EMAIL'
if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
    $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
}

if (count($arrayRoles) > 0) {
    // Wenn schon dann alle Namen und die dazugehörigen Emails auslesen und in die versand Liste hinzufügen
    $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email, rol_name
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
        INNER JOIN '.TBL_USERS.'
                ON usr_id = mem_usr_id
        INNER JOIN ' . TBL_USER_DATA . ' AS email
                ON email.usd_usr_id = usr_id
               AND LENGTH(email.usd_value) > 0
        INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                ON field.usf_id = email.usd_usf_id
               AND field.usf_type = \'EMAIL\'
                   ' . $sqlEmailField . '
        INNER JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE rol_id           IN ('.implode(',', $arrayRoles).')
               AND cat_org_id       = ? -- $gCurrentOrgId
               AND mem_begin       <= ? -- DATE_NOW
               AND mem_end          > ? -- DATE_NOW
               AND usr_valid        = true
          ORDER BY last_name, first_name';
    $queryParams = array(
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $gCurrentOrgId,
        DATE_NOW,
        DATE_NOW
    );
    $usersStatement = $gDb->queryPrepared($sql, $queryParams);

    while ($row = $usersStatement->fetch()) {
        if ($ecardSendResult) {
            // create and send ecard
            $ecardHtmlData   = $funcClass->parseEcardTemplate($imageUrl, $postMessage, $ecardDataToParse, $row['first_name'].' '.$row['last_name'], $row['email']);
            $ecardSendResult = $funcClass->sendEcard($senderName, $senderEmail, $ecardHtmlData, $row['first_name'], $row['last_name'], $row['email'], $imageServerPath);
        }
    }

    // add roles to message object
    foreach ($arrayRoles as $roleId) {
        $message->addRole($roleId, 0);
    }
}

if (count($arrayUsers) > 0) {
    $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email
              FROM '.TBL_USERS.'
        INNER JOIN ' . TBL_USER_DATA . ' AS email
                ON email.usd_usr_id = usr_id
               AND LENGTH(email.usd_value) > 0
        INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                ON field.usf_id = email.usd_usf_id
               AND field.usf_type = \'EMAIL\'
                   ' . $sqlEmailField . '
        INNER JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE usr_id           IN ('.implode(',', $arrayUsers).')
               AND usr_valid        = true
          ORDER BY last_name, first_name';
    $queryParams = array(
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
    );
    $usersStatement = $gDb->queryPrepared($sql, $queryParams);

    while ($row = $usersStatement->fetch()) {
        if ($ecardSendResult) {
            // create and send ecard
            $ecardHtmlData   = $funcClass->parseEcardTemplate($imageUrl, $postMessage, $ecardDataToParse, $row['first_name'].' '.$row['last_name'], $row['email']);
            $ecardSendResult = $funcClass->sendEcard($senderName, $senderEmail, $ecardHtmlData, $row['first_name'], $row['last_name'], $row['email'], $imageServerPath);
        }
    }

    // add roles to message object
    foreach ($arrayUsers as $userId) {
        $message->addUser($userId);
    }
}

// show result
if ($ecardSendResult) {
    $message->addContent($ecardHtmlData);
    $message->save();

    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    $gMessage->show($gL10n->get('SYS_ECARD_SUCCESSFULLY_SEND'));
// => EXIT
} else {
    $gMessage->show($gL10n->get('SYS_ECARD_NOT_SUCCESSFULLY_SEND'));
    // => EXIT
}
