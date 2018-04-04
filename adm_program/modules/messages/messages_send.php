<?php
/**
 ***********************************************************************************************
 * Check message information and save it
 *
 * @copyright 2004-2018 The Admidio Team
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

// Initialize and check the parameters
$getMsgId   = admFuncVariableIsValid($_GET, 'msg_id',   'int');
$getMsgType = admFuncVariableIsValid($_GET, 'msg_type', 'string');

// Check form values
$postFrom       = admFuncVariableIsValid($_POST, 'mailfrom', 'string');
$postName       = admFuncVariableIsValid($_POST, 'namefrom', 'string');
$postSubject    = admFuncVariableIsValid($_POST, 'subject',  'html');
$postSubjectSQL = admFuncVariableIsValid($_POST, 'subject',  'string');
$postBody       = admFuncVariableIsValid($_POST, 'msg_body', 'html');
$postBodySQL    = admFuncVariableIsValid($_POST, 'msg_body', 'string');
$postDeliveryConfirmation = admFuncVariableIsValid($_POST, 'delivery_confirmation', 'bool');
$postCaptcha    = admFuncVariableIsValid($_POST, 'captcha_code', 'string');
$postUserIdList = admFuncVariableIsValid($_POST, 'userIdList',   'string');
$postListId     = admFuncVariableIsValid($_POST, 'lst_id',       'int');

// save form data in session for back navigation
$_SESSION['message_request'] = $_POST;

// save page in navigation - to have a check for a navigation back.
$gNavigation->addUrl(CURRENT_URL);

if (isset($_POST['msg_to']))
{
    $postTo = $_POST['msg_to'];
}
else
{
    // message when no receiver is given
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TO'))));
    // => EXIT
}

if ($postSubjectSQL === '')
{
    // message when no subject is given
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('MAI_SUBJECT'))));
    // => EXIT
}

if ($postBodySQL === '')
{
    // message when no subject is given
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_MESSAGE'))));
    // => EXIT
}

$message = new TableMessage($gDb, $getMsgId);

if ($getMsgId > 0)
{
    $getMsgType = $message->getValue('msg_type');
}

// if message not PM it must be Email and then directly check the parameters
if ($getMsgType !== TableMessage::MESSAGE_TYPE_PM)
{
    $getMsgType = TableMessage::MESSAGE_TYPE_EMAIL;
}

// Stop if pm should be send pm module is disabled
if ($getMsgType === TableMessage::MESSAGE_TYPE_PM && !$gSettingsManager->getBool('enable_pm_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Stop if mail should be send and mail module is disabled
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL && !$gSettingsManager->getBool('enable_mail_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$sendResult = false;
$currUsrId = (int) $gCurrentUser->getValue('usr_id');

// if message is EMAIL then check the parameters
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL)
{
    // allow option to send a copy to your email address only for registered users because of spam abuse
    $postCarbonCopy = 0;
    if ($gValidLogin)
    {
        $postCarbonCopy = admFuncVariableIsValid($_POST, 'carbon_copy', 'bool');
    }

    // if Attachment size is higher than max_post_size from php.ini, then $_POST is empty.
    if (empty($_POST))
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // Check Captcha if enabled and user logged out
    if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha'))
    {
        try
        {
            FormValidation::checkCaptcha($postCaptcha);
        }
        catch (AdmException $e)
        {
            $e->showHtml();
            // => EXIT
        }
    }
}

// if user is logged in then show sender name and email
if ($currUsrId > 0)
{
    $postName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
    if (!strValidCharacters($postFrom, 'email'))
    {
        $postFrom = $gCurrentUser->getValue('EMAIL');
    }
}
else
{
    if ($postName === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('MAI_YOUR_NAME'))));
        // => EXIT
    }
    if (!strValidCharacters($postFrom, 'email'))
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('MAI_YOUR_EMAIL'))));
        // => EXIT
    }
}

// if no User is set, he is not able to ask for delivery confirmation
if (!($currUsrId > 0 && (int) $gSettingsManager->get('mail_delivery_confirmation') === 2)
&&  (int) $gSettingsManager->get('mail_delivery_confirmation') !== 1)
{
    $postDeliveryConfirmation = false;
}

// check if PM or Email and to steps:
if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL)
{
    $receiver = array();
    $receiverString = '';

    if (isset($postTo))
    {
        if ($postListId > 0) // the id of a list was passed
        {
            $postTo = explode(',', $postUserIdList);
        }

        // Create new Email Object
        $email = new Email();

        foreach ($postTo as $value)
        {
            // check if role or user is given
            if (StringUtils::strContains($value, ':'))
            {
                $moduleMessages = new ModuleMessages();
                $group = $moduleMessages->msgGroupSplit($value);

                // check if role rights are granted to the User
                $sql = 'SELECT rol_mail_this_role, rol_id
                          FROM '.TBL_ROLES.'
                    INNER JOIN '.TBL_CATEGORIES.'
                            ON cat_id = rol_cat_id
                           AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                               OR cat_org_id IS NULL)
                         WHERE rol_id = ? -- $group[\'id\']';
                $statement = $gDb->queryPrepared($sql, array($gCurrentOrganization->getValue('org_id'), $group['id']));
                $row = $statement->fetch();

                // logged out ones just to role with permission level "all visitors"
                // logged in user is just allowed to send to role with permission
                // role must be from actual Organisation
                if ((!$gValidLogin && (int) $row['rol_mail_this_role'] !== 3)
                || ($gValidLogin  && !$gCurrentUser->hasRightSendMailToRole((int) $row['rol_id']))
                || $row['rol_id'] === null)
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                    // => EXIT
                }

                $queryParams = array(
                    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                    $group['id'],
                    $gCurrentOrganization->getValue('org_id')
                );

                if ($group['status'] === 'former' && $gSettingsManager->getBool('mail_show_former'))
                {
                    // only former members
                    $sqlConditions = ' AND mem_end < ? -- DATE_NOW ';
                    $queryParams[] = DATE_NOW;
                }
                elseif ($group['status'] === 'active_former' && $gSettingsManager->getBool('mail_show_former'))
                {
                    // former members and active members
                    $sqlConditions = ' AND mem_begin < ? -- DATE_NOW ';
                    $queryParams[] = DATE_NOW;
                }
                else
                {
                    // only active members
                    $sqlConditions = ' AND mem_begin <= ? -- DATE_NOW
                                       AND mem_end    > ? -- DATE_NOW ';
                    $queryParams[] = DATE_NOW;
                    $queryParams[] = DATE_NOW;
                }

                $sql = 'SELECT first_name.usd_value AS firstName, last_name.usd_value AS lastName, email.usd_value AS email
                          FROM '.TBL_MEMBERS.'
                    INNER JOIN '.TBL_ROLES.'
                            ON rol_id = mem_rol_id
                    INNER JOIN '.TBL_CATEGORIES.'
                            ON cat_id = rol_cat_id
                    INNER JOIN '.TBL_USERS.'
                            ON usr_id = mem_usr_id
                    INNER JOIN '.TBL_USER_DATA.' AS email
                            ON email.usd_usr_id = usr_id
                           AND LENGTH(email.usd_value) > 0
                    INNER JOIN '.TBL_USER_FIELDS.' AS field
                            ON field.usf_id = email.usd_usf_id
                           AND field.usf_type = \'EMAIL\'
                     LEFT JOIN '.TBL_USER_DATA.' AS last_name
                            ON last_name.usd_usr_id = usr_id
                           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                     LEFT JOIN '.TBL_USER_DATA.' AS first_name
                            ON first_name.usd_usr_id = usr_id
                           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                         WHERE rol_id    = ? -- $group[\'id\']
                           AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                               OR cat_org_id IS NULL )
                           AND usr_valid = 1
                               '.$sqlConditions;

                // Wenn der User eingeloggt ist, wird die UserID im Statement ausgeschlossen,
                // damit er die Mail nicht an sich selber schickt.
                if ($gValidLogin)
                {
                    $sql .= '
                        AND usr_id <> ? -- $currUsrId';
                    $queryParams[] = $currUsrId;
                }
                $statement = $gDb->queryPrepared($sql, $queryParams);

                if ($statement->rowCount() > 0)
                {
                    // normally we need no To-address and set "undisclosed recipients", but if
                    // that won't work than the following address will be set
                    if ((int) $gSettingsManager->get('mail_recipients_with_roles') === 1)
                    {
                        // fill recipient with sender address to prevent problems with provider
                        $email->addRecipient($postFrom, $postName);
                    }
                    elseif ((int) $gSettingsManager->get('mail_recipients_with_roles') === 2)
                    {
                        // fill recipient with administrators address to prevent problems with provider
                        $email->addRecipient($gSettingsManager->getString('email_administrator'), $gL10n->get('SYS_ADMINISTRATOR'));
                    }

                    // all role members will be attached as BCC
                    while ($row = $statement->fetch())
                    {
                        if (strValidCharacters($row['email'], 'email'))
                        {
                            $receiver[] = array($row['email'], $row['firstName'] . ' ' . $row['lastName']);
                        }
                    }
                }
            }
            else
            {
                // create user object
                $user = new User($gDb, $gProfileFields, $value);

                // only send email to user if current user is allowed to view this user and he has a valid email address
                if ($gCurrentUser->hasRightViewProfile($user) && strValidCharacters($user->getValue('EMAIL'), 'email'))
                {
                    $receiver[] = array($user->getValue('EMAIL'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
                }
            }
        }
        $receiverString = implode(' | ', $postTo);
    }
    else
    {
        // message when no receiver is given
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // if no valid recipients exists show message
    if (count($receiver) === 0)
    {
        $gMessage->show($gL10n->get('MSG_NO_VALID_RECIPIENTS'));
        // => EXIT
    }

    // check if name is given
    if ($postName === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    // if valid login then sender should always current user
    if ($gValidLogin)
    {
        $postName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
    }

    // set sending address
    if ($email->setSender($postFrom, $postName))
    {
        // set subject
        if ($email->setSubject($postSubject))
        {
            // check for attachment
            if (isset($_FILES['userfile']))
            {
                // final check if user is logged in
                if (!$gValidLogin)
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                    // => EXIT
                }
                $attachmentSize = 0;
                // add now every attachment
                for ($currentAttachmentNo = 0; isset($_FILES['userfile']['name'][$currentAttachmentNo]); ++$currentAttachmentNo)
                {
                    // check if Upload was OK
                    if (($_FILES['userfile']['error'][$currentAttachmentNo] !== UPLOAD_ERR_OK)
                    &&  ($_FILES['userfile']['error'][$currentAttachmentNo] !== UPLOAD_ERR_NO_FILE))
                    {
                        $gMessage->show($gL10n->get('MAI_ATTACHMENT_TO_LARGE'));
                        // => EXIT
                    }

                    if ($_FILES['userfile']['error'][$currentAttachmentNo] === UPLOAD_ERR_OK)
                    {
                        // check the size of the attachment
                        $attachmentSize += $_FILES['userfile']['size'][$currentAttachmentNo];
                        if ($attachmentSize > Email::getMaxAttachmentSize())
                        {
                            $gMessage->show($gL10n->get('MAI_ATTACHMENT_TO_LARGE'));
                            // => EXIT
                        }

                        // set filetyp to standard if not given
                        if (strlen($_FILES['userfile']['type'][$currentAttachmentNo]) <= 0)
                        {
                            $_FILES['userfile']['type'][$currentAttachmentNo] = 'application/octet-stream';
                        }

                        // add the attachment to the mail
                        try
                        {
                            $email->addAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo], $encoding = 'base64', $_FILES['userfile']['type'][$currentAttachmentNo]);
                        }
                        catch (phpmailerException $e)
                        {
                            $gMessage->show($e->errorMessage());
                            // => EXIT
                        }
                    }
                }
            }
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('MAI_SUBJECT'))));
            // => EXIT
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))));
        // => EXIT
    }

    // if possible send html mail
    if ($gValidLogin && $gSettingsManager->getBool('mail_html_registered_users'))
    {
        $email->sendDataAsHtml();
    }

    // set flag if copy should be send to sender
    if (isset($postCarbonCopy) && $postCarbonCopy)
    {
        $email->setCopyToSenderFlag();

        // if mail was send to user than show recipients in copy of mail if current user has a valid login
        if ($gValidLogin)
        {
            $email->setListRecipientsFlag();
        }
    }

    // get array with unique receivers
    $sendResults = array_map('unserialize', array_unique(array_map('serialize', $receiver)));
    $receivers = count($sendResults);
    foreach ($sendResults as $address)
    {
        if ($receivers === 1)
        {
            $email->addRecipient($address[0], $address[1]);
        }
        else
        {
            $email->addBlindCopy($address[0], $address[1]);
        }
    }

    // add confirmation mail to the sender
    if ($postDeliveryConfirmation)
    {
        $email->ConfirmReadingTo = $gCurrentUser->getValue('EMAIL');
    }

    // load the template and set the new email body with template
    try
    {
        $emailTemplate = FileSystemUtils::readFile(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/template.html');
    }
    catch (\RuntimeException $exception)
    {
        $emailTemplate = '#message#';
    }

    require_once(__DIR__ . '/messages_functions.php');

    if ($postListId > 0)
    {
        $showList = new ListConfiguration($gDb, $postListId);
        $listName = $showList->getValue('lst_name');
        $receiverString = 'list ' . $gL10n->get('LST_LIST') . ($listName === '' ? '' : ' - ' . $listName);
    }

    $receiverName = prepareReceivers($receiverString);

    $replaces = array(
        '#sender#'   => $postName,
        '#message#'  => $postBody,
        '#receiver#' => $receiverName
    );
    $emailTemplate = StringUtils::strMultiReplace($emailTemplate, $replaces);

    // prepare body of email with note of sender and homepage
    $email->setSenderInText($postName, $receiverName);

    // set Text
    $email->setText($emailTemplate);

    // finally send the mail
    $sendResult = $email->sendEmail();

    // within this mode an smtp protocol will be shown and the header was still send to browser
    if ($gDebug && headers_sent())
    {
        $email->isSMTP();
        $gMessage->showHtmlTextOnly(true);
    }
}
// ***** PM *****
else
{
    // if $postTo is not an Array, it is send from the hidden field.
    if (!is_array($postTo))
    {
        $postTo = array($postTo);
    }

    // get user data from Database
    $user = new User($gDb, $gProfileFields, $postTo[0]);

    // check if it is allowed to send to this user
    if ((!$gCurrentUser->editUsers() && !isMember($user->getValue('usr_id'))) || $user->getValue('usr_id') === '')
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
        // => EXIT
    }

    // check if receiver of message has valid login
    if ($user->getValue('usr_login_name') === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TO'))));
        // => EXIT
    }

    // save page in navigation - to have a check for a navigation back.
    $gNavigation->addUrl(CURRENT_URL);

    if ($getMsgId === 0)
    {
        $sql = 'INSERT INTO '. TBL_MESSAGES. '
                       (msg_type, msg_subject, msg_usr_id_sender, msg_usr_id_receiver, msg_timestamp, msg_read)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 1) -- $getMsgType, $postSubjectSQL, $currUsrId, $postTo[0]';
        $gDb->queryPrepared($sql, array($getMsgType, $postSubjectSQL, $currUsrId, $postTo[0]));
        $getMsgId = $gDb->lastInsertId();
    }
    else
    {
        $sql = 'UPDATE '. TBL_MESSAGES. '
                   SET msg_read = 1
                     , msg_timestamp = CURRENT_TIMESTAMP
                     , msg_usr_id_sender = ? -- $currUsrId
                     , msg_usr_id_receiver = ? -- $postTo[0]
                 WHERE msg_id = ? -- $getMsgId';
        $gDb->queryPrepared($sql, array($currUsrId, $postTo[0], $getMsgId));
    }

    $messagePartNr = $message->countMessageParts() + 1;

    $sql = 'INSERT INTO '. TBL_MESSAGES_CONTENT. '
                   (msc_msg_id, msc_part_id, msc_usr_id, msc_message, msc_timestamp)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP) -- $getMsgId, $messagePartNr, $currUsrId, $postBodySQL';

    if ($gDb->queryPrepared($sql, array($getMsgId, $messagePartNr, $currUsrId, $postBodySQL)))
    {
        $sendResult = true;
    }
}

// message if send/save is OK
if ($sendResult === true) // don't remove check === true. ($sendResult) won't work
{
    // save mail also to database
    if ($getMsgType === TableMessage::MESSAGE_TYPE_EMAIL && $gValidLogin)
    {
        $sql = 'INSERT INTO '. TBL_MESSAGES. '
                       (msg_type, msg_subject, msg_usr_id_sender, msg_usr_id_receiver, msg_timestamp, msg_read)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 0) -- $getMsgType, $postSubjectSQL, $currUsrId, $receiverString';
        $gDb->queryPrepared($sql, array($getMsgType, $postSubjectSQL, $currUsrId, $receiverString));
        $getMsgId = $gDb->lastInsertId();

        $sql = 'INSERT INTO '. TBL_MESSAGES_CONTENT. '
                       (msc_msg_id, msc_part_id, msc_usr_id, msc_message, msc_timestamp)
                VALUES (?, 1, ?, ?, CURRENT_TIMESTAMP) -- $getMsgId, $currUsrId, $postBodySQL';
        $gDb->queryPrepared($sql, array($getMsgId, $currUsrId, $postBodySQL));
    }

    // after sending remove the actual Page from the NaviObject and remove also the send-page
    $gNavigation->deleteLastUrl();
    $gNavigation->deleteLastUrl();

    // message if sending was OK
    if ($gNavigation->count() > 0)
    {
        $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    }
    else
    {
        $gMessage->setForwardUrl($gHomepage, 2000);
    }

    if ($getMsgType === TableMessage::MESSAGE_TYPE_PM)
    {
        $gMessage->show($gL10n->get('MSG_PM_SEND', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))));
        // => EXIT
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
        // => EXIT
    }
}
else
{
    if ($getMsgType === TableMessage::MESSAGE_TYPE_PM)
    {
        $gMessage->show($sendResult . '<br />' . $gL10n->get('MSG_PM_NOT_SEND', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $sendResult)));
        // => EXIT
    }
    else
    {
        $gMessage->show($sendResult . '<br />' . $gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)));
        // => EXIT
    }
}
