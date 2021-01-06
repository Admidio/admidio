<?php
/**
 ***********************************************************************************************
 * Messages Functions
 *
 * @copyright 2004-2021 The Admidio Team
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
        <a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_id' => $msgId)) . '">
            <i class="fas ' . $icon . '" data-toggle="tooltip" title="' . $title . '"></i>
        </a>';
}

/**
 * @param int    $msgId
 * @param string $msgSubject
 * @return string
 */
function getMessageLink($msgId, $msgSubject)
{
    return '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_id' => $msgId)) . '">' . $msgSubject . '</a>';
}

/**
 * Create a readable string of recipients from role-ids and user-ids of the select2 control
 * @param string $recipientsString  The source string with the role and user-ids of the select2 control
 * @param bool   $showFullUserNames If set to true than the individual recipients will be shown with full user name
 * @return string Returns a readable string of recipients roles and users e.g. "Members, John Doe"
 */
function prepareRecipients($recipientsString, $showFullUserNames = false)
{
    global $gDb, $gProfileFields, $gL10n;

    $singleRecipientsCount = 0;
    $recipientName = '';
    $recipientsSplit = explode('|', $recipientsString);

    foreach ($recipientsSplit as $recipients)
    {
        if (str_starts_with($recipients, 'list '))
        {
            $recipientName .= '; ' . substr($recipients, 5);
        }
        elseif (str_contains($recipients, ':'))
        {
            $moduleMessages = new ModuleMessages();
            $recipientName .= '; ' . $moduleMessages->msgGroupNameSplit($recipients);
        }
        else
        {
            $singleRecipientsCount = $singleRecipientsCount + 1;

            if($showFullUserNames)
            {
                $user = new User($gDb, $gProfileFields, (int) trim($recipients));
                $recipientName .= '; ' . $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
            }
        }
    }

    if(strlen($recipientName) > 0)
    {
        $recipientName = substr($recipientName, 2);
    }

    // if full user names should not be shown than create a text with the number of individual recipients
    if(!$showFullUserNames && $singleRecipientsCount > 0)
    {
        if($singleRecipientsCount === 1)
        {
            $textIndividualRecipients = $gL10n->get('SYS_COUNT_INDIVIDUAL_RECIPIENT', array($singleRecipientsCount));
        }
        else
        {
            $textIndividualRecipients = $gL10n->get('SYS_COUNT_INDIVIDUAL_RECIPIENTS', array($singleRecipientsCount));
        }

        if(strlen($recipientName) > 0)
        {
            $recipientName = $gL10n->get('SYS_PARAMETER1_AND_PARAMETER2', array($recipientName, $textIndividualRecipients));
        }
        else
        {
            $recipientName = $textIndividualRecipients;
        }
    }

    return $recipientName;
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
        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
            data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/system/popup_message.php', array('type' => 'msg', 'element_id' => 'row_message_' . $rowIndex, 'name' => $msgSubject, 'database_id' => $msgId)) . '">
            <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('MSG_REMOVE').'"></i>
        </a>';
}
