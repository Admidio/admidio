<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
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
        global $gDb, $gProfileFields, $gCurrentOrgId;

        // Select new Members of the group
        $sql = 'SELECT usr_id as userID, usr_uuid as userUUID, usr_login_name as loginName, reg_timestamp as registrationTimestamp, last_name.usd_value AS lastName,
                       first_name.usd_value AS firstName, email.usd_value AS email, reg_validation_id as validationID
                  FROM '.TBL_REGISTRATIONS.'
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = reg_usr_id
             LEFT JOIN '.TBL_USER_DATA.' AS last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
             LEFT JOIN '.TBL_USER_DATA.' AS first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             LEFT JOIN '.TBL_USER_DATA.' AS email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
                 WHERE usr_valid = false
                   AND reg_org_id = ? -- $gCurrentOrgId
              ORDER BY lastName, firstName';
        $queryParameters = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $gProfileFields->getProperty('EMAIL', 'usf_id'),
            $gCurrentOrgId
        );
        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws SmartyException
     */
    public function createContent()
    {
        global $gL10n, $gSettingsManager, $gMessage, $gHomepage;

        $registrations = $this->getRegistrationsArray();
        $templateData = array();

        if (count($registrations) === 0) {
            $gMessage->setForwardUrl($gHomepage);
            $gMessage->show($gL10n->get('SYS_NO_NEW_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
            // => EXIT
        }

        foreach($registrations as $row) {
            $templateRow = array();
            $templateRow['id'] = 'row_user_'.$row['userUUID'];
            $templateRow['title'] = $row['firstName'] . ' ' . $row['lastName'];

            $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['registrationTimestamp']);
            $templateRow['information'][] = $gL10n->get('SYS_REGISTRATION_AT', array($timestampCreate->format($gSettingsManager->getString('system_date')), $timestampCreate->format($gSettingsManager->getString('system_time'))));
            $templateRow['information'][] = $gL10n->get('SYS_USERNAME') . ': ' . $row['loginName'];
            $templateRow['information'][] = $gL10n->get('SYS_EMAIL') . ': <a href="mailto:'.$row['email'].'">'.$row['email'].'</a>';

            if ((string) $row['validationID'] === '') {
                $templateRow['information'][] = '<div class="alert alert-success"><i class="fas fa-check-circle"></i>' . $gL10n->get('SYS_REGISTRATION_CONFIRMED') . '</div>';
            } else {
                $templateRow['information'][] = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>' . $gL10n->get('SYS_REGISTRATION_NOT_CONFIRMED') . '</div>';
            }

            $templateRow['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['userUUID'])),
                'icon' => 'fas fa-eye',
                'tooltip' => $gL10n->get('SYS_SHOW_PROFILE')
            );
            $templateRow['actions'][] = array(
                'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_SYSTEM.'/popup_message.php', array('type' => 'nwu', 'element_id' => 'row_user_'.$row['userUUID'], 'name' => $row['firstName'].' '.$row['lastName'], 'database_id' => $row['userUUID'])),
                'icon' => 'fas fa-trash-alt',
                'tooltip' => $gL10n->get('SYS_DELETE')
            );
            $templateRow['buttons'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_assign.php', array('new_user_uuid' => $row['userUUID'])),
                'name' => $gL10n->get('SYS_ASSIGN_REGISTRATION')
            );

            $templateData[] = $templateRow;
        }

        $this->assign('cards', $templateData);
        $this->pageContent = $this->fetch('modules/registration.list.tpl');
    }
}
