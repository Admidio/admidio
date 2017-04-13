<?php
/**
 ***********************************************************************************************
 * Organization preferences
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * show_option : show preferences of module with this text id
 *               Example: SYS_COMMON or
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$showOption = admFuncVariableIsValid($_GET, 'show_option', 'string');

$headline = $gL10n->get('SYS_SETTINGS');

// only administrators are allowed to edit organization preferences
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

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
$page = new HtmlPage($headline);
$page->enableModal();

$showOptionValidModules = array('announcements', 'downloads', 'guestbook', 'ecards', 'lists', 'messages',
                                'photos', 'profile', 'events', 'links', 'user_management');

// open the modules tab if the options of a module should be shown
if(in_array($showOption, $showOptionValidModules, true))
{
    $page->addJavascript('
        $("#tabs_nav_modules").attr("class", "active");
        $("#tabs-modules").attr("class", "tab-pane active");
        $("#collapse_'.$showOption.'").attr("class", "panel-collapse collapse in");
        location.hash = "#" + "panel_'.$showOption.'";',
        true
    );
}
else
{
    $page->addJavascript('
        $("#tabs_nav_common").attr("class", "active");
        $("#tabs-common").attr("class", "tab-pane active");
        $("#collapse_'.$showOption.'").attr("class", "panel-collapse collapse in");
        location.hash = "#" + "panel_'.$showOption.'";',
        true
    );
}

$page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        $("#"+id+" .form-alert").hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url:     action,
            data:    $(this).serialize(),
            success: function(data) {
                if (data === "success") {
                    if (id === "captcha_preferences_form") {
                        // reload captcha if form is saved
                        $("#captcha").attr("src", "' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php?" + Math.random());
                    }
                    $("#"+id+" .form-alert").attr("class", "alert alert-success form-alert");
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    $("#"+id+" .form-alert").fadeIn("slow");
                    $("#"+id+" .form-alert").animate({opacity: 1.0}, 2500);
                    $("#"+id+" .form-alert").fadeOut("slow");
                } else {
                    $("#"+id+" .form-alert").attr("class", "alert alert-danger form-alert");
                    $("#"+id+" .form-alert").fadeIn();
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>" + data);
                }
            }
        });
    });

    $("#link_check_for_update").click(function() {
        $("#admidio_version_content").empty();
        $("#admidio_version_content").prepend("<img src=\''.THEME_URL.'/icons/loader_inline.gif\' id=\'loadindicator\'/>").show();
        $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/preferences/update_check.php", {mode:"2"}, function(htmlVersion) {
            $("#admidio_version_content").empty();
            $("#admidio_version_content").append(htmlVersion);
        });
        return false;
    });    ', true);

if($showOption !== '')
{
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // add back link to module menu
    $preferencesMenu = $page->getMenu();
    $preferencesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}
else
{
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline);
}

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
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_common">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/options.png" alt="'.$gL10n->get('SYS_COMMON').'" />'.$gL10n->get('SYS_COMMON').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_common" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('common_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=common', $page, array('class' => 'form-preferences'));

                        // search all available themes in theme folder
                        $themes = admFuncGetDirectoryEntries(ADMIDIO_PATH . FOLDER_THEMES, 'dir');
                        if (!is_array($themes))
                        {
                            $gMessage->show($gL10n->get('ECA_TEMPLATE_FOLDER_OPEN'));
                            // => EXIT
                        }
                        $form->addSelectBox('theme', $gL10n->get('ORG_ADMIDIO_THEME'), $themes, array('property' => FIELD_REQUIRED, 'defaultValue' => $form_values['theme'], 'helpTextIdInline' => 'ORG_ADMIDIO_THEME_DESC'));
                        $form->addInput('homepage_logout', $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('SYS_VISITORS').')', $form_values['homepage_logout'],
                            array('maxLength' => 250, 'property' => FIELD_REQUIRED, 'helpTextIdInline' => 'ORG_HOMEPAGE_VISITORS'));
                        $form->addInput('homepage_login', $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('ORG_REGISTERED_USERS').')', $form_values['homepage_login'],
                            array('maxLength' => 250, 'property' => FIELD_REQUIRED, 'helpTextIdInline' => 'ORG_HOMEPAGE_REGISTERED_USERS'));
                        $form->addCheckbox('enable_rss', $gL10n->get('ORG_ENABLE_RSS_FEEDS'), (bool) $form_values['enable_rss'], array('helpTextIdInline' => 'ORG_ENABLE_RSS_FEEDS_DESC'));
                        $form->addCheckbox('enable_auto_login', $gL10n->get('ORG_LOGIN_AUTOMATICALLY'), (bool) $form_values['enable_auto_login'], array('helpTextIdInline' => 'ORG_LOGIN_AUTOMATICALLY_DESC'));
                        $form->addInput('logout_minutes', $gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER'), $form_values['logout_minutes'],
                            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_AUTOMATOC_LOGOUT_AFTER_DESC', 'SYS_REMEMBER_ME')));
                        $form->addCheckbox('enable_password_recovery', $gL10n->get('ORG_SEND_PASSWORD'), (bool) $form_values['enable_password_recovery'], array('helpTextIdInline' => 'ORG_SEND_PASSWORD_DESC'));
                        $form->addCheckbox('system_search_similar', $gL10n->get('ORG_SEARCH_SIMILAR_NAMES'), (bool) $form_values['system_search_similar'], array('helpTextIdInline' => 'ORG_SEARCH_SIMILAR_NAMES_DESC'));
                        $selectBoxEntries = array(0 => $gL10n->get('SYS_DONT_SHOW'), 1 => $gL10n->get('SYS_FIRSTNAME_LASTNAME'), 2 => $gL10n->get('SYS_USERNAME'));
                        $form->addSelectBox('system_show_create_edit', $gL10n->get('ORG_SHOW_CREATE_EDIT'), $selectBoxEntries, array('defaultValue' => $form_values['system_show_create_edit'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_SHOW_CREATE_EDIT_DESC'));
                        $form->addCheckbox('system_js_editor_enabled', $gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE'), (bool) $form_values['system_js_editor_enabled'], array('helpTextIdInline' => 'ORG_JAVASCRIPT_EDITOR_ENABLE_DESC'));
                        $form->addInput('system_js_editor_color', $gL10n->get('ORG_JAVASCRIPT_EDITOR_COLOR'), $form_values['system_js_editor_color'],
                            array('maxLength' => 10, 'helpTextIdInline' => array('ORG_JAVASCRIPT_EDITOR_COLOR_DESC', 'SYS_REMEMBER_ME'), 'class' => 'form-control-small'));
                        $selectBoxEntries = array(
                            0 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_NO'),
                            1 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_LOW'),
                            2 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_MID'),
                            3 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_HIGH'),
                            4 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_VERY_HIGH')
                        );
                        $form->addSelectBox('password_min_strength', $gL10n->get('ORG_PASSWORD_MIN_STRENGTH'), $selectBoxEntries, array('defaultValue' => $form_values['password_min_strength'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_PASSWORD_MIN_STRENGTH_DESC'));
                        $form->addCheckbox('system_browser_update_check', $gL10n->get('ORG_BROWSER_UPDATE_CHECK'), (bool) $form_values['system_browser_update_check'], array('helpTextIdInline' => 'ORG_BROWSER_UPDATE_CHECK_DESC'));
                        $form->addSubmitButton('btn_save_common', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_organization">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_organization">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/chart_organisation.png" alt="'.$gL10n->get('SYS_ORGANIZATION').'" />'.$gL10n->get('SYS_ORGANIZATION').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_organization" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('organization_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=organization', $page, array('class' => 'form-preferences'));
                        $form->addInput('org_shortname', $gL10n->get('SYS_NAME_ABBREVIATION'), $form_values['org_shortname'], array('property' => FIELD_DISABLED, 'class' => 'form-control-small'));
                        $form->addInput('org_longname', $gL10n->get('SYS_NAME'), $form_values['org_longname'], array('maxLength' => 60, 'property' => FIELD_REQUIRED));
                        $form->addInput('org_homepage', $gL10n->get('SYS_WEBSITE'), $form_values['org_homepage'], array('maxLength' => 60));

                        // Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
                        if(!$gCurrentOrganization->hasChildOrganizations())
                        {
                            $sql = 'SELECT org_id, org_longname
                                      FROM '.TBL_ORGANIZATIONS.'
                                     WHERE org_id <> '. $gCurrentOrganization->getValue('org_id'). '
                                       AND org_org_id_parent IS NULL
                                  ORDER BY org_longname ASC, org_shortname ASC';
                            $form->addSelectBoxFromSql('org_org_id_parent', $gL10n->get('ORG_PARENT_ORGANIZATION'), $gDb, $sql, array('defaultValue'     => $form_values['org_org_id_parent'],
                                                                                                                                      'helpTextIdInline' => 'ORG_PARENT_ORGANIZATION_DESC'));
                        }

                        if($gCurrentOrganization->countAllRecords() > 1)
                        {
                            $form->addCheckbox('system_organization_select', $gL10n->get('ORG_SHOW_ORGANIZATION_SELECT'), (bool) $form_values['system_organization_select'], array('helpTextIdInline' => 'ORG_SHOW_ORGANIZATION_SELECT_DESC'));
                        }

                        $html = '<a id="add_another_organization" class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/preferences/preferences_function.php?mode=2"><img
                                    src="'. THEME_URL. '/icons/add.png" alt="'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'" />'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'</a>';
                        $htmlDesc = $gL10n->get('ORG_ADD_ORGANIZATION_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('ORG_NEW_ORGANIZATION'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addSubmitButton('btn_save_organization', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_regional_settings">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_regional_settings">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/world.png" alt="'.$gL10n->get('ORG_REGIONAL_SETTINGS').'" />'.$gL10n->get('ORG_REGIONAL_SETTINGS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_regional_settings" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('regional_settings_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=regional_settings', $page, array('class' => 'form-preferences'));
                        $form->addInput('system_timezone', $gL10n->get('ORG_TIMEZONE'), $gTimezone, array('property' => FIELD_DISABLED, 'class' => 'form-control-small', 'helpTextIdInline' => 'ORG_TIMEZONE_DESC'));
                        $form->addSelectBox('system_language', $gL10n->get('SYS_LANGUAGE'), $gL10n->getAvailableLanguages(),
                                            array('property' => FIELD_REQUIRED, 'defaultValue' => $form_values['system_language']));
                        $form->addSelectBox('default_country', $gL10n->get('PRO_DEFAULT_COUNTRY'), $gL10n->getCountries(),
                                            array('defaultValue' => $form_values['default_country'], 'helpTextIdInline' => 'PRO_DEFAULT_COUNTRY_DESC'));
                        $form->addInput('system_date', $gL10n->get('ORG_DATE_FORMAT'), $form_values['system_date'], array('maxLength'        => 20,
                                                                                                                          'helpTextIdInline' => array('ORG_DATE_FORMAT_DESC', '<a href="https://secure.php.net/manual/en/function.date.php">date()</a>'),
                                                                                                                          'class'            => 'form-control-small'));
                        $form->addInput('system_time', $gL10n->get('ORG_TIME_FORMAT'), $form_values['system_time'], array('maxLength'        => 20,
                                                                                                                          'helpTextIdInline' => array('ORG_TIME_FORMAT_DESC', '<a href="https://secure.php.net/manual/en/function.date.php">date()</a>'),
                                                                                                                          'class'            => 'form-control-small'));
                        $form->addInput('system_currency', $gL10n->get('ORG_CURRENCY'), $form_values['system_currency'], array('maxLength' => 20, 'helpTextIdInline' => 'ORG_CURRENCY_DESC', 'class' => 'form-control-small'));
                        $form->addSubmitButton('btn_save_regional_settings', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_registration">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_registration">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" />'.$gL10n->get('SYS_REGISTRATION').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_registration" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('registration_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=registration', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array(0 => $gL10n->get('SYS_DEACTIVATED'), 1 => $gL10n->get('ORG_FAST_REGISTRATION'), 2 => $gL10n->get('ORG_ADVANCED_REGISTRATION'));
                        $form->addSelectBox('registration_mode', $gL10n->get('SYS_REGISTRATION'), $selectBoxEntries, array('defaultValue' => $form_values['registration_mode'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_REGISTRATION_MODE'));
                        $form->addCheckbox('enable_registration_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), (bool) $form_values['enable_registration_captcha'], array('helpTextIdInline' => 'ORG_CAPTCHA_REGISTRATION'));
                        $form->addCheckbox('enable_registration_admin_mail', $gL10n->get('ORG_EMAIL_ALERTS'), (bool) $form_values['enable_registration_admin_mail'], array('helpTextIdInline' => array('ORG_EMAIL_ALERTS_DESC', 'ROL_RIGHT_APPROVE_USERS')));
                        $form->addSubmitButton('btn_save_registration', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_email_dispatch">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_email_dispatch">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/system_mail.png" alt="'.$gL10n->get('SYS_MAIL_DISPATCH').'" />'.$gL10n->get('SYS_MAIL_DISPATCH').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_email_dispatch" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('email_dispatch_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=email_dispatch', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('phpmail' => $gL10n->get('MAI_PHP_MAIL'), 'SMTP' => $gL10n->get('MAI_SMTP'));
                        $form->addSelectBox('mail_send_method', $gL10n->get('MAI_SEND_METHOD'), $selectBoxEntries, array('defaultValue' => $form_values['mail_send_method'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_SEND_METHOD_DESC'));
                        $form->addInput('mail_sendmail_address', $gL10n->get('MAI_SENDER_EMAIL'), $form_values['mail_sendmail_address'], array('maxLength' => 50, 'helpTextIdInline' => array('MAI_SENDER_EMAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST'])));
                        $form->addInput('mail_sendmail_name', $gL10n->get('MAI_SENDER_NAME'), $form_values['mail_sendmail_name'], array('maxLength' => 50, 'helpTextIdInline' => 'MAI_SENDER_NAME_DESC'));
                        $selectBoxEntries = array(0 => $gL10n->get('MAI_HIDDEN'), 1 => $gL10n->get('MAI_SENDER'), 2 => $gL10n->get('SYS_ADMINISTRATOR'));
                        $form->addSelectBox('mail_recipients_with_roles', $gL10n->get('MAI_RECIPIENTS_WITH_ROLES'), $selectBoxEntries, array('defaultValue' => $form_values['mail_recipients_with_roles'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_RECIPIENTS_WITH_ROLES_DESC'));
                        $form->addInput('mail_bcc_count', $gL10n->get('MAI_COUNT_BCC'), $form_values['mail_bcc_count'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'MAI_COUNT_BCC_DESC'));
                        $selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
                        $form->addSelectBox('mail_character_encoding', $gL10n->get('MAI_CHARACTER_ENCODING'), $selectBoxEntries, array('defaultValue' => $form_values['mail_character_encoding'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_CHARACTER_ENCODING_DESC'));
                        $form->addInput('mail_smtp_host', $gL10n->get('MAI_SMTP_HOST'), $form_values['mail_smtp_host'], array('maxLength' => 50, 'helpTextIdInline' => 'MAI_SMTP_HOST_DESC'));
                        $form->addCheckbox('mail_smtp_auth', $gL10n->get('MAI_SMTP_AUTH'), (bool) $form_values['mail_smtp_auth'], array('helpTextIdInline' => 'MAI_SMTP_AUTH_DESC'));
                        $form->addInput('mail_smtp_port', $gL10n->get('MAI_SMTP_PORT'), $form_values['mail_smtp_port'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'MAI_SMTP_PORT_DESC'));
                        $selectBoxEntries = array('' => $gL10n->get('MAI_SMTP_SECURE_NO'), 'ssl' => $gL10n->get('MAI_SMTP_SECURE_SSL'), 'tls' => $gL10n->get('MAI_SMTP_SECURE_TLS'));
                        $form->addSelectBox('mail_smtp_secure', $gL10n->get('MAI_SMTP_SECURE'), $selectBoxEntries, array('defaultValue' => $form_values['mail_smtp_secure'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_SMTP_SECURE_DESC'));
                        $selectBoxEntries = array('LOGIN' => $gL10n->get('MAI_SMTP_AUTH_LOGIN'), 'PLAIN' => $gL10n->get('MAI_SMTP_AUTH_PLAIN'), 'NTLM' => $gL10n->get('MAI_SMTP_AUTH_NTLM'));
                        $form->addSelectBox('mail_smtp_authentication_type', $gL10n->get('MAI_SMTP_AUTH_TYPE'), $selectBoxEntries, array('defaultValue' => $form_values['mail_smtp_authentication_type'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_SMTP_AUTH_TYPE_DESC'));
                        $form->addInput('mail_smtp_user', $gL10n->get('MAI_SMTP_USER'), $form_values['mail_smtp_user'], array('maxLength' => 100, 'helpTextIdInline' => 'MAI_SMTP_USER_DESC'));
                        $form->addInput('mail_smtp_password', $gL10n->get('MAI_SMTP_PASSWORD'), $form_values['mail_smtp_password'], array('type' => 'password', 'maxLength' => 50, 'helpTextIdInline' => 'MAI_SMTP_PASSWORD_DESC'));
                        $form->addSubmitButton('btn_save_email_dispatch', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_system_notification">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_system_notification">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/system_notification.png" alt="'.$gL10n->get('SYS_SYSTEM_MAILS').'" />'.$gL10n->get('SYS_SYSTEM_MAILS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_system_notification" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $text = new TableText($gDb);
                        $form = new HtmlForm('system_notification_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=system_notification', $page, array('class' => 'form-preferences'));
                        $form->addCheckbox('enable_system_mails', $gL10n->get('ORG_ACTIVATE_SYSTEM_MAILS'), (bool) $form_values['enable_system_mails'], array('helpTextIdInline' => 'ORG_ACTIVATE_SYSTEM_MAILS_DESC'));
                        $form->addInput('email_administrator', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $form_values['email_administrator'], array('type' => 'email', 'maxLength' => 50, 'helpTextIdInline' => 'ORG_SYSTEM_MAIL_ADDRESS_DESC'));
                        $form->addCheckbox('enable_email_notification', $gL10n->get('ORG_SYSTEM_MAIL_NEW_ENTRIES'), (bool) $form_values['enable_email_notification'], array('helpTextIdInline' => array('ORG_SYSTEM_MAIL_NEW_ENTRIES_DESC', '<em>'.$gPreferences['email_administrator'].'</em>')));
                        $form->addCustomContent($gL10n->get('SYS_SYSTEM_MAILS'),
                            '<p>'.$gL10n->get('ORG_SYSTEM_MAIL_TEXTS_DESC').':</p>
                            <p><strong>#user_first_name#</strong> - '.$gL10n->get('ORG_VARIABLE_FIRST_NAME').'<br />
                            <strong>#user_last_name#</strong> - '.$gL10n->get('ORG_VARIABLE_LAST_NAME').'<br />
                            <strong>#user_login_name#</strong> - '.$gL10n->get('ORG_VARIABLE_USERNAME').'<br />
                            <strong>#user_email#</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL').'<br />
                            <strong>#administrator_email#</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION').'<br />
                            <strong>#organization_short_name#</strong> - '.$gL10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION').'<br />
                            <strong>#organization_long_name#</strong> - '.$gL10n->get('ORG_VARIABLE_NAME_ORGANIZATION').'<br />
                            <strong>#organization_homepage#</strong> - '.$gL10n->get('ORG_VARIABLE_URL_ORGANIZATION').'</p>');

                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_WEBMASTER', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_REGISTRATION_WEBMASTER', $gL10n->get('ORG_NOTIFY_ADMINISTRATOR'), $text->getValue('txt_text'), 7);
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_USER', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_REGISTRATION_USER', $gL10n->get('ORG_CONFIRM_REGISTRATION'), $text->getValue('txt_text'), 7);
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REFUSE_REGISTRATION', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_REFUSE_REGISTRATION', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_NEW_PASSWORD', $gL10n->get('ORG_SEND_NEW_PASSWORD'), $text->getValue('txt_text'), 7,
                            array('helpTextIdInline' => $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br /><strong>#variable1#</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD')));
                        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_ACTIVATION_LINK', 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
                        $form->addMultilineTextInput('SYSMAIL_ACTIVATION_LINK', $gL10n->get('ORG_NEW_PASSWORD_ACTIVATION_LINK'), $text->getValue('txt_text'), 7,
                            array('helpTextIdInline' => $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br />
                            <strong>#variable1#</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br />
                            <strong>#variable2#</strong> - '.$gL10n->get('ORG_VARIABLE_ACTIVATION_LINK')));

                        $form->addSubmitButton('btn_save_system_notification', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_captcha">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_captcha">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/captcha.png" alt="'.$gL10n->get('SYS_CAPTCHA').'" />'.$gL10n->get('SYS_CAPTCHA').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_captcha" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('captcha_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=captcha', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('pic' => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'), 'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'), 'word' => $gL10n->get('ORG_CAPTCHA_TYPE_WORDS'));
                        $form->addSelectBox('captcha_type', $gL10n->get('ORG_CAPTCHA_TYPE'), $selectBoxEntries, array('defaultValue' => $form_values['captcha_type'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_CAPTCHA_TYPE_TEXT'));

                        $fonts = admFuncGetDirectoryEntries('../../system/fonts/');
                        asort($fonts);
                        $form->addSelectBox('captcha_fonts', $gL10n->get('SYS_FONT'), $fonts, array('defaultValue' => $form_values['captcha_fonts'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_CAPTCHA_FONT'));
                        $form->addInput('captcha_width', $gL10n->get('SYS_WIDTH').' ('.$gL10n->get('ORG_PIXEL').')', $form_values['captcha_width'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'ORG_CAPTCHA_WIDTH_DESC'));
                        $form->addInput('captcha_lines_numbers', $gL10n->get('ORG_CAPTCHA_LINES_NUMBERS'), $form_values['captcha_lines_numbers'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 25, 'helpTextIdInline' => 'ORG_CAPTCHA_LINES_NUMBERS_DESC'));
                        $form->addInput('captcha_perturbation', $gL10n->get('ORG_CAPTCHA_DISTORTION'), $form_values['captcha_perturbation'], array('type' => 'string', 'helpTextIdInline' => 'ORG_CAPTCHA_DISTORTION_DESC', 'class' => 'form-control-small'));
                        $backgrounds = admFuncGetDirectoryEntries('../../libs/securimage/backgrounds/');
                        asort($backgrounds);
                        $form->addSelectBox('captcha_background_image', $gL10n->get('ORG_CAPTCHA_BACKGROUND_IMAGE'), $backgrounds, array('defaultValue' => $form_values['captcha_background_image'], 'showContextDependentFirstEntry' => true, 'helpTextIdInline' => 'ORG_CAPTCHA_BACKGROUND_IMAGE_DESC'));
                        $form->addInput('captcha_background_color', $gL10n->get('ORG_CAPTCHA_BACKGROUND_COLOR'), $form_values['captcha_background_color'], array('maxLength' => 7, 'class' => 'form-control-small'));
                        $form->addInput('captcha_text_color', $gL10n->get('ORG_CAPTCHA_CHARACTERS_COLOR'), $form_values['captcha_text_color'], array('maxLength' => 7, 'class' => 'form-control-small'));
                        $form->addInput('captcha_line_color', $gL10n->get('ORG_CAPTCHA_LINE_COLOR'), $form_values['captcha_line_color'], array('maxLength' => 7, 'helpTextIdInline' => array('ORG_CAPTCHA_COLOR_DESC', '<a href="https://en.wikipedia.org/wiki/Web_colors">', '</a>'), 'class' => 'form-control-small'));
                        $form->addInput('captcha_charset', $gL10n->get('ORG_CAPTCHA_SIGNS'), $form_values['captcha_charset'], array('maxLength' => 80, 'helpTextIdInline' => 'ORG_CAPTCHA_SIGNS_TEXT'));
                        $form->addInput('captcha_signature', $gL10n->get('ORG_CAPTCHA_SIGNATURE'), $form_values['captcha_signature'], array('maxLength' => 60, 'helpTextIdInline' => 'ORG_CAPTCHA_SIGNATURE_TEXT'));
                        $html = '<img id="captcha" src="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php" alt="CAPTCHA Image" />
                                 <a class="admidio-icon-link" href="#" onclick="document.getElementById(\'captcha\').src=\'' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php?\' + Math.random(); return false"><img
                                    src="'.THEME_URL.'/icons/view-refresh.png" alt="'.$gL10n->get('SYS_RELOAD').'" title="'.$gL10n->get('SYS_RELOAD').'" /></a>';
                        $form->addCustomContent($gL10n->get('ORG_CAPTCHA_PREVIEW'), $html, array('helpTextIdInline' => 'ORG_CAPTCHA_PREVIEW_TEXT'));

                        $form->addSubmitButton('btn_save_captcha', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_system_informations">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_common" href="#collapse_system_informations">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/info.png" alt="'.$gL10n->get('ORG_SYSTEM_INFORMATIONS').'" />'.$gL10n->get('ORG_SYSTEM_INFORMATIONS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_system_informations" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // create a static form
                        $form = new HtmlForm('system_informations_preferences_form', null, $page);
                        $html = '<span id="admidio_version_content">'.ADMIDIO_VERSION_TEXT.'
                                    <a id="link_check_for_update" href="#link_check_for_update" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
                                 </span>';
                        $form->addCustomContent($gL10n->get('SYS_ADMIDIO_VERSION'), $html);

                        // if database version is different to file version, then show database version
                        if(strcmp(ADMIDIO_VERSION, $gSystemComponent->getValue('com_version')) !== 0)
                        {
                            $form->addStaticControl('admidio_database_version', $gL10n->get('ORG_DIFFERENT_DATABASE_VERSION'), $gSystemComponent->getValue('com_version'));
                        }
                        $form->addStaticControl('last_update_step', $gL10n->get('ORG_LAST_UPDATE_STEP'), $gSystemComponent->getValue('com_update_step'));

                        if(version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
                        {
                            $html = '<span class="text-danger"><strong>'.PHP_VERSION.'</strong></span> &rarr; '.$gL10n->get('SYS_PHP_VERSION_REQUIRED', MIN_PHP_VERSION);
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.PHP_VERSION.'</strong></span>';
                        }
                        $form->addStaticControl('php_version', $gL10n->get('SYS_PHP_VERSION'), $html);

                        if(version_compare($gDb->getVersion(), $gDb->getMinimumRequiredVersion(), '<'))
                        {
                            $html = '<span class="text-danger"><strong>'.$gDb->getVersion().'</strong></span> &rarr; '.$gL10n->get('SYS_DATABASE_VERSION_REQUIRED', $gDb->getMinimumRequiredVersion());
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.$gDb->getVersion().'</strong></span>';
                        }
                        $form->addStaticControl('database_version', $gDb->getName().'-'.$gL10n->get('SYS_VERSION'), $html);

                        // deprecated: Remove if PHP 5.3 dropped
                        if(ini_get('safe_mode') === '1')
                        {
                            $gLogger->warning('DEPRECATED: Safe-Mode is enabled!');
                            $html = '<span class="text-danger"><strong>'.$gL10n->get('SYS_ON').'</strong></span> &rarr; '.$gL10n->get('SYS_SAFE_MODE_PROBLEM');
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.$gL10n->get('SYS_OFF').'</strong></span>';
                        }
                        $form->addStaticControl('safe_mode', $gL10n->get('SYS_SAFE_MODE'), $html);

                        try
                        {
                            PasswordHashing::genRandomInt(0, 1, true);
                            $html = '<span class="text-success"><strong>' . $gL10n->get('SYS_SECURE') . '</strong></span>';
                        }
                        catch (AdmException $e)
                        {
                            $html = '<span class="text-danger"><strong>' . $gL10n->get('SYS_PRNG_INSECURE') . '</strong><br />' . $e->getText() . '</span>';
                        }
                        $form->addStaticControl('pseudo_random_number_generator', $gL10n->get('SYS_PRNG'), $html);

                        if(ini_get('post_max_size') !== '')
                        {
                            $form->addStaticControl('post_max_size', $gL10n->get('SYS_POST_MAX_SIZE'), ini_get('post_max_size'));
                        }
                        else
                        {
                            $form->addStaticControl('post_max_size', $gL10n->get('SYS_POST_MAX_SIZE'), $gL10n->get('SYS_NOT_SET'));
                        }

                        if(ini_get('memory_limit') !== '')
                        {
                            $form->addStaticControl('memory_limit', $gL10n->get('SYS_MEMORY_LIMIT'), ini_get('memory_limit'));
                        }
                        else
                        {
                            $form->addStaticControl('memory_limit', $gL10n->get('SYS_MEMORY_LIMIT'), $gL10n->get('SYS_NOT_SET'));
                        }

                        if(ini_get('file_uploads') === '1')
                        {
                            $html = '<span class="text-success"><strong>'.$gL10n->get('SYS_ON').'</strong></span>';
                        }
                        else
                        {
                            $html = '<span class="text-danger"><strong>'.$gL10n->get('SYS_OFF').'</strong></span>';
                        }
                        $form->addStaticControl('file_uploads', $gL10n->get('SYS_FILE_UPLOADS'), $html);

                        if(ini_get('upload_max_filesize') !== '')
                        {
                            $form->addStaticControl('upload_max_filesize', $gL10n->get('SYS_UPLOAD_MAX_FILESIZE'), ini_get('upload_max_filesize'));
                        }
                        else
                        {
                            $form->addStaticControl('upload_max_filesize', $gL10n->get('SYS_UPLOAD_MAX_FILESIZE'), $gL10n->get('SYS_NOT_SET'));
                        }

                        $form->addStaticControl('max_processable_image_size', $gL10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE'), round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('SYS_MEGA_PIXEL'));
                        $html = '<a href="preferences_function.php?mode=4" target="_blank">phpinfo()</a>';
                        $form->addStaticControl('php_info', $gL10n->get('SYS_PHP_INFO'), $html);

                        if(isset($gDebug) && $gDebug)
                        {
                            $html = '<span class="text-danger"><strong>'.$gL10n->get('SYS_ON').'</strong></span>';
                        }
                        else
                        {
                            $html = '<span class="text-success"><strong>'.$gL10n->get('SYS_OFF').'</strong></span>';
                        }
                        $form->addStaticControl('debug_mode', $gL10n->get('SYS_DEBUG_MODUS'), $html);
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
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_announcements">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/announcements.png" alt="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" />'.$gL10n->get('ANN_ANNOUNCEMENTS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_announcements" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('announcements_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=announcements', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_announcements_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, array('defaultValue' => $form_values['enable_announcements_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC'));
                        $form->addInput('announcements_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['announcements_per_page'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', 10)));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php?type=ANN"><img
                                    src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addSubmitButton('btn_save_announcements', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_user_management">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_user_management">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/user_administration.png" alt="'.$gL10n->get('MEM_USER_MANAGEMENT').'" />'.$gL10n->get('MEM_USER_MANAGEMENT').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_user_management" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('user_management_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=user_management', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
                        $form->addSelectBox('members_users_per_page', $gL10n->get('MEM_USERS_PER_PAGE'), $selectBoxEntries, array('defaultValue' => $form_values['members_users_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MEM_USERS_PER_PAGE_DESC'));
                        $form->addInput('members_days_field_history', $gL10n->get('MEM_DAYS_FIELD_HISTORY'), $form_values['members_days_field_history'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999999999, 'helpTextIdInline' => 'MEM_DAYS_FIELD_HISTORY_DESC'));
                        $form->addCheckbox('members_show_all_users', $gL10n->get('ORG_SHOW_ALL_USERS'), (bool) $form_values['members_show_all_users'], array('helpTextIdInline' => 'ORG_SHOW_ALL_USERS_DESC'));
                        $form->addCheckbox('members_enable_user_relations', $gL10n->get('MEM_ENABLE_USER_RELATIONS'), (bool) $form_values['members_enable_user_relations'], array('helpTextIdInline' => 'MEM_ENABLE_USER_RELATIONS_DESC'));
                        $form->addSubmitButton('btn_save_user_management', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_downloads">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_downloads">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/download.png" alt="'.$gL10n->get('DOW_DOWNLOADS').'" />'.$gL10n->get('DOW_DOWNLOADS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_downloads" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('downloads_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=downloads', $page, array('class' => 'form-preferences'));
                        $form->addCheckbox('enable_download_module', $gL10n->get('DOW_ENABLE_DOWNLOAD_MODULE'), (bool) $form_values['enable_download_module'], array('helpTextIdInline' => 'DOW_ENABLE_DOWNLOAD_MODULE_DESC'));
                        $form->addInput('max_file_upload_size', $gL10n->get('DOW_MAXIMUM_FILE_SIZE').' (MB)', $form_values['max_file_upload_size'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999999, 'step' => 1, 'helpTextIdInline' => 'DOW_MAXIMUM_FILE_SIZE_DESC'));
                        $form->addSubmitButton('btn_save_downloads', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_photos">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_photos">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/photo.png" alt="'.$gL10n->get('PHO_PHOTOS').'" />'.$gL10n->get('PHO_PHOTOS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_photos" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('photos_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=photos', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_photo_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, array('defaultValue' => $form_values['enable_photo_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC'));
                        $selectBoxEntries = array('1' => $gL10n->get('PHO_MODAL_WINDOW'), '2' => $gL10n->get('PHO_SAME_WINDOW'), '0' => $gL10n->get('PHO_POPUP_WINDOW'));
                        $form->addSelectBox('photo_show_mode', $gL10n->get('PHO_DISPLAY_PHOTOS'), $selectBoxEntries, array('defaultValue' => $form_values['photo_show_mode'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PHO_DISPLAY_PHOTOS_DESC'));
                        $form->addInput('photo_albums_per_page', $gL10n->get('PHO_NUMBER_OF_ALBUMS_PER_PAGE'), $form_values['photo_albums_per_page'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', 10)));
                        $form->addInput('photo_thumbs_page', $gL10n->get('PHO_THUMBNAILS_PER_PAGE'), $form_values['photo_thumbs_page'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'PHO_THUMBNAILS_PER_PAGE_DESC'));
                        $form->addInput('photo_thumbs_scale', $gL10n->get('PHO_SCALE_THUMBNAILS'), $form_values['photo_thumbs_scale'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'PHO_SCALE_THUMBNAILS_DESC'));
                        $form->addInput('photo_save_scale', $gL10n->get('PHO_SCALE_AT_UPLOAD'), $form_values['photo_save_scale'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'PHO_SCALE_AT_UPLOAD_DESC'));
                        $form->addInput('photo_show_width', $gL10n->get('PHO_MAX_PHOTO_SIZE_WIDTH'), $form_values['photo_show_width'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999));
                        $form->addInput('photo_show_height', $gL10n->get('PHO_MAX_PHOTO_SIZE_HEIGHT'), $form_values['photo_show_height'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'PHO_MAX_PHOTO_SIZE_DESC'));
                        $form->addInput('photo_image_text', $gL10n->get('PHO_SHOW_CAPTION'), $form_values['photo_image_text'], array('maxLength' => 60, 'helpTextIdInline' => array('PHO_SHOW_CAPTION_DESC', DOMAIN)));
                        $form->addInput('photo_image_text_size', $gL10n->get('PHO_CAPTION_SIZE'), $form_values['photo_image_text_size'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'PHO_CAPTION_SIZE_DESC'));
                        $form->addCheckbox('photo_download_enabled', $gL10n->get('PHO_DOWNLOAD_ENABLED'), (bool) $form_values['photo_download_enabled'], array('helpTextIdInline' => array('PHO_DOWNLOAD_ENABLED_DESC', $gL10n->get('PHO_KEEP_ORIGINAL'))));
                        $form->addCheckbox('photo_keep_original', $gL10n->get('PHO_KEEP_ORIGINAL'), (bool) $form_values['photo_keep_original'], array('helpTextIdInline' => array('PHO_KEEP_ORIGINAL_DESC', $gL10n->get('PHO_DOWNLOAD_ENABLED'))));
                        $form->addSubmitButton('btn_save_photos', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_guestbook">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_guestbook">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/guestbook.png" alt="'.$gL10n->get('GBO_GUESTBOOK').'" />'.$gL10n->get('GBO_GUESTBOOK').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_guestbook" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('guestbook_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=guestbook', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_guestbook_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, array('defaultValue' => $form_values['enable_guestbook_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC'));
                        $form->addInput('guestbook_entries_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['guestbook_entries_per_page'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', 10)));
                        $form->addCheckbox('enable_guestbook_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), (bool) $form_values['enable_guestbook_captcha'], array('helpTextIdInline' => 'GBO_CAPTCHA_DESC'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_NOBODY'), '1' => $gL10n->get('GBO_ONLY_VISITORS'), '2' => $gL10n->get('SYS_ALL'));
                        $form->addSelectBox('enable_guestbook_moderation', $gL10n->get('GBO_GUESTBOOK_MODERATION'), $selectBoxEntries, array('defaultValue' => $form_values['enable_guestbook_moderation'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'GBO_GUESTBOOK_MODERATION_DESC'));
                        $form->addCheckbox('enable_gbook_comments4all', $gL10n->get('GBO_COMMENTS4ALL'), (bool) $form_values['enable_gbook_comments4all'], array('helpTextIdInline' => 'GBO_COMMENTS4ALL_DESC'));
                        $form->addCheckbox('enable_intial_comments_loading', $gL10n->get('GBO_INITIAL_COMMENTS_LOADING'), (bool) $form_values['enable_intial_comments_loading'], array('helpTextIdInline' => 'GBO_INITIAL_COMMENTS_LOADING_DESC'));
                        $form->addInput('flooding_protection_time', $gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL'), $form_values['flooding_protection_time'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'GBO_FLOODING_PROTECTION_INTERVALL_DESC'));
                        $form->addSubmitButton('btn_save_guestbook', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_ecards">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_ecards">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/ecard.png" alt="'.$gL10n->get('ECA_GREETING_CARDS').'" />'.$gL10n->get('ECA_GREETING_CARDS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_ecards" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('ecards_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=ecards', $page, array('class' => 'form-preferences'));
                        $form->addCheckbox('enable_ecard_module', $gL10n->get('ECA_ACTIVATE_GREETING_CARDS'), (bool) $form_values['enable_ecard_module'], array('helpTextIdInline' => 'ECA_ACTIVATE_GREETING_CARDS_DESC'));
                        $form->addInput('ecard_thumbs_scale', $gL10n->get('PHO_SCALE_THUMBNAILS'), $form_values['ecard_thumbs_scale'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'ECA_SCALE_THUMBNAILS_DESC'));
                        $form->addInput('ecard_card_picture_width', $gL10n->get('PHO_MAX_PHOTO_SIZE_WIDTH'), $form_values['ecard_card_picture_width'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999));
                        $form->addInput('ecard_card_picture_height', $gL10n->get('PHO_MAX_PHOTO_SIZE_HEIGHT'), $form_values['ecard_card_picture_height'], array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'helpTextIdInline' => 'ECA_MAX_PHOTO_SIZE_DESC'));
                        $templates = admFuncGetDirectoryEntries(THEME_ADMIDIO_PATH.'/ecard_templates');
                        if (!is_array($templates))
                        {
                            $gMessage->show($gL10n->get('ECA_TEMPLATE_FOLDER_OPEN'));
                            // => EXIT
                        }
                        foreach($templates as $key => $templateName)
                        {
                            $templates[$key] = ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $templateName)));
                        }
                        $form->addSelectBox('ecard_template', $gL10n->get('ECA_TEMPLATE'), $templates, array('defaultValue' => $form_values['ecard_template'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ECA_TEMPLATE_DESC'));
                        $form->addSubmitButton('btn_save_ecards', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_lists">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_lists">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/list.png" alt="'.$gL10n->get('LST_LISTS').'" />'.$gL10n->get('LST_LISTS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_lists" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('lists_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=lists', $page, array('class' => 'form-preferences'));
                        $form->addInput('lists_roles_per_page', $gL10n->get('LST_NUMBER_OF_ROLES_PER_PAGE'), $form_values['lists_roles_per_page'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', 10)));
                        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
                        $form->addSelectBox('lists_members_per_page', $gL10n->get('LST_MEMBERS_PER_PAGE'), $selectBoxEntries, array('defaultValue' => $form_values['lists_members_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'LST_MEMBERS_PER_PAGE_DESC'));
                        $form->addCheckbox('lists_hide_overview_details', $gL10n->get('LST_HIDE_DETAILS'), (bool) $form_values['lists_hide_overview_details'], array('helpTextIdInline' => 'LST_HIDE_DETAILS_DESC'));
                        // read all global lists
                        $sql = 'SELECT lst_id, lst_name
                                  FROM '.TBL_LISTS.'
                                 WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id') .'
                                   AND lst_global = 1
                              ORDER BY lst_name ASC, lst_timestamp DESC';
                        $form->addSelectBoxFromSql('lists_default_configuration', $gL10n->get('LST_DEFAULT_CONFIGURATION'), $gDb, $sql, array('defaultValue' => $form_values['lists_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'LST_DEFAULT_CONFIGURATION_DESC'));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php?type=ROL"><img
                                    src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addSubmitButton('btn_save_lists', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_messages">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_messages">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/messages.png" alt="'.$gL10n->get('SYS_MESSAGES').'" />'.$gL10n->get('SYS_MESSAGES').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_messages" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('messages_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=messages', $page, array('class' => 'form-preferences'));
                        $form->addCheckbox('enable_mail_module', $gL10n->get('MAI_ACTIVATE_EMAIL_MODULE'), (bool) $form_values['enable_mail_module'], array('helpTextIdInline' => 'MAI_ACTIVATE_EMAIL_MODULE_DESC'));
                        $form->addCheckbox('enable_pm_module', $gL10n->get('MSG_ACTIVATE_PM_MODULE'), (bool) $form_values['enable_pm_module'], array('helpTextIdInline' => 'MSG_ACTIVATE_PM_MODULE_DESC'));
                        $form->addCheckbox('enable_chat_module', $gL10n->get('MSG_ACTIVATE_CHAT_MODULE'), (bool) $form_values['enable_chat_module'], array('helpTextIdInline' => 'MSG_ACTIVATE_CHAT_MODULE_DESC'));
                        $form->addCheckbox('enable_mail_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), (bool) $form_values['enable_mail_captcha'], array('helpTextIdInline' => 'MAI_SHOW_CAPTCHA_DESC'));
                        $form->addInput('mail_max_receiver', $gL10n->get('MAI_MAX_RECEIVER'), $form_values['mail_max_receiver'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'MAI_MAX_RECEIVER_DESC'));
                        $form->addCheckbox('mail_show_former', $gL10n->get('MSG_SHOW_FORMER'), (bool) $form_values['mail_show_former'], array('helpTextIdInline' => 'MSG_SHOW_FORMER_DESC'));
                        $form->addCheckbox('mail_into_to', $gL10n->get('MAI_INTO_TO'), (bool) $form_values['mail_into_to'], array('helpTextIdInline' => 'MAI_INTO_TO_DESC'));
                        $form->addInput('max_email_attachment_size', $gL10n->get('MAI_ATTACHMENT_SIZE').' (MB)', $form_values['max_email_attachment_size'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999, 'helpTextIdInline' => 'MAI_ATTACHMENT_SIZE_DESC'));
                        $form->addCheckbox('mail_html_registered_users', $gL10n->get('MAI_HTML_MAILS_REGISTERED_USERS'), (bool) $form_values['mail_html_registered_users'], array('helpTextIdInline' => 'MAI_HTML_MAILS_REGISTERED_USERS_DESC'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('mail_delivery_confirmation', $gL10n->get('MAI_DELIVERY_CONFIRMATION'), $selectBoxEntries, array('defaultValue' => $form_values['mail_delivery_confirmation'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_DELIVERY_CONFIRMATION_DESC'));
                        $form->addSubmitButton('btn_save_messages', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_profile">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_profile">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/profile.png" alt="'.$gL10n->get('PRO_PROFILE').'" />'.$gL10n->get('PRO_PROFILE').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_profile" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('profile_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=profile', $page, array('class' => 'form-preferences'));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/preferences/fields.php"><img
                                    src="'. THEME_URL. '/icons/application_form_edit.png" alt="'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'" />'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'</a>';
                        $htmlDesc = $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addCheckbox('profile_log_edit_fields', $gL10n->get('PRO_LOG_EDIT_FIELDS'), (bool) $form_values['profile_log_edit_fields'], array('helpTextIdInline' => 'PRO_LOG_EDIT_FIELDS_DESC'));
                        $form->addCheckbox('profile_show_map_link', $gL10n->get('PRO_SHOW_MAP_LINK'), (bool) $form_values['profile_show_map_link'], array('helpTextIdInline' => 'PRO_SHOW_MAP_LINK_DESC'));
                        $form->addCheckbox('profile_show_roles', $gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP'), (bool) $form_values['profile_show_roles'], array('helpTextIdInline' => 'PRO_SHOW_ROLE_MEMBERSHIP_DESC'));
                        $form->addCheckbox('profile_show_former_roles', $gL10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP'), (bool) $form_values['profile_show_former_roles'], array('helpTextIdInline' => 'PRO_SHOW_FORMER_ROLE_MEMBERSHIP_DESC'));

                        if($gCurrentOrganization->getValue('org_org_id_parent') > 0
                        || $gCurrentOrganization->hasChildOrganizations())
                        {
                            $form->addCheckbox('profile_show_extern_roles', $gL10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS'), (bool) $form_values['profile_show_extern_roles'], array('helpTextIdInline' => 'PRO_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC'));
                        }

                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
                        $form->addSelectBox('profile_photo_storage', $gL10n->get('PRO_LOCATION_PROFILE_PICTURES'), $selectBoxEntries, array('defaultValue' => $form_values['profile_photo_storage'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PRO_LOCATION_PROFILE_PICTURES_DESC'));
                        $form->addSubmitButton('btn_save_profile', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_events">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_events">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/dates.png" alt="'.$gL10n->get('DAT_DATES').'" />'.$gL10n->get('DAT_DATES').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_events" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('events_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=events', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_dates_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, array('defaultValue' => $form_values['enable_dates_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC'));
                        if($gPreferences['dates_show_rooms'])
                        {
                            $selectBoxEntries = array('detail' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'), 'room' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'), 'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'), 'description' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION'));
                        }
                        else
                        {
                            $selectBoxEntries = array('detail' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'), 'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'), 'description' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION'));
                        }
                        $form->addSelectBox('dates_view', $gL10n->get('DAT_VIEW_MODE'), $selectBoxEntries, array('defaultValue' => $form_values['dates_view'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('DAT_VIEW_MODE_DESC', 'DAT_VIEW_MODE_DETAIL', 'DAT_VIEW_MODE_COMPACT')));
                        $form->addInput('dates_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['dates_per_page'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', 10)));
                        $form->addCheckbox('enable_dates_ical', $gL10n->get('DAT_ENABLE_ICAL'), (bool) $form_values['enable_dates_ical'], array('helpTextIdInline' => 'DAT_ENABLE_ICAL_DESC'));
                        $form->addInput('dates_ical_days_past', $gL10n->get('DAT_ICAL_DAYS_PAST'), $form_values['dates_ical_days_past'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'DAT_ICAL_DAYS_PAST_DESC'));
                        $form->addInput('dates_ical_days_future', $gL10n->get('DAT_ICAL_DAYS_FUTURE'), $form_values['dates_ical_days_future'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'DAT_ICAL_DAYS_FUTURE_DESC'));
                        $form->addCheckbox('dates_show_map_link', $gL10n->get('DAT_SHOW_MAP_LINK'), (bool) $form_values['dates_show_map_link'], array('helpTextIdInline' => 'DAT_SHOW_MAP_LINK_DESC'));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'"><img
                                    src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_EDIT_CALENDAR_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('DAT_MANAGE_CALENDARS'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addCheckbox('dates_show_rooms', $gL10n->get('DAT_ROOM_SELECTABLE'), (bool) $form_values['dates_show_rooms'], array('helpTextIdInline' => 'DAT_ROOM_SELECTABLE_DESC'));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/rooms/rooms.php"><img
                                    src="'. THEME_URL. '/icons/home.png" alt="'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_EDIT_ROOMS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('DAT_EDIT_ROOMS'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addSubmitButton('btn_save_events', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            <div class="panel panel-default" id="panel_links">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_links">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/weblinks.png" alt="'.$gL10n->get('LNK_WEBLINKS').'" />'.$gL10n->get('LNK_WEBLINKS').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_links" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('links_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=links', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_weblinks_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, array('defaultValue' => $form_values['enable_weblinks_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC'));
                        $form->addInput('weblinks_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $form_values['weblinks_per_page'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', 0)));
                        $selectBoxEntries = array('_self' => $gL10n->get('LNK_SAME_WINDOW'), '_blank' => $gL10n->get('LNK_NEW_WINDOW'));
                        $form->addSelectBox('weblinks_target', $gL10n->get('LNK_LINK_TARGET'), $selectBoxEntries, array('defaultValue' => $form_values['weblinks_target'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'LNK_LINK_TARGET_DESC'));
                        $form->addInput('weblinks_redirect_seconds', $gL10n->get('LNK_DISPLAY_REDIRECT'), $form_values['weblinks_redirect_seconds'], array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'helpTextIdInline' => 'LNK_DISPLAY_REDIRECT_DESC'));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php?type=LNK"><img
                                    src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addSubmitButton('btn_save_links', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>');
            /*
            <div class="panel panel-default" id="panel_inventory">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#accordion_modules" href="#collapse_inventory">
                            <img class="admidio-panel-heading-icon" src="'.THEME_URL.'/icons/inventory.png" alt="'.$gL10n->get('INV_INVENTORY').'" />'.$gL10n->get('INV_INVENTORY').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_inventory" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('inventory_preferences_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php?form=inventory', $page, array('class' => 'form-preferences'));
                        $selectBoxEntries = array('0' => $gL10n->get('SYS_DEACTIVATED'), '1' => $gL10n->get('SYS_ACTIVATED'), '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'));
                        $form->addSelectBox('enable_inventory_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries, array('defaultValue' => $form_values['enable_inventory_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC'));
                        $html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/rooms/rooms.php"><img
                                    src="'. THEME_URL. '/icons/home.png" alt="'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>';
                        $htmlDesc = $gL10n->get('DAT_EDIT_ROOMS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
                        $form->addCustomContent($gL10n->get('DAT_EDIT_ROOMS'), $html, array('helpTextIdInline' => $htmlDesc));
                        $form->addSubmitButton('btn_save_inventory', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
            */
        $page->addHtml('</div>
    </div>
</div>');

$page->show();
