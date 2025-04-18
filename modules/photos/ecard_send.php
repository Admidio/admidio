<?php
/**
 ***********************************************************************************************
 * Send ecard to users and show status message
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Messages\Entity\Message;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\ValueObject\ECard;
use Admidio\Roles\Entity\Role;
use Ramsey\Uuid\Uuid;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // check if the photo module is enabled and eCard is enabled
    if (!$gSettingsManager->getBool('photo_ecard_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users can access the module
        require(__DIR__ . '/../../system/login_valid.php');
    }

    // Initialize and check the parameters
    $postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
    $postPhotoUuid = admFuncVariableIsValid($_POST, 'photo_uuid', 'uuid', array('requireValue' => true));
    $postPhotoNr = admFuncVariableIsValid($_POST, 'photo_nr', 'int', array('requireValue' => true));
    $postMessage = $_POST['ecard_message'];

    $funcClass = new ECard($gL10n);
    $photoAlbum = new Album($gDb);
    $photoAlbum->readDataByUuid($postPhotoUuid);
    $imageUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $postPhotoUuid, 'photo_nr' => $postPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_ecard_scale'), 'max_height' => $gSettingsManager->getInt('photo_ecard_scale')));
    $imageServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id') . '/' . $postPhotoNr . '.jpg';

    // check if user has right to view the album
    if (!$photoAlbum->isVisible()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // the logged-in user has no valid mail address stored in his profile, which can be used as sender
    if ($gValidLogin && $gCurrentUser->getValue('EMAIL') === '') {
        throw new Exception('SYS_CURRENT_USER_NO_EMAIL', array('<a href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php">', '</a>'));
    }

    $senderName  = $gCurrentUser->getValue('FIRST_NAME') . ' ecard_send.php' . $gCurrentUser->getValue('LAST_NAME');
    $senderEmail = $gCurrentUser->getValue('EMAIL');

    // check form field input and sanitized it from malicious content
    $photosEcardSendForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $photosEcardSendForm->validate($_POST);

    // read template from file system
    $ecardDataToParse = $funcClass->getEcardTemplate($postTemplateName);

    // if template was not found then show error
    if ($ecardDataToParse === null) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // check if user has right to send mail to selected roles and users
    $arrayRoles = array();
    $arrayUsers = array();
    $receiverString = implode(' | ', $_POST['ecard_recipients']);
    $sqlEmailField = '';

    foreach ($_POST['ecard_recipients'] as $value) {
        if (str_contains($value, 'groupID')) {
            if (Uuid::isValid(substr($value, 9))) {
                $role_uuid = substr($value, 9);

                $role = new Role($gDb);
                $role->readDataByUuid($role_uuid);
                if ($gCurrentUser->hasRightSendMailToRole($role->getValue('rol_id'))) {
                    $arrayRoles[] = $role_uuid;
                }
            }
        } else {
            if (Uuid::isValid($value)) {
                $arrayUsers[] = $value;
            }
        }
    }

    if (count($arrayRoles) === 0 && count($arrayUsers) === 0) {
        $ecardSendResult = false;
    } else {
        $ecardSendResult = true;
    }

    // object to handle the current message in the database
    $message = new Message($gDb);
    $message->setValue('msg_type', Message::MESSAGE_TYPE_EMAIL);
    $message->setValue('msg_subject', $gL10n->get('SYS_GREETING_CARD') . ': ' . $gL10n->get('SYS_NEW_MESSAGE_RECEIVED'));
    $message->setValue('msg_usr_id_sender', $gCurrentUserId);

    // set condition if email should only send to the email address of the user field
    // with the internal name 'EMAIL'
    if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
        $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
    }

    if (count($arrayRoles) > 0) {
        // read all names and the corresponding emails and add them to the send list
        $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email, rol_name
              FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
        INNER JOIN ' . TBL_USERS . '
                ON usr_id = mem_usr_id
        INNER JOIN ' . TBL_USER_DATA . ' AS email
                ON email.usd_usr_id = usr_id
               AND LENGTH(email.usd_value) > 0
        INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                ON field.usf_id = email.usd_usf_id
               AND field.usf_type = \'EMAIL\'
                   ' . $sqlEmailField . '
        INNER JOIN ' . TBL_USER_DATA . ' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN ' . TBL_USER_DATA . ' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE rol_uuid    IN (' . Database::getQmForValues($arrayRoles) . ')
               AND cat_org_id  = ? -- $gCurrentOrgId
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end     > ? -- DATE_NOW
               AND usr_valid   = true
          ORDER BY last_name, first_name';
        $queryParams = array_merge(
            array(
                $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
            ),
            $arrayRoles,
            array(
                $gCurrentOrgId,
                DATE_NOW,
                DATE_NOW
            )
        );
        $usersStatement = $gDb->queryPrepared($sql, $queryParams);

        while ($row = $usersStatement->fetch()) {
            if ($ecardSendResult) {
                // create and send ecard
                $ecardHtmlData = $funcClass->parseEcardTemplate($imageUrl, $postMessage, $ecardDataToParse, $row['first_name'] . ' ' . $row['last_name'], $row['email']);
                $ecardSendResult = $funcClass->sendEcard($senderName, $senderEmail, $ecardHtmlData, $row['first_name'], $row['last_name'], $row['email'], $imageServerPath);
            }
        }

        // add roles to message object
        foreach ($arrayRoles as $roleUUID) {
            $message->addRoleUUID($roleUUID, 0);
        }
    }

    if (count($arrayUsers) > 0) {
        $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email
              FROM ' . TBL_USERS . '
        INNER JOIN ' . TBL_USER_DATA . ' AS email
                ON email.usd_usr_id = usr_id
               AND LENGTH(email.usd_value) > 0
        INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                ON field.usf_id = email.usd_usf_id
               AND field.usf_type = \'EMAIL\'
                   ' . $sqlEmailField . '
        INNER JOIN ' . TBL_USER_DATA . ' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN ' . TBL_USER_DATA . ' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE usr_uuid  IN (' . Database::getQmForValues($arrayUsers) . ')
               AND usr_valid = true
          ORDER BY last_name, first_name';
        $queryParams = array_merge(
            array(
                $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
            ),
            $arrayUsers
        );
        $usersStatement = $gDb->queryPrepared($sql, $queryParams);

        while ($row = $usersStatement->fetch()) {
            if ($ecardSendResult) {
                // create and send ecard
                $ecardHtmlData = $funcClass->parseEcardTemplate($imageUrl, $postMessage, $ecardDataToParse, $row['first_name'] . ' ' . $row['last_name'], $row['email']);
                $ecardSendResult = $funcClass->sendEcard($senderName, $senderEmail, $ecardHtmlData, $row['first_name'], $row['last_name'], $row['email'], $imageServerPath);
            }
        }

        // add roles to message object
        foreach ($arrayUsers as $userUUID) {
            $message->addUserByUUID($userUUID);
        }
    }

    // show result
    if ($ecardSendResult) {
        $message->addContent($ecardHtmlData);
        $message->save();

        echo json_encode(array(
            'status' => 'success',
            'message' => $gL10n->get('SYS_ECARD_SUCCESSFULLY_SEND'),
            'url' => $gNavigation->getPreviousUrl()
        ));
        exit();
    } else {
        throw new Exception('SYS_ECARD_NOT_SUCCESSFULLY_SEND');
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
