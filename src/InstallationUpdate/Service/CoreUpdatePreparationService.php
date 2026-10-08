<?php
namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Exception;
use ZipArchive;

/**
 * Prepares an automatic core update while the normal Admidio application is still intact.
 *
 * The destructive update is deliberately not performed by this class. It downloads and verifies
 * the package, installs the self-contained updater below install/core-update and creates the
 * protected workspace and authorization cookie used by that updater.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CoreUpdatePreparationService
{
    private const GIT_MASTER_PACKAGE_URL = 'https://github.com/Admidio/admidio/archive/refs/heads/master.zip';
    private const WORKSPACE_SUBDIRECTORY = '/core-update';
    private const UPDATER_DIRECTORY = '/install/core-update';
    private const PUBLIC_KEY_FILE = '/install/core-update/public-key.pem';
    private const AUTHORIZATION_LIFETIME = 86400;
    private const MAX_DOWNLOAD_SIZE = 536870912;

    /**
     * Returns the update information required by the settings page.
     *
     * The existing update.txt format remains valid. Automatic release updates are enabled once
     * the following additional entries are published:
     *
     * Package-URL=https://...
     * Release-URL=https://...
     *
     * @param bool $gitMaster If true, information for the current Git master branch is returned.
     * @return array<string,mixed>
     * @throws Exception
     */
    public function getUpdateInformation(bool $gitMaster = false): array
    {
        if ($gitMaster) {
            return array(
                'source' => 'master',
                'installedVersion' => ADMIDIO_VERSION_TEXT,
                'availableVersion' => 'Git master',
                'updateAvailable' => true,
                'automaticUpdateAvailable' => true,
                'packageUrl' => self::GIT_MASTER_PACKAGE_URL,
                'releaseUrl' => '',
                'warning' => 'SYS_CORE_UPDATE_MASTER_WARNING'
            );
        }

        $information = $this->readUpdateInformation();
        $availableVersion = $information['Version'] ?? '';
        $packageUrl = $information['Package-URL'] ?? '';
        $releaseUrl = $information['Release-URL'] ?? '';
        $publicKeyFile = $this->getTrustedPublicKeyFile();
        $publicKeyAvailable = is_readable($publicKeyFile)
            && trim((string) file_get_contents($publicKeyFile)) !== '';

        return array(
            'source' => 'release',
            'installedVersion' => ADMIDIO_VERSION_TEXT,
            'availableVersion' => $availableVersion,
            'updateAvailable' => $availableVersion !== ''
                && version_compare($availableVersion, ADMIDIO_VERSION, '>'),
            'automaticUpdateAvailable' => $packageUrl !== ''
                && $releaseUrl !== ''
                && $publicKeyAvailable,
            'packageUrl' => $packageUrl,
            'releaseUrl' => $releaseUrl,
            'warning' => ''
        );
    }

    /**
     * Downloads the selected package, verifies it where possible and installs only the isolated
     * updater. The returned URL starts the updater without requiring another administrator login.
     *
     * @param bool $gitMaster If true, the current Git master archive is used.
     * @return string URL of the isolated updater.
     * @throws Exception
     */
    public function prepareUpdate(bool $gitMaster): string
    {
        global $gCurrentUserId, $gL10n;

        if (!extension_loaded('curl')
            || !extension_loaded('zip')
            || (!$gitMaster && !extension_loaded('openssl'))) {
            throw new Exception('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS');
        }

        $this->removeExpiredActiveUpdate();

        $activeFile = $this->getWorkspaceRoot() . '/active.json';
        if (is_file($activeFile)) {
            throw new Exception('SYS_CORE_UPDATE_ALREADY_RUNNING');
        }

        $updateInformation = $this->getUpdateInformation($gitMaster);
        if (!$gitMaster && !$updateInformation['updateAvailable']) {
            throw new Exception('SYS_USING_CURRENT_VERSION', array(''));
        }
        if (!$updateInformation['automaticUpdateAvailable']) {
            throw new Exception('SYS_CORE_UPDATE_RELEASE_NOT_CONFIGURED');
        }

        $updateId = bin2hex(random_bytes(16));
        $authorizationToken = bin2hex(random_bytes(32));
        $csrfToken = bin2hex(random_bytes(32));
        $workspace = $this->getWorkspaceRoot() . '/' . $updateId;

        $this->createDirectory($workspace);
        $this->writeJsonFile($activeFile, array(
            'updateId' => $updateId,
            'expiresAt' => time() + self::AUTHORIZATION_LIFETIME
        ));

        try {
            $packageFile = $workspace . '/package.zip';
            $manifest = array();
            $manifestSha256 = '';

            if ($gitMaster) {
                $this->downloadFile(self::GIT_MASTER_PACKAGE_URL, $packageFile);
            } else {
                $trustedPublicKey = trim((string) file_get_contents($this->getTrustedPublicKeyFile()));
                if ($trustedPublicKey === '') {
                    throw new Exception('SYS_CORE_UPDATE_SIGNATURE_INVALID');
                }

                // Keep the currently trusted key in the protected workspace. The isolated updater
                // must not depend on a public-key file that it replaces itself during the update.
                if (file_put_contents($workspace . '/trusted-public-key.pem', $trustedPublicKey . PHP_EOL, LOCK_EX) === false) {
                    throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                }

                $releaseFile = $workspace . '/release.json';
                $releaseSignatureFile = $workspace . '/release.json.sig';
                $this->downloadFile((string) $updateInformation['releaseUrl'], $releaseFile);
                $this->downloadFile((string) $updateInformation['releaseUrl'] . '.sig', $releaseSignatureFile);

                $release = $this->readAndVerifyReleaseDescriptor(
                    $releaseFile,
                    $releaseSignatureFile,
                    $trustedPublicKey
                );

                if (($release['version'] ?? '') !== $updateInformation['availableVersion']) {
                    throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
                }

                $this->downloadFile((string) $updateInformation['packageUrl'], $packageFile);
                $packageHash = $this->calculateFileHash($packageFile);
                if (!hash_equals(strtolower((string) $release['packageSha256']), $packageHash)) {
                    throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                }
                if (isset($release['packageSize']) && (int) $release['packageSize'] !== (int) filesize($packageFile)) {
                    throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                }

                $archiveRoot = $this->findArchiveRoot($packageFile);
                $manifest = $this->extractAndVerifyPackageManifest(
                    $packageFile,
                    $archiveRoot,
                    $trustedPublicKey,
                    (string) $release['version'],
                    (string) $release['manifestSha256'],
                    $workspace
                );
                $manifestSha256 = $this->calculateFileHash($workspace . '/update-manifest.json');
            }

            if (!isset($archiveRoot)) {
                $archiveRoot = $this->findArchiveRoot($packageFile);
            }

            $this->installUpdater($packageFile, $archiveRoot, $manifest);

            $cookieName = COOKIE_PREFIX . '_CORE_UPDATE_' . substr($updateId, 0, 12);
            $state = array(
                'schema' => 2,
                'updateId' => $updateId,
                'source' => $gitMaster ? 'master' : 'release',
                'sourceVersion' => ADMIDIO_VERSION_TEXT,
                'targetVersion' => $gitMaster ? 'Git master' : (string) $updateInformation['availableVersion'],
                'packageUrl' => $gitMaster ? self::GIT_MASTER_PACKAGE_URL : (string) $updateInformation['packageUrl'],
                'packageSha256' => $this->calculateFileHash($packageFile),
                'manifestSha256' => $manifestSha256,
                'archiveRoot' => $archiveRoot,
                'state' => 'prepared',
                'currentOperation' => 0,
                'totalOperations' => 0,
                'createdAt' => time(),
                'expiresAt' => time() + self::AUTHORIZATION_LIFETIME,
                'administratorUserId' => (int) $gCurrentUserId,
                'cookieName' => $cookieName,
                'authorizationHash' => hash('sha256', $authorizationToken),
                'csrfToken' => $csrfToken,
                'baseUrl' => ADMIDIO_URL,
                'languageCode' => $gL10n->getLanguage(),
                'translations' => $this->getUpdaterTranslations(),
                'lastError' => ''
            );
            $this->writeJsonFile($workspace . '/state.json', $state);

            $cookiePath = rtrim(ADMIDIO_URL_PATH, '/') . '/install/';
            if ($cookiePath === '') {
                $cookiePath = '/';
            }
            setcookie($cookieName, $authorizationToken, array(
                'expires' => $state['expiresAt'],
                'path' => $cookiePath,
                'secure' => HTTPS,
                'httponly' => true,
                'samesite' => 'Strict'
            ));

            return ADMIDIO_URL . self::UPDATER_DIRECTORY . '/index.php?update_id=' . rawurlencode($updateId);
        } catch (\Throwable $exception) {
            $this->deleteDirectory($workspace);
            @unlink($activeFile);
            throw $exception;
        }
    }

    /**
     * @return array<string,string>
     * @throws Exception
     */
    private function readUpdateInformation(): array
    {
        $contents = $this->downloadString($this->getUpdateInformationUrl());
        $information = array();

        foreach (preg_split('/\R/', $contents) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $information[trim($name)] = trim($value);
        }

        if (!isset($information['Version'])) {
            throw new Exception('SYS_CORE_UPDATE_INFORMATION_INVALID');
        }

        foreach (array('Package-URL', 'Release-URL') as $urlKey) {
            if (isset($information[$urlKey]) && $information[$urlKey] !== '') {
                $information[$urlKey] = $this->resolveUpdateUrl($information[$urlKey]);
            }
        }

        return $information;
    }

    /**
     * @param string $releaseFile
     * @param string $signatureFile
     * @param string $publicKey
     * @return array<string,mixed>
     * @throws Exception
     */
    private function readAndVerifyReleaseDescriptor(
        string $releaseFile,
        string $signatureFile,
        string $publicKey
    ): array {
        $releaseContents = file_get_contents($releaseFile);
        $signatureContents = file_get_contents($signatureFile);

        if ($releaseContents === false || $signatureContents === false) {
            throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
        }

        $release = json_decode($releaseContents, true);
        $signature = base64_decode(trim($signatureContents), true);

        if (!is_array($release)
            || ($release['schema'] ?? null) !== 1
            || !isset($release['version'], $release['packageSha256'], $release['manifestSha256'])
            || !is_string($release['version'])
            || !preg_match('/^[a-f0-9]{64}$/i', (string) $release['packageSha256'])
            || !preg_match('/^[a-f0-9]{64}$/i', (string) $release['manifestSha256'])
            || $signature === false) {
            throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
        }

        if (isset($release['packageSize']) && (!is_int($release['packageSize']) || $release['packageSize'] <= 0)) {
            throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
        }

        if (openssl_verify($releaseContents, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new Exception('SYS_CORE_UPDATE_SIGNATURE_INVALID');
        }

        return $release;
    }


    /**
     * Extracts the file inventory from the already authenticated release ZIP and verifies its
     * independent signature. The full inventory is only downloaded as part of the release package.
     *
     * @param string $packageFile
     * @param string $archiveRoot
     * @param string $publicKey
     * @param string $expectedVersion
     * @param string $expectedManifestSha256
     * @param string $workspace
     * @return array<string,mixed>
     * @throws Exception
     */
    private function extractAndVerifyPackageManifest(
        string $packageFile,
        string $archiveRoot,
        string $publicKey,
        string $expectedVersion,
        string $expectedManifestSha256,
        string $workspace
    ): array {
        $archive = new ZipArchive();
        if ($archive->open($packageFile) !== true) {
            throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
        }

        try {
            $manifestEntry = $archiveRoot . 'install/update-manifest.json';
            $signatureEntry = $archiveRoot . 'install/update-manifest.json.sig';
            $manifestContents = $archive->getFromName($manifestEntry);
            $signatureContents = $archive->getFromName($signatureEntry);
        } finally {
            $archive->close();
        }

        if (!is_string($manifestContents) || !is_string($signatureContents)) {
            throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
        }

        if (!hash_equals(strtolower($expectedManifestSha256), hash('sha256', $manifestContents))) {
            throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
        }

        $signature = base64_decode(trim($signatureContents), true);
        if ($signature === false
            || openssl_verify($manifestContents, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new Exception('SYS_CORE_UPDATE_SIGNATURE_INVALID');
        }

        $manifest = json_decode($manifestContents, true);
        if (!is_array($manifest)
            || ($manifest['schema'] ?? null) !== 1
            || ($manifest['version'] ?? '') !== $expectedVersion
            || !isset($manifest['files'], $manifest['managedDirectories'], $manifest['preservePaths'], $manifest['manageRootFiles'])
            || !is_array($manifest['files'])
            || !is_array($manifest['managedDirectories'])
            || !is_array($manifest['preservePaths'])
            || !is_bool($manifest['manageRootFiles'])) {
            throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
        }

        $caseInsensitivePaths = array();
        foreach ($manifest['files'] as $path => $hash) {
            $this->validateRelativePath((string) $path);
            if (!preg_match('/^[a-f0-9]{64}$/i', (string) $hash)) {
                throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
            }

            $caseKey = strtolower((string) $path);
            if (isset($caseInsensitivePaths[$caseKey])) {
                throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
            }
            $caseInsensitivePaths[$caseKey] = true;
        }

        foreach (array_merge($manifest['managedDirectories'], $manifest['preservePaths']) as $path) {
            if (!is_string($path)) {
                throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
            }
            $this->validateRelativePath($path);
        }

        if (file_put_contents($workspace . '/update-manifest.json', $manifestContents, LOCK_EX) === false
            || file_put_contents($workspace . '/update-manifest.json.sig', $signatureContents, LOCK_EX) === false) {
            throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }

        return $manifest;
    }


    /**
     * @param string $packageFile
     * @return string Prefix of the package contents, including a trailing slash or an empty string.
     * @throws Exception
     */
    private function findArchiveRoot(string $packageFile): string
    {
        $archive = new ZipArchive();
        if ($archive->open($packageFile) !== true) {
            throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
        }

        $needle = 'install/core-update/index.php';
        $archiveRoot = null;

        for ($index = 0; $index < $archive->numFiles; ++$index) {
            $entryName = str_replace('\\', '/', (string) $archive->getNameIndex($index));
            if ($entryName === $needle) {
                $archiveRoot = '';
                break;
            }
            if (str_ends_with($entryName, '/' . $needle)) {
                $archiveRoot = substr($entryName, 0, -strlen($needle));
                break;
            }
        }

        $archive->close();

        if ($archiveRoot === null) {
            throw new Exception('SYS_CORE_UPDATE_UPDATER_MISSING');
        }

        return $archiveRoot;
    }

    /**
     * @param string $packageFile
     * @param string $archiveRoot
     * @param array<string,mixed> $manifest
     * @throws Exception
     */
    private function installUpdater(string $packageFile, string $archiveRoot, array $manifest): void
    {
        $archive = new ZipArchive();
        if ($archive->open($packageFile) !== true) {
            throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
        }

        $workspace = dirname($packageFile);
        $stagingDirectory = $workspace . '/updater-staging';
        $backupDirectory = $workspace . '/updater-backup';
        $targetDirectory = ADMIDIO_PATH . self::UPDATER_DIRECTORY;
        $archivePrefix = $archiveRoot . 'install/core-update/';
        $stagedFiles = array();

        $this->deleteDirectory($stagingDirectory);
        $this->deleteDirectory($backupDirectory);
        $this->createDirectory($stagingDirectory);
        $this->createDirectory($backupDirectory);

        try {
            for ($index = 0; $index < $archive->numFiles; ++$index) {
                $entryName = str_replace('\\', '/', (string) $archive->getNameIndex($index));
                if (!str_starts_with($entryName, $archivePrefix) || str_ends_with($entryName, '/')) {
                    continue;
                }

                $relativeUpdaterPath = substr($entryName, strlen($archivePrefix));
                $this->validateRelativePath($relativeUpdaterPath);
                $manifestPath = 'install/core-update/' . $relativeUpdaterPath;

                if ($manifest !== array() && !isset($manifest['files'][$manifestPath])) {
                    throw new Exception('SYS_CORE_UPDATE_MANIFEST_INVALID');
                }

                $operatingSystem = 0;
                $externalAttributes = 0;
                if ($archive->getExternalAttributesIndex($index, $operatingSystem, $externalAttributes)) {
                    $fileType = ($externalAttributes >> 16) & 0170000;
                    if ($fileType === 0120000) {
                        throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                    }
                }

                $source = $archive->getStream($entryName);
                if (!is_resource($source)) {
                    throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                }

                $stagedFile = $stagingDirectory . '/' . $relativeUpdaterPath;
                $this->createDirectory(dirname($stagedFile));
                $target = fopen($stagedFile, 'wb');
                if (!is_resource($target)) {
                    fclose($source);
                    throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                }

                $copySuccessful = stream_copy_to_stream($source, $target) !== false;
                fflush($target);
                if (function_exists('fsync')) {
                    fsync($target);
                }
                fclose($source);
                fclose($target);

                if (!$copySuccessful) {
                    throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                }

                $fileHash = $this->calculateFileHash($stagedFile);
                if ($fileHash === false
                    || ($manifest !== array()
                        && !hash_equals(strtolower((string) $manifest['files'][$manifestPath]), $fileHash))) {
                    throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                }

                $stagedFiles[] = $relativeUpdaterPath;
            }
        } finally {
            $archive->close();
        }

        if (count($stagedFiles) < 2
            || !is_file($stagingDirectory . '/index.php')
            || !is_file($stagingDirectory . '/CoreUpdater.php')) {
            $this->deleteDirectory($stagingDirectory);
            $this->deleteDirectory($backupDirectory);
            throw new Exception('SYS_CORE_UPDATE_UPDATER_MISSING');
        }

        $installedFiles = array();

        try {
            foreach ($stagedFiles as $relativeUpdaterPath) {
                $stagedFile = $stagingDirectory . '/' . $relativeUpdaterPath;
                $targetFile = $targetDirectory . '/' . $relativeUpdaterPath;
                $backupFile = $backupDirectory . '/' . $relativeUpdaterPath;
                $targetExisted = is_file($targetFile);

                if (is_link($targetFile)) {
                    throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                }

                $this->createDirectory(dirname($targetFile));

                if ($targetExisted) {
                    $this->createDirectory(dirname($backupFile));
                    if (!copy($targetFile, $backupFile)) {
                        throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                    }
                }

                $temporaryFile = dirname($targetFile) . '/.' . basename($targetFile) . '.admidio-update.tmp';
                @unlink($temporaryFile);
                if (!copy($stagedFile, $temporaryFile)) {
                    throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                }

                $expectedHash = $this->calculateFileHash($stagedFile);
                $temporaryHash = $this->calculateFileHash($temporaryFile);
                if ($expectedHash === false || $temporaryHash === false || !hash_equals($expectedHash, $temporaryHash)) {
                    @unlink($temporaryFile);
                    throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
                }

                if ($targetExisted) {
                    $targetMode = fileperms($targetFile);
                    if ($targetMode !== false) {
                        @chmod($temporaryFile, $targetMode & 0777);
                    }
                }

                // Record the operation before the target is changed, so that the catch block can restore it.
                $installedFiles[] = array(
                    'target' => $targetFile,
                    'backup' => $backupFile,
                    'existed' => $targetExisted
                );

                if ($targetExisted && !@unlink($targetFile)) {
                    @unlink($temporaryFile);
                    throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                }

                if (!@rename($temporaryFile, $targetFile)) {
                    @unlink($temporaryFile);
                    throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
                }
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($installedFiles) as $installedFile) {
                $targetFile = (string) $installedFile['target'];

                if ((bool) $installedFile['existed']) {
                    $backupFile = (string) $installedFile['backup'];
                    $restoreFile = dirname($targetFile) . '/.' . basename($targetFile) . '.admidio-restore.tmp';
                    @unlink($restoreFile);

                    if (is_file($backupFile)
                        && copy($backupFile, $restoreFile)
                        && (!is_file($targetFile) || @unlink($targetFile))
                        && @rename($restoreFile, $targetFile)) {
                        continue;
                    }

                    @unlink($restoreFile);
                } else {
                    @unlink($targetFile);
                }
            }

            throw $exception;
        } finally {
            $this->deleteDirectory($stagingDirectory);
            $this->deleteDirectory($backupDirectory);
        }
    }

    private function getUpdateInformationUrl(): string
    {
        return $this->getUpdateServerUrl() . 'update.txt';
    }

    /**
     * The normal update server is always used in production. A custom server may only be selected
     * from config.php while Admidio debug mode is enabled. This keeps local test endpoints from
     * accidentally becoming a production setting.
     */
    private function getUpdateServerUrl(): string
    {
        global $gDebug, $gCoreUpdateServerUrl;

        if (!empty($gDebug)
            && isset($gCoreUpdateServerUrl)
            && is_string($gCoreUpdateServerUrl)
            && trim($gCoreUpdateServerUrl) !== '') {
            $url = rtrim(trim($gCoreUpdateServerUrl), '/') . '/';
            $parts = parse_url($url);

            if (!is_array($parts)
                || !isset($parts['host'])
                || !in_array(strtolower((string) ($parts['scheme'] ?? '')), array('http', 'https'), true)
                || isset($parts['user'])
                || isset($parts['pass'])) {
                throw new Exception('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID');
            }

            return $url;
        }

        return ADMIDIO_HOMEPAGE;
    }

    /**
     * In debug mode a test public key may be selected from config.php. Production updates always
     * use the public key shipped with Admidio.
     */
    private function getTrustedPublicKeyFile(): string
    {
        global $gDebug, $gCoreUpdatePublicKeyFile;

        if (!empty($gDebug)
            && isset($gCoreUpdatePublicKeyFile)
            && is_string($gCoreUpdatePublicKeyFile)
            && trim($gCoreUpdatePublicKeyFile) !== '') {
            $file = trim($gCoreUpdatePublicKeyFile);

            if (str_starts_with($file, '/')
                || str_starts_with($file, '\\')
                || preg_match('/^[a-zA-Z]:[\/\\\\]/', $file) === 1) {
                return $file;
            }

            return ADMIDIO_PATH . '/' . ltrim(str_replace('\\', '/', $file), '/');
        }

        return ADMIDIO_PATH . self::PUBLIC_KEY_FILE;
    }

    /**
     * Resolve Package-URL and Release-URL relative to the update server. Relative URLs make a
     * generated test update.txt portable between www.admidio.org and a local updates.local host.
     */
    private function resolveUpdateUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            throw new Exception('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID');
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $baseUrl = $this->getUpdateServerUrl();
        if (str_starts_with($url, '/')) {
            $parts = parse_url($baseUrl);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
                throw new Exception('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID');
            }

            $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
            return $parts['scheme'] . '://' . $parts['host'] . $port . $url;
        }

        return $baseUrl . ltrim($url, '/');
    }

    private function isConfiguredDebugUpdateUrl(string $url): bool
    {
        global $gDebug, $gCoreUpdateServerUrl;

        if (empty($gDebug)
            || !isset($gCoreUpdateServerUrl)
            || !is_string($gCoreUpdateServerUrl)
            || trim($gCoreUpdateServerUrl) === '') {
            return false;
        }

        $configured = parse_url(rtrim(trim($gCoreUpdateServerUrl), '/') . '/');
        $candidate = parse_url($url);

        if (!is_array($configured) || !is_array($candidate)) {
            return false;
        }

        $configuredScheme = strtolower((string) ($configured['scheme'] ?? ''));
        $candidateScheme = strtolower((string) ($candidate['scheme'] ?? ''));
        $configuredHost = strtolower((string) ($configured['host'] ?? ''));
        $candidateHost = strtolower((string) ($candidate['host'] ?? ''));

        $configuredPort = isset($configured['port'])
            ? (int) $configured['port']
            : ($configuredScheme === 'https' ? 443 : 80);
        $candidatePort = isset($candidate['port'])
            ? (int) $candidate['port']
            : ($candidateScheme === 'https' ? 443 : 80);

        return in_array($configuredScheme, array('http', 'https'), true)
            && $candidateScheme === $configuredScheme
            && $candidateHost === $configuredHost
            && $candidatePort === $configuredPort
            && !isset($candidate['user'])
            && !isset($candidate['pass']);
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    private function downloadString(string $url): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'admidio-update-');
        if ($temporaryFile === false) {
            throw new Exception('SYS_CORE_UPDATE_DOWNLOAD_FAILED', array(''));
        }

        try {
            $this->downloadFile($url, $temporaryFile);
            return (string) file_get_contents($temporaryFile);
        } finally {
            @unlink($temporaryFile);
        }
    }

    /**
     * @param string $url
     * @param string $targetFile
     * @throws Exception
     */
    private function downloadFile(string $url, string $targetFile): void
    {
        $this->validateDownloadUrl($url);

        $target = fopen($targetFile, 'wb');
        if (!is_resource($target)) {
            throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }

        $curlProtocols = CURLPROTO_HTTPS;
        if ($this->isConfiguredDebugUpdateUrl($url) && parse_url($url, PHP_URL_SCHEME) === 'http') {
            $curlProtocols |= CURLPROTO_HTTP;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_FILE => $target,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => $curlProtocols,
            CURLOPT_REDIR_PROTOCOLS => $curlProtocols,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_USERAGENT => 'Admidio/' . ADMIDIO_VERSION_TEXT . ' Core Updater',
            CURLOPT_FAILONERROR => true,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($resource, float $downloadSize, float $downloaded): int {
                if ($downloadSize > self::MAX_DOWNLOAD_SIZE || $downloaded > self::MAX_DOWNLOAD_SIZE) {
                    return 1;
                }
                return 0;
            }
        ));

        $success = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        curl_close($curl);
        fflush($target);
        fclose($target);

        if ($effectiveUrl !== '') {
            $this->validateDownloadUrl($effectiveUrl);
        }

        if ($success !== true || $statusCode < 200 || $statusCode >= 300 || !is_file($targetFile) || filesize($targetFile) === 0) {
            @unlink($targetFile);
            throw new Exception('SYS_CORE_UPDATE_DOWNLOAD_FAILED', array($error));
        }
    }

    /**
     * @param string $url
     * @throws Exception
     */
    private function validateDownloadUrl(string $url): void
    {
        if ($this->isConfiguredDebugUpdateUrl($url)) {
            return;
        }

        $parts = parse_url($url);
        $allowedHosts = array(
            'www.admidio.org',
            'admidio.org',
            'sourceforge.net',
            'downloads.sourceforge.net',
            'github.com',
            'codeload.github.com'
        );

        $host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $trustedHost = in_array($host, $allowedHosts, true)
            || str_ends_with($host, '.dl.sourceforge.net');

        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || !$trustedHost) {
            throw new Exception('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID');
        }
    }

    /**
     * @param string $file
     * @return string
     * @throws Exception
     */
    private function calculateFileHash(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
        }

        return $hash;
    }

    /**
     * @param string $path
     * @throws Exception
     */
    private function validateRelativePath(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[a-zA-Z]:\//', $path)
            || str_contains($path, "\0")
            || in_array('..', explode('/', $path), true)) {
            throw new Exception('SYS_CORE_UPDATE_PACKAGE_INVALID');
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        return $value;
    }


    /**
     * Resolve the small set of updater UI strings while the normal Admidio language service is
     * available. The isolated updater stores these texts in state.json and therefore remains
     * independent of language files that are replaced during the update.
     *
     * @return array<string,string>
     */
    private function getUpdaterTranslations(): array
    {
        global $gL10n;

        $keys = array(
            'title' => 'SYS_CORE_UPDATE_TITLE',
            'installedVersion' => 'SYS_CORE_UPDATE_INSTALLED_VERSION',
            'target' => 'SYS_CORE_UPDATE_TARGET',
            'currentState' => 'SYS_CORE_UPDATE_CURRENT_STATE',
            'ready' => 'SYS_CORE_UPDATE_READY',
            'startResume' => 'SYS_CORE_UPDATE_START_RESUME',
            'restorePrevious' => 'SYS_CORE_UPDATE_RESTORE_PREVIOUS',
            'masterWarning' => 'SYS_CORE_UPDATE_MASTER_WARNING',
            'requestFailed' => 'SYS_CORE_UPDATE_REQUEST_FAILED',
            'confirmRestore' => 'SYS_CORE_UPDATE_CONFIRM_RESTORE',
            'progressLabel' => 'SYS_CORE_UPDATE_PROGRESS',
            'openAdmidio' => 'SYS_CORE_UPDATE_OPEN_ADMIDIO',
            'packageExtracted' => 'SYS_CORE_UPDATE_PACKAGE_EXTRACTED',
            'preflightSuccessful' => 'SYS_CORE_UPDATE_PREFLIGHT_SUCCESS',
            'maintenanceActive' => 'SYS_CORE_UPDATE_MAINTENANCE_ACTIVE',
            'filesInstalledDatabaseStart' => 'SYS_CORE_UPDATE_FILES_INSTALLED_DB_START',
            'filesInstalling' => 'SYS_CORE_UPDATE_FILES_INSTALLING',
            'databaseStart' => 'SYS_CORE_UPDATE_DATABASE_START',
            'databaseContinue' => 'SYS_CORE_UPDATE_DATABASE_CONTINUE',
            'alreadyCompleted' => 'SYS_CORE_UPDATE_ALREADY_COMPLETED',
            'filesystemRolledBack' => 'SYS_CORE_UPDATE_FILESYSTEM_ROLLED_BACK',
            'previousFilesRestored' => 'SYS_CORE_UPDATE_PREVIOUS_FILES_RESTORED',
            'updateSuccessful' => 'SYS_CORE_UPDATE_SUCCESSFUL',
            'maintenanceMessage' => 'SYS_CORE_UPDATE_MAINTENANCE_MESSAGE'
        );

        $translations = array();
        foreach ($keys as $name => $languageKey) {
            $translations[$name] = $gL10n->get($languageKey);
        }

        return $translations;
    }


    private function getWorkspaceRoot(): string
    {
        return ADMIDIO_PATH . FOLDER_TEMP_DATA . self::WORKSPACE_SUBDIRECTORY;
    }

    /**
     * @param string $directory
     * @throws Exception
     */
    private function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
    }

    /**
     * @param string $file
     * @param array<string,mixed> $data
     * @throws Exception
     */
    private function writeJsonFile(string $file, array $data): void
    {
        $this->createDirectory(dirname($file));
        $temporaryFile = $file . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($temporaryFile, $json, LOCK_EX) === false || !@rename($temporaryFile, $file)) {
            @unlink($temporaryFile);
            throw new Exception('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
    }

    private function removeExpiredActiveUpdate(): void
    {
        $activeFile = $this->getWorkspaceRoot() . '/active.json';
        if (!is_file($activeFile)) {
            return;
        }

        $active = json_decode((string) file_get_contents($activeFile), true);
        if (!is_array($active) || (int) ($active['expiresAt'] ?? 0) < time()) {
            if (isset($active['updateId']) && preg_match('/^[a-f0-9]{32}$/', (string) $active['updateId'])) {
                $this->deleteDirectory($this->getWorkspaceRoot() . '/' . $active['updateId']);
            }
            @unlink($activeFile);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($directory);
    }
}
