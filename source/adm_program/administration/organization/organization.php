<?php
/******************************************************************************
 * Organization preferences
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * show_option : show preferences of module with this text id
 *               Example: SYS_COMMON or 
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_text.php');

// Initialize and check the parameters
$showOption = admFuncVariableIsValid($_GET, 'show_option', 'string');
$showOptionGenJs = '';

// nur Webmaster duerfen Organisationen bearbeiten
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// der Installationsordner darf aus Sicherheitsgruenden nicht existieren
if($gDebug == 0 && file_exists('../../../adm_install'))
{
    $gMessage->show($gL10n->get('SYS_INSTALL_FOLDER_EXIST'));
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

$html_icon_warning = '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />';

if(isset($_SESSION['organization_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['organization_request']);
    unset($_SESSION['organization_request']);
}
else
{
    foreach($gCurrentOrganization->dbColumns as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // alle Systemeinstellungen in das form-Array schreiben
    foreach($gPreferences as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // Forumpassword immer auf 0000 setzen, damit es nicht ausgelesen werden kann
    $form_values['forum_pw'] = '0000';
}

// Je nach übergebenen string werden die Tabs gewechselt
// und die jeweilige Sektion des Accordion automatisch aufgeklappt 
if( strlen($showOption) > 0 )
{
	switch((string)$showOption)
	{
		case 'SYS_COMMON':
		case 'ORG_ORGANIZATION_REGIONAL_SETTINGS':
		case 'SYS_REGISTRATION':
		case 'SYS_SYSTEM_MAILS':
		case 'SYS_CAPTCHA':
		case 'ORG_SYSTEM_INFORMATIONS':
		{
			// Erstes Tab für Allgemeine Einstellungen + Sektion aufklappen
			$showOptionGenJs .= '$("#tabs").bind("tabscreate", function(event, ui) {
				$("#tabs").tabs("select" , 0 );
				$("#accordion-common").accordion("activate", $("#'.$showOption.'"));
			});';			
		} break;
		case 'ANN_ANNOUNCEMENTS':
		case 'DOW_DOWNLOADS':
		case 'PHO_PHOTOS':
		case 'SYS_FORUM':
		case 'GBO_GUESTBOOK':
		case 'LST_LISTS':
		case 'MAI_EMAILS':
		case 'ECA_GREETING_CARDS':
		case 'PRO_PROFILE':
		case 'DAT_DATES':
		case 'LNK_WEBLINKS':
		{
			// Zweites Tab für Modul Einstellungen + Sektion aufklappen
			$showOptionGenJs .= '$("#tabs").bind("tabscreate", function(event, ui) {
				$("#tabs").tabs("select" , 1 );
				$("#accordion-modules").accordion("activate", $("#'.$showOption.'"));
			});';
		} break;
	}
}

// zusaetzliche Daten fuer den Html-Kopf setzen
$gLayout['title']  = $gL10n->get('ORG_ORGANIZATION_PROPERTIES');
$gLayout['header'] =  '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/administration/organization/organization.js" ></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.ui.core.js" ></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.ui.widget.js" ></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.ui.tabs.js" ></script>		
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.ui.accordion.js" ></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.ui.scrollTo.js" ></script>
    <script type="text/javascript"><!--
        var organizationJS = new organizationClass();
        organizationJS.ids = new Array("general", "register", "announcement-module", "download-module", "photo-module", "forum",
                    "guestbook-module", "list-module", "mail-module", "system-mail", "ecard-module", "profile-module",
                    "dates-module", "links-module", "systeminfo", "captcha");
        organizationJS.ecard_CCRecipients = "'.$form_values["ecard_cc_recipients"].'";
        organizationJS.forum_Server = "'.$form_values["forum_srv"].'";
        organizationJS.forum_User = "'.$form_values["forum_usr"].'";
        organizationJS.forum_PW = "'.$form_values["forum_pw"].'";
        organizationJS.forum_DB = "'.$form_values["forum_db"].'";
        organizationJS.text_Server = "'.$gL10n->get('SYS_SERVER').':";
        organizationJS.text_User = "'.$gL10n->get('SYS_LOGIN').':";
        organizationJS.text_PW = "'.$gL10n->get('SYS_PASSWORD').':";
        organizationJS.text_DB = "'.$gL10n->get('SYS_DATABASE').':";
        $(document).ready(function()
        {
			'.$showOptionGenJs.'
            organizationJS.init();
            organizationJS.drawForumAccessDataTable();						
        });
    //--></script>
	<link rel="stylesheet" type="text/css" href="'.THEME_PATH.'/css/jquery.css">';

// Html-Kopf ausgeben
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>

<div class="formLayout" id="admOrganizationMenu">
	<div class="formBody">
	<form action="'.$g_root_path.'/adm_program/administration/organization/organization_function.php" method="post">
	<div id="tabs">
		<ul>
			<li><a href="#tabs-common">'.$gL10n->get('SYS_COMMON').'</a></li>
			<li><a href="#tabs-modules">'.$gL10n->get('SYS_MODULES').'</a></li>
		</ul>
		<div id="tabs-common">
			<div id="accordion-common">';
			/**************************************************************************************/
        	// Einstellungen Allgemein
        	/**************************************************************************************/
			
			echo '<h3 id="SYS_COMMON" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/options.png" alt="'.$gL10n->get('SYS_COMMON').'" title="'.$gL10n->get('SYS_COMMON').'" /></a>
				<a href="#">'.$gL10n->get('SYS_COMMON').'</a>
			</h3>
			<div class="groupBoxBody" style="display: none;">
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="theme">'.$gL10n->get('ORG_ADMIDIO_THEME').':</label></dt>
							<dd>
								<select size="1" id="theme" name="theme">
									<option value="">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
									$themes_path = SERVER_PATH. '/adm_themes';
									$dir_handle  = opendir($themes_path);

									while (false !== ($filename = readdir($dir_handle)))
									{
										if(is_file($filename) == false
										&& strpos($filename, '.') !== 0)
										{
											echo '<option value="'.$filename.'" ';
											if($form_values['theme'] == $filename)
											{
												echo ' selected="selected" ';
											}
											echo '>'.$filename.'</option>';
										}
									}
								echo '</select>
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_ADMIDIO_THEME_DESC').'</li>
					<li>
						<dl>
							<dt><label for="homepage_logout">'.$gL10n->get('SYS_HOMEPAGE').' ('.$gL10n->get('SYS_VISITORS').'):</label></dt>
							<dd><input type="text" id="homepage_logout" name="homepage_logout" style="width: 200px;" maxlength="250" value="'. $form_values['homepage_logout']. '" /></dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_HOMEPAGE_VISITORS').'</li>
					<li>
						<dl>
							<dt><label for="homepage_login">'.$gL10n->get('SYS_HOMEPAGE').' ('.$gL10n->get('ORG_REGISTERED_USERS').'):</label></dt>
							<dd><input type="text" id="homepage_login" name="homepage_login" style="width: 200px;" maxlength="250" value="'. $form_values['homepage_login']. '" /></dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_HOMEPAGE_REGISTERED_USERS').'</li>
					<li>
						<dl>
							<dt><label for="enable_rss">'.$gL10n->get('ORG_ENABLE_RSS_FEEDS').':</label></dt>
							<dd>
								<input type="checkbox" id="enable_rss" name="enable_rss" ';
								if(isset($form_values['enable_rss']) && $form_values['enable_rss'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_ENABLE_RSS_FEEDS_DESC').'</li>
					<li>
						<dl>
							<dt><label for="enable_auto_login">'.$gL10n->get('ORG_LOGIN_AUTOMATICALLY').':</label></dt>
							<dd>
								<input type="checkbox" id="enable_auto_login" name="enable_auto_login" ';
								if(isset($form_values['enable_auto_login']) && $form_values['enable_auto_login'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_LOGIN_AUTOMATICALLY_DESC').'</li>
					<li>
						<dl>
							<dt><label for="logout_minutes">'.$gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER').':</label></dt>
							<dd><input type="text" id="logout_minutes" name="logout_minutes" style="width: 50px;" maxlength="4" value="'. $form_values['logout_minutes']. '" /> '.$gL10n->get('SYS_MINUTES').'</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER_DESC', $gL10n->get('SYS_REMEMBER_ME')).'</li>
					<li>
						<dl>
							<dt><label for="enable_password_recovery">'.$gL10n->get('ORG_SEND_PASSWORD').':</label>
							</dt>
							<dd>
								<input type="checkbox" id="enable_password_recovery" name="enable_password_recovery" ';
								if(isset($form_values['enable_password_recovery']) && $form_values['enable_password_recovery'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_SEND_PASSWORD_DESC').'</li>
					<li>
						<dl>
							<dt><label for="system_search_similar">'.$gL10n->get('ORG_SEARCH_SIMILAR_NAMES').':</label>
							</dt>
							<dd>
								<input type="checkbox" id="system_search_similar" name="system_search_similar" ';
								if(isset($form_values['system_search_similar']) && $form_values['system_search_similar'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_SEARCH_SIMILAR_NAMES_DESC').'</li>
					<li>
						<dl>
							<dt><label for="system_js_editor_enabled">'.$gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE').':</label></dt>
							<dd>
								<input type="checkbox" id="system_js_editor_enabled" name="system_js_editor_enabled" ';
								if(isset($form_values['system_js_editor_enabled']) && $form_values['system_js_editor_enabled'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE_DESC').'</li>
					<li>
						<dl>
							<dt><label for="system_js_editor_color">'.$gL10n->get('ORG_JAVASCRIPT_EDITOR_COLOR').':</label></dt>
							<dd><input type="text" id="system_js_editor_color" name="system_js_editor_color" style="width: 100px;" maxlength="10" value="'. $form_values['system_js_editor_color']. '" /></dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_JAVASCRIPT_EDITOR_COLOR_DESC').'</li>
				</ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
			</div>';
			
			/**************************************************************************************/
        	// Organization and regional settings
        	/**************************************************************************************/
			
			echo '<h3 id="ORG_ORGANIZATION_REGIONAL_SETTINGS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/world.png" alt="'.$gL10n->get('ORG_ORGANIZATION_REGIONAL_SETTINGS').'" title="'.$gL10n->get('ORG_ORGANIZATION_REGIONAL_SETTINGS').'" /></a>
				<a href="#">'.$gL10n->get('ORG_ORGANIZATION_REGIONAL_SETTINGS').'</a>
			</h3>
			<div class="groupBoxBody" style="display: none;">
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="org_shortname">'.$gL10n->get('SYS_NAME_ABBREVIATION').':</label></dt>
							<dd><input type="text" id="org_shortname" name="org_shortname" disabled="disabled" style="width: 100px;" maxlength="10" value="'. $form_values['org_shortname']. '" /></dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="org_longname">'.$gL10n->get('SYS_NAME').':</label></dt>
							<dd><input type="text" id="org_longname" name="org_longname" style="width: 200px;" maxlength="60" value="'. $form_values['org_longname']. '" /></dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="org_homepage">'.$gL10n->get('SYS_WEBSITE').':</label></dt>
							<dd><input type="text" id="org_homepage" name="org_homepage" style="width: 200px;" maxlength="60" value="'. $form_values['org_homepage']. '" /></dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="system_language">'.$gL10n->get('SYS_LANGUAGE').':</label></dt>
							<dd>'. FormElements::generateXMLSelectBox(SERVER_PATH.'/adm_program/languages/languages.xml', 'ISOCODE', 'NAME', 'system_language', $form_values['system_language']).'</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="system_date">'.$gL10n->get('ORG_DATE_FORMAT').':</label></dt>
							<dd><input type="text" id="system_date" name="system_date" style="width: 100px;" maxlength="20" value="'. $form_values['system_date']. '" /></dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_DATE_FORMAT_DESC', '<a href="http://www.php.net/date">date()</a>').'</li>
					<li>
						<dl>
							<dt><label for="system_time">'.$gL10n->get('ORG_TIME_FORMAT').':</label></dt>
							<dd><input type="text" id="system_time" name="system_time" style="width: 100px;" maxlength="20" value="'. $form_values['system_time']. '" /></dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_TIME_FORMAT_DESC', '<a href="http://www.php.net/date">date()</a>').'</li>
					<li>
						<dl>
							<dt><label for="system_currency">'.$gL10n->get('ORG_CURRENCY').':</label></dt>
							<dd><input type="text" id="system_currency" name="system_currency" style="width: 100px;" maxlength="20" value="'. $form_values['system_currency']. '" /></dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_CURRENCY_DESC').'</li>';

					//Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
					if($gCurrentOrganization->hasChildOrganizations() == false)
					{
						$organizationSelectBox = FormElements::generateOrganizationSelectBox($form_values['org_org_id_parent'], 'org_org_id_parent', 1);

						if(strlen($organizationSelectBox) > 0)
						{
							// Auswahlfeld fuer die uebergeordnete Organisation
							echo '
							<li>
								<dl>
									<dt><label for="org_org_id_parent">'.$gL10n->get('ORG_PARENT_ORGANIZATION').':</label></dt>
									<dd>'.$organizationSelectBox.'</dd>
								</dl>
							</li>
							<li class="smallFontSize">'.$gL10n->get('ORG_PARENT_ORGANIZATION_DESC').'</li>';
						}
					}
					
					if($gCurrentOrganization->countAllRecords() > 1)
					{
						echo '<li>
							<dl>
								<dt><label for="system_organization_select">'.$gL10n->get('ORG_SHOW_ORGANIZATION_SELECT').':</label></dt>
								<dd>
									<input type="checkbox" id="system_organization_select" name="system_organization_select" ';
									if(isset($form_values['system_organization_select']) && $form_values['system_organization_select'] == 1)
									{
										echo ' checked="checked" ';
									}
									echo ' value="1" />
								</dd>
							</dl>
						</li>
						<li class="smallFontSize">'.$gL10n->get('ORG_SHOW_ORGANIZATION_SELECT_DESC').'</li>';
					}
				echo '</ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
			</div>';
			
			/**************************************************************************************/
        	// Einstellungen Registrierung
        	/**************************************************************************************/
			
			echo '<h3 id="SYS_REGISTRATION" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" title="'.$gL10n->get('SYS_REGISTRATION').'" /></a>
				<a href="#">'.$gL10n->get('SYS_REGISTRATION').'</a>
			</h3>
			<div class="groupBoxBody" style="display: none;">
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="registration_mode">'.$gL10n->get('SYS_REGISTRATION').':</label></dt>
							<dd>';
								$selectBoxEntries = array(0 => $gL10n->get('SYS_DEACTIVATED'), 1 => $gL10n->get('ORG_FAST_REGISTRATION'), 2 => $gL10n->get('ORG_ADVANCED_REGISTRATION'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['registration_mode'], 'registration_mode');
							echo '</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_REGISTRATION_MODE').'</li>
					<li>
						<dl>
							<dt><label for="enable_registration_captcha">'.$gL10n->get('ORG_ENABLE_CAPTCHA').':</label></dt>
							<dd>
								<input type="checkbox" id="enable_registration_captcha" name="enable_registration_captcha" ';
								if(isset($form_values['enable_registration_captcha']) && $form_values['enable_registration_captcha'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_CAPTCHA_REGISTRATION').'</li>
					<li>
						<dl>
							<dt><label for="enable_registration_admin_mail">'.$gL10n->get('ORG_EMAIL_ALERTS').':</label></dt>
							<dd>
								<input type="checkbox" id="enable_registration_admin_mail" name="enable_registration_admin_mail" ';
								if(isset($form_values['enable_registration_admin_mail']) && $form_values['enable_registration_admin_mail'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('ORG_EMAIL_ALERTS_DESC', $gL10n->get('ROL_RIGHT_APPROVE_USERS')).'</li>
				</ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
			</div>';
			/**************************************************************************************/
			//Einstellungen Systemmails
			/**************************************************************************************/

			$text = new TableText($gDb);
			echo '<h3 id="SYS_SYSTEM_MAILS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/system_mail.png" alt="'.$gL10n->get('SYS_SYSTEM_MAILS').'" title="'.$gL10n->get('SYS_SYSTEM_MAILS').'" /></a>
            	<a href="#">'.$gL10n->get('SYS_SYSTEM_MAILS').'</a>
			</h3>        
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_system_mails">'.$gL10n->get('ORG_ACTIVATE_SYSTEM_MAILS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_system_mails" name="enable_system_mails" ';
                                if(isset($form_values['enable_system_mails']) && $form_values['enable_system_mails'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACTIVATE_SYSTEM_MAILS_DESC').'</li>                  
                    <li>
                        <dl>
                            <dt><label for="email_administrator">'.$gL10n->get('ORG_SYSTEM_MAIL_ADDRESS').':</label></dt>
                            <dd><input type="text" id="email_administrator" name="email_administrator" style="width: 200px;" maxlength="50" value="'. $form_values['email_administrator'].'" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_SYSTEM_MAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST']).'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_email_notification">'.$gL10n->get('ORG_SYSTEM_MAIL_NEW_ENTRIES').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_email_notification" name="enable_email_notification" ';
                                if(isset($form_values['enable_email_notification']) && $form_values['enable_email_notification'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_SYSTEM_MAIL_NEW_ENTRIES_DESC', '<i>'.$gPreferences['email_administrator'].'</i>').'</li>
                    <li>
                        <dl>
                            <dt><label>'.$gL10n->get('ORG_SYSTEM_MAIL_TEXTS').':</label></dt>
                            <dd><br /></dd>
                        </dl>
                    </li>
                    <li  class="smallFontSize">'.$gL10n->get('ORG_SYSTEM_MAIL_TEXTS_DESC').':<br />
                        <strong>%user_first_name%</strong> - '.$gL10n->get('ORG_VARIABLE_FIRST_NAME').'<br />
                        <strong>%user_last_name%</strong> - '.$gL10n->get('ORG_VARIABLE_LAST_NAME').'<br />
                        <strong>%user_login_name%</strong> - '.$gL10n->get('ORG_VARIABLE_USERNAME').'<br />
                        <strong>%user_email%</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL').'<br />
                        <strong>%webmaster_email%</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION').'<br />
                        <strong>%organization_short_name%</strong> - '.$gL10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION').'<br />
                        <strong>%organization_long_name%</strong> - '.$gL10n->get('ORG_VARIABLE_NAME_ORGANIZATION').'<br />
                        <strong>%organization_homepage%</strong> - '.$gL10n->get('ORG_VARIABLE_URL_ORGANIZATION').'<br /><br />
                    </li>';

                    $text->readData("SYSMAIL_REGISTRATION_USER");
                    echo '<li>
                        '.$gL10n->get('ORG_CONFIRM_REGISTRATION').':<br />
                        <textarea id="SYSMAIL_REGISTRATION_USER" name="SYSMAIL_REGISTRATION_USER" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_REGISTRATION_WEBMASTER");
                    echo '<li>
                        <br />'.$gL10n->get('ORG_NOTIFY_WEBMASTER').':<br />
                        <textarea id="SYSMAIL_REGISTRATION_WEBMASTER" name="SYSMAIL_REGISTRATION_WEBMASTER" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_NEW_PASSWORD");
                    echo '<li>
                        <br />'.$gL10n->get('ORG_SEND_NEW_PASSWORD').':<br />
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br />
                        <strong>%variable1%</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br />
                    </li>
                    <li>
                        <textarea id="SYSMAIL_NEW_PASSWORD" name="SYSMAIL_NEW_PASSWORD" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_ACTIVATION_LINK");
                    echo '<li>
                        <br />'.$gL10n->get('ORG_NEW_PASSWORD_ACTIVATION_LINK').':<br />
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br />
                        <strong>%variable1%</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br />
                        <strong>%variable2%</strong> - '.$gL10n->get('ORG_VARIABLE_ACTIVATION_LINK').'<br />
                    </li>
                    <li>
                        <textarea id="SYSMAIL_ACTIVATION_LINK" name="SYSMAIL_ACTIVATION_LINK" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';
			/**************************************************************************************/
			//Einstellungen Captcha
			/**************************************************************************************/
		
			echo '<h3 id="SYS_CAPTCHA" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/captcha.png" alt="'.$gL10n->get('SYS_CAPTCHA').'" title="'.$gL10n->get('SYS_CAPTCHA').'" /></a>
            	<a href="#">'.$gL10n->get('SYS_CAPTCHA').'</a>
			</h3>        
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li class="smallFontSize">'.$gL10n->get("ORG_CAPTCHA").'</li>
					<li>
                        <dl>
                            <dt><label for="captcha_type">'.$gL10n->get('ORG_CAPTCHA_TYPE').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('pic' => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'), 'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['captcha_type'], 'captcha_type');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get("ORG_CAPTCHA_TYPE_TEXT").'
                    </li>
					';
			if($gPreferences['captcha_type'] == 'pic')
			{
				echo '	<li>
                        <dl>
                            <dt><label for="captcha_fonts">'.$gL10n->get("SYS_FONT").':</label></dt>
                            <dd>
								<select size="1" id="captcha_fonts" name="captcha_fonts" style="width:120px;">
								';
								$fonts = getfilenames('../../system/fonts/');
								array_push($fonts,'Theme');
								asort($fonts);
								foreach($fonts as $myfonts)
								{
									if($myfonts == $form_values['captcha_fonts']){
									   $select = ' selected="selected"';
								    }
									else {
									   $select = '';
								    }
									echo '<option value="'.$myfonts.'"'.$select.'>'.$myfonts.'</option>
									';
								}
                             echo '</select>
							</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get('ORG_CAPTCHA_FONT').'
                    </li>					
                    <li>
                        <dl>
                            <dt><label for="captcha_font_size">'.$gL10n->get('SYS_FONT_SIZE').':</label></dt>
                            <dd>';
                                echo getMenueSettings(array ('9','10','11','12','13','14','15','16','17','18','20','22','24','30'),'captcha_font_size',$form_values['captcha_font_size'],'120','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$gL10n->get("ORG_CAPTCHA_FONT_SIZE").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_background_color">'.$gL10n->get("ORG_CAPTCHA_BACKGROUND_COLOR").':</label></dt>
                            <dd>
								<input type="text" id="captcha_background_color" name="captcha_background_color" style="width: 60px;" maxlength="7" value="'.$form_values['captcha_background_color'].'" />
							</dd>
                        </dl>
                    </li>
					<li class="smallFontSize">
                        '.$gL10n->get("ORG_CAPTCHA_BACKGROUND_COLOR_TEXT").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_width">'.$gL10n->get("ORG_CAPTCHA_SCALING").':</label></dt>
                            <dd><input type="text" id="captcha_width" name="captcha_width" style="width: 50px;" maxlength="4" value="'.$form_values['captcha_width'].'" />
                                x
                                <input type="text" id="captcha_height" name="captcha_height" style="width: 50px;" maxlength="4" value="'.$form_values['captcha_height'].'" />
                                '.$gL10n->get('ORG_PIXEL').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get("ORG_CAPTCHA_SIZE").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_signs">'.$gL10n->get("ORG_CAPTCHA_SIGNS").':</label></dt>
                            <dd>
                                <input type="text" id="captcha_signs" name="captcha_signs" maxlength="80" size="35" value="'.$form_values['captcha_signs'].'" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get("ORG_CAPTCHA_SIGNS_TEXT").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_signature">'.$gL10n->get("ORG_CAPTCHA_SIGNATURE").':</label></dt>
                            <dd>
                                <input type="text" id="captcha_signature" name="captcha_signature" maxlength="60" size="35" value="'.$form_values['captcha_signature'].'" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get("ORG_CAPTCHA_SIGNATURE_TEXT").'
                    </li>
					<li>
                        <dl>
                            <dt><label for="captcha_signature_font_size">'.$gL10n->get("SYS_FONT_SIZE").':</label></dt>
                            <dd>';
                                echo getMenueSettings(array ("9","10","11","12","13","14","15","16","17","18","20","22","24","30"),'captcha_signature_font_size',$form_values["captcha_signature_font_size"],'120','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$gL10n->get("ORG_CAPTCHA_SIGNATURE_FONT_SIZE").'
                    </li>';
			}
						
			if($gPreferences['captcha_type']=='pic')
			{
				$captcha_parameter = '&amp;type=pic';
			}
			else
			{
				$captcha_parameter = '';
			}

			echo '
					<li>
                        <dl>
                            <dt><label><a rel="colorboxHelp" href="captcha_preview.php?inline=true'.$captcha_parameter.'">'.$gL10n->get("ORG_CAPTCHA_PREVIEW").'</a></label></dt>
                            <dd>&nbsp;</dd>
                        </dl>
                    </li>
					<li class="smallFontSize">'.$gL10n->get('ORG_CAPTCHA_PREVIEW_TEXT').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
            </div>';

			/**************************************************************************************/
			//Systeminformationen
			/**************************************************************************************/

        	echo '<h3 id="ORG_SYSTEM_INFORMATIONS" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/info.png" alt="'.$gL10n->get('ORG_SYSTEM_INFOS').'" title="'.$gL10n->get('ORG_SYSTEM_INFOS').'" /></a>
            	<a href="#">'.$gL10n->get('ORG_SYSTEM_INFORMATIONS').'</a>
			</h3>  
			<div class="groupBoxBody" style="display: none;">';
				require_once('systeminfo.php');
				echo'<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';
			
			// ENDE accordion-common
			echo'</div>
		</div>
		<div id="tabs-modules">
			<div id="accordion-modules">';
			/**************************************************************************************/
        	//Einstellungen Ankuendigungsmodul
        	/**************************************************************************************/
			
			echo '<h3 id="ANN_ANNOUNCEMENTS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/announcements.png" alt="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" title="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" /></a>
				<a href="#">'.$gL10n->get('ANN_ANNOUNCEMENTS').'</a>
			</h3>
			<div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_announcements_module">'.$gL10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['enable_announcements_module'], 'enable_announcements_module');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="announcements_per_page">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="announcements_per_page" name="announcements_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['announcements_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
			</div>';
			
			/**************************************************************************************/
			//Einstellungen Downloadmodul
			/**************************************************************************************/

        	echo '<h3 id="DOW_DOWNLOADS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/download.png" alt="'.$gL10n->get('DOW_DOWNLOADS').'" title="'.$gL10n->get('DOW_DOWNLOADS').'" /></a>
            	<a href="#">'.$gL10n->get('DOW_DOWNLOADS').'</a>
			</h3>
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_download_module">'.$gL10n->get('DOW_ENABLE_DOWNLOAD_MODULE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_download_module" name="enable_download_module" ';
                                if(isset($form_values['enable_download_module']) && $form_values['enable_download_module'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DOW_ENABLE_DOWNLOAD_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="max_file_upload_size">'.$gL10n->get('DOW_MAXIMUM_FILE_SIZE').':</label></dt>
                            <dd>
                                <input type="text" id="max_file_upload_size" name="max_file_upload_size" style="width: 50px;"
                                    maxlength="10" value="'. $form_values['max_file_upload_size']. '" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DOW_MAXIMUM_FILE_SIZE_DESC').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';

			/**************************************************************************************/
			//Einstellungen Fotomodul
			/**************************************************************************************/

        	echo '<h3 id="PHO_PHOTOS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/photo.png" alt="'.$gL10n->get('PHO_PHOTOS').'" title="'.$gL10n->get('PHO_PHOTOS').'" /></a>
            	<a href="#">'.$gL10n->get('PHO_PHOTOS').'</a>
			</h3>			
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_photo_module">'.$gL10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['enable_photo_module'], 'enable_photo_module');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_show_mode">'.$gL10n->get('PHO_DISPLAY_PHOTOS').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('PHO_POPUP_WINDOW'), '1' => $gL10n->get('PHO_COLORBOX'), '2' => $gL10n->get('PHO_SAME_WINDOW'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['photo_show_mode'], 'photo_show_mode');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_DISPLAY_PHOTOS_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_slideshow_speed">'.$gL10n->get('PHO_SLIDESHOW_SPEED').':</label></dt>
                            <dd>
                                <input type="text" id="photo_slideshow_speed" name="photo_slideshow_speed" style="width: 50px;" maxlength="10" value="'. $form_values['photo_slideshow_speed']. '" /> '.$gL10n->get('ORG_SECONDS').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_SLIDESHOW_SPEED_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_upload_mode">'.$gL10n->get('PHO_MULTIUPLOAD').':</label></dt>
                            <dd>
                                <input type="checkbox" id="photo_upload_mode" name="photo_upload_mode" ';
                                if(isset($form_values['photo_upload_mode']) && $form_values['photo_upload_mode'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_MULTIUPLOAD_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_thumbs_row">'.$gL10n->get('PHO_THUMBNAILS_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="photo_thumbs_column" name="photo_thumbs_column" style="width: 50px;" maxlength="2" value="'. $form_values['photo_thumbs_column']. '" /> x
                                <input type="text" id="photo_thumbs_row" name="photo_thumbs_row" style="width: 50px;" maxlength="2" value="'. $form_values['photo_thumbs_row']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_THUMBNAILS_PER_PAGE_DESC').'</li>

                    <li>
                        <dl>
                            <dt><label for="photo_thumbs_scale">'.$gL10n->get('PHO_SCALE_THUMBNAILS').':</label></dt>
                            <dd>
                                <input type="text" id="photo_thumbs_scale" name="photo_thumbs_scale" style="width: 50px;" maxlength="4" value="'. $form_values['photo_thumbs_scale']. '" /> '.$gL10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_SCALE_THUMBNAILS_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_save_scale">'.$gL10n->get('PHO_SCALE_AT_UPLOAD').':</label></dt>
                            <dd>
                                <input type="text" id="photo_save_scale" name="photo_save_scale" style="width: 50px;" maxlength="4" value="'. $form_values['photo_save_scale']. '" /> '.$gL10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_SCALE_AT_UPLOAD_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_show_width">'.$gL10n->get('PHO_MAX_PHOTO_SIZE').':</label></dt>
                            <dd>
                                <input type="text" id="photo_show_width" name="photo_show_width" style="width: 50px;" maxlength="4" value="'. $form_values['photo_show_width']. '" /> x
                                <input type="text" id="photo_show_height" name="photo_show_height" style="width: 50px;" maxlength="4" value="'. $form_values['photo_show_height']. '" /> '.$gL10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_MAX_PHOTO_SIZE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_image_text">'.$gL10n->get('PHO_SHOW_CAPTION').':</label></dt>
                            <dd>
                                <input type="text" id="photo_image_text" name="photo_image_text" maxlength="60" value="'.$form_values['photo_image_text']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_SHOW_CAPTION_DESC' ,$gCurrentOrganization->getValue('org_homepage')).'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';

			/**************************************************************************************/
			//Einstellungen Forum
			/**************************************************************************************/

        	echo '<h3 id="SYS_FORUM" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/forum.png" alt="'.$gL10n->get('SYS_FORUM').'" title="'.$gL10n->get('SYS_FORUM').'" /></a>
            	<a href="#">'.$gL10n->get('SYS_FORUM').'</a>
			</h3>
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_forum_interface">'.$gL10n->get('ORG_ACTIVATE_FORUM').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_forum_interface" name="enable_forum_interface" ';
                                if(isset($form_values['enable_forum_interface']) && $form_values['enable_forum_interface'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACTIVATE_FORUM_DESC', $html_icon_warning).'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_version">'.$gL10n->get('ORG_USED_FORUM').':</label></dt>
                            <dd>
                                <select size="1" id="forum_version" name="forum_version">
                                    <option value="phpBB2" ';
                                    if($form_values['forum_version'] == 'phpBB2')
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>phpBB2</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_WHICH_FORUM_USED').'<br/>
                        <table summary="Forum_Auflistung" border="0">
                            <tr><td>1) "phpbb2"</td><td> - PHP Bulletin Board 2.x (Standard)</td></tr>
                        </table>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="forum_link_intern">'.$gL10n->get('ORG_ACTIVATE_FORUM_LINK_INTERN').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_link_intern" name="forum_link_intern" ';
                                if(isset($form_values['forum_link_intern']) && $form_values['forum_link_intern'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACTIVATE_FORUM_LINK_INTERN_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_width">'.$gL10n->get('ORG_FORUM_WIDTH').':</label></dt>
                            <dd>
                                <input type="text" id="forum_width" name="forum_width" maxlength="4" style="width: 50px;" value="'. $form_values['forum_width']. '" /> '.$gL10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_FORUM_WIDTH_DESC', $html_icon_warning).'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_export_user">'.$gL10n->get('ORG_EXPORT_ADMIDIO_USER').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_export_user" name="forum_export_user" ';
                                if(isset($form_values['forum_export_user']) && $form_values['forum_export_user'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_EXPORT_ADMIDIO_USER_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_set_admin">'.$gL10n->get('ORG_EXPORT_WEBMASTER_STATUS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_set_admin" name="forum_set_admin" ';
                                if(isset($form_values['forum_set_admin']) && $form_values['forum_set_admin'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_EXPORT_WEBMASTER_STATUS_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_praefix">'.$gL10n->get('ORG_FORUM_TABLE_PREFIX').':</label></dt>
                            <dd>
                                <input type="text" id="forum_praefix" name="forum_praefix" style="width: 50px;" value="'. $form_values['forum_praefix']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_FORUM_TABLE_PREFIX_DESC').'</li>
                    <li>
                        <dl>
                            <dt><strong>'.$gL10n->get('ORG_ACCESS_FORUM_DATABASE').'</strong></dt>
                            <dd>&nbsp;</dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="forum_sqldata_from_admidio">'.$gL10n->get('ORG_ACCESS_DATA_ADMIDIO').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_sqldata_from_admidio" name="forum_sqldata_from_admidio" onclick="javascript:organizationJS.drawForumAccessDataTable();" ';
                                if(isset($form_values['forum_sqldata_from_admidio']) && $form_values['forum_sqldata_from_admidio'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACCESS_DATA_ADMIDIO_DESC').'</li>
                    <li id="forum_access_data"></li>
                    <li id="forum_access_data_text" class="smallFontSize">'.$gL10n->get('ORG_ACCESS_FORUM_DATABASE_DESC').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';

			/**************************************************************************************/
			//Einstellungen Gaestebuchmodul
			/**************************************************************************************/

        	echo '<h3 id="GBO_GUESTBOOK" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/guestbook.png" alt="'.$gL10n->get('GBO_GUESTBOOK').'" title="'.$gL10n->get('GBO_GUESTBOOK').'" /></a>
            	<a href="#">'.$gL10n->get('GBO_GUESTBOOK').'</a>
			</h3>
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_guestbook_module">'.$gL10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['enable_guestbook_module'], 'enable_guestbook_module');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="guestbook_entries_per_page">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="guestbook_entries_per_page" name="guestbook_entries_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['guestbook_entries_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_guestbook_captcha">'.$gL10n->get('ORG_ENABLE_CAPTCHA').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_guestbook_captcha" name="enable_guestbook_captcha" ';
                                if(isset($form_values['enable_guestbook_captcha']) && $form_values['enable_guestbook_captcha'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('GBO_CAPTCHA_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_guestbook_moderation">'.$gL10n->get('GBO_GUESTBOOK_MODERATION').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_NOBODY'), '1' => $gL10n->get('GBO_ONLY_VISITORS'), '2' => $gL10n->get('SYS_ALL'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['enable_guestbook_moderation'], 'enable_guestbook_moderation');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('GBO_GUESTBOOK_MODERATION_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_gbook_comments4all">'.$gL10n->get('GBO_COMMENTS4ALL').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_gbook_comments4all" name="enable_gbook_comments4all" ';
                                if(isset($form_values['enable_gbook_comments4all']) && $form_values['enable_gbook_comments4all'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('GBO_COMMENTS4ALL_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_intial_comments_loading">'.$gL10n->get('GBO_INITIAL_COMMENTS_LOADING').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_intial_comments_loading" name="enable_intial_comments_loading" ';
                                if(isset($form_values['enable_intial_comments_loading']) && $form_values['enable_intial_comments_loading'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('GBO_INITIAL_COMMENTS_LOADING_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="flooding_protection_time">'.$gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL').':</label></dt>
                            <dd>
                                <input type="text" id="flooding_protection_time" name="flooding_protection_time" style="width: 50px;" 
                                    maxlength="4" value="'. $form_values['flooding_protection_time']. '" /> '.$gL10n->get('SYS_SECONDS').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL_DESC').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';

			/**************************************************************************************/
			//Einstellungen Listenmodul
			/**************************************************************************************/
	
			echo '<h3 id="LST_LISTS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/list.png" alt="'.$gL10n->get('LST_LISTS').'" title="'.$gL10n->get('LST_LISTS').'" /></a>
            	<a href="#">'.$gL10n->get('LST_LISTS').'</a>
			</h3>
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="lists_roles_per_page">'.$gL10n->get('LST_NUMBER_OF_ROLES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="lists_roles_per_page" name="lists_roles_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['lists_roles_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="lists_members_per_page">'.$gL10n->get('LST_MEMBERS_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="lists_members_per_page" name="lists_members_per_page" style="width: 50px;"
                                    maxlength="4" value="'. $form_values['lists_members_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('LST_MEMBERS_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="lists_hide_overview_details">'.$gL10n->get('LST_HIDE_DETAILS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="lists_hide_overview_details" name="lists_hide_overview_details" ';
                                if(isset($form_values['lists_hide_overview_details']) && $form_values['lists_hide_overview_details'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('LST_HIDE_DETAILS_DESC').'</li>                 
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';

			/**************************************************************************************/
			//Einstellungen Mailmodul
			/**************************************************************************************/

        	echo '<h3 id="MAI_EMAILS" class="iconTextLink" >
				<a href="#"><img src="'.THEME_PATH.'/icons/email.png" alt="'.$gL10n->get('MAI_EMAILS').'" title="'.$gL10n->get('MAI_EMAILS').'" /></a>
            	<a href="#">'.$gL10n->get('MAI_EMAILS').'</a>
			</h3>
			<div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_mail_module">'.$gL10n->get('MAI_ACTIVATE_EMAIL_MODULE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_mail_module" name="enable_mail_module" ';
                                if(isset($form_values['enable_mail_module']) && $form_values['enable_mail_module'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_ACTIVATE_EMAIL_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="mail_bcc_count">'.$gL10n->get('MAI_COUNT_BCC').':</label>
                            </dt>
                            <dd>
                                <input type="text" id="mail_bcc_count" name="mail_bcc_count" style="width: 50px;" maxlength="6" value="'. $form_values['mail_bcc_count']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_COUNT_BCC_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="mail_sender_into_to">'.$gL10n->get('MAI_SENDER_INTO_TO').':</label>
                            </dt>
                            <dd>
                                <input type="checkbox" id="mail_sender_into_to" name="mail_sender_into_to" ';
                                if(isset($form_values['mail_sender_into_to']) && $form_values['mail_sender_into_to'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_SENDER_INTO_TO_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_mail_captcha">'.$gL10n->get('ORG_ENABLE_CAPTCHA').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_mail_captcha" name="enable_mail_captcha" ';
                                if(isset($form_values['enable_mail_captcha']) && $form_values['enable_mail_captcha'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_SHOW_CAPTCHA_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="max_email_attachment_size">'.$gL10n->get('MAI_ATTACHMENT_SIZE').':</label></dt>
                            <dd>
                                <input type="text" id="max_email_attachment_size" name="max_email_attachment_size" style="width: 50px;" maxlength="6" value="'.$form_values['max_email_attachment_size'].'" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_ATTACHMENT_SIZE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="mail_sendmail_address">'.$gL10n->get('MAI_SENDER_EMAIL').':</label></dt>
                            <dd><input type="text" id="mail_sendmail_address" name="mail_sendmail_address" style="width: 200px;" maxlength="50" value="'. $form_values['mail_sendmail_address'].'" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_SENDER_EMAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST']).'</li>
					<li>
                        <dl>
                            <dt><label for="mail_character_encoding">'.$gL10n->get('MAI_CHARACTER_ENCODING').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['mail_character_encoding'], 'mail_character_encoding');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('MAI_CHARACTER_ENCODING_DESC').'</li>
					<li>
						<dl>
							<dt><label for="mail_html_registered_users">'.$gL10n->get('MAI_HTML_MAILS_REGISTERED_USERS').':</label></dt>
							<dd>
								<input type="checkbox" id="mail_html_registered_users" name="mail_html_registered_users" ';
								if(isset($form_values['mail_html_registered_users']) && $form_values['mail_html_registered_users'] == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
							</dd>
						</dl>
					</li>
					<li class="smallFontSize">'.$gL10n->get('MAI_HTML_MAILS_REGISTERED_USERS_DESC').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
            </div>';
			/**************************************************************************************/
			//Einstellungen Grußkartenmodul
			/**************************************************************************************/
        	echo '<h3 id="ECA_GREETING_CARDS" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/ecard.png" alt="'.$gL10n->get('ECA_GREETING_CARDS').'" title="'.$gL10n->get('ECA_GREETING_CARDS').'" /></a>
            	<a href="#">'.$gL10n->get('ECA_GREETING_CARDS').'</a>
			</h3>        
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_ecard_module">'.$gL10n->get("ECA_ACTIVATE_GREETING_CARDS").':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_ecard_module" name="enable_ecard_module" ';
                                if(isset($form_values["enable_ecard_module"]) && $form_values["enable_ecard_module"] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
						'.$gL10n->get("ECA_ACTIVATE_GREETING_CARDS_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_view_width">'.$gL10n->get("ECA_SCALING_PREVIEW").':</label></dt>
                            <dd><input type="text" id="ecard_view_width" name="ecard_view_width" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_view_width"].'" />
                                x
                                <input type="text" id="ecard_view_height" name="ecard_view_height" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_view_height"].'" />
                                '.$gL10n->get('ORG_PIXEL').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get("ECA_SCALING_PREVIEW_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_card_picture_width">'.$gL10n->get("ECA_SCALING_GREETING_CARD").':</label></dt>
                            <dd><input type="text" id="ecard_card_picture_width" name="ecard_card_picture_width" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_card_picture_width"].'" />
                                x
                                <input type="text" id="ecard_card_picture_height" name="ecard_card_picture_height" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_card_picture_height"].'" />
                                '.$gL10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$gL10n->get("ECA_SCALING_GREETING_CARD_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_cc_recipients">'.$gL10n->get("ECA_MAX_CC").':</label>
                            </dt>
                            <dd>
                            <select size="1" id="enable_ecard_cc_recipients" name="enable_ecard_cc_recipients" style="margin-right:20px;" onchange="javascript:organizationJS.showHideMoreSettings(\'cc_recipients_count\',\'enable_ecard_cc_recipients\',\'ecard_cc_recipients\',0);">
                                    <option value="0" ';
                                    if($form_values["enable_ecard_cc_recipients"] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$gL10n->get("SYS_DEACTIVATED").'</option>
                                    <option value="1" ';
                                    if($form_values["enable_ecard_cc_recipients"] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$gL10n->get("SYS_ACTIVATED").'</option>
                                </select>
                                <div id="cc_recipients_count" style="display:inline;">';
                                if($form_values["enable_ecard_cc_recipients"] == 1)
                                {
                                echo '<input type="text" id="ecard_cc_recipients" name="ecard_cc_recipients" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_cc_recipients"].'" />';
                                }
                            echo '</div>
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get("ECA_MAX_CC_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_template">'.$gL10n->get('ECA_TEMPLATE').':</label></dt>
                            <dd>';
                                echo getMenueSettings(getfilenames(THEME_SERVER_PATH.'/ecard_templates'),'ecard_template',$form_values['ecard_template'],'180','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$gL10n->get('ECA_TEMPLATE_DESC').'
                    </li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';        

			/**************************************************************************************/
			//Einstellungen Profilmodul
			/**************************************************************************************/

        	echo '<h3 id="PRO_PROFILE" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('PRO_PROFILE').'" title="'.$gL10n->get('PRO_PROFILE').'" /></a>
            	<a href="#">'.$gL10n->get('PRO_PROFILE').'</a>
			</h3>       		
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label>'.$gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS').':</label></dt>
                            <dd>
                                <div class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php"><img
                                    src="'. THEME_PATH. '/icons/application_form.png" alt="'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'" /></a>
                                    <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php">'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'</a>
                                </div>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS_DESC', '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />').'</li>
                    <li>
                        <dl>
                            <dt><label for="default_country">'.$gL10n->get('PRO_DEFAULT_COUNTRY').':</label></dt>
                            <dd>
                                <select size="1" id="default_country" name="default_country">
                                    <option value="">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
                                    foreach($gL10n->getCountries() as $key => $value)
                                    {
                                        echo '<option value="'.$key.'" ';
                                        if($key == $form_values['default_country'])
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$value.'</option>';
                                    }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_DEFAULT_COUNTRY_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_show_map_link">'.$gL10n->get('PRO_SHOW_MAP_LINK').':</label></dt>
                            <dd>
                                <input type="checkbox" id="profile_show_map_link" name="profile_show_map_link" ';
                                if(isset($form_values['profile_show_map_link']) && $form_values['profile_show_map_link'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_SHOW_MAP_LINK_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_show_roles">'.$gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP').':</label></dt>
                            <dd>
                                <input type="checkbox" id="profile_show_roles" name="profile_show_roles" ';
                                if(isset($form_values['profile_show_roles']) && $form_values['profile_show_roles'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_show_former_roles">'.$gL10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP').':</label></dt>
                            <dd>
                                <input type="checkbox" id="profile_show_former_roles" name="profile_show_former_roles" ';
                                if(isset($form_values['profile_show_former_roles']) && $form_values['profile_show_former_roles'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP_DESC').'</li>';

                    if($gCurrentOrganization->getValue('org_org_id_parent') > 0
                    || $gCurrentOrganization->hasChildOrganizations() )
                    {
                        echo '
                        <li>
                            <dl>
                                <dt><label for="profile_show_extern_roles">'.$gL10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS').':</label></dt>
                                <dd>
                                    <input type="checkbox" id="profile_show_extern_roles" name="profile_show_extern_roles" ';
                                    if(isset($form_values['profile_show_extern_roles']) && $form_values['profile_show_extern_roles'] == 1)
                                    {
                                        echo ' checked="checked" ';
                                    }
                                    echo ' value="1" />
                                </dd>
                            </dl>
                        </li>
                        <li class="smallFontSize">'.$gL10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC').'</li>';
                    }
                    echo '
                    <li>
                        <dl>
                            <dt><label for="profile_photo_storage">'.$gL10n->get('PRO_LOCATION_PROFILE_PICTURES').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['profile_photo_storage'], 'profile_photo_storage', true);
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_LOCATION_PROFILE_PICTURES_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_default_role">'.$gL10n->get('PRO_DEFAULT_ROLE').':</label></dt>
                            <dd>
                                '.FormElements::generateRoleSelectBox($gPreferences['profile_default_role'], 'profile_default_role').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PRO_DEFAULT_ROLE_DESC').'</li>                       
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';

			/**************************************************************************************/
			//Einstellungen Terminmodul
			/**************************************************************************************/

        	echo '<h3 id="DAT_DATES" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/dates.png" alt="'.$gL10n->get('DAT_DATES').'" title="'.$gL10n->get('DAT_DATES').'" /></a>
            	<a href="#">'.$gL10n->get('DAT_DATES').'</a>
			</h3>  
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_dates_module">'.$gL10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['enable_dates_module'], 'enable_dates_module');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_per_page">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="dates_per_page" name="dates_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['dates_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>                    
                    <li>
                        <dl>
                            <dt><label for="enable_dates_ical">'.$gL10n->get('DAT_ENABLE_ICAL').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_dates_ical" name="enable_dates_ical" ';
                                if(isset($form_values['enable_dates_ical']) && $form_values['enable_dates_ical'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_ENABLE_ICAL_DESC').'</li>                    
                    <li>
                        <dl>
                            <dt><label for="dates_ical_days_past">'.$gL10n->get('DAT_ICAL_DAYS_PAST').':</label></dt>
                            <dd>
                                <input type="text" id="dates_ical_days_past" name="dates_ical_days_past"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['dates_ical_days_past']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_ICAL_DAYS_PAST_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_ical_days_future">'.$gL10n->get('DAT_ICAL_DAYS_FUTURE').':</label></dt>
                            <dd>
                                <input type="text" id="dates_ical_days_future" name="dates_ical_days_future"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['dates_ical_days_future']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_ICAL_DAYS_FUTURE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_show_map_link">'.$gL10n->get('DAT_SHOW_MAP_LINK').':</label></dt>
                            <dd>
                                <input type="checkbox" id="dates_show_map_link" name="dates_show_map_link" ';
                                if(isset($form_values['dates_show_map_link']) && $form_values['dates_show_map_link'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_SHOW_MAP_LINK_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_show_calendar_select">'.$gL10n->get('DAT_SHOW_CALENDAR_SELECTION').':</label></dt>
                            <dd>
                                <input type="checkbox" id="dates_show_calendar_select" name="dates_show_calendar_select" ';
                                if($form_values['dates_show_calendar_select'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1"/>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_SHOW_CALENDAR_SELECTION_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_show_rooms">'.$gL10n->get('DAT_ROOM_SELECTABLE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="dates_show_rooms" name="dates_show_rooms" ';
                                if($form_values['dates_show_rooms'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1"/>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_ROOM_SELECTABLE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label>'.$gL10n->get('DAT_EDIT_ROOMS').':</label></dt>
                            <dd>
                                <div class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php"><img
                                    src="'. THEME_PATH. '/icons/home.png" alt="'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'" /></a>
                                    <a href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php">'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>
                                </div>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('DAT_EDIT_ROOMS_DESC', '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>                
        	</div>';

			/**************************************************************************************/
			//Einstellungen Weblinksmodul
			/**************************************************************************************/

        	echo '<h3 id="LNK_WEBLINKS" class="iconTextLink">
				<a href="#"><img src="'.THEME_PATH.'/icons/weblinks.png" alt="'.$gL10n->get('LNK_WEBLINKS').'" title="'.$gL10n->get('LNK_WEBLINKS').'" /></a>
            	<a href="#">'.$gL10n->get('LNK_WEBLINKS').'</a>
			</h3>  
            <div class="groupBoxBody" style="display: none;">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_weblinks_module">'.$gL10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['enable_weblinks_module'], 'enable_weblinks_module');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_per_page">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="weblinks_per_page" name="weblinks_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['weblinks_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_target">'.$gL10n->get('LNK_LINK_TARGET').':</label></dt>
                            <dd>';
								$selectBoxEntries = array('_self' => $gL10n->get('LNK_SAME_WINDOW'), '_blank' => $gL10n->get('LNK_NEW_WINDOW'));
								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['weblinks_target'], 'weblinks_target');
                            echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('LNK_LINK_TARGET_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_redirect_seconds">'.$gL10n->get('LNK_DISPLAY_REDIRECT').':</label></dt>
                            <dd><input type="text" id="weblinks_redirect_seconds" name="weblinks_redirect_seconds" style="width: 50px;" maxlength="4" value="'. $form_values['weblinks_redirect_seconds']. '" /> '.$gL10n->get('SYS_SECONDS').'</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('LNK_DISPLAY_REDIRECT_DESC').'</li>
                </ul>
				<br />
				<div class="formSubmit">	
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
        	</div>';
			// ENDE accordion-modules
			echo'</div>
		</div>
	</div>
	</form>
	</div>
</div>';

function getfilenames($directory)
{
	$array_files    = array();
	$i                = 0;
	if($curdir = opendir($directory))
	{
		while($file = readdir($curdir))
		{
			if($file != '.' && $file != '..')
			{
				$array_files[$i] = $file;
				$i++;
			}
		}
	}
	closedir($curdir);
	return $array_files;
}

// oeffnet ein File und gibt alle Zeilen als Array zurueck
// Uebergabe:
//            $filepath .. Der Pfad zu dem File
function getElementsFromFile($filepath)
{
	$elementsFromFile = array();
	$list = fopen($filepath, "r");
	while (!feof($list))
	{
		array_push($elementsFromFile,trim(fgets($list)));
	}
	return $elementsFromFile;
}

// gibt ein Menue fuer die Einstellungen des Grußkartenmoduls aus
// Uebergabe:
//             $data_array     .. Daten fuer die Einstellungen in einem Array
//            $name            .. Name des Drop down Menues
//            $first_value     .. der Standart Wert oder eingestellte Wert vom Benutzer
//            $width           .. die Groeße des Menues
//            $showFont        .. wenn gesetzt werden   die Menue Eintraege mit der übergebenen Schriftart dargestellt   (Darstellung der Schriftarten)
//            $showColor       .. wenn gesetzt bekommen die Menue Eintraege einen farbigen Hintergrund (Darstellung der Farben)
function getMenueSettings($data_array,$name,$first_value,$width,$showFont,$showColor)
{
	$temp_data = '';
	$temp_data .=  '<select size="1" id="'.$name.'" name="'.$name.'" style="width:'.$width.'px;">';
	for($i=0; $i<count($data_array);$i++)
	{
		$name = "";
		if(!is_integer($data_array[$i]) && strpos($data_array[$i],'.tpl') > 0)
		{
			$name = ucfirst(preg_replace("/[_-]/"," ",str_replace(".tpl","",$data_array[$i])));
		}
		elseif(is_integer($data_array[$i]))
		{
			$name = $data_array[$i];
		}
		else if(strpos($data_array[$i],'.') === false)
		{
			$name = $data_array[$i];
		}
		if($name != '')
		{
			if (strcmp($data_array[$i],$first_value) == 0 && $showFont != "true" && $showColor != "true")
			{
				$temp_data .= '<option value="'.$data_array[$i].'" selected="selected">'.$name.'</option>';
			}
			else if($showFont != "true" && $showColor != "true")
			{
				$temp_data .= '<option value="'.$data_array[$i].'">'.$name.'</option>';
			}
			else if (strcmp($data_array[$i],$first_value) == 0 && $showColor != 'true')
			{
				$temp_data .= '<option value="'.$data_array[$i].'" selected="selected" style="font-family:'.$name.';">'.$name.'</option>';
			}
			else if($showColor != "true")
			{
				$temp_data .= '<option value="'.$data_array[$i].'" style="font-family:'.$name.';">'.$name.'</option>';
			}
			else if (strcmp($data_array[$i],$first_value) == 0)
			{
				$temp_data .= '<option value="'.$data_array[$i].'" selected="selected" style="background-color:'.$name.';">'.$name.'</option>';
			}
			else
			{
				$temp_data .= '<option value="'.$data_array[$i].'" style="background-color:'.$name.';">'.$name.'</option>';
			}
		}
	}
	$temp_data .='</select>';
	return $temp_data;
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>