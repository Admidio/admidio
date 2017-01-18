<?php
/**
 ***********************************************************************************************
 * Messages Functions
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */

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
        if (strpos($receivers, 'list ') === 0)
        {
            $receiverNames .= '; ' . substr($receivers, 5);
        }
        elseif (strpos($receivers, ':') > 0)
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
