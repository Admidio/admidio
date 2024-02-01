<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

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
     * @param HtmlPage $page Html content will be added to this page.
     * @param string $organizationShortName Optional the organization that should be preselected in the dialog.
     * @throws AdmException|SmartyException
     * @throws Exception
     */
    public function addHtmlLogin(HtmlPage $page, string $organizationShortName = '')
    {
        global $gDb, $gSettingsManager, $gL10n, $gCurrentOrganization;

        if ($organizationShortName === '') {
            $organizationShortName = $gCurrentOrganization->getValue('org_shortname');
        }

        // read id of administrator role
        $sql = 'SELECT MIN(rol_id) as rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_administrator = true
                   AND (  cat_org_id = (SELECT org_id
                                          FROM '.TBL_ORGANIZATIONS.'
                                         WHERE org_shortname = ? /* $gCurrentOrgId */)
                       OR cat_org_id IS NULL )';
        $pdoStatement = $gDb->queryPrepared($sql, array($organizationShortName));

        // create role object for administrator
        $roleAdministrator = new TableRoles($gDb, (int) $pdoStatement->fetchColumn());

        // show form
        $form = new HtmlForm(
            'login_form',
            ADMIDIO_URL.'/adm_program/system/login.php?mode=check',
            $page,
            array('showRequiredFields' => false)
        );

        $form->addInput(
            'usr_login_name',
            $gL10n->get('SYS_USERNAME'),
            '',
            array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
        );
        $form->addInput(
            'usr_password',
            $gL10n->get('SYS_PASSWORD'),
            '',
            array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
        );

        // show selectbox with all organizations of database
        if ($gSettingsManager->getBool('system_organization_select')) {
            $sql = 'SELECT org_shortname, org_longname
                      FROM '.TBL_ORGANIZATIONS.'
                  ORDER BY org_longname ASC, org_shortname ASC';
            $form->addSelectBoxFromSql(
                'org_shortname',
                $gL10n->get('SYS_ORGANIZATION'),
                $gDb,
                $sql,
                array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $organizationShortName, 'class' => 'form-control-small')
            );
        }

        if ($gSettingsManager->getBool('enable_auto_login')) {
            $form->addCheckbox('auto_login', $gL10n->get('SYS_REMEMBER_ME'));
        }
        $form->addSubmitButton('btn_login', $gL10n->get('SYS_LOGIN'), array('icon' => 'fa-key', 'class' => ' offset-sm-3'));
        $page->addHtml($form->show());

        if ($gSettingsManager->getBool('registration_enable_module')) {
            $page->addHtml('
                <div id="login_registration_link">
                    <small>
                        <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php">'.$gL10n->get('SYS_WANT_REGISTER').'</a>
                    </small>
                </div>');
        }

        // show link if user has login problems
        if ($gSettingsManager->getBool('enable_password_recovery') && $gSettingsManager->getBool('system_notifications_enabled')) {
            // request to reset the password
            $forgotPasswordLink = ADMIDIO_URL.FOLDER_SYSTEM.'/password_reset.php';
        } elseif ($gSettingsManager->getBool('enable_mail_module') && $roleAdministrator->getValue('rol_mail_this_role') == 3) {
            // show link of message module to send mail to administrator role
            $forgotPasswordLink = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('role_uuid' => $roleAdministrator->getValue('rol_uuid'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        } else {
            // show link to send mail with local mail-client to administrator
            $forgotPasswordLink = SecurityUtils::encodeUrl('mailto:'.$gSettingsManager->getString('email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        }

        $page->addHtml('
            <div id="login_forgot_password_link" class="admidio-margin-bottom">
                <small><a href="'.$forgotPasswordLink.'">'.$gL10n->get('SYS_FORGOT_MY_PASSWORD').'</a></small>
            </div>');
    }

    /**
     * Check if a user with that username exists and the password is set correct. If the user choose a different
     * organization than the session data will be updated.
     * @return bool Returns **true** if the login data are valid
     * @throws AdmException
     * @throws Exception
     */
    public function checkLogin(): bool
    {
        global $gDb, $gCurrentOrganization, $gCurrentOrgId, $gProfileFields, $gCurrentSession, $gSettingsManager;
        global $gMenu, $gCurrentUser, $gCurrentUserId, $gCurrentUserUUID, $gL10n;
        
        $postLoginName = admFuncVariableIsValid($_POST, (isset($_POST['usr_login_name']) ? 'usr_login_name' : 'plg_usr_login_name'), 'string');
        $postPassword = (isset($_POST['usr_password']) ? $_POST['usr_password'] : $_POST['plg_usr_password']);
        $postOrgShortName = admFuncVariableIsValid($_POST, (isset($_POST['org_shortname']) ? 'org_shortname' : 'plg_org_shortname'), 'string');
        $postAutoLogin = admFuncVariableIsValid($_POST, (isset($_POST['auto_login']) ? 'auto_login' : 'plg_auto_login'), 'bool');

        if ($postLoginName === '') {
            throw new AdmException('SYS_FIELD_EMPTY', array($gL10n->get('SYS_USERNAME')));
            // => EXIT
        }

        if ($postPassword === '') {
            throw new AdmException('SYS_FIELD_EMPTY', array($gL10n->get('SYS_PASSWORD')));
            // => EXIT
        }

        // Search for username
        $sql = 'SELECT usr_id
              FROM ' . TBL_USERS . '
             WHERE UPPER(usr_login_name) = UPPER(?)';
        $userStatement = $gDb->queryPrepared($sql, array($postLoginName));

        if ($userStatement->rowCount() === 0) {
            throw new AdmException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
            // => EXIT
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

        return $gCurrentUser->checkLogin($postPassword, $postAutoLogin);
    }
}
