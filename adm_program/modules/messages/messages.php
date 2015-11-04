<?php
/******************************************************************************
 * PM list page
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
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

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Initialize and check the parameters
$getMsgId = admFuncVariableIsValid($_GET, 'msg_id', 'numeric', array('defaultValue' => 0));

if ($getMsgId != 0)
{
    $delMessage = new TableMessage($gDb, $getMsgId);

    // Function to delete message
    $delete = $delMessage->delete();
    echo $delete;
    exit();
}

$headline = $gL10n->get('SYS_MESSAGES');

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// get module menu for emails
$EmailMenu = $page->getMenu();
// link to write new email
if ($gPreferences['enable_mail_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewEmail', $g_root_path.'/adm_program/modules/messages/messages_write.php', $gL10n->get('MAI_SEND_EMAIL'), '/email.png');
}
// link to write new PM
if ($gPreferences['enable_pm_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewPm', $g_root_path.'/adm_program/modules/messages/messages_write.php?msg_type=PM', $gL10n->get('PMS_SEND_PM'), '/pm.png');
}

// link to Chat
if ($gPreferences['enable_chat_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewChat', $g_root_path.'/adm_program/modules/messages/messages_chat.php', $gL10n->get('MSG_CHAT'), '/chat.png');
}

if($gCurrentUser->isWebmaster())
{
    $EmailMenu->addItem('admMenuItemPreferences', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=messages',
                    $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$table = new HtmlTable('adm_lists_table', $page, true, true);

$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$table->addAttribute('border', '0');
$table->addTableHeader();

$table->addRowHeadingByArray(array( $gL10n->get('SYS_CATEGORY'), $gL10n->get('MAI_SUBJECT'), $gL10n->get('MSG_OPPOSITE'), $gL10n->get('SYS_DATE'), $gL10n->get('SYS_FEATURES')));
$key = 0;
$part1 = '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=msg&amp;element_id=row_message_';
$part2 = '"><img src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';
$href  = 'href="'.$g_root_path.'/adm_program/modules/messages/messages_write.php?msg_id=';

// open some additonal functions for messages
$modulemessages = new ModuleMessages();

// find all own Email messages
$result = $modulemessages->msgGetUserEmails($gCurrentUser->getValue('usr_id'));
if(isset($result))
{
    while ($row = $gDb->fetch_array($result))
    {
        $ReceiverName = '';
        if (strpos($row['user'], '|') > 0)
        {
            $reciversplit = explode('|', $row['user']);
            foreach ($reciversplit as $value)
            {
                if (strpos($value, ':') > 0)
                {
                    $ReceiverName .= '; ' . $modulemessages->msgGroupNameSplit($value);
                }
                else
                {
                    $user = new User($gDb, $gProfileFields, $value);
                    $ReceiverName .= '; ' . $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
                }
            }
        }
        else
        {
            if (strpos($row['user'], ':') > 0)
            {
                $ReceiverName .= '; ' . $modulemessages->msgGroupNameSplit($row['user']);
            }
            else
            {
                $user = new User($gDb, $gProfileFields, $row['user']);
                $ReceiverName .= '; ' . $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
            }
        }
        $ReceiverName = substr($ReceiverName, 2);

        $message = new TableMessage($gDb, $row['msg_id']);
        $key++;

        $messageAdministration = $part1 . $key . '&amp;name='.urlencode($message->getValue('msg_subject')).'&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(array( '<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') .'">
                <img class="admidio-icon-info" src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" />',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $ReceiverName, $message->getValue('msg_timestamp'), $messageAdministration), 'row_message_'.$key);
   }
}

// find all unread PM messages
$result = $modulemessages->msgGetUserUnread($gCurrentUser->getValue('usr_id'));
if(isset($result))
{
    while ($row = $gDb->fetch_array($result))
    {
        if($row['msg_usr_id_sender'] == $gCurrentUser->getValue('usr_id'))
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
        }
        $ReceiverName = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        $message = new TableMessage($gDb, $row['msg_id']);
        $key++;

        $messageAdministration = $part1 . $key . '&amp;name=' . urlencode($message->getValue('msg_subject')) . '&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(array('<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') . '">
                <img class="admidio-icon-info" src="'. THEME_PATH. '/icons/pm.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $ReceiverName, $message->getValue('msg_timestamp'), $messageAdministration), 'row_message_'.$key, array('style' => 'font-weight: bold'));
   }
}

// find all read or own PM messages
$result = $modulemessages->msgGetUser($gCurrentUser->getValue('usr_id'));
if(isset($result))
{
    while ($row = $gDb->fetch_array($result))
    {
        if($row['msg_usr_id_sender'] == $gCurrentUser->getValue('usr_id'))
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
        }

        $ReceiverName = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        $message = new TableMessage($gDb, $row['msg_id']);
        $key++;

        $messageAdministration = $part1 . $key . '&amp;name=' . urlencode($message->getValue('msg_subject')) . '&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(array('<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') . '">
                <img class="admidio-icon-info" src="'. THEME_PATH. '/icons/pm.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $ReceiverName, $message->getValue('msg_timestamp'), $messageAdministration), 'row_message_'.$key);
    }
}

// special settings for the table
$table->setDatatablesOrderColumns(array(array(4, 'desc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();
