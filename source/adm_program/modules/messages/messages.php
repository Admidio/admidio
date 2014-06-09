<?php
/******************************************************************************
 * messages main page
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id    - send message to the given user ID
 * subject   - subject of the message
 * msg_id    - ID of the message -> just for answers
 *
 *****************************************************************************/

require_once('../../system/common.php');

$formerMembers = 0;

// Initialize and check the parameters
$getUserId      = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getSubject     = admFuncVariableIsValid($_GET, 'subject', 'html', '');
$getMsgId       = admFuncVariableIsValid($_GET, 'msg_id', 'numeric', 0);

// check if the call of the page was allowed
if ($gPreferences['enable_mail_module'] != 1)
{
    // message if the sending of PM is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//check for valid call
if ($getUserId == 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Update the read status of the message
if ($getMsgId > 0)
{
	$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_user1read = '0'
            WHERE msg_id2 = 0 and msg_id1 = ".$getMsgId." and msg_usrid1 = '".$gCurrentUser->getValue('usr_id')."'";
    $gDb->query($sql);
	
	$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_user2read = '0'
            WHERE msg_id2 = 0 and msg_id1 = ".$getMsgId." and msg_usrid2 = '".$gCurrentUser->getValue('usr_id')."'";
    $gDb->query($sql);
}

// if an User ID is given, we need to check if the actual user is alowed to contact this user
// and we check if there is an valid email on this user...

//usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB holen
$user = new User($gDb, $gProfileFields, $getUserId);

// darf auf die User-Id zugegriffen werden    
if((  $gCurrentUser->editUsers() == false
   && isMember($user->getValue('usr_id')) == false)
|| strlen($user->getValue('usr_id')) == 0 )
{
    $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
}

if ($getMsgId > 0)
{
    $sql = "SELECT msg_id1, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp 
              FROM ". TBL_MESSAGES. "
             WHERE msg_id2 > 0 AND msg_id1 = ". $getMsgId ."
			 and msg_type = 'PM'
             ORDER BY msg_id2 DESC";

    $result = $gDb->query($sql);
}

$form_values['name']         = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
$form_values['subject']      = $getSubject;
	
if (strlen($getSubject) > 0)
{
    $headline = $gL10n->get('MAI_SUBJECT').': '.$getSubject;
}
else
{
    $headline = $gL10n->get('PMS_SEND_PM');
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage();

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// show headline of module
$page->addHeadline($headline);

$formParam = 'msg_type=PM';

// id must be send to the next script
$formParam .= '&usr_id='.$getUserId;

if ($getMsgId > 0)
{
$formParam .= '&'.'msg_id='.$getMsgId;
}

// show form
$form = new HtmlForm('pm_send_form', $g_root_path.'/adm_program/modules/messages/messages_send.php?'.$formParam, $page, true);
$form->openGroupBox('gb_pm_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));

// Username to send the PM to
$form->addTextInput('msg_to', $gL10n->get('SYS_TO'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), 50, FIELD_DISABLED);

$form->closeGroupBox();

$form->openGroupBox('gb_pm_message', $gL10n->get('SYS_MESSAGE'));

if(strlen($getSubject) > 0)
{
$form->addTextInput('subject', $gL10n->get('MAI_SUBJECT'), $getSubject, 77, FIELD_DISABLED);
}
else
{
$form->addTextInput('subject', $gL10n->get('MAI_SUBJECT'), '', 77, FIELD_MANDATORY);
}

$form->addMultilineTextInput('msg_body', $gL10n->get('SYS_PM'), null, 10, 254);

$form->closeGroupBox();

$form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), THEME_PATH.'/icons/email.png');

// add form to html page
$page->addHtml($form->show(false));

// list history of this PM
if(isset($result))
{
    while ($row = $gDb->fetch_array($result)) {
	
		if ($row['msg_usrid1'] == $gCurrentUser->getValue('usr_id'))
		{
			$sentUser = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
		}
		else
		{
			$sentUser = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
		}

		
	    $page->addHtml('
        <div class="admBoxLayout" id="gbo_'.$row['msg_id1'].'">
            <div class="admBoxHead">
                <div class="admBoxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$sentUser.'" />'.$sentUser);

                $page->addHtml('</div>

                <div class="admBoxHeadRight">'.$row['msg_timestamp']. '&nbsp;');

                $page->addHtml('</div>
            </div>

            <div class="admBoxBody">'.
                nl2br($row['msg_message']));

            $page->addHtml('</div>
        </div>');
		
    }
}

// show page
$page->show();

?>