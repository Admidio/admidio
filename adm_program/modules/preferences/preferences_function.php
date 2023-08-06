<?php
/**
 ***********************************************************************************************
 * Save organization preferences
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : 1 - Save organization preferences
 *            2 - show welcome dialog for new organization
 *            3 - Create basic data for new organization in database
 *            4 - set directory protection, write htaccess
 *            5 - send test email
 * form     : The name of the form preferences that were submitted.
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1));
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

// in ajax mode only return simple text on error
if ($getMode === 1) {
    $gMessage->showHtmlTextOnly(true);

    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }
}

// only administrators are allowed to edit organization preferences or create new organizations
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

function getTemplateFileName($folder, $templateName)
{
    // get all files from the folder
    $files = array_keys(FileSystemUtils::getDirectoryContent($folder, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
    $templateFileName = '';

    foreach ($files as $fileName) {
        if ($templateName === ucfirst(preg_replace('/[_-]/', ' ', str_replace(array('.tpl', '.html', '.txt'), '', $fileName)))) {
            $templateFileName = $fileName;
        }
    }
    return $templateFileName;
}

switch ($getMode) {
    case 1:
        $checkboxes = array();

        try {
            // first check the fields of the submitted form
            switch ($getForm) {
                case 'common':
                    $checkboxes = array(
                        'system_cookie_note', 'enable_rss',
                        'system_search_similar', 'system_js_editor_enabled', 'system_browser_update_check'
                    );

                    if (!StringUtils::strIsValidFolderName($_POST['theme'])
                    || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $_POST['theme'] . '/index.html')) {
                        $gMessage->show($gL10n->get('ORG_INVALID_THEME'));
                        // => EXIT
                    }
                    if ($_POST['system_url_imprint'] !== '' && !StringUtils::strValidCharacters($_POST['system_url_imprint'], 'url')) {
                        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_IMPRINT'))));
                        // => EXIT
                    }
                    if ($_POST['system_url_data_protection'] !== '' && !StringUtils::strValidCharacters($_POST['system_url_data_protection'], 'url')) {
                        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_DATA_PROTECTION'))));
                        // => EXIT
                    }
                    break;

                case 'security':
                    $checkboxes = array(
                        'enable_auto_login', 'enable_password_recovery'
                    );

                    if (!is_numeric($_POST['logout_minutes']) || $_POST['logout_minutes'] <= 0) {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_AUTOMATIC_LOGOUT_AFTER'))));
                        // => EXIT
                    }

                    if (!isset($_POST['enable_auto_login']) && $gSettingsManager->getBool('enable_auto_login')) {
                        // if auto login was deactivated than delete all saved logins
                        $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                        $gDb->queryPrepared($sql);
                    }
                    break;

                case 'organization':
                    $checkboxes = array('system_organization_select');

                    if ($_POST['org_longname'] === '') {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
                        // => EXIT
                    }

                    if ($_POST['email_administrator'] === '') {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_EMAIL_ADMINISTRATOR'))));
                        // => EXIT
                    } else {
                        if (!StringUtils::strValidCharacters($_POST['email_administrator'], 'email')) {
                            $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL_ADMINISTRATOR'))));
                            // => EXIT
                        }
                    }
                    break;

                case 'regional_settings':
                    if (!StringUtils::strIsValidFolderName($_POST['system_language'])
                    || !is_file(ADMIDIO_PATH . FOLDER_LANGUAGES . '/' . $_POST['system_language'] . '.xml')) {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_LANGUAGE'))));
                        // => EXIT
                    }

                    if ($_POST['system_date'] === '') {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_DATE_FORMAT'))));
                        // => EXIT
                    }

                    if ($_POST['system_time'] === '') {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_TIME_FORMAT'))));
                        // => EXIT
                    }
                    break;

                case 'registration':
                    $checkboxes = array('registration_enable_module', 'enable_registration_captcha', 'registration_adopt_all_data', 'enable_registration_admin_mail');
                    break;

                case 'email_dispatch':
                    $checkboxes = array('mail_into_to', 'mail_smtp_auth');

                    if ($_POST['mail_sendmail_address'] !== '') {
                        if (!StringUtils::strValidCharacters($_POST['mail_sendmail_address'], 'email')) {
                            $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_SENDER_EMAIL'))));
                            // => EXIT
                        }
                    }
                    break;

                case 'system_notification':
                    $checkboxes = array('system_notifications_enabled', 'system_notifications_new_entries', 'system_notifications_profile_changes');
                    break;

                case 'captcha':
                    break;

                case 'announcements':
                    break;

                case 'user_management':
                    $checkboxes = array('members_show_all_users', 'members_enable_user_relations');
                    break;

                case 'documents-files':
                    $checkboxes = array('documents_files_enable_module');
                    break;

                case 'guestbook':
                    $checkboxes = array('enable_guestbook_captcha', 'enable_gbook_comments4all',
                                        'enable_intial_comments_loading');
                    break;

                case 'ecards':
                    $checkboxes = array('enable_ecard_module');

                    // get real filename of the template file
                    if ($_POST['ecard_template'] !== $gSettingsManager->getString('ecard_template')) {
                        $_POST['ecard_template'] = getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', $_POST['ecard_template']);
                    }
                    break;

                case 'groups-roles':
                    $checkboxes = array('groups_roles_enable_module');
                    break;

                case 'category-report':
                    $checkboxes = array('category_report_enable_module');
                    break;

                case 'messages':
                    $checkboxes = array('enable_mail_module', 'enable_pm_module', 'enable_mail_captcha',
                                        'mail_send_to_all_addresses', 'mail_html_registered_users', 'mail_show_former', 'mail_save_attachments');

                    // get real filename of the template file
                    if ($_POST['mail_template'] !== $gSettingsManager->getString('mail_template')) {
                        $_POST['mail_template'] = getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates', $_POST['mail_template']);
                    }
                    break;

                case 'photos':
                    $checkboxes = array('photo_download_enabled', 'photo_keep_original');
                    break;

                case 'profile':
                    $checkboxes = array('profile_log_edit_fields', 'profile_show_map_link', 'profile_show_roles',
                                        'profile_show_former_roles', 'profile_show_extern_roles');
                    break;

                case 'events':
                    $checkboxes = array('enable_dates_ical', 'dates_show_map_link', 'dates_show_rooms', 'dates_save_all_confirmations', 'dates_may_take_part');
                    break;

                case 'links':
                    if (!is_numeric($_POST['weblinks_redirect_seconds']) || $_POST['weblinks_redirect_seconds'] < 0) {
                        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_DISPLAY_REDIRECT'))));
                        // => EXIT
                    }
                    break;

                default:
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                    // => EXIT
            }
        } catch (AdmException $e) {
            $e->showText();
            // => EXIT
        }
        // check every checkbox if a value was committed
        // if no value is found then set 0 because 0 will not be committed in a html checkbox element
        foreach ($checkboxes as $value) {
            if (!isset($_POST[$value]) || $_POST[$value] != 1) {
                $_POST[$value] = 0;
            }
        }

        // then update the database with the new values

        foreach ($_POST as $key => $value) { // TODO possible security issue
            // Sort out elements that are not stored in adm_preferences here
            if (!in_array($key, array('save', 'admidio-csrf-token'))) {
                if (str_starts_with($key, 'org_')) {
                    $gCurrentOrganization->setValue($key, $value);
                } elseif (str_starts_with($key, 'SYSMAIL_')) {
                    $text = new TableText($gDb);
                    $text->readDataByColumns(array('txt_org_id' => $gCurrentOrgId, 'txt_name' => $key));
                    $text->setValue('txt_text', $value);
                    $text->save();
                } elseif ($key === 'enable_auto_login' && $value == 0 && $gSettingsManager->getBool('enable_auto_login')) {
                    // if deactivate auto login than delete all saved logins
                    $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                    $gDb->queryPrepared($sql);
                    $gSettingsManager->set($key, $value);
                } else {
                    $gSettingsManager->set($key, $value);
                }
            }
        }

        // now save all data
        $gCurrentOrganization->save();

        // refresh language if necessary
        if ($gL10n->getLanguage() !== $gSettingsManager->getString('system_language')) {
            $gL10n->setLanguage($gSettingsManager->getString('system_language'));
        }

        // clean up
        $gCurrentSession->reloadAllSessions();

        echo 'success';
        break;

    // show welcome dialog for new organization
    case 2:
        if (isset($_SESSION['add_organization_request'])) {
            $formValues = $_SESSION['add_organization_request'];
            unset($_SESSION['add_organization_request']);
        } else {
            $formValues['orgaShortName'] = '';
            $formValues['orgaLongName']  = '';
            $formValues['orgaEmail']     = '';
        }

        $headline = $gL10n->get('INS_ADD_ORGANIZATION');

        // create html page object
        $page = new HtmlPage('admidio-new-organization', $headline);

        // add current url to navigation stack
        $gNavigation->addUrl(CURRENT_URL, $headline);

        $page->addHtml('<p class="lead">'.$gL10n->get('ORG_NEW_ORGANIZATION_DESC').'</p>');

        // show form
        $form = new HtmlForm('add_new_organization_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences_function.php', array('mode' => '3')), $page);
        $form->addInput(
            'orgaShortName',
            $gL10n->get('SYS_NAME_ABBREVIATION'),
            $formValues['orgaShortName'],
            array('maxLength' => 10, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
        );
        $form->addInput(
            'orgaLongName',
            $gL10n->get('SYS_NAME'),
            $formValues['orgaLongName'],
            array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addInput(
            'orgaEmail',
            $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
            $formValues['orgaEmail'],
            array('type' => 'email', 'maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addSubmitButton(
            'btn_forward',
            $gL10n->get('INS_SET_UP_ORGANIZATION'),
            array('icon' => 'fa-wrench', 'class' => ' offset-sm-3')
        );

        // add form to html page and show page
        $page->addHtml($form->show());
        $page->show();
        break;

    // Create basic data for new organization in database
    case 3:
        $_SESSION['add_organization_request'] = $_POST;

        try {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
        } catch (AdmException $exception) {
            $exception->showHtml();
            // => EXIT
        }

        // form fields are not filled
        if ($_POST['orgaShortName'] === '' || $_POST['orgaLongName'] === '') {
            $gMessage->show($gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'));
            // => EXIT
        }

        // check if orga shortname exists
        $organization = new Organization($gDb, $_POST['orgaShortName']);
        if ($organization->getValue('org_id') > 0) {
            $gMessage->show($gL10n->get('INS_ORGA_SHORTNAME_EXISTS', array($_POST['orgaShortName'])));
            // => EXIT
        }

        // allow only letters, numbers and special characters like .-_+@
        if (!StringUtils::strValidCharacters($_POST['orgaShortName'], 'noSpecialChar')) {
            $gMessage->show($gL10n->get('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION')));
            // => EXIT
        }

        // set execution time to 2 minutes because we have a lot to do
        PhpIniUtils::startNewExecutionTimeLimit(120);

        $gDb->startTransaction();

        // create new organization
        $newOrganization = new Organization($gDb, $_POST['orgaShortName']);
        $newOrganization->setValue('org_longname', $_POST['orgaLongName']);
        $newOrganization->setValue('org_shortname', $_POST['orgaShortName']);
        $newOrganization->setValue('org_homepage', ADMIDIO_URL);
        $newOrganization->save();

        // write all preferences from preferences.php in table adm_preferences
        require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php');

        // set some specific preferences whose values came from user input of the installation wizard
        $defaultOrgPreferences['email_administrator'] = $_POST['orgaEmail'];
        $defaultOrgPreferences['system_language']     = $gSettingsManager->getString('system_language');

        // create all necessary data for this organization
        $settingsManager =& $newOrganization->getSettingsManager();
        $settingsManager->setMulti($defaultOrgPreferences, false);
        $newOrganization->createBasicData($gCurrentUserId);

        // now refresh the session organization object because of the new organization
        $currentOrganizationId = $gCurrentOrgId;
        $gCurrentOrganization = new Organization($gDb, $currentOrganizationId);

        // if installation of second organization than show organization select at login
        if ($gCurrentOrganization->countAllRecords() === 2) {
            $sql = 'UPDATE '.TBL_PREFERENCES.'
                       SET prf_value = 1
                     WHERE prf_name = \'system_organization_select\'';
            $gDb->queryPrepared($sql);
        }

        $gDb->endTransaction();

        // create html page object
        $page = new HtmlPage('admidio-new-organization-successful', $gL10n->get('INS_SETUP_WAS_SUCCESSFUL'));

        $page->addHtml('<p class="lead">'.$gL10n->get('ORG_ORGANIZATION_SUCCESSFULLY_ADDED', array($_POST['orgaLongName'])).'</p>');

        // show form
        $form = new HtmlForm('add_new_organization_form', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', $page);
        $form->addSubmitButton('btn_forward', $gL10n->get('SYS_NEXT'), array('icon' => 'fa-arrow-circle-right'));

        // add form to html page and show page
        $page->addHtml($form->show());
        $page->show();

        // clean up
        unset($_SESSION['add_organization_request']);
        break;

    // set directory protection, write htaccess
    case 4:
        if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess')) {
            echo $gL10n->get('SYS_ON');
            return;
        }

        // create ".htaccess" file for folder "adm_my_files"
        $htaccess = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);
        if ($htaccess->protectFolder()) {
            echo $gL10n->get('SYS_ON');
            return;
        }

        $gLogger->warning('htaccess file could not be created!');

        echo $gL10n->get('SYS_OFF');
        break;

    // send test email
    case 5:
        $debugOutput = '';

        $email = new Email();
        $email->setDebugMode(true);

        if ($gSettingsManager->getBool('mail_html_registered_users')) {
            $email->setHtmlMail();
        }

        // set email data
        $email->setSender($gSettingsManager->getString('email_administrator'), $gL10n->get('SYS_ADMINISTRATOR'));
        $email->addRecipientsByUserId($gCurrentUserId);
        $email->setSubject($gL10n->get('SYS_EMAIL_FUNCTION_TEST', array($gCurrentOrganization->getValue('org_longname'))));
        $email->setTemplateText(
            $gL10n->get('SYS_EMAIL_FUNCTION_TEST_CONTENT', array($gCurrentOrganization->getValue('org_homepage'), $gCurrentOrganization->getValue('org_longname'))),
            $gCurrentUser->getValue('FIRSTNAME') . ' ' . $gCurrentUser->getValue('LASTNAME'),
            $gCurrentUser->getValue('EMAIL'),
            $gCurrentUser->getValue('usr_uuid'),
            $gL10n->get('SYS_ADMINISTRATOR')
        );

        // finally send the mail
        $sendResult = $email->sendEmail();

        if (isset($GLOBALS['phpmailer_output_debug'])) {
            $debugOutput .= '<br /><br /><h3>' . $gL10n->get('SYS_DEBUG_OUTPUT') . '</h3>' . $GLOBALS['phpmailer_output_debug'];
        }

        // message if send/save is OK
        if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
            $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'email_dispatch')));
            $gMessage->show($gL10n->get('SYS_EMAIL_SEND') . $debugOutput);
        // => EXIT
        } else {
            $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)) . $debugOutput);
            // => EXIT
        }
        break;
}
