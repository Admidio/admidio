<?php
/**
 ***********************************************************************************************
 * Login Form
 *
 * Login Form stellt das Loginformular mit den entsprechenden Feldern dar,
 * damit sich ein Benutzer anmelden kann. Ist der Benutzer angemeldet, so
 * werden an der Stelle der Felder nun nützliche Informationen des Benutzers
 * angezeigt.
 *
 * Compatible with Admidio version 4.1
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

// set default values if there no value has been stored in the config.php
if (!isset($plg_show_register_link) || !is_numeric($plg_show_register_link)) {
    $plg_show_register_link = 1;
}

if (!isset($plg_show_email_link) || !is_numeric($plg_show_email_link)) {
    $plg_show_email_link = 1;
}

if (!isset($plg_show_logout_link) || !is_numeric($plg_show_logout_link)) {
    $plg_show_logout_link = 0;
}

if (isset($plg_link_target)) {
    $plg_link_target = strip_tags($plg_link_target);
} else {
    $plg_link_target = '_self';
}

if (!isset($plg_rank)) {
    $plg_rank = array();
}

// if page object is set then integrate css file of this plugin
global $page;
if (isset($page) && $page instanceof HtmlPage) {
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . '/login_form/login_form.css');
}

echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
    if ($gValidLogin) {
        echo '<h3>'.$gL10n->get('SYS_REGISTERED_AS').'</h3>';
    } else {
        echo '<h3>'.$gL10n->get('SYS_LOGIN').'</h3>';
    }

if ($gValidLogin) {
    if ($plg_link_target === '' || str_starts_with($plg_link_target, '_')) {
        $jsContentNextPage = 'self.location.href = \'' . ADMIDIO_URL . FOLDER_SYSTEM . '/logout.php\';';
    } else {
        $jsContentNextPage = '
        parent.'. $plg_link_target. '.location.href = \'' . ADMIDIO_URL . FOLDER_SYSTEM . '/logout.php\';
        self.location.reload();';
    }

    $jsContent = '$("#adm_logout_link").click(function() {'.$jsContentNextPage.'});';

    if (isset($page) && $page instanceof HtmlPage) {
        $page->addJavascript($jsContent, true);
    } else {
        echo '
        <script type="text/javascript">
            $(function() {
                '.$jsContent.'
            });
        </script>';
    }

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
            $htmlUserRank = ' ('.$currentUserRankTitle.')';
        }
    }

    if ($gCurrentUser->getValue('usr_last_login') === '') {
        $lastLogin = '---';
    } else {
        $lastLogin = $gCurrentUser->getValue('usr_last_login');
    }

    // create a static form
    $form = new HtmlForm('plugin-login-static-form', '#', null, array('type' => 'vertical', 'setFocus' => false));
    $form->addStaticControl(
        'plg_user',
        $gL10n->get('SYS_MEMBER'),
        '<a href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES. '/profile/profile.php', array('user_uuid' => $gCurrentUser->getValue('usr_uuid'))). '" target="'. $plg_link_target. '" title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'
        . $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') .
        '</a>'
    );
    $form->addStaticControl('plg_active_since', $gL10n->get('PLG_LOGIN_ACTIVE_SINCE'), $gCurrentSession->getValue('ses_begin', $gSettingsManager->getString('system_time')));
    $form->addStaticControl('plg_last_login', $gL10n->get('PLG_LOGIN_LAST_LOGIN'), $lastLogin);
    $form->addStaticControl('plg_number_of_logins', $gL10n->get('PLG_LOGIN_NUMBER_OF_LOGINS'), (int) $gCurrentUser->getValue('usr_number_login').$htmlUserRank);
    echo $form->show();

    if ($plg_show_logout_link) {
        // show link for logout
        echo '<div class="btn-group-vertical" role="group">
            <a id="adm_logout_link" class="btn admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_SYSTEM.'/logout.php"><i class="fas fa-sign-out-alt"></i>'.$gL10n->get('SYS_LOGOUT').'</a>
        </div>';
    }
} else {
    // create and show the login form

    $form = new HtmlForm(
        'plugin-login-form',
        ADMIDIO_URL.'/adm_program/system/login_check.php',
        null,
        array('type' => 'vertical', 'setFocus' => false, 'showRequiredFields' => false)
    );
    $form->addInput(
        'plg_usr_login_name',
        $gL10n->get('SYS_USERNAME'),
        '',
        array('property' => HtmlForm::FIELD_REQUIRED, 'maxLength' => 254)
    );
    $form->addInput(
        'plg_usr_password',
        $gL10n->get('SYS_PASSWORD'),
        '',
        array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED)
    );

    // show selectbox with all organizations of database
    if ($gSettingsManager->getBool('system_organization_select')) {
        $sql = 'SELECT org_id, org_longname
                  FROM '.TBL_ORGANIZATIONS.'
              ORDER BY org_longname ASC, org_shortname ASC';
        $form->addSelectBoxFromSql(
            'plg_org_id',
            $gL10n->get('SYS_ORGANIZATION'),
            $gDb,
            $sql,
            array('defaultValue' => $gCurrentOrgId, 'showContextDependentFirstEntry' => false)
        );
    }

    if ($gSettingsManager->getBool('enable_auto_login')) {
        $form->addCheckbox('plg_auto_login', $gL10n->get('SYS_REMEMBER_ME'), false);
    }

    $form->addSubmitButton('next_page', $gL10n->get('SYS_LOGIN'), array('icon' => 'fa-key'));
    echo $form->show();

    echo '<div class="btn-group-vertical" role="group">';

    // show links for registration and help
    if ($plg_show_register_link && $gSettingsManager->getBool('registration_enable_module')) {
        echo '<a class="btn admidio-icon-link" href="'. ADMIDIO_URL. FOLDER_MODULES. '/registration/registration.php" target="'. $plg_link_target. '"><i class="fas fa-address-card"></i>'.$gL10n->get('SYS_REGISTRATION').'</a>';
    }

    if ($plg_show_email_link) {
        // read id of administrator role
        $sql = 'SELECT MIN(rol_id) as rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_administrator = true
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )';
        $administratorStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        // create role object for administrator
        $roleAdministrator = new TableRoles($gDb, (int) $administratorStatement->fetchColumn());

        $linkText = $gL10n->get('SYS_LOGIN_PROBLEMS');

        // show link if user has login problems
        if ($gSettingsManager->getBool('enable_password_recovery') && $gSettingsManager->getBool('system_notifications_enabled')) {
            // request to reset the password
            $linkUrl  = ADMIDIO_URL.FOLDER_SYSTEM.'/password_reset.php';
            $linkText = $gL10n->get('SYS_PASSWORD_FORGOTTEN');
        } elseif ($gSettingsManager->getBool('enable_mail_module') && $roleAdministrator->getValue('rol_mail_this_role') == 3) {
            // show link of message module to send mail to administrator role
            $linkUrl = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES. '/messages/messages_write.php', array('role_uuid' => $roleAdministrator->getValue('rol_uuid'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        } else {
            // show link to send mail with local mail-client to administrator
            $linkUrl = SecurityUtils::encodeUrl('mailto:'. $gSettingsManager->getString('email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        }

        echo '<a class="btn admidio-icon-link" href="'. $linkUrl. '" target="'.$plg_link_target.'"><i class="fas fa-envelope"></i>'.$linkText.'</a>';
    }
    echo '</div>';
}

echo '</div>';
