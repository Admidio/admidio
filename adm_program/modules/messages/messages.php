<?php
/**
 ***********************************************************************************************
 * PM list page
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// check if the call of the page was allowed
if ($gPreferences['enable_pm_module'] != 1 && $gPreferences['enable_mail_module'] != 1 && $gPreferences['enable_chat_module'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// Initialize and check the parameters
$getMsgId = admFuncVariableIsValid($_GET, 'msg_id', 'int', array('defaultValue' => 0));

if ($getMsgId > 0)
{
    $delMessage = new TableMessage($gDb, $getMsgId);

    // Function to delete message
    $delete = $delMessage->delete();
    if ($delete)
    {
        echo 'done';
    }
    else
    {
        echo 'delete not OK';
    }
    exit();
}

$headline = $gL10n->get('SYS_MESSAGES');

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

// get module menu for emails
$EmailMenu = $page->getMenu();
// link to write new email
if ($gPreferences['enable_mail_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewEmail', ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', $gL10n->get('MAI_SEND_EMAIL'), '/email.png');
}
// link to write new PM
if ($gPreferences['enable_pm_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewPm', ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?msg_type=PM', $gL10n->get('PMS_SEND_PM'), '/pm.png');
}

// link to Chat
if ($gPreferences['enable_chat_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewChat', ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_chat.php', $gL10n->get('MSG_CHAT'), '/chat.png');
}

if($gCurrentUser->isAdministrator())
{
    $EmailMenu->addItem('admMenuItemPreferences', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=messages',
                    $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$table = new HtmlTable('adm_lists_table', $page, true, true);

$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));

$table->addRowHeadingByArray(array(
    '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/email.png" alt="'.$gL10n->get('SYS_CATEGORY').'" title="'.$gL10n->get('SYS_CATEGORY').'" />',
    $gL10n->get('MAI_SUBJECT'),
    $gL10n->get('MSG_OPPOSITE'),
    $gL10n->get('SYS_DATE'),
    ''
));
$table->disableDatatablesColumnsSort(array(5));
$key = 0;
$part1 = '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal" href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=msg&amp;element_id=row_message_';
$part2 = '"><img src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';
$href  = 'href="'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?msg_id=';

// open some additonal functions for messages
$moduleMessages = new ModuleMessages();

// find all own Email messages
$statement = $moduleMessages->msgGetUserEmails($gCurrentUser->getValue('usr_id'));
if(isset($statement))
{
    require_once('messages_functions.php');

    while ($row = $statement->fetch())
    {
        $receiverName = prepareReceivers($row['user']);

        $message = new TableMessage($gDb, $row['msg_id']);
        ++$key;

        $messageAdministration = $part1 . $key . '&amp;name='.urlencode($message->getValue('msg_subject')).'&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(
            array(
                '<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') .'">
                    <img class="admidio-icon-info" src="'. THEME_URL. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" />
                </a>',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $receiverName,
                $message->getValue('msg_timestamp'),
                $messageAdministration
            ),
            'row_message_'.$key
        );
    }
}

// find all unread PM messages
$statement = $moduleMessages->msgGetUserUnread($gCurrentUser->getValue('usr_id'));
if(isset($statement))
{
    while ($row = $statement->fetch())
    {
        if((int) $row['msg_usr_id_sender'] === (int) $gCurrentUser->getValue('usr_id'))
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
        }
        $receiverName = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        $message = new TableMessage($gDb, $row['msg_id']);
        ++$key;

        $messageAdministration = $part1 . $key . '&amp;name=' . urlencode($message->getValue('msg_subject')) . '&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(
            array(
                '<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') . '">
                    <img class="admidio-icon-info" src="'. THEME_URL. '/icons/pm.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />
                </a>',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $receiverName,
                $message->getValue('msg_timestamp'),
                $messageAdministration
            ),
            'row_message_'.$key,
            array('style' => 'font-weight: bold')
        );
    }
}

// find all read or own PM messages
$statement = $moduleMessages->msgGetUser($gCurrentUser->getValue('usr_id'));
if(isset($statement))
{
    while ($row = $statement->fetch())
    {
        if((int) $row['msg_usr_id_sender'] === (int) $gCurrentUser->getValue('usr_id'))
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
        }

        $receiverName = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        $message = new TableMessage($gDb, $row['msg_id']);
        ++$key;

        $messageAdministration = $part1 . $key . '&amp;name=' . urlencode($message->getValue('msg_subject')) . '&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(
            array(
                '<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') . '">
                    <img class="admidio-icon-info" src="'. THEME_URL. '/icons/pm.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />
                </a>',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $receiverName,
                $message->getValue('msg_timestamp'),
                $messageAdministration
            ),
            'row_message_'.$key
        );
    }
}

// special settings for the table
$table->setDatatablesOrderColumns(array(array(4, 'desc')));

// add table to the form
$page->addHtml($table->show());

// add form to html page and show page
$page->show();
