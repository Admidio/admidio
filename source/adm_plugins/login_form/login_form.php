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
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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

// set database to admidio, sometimes the user has other database connections at the same time
$gDb->setCurrentDB();

echo '<div id="plugin_'. $plugin_folder. '" class="admPluginContent">
<div class="admPluginHeader">';
    if($gValidLogin)
    {
        echo '<h3 class="admHeadline3">'.$gL10n->get('SYS_REGISTERED_AS').'</h3>';
    }
    else
    {
        echo '<h3 class="admHeadline3">'.$gL10n->get('SYS_LOGIN').'</h3>';
    }
echo '</div>
<div class="admPluginBody">';

if($gValidLogin == 1)
{
    echo '    
    <script type="text/javascript">
    	$(document).ready(function() {
			$(".admLogout").click(function() {';
                if(strlen($plg_link_target) > 0 && strpos($plg_link_target, '_') === false)
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
    
	if($newLayout)
	{
		echo '<div id="adm-plugin-form-informations" class="admFieldInformations">
			<div class="admFieldRow">
				<div class="admFieldLabel">'.$gL10n->get('SYS_MEMBER').':</div>
				<div class="admFieldElement">
					<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $gCurrentUser->getValue('usr_id'). '" 
                    '. $plg_link_target. ' title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'. $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'). '</a>
				</div>
			</div>
			<div class="admFieldRow">
				<div class="admFieldLabel">'.$gL10n->get('PLG_LOGIN_ACTIVE_SINCE').':</div>
				<div class="admFieldElement">'.$gCurrentSession->getValue('ses_begin', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK').'
				</div>
			</div>
			<div class="admFieldRow">
				<div class="admFieldLabel">'.$gL10n->get('PLG_LOGIN_LAST_LOGIN').':</div>
				<div class="admFieldElement">'. $gCurrentUser->getValue('usr_last_login'). '</div>
			</div>
			<div class="admFieldRow">
				<div class="admFieldLabel">'.$gL10n->get('PLG_LOGIN_NUMBER_OF_LOGINS').':</div>
				<div class="admFieldElement">'. $gCurrentUser->getValue('usr_number_login');
    
					// show the rank of the user if this is configured in the config.php
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

                        if(strlen($currentUserRankTitle) > 0)
                        {
                            echo ' ('.$currentUserRankTitle.')';
                        }
                    }
                echo '</div>
			</div>
		</div>';
			
		// show link for logout
		if($plg_show_icons)
		{
			echo '<span class="admIconTextLink">
				<a class="admLogout" href="#"><img src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_LOGOUT').'" /></a>
				<a class="admLogout" href="#">'.$gL10n->get('SYS_LOGOUT').'</a>
			</span>';
		}
		else
		{
			echo '<a class="admLogout" href="#"><img src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_LOGOUT').'" /></a>';
		}
	}
	else
	{
		echo '<ul class="formFieldList" id="plgLoginFormFieldList">
			<li>
				<dl>
					<dt>'.$gL10n->get('SYS_MEMBER').':</dt>
					<dd>
						<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $gCurrentUser->getValue('usr_id'). '" 
						'. $plg_link_target. ' title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'. $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'). '</a>
					</dd>
				</dl>
			</li>
			<li>
				<dl>
					<dt>'.$gL10n->get('PLG_LOGIN_ACTIVE_SINCE').':</dt>
					<dd>'. $gCurrentSession->getValue('ses_begin', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK').'</dd>
				</dl>
			</li>
			<li>
				<dl>
					<dt>'.$gL10n->get('PLG_LOGIN_LAST_LOGIN').':</dt>
					<dd>'. $gCurrentUser->getValue('usr_last_login'). '</dd>
				</dl>
			</li>
			<li>
				<dl>
					<dt>'.$gL10n->get('PLG_LOGIN_NUMBER_OF_LOGINS').':</dt>
					<dd>'. $gCurrentUser->getValue('usr_number_login');
		
						// show the rank of the user if this is configured in the config.php
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

							if(strlen($currentUserRankTitle) > 0)
							{
								echo '&nbsp;('.$currentUserRankTitle.')';
							}
						}
					echo '</dd>
				</dl>
			</li>';

			// Link zum Ausloggen
			if($plg_show_logout_link)
			{
				if($plg_show_icons)
				{
					$iconCode = '<a class="admLogout" href="#"><img src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_LOGOUT').'" /></a>';
				}
				echo '<li>
					<dl>
						<dt class="iconTextLink">'. $iconCode. '
							<a class="admLogout" href="#">'.$gL10n->get('SYS_LOGOUT').'</a>
						</dt>
					</dl>
				</li>';
			}
			echo '</ul>';
        }
}
else
{
    if($newLayout)
    {
        // create and show the login form
        if($plg_show_icons == 1)
        {
            $iconCode  = THEME_PATH. '/icons/key.png';
        }
    
        $form = new HtmlForm('plugin-login-form-form', $g_root_path.'/adm_program/system/login_check.php');
        $form->addTextInput('plg_usr_login_name', $gL10n->get('SYS_USERNAME'), null, 35);
        $form->addPasswordInput('plg_usr_password', $gL10n->get('SYS_PASSWORD'));
        
        // show selectbox with all organizations of database
        if($gPreferences['system_organization_select'] == 1)
        {
            $sql = 'SELECT org_id, org_longname FROM '.TBL_ORGANIZATIONS.' ORDER BY org_longname ASC, org_shortname ASC';
            $form->addSelectBoxFromSql('plg_org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql, false, $gCurrentOrganization->getValue('org_id'), true);
        }

        if($gPreferences['enable_auto_login'] == 1)
        {
            $form->addCheckbox('plg_auto_login', $gL10n->get('SYS_REMEMBER_ME'), '1');
        }

        $form->addSubmitButton('next_page', $gL10n->get('SYS_LOGIN'), $iconCode, null, null);
        $form->show();
        
        // show links for registration and help
        if($plg_show_register_link && $gPreferences['registration_mode'])
        {
            if($plg_show_icons)
            {
                echo '<span class="admIconTextLink">
                    <a href="'. $g_root_path. '/adm_program/system/registration.php"><img src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/system/registration.php" '. $plg_link_target. '>'.$gL10n->get('SYS_REGISTRATION').'</a>
                </span>';
            }
            else
            {
                echo '<a href="'. $g_root_path. '/adm_program/system/registration.php" '. $plg_link_target. '>'.$gL10n->get('SYS_REGISTRATION').'</a>';
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

            // Link bei Loginproblemen
            if($gPreferences['enable_password_recovery'] == 1
            && $gPreferences['enable_system_mails'] == 1)
            {
                // neues Passwort zusenden
                $emailLink = $g_root_path.'/adm_program/system/lost_password.php';
            }
            elseif($gPreferences['enable_mail_module'] == 1 
            && $roleWebmaster->getValue('rol_mail_this_role') == 3)
            {
                // Mailmodul aufrufen mit Webmaster als Ansprechpartner
                $emailLink = $g_root_path.'/adm_program/modules/mail/mail.php?rol_id='. $roleWebmaster->getValue('rol_id'). '&amp;subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
            }
            else
            {
                // direkte Mail an den Webmaster ueber einen externen Mailclient
                $emailLink = 'mailto:'. $gPreferences['email_administrator']. '?subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
            }

            if($plg_show_icons)
            {
                echo '<span class="admIconTextLink">
                    <a href="'. $emailLink. '"><img src="'. THEME_PATH. '/icons/email_key.png" alt="'.$gL10n->get('SYS_LOGIN_PROBLEMS').'" /></a>
                    <a href="'. $emailLink. '" '. $plg_link_target. '>'.$gL10n->get('SYS_LOGIN_PROBLEMS').'</a>
                </span>';
            }
            else
            {
                echo '<a href="'. $emailLink. '" '. $plg_link_target. '>'.$gL10n->get('SYS_LOGIN_PROBLEMS').'</a>';
            }
            
        }
    }
    else
    {
    if($plg_show_icons == 1)
    {
        $iconCode  = '<img src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('SYS_LOGIN').'" />&nbsp;';
    }
    echo '
    <form id="plugin_'. $plugin_folder. '" style="display: inline;" action="'. $g_root_path. '/adm_program/system/login_check.php" method="post">
        <ul class="formFieldList" id="plgLoginFormFieldList">
            <li>
                <dl>
                    <dt><label for="plg_usr_login_name">'.$gL10n->get('SYS_USERNAME').':</label></dt>
                    <dd><input type="text" id="plg_usr_login_name" name="plg_usr_login_name" size="10" maxlength="35" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="plg_usr_password">'.$gL10n->get('SYS_PASSWORD').':</label></dt>
                    <dd><input type="password" id="plg_usr_password" name="plg_usr_password" size="10" /></dd>
                </dl>
            </li>';

			// show selectbox with all organizations of database
			if($gPreferences['system_organization_select'] == 1)
			{
				echo '<li>
					<dl>
						<dt><label for="org_id">'.$gL10n->get('SYS_ORGANIZATION').':</label></dt>
						<dd>'.FormElements::generateOrganizationSelectBox($g_organization, 'plg_org_id').'</dd>
					</dl>
				</li>';
			}

            if($gPreferences['enable_auto_login'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt><label for="plg_auto_login">'.$gL10n->get('SYS_REMEMBER_ME').':</label></dt>
                        <dd><input type="checkbox" id="plg_auto_login" name="plg_auto_login" value="1" /></dd>
                    </dl>
                </li>';
            } 

            echo '
            <li id="plgRowLoginButton">
                <dl>
                    <dt>
                        <button type="submit">'.$iconCode. $gL10n->get('SYS_LOGIN').'</button>
                    </dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>';
        
            // Links zum Registrieren und melden eines Problems anzeigen, falls gewuenscht
            if($plg_show_register_link || $plg_show_email_link)
            {
                echo '<li>
                    <dl>';
                        if($plg_show_register_link && $gPreferences['registration_mode'])
                        {
                            if($plg_show_icons)
                            {
                                $iconCode = '<span class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/system/registration.php"><img src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" /></a>
                                    <a href="'. $g_root_path. '/adm_program/system/registration.php" '. $plg_link_target. '>'.$gL10n->get('SYS_REGISTRATION').'</a>
                                </span>';
                            }
                            else
                            {
                                $iconCode = '<a href="'. $g_root_path. '/adm_program/system/registration.php" '. $plg_link_target. '>'.$gL10n->get('SYS_REGISTRATION').'</a>';
                            }
                            echo '<dt>'.$iconCode.'</dt>
                                <dd>&nbsp;</dd>';
                        }
                        if($plg_show_register_link && $plg_show_email_link)
                        {
                            echo '</dl></li><li><dl>';
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

                            // Link bei Loginproblemen
                            if($gPreferences['enable_password_recovery'] == 1
                            && $gPreferences['enable_system_mails'] == 1)
                            {
                                // neues Passwort zusenden
                                $mail_link = $g_root_path.'/adm_program/system/lost_password.php';
                            }
                            elseif($gPreferences['enable_mail_module'] == 1 
                            && $roleWebmaster->getValue('rol_mail_this_role') == 3)
                            {
                                // Mailmodul aufrufen mit Webmaster als Ansprechpartner
                                $mail_link = $g_root_path.'/adm_program/modules/mail/mail.php?rol_id='. $roleWebmaster->getValue('rol_id'). '&amp;subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
                            }
                            else
                            {
                                // direkte Mail an den Webmaster ueber einen externen Mailclient
                                $mail_link = 'mailto:'. $gPreferences['email_administrator']. '?subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
                            }

                            if($plg_show_icons)
                            {
                                $iconCode = '<span class="iconTextLink">
                                    <a href="'. $mail_link. '"><img src="'. THEME_PATH. '/icons/email_key.png" alt="'.$gL10n->get('SYS_LOGIN_PROBLEMS').'" /></a>
                                    <a href="'. $mail_link. '" '. $plg_link_target. '>'.$gL10n->get('SYS_LOGIN_PROBLEMS').'</a>
                                </span>';
                            }
                            else
                            {
                                $iconCode = '<a href="'. $mail_link. '" '. $plg_link_target. '>'.$gL10n->get('SYS_LOGIN_PROBLEMS').'</a>';
                            }
                            
                            echo '<dt>'.$iconCode.'</dt>
                            <dd>&nbsp;</dd>';
                        }
                    echo '</dl>
                </li>';
            }    
        echo '</ul>
    </form>';   
    }
}

echo '</div></div>';

?>