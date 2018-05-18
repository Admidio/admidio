<?php
/**
 ***********************************************************************************************
 * messages form page
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * usr_id    - send message to the given user ID
 * subject   - subject of the message
 * msg_id    - ID of the message -> just for answers
 * rol_id    - Statt einem Rollennamen/Kategorienamen kann auch eine RollenId uebergeben werden
 * carbon_copy - false - (Default) Checkbox "Kopie an mich senden" ist NICHT gesetzt
 *             - true  - Checkbox "Kopie an mich senden" ist gesetzt
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getMsgType    = admFuncVariableIsValid($_GET, 'msg_type',    'string');
$getUserId     = admFuncVariableIsValid($_GET, 'usr_id',      'int');
$getSubject    = admFuncVariableIsValid($_GET, 'subject',     'html');
$getMsgId      = admFuncVariableIsValid($_GET, 'msg_id',      'int');
$getRoleId     = admFuncVariableIsValid($_GET, 'rol_id',      'int');
$getCarbonCopy = admFuncVariableIsValid($_GET, 'carbon_copy', 'bool', array('defaultValue' => false));
$getDeliveryConfirmation = admFuncVariableIsValid($_GET, 'delivery_confirmation', 'bool');

// Check form values
$postUserIdList = admFuncVariableIsValid($_POST, 'userIdList', 'string');
$postListId     = admFuncVariableIsValid($_POST, 'lst_id',     'int');

if ($getMsgId > 0)
{
    $message = new TableMessage($gDb, $getMsgId);
    $getMsgType = $message->getValue('msg_type');
}

// check if the call of the page was allowed by settings
if ((!$gSettingsManager->getBool('enable_mail_module') && $getMsgType !== TableMessage::MESSAGE_TYPE_PM)
   || (!$gSettingsManager->getBool('enable_pm_module') && $getMsgType === TableMessage::MESSAGE_TYPE_PM))
{
    // message if the sending of PM is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// check for valid login
if (!$gValidLogin && $getUserId === 0 && $getMsgType === TableMessage::MESSAGE_TYPE_PM)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// check if user has email address for sending a email
if ($gValidLogin && $getMsgType !== TableMessage::MESSAGE_TYPE_PM && $gCurrentUser->getValue('EMAIL') === '')
{
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', array('<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php">', '</a>')));
    // => EXIT
}

$currUsrId = (int) $gCurrentUser->getValue('usr_id');
$currOrgId = (int) $gCurrentOrganization->getValue('org_id');

// Update the read status of the message
if ($getMsgId > 0)
{
    // update the read-status
    $message->setReadValue($currUsrId);

    $getSubject = $message->getValue('msg_subject');
    $getUserId  = $message->getConversationPartner($currUsrId);

    $messageStatement = $message->getConversation($getMsgId);
}

$maxNumberRecipients = 1;
if ($getMsgType !== TableMessage::MESSAGE_TYPE_PM && $gSettingsManager->getInt('mail_max_receiver') > 0)
{
    $maxNumberRecipients = $gSettingsManager->getInt('mail_max_receiver');
}

$list = array();
$arrAllVisibleRoles = $gCurrentUser->getAllVisibleRoles();

if ($gValidLogin && $getMsgType === TableMessage::MESSAGE_TYPE_PM && count($arrAllVisibleRoles) > 0)
{
    $sql = 'SELECT usr_id, first_name.usd_value AS first_name, last_name.usd_value AS last_name, usr_login_name
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
        INNER JOIN '.TBL_USERS.'
                ON usr_id = mem_usr_id
         LEFT JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE rol_id IN ('.replaceValuesArrWithQM($arrAllVisibleRoles).')
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id = ? -- $currOrgId
                   OR cat_org_id IS NULL )
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end   >= ? -- DATE_NOW
               AND usr_id <> ? -- $currUsrId
               AND usr_valid  = 1
               AND usr_login_name IS NOT NULL
          GROUP BY usr_id, last_name.usd_value, first_name.usd_value, usr_login_name
          ORDER BY last_name.usd_value, first_name.usd_value';
    $queryParamsArr = array(
        array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
        ),
        $arrAllVisibleRoles,
        array(
            $currOrgId,
            DATE_NOW,
            DATE_NOW,
            $currUsrId
        )
    );
    $dropStatement = $gDb->queryPrepared($sql, array_merge($queryParamsArr[0], $queryParamsArr[1], $queryParamsArr[2]));

    while ($row = $dropStatement->fetch())
    {
        $list[] = array($row['usr_id'], $row['last_name'].' '.$row['first_name'].' (' .$row['usr_login_name'].')', '');
    }

    // no roles or users found then show message
    if(count($list) === 0)
    {
        $gMessage->show($gL10n->get('MSG_NO_ROLES_AND_USERS'));
        // => EXIT
    }
}

if ($getUserId > 0)
{
    // usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($gDb, $gProfileFields, $getUserId);

    // if an User ID is given, we need to check if the actual user is allowed to contact this user
    if ((!$gCurrentUser->editUsers() && !isMember($user->getValue('usr_id'))) || $user->getValue('usr_id') === '')
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
        // => EXIT
    }
}

if ($getSubject !== '')
{
    $headline = $gL10n->get('MAI_SUBJECT').': '.$getSubject;
}
else
{
    $headline = $gL10n->get('MAI_SEND_EMAIL');
    if ($getMsgType === TableMessage::MESSAGE_TYPE_PM)
    {
        $headline = $gL10n->get('PMS_SEND_PM');
    }
}

// Wenn die letzte URL in der Zuruecknavigation die des Scriptes message_send.php ist,
// dann soll das Formular gefuellt werden mit den Werten aus der Session
if (admStrContains($gNavigation->getUrl(), 'messages_send.php') && isset($_SESSION['message_request']))
{
    // Das Formular wurde also schon einmal ausgefüllt,
    // da der User hier wieder gelandet ist nach der Mailversand-Seite
    $formValues = strStripSlashesDeep($_SESSION['message_request']);
    unset($_SESSION['message_request']);

    if(!isset($formValues['carbon_copy']))
    {
        $formValues['carbon_copy'] = false;
    }
    if(!isset($formValues['delivery_confirmation']))
    {
        $formValues['delivery_confirmation'] = false;
    }
}
else
{
    $formValues['namefrom']    = '';
    $formValues['mailfrom']    = '';
    $formValues['subject']     = $getSubject;
    $formValues['msg_body']    = '';
    $formValues['msg_to']      = '';
    $formValues['carbon_copy'] = $getCarbonCopy;
    $formValues['delivery_confirmation'] = $getDeliveryConfirmation;
}

// create html page object
$page = new HtmlPage($headline);

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// add back link to module menu
$messagesWriteMenu = $page->getMenu();
$messagesWriteMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

if ($getMsgType === TableMessage::MESSAGE_TYPE_PM)
{
    $formParams = array('msg_type' => 'PM');
    if ($getMsgId > 0)
    {
        $formParams['msg_id'] = $getMsgId;
    }

    // show form
    $form = new HtmlForm('pm_send_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_send.php', $formParams), $page, array('enableFileUpload' => true));

    if ($getUserId === 0)
    {
        $form->openGroupBox('gb_pm_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));
        $form->addSelectBox(
            'msg_to', $gL10n->get('SYS_TO'), $list,
            array(
                'property'               => HtmlForm::FIELD_REQUIRED,
                'multiselect'            => true,
                'maximumSelectionNumber' => $maxNumberRecipients,
                'helpTextIdLabel'        => 'MSG_SEND_PM'
            )
        );
        $form->closeGroupBox();
        $sendto = '';
    }
    else
    {
        $form->addInput('msg_to', '', $getUserId, array('type' => 'hidden'));
        $sendto = ' ' . $gL10n->get('SYS_TO') . ' ' .$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').' ('.$user->getValue('usr_login_name').')';
    }

    $form->openGroupBox('gb_pm_message', $gL10n->get('SYS_MESSAGE') . $sendto);

    if($getSubject === '')
    {
        $form->addInput(
            'subject', $gL10n->get('MAI_SUBJECT'), $formValues['subject'],
            array('maxLength' => 77, 'property' => HtmlForm::FIELD_REQUIRED)
        );
    }
    else
    {
        $form->addInput('subject', '', $formValues['subject'], array('type' => 'hidden'));
    }

    $form->addMultilineTextInput(
        'msg_body', $gL10n->get('SYS_PM'), $formValues['msg_body'], 10,
        array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED)
    );

    $form->closeGroupBox();

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => THEME_URL.'/icons/email.png'));

    // add form to html page
    $page->addHtml($form->show(false));
}
elseif (!isset($messageStatement))
{
    if ($getUserId > 0)
    {
        // besitzt der User eine gueltige E-Mail-Adresse
        if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
        {
            $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'))));
            // => EXIT
        }
    }
    elseif ($getRoleId > 0)
    {
        // wird eine bestimmte Rolle aufgerufen, dann pruefen, ob die Rechte dazu vorhanden sind
        $role = new TableRoles($gDb);
        $role->readDataById($getRoleId);

        // Ausgeloggte duerfen nur an Rollen mit dem Flag "alle Besucher der Seite" Mails schreiben
        // Eingeloggte duerfen nur an Rollen Mails schreiben, zu denen sie berechtigt sind
        // Rollen muessen zur aktuellen Organisation gehoeren
        if((!$gValidLogin && $role->getValue('rol_mail_this_role') != 3)
        || ($gValidLogin  && !$gCurrentUser->hasRightSendMailToRole($getRoleId))
        || $role->getValue('rol_id') == null)
        {
           $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
            // => EXIT
        }

        $rollenName = $role->getValue('rol_name');
    }

    $formParams = array();

    // if subject was set as param then send this subject to next script
    if ($getSubject !== '')
    {
        $formParams['subject'] = $getSubject;
    }

    // show form
    $form = new HtmlForm('mail_send_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_send.php', $formParams), $page, array('enableFileUpload' => true));
    $form->openGroupBox('gb_mail_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));

    $sqlRoleIds = array();
    $sqlUserIds = '';
    $sqlParticipationRoles = '';

    if ($getUserId > 0)
    {
        // usr_id was committed then write email to this user
        $preloadData = $getUserId;
        $sqlUserIds  = ' AND usr_id = ? -- $getUserId';
    }
    elseif ($getRoleId > 0)
    {
        // role id was committed then write email to this role
        $preloadData = 'groupID: '.$getRoleId;
        $sqlRoleIds  = array($getRoleId);
    }
    else
    {
        // no user or role was committed then show list with all roles and users
        // where the current user has the right to send email
        $preloadData = isset($formValues['msg_to']) ? $formValues['msg_to'] : '';
        $sqlRoleIds = $gCurrentUser->getAllMailRoles();
        $sqlParticipationRoles = ' AND cat_name_intern <> \'EVENTS\' ';
    }

    // keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
    if ($gValidLogin)
    {
        $list = array();
        $listFormer = array();
        $listActiveAndFormer = array();
        $listRoleIdsArray = array();

        if(count($sqlRoleIds) === 0)
        {
            // if only send mail to one user than this user must be in a role the current user is allowed to see
            $listVisibleRoleArray = $arrAllVisibleRoles;
        }
        else
        {
            // list array with all roles where user is allowed to send mail to
            $sql = 'SELECT rol_id, rol_name
                      FROM '.TBL_ROLES.'
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                       AND (  cat_org_id = ? -- $currOrgId
                           OR cat_org_id IS NULL)
                     WHERE rol_id IN ('.replaceValuesArrWithQM($sqlRoleIds).')
                       AND rol_valid = 1
                           '.$sqlParticipationRoles.'
                  ORDER BY rol_name ASC';
            $rolesStatement = $gDb->queryPrepared($sql, array_merge(array($currOrgId), $sqlRoleIds));
            $rolesArray = $rolesStatement->fetchAll();

            foreach ($rolesArray as $roleArray)
            {
                // Rollenobjekt anlegen
                $role = new TableRoles($gDb);
                $role->setArray($roleArray);
                $list[] = array('groupID: '.$roleArray['rol_id'], $roleArray['rol_name'], $gL10n->get('SYS_ROLES'). ' (' .$gL10n->get('LST_ACTIVE_MEMBERS') . ')');
                $listRoleIdsArray[] = $roleArray['rol_id'];
                if($role->hasFormerMembers() > 0 && $gSettingsManager->getBool('mail_show_former'))
                {
                    // list role with former members
                    $listFormer[] = array('groupID: '.$roleArray['rol_id'].'-1', $roleArray['rol_name'].' '.'('.$gL10n->get('SYS_FORMER_PL').')', $gL10n->get('SYS_ROLES'). ' (' .$gL10n->get('LST_FORMER_MEMBERS') . ')');
                    // list role with active and former members
                    $listActiveAndFormer[] = array('groupID: '.$roleArray['rol_id'].'-2', $roleArray['rol_name'].' '.'('.$gL10n->get('MSG_ACTIVE_FORMER_SHORT').')', $gL10n->get('SYS_ROLES'). ' (' .$gL10n->get('LST_ACTIVE_FORMER_MEMBERS') . ')');
                }
            }

            $list = array_merge($list, $listFormer, $listActiveAndFormer);
            $listVisibleRoleArray = array_intersect($listRoleIdsArray, $arrAllVisibleRoles);
        }

        if($getRoleId === 0 && count($listVisibleRoleArray) > 0)
        {
            // if no special role was preselected then list users
            $sql = 'SELECT usr_id, first_name.usd_value AS first_name, last_name.usd_value AS last_name, rol_id, mem_begin, mem_end
                      FROM '.TBL_MEMBERS.'
                INNER JOIN '.TBL_ROLES.'
                        ON rol_id = mem_rol_id
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
                     WHERE usr_id    <> ? -- $currUsrId
                       AND mem_begin <= ? -- DATE_NOW
                       AND rol_id IN ('.replaceValuesArrWithQM($listVisibleRoleArray).')
                           '.$sqlUserIds.'
                       AND usr_valid = 1
                  ORDER BY last_name, first_name, mem_end DESC';
            $queryParams = array_merge(
                array(
                    (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                    (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                    $currUsrId,
                    DATE_NOW
                ),
                $listVisibleRoleArray
            );
            if ($sqlUserIds !== '')
            {
                $queryParams[] = $getUserId;
            }
            $statement = $gDb->queryPrepared($sql, $queryParams);

            $passiveList = array();
            $activeList = array();

            while ($row = $statement->fetch())
            {
                $usrId = (int) $row['usr_id'];

                // every user should only be once in the list
                if (!isset($currentUserId) || $currentUserId !== $usrId)
                {
                    // if membership is active then show them as active members
                    if($row['mem_begin'] <= DATE_NOW && $row['mem_end'] >= DATE_NOW)
                    {
                        $activeList[]  = array($usrId, $row['last_name'].' '.$row['first_name'], $gL10n->get('LST_ACTIVE_MEMBERS'));
                        $currentUserId = $usrId;
                    }
                    elseif($gSettingsManager->getBool('mail_show_former'))
                    {
                        $passiveList[] = array($usrId, $row['last_name'].' '.$row['first_name'], $gL10n->get('LST_FORMER_MEMBERS'));
                        $currentUserId = $usrId;
                    }
                }
            }

            $list = array_merge($list, $activeList, $passiveList);
        }
    }
    else
    {
        $maxNumberRecipients = 1;
        // list all roles where guests could send mails to
        $sql = 'SELECT rol_id, rol_name
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                   AND (  cat_org_id = ? -- $currOrgId
                       OR cat_org_id IS NULL)
                 WHERE rol_mail_this_role = 3
                   AND rol_valid = 1
              ORDER BY cat_sequence, rol_name';

        $statement = $gDb->queryPrepared($sql, array($currOrgId));
        while ($row = $statement->fetch())
        {
            $list[] = array('groupID: '.$row['rol_id'], $row['rol_name'], '');
        }
    }

    if($postListId > 0)
    {
        $preloadData = 'dummy';
        $showlist = new ListConfiguration($gDb, $postListId);
        $list = array('dummy' => $gL10n->get('LST_LIST'). (strlen($showlist->getValue('lst_name')) > 0 ? ' - '.$showlist->getValue('lst_name') : ''));
        $form->addInput('userIdList', '', $postUserIdList, array('property' => HtmlForm::FIELD_HIDDEN));
        $form->addInput('lst_id', '', $postListId, array('property' => HtmlForm::FIELD_HIDDEN));
    }

    // no roles or users found then show message
    if(count($list) === 0)
    {
        $gMessage->show($gL10n->get('MSG_NO_ROLES_AND_USERS'));
        // => EXIT
    }

    $form->addSelectBox(
        'msg_to', $gL10n->get('SYS_TO'), $list,
        array(
            'property'               => HtmlForm::FIELD_REQUIRED,
            'multiselect'            => true,
            'maximumSelectionNumber' => $maxNumberRecipients,
            'helpTextIdLabel'        => 'MAI_SEND_MAIL_TO_ROLE',
            'defaultValue'           => $preloadData
        )
    );

    $form->addLine();

    if ($currUsrId > 0)
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_USER_FIELDS.'
            INNER JOIN '. TBL_USER_DATA .'
                    ON usd_usf_id = usf_id
                 WHERE usf_type = \'EMAIL\'
                   AND usd_usr_id = ? -- $currUsrId
                   AND usd_value IS NOT NULL';

        $pdoStatement = $gDb->queryPrepared($sql, array($currUsrId));
        $possibleEmails = $pdoStatement->fetchColumn();

        $form->addInput(
            'name', $gL10n->get('MAI_YOUR_NAME'), $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'),
            array('maxLength' => 50, 'property' => HtmlForm::FIELD_DISABLED)
        );

        if($possibleEmails > 1)
        {
            $sqlData = array();
            $sqlData['query'] = 'SELECT email.usd_value AS ID, email.usd_value AS email
                                   FROM '.TBL_USERS.'
                             INNER JOIN '.TBL_USER_DATA.' AS email
                                     ON email.usd_usr_id = usr_id
                                    AND LENGTH(email.usd_value) > 0
                             INNER JOIN '.TBL_USER_FIELDS.' AS field
                                     ON field.usf_id = email.usd_usf_id
                                    AND field.usf_type = \'EMAIL\'
                                  WHERE usr_id = ? -- $currUsrId
                                    AND usr_valid = 1
                               GROUP BY email.usd_value, email.usd_value';
            $sqlData['params'] = array($currUsrId);

            $form->addSelectBoxFromSql(
                'mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $gDb, $sqlData,
                array('maxLength' => 50, 'defaultValue' => $gCurrentUser->getValue('EMAIL'), 'showContextDependentFirstEntry' => false)
            );
        }
        else
        {
            $form->addInput(
                'mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'),
                array('maxLength' => 50, 'property' => HtmlForm::FIELD_DISABLED)
            );
        }
    }
    else
    {
        $form->addInput(
            'namefrom', $gL10n->get('MAI_YOUR_NAME'), $formValues['namefrom'],
            array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addInput(
            'mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $formValues['mailfrom'],
            array('type' => 'email', 'maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
        );
    }

    // show option to send a copy to your email address only for registered users because of spam abuse
    if($gValidLogin)
    {
        $form->addCheckbox('carbon_copy', $gL10n->get('MAI_SEND_COPY'), $formValues['carbon_copy']);
    }

    // if preference is set then show a checkbox where the user can request a delivery confirmation for the email
    if (($currUsrId > 0 && (int) $gSettingsManager->get('mail_delivery_confirmation') === 2) || (int) $gSettingsManager->get('mail_delivery_confirmation') === 1)
    {
        $form->addCheckbox('delivery_confirmation', $gL10n->get('MAI_DELIVERY_CONFIRMATION'), $formValues['delivery_confirmation']);
    }

    $form->closeGroupBox();

    $form->openGroupBox('gb_mail_message', $gL10n->get('SYS_MESSAGE'));
    $form->addInput(
        'subject', $gL10n->get('MAI_SUBJECT'), $formValues['subject'],
        array('maxLength' => 77, 'property' => HtmlForm::FIELD_REQUIRED)
    );

    // Nur eingeloggte User duerfen Attachments anhaengen...
    if ($gValidLogin && ($gSettingsManager->getInt('max_email_attachment_size') > 0) && PhpIniUtils::isFileUploadEnabled())
    {
        $form->addFileUpload(
            'btn_add_attachment', $gL10n->get('MAI_ATTACHEMENT'),
            array(
                'enableMultiUploads' => true,
                'maxUploadSize'      => Email::getMaxAttachmentSize(),
                'multiUploadLabel'   => $gL10n->get('MAI_ADD_ATTACHEMENT'),
                'hideUploadField'    => true,
                'helpTextIdLabel'    => array('MAI_MAX_ATTACHMENT_SIZE', Email::getMaxAttachmentSize(Email::SIZE_UNIT_MEBIBYTE))
            )
        );
    }

    // add textfield or ckeditor to form
    if($gValidLogin && $gSettingsManager->getBool('mail_html_registered_users'))
    {
        $form->addEditor('msg_body', '', $formValues['msg_body'], array('property' => HtmlForm::FIELD_REQUIRED));
    }
    else
    {
        $form->addMultilineTextInput(
            'msg_body', $gL10n->get('SYS_TEXT'), $formValues['msg_body'], 10,
            array('property' => HtmlForm::FIELD_REQUIRED)
        );
    }

    $form->closeGroupBox();

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha'))
    {
        $form->openGroupBox('gb_confirmation_of_input', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
        $form->addCaptcha('captcha_code');
        $form->closeGroupBox();
    }

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => THEME_URL.'/icons/email.png'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
}

if (isset($messageStatement))
{
    require_once(__DIR__ . '/messages_functions.php');

    $page->addHtml('<br />');
    while ($row = $messageStatement->fetch())
    {
        if ((int) $row['msc_usr_id'] === $currUsrId)
        {
            $sentUser = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
        }
        else
        {
            $sentUser = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        }

        $receiverName = '';
        $messageText = htmlspecialchars_decode($row['msc_message']);
        if ($getMsgType === TableMessage::MESSAGE_TYPE_PM)
        {
            // list history of this PM
            $messageText = nl2br($row['msc_message']);
        }
        else
        {
            $message = new TableMessage($gDb, $getMsgId);
            $receivers = $message->getValue('msg_usr_id_receiver');
            // open some additional functions for messages

            $receiverName = prepareReceivers($receivers);
            $receiverName = '<div class="panel-footer">'.$gL10n->get('MSG_OPPOSITE').': '.$receiverName.'</div>';
        }

        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $row['msc_timestamp']);
        $page->addHtml('
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-sm-8">
                        <img class="admidio-panel-heading-icon" src="'. THEME_URL. '/icons/guestbook.png" alt="'.$sentUser.'" />' . $sentUser . '
                    </div>
                    <div class="col-sm-4 text-right">' . $date->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')) .
                    '</div>
                </div>
            </div>
            <div class="panel-body">'.
                $messageText.'
            </div>
            '.$receiverName.'
        </div>');
    }
}

// show page
$page->show();
