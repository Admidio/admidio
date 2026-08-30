<?php
/**
 ***********************************************************************************************
 * Check message information and save it
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * msg_uuid  - Message UUID for conversations
 * user_uuid - Send message to the given user UUID
 * msg_type  - set message type
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Messages\Entity\Message;
use Admidio\Messages\Service\MessageService;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getMsgUUID = admFuncVariableIsValid($_GET, 'msg_uuid', 'uuid');
    $getUsrUUID = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getMsgType = admFuncVariableIsValid($_GET, 'msg_type', 'string');

    $postUserUuidList = '';
    $postListUuid = '';

    if ($gValidLogin) {
        $postUserUuidList = admFuncVariableIsValid($_POST, 'userUuidList', 'string');
        $postListUuid = admFuncVariableIsValid($_POST, 'list_uuid', 'uuid');
    }

    // check form field input and sanitize it from malicious content
    $messagesSendForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $messagesSendForm->validate($_POST);

    $recipients = array_values(
        array_filter(
            isset($_POST['msg_to']) && is_array($_POST['msg_to']) ? $_POST['msg_to'] : array(),
            'is_string'
        )
    );

    if ($postUserUuidList !== '') {
        $recipients = explode(',', $postUserUuidList);
        foreach ($recipients as $key => $userUuid) {
            if (!Uuid::isValid($userUuid)) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }
        }
    }

    if ($getMsgType === Message::MESSAGE_TYPE_PM
        && $getUsrUUID === ''
        && isset($recipients[0])
        && Uuid::isValid($recipients[0])) {
        $getUsrUUID = $recipients[0];
    }

    $attachments = array();
    if (isset($_FILES['userfile'])) {
        if (!$gValidLogin) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        for ($currentAttachmentNo = 0;
             isset($_FILES['userfile']['name'][$currentAttachmentNo]);
             ++$currentAttachmentNo) {
            $uploadError = $_FILES['userfile']['error'][$currentAttachmentNo];

            if ($uploadError !== UPLOAD_ERR_OK && $uploadError !== UPLOAD_ERR_NO_FILE) {
                throw new Exception('SYS_ATTACHMENT_TO_LARGE');
            }

            $temporaryFile = (string)$_FILES['userfile']['tmp_name'][$currentAttachmentNo];
            if ($temporaryFile === '') {
                continue;
            }

            if (!file_exists($temporaryFile) || !is_uploaded_file($temporaryFile)) {
                throw new Exception('SYS_FILE_NOT_EXIST');
            }

            $attachments[] = array(
                'path' => $temporaryFile,
                'name' => (string)$_FILES['userfile']['name'][$currentAttachmentNo],
                'type' => (string)$_FILES['userfile']['type'][$currentAttachmentNo]
            );
        }
    }

    $messageService = new MessageService($gDb);
    $message = $messageService->sendData(
        $getMsgType,
        (string)$formValues['msg_subject'],
        (string)$formValues['msg_body'],
        $recipients,
        $getMsgUUID,
        $getUsrUUID,
        $postListUuid,
        $attachments,
        (string)($formValues['sender_name'] ?? ''),
        (string)($formValues['sender_email'] ?? ''),
        !empty($formValues['delivery_confirmation']),
        !empty($formValues['carbon_copy'])
    );

    $gNavigation->deleteLastUrl();

    if ((string)$message->getValue('msg_type') === Message::MESSAGE_TYPE_PM) {
        $partnerId = (int)$message->getValue('msg_usr_id_sender') !== $gCurrentUserId
            ? (int)$message->getValue('msg_usr_id_sender')
            : (int)$message->getConversationPartner();
        $user = new User($gDb, $gProfileFields, $partnerId);

        $successMessage = $gL10n->get(
            'SYS_PRIVATE_MESSAGE_SEND',
            array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))
        );
    } else {
        $successMessage = $gL10n->get('SYS_EMAIL_SEND');
    }

    echo json_encode(array(
        'status' => 'success',
        'message' => $successMessage,
        'url' => $gNavigation->getUrl()
    ));
} catch (Throwable $e) {
    handleException($e, true);
}
