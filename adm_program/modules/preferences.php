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
 *            htaccess       - set directory protection, write htaccess
 *            test_email     - send test email
 *            backup         - create backup of Admidio database
 *            update_check   - Check for a new version of Admidio
 * panel    : The name of the preferences panel that should be shown or saved.
 ***********************************************************************************************
 */
use Admidio\Exception;
use Admidio\UserInterface\Preferences;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'html',
            'validValues' => array('html', 'html_form', 'save', 'htaccess', 'test_email', 'backup', 'update_check')
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
            $page = new Preferences('adm_preferences', $headline);

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
                $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('show_option' => 'email_dispatch')));
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

        case 'update_check':
            $preferences = new Admidio\Modules\Preferences();
            echo $preferences->showUpdateInfo();
            break;
    }
} catch (Exception $exception) {
    if (in_array($getMode, array('save', 'new_org_create'))) {
        echo json_encode(array('status' => 'error', 'message' => $exception->getMessage()));
    } elseif ($getMode === 'html_form') {
        echo $exception->getMessage();
    } else {
        $gMessage->show($exception->getMessage());
    }
}
