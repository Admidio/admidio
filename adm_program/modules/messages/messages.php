<?php
/**
 ***********************************************************************************************
 * PM list page
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/messages_functions.php');

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// check if the call of the page was allowed
if (!$gSettingsManager->getBool('enable_pm_module') && !$gSettingsManager->getBool('enable_mail_module') && !$gSettingsManager->getBool('enable_chat_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
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
$page = new HtmlPage('admidio-messages', $headline);

// link to write new email
if ($gSettingsManager->getBool('enable_mail_module'))
{
    $page->addPageFunctionsMenuItem('menu_item_messages_new_email', $gL10n->get('SYS_WRITE_EMAIL'),
        ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', 'fa-envelope-open');
}
// link to write new PM
if ($gSettingsManager->getBool('enable_pm_module'))
{
    $page->addPageFunctionsMenuItem('menu_item_messages_new_pm', $gL10n->get('SYS_WRITE_PM'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('msg_type' => 'PM')),
        'fa-comment-alt');
}

// link to Chat
if ($gSettingsManager->getBool('enable_chat_module'))
{
    $page->addPageFunctionsMenuItem('menu_item_messages_chat', $gL10n->get('MSG_CHAT'),
        ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_chat.php', 'fa-comments');
}

$table = new HtmlTable('adm_lists_table', $page, true, true);

$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));

$table->addRowHeadingByArray(array(
    '<i class="fas fa-envelope" data-toggle="tooltip" title="' . $gL10n->get('SYS_CATEGORY') . '"></i>',
    $gL10n->get('MAI_SUBJECT'),
    $gL10n->get('MSG_OPPOSITE'),
    $gL10n->get('SYS_DATE'),
    ''
));
$table->disableDatatablesColumnsSort(array(5));

// open some additional functions for messages
$moduleMessages = new ModuleMessages();
$usrId = (int) $gCurrentUser->getValue('usr_id');
$rowIndex = 0;

// find all own Email messages
$allEmailsStatement = $moduleMessages->msgGetUserEmails($usrId);
while ($row = $allEmailsStatement->fetch())
{
    ++$rowIndex;
    $msgId = (int) $row['msg_id'];
    $message = new TableMessage($gDb, $msgId);
    $msgSubject = $message->getValue('msg_subject');

    $table->addRowByArray(
        array(
            getMessageIcon($msgId, 'fa-envelope', $gL10n->get('SYS_EMAIL')),
            getMessageLink($msgId, $msgSubject),
            prepareRecipients($row['user'], true),
            $message->getValue('msg_timestamp'),
            getAdministrationLink($rowIndex, $msgId, $msgSubject)
        ),
        'row_message_'.$rowIndex
    );
}

// find all unread PM messages
$pmUnreadStatement = $moduleMessages->msgGetUserUnread($usrId);
while ($row = $pmUnreadStatement->fetch())
{
    ++$rowIndex;
    $msgId = (int) $row['msg_id'];
    $message = new TableMessage($gDb, $msgId);
    $msgSubject = $message->getValue('msg_subject');

    $table->addRowByArray(
        array(
            getMessageIcon($msgId, 'fa-comment-alt', $gL10n->get('PMS_MESSAGE')),
            getMessageLink($msgId, $msgSubject),
            getReceiverName($row, $usrId),
            $message->getValue('msg_timestamp'),
            getAdministrationLink($rowIndex, $msgId, $msgSubject)
        ),
        'row_message_'.$rowIndex,
        array('style' => 'font-weight: bold')
    );
}

// find all read or own PM messages
$pwReadOrOwnStatement = $moduleMessages->msgGetUser($usrId);
while ($row = $pwReadOrOwnStatement->fetch())
{
    ++$rowIndex;
    $msgId = (int) $row['msg_id'];
    $message = new TableMessage($gDb, $msgId);
    $msgSubject = $message->getValue('msg_subject');

    $table->addRowByArray(
        array(
            getMessageIcon($msgId, 'fa-comment-alt', $gL10n->get('PMS_MESSAGE')),
            getMessageLink($msgId, $msgSubject),
            getReceiverName($row, $usrId),
            $message->getValue('msg_timestamp'),
            getAdministrationLink($rowIndex, $msgId, $msgSubject)
        ),
        'row_message_'.$rowIndex
    );
}

// special settings for the table
$table->setDatatablesOrderColumns(array(array(4, 'desc')));

// add table to the form
$page->addHtml($table->show());

// add form to html page and show page
$page->show();
