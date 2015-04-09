<?php
/******************************************************************************
 * PHP process for the Admidio CHAT
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * function  - set the function of the call
 * nickname  - set the nickname for the CHAT entry
 * message   - set the message for the CHAT entry
 * state     - gives the number of entries in the list that the user can see
 * 
 *****************************************************************************/
 
    require_once('../../system/common.php');
    
    // check for valid login
    if (!$gValidLogin)
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // check if the call of the page was allowed by settings
    if ($gPreferences['enable_chat_module'] != 1)
    {
        // message if the Chat is not allowed
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    }

    $postFunction = admFuncVariableIsValid($_POST, 'function', 'string');
    $postNickname = admFuncVariableIsValid($_POST, 'nickname', 'string');
    $postMessage  = admFuncVariableIsValid($_POST, 'message', 'string');
    $postLines    = admFuncVariableIsValid($_POST, 'state', 'number');

    $log = array();

    switch($postFunction) 
    {
        case('update'):
        
            $sql = "SELECT MAX(msg_part_id) as max_id
              FROM ". TBL_MESSAGES."
              where msg_converation_id = 0";

            $result = $gDb->query($sql);
            $row = $gDb->fetch_array($result);
            $MsgId = $row['max_id'];
            
            if( $MsgId+25 < $postLines)
            {
                $postLines = $postLines - 50;
            }
            
            if($postLines >= 100)
            {
                $log['test'] = '100';
                
                $sql = "DELETE FROM ". TBL_MESSAGES. " WHERE msg_type = 'CHAT' and msg_converation_id = 0 and msg_part_id <= 50";
                $gDb->query($sql);
                
                $sql = "UPDATE ". TBL_MESSAGES. " SET msg_part_id = msg_part_id - 50 WHERE msg_type = 'CHAT' and msg_converation_id = 0";
                $gDb->query($sql);
                
                $postLines = $postLines - 50;
                $MsgId = $MsgId - 50;
            }
            
            if($postLines == $MsgId)
            {
                $log['state'] = $postLines;
                $log['text'] = false;
            }
            else
            {
                $text = array();
                
                $sql = "SELECT msg_part_id, msg_subject, msg_message, msg_timestamp
                  FROM ". TBL_MESSAGES. "
                 WHERE msg_type = 'CHAT'
                   AND msg_converation_id  = 0
                   AND msg_part_id  > ".$postLines. "
                 ORDER BY msg_part_id";

                $result = $gDb->query($sql);
                while($row = $gDb->fetch_array($result))
                {
                    $text[] = '<time>'.date("d.m - H:i", strtotime($row['msg_timestamp'])).'</time><span>'.$row['msg_subject'].'</span>'.$row['msg_message'];
                }
                
                $log['state'] = $MsgId;
                $log['text'] = $text; 
            }
            break;
         
        case('send'):
            $reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
            if(($postMessage) != "\n")
            {
                if(preg_match($reg_exUrl, $postMessage, $url)) 
                {
                       $postMessage = preg_replace($reg_exUrl, '<a href="'.$url[0].'" target="_blank">'.$url[0].'</a>', $postMessage);
                } 
                // write to file for debuging
                // fwrite(fopen('chat.txt', 'a'), "<span>". $postNickname . "</span>" . $postMessage = str_replace("\n", " ", $postMessage) . "\n"); 
            }
            $sql = "SELECT MAX(msg_part_id) as max_id
              FROM ". TBL_MESSAGES."
              where msg_converation_id = 0";

            $result = $gDb->query($sql);
            $row = $gDb->fetch_array($result);
            $MsgId = $row['max_id'] + 1;

            $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_converation_id, msg_part_id, msg_subject, msg_usr_id_sender, msg_usr_id_receiver, msg_message, msg_timestamp, msg_read) 
                VALUES ('CHAT', '0', '".$MsgId."', '".$postNickname."', '', '', '".$postMessage."', CURRENT_TIMESTAMP, '0')";

            $gDb->query($sql); 
            break;
        case('delete'):
            $sql = "DELETE FROM ". TBL_MESSAGES. " WHERE msg_type = 'CHAT' and msg_converation_id = 0";
            $gDb->query($sql);
            break;
    }
    
    echo json_encode($log);

?>