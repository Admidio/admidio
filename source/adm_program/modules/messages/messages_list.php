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
if ($gPreferences['enable_pm_module'] != 1 && $gPreferences['enable_mail_module'] != 1)
{
    // message if the sending of messages is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//SQL to find all unread PM messages
$sql = "SELECT msg_type, msg_id1, msg_subject, msg_timestamp,
			CASE WHEN msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usrid2
			ELSE msg_usrid1 
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_id2 = 0 and ((msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." and msg_user1read= 1)
		 or (msg_usrid2 = ". $gCurrentUser->getValue('usr_id') ." and msg_user2read= 1))
		 ORDER BY msg_id1 DESC";

$result = $gDb->query($sql);

//SQL to find all read PM messages
$sql = "SELECT msg_type, msg_id1, msg_subject, msg_timestamp,
			CASE WHEN msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usrid2
			ELSE msg_usrid1 
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_id2 = 0 and ((msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." and msg_user1read= 0)
		 or (msg_usrid2 = ". $gCurrentUser->getValue('usr_id') ." and msg_user2read= 0))
		 ORDER BY msg_id1 DESC";

$result1 = $gDb->query($sql);

//SQL to find all own Email messages
$sql = "SELECT msg_type, msg_id1, msg_subject, msg_timestamp,
			CASE WHEN msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usrid2
			ELSE msg_usrid1 
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'EMAIL' and (msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ."
		 or msg_usrid2 = ". $gCurrentUser->getValue('usr_id') ." )
		 ORDER BY msg_id1 DESC";

$resultMail = $gDb->query($sql);

$headline = 'Message History';

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create module menu for emails
$EmailMenu = new ModuleMenu('admMenuEmail');
// link to write new email
if ($gPreferences['enable_mail_module'] == 1 )
{
    $EmailMenu->addItem('admMenuItemNewEmail', $g_root_path.'/adm_program/modules/messages/messages.php', $gL10n->get('MAI_SEND_EMAIL'), '/email.png');
}
// link to write new PM
if ($gPreferences['enable_pm_module'] == 1 )
{
    $EmailMenu->addItem('admMenuItemNewPm', $g_root_path.'/adm_program/modules/messages/messages.php?msg_type=PM', $gL10n->get('PMS_SEND_PM'), '/email.png');
}
$EmailMenu->addItem('admMenuItemPreferences', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=messages', 
					$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');

// create html page object
$page = new HtmlPage();

// show headline for Table
$page->addHeadline($headline);

$page->addHtml($EmailMenu->show(false));

$table = new HtmlTable('adm_lists_email_table', $page, true, true);

$table->addAttribute('border', '0');
$table->addTableHeader();

$table = new HtmlTable('adm_lists_table', $page, true, true);

$table->addRowHeadingByArray(array( 'Typ' ,$gL10n->get('MAI_SUBJECT'), $gL10n->get('SYS_SENDER'), $gL10n->get('SYS_DATE')));

if(isset($resultMail))
{
    while ($row = $gDb->fetch_array($resultMail)) {
        $user = new User($gDb, $gProfileFields, $row['user']);

		$table->addRowByArray(array( '<img class="iconInformation" src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp']), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
   }
}

if(isset($result))
{
    while ($row = $gDb->fetch_array($result)) {
        $user = new User($gDb, $gProfileFields, $row['user']);

		$table->addRowByArray(array('<img class="iconInformation" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp']), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
   }
}

if(isset($result1))
{
	while ($row = $gDb->fetch_array($result1)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		
		$table->addRowByArray(array('<img class="iconInformation" src="'. THEME_PATH. '/icons/email_answer.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />' ,$row['msg_subject'], $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $row['msg_timestamp']), null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
    }
}

//special settings for the table
$table->setDatatablesOrderColumns(array(array(3, 'asc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();

?>