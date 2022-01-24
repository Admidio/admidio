<?php
/**
 ***********************************************************************************************
 * Check message information and save it
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * msg_id    - set message id for conversation
 * msg_type  - set message type
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

use PHPMailer\PHPMailer\Exception;

// Initialize and check the parameters
$getMsgUuid = admFuncVariableIsValid($_GET, 'msg_uuid', 'string');
$getMsgType = admFuncVariableIsValid($_GET, 'msg_type', 'string');

// Check form values
$postFrom       = admFuncVariableIsValid($_POST, 'mailfrom', 'string');
$postName       = admFuncVariableIsValid($_POST, 'namefrom', 'string');
$postSubject    = StringUtils::strStripTags($_POST['msg_subject']); // Subject should be send without html conversations
$postSubjectSQL = admFuncVariableIsValid($_POST, 'msg_subject', 'string');
$postBody       = admFuncVariableIsValid($_POST, 'msg_body', 'html');
$postDeliveryConfirmation = admFuncVariableIsValid($_POST, 'delivery_confirmation', 'bool');
$postCaptcha    = admFuncVariableIsValid($_POST, 'captcha_code', 'string');
$postUserIdList = admFuncVariableIsValid($_POST, 'userIdList', 'string');
$postListUuid   = admFuncVariableIsValid($_POST, 'list_uuid', 'string');

// save form data in session for back navigation
$_SESSION['message_request'] = $_POST;

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

if (isset($_POST['msg_to'])) {
    $postTo = $_POST['msg_to'];
} else {
    // message when no receiver is given
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TO'))));
    // => EXIT
}

if ($postSubjectSQL === '') {
    // message when no subject is given
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_SUBJECT'))));
    // => EXIT
}

if ($postBody === '') {
    // message when no subject is given
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_MESSAGE'))));
    // => EXIT
}

$message = new TableMessage($gDb);
$message->readDataByUuid($getMsgUuid);

if ($getMsgUuid !== '') {
    $getMsgType = $message->getValue('msg_type');
}

// if message not PM it must be Email and then directly check the parameters
if ($getMsgType !== TableMessage::MESSAGE_TYPE_PM) {
    $getMsgType = TableMessage::MESSAGE_TYPE_EMAIL;
}

// Stop if pm should be send pm module is disabled
if ($getMsgType === TableMessage::MESSAGE_TYPE_PM && !$gSettingsManager->getBool('enable_pm_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Stop if mail should be send and mail module is disabled
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL && !$gSettingsManager->getBool('enable_mail_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$sendResult = false;

// if message is EMAIL then check the parameters
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL) {
    // allow option to send a copy to your email address only for registered users because of spam abuse
    $postCarbonCopy = 0;
    if ($gValidLogin) {
        $postCarbonCopy = admFuncVariableIsValid($_POST, 'carbon_copy', 'bool');
    }

    // if Attachment size is higher than max_post_size from php.ini, then $_POST is empty.
    if (empty($_POST)) {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // Check Captcha if enabled and user logged out
    if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha')) {
        try {
            FormValidation::checkCaptcha($postCaptcha);
        } catch (AdmException $e) {
            $e->showHtml();
            // => EXIT
        }
    }
}

// if user is logged in then show sender name and email
if ($gCurrentUserId > 0) {
    $postName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
    if (!StringUtils::strValidCharacters($postFrom, 'email')) {
        $postFrom = $gCurrentUser->getValue('EMAIL');
    }
} else {
    if ($postName === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_YOUR_NAME'))));
        // => EXIT
    }
    if (!StringUtils::strValidCharacters($postFrom, 'email')) {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_YOUR_EMAIL'))));
        // => EXIT
    }
}

// if no User is set, he is not able to ask for delivery confirmation
if (!($gCurrentUserId > 0 && (int) $gSettingsManager->get('mail_delivery_confirmation') === 2)
&&  (int) $gSettingsManager->get('mail_delivery_confirmation') !== 1) {
    $postDeliveryConfirmation = false;
}

// object to handle the current message in the database
$message->setValue('msg_type', $getMsgType);
$message->setValue('msg_subject', $postSubject);
$message->setValue('msg_usr_id_sender', $gCurrentUserId);
$message->addContent($postBody);

// check if PM or Email and to steps:
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL) {
    $receiver = array();
    $sqlConditions  = '';
    $sqlEmailField  = '';

    if (isset($postTo)) {
        if ($postListUuid !== '') { // the uuid of a list was passed
            $postTo = explode(',', $postUserIdList);
        }

        // Create new Email Object
        $email = new Email();

        foreach ($postTo as $value) {
            // set condition if email should only send to the email address of the user field
            // with the internal name 'EMAIL'
            if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
                $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
            }

            // check if role or user is given
            if (str_contains($value, ':')) {
                $moduleMessages = new ModuleMessages();
                $group = $moduleMessages->msgGroupSplit($value);

                // check if role rights are granted to the User
                $sql = 'SELECT rol_mail_this_role, rol_id, rol_name
                          FROM ' . TBL_ROLES . '
                    INNER JOIN ' . TBL_CATEGORIES . '
                            ON cat_id = rol_cat_id
                           AND (  cat_org_id = ? -- $gCurrentOrgId
                               OR cat_org_id IS NULL)
                         WHERE rol_id = ? -- $group[\'id\']';
                $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $group['id']));
                $row = $statement->fetch();

                // add role to the message object
                $message->addRole($group['id'], $group['role_mode'], $row['rol_name']);

                // logged out ones just to role with permission level "all visitors"
                // logged in user is just allowed to send to role with permission
                // role must be from actual Organisation
                if ((!$gValidLogin && (int) $row['rol_mail_this_role'] !== 3)
                || ($gValidLogin  && !$gCurrentUser->hasRightSendMailToRole((int) $row['rol_id']))
                || $row['rol_id'] === null) {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                    // => EXIT
                }

                $queryParams = array(
                    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                    $group['id'],
                    $gCurrentOrgId
                );

                if ($group['status'] === 'former' && $gSettingsManager->getBool('mail_show_former')) {
                    // only former members
                    $sqlConditions = ' AND mem_end < ? -- DATE_NOW ';
                    $queryParams[] = DATE_NOW;
                } elseif ($group['status'] === 'active_former' && $gSettingsManager->getBool('mail_show_former')) {
                    // former members and active members
                    $sqlConditions = ' AND mem_begin < ? -- DATE_NOW ';
                    $queryParams[] = DATE_NOW;
                } else {
                    // only active members
                    $sqlConditions = ' AND mem_begin <= ? -- DATE_NOW
                                       AND mem_end    > ? -- DATE_NOW ';
                    $queryParams[] = DATE_NOW;
                    $queryParams[] = DATE_NOW;
                }

                $sql = 'SELECT first_name.usd_value AS firstname, last_name.usd_value AS lastname, email.usd_value AS email
                          FROM ' . TBL_MEMBERS . '
                    INNER JOIN ' . TBL_ROLES . '
                            ON rol_id = mem_rol_id
                    INNER JOIN ' . TBL_CATEGORIES . '
                            ON cat_id = rol_cat_id
                    INNER JOIN ' . TBL_USERS . '
                            ON usr_id = mem_usr_id
                    INNER JOIN ' . TBL_USER_DATA . ' AS email
                            ON email.usd_usr_id = usr_id
                           AND LENGTH(email.usd_value) > 0
                    INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                            ON field.usf_id = email.usd_usf_id
                           AND field.usf_type = \'EMAIL\'
                               ' . $sqlEmailField . '
                     LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                            ON last_name.usd_usr_id = usr_id
                           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                     LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                            ON first_name.usd_usr_id = usr_id
                           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                         WHERE rol_id    = ? -- $group[\'id\']
                           AND (  cat_org_id = ? -- $gCurrentOrgId
                               OR cat_org_id IS NULL )
                           AND usr_valid = true
                               ' . $sqlConditions;

                // if current user is logged in the user id must be excluded because we don't want
                // to send the email to himself
                if ($gValidLogin) {
                    $sql .= '
                        AND usr_id <> ? -- $gCurrentUserId';
                    $queryParams[] = $gCurrentUserId;
                }
                $statement = $gDb->queryPrepared($sql, $queryParams);

                if ($statement->rowCount() > 0) {
                    // all role members will be attached as BCC
                    while ($row = $statement->fetch()) {
                        if (StringUtils::strValidCharacters($row['email'], 'email')) {
                            $receiver[] = array($row['email'], $row['firstname'] . ' ' . $row['lastname']);
                        }
                    }
                }
            } else {
                // create user object
                $user = new User($gDb, $gProfileFields, $value);

                // only send email to user if current user is allowed to view this user and he has a valid email address
                if ($gCurrentUser->hasRightViewProfile($user)) {
                    // add user to the message object
                    $message->addUser((int) $user->getValue('usr_id'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));

                    $sql = 'SELECT first_name.usd_value AS firstname, last_name.usd_value AS lastname, email.usd_value AS email
                              FROM ' . TBL_USERS . '
                        INNER JOIN ' . TBL_USER_DATA . ' AS email
                                ON email.usd_usr_id = usr_id
                               AND LENGTH(email.usd_value) > 0
                        INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                                ON field.usf_id = email.usd_usf_id
                               AND field.usf_type = \'EMAIL\'
                                   ' . $sqlEmailField . '
                         LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                                ON last_name.usd_usr_id = usr_id
                               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                         LEFT JOIN '.TBL_USER_DATA.' AS first_name
                                ON first_name.usd_usr_id = usr_id
                               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                             WHERE usr_id = ? -- $user->getValue(\'usr_id\')
                               AND usr_valid = true ';
                    $statement = $gDb->queryPrepared($sql, array((int) $gProfileFields->getProperty('LAST_NAME', 'usf_id'), (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), (int) $user->getValue('usr_id')));

                    while ($row = $statement->fetch()) {
                        if (StringUtils::strValidCharacters($row['email'], 'email')) {
                            $receiver[] = array($row['email'], $row['firstname'] . ' ' . $row['lastname']);
                        }
                    }
                }
            }
        }
    } else {
        // message when no receiver is given
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // if no valid recipients exists show message
    if (count($receiver) === 0) {
        $gMessage->show($gL10n->get('SYS_NO_VALID_RECIPIENTS'));
        // => EXIT
    }

    // check if name is given
    if ($postName === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    // if valid login then sender should always current user
    if ($gValidLogin) {
        $postName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
    }

    // set sending address
    if ($email->setSender($postFrom, $postName)) {
        // set subject
        if ($email->setSubject($postSubject)) {
            // check for attachment
            if (isset($_FILES['userfile'])) {
                // final check if user is logged in
                if (!$gValidLogin) {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                    // => EXIT
                }
                $attachmentSize = 0;
                // add now every attachment
                for ($currentAttachmentNo = 0; isset($_FILES['userfile']['name'][$currentAttachmentNo]); ++$currentAttachmentNo) {
                    // check if Upload was OK
                    if (($_FILES['userfile']['error'][$currentAttachmentNo] !== UPLOAD_ERR_OK)
                    &&  ($_FILES['userfile']['error'][$currentAttachmentNo] !== UPLOAD_ERR_NO_FILE)) {
                        $gMessage->show($gL10n->get('SYS_ATTACHMENT_TO_LARGE'));
                        // => EXIT
                    }

                    // check if a file was really uploaded
                    if (!file_exists($_FILES['userfile']['tmp_name'][$currentAttachmentNo]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][$currentAttachmentNo])) {
                        $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
                        // => EXIT
                    }

                    if ($_FILES['userfile']['error'][$currentAttachmentNo] === UPLOAD_ERR_OK) {
                        // check the size of the attachment
                        $attachmentSize += $_FILES['userfile']['size'][$currentAttachmentNo];
                        if ($attachmentSize > Email::getMaxAttachmentSize()) {
                            $gMessage->show($gL10n->get('SYS_ATTACHMENT_TO_LARGE'));
                            // => EXIT
                        }

                        // set filetyp to standard if not given
                        if (strlen($_FILES['userfile']['type'][$currentAttachmentNo]) <= 0) {
                            $_FILES['userfile']['type'][$currentAttachmentNo] = 'application/octet-stream';
                        }

                        // add the attachment to the email and message object
                        try {
                            $email->addAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo], $encoding = 'base64', $_FILES['userfile']['type'][$currentAttachmentNo]);
                            $message->addAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo]);
                        } catch (Exception $e) {
                            $gMessage->show($e->errorMessage());
                            // => EXIT
                        } catch (\Exception $e) {
                            $gMessage->show($e->getMessage());
                            // => EXIT
                        }
                    }
                }
            }
        } else {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_SUBJECT'))));
            // => EXIT
        }
    } else {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))));
        // => EXIT
    }

    // if possible send html mail
    if ($gValidLogin && $gSettingsManager->getBool('mail_html_registered_users')) {
        $email->setHtmlMail();
    }

    // set flag if copy should be send to sender
    if (isset($postCarbonCopy) && $postCarbonCopy) {
        $email->setCopyToSenderFlag();
    }

    // get array with unique receivers
    $sendResults = array_map('unserialize', array_unique(array_map('serialize', $receiver)));
    $receivers = count($sendResults);
    foreach ($sendResults as $address) {
        if ($receivers === 1) {
            $email->addRecipient($address[0], $address[1]);
        } else {
            $email->addBlindCopy($address[0], $address[1]);
        }
    }

    if ($receivers > 1) {
        // normally we need no To-address and set "undisclosed recipients", but if
        // that won't work than the following address will be set
        if ((int) $gSettingsManager->get('mail_recipients_with_roles') === 1) {
            // fill recipient with sender address to prevent problems with provider
            $email->addRecipient($postFrom, $postName);
        } elseif ((int) $gSettingsManager->get('mail_recipients_with_roles') === 2) {
            // fill recipient with administrators address to prevent problems with provider
            $email->addRecipient($gSettingsManager->getString('email_administrator'), $gL10n->get('SYS_ADMINISTRATOR'));
        }
    }

    // add confirmation mail to the sender
    if ($postDeliveryConfirmation) {
        $email->ConfirmReadingTo = $gCurrentUser->getValue('EMAIL');
    }

    if ($postListUuid !== '') {
        $showList = new ListConfiguration($gDb);
        $showList->readDataByUuid($postListUuid);
        $listName = $showList->getValue('lst_name');
        $receiverName = $gL10n->get('SYS_LIST') . ($listName === '' ? '' : ' - ' . $listName);
    } elseif ($gSettingsManager->getBool('mail_into_to')) {
        $receiverName = $message->getRecipientsNamesString(true);
    } else {
        $receiverName = $message->getRecipientsNamesString(false);
    }

    // load mail template and replace text
    $email->setTemplateText($postBody, $postName, $gCurrentUser->getValue('EMAIL'), $gCurrentUser->getValue('usr_uuid'), $receiverName);

    // finally send the mail
    $sendResult = $email->sendEmail();

    // within this mode an smtp protocol will be shown and the header was still send to browser
    if ($gDebug && headers_sent()) {
        $email->isSMTP();
        $gMessage->showHtmlTextOnly(true);
    }
} else {
    // ***** PM *****
    // if $postTo is not an Array, it is send from the hidden field.
    if (!is_array($postTo)) {
        $postTo = array($postTo);
    }

    // get user data from Database
    $user = new User($gDb, $gProfileFields, $postTo[0]);

    // add user to the message object
    $message->addUser((int) $user->getValue('usr_id'));
    $message->setValue('msg_read', 1);

    // check if it is allowed to send to this user
    if ((!$gCurrentUser->editUsers() && !isMember((int) $user->getValue('usr_id'))) || $user->getValue('usr_id') === '') {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
        // => EXIT
    }

    // check if receiver of message has valid login
    if ($user->getValue('usr_login_name') === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TO'))));
        // => EXIT
    }

    $sendResult = true;
}

// message if send/save is OK
if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
    if ($gValidLogin) {
        // save mail or message to database
        $message->save();
    }

    // after sending remove the send page from navigation stack
    $gNavigation->deleteLastUrl();
    unset($_SESSION['message_request']);

    // message if sending was OK
    if ($gNavigation->count() > 0) {
        $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    } else {
        $gMessage->setForwardUrl($gHomepage, 2000);
    }

    if ($getMsgType === TableMessage::MESSAGE_TYPE_PM) {
        $gMessage->show($gL10n->get('SYS_PRIVATE_MESSAGE_SEND', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))));
    // => EXIT
    } else {
        $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
        // => EXIT
    }
} else {
    if ($getMsgType === TableMessage::MESSAGE_TYPE_PM) {
        $gMessage->show($gL10n->get('SYS_PRIVATE_MESSAGE_NOT_SEND', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $sendResult)));
    // => EXIT
    } else {
        $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)));
        // => EXIT
    }
}
