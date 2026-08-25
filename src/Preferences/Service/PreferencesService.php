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
use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\ValueObject\SettingsManager;

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
    private const CONFIG_DOCUMENT_SCHEMA = 'admidio-preferences';
    private const CONFIG_DOCUMENT_VERSION = 1;

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
        global $gSettingsManager, $gCurrentSession, $gDb, $gCurrentOrgId;

        // check form field input and sanitized it from malicious content
        $preferencesForm = $gCurrentSession->getFormObject($formData['adm_csrf_token']);
        $formValues = $preferencesForm->validate($formData);

        // Adapt presentation-specific values before canonical preference validation.
        switch ($panel) {
            case 'messages':
                // The select box uses a readable template name; store the real filename.
                if ($formData['mail_template'] !== $gSettingsManager->getString('mail_template')) {
                    $formValues['mail_template'] = $this->getTemplateFileName(
                        ADMIDIO_PATH . FOLDER_DATA . '/mail_templates',
                        $formData['mail_template']
                    );
                }
                break;

            case 'photos':
                // The select box uses a readable template name; store the real filename.
                if ($formData['photo_ecard_template'] !== $gSettingsManager->getString('photo_ecard_template')) {
                    $formValues['photo_ecard_template'] = $this->getTemplateFileName(
                        ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates',
                        $formData['photo_ecard_template']
                    );
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
        }

        // Separate the described preferences from the texts. They are normalized as one target
        // set so cross-preference rules see all changes from this panel at once.
        $corePreferences = array();

        foreach ($formValues as $key => $value) {
            if (in_array($key, array('save', 'adm_csrf_token'), true)) {
                continue;
            }

            if (str_starts_with($key, 'SYSMAIL_')) {
                $text = new Text($gDb);
                $text->readDataByColumns(array('txt_org_id' => $gCurrentOrgId, 'txt_name' => $key));
                $text->setValue('txt_text', $value);
                $text->save();
                continue;
            }

            if (is_string($value) && str_starts_with($value, '["') && str_ends_with($value, '"]')) {
                $value = implode(',', json_decode($value, true));
            }

            if (PreferenceDefinitions::isSupported($key)) {
                $corePreferences[$key] = $value;
                continue;
            }

            // Everything a module or a plugin registered is supported above, so what is left is
            // a preference nothing describes. It is stored as it is submitted.
            $gSettingsManager->set($key, $value);
        }

        if (count($corePreferences) > 0) {
            $this->persistCorePreferences(PreferenceDefinitions::normalizeValues($corePreferences));
        }

        // clean up
        $gCurrentSession->reloadAllSessions();
    }

    /**
     * Return the administrator-editable organization preferences with their schema metadata.
     *
     * @param bool $includeSecrets Include sensitive values such as the SMTP password.
     * @return array<string,array{value:string,type:string,sensitive:bool}>
     * @throws Exception
     */
    public function getEditablePreferences(bool $includeSecrets = false): array
    {
        global $gSettingsManager;

        $settings = $gSettingsManager->getAll(true);
        $result = array();

        foreach (PreferenceDefinitions::supportedNames() as $name) {
            if (!array_key_exists($name, $settings)) {
                continue;
            }

            $definition = PreferenceDefinitions::definition($name);
            if ($definition['sensitive'] && !$includeSecrets) {
                continue;
            }

            $result[$name] = array(
                'value' => (string)$settings[$name],
                'type' => $definition['type'],
                'sensitive' => $definition['sensitive']
            );
        }

        return $result;
    }

    /**
     * Read one administrator-editable preference.
     *
     * @return array{value:string,type:string,sensitive:bool}
     * @throws Exception
     */
    public function getEditablePreference(string $name, bool $includeSecrets = false): array
    {
        global $gSettingsManager;

        $definition = PreferenceDefinitions::definition($name);
        if ($definition['sensitive'] && !$includeSecrets) {
            return array(
                'value' => '********',
                'type' => $definition['type'],
                'sensitive' => true
            );
        }

        return array(
            'value' => $gSettingsManager->get($name, true),
            'type' => $definition['type'],
            'sensitive' => $definition['sensitive']
        );
    }

    /**
     * Validate and persist one administrator-editable organization preference.
     *
     * @throws Exception
     */
    public function setEditablePreference(string $name, mixed $value): string
    {
        $normalized = PreferenceDefinitions::normalize($name, $value);
        $this->persistCorePreferences(array($name => $normalized));
        return $normalized;
    }

    /**
     * Create the versioned configuration document used by headless export/import.
     * Sensitive settings are omitted unless explicitly requested.
     *
     * @return array<string,mixed>
     * @throws Exception
     */
    public function exportConfiguration(bool $includeSecrets = false): array
    {
        global $gCurrentOrganization;

        $preferences = array();
        foreach ($this->getEditablePreferences($includeSecrets) as $name => $entry) {
            $preferences[$name] = $entry['value'];
        }

        $document = array(
            'schema' => self::CONFIG_DOCUMENT_SCHEMA,
            'version' => self::CONFIG_DOCUMENT_VERSION,
            'admidioVersion' => defined('ADMIDIO_VERSION') ? ADMIDIO_VERSION : null,
            'secretsIncluded' => $includeSecrets,
            'preferences' => $preferences
        );

        if (isset($gCurrentOrganization) && is_object($gCurrentOrganization)) {
            $document['organization'] = array(
                'uuid' => (string)$gCurrentOrganization->getValue('org_uuid'),
                'shortName' => (string)$gCurrentOrganization->getValue('org_shortname')
            );
        }

        return $document;
    }

    /**
     * Validate a versioned configuration document without changing state.
     *
     * @param array<string,mixed> $document
     * @return array<string,string> Normalized preference values.
     */
    public function validateConfigurationImport(array $document, bool $includeSecrets = false): array
    {
        if (($document['schema'] ?? null) !== self::CONFIG_DOCUMENT_SCHEMA) {
            throw new \InvalidArgumentException('Configuration document has an unsupported schema.');
        }
        if (($document['version'] ?? null) !== self::CONFIG_DOCUMENT_VERSION) {
            throw new \InvalidArgumentException('Configuration document has an unsupported version.');
        }
        if (!isset($document['preferences']) || !is_array($document['preferences'])) {
            throw new \InvalidArgumentException('Configuration document must contain a preferences object.');
        }

        $values = array();
        foreach ($document['preferences'] as $name => $value) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Configuration preference names must be strings.');
            }
            if (PreferenceDefinitions::isSensitive($name) && !$includeSecrets) {
                throw new \InvalidArgumentException(
                    'Configuration document contains sensitive preference "' . $name
                    . '". Use --include-secrets to import it explicitly.'
                );
            }
            $values[$name] = $value;
        }

        $normalized = PreferenceDefinitions::normalizeValues($values);
        ksort($normalized);
        return $normalized;
    }

    /**
     * Validate and atomically import a versioned configuration document.
     *
     * @param array<string,mixed> $document
     * @return array<string,string> Normalized values that were applied.
     * @throws Exception
     */
    public function importConfiguration(array $document, bool $includeSecrets = false): array
    {
        $normalized = $this->validateConfigurationImport($document, $includeSecrets);
        $this->persistCorePreferences($normalized);
        return $normalized;
    }

    /**
     * Persist already-normalized core preferences and apply side effects shared by web and CLI.
     *
     * @param array<string,string> $normalized
     * @throws Exception
     */
    /**
     * Write the registry default of the named preferences into every organization.
     *
     * A preference exists for an organization as soon as it has a row, and it gets that row here.
     * An organization that already stores a value keeps it, so seeding the same names again is
     * what an update does when a new version brought new preferences.
     *
     * The names are always explicit: a definition that is registered in this request does not
     * necessarily belong to something that is installed, and only what is installed gets rows.
     *
     * @param array<int,string> $names The preferences to seed.
     * @throws Exception
     */
    public static function seedDefaults(array $names): void
    {
        global $gDb, $gSettingsManager;

        $defaults = array_intersect_key(PreferenceDefinitions::defaults(), array_flip($names));
        if ($defaults === array()) {
            return;
        }

        $statement = $gDb->queryPrepared('SELECT org_id FROM ' . TBL_ORGANIZATIONS);
        while ($organizationId = $statement->fetchColumn()) {
            $organization = new Organization($gDb, (int)$organizationId);
            $settingsManager =& $organization->getSettingsManager();
            // false: an organization that already decided about a preference is not overwritten.
            $settingsManager->setMulti($defaults, false);
        }

        if (isset($gSettingsManager) && $gSettingsManager instanceof SettingsManager) {
            $gSettingsManager->resetAll();
        }
    }

    /**
     * Remove the named preferences from every organization.
     *
     * The counterpart of seedDefaults(), for a module or plugin that is uninstalled. A preference
     * that an organization never stored is silently skipped.
     *
     * @param array<int,string> $names
     * @throws Exception
     */
    public static function removePreferences(array $names): void
    {
        global $gDb, $gSettingsManager;

        if ($names === array()) {
            return;
        }

        $statement = $gDb->queryPrepared('SELECT org_id FROM ' . TBL_ORGANIZATIONS);
        while ($organizationId = $statement->fetchColumn()) {
            $organization = new Organization($gDb, (int)$organizationId);
            $settingsManager =& $organization->getSettingsManager();
            foreach ($names as $name) {
                if ($settingsManager->has($name)) {
                    $settingsManager->del($name);
                }
            }
        }

        if (isset($gSettingsManager) && $gSettingsManager instanceof SettingsManager) {
            $gSettingsManager->resetAll();
        }
    }

    private function persistCorePreferences(array $normalized): void
    {
        global $gDb, $gL10n, $gSettingsManager;

        if (count($normalized) === 0) {
            return;
        }

        $disableAutoLogin = isset($normalized['enable_auto_login'])
            && $normalized['enable_auto_login'] === '0'
            && $gSettingsManager->getBool('enable_auto_login');

        $gDb->startTransaction();
        try {
            if ($disableAutoLogin) {
                $gDb->queryPrepared('DELETE FROM ' . TBL_AUTO_LOGIN);
            }

            $gSettingsManager->setMulti($normalized);
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            $gSettingsManager->resetAll();
            throw $exception;
        }

        if (isset($normalized['system_language'])
            && isset($gL10n)
            && $gL10n->getLanguage() !== $normalized['system_language']) {
            $gL10n->setLanguage($normalized['system_language']);
        }
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
