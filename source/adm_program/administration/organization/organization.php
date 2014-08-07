<?php
/******************************************************************************
 * Organization preferences
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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

// Initialize and check the parameters
$showOption = admFuncVariableIsValid($_GET, 'show_option', 'string');

$headline = $gL10n->get('SYS_SETTINGS');

// only webmasters are allowed to edit organization preferences
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$html_icon_warning = '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />';

// read organization values into form array
foreach($gCurrentOrganization->dbColumns as $key => $value)
{
    $form_values[$key] = $value;
}

// read all system preferences into form array
foreach($gPreferences as $key => $value)
{
    $form_values[$key] = $value;
}

// create html page object
$page = new HtmlPage();
$showOptionValidModules = array('announcements', 'downloads', 'guestbook', 'lists', 'messages', 'profile', 'events', 'links');

// open the modules tab if the options of a module should be shown 
if(in_array($showOption, $showOptionValidModules) == true)
{
    $page->addJavascript('$("#tabs_nav_modules").attr("class", "active");
        $("#tabs-modules").attr("class", "tab-pane active");
        $("#collapse_'.$showOption.'").attr("class", "panel-collapse collapse in");
        location.hash = "#" + "panel_'.$showOption.'";', true);
}
else
{
    $page->addJavascript('$("#tabs_nav_common").attr("class", "active");
        $("#tabs-common").attr("class", "tab-pane active");
        $("#collapse_'.$showOption.'").attr("class", "panel-collapse collapse in");
        location.hash = "#" + "panel_'.$showOption.'";', true);
}

if(strlen($showOption) > 0)
{
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
    // show back link
    $page->addHtml($gNavigation->getHtmlBackButton());
}
else
{
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline);
}

$page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        $("#"+id+" .form-alert").hide();

        // disable default form submit
        event.preventDefault();
        
        $.ajax({
            type:    "POST",
            url:     action,
            data:    $(this).serialize(),
            success: function(data) {
                if(data == "success") {
                    $("#"+id+" .form-alert").attr("class", "alert alert-success form-alert");
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    $("#"+id+" .form-alert").fadeIn("slow");
                    $("#"+id+" .form-alert").animate({opacity: 1.0}, 2500);
                    $("#"+id+" .form-alert").fadeOut("slow");
                }
                else {
                    $("#"+id+" .form-alert").attr("class", "alert alert-danger form-alert");
                    $("#"+id+" .form-alert").fadeIn();
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-remove\"></span>"+data);
                }
            }
        });    
    });
    
    $("#link_check_for_update").click(function() {
        $("#admidio_version_content").empty();
        $("#admidio_version_content").prepend("<img src=\''.THEME_PATH.'/icons/loader_inline.gif\' id=\'loadindicator\'/>").show();
        $.get("'.$g_root_path.'/adm_program/administration/organization/update_check.php", {mode:"2"}, function(htmlVersion){
            $("#admidio_version_content").empty();
            $("#admidio_version_content").append(htmlVersion);               
        });
        return false;
    });    ', true);

// add headline and title of module
$page->addHeadline($headline);

$page->addHtml('
<ul class="nav nav-tabs" id="preferences_tabs">
  <li id="tabs_nav_common"><a href="#tabs-common" data-toggle="tab">'.$gL10n->get('SYS_COMMON').'</a></li>
  <li id="tabs_nav_modules"><a href="#tabs-modules" data-toggle="tab">'.$gL10n->get('SYS_MODULES').'</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane" id="tabs-common">
        <div class="panel-group" id="accordion_common">
            <div class="panel panel-default" id="panel_common">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_common">
                            <img src="'.THEME_PATH.'/icons/options.png" alt="'.$gL10n->get('SYS_COMMON').'" title="'.$gL10n->get('SYS_COMMON').'" />'.$gL10n->get('SYS_COMMON').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_common" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('common_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=common', $page, 'default', false, 'form-preferences');
                        
                        // search all available themes in theme folder
                        $themes = getDirectoryEntries(SERVER_PATH.'/adm_themes', 'dir');
                        $form->addSelectBox('theme', $gL10n->get('ORG_ADMIDIO_THEME'), $themes, FIELD_DEFAULT, $form_values['theme'], true, false, null, 'ORG_ADMIDIO_THEME_DESC');
                        $form->addTextInput('homepage_logout', $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('SYS_VISITORS').')', $form_values['homepage_logout'], 
                            250, FIELD_DEFAULT, 'text', null, 'ORG_HOMEPAGE_VISITORS');
                        $form->addTextInput('homepage_login', $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('ORG_REGISTERED_USERS').')', $form_values['homepage_login'], 
                            250, FIELD_DEFAULT, 'text', null, 'ORG_HOMEPAGE_REGISTERED_USERS');
                        $form->addCheckbox('enable_rss', $gL10n->get('ORG_ENABLE_RSS_FEEDS'), $form_values['enable_rss'], FIELD_DEFAULT, null, 'ORG_ENABLE_RSS_FEEDS_DESC');
                        $form->addCheckbox('enable_auto_login', $gL10n->get('ORG_LOGIN_AUTOMATICALLY'), $form_values['enable_auto_login'], FIELD_DEFAULT, null, 'ORG_LOGIN_AUTOMATICALLY_DESC');
                        $form->addTextInput('logout_minutes', $gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER'), $form_values['logout_minutes'], 
                            4, FIELD_DEFAULT, 'number', null, array('ORG_AUTOMATOC_LOGOUT_AFTER_DESC', 'SYS_REMEMBER_ME'));
                        $form->addCheckbox('enable_password_recovery', $gL10n->get('ORG_SEND_PASSWORD'), $form_values['enable_password_recovery'], FIELD_DEFAULT, null, 'ORG_SEND_PASSWORD_DESC');
                        $form->addCheckbox('system_search_similar', $gL10n->get('ORG_SEARCH_SIMILAR_NAMES'), $form_values['system_search_similar'], FIELD_DEFAULT, null, 'ORG_SEARCH_SIMILAR_NAMES_DESC');
                        $selectBoxEntries = array(0 => $gL10n->get('SYS_DONT_SHOW'), 1 => $gL10n->get('SYS_FIRSTNAME_LASTNAME'), 2 => $gL10n->get('SYS_USERNAME'));
                        $form->addSelectBox('system_show_create_edit', $gL10n->get('ORG_SHOW_CREATE_EDIT'), $selectBoxEntries, FIELD_DEFAULT, $form_values['system_show_create_edit'], false, false, null, 'ORG_SHOW_CREATE_EDIT_DESC');
                        $form->addCheckbox('system_js_editor_enabled', $gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE'), $form_values['system_js_editor_enabled'], FIELD_DEFAULT, null, 'ORG_JAVASCRIPT_EDITOR_ENABLE_DESC');
                        $form->addTextInput('system_js_editor_color', $gL10n->get('ORG_JAVASCRIPT_EDITOR_COLOR'), $form_values['system_js_editor_color'], 
                            10, FIELD_DEFAULT, 'text', null, array('ORG_JAVASCRIPT_EDITOR_COLOR_DESC', 'SYS_REMEMBER_ME'), null, 'form-control-small');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_regional_settings">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_regional_settings">
                            <img src="'.THEME_PATH.'/icons/world.png" alt="'.$gL10n->get('ORG_ORGANIZATION_REGIONAL_SETTINGS').'" title="'.$gL10n->get('ORG_ORGANIZATION_REGIONAL_SETTINGS').'" />'.$gL10n->get('ORG_ORGANIZATION_REGIONAL_SETTINGS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_regional_settings" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('regional_settings_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=regional_settings', $page, 'default', false, 'form-preferences');
                        $form->addStaticControl('org_shortname', $gL10n->get('SYS_NAME_ABBREVIATION'), $form_values['org_shortname'], 
                            null, null, null, 'form-control-small');
                        $form->addTextInput('org_longname', $gL10n->get('SYS_NAME'), $form_values['org_longname'], 60);
                        $form->addTextInput('org_homepage', $gL10n->get('SYS_WEBSITE'), $form_values['org_homepage'], 60, FIELD_DEFAULT, 'url');
                        $form->addSelectBoxFromXml('system_language', $gL10n->get('SYS_LANGUAGE'), SERVER_PATH.'/adm_program/languages/languages.xml', 
                            'ISOCODE', 'NAME', FIELD_DEFAULT, $form_values['system_language'], true);
                        $form->addSelectBox('default_country', $gL10n->get('PRO_DEFAULT_COUNTRY'), $gL10n->getCountries(), FIELD_DEFAULT, $form_values['default_country'], true, false, null, 'PRO_DEFAULT_COUNTRY_DESC');
                        $form->addTextInput('system_date', $gL10n->get('ORG_DATE_FORMAT'), $form_values['system_date'], 20, FIELD_DEFAULT, 'text', 
                            null, array('ORG_DATE_FORMAT_DESC', '<a href="http://www.php.net/date">date()</a>'), null, 'form-control-small');
                        $form->addTextInput('system_time', $gL10n->get('ORG_TIME_FORMAT'), $form_values['system_time'], 20, FIELD_DEFAULT, 'text', 
                            null, array('ORG_TIME_FORMAT_DESC', '<a href="http://www.php.net/date">date()</a>'), null, 'form-control-small');
                        $form->addTextInput('system_currency', $gL10n->get('ORG_CURRENCY'), $form_values['system_currency'], 20, FIELD_DEFAULT, 'text', 
                            null, 'ORG_CURRENCY_DESC', null, 'form-control-small');
                            
                        //Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
                        if($gCurrentOrganization->hasChildOrganizations() == false)
                        {
                            $sql = 'SELECT org_id, org_longname FROM '. TBL_ORGANIZATIONS.'
                                     WHERE org_id <> '. $gCurrentOrganization->getValue('org_id'). '
				                       AND org_org_id_parent is NULL
				                     ORDER BY org_longname ASC, org_shortname ASC';
				            $form->addSelectBoxFromSql('org_org_id_parent', $gL10n->get('ORG_PARENT_ORGANIZATION'), $gDb, $sql, FIELD_DEFAULT, 
				                $form_values['org_org_id_parent'], false, false, null, 'ORG_PARENT_ORGANIZATION_DESC');
                        }

                        if($gCurrentOrganization->countAllRecords() > 1)
                        {
                            $form->addCheckbox('system_organization_select', $gL10n->get('ORG_SHOW_ORGANIZATION_SELECT'), $form_values['system_organization_select'], 
                                FIELD_DEFAULT, null, 'ORG_SHOW_ORGANIZATION_SELECT_DESC');
                        }
                        
                        $form->addCheckbox('system_show_all_users', $gL10n->get('ORG_SHOW_ALL_USERS'), $form_values['system_show_all_users'], 
                            FIELD_DEFAULT, null, 'ORG_SHOW_ALL_USERS_DESC');
                        $html = '<a class="icon-text-link" href="'. $g_root_path. '/adm_program/administration/organization/organization_function.php?mode=2"><img
                                    src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'" />'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'</a>';
                        $htmlDesc = $gL10n->get('ORG_ADD_ORGANIZATION_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent('add_another_organization', $gL10n->get('ORG_NEW_ORGANIZATION'), $html, null, $htmlDesc);
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_registration">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_registration">
                            <img src="'.THEME_PATH.'/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" title="'.$gL10n->get('SYS_REGISTRATION').'" />'.$gL10n->get('SYS_REGISTRATION').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_registration" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('registration_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=registration', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array(0 => $gL10n->get('SYS_DEACTIVATED'), 1 => $gL10n->get('ORG_FAST_REGISTRATION'), 2 => $gL10n->get('ORG_ADVANCED_REGISTRATION'));
                        $form->addSelectBox('registration_mode', $gL10n->get('SYS_REGISTRATION'), $selectBoxEntries, FIELD_DEFAULT, $form_values['registration_mode'], false, false, null, 'ORG_REGISTRATION_MODE');
                        $form->addCheckbox('enable_registration_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), $form_values['enable_registration_captcha'], 
                            FIELD_DEFAULT, null, 'ORG_CAPTCHA_REGISTRATION');
                        $form->addCheckbox('enable_registration_admin_mail', $gL10n->get('ORG_EMAIL_ALERTS'), $form_values['enable_registration_admin_mail'], 
                            FIELD_DEFAULT, null, array('ORG_EMAIL_ALERTS_DESC', 'ROL_RIGHT_APPROVE_USERS'));
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_email_dispatch">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_email_dispatch">
                            <img src="'.THEME_PATH.'/icons/system_mail.png" alt="'.$gL10n->get('SYS_MAIL_DISPATCH').'" title="'.$gL10n->get('SYS_MAIL_DISPATCH').'" />'.$gL10n->get('SYS_MAIL_DISPATCH').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_email_dispatch" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('email_dispatch_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=email_dispatch', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array('phpmail' => $gL10n->get('MAI_PHP_MAIL'), 'SMTP' => $gL10n->get('MAI_SMTP'));
                        $form->addSelectBox('mail_send_method', $gL10n->get('MAI_SEND_METHOD'), $selectBoxEntries, FIELD_DEFAULT, $form_values['mail_send_method'], false, false, null, 'MAI_SEND_METHOD_DESC');
                        $form->addTextInput('mail_bcc_count', $gL10n->get('MAI_COUNT_BCC'), $form_values['mail_bcc_count'], 6, FIELD_DEFAULT, 'number', null, 'MAI_COUNT_BCC_DESC');
                        $form->addCheckbox('mail_sender_into_to', $gL10n->get('MAI_SENDER_INTO_TO'), $form_values['mail_sender_into_to'], 
                            FIELD_DEFAULT, null, 'MAI_SENDER_INTO_TO_DESC');
                        $selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
                        $form->addSelectBox('mail_character_encoding', $gL10n->get('MAI_CHARACTER_ENCODING'), $selectBoxEntries, FIELD_DEFAULT, $form_values['mail_character_encoding'], false, false, null, 'MAI_CHARACTER_ENCODING_DESC');
                        $form->addTextInput('mail_smtp_host', $gL10n->get('MAI_SMTP_HOST'), $form_values['mail_smtp_host'], 50, FIELD_DEFAULT, 'text', null, 'MAI_SMTP_HOST_DESC');
                        $form->addCheckbox('mail_smtp_auth', $gL10n->get('MAI_SMTP_AUTH'), $form_values['mail_smtp_auth'], 
                            FIELD_DEFAULT, null, 'MAI_SMTP_AUTH_DESC');
                        $form->addTextInput('mail_smtp_port', $gL10n->get('MAI_SMTP_PORT'), $form_values['mail_smtp_port'], 4, FIELD_DEFAULT, 'number', null, 'MAI_SMTP_PORT_DESC');
                        $selectBoxEntries = array('' => $gL10n->get('MAI_SMTP_SECURE_NO'), 'ssl' => $gL10n->get('MAI_SMTP_SECURE_SSL'), 'tls' => $gL10n->get('MAI_SMTP_SECURE_TLS'));
                        $form->addSelectBox('mail_smtp_secure', $gL10n->get('MAI_SMTP_SECURE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['mail_smtp_secure'], false, false, null, 'MAI_SMTP_SECURE_DESC');
                        $selectBoxEntries = array('LOGIN' => $gL10n->get('MAI_SMTP_AUTH_LOGIN'), 'PLAIN' => $gL10n->get('MAI_SMTP_AUTH_PLAIN'), 'NTLM' => $gL10n->get('MAI_SMTP_AUTH_NTLM'));
                        $form->addSelectBox('mail_smtp_authentication_type', $gL10n->get('MAI_SMTP_AUTH_TYPE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['mail_smtp_authentication_type'], false, false, null, 'MAI_SMTP_AUTH_TYPE_DESC');
                        $form->addTextInput('mail_smtp_user', $gL10n->get('MAI_SMTP_USER'), $form_values['mail_smtp_user'], 100, FIELD_DEFAULT, 'text', null, 'MAI_SMTP_USER_DESC');
                        $form->addTextInput('mail_smtp_password', $gL10n->get('MAI_SMTP_PASSWORD'), $form_values['mail_smtp_password'], 50, FIELD_DEFAULT, 'password', null, 'MAI_SMTP_PASSWORD_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_system_notification">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_system_notification">
                            <img src="'.THEME_PATH.'/icons/system_notification.png" alt="'.$gL10n->get('SYS_SYSTEM_MAILS').'" title="'.$gL10n->get('SYS_SYSTEM_MAILS').'" />'.$gL10n->get('SYS_SYSTEM_MAILS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_system_notification" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $text = new TableText($gDb);
                        $form = new HtmlForm('system_notification_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=system_notification', $page, 'default', false, 'form-preferences');
                        $form->addCheckbox('enable_system_mails', $gL10n->get('ORG_ACTIVATE_SYSTEM_MAILS'), $form_values['enable_system_mails'], 
                            FIELD_DEFAULT, null, 'ORG_ACTIVATE_SYSTEM_MAILS_DESC');
                        $form->addTextInput('email_administrator', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $form_values['email_administrator'], 50, FIELD_DEFAULT, 'email', 
                            null, array('ORG_SYSTEM_MAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST']));
                        $form->addCheckbox('enable_email_notification', $gL10n->get('ORG_SYSTEM_MAIL_NEW_ENTRIES'), $form_values['enable_email_notification'], 
                            FIELD_DEFAULT, null, array('ORG_SYSTEM_MAIL_NEW_ENTRIES_DESC', '<i>'.$gPreferences['email_administrator'].'</i>'));
                        $form->addCustomContent('system_mail_text_description', $gL10n->get('ORG_SYSTEM_MAIL_TEXTS'), 
                            '<p>'.$gL10n->get('ORG_SYSTEM_MAIL_TEXTS_DESC').':</p>
                            <p><strong>%user_first_name%</strong> - '.$gL10n->get('ORG_VARIABLE_FIRST_NAME').'<br />
                            <strong>%user_last_name%</strong> - '.$gL10n->get('ORG_VARIABLE_LAST_NAME').'<br />
                            <strong>%user_login_name%</strong> - '.$gL10n->get('ORG_VARIABLE_USERNAME').'<br />
                            <strong>%user_email%</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL').'<br />
                            <strong>%webmaster_email%</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION').'<br />
                            <strong>%organization_short_name%</strong> - '.$gL10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION').'<br />
                            <strong>%organization_long_name%</strong> - '.$gL10n->get('ORG_VARIABLE_NAME_ORGANIZATION').'<br />
                            <strong>%organization_homepage%</strong> - '.$gL10n->get('ORG_VARIABLE_URL_ORGANIZATION').'</p>');
                            
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_WEBMASTER', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_REGISTRATION_WEBMASTER', $gL10n->get('ORG_NOTIFY_WEBMASTER'), $text->getValue('txt_text'), 7);
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_USER', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_REGISTRATION_USER', $gL10n->get('ORG_CONFIRM_REGISTRATION'), $text->getValue('txt_text'), 7);
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REFUSE_REGISTRATION', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_REFUSE_REGISTRATION', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_NEW_PASSWORD', $gL10n->get('ORG_SEND_NEW_PASSWORD'), $text->getValue('txt_text'), 7, 0, FIELD_DEFAULT, null,
                            $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br /><strong>%variable1%</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD'));
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_ACTIVATION_LINK', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_ACTIVATION_LINK', $gL10n->get('ORG_NEW_PASSWORD_ACTIVATION_LINK'), $text->getValue('txt_text'), 7, 0, FIELD_DEFAULT, null,
                            $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br />
                            <strong>%variable1%</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br />
                            <strong>%variable2%</strong> - '.$gL10n->get('ORG_VARIABLE_ACTIVATION_LINK'));
                        
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_captcha">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_captcha">
                            <img src="'.THEME_PATH.'/icons/captcha.png" alt="'.$gL10n->get('SYS_CAPTCHA').'" title="'.$gL10n->get('SYS_CAPTCHA').'" />'.$gL10n->get('SYS_CAPTCHA').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_captcha" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('captcha_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=captcha', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array('pic' => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'), 'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'));
                        $form->addSelectBox('captcha_type', $gL10n->get('ORG_CAPTCHA_TYPE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['captcha_type'], false, false, null, 'ORG_CAPTCHA_TYPE_TEXT');
                        
                        $fonts = getDirectoryEntries('../../system/fonts/');
                        $fonts['Theme'] = 'Theme';
                        asort($fonts);
                        $form->addSelectBox('captcha_fonts', $gL10n->get('SYS_FONT'), $fonts, FIELD_DEFAULT, $form_values['captcha_fonts'], false, false, null, 'ORG_CAPTCHA_FONT');
                        $selectBoxEntries = array ('9','10','11','12','13','14','15','16','17','18','20','22','24','30');
                        $form->addSelectBox('captcha_font_size', $gL10n->get('SYS_FONT_SIZE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['captcha_font_size'], false, false, null, 'ORG_CAPTCHA_FONT_SIZE');
                        $form->addTextInput('captcha_background_color', $gL10n->get('ORG_CAPTCHA_BACKGROUND_COLOR'), $form_values['captcha_background_color'], 7, FIELD_DEFAULT, 'text', null, 'ORG_CAPTCHA_BACKGROUND_COLOR_TEXT', null, 'form-control-small');
                        $form->addTextInput('captcha_width', $gL10n->get('ORG_CAPTCHA_WIDTH').' ('.$gL10n->get('ORG_PIXEL').')', $form_values['captcha_width'], 4, FIELD_DEFAULT, 'number', null, 'ORG_CAPTCHA_WIDTH_DESC');
                        $form->addTextInput('captcha_height', $gL10n->get('ORG_CAPTCHA_HEIGHT').' ('.$gL10n->get('ORG_PIXEL').')', $form_values['captcha_height'], 4, FIELD_DEFAULT, 'number', null, 'ORG_CAPTCHA_HEIGHT_DESC');
                        $form->addTextInput('captcha_signs', $gL10n->get('ORG_CAPTCHA_SIGNS'), $form_values['captcha_signs'], 80, FIELD_DEFAULT, 'text', null, 'ORG_CAPTCHA_SIGNS_TEXT');
                        $form->addTextInput('captcha_signature', $gL10n->get('ORG_CAPTCHA_SIGNATURE'), $form_values['captcha_signature'], 60, FIELD_DEFAULT, 'text', null, 'ORG_CAPTCHA_SIGNATURE_TEXT');
                        $selectBoxEntries = array ('9','10','11','12','13','14','15','16','17','18','20','22','24','30');
                        $form->addSelectBox('captcha_signature_font_size', $gL10n->get('SYS_FONT_SIZE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['captcha_signature_font_size'], false, false, null, 'ORG_CAPTCHA_SIGNATURE_FONT_SIZE');

                        if($gPreferences['captcha_type']=='pic')
                        {
                            $captcha_parameter = '&amp;type=pic';
                        }
                        else
                        {
                            $captcha_parameter = '';
                        }
                        $html = '<a class="icon-text-link colorbox-dialog" href="captcha_preview.php?inline=true'.$captcha_parameter.'"><img
                                    src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('SYS_PREVIEW').'" />'.$gL10n->get('SYS_PREVIEW').'</a>';
                        $form->addCustomContent('preview_captcha', $gL10n->get('ORG_CAPTCHA_PREVIEW'), $html, null, 'ORG_CAPTCHA_PREVIEW_TEXT');

                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_system_informations">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_common" href="#collapse_system_informations">
                            <img src="'.THEME_PATH.'/icons/info.png" alt="'.$gL10n->get('ORG_SYSTEM_INFOS').'" title="'.$gL10n->get('ORG_SYSTEM_INFOS').'" />'.$gL10n->get('ORG_SYSTEM_INFOS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_system_informations" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // create a static form
                        $form = new HtmlForm('system_informations_preferences_form', null, $page);
                        $html = '<span id="admidio_version_content">'.ADMIDIO_VERSION. BETA_VERSION_TEXT.'
                                    <a id="link_check_for_update" href="#link_check_for_update" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
                                 </span>';
                        $form->addCustomContent('admidio_version', $gL10n->get('SYS_ADMIDIO_VERSION'), $html);
                        
                        // if database version is different to file version, then show database version
                        if(strcmp(ADMIDIO_VERSION, $gSystemComponent->getValue('com_version')) != 0)
                        {
                            $form->addStaticControl('database_version', $gL10n->get('ORG_DIFFERENT_DATABASE_VERSION'), $gSystemComponent->getValue('com_version'));
                        }
                        $form->addStaticControl('last_update_step', $gL10n->get('ORG_LAST_UPDATE_STEP'), $gSystemComponent->getValue('com_update_step'));

                        if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
                        {
                            $html = '<span class="text-danger"><strong>'.phpversion().'</strong></span> &rarr; '.$gL10n->get('SYS_PHP_VERSION_REQUIRED', MIN_PHP_VERSION);
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.phpversion().'</strong></span>';
                        }
                        $form->addCustomContent('php_version', $gL10n->get('SYS_PHP_VERSION'), $html);

                        if(version_compare($gDb->getVersion(), $gDb->getMinVersion()) == -1)
                        {
                            $html = '<span class="text-danger"><strong>'.$gDb->getVersion().'</strong></span> &rarr; '.$gL10n->get('SYS_DATABASE_VERSION_REQUIRED', $gDb->getMinVersion());
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.$gDb->getVersion().'</strong></span>';
                        }
                        $form->addCustomContent('database_version', $gDb->getName().'-'.$gL10n->get('SYS_VERSION'), $html);
                        
                        if(ini_get('safe_mode') == 1)
                        {
                            $html = '<span class="text-danger"><strong>'.$gL10n->get('SYS_ON').'</strong></span> &rarr; '.$gL10n->get('SYS_SAFE_MODE_PROBLEM');
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.$gL10n->get('SYS_OFF').'</strong></span>';
                        }
                        $form->addCustomContent('safe_mode', $gL10n->get('SYS_SAFE_MODE'), $html);
                        
                        if(ini_get('post_max_size')!='')
                        {
                            $form->addStaticControl('post_max_size', $gL10n->get('SYS_POST_MAX_SIZE'), ini_get('post_max_size'));
                        }
                        else
                        {
                            $form->addStaticControl('post_max_size', $gL10n->get('SYS_POST_MAX_SIZE'), $gL10n->get('SYS_NOT_SET'));
                        }

                        if(ini_get('memory_limit')!='')
                        {
                            $form->addStaticControl('memory_limit', $gL10n->get('SYS_MEMORY_LIMIT'), ini_get('memory_limit'));
                        }
                        else
                        {
                            $form->addStaticControl('memory_limit', $gL10n->get('SYS_MEMORY_LIMIT'), $gL10n->get('SYS_NOT_SET'));
                        }

                        if(ini_get('file_uploads') == 1)
                        {
                            $html = '<span class="text-success"><strong>'.$gL10n->get('SYS_ON').'</strong></span>';
                        }
                        else
                        {
                            $html = '<span class="text-danger"><strong>'.$gL10n->get('SYS_OFF').'</strong></span>';
                        }
                        $form->addCustomContent('file_uploads', $gL10n->get('SYS_FILE_UPLOADS'), $html);
        
                        if(ini_get('upload_max_filesize')!='')
                        {
                            $form->addStaticControl('upload_max_filesize', $gL10n->get('SYS_UPLOAD_MAX_FILESIZE'), ini_get('upload_max_filesize'));
                        }
                        else
                        {
                            $form->addStaticControl('upload_max_filesize', $gL10n->get('SYS_UPLOAD_MAX_FILESIZE'), $gL10n->get('SYS_NOT_SET'));
                        }

                        $form->addStaticControl('max_processable_image_size', $gL10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE'), round((admFuncProcessableImageSize()/1000000), 2).' '.$gL10n->get('SYS_MEGA_PIXEL'));
                        $html = '<a href="organization_function.php?mode=4" target="_blank">phpinfo()</a>';
                        $form->addCustomContent('php_info', $gL10n->get('SYS_PHP_INFO'), $html);

                        if(isset($gDebug))
                        {
                            $html = '<span class="text-danger"><strong>'.$gL10n->get('SYS_ON').'</strong></span>';
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.$gL10n->get('SYS_OFF').'</strong></span>';
                        }
                        $form->addCustomContent('debug_modus', $gL10n->get('SYS_DEBUG_MODUS'), $html);
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane" id="tabs-modules">
        <div class="panel-group" id="accordion_modules">
            <div class="panel panel-default" id="panel_announcements">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_announcements">
                            <img src="'.THEME_PATH.'/icons/announcements.png" alt="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" title="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" />'.$gL10n->get('ANN_ANNOUNCEMENTS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_announcements" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('announcements_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=announcements', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_announcements_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['enable_announcements_module'], false, false, null, 'ORG_ACCESS_TO_MODULE_DESC');
                        $form->addTextInput('announcements_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['announcements_per_page'], 4, FIELD_DEFAULT, 'number', 
                            null, 'ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_downloads">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_downloads">
                            <img src="'.THEME_PATH.'/icons/download.png" alt="'.$gL10n->get('DOW_DOWNLOADS').'" title="'.$gL10n->get('DOW_DOWNLOADS').'" />'.$gL10n->get('DOW_DOWNLOADS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_downloads" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('downloads_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=downloads', $page, 'default', false, 'form-preferences');
                        $form->addCheckbox('enable_download_module', $gL10n->get('DOW_ENABLE_DOWNLOAD_MODULE'), $form_values['enable_download_module'], 
                            FIELD_DEFAULT, null, 'DOW_ENABLE_DOWNLOAD_MODULE_DESC');
                        $form->addTextInput('max_file_upload_size', $gL10n->get('DOW_MAXIMUM_FILE_SIZE').' (KB)', $form_values['max_file_upload_size'], 10, FIELD_DEFAULT, 'number', 
                            null, 'DOW_MAXIMUM_FILE_SIZE_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_guestbook">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_guestbook">
                            <img src="'.THEME_PATH.'/icons/guestbook.png" alt="'.$gL10n->get('GBO_GUESTBOOK').'" title="'.$gL10n->get('GBO_GUESTBOOK').'" />'.$gL10n->get('GBO_GUESTBOOK').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_guestbook" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('guestbook_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=guestbook', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_guestbook_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['enable_guestbook_module'], false, false, null, 'ORG_ACCESS_TO_MODULE_DESC');
                        $form->addTextInput('guestbook_entries_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['guestbook_entries_per_page'], 4, FIELD_DEFAULT, 'number', null, 'ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC');
                        $form->addCheckbox('enable_guestbook_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), $form_values['enable_guestbook_captcha'], FIELD_DEFAULT, null, 'GBO_CAPTCHA_DESC');
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_NOBODY'), '1' => $gL10n->get('GBO_ONLY_VISITORS'), '2' => $gL10n->get('SYS_ALL'));
                        $form->addSelectBox('enable_guestbook_moderation', $gL10n->get('GBO_GUESTBOOK_MODERATION'), $selectBoxEntries, FIELD_DEFAULT, $form_values['enable_guestbook_moderation'], false, false, null, 'GBO_GUESTBOOK_MODERATION_DESC');
                        $form->addCheckbox('enable_gbook_comments4all', $gL10n->get('GBO_COMMENTS4ALL'), $form_values['enable_gbook_comments4all'], FIELD_DEFAULT, null, 'GBO_COMMENTS4ALL_DESC');
                        $form->addCheckbox('enable_intial_comments_loading', $gL10n->get('GBO_INITIAL_COMMENTS_LOADING'), $form_values['enable_intial_comments_loading'], FIELD_DEFAULT, null, 'GBO_INITIAL_COMMENTS_LOADING_DESC');
                        $form->addTextInput('flooding_protection_time', $gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL'), $form_values['flooding_protection_time'], 4, FIELD_DEFAULT, 'number', null, 'GBO_FLOODING_PROTECTION_INTERVALL_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_lists">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_lists">
                            <img src="'.THEME_PATH.'/icons/list.png" alt="'.$gL10n->get('LST_LISTS').'" title="'.$gL10n->get('LST_LISTS').'" />'.$gL10n->get('LST_LISTS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_lists" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('lists_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=lists', $page, 'default', false, 'form-preferences');
                        $form->addTextInput('lists_roles_per_page', $gL10n->get('LST_NUMBER_OF_ROLES_PER_PAGE'), $form_values['lists_roles_per_page'], 10, FIELD_DEFAULT, 'number', null, 'ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC');
                        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
                        $form->addSelectBox('lists_members_per_page', $gL10n->get('LST_MEMBERS_PER_PAGE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['lists_members_per_page'], false, false, null, 'LST_MEMBERS_PER_PAGE_DESC');
                        $form->addCheckbox('lists_hide_overview_details', $gL10n->get('LST_HIDE_DETAILS'), $form_values['lists_hide_overview_details'], FIELD_DEFAULT, null, 'LST_HIDE_DETAILS_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_messages">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_messages">
                            <img src="'.THEME_PATH.'/icons/email.png" alt="'.$gL10n->get('SYS_MESSAGES').'" title="'.$gL10n->get('SYS_MESSAGES').'" />'.$gL10n->get('SYS_MESSAGES').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_messages" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('messages_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=messages', $page, 'default', false, 'form-preferences');
                        $form->addCheckbox('enable_mail_module', $gL10n->get('MAI_ACTIVATE_EMAIL_MODULE'), $form_values['enable_mail_module'], FIELD_DEFAULT, null, 'MAI_ACTIVATE_EMAIL_MODULE_DESC');
                        $form->addCheckbox('enable_pm_module', $gL10n->get('MSG_ACTIVATE_PM_MODULE'), $form_values['enable_pm_module'], FIELD_DEFAULT, null, 'MSG_ACTIVATE_PM_MODULE_DESC');
                        $form->addCheckbox('enable_mail_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), $form_values['enable_mail_captcha'], FIELD_DEFAULT, null, 'MAI_SHOW_CAPTCHA_DESC');
                        $form->addTextInput('max_email_attachment_size', $gL10n->get('MAI_ATTACHMENT_SIZE').' (KB)', $form_values['max_email_attachment_size'], 6, FIELD_DEFAULT, 'number', null, 'MAI_ATTACHMENT_SIZE_DESC');
                        $form->addTextInput('mail_sendmail_address', $gL10n->get('MAI_SENDER_EMAIL'), $form_values['mail_sendmail_address'], 50, FIELD_DEFAULT, 'text', null, array('MAI_SENDER_EMAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST']));
                        $form->addTextInput('mail_sendmail_name', $gL10n->get('MAI_SENDER_NAME'), $form_values['mail_sendmail_name'], 50, FIELD_DEFAULT, 'text', null, 'MAI_SENDER_NAME_DESC');
                        $form->addCheckbox('mail_html_registered_users', $gL10n->get('MAI_HTML_MAILS_REGISTERED_USERS'), $form_values['mail_html_registered_users'], FIELD_DEFAULT, null, 'MAI_HTML_MAILS_REGISTERED_USERS_DESC');
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('mail_delivery_confirmation', $gL10n->get('MAI_DELIVERY_CONFIRMATION'), $selectBoxEntries, FIELD_DEFAULT, $form_values['mail_delivery_confirmation'], false, false, null, 'MAI_DELIVERY_CONFIRMATION_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_profile">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_profile">
                            <img src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('PRO_PROFILE').'" title="'.$gL10n->get('PRO_PROFILE').'" />'.$gL10n->get('PRO_PROFILE').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_profile" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('profile_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=profile', $page, 'default', false, 'form-preferences');
                        $html = '<a class="icon-text-link" href="'. $g_root_path. '/adm_program/administration/organization/fields.php"><img
                                    src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'" />'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'</a>';
                        $htmlDesc = $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent('maintain_profile_fields', $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), $html, null, $htmlDesc);
                        $form->addCheckbox('profile_log_edit_fields', $gL10n->get('PRO_LOG_EDIT_FIELDS'), $form_values['profile_log_edit_fields'], FIELD_DEFAULT, null, 'PRO_LOG_EDIT_FIELDS_DESC');
                        $form->addCheckbox('profile_show_map_link', $gL10n->get('PRO_SHOW_MAP_LINK'), $form_values['profile_show_map_link'], FIELD_DEFAULT, null, 'PRO_SHOW_MAP_LINK_DESC');
                        $form->addCheckbox('profile_show_roles', $gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP'), $form_values['profile_show_roles'], FIELD_DEFAULT, null, 'PRO_SHOW_ROLE_MEMBERSHIP_DESC');
                        $form->addCheckbox('profile_show_former_roles', $gL10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP'), $form_values['profile_show_former_roles'], FIELD_DEFAULT, null, 'PRO_SHOW_FORMER_ROLE_MEMBERSHIP_DESC');

                        if($gCurrentOrganization->getValue('org_org_id_parent') > 0
                        || $gCurrentOrganization->hasChildOrganizations() )
                        {
                            $form->addCheckbox('profile_show_extern_roles', $gL10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS'), $form_values['profile_show_extern_roles'], FIELD_DEFAULT, null, 'PRO_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC');
                        }

                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
                        $form->addSelectBox('profile_photo_storage', $gL10n->get('PRO_LOCATION_PROFILE_PICTURES'), $selectBoxEntries, FIELD_DEFAULT, $form_values['profile_photo_storage'], false, false, null, 'PRO_LOCATION_PROFILE_PICTURES_DESC');
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_events">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_events">
                            <img src="'.THEME_PATH.'/icons/dates.png" alt="'.$gL10n->get('DAT_DATES').'" title="'.$gL10n->get('DAT_DATES').'" />'.$gL10n->get('DAT_DATES').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_events" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('events_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=events', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_dates_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['enable_dates_module'], false, false, null, 'ORG_ACCESS_TO_MODULE_DESC');
                        $selectBoxEntries = array('html' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'));
                        $form->addSelectBox('dates_viewmode', $gL10n->get('DAT_VIEW_MODE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['dates_viewmode'], false, false, null, array('DAT_VIEW_MODE_DESC', 'DAT_VIEW_MODE_DETAIL', 'DAT_VIEW_MODE_COMPACT'));
                        $form->addTextInput('dates_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['dates_per_page'], 4, FIELD_DEFAULT, 'number', null, 'ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC');
                        $form->addCheckbox('enable_dates_ical', $gL10n->get('DAT_ENABLE_ICAL'), $form_values['enable_dates_ical'], FIELD_DEFAULT, null, 'DAT_ENABLE_ICAL_DESC');
                        $form->addTextInput('dates_ical_days_past', $gL10n->get('DAT_ICAL_DAYS_PAST'), $form_values['dates_ical_days_past'], 4, FIELD_DEFAULT, 'number', null, 'DAT_ICAL_DAYS_PAST_DESC');
                        $form->addTextInput('dates_ical_days_future', $gL10n->get('DAT_ICAL_DAYS_FUTURE'), $form_values['dates_ical_days_future'], 4, FIELD_DEFAULT, 'number', null, 'DAT_ICAL_DAYS_FUTURE_DESC');
                        $form->addCheckbox('dates_show_map_link', $gL10n->get('DAT_SHOW_MAP_LINK'), $form_values['dates_show_map_link'], FIELD_DEFAULT, null, 'DAT_SHOW_MAP_LINK_DESC');
                        $html = '<a class="icon-text-link" href="'. $g_root_path. '/adm_program/administration/categories/categories.php?type=DAT&title='.$gL10n->get('DAT_CALENDAR').'"><img
                                    src="'. THEME_PATH. '/icons/application_view_tile.png" alt="'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_EDIT_CALENDAR_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent('manage_calendars', $gL10n->get('DAT_MANAGE_CALENDARS'), $html, null, $htmlDesc);
                        $form->addCheckbox('dates_show_rooms', $gL10n->get('DAT_ROOM_SELECTABLE'), $form_values['dates_show_rooms'], FIELD_DEFAULT, null, 'DAT_ROOM_SELECTABLE_DESC');
                        $html = '<a class="icon-text-link" href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php"><img
                                    src="'. THEME_PATH. '/icons/home.png" alt="'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_EDIT_ROOMS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent('edit_rooms', $gL10n->get('DAT_EDIT_ROOMS'), $html, null, $htmlDesc);
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_links">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_links">
                            <img src="'.THEME_PATH.'/icons/weblinks.png" alt="'.$gL10n->get('LNK_WEBLINKS').'" title="'.$gL10n->get('LNK_WEBLINKS').'" />'.$gL10n->get('LNK_WEBLINKS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_links" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('links_preferences_form', $g_root_path.'/adm_program/administration/organization/organization_function.php?form=links', $page, 'default', false, 'form-preferences');
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_weblinks_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, FIELD_DEFAULT, $form_values['enable_weblinks_module'], false, false, null, 'ORG_ACCESS_TO_MODULE_DESC');
                        $form->addTextInput('weblinks_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['weblinks_per_page'], 4, FIELD_DEFAULT, 'number', null, 'ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC');
                        $selectBoxEntries = array('_self' => $gL10n->get('LNK_SAME_WINDOW'), '_blank' => $gL10n->get('LNK_NEW_WINDOW'));
                        $form->addSelectBox('weblinks_target', $gL10n->get('LNK_LINK_TARGET'), $selectBoxEntries, FIELD_DEFAULT, $form_values['weblinks_target'], false, false, null, 'LNK_LINK_TARGET_DESC');
                        $form->addTextInput('weblinks_redirect_seconds', $gL10n->get('LNK_DISPLAY_REDIRECT'), $form_values['weblinks_redirect_seconds'], 4, FIELD_DEFAULT, 'number', null, 'LNK_DISPLAY_REDIRECT_DESC');
                        $html = '<a class="icon-text-link" href="'. $g_root_path. '/adm_program/administration/categories/categories.php?type=LNK"><img
                                    src="'. THEME_PATH. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent('maintain_links_categories', $gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html, null, $htmlDesc);
                        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png', null, ' col-sm-offset-3');                    
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
        </div>
    </div>
</div>
');

$page->show();

exit();
            
            
            /**************************************************************************************/
            // Preferences photo module
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
                    <li>
                        <dl>
                            <dt><label for="photo_download_enabled">'.$gL10n->get('PHO_DOWNLOAD_ENABLED').':</label></dt>
                            <dd>
                                <input type="checkbox" id="photo_download_enabled" name="photo_download_enabled" ';
                                if(isset($form_values['photo_download_enabled']) && $form_values['photo_download_enabled'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_DOWNLOAD_ENABLED_DESC', $gL10n->get('PHO_KEEP_ORIGINAL')).'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_keep_original">'.$gL10n->get('PHO_KEEP_ORIGINAL').':</label></dt>
                            <dd>
                                <input type="checkbox" id="photo_keep_original" name="photo_keep_original" ';
                                if(isset($form_values['photo_keep_original']) && $form_values['photo_keep_original'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$gL10n->get('PHO_KEEP_ORIGINAL_DESC', $gL10n->get('PHO_DOWNLOAD_ENABLED')).'</li>
                 </ul>
                <br />
                <div class="formSubmit">    
                    <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
                </div>
            </div>';

            /**************************************************************************************/
            // Preferences ecards module
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
                                echo getMenueSettings(getDirectoryEntries(THEME_SERVER_PATH.'/ecard_templates'),'ecard_template',$form_values['ecard_template'],'180','false','false');
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
            </div>        

            </div>';
            // ENDE accordion-modules
            echo'</div>
        </div>
    </div>
    </form>
    </div>
</div>';

/** Search all files or directories in the specified directory.
 *  @param $directory  The directory where the files or directories should be searched.
 *  @param $searchType This could be @b file or @b dir and represent the type of entries that should be searched.
 *  @return Returns an array with all found entries.
 */
function getDirectoryEntries($directory, $searchType = 'file')
{
    $array_files = array();
    
    if($curdir = opendir($directory))
    {
        while($filename = readdir($curdir))
        {
            if(strpos($filename, '.') !== 0)
            {
                if(($searchType == 'file' && is_file($directory.'/'.$filename) == true)
                || ($searchType == 'dir'  && is_dir($directory.'/'.$filename) == true))
                {
                    $array_files[$filename] = $filename;
                }
            }
        }
    }
    closedir($curdir);
    asort($array_files);
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