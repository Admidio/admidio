<?php
/******************************************************************************
 * PM list page
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
    // message if the sending of messages is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Initialize and check the parameters
$getMsgIdDel = admFuncVariableIsValid($_GET, 'msg_id_del', 'numeric', array('defaultValue' => 0));

if ($getMsgIdDel != 0)
{
	$getMsgTyp = admFuncVariableIsValid($_GET, 'msg_typ', 'string');
	
	if ($getMsgTyp == 'PM')
	{
		//SQL to find all read PM messages
		$sql = "SELECT CASE WHEN msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usr_id_receiver ELSE msg_usr_id_sender END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_part_id = 0 and msg_con_id = ". $getMsgIdDel ."
		 ORDER BY msg_con_id DESC";
		$result = $gDb->query($sql);
		$row = $gDb->fetch_array($result);

		//SQL to delete PM messages
		$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_read = '2', msg_timestamp = CURRENT_TIMESTAMP, msg_usr_id_sender = '".$gCurrentUser->getValue('usr_id')."', msg_usr_id_receiver = '".$row['user']."'
         WHERE msg_part_id = 0 and msg_con_id = ".$getMsgIdDel;
	}
	else
	{
		//SQL to delete EMAIL messages
	    $sql = "DELETE FROM ". TBL_MESSAGES. "
         WHERE msg_con_id = ". $getMsgIdDel ." and (msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ."
		 or msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." )";
	}

	$gDb->query($sql);

}

//SQL to find all unread PM messages
$sql = "SELECT msg_type, msg_con_id, msg_subject, msg_timestamp,
			CASE WHEN msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usr_id_receiver
			ELSE msg_usr_id_sender
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_part_id = 0 and (msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." and msg_read = 1)
		 ORDER BY msg_con_id DESC";

$result = $gDb->query($sql);

//SQL to find all read PM messages
$sql = "SELECT msg_type, msg_con_id, msg_subject, msg_timestamp,
			CASE WHEN msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usr_id_receiver
			ELSE msg_usr_id_sender
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_part_id = 0 and ((msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." and msg_read = 0)
		 or msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ." and  msg_read < 2)
		 ORDER BY msg_con_id DESC";

$result1 = $gDb->query($sql);

//SQL to find all ask for deleted PM messages
$sql = "SELECT msg_type, msg_con_id, msg_subject, msg_timestamp, msg_usr_id_sender AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_part_id = 0 and msg_usr_id_sender <> ". $gCurrentUser->getValue('usr_id') ." and msg_read = 2
		 ORDER BY msg_con_id DESC";

$result2 = $gDb->query($sql);

//SQL to find all own Email messages
$sql = "SELECT msg_type, msg_con_id, msg_subject, msg_timestamp, msg_usr_id_sender AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'EMAIL' and (msg_usr_id_sender = ". $gCurrentUser->getValue('usr_id') ."
		 or msg_usr_id_receiver = ". $gCurrentUser->getValue('usr_id') ." )
		 ORDER BY msg_con_id DESC";

$resultMail = $gDb->query($sql);

$headline = $gL10n->get('SYS_MESSAGES');;

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage();

// show headline for Table
$page->addHeadline($headline);

// create module menu for emails
$EmailMenu = new HtmlNavbar('admMenuEmail', $headline, $page);
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

$page->addHtml($EmailMenu->show(false));

$table = new HtmlTable('adm_lists_table', $page, true, true);

$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$table->addAttribute('border', '0');
$table->addTableHeader();

$table->addRowHeadingByArray(array( $gL10n->get('SYS_CATEGORY'),$gL10n->get('MAI_SUBJECT'), $gL10n->get('MSG_OPPOSITE'), $gL10n->get('SYS_DATE'), $gL10n->get('SYS_FEATURES')));

if(isset($resultMail))
{
    while ($row = $gDb->fetch_array($resultMail)) {
        $user = new User($gDb, $gProfileFields, $row['user']);

		$messageAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages.php?msg_id_del='.$row['msg_con_id'].'&amp;msg_typ=MAIL"><img
			                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';

		$table->addRowByArray(array( '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp'], $messageAdministration), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages_write.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_con_id']. '\''));
   }
}

if(isset($result))
{
    while ($row = $gDb->fetch_array($result)) {
        $user = new User($gDb, $gProfileFields, $row['user']);

		$table->addRowByArray(array('<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp'], ' '), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages_write.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_con_id']. '\''));
   }
}

if(isset($result1))
{
	while ($row = $gDb->fetch_array($result1)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		
		$messageAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages.php?msg_id_del='.$row['msg_con_id'].'&amp;msg_typ=PM"><img
			                     src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';

		$table->addRowByArray(array('<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp'], $messageAdministration), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages_write.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_con_id']. '\''));
    }
}

if(isset($result2))
{
	while ($row = $gDb->fetch_array($result2)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$messageAdministration = '';

	    $messageAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages.php?msg_id_del='.$row['msg_con_id'].'&amp;msg_typ=PM_del"><img
			                     src="'. THEME_PATH. '/icons/close.png" alt="'.$gL10n->get('MSG_REMOVE_CONFIRM').'" title="'.$gL10n->get('MSG_REMOVE_CONFIRM').'" /></a>
								 <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages_write.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_con_id']. '"><img
			                     src="'. THEME_PATH. '/icons/arrow_turn_right.png" alt="'.$gL10n->get('MSG_REMOVE_REVERT').'" title="'.$gL10n->get('MSG_REMOVE_REVERT').'" /></a>';
								 
								 

		$table->addRowByArray(array('<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp'], $messageAdministration), null, array('style' => 'cursor: pointer'));
    }
}

//special settings for the table
$table->setDatatablesOrderColumns(array(array(4, 'desc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();

?>