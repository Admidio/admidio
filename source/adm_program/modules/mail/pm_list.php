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
$sql = 'SELECT pm_id1, pm_subject, pm_timestamp,
			CASE WHEN pm_usrid1 = '. $gCurrentUser->getValue('usr_id') .' THEN pm_usrid2
			ELSE pm_usrid1 
			END AS user
        FROM '. TBL_PM. '
         WHERE pm_id2 = 0 and ((pm_usrid1 = '. $gCurrentUser->getValue('usr_id') .' and pm_user1read= 1)
		 or (pm_usrid2 = '. $gCurrentUser->getValue('usr_id') .' and pm_user2read= 1))
		 ORDER BY pm_id1 DESC';

$result = $gDb->query($sql);

//SQL to find all read messages
$sql = 'SELECT pm_id1, pm_subject, pm_timestamp,
			CASE WHEN pm_usrid1 = '. $gCurrentUser->getValue('usr_id') .' THEN pm_usrid2
			ELSE pm_usrid1 
			END AS user
        FROM '. TBL_PM. '
         WHERE pm_id2 = 0 and ((pm_usrid1 = '. $gCurrentUser->getValue('usr_id') .' and pm_user1read= 0)
		 or (pm_usrid2 = '. $gCurrentUser->getValue('usr_id') .' and pm_user2read= 0))
		 ORDER BY pm_id1 DESC';

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

$table = new HtmlTable('adm_lists_table', true, $page, 'table');

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
		$table->addColumn($row['pm_subject'], array('style' => 'cursor: pointer; font-weight:bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/mail/pm.php?usr_id='.$row['user'].'&subject='.$row['pm_subject'].'&pm_id='. $row['pm_id1']. '\''));
        $table->addColumn($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), array('style' => 'cursor: pointer; font-weight:bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/mail/pm.php?usr_id='.$row['user'].'&subject='.$row['pm_subject'].'&pm_id='. $row['pm_id1']. '\''));
		$table->addColumn($row['pm_timestamp'], array('style' => 'cursor: pointer; font-weight:bold', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/mail/pm.php?usr_id='.$row['user'].'&subject='.$row['pm_subject'].'&pm_id='. $row['pm_id1']. '\''));
    }
}

if(isset($result1))
{
	while ($row = $gDb->fetch_array($result1)) {
        $user = new User($gDb, $gProfileFields, $row['user']);
		$table->addRow();
		$table->addColumn($row['pm_subject'], array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/mail/pm.php?usr_id='.$row['user'].'&subject='.$row['pm_subject'].'&pm_id='. $row['pm_id1']. '\''));
        $table->addColumn($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/mail/pm.php?usr_id='.$row['user'].'&subject='.$row['pm_subject'].'&pm_id='. $row['pm_id1']. '\''));
		$table->addColumn($row['pm_timestamp'], array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/mail/pm.php?usr_id='.$row['user'].'&subject='.$row['pm_subject'].'&pm_id='. $row['pm_id1']. '\''));
    }
}

//special settings for the table
$table->setDatatablesOrderColumns(array(array(3, 'asc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();

?>