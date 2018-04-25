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
 * Compatible with Admidio version 3.3
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');
require_once(__DIR__ . '/config.php');

// initialize parameters
$iconCode = null;

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(!isset($plg_show_register_link) || !is_numeric($plg_show_register_link))
{
    $plg_show_register_link = 1;
}

if(!isset($plg_show_email_link) || !is_numeric($plg_show_email_link))
{
    $plg_show_email_link = 1;
}

if(!isset($plg_show_logout_link) || !is_numeric($plg_show_logout_link))
{
    $plg_show_logout_link = 1;
}

if(!isset($plg_show_icons) || !is_numeric($plg_show_icons))
{
    $plg_show_icons = 1;
}

if(isset($plg_link_target) && $plg_link_target !== '_self')
{
    $plg_link_target = ' target="'. strip_tags($plg_link_target). '" ';
}
else
{
    $plg_link_target = '';
}

if(!isset($plg_rank))
{
    $plg_rank = array();
}

// if page object is set then integrate css file of this plugin
global $page;
if(isset($page) && $page instanceof HtmlPage)
{
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . '/login_form/login_form.css');
}

echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
    if($gValidLogin)
    {
        echo '<h3>'.$gL10n->get('SYS_REGISTERED_AS').'</h3>';
    }
    else
    {
        echo '<h3>'.$gL10n->get('SYS_LOGIN').'</h3>';
    }

if($gValidLogin)
{
    if($plg_link_target === '' || StringUtils::strStartsWith($plg_link_target, '_'))
    {
        $jsContentNextPage = 'self.location.href = \''. ADMIDIO_URL. '/adm_program/system/logout.php\';';
    }
    else
    {
        $jsContentNextPage = '
        parent.'. $plg_link_target. '.location.href = \''. ADMIDIO_URL. '/adm_program/system/logout.php\';
        self.location.reload();';
    }

    $jsContent = '$("#adm_logout_link").click(function() {'.$jsContentNextPage.'});';

    if(isset($page) && $page instanceof HtmlPage)
    {
        $page->addJavascript($jsContent, true);
    }
    else
    {
        echo '
        <script type="text/javascript">
            $(function() {
                '.$jsContent.'
            });
        </script>';
    }

    // show the rank of the user if this is configured in the config.php
    $htmlUserRank = '';

    if(count($plg_rank) > 0)
    {
        $currentUserRankTitle = '';
        $rankTitle = reset($plg_rank);

        while ($rankTitle !== false)
        {
            $rankAssessment = key($plg_rank);
            if($rankAssessment < $gCurrentUser->getValue('usr_number_login'))
            {
                $currentUserRankTitle = $rankTitle;
            }
            $rankTitle = next($plg_rank);
        }

        if($currentUserRankTitle !== '')
        {
            $htmlUserRank = ' ('.$currentUserRankTitle.')';
        }
    }

    if ($gCurrentUser->getValue('usr_last_login') === '')
    {
        $lastLogin = '---';
    }
    else
    {
        $lastLogin = $gCurrentUser->getValue('usr_last_login');
    }

    // create a static form
    $form = new HtmlForm('plugin-login-static-form', '#', null, array('type' => 'vertical', 'setFocus' => false));
    $form->addStaticControl(
        'plg_user', $gL10n->get('SYS_MEMBER'),
        '<a href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES. '/profile/profile.php', array('user_id' => $gCurrentUser->getValue('usr_id'))). '" '. $plg_link_target. ' title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'
        . $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') .
        '</a>'
    );
    $form->addStaticControl('plg_active_since', $gL10n->get('PLG_LOGIN_ACTIVE_SINCE'), $gCurrentSession->getValue('ses_begin', $gSettingsManager->getString('system_time')));
    $form->addStaticControl('plg_last_login', $gL10n->get('PLG_LOGIN_LAST_LOGIN'), $lastLogin);
    $form->addStaticControl('plg_number_of_logins', $gL10n->get('PLG_LOGIN_NUMBER_OF_LOGINS'), $gCurrentUser->getValue('usr_number_login').$htmlUserRank);
    echo $form->show();

    echo '<div class="btn-group-vertical" role="group">';

    // show link for logout
    if($plg_show_icons)
    {
        echo '<a id="adm_logout_link" class="btn" href="'.ADMIDIO_URL.'/adm_program/system/logout.php"><img
            src="'. THEME_URL. '/icons/door_in.png" alt="'.$gL10n->get('SYS_LOGOUT').'" />'.$gL10n->get('SYS_LOGOUT').'</a>';
    }
    else
    {
        echo '<a id="adm_logout_link" href="'.ADMIDIO_URL.'/adm_program/system/logout.php">'.$gL10n->get('SYS_LOGOUT').'</a>';
    }
    echo '</div>';
}
else
{
    // create and show the login form
    if($plg_show_icons)
    {
        $iconCode  = THEME_URL. '/icons/key.png';
    }

    $form = new HtmlForm(
        'plugin-login-form', ADMIDIO_URL.'/adm_program/system/login_check.php', null,
        array('type' => 'vertical', 'setFocus' => false, 'showRequiredFields' => false)
    );
    $form->addInput(
        'plg_usr_login_name', $gL10n->get('SYS_USERNAME'), '',
        array('property' => HtmlForm::FIELD_REQUIRED, 'maxLength' => 35)
    );
    // TODO Future: 'minLength' => PASSWORD_MIN_LENGTH
    $form->addInput(
        'plg_usr_password', $gL10n->get('SYS_PASSWORD'), '',
        array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED)
    );

    // show selectbox with all organizations of database
    if($gSettingsManager->getBool('system_organization_select'))
    {
        $sql = 'SELECT org_id, org_longname
                  FROM '.TBL_ORGANIZATIONS.'
              ORDER BY org_longname ASC, org_shortname ASC';
        $form->addSelectBoxFromSql(
            'plg_org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql,
            array('defaultValue' => $gCurrentOrganization->getValue('org_id'), 'showContextDependentFirstEntry' => false)
        );
    }

    if($gSettingsManager->getBool('enable_auto_login'))
    {
        $form->addCheckbox('plg_auto_login', $gL10n->get('SYS_REMEMBER_ME'), false);
    }

    $form->addSubmitButton('next_page', $gL10n->get('SYS_LOGIN'), array('icon' => $iconCode));
    echo $form->show();

    echo '<div class="btn-group-vertical" role="group">';

    // show links for registration and help
    if($plg_show_register_link && $gSettingsManager->getBool('registration_enable_module'))
    {
        if($plg_show_icons)
        {
            echo '
            <a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES. '/registration/registration.php"><img
                src="'. THEME_URL. '/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" />'.$gL10n->get('SYS_REGISTRATION').'</a>';
        }
        else
        {
            echo '<a href="'. ADMIDIO_URL. FOLDER_MODULES. '/registration/registration.php" '. $plg_link_target. '>'.$gL10n->get('SYS_REGISTRATION').'</a>';
        }
    }

    if($plg_show_email_link)
    {
        // read id of administrator role
        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_administrator = 1
                   AND rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')
                   AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                       OR cat_org_id IS NULL )';
        $administratorStatement = $gDb->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR'), $gCurrentOrganization->getValue('org_id')));

        // create role object for administrator
        $roleAdministrator = new TableRoles($gDb, (int) $administratorStatement->fetchColumn());

        $linkText = $gL10n->get('SYS_LOGIN_PROBLEMS');

        // Link bei Loginproblemen
        if($gSettingsManager->getBool('enable_password_recovery') && $gSettingsManager->getBool('enable_system_mails'))
        {
            // neues Passwort zusenden
            $linkUrl  = ADMIDIO_URL.'/adm_program/system/lost_password.php';
            $linkText = $gL10n->get('SYS_PASSWORD_FORGOTTEN');
        }
        elseif($gSettingsManager->getBool('enable_mail_module') && $roleAdministrator->getValue('rol_mail_this_role') == 3)
        {
            // show link of message module to send mail to administrator role
            $linkUrl = safeUrl(ADMIDIO_URL. FOLDER_MODULES. '/messages/messages_write.php', array('rol_id' => $roleAdministrator->getValue('rol_id'), 'subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        }
        else
        {
            // show link to send mail with local mail-client to administrator
            $linkUrl = safeUrl('mailto:'. $gSettingsManager->getString('email_administrator'), array('subject' => $gL10n->get('SYS_LOGIN_PROBLEMS')));
        }

        if($plg_show_icons)
        {
            echo '
            <a class="btn" href="'. $linkUrl. '"><img
                src="'. THEME_URL. '/icons/email_key.png" alt="'.$linkText.'" />'.$linkText.'</a>';
        }
        else
        {
            echo '<a href="'.$linkUrl.'" '.$plg_link_target.'>'.$linkText.'</a>';
        }
    }
    echo '</div>';
}

echo '</div>';
