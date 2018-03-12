<?php
/**
 ***********************************************************************************************
 * PHP process for the Admidio CHAT
 *
 * @copyright 2004-2018 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// check if the call of the page was allowed by settings
if (!$gSettingsManager->getBool('enable_chat_module'))
{
    // message if the Chat is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$postFunction = admFuncVariableIsValid($_POST, 'function', 'string');
$postMessage  = admFuncVariableIsValid($_POST, 'message',  'string');
$postLines    = admFuncVariableIsValid($_POST, 'state',    'int');

$log = array();

// open some additional functions for messages
$moduleMessages = new ModuleMessages();
// find ID of the admidio Chat
$msgId = $moduleMessages->msgGetChatId();

$sql = 'SELECT MAX(msc_part_id) AS max_id
          FROM '.TBL_MESSAGES_CONTENT.'
         WHERE msc_msg_id = ?';
$pdoStatement = $gDb->queryPrepared($sql, array($msgId));
$msgPart = $pdoStatement->fetchColumn();
if(!$msgPart)
{
    $msgId = 0;
    $msgPart = 0;
}

switch($postFunction)
{
    case 'update':
        if($msgPart + 25 < $postLines)
        {
            $postLines -= 50;
        }

        if($postLines >= 100)
        {
            $log['test'] = '100';

            $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id = ?
                       AND msc_part_id <= 50';
            $gDb->queryPrepared($sql, array($msgId));

            $sql = 'UPDATE '.TBL_MESSAGES_CONTENT.'
                       SET msc_part_id = msc_part_id - 50
                     WHERE msc_msg_id = ?';
            $gDb->queryPrepared($sql, array($msgId));

            $postLines -= 50;
            $msgPart -= 50;
        }

        if($postLines === $msgPart)
        {
            $log['state'] = $postLines;
            $log['text']  = false;
        }
        else
        {
            $text = array();

            $sql = 'SELECT msc_part_id, msc_usr_id, msc_message, msc_timestamp
                      FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id  = ? -- $msgId
                       AND msc_part_id > ? -- $postLines
                  ORDER BY msc_part_id';

            $statement = $gDb->queryPrepared($sql, array($msgId, $postLines));
            while($row = $statement->fetch())
            {
                $user = new User($gDb, $gProfileFields, $row['msc_usr_id']);
                $date = \DateTime::createFromFormat('Y-m-d H:i:s', $row['msc_timestamp']);
                $text[] = '<time>'.$date->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')).'</time><span>'.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').'</span>'.$row['msc_message'];
            }

            $log['state'] = $msgPart;
            $log['text']  = $text;
        }
        break;

    case 'send':
        if($postMessage !== "\n")
        {
            $regexUrl = '/(http|ftp)s?\:\/\/[a-zA-Z\d\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?/';
            if(preg_match($regexUrl, $postMessage, $url))
            {
                $postMessage = preg_replace($regexUrl, '<a href="'.$url[0].'" target="_blank">'.$url[0].'</a>', $postMessage);
            }
        }

        if($msgId === 0)
        {
            $sql = 'INSERT INTO '. TBL_MESSAGES. '
                           (msg_type, msg_subject, msg_usr_id_sender, msg_usr_id_receiver, msg_timestamp, msg_read)
                    VALUES (\'CHAT\', \'DUMMY\', 1, 1, CURRENT_TIMESTAMP, 0)';
            $gDb->queryPrepared($sql);
            $msgId = $moduleMessages->msgGetChatId();
        }

        $msgPart = $msgPart + 1;

        $sql = 'INSERT INTO '. TBL_MESSAGES_CONTENT. '
                       (msc_msg_id, msc_part_id, msc_usr_id, msc_message, msc_timestamp)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP) -- $msgId, $msgPart, $gCurrentUser->getValue(\'usr_id\'), $postMessage';
        $gDb->queryPrepared($sql, array($msgId, $msgPart, (int) $gCurrentUser->getValue('usr_id'), $postMessage));

        $log['state'] = $msgPart;
        break;

    case 'delete':
        $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                 WHERE msc_msg_id = ?';
        $gDb->queryPrepared($sql, array($msgId));
        break;
}

header('Content-Type: application/json');
echo json_encode($log);
