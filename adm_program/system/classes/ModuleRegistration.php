<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Some functions for the registration module
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // check the given Array for charecter and split it.
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message and set a link to a page that should be shown after user click ok
 * $gMessage->setForwardUrl('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message with yes and no button and set a link to a page that should be shown after user click yes
 * $gMessage->setForwardYesNo('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 * ```
 */
class ModuleRegistration extends Module
{
    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct()
    {
    }

    /**
     * Creates an array with all available registrations. The array contains the following entries:
     * array(userID, userUUID, loginName, registrationTimestamp, lastName, firstName, email)
     * @return array Returns an array with information about every available registration
     */
    public function getRegistrationsArray(): array
    {
        global $gDb, $gProfileFields, $gCurrentOrgId;

        // Select new Members of the group
        $sql = 'SELECT usr_id as userID, usr_uuid as userUUID, usr_login_name as loginName, reg_timestamp as registrationTimestamp, last_name.usd_value AS lastName,
                       first_name.usd_value AS firstName, email.usd_value AS email
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
}
