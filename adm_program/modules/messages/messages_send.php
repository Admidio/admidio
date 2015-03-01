<?php
/******************************************************************************
 * Check message information and save it
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * msg_id    - set message id for conversations
 * msg_type  - set message type
 * 
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/template.php');

// Initialize and check the parameters
$getMsgId        = admFuncVariableIsValid($_GET, 'msg_id', 'numeric');
$getMsgType      = admFuncVariableIsValid($_GET, 'msg_type', 'string');

// Check form values
$postFrom        = admFuncVariableIsValid($_POST, 'mailfrom', 'string');
$postName        = admFuncVariableIsValid($_POST, 'name', 'string');
$postSubject     = admFuncVariableIsValid($_POST, 'subject', 'html');
$postSubjectSQL  = admFuncVariableIsValid($_POST, 'subject', 'string');
$postTo          = $_POST['msg_to'];
$postBody        = admFuncVariableIsValid($_POST, 'msg_body', 'html');
$postBodySQL     = admFuncVariableIsValid($_POST, 'msg_body', 'string');
$postDeliveryConfirmation  = admFuncVariableIsValid($_POST, 'delivery_confirmation', 'boolean');
$postCaptcha     = admFuncVariableIsValid($_POST, 'captcha', 'string');

// if message not PM it must be Email and then directly check the parameters
if ($getMsgType != 'PM')
{
    $getMsgType      = 'EMAIL';

    // Stop if mail should be send and mail module is disabled
    if($gPreferences['enable_mail_module'] != 1)
    {
            $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    }

    // allow option to send a copy to your email address only for registered users because of spam abuse
    if($gValidLogin)
    {
        $postCarbonCopy = admFuncVariableIsValid($_POST, 'carbon_copy', 'boolean');
    }
    else
    {
        $postCarbonCopy = 0;
    }

    // if Attachmentsize is higher than max_post_size from php.ini, then $_POST is empty.
    if (empty($_POST))
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // Check Captcha if enabled and user logged out
    if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($postCaptcha) )
        {
            if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
            else if($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
        }
    }
    
}

// Stop if pm should be send pm module is disabled
if($gPreferences['enable_pm_module'] != 1 && $getMsgType == 'PM')
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// if user is logged in then show sender name and email
if ($gCurrentUser->getValue('usr_id') > 0)
{
    $postName = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
    $postFrom = $gCurrentUser->getValue('EMAIL');
}

// if no User is set, he is not able to ask for delivery confirmation 
if(!($gCurrentUser->getValue('usr_id')>0 && $gPreferences['mail_delivery_confirmation']==2) && $gPreferences['mail_delivery_confirmation']!=1)
{
    $postDeliveryConfirmation = 0;
}

// check if PM or Email and to steps:
if ($getMsgType == 'EMAIL')
{

	// put values into SESSION
	$_SESSION['message_request'] = array(
		'name'          => $postName,
		'msgfrom'       => $postFrom,
		'subject'       => $postSubject,
		'msg_body'      => $postBody,
		'carbon_copy'   => $postCarbonCopy,
		'delivery_confirmation' => $postDeliveryConfirmation,
	);

    if (isset($postTo))
    {
        $receiver = array();

        // Create new Email Object
        $email = new Email();

        foreach ($postTo as $value)
        {
            // check if role or user is given
            if (strpos($value,':') == true) 
            {
                $groupsplit = explode( ':', $value);

                if (strpos($groupsplit[1],'-') == true)
                {
                    $group = explode( '-', $groupsplit[1]);
                }
                else
                {
                    $group[0] = $groupsplit[1];
                    $group[1] = 0;
                }

                // check if role rights are granted to the User
                $sql = 'SELECT rol_mail_this_role, rol_name, rol_id 
                          FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                         WHERE rol_cat_id    = cat_id
                           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                               OR cat_org_id IS NULL)
                           AND rol_id = '.$group[0];
                $result = $gDb->query($sql);
                $row    = $gDb->fetch_array($result);

                // logged in user is just allowed to send to role with permission
                // logged out ones just to role with permission level "all visitors"
                // role must be from actual Organisation
                if((!$gValidLogin && $row['rol_mail_this_role'] != 3)
                || ($gValidLogin  && !$gCurrentUser->hasRightSendMailToRole($row['rol_id']))
                || $row['rol_id']  == null)
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }

                if($group[1] == 1)
                {
                    // only former members
                    $sqlConditions = ' AND mem_end < \''.DATE_NOW.'\' ';
                }
                elseif($group[1] == 2)
                {
                    // former members and active members
                    $sqlConditions = ' AND mem_begin < \''.DATE_NOW.'\' ';
                }
                else
                {
                    // only active members
                    $sqlConditions = ' AND mem_begin  <= \''.DATE_NOW.'\'
                                       AND mem_end     > \''.DATE_NOW.'\' ';
                }

                $sql   = 'SELECT first_name.usd_value as first_name, last_name.usd_value as last_name, 
                                 email.usd_value as email, rol_name
                            FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
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
                           WHERE rol_id      = '.$group[0].'
                             AND rol_cat_id  = cat_id
                             AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                                 OR cat_org_id IS NULL )
                             AND mem_rol_id  = rol_id
                             AND mem_usr_id  = usr_id
                             AND usr_valid   = 1 '.
                                 $sqlConditions;

                // Wenn der User eingeloggt ist, wird die UserID im Statement ausgeschlossen, 
                // damit er die Mail nicht an sich selber schickt.
				// *******************************************************
                if ($gValidLogin)
                {
                    $sql =$sql. ' AND usr_id <> '. $gCurrentUser->getValue('usr_id');
                }
                $result = $gDb->query($sql);

                if($gDb->num_rows($result) > 0)
                {
                    // normaly we need no To-address and set "undisclosed recipients", but if 
                    // that won't work than the From-address will be set 
                    if($gPreferences['mail_sender_into_to'] == 1)
                    {
                        // always fill recipient if preference is set to prevent problems with provider
                        $email->addRecipient($postFrom,$postName);
                    }

                    // all role members will be attached as BCC
                    while ($row = $gDb->fetch_object($result))
                    {
                        $receiver[] = array($row->email , $row->first_name.' '.$row->last_name);
                    }

                }
                else
                {
                    // error if role has no email addresses or role ID is not existing
                    $gMessage->show($gL10n->get('MAI_ROLE_NO_EMAILS'));
                }

            }
            else
            {
                $user = new User($gDb, $gProfileFields, $value);
                
                // error if no valid Email for given user ID
                if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
                {
                    $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
                }
                
                $receiver[] = array($user->getValue('EMAIL'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'));
            }
        }
    }
    else
    {
        // message when no receiver is given
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // save page in navigation - to have a check for a navigation back.
    $gNavigation->addUrl(CURRENT_URL);

    // check if name is given
    if(strlen($postName) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }

    // check sending attributes for user, to be sure that they are correct
    if ( $gValidLogin 
    && (  $postFrom != $gCurrentUser->getValue('EMAIL') 
       || $postName != $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME')) )
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // set sending address
    if ($email->setSender($postFrom,$postName))
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
                }
                $attachmentSize = 0;
                // add now every attachment
                for($currentAttachmentNo = 0; isset($_FILES['userfile']['name'][$currentAttachmentNo]) == true; $currentAttachmentNo++)
                {
                    // check if Upload was OK
                    if (($_FILES['userfile']['error'][$currentAttachmentNo] != 0) &&  ($_FILES['userfile']['error'][$currentAttachmentNo] != 4))
                    {
                        $gMessage->show($gL10n->get('MAI_ATTACHMENT_TO_LARGE'));
                    }
                    
                    if ($_FILES['userfile']['error'][$currentAttachmentNo] == 0)
                    {
                        // check the size of the attachment
                        $attachmentSize = $attachmentSize + $_FILES['userfile']['size'][$currentAttachmentNo];
                        if($attachmentSize > $email->getMaxAttachementSize("b"))
                        {
                            $gMessage->show($gL10n->get('MAI_ATTACHMENT_TO_LARGE'));
                        }

                        // set filetyp to standart if not given
                        if (strlen($_FILES['userfile']['type'][$currentAttachmentNo]) <= 0)
                        {
                            $_FILES['userfile']['type'][$currentAttachmentNo] = 'application/octet-stream';                        
                        }

                        // add the attachment to the mail
                        try
                        {
                            $email->AddAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo], $encoding = 'base64', $_FILES['userfile']['type'][$currentAttachmentNo]);
                        }
                        catch (phpmailerException $e)
                        {
                            $gMessage->show($e->errorMessage());
                        }             
                    }
                }
            }
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('MAI_SUBJECT')));
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')));
    }

    // if possible send html mail
    if($gValidLogin == true && $gPreferences['mail_html_registered_users'] == 1)
    {
        $email->sendDataAsHtml();
    }

    // set flag if copy should be send to sender
    if (isset($postCarbonCopy) && $postCarbonCopy == true)
    {
        $email->setCopyToSenderFlag();

        // if mail was send to user than show recipients in copy of mail if current user has a valid login
        if($gValidLogin)
        {
            $email->setListRecipientsFlag();
        }
    }

	$sendresult = array_map("unserialize", array_unique(array_map("serialize", $receiver)));
	$receivers = count($sendresult);
	foreach ($sendresult as $address)
    {
        if ( $gPreferences['mail_into_to'] == 1 || $receivers == 1)
        {
		    $email->addRecipient($address[0], $address[1]);
        }
		else
		{
		    $email->addBlindCopy($address[0], $address[1]);
		}
    }

    // add confirmation mail to the sender
    if($postDeliveryConfirmation == 1)
    {
        $email->ConfirmReadingTo = $gCurrentUser->getValue('EMAIL');
    }

    // load the template and set the new email body with template
    $emailTemplate = admReadTemplateFile("template.html");
    $emailTemplate = str_replace("#message#",$postBody,$emailTemplate);

    // set Text
    $email->setText($emailTemplate);

    // finally send the mail
    $sendResult = $email->sendEmail();

}
// ***** PM *****
else
{
    // if $postTo is not an Array, it is send from the hidden field.
	if(!is_array($postTo))
	{
	    $postTo = array($postTo);
	}
	
	// get user data from Database
    $user = new User($gDb, $gProfileFields, $postTo[0]);

    // check if it is allowed to send to this user    
    if(($gCurrentUser->editUsers() == false && isMember($user->getValue('usr_id')) == false)|| strlen($user->getValue('usr_id')) == 0 )
    {
            $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
    }

    // check if receiver of message has valid login
    if(strlen($user->getValue('usr_login_name')) == 0)
    {
        $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }

    // save page in navigation - to have a check for a navigation back.
    $gNavigation->addUrl(CURRENT_URL);

    if ($getMsgId == 0)
    {
        $PMId2 = 1;

        $sql = "SELECT MAX(msg_id1) as max_id
              FROM ". TBL_MESSAGES;

        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        $getMsgId = $row['max_id'] + 1;

        $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_read) 
            VALUES ('".$getMsgType."', '".$getMsgId."', 0, '".$postSubjectSQL."', '".$gCurrentUser->getValue('usr_id')."', '".$postTo[0]."', '', CURRENT_TIMESTAMP, '1')";

        $gDb->query($sql);
    }
    else
    {
        $sql = "SELECT MAX(msg_id2) as max_id
              FROM ".TBL_MESSAGES." 
			  where msg_id1 = ".$getMsgId;

        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        $PMId2 = $row['max_id'] + 1;

        $sql = "UPDATE ". TBL_MESSAGES. " SET  msg_read = '1', msg_timestamp = CURRENT_TIMESTAMP, msg_usrid1 = '".$gCurrentUser->getValue('usr_id')."', msg_usrid2 = '".$postTo[0]."'
                WHERE msg_id2 = 0 and msg_id1 = ".$getMsgId;

        $gDb->query($sql);
    }

    $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_read) 
            VALUES ('".$getMsgType."', '".$getMsgId."', '".$PMId2."', '', '".$gCurrentUser->getValue('usr_id')."', '".$postTo[0]."', '".$postBodySQL."', CURRENT_TIMESTAMP, '0')";

    if ($gDb->query($sql)) {
      $sendResult = TRUE;
    }
}

// message if send/save is OK
if ($sendResult === TRUE)
{
    // save mail also to database
    if ($getMsgType != 'PM')
    {
         $sql = "SELECT MAX(msg_id1) as max_id
          FROM ". TBL_MESSAGES;

        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        $getMsgId = $row['max_id'] + 1;

        $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_read) 
            VALUES ('".$getMsgType."', '".$getMsgId."', 0, '".$postSubjectSQL."', '".$gCurrentUser->getValue('usr_id')."', '', '".$postBodySQL."', CURRENT_TIMESTAMP, '0')";

        $gDb->query($sql);    
    }

    // Delete CaptchaCode if send/save was correct
    if (isset($_SESSION['captchacode']))
    {
        unset($_SESSION['captchacode']);
    }

    // after sending remove the actual Page from the NaviObject and remove also the send-page
    $gNavigation->deleteLastUrl();
    $gNavigation->deleteLastUrl();
    
    // message if sending was OK
    if($gNavigation->count() > 0)
    {
		$gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    }
    else
    {
        $gMessage->setForwardUrl($gHomepage, 2000);
    }

    if ($getMsgType != 'PM')
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_SEND', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }
}
else
{
    if ($getMsgType != 'PM')
    {
        $gMessage->show($sendResult.'<br />'.$gL10n->get('SYS_EMAIL_NOT_SEND', $sendResult));
    }
    else
    {
        $gMessage->show($sendResult.'<br />'.$gL10n->get('SYS_EMAIL_NOT_SEND', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $sendResult));
    }
}

?>