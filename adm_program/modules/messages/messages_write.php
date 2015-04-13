<?php
/******************************************************************************
 * messages form page
 *
 * Copyright    : (c) 2004 - 2014 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id    - send message to the given user ID
 * subject   - subject of the message
 * msg_id    - ID of the message -> just for answers
 * rol_id    - Statt einem Rollennamen/Kategorienamen kann auch eine RollenId uebergeben werden
 * carbon_copy - 1 (Default) Checkbox "Kopie an mich senden" ist gesetzt
 *             - 0 Checkbox "Kopie an mich senden" ist NICHT gesetzt
 * show_members : 0 - (Default) show active members of role
 *                1 - show former members of role
 *                2 - show active and former members of role
 *
 *****************************************************************************/

require_once('../../system/common.php');

$formerMembers = 0;

// Initialize and check the parameters
$getMsgType     = admFuncVariableIsValid($_GET, 'msg_type', 'string');
$getUserId      = admFuncVariableIsValid($_GET, 'usr_id', 'numeric');
$getSubject     = admFuncVariableIsValid($_GET, 'subject', 'html');
$getMsgId       = admFuncVariableIsValid($_GET, 'msg_id', 'numeric');
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id', 'numeric');
$getCarbonCopy  = admFuncVariableIsValid($_GET, 'carbon_copy', 'boolean', array('defaultValue' => 0));
$getDeliveryConfirmation  = admFuncVariableIsValid($_GET, 'delivery_confirmation', 'boolean');
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'numeric');


// check if the call of the page was allowed by settings
if ($gPreferences['enable_mail_module'] != 1 && $getMsgType != 'PM')
{
    // message if the sending of PM is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// check if the call of the page was allowed by settings
if ($gPreferences['enable_pm_module'] != 1 && $getMsgType == 'PM')
{
    // message if the sending of PM is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// check for valid login
if (!$gValidLogin && $getUserId == 0 && $getMsgType == 'PM')
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// check if user has email address for sending a email
if ($gValidLogin && $getMsgType != 'PM' && strlen($gCurrentUser->getValue('EMAIL')) == 0)
{
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}

// Update the read status of the message
if ($getMsgId > 0)
{
    $message = new TableMessage($gDb, $getMsgId);

    // update the read-status
    $message->setReadValue($gCurrentUser->getValue('usr_id'));
    
    $msg_converation_id = $message->getValue('msg_converation_id');
    $getMsgType = $message->getValue('msg_type');
    $getSubject = $message->getValue('msg_subject');
    
    $sql = "SELECT msc_usr_id, msc_message, msc_timestamp 
                  FROM ". TBL_MESSAGES_CONTENT. "
                 WHERE msc_msg_id = ". $getMsgId ."
                 ORDER BY msc_part_id DESC";

    $message_result = $gDb->query($sql);

}

$recept_number = 1;
if ($gPreferences['mail_max_receiver'] > 0)
{
    $recept_number = $gPreferences['mail_max_receiver'];
}

$list = array();

if ($getMsgType == 'PM')
{

    $recept_number = 1;

    $sql = "SELECT usr_id, CONCAT(row1id1.usd_value, ' ', row2id2.usd_value) as name, usr_login_name
                  FROM ".TBL_ROLES.", ".TBL_CATEGORIES.", ".TBL_MEMBERS.", ".TBL_USERS."
                        LEFT JOIN ".TBL_USER_DATA." row1id1
                                           ON row1id1.usd_usr_id = usr_id
                                          AND row1id1.usd_usf_id = 1 LEFT JOIN ".TBL_USER_DATA." row2id2
                                           ON row2id2.usd_usr_id = usr_id
                                          AND row2id2.usd_usf_id = 2
                 WHERE rol_id IN (2)
                   AND rol_cat_id = cat_id
                   AND ( cat_org_id = 1
                       OR cat_org_id IS NULL )
                   AND mem_rol_id = rol_id
                   AND mem_usr_id = usr_id
                   AND usr_id <> ".$gCurrentUser->getValue('usr_id')."
                   AND usr_valid  = 1
                        AND usr_login_name IS NOT NULL";

    $drop_result = $gDb->query($sql);
    
    if ($gValidLogin)
    {
        while ($row = $gDb->fetch_array($drop_result))
        {
            $list[] = array($row['usr_id'], $row['name'].' (' .$row['usr_login_name'].')', '');
        }
    }
}

if ($getUserId > 0)
{
    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($gDb, $gProfileFields, $getUserId);

    // if an User ID is given, we need to check if the actual user is allowed to contact this user  
    if (($gCurrentUser->editUsers() == false
       && isMember($user->getValue('usr_id')) == false)
    || strlen($user->getValue('usr_id')) == 0 )
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
    }
}

if (strlen($getSubject) > 0)
{
    $headline = $gL10n->get('MAI_SUBJECT').': '.$getSubject;
}
else
{
    $headline = $gL10n->get('MAI_SEND_EMAIL');
    if ($getMsgType == 'PM')
    {
        $headline = $gL10n->get('PMS_SEND_PM');
    }
}

// create html page object
$page = new HtmlPage();

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// show headline of module
$page->addHeadline($headline);

// create module menu with back link
$messagesWriteMenu = new HtmlNavbar('menu_messages_write', $headline, $page);
$messagesWriteMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($messagesWriteMenu->show(false));

if ($getMsgType == 'PM')
{

    $formParam = 'msg_type=PM';

    if ($getUserId > 0)
    {
        $form_values['subject'] = $getSubject;
    }

    if ($getMsgId > 0)
    {
        $formParam .= '&'.'msg_id='.$getMsgId;
    }

    // show form
    $form = new HtmlForm('pm_send_form', $g_root_path.'/adm_program/modules/messages/messages_send.php?'.$formParam, $page, array('enableFileUpload' => true));

    if ($getUserId == 0)
    {
        $form->openGroupBox('gb_pm_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));
        $form->addSelectBox('msg_to', $gL10n->get('SYS_TO'), $list, array('property' => FIELD_MANDATORY, 
                            'showContextDependentFirstEntry' => false, 'multiselect' => true, 'helpTextIdLabel' => 'MAI_SEND_MAIL_TO_ROLE'));
        $form->closeGroupBox();
        $sendto = '';
    }
    else
    {
        $form->addInput('msg_to', null, $getUserId, array('type' => 'hidden'));
        $sendto = ' ' . $gL10n->get('SYS_TO') . ' ' .$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').' ('.$user->getValue('usr_login_name').')';
    }

    $form->openGroupBox('gb_pm_message', $gL10n->get('SYS_MESSAGE') . $sendto);

    if(strlen($getSubject) == 0)
    {
        $form->addInput('subject', $gL10n->get('MAI_SUBJECT'), '', array('maxLength' => 77, 'property' => FIELD_MANDATORY));
    }

    $form->addMultilineTextInput('msg_body', $gL10n->get('SYS_PM'), null, 10, array('maxLength' => 254, 'property' => FIELD_MANDATORY));

    $form->closeGroupBox();

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => THEME_PATH.'/icons/email.png'));

    // add form to html page
    $page->addHtml($form->show(false));
    
    // list history of this PM
    if(isset($message_result))
    {
        $page->addHtml('<br>');
        while ($row = $gDb->fetch_array($message_result)) {
        
            if ($row['msc_usr_id'] == $gCurrentUser->getValue('usr_id'))
            {
                $sentUser = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
            }
            else
            {
                $sentUser = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
            }

        $page->addHtml('
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-sm-8">
                        <img class="admidio-panel-heading-icon" src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$sentUser.'" />' . $sentUser . '
                    </div>
                    <div class="col-sm-4 text-right">' . $row['msc_timestamp'] . 
                    '</div>
                </div>
            </div>
            <div class="panel-body">'.
                nl2br($row['msc_message']).'
            </div>
        </div>');

        }
    }

}
else if (isset($message_result))
{
    
    // show email to user
    if (isset($message_result))
    {
        while ($row = $gDb->fetch_array($message_result)) 
        {
            if ($row['msc_usr_id'] == $gCurrentUser->getValue('usr_id'))
            {
                $sentUser = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
            }
            else
            {
                $sentUser = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
            }

            $page->addHtml('
            <div class="panel-heading">
                <div class="row">
                    <div class="col-sm-8">
                        <img class="admidio-panel-heading-icon" src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$sentUser.'" />'.$sentUser.'
                    </div>
                    <div class="col-sm-4 text-right">'.$row['msc_timestamp']);

                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel-footer">'.
                htmlspecialchars_decode($row['msc_message']).'
            </div>');
            
        }
    }
    
}
else
{
    if ($getUserId > 0)
    {
        // besitzt der User eine gueltige E-Mail-Adresse
        if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
        {
            $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
        }

        $userEmail = $user->getValue('EMAIL');
    }
    elseif ($getRoleId > 0)
    {
        // wird eine bestimmte Rolle aufgerufen, dann pruefen, ob die Rechte dazu vorhanden sind

        $sqlConditions = ' AND rol_id = '.$getRoleId;

        $sql = 'SELECT rol_mail_this_role, rol_name, rol_id, 
                       (SELECT COUNT(1)
                          FROM '.TBL_MEMBERS.'
                         WHERE mem_rol_id = rol_id
                           AND (  mem_begin > \''.DATE_NOW.'\'
                               OR mem_end   < \''.DATE_NOW.'\')) as former
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                 WHERE rol_cat_id    = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL)'.
                       $sqlConditions;
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);

        // Ausgeloggte duerfen nur an Rollen mit dem Flag "alle Besucher der Seite" Mails schreiben
        // Eingeloggte duerfen nur an Rollen Mails schreiben, zu denen sie berechtigt sind
        // Rollen muessen zur aktuellen Organisation gehoeren
        if(($gValidLogin == false && $row['rol_mail_this_role'] != 3)
        || ($gValidLogin == true  && $gCurrentUser->hasRightSendMailToRole($row['rol_id']) == false)
        || $row['rol_id']  == null)
        {
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        }

        $rollenName = $row['rol_name'];
        $rollenID   = $getRoleId;
        $formerMembers = $row['former'];
    }

    // Wenn die letzte URL in der Zuruecknavigation die des Scriptes message_send.php ist,
    // dann soll das Formular gefuellt werden mit den Werten aus der Session
    if (strpos($gNavigation->getUrl(),'message_send.php') > 0 && isset($_SESSION['message_request']))
    {
        // Das Formular wurde also schon einmal ausgefüllt,
        // da der User hier wieder gelandet ist nach der Mailversand-Seite
        $form_values = strStripSlashesDeep($_SESSION['message_request']);
        unset($_SESSION['message_request']);
        $gNavigation->deleteLastUrl();
    }
    else
    {
        $form_values['name']         = '';
        $form_values['mailfrom']     = '';
        $form_values['subject']      = $getSubject;
        $form_values['msg_body']     = '';
        $form_values['msg_to']       = 0;
        $form_values['carbon_copy']  = $getCarbonCopy;
        $form_values['delivery_confirmation']  = $getDeliveryConfirmation;
    }

    $formParam = '';

    // if subject was set as param then send this subject to next script
    if (strlen($getSubject) > 0)
    {
        $formParam .= 'subject='.$getSubject.'&';
    }
    
    // show form
    $form = new HtmlForm('mail_send_form', $g_root_path.'/adm_program/modules/messages/messages_send.php?'.$formParam, $page, array('enableFileUpload' => true));
    $form->openGroupBox('gb_mail_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));
    
    if ($getUserId > 0)
    {
        // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
        $preload_data = '{ id: "' .$getUserId. '", text: "' .$userEmail. '", locked: true}';
    }
    elseif ($getRoleId > 0)
    {
        // Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
        $preload_data = '{ id: "groupID: ' .$rollenID. '", text: "' .$rollenName. '", locked: true}';
    }
    
    // keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
    if ($gValidLogin)
    {
        // alle Rollen auflisten,
        // an die im eingeloggten Zustand Mails versendet werden duerfen
        $sql = 'SELECT rol_id, rol_name, cat_name,
                    (SELECT COUNT(1)
                         FROM '.TBL_MEMBERS.'
                        WHERE mem_rol_id = rol_id
                         AND (  mem_begin > \''.DATE_NOW.'\'
                        OR mem_end   < \''.DATE_NOW.'\')) as former
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                 WHERE rol_valid   = 1
                   AND rol_cat_id  = cat_id
                   AND cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                 ORDER BY cat_sequence, rol_name ';
        
        // add a selectbox where you can choose to which groups (active, former) you want to send the mail
        for ($act_or = 0; $act_or <= 2; $act_or++)
        {
            $act_group = '';
            $act_group_short = '';
            if ($act_or == 1)
            {
                $act_group = $gL10n->get('SYS_ROLES'). ' (' .$gL10n->get('LST_FORMER_MEMBERS') . ')';
                $act_group_short = '('.$gL10n->get('SYS_FORMER_PL').')';
                $act_number = '-1';
            }
            else if ($act_or == 2)
            {
                $act_group = $gL10n->get('SYS_ROLES'). ' (' . $gL10n->get('LST_ACTIVE_FORMER_MEMBERS') . ')';
                $act_group_short = '('.$gL10n->get('MSG_ACTIVE_FORMER_SHORT').')';
                $act_number = '-2';
            }
            else
            {
                $act_group = $gL10n->get('SYS_ROLES'). ' (' .$gL10n->get('LST_ACTIVE_MEMBERS') . ')';
                $act_number = '';
            }
            
            $result = $gDb->query($sql);
            while ($row = $gDb->fetch_array($result)) 
            {
                if($act_number == '' || $row['former'] > 0)
                {
                    $list[] = array('groupID: '.$row['rol_id'].$act_number, $row['rol_name'].' '.$act_group_short, $act_group);
                }
            }

        }
        
        // select Users
        
        $sql   = 'SELECT usr_id, first_name.usd_value as first_name, last_name.usd_value as last_name, 
                                 email.usd_value as email, (SELECT count(1)
                         FROM '.TBL_MEMBERS.' as temp
                        WHERE mem_usr_id = usr_id
                         AND (  mem_begin > \''.DATE_NOW.'\'
                        OR mem_end   < \''.DATE_NOW.'\')) as former
                    FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
                    JOIN '. TBL_USER_DATA. ' as email
                      ON email.usd_usr_id = usr_id
                     AND LENGTH(email.usd_value) > 0
                    JOIN '.TBL_USER_FIELDS.' as field
                      ON field.usf_id = email.usd_usf_id
                     AND field.usf_type = \'EMAIL\'
                    LEFT JOIN '. TBL_USER_DATA. ' as last_name
                      ON last_name.usd_usr_id = usr_id
                     AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                    LEFT JOIN '. TBL_USER_DATA. ' as first_name
                      ON first_name.usd_usr_id = usr_id
                     AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                   WHERE mem_usr_id  = usr_id
                     AND usr_id <> '.$gCurrentUser->getValue('usr_id').'
                     AND usr_valid   = 1
                   GROUP BY usr_id, first_name.usd_value, last_name.usd_value, email.usd_value
                   ORDER BY former, first_name, last_name';        

        $result = $gDb->query($sql);

        $next = true;
        $test = "Aktive Mitglieder";
        
        while ($row = $gDb->fetch_array($result)) {
            if ($row['former'] == 1 && $next == true)
            {
                $test = "Inactive Mitglieder";
                $next = false;
            }

            $list[] = array($row['usr_id'], $row['first_name'].' '.$row['last_name']. ' ('.$row['email'].')', $test);
        }

    }
    else
    {
        $recept_number = 1;
        // list all roles where guests could send mails to
        $sql = 'SELECT rol_id, rol_name, cat_name 
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                 WHERE rol_mail_this_role = 3
                   AND rol_valid  = 1
                   AND rol_cat_id = cat_id
                   AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                 ORDER BY cat_sequence, rol_name ';

        $result = $gDb->query($sql);
        while($row = $gDb->fetch_array($result))
        {
            $list[] = array('groupID: '.$row['rol_id'], $row['rol_name'], '');
        }
        
    }
    
    $form->addSelectBox('msg_to', $gL10n->get('SYS_TO'), $list, array('property' => FIELD_MANDATORY, 
                        'showContextDependentFirstEntry' => false, 'multiselect' => true, 'helpTextIdLabel' => 'MAI_SEND_MAIL_TO_ROLE'));

    $form->addLine();

    if ($gCurrentUser->getValue('usr_id') > 0)
    {
        $form->addInput('name', $gL10n->get('MAI_YOUR_NAME'), $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'), array('maxLength' => 50, 'property' => FIELD_DISABLED));
        $form->addInput('mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'), array('maxLength' => 50, 'property' => FIELD_DISABLED));
    }
    else
    {
        $form->addInput('name', $gL10n->get('MAI_YOUR_NAME'), $form_values['name'], array('maxLength' => 50, 'property' => FIELD_MANDATORY));
        $form->addInput('mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $form_values['mailfrom'], array('maxLength' => 50, 'property' => FIELD_MANDATORY));
    }

    // show option to send a copy to your email address only for registered users because of spam abuse
    if($gValidLogin)
    {
        $form->addCheckbox('carbon_copy', $gL10n->get('MAI_SEND_COPY'), $form_values['carbon_copy']);
    }

    // if preference is set then show a checkbox where the user can request a delivery confirmation for the email
    if (($gCurrentUser->getValue('usr_id') > 0 && $gPreferences['mail_delivery_confirmation']==2) || $gPreferences['mail_delivery_confirmation']==1)
    {
        $form->addCheckbox('delivery_confirmation', $gL10n->get('MAI_DELIVERY_CONFIRMATION'), $form_values['delivery_confirmation']);
    }

    $form->closeGroupBox();

    $form->openGroupBox('gb_mail_message', $gL10n->get('SYS_MESSAGE'));
    $form->addInput('subject', $gL10n->get('MAI_SUBJECT'), $form_values['subject'], array('maxLength' => 77, 'property' => FIELD_MANDATORY));

    // Nur eingeloggte User duerfen Attachments anhaengen...
    if (($gValidLogin) && ($gPreferences['max_email_attachment_size'] > 0) && (ini_get('file_uploads') == '1'))
    {
        $form->addFileUpload('btn_add_attachment', $gL10n->get('MAI_ATTACHEMENT'), array('enableMultiUploads' => true, 'multiUploadLabel' => $gL10n->get('MAI_ADD_ATTACHEMENT'), 
            'hideUploadField' => true, 'helpTextIdLabel' => array('MAI_MAX_ATTACHMENT_SIZE', Email::getMaxAttachementSize('mb'))));
    }

    // add textfield or ckeditor to form
    if($gValidLogin == true && $gPreferences['mail_html_registered_users'] == 1)
    {
        $form->addEditor('msg_body', null, $form_values['msg_body']);
    }
    else
    {
        $form->addMultilineTextInput('msg_body', $gL10n->get('SYS_TEXT'), null, 10);
    }

    $form->closeGroupBox();

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
    {
        $form->openGroupBox('gb_confirmation_of_input', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
        $form->addCaptcha('captcha', $gPreferences['captcha_type']);
        $form->closeGroupBox();
    }

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => THEME_PATH.'/icons/email.png'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
}

$preload = '';

if (isset($preload_data))
{
  $preload = '$("#msg_to").select2("data", ['.$preload_data.'] )';
}

// add JS code for the drop down to find email addresses and groups
if(isset($list))
    {
    
        $page->addHtml(
        '<script>

            $(document).ready(function () {
                $("#msg_to").select2({
                    placeholder: "'.$gL10n->get('SYS_SELECT_FROM_LIST').'",
                    allowClear: true,
                    maximumSelectionSize: '.$recept_number.',
                    separator: ";",
                    closeOnSelect: false
                });'
                .$preload.
            '});

        </script>');
    }

// show page
$page->show();

?>