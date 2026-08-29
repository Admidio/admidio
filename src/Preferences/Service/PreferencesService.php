<?php

namespace Admidio\Preferences\Service;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Htaccess;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Infrastructure\Language;
use Admidio\SSO\Service\OIDCService;

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
     * Registered presenter callbacks by component ID.
     * @var array<int, callable[]>
     */
    private static array $pluginPresenters = array();
    /**
     * Registered presenter callbacks by component ID.
     * @var array<int, callable[]>
     */
    private static array $overviewPluginPresenters = array();

    /**
     * Register a preferences presenter for a plugin.
     *
     * @param int $componentId   The component ID of the plugin.
     * @param callable $presenterCallback  A callable that renders the plugin's preferences panel.
     */
    public static function addPluginPreferencesPresenter(int $componentId, callable $presenterCallback): void
    {
        if (!isset(self::$pluginPresenters[$componentId])) {
            self::$pluginPresenters[$componentId] = array();
        }
        self::$pluginPresenters[$componentId][] = $presenterCallback;
    }

    /**
     * Get all registered presenter callbacks, grouped by component ID.
     *
     * @return array<int, callable[]>
     */
    public static function getPluginPresenters(): array
    {
        return array_merge(self::$overviewPluginPresenters, self::$pluginPresenters);
    }

    /**
     * Build the panel definitions for the "Plugins" tab.
     *
     * This method gathers metadata from each plugin and prepares
     * the structure used by the PreferencesPresenter to render the accordion.
     *
     * @return array<int, array{id:string, title:string, icon?:string, subcards?:bool}>
     * @throws Exception
     */
    public static function getPluginPanels(): array
    {
        $panels = array();

        foreach (self::$pluginPresenters as $comId => $callbacks) {
            // Retrieve plugin metadata by component ID (you may need to implement this lookup)
            $pluginManager = new PluginManager();
            $metadata = $pluginManager->getMetadataByComponentId($comId);

            $panels[] = array(
                'id'       => preg_replace('/\s+/', '_', preg_replace('/[^a-z0-9_ ]/', '', strtolower(Language::translateIfTranslationStrId($metadata['name'])))),
                'title'    => Language::translateIfTranslationStrId($metadata['name']),
                'icon'     => $metadata['icon'] ?? 'bi-puzzle',
                'subcards' => $metadata['hasSubcards'] ?? false,
            );
        }

        return $panels;
    }

    /**
     * Register a preferences presenter for a plugin.
     *
     * @param int $componentId   The component ID of the plugin.
     * @param callable $presenterCallback  A callable that renders the plugin's preferences panel.
     */
    public static function addOverviewPluginPreferencesPresenter(int $componentId, callable $presenterCallback): void
    {
        if (!isset(self::$overviewPluginPresenters[$componentId])) {
            self::$overviewPluginPresenters[$componentId] = array();
        }
        self::$overviewPluginPresenters[$componentId][] = $presenterCallback;
    }

    /**
     * Build the panel definitions for the "Plugins" tab.
     *
     * This method gathers metadata from each plugin and prepares
     * the structure used by the PreferencesPresenter to render the accordion.
     *
     * @return array<int, array{id:string, title:string, icon?:string, subcards?:bool}>
     * @throws Exception
     */
    public static function getOverviewPluginPanels(): array
    {
        $panels = array();

        foreach (self::$overviewPluginPresenters as $comId => $callbacks) {
            // Retrieve plugin metadata by component ID (you may need to implement this lookup)
            $pluginManager = new PluginManager();
            $metadata = $pluginManager->getMetadataByComponentId($comId);

            $panels[] = array(
                'id'       => preg_replace('/\s+/', '_', preg_replace('/[^a-z0-9_ ]/', '', strtolower(Language::translateIfTranslationStrId($metadata['name'])))),
                'title'    => Language::translateIfTranslationStrId($metadata['name']),
                'icon'     => $metadata['icon'] ?? 'bi-puzzle',
                'subcards' => $metadata['hasSubcards'] ?? false,
            );
        }

        return $panels;
    }

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
     * Parse the update information of the Admidio server into its key/value pairs.
     *
     * The file holds one "name=value" pair per line. Keys are compared exactly, so a new key can
     * be added to the file without colliding with the name of an existing one.
     *
     * @param string $updateInfo Content of the update information file.
     * @return array<string,string> Named array with the value of every key of the file.
     */
    private function parseUpdateInformation(string $updateInfo): array
    {
        // a UTF-8 BOM would otherwise become part of the first key
        if (str_starts_with($updateInfo, "\xEF\xBB\xBF")) {
            $updateInfo = substr($updateInfo, 3);
        }

        $information = array();

        foreach (preg_split('/\R/', $updateInfo) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);

            // the first occurrence wins, so a duplicate line cannot override the published value
            if ($name !== '' && !array_key_exists($name, $information)) {
                $information[$name] = trim($value);
            }
        }

        return $information;
    }

    /**
     * Read the available Admidio versions and determine the update state.
     *
     * A version that the update server does not provide is returned as an empty string. How an
     * unknown version should be presented is up to the caller.
     *
     * @return array{stableVersion:string,betaVersion:string,betaRelease:string,versionUpdate:int}
     */
    public function getUpdateInformation(): array
    {
        $updateInfoUrl = ADMIDIO_HOMEPAGE . 'update.txt';
        $updateInfo = @file_get_contents($updateInfoUrl);

        if ($updateInfo === false) {
            return array(
                'stableVersion' => '',
                'betaVersion' => '',
                'betaRelease' => '',
                'versionUpdate' => 99
            );
        }

        $information = $this->parseUpdateInformation($updateInfo);
        $stableVersion = $information['Version'] ?? '';
        $betaVersion = $information['Beta-Version'] ?? '';
        $betaRelease = $information['Beta-Release'] ?? '';

        return array(
            'stableVersion' => $stableVersion,
            'betaVersion' => $betaVersion,
            'betaRelease' => $betaRelease,
            'versionUpdate' => $this->checkVersion(
                ADMIDIO_VERSION,
                $stableVersion,
                $betaVersion,
                $betaRelease,
                ADMIDIO_VERSION_BETA
            )
        );
    }

    /**
     * check availability of update information and render it for the preferences UI.
     * @return string Returns the HTML of the update check
     * @throws Exception
     */
    function showUpdateInfo(): string
    {
        global $gL10n;
        $html = '';

        $updateInformation = $this->getUpdateInformation();
        $stableVersion = $updateInformation['stableVersion'];
        $betaVersion = $updateInformation['betaVersion'];
        $betaRelease = $updateInformation['betaRelease'];
        $versionUpdate = $updateInformation['versionUpdate'];

        /*
         * A version that the update server doesn't provide is displayed as "n/a". The values are
         * read from a file on the Admidio server and are encoded here, so that the HTML below
         * cannot be manipulated through that file.
         */
        $stableVersionText = SecurityUtils::encodeHTML(($stableVersion === '') ? 'n/a' : $stableVersion);
        $betaVersionText = SecurityUtils::encodeHTML(($betaVersion === '') ? 'n/a' : $betaVersion);
        $betaReleaseText = SecurityUtils::encodeHTML($betaRelease);

        // $versionUpdate (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version, 99 = No connection)
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
            <a class="icon-link" href="' . ADMIDIO_HOMEPAGE . 'download.php" title="' . $gL10n->get('SYS_ADMIDIO_DOWNLOAD_PAGE') . '" target="_blank">' .
            '<i class="bi bi-link"></i>' . $stableVersionText . '
            </a>
            <br />
            ' . $gL10n->get('SYS_AVAILABLE_BETA') . ': &nbsp;';

        if ($versionUpdate !== 99 && $betaVersion !== '') {
            $html .= '
                <a class="icon-link" href="' . ADMIDIO_HOMEPAGE . 'intern/adm_program/modules/announcements/announcements.php?cat_uuid=e2be424d-dd72-4c01-99ad-f8f91ec8830f" title="' . $gL10n->get('SYS_ADMIDIO_DOWNLOAD_PAGE') . '" target="_blank">' .
                '<i class="bi bi-link"></i>' . $betaVersionText . ' Beta ' . $betaReleaseText . '
                </a>';
        } else {
            $html .= $betaVersionText;
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
    public function save(string $panel, array $formData): void
    {
        global $gL10n, $gSettingsManager, $gCurrentSession, $gDb, $gCurrentOrgId;

        // check form field input and sanitized it from malicious content
        $preferencesForm = $gCurrentSession->getFormObject($formData['adm_csrf_token']);
        $formValues = $preferencesForm->validate($formData);

        // first check the fields of the submitted form
        switch ($panel) {
            case 'design':
                if (!StringUtils::strIsValidFolderName($formData['theme'])
                    || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $formData['theme'] . '/index.html')) {
                    throw new Exception('ORG_INVALID_THEME');
                }
                if (!empty($formData['theme_fallback'])) {
                    if (!StringUtils::strIsValidFolderName($formData['theme_fallback'])
                        || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $formData['theme_fallback'] . '/index.html')) {
                        throw new Exception('ORG_INVALID_THEME_FALLBACK');
                    }
                }
                break;

            case 'security':
                if (!isset($formData['enable_auto_login']) && $gSettingsManager->getBool('enable_auto_login')) {
                    // if auto login was deactivated than delete all saved logins
                    $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                    $gDb->queryPrepared($sql);
                }
                break;

            case 'regional_settings':
                if (!StringUtils::strIsValidFolderName($formData['system_language'])
                    || !is_file(ADMIDIO_PATH . FOLDER_LANGUAGES . '/' . $formData['system_language'] . '.xml')) {
                    throw new Exception('SYS_FIELD_EMPTY', array('SYS_LANGUAGE'));
                }
                break;

            case 'messages':
                // get real filename of the template file
                if ($formData['mail_template'] !== $gSettingsManager->getString('mail_template')) {
                    $formValues['mail_template'] = $this->getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates', $formData['mail_template']);
                }
                break;

            case 'photos':
                // get real filename of the template file
                if ($formData['photo_ecard_template'] !== $gSettingsManager->getString('photo_ecard_template')) {
                    $formValues['photo_ecard_template'] = $this->getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', $formData['photo_ecard_template']);
                }
                break;

            case 'changelog':
                // The form offers one checkbox per area, the preferences store one flag per
                // database table. An area that is still in its mixed state was not touched by the
                // user, so the flags of its tables have to be left exactly as they are.
                foreach (ChangelogService::getVisibleAreas() as $areaId => $area) {
                    $areaValue = $formValues['changelog_area_' . $areaId] ?? 'mixed';
                    unset($formValues['changelog_area_' . $areaId]);

                    if ($areaValue !== '1' && $areaValue !== '0') {
                        continue;
                    }

                    foreach ($area['tables'] as $tableName) {
                        $formValues['changelog_table_' . $tableName] = $areaValue;
                    }
                }
                break;

            case 'sso': 
                // empty issuerURL means "Use the default URL from the admidio installation's URL"
                $issuerURL = trim((string)($formValues['sso_oidc_issuer_url'] ?? ''));

                if ($issuerURL !== '') {
                    $issuerURL = rtrim($issuerURL, '/');
                }

                // Do not persist the installation-derived default. An empty setting
                // allows the issuer URL to follow later changes to ADMIDIO_URL.
                if ($issuerURL === OIDCService::getDefaultIssuerURL()) {
                    $issuerURL = '';
                }

                $formValues['sso_oidc_issuer_url'] = $issuerURL;
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
                } elseif (is_string($value) && str_starts_with($value, '["') && str_ends_with($value, '"]')) { // check if the value is a JSON array
                    // decode JSON array and save it as an array
                    $value = implode(',', json_decode($value, true));
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
     * @throws Exception|\PHPMailer\PHPMailer\Exception
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
        $email->setSender($gSettingsManager->getString('mail_sender_email'), $gSettingsManager->getString('mail_sender_name'));
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
