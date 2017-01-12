<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$postFunction = admFuncVariableIsValid($_POST, 'function', 'string');
$postNickname = admFuncVariableIsValid($_POST, 'nickname', 'string');
$postMessage  = admFuncVariableIsValid($_POST, 'message',  'string');
$postLines    = admFuncVariableIsValid($_POST, 'state',    'number');

$log = array();

echo '<script>
$(function() {
    $("button#submit").click(function() {
        $.post({
            url: "process.php",
            data: $("#template-form").serialize(),
            success: function(data) {
                $("#responsestatus").val(data);
                $("#subscription-confirm").modal("show");
            }
        });
    });
});
</script>
';

switch($postFunction)
{
    case 'update':
        $sql = 'SELECT MAX(msg_part_id) AS max_id
                  FROM '.TBL_MESSAGES.'
                 WHERE msg_converation_id = 0';

        $pdoStatement = $gDb->query($sql);
        $msgId = (int) $pdoStatement->fetchColumn();

        if($msgId + 25 < $postLines)
        {
            $postLines -= 50;
        }

        if($postLines >= 100)
        {
            $log['test'] = '100';

            $sql = 'DELETE FROM '.TBL_MESSAGES.'
                     WHERE msg_type = \'CHAT\' AND msg_converation_id = 0 AND msg_part_id <= 50';
            $gDb->query($sql);

            $sql = 'UPDATE '.TBL_MESSAGES.'
                       SET msg_part_id = msg_part_id - 50
                     WHERE msg_type = \'CHAT\' AND msg_converation_id = 0';
            $gDb->query($sql);

            $postLines -= 50;
            $msgId -= 50;
        }

        if($postLines === $msgId)
        {
            $log['state'] = $postLines;
            $log['text'] = false;
        }
        else
        {
            $text = array();

            $sql = 'SELECT msg_part_id, msg_subject, msg_message, msg_timestamp
                      FROM '.TBL_MESSAGES.'
                     WHERE msg_type = \'CHAT\'
                       AND msg_converation_id  = 0
                       AND msg_part_id > '.$postLines.'
                  ORDER BY msg_part_id';

            $statement = $gDb->query($sql);
            while($row = $statement->fetch())
            {
                $text[] = '<time>'.date('d.m - H:i', strtotime($row['msg_timestamp'])).'</time><span>'.$row['msg_subject'].'</span>'.$row['msg_message'];
            }

            $log['state'] = $msgId;
            $log['text'] = $text;
        }
        break;

    case 'send':
        if($postMessage !== "\n")
        {
            $reg_exUrl = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/';
            if(preg_match($reg_exUrl, $postMessage, $url))
            {
                $postMessage = preg_replace($reg_exUrl, '<a href="'.$url[0].'" target="_blank">'.$url[0].'</a>', $postMessage);
            }
            fwrite(fopen('chat.txt', 'ab'), '<span>'. $postNickname . '</span>' . $postMessage = str_replace("\n", ' ', $postMessage) . "\n");
        }
        $sql = 'SELECT MAX(msg_part_id) AS max_id
                  FROM '. TBL_MESSAGES.'
                 WHERE msg_converation_id = 0';
        $pdoStatement = $gDb->query($sql);
        $msgId = $pdoStatement->fetchColumn() + 1;

        $sql = 'INSERT INTO '.TBL_MESSAGES.' (msg_type, msg_converation_id, msg_part_id, msg_subject, msg_usr_id_sender, msg_usr_id_receiver, msg_message, msg_timestamp, msg_read)
            VALUES (\'CHAT\', \'0\', \''.$msgId.'\', \''.$postNickname.'\', \'\', \'\', \''.$postMessage.'\', CURRENT_TIMESTAMP, \'0\')';

        $gDb->query($sql);
        break;

    case 'delete':
        $sql = 'DELETE FROM '.TBL_MESSAGES.'
                 WHERE msg_type = \'CHAT\' AND msg_converation_id = 0';
        $gDb->query($sql);
        break;
}

echo json_encode($log);
