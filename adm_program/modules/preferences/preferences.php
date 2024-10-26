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
 * mode     : html           - (default) Show page with all preferences panels
 *            html_form      - Returns the html of the requested form
 *            save           - Save organization preferences
 *            new_org_dialog - show welcome dialog for new organization
 *            new_org_create - Create basic data for new organization in database
 *            new_org_create_success - Show success dialog if new organization was created
 *            htaccess       - set directory protection, write htaccess
 *            test_email     - send test email
 *            backup         - create backup of Admidio database
 * panel    : The name of the preferences panel that should be shown or saved.
 ***********************************************************************************************
 */
use Admidio\Exception;
use Admidio\UserInterface\Form;
use Admidio\UserInterface\Preferences;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'html',
            'validValues' => array('html', 'html_form', 'save', 'new_org_dialog', 'new_org_create', 'new_org_create_success', 'htaccess', 'test_email', 'backup')
        ));
    $getPanel = admFuncVariableIsValid($_GET, 'panel', 'string');

    // only administrators are allowed to view, edit organization preferences or create new organizations
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    /**
     * @param string $folder
     * @param string $templateName
     * @return string
     */
    function getTemplateFileName(string $folder, string $templateName): string
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
        case 'html':
            $headline = $gL10n->get('SYS_SETTINGS');

            if ($getPanel === '') {
                $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-gear-fill');
            }
            // create html page object
            $page = new Preferences('admidio-preferences', $headline);

            if ($getPanel !== '') {
                $page->setPanelToShow($getPanel);
                // add current url to navigation stack
                $gNavigation->addUrl(CURRENT_URL, $headline);
            }

            $page->show();
            break;
        case 'save':
            // check form field input and sanitized it from malicious content
            $preferencesForm = $gCurrentSession->getFormObject($_POST['admidio-csrf-token']);
            $formValues = $preferencesForm->validate($_POST);

            // first check the fields of the submitted form
            switch ($getPanel) {
                case 'Common':
                    if (!StringUtils::strIsValidFolderName($_POST['theme'])
                        || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $_POST['theme'] . '/index.html')) {
                        throw new Exception('ORG_INVALID_THEME');
                    }
                    break;

                case 'Security':
                    if (!isset($_POST['enable_auto_login']) && $gSettingsManager->getBool('enable_auto_login')) {
                        // if auto login was deactivated than delete all saved logins
                        $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                        $gDb->queryPrepared($sql);
                    }
                    break;

                case 'RegionalSettings':
                    if (!StringUtils::strIsValidFolderName($_POST['system_language'])
                        || !is_file(ADMIDIO_PATH . FOLDER_LANGUAGES . '/' . $_POST['system_language'] . '.xml')) {
                        throw new Exception('SYS_FIELD_EMPTY', array('SYS_LANGUAGE'));
                    }
                    break;

                case 'Messages':
                    // get real filename of the template file
                    if ($_POST['mail_template'] !== $gSettingsManager->getString('mail_template')) {
                        $formValues['mail_template'] = getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates', $_POST['mail_template']);
                    }
                    break;

                case 'Photos':
                    // get real filename of the template file
                    if ($_POST['photo_ecard_template'] !== $gSettingsManager->getString('photo_ecard_template')) {
                        $formValues['photo_ecard_template'] = getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', $_POST['photo_ecard_template']);
                    }
                    break;
            }

            // then update the database with the new values

            foreach ($formValues as $key => $value) {
                // Sort out elements that are not stored in adm_preferences here
                if (!in_array($key, array('save', 'admidio-csrf-token'))) {
                    if (str_starts_with($key, 'SYSMAIL_')) {
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

            // refresh language if necessary
            if ($gL10n->getLanguage() !== $gSettingsManager->getString('system_language')) {
                $gL10n->setLanguage($gSettingsManager->getString('system_language'));
            }

            // clean up
            $gCurrentSession->reloadAllSessions();

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA')));
            break;

        // Returns the html of the requested form
        case 'html_form':
            $preferencesUI = new Preferences('preferencesForm');
            $methodName = 'create' . $getPanel . 'Form';
            echo $preferencesUI->{$methodName}();
            break;

        // show welcome dialog for new organization
        case 'new_org_dialog':
            $headline = $gL10n->get('SYS_ADD_ORGANIZATION');

            // add current url to navigation stack
            $gNavigation->addUrl(CURRENT_URL, $headline);

            // create html page object
            $page = new HtmlPage('admidio-new-organization', $headline);

            // show form
            $form = new Form(
                'newOrganizationForm',
                'modules/organizations.new.tpl',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php', array('mode' => 'new_org_create')),
                $page
            );
            $form->addInput(
                'orgaShortName',
                $gL10n->get('SYS_NAME_ABBREVIATION'),
                '',
                array('maxLength' => 10, 'property' => Form::FIELD_REQUIRED, 'class' => 'form-control-small')
            );
            $form->addInput(
                'orgaLongName',
                $gL10n->get('SYS_NAME'),
                '',
                array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED)
            );
            $form->addInput(
                'orgaEmail',
                $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
                '',
                array('type' => 'email', 'maxLength' => 254, 'property' => Form::FIELD_REQUIRED)
            );
            $form->addSubmitButton(
                'btn_forward',
                $gL10n->get('INS_SET_UP_ORGANIZATION'),
                array('icon' => 'bi-wrench')
            );

            $form->addToHtmlPage();
            $gCurrentSession->addFormObject($form);
            $page->show();
            break;

        // Create basic data for new organization in database
        case 'new_org_create':
            // check form field input and sanitized it from malicious content
            $newOrganizationForm = $gCurrentSession->getFormObject($_POST['admidio-csrf-token']);
            $formValues = $newOrganizationForm->validate($_POST);

            // check if organization shortname exists
            $organization = new Organization($gDb, $formValues['orgaShortName']);
            if ($organization->getValue('org_id') > 0) {
                throw new Exception('INS_ORGA_SHORTNAME_EXISTS', array($formValues['orgaShortName']));
            }

            // allow only letters, numbers and special characters like .-_+@
            if (!StringUtils::strValidCharacters($formValues['orgaShortName'], 'noSpecialChar')) {
                throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION'));
            }

            // set execution time to 2 minutes because we have a lot to do
            PhpIniUtils::startNewExecutionTimeLimit(120);

            $gDb->startTransaction();

            // create new organization
            $_SESSION['orgaLongName'] = $formValues['orgaLongName'];
            $newOrganization = new Organization($gDb, $formValues['orgaShortName']);
            $newOrganization->setValue('org_longname', $formValues['orgaLongName']);
            $newOrganization->setValue('org_shortname', $formValues['orgaShortName']);
            $newOrganization->setValue('org_homepage', ADMIDIO_URL);
            $newOrganization->setValue('org_email_administrator', $formValues['orgaEmail']);
            $newOrganization->setValue('org_show_org_select', true);
            $newOrganization->setValue('org_org_id_parent', $gCurrentOrgId);
            $newOrganization->save();

            // write all preferences from preferences.php in table adm_preferences
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php');

            // set some specific preferences whose values came from user input of the installation wizard
            $defaultOrgPreferences['system_language'] = $gSettingsManager->getString('system_language');

            // create all necessary data for this organization
            $settingsManager =& $newOrganization->getSettingsManager();
            $settingsManager->setMulti($defaultOrgPreferences, false);
            $newOrganization->createBasicData($gCurrentUserId);

            // now refresh the session organization object because of the new organization
            $currentOrganizationId = $gCurrentOrgId;
            $gCurrentOrganization = new Organization($gDb, $currentOrganizationId);

            // if installation of second organization than show organization select at login
            if ($gCurrentOrganization->countAllRecords() === 2) {
                $gCurrentOrganization->setValue('org_show_org_select', true);
                $gCurrentOrganization->save();
            }

            $gDb->endTransaction();
            $gNavigation->deleteLastUrl();

            echo json_encode(array(
                'status' => 'success',
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php', array('mode' => 'new_org_create_success'))
            ));
            break;

        // Show success dialog if new organization was created
        case 'new_org_create_success':
            $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_MODULES . '/organizations/organizations.php');
            $gMessage->show($gL10n->get('ORG_ORGANIZATION_SUCCESSFULLY_ADDED', array($_SESSION['orgaLongName'])), $gL10n->get('INS_SETUP_WAS_SUCCESSFUL'));
            break;

        // set directory protection, write htaccess
        case 'htaccess':
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
        case 'test_email':
            $debugOutput = '';

            $email = new Email();
            $email->setDebugMode(true);

            if ($gSettingsManager->getBool('mail_html_registered_users')) {
                $email->setHtmlMail();
            }

            // set email data
            $email->setSender($gCurrentOrganization->getValue('org_email_administrator'), $gL10n->get('SYS_ADMINISTRATOR'));
            $email->addRecipientsByUser($gCurrentUser->getValue('usr_uuid'));
            $email->setSubject($gL10n->get('SYS_EMAIL_FUNCTION_TEST', array($gCurrentOrganization->getValue('org_longname', 'database'))));
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
                $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php', array('show_option' => 'email_dispatch')));
                $gMessage->show($gL10n->get('SYS_EMAIL_SEND') . $debugOutput);
                // => EXIT
            } else {
                $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)) . $debugOutput);
                // => EXIT
            }
            break;

        // create backup of Admidio database
        case 'backup':
            // function not available for other databases except MySQL
            if (DB_ENGINE !== Database::PDO_ENGINE_MYSQL) {
                throw new Exception('SYS_MODULE_DISABLED');
            }

            $dump = new DatabaseDump($gDb);
            $dump->create('admidio_dump_' . $g_adm_db . '.sql.gzip');
            $dump->export();
            $dump->deleteDumpFile();
            break;
    }
} catch (Exception|Exception $exception) {
    if (in_array($getMode, array('save', 'new_org_create'))) {
        echo json_encode(array('status' => 'error', 'message' => $exception->getMessage()));
    } elseif ($getMode === 'html_form') {
        echo $exception->getMessage();
    } else {
        $gMessage->show($exception->getMessage());
    }
}
