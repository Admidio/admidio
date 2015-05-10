<?php
/******************************************************************************
 * Login Form
 *
 * Version 1.6.0
 *
 * Login Form stellt das Loginformular mit den entsprechenden Feldern dar,
 * damit sich ein Benutzer anmelden kann. Ist der Benutzer angemeldet, so
 * werden an der Stelle der Felder nun nützliche Informationen des Benutzers
 * angezeigt.
 *
 * Compatible with Admidio version 2.3.0
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'login_form.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

// initialize parameters
$iconCode = null;

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_show_register_link) == false || is_numeric($plg_show_register_link) == false)
{
    $plg_show_register_link = 1;
}

if(isset($plg_show_email_link) == false || is_numeric($plg_show_email_link) == false)
{
    $plg_show_email_link = 1;
}

if(isset($plg_show_logout_link) == false || is_numeric($plg_show_logout_link) == false)
{
    $plg_show_logout_link = 1;
}

if(isset($plg_show_icons) == false || is_numeric($plg_show_icons) == false)
{
    $plg_show_icons = 1;
}

if(isset($plg_link_target) && $plg_link_target != '_self')
{
    $plg_link_target = ' target="'. strip_tags($plg_link_target). '" ';
}
else
{
    $plg_link_target = '';
}

if(isset($plg_rank) == false)
{
    $plg_rank = array();
}

// if page object is set then integrate css file of this plugin
global $page;
if(isset($page) && is_object($page))
{
    $page->addCssFile($g_root_path.'/adm_plugins/login_form/login_form.css');
}

// set database to admidio, sometimes the user has other database connections at the same time
global $gDb;
$gDb->setCurrentDB();

echo '<div id="plugin_'. $plugin_folder. '" class="admidio-plugin-content">';
    if($gValidLogin)
    {
        echo '<h3>'.$gL10n->get('SYS_REGISTERED_AS').'</h3>';
    }
    else
    {
        echo '<h3>'.$gL10n->get('SYS_LOGIN').'</h3>';
    }

if($gValidLogin == 1)
{
    echo '
    <script type="text/javascript">
        $(document).ready(function() {
            $("#adm_logout_link").click(function() {';
                if($plg_link_target !== '' && strpos($plg_link_target, '_') === false)
                {
                    echo '
                    parent.'. $plg_link_target. '.location.href = \''. $g_root_path. '/adm_program/system/logout.php\';
                    self.location.reload(); ';
                }
                else
                {
                    echo 'self.location.href = \''. $g_root_path. '/adm_program/system/logout.php\';';
                }
            echo '
            });
        });
    </script>';

    // show the rank of the user if this is configured in the config.php
    $htmlUserRank = '';

    if(count($plg_rank) > 0)
    {
        $currentUserRankTitle = '';
        $rankTitle = reset($plg_rank);

        while($rankTitle != false)
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

    // create a static form
    $form = new HtmlForm('plugin-login-static-form', null, null, array('type' => 'vertical', 'setFocus' => false));
    $form->addStaticControl('plg_user', $gL10n->get('SYS_MEMBER'), '<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $gCurrentUser->getValue('usr_id'). '"
                '. $plg_link_target. ' title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'. $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'). '</a>');
    $form->addStaticControl('plg_active_since', $gL10n->get('PLG_LOGIN_ACTIVE_SINCE'), $gCurrentSession->getValue('ses_begin', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK'));
    $form->addStaticControl('plg_last_login', $gL10n->get('PLG_LOGIN_LAST_LOGIN'), $gCurrentUser->getValue('usr_last_login'));
    $form->addStaticControl('plg_number_of_logins', $gL10n->get('PLG_LOGIN_NUMBER_OF_LOGINS'), $gCurrentUser->getValue('usr_number_login').$htmlUserRank);
    $form->show();

    echo '<div class="btn-group-vertical" role="group">';

    // show link for logout
    if($plg_show_icons)
    {
        echo '<a id="adm_logout_link" class="btn" href="'.$g_root_path.'/adm_program/system/logout.php"><img
            src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_LOGOUT').'" />'.$gL10n->get('SYS_LOGOUT').'</a>';
    }
    else
    {
        echo '<a id="adm_logout_link" href="'.$g_root_path.'/adm_program/system/logout.php">'.$gL10n->get('SYS_LOGOUT').'</a>';
    }
    echo '</div>';
}
else
{
    // create and show the login form
    if($plg_show_icons == 1)
    {
        $iconCode  = THEME_PATH. '/icons/key.png';
    }

    $form = new HtmlForm('plugin-login-form', $g_root_path.'/adm_program/system/login_check.php', null, array('type' => 'vertical', 'setFocus' => false));
    $form->addInput('plg_usr_login_name', $gL10n->get('SYS_USERNAME'), null, array('property' => FIELD_MANDATORY, 'maxLength' => 35));
    $form->addInput('plg_usr_password', $gL10n->get('SYS_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_MANDATORY));

    // show selectbox with all organizations of database
    if($gPreferences['system_organization_select'] == 1)
    {
        $sql = 'SELECT org_id, org_longname FROM '.TBL_ORGANIZATIONS.' ORDER BY org_longname ASC, org_shortname ASC';
        $form->addSelectBoxFromSql('plg_org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql,
            array('defaultValue' => $gCurrentOrganization->getValue('org_id'), 'showContextDependentFirstEntry' => false));
    }

    if($gPreferences['enable_auto_login'] == 1)
    {
        $form->addCheckbox('plg_auto_login', $gL10n->get('SYS_REMEMBER_ME'), '0');
    }

    $form->addSubmitButton('next_page', $gL10n->get('SYS_LOGIN'), array('icon' => $iconCode));
    $form->show();

    echo '<div class="btn-group-vertical" role="group">';

    // show links for registration and help
    if($plg_show_register_link && $gPreferences['registration_mode'])
    {
        if($plg_show_icons)
        {
            echo '
            <a class="btn" href="'. $g_root_path. '/adm_program/modules/registration/registration.php"><img
                src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" />'.$gL10n->get('SYS_REGISTRATION').'</a>';
        }
        else
        {
            echo '<a href="'. $g_root_path. '/adm_program/modules/registration/registration.php" '. $plg_link_target. '>'.$gL10n->get('SYS_REGISTRATION').'</a>';
        }
    }

    if($plg_show_email_link)
    {
        // read id of webmaster role
        $sql = 'SELECT rol_id FROM '.TBL_ROLES.', '.TBL_CATEGORIES.'
                 WHERE rol_name LIKE \''.$gL10n->get('SYS_WEBMASTER').'\'
                   AND rol_webmaster = 1
                   AND rol_cat_id = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL ) ';
        $gDb->query($sql);
        $row = $gDb->fetch_array();

        // create role object for webmaster
        $roleWebmaster = new TableRoles($gDb, $row['rol_id']);

        $linkText = $gL10n->get('SYS_LOGIN_PROBLEMS');

        // Link bei Loginproblemen
        if($gPreferences['enable_password_recovery'] == 1
        && $gPreferences['enable_system_mails'] == 1)
        {
            // neues Passwort zusenden
            $linkUrl  = $g_root_path.'/adm_program/system/lost_password.php';
            $linkText = $gL10n->get('SYS_PASSWORD_FORGOTTEN');
        }
        elseif($gPreferences['enable_mail_module'] == 1
        && $roleWebmaster->getValue('rol_mail_this_role') == 3)
        {
            // Mailmodul aufrufen mit Webmaster als Ansprechpartner
            $linkUrl = $g_root_path.'/adm_program/modules/messages/messages_write.php?rol_id='. $roleWebmaster->getValue('rol_id'). '&amp;subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
        }
        else
        {
            // direkte Mail an den Webmaster ueber einen externen Mailclient
            $linkUrl = 'mailto:'. $gPreferences['email_administrator']. '?subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
        }

        if($plg_show_icons)
        {
            echo '
            <a class="btn" href="'. $linkUrl. '"><img
                src="'. THEME_PATH. '/icons/email_key.png" alt="'.$linkText.'" />'.$linkText.'</a>';
        }
        else
        {
            echo '<a href="'.$linkUrl.'" '.$plg_link_target.'>'.$linkText.'</a>';
        }
    }
    echo '</div>';
}

echo '</div>';

?>
