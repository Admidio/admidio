<?php
/**
 * Self-contained Admidio core updater.
 *
 * This file deliberately has no dependency on the normal Admidio autoloader or source tree. The
 * state, the lock, the workspace and the texts come from the classes in lib/; who may run it is
 * decided by index.php before this class is used.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
use AdmidioCoreUpdater\Config;
use AdmidioCoreUpdater\Credentials;
use AdmidioCoreUpdater\StateStore;
use AdmidioCoreUpdater\Texts;

class CoreUpdater
{
    /**
     * The texts of the updater by the names this class uses.
     */
    private const TEXT_KEYS = array(
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

    private const INSTALLER_RELATIVE_PATH = 'install/core-update/';
    private const FILES_PER_REQUEST = 40;
    private const MAX_UNCOMPRESSED_SIZE = 1073741824;

    private string $rootPath;
    private string $updateId;
    private string $workspace;
    /** @var array<string,mixed> */
    private array $state = array();
    /** @var array<string,mixed>|null */
    private ?array $manifest = null;

    public function __construct(
        private readonly Config $config,
        private readonly StateStore $store,
        private readonly Texts $texts,
        string $updateId
    ) {
        $realRootPath = realpath($config->rootPath);
        if ($realRootPath === false) {
            throw new RuntimeException('The Admidio installation path could not be determined.');
        }

        $this->rootPath = rtrim(str_replace('\\', '/', $realRootPath), '/');
        $this->updateId = $updateId;
        $this->workspace = $store->getWorkspace($updateId);
        $this->state = $this->readState();
    }

    /**
     * Returns a text of the updater by the name this class uses for it.
     *
     * @param string $name
     * @param array<int,string> $params
     */
    public function getText(string $name, array $params = array()): string
    {
        return $this->texts->get(self::TEXT_KEYS[$name] ?? $name, $params);
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatus(): array
    {
        return array(
            'state' => (string) ($this->state['state'] ?? 'unknown'),
            'channel' => (string) ($this->state['channel'] ?? ''),
            'installedVersion' => (string) ($this->state['installedVersion'] ?? ''),
            'targetVersion' => (string) ($this->state['targetVersion'] ?? ''),
            'currentOperation' => (int) ($this->state['currentOperation'] ?? 0),
            'totalOperations' => (int) ($this->state['totalOperations'] ?? 0),
            'lastError' => (string) ($this->state['lastError'] ?? ''),
            'progress' => $this->calculateProgress()
        );
    }

    /**
     * Performs exactly one resumable update step.
     *
     * @return array<string,mixed>
     */
    public function runNextStep(): array
    {
        $this->acquireLock();

        try {
            $this->state = $this->readState();
            $state = (string) $this->state['state'];

            if ($state === 'prepared') {
                $this->extractPackage();
                $this->setState('extracted');
                return $this->successResponse($this->getText('packageExtracted'));
            }

            if ($state === 'extracted') {
                $this->preflightAndCreateOperations();
                $this->setState('ready');
                return $this->successResponse($this->getText('preflightSuccessful'));
            }

            if ($state === 'ready') {
                $this->enableMaintenanceMode();
                $this->setState('installing');
                return $this->successResponse($this->getText('maintenanceActive'));
            }

            if ($state === 'installing') {
                $this->processOperationsBatch();

                if ((int) $this->state['currentOperation'] >= (int) $this->state['totalOperations']) {
                    $this->verifyInstalledRelease();
                    $this->setState('files_installed');
                    return array(
                        'status' => 'redirect',
                        'message' => $this->getText('filesInstalledDatabaseStart'),
                        'url' => $this->getDatabaseUpdateUrl(),
                        'progress' => 95
                    );
                }

                return $this->successResponse($this->getText('filesInstalling'));
            }

            if ($state === 'files_installed') {
                return array(
                    'status' => 'redirect',
                    'message' => $this->getText('databaseStart'),
                    'url' => $this->getDatabaseUpdateUrl(),
                    'progress' => 95
                );
            }

            // install/update.php owns these states: it runs the migration and records the result.
            if ($state === 'database_running' || $state === 'database_failed') {
                return array(
                    'status' => 'redirect',
                    'message' => $this->getText('databaseContinue'),
                    'url' => $this->getDatabaseUpdateUrl(),
                    'progress' => 95
                );
            }

            if ($state === 'completed') {
                return array(
                    'status' => 'completed',
                    'message' => $this->getText('alreadyCompleted'),
                    'progress' => 100
                );
            }

            if ($state === 'rollback_required') {
                return array(
                    'status' => 'rollback_required',
                    'message' => (string) ($this->state['lastError'] ?? 'The file update failed.'),
                    'progress' => $this->calculateProgress()
                );
            }

            if ($state === 'rolled_back' || $state === 'failed') {
                return array(
                    'status' => 'error',
                    'message' => (string) ($this->state['lastError'] ?? 'The update could not be completed.'),
                    'progress' => $this->calculateProgress()
                );
            }

            throw new RuntimeException('Unknown core update state: ' . $state);
        } catch (Throwable $exception) {
            $currentState = (string) ($this->state['state'] ?? '');
            $this->state['lastError'] = $exception->getMessage();

            if (in_array($currentState, array('ready', 'installing'), true)) {
                $this->state['state'] = 'rollback_required';
            } elseif (!in_array($currentState, array('files_installed', 'database_running', 'database_failed', 'completed'), true)) {
                $this->state['state'] = 'failed';
            }

            $this->writeState();
            throw $exception;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Rolls back all journaled filesystem operations. Database changes are never rolled back.
     *
     * @return array<string,mixed>
     */
    public function rollback(): array
    {
        $this->acquireLock();

        try {
            $this->state = $this->readState();
            if (!in_array((string) $this->state['state'], array('installing', 'rollback_required', 'failed'), true)) {
                throw new RuntimeException('Rollback is not possible in the current update state.');
            }
            // The database may already be ahead of the old files, so they must not come back.
            if (!empty($this->state['databaseStarted'])) {
                throw new RuntimeException('Rollback is not possible after the database update has started.');
            }

            $entries = $this->readJournal();
            $operations = array();

            foreach ($entries as $entry) {
                $index = (int) ($entry['index'] ?? -1);
                if ($index >= 0 && ($entry['phase'] ?? '') === 'prepared') {
                    $operations[$index] = $entry;
                }
            }

            krsort($operations, SORT_NUMERIC);
            foreach ($operations as $entry) {
                $targetPath = $this->absoluteTargetPath((string) $entry['path']);
                $action = (string) $entry['action'];
                $backupPath = isset($entry['backup']) && $entry['backup'] !== ''
                    ? $this->workspace . '/' . $entry['backup']
                    : '';

                if ($action === 'create') {
                    if (is_file($targetPath) || is_link($targetPath)) {
                        if (!@unlink($targetPath)) {
                            throw new RuntimeException('Could not remove newly created file during rollback: ' . $entry['path']);
                        }
                    }
                } else {
                    if ($backupPath === '' || !is_file($backupPath)) {
                        throw new RuntimeException('Backup file is missing during rollback: ' . $entry['path']);
                    }
                    $this->copyFileAtomically($backupPath, $targetPath, isset($entry['mode']) ? (int) $entry['mode'] : null);
                }
            }

            $this->disableMaintenanceMode();
            $this->state['state'] = 'rolled_back';
            $this->state['lastError'] = $this->getText('filesystemRolledBack');
            // The update is over, so nobody may continue it.
            Credentials::revokeAll($this->state);
            $this->writeState();
            Credentials::clearCookie($this->config);

            $this->deleteDirectory($this->workspace . '/extracted');
            $this->deleteDirectory($this->workspace . '/backup');
            @unlink($this->workspace . '/package.zip');
            @unlink($this->workspace . '/operations.json');
            @unlink($this->workspace . '/journal.jsonl');
            @unlink($this->workspace . '/release.json');
            @unlink($this->workspace . '/release.json.sig');
            @unlink($this->workspace . '/update-manifest.json');
            @unlink($this->workspace . '/update-manifest.json.sig');
            @unlink($this->workspace . '/trusted-public-key.pem');
            $this->store->clearActive($this->updateId);

            return array(
                'status' => 'rolled_back',
                'message' => $this->getText('previousFilesRestored'),
                'progress' => 0
            );
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Completes cleanup after install/update.php has finished the database update.
     *
     * @return array<string,mixed>
     */
    public function finishUpdate(): array
    {
        $this->acquireLock();

        try {
            $this->state = $this->readState();
            // install/update.php sets this state and switches maintenance mode off itself.
            if ((string) $this->state['state'] !== 'completed') {
                throw new RuntimeException('The database update has not completed.');
            }

            $this->disableMaintenanceMode();
            $this->state['state'] = 'completed';
            $this->state['lastError'] = '';
            // The update is over, so nobody may continue it.
            Credentials::revokeAll($this->state);
            $this->writeState();
            Credentials::clearCookie($this->config);

            $this->deleteDirectory($this->workspace . '/extracted');
            $this->deleteDirectory($this->workspace . '/backup');
            @unlink($this->workspace . '/package.zip');
            @unlink($this->workspace . '/operations.json');
            @unlink($this->workspace . '/journal.jsonl');
            @unlink($this->workspace . '/release.json');
            @unlink($this->workspace . '/release.json.sig');
            @unlink($this->workspace . '/update-manifest.json');
            @unlink($this->workspace . '/update-manifest.json.sig');
            @unlink($this->workspace . '/trusted-public-key.pem');
            $this->store->clearActive($this->updateId);

            return array(
                'status' => 'completed',
                'message' => $this->getText('updateSuccessful', array((string) $this->state['targetVersion'])),
                'progress' => 100,
                'url' => $this->config->baseUrl . '/modules/overview.php'
            );
        } finally {
            $this->releaseLock();
        }
    }

    private function extractPackage(): void
    {
        $packageFile = $this->workspace . '/package.zip';
        if (!is_file($packageFile)
            || !hash_equals((string) $this->state['packageSha256'], $this->calculateFileHash($packageFile))) {
            throw new RuntimeException('The downloaded update package is missing or has changed.');
        }

        $destination = $this->workspace . '/extracted';
        $this->deleteDirectory($destination);
        $this->createDirectory($destination);

        $manifest = $this->loadManifest();
        $manifestFiles = $manifest !== array() ? $manifest['files'] : array();
        $archiveRoot = (string) $this->state['archiveRoot'];
        $archive = new ZipArchive();

        if ($archive->open($packageFile) !== true) {
            throw new RuntimeException('The update ZIP archive could not be opened.');
        }

        $totalUncompressedSize = 0;
        $extractedFiles = array();
        $metadataFiles = array(
            'install/update-manifest.json',
            'install/update-manifest.json.sig'
        );

        try {
            for ($index = 0; $index < $archive->numFiles; ++$index) {
                $statistics = $archive->statIndex($index);
                if (!is_array($statistics) || !isset($statistics['name'])) {
                    throw new RuntimeException('An update archive entry could not be read.');
                }

                $archivePath = str_replace('\\', '/', (string) $statistics['name']);
                $this->validateArchivePath($archivePath);

                if (!str_starts_with($archivePath, $archiveRoot)) {
                    throw new RuntimeException('The update archive contains more than one root directory.');
                }

                $relativePath = substr($archivePath, strlen($archiveRoot));
                if ($relativePath === '' || str_ends_with($relativePath, '/')) {
                    continue;
                }

                $this->validateRelativePath($relativePath);
                $this->rejectArchiveLink($archive, $index, $relativePath);

                $size = (int) ($statistics['size'] ?? 0);
                $totalUncompressedSize += $size;
                if ($size < 0 || $totalUncompressedSize > self::MAX_UNCOMPRESSED_SIZE) {
                    throw new RuntimeException('The uncompressed update package is unexpectedly large.');
                }

                if ($manifest !== array() && in_array($relativePath, $metadataFiles, true)) {
                    // These two files are already authenticated and copied to the protected workspace
                    // by the normal application before the isolated updater is installed.
                    continue;
                }

                if ($manifest !== array() && !array_key_exists($relativePath, $manifestFiles)) {
                    throw new RuntimeException('The signed manifest does not declare archive file: ' . $relativePath);
                }

                $targetFile = $destination . '/' . $relativePath;
                $this->createDirectory(dirname($targetFile));

                $source = $archive->getStream($archivePath);
                $target = fopen($targetFile, 'wb');
                if (!is_resource($source) || !is_resource($target)) {
                    if (is_resource($source)) {
                        fclose($source);
                    }
                    if (is_resource($target)) {
                        fclose($target);
                    }
                    throw new RuntimeException('Could not extract update file: ' . $relativePath);
                }

                if (stream_copy_to_stream($source, $target) === false) {
                    fclose($source);
                    fclose($target);
                    throw new RuntimeException('Could not extract update file: ' . $relativePath);
                }
                fflush($target);
                fclose($source);
                fclose($target);

                $hash = $this->calculateFileHash($targetFile);
                if ($manifest !== array()
                    && !hash_equals(strtolower((string) $manifestFiles[$relativePath]), $hash)) {
                    throw new RuntimeException('The extracted file does not match the signed manifest: ' . $relativePath);
                }

                $caseKey = strtolower($relativePath);
                if (isset($extractedFiles[$caseKey])) {
                    throw new RuntimeException('The update archive contains colliding file names: ' . $relativePath);
                }
                $extractedFiles[$caseKey] = true;
            }
        } finally {
            $archive->close();
        }

        if (!is_file($destination . '/index.php')
            || !is_file($destination . '/system/bootstrap/constants.php')
            || !is_file($destination . '/install/update.php')) {
            throw new RuntimeException('The update package does not contain a complete Admidio installation.');
        }

        if ($manifest !== array() && count($manifestFiles) !== count($extractedFiles)) {
            throw new RuntimeException('The update package does not contain all files declared by the signed manifest.');
        }
    }

    private function preflightAndCreateOperations(): void
    {
        $extractedDirectory = $this->workspace . '/extracted';
        $manifest = $this->loadManifest();

        $this->checkTargetPhpVersion($extractedDirectory);
        $this->checkMasterDependencies($extractedDirectory);

        $operations = array();
        $targetFiles = $manifest !== array()
            ? array_keys($manifest['files'])
            : $this->collectExtractedFiles($extractedDirectory);

        sort($targetFiles, SORT_STRING);
        foreach ($targetFiles as $relativePath) {
            $relativePath = str_replace('\\', '/', (string) $relativePath);
            $this->validateRelativePath($relativePath);

            if ($this->isPreservedPath($relativePath)
                || (($this->state['channel'] ?? '') === 'master' && !$this->shouldInstallMasterPath($relativePath))) {
                continue;
            }

            $sourceFile = $extractedDirectory . '/' . $relativePath;
            if (!is_file($sourceFile)) {
                throw new RuntimeException('The prepared update file is missing: ' . $relativePath);
            }

            $targetFile = $this->absoluteTargetPath($relativePath);
            if (is_link($targetFile)) {
                throw new RuntimeException('Core files that are symbolic links cannot be updated automatically: ' . $relativePath);
            }

            $expectedHash = $manifest !== array()
                ? strtolower((string) $manifest['files'][$relativePath])
                : $this->calculateFileHash($sourceFile);

            if (is_file($targetFile) && hash_equals($expectedHash, $this->calculateFileHash($targetFile))) {
                continue;
            }

            $operations[] = array(
                'action' => is_file($targetFile) ? 'replace' : 'create',
                'path' => $relativePath,
                'hash' => $expectedHash
            );
        }

        // For signed releases the target manifest is authoritative for all managed core
        // directories. Any old or unexpected file in those locations is backed up and removed,
        // so a compromised or obsolete core file cannot silently survive a successful update.
        if ($manifest !== array()) {
            foreach ($this->collectUnexpectedManagedFiles($manifest) as $relativePath) {
                $operations[] = array(
                    'action' => 'delete',
                    'path' => $relativePath,
                    'hash' => ''
                );
            }
        }

        if ($operations === array()) {
            // Even if every file already has the target checksum, keep a zero-operation plan so
            // the final integrity verification and database handover can still run.
            $this->writeJsonFile($this->workspace . '/operations.json', array());
            $this->state['currentOperation'] = 0;
            $this->state['totalOperations'] = 0;
            $this->writeState();
            return;
        }

        $requiredSpace = (int) filesize($this->workspace . '/package.zip');
        foreach ($operations as $operation) {
            $sourceFile = $extractedDirectory . '/' . $operation['path'];
            $targetFile = $this->absoluteTargetPath($operation['path']);

            if (is_file($sourceFile)) {
                $requiredSpace += (int) filesize($sourceFile);
            }
            if (is_file($targetFile)) {
                $requiredSpace += (int) filesize($targetFile);
            }

            $this->assertTargetWritable($targetFile);
        }

        $freeSpace = @disk_free_space($this->rootPath);
        if ($freeSpace !== false && $freeSpace < $requiredSpace + 10485760) {
            throw new RuntimeException('There is not enough free disk space to update Admidio safely.');
        }

        $this->writeJsonFile($this->workspace . '/operations.json', $operations);
        $this->state['currentOperation'] = 0;
        $this->state['totalOperations'] = count($operations);
        $this->writeState();
    }

    private function processOperationsBatch(): void
    {
        $operations = $this->readJsonFile($this->workspace . '/operations.json');
        if (!is_array($operations)) {
            throw new RuntimeException('The core update operation list is missing.');
        }

        $journalStatus = array();
        foreach ($this->readJournal() as $entry) {
            $index = (int) ($entry['index'] ?? -1);
            $phase = (string) ($entry['phase'] ?? '');
            if ($index >= 0 && in_array($phase, array('prepared', 'done'), true)) {
                $journalStatus[$index][$phase] = $entry;
            }
        }

        $startIndex = (int) $this->state['currentOperation'];
        $endIndex = min(count($operations), $startIndex + self::FILES_PER_REQUEST);

        for ($index = $startIndex; $index < $endIndex; ++$index) {
            if (!isset($operations[$index]) || !is_array($operations[$index])) {
                throw new RuntimeException('The core update operation list is invalid.');
            }

            if (isset($journalStatus[$index]['done'])) {
                $this->state['currentOperation'] = $index + 1;
                $this->writeState();
                continue;
            }

            if (isset($journalStatus[$index]['prepared'])) {
                $this->restorePreparedOperation($journalStatus[$index]['prepared']);
            }

            $this->performOperation($index, $operations[$index]);
            $journalStatus[$index]['done'] = true;
            $this->state['currentOperation'] = $index + 1;
            $this->writeState();
        }
    }

    /**
     * @param int $index
     * @param array<string,mixed> $operation
     */
    private function performOperation(int $index, array $operation): void
    {
        $action = (string) ($operation['action'] ?? '');
        $relativePath = (string) ($operation['path'] ?? '');
        $this->validateRelativePath($relativePath);

        if (!in_array($action, array('create', 'replace', 'delete'), true)
            || $this->isPreservedPath($relativePath)) {
            throw new RuntimeException('Invalid core update operation.');
        }

        $targetFile = $this->absoluteTargetPath($relativePath);
        $sourceFile = $this->workspace . '/extracted/' . $relativePath;
        $backupRelativePath = '';
        $mode = null;

        if ($action === 'replace' || $action === 'delete') {
            if (!is_file($targetFile) && !is_link($targetFile)) {
                if ($action === 'delete') {
                    return;
                }
                throw new RuntimeException('File to be replaced no longer exists: ' . $relativePath);
            }

            $backupRelativePath = 'backup/' . $relativePath;
            $backupFile = $this->workspace . '/' . $backupRelativePath;
            $this->createDirectory(dirname($backupFile));

            if (!copy($targetFile, $backupFile)
                || !hash_equals($this->calculateFileHash($targetFile), $this->calculateFileHash($backupFile))) {
                throw new RuntimeException('Could not back up file: ' . $relativePath);
            }
            $fileMode = fileperms($targetFile);
            $mode = $fileMode === false ? null : $fileMode & 0777;
        }

        $journalEntry = array(
            'index' => $index,
            'phase' => 'prepared',
            'action' => $action,
            'path' => $relativePath,
            'backup' => $backupRelativePath,
            'mode' => $mode
        );
        $this->appendJournal($journalEntry);

        if ($action === 'delete') {
            if (!@unlink($targetFile)) {
                throw new RuntimeException('Could not delete obsolete core file: ' . $relativePath);
            }
        } else {
            if (!is_file($sourceFile)
                || !hash_equals((string) $operation['hash'], $this->calculateFileHash($sourceFile))) {
                throw new RuntimeException('Prepared update file has changed: ' . $relativePath);
            }
            $this->copyFileAtomically($sourceFile, $targetFile, $mode);
        }

        $journalEntry['phase'] = 'done';
        $this->appendJournal($journalEntry);
    }

    /**
     * @param array<string,mixed> $preparedEntry
     */
    private function restorePreparedOperation(array $preparedEntry): void
    {
        $targetFile = $this->absoluteTargetPath((string) $preparedEntry['path']);
        if (($preparedEntry['action'] ?? '') === 'create') {
            if (is_file($targetFile) || is_link($targetFile)) {
                @unlink($targetFile);
            }
            return;
        }

        $backupFile = $this->workspace . '/' . (string) $preparedEntry['backup'];
        if (!is_file($backupFile)) {
            throw new RuntimeException('Could not recover an interrupted file operation: ' . $preparedEntry['path']);
        }

        $this->copyFileAtomically(
            $backupFile,
            $targetFile,
            isset($preparedEntry['mode']) ? (int) $preparedEntry['mode'] : null
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function loadManifest(): array
    {
        if (($this->state['channel'] ?? '') !== 'stable') {
            return array();
        }

        if (is_array($this->manifest)) {
            return $this->manifest;
        }

        $manifestFile = $this->workspace . '/update-manifest.json';
        $signatureFile = $this->workspace . '/update-manifest.json.sig';
        $publicKeyFile = $this->workspace . '/trusted-public-key.pem';

        $manifestContents = file_get_contents($manifestFile);
        $signatureContents = file_get_contents($signatureFile);
        $publicKey = file_get_contents($publicKeyFile);

        if ($manifestContents === false
            || $signatureContents === false
            || $publicKey === false
            || trim($publicKey) === ''
            || !isset($this->state['manifestSha256'])
            || !hash_equals(strtolower((string) $this->state['manifestSha256']), hash('sha256', $manifestContents))) {
            throw new RuntimeException('The signed update manifest is missing or invalid.');
        }

        $signature = base64_decode(trim($signatureContents), true);
        if ($signature === false
            || openssl_verify($manifestContents, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('The update manifest signature could not be verified.');
        }

        $manifest = json_decode($manifestContents, true);
        if (!is_array($manifest)
            || ($manifest['schema'] ?? null) !== 1
            || ($manifest['version'] ?? '') !== ($this->state['targetVersion'] ?? '')
            || !isset($manifest['files'], $manifest['managedDirectories'], $manifest['preservePaths'], $manifest['manageRootFiles'])
            || !is_array($manifest['files'])
            || !is_array($manifest['managedDirectories'])
            || !is_array($manifest['preservePaths'])
            || !is_bool($manifest['manageRootFiles'])) {
            throw new RuntimeException('The signed update manifest is missing or invalid.');
        }

        $caseInsensitivePaths = array();
        foreach ($manifest['files'] as $path => $hash) {
            $path = (string) $path;
            $this->validateRelativePath($path);
            if (!preg_match('/^[a-f0-9]{64}$/i', (string) $hash)) {
                throw new RuntimeException('The signed update manifest contains an invalid file checksum.');
            }

            $caseKey = strtolower($path);
            if (isset($caseInsensitivePaths[$caseKey])) {
                throw new RuntimeException('The signed update manifest contains colliding file names.');
            }
            $caseInsensitivePaths[$caseKey] = true;
        }

        foreach (array_merge($manifest['managedDirectories'], $manifest['preservePaths']) as $path) {
            if (!is_string($path)) {
                throw new RuntimeException('The signed update manifest contains an invalid path.');
            }
            $this->validateRelativePath($path);
        }

        $this->manifest = $manifest;
        return $manifest;
    }


    private function verifyInstalledRelease(): void
    {
        if (($this->state['channel'] ?? '') !== 'stable') {
            return;
        }

        $manifest = $this->loadManifest();
        foreach ($manifest['files'] as $relativePath => $expectedHash) {
            $relativePath = (string) $relativePath;
            if ($this->isPreservedPath($relativePath)) {
                continue;
            }

            $targetFile = $this->absoluteTargetPath($relativePath);
            if (!is_file($targetFile)
                || is_link($targetFile)
                || !hash_equals(strtolower((string) $expectedHash), $this->calculateFileHash($targetFile))) {
                throw new RuntimeException(
                    'The installed core file does not match the authenticated release: ' . $relativePath
                );
            }
        }

        $unexpectedFiles = $this->collectUnexpectedManagedFiles($manifest);
        if ($unexpectedFiles !== array()) {
            throw new RuntimeException(
                'Unexpected files remain in an Admidio core directory after the update: '
                . implode(', ', array_slice($unexpectedFiles, 0, 5))
            );
        }
    }


    private function checkTargetPhpVersion(string $extractedDirectory): void
    {
        $constants = file_get_contents($extractedDirectory . '/system/bootstrap/constants.php');
        if ($constants === false
            || !preg_match("/const\s+MIN_PHP_VERSION\s*=\s*'([^']+)'/", $constants, $matches)) {
            throw new RuntimeException('The target PHP requirement could not be determined.');
        }

        if (version_compare(PHP_VERSION, $matches[1], '<')) {
            throw new RuntimeException(
                'The target Admidio version requires PHP ' . $matches[1] . ' or newer. Installed: ' . PHP_VERSION
            );
        }
    }

    private function checkMasterDependencies(string $extractedDirectory): void
    {
        if (($this->state['channel'] ?? '') !== 'master') {
            if (!is_file($extractedDirectory . '/vendor/autoload.php')) {
                throw new RuntimeException('The signed release package does not contain its Composer dependencies.');
            }
            return;
        }

        $targetAutoloader = $extractedDirectory . '/vendor/autoload.php';
        if (is_file($targetAutoloader)) {
            return;
        }

        $installedLock = $this->rootPath . '/composer.lock';
        $targetLock = $extractedDirectory . '/composer.lock';
        if (!is_file($installedLock)
            || !is_file($targetLock)
            || !hash_equals($this->calculateFileHash($installedLock), $this->calculateFileHash($targetLock))) {
            throw new RuntimeException(
                'Git master changed Composer dependencies but the GitHub source archive does not contain vendor files. '
                . 'Install a complete master build or update the dependencies manually.'
            );
        }

        if (!is_file($this->rootPath . '/vendor/autoload.php')) {
            throw new RuntimeException('The existing Composer dependencies are missing.');
        }

        $installedComposer = $this->readJsonFile($this->rootPath . '/composer.json');
        $targetComposer = $this->readJsonFile($extractedDirectory . '/composer.json');
        if (!is_array($installedComposer)
            || !is_array($targetComposer)
            || ($installedComposer['autoload'] ?? array()) !== ($targetComposer['autoload'] ?? array())) {
            throw new RuntimeException(
                'Git master changed the root Composer autoload configuration but the source archive '
                . 'does not contain regenerated vendor metadata.'
            );
        }
    }

    /**
     * @return array<int,string>
     */
    private function collectExtractedFiles(string $directory): array
    {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if (!$entry->isFile() || $entry->isLink()) {
                continue;
            }

            $relativePath = substr(str_replace('\\', '/', $entry->getPathname()), strlen($directory) + 1);
            $files[] = $relativePath;
        }

        return $files;
    }


    /**
     * Returns files that currently exist inside release-owned paths but are not part of the
     * authenticated target release. They are handled as normal delete operations and therefore
     * backed up before removal.
     *
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private function collectUnexpectedManagedFiles(array $manifest): array
    {
        $expectedFiles = array_fill_keys(array_keys($manifest['files']), true);
        $unexpectedFiles = array();

        foreach ($manifest['managedDirectories'] as $managedDirectory) {
            $managedDirectory = rtrim(str_replace('\\', '/', (string) $managedDirectory), '/');
            $this->validateRelativePath($managedDirectory);

            if ($this->isPreservedPath($managedDirectory)) {
                continue;
            }

            $absoluteDirectory = $this->absoluteTargetPath($managedDirectory);
            if (!file_exists($absoluteDirectory) && !is_link($absoluteDirectory)) {
                continue;
            }
            if (is_link($absoluteDirectory)) {
                throw new RuntimeException(
                    'A managed core directory is a symbolic link and cannot be updated automatically: '
                    . $managedDirectory
                );
            }
            if (!is_dir($absoluteDirectory)) {
                throw new RuntimeException(
                    'A managed core directory is not a directory: ' . $managedDirectory
                );
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteDirectory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                $relativePath = substr(
                    str_replace('\\', '/', $entry->getPathname()),
                    strlen($this->rootPath) + 1
                );
                $this->validateRelativePath($relativePath);

                if ($this->isPreservedPath($relativePath)) {
                    continue;
                }
                if ($entry->isLink()) {
                    throw new RuntimeException(
                        'A managed core path is a symbolic link and cannot be updated automatically: '
                        . $relativePath
                    );
                }
                if ($entry->isFile() && !isset($expectedFiles[$relativePath])) {
                    $unexpectedFiles[$relativePath] = true;
                }
            }
        }

        if (($manifest['manageRootFiles'] ?? false) === true) {
            $rootEntries = scandir($this->rootPath);
            if ($rootEntries === false) {
                throw new RuntimeException('The Admidio root directory could not be inspected.');
            }

            foreach ($rootEntries as $entryName) {
                if ($entryName === '.' || $entryName === '..') {
                    continue;
                }

                $absolutePath = $this->rootPath . '/' . $entryName;
                if (is_dir($absolutePath) && !is_link($absolutePath)) {
                    continue;
                }

                $this->validateRelativePath($entryName);
                if ($this->isPreservedPath($entryName)) {
                    continue;
                }
                if (is_link($absolutePath)) {
                    throw new RuntimeException(
                        'A managed Admidio root path is a symbolic link and cannot be updated automatically: '
                        . $entryName
                    );
                }
                if (is_file($absolutePath) && !isset($expectedFiles[$entryName])) {
                    $unexpectedFiles[$entryName] = true;
                }
            }
        }

        $unexpectedFiles = array_keys($unexpectedFiles);
        sort($unexpectedFiles, SORT_STRING);

        return $unexpectedFiles;
    }


    private function isPreservedPath(string $relativePath): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $preservePaths = array(
            'adm_my_files',
            'config.php',
            'custom_css.css',
            'custom_favicon.png',
            'custom_logo.png',
            '.htaccess',
            '.user.ini',
            'robots.txt',
            rtrim(self::INSTALLER_RELATIVE_PATH, '/')
        );

        if (($this->state['channel'] ?? '') === 'stable') {
            $manifest = $this->loadManifest();
            foreach ($manifest['preservePaths'] as $preservePath) {
                $preservePaths[] = trim(str_replace('\\', '/', (string) $preservePath), '/');
            }
        }

        foreach (array_unique($preservePaths) as $preservePath) {
            if ($preservePath !== ''
                && ($relativePath === $preservePath || str_starts_with($relativePath, $preservePath . '/'))) {
                return true;
            }
        }

        return false;
    }

    private function shouldInstallMasterPath(string $relativePath): bool
    {
        $firstSegment = explode('/', $relativePath, 2)[0];
        $excludedRootEntries = array(
            '.git',
            '.github',
            '.vscode',
            '.idea',
            '.codeclimate.yml',
            '.dockerignore',
            '.editorconfig',
            '.gitattributes',
            '.gitignore',
            '.php-cs-fixer.dist.php',
            '.travis.yml',
            'Dockerfile',
            'build-release.php',
            'README-Docker.md',
            'demo_data',
            'dockerscripts',
            'example-hooks',
            'hooks'
        );

        return !in_array($firstSegment, $excludedRootEntries, true);
    }

    private function assertTargetWritable(string $targetFile): void
    {
        $directory = dirname($targetFile);
        while (!is_dir($directory) && $directory !== dirname($directory)) {
            $directory = dirname($directory);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('The target directory is not writable: ' . $directory);
        }

        if (is_file($targetFile) && !is_readable($targetFile)) {
            throw new RuntimeException('The core file cannot be read for backup: ' . $targetFile);
        }
    }

    private function enableMaintenanceMode(): void
    {
        $maintenanceFile = $this->rootPath . '/adm_my_files/maintenance.json';
        $maintenanceOwner = 'core-update:' . $this->updateId;
        $existingMaintenance = $this->readJsonFile($maintenanceFile);

        if (is_file($maintenanceFile)
            && (!is_array($existingMaintenance)
                || (($existingMaintenance['owner'] ?? '') !== ''
                    && ($existingMaintenance['owner'] ?? '') !== $maintenanceOwner))) {
            throw new RuntimeException('Another Admidio maintenance operation is already active.');
        }

         $this->writeJsonFile($maintenanceFile, array(
            'schema' => 1,
            'owner' => $maintenanceOwner,
            'startedAt' => time(),
            'retryAfter' => 120,
            'title' => $this->getText('title'),
            'message' => $this->getText('maintenanceMessage'),
            'allowedScripts' => array(
                'install/core-update/index.php',
                'install/update.php'
            )
         ));
    }

    private function disableMaintenanceMode(): void
    {
        $maintenanceFile = $this->rootPath . '/adm_my_files/maintenance.json';
        $maintenanceData = $this->readJsonFile($maintenanceFile);

        if (is_array($maintenanceData)
            && ($maintenanceData['owner'] ?? '') === 'core-update:' . $this->updateId) {
            @unlink($maintenanceFile);
        }
    }

    private function getDatabaseUpdateUrl(): string
    {
        return $this->config->baseUrl
            . '/install/update.php?core_update=' . rawurlencode($this->updateId);
    }

    private function getSelfUrl(string $action = ''): string
    {
        $url = $this->config->baseUrl
            . '/install/core-update/index.php?update_id=' . rawurlencode($this->updateId);

        if ($action !== '') {
            $url .= '&action=' . rawurlencode($action);
        }

        return $url;
    }

    private function calculateProgress(): int
    {
        $state = (string) ($this->state['state'] ?? '');
        if ($state === 'prepared') {
            return 5;
        }
        if ($state === 'extracted') {
            return 20;
        }
        if ($state === 'ready') {
            return 30;
        }
        if (in_array($state, array('files_installed', 'database_running', 'database_failed'), true)) {
            return 95;
        }
        if ($state === 'completed') {
            return 100;
        }
        if ($state === 'installing') {
            $total = max(1, (int) ($this->state['totalOperations'] ?? 0));
            $current = (int) ($this->state['currentOperation'] ?? 0);
            return min(94, 30 + (int) floor(($current / $total) * 64));
        }

        return 0;
    }

    /**
     * @param string $message
     * @return array<string,mixed>
     */
    private function successResponse(string $message): array
    {
        return array(
            'status' => 'success',
            'message' => $message,
            'progress' => $this->calculateProgress(),
            'state' => (string) $this->state['state']
        );
    }

    private function setState(string $state): void
    {
        $this->state['state'] = $state;
        $this->state['lastError'] = '';
        $this->writeState();
    }

    /**
     * @return array<string,mixed>
     */
    private function readState(): array
    {
        return $this->store->read($this->updateId);
    }

    private function writeState(): void
    {
        $this->store->write($this->state);
    }

    /**
     * @param string $file
     * @return mixed
     */
    private function readJsonFile(string $file): mixed
    {
        if (!is_file($file)) {
            return null;
        }

        try {
            return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param string $file
     * @param array<mixed> $data
     */
    private function writeJsonFile(string $file, array $data, bool $keepPrevious = false): void
    {
        $this->createDirectory(dirname($file));
        $temporaryFile = $file . '.new';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $handle = fopen($temporaryFile, 'wb');
        if (!is_resource($handle)
            || fwrite($handle, $json) !== strlen($json)
            || !fflush($handle)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($temporaryFile);
            throw new RuntimeException('Could not persist the core update state.');
        }

        if (function_exists('fsync')) {
            @fsync($handle);
        }
        fclose($handle);

        if ($keepPrevious && is_file($file)) {
            @copy($file, $file . '.previous');
        }

        if (!@rename($temporaryFile, $file)) {
            if (is_file($file) && !@unlink($file)) {
                @unlink($temporaryFile);
                throw new RuntimeException('Could not replace the core update state.');
            }
            if (!@rename($temporaryFile, $file)) {
                @unlink($temporaryFile);
                throw new RuntimeException('Could not replace the core update state.');
            }
        }
    }

    /**
     * journal.jsonl is an append-only JSON Lines file. Each line is an independently valid JSON
     * record, so a timeout cannot corrupt all earlier operations by interrupting a full-array rewrite.
     *
     * @param array<string,mixed> $entry
     */
    private function appendJournal(array $entry): void
    {
        $journalFile = $this->workspace . '/journal.jsonl';
        $handle = fopen($journalFile, 'ab');
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        if (!is_resource($handle)
            || !flock($handle, LOCK_EX)
            || fwrite($handle, $line) !== strlen($line)
            || !fflush($handle)) {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                fclose($handle);
            }
            throw new RuntimeException('Could not write the core update journal.');
        }

        if (function_exists('fsync')) {
            @fsync($handle);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readJournal(): array
    {
        $journalFile = $this->workspace . '/journal.jsonl';
        if (!is_file($journalFile)) {
            return array();
        }

        $lines = file($journalFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Could not read the core update journal.');
        }

        $entries = array();
        $lastLine = count($lines) - 1;

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                // A timeout can only leave the last appended line incomplete.
                if ($lineNumber === $lastLine) {
                    break;
                }
                throw new RuntimeException('The core update journal is invalid.');
            }

            if (!is_array($entry)) {
                throw new RuntimeException('The core update journal is invalid.');
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    private function calculateFileHash(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new RuntimeException('Could not calculate the file checksum: ' . $file);
        }

        return $hash;
    }

    private function copyFileAtomically(string $sourceFile, string $targetFile, ?int $mode): void
    {
        $this->createDirectory(dirname($targetFile));
        $temporaryFile = dirname($targetFile)
            . '/.' . basename($targetFile) . '.admidio-update-' . substr($this->updateId, 0, 8) . '.tmp';

        if (!copy($sourceFile, $temporaryFile)
            || !hash_equals($this->calculateFileHash($sourceFile), $this->calculateFileHash($temporaryFile))) {
            @unlink($temporaryFile);
            throw new RuntimeException('Could not prepare target file: ' . $targetFile);
        }

        if ($mode !== null) {
            @chmod($temporaryFile, $mode);
        }

        if ((is_file($targetFile) || is_link($targetFile)) && !@unlink($targetFile)) {
            @unlink($temporaryFile);
            throw new RuntimeException('Could not replace target file: ' . $targetFile);
        }

        if (!@rename($temporaryFile, $targetFile)) {
            @unlink($temporaryFile);
            throw new RuntimeException('Could not activate target file: ' . $targetFile);
        }
    }

    private function absoluteTargetPath(string $relativePath): string
    {
        $this->validateRelativePath($relativePath);
        return $this->rootPath . '/' . $relativePath;
    }

    private function validateArchivePath(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[a-zA-Z]:\//', $path)
            || str_contains($path, "\0")
            || in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('The update archive contains an unsafe path.');
        }
    }

    private function validateRelativePath(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[a-zA-Z]:\//', $path)
            || str_contains($path, "\0")
            || in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('Invalid relative core update path.');
        }
    }

    private function rejectArchiveLink(ZipArchive $archive, int $index, string $relativePath): void
    {
        $operatingSystem = 0;
        $attributes = 0;
        if ($archive->getExternalAttributesIndex($index, $operatingSystem, $attributes)
            && $operatingSystem === ZipArchive::OPSYS_UNIX) {
            $fileType = ($attributes >> 16) & 0170000;
            if ($fileType === 0120000) {
                throw new RuntimeException('Symbolic links are not allowed in the update archive: ' . $relativePath);
            }
        }
    }

    private function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create directory: ' . $directory);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
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

    private function acquireLock(): void
    {
        $this->store->acquire(false);
    }

    private function releaseLock(): void
    {
        $this->store->release();
    }
}
