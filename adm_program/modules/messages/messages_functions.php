<?php
/**
 ***********************************************************************************************
 * Messages Functions
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */

/**
 * @param int    $msgId
 * @param string $icon
 * @param string $title
 * @return string
 */
function getMessageIcon($msgId, $icon, $title)
{
    return '
        <a class="admidio-icon-link" href="' . safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_id' => $msgId)) . '">
            <img class="admidio-icon-info" src="' . THEME_URL . '/icons/' . $icon . '" alt="' . $title . '" title="' . $title . '" />
        </a>';
}

/**
 * @param int    $msgId
 * @param string $msgSubject
 * @return string
 */
function getMessageLink($msgId, $msgSubject)
{
    return '<a href="' . safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_id' => $msgId)) . '">' . $msgSubject . '</a>';
}

/**
 * @param string $receiversString
 * @return string
 */
function prepareReceivers($receiversString)
{
    global $gDb, $gProfileFields;

    $receiverNames = '';
    $receiversSplit = explode('|', $receiversString);
    foreach ($receiversSplit as $receivers)
    {
        if (StringUtils::strStartsWith($receivers, 'list '))
        {
            $receiverNames .= '; ' . substr($receivers, 5);
        }
        elseif (StringUtils::strContains($receivers, ':'))
        {
            $moduleMessages = new ModuleMessages();
            $receiverNames .= '; ' . $moduleMessages->msgGroupNameSplit($receivers);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, (int) trim($receivers));
            $receiverNames .= '; ' . $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
        }
    }

    return substr($receiverNames, 2);
}

/**
 * @param array<string,mixed> $row
 * @param int                 $usrId
 * @return string
 */
function getReceiverName(array $row, $usrId)
{
    global $gDb, $gProfileFields;

    if ((int) $row['msg_usr_id_sender'] === $usrId)
    {
        $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
    }
    else
    {
        $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
    }

    return $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
}

/**
 * @param int    $rowIndex
 * @param int    $msgId
 * @param string $msgSubject
 * @return string
 */
function getAdministrationLink($rowIndex, $msgId, $msgSubject)
{
    global $gL10n;

    return '
        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
            href="' . safeUrl(ADMIDIO_URL . '/adm_program/system/popup_message.php', array('type' => 'msg', 'element_id' => 'row_message_' . $rowIndex, 'name' => $msgSubject, 'database_id' => $msgId)) . '">
            <img src="' . THEME_URL . '/icons/delete.png" alt="' . $gL10n->get('MSG_REMOVE') . '" title="' . $gL10n->get('MSG_REMOVE') . '" />
        </a>';
}
