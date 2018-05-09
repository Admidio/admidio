<?php
/**
 ***********************************************************************************************
 * Organization preferences
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * show_option : show preferences of module with this text id
 *               Example: SYS_COMMON or
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$showOption = admFuncVariableIsValid($_GET, 'show_option', 'string');

$headline = $gL10n->get('SYS_SETTINGS');

// only administrators are allowed to edit organization preferences
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// read organization and all system preferences values into form array
$formValues = array_merge($gCurrentOrganization->getDbColumns(), $gSettingsManager->getAll());

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$showOptionValidModules = array(
    'announcements', 'downloads', 'guestbook', 'ecards', 'lists',
    'messages', 'photos', 'profile', 'events', 'links', 'user_management'
);

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
        var formAlert = $("#" + id + " .form-alert");
        formAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {
                    if (id === "captcha_preferences_form") {
                        // reload captcha if form is saved
                        $("#captcha").attr("src", "' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php?" + Math.random());
                    }
                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>" + data);
                }
            }
        });
    });

    $("#link_check_for_update").click(function() {
        var admVersionContent = $("#admidio_version_content");

        admVersionContent.html("<img src=\"'.THEME_URL.'/icons/loader_inline.gif\" id=\"loadindicator\"/>").show();
        $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/preferences/update_check.php", {mode: "2"}, function(htmlVersion) {
            admVersionContent.html(htmlVersion);
        });
        return false;
    });

    $("#link_directory_protection").click(function() {
        var dirProtectionStatus = $("#directory_protection_status");

        dirProtectionStatus.html("<img src=\"'.THEME_URL.'/icons/loader_inline.gif\" id=\"loadindicator\"/>").show();
        $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php", {mode: "4"}, function(statusText) {
            var directoryProtection = dirProtectionStatus.parent().parent().parent();
            directoryProtection.html("<span class=\"text-success\"><strong>" + statusText + "</strong></span>");
        });
        return false;
    });',
    true
);

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

$orgId = (int) $gCurrentOrganization->getValue('org_id');

/**
 * @param string $type
 * @param string $text
 * @param string $info
 * @return string
 */
function getStaticText($type, $text, $info = '')
{
    return '<span class="text-' . $type . '"><strong>' . $text . '</strong></span>' . $info;
}

/**
 * @param string $group
 * @param string $id
 * @param string $title
 * @param string $icon
 * @param string $body
 * @return string
 */
function getPreferencePanel($group, $id, $title, $icon, $body)
{
    return '
        <div class="panel panel-default" id="panel_' . $id . '">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" data-parent="#accordion_' . $group . '" href="#collapse_' . $id . '">
                        <img class="admidio-panel-heading-icon" src="' . THEME_URL . '/icons/' . $icon . '" alt="' . $title . '" />' . $title . '
                    </a>
                </h4>
            </div>
            <div id="collapse_' . $id . '" class="panel-collapse collapse">
                <div class="panel-body">
                    ' . $body . '
                </div>
            </div>
        </div>
    ';
}

$page->addHtml('
<ul class="nav nav-tabs" id="preferences_tabs">
  <li id="tabs_nav_common"><a href="#tabs-common" data-toggle="tab">'.$gL10n->get('SYS_COMMON').'</a></li>
  <li id="tabs_nav_modules"><a href="#tabs-modules" data-toggle="tab">'.$gL10n->get('SYS_MODULES').'</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane" id="tabs-common">
        <div class="panel-group" id="accordion_common">');

// PANEL: COMMON

$formCommon = new HtmlForm(
    'common_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'common')),
    $page, array('class' => 'form-preferences')
);

// search all available themes in theme folder
$themes = admFuncGetDirectoryEntries(ADMIDIO_PATH . FOLDER_THEMES, 'dir');
if (!is_array($themes))
{
    $gMessage->show($gL10n->get('ECA_TEMPLATE_FOLDER_OPEN'));
    // => EXIT
}
$formCommon->addSelectBox(
    'theme', $gL10n->get('ORG_ADMIDIO_THEME'), $themes,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['theme'], 'helpTextIdInline' => 'ORG_ADMIDIO_THEME_DESC')
);
$formCommon->addInput(
    'homepage_logout', $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('SYS_VISITORS').')', $formValues['homepage_logout'],
    array('maxLength' => 250, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdInline' => 'ORG_HOMEPAGE_VISITORS')
);
$formCommon->addInput(
    'homepage_login', $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('ORG_REGISTERED_USERS').')', $formValues['homepage_login'],
    array('maxLength' => 250, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdInline' => 'ORG_HOMEPAGE_REGISTERED_USERS')
);
$formCommon->addInput(
    'logout_minutes', $gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER'), $formValues['logout_minutes'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_AUTOMATOC_LOGOUT_AFTER_DESC', array('SYS_REMEMBER_ME')))
);
$formCommon->addCheckbox(
    'enable_auto_login', $gL10n->get('ORG_LOGIN_AUTOMATICALLY'), (bool) $formValues['enable_auto_login'],
    array('helpTextIdInline' => 'ORG_LOGIN_AUTOMATICALLY_DESC')
);
$formCommon->addCheckbox(
    'enable_password_recovery', $gL10n->get('ORG_SEND_PASSWORD'), (bool) $formValues['enable_password_recovery'],
    array('helpTextIdInline' => array('ORG_SEND_PASSWORD_DESC', array('ORG_ACTIVATE_SYSTEM_MAILS')))
);
$formCommon->addCheckbox(
    'enable_rss', $gL10n->get('ORG_ENABLE_RSS_FEEDS'), (bool) $formValues['enable_rss'],
    array('helpTextIdInline' => 'ORG_ENABLE_RSS_FEEDS_DESC')
);
$formCommon->addCheckbox(
    'system_cookie_note', $gL10n->get('SYS_COOKIE_NOTE'), (bool) $formValues['system_cookie_note'],
    array('helpTextIdInline' => 'SYS_COOKIE_NOTE_DESC')
);
$formCommon->addCheckbox(
    'system_search_similar', $gL10n->get('ORG_SEARCH_SIMILAR_NAMES'), (bool) $formValues['system_search_similar'],
    array('helpTextIdInline' => 'ORG_SEARCH_SIMILAR_NAMES_DESC')
);
$selectBoxEntries = array(0 => $gL10n->get('SYS_DONT_SHOW'), 1 => $gL10n->get('SYS_FIRSTNAME_LASTNAME'), 2 => $gL10n->get('SYS_USERNAME'));
$formCommon->addSelectBox(
    'system_show_create_edit', $gL10n->get('ORG_SHOW_CREATE_EDIT'), $selectBoxEntries,
    array('defaultValue' => $formValues['system_show_create_edit'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_SHOW_CREATE_EDIT_DESC')
);
$formCommon->addInput(
    'system_js_editor_color', $gL10n->get('ORG_JAVASCRIPT_EDITOR_COLOR'), $formValues['system_js_editor_color'],
    array('maxLength' => 10, 'helpTextIdInline' => array('ORG_JAVASCRIPT_EDITOR_COLOR_DESC', array('SYS_REMEMBER_ME')), 'class' => 'form-control-small')
);
$selectBoxEntries = array(
    0 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_NO'),
    1 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_LOW'),
    2 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_MID'),
    3 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_HIGH'),
    4 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_VERY_HIGH')
);
$formCommon->addSelectBox(
    'password_min_strength', $gL10n->get('ORG_PASSWORD_MIN_STRENGTH'), $selectBoxEntries,
    array('defaultValue' => $formValues['password_min_strength'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_PASSWORD_MIN_STRENGTH_DESC')
);
$formCommon->addCheckbox(
    'system_js_editor_enabled', $gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE'), (bool) $formValues['system_js_editor_enabled'],
    array('helpTextIdInline' => 'ORG_JAVASCRIPT_EDITOR_ENABLE_DESC')
);
$formCommon->addCheckbox(
    'system_browser_update_check', $gL10n->get('ORG_BROWSER_UPDATE_CHECK'), (bool) $formValues['system_browser_update_check'],
    array('helpTextIdInline' => 'ORG_BROWSER_UPDATE_CHECK_DESC')
);
$formCommon->addSubmitButton(
    'btn_save_common', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'common', $gL10n->get('SYS_COMMON'), 'options.png', $formCommon->show(false)));

// PANEL: ORGANIZATION

$formOrganization = new HtmlForm(
    'organization_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'organization')),
    $page, array('class' => 'form-preferences')
);

$formOrganization->addInput(
    'org_shortname', $gL10n->get('SYS_NAME_ABBREVIATION'), $formValues['org_shortname'],
    array('property' => HtmlForm::FIELD_DISABLED, 'class' => 'form-control-small')
);
$formOrganization->addInput(
    'org_longname', $gL10n->get('SYS_NAME'), $formValues['org_longname'],
    array('maxLength' => 60, 'property' => HtmlForm::FIELD_REQUIRED)
);
$formOrganization->addInput(
    'org_homepage', $gL10n->get('SYS_WEBSITE'), $formValues['org_homepage'],
    array('maxLength' => 60)
);

if($gCurrentOrganization->countAllRecords() > 1)
{
    // Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
    if(!$gCurrentOrganization->isParentOrganization())
    {
        $sqlData = array();
        $sqlData['query'] = 'SELECT org_id, org_longname
                               FROM '.TBL_ORGANIZATIONS.'
                              WHERE org_id <> ? -- $orgId
                                AND org_org_id_parent IS NULL
                           ORDER BY org_longname ASC, org_shortname ASC';
        $sqlData['params'] = array($orgId);
        $formOrganization->addSelectBoxFromSql(
            'org_org_id_parent', $gL10n->get('ORG_PARENT_ORGANIZATION'), $gDb, $sqlData,
            array('defaultValue' => $formValues['org_org_id_parent'], 'helpTextIdInline' => 'ORG_PARENT_ORGANIZATION_DESC')
        );
    }

    $formOrganization->addCheckbox(
        'system_organization_select', $gL10n->get('ORG_SHOW_ORGANIZATION_SELECT'), (bool) $formValues['system_organization_select'],
        array('helpTextIdInline' => 'ORG_SHOW_ORGANIZATION_SELECT_DESC')
    );
}

$html = '<a id="add_another_organization" class="btn" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/preferences/preferences_function.php', array('mode' => '2')).'"><img
            src="'. THEME_URL. '/icons/add.png" alt="'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'" />'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'</a>';
$htmlDesc = $gL10n->get('ORG_ADD_ORGANIZATION_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formOrganization->addCustomContent($gL10n->get('ORG_NEW_ORGANIZATION'), $html, array('helpTextIdInline' => $htmlDesc));
$formOrganization->addSubmitButton(
    'btn_save_organization', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'organization', $gL10n->get('SYS_ORGANIZATION'), 'chart_organisation.png', $formOrganization->show(false)));

// PANEL: REGIONAL SETTINGS

$formRegionalSettings = new HtmlForm(
    'regional_settings_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'regional_settings')),
    $page, array('class' => 'form-preferences')
);

$formRegionalSettings->addInput(
    'system_timezone', $gL10n->get('ORG_TIMEZONE'), $gTimezone,
    array('property' => HtmlForm::FIELD_DISABLED, 'class' => 'form-control-small', 'helpTextIdInline' => 'ORG_TIMEZONE_DESC')
);
$formRegionalSettings->addSelectBox(
    'system_language', $gL10n->get('SYS_LANGUAGE'), $gL10n->getAvailableLanguages(),
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['system_language'])
);
$formRegionalSettings->addSelectBox(
    'default_country', $gL10n->get('PRO_DEFAULT_COUNTRY'), $gL10n->getCountries(),
    array('defaultValue' => $formValues['default_country'], 'helpTextIdInline' => 'PRO_DEFAULT_COUNTRY_DESC')
);
$formRegionalSettings->addInput(
    'system_date', $gL10n->get('ORG_DATE_FORMAT'), $formValues['system_date'],
    array('maxLength' => 20, 'helpTextIdInline' => array('ORG_DATE_FORMAT_DESC', array('<a href="https://secure.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
);
$formRegionalSettings->addInput(
    'system_time', $gL10n->get('ORG_TIME_FORMAT'), $formValues['system_time'],
    array('maxLength' => 20, 'helpTextIdInline' => array('ORG_TIME_FORMAT_DESC', array('<a href="https://secure.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
);
$formRegionalSettings->addInput(
    'system_currency', $gL10n->get('ORG_CURRENCY'), $formValues['system_currency'],
    array('maxLength' => 20, 'helpTextIdInline' => 'ORG_CURRENCY_DESC', 'class' => 'form-control-small')
);
$formRegionalSettings->addSubmitButton(
    'btn_save_regional_settings', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'regional_settings', $gL10n->get('ORG_REGIONAL_SETTINGS'), 'world.png', $formRegionalSettings->show(false)));

// PANEL: REGISTRATION

$formRegistration = new HtmlForm(
    'registration_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'registration')),
    $page, array('class' => 'form-preferences')
);

$formRegistration->addCheckbox(
    'registration_enable_module', $gL10n->get('ORG_ENABLE_REGISTRATION_MODULE'), (bool) $formValues['registration_enable_module'],
    array('helpTextIdInline' => 'ORG_ENABLE_REGISTRATION_MODULE_DESC')
);
$formRegistration->addCheckbox(
    'enable_registration_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), (bool) $formValues['enable_registration_captcha'],
    array('helpTextIdInline' => 'ORG_CAPTCHA_REGISTRATION')
);
$formRegistration->addCheckbox(
    'enable_registration_admin_mail', $gL10n->get('ORG_EMAIL_ALERTS'), (bool) $formValues['enable_registration_admin_mail'],
    array('helpTextIdInline' => array('ORG_EMAIL_ALERTS_DESC', array('ROL_RIGHT_APPROVE_USERS')))
);
$formRegistration->addSubmitButton(
    'btn_save_registration', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'registration', $gL10n->get('SYS_REGISTRATION'), 'new_registrations.png', $formRegistration->show(false)));

// PANEL: EMAIL DISPATCH

$formEmailDispatch = new HtmlForm(
    'email_dispatch_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'email_dispatch')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array('phpmail' => $gL10n->get('MAI_PHP_MAIL'), 'SMTP' => $gL10n->get('MAI_SMTP'));
$formEmailDispatch->addSelectBox(
    'mail_send_method', $gL10n->get('MAI_SEND_METHOD'), $selectBoxEntries,
    array('defaultValue' => $formValues['mail_send_method'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_SEND_METHOD_DESC')
);
$formEmailDispatch->addInput(
    'mail_sendmail_address', $gL10n->get('MAI_SENDER_EMAIL'), $formValues['mail_sendmail_address'],
    array('maxLength' => 50, 'helpTextIdInline' => array('MAI_SENDER_EMAIL_ADDRESS_DESC', array(DOMAIN)))
);
$formEmailDispatch->addInput(
    'mail_sendmail_name', $gL10n->get('MAI_SENDER_NAME'), $formValues['mail_sendmail_name'],
    array('maxLength' => 50, 'helpTextIdInline' => 'MAI_SENDER_NAME_DESC')
);
$selectBoxEntries = array(0 => $gL10n->get('MAI_HIDDEN'), 1 => $gL10n->get('MAI_SENDER'), 2 => $gL10n->get('SYS_ADMINISTRATOR'));
$formEmailDispatch->addSelectBox(
    'mail_recipients_with_roles', $gL10n->get('MAI_RECIPIENTS_WITH_ROLES'), $selectBoxEntries,
    array('defaultValue' => $formValues['mail_recipients_with_roles'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_RECIPIENTS_WITH_ROLES_DESC')
);
$formEmailDispatch->addInput(
    'mail_bcc_count', $gL10n->get('MAI_COUNT_BCC'), $formValues['mail_bcc_count'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'MAI_COUNT_BCC_DESC')
);
$selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
$formEmailDispatch->addSelectBox(
    'mail_character_encoding', $gL10n->get('MAI_CHARACTER_ENCODING'), $selectBoxEntries,
    array('defaultValue' => $formValues['mail_character_encoding'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_CHARACTER_ENCODING_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_host', $gL10n->get('MAI_SMTP_HOST'), $formValues['mail_smtp_host'],
    array('maxLength' => 50, 'helpTextIdInline' => 'MAI_SMTP_HOST_DESC')
);
$formEmailDispatch->addCheckbox(
    'mail_smtp_auth', $gL10n->get('MAI_SMTP_AUTH'), (bool) $formValues['mail_smtp_auth'],
    array('helpTextIdInline' => 'MAI_SMTP_AUTH_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_port', $gL10n->get('MAI_SMTP_PORT'), $formValues['mail_smtp_port'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'MAI_SMTP_PORT_DESC')
);
$selectBoxEntries = array(
    ''    => $gL10n->get('MAI_SMTP_SECURE_NO'),
    'ssl' => $gL10n->get('MAI_SMTP_SECURE_SSL'),
    'tls' => $gL10n->get('MAI_SMTP_SECURE_TLS')
);
$formEmailDispatch->addSelectBox(
    'mail_smtp_secure', $gL10n->get('MAI_SMTP_SECURE'), $selectBoxEntries,
    array('defaultValue' => $formValues['mail_smtp_secure'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_SMTP_SECURE_DESC')
);
$selectBoxEntries = array(
    'LOGIN' => $gL10n->get('MAI_SMTP_AUTH_LOGIN'),
    'PLAIN' => $gL10n->get('MAI_SMTP_AUTH_PLAIN'),
    'NTLM'  => $gL10n->get('MAI_SMTP_AUTH_NTLM')
);
$formEmailDispatch->addSelectBox(
    'mail_smtp_authentication_type', $gL10n->get('MAI_SMTP_AUTH_TYPE'), $selectBoxEntries,
    array('defaultValue' => $formValues['mail_smtp_authentication_type'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_SMTP_AUTH_TYPE_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_user', $gL10n->get('MAI_SMTP_USER'), $formValues['mail_smtp_user'],
    array('maxLength' => 100, 'helpTextIdInline' => 'MAI_SMTP_USER_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_password', $gL10n->get('MAI_SMTP_PASSWORD'), $formValues['mail_smtp_password'],
    array('type' => 'password', 'maxLength' => 50, 'helpTextIdInline' => 'MAI_SMTP_PASSWORD_DESC')
);
$formEmailDispatch->addSubmitButton(
    'btn_save_email_dispatch', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'email_dispatch', $gL10n->get('SYS_MAIL_DISPATCH'), 'system_mail.png', $formEmailDispatch->show(false)));

// PANEL: SYSTEM NOTIFICATION

$formSystemNotification = new HtmlForm(
    'system_notification_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'system_notification')),
    $page, array('class' => 'form-preferences')
);

$formSystemNotification->addCheckbox(
    'enable_system_mails', $gL10n->get('ORG_ACTIVATE_SYSTEM_MAILS'), (bool) $formValues['enable_system_mails'],
    array('helpTextIdInline' => 'ORG_ACTIVATE_SYSTEM_MAILS_DESC')
);
$formSystemNotification->addInput(
    'email_administrator', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $formValues['email_administrator'],
    array('type' => 'email', 'maxLength' => 50, 'helpTextIdInline' => 'ORG_SYSTEM_MAIL_ADDRESS_DESC')
);
$formSystemNotification->addCheckbox(
    'enable_email_notification', $gL10n->get('ORG_SYSTEM_MAIL_NEW_ENTRIES'), (bool) $formValues['enable_email_notification'],
    array('helpTextIdInline' => array('ORG_SYSTEM_MAIL_NEW_ENTRIES_DESC', array('<em>'.$gSettingsManager->getString('email_administrator').'</em>')))
);
$formSystemNotification->addCustomContent($gL10n->get('SYS_SYSTEM_MAILS'),
    '<p>'.$gL10n->get('ORG_SYSTEM_MAIL_TEXTS_DESC').':</p>
    <p><strong>#user_first_name#</strong> - '.$gL10n->get('ORG_VARIABLE_FIRST_NAME').'<br />
    <strong>#user_last_name#</strong> - '.$gL10n->get('ORG_VARIABLE_LAST_NAME').'<br />
    <strong>#user_login_name#</strong> - '.$gL10n->get('ORG_VARIABLE_USERNAME').'<br />
    <strong>#user_email#</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL').'<br />
    <strong>#administrator_email#</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION').'<br />
    <strong>#organization_short_name#</strong> - '.$gL10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION').'<br />
    <strong>#organization_long_name#</strong> - '.$gL10n->get('ORG_VARIABLE_NAME_ORGANIZATION').'<br />
    <strong>#organization_homepage#</strong> - '.$gL10n->get('ORG_VARIABLE_URL_ORGANIZATION').'</p>');

$text = new TableText($gDb);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_WEBMASTER', 'txt_org_id' => $orgId));
$formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_WEBMASTER', $gL10n->get('ORG_NOTIFY_ADMINISTRATOR'), $text->getValue('txt_text'), 7);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_USER', 'txt_org_id' => $orgId));
$formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_USER', $gL10n->get('ORG_CONFIRM_REGISTRATION'), $text->getValue('txt_text'), 7);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_REFUSE_REGISTRATION', 'txt_org_id' => $orgId));
$formSystemNotification->addMultilineTextInput('SYSMAIL_REFUSE_REGISTRATION', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $orgId));
$htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br /><strong>#variable1#</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD');
$formSystemNotification->addMultilineTextInput(
    'SYSMAIL_NEW_PASSWORD', $gL10n->get('ORG_SEND_NEW_PASSWORD'), $text->getValue('txt_text'), 7,
    array('helpTextIdInline' => $htmlDesc)
);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_ACTIVATION_LINK', 'txt_org_id' => $orgId));
$htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br /><strong>#variable1#</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br /><strong>#variable2#</strong> - '.$gL10n->get('ORG_VARIABLE_ACTIVATION_LINK');
$formSystemNotification->addMultilineTextInput(
    'SYSMAIL_ACTIVATION_LINK', $gL10n->get('ORG_NEW_PASSWORD_ACTIVATION_LINK'), $text->getValue('txt_text'), 7,
    array('helpTextIdInline' => $htmlDesc)
);

$formSystemNotification->addSubmitButton(
    'btn_save_system_notification', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'system_notification', $gL10n->get('SYS_SYSTEM_MAILS'), 'system_notification.png', $formSystemNotification->show(false)));

// PANEL: CAPTCHA

$formCaptcha = new HtmlForm(
    'captcha_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'captcha')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    'pic'  => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'),
    'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'),
    'word' => $gL10n->get('ORG_CAPTCHA_TYPE_WORDS')
);
$formCaptcha->addSelectBox(
    'captcha_type', $gL10n->get('ORG_CAPTCHA_TYPE'), $selectBoxEntries,
    array('defaultValue' => $formValues['captcha_type'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_CAPTCHA_TYPE_TEXT')
);

$fonts = admFuncGetDirectoryEntries(ADMIDIO_PATH . '/adm_program/system/fonts/');
asort($fonts);
$formCaptcha->addSelectBox(
    'captcha_fonts', $gL10n->get('SYS_FONT'), $fonts,
    array('defaultValue' => $formValues['captcha_fonts'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_CAPTCHA_FONT')
);
$formCaptcha->addInput(
    'captcha_width', $gL10n->get('SYS_WIDTH').' ('.$gL10n->get('ORG_PIXEL').')', $formValues['captcha_width'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'ORG_CAPTCHA_WIDTH_DESC')
);
$formCaptcha->addInput(
    'captcha_lines_numbers', $gL10n->get('ORG_CAPTCHA_LINES_NUMBERS'), $formValues['captcha_lines_numbers'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 25, 'step' => 1, 'helpTextIdInline' => 'ORG_CAPTCHA_LINES_NUMBERS_DESC')
);
$formCaptcha->addInput(
    'captcha_perturbation', $gL10n->get('ORG_CAPTCHA_DISTORTION'), $formValues['captcha_perturbation'],
    array('type' => 'string', 'helpTextIdInline' => 'ORG_CAPTCHA_DISTORTION_DESC', 'class' => 'form-control-small')
);
$backgrounds = admFuncGetDirectoryEntries(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/securimage/backgrounds/');
asort($backgrounds);
$formCaptcha->addSelectBox(
    'captcha_background_image', $gL10n->get('ORG_CAPTCHA_BACKGROUND_IMAGE'), $backgrounds,
    array('defaultValue' => $formValues['captcha_background_image'], 'showContextDependentFirstEntry' => true, 'helpTextIdInline' => 'ORG_CAPTCHA_BACKGROUND_IMAGE_DESC'));
$formCaptcha->addInput(
    'captcha_background_color', $gL10n->get('ORG_CAPTCHA_BACKGROUND_COLOR'), $formValues['captcha_background_color'],
    array('maxLength' => 7, 'class' => 'form-control-small'));
$formCaptcha->addInput(
    'captcha_text_color', $gL10n->get('ORG_CAPTCHA_CHARACTERS_COLOR'), $formValues['captcha_text_color'],
    array('maxLength' => 7, 'class' => 'form-control-small'));
$formCaptcha->addInput(
    'captcha_line_color', $gL10n->get('ORG_CAPTCHA_LINE_COLOR'), $formValues['captcha_line_color'],
    array('maxLength' => 7, 'helpTextIdInline' => array('ORG_CAPTCHA_COLOR_DESC', array('<a href="https://en.wikipedia.org/wiki/Web_colors">', '</a>')), 'class' => 'form-control-small'));
$formCaptcha->addInput(
    'captcha_charset', $gL10n->get('ORG_CAPTCHA_SIGNS'), $formValues['captcha_charset'],
    array('maxLength' => 80, 'helpTextIdInline' => 'ORG_CAPTCHA_SIGNS_TEXT')
);
$formCaptcha->addInput(
    'captcha_signature', $gL10n->get('ORG_CAPTCHA_SIGNATURE'), $formValues['captcha_signature'],
    array('maxLength' => 60, 'helpTextIdInline' => 'ORG_CAPTCHA_SIGNATURE_TEXT')
);
$html = '<img id="captcha" src="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php" alt="CAPTCHA Image" />
         <a class="admidio-icon-link" href="#" onclick="document.getElementById(\'captcha\').src=\'' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php?\' + Math.random(); return false"><img
            src="'.THEME_URL.'/icons/view-refresh.png" alt="'.$gL10n->get('SYS_RELOAD').'" title="'.$gL10n->get('SYS_RELOAD').'" /></a>';
$formCaptcha->addCustomContent(
    $gL10n->get('ORG_CAPTCHA_PREVIEW'), $html,
    array('helpTextIdInline' => 'ORG_CAPTCHA_PREVIEW_TEXT')
);

$formCaptcha->addSubmitButton(
    'btn_save_captcha', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('common', 'captcha', $gL10n->get('SYS_CAPTCHA'), 'captcha.png', $formCaptcha->show(false)));

// PANEL: SYSTEM INFORMATION

$formSystemInformation = new HtmlForm('system_information_preferences_form', null, $page);

$html = '<span id="admidio_version_content">'.ADMIDIO_VERSION_TEXT.'
            <a id="link_check_for_update" href="#link_check_for_update" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
         </span>';
$formSystemInformation->addCustomContent($gL10n->get('SYS_ADMIDIO_VERSION'), $html);

// if database version is different to file version, then show database version
if($gSystemComponent->getValue('com_version') !== ADMIDIO_VERSION)
{
    $formSystemInformation->addStaticControl('admidio_database_version', $gL10n->get('ORG_DIFFERENT_DATABASE_VERSION'), $gSystemComponent->getValue('com_version'));
}

$component = new ComponentUpdate($gDb);
$component->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
$updateStep = (int) $gSystemComponent->getValue('com_update_step');
$maxStep = $component->getMaxUpdateStep();
$textStep = $updateStep . ' / ' . $maxStep;
if ($updateStep === $maxStep)
{
    $html = getStaticText('success', $textStep);
}
elseif ($updateStep > $maxStep)
{
    $html = getStaticText('warning', $textStep);
}
else
{
    $html = getStaticText('danger', $textStep);
}
$formSystemInformation->addStaticControl('last_update_step', $gL10n->get('ORG_LAST_UPDATE_STEP'), $html);

if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
{
    $html = getStaticText('danger', PHP_VERSION, ' &rarr; '.$gL10n->get('SYS_PHP_VERSION_REQUIRED', array(MIN_PHP_VERSION)));
}
elseif (version_compare(PHP_VERSION, '5.6', '<'))
{
    $html = getStaticText('warning', PHP_VERSION, ' &rarr; '.$gL10n->get('SYS_PHP_VERSION_EOL', array('<a href="https://secure.php.net/supported-versions.php" target="_blank">Supported Versions</a>')));
}
else
{
    $html = getStaticText('success', PHP_VERSION);
}
$formSystemInformation->addStaticControl('php_version', $gL10n->get('SYS_PHP_VERSION'), $html);

if(version_compare($gDb->getVersion(), $gDb->getMinimumRequiredVersion(), '<'))
{
    $html = getStaticText('danger', $gDb->getVersion(), ' &rarr; '.$gL10n->get('SYS_DATABASE_VERSION_REQUIRED', array($gDb->getMinimumRequiredVersion())));
}
else
{
    $html = getStaticText('success', $gDb->getVersion());
}
$formSystemInformation->addStaticControl('database_version', $gDb->getName().'-'.$gL10n->get('SYS_VERSION'), $html);

// TODO deprecated: Remove if PHP 5.3 dropped
if(PhpIniUtils::isSafeModeEnabled())
{
    $gLogger->warning('DEPRECATED: Safe-Mode is enabled!');
    $html = getStaticText('danger', $gL10n->get('SYS_ON'), ' &rarr; '.$gL10n->get('SYS_SAFE_MODE_PROBLEM'));
}
else
{
    $html = getStaticText('success', $gL10n->get('SYS_OFF'));
}
$formSystemInformation->addStaticControl('safe_mode', $gL10n->get('SYS_SAFE_MODE'), $html);

try
{
    PasswordHashing::genRandomInt(0, 1, true);
    $html = getStaticText('success', $gL10n->get('SYS_SECURE'));
}
catch (AdmException $e)
{
    $html = getStaticText('danger', $gL10n->get('SYS_PRNG_INSECURE'), '<br />' . $e->getText());
}
$formSystemInformation->addStaticControl('pseudo_random_number_generator', $gL10n->get('SYS_PRNG'), $html);

if(is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess'))
{
    $html = getStaticText('success', $gL10n->get('SYS_ON'));
}
else
{
    $html = getStaticText(
        'danger',
        '<span id="directory_protection_status">' . $gL10n->get('SYS_OFF') . '</span>',
        ' &rarr; <a id="link_directory_protection" href="#link_directory_protection" title="'.$gL10n->get('SYS_CREATE_HTACCESS').'">'.$gL10n->get('SYS_CREATE_HTACCESS').'</a>'
    );
}
$formSystemInformation->addStaticControl('directory_protection', $gL10n->get('SYS_DIRECTORY_PROTECTION'), $html);

$postMaxSize = PhpIniUtils::getPostMaxSize();
if($postMaxSize === -1)
{
    $html = getStaticText('warning', $gL10n->get('SYS_NOT_SET'));
}
else
{
    $html = getStaticText('success', FileSystemUtils::getHumanReadableBytes($postMaxSize));
}
$formSystemInformation->addStaticControl('post_max_size', $gL10n->get('SYS_POST_MAX_SIZE'), $html);

$memoryLimit = PhpIniUtils::getMemoryLimit();
if($memoryLimit === -1)
{
    $html = getStaticText('warning', $gL10n->get('SYS_NOT_SET'));
}
else
{
    $html = getStaticText('success', FileSystemUtils::getHumanReadableBytes($memoryLimit));
}
$formSystemInformation->addStaticControl('memory_limit', $gL10n->get('SYS_MEMORY_LIMIT'), $html);

if(PhpIniUtils::isFileUploadEnabled())
{
    $html = getStaticText('success', $gL10n->get('SYS_ON'));
}
else
{
    $html = getStaticText('danger', $gL10n->get('SYS_OFF'));
}
$formSystemInformation->addStaticControl('file_uploads', $gL10n->get('SYS_FILE_UPLOADS'), $html);

$fileUploadMaxFileSize = PhpIniUtils::getFileUploadMaxFileSize();
if($fileUploadMaxFileSize === -1)
{
    $html = getStaticText('warning', $gL10n->get('SYS_NOT_SET'));
}
else
{
    $html = getStaticText('success', FileSystemUtils::getHumanReadableBytes($fileUploadMaxFileSize));
}
$formSystemInformation->addStaticControl('upload_max_filesize', $gL10n->get('SYS_UPLOAD_MAX_FILESIZE'), $html);

$formSystemInformation->addStaticControl('max_processable_image_size', $gL10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE'), round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('SYS_MEGAPIXEL'));
$html = '<a href="' . ADMIDIO_URL . '/adm_program/system/phpinfo.php' . '" target="_blank">phpinfo()</a>';
$formSystemInformation->addStaticControl('php_info', $gL10n->get('SYS_PHP_INFO'), $html);

if($gDebug)
{
    $html = getStaticText('danger', $gL10n->get('SYS_ON'));
}
else
{
    $html = getStaticText('success', $gL10n->get('SYS_OFF'));
}
$formSystemInformation->addStaticControl('debug_mode', $gL10n->get('SYS_DEBUG_MODUS'), $html);

try
{
    $diskSpace = FileSystemUtils::getDiskSpace();

    $diskUsagePercent = round(($diskSpace['used'] / $diskSpace['total']) * 100, 1);
    $progressBarClass = '';
    if ($diskUsagePercent > 90)
    {
        $progressBarClass = ' progress-bar-danger';
    }
    elseif ($diskUsagePercent > 70)
    {
        $progressBarClass = ' progress-bar-warning';
    }
    $html = '
    <div class="progress">
        <div class="progress-bar' . $progressBarClass . '" role="progressbar" aria-valuenow="' . $diskSpace['used'] . '" aria-valuemin="0" aria-valuemax="' . $diskSpace['total'] . '" style="width: ' . $diskUsagePercent . '%;">
            ' . FileSystemUtils::getHumanReadableBytes($diskSpace['used']) . ' / ' . FileSystemUtils::getHumanReadableBytes($diskSpace['total']) . '
        </div>
    </div>';
}
catch (\RuntimeException $exception)
{
    $gLogger->error('FILE-SYSTEM: Disk space could not be determined!');

    $html = getStaticText('danger', $gL10n->get('SYS_DISK_SPACE_ERROR', array($exception->getMessage())));
}
$formSystemInformation->addStaticControl('disk_space', $gL10n->get('SYS_DISK_SPACE'), $html);

$page->addHtml(getPreferencePanel('common', 'system_information', $gL10n->get('ORG_SYSTEM_INFORMATION'), 'info.png', $formSystemInformation->show(false)));

$page->addHtml('
        </div>
    </div>
    <div class="tab-pane" id="tabs-modules">
        <div class="panel-group" id="accordion_modules">');

// PANEL: ANNOUNCEMENTS

$formAnnouncements = new HtmlForm(
    'announcements_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'announcements')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DEACTIVATED'),
    '1' => $gL10n->get('SYS_ACTIVATED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formAnnouncements->addSelectBox(
    'enable_announcements_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries,
    array('defaultValue' => $formValues['enable_announcements_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$formAnnouncements->addInput(
    'announcements_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $formValues['announcements_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$html = '<a class="btn" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'ANN')).'"><img
            src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
$htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formAnnouncements->addCustomContent(
    $gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html,
    array('helpTextIdInline' => $htmlDesc)
);
$formAnnouncements->addSubmitButton(
    'btn_save_announcements', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'announcements', $gL10n->get('ANN_ANNOUNCEMENTS'), 'announcements.png', $formAnnouncements->show(false)));

// PANEL: USER MANAGEMENT

$formUserManagement = new HtmlForm(
    'user_management_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'user_management')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
$formUserManagement->addSelectBox(
    'members_users_per_page', $gL10n->get('MEM_USERS_PER_PAGE'), $selectBoxEntries,
    array('defaultValue' => $formValues['members_users_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MEM_USERS_PER_PAGE_DESC')
);
$formUserManagement->addInput(
    'members_days_field_history', $gL10n->get('MEM_DAYS_FIELD_HISTORY'), $formValues['members_days_field_history'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999999999, 'step' => 1, 'helpTextIdInline' => 'MEM_DAYS_FIELD_HISTORY_DESC')
);
$formUserManagement->addCheckbox(
    'members_show_all_users', $gL10n->get('ORG_SHOW_ALL_USERS'), (bool) $formValues['members_show_all_users'],
    array('helpTextIdInline' => 'ORG_SHOW_ALL_USERS_DESC')
);
$formUserManagement->addCheckbox(
    'members_enable_user_relations', $gL10n->get('MEM_ENABLE_USER_RELATIONS'), (bool) $formValues['members_enable_user_relations'],
    array('helpTextIdInline' => 'MEM_ENABLE_USER_RELATIONS_DESC')
);
$formUserManagement->addSubmitButton(
    'btn_save_user_management', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'user_administration', $gL10n->get('MEM_USER_MANAGEMENT'), 'user_administration.png', $formUserManagement->show(false)));

// PANEL: DOWNLOADS

$formDownloads = new HtmlForm(
    'downloads_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'downloads')),
    $page, array('class' => 'form-preferences')
);

$formDownloads->addCheckbox(
    'enable_download_module', $gL10n->get('DOW_ENABLE_DOWNLOAD_MODULE'), (bool) $formValues['enable_download_module'],
    array('helpTextIdInline' => 'DOW_ENABLE_DOWNLOAD_MODULE_DESC')
);
$formDownloads->addInput(
    'max_file_upload_size', $gL10n->get('DOW_MAXIMUM_FILE_SIZE').' (MB)', $formValues['max_file_upload_size'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999999, 'step' => 1, 'helpTextIdInline' => 'DOW_MAXIMUM_FILE_SIZE_DESC')
);
$formDownloads->addSubmitButton(
    'btn_save_downloads', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'downloads', $gL10n->get('DOW_DOWNLOADS'), 'download.png', $formDownloads->show(false)));

// PANEL: PHOTOS

$formPhotos = new HtmlForm(
    'photos_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'photos')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DEACTIVATED'),
    '1' => $gL10n->get('SYS_ACTIVATED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formPhotos->addSelectBox(
    'enable_photo_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries,
    array('defaultValue' => $formValues['enable_photo_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$selectBoxEntries = array(
    '1' => $gL10n->get('PHO_MODAL_WINDOW'),
    '2' => $gL10n->get('PHO_SAME_WINDOW'),
    '0' => $gL10n->get('PHO_POPUP_WINDOW')
);
$formPhotos->addSelectBox(
    'photo_show_mode', $gL10n->get('PHO_DISPLAY_PHOTOS'), $selectBoxEntries,
    array('defaultValue' => $formValues['photo_show_mode'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PHO_DISPLAY_PHOTOS_DESC')
);
$formPhotos->addInput(
    'photo_albums_per_page', $gL10n->get('PHO_NUMBER_OF_ALBUMS_PER_PAGE'), $formValues['photo_albums_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$formPhotos->addInput(
    'photo_thumbs_page', $gL10n->get('PHO_THUMBNAILS_PER_PAGE'), $formValues['photo_thumbs_page'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_THUMBNAILS_PER_PAGE_DESC')
);
$formPhotos->addInput(
    'photo_thumbs_scale', $gL10n->get('PHO_SCALE_THUMBNAILS'), $formValues['photo_thumbs_scale'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_SCALE_THUMBNAILS_DESC')
);
$formPhotos->addInput(
    'photo_save_scale', $gL10n->get('PHO_SCALE_AT_UPLOAD'), $formValues['photo_save_scale'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_SCALE_AT_UPLOAD_DESC')
);
$formPhotos->addInput(
    'photo_show_width', $gL10n->get('PHO_MAX_PHOTO_SIZE_WIDTH'), $formValues['photo_show_width'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1)
);
$formPhotos->addInput(
    'photo_show_height', $gL10n->get('PHO_MAX_PHOTO_SIZE_HEIGHT'), $formValues['photo_show_height'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_MAX_PHOTO_SIZE_DESC')
);
$formPhotos->addInput(
    'photo_image_text', $gL10n->get('PHO_SHOW_CAPTION'), $formValues['photo_image_text'],
    array('maxLength' => 60, 'helpTextIdInline' => array('PHO_SHOW_CAPTION_DESC', array(DOMAIN)))
);
$formPhotos->addInput(
    'photo_image_text_size', $gL10n->get('PHO_CAPTION_SIZE'), $formValues['photo_image_text_size'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_CAPTION_SIZE_DESC')
);
$formPhotos->addCheckbox(
    'photo_download_enabled', $gL10n->get('PHO_DOWNLOAD_ENABLED'), (bool) $formValues['photo_download_enabled'],
    array('helpTextIdInline' => array('PHO_DOWNLOAD_ENABLED_DESC', array($gL10n->get('PHO_KEEP_ORIGINAL'))))
);
$formPhotos->addCheckbox(
    'photo_keep_original', $gL10n->get('PHO_KEEP_ORIGINAL'), (bool) $formValues['photo_keep_original'],
    array('helpTextIdInline' => array('PHO_KEEP_ORIGINAL_DESC', array($gL10n->get('PHO_DOWNLOAD_ENABLED'))))
);
$formPhotos->addSubmitButton(
    'btn_save_photos', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'photos', $gL10n->get('PHO_PHOTOS'), 'photo.png', $formPhotos->show(false)));

// PANEL: GUESTBOOK

$formGuestbook = new HtmlForm(
    'guestbook_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'guestbook')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DEACTIVATED'),
    '1' => $gL10n->get('SYS_ACTIVATED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formGuestbook->addSelectBox(
    'enable_guestbook_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries,
    array('defaultValue' => $formValues['enable_guestbook_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$formGuestbook->addInput(
    'guestbook_entries_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $formValues['guestbook_entries_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$formGuestbook->addCheckbox(
    'enable_guestbook_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), (bool) $formValues['enable_guestbook_captcha'],
    array('helpTextIdInline' => 'GBO_CAPTCHA_DESC')
);
$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_NOBODY'),
    '1' => $gL10n->get('GBO_ONLY_VISITORS'),
    '2' => $gL10n->get('SYS_ALL')
);
$formGuestbook->addSelectBox(
    'enable_guestbook_moderation', $gL10n->get('GBO_GUESTBOOK_MODERATION'), $selectBoxEntries,
    array('defaultValue' => $formValues['enable_guestbook_moderation'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'GBO_GUESTBOOK_MODERATION_DESC')
);
$formGuestbook->addCheckbox(
    'enable_gbook_comments4all', $gL10n->get('GBO_COMMENTS4ALL'), (bool) $formValues['enable_gbook_comments4all'],
    array('helpTextIdInline' => 'GBO_COMMENTS4ALL_DESC')
);
$formGuestbook->addCheckbox(
    'enable_intial_comments_loading', $gL10n->get('GBO_INITIAL_COMMENTS_LOADING'), (bool) $formValues['enable_intial_comments_loading'],
    array('helpTextIdInline' => 'GBO_INITIAL_COMMENTS_LOADING_DESC')
);
$formGuestbook->addInput(
    'flooding_protection_time', $gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL'), $formValues['flooding_protection_time'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'GBO_FLOODING_PROTECTION_INTERVALL_DESC')
);
$formGuestbook->addSubmitButton(
    'btn_save_guestbook', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'guestbook', $gL10n->get('GBO_GUESTBOOK'), 'guestbook.png', $formGuestbook->show(false)));

// PANEL: ECARDS

$formEcards = new HtmlForm(
    'ecards_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'ecards')),
    $page, array('class' => 'form-preferences')
);

$formEcards->addCheckbox(
    'enable_ecard_module', $gL10n->get('ECA_ACTIVATE_GREETING_CARDS'), (bool) $formValues['enable_ecard_module'],
    array('helpTextIdInline' => 'ECA_ACTIVATE_GREETING_CARDS_DESC')
);
$formEcards->addInput(
    'ecard_thumbs_scale', $gL10n->get('PHO_SCALE_THUMBNAILS'), $formValues['ecard_thumbs_scale'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'ECA_SCALE_THUMBNAILS_DESC')
);
$formEcards->addInput(
    'ecard_card_picture_width', $gL10n->get('PHO_MAX_PHOTO_SIZE_WIDTH'), $formValues['ecard_card_picture_width'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1)
);
$formEcards->addInput(
    'ecard_card_picture_height', $gL10n->get('PHO_MAX_PHOTO_SIZE_HEIGHT'), $formValues['ecard_card_picture_height'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'ECA_MAX_PHOTO_SIZE_DESC')
);
$templates = admFuncGetDirectoryEntries(THEME_ADMIDIO_PATH.'/ecard_templates');
if (!is_array($templates))
{
    $gMessage->show($gL10n->get('ECA_TEMPLATE_FOLDER_OPEN'));
    // => EXIT
}
foreach($templates as &$templateName)
{
    $templateName = ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $templateName)));
}
unset($templateName);
$formEcards->addSelectBox(
    'ecard_template', $gL10n->get('ECA_TEMPLATE'), $templates,
    array('defaultValue' => $formValues['ecard_template'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ECA_TEMPLATE_DESC')
);
$formEcards->addSubmitButton(
    'btn_save_ecards', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'ecards', $gL10n->get('ECA_GREETING_CARDS'), 'ecard.png', $formEcards->show(false)));

// PANEL: LISTS

$formLists = new HtmlForm(
    'lists_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'lists')),
    $page, array('class' => 'form-preferences')
);

$formLists->addCheckbox(
    'lists_enable_module', $gL10n->get('LST_ENABLE_LISTS_MODULE'), (bool) $formValues['lists_enable_module'],
    array('helpTextIdInline' => 'LST_ENABLE_LISTS_MODULE_DESC')
);
$formLists->addInput(
    'lists_roles_per_page', $gL10n->get('LST_NUMBER_OF_ROLES_PER_PAGE'), $formValues['lists_roles_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
$formLists->addSelectBox(
    'lists_members_per_page', $gL10n->get('LST_MEMBERS_PER_PAGE'), $selectBoxEntries,
    array('defaultValue' => $formValues['lists_members_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'LST_MEMBERS_PER_PAGE_DESC')
);
$formLists->addCheckbox(
    'lists_hide_overview_details', $gL10n->get('LST_HIDE_DETAILS'), (bool) $formValues['lists_hide_overview_details'],
    array('helpTextIdInline' => 'LST_HIDE_DETAILS_DESC')
);
// read all global lists
$sqlData = array();
$sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM '.TBL_LISTS.'
                      WHERE lst_org_id = ? -- $orgId
                        AND lst_global = 1
                   ORDER BY lst_name ASC, lst_timestamp DESC';
$sqlData['params'] = array($orgId);
$formLists->addSelectBoxFromSql(
    'lists_default_configuration', $gL10n->get('LST_DEFAULT_CONFIGURATION'), $gDb, $sqlData,
    array('defaultValue' => $formValues['lists_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'LST_DEFAULT_CONFIGURATION_DESC')
);
$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_NOBODY'),
    '1' => preg_replace('/<\/?strong>/', '"', $gL10n->get('LST_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('ROL_RIGHT_ASSIGN_ROLES')))),
    '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('LST_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('ROL_RIGHT_EDIT_USER'))))
);
$formLists->addSelectBox(
    'lists_show_former_members', $gL10n->get('LST_SHOW_FORMER_MEMBERS'), $selectBoxEntries,
    array('defaultValue' => $formValues['lists_show_former_members'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('LST_SHOW_FORMER_MEMBERS_DESC', array($gL10n->get('LST_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('ROL_RIGHT_EDIT_USER'))))))
);
$html = '<a class="btn" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'ROL')).'"><img
            src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
$htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formLists->addCustomContent($gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html, array('helpTextIdInline' => $htmlDesc));
$formLists->addSubmitButton(
    'btn_save_lists', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'lists', $gL10n->get('LST_LISTS'), 'list.png', $formLists->show(false)));

// PANEL: MESSAGES

$formMessages = new HtmlForm(
    'messages_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'messages')),
    $page, array('class' => 'form-preferences')
);

$formMessages->addCheckbox(
    'enable_mail_module', $gL10n->get('MAI_ACTIVATE_EMAIL_MODULE'), (bool) $formValues['enable_mail_module'],
    array('helpTextIdInline' => 'MAI_ACTIVATE_EMAIL_MODULE_DESC')
);
$formMessages->addCheckbox(
    'enable_pm_module', $gL10n->get('MSG_ACTIVATE_PM_MODULE'), (bool) $formValues['enable_pm_module'],
    array('helpTextIdInline' => 'MSG_ACTIVATE_PM_MODULE_DESC')
);
$formMessages->addCheckbox(
    'enable_chat_module', $gL10n->get('MSG_ACTIVATE_CHAT_MODULE'), (bool) $formValues['enable_chat_module'],
    array('helpTextIdInline' => 'MSG_ACTIVATE_CHAT_MODULE_DESC')
);
$formMessages->addCheckbox(
    'enable_mail_captcha', $gL10n->get('ORG_ENABLE_CAPTCHA'), (bool) $formValues['enable_mail_captcha'],
    array('helpTextIdInline' => 'MAI_SHOW_CAPTCHA_DESC')
);
$formMessages->addInput(
    'mail_max_receiver', $gL10n->get('MAI_MAX_RECEIVER'), $formValues['mail_max_receiver'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'MAI_MAX_RECEIVER_DESC')
);
$formMessages->addCheckbox(
    'mail_show_former', $gL10n->get('MSG_SHOW_FORMER'), (bool) $formValues['mail_show_former'],
    array('helpTextIdInline' => 'MSG_SHOW_FORMER_DESC')
);
$formMessages->addCheckbox(
    'mail_into_to', $gL10n->get('MAI_INTO_TO'), (bool) $formValues['mail_into_to'],
    array('helpTextIdInline' => 'MAI_INTO_TO_DESC')
);
$formMessages->addInput(
    'max_email_attachment_size', $gL10n->get('MAI_ATTACHMENT_SIZE').' (MB)', $formValues['max_email_attachment_size'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999, 'step' => 1, 'helpTextIdInline' => 'MAI_ATTACHMENT_SIZE_DESC')
);
$formMessages->addCheckbox(
    'mail_html_registered_users', $gL10n->get('MAI_HTML_MAILS_REGISTERED_USERS'), (bool) $formValues['mail_html_registered_users'],
    array('helpTextIdInline' => 'MAI_HTML_MAILS_REGISTERED_USERS_DESC')
);
$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DEACTIVATED'),
    '1' => $gL10n->get('SYS_ACTIVATED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formMessages->addSelectBox(
    'mail_delivery_confirmation', $gL10n->get('MAI_DELIVERY_CONFIRMATION'), $selectBoxEntries,
    array('defaultValue' => $formValues['mail_delivery_confirmation'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'MAI_DELIVERY_CONFIRMATION_DESC')
);
$formMessages->addSubmitButton(
    'btn_save_messages', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'messages', $gL10n->get('SYS_MESSAGES'), 'messages.png', $formMessages->show(false)));

// PANEL: PROFILE

$formProfile = new HtmlForm(
    'profile_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'profile')),
    $page, array('class' => 'form-preferences')
);

$html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/preferences/fields.php"><img
            src="'. THEME_URL. '/icons/application_form_edit.png" alt="'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'" />'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'</a>';
$htmlDesc = $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formProfile->addCustomContent($gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), $html, array('helpTextIdInline' => $htmlDesc));
$formProfile->addCheckbox(
    'profile_log_edit_fields', $gL10n->get('PRO_LOG_EDIT_FIELDS'), (bool) $formValues['profile_log_edit_fields'],
    array('helpTextIdInline' => 'PRO_LOG_EDIT_FIELDS_DESC')
);
$formProfile->addCheckbox(
    'profile_show_map_link', $gL10n->get('PRO_SHOW_MAP_LINK'), (bool) $formValues['profile_show_map_link'],
    array('helpTextIdInline' => 'PRO_SHOW_MAP_LINK_DESC')
);
$formProfile->addCheckbox(
    'profile_show_roles', $gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP'), (bool) $formValues['profile_show_roles'],
    array('helpTextIdInline' => 'PRO_SHOW_ROLE_MEMBERSHIP_DESC')
);
$formProfile->addCheckbox(
    'profile_show_former_roles', $gL10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP'), (bool) $formValues['profile_show_former_roles'],
    array('helpTextIdInline' => 'PRO_SHOW_FORMER_ROLE_MEMBERSHIP_DESC')
);

if($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->isParentOrganization())
{
    $formProfile->addCheckbox(
        'profile_show_extern_roles', $gL10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS'), (bool) $formValues['profile_show_extern_roles'],
        array('helpTextIdInline' => 'PRO_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC')
    );
}

$selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
$formProfile->addSelectBox(
    'profile_photo_storage', $gL10n->get('PRO_LOCATION_PROFILE_PICTURES'), $selectBoxEntries,
    array('defaultValue' => $formValues['profile_photo_storage'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PRO_LOCATION_PROFILE_PICTURES_DESC')
);
$formProfile->addSubmitButton(
    'btn_save_profile', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'profile', $gL10n->get('PRO_PROFILE'), 'profile.png', $formProfile->show(false)));

// PANEL: EVENTS

$formEvents = new HtmlForm(
    'events_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'events')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DEACTIVATED'),
    '1' => $gL10n->get('SYS_ACTIVATED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formEvents->addSelectBox(
    'enable_dates_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries,
    array('defaultValue' => $formValues['enable_dates_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
if($gSettingsManager->getBool('dates_show_rooms'))
{
    $selectBoxEntries = array(
        'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
        'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
        'room'         => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'),
        'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
        'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
    );
}
else
{
    $selectBoxEntries = array(
        'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
        'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
        'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
        'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
    );
}
$formEvents->addSelectBox(
    'dates_view', $gL10n->get('DAT_VIEW_MODE'), $selectBoxEntries,
    array('defaultValue' => $formValues['dates_view'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('DAT_VIEW_MODE_DESC', array('DAT_VIEW_MODE_DETAIL', 'DAT_VIEW_MODE_COMPACT')))
);
$formEvents->addInput(
    'dates_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $formValues['dates_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$formEvents->addCheckbox(
    'enable_dates_ical', $gL10n->get('DAT_ENABLE_ICAL'), (bool) $formValues['enable_dates_ical'],
    array('helpTextIdInline' => 'DAT_ENABLE_ICAL_DESC')
);
$formEvents->addInput(
    'dates_ical_days_past', $gL10n->get('DAT_ICAL_DAYS_PAST'), $formValues['dates_ical_days_past'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'DAT_ICAL_DAYS_PAST_DESC')
);
$formEvents->addInput(
    'dates_ical_days_future', $gL10n->get('DAT_ICAL_DAYS_FUTURE'), $formValues['dates_ical_days_future'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'DAT_ICAL_DAYS_FUTURE_DESC')
);
$formEvents->addCheckbox(
    'dates_show_map_link', $gL10n->get('DAT_SHOW_MAP_LINK'), (bool) $formValues['dates_show_map_link'],
    array('helpTextIdInline' => 'DAT_SHOW_MAP_LINK_DESC')
);
$sqlData = array();
$sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM '.TBL_LISTS.'
                      WHERE lst_org_id = ? -- $orgId
                        AND lst_global = 1
                   ORDER BY lst_name ASC, lst_timestamp DESC';
$sqlData['params'] = array($orgId);
$formEvents->addSelectBoxFromSql(
    'dates_default_list_configuration', $gL10n->get('DAT_DEFAULT_LIST_CONFIGURATION'), $gDb, $sqlData,
    array('defaultValue' => $formValues['dates_default_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'DAT_DEFAULT_LIST_CONFIGURATION_DESC')
);
$formEvents->addCheckbox(
    'dates_save_all_confirmations', $gL10n->get('DAT_SAVE_ALL_CONFIRMATIONS'), (bool) $formValues['dates_save_all_confirmations'],
    array('helpTextIdInline' => 'DAT_SAVE_ALL_CONFIRMATIONS_DESC')
);
$html = '<a class="btn" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'DAT', 'title' => $gL10n->get('DAT_CALENDAR'))).'"><img
            src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'</a>';
$htmlDesc = $gL10n->get('DAT_EDIT_CALENDAR_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formEvents->addCustomContent($gL10n->get('DAT_MANAGE_CALENDARS'), $html, array('helpTextIdInline' => $htmlDesc));
$formEvents->addCheckbox(
    'dates_show_rooms', $gL10n->get('DAT_ROOM_SELECTABLE'), (bool) $formValues['dates_show_rooms'],
    array('helpTextIdInline' => 'DAT_ROOM_SELECTABLE_DESC')
);
$html = '<a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/rooms/rooms.php"><img
            src="'. THEME_URL. '/icons/home.png" alt="'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'" />'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>';
$htmlDesc = $gL10n->get('DAT_EDIT_ROOMS_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formEvents->addCustomContent($gL10n->get('DAT_EDIT_ROOMS'), $html, array('helpTextIdInline' => $htmlDesc));
$formEvents->addSubmitButton(
    'btn_save_events', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'events', $gL10n->get('DAT_DATES'), 'dates.png', $formEvents->show(false)));

// PANEL: WEBLINKS

$formWeblinks = new HtmlForm(
    'links_preferences_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'links')),
    $page, array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DEACTIVATED'),
    '1' => $gL10n->get('SYS_ACTIVATED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formWeblinks->addSelectBox(
    'enable_weblinks_module', $gL10n->get('ORG_ACCESS_TO_MODULE'), $selectBoxEntries,
    array('defaultValue' => $formValues['enable_weblinks_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$formWeblinks->addInput(
    'weblinks_per_page', $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'), $formValues['weblinks_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(0)))
);
$selectBoxEntries = array('_self' => $gL10n->get('LNK_SAME_WINDOW'), '_blank' => $gL10n->get('LNK_NEW_WINDOW'));
$formWeblinks->addSelectBox(
    'weblinks_target', $gL10n->get('LNK_LINK_TARGET'), $selectBoxEntries,
    array('defaultValue' => $formValues['weblinks_target'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'LNK_LINK_TARGET_DESC')
);
$formWeblinks->addInput(
    'weblinks_redirect_seconds', $gL10n->get('LNK_DISPLAY_REDIRECT'), $formValues['weblinks_redirect_seconds'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'LNK_DISPLAY_REDIRECT_DESC')
);
$html = '<a class="btn" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'LNK')).'"><img
            src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'" />'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
$htmlDesc = $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC').'<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'</div>';
$formWeblinks->addCustomContent(
    $gL10n->get('SYS_MAINTAIN_CATEGORIES'), $html,
    array('helpTextIdInline' => $htmlDesc)
);
$formWeblinks->addSubmitButton(
    'btn_save_links', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);

$page->addHtml(getPreferencePanel('modules', 'links', $gL10n->get('LNK_WEBLINKS'), 'weblinks.png', $formWeblinks->show(false)));

$page->addHtml('
        </div>
    </div>
</div>');

$page->show();
