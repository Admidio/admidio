<?php
/**
 ***********************************************************************************************
 * Organization preferences
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * show_option : show preferences of module with this text id
 *               Example: SYS_COMMON or
 ***********************************************************************************************
 */
use Admidio\UserInterface\Preferences;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $showOption = admFuncVariableIsValid($_GET, 'show_option', 'string');

    // only administrators are allowed to edit organization preferences
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    $headline = $gL10n->get('SYS_SETTINGS');

    if ($showOption !== '') {
        // add current url to navigation stack
        $gNavigation->addUrl(CURRENT_URL, $headline);
    } else {
        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-gear-fill');
    }

    // create html page object
    $page = new Preferences('admidio-preferences', $headline);
    $page->show();
    exit();


    /**
     * Read all file names of a folder and return an array where the file names are the keys and a readable
     * version of the file names are the values.
     * @param string $folder
     * @return false|int[]|string[]
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    function getArrayFileNames(string $folder)
    {
        // get all files from the folder
        $files = array_keys(FileSystemUtils::getDirectoryContent($folder, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));

        foreach ($files as &$templateName) {
            $templateName = ucfirst(preg_replace('/[_-]/', ' ', str_replace(array('.tpl', '.html', '.txt'), '', $templateName)));
        }
        unset($templateName);

        return $files;
    }

    /**
     * @param string $type
     * @param string $text
     * @param string $info
     * @return string
     */
    function getStaticText(string $type, string $text, string $info = ''): string
    {
        return '<span class="text-' . $type . '"><strong>' . $text . '</strong></span>' . $info;
    }

    /**
     * @param string $id
     * @param string $parentId
     * @param string $title
     * @param string $icon
     * @param string $body
     * @return string
     */
    function getPreferencePanel(string $id, string $parentId, string $title, string $icon, string $body): string
    {
        $html = '
        <div id="admidio-panel-' . $id . '" class="accordion-item">
            <h2 class="accordion-header" data-bs-toggle="collapse" data-bs-target="#collapse_' . $id . '">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_' . $id . '" aria-expanded="true" aria-controls="collapseOne">
                    <i class="bi ' . $icon . '"></i>' . $title . '
                </button>
            </h2>
            <div id="collapse_' . $id . '" class="accordion-collapse collapse" data-bs-parent="#' . $parentId . '">
                <div class="accordion-body">
                    ' . $body . '
                </div>
            </div>
        </div>
    ';
        return $html;
    }

/*

    // PANEL: SYSTEM NOTIFICATION

    $formSystemNotification = new HtmlForm(
        'system_notification_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'system_notification')),
        $page,
        array('class' => 'form-preferences')
    );

    $formSystemNotification->addCheckbox(
        'system_notifications_enabled',
        $gL10n->get('SYS_ENABLE_NOTIFICATIONS'),
        (bool)$formValues['system_notifications_enabled'],
        array('helpTextId' => 'SYS_ENABLE_NOTIFICATIONS_DESC')
    );

    $formSystemNotification->addCheckbox(
        'system_notifications_new_entries',
        $gL10n->get('SYS_NOTIFICATION_NEW_ENTRIES'),
        (bool)$formValues['system_notifications_new_entries'],
        array('helpTextId' => 'SYS_NOTIFICATION_NEW_ENTRIES_DESC')
    );
    $formSystemNotification->addCheckbox(
        'system_notifications_profile_changes',
        $gL10n->get('SYS_NOTIFICATION_PROFILE_CHANGES'),
        (bool)$formValues['system_notifications_profile_changes'],
        array('helpTextId' => 'SYS_NOTIFICATION_PROFILE_CHANGES_DESC')
    );

    // read all roles of the organization
    $sqlData = array();
    $sqlData['query'] = 'SELECT rol_uuid, rol_name, cat_name
               FROM ' . TBL_ROLES . '
         INNER JOIN ' . TBL_CATEGORIES . '
                 ON cat_id = rol_cat_id
         INNER JOIN ' . TBL_ORGANIZATIONS . '
                 ON org_id = cat_org_id
              WHERE rol_valid  = true
                AND rol_system = false
                AND rol_all_lists_view = true
                AND cat_org_id = ? -- $gCurrentOrgId
                AND cat_name_intern <> \'EVENTS\'
           ORDER BY cat_name, rol_name';
    $sqlData['params'] = array($gCurrentOrgId);
    $formSystemNotification->addSelectBoxFromSql(
        'system_notifications_role',
        $gL10n->get('SYS_NOTIFICATION_ROLE'),
        $gDb,
        $sqlData,
        array('defaultValue' => $formValues['system_notifications_role'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NOTIFICATION_ROLE_DESC', array('SYS_RIGHT_ALL_LISTS_VIEW')))
    );

    $formSystemNotification->addCustomContent(
        $gL10n->get('SYS_SYSTEM_MAILS'),
        '<p>' . $gL10n->get('SYS_SYSTEM_MAIL_TEXTS_DESC') . ':</p>
    <p><strong>#user_first_name#</strong> - ' . $gL10n->get('ORG_VARIABLE_FIRST_NAME') . '<br />
    <strong>#user_last_name#</strong> - ' . $gL10n->get('ORG_VARIABLE_LAST_NAME') . '<br />
    <strong>#user_login_name#</strong> - ' . $gL10n->get('ORG_VARIABLE_USERNAME') . '<br />
    <strong>#user_email#</strong> - ' . $gL10n->get('ORG_VARIABLE_EMAIL') . '<br />
    <strong>#administrator_email#</strong> - ' . $gL10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION') . '<br />
    <strong>#organization_short_name#</strong> - ' . $gL10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION') . '<br />
    <strong>#organization_long_name#</strong> - ' . $gL10n->get('ORG_VARIABLE_NAME_ORGANIZATION') . '<br />
    <strong>#organization_homepage#</strong> - ' . $gL10n->get('ORG_VARIABLE_URL_ORGANIZATION') . '</p>'
    );

    $text = new TableText($gDb);
    $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_CONFIRMATION', 'txt_org_id' => $gCurrentOrgId));
    $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_CONFIRMATION', $gL10n->get('SYS_NOTIFICATION_REGISTRATION_CONFIRMATION'), $text->getValue('txt_text'), 7);
    $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_NEW', 'txt_org_id' => $gCurrentOrgId));
    $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_NEW', $gL10n->get('SYS_NOTIFICATION_NEW_REGISTRATION'), $text->getValue('txt_text'), 7);
    $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_APPROVED', 'txt_org_id' => $gCurrentOrgId));
    $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_APPROVED', $gL10n->get('SYS_NOTIFICATION_REGISTRATION_APPROVAL'), $text->getValue('txt_text'), 7);
    $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_REFUSED', 'txt_org_id' => $gCurrentOrgId));
    $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_REFUSED', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
    $text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $gCurrentOrgId));
    $htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES') . ':<br /><strong>#variable1#</strong> - ' . $gL10n->get('ORG_VARIABLE_NEW_PASSWORD');
    $formSystemNotification->addMultilineTextInput(
        'SYSMAIL_NEW_PASSWORD',
        $gL10n->get('ORG_SEND_NEW_PASSWORD'),
        $text->getValue('txt_text'),
        7,
        array('helpTextId' => $htmlDesc)
    );
    $text->readDataByColumns(array('txt_name' => 'SYSMAIL_PASSWORD_RESET', 'txt_org_id' => $gCurrentOrgId));
    $htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES') . ':<br /><strong>#variable1#</strong> - ' . $gL10n->get('ORG_VARIABLE_ACTIVATION_LINK');
    $formSystemNotification->addMultilineTextInput(
        'SYSMAIL_PASSWORD_RESET',
        $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
        $text->getValue('txt_text'),
        7,
        array('helpTextId' => $htmlDesc)
    );

    $formSystemNotification->addSubmitButton(
        'btn_save_system_notification',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('system_notification', 'accordion_preferences', $gL10n->get('SYS_SYSTEM_MAILS'), 'bi-broadcast-pin', $formSystemNotification->show()));

    // PANEL: CAPTCHA

    $formCaptcha = new HtmlForm(
        'captcha_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'captcha')),
        $page,
        array('class' => 'form-preferences')
    );

    $selectBoxEntries = array(
        'pic' => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'),
        'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'),
        'word' => $gL10n->get('ORG_CAPTCHA_TYPE_WORDS')
    );
    $formCaptcha->addSelectBox(
        'captcha_type',
        $gL10n->get('ORG_CAPTCHA_TYPE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['captcha_type'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_CAPTCHA_TYPE_TEXT')
    );

    $fonts = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . '/adm_program/system/fonts/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
    asort($fonts);
    $formCaptcha->addSelectBox(
        'captcha_fonts',
        $gL10n->get('SYS_FONT'),
        $fonts,
        array('defaultValue' => $formValues['captcha_fonts'], 'showContextDependentFirstEntry' => false, 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_CAPTCHA_FONT')
    );
    $formCaptcha->addInput(
        'captcha_width',
        $gL10n->get('SYS_WIDTH') . ' (' . $gL10n->get('ORG_PIXEL') . ')',
        $formValues['captcha_width'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'ORG_CAPTCHA_WIDTH_DESC')
    );
    $formCaptcha->addInput(
        'captcha_lines_numbers',
        $gL10n->get('ORG_CAPTCHA_LINES_NUMBERS'),
        $formValues['captcha_lines_numbers'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 25, 'step' => 1, 'helpTextId' => 'ORG_CAPTCHA_LINES_NUMBERS_DESC')
    );
    $formCaptcha->addInput(
        'captcha_perturbation',
        $gL10n->get('ORG_CAPTCHA_DISTORTION'),
        $formValues['captcha_perturbation'],
        array('type' => 'string', 'helpTextId' => 'ORG_CAPTCHA_DISTORTION_DESC', 'class' => 'form-control-small')
    );
    $backgrounds = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_LIBS . '/securimage/backgrounds/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
    asort($backgrounds);
    $formCaptcha->addSelectBox(
        'captcha_background_image',
        $gL10n->get('ORG_CAPTCHA_BACKGROUND_IMAGE'),
        $backgrounds,
        array('defaultValue' => $formValues['captcha_background_image'], 'showContextDependentFirstEntry' => true, 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_CAPTCHA_BACKGROUND_IMAGE_DESC')
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
        array('maxLength' => 7, 'helpTextId' => array('ORG_CAPTCHA_COLOR_DESC', array('<a href="https://en.wikipedia.org/wiki/Web_colors">', '</a>')), 'class' => 'form-control-small')
    );
    $formCaptcha->addInput(
        'captcha_charset',
        $gL10n->get('ORG_CAPTCHA_SIGNS'),
        $formValues['captcha_charset'],
        array('maxLength' => 80, 'helpTextId' => 'ORG_CAPTCHA_SIGNS_TEXT')
    );
    $formCaptcha->addInput(
        'captcha_signature',
        $gL10n->get('ORG_CAPTCHA_SIGNATURE'),
        $formValues['captcha_signature'],
        array('maxLength' => 60, 'helpTextId' => 'ORG_CAPTCHA_SIGNATURE_TEXT')
    );
    $html = '<img id="captcha" src="' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php" alt="CAPTCHA Image" />
         <a id="captcha-refresh" class="admidio-icon-link" href="javascript:void(0)">
            <i class="bi bi-arrow-repeat" style="font-size: 22pt;" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_RELOAD') . '"></i></a>';
    $formCaptcha->addCustomContent(
        $gL10n->get('ORG_CAPTCHA_PREVIEW'),
        $html,
        array('helpTextId' => 'ORG_CAPTCHA_PREVIEW_TEXT')
    );

    $formCaptcha->addSubmitButton(
        'btn_save_captcha',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('captcha', 'accordion_preferences', $gL10n->get('SYS_CAPTCHA'), 'bi-fonts', $formCaptcha->show()));

    // PANEL: ADMIDIO UPDATE

    $formAdmidioUpdate = new HtmlForm('admidio_update_preferences_form', '', $page);

    $html = '<span id="admidio_version_content">' . ADMIDIO_VERSION_TEXT . '
            <a id="link_check_for_update" href="#link_check_for_update" title="' . $gL10n->get('SYS_CHECK_FOR_UPDATE') . '">' . $gL10n->get('SYS_CHECK_FOR_UPDATE') . '</a>
         </span>';
    $formAdmidioUpdate->addCustomContent($gL10n->get('SYS_ADMIDIO_VERSION'), $html);

    // if database version is different to file version, then show database version
    if ($gSystemComponent->getValue('com_version') !== ADMIDIO_VERSION) {
        $formAdmidioUpdate->addStaticControl('admidio_database_version', $gL10n->get('ORG_DIFFERENT_DATABASE_VERSION'), $gSystemComponent->getValue('com_version'));
    }

    $component = new ComponentUpdate($gDb);
    $component->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $updateStep = (int)$gSystemComponent->getValue('com_update_step');
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

    if (DB_ENGINE === Database::PDO_ENGINE_MYSQL) {
        $html = '<a class="btn btn-secondary" id="add_another_organization" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'backup')) . '">
            <i class="bi bi-download"></i>' . $gL10n->get('SYS_DOWNLOAD_DATABASE_BACKUP') . '</a>';
        $formAdmidioUpdate->addCustomContent($gL10n->get('SYS_DATABASE_BACKUP'), $html, array('helpTextId' => 'SYS_DATABASE_BACKUP_DESC'));
    }
    $html = '<a id="donate" href="' . ADMIDIO_HOMEPAGE . 'donate.php" target="_blank">
            <i class="bi bi-heart-fill"></i>' . $gL10n->get('SYS_DONATE') . '</a>';
    $formAdmidioUpdate->addCustomContent($gL10n->get('SYS_SUPPORT_ADMIDIO'), $html, array('helpTextId' => 'INS_SUPPORT_FURTHER_DEVELOPMENT'));

    $page->addHtml(getPreferencePanel('admidio_version_backup', 'accordion_preferences', $gL10n->get('SYS_ADMIDIO_VERSION_BACKUP'), 'bi-cloud-arrow-down-fill', $formAdmidioUpdate->show()));

    // PANEL: PHP

    $formPhp = new HtmlForm('php_preferences_form', '', $page);

    if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
        $html = getStaticText('danger', PHP_VERSION, ' &rarr; ' . $gL10n->get('SYS_PHP_VERSION_REQUIRED', array(MIN_PHP_VERSION)));
    } elseif (version_compare(PHP_VERSION, '7.2', '<')) {
        $html = getStaticText('warning', PHP_VERSION, ' &rarr; ' . $gL10n->get('SYS_PHP_VERSION_EOL', array('<a href="https://www.php.net/supported-versions.php" target="_blank">Supported Versions</a>')));
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
        $html = getStaticText('danger', $gL10n->get('SYS_PRNG_INSECURE'), '<br />' . $e->getMessage());
    }
    $formPhp->addStaticControl('pseudo_random_number_generator', $gL10n->get('SYS_PRNG'), $html);

    $html = '<a href="' . ADMIDIO_URL . '/adm_program/system/phpinfo.php' . '" target="_blank">phpinfo()</a> <i class="bi bi-box-arrow-up-right"></i>';
    $formPhp->addStaticControl('php_info', $gL10n->get('SYS_PHP_INFO'), $html);

    $page->addHtml(getPreferencePanel('php', 'accordion_preferences', $gL10n->get('SYS_PHP'), 'bi-filetype-php', $formPhp->show()));

    // PANEL: SYSTEM INFORMATION

    $formSystemInformation = new HtmlForm('system_information_preferences_form', '', $page);

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
        $html = getStaticText('danger', $gDb->getVersion(), ' &rarr; ' . $gL10n->get('SYS_DATABASE_VERSION_REQUIRED', array($gDb->getMinimumRequiredVersion())));
    } else {
        $html = getStaticText('success', $gDb->getVersion());
    }
    $formSystemInformation->addStaticControl('database_version', $gDb->getName() . '-' . $gL10n->get('SYS_VERSION'), $html);

    if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess')) {
        $html = getStaticText('success', $gL10n->get('SYS_ON'));
    } else {
        $html = getStaticText(
            'danger',
            '<span id="directory_protection_status">' . $gL10n->get('SYS_OFF') . '</span>',
            ' &rarr; <a id="link_directory_protection" href="#link_directory_protection" title="' . $gL10n->get('SYS_CREATE_HTACCESS') . '">' . $gL10n->get('SYS_CREATE_HTACCESS') . '</a>'
        );
    }
    $formSystemInformation->addStaticControl('directory_protection', $gL10n->get('SYS_DIRECTORY_PROTECTION'), $html);

    $formSystemInformation->addStaticControl('max_processable_image_size', $gL10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE'), round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2) . ' ' . $gL10n->get('SYS_MEGAPIXEL'));

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
    } catch (RuntimeException $exception) {
        $gLogger->error('FILE-SYSTEM: Disk space could not be determined!');

        $html = getStaticText('danger', $gL10n->get('SYS_DISK_SPACE_ERROR', array($exception->getMessage())));
    }
    $formSystemInformation->addStaticControl('disk_space', $gL10n->get('SYS_DISK_SPACE'), $html);

    $page->addHtml(getPreferencePanel('system_information', 'accordion_preferences', $gL10n->get('ORG_SYSTEM_INFORMATION'), 'bi-info-circle-fill', $formSystemInformation->show()));

    $page->addHtml('
        </div>
    </div>
    <div class="tab-pane fade" id="tabs-modules" role="tabpanel">
        <div class="accordion" id="accordion_modules">');

    // PANEL: ANNOUNCEMENTS

    $formAnnouncements = new HtmlForm(
        'announcements_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'announcements')),
        $page,
        array('class' => 'form-preferences')
    );

    $selectBoxEntries = array(
        '0' => $gL10n->get('SYS_DISABLED'),
        '1' => $gL10n->get('SYS_ENABLED'),
        '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
    );
    $formAnnouncements->addSelectBox(
        'announcements_module_enabled',
        $gL10n->get('ORG_ACCESS_TO_MODULE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['announcements_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
    );
    $formAnnouncements->addInput(
        'announcements_per_page',
        $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
        $formValues['announcements_per_page'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
    );
    $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories.php', array('type' => 'ANN')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
    $formAnnouncements->addCustomContent(
        $gL10n->get('SYS_EDIT_CATEGORIES'),
        $html,
        array('helpTextId' => 'SYS_MAINTAIN_CATEGORIES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
    );
    $formAnnouncements->addSubmitButton(
        'btn_save_announcements',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('announcements', 'accordion_modules', $gL10n->get('SYS_ANNOUNCEMENTS'), 'bi-newspaper', $formAnnouncements->show()));

    // PANEL: CONTACTS

    $formUserManagement = new HtmlForm(
        'contacts_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'contacts')),
        $page,
        array('class' => 'form-preferences')
    );

    // read all global lists
    $sqlData = array();
    $sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM ' . TBL_LISTS . '
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                        AND NOT EXISTS (SELECT 1
                                       FROM ' . TBL_LIST_COLUMNS . '
                                       WHERE lsc_lst_id = lst_id
                                       AND lsc_special_field LIKE \'mem%\')
                   ORDER BY lst_name, lst_timestamp DESC';
    $sqlData['params'] = array($gCurrentOrgId);
    $formUserManagement->addSelectBoxFromSql(
        'contacts_list_configuration',
        $gL10n->get('SYS_CONFIGURATION_LIST'),
        $gDb,
        $sqlData,
        array('defaultValue' => $formValues['contacts_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_MEMBERS_CONFIGURATION_DESC')
    );
    $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
    $formUserManagement->addSelectBox(
        'contacts_per_page',
        $gL10n->get('SYS_CONTACTS_PER_PAGE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['contacts_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(25)))
    );
    $formUserManagement->addInput(
        'contacts_field_history_days',
        $gL10n->get('SYS_DAYS_FIELD_HISTORY'),
        $formValues['contacts_field_history_days'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999999999, 'step' => 1, 'helpTextId' => 'SYS_DAYS_FIELD_HISTORY_DESC')
    );
    $formUserManagement->addCheckbox(
        'contacts_show_all',
        $gL10n->get('SYS_SHOW_ALL_CONTACTS'),
        (bool)$formValues['contacts_show_all'],
        array('helpTextId' => 'SYS_SHOW_ALL_CONTACTS_DESC')
    );
    $formUserManagement->addCheckbox(
        'contacts_user_relations_enabled',
        $gL10n->get('SYS_ENABLE_USER_RELATIONS'),
        (bool)$formValues['contacts_user_relations_enabled'],
        array('helpTextId' => 'SYS_ENABLE_USER_RELATIONS_DESC')
    );

    $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes.php">
            <i class="bi bi-person-heart"></i>' . $gL10n->get('SYS_SWITCH_TO_RELATIONSHIP_CONFIGURATION') . '</a>';
    $formUserManagement->addCustomContent($gL10n->get('SYS_USER_RELATIONS'), $html, array('helpTextId' => 'SYS_MAINTAIN_USER_RELATION_TYPES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));

    $formUserManagement->addSubmitButton(
        'btn_save_contacts',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('contacts', 'accordion_modules', $gL10n->get('SYS_CONTACTS'), 'bi-person-vcard-fill', $formUserManagement->show()));

    // PANEL: DOCUMENTS-FILES

    $formDownloads = new HtmlForm(
        'documents_files_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'documents-files')),
        $page,
        array('class' => 'form-preferences')
    );

    $formDownloads->addCheckbox(
        'documents_files_module_enabled',
        $gL10n->get('SYS_ENABLE_DOCUMENTS_FILES_MODULE'),
        (bool)$formValues['documents_files_module_enabled'],
        array('helpTextId' => 'SYS_ENABLE_DOCUMENTS_FILES_MODULE_DESC')
    );
    $formDownloads->addInput(
        'documents_files_max_upload_size',
        $gL10n->get('SYS_MAXIMUM_FILE_SIZE') . ' (MB)',
        $formValues['documents_files_max_upload_size'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999999, 'step' => 1, 'helpTextId' => 'SYS_MAXIMUM_FILE_SIZE_DESC')
    );
    $formDownloads->addSubmitButton(
        'btn_save_documents_files',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('documents-files', 'accordion_modules', $gL10n->get('SYS_DOCUMENTS_FILES'), 'bi-file-earmark-arrow-down-fill', $formDownloads->show()));

    // PANEL: PHOTOS

    $formPhotos = new HtmlForm(
        'photos_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'photos')),
        $page,
        array('class' => 'form-preferences')
    );

    $selectBoxEntries = array(
        '0' => $gL10n->get('SYS_DISABLED'),
        '1' => $gL10n->get('SYS_ENABLED'),
        '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
    );
    $formPhotos->addSelectBox(
        'photo_module_enabled',
        $gL10n->get('ORG_ACCESS_TO_MODULE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['photo_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
    );
    $selectBoxEntries = array(
        '1' => $gL10n->get('SYS_MODAL_WINDOW'),
        '2' => $gL10n->get('SYS_SAME_WINDOW')
    );
    $formPhotos->addSelectBox(
        'photo_show_mode',
        $gL10n->get('SYS_PHOTOS_PRESENTATION'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['photo_show_mode'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_PHOTOS_PRESENTATION_DESC')
    );
    $formPhotos->addInput(
        'photo_albums_per_page',
        $gL10n->get('SYS_NUMBER_OF_ALBUMS_PER_PAGE'),
        $formValues['photo_albums_per_page'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(24)))
    );
    $formPhotos->addInput(
        'photo_thumbs_page',
        $gL10n->get('SYS_THUMBNAILS_PER_PAGE'),
        $formValues['photo_thumbs_page'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_THUMBNAILS_PER_PAGE_DESC', array(24)))
    );
    $formPhotos->addInput(
        'photo_thumbs_scale',
        $gL10n->get('SYS_THUMBNAIL_SCALING'),
        $formValues['photo_thumbs_scale'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_THUMBNAIL_SCALING_DESC', array(500)))
    );
    $formPhotos->addInput(
        'photo_show_width',
        $gL10n->get('SYS_MAX_PHOTO_SIZE_WIDTH'),
        $formValues['photo_show_width'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1)
    );
    $formPhotos->addInput(
        'photo_show_height',
        $gL10n->get('SYS_MAX_PHOTO_SIZE_HEIGHT'),
        $formValues['photo_show_height'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_MAX_PHOTO_SIZE_DESC', array(1200, 1200)))
    );
    $formPhotos->addInput(
        'photo_image_text',
        $gL10n->get('SYS_SHOW_WATERMARK'),
        $formValues['photo_image_text'],
        array('maxLength' => 60, 'helpTextId' => array('SYS_SHOW_WATERMARK_DESC', array('© ' . DOMAIN)))
    );
    $formPhotos->addInput(
        'photo_image_text_size',
        $gL10n->get('SYS_CAPTION_SIZE'),
        $formValues['photo_image_text_size'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_CAPTION_SIZE_DESC')
    );
    $formPhotos->addCheckbox(
        'photo_download_enabled',
        $gL10n->get('SYS_ENABLE_DOWNLOAD'),
        (bool)$formValues['photo_download_enabled'],
        array('helpTextId' => array('SYS_ENABLE_DOWNLOAD_DESC', array('SYS_KEEP_ORIGINAL')))
    );
    $formPhotos->addCheckbox(
        'photo_keep_original',
        $gL10n->get('SYS_KEEP_ORIGINAL'),
        (bool)$formValues['photo_keep_original'],
        array('helpTextId' => array('SYS_KEEP_ORIGINAL_DESC', array('SYS_ENABLE_DOWNLOAD')))
    );
    $formPhotos->addCheckbox(
        'photo_ecard_enabled',
        $gL10n->get('SYS_ENABLE_GREETING_CARDS'),
        (bool)$formValues['photo_ecard_enabled'],
        array('helpTextId' => 'SYS_ENABLE_GREETING_CARDS_DESC')
    );
    $formPhotos->addInput(
        'photo_ecard_scale',
        $gL10n->get('SYS_THUMBNAIL_SCALING'),
        $formValues['photo_ecard_scale'],
        array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_ECARD_MAX_PHOTO_SIZE_DESC', array(500)))
    );

    $formPhotos->addSelectBox(
        'photo_ecard_template',
        $gL10n->get('SYS_TEMPLATE'),
        getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates'),
        array(
            'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $formValues['photo_ecard_template']))),
            'showContextDependentFirstEntry' => false,
            'arrayKeyIsNotValue' => true,
            'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
            'helpTextId' => 'SYS_TEMPLATE_DESC'
        )
    );

    $formPhotos->addSubmitButton(
        'btn_save_photos',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('photos', 'accordion_modules', $gL10n->get('SYS_PHOTOS'), 'bi-image-fill', $formPhotos->show()));

    // PANEL: GUESTBOOK

    $formGuestbook = new HtmlForm(
        'guestbook_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'guestbook')),
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
        array('defaultValue' => $formValues['enable_guestbook_module'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
    );
    $formGuestbook->addInput(
        'guestbook_entries_per_page',
        $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
        $formValues['guestbook_entries_per_page'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
    );
    $formGuestbook->addCheckbox(
        'enable_guestbook_captcha',
        $gL10n->get('ORG_ENABLE_CAPTCHA'),
        (bool)$formValues['enable_guestbook_captcha'],
        array('helpTextId' => 'GBO_CAPTCHA_DESC')
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
        array('defaultValue' => $formValues['enable_guestbook_moderation'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'GBO_GUESTBOOK_MODERATION_DESC')
    );
    $formGuestbook->addCheckbox(
        'enable_gbook_comments4all',
        $gL10n->get('GBO_COMMENTS4ALL'),
        (bool)$formValues['enable_gbook_comments4all'],
        array('helpTextId' => 'GBO_COMMENTS4ALL_DESC')
    );
    $formGuestbook->addCheckbox(
        'enable_intial_comments_loading',
        $gL10n->get('GBO_INITIAL_COMMENTS_LOADING'),
        (bool)$formValues['enable_intial_comments_loading'],
        array('helpTextId' => 'GBO_INITIAL_COMMENTS_LOADING_DESC')
    );
    $formGuestbook->addInput(
        'flooding_protection_time',
        $gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL'),
        $formValues['flooding_protection_time'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'GBO_FLOODING_PROTECTION_INTERVALL_DESC')
    );
    $formGuestbook->addSubmitButton(
        'btn_save_guestbook',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('guestbook', 'accordion_modules', $gL10n->get('GBO_GUESTBOOK'), 'bi-book-half', $formGuestbook->show()));

    // PANEL: GROUPS AND ROLES

    $formGroupsRoles = new HtmlForm(
        'groups_roles_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'groups-roles')),
        $page,
        array('class' => 'form-preferences')
    );

    $formGroupsRoles->addCheckbox(
        'groups_roles_enable_module',
        $gL10n->get('SYS_ENABLE_GROUPS_ROLES'),
        (bool)$formValues['groups_roles_enable_module'],
        array('helpTextId' => 'SYS_ENABLE_GROUPS_ROLES_DESC')
    );
    $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
    $formGroupsRoles->addSelectBox(
        'groups_roles_members_per_page',
        $gL10n->get('SYS_MEMBERS_PER_PAGE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['groups_roles_members_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_MEMBERS_PER_PAGE_DESC')
    );
    // read all global lists
    $sqlData = array();
    $sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM ' . TBL_LISTS . '
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                   ORDER BY lst_name, lst_timestamp DESC';
    $sqlData['params'] = array($gCurrentOrgId);
    $formGroupsRoles->addSelectBoxFromSql(
        'groups_roles_default_configuration',
        $gL10n->get('SYS_DEFAULT_CONFIGURATION'),
        $gDb,
        $sqlData,
        array('defaultValue' => $formValues['groups_roles_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_CONFIGURATION_LISTS_DESC')
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
        array('defaultValue' => $formValues['groups_roles_show_former_members'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_SHOW_FORMER_MEMBERS_DESC', array($gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))))
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
        array('defaultValue' => $formValues['groups_roles_export'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_EXPORT_LISTS_DESC')
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
        array('defaultValue' => $formValues['groups_roles_edit_lists'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_CONFIGURE_LISTS_DESC')
    );
    $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories.php', array('type' => 'ROL')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
    $formGroupsRoles->addCustomContent($gL10n->get('SYS_EDIT_CATEGORIES'), $html, array('helpTextId' => 'SYS_MAINTAIN_CATEGORIES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
    $formGroupsRoles->addSubmitButton(
        'btn_save_lists',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('groups-roles', 'accordion_modules', $gL10n->get('SYS_GROUPS_ROLES'), 'bi-people-fill', $formGroupsRoles->show()));

    // PANEL: CATEGORY-REPORT

    $formCategoryReport = new HtmlForm(
        'category_report_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'category-report')),
        $page,
        array('class' => 'form-preferences')
    );

    $formCategoryReport->addCheckbox(
        'category_report_enable_module',
        $gL10n->get('SYS_ENABLE_CATEGORY_REPORT'),
        (bool)$formValues['category_report_enable_module'],
        array('helpTextId' => array('SYS_ENABLE_CATEGORY_REPORT_DESC', array($gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'))))
    );
    // read all global lists
    $sqlData = array();
    $sqlData['query'] = 'SELECT crt_id, crt_name
                       FROM ' . TBL_CATEGORY_REPORT . '
                      WHERE crt_org_id = ? -- $gCurrentOrgId
                   ORDER BY crt_name';
    $sqlData['params'] = array($gCurrentOrgId);
    $formCategoryReport->addSelectBoxFromSql(
        'category_report_default_configuration',
        $gL10n->get('SYS_DEFAULT_CONFIGURATION'),
        $gDb,
        $sqlData,
        array('defaultValue' => $formValues['category_report_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_CONFIGURATION_CAT_REP_DESC')
    );

    $formCategoryReport->addSubmitButton(
        'btn_save_documents_files',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('category-report', 'accordion_modules', $gL10n->get('SYS_CATEGORY_REPORT'), 'bi-list-stars', $formCategoryReport->show()));

    // PANEL: MESSAGES

    $formMessages = new HtmlForm(
        'messages_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'messages')),
        $page,
        array('class' => 'form-preferences')
    );

    $formMessages->addCheckbox(
        'enable_mail_module',
        $gL10n->get('SYS_ENABLE_EMAILS'),
        (bool)$formValues['enable_mail_module'],
        array('helpTextId' => 'SYS_ENABLE_EMAILS_DESC')
    );
    $formMessages->addCheckbox(
        'enable_pm_module',
        $gL10n->get('SYS_ENABLE_PM_MODULE'),
        (bool)$formValues['enable_pm_module'],
        array('helpTextId' => 'SYS_ENABLE_PM_MODULE_DESC')
    );
    $formMessages->addCheckbox(
        'enable_mail_captcha',
        $gL10n->get('ORG_ENABLE_CAPTCHA'),
        (bool)$formValues['enable_mail_captcha'],
        array('helpTextId' => 'SYS_SHOW_CAPTCHA_DESC')
    );

    $formMessages->addSelectBox(
        'mail_template',
        $gL10n->get('SYS_EMAIL_TEMPLATE'),
        getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates'),
        array(
            'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.html', '', $formValues['mail_template']))),
            'showContextDependentFirstEntry' => true,
            'arrayKeyIsNotValue' => true,
            'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
            'helpTextId' => array('SYS_EMAIL_TEMPLATE_DESC', array('adm_my_files/mail_templates', '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:e-mail-templates">', '</a>')))
    );
    $formMessages->addInput(
        'mail_max_receiver',
        $gL10n->get('SYS_MAX_RECEIVER'),
        $formValues['mail_max_receiver'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_MAX_RECEIVER_DESC')
    );
    $formMessages->addCheckbox(
        'mail_send_to_all_addresses',
        $gL10n->get('SYS_SEND_EMAIL_TO_ALL_ADDRESSES'),
        (bool)$formValues['mail_send_to_all_addresses'],
        array('helpTextId' => 'SYS_SEND_EMAIL_TO_ALL_ADDRESSES_DESC')
    );
    $formMessages->addCheckbox(
        'mail_show_former',
        $gL10n->get('SYS_SEND_EMAIL_FORMER'),
        (bool)$formValues['mail_show_former'],
        array('helpTextId' => 'SYS_SEND_EMAIL_FORMER_DESC')
    );
    $formMessages->addInput(
        'max_email_attachment_size',
        $gL10n->get('SYS_ATTACHMENT_SIZE') . ' (MB)',
        $formValues['max_email_attachment_size'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999, 'step' => 1, 'helpTextId' => 'SYS_ATTACHMENT_SIZE_DESC')
    );
    $formMessages->addCheckbox(
        'mail_save_attachments',
        $gL10n->get('SYS_SAVE_ATTACHMENTS'),
        (bool)$formValues['mail_save_attachments'],
        array('helpTextId' => 'SYS_SAVE_ATTACHMENTS_DESC')
    );
    $formMessages->addCheckbox(
        'mail_html_registered_users',
        $gL10n->get('SYS_HTML_MAILS_REGISTERED_USERS'),
        (bool)$formValues['mail_html_registered_users'],
        array('helpTextId' => 'SYS_HTML_MAILS_REGISTERED_USERS_DESC')
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
        array('defaultValue' => $formValues['mail_delivery_confirmation'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DELIVERY_CONFIRMATION_DESC')
    );
    $formMessages->addSubmitButton(
        'btn_save_messages',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('messages', 'accordion_modules', $gL10n->get('SYS_MESSAGES'), 'bi-envelope-fill', $formMessages->show()));

    // PANEL: PROFILE

    $formProfile = new HtmlForm(
        'profile_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'profile')),
        $page,
        array('class' => 'form-preferences')
    );

    $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields.php">
            <i class="bi bi-ui-radios"></i>' . $gL10n->get('SYS_SWITCH_TO_PROFILE_FIELDS_CONFIGURATION') . '</a>';
    $formProfile->addCustomContent($gL10n->get('SYS_EDIT_PROFILE_FIELDS'), $html, array('helpTextId' => 'SYS_MANAGE_PROFILE_FIELDS_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
    $formProfile->addCheckbox(
        'profile_log_edit_fields',
        $gL10n->get('SYS_LOG_ALL_CHANGES'),
        (bool)$formValues['profile_log_edit_fields'],
        array('helpTextId' => 'SYS_LOG_ALL_CHANGES_DESC')
    );
    $formProfile->addCheckbox(
        'profile_show_map_link',
        $gL10n->get('SYS_SHOW_MAP_LINK'),
        (bool)$formValues['profile_show_map_link'],
        array('helpTextId' => 'SYS_SHOW_MAP_LINK_PROFILE_DESC')
    );
    $formProfile->addCheckbox(
        'profile_show_roles',
        $gL10n->get('SYS_SHOW_ROLE_MEMBERSHIP'),
        (bool)$formValues['profile_show_roles'],
        array('helpTextId' => 'SYS_SHOW_ROLE_MEMBERSHIP_DESC')
    );
    $formProfile->addCheckbox(
        'profile_show_former_roles',
        $gL10n->get('SYS_SHOW_FORMER_ROLE_MEMBERSHIP'),
        (bool)$formValues['profile_show_former_roles'],
        array('helpTextId' => 'SYS_SHOW_FORMER_ROLE_MEMBERSHIP_DESC')
    );

    if ($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->isParentOrganization()) {
        $formProfile->addCheckbox(
            'profile_show_extern_roles',
            $gL10n->get('SYS_SHOW_ROLES_OTHER_ORGANIZATIONS'),
            (bool)$formValues['profile_show_extern_roles'],
            array('helpTextId' => 'SYS_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC')
        );
    }

    $selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
    $formProfile->addSelectBox(
        'profile_photo_storage',
        $gL10n->get('SYS_LOCATION_PROFILE_PICTURES'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['profile_photo_storage'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_LOCATION_PROFILE_PICTURES_DESC')
    );
    $formProfile->addSubmitButton(
        'btn_save_profile',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('profile', 'accordion_modules', $gL10n->get('SYS_PROFILE'), 'bi-person-fill', $formProfile->show()));

    // PANEL: EVENTS

    $formEvents = new HtmlForm(
        'events_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'events')),
        $page,
        array('class' => 'form-preferences')
    );

    $selectBoxEntries = array(
        '0' => $gL10n->get('SYS_DISABLED'),
        '1' => $gL10n->get('SYS_ENABLED'),
        '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
    );
    $formEvents->addSelectBox(
        'events_module_enabled',
        $gL10n->get('ORG_ACCESS_TO_MODULE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['events_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
    );
    if ($gSettingsManager->getBool('events_rooms_enabled')) {
        $selectBoxEntries = array(
            'detail' => $gL10n->get('SYS_DETAILED'),
            'compact' => $gL10n->get('SYS_COMPACT'),
            'room' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_ROOM'),
            'participants' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_PARTICIPANTS'),
            'description' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_DESCRIPTION')
        );
    } else {
        $selectBoxEntries = array(
            'detail' => $gL10n->get('SYS_DETAILED'),
            'compact' => $gL10n->get('SYS_COMPACT'),
            'participants' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_PARTICIPANTS'),
            'description' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_DESCRIPTION')
        );
    }
    $formEvents->addSelectBox(
        'events_view',
        $gL10n->get('SYS_DEFAULT_VIEW'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['events_view'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_DEFAULT_VIEW_DESC', array('SYS_DETAILED', 'SYS_COMPACT')))
    );
    $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
    $formEvents->addSelectBox(
        'events_per_page',
        $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['events_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
    );
    $formEvents->addCheckbox(
        'events_ical_export_enabled',
        $gL10n->get('SYS_ENABLE_ICAL_EXPORT'),
        (bool)$formValues['events_ical_export_enabled'],
        array('helpTextId' => 'SYS_ENABLE_ICAL_EXPORT_DESC')
    );
    $formEvents->addCheckbox(
        'events_show_map_link',
        $gL10n->get('SYS_SHOW_MAP_LINK'),
        (bool)$formValues['events_show_map_link'],
        array('helpTextId' => 'SYS_SHOW_MAP_LINK_DESC')
    );
    $sqlData = array();
    $sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM ' . TBL_LISTS . '
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                   ORDER BY lst_name, lst_timestamp DESC';
    $sqlData['params'] = array($gCurrentOrgId);
    $formEvents->addSelectBoxFromSql(
        'events_list_configuration',
        $gL10n->get('SYS_DEFAULT_LIST_CONFIGURATION_PARTICIPATION'),
        $gDb,
        $sqlData,
        array('defaultValue' => $formValues['events_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_LIST_CONFIGURATION_PARTICIPATION_DESC')
    );
    $formEvents->addCheckbox(
        'events_save_cancellations',
        $gL10n->get('SYS_SAVE_ALL_CANCELLATIONS'),
        (bool)$formValues['events_save_cancellations'],
        array('helpTextId' => 'SYS_SAVE_ALL_CANCELLATIONS_DESC')
    );
    $formEvents->addCheckbox(
        'events_may_take_part',
        $gL10n->get('SYS_MAYBE_PARTICIPATE'),
        (bool)$formValues['events_may_take_part'],
        array('helpTextId' => array('SYS_MAYBE_PARTICIPATE_DESC', array('SYS_PARTICIPATE', 'SYS_CANCEL', 'SYS_EVENT_PARTICIPATION_TENTATIVE')))
    );
    $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories.php', array('type' => 'EVT')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CALENDAR_MANAGEMENT') . '</a>';
    $formEvents->addCustomContent($gL10n->get('SYS_EDIT_CALENDARS'), $html, array('helpTextId' => 'SYS_EDIT_CALENDAR_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
    $formEvents->addCheckbox(
        'events_rooms_enabled',
        $gL10n->get('SYS_ROOM_SELECTABLE'),
        (bool)$formValues['events_rooms_enabled'],
        array('helpTextId' => 'SYS_ROOM_SELECTABLE_DESC')
    );
    $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/rooms/rooms.php">
            <i class="bi bi-house-door-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_ROOM_MANAGEMENT') . '</a>';
    $formEvents->addCustomContent($gL10n->get('SYS_EDIT_ROOMS'), $html, array('helpTextId' => 'SYS_EDIT_ROOMS_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
    $formEvents->addSubmitButton(
        'btn_save_events',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('events', 'accordion_modules', $gL10n->get('SYS_EVENTS'), 'bi-calendar-week-fill', $formEvents->show()));

    // PANEL: WEBLINKS

    $formWeblinks = new HtmlForm(
        'links_preferences_form',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'links')),
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
        array('defaultValue' => $formValues['enable_weblinks_module'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
    );
    $formWeblinks->addInput(
        'weblinks_per_page',
        $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
        $formValues['weblinks_per_page'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(0)))
    );
    $selectBoxEntries = array('_self' => $gL10n->get('SYS_SAME_WINDOW'), '_blank' => $gL10n->get('SYS_NEW_WINDOW'));
    $formWeblinks->addSelectBox(
        'weblinks_target',
        $gL10n->get('SYS_LINK_TARGET'),
        $selectBoxEntries,
        array('defaultValue' => $formValues['weblinks_target'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_LINK_TARGET_DESC')
    );
    $formWeblinks->addInput(
        'weblinks_redirect_seconds',
        $gL10n->get('SYS_DISPLAY_REDIRECT'),
        $formValues['weblinks_redirect_seconds'],
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_DISPLAY_REDIRECT_DESC')
    );
    $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories.php', array('type' => 'LNK')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
    $formWeblinks->addCustomContent(
        $gL10n->get('SYS_EDIT_CATEGORIES'),
        $html,
        array('helpTextId' => $gL10n->get('SYS_MAINTAIN_CATEGORIES_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
    );
    $formWeblinks->addSubmitButton(
        'btn_save_links',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg')
    );

    $page->addHtml(getPreferencePanel('links', 'accordion_modules', $gL10n->get('SYS_WEBLINKS'), 'bi-link-45deg', $formWeblinks->show()));
*/
/*
    $page->addHtml('
            </div>
        </div>
    </div>');*/

} catch (AdmException|Exception|\Smarty\Exception|UnexpectedValueException $e) {
    $gMessage->show($e->getMessage());
}
