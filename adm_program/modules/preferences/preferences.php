<?php
/**
 ***********************************************************************************************
 * Organization preferences
 *
 * @copyright 2004-2023 The Admidio Team
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
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

/**
 * Read all file names of a folder and return an array where the file names are the keys and a readable
 * version of the file names are the values.
 * @param $folder
 * @return false|int[]|string[]
 * @throws UnexpectedValueException
 * @throws RuntimeException
 */
function getArrayFileNames($folder)
{
    // get all files from the folder
    $files = array_keys(FileSystemUtils::getDirectoryContent($folder, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));

    foreach ($files as &$templateName) {
        $templateName = ucfirst(preg_replace('/[_-]/', ' ', str_replace(array('.tpl', '.html', '.txt'), '', $templateName)));
    }
    unset($templateName);

    return $files;
}

// read organization and all system preferences values into form array
$formValues = array_merge($gCurrentOrganization->getDbColumns(), $gSettingsManager->getAll());

// create html page object
$page = new HtmlPage('admidio-preferences', $headline);

$showOptionValidModules = array(
    'announcements', 'documents-files', 'guestbook', 'ecards', 'groups-roles',
    'messages', 'photos', 'profile', 'events', 'links', 'user_management', 'category-report'
);

// open the modules tab if the options of a module should be shown
if (in_array($showOption, $showOptionValidModules, true)) {
    $page->addJavascript(
        '
        $("#tabs_nav_modules").attr("class", "nav-link active");
        $("#tabs-modules").attr("class", "tab-pane fade show active");
        $("#collapse_'.$showOption.'").attr("class", "collapse show");
        location.hash = "#" + "panel_'.$showOption.'";',
        true
    );
} else {
    $page->addJavascript(
        '
        $("#tabs_nav_common").attr("class", "nav-link active");
        $("#tabs-common").attr("class", "tab-pane fade show active");
        $("#collapse_'.$showOption.'").attr("class", "collapse show");
        location.hash = "#" + "panel_'.$showOption.'";',
        true
    );
}

$page->addJavascript(
    '
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
                        $("#captcha").attr("src", "' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/dapphp/securimage/securimage_show.php?" + Math.random());
                    }
                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<i class=\"fas fa-check\"></i><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<i class=\"fas fa-exclamation-circle\"></i>" + data);
                }
            }
        });
    });

    $("#link_check_for_update").click(function() {
        var admVersionContent = $("#admidio_version_content");

        admVersionContent.html("<i class=\"fas fa-spinner fa-spin\"></i>").show();
        $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/preferences/update_check.php", {mode: "2"}, function(htmlVersion) {
            admVersionContent.html(htmlVersion);
        });
        return false;
    });

    $("#link_directory_protection").click(function() {
        var dirProtectionStatus = $("#directory_protection_status");

        dirProtectionStatus.html("<i class=\"fas fa-spinner fa-spin\"></i>").show();
        $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php", {mode: "4"}, function(statusText) {
            var directoryProtection = dirProtectionStatus.parent().parent().parent();
            directoryProtection.html("<span class=\"text-success\"><strong>" + statusText + "</strong></span>");
        });
        return false;
    });',
    true
);

if ($showOption !== '') {
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
} else {
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-cog');
}

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
function getPreferencePanel($group, $id, $parentId, $title, $icon, $body)
{
    $html = '
        <div id="admidio-panel-' . $id . '" class="card">
            <div class="card-header" data-toggle="collapse" data-target="#collapse_' . $id . '">
                <i class="' . $icon . ' fa-fw"></i>' . $title . '
            </div>
            <div id="collapse_' . $id . '" class="collapse" aria-labelledby="headingOne" data-parent="#' . $parentId . '">
                <div class="card-body">
                    ' . $body . '
                </div>
            </div>
        </div>
    ';
    return $html;
}

$page->addHtml('
<ul id="admidio-preferences-tabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a id="tabs_nav_common" class="nav-link" href="#tabs-common" data-toggle="tab" role="tab">'.$gL10n->get('SYS_COMMON').'</a>
    </li>
    <li class="nav-item">
        <a id="tabs_nav_modules" class="nav-link" href="#tabs-modules" data-toggle="tab" role="tab">'.$gL10n->get('SYS_MODULES').'</a>
    </li>
</ul>

<div id="admidio-preferences-tab-content" class="tab-content">
    <div class="tab-pane fade" id="tabs-common" role="tabpanel">
        <div class="accordion" id="accordion_preferences">');

// PANEL: COMMON

$formCommon = new HtmlForm(
    'common_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'common')),
    $page,
    array('class' => 'form-preferences')
);

// search all available themes in theme folder
$themes = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_THEMES, false, false, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY)));
if (count($themes) === 0) {
    $gMessage->show($gL10n->get('SYS_TEMPLATE_FOLDER_OPEN'));
    // => EXIT
}
$formCommon->addSelectBox(
    'theme',
    $gL10n->get('ORG_ADMIDIO_THEME'),
    $themes,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['theme'], 'arrayKeyIsNotValue' => true, 'helpTextIdInline' => 'ORG_ADMIDIO_THEME_DESC')
);
$formCommon->addInput(
    'homepage_logout',
    $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('SYS_VISITORS').')',
    $formValues['homepage_logout'],
    array('maxLength' => 250, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdInline' => 'ORG_HOMEPAGE_VISITORS')
);
$formCommon->addInput(
    'homepage_login',
    $gL10n->get('SYS_HOMEPAGE').'<br />('.$gL10n->get('ORG_REGISTERED_USERS').')',
    $formValues['homepage_login'],
    array('maxLength' => 250, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdInline' => 'ORG_HOMEPAGE_REGISTERED_USERS')
);
$formCommon->addCheckbox(
    'enable_rss',
    $gL10n->get('ORG_ENABLE_RSS_FEEDS'),
    (bool) $formValues['enable_rss'],
    array('helpTextIdInline' => 'ORG_ENABLE_RSS_FEEDS_DESC')
);
$formCommon->addCheckbox(
    'system_cookie_note',
    $gL10n->get('SYS_COOKIE_NOTE'),
    (bool) $formValues['system_cookie_note'],
    array('helpTextIdInline' => 'SYS_COOKIE_NOTE_DESC')
);
$formCommon->addCheckbox(
    'system_search_similar',
    $gL10n->get('ORG_SEARCH_SIMILAR_NAMES'),
    (bool) $formValues['system_search_similar'],
    array('helpTextIdInline' => 'ORG_SEARCH_SIMILAR_NAMES_DESC')
);
$selectBoxEntries = array(0 => $gL10n->get('SYS_DONT_SHOW'), 1 => $gL10n->get('SYS_FIRSTNAME_LASTNAME'), 2 => $gL10n->get('SYS_USERNAME'));
$formCommon->addSelectBox(
    'system_show_create_edit',
    $gL10n->get('ORG_SHOW_CREATE_EDIT'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['system_show_create_edit'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_SHOW_CREATE_EDIT_DESC')
);
$formCommon->addInput(
    'system_url_data_protection',
    $gL10n->get('SYS_DATA_PROTECTION'),
    $formValues['system_url_data_protection'],
    array('maxLength' => 250, 'helpTextIdInline' => 'SYS_DATA_PROTECTION_DESC')
);
$formCommon->addInput(
    'system_url_imprint',
    $gL10n->get('SYS_IMPRINT'),
    $formValues['system_url_imprint'],
    array('maxLength' => 250, 'helpTextIdInline' => 'SYS_IMPRINT_DESC')
);
$formCommon->addInput(
    'system_js_editor_color',
    $gL10n->get('ORG_JAVASCRIPT_EDITOR_COLOR'),
    $formValues['system_js_editor_color'],
    array('maxLength' => 10, 'helpTextIdInline' => array('ORG_JAVASCRIPT_EDITOR_COLOR_DESC', array('SYS_REMEMBER_ME')), 'class' => 'form-control-small')
);
$formCommon->addCheckbox(
    'system_js_editor_enabled',
    $gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE'),
    (bool) $formValues['system_js_editor_enabled'],
    array('helpTextIdInline' => 'ORG_JAVASCRIPT_EDITOR_ENABLE_DESC')
);
$formCommon->addCheckbox(
    'system_browser_update_check',
    $gL10n->get('ORG_BROWSER_UPDATE_CHECK'),
    (bool) $formValues['system_browser_update_check'],
    array('helpTextIdInline' => 'ORG_BROWSER_UPDATE_CHECK_DESC')
);
$formCommon->addSubmitButton(
    'btn_save_common',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'common', 'accordion_preferences', $gL10n->get('SYS_COMMON'), 'fas fa-cog', $formCommon->show()));

// PANEL: SECURITY

$formSecurity = new HtmlForm(
    'security_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'security')),
    $page,
    array('class' => 'form-preferences')
);

$formSecurity->addInput(
    'logout_minutes',
    $gL10n->get('ORG_AUTOMATIC_LOGOUT_AFTER'),
    $formValues['logout_minutes'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_AUTOMATIC_LOGOUT_AFTER_DESC', array('SYS_REMEMBER_ME')))
);
$selectBoxEntries = array(
    0 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_NO'),
    1 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_LOW'),
    2 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_MID'),
    3 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_HIGH'),
    4 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_VERY_HIGH')
);
$formSecurity->addSelectBox(
    'password_min_strength',
    $gL10n->get('ORG_PASSWORD_MIN_STRENGTH'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['password_min_strength'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_PASSWORD_MIN_STRENGTH_DESC')
);
$formSecurity->addCheckbox(
    'enable_auto_login',
    $gL10n->get('ORG_LOGIN_AUTOMATICALLY'),
    (bool) $formValues['enable_auto_login'],
    array('helpTextIdInline' => 'ORG_LOGIN_AUTOMATICALLY_DESC')
);
$formSecurity->addCheckbox(
    'enable_password_recovery',
    $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
    (bool) $formValues['enable_password_recovery'],
    array('helpTextIdInline' => array('SYS_PASSWORD_FORGOTTEN_PREF_DESC', array('SYS_ENABLE_NOTIFICATIONS')))
);


$formSecurity->addSubmitButton(
    'btn_save_security',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'security', 'accordion_preferences', $gL10n->get('SYS_SECURITY'), 'fas fa-shield-alt', $formSecurity->show()));

// PANEL: ORGANIZATION

$formOrganization = new HtmlForm(
    'organization_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'organization')),
    $page,
    array('class' => 'form-preferences')
);

$formOrganization->addInput(
    'org_shortname',
    $gL10n->get('SYS_NAME_ABBREVIATION'),
    $formValues['org_shortname'],
    array('property' => HtmlForm::FIELD_DISABLED, 'class' => 'form-control-small')
);
$formOrganization->addInput(
    'org_longname',
    $gL10n->get('SYS_NAME'),
    $formValues['org_longname'],
    array('maxLength' => 60, 'property' => HtmlForm::FIELD_REQUIRED)
);
$formOrganization->addInput(
    'org_homepage',
    $gL10n->get('SYS_WEBSITE'),
    $formValues['org_homepage'],
    array('maxLength' => 60)
);
$formOrganization->addInput(
    'email_administrator',
    $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
    $formValues['email_administrator'],
    array('type' => 'email', 'maxLength' => 50, 'helpTextIdInline' => 'SYS_EMAIL_ADMINISTRATOR_DESC')
);

if ($gCurrentOrganization->countAllRecords() > 1) {
    // Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
    if (!$gCurrentOrganization->isParentOrganization()) {
        $sqlData = array();
        $sqlData['query'] = 'SELECT org_id, org_longname
                               FROM '.TBL_ORGANIZATIONS.'
                              WHERE org_id <> ? -- $gCurrentOrgId
                                AND org_org_id_parent IS NULL
                           ORDER BY org_longname ASC, org_shortname ASC';
        $sqlData['params'] = array($gCurrentOrgId);
        $formOrganization->addSelectBoxFromSql(
            'org_org_id_parent',
            $gL10n->get('ORG_PARENT_ORGANIZATION'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['org_org_id_parent'], 'helpTextIdInline' => 'ORG_PARENT_ORGANIZATION_DESC')
        );
    }

    $formOrganization->addCheckbox(
        'system_organization_select',
        $gL10n->get('ORG_SHOW_ORGANIZATION_SELECT'),
        (bool) $formValues['system_organization_select'],
        array('helpTextIdInline' => 'ORG_SHOW_ORGANIZATION_SELECT_DESC')
    );
}

$html = '<a class="btn btn-secondary" id="add_another_organization" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/preferences/preferences_function.php', array('mode' => '2')).'">
            <i class="fas fa-plus-circle"></i>'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'</a>';
$formOrganization->addCustomContent($gL10n->get('ORG_NEW_ORGANIZATION'), $html, array('helpTextIdInline' => $gL10n->get('ORG_ADD_ORGANIZATION_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
$formOrganization->addSubmitButton(
    'btn_save_organization',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'organization', 'accordion_preferences', $gL10n->get('SYS_ORGANIZATION'), 'fas fa-sitemap', $formOrganization->show()));

// PANEL: REGIONAL SETTINGS

$formRegionalSettings = new HtmlForm(
    'regional_settings_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'regional_settings')),
    $page,
    array('class' => 'form-preferences')
);

$formRegionalSettings->addInput(
    'system_timezone',
    $gL10n->get('ORG_TIMEZONE'),
    $gTimezone,
    array('property' => HtmlForm::FIELD_DISABLED, 'class' => 'form-control-small', 'helpTextIdInline' => 'ORG_TIMEZONE_DESC')
);
$formRegionalSettings->addSelectBox(
    'system_language',
    $gL10n->get('SYS_LANGUAGE'),
    $gL10n->getAvailableLanguages(),
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['system_language'], 'helpTextIdInline' => array('SYS_LANGUAGE_HELP_TRANSLATION', array('<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:entwickler:uebersetzen">', '</a>')))
);
$formRegionalSettings->addSelectBox(
    'default_country',
    $gL10n->get('PRO_DEFAULT_COUNTRY'),
    $gL10n->getCountries(),
    array('defaultValue' => $formValues['default_country'], 'helpTextIdInline' => 'PRO_DEFAULT_COUNTRY_DESC')
);
$formRegionalSettings->addInput(
    'system_date',
    $gL10n->get('ORG_DATE_FORMAT'),
    $formValues['system_date'],
    array('maxLength' => 20, 'helpTextIdInline' => array('ORG_DATE_FORMAT_DESC', array('<a href="https://www.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
);
$formRegionalSettings->addInput(
    'system_time',
    $gL10n->get('ORG_TIME_FORMAT'),
    $formValues['system_time'],
    array('maxLength' => 20, 'helpTextIdInline' => array('ORG_TIME_FORMAT_DESC', array('<a href="https://www.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
);
$formRegionalSettings->addInput(
    'system_currency',
    $gL10n->get('ORG_CURRENCY'),
    $formValues['system_currency'],
    array('maxLength' => 20, 'helpTextIdInline' => 'ORG_CURRENCY_DESC', 'class' => 'form-control-small')
);
$formRegionalSettings->addSubmitButton(
    'btn_save_regional_settings',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'regional_settings', 'accordion_preferences', $gL10n->get('ORG_REGIONAL_SETTINGS'), 'fas fa-globe', $formRegionalSettings->show()));

// PANEL: REGISTRATION

$formRegistration = new HtmlForm(
    'registration_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'registration')),
    $page,
    array('class' => 'form-preferences')
);

$formRegistration->addCheckbox(
    'registration_enable_module',
    $gL10n->get('ORG_ENABLE_REGISTRATION_MODULE'),
    (bool) $formValues['registration_enable_module'],
    array('helpTextIdInline' => 'ORG_ENABLE_REGISTRATION_MODULE_DESC')
);
$formRegistration->addCheckbox(
    'enable_registration_captcha',
    $gL10n->get('ORG_ENABLE_CAPTCHA'),
    (bool) $formValues['enable_registration_captcha'],
    array('helpTextIdInline' => 'ORG_CAPTCHA_REGISTRATION')
);
$formRegistration->addCheckbox(
    'registration_adopt_all_data',
    $gL10n->get('SYS_REGISTRATION_ADOPT_ALL_DATA'),
    (bool) $formValues['registration_adopt_all_data'],
    array('helpTextIdInline' => 'SYS_REGISTRATION_ADOPT_ALL_DATA_DESC')
);
$formRegistration->addCheckbox(
    'enable_registration_admin_mail',
    $gL10n->get('ORG_EMAIL_ALERTS'),
    (bool) $formValues['enable_registration_admin_mail'],
    array('helpTextIdInline' => array('ORG_EMAIL_ALERTS_DESC', array('SYS_RIGHT_APPROVE_USERS')))
);
$formRegistration->addSubmitButton(
    'btn_save_registration',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'registration', 'accordion_preferences', $gL10n->get('SYS_REGISTRATION'), 'fas fa-address-card', $formRegistration->show()));

// PANEL: EMAIL DISPATCH

$formEmailDispatch = new HtmlForm(
    'email_dispatch_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'email_dispatch')),
    $page,
    array('class' => 'form-preferences')
);
$selectBoxEntries = array('phpmail' => $gL10n->get('SYS_PHP_MAIL'), 'SMTP' => $gL10n->get('SYS_SMTP'));
$formEmailDispatch->addSelectBox(
    'mail_send_method',
    $gL10n->get('SYS_SEND_METHOD'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_send_method'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_SEND_METHOD_DESC')
);
$formEmailDispatch->addInput(
    'mail_sendmail_address',
    $gL10n->get('SYS_SENDER_EMAIL'),
    $formValues['mail_sendmail_address'],
    array('maxLength' => 50, 'helpTextIdInline' => array('SYS_SENDER_EMAIL_ADDRESS_DESC', array(DOMAIN)))
);
$formEmailDispatch->addInput(
    'mail_sendmail_name',
    $gL10n->get('SYS_SENDER_NAME'),
    $formValues['mail_sendmail_name'],
    array('maxLength' => 50, 'helpTextIdInline' => 'SYS_SENDER_NAME_DESC')
);

// Add js to show or hide mail options
$page->addJavascript('
    $(function(){
        var fieldsToHideOnSingleMode = "#mail_recipients_with_roles_group, #mail_into_to_group, #mail_number_recipients_group";
        if($("#mail_sending_mode").val() == 1) {
            $(fieldsToHideOnSingleMode).hide();
        }
        $("#mail_sending_mode").on("change", function() {
            if($("#mail_sending_mode").val() == 1) {
                $(fieldsToHideOnSingleMode).hide();
            } else {
                $(fieldsToHideOnSingleMode).show();
            }
        });
    });
');

$selectBoxEntries = array(0 => $gL10n->get('SYS_MAIL_BULK'), 1 => $gL10n->get('SYS_MAIL_SINGLE'));
$formEmailDispatch->addSelectBox(
    'mail_sending_mode',
    $gL10n->get('SYS_MAIL_SENDING_MODE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_sending_mode'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_MAIL_SENDING_MODE_DESC')
);

$selectBoxEntries = array(0 => $gL10n->get('SYS_HIDDEN'), 1 => $gL10n->get('SYS_SENDER'), 2 => $gL10n->get('SYS_ADMINISTRATOR'));
$formEmailDispatch->addSelectBox(
    'mail_recipients_with_roles',
    $gL10n->get('SYS_MULTIPLE_RECIPIENTS'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_recipients_with_roles'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_MULTIPLE_RECIPIENTS_DESC')
);
$formEmailDispatch->addCheckbox(
    'mail_into_to',
    $gL10n->get('SYS_INTO_TO'),
    (bool) $formValues['mail_into_to'],
    array('helpTextIdInline' => 'SYS_INTO_TO_DESC')
);
$formEmailDispatch->addInput(
    'mail_number_recipients',
    $gL10n->get('SYS_NUMBER_RECIPIENTS'),
    $formValues['mail_number_recipients'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'SYS_NUMBER_RECIPIENTS_DESC')
);

$selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
$formEmailDispatch->addSelectBox(
    'mail_character_encoding',
    $gL10n->get('SYS_CHARACTER_ENCODING'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_character_encoding'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_CHARACTER_ENCODING_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_host',
    $gL10n->get('SYS_SMTP_HOST'),
    $formValues['mail_smtp_host'],
    array('maxLength' => 50, 'helpTextIdInline' => 'SYS_SMTP_HOST_DESC')
);
$formEmailDispatch->addCheckbox(
    'mail_smtp_auth',
    $gL10n->get('SYS_SMTP_AUTH'),
    (bool) $formValues['mail_smtp_auth'],
    array('helpTextIdInline' => 'SYS_SMTP_AUTH_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_port',
    $gL10n->get('SYS_SMTP_PORT'),
    $formValues['mail_smtp_port'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'SYS_SMTP_PORT_DESC')
);
$selectBoxEntries = array(
    ''    => $gL10n->get('SYS_SMTP_SECURE_NO'),
    'ssl' => $gL10n->get('SYS_SMTP_SECURE_SSL'),
    'tls' => $gL10n->get('SYS_SMTP_SECURE_TLS')
);
$formEmailDispatch->addSelectBox(
    'mail_smtp_secure',
    $gL10n->get('SYS_SMTP_SECURE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_smtp_secure'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_SMTP_SECURE_DESC')
);
$selectBoxEntries = array(
    ''         => $gL10n->get('SYS_AUTO_DETECT'),
    'LOGIN'    => $gL10n->get('SYS_SMTP_AUTH_LOGIN'),
    'PLAIN'    => $gL10n->get('SYS_SMTP_AUTH_PLAIN'),
    'CRAM-MD5' => $gL10n->get('SYS_SMTP_AUTH_CRAM_MD5')
);
$formEmailDispatch->addSelectBox(
    'mail_smtp_authentication_type',
    $gL10n->get('SYS_SMTP_AUTH_TYPE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_smtp_authentication_type'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('SYS_SMTP_AUTH_TYPE_DESC', array('SYS_AUTO_DETECT')))
);
$formEmailDispatch->addInput(
    'mail_smtp_user',
    $gL10n->get('SYS_SMTP_USER'),
    $formValues['mail_smtp_user'],
    array('maxLength' => 100, 'helpTextIdInline' => 'SYS_SMTP_USER_DESC')
);
$formEmailDispatch->addInput(
    'mail_smtp_password',
    $gL10n->get('SYS_SMTP_PASSWORD'),
    $formValues['mail_smtp_password'],
    array('type' => 'password', 'maxLength' => 50, 'helpTextIdInline' => 'SYS_SMTP_PASSWORD_DESC')
);
$html = '<a class="btn btn-secondary" id="send_test_mail" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/preferences/preferences_function.php', array('mode' => '5')).'">
            <i class="fas fa-envelope"></i>'.$gL10n->get('SYS_SEND_TEST_MAIL').'</a>';
$formEmailDispatch->addCustomContent($gL10n->get('SYS_TEST_MAIL'), $html, array('helpTextIdInline' => $gL10n->get('SYS_TEST_MAIL_DESC', array($gL10n->get('SYS_EMAIL_FUNCTION_TEST', array($gCurrentOrganization->getValue('org_longname')))))));
$formEmailDispatch->addSubmitButton(
    'btn_save_email_dispatch',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'email_dispatch', 'accordion_preferences', $gL10n->get('SYS_MAIL_DISPATCH'), 'fas fa-envelope', $formEmailDispatch->show()));

// PANEL: SYSTEM NOTIFICATION

$formSystemNotification = new HtmlForm(
    'system_notification_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'system_notification')),
    $page,
    array('class' => 'form-preferences')
);

$formSystemNotification->addCheckbox(
    'system_notifications_enabled',
    $gL10n->get('SYS_ENABLE_NOTIFICATIONS'),
    (bool) $formValues['system_notifications_enabled'],
    array('helpTextIdInline' => 'SYS_ENABLE_NOTIFICATIONS_DESC')
);

$formSystemNotification->addCheckbox(
    'system_notifications_new_entries',
    $gL10n->get('SYS_NOTIFICATION_NEW_ENTRIES'),
    (bool) $formValues['system_notifications_new_entries'],
    array('helpTextIdInline' => 'SYS_NOTIFICATION_NEW_ENTRIES_DESC')
);
$formSystemNotification->addCheckbox(
    'system_notifications_profile_changes',
    $gL10n->get('SYS_NOTIFICATION_PROFILE_CHANGES'),
    (bool) $formValues['system_notifications_profile_changes'],
    array('helpTextIdInline' => 'SYS_NOTIFICATION_PROFILE_CHANGES_DESC')
);

// read all roles of the organization
$sqlData = array();
$sqlData['query'] = 'SELECT rol_uuid, rol_name, cat_name
               FROM '.TBL_ROLES.'
         INNER JOIN '.TBL_CATEGORIES.'
                 ON cat_id = rol_cat_id
         INNER JOIN '.TBL_ORGANIZATIONS.'
                 ON org_id = cat_org_id
              WHERE rol_valid  = true
                AND rol_system = false
                AND cat_org_id = ? -- $gCurrentOrgId
                AND cat_name_intern <> \'EVENTS\'
           ORDER BY cat_name, rol_name';
$sqlData['params'] = array($gCurrentOrgId);
$formSystemNotification->addSelectBoxFromSql(
    'system_notifications_role',
    $gL10n->get('SYS_NOTIFICATION_ROLE'),
    $gDb,
    $sqlData,
    array('defaultValue' => $formValues['system_notifications_role'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_NOTIFICATION_ROLE_DESC')
);

$formSystemNotification->addCustomContent(
    $gL10n->get('SYS_SYSTEM_MAILS'),
    '<p>'.$gL10n->get('ORG_SYSTEM_MAIL_TEXTS_DESC').':</p>
    <p><strong>#user_first_name#</strong> - '.$gL10n->get('ORG_VARIABLE_FIRST_NAME').'<br />
    <strong>#user_last_name#</strong> - '.$gL10n->get('ORG_VARIABLE_LAST_NAME').'<br />
    <strong>#user_login_name#</strong> - '.$gL10n->get('ORG_VARIABLE_USERNAME').'<br />
    <strong>#user_email#</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL').'<br />
    <strong>#administrator_email#</strong> - '.$gL10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION').'<br />
    <strong>#organization_short_name#</strong> - '.$gL10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION').'<br />
    <strong>#organization_long_name#</strong> - '.$gL10n->get('ORG_VARIABLE_NAME_ORGANIZATION').'<br />
    <strong>#organization_homepage#</strong> - '.$gL10n->get('ORG_VARIABLE_URL_ORGANIZATION').'</p>'
);

$text = new TableText($gDb);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_WEBMASTER', 'txt_org_id' => $gCurrentOrgId));
$formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_WEBMASTER', $gL10n->get('SYS_NOTIFICATION_NEW_REGISTRATION'), $text->getValue('txt_text'), 7);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_USER', 'txt_org_id' => $gCurrentOrgId));
$formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_USER', $gL10n->get('ORG_CONFIRM_REGISTRATION'), $text->getValue('txt_text'), 7);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_REFUSE_REGISTRATION', 'txt_org_id' => $gCurrentOrgId));
$formSystemNotification->addMultilineTextInput('SYSMAIL_REFUSE_REGISTRATION', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $gCurrentOrgId));
$htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br /><strong>#variable1#</strong> - '.$gL10n->get('ORG_VARIABLE_NEW_PASSWORD');
$formSystemNotification->addMultilineTextInput(
    'SYSMAIL_NEW_PASSWORD',
    $gL10n->get('ORG_SEND_NEW_PASSWORD'),
    $text->getValue('txt_text'),
    7,
    array('helpTextIdInline' => $htmlDesc)
);
$text->readDataByColumns(array('txt_name' => 'SYSMAIL_PASSWORD_RESET', 'txt_org_id' => $gCurrentOrgId));
$htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES').':<br /><strong>#variable1#</strong> - '.$gL10n->get('ORG_VARIABLE_ACTIVATION_LINK');
$formSystemNotification->addMultilineTextInput(
    'SYSMAIL_PASSWORD_RESET',
    $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
    $text->getValue('txt_text'),
    7,
    array('helpTextIdInline' => $htmlDesc)
);

$formSystemNotification->addSubmitButton(
    'btn_save_system_notification',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'system_notification', 'accordion_preferences', $gL10n->get('SYS_SYSTEM_MAILS'), 'fas fa-broadcast-tower', $formSystemNotification->show()));

// PANEL: CAPTCHA

$formCaptcha = new HtmlForm(
    'captcha_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'captcha')),
    $page,
    array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    'pic'  => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'),
    'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'),
    'word' => $gL10n->get('ORG_CAPTCHA_TYPE_WORDS')
);
$formCaptcha->addSelectBox(
    'captcha_type',
    $gL10n->get('ORG_CAPTCHA_TYPE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['captcha_type'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_CAPTCHA_TYPE_TEXT')
);

$fonts = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . '/adm_program/system/fonts/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
asort($fonts);
$formCaptcha->addSelectBox(
    'captcha_fonts',
    $gL10n->get('SYS_FONT'),
    $fonts,
    array('defaultValue' => $formValues['captcha_fonts'], 'showContextDependentFirstEntry' => false, 'arrayKeyIsNotValue' => true, 'helpTextIdInline' => 'ORG_CAPTCHA_FONT')
);
$formCaptcha->addInput(
    'captcha_width',
    $gL10n->get('SYS_WIDTH').' ('.$gL10n->get('ORG_PIXEL').')',
    $formValues['captcha_width'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'ORG_CAPTCHA_WIDTH_DESC')
);
$formCaptcha->addInput(
    'captcha_lines_numbers',
    $gL10n->get('ORG_CAPTCHA_LINES_NUMBERS'),
    $formValues['captcha_lines_numbers'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 25, 'step' => 1, 'helpTextIdInline' => 'ORG_CAPTCHA_LINES_NUMBERS_DESC')
);
$formCaptcha->addInput(
    'captcha_perturbation',
    $gL10n->get('ORG_CAPTCHA_DISTORTION'),
    $formValues['captcha_perturbation'],
    array('type' => 'string', 'helpTextIdInline' => 'ORG_CAPTCHA_DISTORTION_DESC', 'class' => 'form-control-small')
);
$backgrounds = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/dapphp/securimage/backgrounds/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
asort($backgrounds);
$formCaptcha->addSelectBox(
    'captcha_background_image',
    $gL10n->get('ORG_CAPTCHA_BACKGROUND_IMAGE'),
    $backgrounds,
    array('defaultValue' => $formValues['captcha_background_image'], 'showContextDependentFirstEntry' => true, 'arrayKeyIsNotValue' => true, 'helpTextIdInline' => 'ORG_CAPTCHA_BACKGROUND_IMAGE_DESC')
);
$formCaptcha->addInput(
    'captcha_background_color',
    $gL10n->get('ORG_CAPTCHA_BACKGROUND_COLOR'),
    $formValues['captcha_background_color'],
    array('maxLength' => 7, 'class' => 'form-control-small')
);
$formCaptcha->addInput(
    'captcha_text_color',
    $gL10n->get('ORG_CAPTCHA_CHARACTERS_COLOR'),
    $formValues['captcha_text_color'],
    array('maxLength' => 7, 'class' => 'form-control-small')
);
$formCaptcha->addInput(
    'captcha_line_color',
    $gL10n->get('ORG_CAPTCHA_LINE_COLOR'),
    $formValues['captcha_line_color'],
    array('maxLength' => 7, 'helpTextIdInline' => array('ORG_CAPTCHA_COLOR_DESC', array('<a href="https://en.wikipedia.org/wiki/Web_colors">', '</a>')), 'class' => 'form-control-small')
);
$formCaptcha->addInput(
    'captcha_charset',
    $gL10n->get('ORG_CAPTCHA_SIGNS'),
    $formValues['captcha_charset'],
    array('maxLength' => 80, 'helpTextIdInline' => 'ORG_CAPTCHA_SIGNS_TEXT')
);
$formCaptcha->addInput(
    'captcha_signature',
    $gL10n->get('ORG_CAPTCHA_SIGNATURE'),
    $formValues['captcha_signature'],
    array('maxLength' => 60, 'helpTextIdInline' => 'ORG_CAPTCHA_SIGNATURE_TEXT')
);
$html = '<img id="captcha" src="' . ADMIDIO_URL . FOLDER_LIBS_SERVER . '/dapphp/securimage/securimage_show.php" alt="CAPTCHA Image" />
         <a class="admidio-icon-link" href="#" onclick="document.getElementById(\'captcha\').src=\'' . ADMIDIO_URL . FOLDER_LIBS_SERVER . '/dapphp/securimage/securimage_show.php?\' + Math.random(); return false">
            <i class="fas fa-sync-alt fa-lg" data-toggle="tooltip" title="'.$gL10n->get('SYS_RELOAD').'"></i></a>';
$formCaptcha->addCustomContent(
    $gL10n->get('ORG_CAPTCHA_PREVIEW'),
    $html,
    array('helpTextIdInline' => 'ORG_CAPTCHA_PREVIEW_TEXT')
);

$formCaptcha->addSubmitButton(
    'btn_save_captcha',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('common', 'captcha', 'accordion_preferences', $gL10n->get('SYS_CAPTCHA'), 'fas fa-font', $formCaptcha->show()));

// PANEL: ADMIDIO UPDATE

$formAdmidioUpdate = new HtmlForm('admidio_update_preferences_form', null, $page);

$html = '<span id="admidio_version_content">'.ADMIDIO_VERSION_TEXT.'
            <a id="link_check_for_update" href="#link_check_for_update" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
         </span>';
$formAdmidioUpdate->addCustomContent($gL10n->get('SYS_ADMIDIO_VERSION'), $html);

// if database version is different to file version, then show database version
if ($gSystemComponent->getValue('com_version') !== ADMIDIO_VERSION) {
    $formAdmidioUpdate->addStaticControl('admidio_database_version', $gL10n->get('ORG_DIFFERENT_DATABASE_VERSION'), $gSystemComponent->getValue('com_version'));
}

$component = new ComponentUpdate($gDb);
$component->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
$updateStep = (int) $gSystemComponent->getValue('com_update_step');
$maxStep = $component->getMaxUpdateStep();
$textStep = $updateStep . ' / ' . $maxStep;
if ($updateStep === $maxStep) {
    $html = getStaticText('success', $textStep);
} elseif ($updateStep > $maxStep) {
    $html = getStaticText('warning', $textStep);
} else {
    $html = getStaticText('danger', $textStep);
}
$formAdmidioUpdate->addStaticControl('last_update_step', $gL10n->get('ORG_LAST_UPDATE_STEP'), $html);

$html = '<a id="donate" href="'. ADMIDIO_HOMEPAGE . 'donate.php" target="_blank">
            <i class="fas fa-heart"></i>'.$gL10n->get('SYS_DONATE').'</a>';
$formAdmidioUpdate->addCustomContent($gL10n->get('SYS_SUPPORT_ADMIDIO'), $html, array('helpTextIdInline' => $gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT')));

$page->addHtml(getPreferencePanel('common', 'admidio_update', 'accordion_preferences', $gL10n->get('SYS_ADMIDIO_UPDATE'), 'fas fa-cloud-download-alt', $formAdmidioUpdate->show()));

// PANEL: PHP

$formPhp = new HtmlForm('php_preferences_form', null, $page);

if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    $html = getStaticText('danger', PHP_VERSION, ' &rarr; '.$gL10n->get('SYS_PHP_VERSION_REQUIRED', array(MIN_PHP_VERSION)));
} elseif (version_compare(PHP_VERSION, '7.2', '<')) {
    $html = getStaticText('warning', PHP_VERSION, ' &rarr; '.$gL10n->get('SYS_PHP_VERSION_EOL', array('<a href="https://www.php.net/supported-versions.php" target="_blank">Supported Versions</a>')));
} else {
    $html = getStaticText('success', PHP_VERSION);
}
$formPhp->addStaticControl('php_version', $gL10n->get('SYS_PHP_VERSION'), $html);

$postMaxSize = PhpIniUtils::getPostMaxSize();
if (is_infinite($postMaxSize)) {
    $html = getStaticText('warning', $gL10n->get('SYS_NOT_SET'));
} else {
    $html = getStaticText('success', FileSystemUtils::getHumanReadableBytes($postMaxSize));
}
$formPhp->addStaticControl('post_max_size', $gL10n->get('SYS_POST_MAX_SIZE'), $html);

$memoryLimit = PhpIniUtils::getMemoryLimit();
if (is_infinite($memoryLimit)) {
    $html = getStaticText('warning', $gL10n->get('SYS_NOT_SET'));
} else {
    $html = getStaticText('success', FileSystemUtils::getHumanReadableBytes($memoryLimit));
}
$formPhp->addStaticControl('memory_limit', $gL10n->get('SYS_MEMORY_LIMIT'), $html);

if (PhpIniUtils::isFileUploadEnabled()) {
    $html = getStaticText('success', $gL10n->get('SYS_ON'));
} else {
    $html = getStaticText('danger', $gL10n->get('SYS_OFF'));
}
$formPhp->addStaticControl('file_uploads', $gL10n->get('SYS_FILE_UPLOADS'), $html);

$fileUploadMaxFileSize = PhpIniUtils::getFileUploadMaxFileSize();
if (is_infinite($fileUploadMaxFileSize)) {
    $html = getStaticText('warning', $gL10n->get('SYS_NOT_SET'));
} else {
    $html = getStaticText('success', FileSystemUtils::getHumanReadableBytes($fileUploadMaxFileSize));
}
$formPhp->addStaticControl('upload_max_filesize', $gL10n->get('SYS_UPLOAD_MAX_FILESIZE'), $html);

try {
    SecurityUtils::getRandomInt(0, 1, true);
    $html = getStaticText('success', $gL10n->get('SYS_SECURE'));
} catch (AdmException $e) {
    $html = getStaticText('danger', $gL10n->get('SYS_PRNG_INSECURE'), '<br />' . $e->getText());
}
$formPhp->addStaticControl('pseudo_random_number_generator', $gL10n->get('SYS_PRNG'), $html);

$html = '<a href="' . ADMIDIO_URL . '/adm_program/system/phpinfo.php' . '" target="_blank">phpinfo()</a> <i class="fas fa-external-link-alt"></i>';
$formPhp->addStaticControl('php_info', $gL10n->get('SYS_PHP_INFO'), $html);

$page->addHtml(getPreferencePanel('common', 'php', 'accordion_preferences', $gL10n->get('SYS_PHP'), 'fab fa-php', $formPhp->show()));

// PANEL: SYSTEM INFORMATION

$formSystemInformation = new HtmlForm('system_information_preferences_form', null, $page);

$formSystemInformation->addStaticControl(
    'operating_system',
    $gL10n->get('SYS_OPERATING_SYSTEM'),
    '<strong>' . SystemInfoUtils::getOS() . '</strong> (' . SystemInfoUtils::getUname() . ')'
);

if (SystemInfoUtils::is64Bit()) {
    $html = getStaticText('success', $gL10n->get('SYS_YES'));
} else {
    $html = getStaticText('success', $gL10n->get('SYS_NO'));
}
$formSystemInformation->addStaticControl('64bit', $gL10n->get('SYS_64BIT'), $html);

if (SystemInfoUtils::isUnixFileSystem()) {
    $html = '<strong>' . $gL10n->get('SYS_YES') . '</strong>';
} else {
    $html = '<strong>' . $gL10n->get('SYS_NO') . '</strong>';
}
$formSystemInformation->addStaticControl('unix', $gL10n->get('SYS_UNIX'), $html);

$formSystemInformation->addStaticControl(
    'directory_separator',
    $gL10n->get('SYS_DIRECTORY_SEPARATOR'),
    '<strong>"' . SystemInfoUtils::getDirectorySeparator() . '"</strong>'
);

$formSystemInformation->addStaticControl(
    'path_separator',
    $gL10n->get('SYS_PATH_SEPARATOR'),
    '<strong>"' . SystemInfoUtils::getPathSeparator() . '"</strong>'
);

$formSystemInformation->addStaticControl(
    'max_path_length',
    $gL10n->get('SYS_MAX_PATH_LENGTH'),
    SystemInfoUtils::getMaxPathLength()
);

if (version_compare($gDb->getVersion(), $gDb->getMinimumRequiredVersion(), '<')) {
    $html = getStaticText('danger', $gDb->getVersion(), ' &rarr; '.$gL10n->get('SYS_DATABASE_VERSION_REQUIRED', array($gDb->getMinimumRequiredVersion())));
} else {
    $html = getStaticText('success', $gDb->getVersion());
}
$formSystemInformation->addStaticControl('database_version', $gDb->getName().'-'.$gL10n->get('SYS_VERSION'), $html);

if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess')) {
    $html = getStaticText('success', $gL10n->get('SYS_ON'));
} else {
    $html = getStaticText(
        'danger',
        '<span id="directory_protection_status">' . $gL10n->get('SYS_OFF') . '</span>',
        ' &rarr; <a id="link_directory_protection" href="#link_directory_protection" title="'.$gL10n->get('SYS_CREATE_HTACCESS').'">'.$gL10n->get('SYS_CREATE_HTACCESS').'</a>'
    );
}
$formSystemInformation->addStaticControl('directory_protection', $gL10n->get('SYS_DIRECTORY_PROTECTION'), $html);

$formSystemInformation->addStaticControl('max_processable_image_size', $gL10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE'), round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('SYS_MEGAPIXEL'));

if (isset($gDebug) && $gDebug) {
    $html = getStaticText('danger', $gL10n->get('SYS_ON'));
} else {
    $html = getStaticText('success', $gL10n->get('SYS_OFF'));
}
$formSystemInformation->addStaticControl('debug_mode', $gL10n->get('SYS_DEBUG_OUTPUT'), $html);

if (isset($gImportDemoData) && $gImportDemoData) {
    $html = getStaticText('danger', $gL10n->get('SYS_ON'));
} else {
    $html = getStaticText('success', $gL10n->get('SYS_OFF'));
}
$formSystemInformation->addStaticControl('import_mode', $gL10n->get('SYS_IMPORT_MODE'), $html);

try {
    $diskSpace = FileSystemUtils::getDiskSpace();

    $diskUsagePercent = round(($diskSpace['used'] / $diskSpace['total']) * 100, 1);
    $progressBarClass = '';
    if ($diskUsagePercent > 90) {
        $progressBarClass = ' progress-bar-danger';
    } elseif ($diskUsagePercent > 70) {
        $progressBarClass = ' progress-bar-warning';
    }
    $html = '
    <div class="progress">
        <div class="progress-bar' . $progressBarClass . '" role="progressbar" aria-valuenow="' . $diskSpace['used'] . '" aria-valuemin="0" aria-valuemax="' . $diskSpace['total'] . '" style="width: ' . $diskUsagePercent . '%;">
            ' . FileSystemUtils::getHumanReadableBytes($diskSpace['used']) . ' / ' . FileSystemUtils::getHumanReadableBytes($diskSpace['total']) . '
        </div>
    </div>';
} catch (\RuntimeException $exception) {
    $gLogger->error('FILE-SYSTEM: Disk space could not be determined!');

    $html = getStaticText('danger', $gL10n->get('SYS_DISK_SPACE_ERROR', array($exception->getMessage())));
}
$formSystemInformation->addStaticControl('disk_space', $gL10n->get('SYS_DISK_SPACE'), $html);

$page->addHtml(getPreferencePanel('common', 'system_information', 'accordion_preferences', $gL10n->get('ORG_SYSTEM_INFORMATION'), 'fas fa-info-circle', $formSystemInformation->show()));

$page->addHtml('
        </div>
    </div>
    <div class="tab-pane fade" id="tabs-modules" role="tabpanel">
        <div class="accordion" id="accordion_modules">');

// PANEL: ANNOUNCEMENTS

$formAnnouncements = new HtmlForm(
    'announcements_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'announcements')),
    $page,
    array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DISABLED'),
    '1' => $gL10n->get('SYS_ENABLED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formAnnouncements->addSelectBox(
    'enable_announcements_module',
    $gL10n->get('ORG_ACCESS_TO_MODULE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['enable_announcements_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$formAnnouncements->addInput(
    'announcements_per_page',
    $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
    $formValues['announcements_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$html = '<a class="btn btn-secondary" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'ANN')).'">
            <i class="fas fa-th-large"></i>'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
$formAnnouncements->addCustomContent(
    $gL10n->get('SYS_EDIT_CATEGORIES'),
    $html,
    array('helpTextIdInline' => $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
);
$formAnnouncements->addSubmitButton(
    'btn_save_announcements',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'announcements', 'accordion_modules', $gL10n->get('SYS_ANNOUNCEMENTS'), 'fas fa-newspaper', $formAnnouncements->show()));

// PANEL: MEMBERS

$formUserManagement = new HtmlForm(
    'user_management_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'user_management')),
    $page,
    array('class' => 'form-preferences')
);

// read all global lists
$sqlData = array();
$sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM '.TBL_LISTS.'
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                        AND NOT EXISTS (SELECT 1
                                       FROM '.TBL_LIST_COLUMNS.'
                                       WHERE lsc_lst_id = lst_id
                                       AND lsc_special_field LIKE \'mem%\')
                   ORDER BY lst_name ASC, lst_timestamp DESC';
$sqlData['params'] = array($gCurrentOrgId);
$formUserManagement->addSelectBoxFromSql(
    'members_list_configuration',
    $gL10n->get('SYS_CONFIGURATION_LIST'),
    $gDb,
    $sqlData,
    array('defaultValue' => $formValues['members_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_MEMBERS_CONFIGURATION_DESC')
);
$selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
$formUserManagement->addSelectBox(
    'members_users_per_page',
    $gL10n->get('SYS_USERS_PER_PAGE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['members_users_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(25)))
);
$formUserManagement->addInput(
    'members_days_field_history',
    $gL10n->get('SYS_DAYS_FIELD_HISTORY'),
    $formValues['members_days_field_history'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999999999, 'step' => 1, 'helpTextIdInline' => 'SYS_DAYS_FIELD_HISTORY_DESC')
);
$formUserManagement->addCheckbox(
    'members_show_all_users',
    $gL10n->get('ORG_SHOW_ALL_USERS'),
    (bool) $formValues['members_show_all_users'],
    array('helpTextIdInline' => 'ORG_SHOW_ALL_USERS_DESC')
);
$formUserManagement->addCheckbox(
    'members_enable_user_relations',
    $gL10n->get('SYS_ENABLE_USER_RELATIONS'),
    (bool) $formValues['members_enable_user_relations'],
    array('helpTextIdInline' => 'SYS_ENABLE_USER_RELATIONS_DESC')
);

$html = '<a class="btn btn-secondary" href="'. ADMIDIO_URL. FOLDER_MODULES.'/userrelations/relationtypes.php">
            <i class="fas fa-people-arrows"></i>'.$gL10n->get('SYS_SWITCH_TO_RELATIONSHIP_CONFIGURATION').'</a>';
$formUserManagement->addCustomContent($gL10n->get('SYS_USER_RELATIONS'), $html, array('helpTextIdInline' => $gL10n->get('SYS_MAINTAIN_USER_RELATION_TYPES_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));

$formUserManagement->addSubmitButton(
    'btn_save_user_management',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'user_administration', 'accordion_modules', $gL10n->get('SYS_MEMBERS'), 'fas fa-users-cog', $formUserManagement->show()));

// PANEL: DOCUMENTS-FILES

$formDownloads = new HtmlForm(
    'documents_files_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'documents-files')),
    $page,
    array('class' => 'form-preferences')
);

$formDownloads->addCheckbox(
    'documents_files_enable_module',
    $gL10n->get('SYS_ENABLE_DOCUMENTS_FILES_MODULE'),
    (bool) $formValues['documents_files_enable_module'],
    array('helpTextIdInline' => 'SYS_ENABLE_DOCUMENTS_FILES_MODULE_DESC')
);
$formDownloads->addInput(
    'max_file_upload_size',
    $gL10n->get('SYS_MAXIMUM_FILE_SIZE').' (MB)',
    $formValues['max_file_upload_size'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999999, 'step' => 1, 'helpTextIdInline' => 'SYS_MAXIMUM_FILE_SIZE_DESC')
);
$formDownloads->addSubmitButton(
    'btn_save_documents_files',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'documents-files', 'accordion_modules', $gL10n->get('SYS_DOCUMENTS_FILES'), 'fas fa-file-download', $formDownloads->show()));

// PANEL: PHOTOS

$formPhotos = new HtmlForm(
    'photos_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'photos')),
    $page,
    array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DISABLED'),
    '1' => $gL10n->get('SYS_ENABLED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formPhotos->addSelectBox(
    'enable_photo_module',
    $gL10n->get('ORG_ACCESS_TO_MODULE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['enable_photo_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$selectBoxEntries = array(
    '1' => $gL10n->get('PHO_MODAL_WINDOW'),
    '2' => $gL10n->get('PHO_SAME_WINDOW'),
    '0' => $gL10n->get('PHO_POPUP_WINDOW')
);
$formPhotos->addSelectBox(
    'photo_show_mode',
    $gL10n->get('PHO_DISPLAY_PHOTOS'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['photo_show_mode'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PHO_DISPLAY_PHOTOS_DESC')
);
$formPhotos->addInput(
    'photo_albums_per_page',
    $gL10n->get('PHO_NUMBER_OF_ALBUMS_PER_PAGE'),
    $formValues['photo_albums_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$formPhotos->addInput(
    'photo_thumbs_page',
    $gL10n->get('PHO_THUMBNAILS_PER_PAGE'),
    $formValues['photo_thumbs_page'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_THUMBNAILS_PER_PAGE_DESC')
);
$formPhotos->addInput(
    'photo_thumbs_scale',
    $gL10n->get('PHO_SCALE_THUMBNAILS'),
    $formValues['photo_thumbs_scale'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_SCALE_THUMBNAILS_DESC')
);
$formPhotos->addInput(
    'photo_save_scale',
    $gL10n->get('PHO_SCALE_AT_UPLOAD'),
    $formValues['photo_save_scale'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_SCALE_AT_UPLOAD_DESC')
);
$formPhotos->addInput(
    'photo_show_width',
    $gL10n->get('PHO_MAX_PHOTO_SIZE_WIDTH'),
    $formValues['photo_show_width'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1)
);
$formPhotos->addInput(
    'photo_show_height',
    $gL10n->get('PHO_MAX_PHOTO_SIZE_HEIGHT'),
    $formValues['photo_show_height'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_MAX_PHOTO_SIZE_DESC')
);
$formPhotos->addInput(
    'photo_image_text',
    $gL10n->get('PHO_SHOW_CAPTION'),
    $formValues['photo_image_text'],
    array('maxLength' => 60, 'helpTextIdInline' => array('PHO_SHOW_CAPTION_DESC', array(DOMAIN)))
);
$formPhotos->addInput(
    'photo_image_text_size',
    $gL10n->get('PHO_CAPTION_SIZE'),
    $formValues['photo_image_text_size'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PHO_CAPTION_SIZE_DESC')
);
$formPhotos->addCheckbox(
    'photo_download_enabled',
    $gL10n->get('PHO_DOWNLOAD_ENABLED'),
    (bool) $formValues['photo_download_enabled'],
    array('helpTextIdInline' => array('PHO_DOWNLOAD_ENABLED_DESC', array($gL10n->get('PHO_KEEP_ORIGINAL'))))
);
$formPhotos->addCheckbox(
    'photo_keep_original',
    $gL10n->get('PHO_KEEP_ORIGINAL'),
    (bool) $formValues['photo_keep_original'],
    array('helpTextIdInline' => array('PHO_KEEP_ORIGINAL_DESC', array($gL10n->get('PHO_DOWNLOAD_ENABLED'))))
);
$formPhotos->addSubmitButton(
    'btn_save_photos',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'photos', 'accordion_modules', $gL10n->get('SYS_PHOTOS'), 'fas fa-image', $formPhotos->show()));

// PANEL: GUESTBOOK

$formGuestbook = new HtmlForm(
    'guestbook_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'guestbook')),
    $page,
    array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DISABLED'),
    '1' => $gL10n->get('SYS_ENABLED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formGuestbook->addSelectBox(
    'enable_guestbook_module',
    $gL10n->get('ORG_ACCESS_TO_MODULE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['enable_guestbook_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$formGuestbook->addInput(
    'guestbook_entries_per_page',
    $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
    $formValues['guestbook_entries_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$formGuestbook->addCheckbox(
    'enable_guestbook_captcha',
    $gL10n->get('ORG_ENABLE_CAPTCHA'),
    (bool) $formValues['enable_guestbook_captcha'],
    array('helpTextIdInline' => 'GBO_CAPTCHA_DESC')
);
$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_NOBODY'),
    '1' => $gL10n->get('GBO_ONLY_VISITORS'),
    '2' => $gL10n->get('SYS_ALL')
);
$formGuestbook->addSelectBox(
    'enable_guestbook_moderation',
    $gL10n->get('GBO_GUESTBOOK_MODERATION'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['enable_guestbook_moderation'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'GBO_GUESTBOOK_MODERATION_DESC')
);
$formGuestbook->addCheckbox(
    'enable_gbook_comments4all',
    $gL10n->get('GBO_COMMENTS4ALL'),
    (bool) $formValues['enable_gbook_comments4all'],
    array('helpTextIdInline' => 'GBO_COMMENTS4ALL_DESC')
);
$formGuestbook->addCheckbox(
    'enable_intial_comments_loading',
    $gL10n->get('GBO_INITIAL_COMMENTS_LOADING'),
    (bool) $formValues['enable_intial_comments_loading'],
    array('helpTextIdInline' => 'GBO_INITIAL_COMMENTS_LOADING_DESC')
);
$formGuestbook->addInput(
    'flooding_protection_time',
    $gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL'),
    $formValues['flooding_protection_time'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'GBO_FLOODING_PROTECTION_INTERVALL_DESC')
);
$formGuestbook->addSubmitButton(
    'btn_save_guestbook',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'guestbook', 'accordion_modules', $gL10n->get('GBO_GUESTBOOK'), 'fas fa-book', $formGuestbook->show()));

// PANEL: ECARDS

$formEcards = new HtmlForm(
    'ecards_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'ecards')),
    $page,
    array('class' => 'form-preferences')
);

$formEcards->addCheckbox(
    'enable_ecard_module',
    $gL10n->get('SYS_ENABLE_GREETING_CARDS'),
    (bool) $formValues['enable_ecard_module'],
    array('helpTextIdInline' => 'SYS_ENABLE_GREETING_CARDS_DESC')
);
$formEcards->addInput(
    'ecard_thumbs_scale',
    $gL10n->get('PHO_SCALE_THUMBNAILS'),
    $formValues['ecard_thumbs_scale'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'SYS_SCALE_THUMBNAILS_DESC')
);
$formEcards->addInput(
    'ecard_card_picture_width',
    $gL10n->get('PHO_MAX_PHOTO_SIZE_WIDTH'),
    $formValues['ecard_card_picture_width'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1)
);
$formEcards->addInput(
    'ecard_card_picture_height',
    $gL10n->get('PHO_MAX_PHOTO_SIZE_HEIGHT'),
    $formValues['ecard_card_picture_height'],
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'SYS_ECARD_MAX_PHOTO_SIZE_DESC')
);

try {
    $formEcards->addSelectBox(
        'ecard_template',
        $gL10n->get('SYS_TEMPLATE'),
        getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates'),
        array(
            'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $formValues['ecard_template']))),
            'showContextDependentFirstEntry' => false,
            'arrayKeyIsNotValue' => true,
            'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
            'helpTextIdInline' => 'SYS_TEMPLATE_DESC'
        )
    );
} catch (UnexpectedValueException $e) {
    $gMessage->show($e->getMessage());
}
$formEcards->addSubmitButton(
    'btn_save_ecards',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'ecards', 'accordion_modules', $gL10n->get('SYS_GREETING_CARDS'), 'fas fa-file-image', $formEcards->show()));

// PANEL: GROUPS AND ROLES

$formGroupsRoles = new HtmlForm(
    'groups_roles_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'groups-roles')),
    $page,
    array('class' => 'form-preferences')
);

$formGroupsRoles->addCheckbox(
    'groups_roles_enable_module',
    $gL10n->get('SYS_ENABLE_GROUPS_ROLES'),
    (bool) $formValues['groups_roles_enable_module'],
    array('helpTextIdInline' => 'SYS_ENABLE_GROUPS_ROLES_DESC')
);
$selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
$formGroupsRoles->addSelectBox(
    'groups_roles_members_per_page',
    $gL10n->get('SYS_MEMBERS_PER_PAGE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['groups_roles_members_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_MEMBERS_PER_PAGE_DESC')
);
// read all global lists
$sqlData = array();
$sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM '.TBL_LISTS.'
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                   ORDER BY lst_name ASC, lst_timestamp DESC';
$sqlData['params'] = array($gCurrentOrgId);
$formGroupsRoles->addSelectBoxFromSql(
    'groups_roles_default_configuration',
    $gL10n->get('SYS_DEFAULT_CONFIGURATION'),
    $gDb,
    $sqlData,
    array('defaultValue' => $formValues['groups_roles_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_DEFAULT_CONFIGURATION_LISTS_DESC')
);
$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_NOBODY'),
    '1' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_ASSIGN_ROLES')))),
    '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))
);
$formGroupsRoles->addSelectBox(
    'groups_roles_show_former_members',
    $gL10n->get('SYS_SHOW_FORMER_MEMBERS'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['groups_roles_show_former_members'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('SYS_SHOW_FORMER_MEMBERS_DESC', array($gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))))
);
$selectBoxEntriesExport = array(
    '0' => $gL10n->get('SYS_NOBODY'),
    '1' => $gL10n->get('SYS_ALL'),
    '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))
);
$formGroupsRoles->addSelectBox(
    'groups_roles_export',
    $gL10n->get('SYS_EXPORT_LISTS'),
    $selectBoxEntriesExport,
    array('defaultValue' => $formValues['groups_roles_export'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_EXPORT_LISTS_DESC')
);
$selectBoxEntriesEditLists = array(
    '1' => $gL10n->get('SYS_ALL'),
    '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER')))),
    '3' => $gL10n->get('SYS_ADMINISTRATORS')
);
$formGroupsRoles->addSelectBox(
    'groups_roles_edit_lists',
    $gL10n->get('SYS_CONFIGURE_LISTS'),
    $selectBoxEntriesEditLists,
    array('defaultValue' => $formValues['groups_roles_edit_lists'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_CONFIGURE_LISTS_DESC')
);
$html = '<a class="btn btn-secondary" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'ROL')).'">
            <i class="fas fa-th-large"></i>'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
$formGroupsRoles->addCustomContent($gL10n->get('SYS_EDIT_CATEGORIES'), $html, array('helpTextIdInline' => $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
$formGroupsRoles->addSubmitButton(
    'btn_save_lists',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'groups-roles', 'accordion_modules', $gL10n->get('SYS_GROUPS_ROLES'), 'fas fa-users', $formGroupsRoles->show()));

// PANEL: CATEGORY-REPORT

$formCategoryReport = new HtmlForm(
    'category_report_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'category-report')),
    $page,
    array('class' => 'form-preferences')
);

$formCategoryReport->addCheckbox(
    'category_report_enable_module',
    $gL10n->get('SYS_ENABLE_CATEGORY_REPORT'),
    (bool) $formValues['category_report_enable_module'],
    array('helpTextIdInline' => 'SYS_ENABLE_CATEGORY_REPORT_DESC')
);
// read all global lists
$sqlData = array();
$sqlData['query'] = 'SELECT crt_id, crt_name
                       FROM '.TBL_CATEGORY_REPORT.'
                      WHERE crt_org_id = ? -- $gCurrentOrgId
                   ORDER BY crt_name ASC';
$sqlData['params'] = array($gCurrentOrgId);
$formCategoryReport->addSelectBoxFromSql(
    'category_report_default_configuration',
    $gL10n->get('SYS_DEFAULT_CONFIGURATION'),
    $gDb,
    $sqlData,
    array('defaultValue' => $formValues['category_report_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_DEFAULT_CONFIGURATION_CAT_REP_DESC')
);

$formCategoryReport->addSubmitButton(
    'btn_save_documents_files',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'category-report', 'accordion_modules', $gL10n->get('SYS_CATEGORY_REPORT'), 'fas fa-list', $formCategoryReport->show()));

// PANEL: MESSAGES

$formMessages = new HtmlForm(
    'messages_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'messages')),
    $page,
    array('class' => 'form-preferences')
);

$formMessages->addCheckbox(
    'enable_mail_module',
    $gL10n->get('SYS_ENABLE_EMAILS'),
    (bool) $formValues['enable_mail_module'],
    array('helpTextIdInline' => 'SYS_ENABLE_EMAILS_DESC')
);
$formMessages->addCheckbox(
    'enable_pm_module',
    $gL10n->get('SYS_ENABLE_PM_MODULE'),
    (bool) $formValues['enable_pm_module'],
    array('helpTextIdInline' => 'SYS_ENABLE_PM_MODULE_DESC')
);
$formMessages->addCheckbox(
    'enable_mail_captcha',
    $gL10n->get('ORG_ENABLE_CAPTCHA'),
    (bool) $formValues['enable_mail_captcha'],
    array('helpTextIdInline' => 'SYS_SHOW_CAPTCHA_DESC')
);

try {
    $formMessages->addSelectBox(
        'mail_template',
        $gL10n->get('SYS_EMAIL_TEMPLATE'),
        getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates'),
        array(
            'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.html', '', $formValues['mail_template']))),
            'showContextDependentFirstEntry' => true,
            'arrayKeyIsNotValue' => true,
            'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
            'helpTextIdInline' => array('SYS_EMAIL_TEMPLATE_DESC', array('adm_my_files/mail_templates', '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:e-mail-templates">', '</a>')))
    );
} catch (UnexpectedValueException $e) {
    $gMessage->show($e->getMessage());
}
$formMessages->addInput(
    'mail_max_receiver',
    $gL10n->get('SYS_MAX_RECEIVER'),
    $formValues['mail_max_receiver'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'SYS_MAX_RECEIVER_DESC')
);
$formMessages->addCheckbox(
    'mail_send_to_all_addresses',
    $gL10n->get('SYS_SEND_EMAIL_TO_ALL_ADDRESSES'),
    (bool) $formValues['mail_send_to_all_addresses'],
    array('helpTextIdInline' => 'SYS_SEND_EMAIL_TO_ALL_ADDRESSES_DESC')
);
$formMessages->addCheckbox(
    'mail_show_former',
    $gL10n->get('SYS_SEND_EMAIL_FORMER'),
    (bool) $formValues['mail_show_former'],
    array('helpTextIdInline' => 'SYS_SEND_EMAIL_FORMER_DESC')
);
$formMessages->addInput(
    'max_email_attachment_size',
    $gL10n->get('SYS_ATTACHMENT_SIZE').' (MB)',
    $formValues['max_email_attachment_size'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999, 'step' => 1, 'helpTextIdInline' => 'SYS_ATTACHMENT_SIZE_DESC')
);
$formMessages->addCheckbox(
    'mail_save_attachments',
    $gL10n->get('SYS_SAVE_ATTACHMENTS'),
    (bool) $formValues['mail_save_attachments'],
    array('helpTextIdInline' => 'SYS_SAVE_ATTACHMENTS_DESC')
);
$formMessages->addCheckbox(
    'mail_html_registered_users',
    $gL10n->get('SYS_HTML_MAILS_REGISTERED_USERS'),
    (bool) $formValues['mail_html_registered_users'],
    array('helpTextIdInline' => 'SYS_HTML_MAILS_REGISTERED_USERS_DESC')
);
$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DISABLED'),
    '1' => $gL10n->get('SYS_ENABLED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formMessages->addSelectBox(
    'mail_delivery_confirmation',
    $gL10n->get('SYS_DELIVERY_CONFIRMATION'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['mail_delivery_confirmation'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_DELIVERY_CONFIRMATION_DESC')
);
$formMessages->addSubmitButton(
    'btn_save_messages',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'messages', 'accordion_modules', $gL10n->get('SYS_MESSAGES'), 'fas fa-comments', $formMessages->show()));

// PANEL: PROFILE

$formProfile = new HtmlForm(
    'profile_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'profile')),
    $page,
    array('class' => 'form-preferences')
);

$html = '<a class="btn btn-secondary" href="'. ADMIDIO_URL. FOLDER_MODULES.'/profile-fields/profile_fields.php">
            <i class="fas fa-th-list"></i>'.$gL10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'</a>';
$formProfile->addCustomContent($gL10n->get('SYS_EDIT_PROFILE_FIELDS'), $html, array('helpTextIdInline' => $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
$formProfile->addCheckbox(
    'profile_log_edit_fields',
    $gL10n->get('PRO_LOG_EDIT_FIELDS'),
    (bool) $formValues['profile_log_edit_fields'],
    array('helpTextIdInline' => 'PRO_LOG_EDIT_FIELDS_DESC')
);
$formProfile->addCheckbox(
    'profile_show_map_link',
    $gL10n->get('PRO_SHOW_MAP_LINK'),
    (bool) $formValues['profile_show_map_link'],
    array('helpTextIdInline' => 'PRO_SHOW_MAP_LINK_DESC')
);
$formProfile->addCheckbox(
    'profile_show_roles',
    $gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP'),
    (bool) $formValues['profile_show_roles'],
    array('helpTextIdInline' => 'PRO_SHOW_ROLE_MEMBERSHIP_DESC')
);
$formProfile->addCheckbox(
    'profile_show_former_roles',
    $gL10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP'),
    (bool) $formValues['profile_show_former_roles'],
    array('helpTextIdInline' => 'PRO_SHOW_FORMER_ROLE_MEMBERSHIP_DESC')
);

if ($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->isParentOrganization()) {
    $formProfile->addCheckbox(
        'profile_show_extern_roles',
        $gL10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS'),
        (bool) $formValues['profile_show_extern_roles'],
        array('helpTextIdInline' => 'PRO_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC')
    );
}

$selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
$formProfile->addSelectBox(
    'profile_photo_storage',
    $gL10n->get('PRO_LOCATION_PROFILE_PICTURES'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['profile_photo_storage'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PRO_LOCATION_PROFILE_PICTURES_DESC')
);
$formProfile->addSubmitButton(
    'btn_save_profile',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'profile', 'accordion_modules', $gL10n->get('PRO_PROFILE'), 'fas fa-user', $formProfile->show()));

// PANEL: EVENTS

$formEvents = new HtmlForm(
    'events_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'events')),
    $page,
    array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DISABLED'),
    '1' => $gL10n->get('SYS_ENABLED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formEvents->addSelectBox(
    'enable_dates_module',
    $gL10n->get('ORG_ACCESS_TO_MODULE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['enable_dates_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
if ($gSettingsManager->getBool('dates_show_rooms')) {
    $selectBoxEntries = array(
        'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
        'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
        'room'         => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'),
        'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
        'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
    );
} else {
    $selectBoxEntries = array(
        'detail'       => $gL10n->get('DAT_VIEW_MODE_DETAIL'),
        'compact'      => $gL10n->get('DAT_VIEW_MODE_COMPACT'),
        'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'),
        'description'  => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION')
    );
}
$formEvents->addSelectBox(
    'dates_view',
    $gL10n->get('DAT_VIEW_MODE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['dates_view'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('DAT_VIEW_MODE_DESC', array('DAT_VIEW_MODE_DETAIL', 'DAT_VIEW_MODE_COMPACT')))
);
$selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
$formEvents->addSelectBox(
    'dates_per_page',
    $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['dates_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
);
$formEvents->addCheckbox(
    'enable_dates_ical',
    $gL10n->get('DAT_ENABLE_ICAL'),
    (bool) $formValues['enable_dates_ical'],
    array('helpTextIdInline' => 'DAT_ENABLE_ICAL_DESC')
);
$formEvents->addInput(
    'dates_ical_days_past',
    $gL10n->get('DAT_ICAL_DAYS_PAST'),
    $formValues['dates_ical_days_past'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'DAT_ICAL_DAYS_PAST_DESC')
);
$formEvents->addInput(
    'dates_ical_days_future',
    $gL10n->get('DAT_ICAL_DAYS_FUTURE'),
    $formValues['dates_ical_days_future'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'DAT_ICAL_DAYS_FUTURE_DESC')
);
$formEvents->addCheckbox(
    'dates_show_map_link',
    $gL10n->get('DAT_SHOW_MAP_LINK'),
    (bool) $formValues['dates_show_map_link'],
    array('helpTextIdInline' => 'DAT_SHOW_MAP_LINK_DESC')
);
$sqlData = array();
$sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM '.TBL_LISTS.'
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                   ORDER BY lst_name ASC, lst_timestamp DESC';
$sqlData['params'] = array($gCurrentOrgId);
$formEvents->addSelectBoxFromSql(
    'dates_default_list_configuration',
    $gL10n->get('DAT_DEFAULT_LIST_CONFIGURATION'),
    $gDb,
    $sqlData,
    array('defaultValue' => $formValues['dates_default_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'DAT_DEFAULT_LIST_CONFIGURATION_DESC')
);
$formEvents->addCheckbox(
    'dates_save_all_confirmations',
    $gL10n->get('DAT_SAVE_ALL_CONFIRMATIONS'),
    (bool) $formValues['dates_save_all_confirmations'],
    array('helpTextIdInline' => 'DAT_SAVE_ALL_CONFIRMATIONS_DESC')
);
$formEvents->addCheckbox(
    'dates_may_take_part',
    $gL10n->get('SYS_MAYBE_PARTICIPATE'),
    (bool) $formValues['dates_may_take_part'],
    array('helpTextIdInline' => $gL10n->get('SYS_MAYBE_PARTICIPATE_DESC', array('SYS_PARTICIPATE', 'DAT_CANCEL', 'DAT_USER_TENTATIVE')))
);
$html = '<a class="btn btn-secondary" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'DAT')).'">
            <i class="fas fa-th-large"></i>'.$gL10n->get('DAT_SWITCH_TO_CALENDAR_ADMINISTRATION').'</a>';
$formEvents->addCustomContent($gL10n->get('SYS_EDIT_CALENDARS'), $html, array('helpTextIdInline' => $gL10n->get('DAT_EDIT_CALENDAR_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
$formEvents->addCheckbox(
    'dates_show_rooms',
    $gL10n->get('DAT_ROOM_SELECTABLE'),
    (bool) $formValues['dates_show_rooms'],
    array('helpTextIdInline' => 'DAT_ROOM_SELECTABLE_DESC')
);
$html = '<a class="btn btn-secondary" href="'. ADMIDIO_URL. FOLDER_MODULES.'/rooms/rooms.php">
            <i class="fas fa-home"></i>'.$gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>';
$formEvents->addCustomContent($gL10n->get('DAT_EDIT_ROOMS'), $html, array('helpTextIdInline' => $gL10n->get('DAT_EDIT_ROOMS_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
$formEvents->addSubmitButton(
    'btn_save_events',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'events', 'accordion_modules', $gL10n->get('DAT_DATES'), 'fas fa-calendar-alt', $formEvents->show()));

// PANEL: WEBLINKS

$formWeblinks = new HtmlForm(
    'links_preferences_form',
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('form' => 'links')),
    $page,
    array('class' => 'form-preferences')
);

$selectBoxEntries = array(
    '0' => $gL10n->get('SYS_DISABLED'),
    '1' => $gL10n->get('SYS_ENABLED'),
    '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
);
$formWeblinks->addSelectBox(
    'enable_weblinks_module',
    $gL10n->get('ORG_ACCESS_TO_MODULE'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['enable_weblinks_module'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'ORG_ACCESS_TO_MODULE_DESC')
);
$formWeblinks->addInput(
    'weblinks_per_page',
    $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
    $formValues['weblinks_per_page'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(0)))
);
$selectBoxEntries = array('_self' => $gL10n->get('SYS_SAME_WINDOW'), '_blank' => $gL10n->get('SYS_NEW_WINDOW'));
$formWeblinks->addSelectBox(
    'weblinks_target',
    $gL10n->get('SYS_LINK_TARGET'),
    $selectBoxEntries,
    array('defaultValue' => $formValues['weblinks_target'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_LINK_TARGET_DESC')
);
$formWeblinks->addInput(
    'weblinks_redirect_seconds',
    $gL10n->get('SYS_DISPLAY_REDIRECT'),
    $formValues['weblinks_redirect_seconds'],
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'SYS_DISPLAY_REDIRECT_DESC')
);
$html = '<a class="btn btn-secondary" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/categories/categories.php', array('type' => 'LNK')).'">
            <i class="fas fa-th-large"></i>'.$gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION').'</a>';
$formWeblinks->addCustomContent(
    $gL10n->get('SYS_EDIT_CATEGORIES'),
    $html,
    array('helpTextIdInline' => $gL10n->get('DAT_MAINTAIN_CATEGORIES_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
);
$formWeblinks->addSubmitButton(
    'btn_save_links',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$page->addHtml(getPreferencePanel('modules', 'links', 'accordion_modules', $gL10n->get('SYS_WEBLINKS'), 'fas fa-link', $formWeblinks->show()));

$page->addHtml('
        </div>
    </div>
</div>');

$page->show();
