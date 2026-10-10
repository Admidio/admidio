<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use Throwable;

/**
 * Phase A of an update: from the choice of the channel to a verified package and the updater of the
 * new release in place. Nothing outside install/core-update/ and the workspace is changed.
 *
 * new        - the administrator chooses the channel
 * checking   - key statement, descriptor, version rule and the checks before the download
 * checked    - the administrator sees the offered version and the results and confirms
 * downloading - the package arrives in parts, a few seconds per request
 * downloaded - size and SHA-256 match the descriptor
 * prepared   - the manifest is verified and the updater of the new release replaced this one;
 *              phase B, which that updater runs, starts here
 *
 * A failed check, a network error or a missing requirement can be fixed and tried again. A failed
 * verification cannot: something is wrong with what the server delivered, so the workspace is
 * removed and the update ends there (concept, section 4).
 *
 * Phase A is the old code in an update - it is the updater that is installed - so it stays as small
 * as it can: a bug in it can only be fixed by the next update.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PhaseA
{
    /** The states of phase A. */
    public const STATES = array('new', 'checking', 'checked', 'downloading', 'downloaded', 'prepared');

    /** The channels every installation can use, and those that need debug mode. */
    private const CHANNELS = array('stable');
    private const DEBUG_CHANNELS = array('beta', 'master');

    /** Bytes per range request, and seconds of downloading per request of the browser. */
    private const CHUNK_SIZE = 4194304;
    private const TIME_BUDGET = 8;

    /** Limits of the small files and of the extracted package. */
    private const MAX_SMALL_FILE = 1048576;
    private const MAX_MANIFEST = 16777216;
    private const MAX_EXTRACTED = 1073741824;

    /** The directory of the updater inside a release. */
    private const UPDATER_DIRECTORY = 'install/core-update/';

    /**
     * @param Config $config
     * @param StateStore $store
     * @param HttpClient $http
     * @param Db|null $db **null** if the database cannot be reached.
     * @param Texts $texts
     * @param string $updaterPath Directory of this updater.
     * @param int $chunkSize Bytes per range request; tests use small parts.
     */
    public function __construct(
        private readonly Config $config,
        private readonly StateStore $store,
        private readonly HttpClient $http,
        private readonly ?Db $db,
        private readonly Texts $texts,
        private readonly string $updaterPath,
        private readonly int $chunkSize = self::CHUNK_SIZE
    ) {
    }

    /**
     * The channels this installation may choose from.
     * @param Config $config
     * @return array<int,string>
     */
    public static function getChannels(Config $config): array
    {
        return $config->debug ? array_merge(self::CHANNELS, self::DEBUG_CHANNELS) : self::CHANNELS;
    }

    /**
     * Check the offered release of a channel and run the checks before the download.
     *
     * An error - the channel cannot be reached, the version is older - takes the update back to the
     * choice of the channel, with the error to show.
     * @param string $updateId
     * @param string $channel
     * @return array<string,mixed> The new state.
     */
    public function check(string $updateId, string $channel): array
    {
        return $this->store->withLock(function () use ($updateId, $channel): array {
            $state = $this->store->read($updateId);
            if (!in_array($state['state'], array('new', 'checking', 'checked'), true) || !in_array($channel, self::getChannels($this->config), true)) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }

            $state['state'] = 'checking';
            $state['channel'] = $channel;
            $this->store->write($state);

            try {
                $workspace = $this->store->getWorkspace($updateId);
                $rootKey = $this->readRootKey();
                $keys = $this->loadKeys($rootKey);
                $descriptorJson = $this->http->get($this->config->getChannelBaseUrl() . '/' . $channel . '/release.json', self::MAX_SMALL_FILE);
                $descriptorSignature = $this->http->get($this->config->getChannelBaseUrl() . '/' . $channel . '/release.json.sig', self::MAX_SMALL_FILE);
                $descriptor = Descriptor::fromSigned($descriptorJson, $descriptorSignature, $keys, $channel);

                $installedVersion = Version::ofFiles($this->config->rootPath);
                $databaseVersion = $this->db !== null ? Version::ofDatabase($this->db) : null;
                $freeSpace = @disk_free_space($this->store->getDirectory());
                $checks = (new Preflight($this->config->rootPath))->run($descriptor, $installedVersion, $databaseVersion,
                    $this->config->dbType, $freeSpace === false ? -1 : (int)$freeSpace);

                // From here on the update relies only on what is in its workspace (concept, section 4).
                $this->writeFile($workspace . '/root-key.pem', $rootKey);
                $keys->save($workspace . '/release-keys.json');
                $this->writeFile($workspace . '/release.json', $descriptorJson);
                $this->writeFile($workspace . '/release.json.sig', $descriptorSignature);

                $probeToken = bin2hex(random_bytes(16));
                $this->writeFile($workspace . '/probe.txt', $probeToken);

                $state = array_merge($state, array(
                    'state' => 'checked',
                    'installedVersion' => $installedVersion,
                    'databaseVersion' => $databaseVersion,
                    'targetVersion' => $descriptor->version,
                    'targetVersionText' => $descriptor->versionText,
                    'versionRule' => Version::compare($descriptor->version, $installedVersion) === 0 ? Version::REINSTALL : Version::UPDATE,
                    'packageSize' => $descriptor->packageSize,
                    'packageSha256' => $descriptor->packageSha256,
                    'checks' => $checks,
                    'probeToken' => $probeToken,
                    'lastError' => ''
                ));
            } catch (UpdaterException $exception) {
                $state = array_merge($state, array('state' => 'new', 'lastError' => $this->describe($exception)));
            }

            $this->store->write($state);

            return $state;
        }, false);
    }

    /**
     * Start the download after the administrator confirmed the checked update.
     * @param string $updateId
     * @return array<string,mixed>
     */
    public function confirm(string $updateId): array
    {
        return $this->store->withLock(function () use ($updateId): array {
            $state = $this->store->read($updateId);
            if ($state['state'] !== 'checked' || Preflight::hasErrors((array)($state['checks'] ?? array()))) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }

            $state = array_merge($state, array('state' => 'downloading', 'downloadedBytes' => 0, 'mirror' => 0, 'lastError' => ''));
            @unlink($this->store->getWorkspace($updateId) . '/package.zip');
            $this->store->write($state);

            return $state;
        }, false);
    }

    /**
     * Do the next bounded piece of work: a part of the download, or the verification and the copy
     * of the new updater.
     * @param string $updateId
     * @return array{status: string, message: string, progress: int, state: string}
     */
    public function run(string $updateId): array
    {
        return $this->store->withLock(function () use ($updateId): array {
            $state = $this->store->read($updateId);

            try {
                if ($state['state'] === 'downloading') {
                    $state = $this->download($state);
                } elseif ($state['state'] === 'downloaded') {
                    $state = $this->verifyAndInstallUpdater($state);
                } elseif ($state['state'] !== 'prepared') {
                    throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
                }
            } catch (VerificationFailure $failure) {
                $this->abort($updateId);

                return array('status' => 'failed', 'message' => $this->describe($failure->getPrevious()), 'progress' => 0, 'state' => 'failed');
            } catch (UpdaterException $exception) {
                // The download can be continued, the requirement fixed; the update stays where it is.
                $state['lastError'] = $this->describe($exception);
                $this->store->write($state);

                return array('status' => 'error', 'message' => $state['lastError'], 'progress' => self::progress($state), 'state' => $state['state']);
            }

            $state['lastError'] = '';
            $this->store->write($state);

            return array(
                'status' => $state['state'] === 'prepared' ? 'prepared' : 'success',
                'message' => $this->texts->get($state['state'] === 'prepared' ? 'SYS_CORE_UPDATE_PREPARED' : 'SYS_CORE_UPDATE_DOWNLOADING',
                    array((string)$state['targetVersion'])),
                'progress' => self::progress($state),
                'state' => $state['state']
            );
        }, false);
    }

    /**
     * Give up an update that has not changed anything yet.
     * @param string $updateId
     * @return void
     */
    public function cancel(string $updateId): void
    {
        $this->store->withLock(function () use ($updateId): void {
            if (!in_array($this->store->read($updateId)['state'], self::STATES, true)) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }
            $this->abort($updateId);
        }, false);
    }

    /**
     * The progress of phase A in percent: the download is the long part.
     * @param array<string,mixed> $state
     * @return int
     */
    public static function progress(array $state): int
    {
        return match ($state['state']) {
            'downloading' => 5 + (int)floor(80 * (int)($state['downloadedBytes'] ?? 0) / max(1, (int)($state['packageSize'] ?? 1))),
            'downloaded' => 85,
            'prepared' => 100,
            default => 0
        };
    }

    /**
     * Download the next parts of the package, as many as fit into the time budget. A server that
     * ignores the range sends the whole file at once. When a mirror fails, the next one is used;
     * when all failed, the error is shown and the next request starts with the first again.
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function download(array $state): array
    {
        $workspace = $this->store->getWorkspace($state['updateId']);
        $descriptor = $this->loadDescriptor($workspace, $state['channel']);
        $file = $workspace . '/package.zip';
        $size = $descriptor->packageSize;

        // The file is the truth about what arrived, should a request have ended halfway.
        clearstatcache(true, $file);
        $offset = is_file($file) ? (int)filesize($file) : 0;
        if ($offset > $size) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The download is larger than the descriptor says.'));
        }

        // A web request gets enough time for its part; the command line has no limit to extend, and
        // set_time_limit() would introduce one there.
        if ((int)ini_get('max_execution_time') > 0) {
            @set_time_limit(self::TIME_BUDGET + 60);
        }
        $deadline = microtime(true) + self::TIME_BUDGET;

        while ($offset < $size && microtime(true) < $deadline) {
            $mirror = (int)($state['mirror'] ?? 0);
            $url = $descriptor->packageUrls[$mirror] ?? $descriptor->packageUrls[0];

            try {
                $response = $this->http->getRange($url, $offset, min($this->chunkSize, $size - $offset), $size);
            } catch (UpdaterException $exception) {
                $state['mirror'] = ($mirror + 1) % count($descriptor->packageUrls);
                $state['downloadedBytes'] = $offset;
                if ($state['mirror'] === 0) {
                    throw $exception;
                }
                continue;
            }

            if ($response['status'] === 200) {
                // No range support: this was the whole file.
                $this->writeFile($file, $response['body']);
                $offset = strlen($response['body']);
                break;
            }

            if (@file_put_contents($file, $response['body'], FILE_APPEND | LOCK_EX) !== strlen($response['body'])) {
                throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
            }
            $offset += strlen($response['body']);
            if ($response['body'] === '') {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'The server sent an empty range.', array(''));
            }
        }

        $state['downloadedBytes'] = $offset;
        if ($offset < $size) {
            return $state;
        }

        if ($offset !== $size || !hash_equals($descriptor->packageSha256, (string)hash_file('sha256', $file))) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID',
                'The package does not have the size and SHA-256 the descriptor names.'));
        }
        $state['state'] = 'downloaded';

        return $state;
    }

    /**
     * Verify the manifest of the package, check its requirements and replace this updater with the
     * one of the new release.
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function verifyAndInstallUpdater(array $state): array
    {
        $workspace = $this->store->getWorkspace($state['updateId']);
        $descriptor = $this->loadDescriptor($workspace, $state['channel']);
        $keys = $this->loadWorkspaceKeys($workspace);

        try {
            $zip = ZipReader::open($workspace . '/package.zip', self::MAX_EXTRACTED);
        } catch (UpdaterException $exception) {
            throw new VerificationFailure($exception);
        }

        try {
            $manifestIndex = $zip->find('install/update-manifest.json');
            $signatureIndex = $zip->find('install/update-manifest.json.sig');
            if ($manifestIndex === null || $signatureIndex === null) {
                throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The package contains no signed manifest.'));
            }

            $manifestJson = $zip->read($manifestIndex, self::MAX_MANIFEST);
            $manifestSignature = $zip->read($signatureIndex, self::MAX_SMALL_FILE);
            try {
                $manifest = Manifest::fromSigned($manifestJson, $manifestSignature, $keys, $descriptor);
            } catch (UpdaterException $exception) {
                throw new VerificationFailure($exception);
            }

            $freeSpace = @disk_free_space($this->store->getDirectory());
            $checks = (new Preflight($this->config->rootPath))->runForManifest($manifest, $descriptor->packageSize, $freeSpace === false ? -1 : (int)$freeSpace);
            $state['checks'] = array_merge((array)($state['checks'] ?? array()), $checks);
            foreach ($checks as $check) {
                if ($check['status'] === Preflight::ERROR) {
                    throw new UpdaterException($check['text'], $check['params']);
                }
            }

            $this->writeFile($workspace . '/update-manifest.json', $manifestJson);
            $this->writeFile($workspace . '/update-manifest.json.sig', $manifestSignature);
            $this->installUpdater($zip, $manifest, $workspace . '/updater');
        } finally {
            $zip->close();
        }

        $state['state'] = 'prepared';

        return $state;
    }

    /**
     * Replace install/core-update/ with the updater of the new release.
     *
     * Every file is extracted into the workspace and checked against the manifest first, so that a
     * broken package never leaves half an updater behind. Files of the old updater that the new one
     * does not have are removed. OPcache is told about each file, so that the next request runs the
     * new code even where timestamps are not checked.
     * @param ZipReader $zip
     * @param Manifest $manifest
     * @param string $staging
     * @return void
     */
    private function installUpdater(ZipReader $zip, Manifest $manifest, string $staging): void
    {
        StateStore::deleteDirectory($staging);
        $files = array();

        foreach ($zip->getEntries() as $index => $path) {
            if (!str_starts_with($path, self::UPDATER_DIRECTORY)) {
                continue;
            }
            if (!isset($manifest->files[$path])) {
                throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest does not list ' . $path . '.'));
            }

            $relative = substr($path, strlen(self::UPDATER_DIRECTORY));
            $this->ensureDirectory(dirname($staging . '/' . $relative));
            if (!hash_equals($manifest->files[$path], $zip->extract($index, $staging . '/' . $relative, self::MAX_MANIFEST))) {
                throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', $path . ' does not match the manifest.'));
            }
            $files[] = $relative;
        }

        if (!in_array('index.php', $files, true) || !in_array('lib/bootstrap.php', $files, true)) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_UPDATER_MISSING', 'The package contains no updater.'));
        }

        $target = $this->config->rootPath . '/' . rtrim(self::UPDATER_DIRECTORY, '/');
        foreach ($files as $relative) {
            $this->ensureDirectory(dirname($target . '/' . $relative));
            $temporary = $target . '/' . $relative . '.' . bin2hex(random_bytes(4)) . '.new';
            if (!@copy($staging . '/' . $relative, $temporary) || !@rename($temporary, $target . '/' . $relative)) {
                @unlink($temporary);
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', 'install/core-update/' . $relative . ' cannot be replaced.');
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($target . '/' . $relative, true);
            }
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            $relative = substr(str_replace('\\', '/', $entry->getPathname()), strlen($target) + 1);
            if ($entry->isFile() && !in_array($relative, $files, true)) {
                @unlink($entry->getPathname());
            } elseif ($entry->isDir()) {
                @rmdir($entry->getPathname()); // only succeeds for a directory that is empty now
            }
        }

        StateStore::deleteDirectory($staging);
    }

    /**
     * The newest key statement: the one shipped with this updater, the one on the update server or
     * the highest one this installation has seen. A higher one is remembered.
     * @param string $rootKey
     * @return KeyStatement
     */
    private function loadKeys(string $rootKey): KeyStatement
    {
        $seenFile = $this->store->getDirectory() . '/keys-seen.json';
        $seen = KeyStatement::fromFile($seenFile, $rootKey);

        $fetched = null;
        try {
            $base = $this->config->getChannelBaseUrl();
            $fetched = KeyStatement::fromSigned(
                $this->http->get($base . '/release-keys.json', self::MAX_SMALL_FILE),
                $this->http->get($base . '/release-keys.json.sig', self::MAX_SMALL_FILE),
                $rootKey
            );
        } catch (UpdaterException) {
            // Without the server the shipped and the seen statement are all there is.
        }

        $keys = KeyStatement::newest(array(KeyStatement::fromFile($this->updaterPath . '/release-keys.json', $rootKey), $fetched, $seen));
        if ($seen === null || $keys->sequence > $seen->sequence) {
            $keys->save($seenFile);
        }

        return $keys;
    }

    /**
     * The public root key of the updater.
     * @return string
     */
    private function readRootKey(): string
    {
        $file = $this->config->getRootKeyFile($this->updaterPath);
        $key = is_file($file) ? (string)file_get_contents($file) : '';
        if ($key === '' || @openssl_pkey_get_public($key) === false) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_NOT_AVAILABLE', 'There is no root key: ' . $file);
        }

        return $key;
    }

    /**
     * The key statement in the workspace, verified again with the root key in the workspace.
     * @param string $workspace
     * @return KeyStatement
     */
    private function loadWorkspaceKeys(string $workspace): KeyStatement
    {
        $keys = KeyStatement::fromFile($workspace . '/release-keys.json', (string)@file_get_contents($workspace . '/root-key.pem'));
        if ($keys === null) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_KEYS_INVALID', 'The key statement in the workspace does not verify.'));
        }

        return $keys;
    }

    /**
     * The descriptor in the workspace, verified again.
     * @param string $workspace
     * @param string $channel
     * @return Descriptor
     */
    private function loadDescriptor(string $workspace, string $channel): Descriptor
    {
        try {
            return Descriptor::fromSigned((string)@file_get_contents($workspace . '/release.json'),
                (string)@file_get_contents($workspace . '/release.json.sig'), $this->loadWorkspaceKeys($workspace), $channel);
        } catch (UpdaterException $exception) {
            throw new VerificationFailure($exception);
        }
    }

    /**
     * End an update that never changed anything: remove its workspace, forget it, and remove the
     * updater cookie of this browser.
     * @param string $updateId
     * @return void
     */
    private function abort(string $updateId): void
    {
        StateStore::deleteDirectory($this->store->getWorkspace($updateId));
        $this->store->clearActive($updateId);
        Credentials::clearCookie($this->config);
    }

    /**
     * The message for the administrator, with the English detail for the log.
     * @param Throwable|null $exception
     * @return string
     */
    private function describe(?Throwable $exception): string
    {
        if ($exception instanceof UpdaterException) {
            if ($exception->getDetail() !== '') {
                error_log('Admidio core updater: ' . $exception->getDetail());
            }

            return $exception->getText($this->texts);
        }

        return $exception !== null ? $exception->getMessage() : '';
    }

    /**
     * @param string $file
     * @param string $content
     * @return void
     */
    private function writeFile(string $file, string $content): void
    {
        if (@file_put_contents($file, $content, LOCK_EX) !== strlen($content)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $file . ' cannot be written.');
        }
    }

    /**
     * @param string $directory
     * @return void
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $directory . ' cannot be created.');
        }
    }
}

/**
 * A verification failed: the update ends, and its workspace is removed (concept, section 4).
 */
final class VerificationFailure extends \RuntimeException
{
    public function __construct(UpdaterException $reason)
    {
        parent::__construct($reason->getMessage(), 0, $reason);
    }
}
