<?php
/**
 ***********************************************************************************************
 * PM list page
 *
 * @copyright 2004-2018 The Admidio Team
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
$page = new HtmlPage($headline);
$page->enableModal();

// get module menu for messages
$messagesMenu = $page->getMenu();
// link to write new email
if ($gSettingsManager->getBool('enable_mail_module'))
{
    $messagesMenu->addItem(
        'admMenuItemNewEmail', ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php',
        $gL10n->get('SYS_WRITE_EMAIL'), '/email.png'
    );
}
// link to write new PM
if ($gSettingsManager->getBool('enable_pm_module'))
{
    $messagesMenu->addItem(
        'admMenuItemNewPm', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('msg_type' => 'PM')),
        $gL10n->get('SYS_WRITE_PM'), '/pm.png'
    );
}

// link to Chat
if ($gSettingsManager->getBool('enable_chat_module'))
{
    $messagesMenu->addItem(
        'admMenuItemNewChat', ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_chat.php',
        $gL10n->get('MSG_CHAT'), '/chat.png'
    );
}

if ($gCurrentUser->isAdministrator())
{
    $messagesMenu->addItem(
        'admMenuItemPreferences', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'messages')),
        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right'
    );
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
            getMessageIcon($msgId, 'email.png', $gL10n->get('SYS_EMAIL')),
            getMessageLink($msgId, $msgSubject),
            prepareReceivers($row['user']),
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
            getMessageIcon($msgId, 'pm.png', $gL10n->get('PMS_MESSAGE')),
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
            getMessageIcon($msgId, 'pm.png', $gL10n->get('PMS_MESSAGE')),
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
