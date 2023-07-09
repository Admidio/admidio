<?php
/**
 ***********************************************************************************************
 * Check message information and save it
 *
 * @copyright 2004-2023 The Admidio Team
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
$postSubject    = StringUtils::strStripTags($_POST['msg_subject']); // Subject should be sent without html conversations
$postSubjectSQL = admFuncVariableIsValid($_POST, 'msg_subject', 'string');
$postBody       = admFuncVariableIsValid($_POST, 'msg_body', 'html');
$postDeliveryConfirmation = admFuncVariableIsValid($_POST, 'delivery_confirmation', 'bool');
$postCaptcha    = admFuncVariableIsValid($_POST, 'captcha_code', 'string');
$postUserUuidList = '';
$postListUuid = '';

if ($gValidLogin) {
    $postUserUuidList = admFuncVariableIsValid($_POST, 'userUuidList', 'string');
    $postListUuid = admFuncVariableIsValid($_POST, 'list_uuid', 'string');
}

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

// Stop if pm should be sent pm module is disabled
if ($getMsgType === TableMessage::MESSAGE_TYPE_PM && !$gSettingsManager->getBool('enable_pm_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Stop if mail should be sent and mail module is disabled
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
if ($message->isNewRecord()) {
    $message->setValue('msg_subject', $postSubject);
}
$message->setValue('msg_type', $getMsgType);
$message->setValue('msg_usr_id_sender', $gCurrentUserId);
$message->addContent($postBody);

// check if PM or Email and to steps:
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL) {
    $sqlConditions  = '';
    $sqlEmailField  = '';

    if (isset($postTo)) {
        if ($postListUuid !== '') { // the uuid of a list was passed
            $postTo = explode(',', $postUserUuidList);
        }

        // Create new Email Object
        $email = new Email();

        try {
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
                         WHERE rol_uuid = ? -- $group[\'uuid\']';
                    $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $group['uuid']));
                    $row = $statement->fetch();

                    // logged out ones just to role with permission level "all visitors"
                    // logged in user is just allowed to send to role with permission
                    // role must be from actual Organisation
                    if ((!$gValidLogin && (int)$row['rol_mail_this_role'] !== 3)
                        || ($gValidLogin && !$gCurrentUser->hasRightSendMailToRole((int)$row['rol_id']))
                        || $row['rol_id'] === null) {
                        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                        // => EXIT
                    }

                    // add role to the message object
                    $message->addRole($row['rol_id'], $group['role_mode'], $row['rol_name']);

                    // add all role members as recipients to the email
                    $email->addRecipientsByRole($group['uuid'], $group['status']);
                } else {
                    // create user object
                    $user = new User($gDb, $gProfileFields);
                    $user->readDataByUuid($value);

                    // only send email to user if current user is allowed to view this user, and he has a valid email address
                    if ($gCurrentUser->hasRightViewProfile($user)) {
                        // add user to the message object
                        $message->addUser((int)$user->getValue('usr_id'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));

                        // add user as recipients to the email
                        $email->addRecipientsByUserId((int)$user->getValue('usr_id'));
                    }
                }
            }
        } catch (AdmException $e) {
            $e->showHtml();
        }
    } else {
        // message when no receiver is given
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // if no valid recipients exists show message
    if ($email->countRecipients() === 0) {
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

                    // only check attachment if there was already a file added
                    if(strlen($_FILES['userfile']['tmp_name'][$currentAttachmentNo]) > 0) {
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

                            // set file type to standard if not given
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

    // set flag if copy should be sent to sender
    if (isset($postCarbonCopy) && $postCarbonCopy) {
        $email->setCopyToSenderFlag();
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
        $receiverName = $message->getRecipientsNamesString();
    } else {
        $receiverName = $message->getRecipientsNamesString(false);
    }

    // load mail template and replace text
    $email->setTemplateText($postBody, $postName, $gCurrentUser->getValue('EMAIL'), $gCurrentUser->getValue('usr_uuid'), $receiverName);

    // finally send the mail
    $sendResult = $email->sendEmail();

    // within this mode a smtp protocol will be shown and the header was still send to browser
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

    // check if user is allowed to view message
    if(!in_array($gCurrentUserId, array($message->getValue('msg_usr_id_sender'), $message->getConversationPartner()))) {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    try {
        // get user data from Database
        $user = new User($gDb, $gProfileFields, $postTo[0]);

        // add user to the message object
        $message->addUser((int) $user->getValue('usr_id'));
        $message->setValue('msg_read', 1);
    } catch (AdmException $e) {
        $e->showHtml();
    }

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

// save message to database if send/save is OK
if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
    if ($gValidLogin) {
        try {
            $message->save();
        } catch (AdmException $e) {
            $e->showHtml();
        }
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
