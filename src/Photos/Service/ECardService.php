<?php

namespace Admidio\Photos\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Messages\Entity\Message;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\ValueObject\ECard;
use Admidio\Roles\Entity\Role;

/**
 * Service for sending e-cards independent of the web form.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ECardService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Send an e-card to users and active members of roles and save the message history.
     *
     * @param array<int,string> $roleUuids
     * @param array<int,string> $userUuids
     * @return Message Saved message history entity.
     * @throws Exception
     */
    public function send(
        string $albumUuid,
        int $photoNumber,
        string $templateName,
        string $ecardMessage,
        array $roleUuids = array(),
        array $userUuids = array()
    ): Message {
        global $gCurrentOrgId, $gCurrentUser, $gCurrentUserId, $gL10n, $gProfileFields,
               $gSettingsManager, $gValidLogin;

        if (!$gSettingsManager->getBool('photo_ecard_enabled')
            || (int)$gSettingsManager->get('photo_module_enabled') === 0) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        StringUtils::strIsValidFileName($templateName, false);

        $photoAlbum = new Album($this->db);
        $photoAlbum->readDataByUuid($albumUuid);

        if ($photoAlbum->isNewRecord() || !$photoAlbum->isVisible()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if ($photoNumber < 1 || $photoNumber > (int)$photoAlbum->getValue('pho_quantity')) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if ($gValidLogin && $gCurrentUser->getValue('EMAIL') === '') {
            throw new Exception(
                'SYS_CURRENT_USER_NO_EMAIL',
                array('<a href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php">', '</a>')
            );
        }

        $ecard = new ECard($gL10n);
        $ecardTemplate = $ecard->getEcardTemplate($templateName);
        if ($ecardTemplate === null) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        $validRoleUuids = array();
        foreach (array_unique($roleUuids) as $roleUuid) {
            $role = new Role($this->db);
            if (!$role->readDataByUuid($roleUuid)) {
                continue;
            }

            if ($gCurrentUser->hasRightSendMailToRole((int)$role->getValue('rol_id'))) {
                $validRoleUuids[] = $roleUuid;
            }
        }

        $validUserUuids = array_values(array_unique(array_filter($userUuids)));

        if (count($validRoleUuids) === 0 && count($validUserUuids) === 0) {
            throw new Exception('SYS_ECARD_NOT_SUCCESSFULLY_SEND');
        }

        $imageUrl = SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php',
            array(
                'photo_uuid' => $albumUuid,
                'photo_nr' => $photoNumber,
                'max_width' => $gSettingsManager->getInt('photo_ecard_scale'),
                'max_height' => $gSettingsManager->getInt('photo_ecard_scale')
            )
        );
        $imageServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'
            . $photoAlbum->getValue('pho_begin', 'Y-m-d')
            . '_' . $photoAlbum->getValue('pho_id')
            . '/' . $photoNumber . '.jpg';

        if (!is_file($imageServerPath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $senderName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
        $senderEmail = $gCurrentUser->getValue('EMAIL');

        $message = new Message($this->db);
        $message->setValue('msg_type', Message::MESSAGE_TYPE_EMAIL);
        $message->setValue(
            'msg_subject',
            $gL10n->get('SYS_GREETING_CARD') . ': ' . $gL10n->get('SYS_NEW_MESSAGE_RECEIVED')
        );
        $message->setValue('msg_usr_id_sender', $gCurrentUserId);

        $sqlEmailField = '';
        if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
            $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
        }

        $sendResult = true;
        $ecardHtmlData = '';

        if (count($validRoleUuids) > 0) {
            $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name,
                            email.usd_value AS email, rol_name
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
                       AND last_name.usd_usf_id = ?
                INNER JOIN ' . TBL_USER_DATA . ' AS first_name
                        ON first_name.usd_usr_id = usr_id
                       AND first_name.usd_usf_id = ?
                     WHERE rol_uuid IN (' . Database::getQmForValues($validRoleUuids) . ')
                       AND cat_org_id = ?
                       AND mem_begin <= ?
                       AND mem_end > ?
                       AND usr_valid = true
                  ORDER BY last_name, first_name';
            $queryParams = array_merge(
                array(
                    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                    $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
                ),
                $validRoleUuids,
                array($gCurrentOrgId, DATE_NOW, DATE_NOW)
            );
            $usersStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $usersStatement->fetch()) {
                if ($sendResult !== true) {
                    break;
                }

                $ecardHtmlData = $ecard->parseEcardTemplate(
                    $imageUrl,
                    $ecardMessage,
                    $ecardTemplate,
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email']
                );
                $sendResult = $ecard->sendEcard(
                    $senderName,
                    $senderEmail,
                    $ecardHtmlData,
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $imageServerPath
                );
            }

            foreach ($validRoleUuids as $roleUuid) {
                $message->addRoleUUID($roleUuid, 0);
            }
        }

        if ($sendResult === true && count($validUserUuids) > 0) {
            $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name,
                            email.usd_value AS email
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
                       AND last_name.usd_usf_id = ?
                INNER JOIN ' . TBL_USER_DATA . ' AS first_name
                        ON first_name.usd_usr_id = usr_id
                       AND first_name.usd_usf_id = ?
                     WHERE usr_uuid IN (' . Database::getQmForValues($validUserUuids) . ')
                       AND usr_valid = true
                  ORDER BY last_name, first_name';
            $queryParams = array_merge(
                array(
                    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                    $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
                ),
                $validUserUuids
            );
            $usersStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $usersStatement->fetch()) {
                if ($sendResult !== true) {
                    break;
                }

                $ecardHtmlData = $ecard->parseEcardTemplate(
                    $imageUrl,
                    $ecardMessage,
                    $ecardTemplate,
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email']
                );
                $sendResult = $ecard->sendEcard(
                    $senderName,
                    $senderEmail,
                    $ecardHtmlData,
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $imageServerPath
                );
            }

            foreach ($validUserUuids as $userUuid) {
                $message->addUserByUUID($userUuid);
            }
        }

        if ($sendResult !== true || $ecardHtmlData === '') {
            throw new Exception('SYS_ECARD_NOT_SUCCESSFULLY_SEND');
        }

        $message->addContent($ecardHtmlData);
        $message->save();

        return $message;
    }
}
