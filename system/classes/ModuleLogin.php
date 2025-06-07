<?php
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

/**
 * Class with methods to display the login module and handle the input.
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
class ModuleLogin
{
    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct()
    {
    }

    /**
     * Create the html content of the login page and add it to the HtmlPage object. Beside the username and password an
     * organization select box could be shown and the flag if auto login should be activated.
     * @param PagePresenter $page Html content will be added to this page.
     * @param string $organizationShortName Optional the organization that should be preselected in the dialog.
     * @throws Exception
     */
    public function addHtmlLogin(PagePresenter $page, string $organizationShortName = '')
    {
        global $gDb, $gSettingsManager, $gL10n, $gCurrentOrganization, $gCurrentSession;

        if ($organizationShortName === '') {
            $organizationShortName = $gCurrentOrganization->getValue('org_shortname');
        }

        // read id of administrator role
        $sql = 'SELECT MIN(rol_id) as rol_id
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_administrator = true
                   AND (  cat_org_id = (SELECT org_id
                                          FROM ' . TBL_ORGANIZATIONS . '
                                         WHERE org_shortname = ? /* $gCurrentOrgId */)
                       OR cat_org_id IS NULL )';
        $pdoStatement = $gDb->queryPrepared($sql, array($organizationShortName));

        // create role object for administrator
        $roleAdministrator = new Role($gDb, (int) $pdoStatement->fetchColumn());

        // show link if user has login problems
        if ($gSettingsManager->getBool('enable_password_recovery') && $gSettingsManager->getBool('system_notifications_enabled')) {
            // request to reset the password
            $forgotPasswordLink = ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php';
        } elseif ($gSettingsManager->getInt('mail_module_enabled') === 1 && $roleAdministrator->getValue('rol_mail_this_role') == 3) {
            // show link of message module to send mail to administrator role
            $forgotPasswordLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('role_uuid' => $roleAdministrator->getValue('rol_uuid'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        } else {
            // show link to send mail with local mail-client to administrator
            $forgotPasswordLink = SecurityUtils::encodeUrl('mailto:' . $gCurrentOrganization->getValue('org_email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        }

        // show form
        $form = new FormPresenter(
            'adm_login_form',
            'system/login.tpl',
            ADMIDIO_URL . FOLDER_SYSTEM . '/login.php?mode=check',
            $page,
            array('showRequiredFields' => false)
        );

        $form->addInput(
            'usr_login_name',
            $gL10n->get('SYS_USERNAME'),
            '',
            array('maxLength' => 254, 'property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addInput(
            'usr_password',
            $gL10n->get('SYS_PASSWORD'),
            '',
            array(
                'type' => 'password',
                'property' => FormPresenter::FIELD_REQUIRED,
                'helpTextId' => '<a href="' . $forgotPasswordLink . '">' . $gL10n->get('SYS_PASSWORD_FORGOTTEN') . '</a>'
            )
        );
        $form->addInput(
            'usr_totp_code',
            $gL10n->get('SYS_SECURITY_CODE'),
            '',
            array('maxLength' => 6)
        );

        // show selectbox with all organizations of database
        $sql = 'SELECT org_shortname, org_longname
                  FROM ' . TBL_ORGANIZATIONS . '
              ORDER BY org_longname, org_shortname';
        $form->addSelectBoxFromSql(
            'org_shortname',
            $gL10n->get('SYS_ORGANIZATION'),
            $gDb,
            $sql,
            array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $organizationShortName)
        );

        $form->addCheckbox('auto_login', $gL10n->get('SYS_REMEMBER_ME'));
        $form->addSubmitButton('adm_button_login', $gL10n->get('SYS_LOGIN'), array('icon' => 'bi-box-arrow-in-right', 'class' => 'offset-sm-3'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Check if a user with that username exists and the password is set correct. If the user choose a different
     * organization than the session data will be updated.
     * @return bool Returns **true** if the login data are valid
     * @throws Exception
     */
    public function checkLogin(): bool
    {
        global $gDb, $gCurrentOrganization, $gCurrentOrgId, $gProfileFields, $gCurrentSession, $gSettingsManager;
        global $gMenu, $gCurrentUser, $gCurrentUserId, $gCurrentUserUUID, $gLogger;

        // check form field input and sanitized it from malicious content
        $loginForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $loginForm->validate($_POST);

        $postLoginName = ($formValues['usr_login_name'] ?? $formValues['plg_usr_login_name']);
        $postPassword = ($formValues['usr_password'] ?? $formValues['plg_usr_password']);
        $postTotpCode = ($formValues['usr_totp_code'] ?? $formValues['plg_usr_totp_code'] ?? null);
        $postOrgShortName = ($formValues['org_shortname'] ?? ($formValues['plg_org_shortname'] ?? $gCurrentOrganization->getValue('org_shortname')));
        $postAutoLogin = ($formValues['auto_login'] ?? $formValues['plg_auto_login']);

        // Search for username
        $sql = 'SELECT usr_id
              FROM ' . TBL_USERS . '
             WHERE UPPER(usr_login_name) = UPPER(?)';
        $userStatement = $gDb->queryPrepared($sql, array($postLoginName));

        // Alternatively, allow email addresses instead of username (if configured).
        if (
            $gSettingsManager->getBool('security_login_email_address_enabled') &&
            $userStatement->rowCount() === 0
        ) {
            $sql = 'SELECT usd_usr_id
                   FROM ' . TBL_USER_DATA . '
                   WHERE usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
                     AND UPPER(usd_value) = UPPER(?)';
            $userStatement = $gDb->queryPrepared($sql, array($gProfileFields->getProperty('EMAIL', 'usf_id'), $postLoginName));

            if ($userStatement->rowCount() > 1) {
                $gLogger->warning('AUTHENTICATION: Multiple Accounts with the given E-Mail address found!', array(
                    'username' => $postLoginName,
                    'password' => '*****'
                ));
            }
        }

        if ($userStatement->rowCount() === 0) {
            throw new Exception('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
        }

        // if login organization is different to organization of config file then create new session variables
        if ($postOrgShortName !== '' && $postOrgShortName !== $gCurrentOrganization->getValue('org_shortname')) {
            // read organization of config file with their preferences
            $gCurrentOrganization->readDataByColumns(array('org_shortname' => $postOrgShortName));
            $gCurrentOrgId = $gCurrentOrganization->getValue('org_id');

            // read new profile field structure for this organization
            $gProfileFields->readProfileFields($gCurrentOrgId);

            // save new organization id to session
            $gCurrentSession->setValue('ses_org_id', $gCurrentOrgId);
            $gCurrentSession->save();

            // read all settings from the new organization
            $gSettingsManager = new SettingsManager($gDb, $gCurrentOrgId);
        }

        // remove all menu entries
        $gMenu->initialize();

        // create user object
        $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());
        $gCurrentUserId = $gCurrentUser->getValue('usr_id');
        $gCurrentUserUUID = $gCurrentUser->getValue('usr_uuid');

        return $gCurrentUser->checkLogin(password: $postPassword, totpCode: $postTotpCode, setAutoLogin: $postAutoLogin);
    }
}
