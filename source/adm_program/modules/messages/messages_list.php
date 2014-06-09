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

//SQL to find all unread messages
$sql = "SELECT msg_type, msg_id1, msg_subject, msg_timestamp,
			CASE WHEN msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usrid2
			ELSE msg_usrid1 
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_id2 = 0 and ((msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." and msg_user1read= 1)
		 or (msg_usrid2 = ". $gCurrentUser->getValue('usr_id') ." and msg_user2read= 1))
		 ORDER BY msg_id1 DESC";

$result = $gDb->query($sql);

//SQL to find all read messages
$sql = "SELECT msg_type, msg_id1, msg_subject, msg_timestamp,
			CASE WHEN msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." THEN msg_usrid2
			ELSE msg_usrid1 
			END AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM' and msg_id2 = 0 and ((msg_usrid1 = ". $gCurrentUser->getValue('usr_id') ." and msg_user1read= 0)
		 or (msg_usrid2 = ". $gCurrentUser->getValue('usr_id') ." and msg_user2read= 0))
		 ORDER BY msg_id1 DESC";

$result1 = $gDb->query($sql);

$headline = $gL10n->get('PMS_MESSAGE');

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage();

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// show headline for Table
$page->addHeadline($headline);

$table = new HtmlTable('adm_lists_table', true, $page);

$table->addAttribute('border', '1');
$table->addTableHeader();
$table->addColumn($gL10n->get('MAI_SUBJECT'), '');
$table->addColumn($gL10n->get('SYS_SENDER'), ''); 
$table->addColumn($gL10n->get('SYS_DATE'), '');
$table->addTableBody();

if(isset($result))
{
    while ($row = $gDb->fetch_array($result)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$table->addRow();
		$table->addColumn($row['msg_subject'], array('style' => 'cursor: pointer; font-weight:bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
        $table->addColumn($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), array('style' => 'cursor: pointer; font-weight:bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
		$table->addColumn($row['msg_timestamp'], array('style' => 'cursor: pointer; font-weight:bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
    }
}

if(isset($result1))
{
	while ($row = $gDb->fetch_array($result1)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$table->addRow();
		$table->addColumn($row['msg_subject'], array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
        $table->addColumn($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
		$table->addColumn($row['msg_timestamp'], array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/messages/messages.php?msg_type='.$row['msg_type'].'&usr_id='.$row['user'].'&subject='.addslashes($row['msg_subject']).'&msg_id='. $row['msg_id1']. '\''));
    }
}

//special settings for the table
$table->setDatatablesOrderColumns(array(array(3, 'asc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();

?>