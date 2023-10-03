<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Needful functions for the registration module
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createContent();
 * $page->show();
 * ```
 */
class ModuleRegistration extends HtmlPage
{
    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct(string $id, string $headline = '')
    {
        parent::__construct($id, $headline);
    }

    /**
     * Creates an array with all available registrations. The array contains the following entries:
     * array(userID, userUUID, loginName, registrationTimestamp, lastName, firstName, email, validationID)
     * @return array Returns an array with information about every available registration
     */
    public function getRegistrationsArray(): array
    {
        global $gDb, $gCurrentOrgId;

        // Select new Members of the group
        $sql = 'SELECT usr_id as userID, usr_uuid as userUUID, usr_login_name as loginName,
                       reg_timestamp as registrationTimestamp, reg_validation_id as validationID
                  FROM '.TBL_REGISTRATIONS.'
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = reg_usr_id
                 WHERE usr_valid = false
                   AND reg_org_id = ? -- $gCurrentOrgId
              ORDER BY reg_validation_id ASC, reg_timestamp DESC';
        $queryParameters = array($gCurrentOrgId);
        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * Search for similar users of the new registration and show all found users with the option to assign
     * the current registration to the existing user or to create a new member. If the registration is
     * assigned to an existing user than there will be a different handling if the user is member of the current
     * organization and if the user has already a login.
     * @param string $userUuid UUID if the user whose registration should be assigned.
     * @throws SmartyException|AdmException
     */
    public function createContentAssignUser(string $userUuid)
    {
        global $gL10n, $gSettingsManager, $gCurrentUser, $gDb, $gProfileFields, $gCurrentOrganization;

        $templateData = array();

        $user = new UserRegistration($gDb, $gProfileFields);
        $user->readDataByUuid($userUuid);
        $similarUserIDs = $user->searchSimilarUsers();

        $this->assign('description', $gL10n->get('SYS_SIMILAR_CONTACTS_FOUND_REGISTRATION', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'))));

        // if current user can edit profiles than create link to profile otherwise create link to auto assign new registration
        if ($gCurrentUser->editUsers()) {
            $this->assign('createNewUserUrl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '3', 'user_uuid' => $userUuid)));
        } else {
            $this->assign('createNewUserUrl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/registration/registration_function.php', array('mode' => '5', 'new_user_uuid' => $userUuid)));
        }
        foreach ($similarUserIDs as $similarUserID) {
            $similarUser = new User($gDb, $gProfileFields, $similarUserID);

            $templateRow = array();
            $templateRow['data'] = $similarUser->getProfileFieldsData();
            $templateRow['profileUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $similarUser->getValue('usr_uuid')));

            if ($gSettingsManager->getBool('enable_mail_module')) {
                $templateRow['emailUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('user_uuid' => $similarUser->getValue('usr_uuid')));
            } else {
                $templateRow['emailUrl'] = 'mailto:'.$similarUser->getValue('EMAIL');
            }

            if ($similarUser->isMemberOfOrganization()) {
                // found user is member of this organization
                if ($similarUser->getValue('usr_login_name') !== '') {
                    // Login data already exists -> Send login data again
                    $button['description'] = $gL10n->get('SYS_USER_VALID_LOGIN'). '<br />'.$gL10n->get('SYS_REMINDER_SEND_LOGIN');
                    $button['label'] = $gL10n->get('SYS_SEND_LOGIN_INFORMATION');
                    $button['icon'] = 'fa-key';
                    $button['url']  = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('new_user_uuid' => $userUuid, 'user_uuid' => $similarUser->getValue('usr_uuid'), 'mode' => '6'));
                } else {
                    // Login data are NOT available -> assign them now
                    $button['description'] = $gL10n->get('SYS_CONTACT_NO_VALID_LOGIN');
                    $button['label'] = $gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION');
                    $button['icon'] = 'fa-user-check';
                    $button['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration/registration_function.php', array('new_user_uuid' => $userUuid, 'user_uuid' => $similarUser->getValue('usr_uuid'), 'mode' => '1'));
                }
            } else {
                // found user is NOT a member of this organization yet
                $button['label'] = $gL10n->get('SYS_ASSIGN_MEMBERSHIP');
                $button['icon'] = 'fa-user-check';
                $button['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('new_user_uuid' => $userUuid, 'user_uuid' => $similarUser->getValue('usr_uuid'), 'mode' => '2'));

                if ($similarUser->getValue('usr_login_name') !== '') {
                    // Login data are already available
                    $button['description'] = $gL10n->get('SYS_USER_NO_MEMBERSHIP_LOGIN', array($gCurrentOrganization->getValue('org_longname')));
                } else {
                    // NO login data available
                    $button['description'] = $gL10n->get('SYS_USER_NO_MEMBERSHIP_NO_LOGIN', array($gCurrentOrganization->getValue('org_longname')));
                }
            }
            $templateRow['button'] = $button;

            $templateData[] = $templateRow;
        }

        $this->assign('similarUsers', $templateData);
        $this->assign('l10n', $gL10n);
        $this->pageContent = $this->fetch('modules/registration.assign.tpl');
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws SmartyException|AdmException
     */
    public function createContentRegistrationList()
    {
        global $gL10n, $gSettingsManager, $gMessage, $gHomepage, $gDb, $gProfileFields, $gCurrentUser;

        $registrations = $this->getRegistrationsArray();
        $templateData = array();

        if (count($registrations) === 0) {
            $gMessage->setForwardUrl($gHomepage);
            $gMessage->show($gL10n->get('SYS_NO_NEW_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
            // => EXIT
        }

        foreach($registrations as $row) {
            $user = new UserRegistration($gDb, $gProfileFields, $row['userID']);
            $similarUserIDs = $user->searchSimilarUsers();

            $templateRow = array();
            $templateRow['id'] = 'row_user_'.$row['userUUID'];
            $templateRow['title'] = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');

            $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['registrationTimestamp']);
            $templateRow['information'][] = $gL10n->get('SYS_REGISTRATION_AT', array($timestampCreate->format($gSettingsManager->getString('system_date')), $timestampCreate->format($gSettingsManager->getString('system_time'))));
            $templateRow['information'][] = $gL10n->get('SYS_USERNAME') . ': ' . $row['loginName'];
            $templateRow['information'][] = $gL10n->get('SYS_EMAIL') . ': <a href="mailto:'.$user->getValue('EMAIL').'">'.$user->getValue('EMAIL').'</a>';

            if ((string) $row['validationID'] === '') {
                $templateRow['information'][] = '<div class="alert alert-success"><i class="fas fa-check-circle"></i>' . $gL10n->get('SYS_REGISTRATION_CONFIRMED') . '</div>';
            } else {
                $templateRow['information'][] = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>' . $gL10n->get('SYS_REGISTRATION_NOT_CONFIRMED') . '</div>';
            }

            if (count($similarUserIDs) > 0) {
                $templateRow['information'][] = '<div class="alert alert-info"><i class="fas fa-info-circle"></i>' . (count($similarUserIDs) === 1 ? $gL10n->get('SYS_CONTACT_SIMILAR_NAME') : $gL10n->get('SYS_MEMBERS_SIMILAR_NAME') ) . '</div>';
            }

            $templateRow['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['userUUID'])),
                'icon' => 'fas fa-eye',
                'tooltip' => $gL10n->get('SYS_SHOW_PROFILE')
            );
            $templateRow['actions'][] = array(
                'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_SYSTEM.'/popup_message.php', array('type' => 'nwu', 'element_id' => 'row_user_'.$row['userUUID'], 'name' => $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), 'database_id' => $row['userUUID'])),
                'icon' => 'fas fa-trash-alt',
                'tooltip' => $gL10n->get('SYS_DELETE')
            );
            if (count($similarUserIDs) > 0) {
                $templateRow['buttons'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php', array('mode' => 'show_similar', 'user_uuid' => $row['userUUID'])),
                    'name' => $gL10n->get('SYS_ASSIGN_REGISTRATION')
                );
            } else {
                $templateRow['buttons'][] = array(
                    'url' => ($gCurrentUser->editUsers() ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '3', 'user_uuid' => $row['userUUID'])) : SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/registration/registration_function.php', array('mode' => '5', 'new_user_uuid' => $row['userUUID']))),
                    'name' => $gL10n->get('SYS_CONFIRM_REGISTRATION')
                );
            }

            $templateData[] = $templateRow;
        }

        $this->assign('cards', $templateData);
        $this->pageContent = $this->fetch('modules/registration.list.tpl');
    }
}
