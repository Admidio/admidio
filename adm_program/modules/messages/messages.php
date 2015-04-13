<?php
/******************************************************************************
 * PM list page
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 *
 *****************************************************************************/

require_once('../../system/common.php');

// check if the call of the page was allowed
if ($gPreferences['enable_pm_module'] != 1 && $gPreferences['enable_mail_module'] != 1 && $gPreferences['enable_chat_module'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Initialize and check the parameters
$getMsgId = admFuncVariableIsValid($_GET, 'msg_id', 'numeric', array('defaultValue' => 0));

if ($getMsgId != 0)
{
	$delMessage = new TableMessage($gDb, $getMsgId);

	//Function to delete message
	$delete = $delMessage->delete($gCurrentUser->getValue('usr_id'));
	
	$gNavigation->deleteLastUrl();
	$gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
	if($delete == 1)
	{
	    $gMessage->show('Message was deleted!');
	}
	else
	{
		$gMessage->show('Message will just be finally deleted if the second user also delete it!');
	}
	
}

//SQL to find all unread PM messages
$sql = "SELECT msg_id,
			CASE WHEN msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usr_id_receiver
			ELSE msg_usr_id_sender
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_part_id = 0 and ((msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." and msg_read = 1)
		 or msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." and  msg_read = 0)
		 ORDER BY msg_id DESC";

$result = $gDb->query($sql);

//SQL to find all read PM messages
$sql = "SELECT msg_id,
			CASE WHEN msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usr_id_receiver
			ELSE msg_usr_id_sender
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_part_id = 0 and ((msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." and msg_read <> 1)
		 or msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." and  msg_read = 1)
		 ORDER BY msg_id DESC";

$result1 = $gDb->query($sql);

//SQL to find all own Email messages
$sql = "SELECT msg_id, msg_usr_id_sender AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'EMAIL' and (msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ."
		 or msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." )
		 ORDER BY msg_id DESC";

$resultMail = $gDb->query($sql);

$headline = $gL10n->get('SYS_MESSAGES');;

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// get module menu for emails
$EmailMenu = $page->getMenu();
// link to write new email
if ($gPreferences['enable_mail_module'] == 1 )
{
    $EmailMenu->addItem('admMenuItemNewEmail', $g_root_path.'/adm_program/modules/messages/messages_write.php', $gL10n->get('MAI_SEND_EMAIL'), '/email.png');
}
// link to write new PM
if ($gPreferences['enable_pm_module'] == 1 )
{
    $EmailMenu->addItem('admMenuItemNewPm', $g_root_path.'/adm_program/modules/messages/messages_write.php?msg_type=PM', $gL10n->get('PMS_SEND_PM'), '/email.png');
}

// link to Chat
if ($gPreferences['enable_chat_module'] == 1 )
{
    $EmailMenu->addItem('admMenuItemNewChat', $g_root_path.'/adm_program/modules/messages/messages_chat.php', $gL10n->get('MSG_CHAT'), '/chat.png');
}

$EmailMenu->addItem('admMenuItemPreferences', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=messages', 
					$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');

$table = new HtmlTable('adm_lists_table', $page, true, true);

$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$table->addAttribute('border', '0');
$table->addTableHeader();

$table->addRowHeadingByArray(array( $gL10n->get('SYS_CATEGORY'),$gL10n->get('MAI_SUBJECT'), $gL10n->get('MSG_OPPOSITE'), $gL10n->get('SYS_DATE'), $gL10n->get('SYS_FEATURES')));

if(isset($resultMail))
{
    while ($row = $gDb->fetch_array($resultMail)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$message = new TableMessage($gDb, $row['msg_id']);

		$messageAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages.php?msg_id='.$row['msg_id'].'"><img
			                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';

		$table->addRowByArray(array( '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" />' , $message->getValue('msg_subject'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $message->getValue('msg_timestamp'), $messageAdministration), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages_write.php?msg_id='. $message->getValue('msg_id').'&usr_id='.$row['user']. '\''));
   }
}

if(isset($result))
{
    while ($row = $gDb->fetch_array($result)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$message = new TableMessage($gDb, $row['msg_id']);

		$messageAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages.php?msg_id='.$row['msg_id'].'"><img
			                     src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';

		$table->addRowByArray(array('<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' , $message->getValue('msg_subject'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $message->getValue('msg_timestamp'), $messageAdministration), null, array('style' => 'cursor: pointer; font-weight: bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages_write.php?msg_id='. $message->getValue('msg_id').'&usr_id='.$row['user']. '\''));
   }
}

if(isset($result1))
{
	while ($row = $gDb->fetch_array($result1)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$message = new TableMessage($gDb, $row['msg_id']);
		
		$messageAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages.php?msg_id='.$row['msg_id'].'"><img
			                     src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';

		$table->addRowByArray(array('<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' , $message->getValue('msg_subject'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $message->getValue('msg_timestamp'), $messageAdministration), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages_write.php?msg_id='. $message->getValue('msg_id').'&usr_id='.$row['user']. '\''));
    }
}

//special settings for the table
$table->setDatatablesOrderColumns(array(array(4, 'desc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();

?>