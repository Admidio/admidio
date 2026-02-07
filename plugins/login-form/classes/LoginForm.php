<?php

namespace LoginForm\classes;

use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use InvalidArgumentException;
use Exception;
use Throwable;

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
class LoginForm extends PluginAbstract
{
    private static array $pluginConfig = array();

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
            ADMIDIO_PATH . FOLDER_PLUGINS . '/LoginForm/templates/plugin.login-form.edit.tpl',
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

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender($page = null) : bool
    {
        global $gSettingsManager, $gCurrentUser, $gCurrentSession, $gValidLogin, $gL10n;

        // show the latest documents & files list
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $loginFormPlugin = new Overview($pluginFolder);
            self::$pluginConfig = self::getPluginConfigValues();

            // check if the plugin is enabled
            if (self::$pluginConfig['login_form_plugin_enabled'] === 1 || ($gValidLogin && self::$pluginConfig['login_form_plugin_enabled'] === 2 && $gValidLogin)) {
                if ($gValidLogin) {
                    $loginData = self::getLoginData();
                    $loginFormPlugin->assignTemplateVariable('userUUID', $gCurrentUser->getValue('usr_uuid'));
                    $loginFormPlugin->assignTemplateVariable('userName', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
                    $loginFormPlugin->assignTemplateVariable('loginActiveSince', $gCurrentSession->getValue('ses_begin', $gSettingsManager->getString('system_time')));
                    $loginFormPlugin->assignTemplateVariable('lastLogin', $loginData['lastLogin']);
                    $loginFormPlugin->assignTemplateVariable('numberOfLogins', $gCurrentUser->getValue('usr_number_login') . $loginData['htmlUserRank']);
                    $loginFormPlugin->assignTemplateVariable('showLogoutLink', self::$pluginConfig['login_form_show_logout_link']);

                    if (isset($page)) {
                        echo $loginFormPlugin->html('plugin.login-form.view.tpl');
                    } else {
                        $loginFormPlugin->showHtmlPage('plugin.login-form.view.tpl');
                    }
                } else {
                    $formPage = $loginFormPlugin->getPage();
                    $form = self::createLoginForm($formPage);
                    if (isset($page)) {
                        $smarty = $loginFormPlugin->createSmartyObject();
                        $smarty->assign('settings', $gSettingsManager);
                        $smarty->assign('showRegisterLink', self::$pluginConfig['login_form_show_register_link']);
                        $form->addToSmarty($smarty);
                        $gCurrentSession->addFormObject($form);
                        echo $smarty->fetch('plugin.login-form.edit.tpl');
                    } else {
                        $_SESSION['login_forward_url_post'] = '1'; // Force a reload of the entire page, especially if it was loaded from an iframe.
                        $form->addToHtmlPage();
                        $gCurrentSession->addFormObject($form);
                        $formPage->assignSmartyVariable('settings', $gSettingsManager);
                        $formPage->assignSmartyVariable('showRegisterLink', self::$pluginConfig['login_form_show_register_link']);
                        $formPage->show();
                    }
                }
            } else {
                throw new InvalidArgumentException($gL10n->get('SYS_INVALID_PAGE_VIEW'));
            }
                
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}