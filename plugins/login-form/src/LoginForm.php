<?php

namespace AdmidioPlugin\LoginForm;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\LoginForm\Presenter\LoginFormPreferencesPresenter;
use Exception;

/**
 ***********************************************************************************************
 * Login Form
 *
 * Login Form represents the login form with the appropriate fields for a user to log in.
 * If the user is logged in, useful information of the user is now displayed in the place
 * of the fields.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class LoginForm
{
    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'login-form';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 1;

    /**
     * The settings of the plugin in the request that is being rendered.
     * @var array<string,mixed>
     */
    private static array $pluginConfig = array();

    /**
     * Announce the widget and the preferences panel. This is what plugin.php calls.
     * @return void
     */
    public static function register(): void
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return;
        }

        PluginWidget::register($plugin, array(self::class, 'renderWidget'), array(
            'sequence' => self::DEFAULT_SEQUENCE
        ));

        Hooks::addFilter(
            PluginPanel::HOOK,
            static function (array $panels) use ($plugin): array {
                global $gL10n;

                $panels[] = array(
                    'id' => PluginPanel::normalizeId($plugin->id),
                    'title' => $gL10n->get($plugin->name),
                    'icon' => $plugin->icon,
                    'group' => PluginPanel::GROUP_OVERVIEW,
                    'sequence' => self::DEFAULT_SEQUENCE,
                    'create' => array(LoginFormPreferencesPresenter::class, 'createForm')
                );

                return $panels;
            },
            PluginPanel::DEFAULT_SEQUENCE,
            1,
            $plugin->id
        );
    }

    /**
     * Build the widget of the overview page: the data of the current user when somebody is logged
     * in, and the login form itself when nobody is. Whether it is shown at all was decided before
     * this is called, by the preference **login_form_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin): string
    {
        global $gSettingsManager, $gCurrentUser, $gCurrentSession, $gValidLogin;

        self::$pluginConfig = $plugin->getSettingValues();

        if (!$gValidLogin) {
            $form = self::createLoginForm($page);
            $form->addToSmarty($page->getSmartyTemplate());
            $gCurrentSession->addFormObject($form);

            return $plugin->renderTemplate($page, 'plugin.login-form.edit.tpl', array(
                'name' => $plugin->id,
                'showRegisterLink' => self::$pluginConfig['login_form_show_register_link']
            ));
        }

        $loginData = self::getLoginData();

        return $plugin->renderTemplate($page, 'plugin.login-form.view.tpl', array(
            'name' => $plugin->id,
            'userUUID' => $gCurrentUser->getValue('usr_uuid'),
            'userName' => $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'),
            'loginActiveSince' => $gCurrentSession->getValue('ses_begin', $gSettingsManager->getString('system_time')),
            'lastLogin' => $loginData['lastLogin'],
            'numberOfLogins' => $gCurrentUser->getValue('usr_number_login') . $loginData['htmlUserRank'],
            'showLogoutLink' => self::$pluginConfig['login_form_show_logout_link']
        ));
    }

    /**
     * @return array<string,string>
     * @throws Exception
     */
    private static function getLoginData() : array
    {
        global $gCurrentUser;

        $loginData = array();
        // show the rank of the user if this is configured in the config.php
        $loginData['htmlUserRank'] = '';

        if (self::$pluginConfig['login_form_enable_ranks']) {
            $currentUserRankTitle = '';
            $rankTitle = reset(self::$pluginConfig['login_form_ranks']);

            while ($rankTitle !== false) {
                $rankAssessment = key(self::$pluginConfig['login_form_ranks']);
                if ($rankAssessment < $gCurrentUser->getValue('usr_number_login')) {
                    $currentUserRankTitle = $rankTitle;
                }
                $rankTitle = next(self::$pluginConfig['login_form_ranks']);
            }

            if ($currentUserRankTitle !== '') {
                $loginData['htmlUserRank'] = ' (' . Language::translateIfTranslationStrId($currentUserRankTitle) . ')';
            }
        }

        if ($gCurrentUser->getValue('usr_last_login') === '') {
            $loginData['lastLogin'] = '---';
        } else {
            $loginData['lastLogin'] = $gCurrentUser->getValue('usr_last_login');
        }

        return $loginData;
    }

    /**
     * @brief Creates the login form
     * @return FormPresenter Returns the login form presenter
     */
    private static function createLoginForm(PagePresenter $formPage) : FormPresenter
    {
        global $gL10n, $gDb, $gCurrentOrgId, $gSettingsManager, $gCurrentOrganization;
        
        // preselected organization should be set by query parameter
        $getOrganizationShortName = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');
        if ($getOrganizationShortName === '') {
            $getOrganizationShortName = $gCurrentOrganization->getValue('org_shortname');
        }

        if (self::$pluginConfig['login_form_show_email_link']) {
            // read id of administrator role
            $sql = 'SELECT MIN(rol_id) as rol_id
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_administrator = true
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )';
            $administratorStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

            // create role object for administrator
            $roleAdministrator = new Role($gDb, (int) $administratorStatement->fetchColumn());

            $linkText = $gL10n->get('SYS_LOGIN_PROBLEMS');

            // show link if user has login problems
            if ($gSettingsManager->getBool('enable_password_recovery') && $gSettingsManager->getBool('system_notifications_enabled')) {
                // request to reset the password
                $linkUrl = ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php';
                $linkText = $gL10n->get('SYS_PASSWORD_FORGOTTEN');
            } elseif ($gSettingsManager->getInt('mail_module_enabled') === 1 && $roleAdministrator->getValue('rol_mail_this_role') == 3) {
                // show link of message module to send mail to administrator role
                $linkUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('role_uuid' => $roleAdministrator->getValue('rol_uuid'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
            } else {
                // show link to send mail with local mail-client to administrator
                $linkUrl = SecurityUtils::encodeUrl('mailto:' . $gCurrentOrganization->getValue('org_email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
            }
            $forgotPasswordLink = '<a href="' . $linkUrl . '">' . $linkText . '</a>';
        } else {
            $forgotPasswordLink = '';
        }

        $form = new FormPresenter(
            'adm_plugin_login_form',
            'plugin.login-form.edit.tpl',
            ADMIDIO_URL . FOLDER_SYSTEM . '/login.php?mode=check',
            $formPage,
            array('type' => 'vertical', 'setFocus' => false, 'showRequiredFields' => false)
        );
        $form->addInput(
            'plg_usr_login_name',
            $gL10n->get('SYS_USERNAME'),
            '',
            array('property' => FormPresenter::FIELD_REQUIRED, 'maxLength' => 254)
        );
        $form->addInput(
            'plg_usr_password',
            $gL10n->get('SYS_PASSWORD'),
            '',
            array(
                'type' => 'password',
                'property' => FormPresenter::FIELD_REQUIRED,
                'helpTextId' => $forgotPasswordLink
            )
        );

        if ($gSettingsManager->getBool('two_factor_authentication_enabled')) {
            $form->addInput(
                'usr_totp_code',
                $gL10n->get('SYS_SECURITY_CODE'),
                '',
                array('maxLength' => 6)
            );
        }

        // show selectbox with all organizations of database
        if ($gCurrentOrganization->getValue('org_show_org_select')) {
            $sql = 'SELECT org_shortname, org_longname
                  FROM ' . TBL_ORGANIZATIONS . '
              ORDER BY org_longname, org_shortname';
            $form->addSelectBoxFromSql(
                'plg_org_shortname',
                $gL10n->get('SYS_ORGANIZATION'),
                $gDb,
                $sql,
                array('defaultValue' => $getOrganizationShortName, 'showContextDependentFirstEntry' => false)
            );
        }

        if ($gSettingsManager->getBool('enable_auto_login')) {
            $form->addCheckbox('plg_auto_login', $gL10n->get('SYS_REMEMBER_ME'));
        }
        $form->addSubmitButton('plg_btn_login', $gL10n->get('SYS_LOGIN'), array('icon' => 'bi-box-arrow-in-right'));

        return $form;
    }
}
