<?php
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

/**
 * @brief Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the contacts module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleContacts('admidio-contacts', $headline);
 * $page->createContentAssignUser();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ModuleContacts extends PagePresenter
{
    /**
     * Constructor that initialize the class member parameters
     * @throws Exception
     */
    public function __construct(string $id, string $headline = '')
    {
        $this->setHeadline($headline);
        parent::__construct($id);
    }

    /**
     * Search for similar users of the new user and show all found users with the option to assign
     * the current user or registration to the existing user or to create a new contact. If the new user or
     * registration is assigned to an existing user than there will be a different handling if the user is member
     * of the current organization and if the user has already a login.
     * @param User $user User object of the user who should be assigned.
     * @param bool $assignRegistration Flag if the user will be assigned through the registration process.
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function createContentAssignUser(User $user, bool $assignRegistration = false)
    {
        global $gL10n, $gSettingsManager, $gCurrentUser, $gDb, $gProfileFields, $gCurrentOrganization;

        $templateData = array();
        $userUuid = $user->getValue('usr_uuid');

        $similarUserIDs = $user->searchSimilarUsers();

        if (count($similarUserIDs) === 0) {
            throw new Exception('No similar users found.');
        }


        if($assignRegistration) {
            $this->smarty->assign('description', $gL10n->get('SYS_SIMILAR_CONTACTS_FOUND_REGISTRATION', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $user->getValue('EMAIL'))));

            // if current user can edit profiles than create link to profile otherwise create link to auto assign new registration
            if ($gCurrentUser->isAdministratorUsers()) {
                $this->smarty->assign('createNewUserUrl',
                    SecurityUtils::encodeUrl(
                        ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php',
                        array(
                            'accept_registration' => true,
                            'user_uuid' => $userUuid
                        )
                    )
                );
            } else {
                $this->smarty->assign('createNewUserUrl',
                    SecurityUtils::encodeUrl(
                        ADMIDIO_URL . FOLDER_MODULES . '/registration.php',
                        array(
                            'mode' => 'create_user',
                            'user_uuid' => $userUuid
                        )
                    )
                );
            }
        } else {
            $this->smarty->assign('description', $gL10n->get('SYS_SIMILAR_CONTACTS_FOUND_ASSIGN', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'))));
            $this->smarty->assign('createNewUserUrl',
                SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php',
                    array(
                        'lastname' => $user->getValue('LAST_NAME'),
                        'firstname' => $user->getValue('FIRST_NAME')
                    )
                )
            );
        }

        foreach ($similarUserIDs as $similarUserID) {
            $similarUser = new User($gDb, $gProfileFields, $similarUserID);

            $button = array();
            $templateRow = array();
            $templateRow['data'] = $similarUser->getProfileFieldsData();
            $templateRow['profileUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $similarUser->getValue('usr_uuid')));

            if ($gSettingsManager->getBool('enable_mail_module')) {
                $templateRow['emailUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('user_uuid' => $similarUser->getValue('usr_uuid')));
            } else {
                $templateRow['emailUrl'] = 'mailto:'.$similarUser->getValue('EMAIL');
            }

            if ($similarUser->isMemberOfOrganization()) {
                // show links to assign a new registration.
                // If not in registration mode than nothing is to do if the user already exists.
                if($assignRegistration) {
                    // found user is member of this organization
                    if ($similarUser->getValue('usr_login_name') !== '') {
                        // Login data already exists -> Send login data again
                        $button['description'] = $gL10n->get('SYS_USER_VALID_LOGIN') . '<br />' . $gL10n->get('SYS_REMINDER_SEND_LOGIN');
                        $button['label'] = $gL10n->get('SYS_SEND_LOGIN_INFORMATION');
                        $button['icon'] = 'bi-key-fill';
                        $button['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration.php', array('user_uuid' => $userUuid, 'user_uuid_assigned' => $similarUser->getValue('usr_uuid'), 'mode' => 'send_login'));
                    } else {
                        // Login data are NOT available -> assign them now
                        $button['description'] = $gL10n->get('SYS_CONTACT_NO_VALID_LOGIN');
                        $button['label'] = $gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION');
                        $button['icon'] = 'bi-person-check-fill';
                        $button['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration.php', array('user_uuid' => $userUuid, 'user_uuid_assigned' => $similarUser->getValue('usr_uuid'), 'mode' => 'assign_member'));
                    }
                }
            } else {
                // found user is NOT a member of this organization yet
                $button['label'] = $gL10n->get('SYS_ASSIGN_MEMBERSHIP');
                $button['icon'] = 'bi-person-check-fill';

                if($assignRegistration) {
                    $button['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration.php', array('user_uuid' => $userUuid, 'user_uuid_assigned' => $similarUser->getValue('usr_uuid'), 'mode' => 'assign_user'));

                    if ($similarUser->getValue('usr_login_name') !== '') {
                        // Login data are already available
                        $button['description'] = $gL10n->get('SYS_USER_NO_MEMBERSHIP_LOGIN', array($gCurrentOrganization->getValue('org_longname')));
                    } else {
                        // NO login data available
                        $button['description'] = $gL10n->get('SYS_USER_NO_MEMBERSHIP_NO_LOGIN', array($gCurrentOrganization->getValue('org_longname')));
                    }
                } else {
                    $button['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php', array('user_uuid' => $userUuid, 'new_user' => true));
                    $button['description'] = $gL10n->get('SYS_USER_NO_MEMBERSHIP', array($gCurrentOrganization->getValue('org_shortname')));
                }
            }

            if (count($button) > 0) {
                $templateRow['button'] = $button;
            }

            $templateData[] = $templateRow;
        }

        $this->smarty->assign('similarUsers', $templateData);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/contacts.assign.tpl');
    }
}
