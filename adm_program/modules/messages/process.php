<?php
/**
 ***********************************************************************************************
 * PHP process for the Admidio CHAT
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * function  - set the function of the call
 * message   - set the message for the CHAT entry
 * state     - gives the number of entries in the list that the user can see
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// check if the call of the page was allowed by settings
if ($gPreferences['enable_chat_module'] != 1)
{
    // message if the Chat is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$postFunction = admFuncVariableIsValid($_POST, 'function', 'string');
$postMessage  = admFuncVariableIsValid($_POST, 'message',  'string');
$postLines    = admFuncVariableIsValid($_POST, 'state',    'int');

$log = array();

// open some additonal functions for messages
$moduleMessages = new ModuleMessages();
// find ID of the admidio Chat
$msg_id = $moduleMessages->msgGetChatId();

$sql = 'SELECT MAX(msc_part_id) AS max_id
          FROM '.TBL_MESSAGES_CONTENT.'
         WHERE msc_msg_id = \''.$msg_id.'\'';
$pdoStatement = $gDb->query($sql);
$msgId = $pdoStatement->fetchColumn();
if(!$msgId)
{
    $msgId = 0;
}

switch($postFunction)
{
    case 'update':
        if($msgId + 25 < $postLines)
        {
            $postLines -= 50;
        }

        if($postLines >= 100)
        {
            $log['test'] = '100';

            $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id = \''.$msg_id.'\' AND msc_part_id <= 50';
            $gDb->query($sql);

            $sql = 'UPDATE '.TBL_MESSAGES_CONTENT.'
                       SET msc_part_id = msc_part_id - 50
                     WHERE msc_msg_id = \''.$msg_id.'\'';
            $gDb->query($sql);

            $postLines -= 50;
            $msgId -= 50;
        }

        if($postLines === $msgId)
        {
            $log['state'] = $postLines;
            $log['text']  = false;
        }
        else
        {
            $text = array();

            $sql = 'SELECT msc_part_id, msc_usr_id, msc_message, msc_timestamp
                      FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id  = \''.$msg_id.'\'
                       AND msc_part_id > '.$postLines. '
                  ORDER BY msc_part_id';

            $statement = $gDb->query($sql);
            while($row = $statement->fetch())
            {
                $user = new User($gDb, $gProfileFields, $row['msc_usr_id']);
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $row['msc_timestamp']);
                $text[] = '<time>'.$date->format($gPreferences['system_date'].' '.$gPreferences['system_time']).'</time><span>'.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').'</span>'.$row['msc_message'];
            }

            $log['state'] = $msgId;
            $log['text']  = $text;
        }
        break;

    case 'send':
        if($postMessage !== "\n")
        {
            $reg_exUrl = '/^(http|ftp)s?\:\/\/[\da-zA-Z\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?/';
            if(preg_match($reg_exUrl, $postMessage, $url))
            {
                $postMessage = preg_replace($reg_exUrl, '<a href="'.$url[0].'" target="_blank">'.$url[0].'</a>', $postMessage);
            }
        }

        if($msgId === 0)
        {
            $sql = 'INSERT INTO '. TBL_MESSAGES. ' (msg_type, msg_subject, msg_usr_id_sender, msg_usr_id_receiver, msg_timestamp, msg_read)
                    VALUES (\'CHAT\', \'DUMMY\', \'1\', \''.$msgId.'\', CURRENT_TIMESTAMP, \'0\')';
            $gDb->query($sql);
            $msg_id = $moduleMessages->msgGetChatId();
        }

        ++$msgId;

        $sql = 'INSERT INTO '. TBL_MESSAGES_CONTENT. ' (msc_msg_id, msc_part_id, msc_usr_id, msc_message, msc_timestamp)
                VALUES (\''.$msg_id.'\', \''.$msgId.'\', \''.$gCurrentUser->getValue('usr_id').'\', \''.$postMessage.'\', CURRENT_TIMESTAMP)';

        $gDb->query($sql);
        $log['state'] = $msgId;
        break;

    case 'delete':
        $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.' WHERE msc_msg_id = \''.$msg_id.'\'';
        $gDb->query($sql);
        break;
}

echo json_encode($log);
