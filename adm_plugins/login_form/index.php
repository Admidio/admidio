<?php
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;

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
try {
    $rootPath = dirname(__DIR__, 2);
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/system/common.php');

    // only include config file if it exists
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    $loginFormPlugin = new Overview($pluginFolder);

    // set default values if there has been no value stored in the config.php
    if (!isset($plg_show_register_link) || !is_numeric($plg_show_register_link)) {
        $plg_show_register_link = 0;
    }

    if (!isset($plg_show_email_link) || !is_numeric($plg_show_email_link)) {
        $plg_show_email_link = 1;
    }

    if (!isset($plg_show_logout_link) || !is_numeric($plg_show_logout_link)) {
        $plg_show_logout_link = 0;
    }

    if (!isset($plg_rank)) {
        $plg_rank = array();
    }

    if ($gValidLogin) {
        // show the rank of the user if this is configured in the config.php
        $htmlUserRank = '';

        if (count($plg_rank) > 0) {
            $currentUserRankTitle = '';
            $rankTitle = reset($plg_rank);

            while ($rankTitle !== false) {
                $rankAssessment = key($plg_rank);
                if ($rankAssessment < $gCurrentUser->getValue('usr_number_login')) {
                    $currentUserRankTitle = $rankTitle;
                }
                $rankTitle = next($plg_rank);
            }

            if ($currentUserRankTitle !== '') {
                $htmlUserRank = ' (' . $currentUserRankTitle . ')';
            }
        }

        if ($gCurrentUser->getValue('usr_last_login') === '') {
            $lastLogin = '---';
        } else {
            $lastLogin = $gCurrentUser->getValue('usr_last_login');
        }

        $loginFormPlugin->assignTemplateVariable('userUUID', $gCurrentUser->getValue('usr_uuid'));
        $loginFormPlugin->assignTemplateVariable('userName', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        $loginFormPlugin->assignTemplateVariable('loginActiveSince', $gCurrentSession->getValue('ses_begin', $gSettingsManager->getString('system_time')));
        $loginFormPlugin->assignTemplateVariable('lastLogin', $lastLogin);
        $loginFormPlugin->assignTemplateVariable('numberOfLogins', $gCurrentUser->getValue('usr_number_login') . $htmlUserRank);
        $loginFormPlugin->assignTemplateVariable('showLogoutLink', $plg_show_logout_link);

        if (isset($page)) {
            echo $loginFormPlugin->html('plugin.login-form.view.tpl');
        } else {
            $loginFormPlugin->showHtmlPage('plugin.login-form.view.tpl');
        }
    } else {
        // create and show the login form

        // preselected organization should be set by query parameter
        $getOrganizationShortName = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');
        if ($getOrganizationShortName === '') {
            $getOrganizationShortName = $gCurrentOrganization->getValue('org_shortname');
        }

        if ($plg_show_email_link) {
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
            } elseif ($gSettingsManager->getBool('enable_mail_module') && $roleAdministrator->getValue('rol_mail_this_role') == 3) {
                // show link of message module to send mail to administrator role
                $linkUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('role_uuid' => $roleAdministrator->getValue('rol_uuid'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
            } else {
                // show link to send mail with local mail-client to administrator
                $linkUrl = SecurityUtils::encodeUrl('mailto:' . $gCurrentOrganization->getValue('org_email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
            }
            $forgotPasswordLink = '<a href="' . $linkUrl . '">' . $gL10n->get('SYS_PASSWORD_FORGOTTEN') . '</a>';
        } else {
            $forgotPasswordLink = '';
        }

        $form = new FormPresenter(
            'adm_plugin_login_form',
            'plugin.login-form.edit.tpl',
            ADMIDIO_URL . FOLDER_SYSTEM . '/login.php?mode=check',
            null,
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

        $smarty = $loginFormPlugin->createSmartyObject();
        $smarty->assign('settings', $gSettingsManager);
        $smarty->assign('showRegisterLink', $plg_show_register_link);
        $form->addToSmarty($smarty);
        $gCurrentSession->addFormObject($form);
        echo $smarty->fetch('plugin.login-form.edit.tpl');
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
