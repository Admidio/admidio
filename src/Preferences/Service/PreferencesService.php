<?php

namespace Admidio\Preferences\Service;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Backups;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Htaccess;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Email;
use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\KeyService;

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

    /** The automatic check for a new release runs at most twice a day. */
    public const UPDATE_CHECK_INTERVAL_SECONDS = 43200;

    /** How long a request waits for the update information of the Admidio server. */
    public const UPDATE_CHECK_TIMEOUT_SECONDS = 5;

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
        global $gSettingsManager;

        $updateInfo = $this->readUpdateInformation();

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

        // Every check keeps what it found, for the notice of an available update.
        if (isset($gSettingsManager) && $gSettingsManager->has('system_update_information')) {
            $gSettingsManager->set('system_update_information', json_encode(array(
                'stableVersion' => $stableVersion,
                'betaVersion' => $betaVersion,
                'betaRelease' => $betaRelease,
                'checkedAt' => time()
            )));
        }

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
     * Read the update information file of the Admidio server. A request of an administrator may
     * have to wait for it, so it gives up after UPDATE_CHECK_TIMEOUT_SECONDS.
     * @return string|false The content of the file, or **false** if it could not be read.
     */
    protected function readUpdateInformation(): string|false
    {
        $context = stream_context_create(array('http' => array('timeout' => self::UPDATE_CHECK_TIMEOUT_SECONDS)));

        return @file_get_contents(ADMIDIO_HOMEPAGE . 'update.txt', false, $context);
    }

    /**
     * Look for a new release of Admidio, but at most once every UPDATE_CHECK_INTERVAL_SECONDS, and
     * only in requests of administrators, so that no member waits for the server of Admidio. The
     * result is kept for the notice of an available update (getAvailableUpdate()).
     *
     * The time of the attempt is stored before the request is made, so that requests that arrive in
     * the meantime do not start a second one, and so that a server that cannot be reached is not
     * asked again on every page. The check can be switched off with the preference
     * system_update_check.
     * @return bool Returns **true** if the update information was requested.
     * @throws Exception
     */
    public function checkForUpdateIfDue(): bool
    {
        global $gSettingsManager, $gValidLogin, $gCurrentUser;

        // This runs on every request, also in an installation whose database does not have these
        // preferences yet.
        if (!$gSettingsManager->has('system_update_check') || !$gSettingsManager->getBool('system_update_check')
            || !$gSettingsManager->has('system_update_check_time')
            || !$gValidLogin || !$gCurrentUser->isAdministrator()) {
            return false;
        }

        // Read again, because another administrator or maintenance:run may have checked meanwhile.
        $now = time();
        if ($now - $gSettingsManager->getInt('system_update_check_time', true) < self::UPDATE_CHECK_INTERVAL_SECONDS) {
            return false;
        }

        $gSettingsManager->set('system_update_check_time', (string) $now);
        $this->getUpdateInformation();

        return true;
    }

    /**
     * The newer release of Admidio that the last check found, as it is shown to an administrator,
     * or an empty string. An installation of a stable release is only told about stable releases;
     * an installation of a beta also about newer betas. The stored versions are compared with the
     * installed one again, so that the notice ends with the update.
     * @return string
     */
    public function getAvailableUpdate(): string
    {
        global $gSettingsManager;

        if (!$gSettingsManager->has('system_update_check') || !$gSettingsManager->getBool('system_update_check')
            || !$gSettingsManager->has('system_update_information')) {
            return '';
        }

        // The session keeps the preferences of its organization, and the result may come from the
        // request of another administrator or from maintenance:run.
        $information = json_decode($gSettingsManager->getString('system_update_information', true), true);
        if (!is_array($information)) {
            return '';
        }

        $stableVersion = (string) ($information['stableVersion'] ?? '');
        $betaVersion = (string) ($information['betaVersion'] ?? '');
        $betaRelease = (string) ($information['betaRelease'] ?? '');
        $versionUpdate = $this->checkVersion(ADMIDIO_VERSION, $stableVersion, $betaVersion, $betaRelease, ADMIDIO_VERSION_BETA);

        if ($versionUpdate === 1 || $versionUpdate === 3) {
            return $stableVersion;
        }
        if ($versionUpdate === 2 && (int) ADMIDIO_VERSION_BETA > 0) {
            return $betaVersion . ' Beta ' . $betaRelease;
        }

        return '';
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

        // A release this installation is offered can be installed right from here.
        if ($versionUpdate !== 99 && $this->getAvailableUpdate() !== '') {
            $html .= '
        <p class="mt-2"><a class="btn btn-primary" href="' . ADMIDIO_URL . FOLDER_INSTALLATION . '/core-update/index.php">
            <i class="bi bi-arrow-repeat"></i>' . $gL10n->get('SYS_CORE_UPDATE_START') . '</a></p>';
        }

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

            case 'update_backup':
                // The directory and the password of the backups belong to the whole installation
                // and are only set in the first organization; elsewhere they are shown without
                // being editable.
                if (!Backups::areSettingsEditable()) {
                    unset($formValues['system_backup_path'], $formValues['system_update_dump_password'], $formValues['system_update_dump_password_remove']);
                    break;
                }
                // The password of the database backups is never shown, so an empty field keeps
                // the stored one; only the checkbox removes it.
                if (!empty($formValues['system_update_dump_password_remove'])) {
                    $formValues['system_update_dump_password'] = '';
                } elseif ((string) ($formValues['system_update_dump_password'] ?? '') === '') {
                    unset($formValues['system_update_dump_password']);
                }
                unset($formValues['system_update_dump_password_remove']);
                if (array_key_exists('system_backup_path', $formValues)) {
                    $formValues['system_backup_path'] = $this->validateBackupPath((string) $formValues['system_backup_path']);
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

                // if any key selection dropdown is set to "Create new key", create one default key and assign
                $keySettingNames = array('sso_saml_signing_key', 'sso_saml_encryption_key', 'sso_oidc_signing_key');

                // A protocol cannot be used without a signing key, so enabling it without any key
                // selected creates the default key instead of rejecting the configuration. A key that
                // is selected but unusable is still reported, so a broken setup is not silently replaced.
                $signingKeySettings = array(
                    'sso_saml_signing_key' => 'sso_saml_enabled',
                    'sso_oidc_signing_key' => 'sso_oidc_enabled'
                );

                $settingsNeedingDefaultKey = array();

                foreach ($keySettingNames as $settingName) {
                    if (($formValues[$settingName] ?? '') === KeyService::CREATE_DEFAULT_KEY_VALUE) {
                        $settingsNeedingDefaultKey[] = $settingName;
                    } elseif (array_key_exists($settingName, $signingKeySettings)
                        && (bool) ($formValues[$signingKeySettings[$settingName]] ?? false)
                        && (int) ($formValues[$settingName] ?? 0) <= 0) {
                        $settingsNeedingDefaultKey[] = $settingName;
                    }
                }

                // One generated RSA key serves SAML signing/encryption as well as OIDC signing,
                // so all requests within this save share a single new key.
                if (count($settingsNeedingDefaultKey) > 0) {
                    $keyService = new KeyService($gDb);
                    $newKeyId = $keyService->createDefaultKey();

                    foreach ($settingsNeedingDefaultKey as $settingName) {
                        $formValues[$settingName] = $newKeyId;
                    }
                }

                $this->validateSSOSettings($formValues);
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
                if (!isset(Text::SYSTEM_MAIL_DEFAULTS[$key])) {
                    continue; // Switches and read-only previews are not separate templates.
                }
                $text = new Text($gDb);
                $text->readDataByColumns(array('txt_org_id' => $gCurrentOrgId, 'txt_name' => $key));
                $text->setSystemMailTemplate((bool)($formValues[$key . '_USE_DEFAULT'] ?? false), (string)$value);
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

        /*
         * A preference that was added after the last update of this installation has no row yet -
         * updateOrgPreferences() writes it, and until then the declared default is what the
         * installation actually behaves like. Reporting that is more useful than refusing the name.
         */
        $value = $gSettingsManager->has($name, true)
            ? $gSettingsManager->get($name)
            : (string)PreferenceDefinitions::defaults()[$name];

        return array(
            'value' => $value,
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
     * The directory of the backups as it is stored: empty for adm_my_files/backups, else the real
     * path of an existing directory the web server can write to. It must not be in the program
     * files of Admidio, which an update replaces, and must not contain the installation.
     * @param string $path The path the administrator entered.
     * @return string
     * @throws Exception
     */
    private function validateBackupPath(string $path): string
    {
        $path = trim($path);
        // The default directory is only created with the first backup.
        if ($path === '' || rtrim(str_replace('\\', '/', $path), '/') === str_replace('\\', '/', ADMIDIO_PATH . FOLDER_DATA . '/backups')) {
            return '';
        }

        $directory = preg_match('#^(/|[A-Za-z]:[\\\\/])#', $path) === 1 ? @realpath($path) : false;
        if ($directory === false || !is_dir($directory)) {
            throw new Exception('ORG_BACKUP_PATH_INVALID', array($path));
        }

        $directory = str_replace('\\', '/', $directory);
        $root = str_replace('\\', '/', (string) realpath(ADMIDIO_PATH));
        $data = $root . FOLDER_DATA;
        if ($directory === $data . '/backups') {
            return '';
        }
        // A root directory such as / ends with its slash already.
        $prefix = rtrim($directory, '/') . '/';
        $inData = str_starts_with($prefix, $data . '/');
        $inInstallation = str_starts_with($prefix, $root . '/');
        $containsInstallation = str_starts_with($root . '/', $prefix);
        if ($containsInstallation || ($inInstallation && !$inData)) {
            throw new Exception('ORG_BACKUP_PATH_IN_INSTALLATION', array($path));
        }
        if (!is_writable($directory)) {
            throw new Exception('ORG_BACKUP_PATH_INVALID', array($path));
        }

        return $directory;
    }

    /**
     * Validate the keys selected for the enabled SSO protocols.
     *
     * Disabled protocols are not validated so that an administrator can
     * deactivate a protocol or repair an invalid configuration.
     *
     * @param array $formValues Validated values of the SSO preferences form.
     * @return void
     * @throws Exception
     */
    private function validateSSOSettings(array $formValues): void
    {
        global $gDb;

        $keyService = new KeyService($gDb);

        $oidcEnabled = (bool) ($formValues['sso_oidc_enabled'] ?? false);
        if ($oidcEnabled) {
            // will trigger an exception on an issuer that is no OIDC issuer identifier
            OIDCService::assertValidIssuerURL((string) ($formValues['sso_oidc_issuer_url'] ?? ''));

            // will trigger an exception on illegal keys
            $keyService->getUsableKey((int) ($formValues['sso_oidc_signing_key'] ?? 0), KeyService::USAGE_OIDC_SIGNING);
        }

        $samlEnabled = (bool) ($formValues['sso_saml_enabled'] ?? false);
        if ($samlEnabled) {
            $keyService->getUsableKey((int) ($formValues['sso_saml_signing_key'] ?? 0), KeyService::USAGE_SAML_SIGNING);

            $samlEncryptionKeyId = (int) ($formValues['sso_saml_encryption_key'] ?? 0);
            if ($samlEncryptionKeyId > 0) {
                $keyService->getUsableKey($samlEncryptionKeyId, KeyService::USAGE_SAML_ENCRYPTION);
            }
        }
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
