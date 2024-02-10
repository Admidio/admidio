<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createContentRegistrationList();
 * $page->show();
 * ```
 */
class ModuleRegistration extends HtmlPage
{
    /**
     * Constructor that initialize the class member parameters
     * @throws Exception
     */
    public function __construct(string $id, string $headline = '')
    {
        parent::__construct($id, $headline);
    }

    /**
     * Creates an array with all available registrations. The array contains the following entries:
     * array(userID, userUUID, loginName, registrationTimestamp, lastName, firstName, email, validationID)
     * @return array Returns an array with information about every available registration
     * @throws Exception
     */
    public function getRegistrationsArray(): array
    {
        global $gDb, $gCurrentOrgId;

        // Select new Members of the group
        $sql = 'SELECT usr_id, usr_uuid, usr_login_name, reg_timestamp, reg_validation_id
                  FROM '.TBL_REGISTRATIONS.'
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = reg_usr_id
                 WHERE usr_valid = false
                   AND reg_org_id = ? -- $gCurrentOrgId
              ORDER BY reg_validation_id DESC, reg_timestamp DESC';
        $queryParameters = array($gCurrentOrgId);
        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws SmartyException|AdmException
     * @throws Exception
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
            $user = new UserRegistration($gDb, $gProfileFields, $row['usr_id']);
            $similarUserIDs = $user->searchSimilarUsers();

            $templateRow = array();
            $templateRow['id'] = 'user_'.$row['usr_uuid'];
            $templateRow['title'] = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');

            $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['reg_timestamp']);
            $templateRow['information'][] = $gL10n->get('SYS_REGISTRATION_AT', array($timestampCreate->format($gSettingsManager->getString('system_date')), $timestampCreate->format($gSettingsManager->getString('system_time'))));
            $templateRow['information'][] = $gL10n->get('SYS_USERNAME') . ': ' . $row['usr_login_name'];
            $templateRow['information'][] = $gL10n->get('SYS_EMAIL') . ': <a href="mailto:'.$user->getValue('EMAIL').'">'.$user->getValue('EMAIL').'</a>';

            if ((string) $row['reg_validation_id'] === '') {
                $templateRow['information'][] = '<div class="alert alert-success"><i class="fas fa-check-circle"></i>' . $gL10n->get('SYS_REGISTRATION_CONFIRMED') . '</div>';
            } else {
                $templateRow['information'][] = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>' . $gL10n->get('SYS_REGISTRATION_NOT_CONFIRMED') . '</div>';
            }

            if (count($similarUserIDs) > 0) {
                $templateRow['information'][] = '<div class="alert alert-info"><i class="fas fa-info-circle"></i>' . (count($similarUserIDs) === 1 ? $gL10n->get('SYS_CONTACT_SIMILAR_NAME') : $gL10n->get('SYS_MEMBERS_SIMILAR_NAME') ) . '</div>';
            }

            $templateRow['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['usr_uuid'])),
                'icon' => 'fas fa-eye',
                'tooltip' => $gL10n->get('SYS_SHOW_PROFILE')
            );
            $templateRow['actions'][] = array(
                'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_SYSTEM.'/popup_message.php', array('type' => 'nwu', 'element_id' => 'user_'.$row['usr_uuid'], 'name' => $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), 'database_id' => $row['usr_uuid'])),
                'icon' => 'fas fa-trash-alt',
                'tooltip' => $gL10n->get('SYS_DELETE')
            );
            if (count($similarUserIDs) > 0) {
                $templateRow['buttons'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php', array('mode' => 'show_similar', 'user_uuid' => $row['usr_uuid'])),
                    'name' => $gL10n->get('SYS_ASSIGN_REGISTRATION')
                );
            } else {
                $templateRow['buttons'][] = array(
                    'url' => ($gCurrentUser->editUsers() ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '3', 'user_uuid' => $row['usr_uuid'])) : SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/registration/registration_function.php', array('mode' => 'create_user', 'new_user_uuid' => $row['usr_uuid']))),
                    'name' => $gL10n->get('SYS_CONFIRM_REGISTRATION')
                );
            }

            $templateData[] = $templateRow;
        }

        $this->assign('cards', $templateData);
        $this->assign('l10n', $gL10n);
        $this->pageContent .= $this->fetch('modules/registration.cards.tpl');
    }
}
