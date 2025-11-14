<?php
/**
 ***********************************************************************************************
 * Check message information and save it
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * msg_id    - set message id for conversation
 * msg_type  - set message type
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Messages\Entity\Message;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getMsgUuid = admFuncVariableIsValid($_GET, 'msg_uuid', 'uuid');
    $getMsgType = admFuncVariableIsValid($_GET, 'msg_type', 'string');

    // Check form values
    $postUserUuidList = '';
    $postListUuid = '';
    $sendResult = false;

    if ($gValidLogin) {
        $postUserUuidList = admFuncVariableIsValid($_POST, 'userUuidList', 'string');
        $postListUuid = admFuncVariableIsValid($_POST, 'list_uuid', 'uuid');
    }

    // check form field input and sanitized it from malicious content
    $messagesSendForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $messagesSendForm->validate($_POST);

    if (isset($_POST['msg_to'])) {
        $postTo = $_POST['msg_to'];
    }

    $message = new Message($gDb);
    $message->readDataByUuid($getMsgUuid);

    if ($getMsgUuid !== '') {
        $getMsgType = $message->getValue('msg_type');
    }

    // if message not PM it must be Email and then directly check the parameters
    if ($getMsgType !== Message::MESSAGE_TYPE_PM) {
        $getMsgType = Message::MESSAGE_TYPE_EMAIL;
    }

    // Stop if pm should be sent pm module is disabled
    if ($getMsgType === Message::MESSAGE_TYPE_PM && !$gSettingsManager->getBool('pm_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Stop if mail should be sent and mail module is disabled
    if ($getMsgType === Message::MESSAGE_TYPE_EMAIL && !($gSettingsManager->getInt('mail_module_enabled') === 1 || ($gSettingsManager->getInt('mail_module_enabled') === 2 && $gValidLogin))) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // if message is EMAIL then check the parameters
    if ($getMsgType === Message::MESSAGE_TYPE_EMAIL) {
        // if Attachment size is higher than max_post_size from php.ini, then $_POST is empty.
        if (empty($_POST)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }

    // object to handle the current message in the database
    if ($message->isNewRecord()) {
        $message->setValue('msg_subject', $formValues['msg_subject']);
    }
    $message->setValue('msg_type', $getMsgType);
    $message->setValue('msg_usr_id_sender', $gCurrentUserId);
    $message->addContent($formValues['msg_body']);

    // check if PM or Email and to steps:
    if ($getMsgType === Message::MESSAGE_TYPE_EMAIL) {
        $sqlConditions = '';
        $sqlEmailField = '';

        // if user is logged in then show sender name and email
        if ($gCurrentUserId > 0) {
            $formValues['namefrom'] = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
            if (!isset($formValues['mailfrom']) || !StringUtils::strValidCharacters((string) $formValues['mailfrom'], 'email')) {
                $formValues['mailfrom'] = $gCurrentUser->getValue('EMAIL');
            }
        }

        // if no User is set, he is not able to ask for delivery confirmation
        if (!($gCurrentUserId > 0 && (int)$gSettingsManager->get('mail_delivery_confirmation') === 2)
            && (int)$gSettingsManager->get('mail_delivery_confirmation') !== 1) {
            $formValues['delivery_confirmation'] = false;
        }

        if (isset($postTo)) {
            if ($postListUuid !== '') { // the uuid of a list was passed
                $postTo = explode(',', $postUserUuidList);
                foreach ($postListUuid as $key => $uuid) {
                    if (!UUID::isValid($uuid)) {
                        unset($postListUuid[$key]);
                    }
                }
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
                     WHERE rol_uuid = ? -- $group[\'uuid\']';
                    $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $group['uuid']));
                    $row = $statement->fetch();

                    // logged out ones just to role with permission level "all visitors"
                    // logged-in user is just allowed to send to role with permission
                    // role must be from actual Organisation
                    if ((!$gValidLogin && (int)$row['rol_mail_this_role'] !== 3)
                        || ($gValidLogin && !$gCurrentUser->hasRightSendMailToRole((int)$row['rol_id']))
                        || $row['rol_id'] === null) {
                        throw new Exception('SYS_INVALID_PAGE_VIEW');
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
                        $message->addUser($user->getValue('usr_id'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));

                        // add user as recipients to the email
                        $email->addRecipientsByUser($user->getValue('usr_uuid'));
                    }
                }
            }
        } else {
            // message when no receiver is given
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // if no valid recipients exists show message
        if ($email->countRecipients() === 0) {
            throw new Exception('SYS_NO_VALID_RECIPIENTS');
        }

        // check if name is given
        if ($formValues['namefrom'] === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_NAME'));
        }

        // set sending address
        if ($email->setSender($formValues['mailfrom'], $formValues['namefrom'])) {
            // set subject
            if ($email->setSubject($formValues['msg_subject'])) {
                // check for attachment
                if (isset($_FILES['userfile'])) {
                    // final check if user is logged in
                    if (!$gValidLogin) {
                        throw new Exception('SYS_INVALID_PAGE_VIEW');
                    }
                    $attachmentSize = 0;
                    // add now every attachment
                    for ($currentAttachmentNo = 0; isset($_FILES['userfile']['name'][$currentAttachmentNo]); ++$currentAttachmentNo) {
                        // check if Upload was OK
                        if (($_FILES['userfile']['error'][$currentAttachmentNo] !== UPLOAD_ERR_OK)
                            && ($_FILES['userfile']['error'][$currentAttachmentNo] !== UPLOAD_ERR_NO_FILE)) {
                            throw new Exception('SYS_ATTACHMENT_TO_LARGE');
                        }

                        // only check attachment if there was already a file added
                        if (strlen($_FILES['userfile']['tmp_name'][$currentAttachmentNo]) > 0) {
                            // check if a file was really uploaded
                            if (!file_exists($_FILES['userfile']['tmp_name'][$currentAttachmentNo]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][$currentAttachmentNo])) {
                                throw new Exception('SYS_FILE_NOT_EXIST');
                            }

                            if ($_FILES['userfile']['error'][$currentAttachmentNo] === UPLOAD_ERR_OK) {
                                // check filename and throw exception if something is wrong
                                StringUtils::strIsValidFileName($_FILES['userfile']['name'][$currentAttachmentNo], false);

                                // check for valid file extension of attachment
                                if(!FileSystemUtils::allowedFileExtension($_FILES['userfile']['name'][$currentAttachmentNo])) {
                                    throw new Exception('SYS_FILE_EXTENSION_INVALID');
                                }

                                // check the size of the attachment
                                $attachmentSize += $_FILES['userfile']['size'][$currentAttachmentNo];
                                if ($attachmentSize > Email::getMaxAttachmentSize()) {
                                    throw new Exception('SYS_ATTACHMENT_TO_LARGE');
                                }

                                // set file type to standard if not given
                                if (strlen($_FILES['userfile']['type'][$currentAttachmentNo]) <= 0) {
                                    $_FILES['userfile']['type'][$currentAttachmentNo] = 'application/octet-stream';
                                }

                                // add the attachment to the email and message object
                                $email->addAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo], $encoding = 'base64', $_FILES['userfile']['type'][$currentAttachmentNo]);
                                $message->addAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo]);
                            }
                        }
                    }
                }
            } else {
                throw new Exception('SYS_FIELD_EMPTY', array('SYS_SUBJECT'));
            }
        } else {
            throw new Exception('SYS_EMAIL_INVALID', array('SYS_EMAIL'));
        }

        // if possible send html mail
        if ($gValidLogin && $gSettingsManager->getBool('mail_html_registered_users')) {
            $email->setHtmlMail();
        }

        // set flag if copy should be sent to sender
        if ( isset($formValues['carbon_copy']) && $formValues['carbon_copy']) {
            $email->setCopyToSenderFlag();
        }

        // add confirmation mail to the sender
        if ($formValues['delivery_confirmation']) {
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
        $email->setTemplateText($formValues['msg_body'], $formValues['namefrom'], $formValues['mailfrom'], $gCurrentUser->getValue('usr_uuid'), $receiverName);

        // finally send the mail
        $sendResult = $email->sendEmail();

        // within this mode a smtp protocol will be shown and the header was still send to browser
        if ($gDebug && headers_sent()) {
            $email->isSMTP();
            $gMessage->showHtmlTextOnly();
        }
    } else {
        // ***** PM *****
        // if $postTo is not an Array, it is sent from the hidden field.
        if (!is_array($postTo)) {
            $postTo = array($postTo);
        }

        // check if user is allowed to view message
        if (!in_array($gCurrentUserId, array($message->getValue('msg_usr_id_sender'), $message->getConversationPartner()))) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // get user data from Database
        $user = new User($gDb, $gProfileFields, $postTo[0]);

        // add user to the message object
        $message->addUser($user->getValue('usr_id'));
        $message->setValue('msg_read', 1);

        // check if it is allowed to send to this user
        if ((!$gCurrentUser->isAdministratorUsers() && !isMember((int)$user->getValue('usr_id'))) || $user->getValue('usr_id') === '') {
            throw new Exception('SYS_USER_ID_NOT_FOUND');
        }

        // check if receiver of message has valid login
        if ($user->getValue('usr_login_name') === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_TO'));
        }

        $sendResult = true;
    }

    // save message to database if send/save is OK
    if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
        if ($gValidLogin) {
            $message->save();
        }

        // after sending remove the send page from navigation stack
        $gNavigation->deleteLastUrl();

        // message if sending was OK
        if ($getMsgType === Message::MESSAGE_TYPE_PM) {
            $successMessage = $gL10n->get('SYS_PRIVATE_MESSAGE_SEND', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')));
        } else {
            $successMessage = $gL10n->get('SYS_EMAIL_SEND');
        }
        echo json_encode(array('status' => 'success', 'message' => $successMessage, 'url' => $gNavigation->getUrl()));
        exit();
    } else {
        if ($getMsgType === Message::MESSAGE_TYPE_PM) {
            throw new Exception('SYS_PRIVATE_MESSAGE_NOT_SEND', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $sendResult));
        } else {
            throw new Exception('SYS_EMAIL_NOT_SEND', array('SYS_RECIPIENT', $sendResult));
        }
    }
} catch (Throwable $e) {
    handleException($e, true);
}
