<?php
/**
 ***********************************************************************************************
 * messages form page
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * msg_type  - This could be EMAIL if you want to write an email or PM if you want to write a private Message
 * user_uuid - send message to the given user UUID
 * subject   - subject of the message
 * msg_uuid  - UUID of the message -> just for answers
 * role_uuid - UUID of a role to which an email should be sent
 * carbon_copy - false - (Default) "Send copy to me" checkbox is NOT set
 *             - true  - "Send copy to me" checkbox is set
 * forward : true - The message of the msg_id will be copied and the base for this new message
 *
 *****************************************************************************/

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Messages\Entity\Message;
use Admidio\Messages\Entity\MessageContent;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getMsgType = admFuncVariableIsValid($_GET, 'msg_type', 'string', array('defaultValue' => Message::MESSAGE_TYPE_EMAIL));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getSubject = admFuncVariableIsValid($_GET, 'subject', 'string');
    $getMsgUuid = admFuncVariableIsValid($_GET, 'msg_uuid', 'uuid');
    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid');
    $getCarbonCopy = admFuncVariableIsValid($_GET, 'carbon_copy', 'bool', array('defaultValue' => false));
    $getDeliveryConfirmation = admFuncVariableIsValid($_GET, 'delivery_confirmation', 'bool');
    $getForward = admFuncVariableIsValid($_GET, 'forward', 'bool');
    $postUserUuidList = '';
    $postListUuid = '';

    // Check form values
    if ($gValidLogin) {
        $postUserUuidList = admFuncVariableIsValid($_POST, 'userUuidList', 'string');
        $postListUuid = admFuncVariableIsValid($_POST, 'list_uuid', 'uuid');
    }

    $message = new Message($gDb);
    $message->readDataByUuid($getMsgUuid);

    if ($getMsgUuid !== '') {
        $getMsgType = $message->getValue('msg_type');
    }

    // check if the call of the page was allowed by settings
    if ((!$gSettingsManager->getBool('enable_mail_module') && $getMsgType !== Message::MESSAGE_TYPE_PM)
        || (!$gSettingsManager->getBool('enable_pm_module') && $getMsgType === Message::MESSAGE_TYPE_PM)) {
        // message if the sending of PM is not allowed
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // check for valid login
    if (!$gValidLogin && $getMsgType === Message::MESSAGE_TYPE_PM) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // check if the current user has email address for sending an email
    if ($gValidLogin && $getMsgType !== Message::MESSAGE_TYPE_PM && !$gCurrentUser->hasEmail()) {
        throw new Exception('SYS_CURRENT_USER_NO_EMAIL', array('<a href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php">', '</a>'));
    }

    // Update the read status of the message
    if ($getMsgUuid !== '') {
        // check if user is allowed to view message
        if (!in_array($gCurrentUserId, array($message->getValue('msg_usr_id_sender'), $message->getConversationPartner()))) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // update the read-status
        $message->setReadValue();

        if ($getForward === true) {
            $getMsgUuid = '';
        } else {
            $messageStatement = $message->getConversation($message->getValue('msg_id'));
            $message->addContent('');
        }

        $getSubject = $message->getValue('msg_subject', 'database');
        if ($gCurrentUserId !== $message->getValue('msg_usr_id_sender')) {
            $user = new User($gDb, $gProfileFields, $message->getValue('msg_usr_id_sender'));
        } else {
            $user = new User($gDb, $gProfileFields, $message->getConversationPartner());
        }
        $getUserUuid = $user->getValue('usr_uuid');
    } elseif ($getUserUuid !== '') {
        $message->setValue('msg_subject', $getSubject);
        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUuid);
    }

    $maxNumberRecipients = 1;
    if ($getMsgType !== Message::MESSAGE_TYPE_PM && $gSettingsManager->getInt('mail_max_receiver') > 0) {
        $maxNumberRecipients = $gSettingsManager->getInt('mail_max_receiver');
    }

    $list = array();

    if ($gValidLogin && $getMsgType === Message::MESSAGE_TYPE_PM && count($gCurrentUser->getRolesWriteMails()) > 0) {
        $sql = 'SELECT usr_id, first_name.usd_value AS first_name, last_name.usd_value AS last_name, usr_login_name
              FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
        INNER JOIN ' . TBL_USERS . '
                ON usr_id = mem_usr_id
         LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
         LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE rol_uuid IN (' . Database::getQmForValues($gCurrentUser->getRolesWriteMails()) . ')
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end   >= ? -- DATE_NOW
               AND usr_id <> ? -- $gCurrentUserId
               AND usr_valid  = true
               AND usr_login_name IS NOT NULL
          GROUP BY usr_id, last_name.usd_value, first_name.usd_value, usr_login_name
          ORDER BY last_name.usd_value, first_name.usd_value';
        $queryParamsArr = array(
            array(
                $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
            ),
            $gCurrentUser->getRolesWriteMails(),
            array(
                $gCurrentOrgId,
                DATE_NOW,
                DATE_NOW,
                $gCurrentUserId
            )
        );
        $dropStatement = $gDb->queryPrepared($sql, array_merge($queryParamsArr[0], $queryParamsArr[1], $queryParamsArr[2]));

        while ($row = $dropStatement->fetch()) {
            $list[] = array($row['usr_id'], $row['last_name'] . ' ' . $row['first_name'] . ' (' . $row['usr_login_name'] . ')', '');
        }

        // no roles or users found then show message
        if (count($list) === 0) {
            throw new Exception('SYS_NO_ROLES_AND_USERS');
        }
    }

    if ($getUserUuid !== '') {
        // if a user ID is given, we need to check if the actual user is allowed to contact this user
        if ((!$gCurrentUser->editUsers() && !isMember((int)$user->getValue('usr_id'))) || $user->getValue('usr_id') === '') {
            throw new Exception('SYS_USER_ID_NOT_FOUND');
        }
    }

    if ($getSubject !== '') {
        $headline = $gL10n->get('SYS_SUBJECT') . ': ' . SecurityUtils::encodeHTML($getSubject);
    } else {
        $headline = $gL10n->get('SYS_SEND_EMAIL');
        if ($getMsgType === Message::MESSAGE_TYPE_PM) {
            $headline = $gL10n->get('SYS_SEND_PRIVATE_MESSAGE');
        }
    }

    if (!$gValidLogin && $getUserUuid === '' && $getRoleUuid === '') {
        // visitors have no message modul and start the navigation here
        $gNavigation->addStartUrl(CURRENT_URL, $headline);
    } else {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-messages-write', $headline);

    if ($getMsgType === Message::MESSAGE_TYPE_PM) {
        // show form
        $form = new FormPresenter(
            'adm_pm_send_form',
            'modules/messages.pm.send.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_send.php', array('msg_type' => 'PM', 'msg_uuid' => $getMsgUuid)),
            $page,
            array('enableFileUpload' => true)
        );

        if ($getUserUuid === '') {
            $form->addSelectBox(
                'msg_to',
                $gL10n->get('SYS_TO'),
                $list,
                array(
                    'property' => FormPresenter::FIELD_REQUIRED,
                    'multiselect' => true,
                    'maximumSelectionNumber' => $maxNumberRecipients,
                    'helpTextId' => 'SYS_SEND_PRIVATE_MESSAGE_DESC'
                )
            );
            $sendTo = '';
        } else {
            $form->addInput(
                'msg_to',
                '',
                $user->getValue('usr_id'),
                array('property' => FormPresenter::FIELD_HIDDEN)
            );
            $sendTo = ' ' . $gL10n->get('SYS_TO') . ' ' . $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME') . ' (' . $user->getValue('usr_login_name') . ')';
        }

        if ($getSubject === '') {
            $form->addInput(
                'msg_subject',
                $gL10n->get('SYS_SUBJECT'),
                $message->getValue('msg_subject'),
                array('maxLength' => 77, 'property' => FormPresenter::FIELD_REQUIRED)
            );
        } else {
            $form->addInput(
                'msg_subject',
                '',
                $message->getValue('msg_subject'),
                array('property' => FormPresenter::FIELD_HIDDEN)
            );
        }

        $form->addMultilineTextInput(
            'msg_body',
            $gL10n->get('SYS_MESSAGE'),
            $message->getContent('database'),
            10,
            array('maxLength' => 254, 'property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addSubmitButton(
            'adm_button_send',
            $gL10n->get('SYS_SEND'),
            array('icon' => 'bi-envelope-fill')
        );

        // add form to html page
        $page->assignSmartyVariable('userUuid', $getUserUuid);
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    } elseif ($getMsgType === Message::MESSAGE_TYPE_EMAIL && $getMsgUuid === '') {
        if ($getUserUuid !== '') {
            // check if the user has email address for receiving an email
            if (!$user->hasEmail()) {
                throw new Exception('SYS_USER_NO_EMAIL', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')));
            }
        } elseif ($getRoleUuid !== '') {
            // if a certain role is called, then check if the rights for it are available
            $role = new Role($gDb);
            $role->readDataByUuid($getRoleUuid);

            // Logged-out users are only allowed to write mails to roles with the flag "all visitors of the site"
            // Logged-in users are only allowed to write mails to roles they are authorized for
            // all roles must belong to the current organization
            if ((!$gValidLogin && $role->getValue('rol_mail_this_role') != 3)
                || ($gValidLogin && !$gCurrentUser->hasRightSendMailToRole($role->getValue('rol_id')))
                || $role->getValue('rol_id') == null) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }

            $rollenName = $role->getValue('rol_name');
        }

        // show form
        $form = new FormPresenter(
            'adm_email_send_form',
            'modules/messages.email.send.tpl',
            ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_send.php',
            $page,
            array('enableFileUpload' => true)
        );

        $sqlRoleUUIDs = array();
        $sqlUserIds = '';
        $sqlParticipationRoles = '';
        $possibleEmails = 0;

        if ($getUserUuid !== '') {
            // usr_id was committed then write email to this user
            $preloadData = $getUserUuid;
            $sqlUserIds = ' AND usr_id = ? -- $user->getValue(\'usr_id\')';
        } elseif ($getRoleUuid !== '') {
            // role id was committed then write email to this role
            $preloadData = 'groupID: ' . $getRoleUuid;
            $sqlRoleUUIDs = array($role->getValue('rol_uuid'));
        } else {
            // no user or role was committed then show list with all roles and users
            // where the current user has the right to send email
            $preloadData = '';
            $sqlRoleUUIDs = $gCurrentUser->getRolesWriteMails();
            $sqlParticipationRoles = ' AND cat_name_intern <> \'EVENTS\' ';
        }

        // no role id set, then list all roles according to login/logout
        if ($gValidLogin) {
            $list = array();
            $listFormer = array();
            $listActiveAndFormer = array();
            $listRoleIdsArray = array();

            if (count($sqlRoleUUIDs) === 0) {
                // if only send mail to one user than this user must be in a role the current user is allowed to see
                $listVisibleRoleArray = $gCurrentUser->getRolesViewMemberships();
            } else {
                // list array with all roles where user is allowed to send mail to
                $sql = 'SELECT rol_id, rol_uuid, rol_name
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                       AND (  cat_org_id = ? -- $gCurrentOrgId
                           OR cat_org_id IS NULL)
                     WHERE rol_uuid IN (' . Database::getQmForValues($sqlRoleUUIDs) . ')
                       AND rol_valid = true
                           ' . $sqlParticipationRoles . '
                  ORDER BY rol_name ASC';
                $rolesStatement = $gDb->queryPrepared($sql, array_merge(array($gCurrentOrgId), $sqlRoleUUIDs));
                $rolesArray = $rolesStatement->fetchAll();

                foreach ($rolesArray as $roleArray) {
                    $role = new Role($gDb);
                    $role->setArray($roleArray);
                    $list[] = array('groupID: ' . $roleArray['rol_uuid'], $roleArray['rol_name'], $gL10n->get('SYS_ROLES') . ' (' . $gL10n->get('SYS_ACTIVE_MEMBERS') . ')');
                    $listRoleIdsArray[] = $roleArray['rol_uuid'];
                    if ($role->hasFormerMembers() > 0 && $gSettingsManager->getBool('mail_show_former')) {
                        // list role with former members
                        $listFormer[] = array('groupID: ' . $roleArray['rol_uuid'] . '+1', $roleArray['rol_name'] . ' ' . '(' . $gL10n->get('SYS_FORMER_PL') . ')', $gL10n->get('SYS_ROLES') . ' (' . $gL10n->get('SYS_FORMER_MEMBERS') . ')');
                        // list role with active and former members
                        $listActiveAndFormer[] = array('groupID: ' . $roleArray['rol_uuid'] . '+2', $roleArray['rol_name'] . ' ' . '(' . $gL10n->get('SYS_ACTIVE_FORMER_MEMBERS_SHORT') . ')', $gL10n->get('SYS_ROLES') . ' (' . $gL10n->get('SYS_ACTIVE_FORMER_MEMBERS') . ')');
                    }
                }

                $list = array_merge($list, $listFormer, $listActiveAndFormer);
                $listVisibleRoleArray = array_intersect($listRoleIdsArray, $gCurrentUser->getRolesViewMemberships());
            }

            if ($getRoleUuid === '' && count($listVisibleRoleArray) > 0) {
                // if no special role was preselected then list users
                $sql = 'SELECT usr_uuid, first_name.usd_value AS first_name, last_name.usd_value AS last_name, rol_id, mem_begin, mem_end
                      FROM ' . TBL_MEMBERS . '
                INNER JOIN ' . TBL_ROLES . '
                        ON rol_id = mem_rol_id
                INNER JOIN ' . TBL_USERS . '
                        ON usr_id = mem_usr_id
                INNER JOIN ' . TBL_USER_DATA . ' AS email
                        ON email.usd_usr_id = usr_id
                       AND LENGTH(email.usd_value) > 0
                INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                        ON field.usf_id = email.usd_usf_id
                       AND field.usf_type = \'EMAIL\'
                 LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                        ON last_name.usd_usr_id = usr_id
                       AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                 LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                        ON first_name.usd_usr_id = usr_id
                       AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                     WHERE usr_id    <> ? -- $gCurrentUserId
                       AND mem_begin <= ? -- DATE_NOW
                       AND rol_uuid IN (' . Database::getQmForValues($listVisibleRoleArray) . ')
                           ' . $sqlUserIds . '
                       AND usr_valid = true
                  ORDER BY last_name, first_name, mem_end DESC';
                $queryParams = array_merge(
                    array(
                        (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                        (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                        $gCurrentUserId,
                        DATE_NOW
                    ),
                    $listVisibleRoleArray
                );
                if ($sqlUserIds !== '') {
                    $queryParams[] = $user->getValue('usr_id');
                }
                $statement = $gDb->queryPrepared($sql, $queryParams);

                $passiveList = array();
                $activeList = array();
                $currentUserId = '';

                while ($row = $statement->fetch()) {
                    // every user should only be once in the list
                    if ($currentUserId !== $row['usr_uuid']) {
                        // if membership is active then show them as active members
                        if ($row['mem_begin'] <= DATE_NOW && $row['mem_end'] >= DATE_NOW) {
                            $activeList[] = array($row['usr_uuid'], $row['last_name'] . ' ' . $row['first_name'], $gL10n->get('SYS_ACTIVE_MEMBERS'));
                            $currentUserId = $row['usr_uuid'];
                        } elseif ($gSettingsManager->getBool('mail_show_former')) {
                            $passiveList[] = array($row['usr_uuid'], $row['last_name'] . ' ' . $row['first_name'], $gL10n->get('SYS_FORMER_MEMBERS'));
                            $currentUserId = $row['usr_uuid'];
                        }
                    }
                }

                $list = array_merge($list, $activeList, $passiveList);
            }
        } else {
            $maxNumberRecipients = 1;
            // list all roles where guests could send mails to
            $sql = 'SELECT rol_uuid, rol_name
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL)
                 WHERE rol_mail_this_role = 3
                   AND rol_valid = true
              ORDER BY cat_sequence, rol_name';

            $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
            while ($row = $statement->fetch()) {
                $list[] = array('groupID: ' . $row['rol_uuid'], $row['rol_name'], '');
            }
        }

        if ($postListUuid !== '') {
            $preloadData = 'dummy';
            $showList = new ListConfiguration($gDb);
            $showList->readDataByUuid($postListUuid);
            $list = array('dummy' => $gL10n->get('SYS_LIST') . (strlen($showList->getValue('lst_name')) > 0 ? ' - ' . $showList->getValue('lst_name') : ''));
            $form->addInput('userUuidList', '', $postUserUuidList, array('property' => FormPresenter::FIELD_HIDDEN));
            $form->addInput('list_uuid', '', $postListUuid, array('property' => FormPresenter::FIELD_HIDDEN));
        }

        // no roles or users found then show message
        if (count($list) === 0) {
            throw new Exception('SYS_NO_ROLES_AND_USERS');
        }

        $form->addSelectBox(
            'msg_to',
            $gL10n->get('SYS_TO'),
            $list,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'multiselect' => true,
                'maximumSelectionNumber' => $maxNumberRecipients,
                'helpTextId' => ($gValidLogin ? '' : 'SYS_SEND_MAIL_TO_ROLE'),
                'defaultValue' => $preloadData
            )
        );

        if ($gCurrentUserId > 0) {
            $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_USER_FIELDS . '
            INNER JOIN ' . TBL_USER_DATA . '
                    ON usd_usf_id = usf_id
                 WHERE usf_type = \'EMAIL\'
                   AND usd_usr_id = ? -- $gCurrentUserId
                   AND usd_value IS NOT NULL';

            $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentUserId));
            $possibleEmails = $pdoStatement->fetchColumn();

            $form->addInput(
                'namefrom',
                $gL10n->get('SYS_YOUR_NAME'),
                $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'),
                array('maxLength' => 50, 'property' => FormPresenter::FIELD_DISABLED)
            );

            if ($possibleEmails > 1) {
                $sqlData = array();
                $sqlData['query'] = 'SELECT email.usd_value AS ID, email.usd_value AS email
                                   FROM ' . TBL_USERS . '
                             INNER JOIN ' . TBL_USER_DATA . ' AS email
                                     ON email.usd_usr_id = usr_id
                                    AND LENGTH(email.usd_value) > 0
                             INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                                     ON field.usf_id = email.usd_usf_id
                                    AND field.usf_type = \'EMAIL\'
                                  WHERE usr_id = ? -- $gCurrentUserId
                                    AND usr_valid = true
                               GROUP BY email.usd_value, email.usd_value';
                $sqlData['params'] = array($gCurrentUserId);

                $form->addSelectBoxFromSql(
                    'mailfrom',
                    $gL10n->get('SYS_YOUR_EMAIL'),
                    $gDb,
                    $sqlData,
                    array('maxLength' => 100, 'defaultValue' => $gCurrentUser->getValue('EMAIL'), 'showContextDependentFirstEntry' => false)
                );
            } else {
                $form->addInput(
                    'mailfrom',
                    $gL10n->get('SYS_YOUR_EMAIL'),
                    $gCurrentUser->getValue('EMAIL'),
                    array('type' => 'email', 'maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED)
                );
            }
        } else {
            $form->addInput(
                'namefrom',
                $gL10n->get('SYS_YOUR_NAME'),
                '',
                array('maxLength' => 50, 'property' => FormPresenter::FIELD_REQUIRED)
            );
            $form->addInput(
                'mailfrom',
                $gL10n->get('SYS_YOUR_EMAIL'),
                '',
                array('type' => 'email', 'maxLength' => 50, 'property' => FormPresenter::FIELD_REQUIRED)
            );
        }

        // show option to send a copy to your email address only for registered users because of spam abuse
        if ($gValidLogin) {
            $form->addCheckbox('carbon_copy', $gL10n->get('SYS_SEND_COPY'), $getCarbonCopy);
        }

        // if preference is set then show a checkbox where the user can request a delivery confirmation for the email
        if (($gCurrentUserId > 0 && (int)$gSettingsManager->get('mail_delivery_confirmation') === 2) || (int)$gSettingsManager->get('mail_delivery_confirmation') === 1) {
            $form->addCheckbox('delivery_confirmation', $gL10n->get('SYS_DELIVERY_CONFIRMATION'), $getDeliveryConfirmation);
        }

        $form->addInput(
            'msg_subject',
            $gL10n->get('SYS_SUBJECT'),
            $message->getValue('msg_subject'),
            array('maxLength' => 77, 'property' => FormPresenter::FIELD_REQUIRED)
        );

        // add multiline text element or ckeditor to form
        if ($gValidLogin && $gSettingsManager->getBool('mail_html_registered_users')) {
            $form->addEditor(
                'msg_body',
                $gL10n->get('SYS_TEXT'),
                $message->getContent(),
                array(
                    'property' => FormPresenter::FIELD_REQUIRED,
                    'helpTextId' => ($gValidLogin && $gSettingsManager->getInt('mail_sending_mode') === Email::SENDINGMODE_SINGLE) ? array('SYS_EMAIL_PARAMETERS_DESC', array('#recipient_firstname#', '#recipient_lastname#', '#recipient_name#', '#recipient_email#')) : null
                )
            );
        } else {
            $form->addMultilineTextInput(
                'msg_body',
                $gL10n->get('SYS_TEXT'),
                $message->getContent('database'),
                10,
                array('property' => FormPresenter::FIELD_REQUIRED)
            );
        }

        // Only logged-in users are allowed to attach files
        if ($gValidLogin && ($gSettingsManager->getInt('max_email_attachment_size') > 0) && PhpIniUtils::isFileUploadEnabled()) {
            $form->addFileUpload(
                'btn_add_attachment',
                $gL10n->get('SYS_ATTACHMENT'),
                array(
                    'enableMultiUploads' => true,
                    'maxUploadSize' => Email::getMaxAttachmentSize(),
                    'multiUploadLabel' => $gL10n->get('SYS_ADD_ATTACHMENT'),
                    'hideUploadField' => true,
                    'helpTextId' => $gL10n->get('SYS_MAX_ATTACHMENT_SIZE', array(Email::getMaxAttachmentSize(Email::SIZE_UNIT_MEBIBYTE))),
                    'icon' => 'bi-paperclip'
                )
            );
        }

        // if captchas are enabled then visitors of the website must resolve this
        if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha')) {
            $form->addCaptcha('adm_captcha_code');
        }

        $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_SEND'), array('icon' => 'bi-envelope-fill'));

        // add form to html page and show page
        $page->assignSmartyVariable('possibleEmails', $possibleEmails);
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    if (isset($messageStatement)) {
        $messageContent = new MessageContent($gDb);

        while ($row = $messageStatement->fetch()) {
            $messageContent->setArray($row);
            $messageFooter = '';

            if ($getMsgType === Message::MESSAGE_TYPE_PM) {
                if ($messageContent->getValue('msc_usr_id') === $gCurrentUserId) {
                    $sentUser = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
                } else {
                    $sentUser = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
                }

                $messageHeader = $gL10n->get('SYS_USERNAME_WITH_TIMESTAMP', array($sentUser,
                    $messageContent->getValue('msc_timestamp', $gSettingsManager->getString('system_date')),
                    $messageContent->getValue('msc_timestamp', $gSettingsManager->getString('system_time'))
                ));
                $messageIcon = 'bi-chat-left-fill';
            } else {
                $messageHeader = $messageContent->getValue('msc_timestamp', $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />' . $gL10n->get('SYS_TO') . ': ' . $message->getRecipientsNamesString();
                $messageIcon = 'bi-envelope-fill';
                $attachments = $message->getAttachmentsInformations();

                if (count($attachments) > 0) {
                    $messageFooter .= '<div class="card-footer"><span class="mr-3"><i class="bi bi-paperclip"></i> ' . $gL10n->get('SYS_ATTACHMENT') . '</span>';
                }

                foreach ($attachments as $attachment) {
                    // get complete path with filename of the attachment
                    $attachmentPath = ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/' . $attachment['admidio_file_name'];

                    if (file_exists($attachmentPath)) {
                        $messageFooter .= '<span class="admidio-attachment mr-3"><a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/get_attachment.php', array('msa_uuid' => $attachment['msa_uuid'])) . '">' . $attachment['file_name'] . '</a></span>';
                    } else {
                        $messageFooter .= '<span class="admidio-attachment mr-3">' . $attachment['file_name'] . '</span>';
                    }
                }

                if (count($attachments) > 0) {
                    $messageFooter .= '</div>';
                }
            }

            $page->addHtml('
        <div class="card admidio-blog">
            <div class="card-header">
                <i class="bi ' . $messageIcon . '"></i>' . $messageHeader . '
            </div>
            <div class="card-body">' . $messageContent->getValue('msc_message') . '</div>
            ' . $messageFooter . '
        </div>');
        }
    }

    // show page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
