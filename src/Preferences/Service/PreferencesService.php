<?php

namespace Admidio\Preferences\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Htaccess;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Email;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the preferences module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PreferencesService
{
    /**
     * Function to check an update
     * @param string $currentVersion
     * @param string $checkStableVersion
     * @param string $checkBetaVersion
     * @param string $betaRelease
     * @param string $betaFlag
     * @return int
     */
    function checkVersion(string $currentVersion, string $checkStableVersion, string $checkBetaVersion, string $betaRelease, string $betaFlag): int
    {
        // Update state (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version)
        $update = 0;

        // Zunächst auf stabile Version prüfen
        if (version_compare($checkStableVersion, $currentVersion, '>')) {
            $update = 1;
        }

        // Check for beta version now
        $status = version_compare($checkBetaVersion, $currentVersion);
        if ($status === 1 || ($status === 0 && version_compare($betaRelease, $betaFlag, '>'))) {
            if ($update === 1) {
                $update = 3;
            } else {
                $update = 2;
            }
        }

        return $update;
    }

    /**
     * Read all file names of a folder and return an array where the file names are the keys and a readable
     * version of the file names are the values.
     * @param string $folder Server path with folder name of whom the files should be read.
     * @return array<int,string> Array with all file names of the given folder.
     */
    static function getArrayFileNames(string $folder): array
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
     * @param string $folder
     * @param string $templateName
     * @return string
     */
    static function getTemplateFileName(string $folder, string $templateName): string
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

    /**
     * Function to determine the update version
     * @param string $updateInfo
     * @param string $search
     * @return string
     */
    function getUpdateVersion(string $updateInfo, string $search): string
    {
        // Variablen festlegen
        $i = 0;
        $pointer = '';
        $updateVersion = '';
        $currentVersionStart = strpos($updateInfo, $search);
        $adding = strlen($search) - 1;

        // Version auslesen
        while ($pointer !== "\n") {
            ++$i;
            $updateVersion .= $pointer;
            $pointer = $updateInfo[$currentVersionStart + $adding + $i];
        }

        return trim($updateVersion, "\n\r");
    }

    /**
     * check availability of update information and if connected
     * read available Admidio versions from server (text file)
     * @return string Returns the html of the update check
     * @throws Exception
     */
    function showUpdateInfo(): string
    {
        global $gL10n;
        $html = '';

        // check availability of update information and if connected
        // read available Admidio versions from server (text file)
        // First select the method (CURL preferred)
        $updateInfoUrl = ADMIDIO_HOMEPAGE . 'update.txt';
        if (@file_get_contents($updateInfoUrl) === false) {
            // Admidio Versionen nicht auslesbar
            $stableVersion = 'n/a';
            $betaVersion = 'n/a';
            $betaRelease = '';

            $versionUpdate = 99;
        } else {
            $updateInfo = file_get_contents($updateInfoUrl);

            // Admidio versions passed from server
            $stableVersion = $this->getUpdateVersion($updateInfo, 'Version=');
            $betaVersion = $this->getUpdateVersion($updateInfo, 'Beta-Version=');
            $betaRelease = $this->getUpdateVersion($updateInfo, 'Beta-Release=');

            // No stable version available (actually impossible)
            if ($stableVersion === '') {
                $stableVersion = 'n/a';
            }

            // No beat version available
            if ($betaVersion === '') {
                $betaVersion = 'n/a';
                $betaRelease = '';
            }

            // check for update
            $versionUpdate = $this->checkVersion(ADMIDIO_VERSION, $stableVersion, $betaVersion, $betaRelease, ADMIDIO_VERSION_BETA);
        }

        // Only continues in display mode, otherwise the current update state can be
        // queried in the $versionUpdate variable.
        // $versionUpdate (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version, 99 = No connection)
        // show update result
        if ($versionUpdate === 1) {
            $versionsText = $gL10n->get('SYS_NEW_VERSION_AVAILABLE');
        } elseif ($versionUpdate === 2) {
            $versionsText = $gL10n->get('SYS_NEW_BETA_AVAILABLE');
        } elseif ($versionUpdate === 3) {
            $versionsText = $gL10n->get('SYS_NEW_BOTH_AVAILABLE');
        } elseif ($versionUpdate === 99) {
            $admidioLink = '<a href="' . ADMIDIO_HOMEPAGE . 'download.php" target="_blank">Admidio</a>';
            $versionsText = $gL10n->get('SYS_CONNECTION_ERROR', array($admidioLink));
        } else {
            $versionsTextBeta = '';
            if (ADMIDIO_VERSION_BETA > 0) {
                $versionsTextBeta = 'Beta ';
            }

            $versionsText = $gL10n->get('SYS_USING_CURRENT_VERSION', array($versionsTextBeta));
        }

        $html .= '
        <p>' . $gL10n->get('SYS_INSTALLED') . ':&nbsp;' . ADMIDIO_VERSION_TEXT . '</p>
        <p>' . $gL10n->get('SYS_AVAILABLE') . ':&nbsp;
            <a href="' . ADMIDIO_HOMEPAGE . 'download.php" title="' . $gL10n->get('SYS_ADMIDIO_DOWNLOAD_PAGE') . '" target="_blank">' .
            '<i class="bi bi-link"></i>' . $stableVersion . '
            </a>
            <br />
            ' . $gL10n->get('SYS_AVAILABLE_BETA') . ': &nbsp;';

        if ($versionUpdate !== 99 && $betaVersion !== 'n/a') {
            $html .= '
                <a href="' . ADMIDIO_HOMEPAGE . 'download.php" title="' . $gL10n->get('SYS_ADMIDIO_DOWNLOAD_PAGE') . '" target="_blank">' .
                '<i class="bi bi-link"></i>' . $betaVersion . ' Beta ' . $betaRelease . '
                </a>';
        } else {
            $html .= $betaVersion;
        }
        $html .= '
        </p>
        <strong>' . $versionsText . '</strong>';
        return $html;
    }

    /**
     * Save all form data of the panel to the database.
     * @param string $panel Name of the panel for which the data should be saved.
     * @param array $formData All form data of the panel.
     * @return void
     * @throws Exception
     */
    public function save(string $panel, array $formData)
    {
        global $gL10n, $gSettingsManager, $gCurrentSession, $gDb, $gCurrentOrgId;

        // check form field input and sanitized it from malicious content
        $preferencesForm = $gCurrentSession->getFormObject($formData['adm_csrf_token']);
        $formValues = $preferencesForm->validate($formData);

        // first check the fields of the submitted form
        switch ($panel) {
            case 'Common':
                if (!StringUtils::strIsValidFolderName($formData['theme'])
                    || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $formData['theme'] . '/index.html')) {
                    throw new Exception('ORG_INVALID_THEME');
                }
                break;

            case 'Security':
                if (!isset($formData['enable_auto_login']) && $gSettingsManager->getBool('enable_auto_login')) {
                    // if auto login was deactivated than delete all saved logins
                    $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                    $gDb->queryPrepared($sql);
                }
                break;

            case 'RegionalSettings':
                if (!StringUtils::strIsValidFolderName($formData['system_language'])
                    || !is_file(ADMIDIO_PATH . FOLDER_LANGUAGES . '/' . $formData['system_language'] . '.xml')) {
                    throw new Exception('SYS_FIELD_EMPTY', array('SYS_LANGUAGE'));
                }
                break;

            case 'Messages':
                // get real filename of the template file
                if ($formData['mail_template'] !== $gSettingsManager->getString('mail_template')) {
                    $formValues['mail_template'] = $this->getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates', $formData['mail_template']);
                }
                break;

            case 'Photos':
                // get real filename of the template file
                if ($formData['photo_ecard_template'] !== $gSettingsManager->getString('photo_ecard_template')) {
                    $formValues['photo_ecard_template'] = $this->getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', $formData['photo_ecard_template']);
                }
                break;

        }

        // then update the database with the new values

        foreach ($formValues as $key => $value) {
            // Sort out elements that are not stored in adm_preferences here
            if (!in_array($key, array('save', 'adm_csrf_token'))) {
                if (str_starts_with($key, 'SYSMAIL_')) {
                    $text = new Text($gDb);
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
    }

    /**
     * Sends a test email to the email address of the organization.
     * @return bool Returns **true** if the email could be sent successfully otherwise **false**.
     * @throws Exception
     */
    public function sendTestEmail(): bool
    {
        global $gSettingsManager, $gCurrentOrganization, $gCurrentUser, $gL10n;

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
            $gCurrentUser->getValue('FIRSTNAME') . ' PreferencesService.php' . $gCurrentUser->getValue('LASTNAME'),
            $gCurrentUser->getValue('EMAIL'),
            $gCurrentUser->getValue('usr_uuid'),
            $gL10n->get('SYS_ADMINISTRATOR')
        );

        // finally send the mail
        return $email->sendEmail();
    }

    /**
     * Check if the data folder adm_my_files is protected through a htaccess file. If it's not
     * protected the function tries to create a htaccess file.
     * @return bool Returns **true** if the folder is protected otherwise **false**
     */
    public function setHtaccessProtection(): bool
    {
        global $gLogger;

        if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess')) {
            return true;
        }

        // create ".htaccess" file for folder "adm_my_files"
        $htaccess = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);
        if ($htaccess->protectFolder()) {
            return true;
        }

        $gLogger->warning('htaccess file could not be created!');
        return false;
    }
}
