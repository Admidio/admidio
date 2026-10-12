<?php
/**
 * Phase A of an update: from the choice of the channel to a verified package with the updater of
 * the new release in place. Nothing outside install/core-update/ and the workspace is changed.
 *
 * new         - the administrator chooses the channel (only with $gDebug, else it is stable)
 * checking    - update.txt, its signature, the version rule and the checks P1-P9 before the download;
 *               a developer channel downloads its source archive here already
 * checked     - the administrator sees the offered version and the results and confirms
 * downloading - the package arrives in parts, a few seconds per request
 * downloaded  - size and SHA-256 match update.txt
 * prepared    - the manifest is read and the updater of the new release replaced this one;
 *               phase B, which that updater runs, starts here
 *
 * A failed check, a network error or a missing requirement can be fixed and tried again. A failed
 * verification cannot: something is wrong with what the server delivered, so the workspace is
 * removed and the update ends there.
 *
 * Every function gets the context of the request: config, pdo (null without a database), texts,
 * updaterPath, http (the options of httpGet()) and chunkSize (bytes per range request).
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use RuntimeException;
use Throwable;

const PHASE_A_STATES = array('new', 'checking', 'checked', 'downloading', 'downloaded', 'prepared');

/**
 * The channels of a release package with a signed update.txt, and the developer channels: GitHub
 * source archives of master, of a branch, a tag or a pull request, unsigned and only with $gDebug.
 */
const RELEASE_CHANNELS = array('stable', 'beta');
const DEVELOPER_CHANNELS = array('master', 'branch', 'tag', 'pr');

/** The largest source archive a developer channel downloads, in one request. */
const MAX_ARCHIVE_SIZE = 157286400;

/** Bytes per range request, and seconds of downloading per request of the browser. */
const CHUNK_SIZE = 4194304;
const TIME_BUDGET = 8;

const MAX_MANIFEST_SIZE = 16777216;
const MAX_EXTRACTED_SIZE = 1073741824;

/** What an installation without the manifest of its release manages (P9). */
const DEFAULT_MANAGED_DIRECTORIES = array('src', 'modules', 'system', 'install', 'languages', 'libs', 'rss', 'vendor', 'themes/simple');
const DEFAULT_MANAGED_ROOT_FILES = array('index.php', 'admidio', 'sw.js', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', 'SECURITY.md');

/** A verification failed: the update ends, and its workspace is removed. */
final class VerificationFailure extends RuntimeException
{
    public function __construct(UpdaterException $reason)
    {
        parent::__construct($reason->getMessage(), 0, $reason);
    }
}

/**
 * The channels this installation may choose from: stable, and with $gDebug also beta and the
 * developer channels.
 * @param array<string,mixed> $config
 * @return array<int,string>
 */
function availableChannels(array $config): array
{
    return $config['debug'] ? array_merge(RELEASE_CHANNELS, DEVELOPER_CHANNELS) : array('stable');
}

function isDeveloperChannel(string $channel): bool
{
    return in_array($channel, DEVELOPER_CHANNELS, true);
}

/**
 * The address of the source archive of a developer channel. The updater builds it itself from the
 * name the administrator entered: a branch or tag name of plain characters, or the number of a pull
 * request (forks included). master needs no name.
 * @param array<string,mixed> $config
 */
function sourceArchiveUrl(array $config, string $channel, string $ref): string
{
    $valid = match ($channel) {
        'master' => $ref === '',
        'branch', 'tag' => strlen($ref) <= 200 && !str_contains($ref, '..') && !str_ends_with($ref, '.lock') && !str_ends_with($ref, '.')
            && preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*$#', $ref) === 1,
        'pr' => preg_match('/^[1-9][0-9]{0,6}$/', $ref) === 1,
        default => false
    };
    if (!$valid) {
        throw new UpdaterException('SYS_CORE_UPDATE_REF_INVALID');
    }

    $base = rtrim($config['archiveUrl'] !== '' ? $config['archiveUrl'] : SOURCE_ARCHIVE_URL, '/') . '/';
    $name = implode('/', array_map('rawurlencode', explode('/', $ref)));

    return $base . match ($channel) {
        'master' => 'refs/heads/master.zip',
        'branch' => 'refs/heads/' . $name . '.zip',
        'tag' => 'refs/tags/' . $name . '.zip',
        default => 'refs/pull/' . $ref . '/head.zip'
    };
}

/**
 * How a developer package is named: the channel and its branch, tag or pull request, for the page
 * and for the name of the kept-files archive.
 * @param array<string,mixed> $state
 */
function developerSource(array $state): string
{
    return match ((string)$state['channel']) {
        'master' => 'master',
        'pr' => 'pr' . $state['ref'],
        default => (string)$state['ref']
    };
}

/**
 * Check the release a channel offers and run the checks before the download. An error - the
 * server cannot be reached, the signature does not match - takes the update back to the choice of
 * the channel, with the error to show.
 * @param array<string,mixed> $ctx
 * @param string $ref The branch, the tag or the number of the pull request of a developer channel.
 * @return array<string,mixed> The new state.
 */
function phaseACheck(array $ctx, string $updateId, string $channel, string $ref = ''): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId, $channel, $ref): array {
        $state = readState($root, $updateId);
        if (!in_array($state['state'], array('new', 'checking', 'checked'), true) || !in_array($channel, availableChannels($ctx['config']), true)) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        $state['state'] = 'checking';
        $state['channel'] = $channel;
        $state['ref'] = $channel === 'master' ? '' : trim($ref);
        writeState($root, $state);

        try {
            $installedVersion = filesVersion($root);
            $databaseVersion = $ctx['pdo'] !== null ? databaseVersion($ctx['pdo'], $ctx['config']['tablePrefix']) : null;
            $freeSpace = @disk_free_space(updatesDirectory($root));
            $freeSpace = $freeSpace === false ? -1 : (int)$freeSpace;

            $state = isDeveloperChannel($channel)
                ? developerCheck($ctx, $state, $installedVersion, $databaseVersion, $freeSpace)
                : releaseCheck($ctx, $state, $installedVersion, $databaseVersion, $freeSpace);

            $probeToken = bin2hex(random_bytes(16));
            writeFile(workspace($root, $updateId) . '/probe.txt', $probeToken);
            $state = array_merge($state, array(
                'state' => 'checked',
                'installedVersion' => $installedVersion,
                'databaseVersion' => $databaseVersion,
                'probeToken' => $probeToken,
                'lastError' => ''
            ));
        } catch (UpdaterException $exception) {
            @unlink(workspace($root, $updateId) . '/package.zip');
            $state = array_merge($state, array('state' => 'new', 'lastError' => describeError($ctx, $exception)));
        }

        writeState($root, $state);
        updateLog($root, $updateId, 'check of channel ' . $channel . ($state['ref'] !== '' ? ' ' . $state['ref'] : '') . ': '
            . ($state['state'] === 'checked' ? $state['installedVersion'] . ' -> ' . $state['targetVersionText']
                : 'failed: ' . (isset($exception) ? errorDetail($exception) : '')));

        return $state;
    }, false);
}

/**
 * The check of a release channel: update.txt and its signature, then the checks P1-P9.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function releaseCheck(array $ctx, array $state, string $installedVersion, ?string $databaseVersion, int $freeSpace): array
{
    $root = $ctx['config']['rootPath'];
    $info = fetchUpdateInfo($ctx['config'], $ctx['updaterPath'], $ctx['http']);
    $release = releaseFromUpdateInfo(parseUpdateInfo($info['text']), (string)$state['channel']);
    $checks = preflightChecks($root, $release, $installedVersion, $databaseVersion, $ctx['config']['dbType'], $freeSpace);

    // From here on the update relies only on what is in its workspace.
    $workspace = workspace($root, $state['updateId']);
    writeFile($workspace . '/release-key.pem', $info['key']);
    writeFile($workspace . '/update.txt', $info['text']);
    writeFile($workspace . '/update.txt.sig', $info['signature']);

    return array_merge($state, array(
        'targetVersion' => $release['version'],
        'targetVersionText' => $release['versionText'],
        'versionRule' => version_compare($release['version'], $installedVersion) === 0 ? 'reinstall' : 'update',
        'packageSize' => $release['packageSize'],
        'packageSha256' => $release['packageSha256'],
        'checks' => $checks
    ));
}

/**
 * The check of a developer channel. Only the source archive itself names its version and commit,
 * so it is downloaded now, in one request, and the administrator confirms exactly this file: a
 * later push to the branch is not installed by surprise.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function developerCheck(array $ctx, array $state, string $installedVersion, ?string $databaseVersion, int $freeSpace): array
{
    $root = $ctx['config']['rootPath'];
    $file = workspace($root, $state['updateId']) . '/package.zip';
    $url = sourceArchiveUrl($ctx['config'], (string)$state['channel'], (string)$state['ref']);
    writeFile($file, httpGet($url, MAX_ARCHIVE_SIZE, $ctx['http']));
    $source = readSourceArchive($file);

    $release = array('version' => $source['version'], 'minimumSourceVersion' => '0.0.0', 'minimumPhpVersion' => $source['minimumPhpVersion'],
        'packageSize' => (int)filesize($file));
    $checks = preflightChecks($root, $release, $installedVersion, $databaseVersion, $ctx['config']['dbType'], $freeSpace, true);
    $installedLock = is_file($root . '/composer.lock') ? (string)hash_file('sha256', $root . '/composer.lock') : '';
    $composerChanged = $source['composerLock'] !== '' && !hash_equals($installedLock, $source['composerLock']);
    $checks[] = array('check' => 'G1', 'status' => 'warning', 'text' => 'SYS_CORE_UPDATE_CHECK_DEVELOPER', 'params' => array($url));
    if (!$source['hasUpdater']) {
        // A version from before the core updater, or a branch without it: the installed updater
        // installs its files and stays, and the database is updated by hand.
        $checks[] = array('check' => 'G3', 'status' => 'warning', 'text' => 'SYS_CORE_UPDATE_CHECK_WITHOUT_UPDATER', 'params' => array());
    }
    if ($composerChanged) {
        $checks[] = array('check' => 'G2', 'status' => 'warning', 'text' => 'SYS_CORE_UPDATE_CHECK_COMPOSER', 'params' => array());
    }

    $state = array_merge($state, array('commit' => $source['commit']));

    return array_merge($state, array(
        'targetVersion' => $source['version'],
        'targetVersionText' => $source['version'] . ' (' . developerSource($state) . ($source['commit'] !== '' ? ' ' . substr($source['commit'], 0, 7) : '') . ')',
        'versionRule' => 'developer',
        'withoutUpdater' => !$source['hasUpdater'],
        'packageSize' => $release['packageSize'],
        'packageSha256' => (string)hash_file('sha256', $file),
        'composerChanged' => $composerChanged,
        'checks' => $checks
    ));
}

/**
 * What a source archive tells about itself: the version of its constants, the commit GitHub writes
 * into the ZIP comment, the PHP version of its composer.json, the SHA-256 of its composer.lock and
 * whether it contains the updater, which runs phase B.
 * @return array{version: string, commit: string, minimumPhpVersion: string, composerLock: string, hasUpdater: bool}
 */
function readSourceArchive(string $file): array
{
    $package = openPackage($file, MAX_EXTRACTED_SIZE);
    try {
        $read = static function (string $path) use ($package): string {
            $index = packageFind($package, $path);

            return $index === null ? '' : packageRead($package, $index, MAX_MANIFEST_SIZE);
        };

        $version = versionFromConstants($read('system/bootstrap/constants.php'));
        if ($version === null) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The source archive names no version in system/bootstrap/constants.php.');
        }
        $composer = json_decode($read('composer.json'), true);
        $php = is_string($composer['require']['php'] ?? null) && preg_match('/(\d+\.\d+(?:\.\d+)?)/', $composer['require']['php'], $matches) === 1
            ? $matches[1] : '0';
        $lock = $read('composer.lock');
        $comment = trim((string)$package['zip']->getArchiveComment());

        return array(
            'version' => $version,
            'commit' => preg_match('/^[0-9a-f]{40}$/', $comment) === 1 ? $comment : '',
            'minimumPhpVersion' => $php,
            'composerLock' => $lock !== '' ? hash('sha256', $lock) : '',
            'hasUpdater' => packageFind($package, 'install/core-update/index.php') !== null
                && packageFind($package, 'install/core-update/lib/bootstrap.php') !== null
        );
    } finally {
        $package['zip']->close();
    }
}

/**
 * The manifest of a source archive, which has none of its own. It lists what a release made from
 * the archive would contain - the files that the archive.exclude rule of its composer.json leaves
 * in, the rule of the release builder - except vendor/, which a source archive does not have. The
 * hashes are those of the archive: the integrity of the copy, not the authenticity of the code.
 * "developer" tells phase B that nothing is removed.
 * @param array{zip: \ZipArchive, entries: array<int,string>} $package
 * @return array<string,mixed>
 */
function developerManifest(array $package, string $version): array
{
    $composerIndex = packageFind($package, 'composer.json');
    $composer = $composerIndex === null ? null : json_decode(packageRead($package, $composerIndex, MAX_MANIFEST_SIZE), true);
    $patterns = array_values(array_filter((array)($composer['archive']['exclude'] ?? array()), 'is_string'));

    $files = array();
    $totalSize = 0;
    foreach ($package['entries'] as $index => $path) {
        if (str_starts_with($path, 'vendor/') || $path === 'install/update-manifest.json' || isArchiveExcluded($path, $patterns)) {
            continue;
        }

        $hash = hash_init('sha256');
        packageCopy($package, $index, MAX_EXTRACTED_SIZE, static function (string $chunk) use ($hash, &$totalSize): void {
            hash_update($hash, $chunk);
            $totalSize += strlen($chunk);
        });
        $files[$path] = hash_final($hash);
    }
    ksort($files, SORT_STRING);
    [$directories, $rootFiles] = managedPaths(array_keys($files));
    $migrationIndex = packageFind($package, 'install/db_scripts/migration-paths.json');

    $require = is_array($composer['require'] ?? null) ? $composer['require'] : array();
    $php = is_string($require['php'] ?? null) && preg_match('/(\d+\.\d+(?:\.\d+)?)/', $require['php'], $matches) === 1 ? $matches[1] : '0';
    $extensions = array_values(array_map(static fn(string $name): string => substr($name, 4),
        array_filter(array_keys($require), static fn(mixed $name): bool => is_string($name) && str_starts_with($name, 'ext-'))));

    return array(
        'version' => $version,
        'developer' => true,
        'managedDirectories' => $directories,
        'managedRootFiles' => $rootFiles,
        'preservePaths' => array(),
        'migrationPaths' => readMigrationPaths($migrationIndex === null ? '' : packageRead($package, $migrationIndex, MAX_MANIFEST_SIZE)),
        'requirements' => array('php' => '>=' . $php, 'extensions' => $extensions),
        'totalSize' => $totalSize,
        'files' => $files
    );
}

/**
 * Whether the archive.exclude rule of composer.json leaves a path out of a release: the last
 * matching pattern decides, "!" includes again, a pattern without a slash matches a name at any
 * depth unless it starts with "/", and a pattern matches the path or one of its parent directories.
 * The release builder uses this as well.
 * @param array<int,string> $patterns
 */
function isArchiveExcluded(string $path, array $patterns): bool
{
    $excluded = false;
    $parts = explode('/', $path);
    foreach ($patterns as $pattern) {
        $negated = str_starts_with($pattern, '!');
        $pattern = $negated ? substr($pattern, 1) : $pattern;
        $anchored = str_starts_with($pattern, '/') || str_contains(trim($pattern, '/'), '/');
        $pattern = trim($pattern, '/');
        $matches = false;
        for ($depth = 1; $depth <= count($parts) && !$matches; $depth++) {
            $prefix = implode('/', array_slice($parts, 0, $depth));
            $matches = fnmatch($pattern, $prefix, FNM_PATHNAME)
                || (!$anchored && fnmatch($pattern, $parts[$depth - 1]));
        }
        if ($matches) {
            $excluded = !$negated;
        }
    }

    return $excluded;
}

/**
 * The managed directories and root files of a release, from the paths of its files: every top
 * directory except adm_my_files, but in themes/ and plugins/ only the official theme and plugins,
 * so that others there are left alone. The release builder uses this as well.
 * @param array<int,string> $paths
 * @return array{0: array<int,string>, 1: array<int,string>} directories and root files
 */
function managedPaths(array $paths): array
{
    $directories = array();
    $rootFiles = array();
    foreach ($paths as $path) {
        $parts = explode('/', $path);
        if (count($parts) === 1) {
            $rootFiles[] = $path;
        } elseif ($parts[0] === 'themes' || $parts[0] === 'plugins') {
            if (count($parts) > 2) {
                $directories[$parts[0] . '/' . $parts[1]] = true;
            }
        } elseif ($parts[0] !== 'adm_my_files') {
            $directories[$parts[0]] = true;
        }
    }

    return array(array_keys($directories), $rootFiles);
}

/**
 * The texts of the updater in a source archive, language => JSON, as the release builder writes
 * them to install/core-update/texts/: until phase B has installed the language files of the new
 * version, the installed ones lack its texts.
 * @param array{zip: \ZipArchive, entries: array<int,string>} $package
 * @return array<string,string>
 */
function developerTexts(array $package): array
{
    $texts = array();
    foreach ($package['entries'] as $index => $path) {
        if (preg_match('#^languages/([a-z]{2,3}(?:-[A-Za-z]{2,4})?)\.xml$#', $path, $matches) === 1) {
            $texts[$matches[1]] = json_encode(languageFileTexts(packageRead($package, $index, MAX_MANIFEST_SIZE)),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }

    return $texts;
}

/**
 * Start the download after the administrator confirmed the checked update. The source archive of a
 * developer channel is already there.
 * @param array<string,mixed> $ctx
 * @return array<string,mixed>
 */
function phaseAConfirm(array $ctx, string $updateId): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($root, $updateId): array {
        $state = readState($root, $updateId);
        if ($state['state'] !== 'checked' || checksHaveErrors((array)($state['checks'] ?? array()))) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }

        if (isDeveloperChannel((string)$state['channel'])) {
            $state = array_merge($state, array('state' => 'downloaded', 'downloadedBytes' => $state['packageSize'], 'lastError' => ''));
        } else {
            @unlink(workspace($root, $updateId) . '/package.zip');
            $state = array_merge($state, array('state' => 'downloading', 'downloadedBytes' => 0, 'lastError' => ''));
        }
        writeState($root, $state);

        return $state;
    }, false);
}

/**
 * Do the next bounded piece of work: a part of the download, or the verification and the copy of
 * the new updater.
 * @param array<string,mixed> $ctx
 * @return array{status: string, message: string, progress: int, state: string}
 */
function phaseARun(array $ctx, string $updateId): array
{
    $root = $ctx['config']['rootPath'];

    return withLock($root, static function () use ($ctx, $root, $updateId): array {
        $state = readState($root, $updateId);

        try {
            $state = match ($state['state']) {
                'downloading' => downloadPart($ctx, $state),
                'downloaded' => verifyAndInstallUpdater($ctx, $state),
                'prepared' => $state,
                default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
            };
        } catch (VerificationFailure $failure) {
            updateLog($root, $updateId, 'verification failed in ' . $state['state'] . ': ' . errorDetail($failure->getPrevious()));
            abortUpdate($ctx, $updateId);

            return array('status' => 'failed', 'message' => describeError($ctx, $failure->getPrevious()), 'progress' => 0, 'state' => 'failed');
        } catch (UpdaterException $exception) {
            // The download can be continued, the requirement fixed; the update stays where it is.
            $state['lastError'] = describeError($ctx, $exception);
            writeState($root, $state);
            updateLog($root, $updateId, 'error in ' . $state['state'] . ': ' . errorDetail($exception));

            return array('status' => 'error', 'message' => $state['lastError'], 'progress' => phaseAProgress($state), 'state' => $state['state']);
        }

        $state['lastError'] = '';
        writeState($root, $state);
        $prepared = $state['state'] === 'prepared';
        if ($prepared) {
            updateLog($root, $updateId, 'state prepared: package verified, updater of ' . $state['targetVersion'] . ' installed');
        }

        return array(
            'status' => $prepared ? 'prepared' : 'success',
            'message' => text($ctx['texts'], $prepared ? 'SYS_CORE_UPDATE_PREPARED' : 'SYS_CORE_UPDATE_DOWNLOADING', array((string)$state['targetVersion'])),
            'progress' => phaseAProgress($state),
            'state' => $state['state']
        );
    }, false);
}

/**
 * Give up an update that has not changed anything yet.
 * @param array<string,mixed> $ctx
 */
function phaseACancel(array $ctx, string $updateId): void
{
    withLock($ctx['config']['rootPath'], static function () use ($ctx, $updateId): void {
        if (!in_array(readState($ctx['config']['rootPath'], $updateId)['state'], PHASE_A_STATES, true)) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
        abortUpdate($ctx, $updateId);
    }, false);
}

/**
 * The progress of phase A in percent; the download is the long part.
 * @param array<string,mixed> $state
 */
function phaseAProgress(array $state): int
{
    return match ($state['state']) {
        'downloading' => 5 + (int)floor(80 * (int)($state['downloadedBytes'] ?? 0) / max(1, (int)($state['packageSize'] ?? 1))),
        'downloaded' => 85,
        'prepared' => 100,
        default => 0
    };
}

/**
 * The release as update.txt in the workspace describes it, verified again with the key in the
 * workspace.
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function workspaceRelease(string $workspace, array $state): array
{
    $text = (string)@file_get_contents($workspace . '/update.txt');
    if (!verifySignature($text, (string)@file_get_contents($workspace . '/update.txt.sig'), (string)@file_get_contents($workspace . '/release-key.pem'))) {
        throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_SIGNATURE_INVALID', 'update.txt in the workspace does not verify.'));
    }

    try {
        return releaseFromUpdateInfo(parseUpdateInfo($text), (string)$state['channel']);
    } catch (UpdaterException $exception) {
        throw new VerificationFailure($exception);
    }
}

/**
 * The SHA-256 the package in the workspace must have: the one of the signed update.txt, or for a
 * developer channel the one of the source archive the administrator confirmed.
 * @param array<string,mixed> $state
 */
function confirmedPackageHash(string $workspace, array $state): string
{
    return isDeveloperChannel((string)$state['channel']) ? (string)$state['packageSha256'] : workspaceRelease($workspace, $state)['packageSha256'];
}

/**
 * Download the next parts of the package, as many as fit into the time budget. The file is the
 * truth about what arrived, should a request have ended halfway. A server that ignores the range
 * sends the whole file at once.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function downloadPart(array $ctx, array $state): array
{
    $workspace = workspace($ctx['config']['rootPath'], $state['updateId']);
    $release = workspaceRelease($workspace, $state);
    $file = $workspace . '/package.zip';
    $size = $release['packageSize'];

    clearstatcache(true, $file);
    $offset = is_file($file) ? (int)filesize($file) : 0;
    if ($offset > $size) {
        throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The download is larger than update.txt says.'));
    }

    $deadline = startTimeBudget(TIME_BUDGET);

    while ($offset < $size && microtime(true) < $deadline) {
        $response = httpGetRange($release['packageUrl'], $offset, min($ctx['chunkSize'], $size - $offset), $size, $ctx['http']);
        if ($response['status'] === 200) {
            // No range support: this was the whole file.
            writeFile($file, $response['body']);
            $offset = strlen($response['body']);
            break;
        }
        if ($response['body'] === '') {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'The server sent an empty range.', array(''));
        }
        if (@file_put_contents($file, $response['body'], FILE_APPEND | LOCK_EX) !== strlen($response['body'])) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
        $offset += strlen($response['body']);
    }

    $state['downloadedBytes'] = $offset;
    if ($offset < $size) {
        return $state;
    }

    if ($offset !== $size || !hash_equals($release['packageSha256'], (string)hash_file('sha256', $file))) {
        throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID',
            'The package does not have the size and SHA-256 that update.txt names.'));
    }
    $state['state'] = 'downloaded';

    return $state;
}

/**
 * Read the manifest of the package, check its requirements and replace this updater with the one
 * of the new release.
 * @param array<string,mixed> $ctx
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function verifyAndInstallUpdater(array $ctx, array $state): array
{
    $root = $ctx['config']['rootPath'];
    $workspace = workspace($root, $state['updateId']);
    if (!hash_equals(confirmedPackageHash($workspace, $state), (string)@hash_file('sha256', $workspace . '/package.zip'))) {
        throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The package in the workspace has changed.'));
    }

    try {
        $package = openPackage($workspace . '/package.zip', MAX_EXTRACTED_SIZE);
    } catch (UpdaterException $exception) {
        throw new VerificationFailure($exception);
    }

    try {
        try {
            if (isDeveloperChannel((string)$state['channel'])) {
                $manifest = developerManifest($package, (string)$state['targetVersion']);
                $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            } else {
                $index = packageFind($package, 'install/update-manifest.json');
                if ($index === null) {
                    throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The package contains no manifest.');
                }
                $manifestJson = packageRead($package, $index, MAX_MANIFEST_SIZE);
                $manifest = readManifest($manifestJson, workspaceRelease($workspace, $state)['version']);
            }
        } catch (UpdaterException $exception) {
            throw new VerificationFailure($exception);
        }

        $freeSpace = @disk_free_space(updatesDirectory($root));
        $checks = manifestChecks($manifest, $freeSpace === false ? -1 : (int)$freeSpace);
        $state['checks'] = array_merge((array)($state['checks'] ?? array()), $checks);
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                throw new UpdaterException($check['text'], $check['params']);
            }
        }

        writeFile($workspace . '/update-manifest.json', $manifestJson);
        // A source archive without the updater leaves the installed one, and its texts, in place.
        if (empty($state['withoutUpdater'])) {
            installUpdater($package, $manifest, $workspace . '/updater', $root);
        }
        if ($manifest['developer'] && empty($state['withoutUpdater'])) {
            ensureDirectory($root . '/install/core-update/texts');
            foreach (developerTexts($package) as $language => $json) {
                writeFile($root . '/install/core-update/texts/' . $language . '.json', $json);
            }
        }
    } finally {
        $package['zip']->close();
    }

    $state['state'] = 'prepared';

    return $state;
}

/**
 * Replace install/core-update/ with the updater of the new release. Every file is extracted into
 * the workspace and checked against the manifest first, so that a broken package never leaves half
 * an updater behind. Files of the old updater the new one does not have are removed, and OPcache is
 * told about each file, so that the next request runs the new code.
 * @param array{zip: \ZipArchive, entries: array<int,string>} $package
 * @param array<string,mixed> $manifest
 */
function installUpdater(array $package, array $manifest, string $staging, string $root): void
{
    deleteDirectory($staging);
    $files = array();

    foreach ($package['entries'] as $index => $path) {
        if (!str_starts_with($path, 'install/core-update/')) {
            continue;
        }
        if (!isset($manifest['files'][$path])) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest does not list ' . $path . '.'));
        }

        $relative = substr($path, strlen('install/core-update/'));
        ensureDirectory(dirname($staging . '/' . $relative));
        if (!hash_equals($manifest['files'][$path], packageExtract($package, $index, $staging . '/' . $relative, MAX_MANIFEST_SIZE))) {
            throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', $path . ' does not match the manifest.'));
        }
        $files[] = $relative;
    }

    if (!in_array('index.php', $files, true) || !in_array('lib/bootstrap.php', $files, true)) {
        throw new VerificationFailure(UpdaterException::withDetail('SYS_CORE_UPDATE_UPDATER_MISSING', 'The package contains no updater.'));
    }

    $target = $root . '/install/core-update';
    foreach ($files as $relative) {
        ensureDirectory(dirname($target . '/' . $relative));
        $temporary = $target . '/' . $relative . '.' . bin2hex(random_bytes(4)) . '.new';
        if (!@copy($staging . '/' . $relative, $temporary) || !@rename($temporary, $target . '/' . $relative)) {
            @unlink($temporary);
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', 'install/core-update/' . $relative . ' cannot be replaced.');
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($target . '/' . $relative, true);
        }
    }

    $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $relative = substr(str_replace('\\', '/', $entry->getPathname()), strlen($target) + 1);
        if ($entry->isFile() && !in_array($relative, $files, true)) {
            @unlink($entry->getPathname());
        } elseif ($entry->isDir()) {
            @rmdir($entry->getPathname()); // only succeeds for a directory that is empty now
        }
    }

    deleteDirectory($staging);
}

/**
 * End an update that never changed anything: remove its workspace, forget it, remove the cookie.
 * @param array<string,mixed> $ctx
 */
function abortUpdate(array $ctx, string $updateId): void
{
    try {
        updateLog($ctx['config']['rootPath'], $updateId, 'update ended, workspace removed');
        keepUpdateLog($ctx['config']['rootPath'], readState($ctx['config']['rootPath'], $updateId));
    } catch (UpdaterException) {
        // Without a readable state there is nothing to name the log by.
    }
    deleteDirectory(workspace($ctx['config']['rootPath'], $updateId));
    clearActiveUpdate($ctx['config']['rootPath'], $updateId);
    clearCredentialCookie($ctx['config']);
}

/**
 * What went wrong, in English, for update.log: the detail of an updater error, or else its message.
 */
function errorDetail(?Throwable $exception): string
{
    if ($exception instanceof UpdaterException && $exception->getDetail() !== '') {
        return $exception->getMessage() . ' (' . $exception->getDetail() . ')';
    }

    return $exception !== null ? $exception->getMessage() : '';
}

/**
 * The message for the administrator; the English detail goes to the log.
 * @param array<string,mixed> $ctx
 */
function describeError(array $ctx, ?Throwable $exception): string
{
    if ($exception instanceof UpdaterException) {
        if ($exception->getDetail() !== '') {
            error_log('Admidio core updater: ' . $exception->getDetail());
        }

        return $exception->getText($ctx['texts']);
    }

    return $exception !== null ? $exception->getMessage() : '';
}

// ------------------------------------------------------------------------------------------------
// Checks before the download (P1-P9; P10, whether adm_my_files can be reached over HTTP, can only
// be answered by the browser, so the page asks it)
// ------------------------------------------------------------------------------------------------

/**
 * The checks that need neither the package nor its manifest. An error blocks the update, a
 * warning is shown and the administrator decides.
 * @param array<string,mixed> $release
 * @return array<int,array{check: string, status: string, text: string, params: array<int,string>}>
 */
function preflightChecks(string $root, array $release, string $installedVersion, ?string $databaseVersion, string $databaseType, int $freeSpace,
    bool $developer = false): array
{
    $checks = array();
    $add = static function (string $check, string $status, string $text, array $params = array()) use (&$checks): void {
        $checks[] = array('check' => $check, 'status' => $status, 'text' => $text, 'params' => array_values(array_map('strval', $params)));
    };

    // P1: PHP version
    version_compare(PHP_VERSION, $release['minimumPhpVersion']) < 0
        ? $add('P1', 'error', 'SYS_CORE_UPDATE_PHP_TOO_OLD', array($release['version'], $release['minimumPhpVersion'], PHP_VERSION))
        : $add('P1', 'ok', 'SYS_CORE_UPDATE_CHECK_PHP', array(PHP_VERSION));

    // P2: the extensions of the updater and the PDO driver
    $missing = missingExtensions(array('zip', 'curl', 'json', 'openssl', 'pdo', $databaseType === 'mysql' ? 'pdo_mysql' : 'pdo_pgsql'));
    $missing !== array()
        ? $add('P2', 'error', 'SYS_CORE_UPDATE_CHECK_EXTENSIONS_MISSING', array(implode(', ', $missing)))
        : $add('P2', 'ok', 'SYS_CORE_UPDATE_CHECK_EXTENSIONS');

    // P3: the version rule and the oldest version the release can be installed on. A developer,
    // who knows what is being tested, may install an older version after a warning.
    try {
        checkCompatibility($release, $installedVersion, PHP_VERSION);
        $rule = checkVersionRule($release['version'], $installedVersion, $databaseVersion);
        $add('P3', 'ok', $rule === 'reinstall' ? 'SYS_CORE_UPDATE_CHECK_REINSTALL' : 'SYS_CORE_UPDATE_CHECK_VERSION', array($installedVersion, $release['version']));
    } catch (UpdaterException $exception) {
        $warning = !$developer ? '' : match ($exception->getMessage()) {
            'SYS_CORE_UPDATE_VERSION_OLDER' => 'SYS_CORE_UPDATE_CHECK_OLDER_DEVELOPER',
            'SYS_CORE_UPDATE_DATABASE_NEWER' => 'SYS_CORE_UPDATE_CHECK_DATABASE_NEWER_DEVELOPER',
            default => ''
        };
        $warning !== ''
            ? $add('P3', 'warning', $warning, $exception->getParams())
            : $add('P3', 'error', $exception->getMessage(), $exception->getParams());
    }

    // P4: no database update is waiting - the database has the version of the files
    if ($databaseVersion === null) {
        $add('P4', 'error', 'SYS_CORE_UPDATE_CHECK_DATABASE_UNKNOWN');
    } elseif (version_compare($databaseVersion, $installedVersion) < 0) {
        $add('P4', 'error', 'SYS_CORE_UPDATE_CHECK_DATABASE_PENDING', array($databaseVersion, $installedVersion));
    } else {
        $add('P4', 'ok', 'SYS_CORE_UPDATE_CHECK_DATABASE');
    }

    // P5: adm_my_files can hold the workspace
    canCreateAndRename(updatesDirectory($root))
        ? $add('P5', 'ok', 'SYS_CORE_UPDATE_CHECK_DATA_DIRECTORY')
        : $add('P5', 'error', 'SYS_CORE_UPDATE_CHECK_NOT_WRITABLE', array('adm_my_files/core-update'));

    // P6: free space; the extracted package is not known yet, three times the package is generous
    $checks[] = spaceCheck($freeSpace, $release['packageSize'], 3 * $release['packageSize']);

    // P8: an open_basedir restriction must include the installation
    $restriction = (string)ini_get('open_basedir');
    if ($restriction !== '') {
        $inside = false;
        foreach (explode(PATH_SEPARATOR, $restriction) as $allowed) {
            $allowed = rtrim(str_replace('\\', '/', $allowed), '/') . '/';
            $inside = $inside || ($allowed !== '/' && str_starts_with(rtrim($root, '/') . '/', $allowed));
        }
        $inside ? $add('P8', 'ok', 'SYS_CORE_UPDATE_CHECK_OPEN_BASEDIR') : $add('P8', 'error', 'SYS_CORE_UPDATE_CHECK_OPEN_BASEDIR_MISSING');
    }

    // P9: a file can be created and renamed in every managed directory and next to the managed root
    // files - files the FTP user owns are found here, not halfway through the installation
    $manifest = is_file($root . '/install/update-manifest.json') ? json_decode((string)file_get_contents($root . '/install/update-manifest.json'), true) : null;
    $directories = is_array($manifest['managedDirectories'] ?? null) ? $manifest['managedDirectories'] : DEFAULT_MANAGED_DIRECTORIES;
    $rootFiles = is_array($manifest['managedRootFiles'] ?? null) ? $manifest['managedRootFiles'] : DEFAULT_MANAGED_ROOT_FILES;
    $failed = array();
    foreach ($directories as $directory) {
        if (is_string($directory) && isSafeRelativePath($directory) && is_dir($root . '/' . $directory) && !canCreateAndRename($root . '/' . $directory)) {
            $failed[] = $directory . '/';
        }
    }
    foreach ($rootFiles as $file) {
        if (is_string($file) && isSafeRelativePath($file) && is_file($root . '/' . $file) && !is_writable($root . '/' . $file)) {
            $failed[] = $file;
        }
    }
    if ($rootFiles !== array() && !canCreateAndRename($root)) {
        $failed[] = '/';
    }
    $failed !== array()
        ? $add('P9', 'error', 'SYS_CORE_UPDATE_CHECK_NOT_WRITABLE', array(implode(', ', $failed)))
        : $add('P9', 'ok', 'SYS_CORE_UPDATE_CHECK_WRITABLE');

    return $checks;
}

/**
 * The checks the manifest of the downloaded package makes exact: its PHP version and extensions,
 * and the space for its real size.
 * @param array<string,mixed> $manifest
 * @return array<int,array{check: string, status: string, text: string, params: array<int,string>}>
 */
function manifestChecks(array $manifest, int $freeSpace): array
{
    $checks = array();
    if (preg_match('/^>=\s*(\d+\.\d+(?:\.\d+)?)$/', $manifest['requirements']['php'], $matches) === 1
        && version_compare(PHP_VERSION, $matches[1]) < 0) {
        $checks[] = array('check' => 'P1', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_PHP_TOO_OLD', 'params' => array($manifest['version'], $matches[1], PHP_VERSION));
    }
    $missing = missingExtensions($manifest['requirements']['extensions']);
    if ($missing !== array()) {
        $checks[] = array('check' => 'P2', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_EXTENSIONS_MISSING', 'params' => array(implode(', ', $missing)));
    }
    // The package is downloaded; only the extracted files and the rollback copy count.
    $checks[] = spaceCheck($freeSpace, 0, $manifest['totalSize']);

    return $checks;
}

/**
 * P6: the package, the extracted files, the rollback copy and a margin of 20 %. Without an answer
 * from the server, or under quotas it does not report, only a warning.
 * @return array{check: string, status: string, text: string, params: array<int,string>}
 */
function spaceCheck(int $freeSpace, int $packageSize, int $extractedSize): array
{
    $needed = (int)ceil(($packageSize + 2 * $extractedSize) * 1.2);
    $megabytes = static fn(int $bytes): string => (string)max(1, (int)ceil($bytes / 1048576));

    return match (true) {
        $freeSpace < 0 => array('check' => 'P6', 'status' => 'warning', 'text' => 'SYS_CORE_UPDATE_CHECK_SPACE_UNKNOWN', 'params' => array($megabytes($needed))),
        $freeSpace < $needed => array('check' => 'P6', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_SPACE_MISSING',
            'params' => array($megabytes($needed), $megabytes($freeSpace))),
        default => array('check' => 'P6', 'status' => 'ok', 'text' => 'SYS_CORE_UPDATE_CHECK_SPACE', 'params' => array($megabytes($freeSpace)))
    };
}

/**
 * @param array<int,string> $extensions
 * @return array<int,string>
 */
function missingExtensions(array $extensions): array
{
    return array_values(array_filter(array_unique($extensions), static fn(string $extension): bool => !extension_loaded($extension)));
}

/** @param array<int,array{status: string}> $checks */
function checksHaveErrors(array $checks): bool
{
    return in_array('error', array_column($checks, 'status'), true);
}

/** Whether a probe file can be created and renamed in a directory - what replacing a file needs. */
function canCreateAndRename(string $directory): bool
{
    if (!is_dir($directory) && !@mkdir($directory, 0770, true)) {
        return false;
    }

    $probe = $directory . '/.admidio-update-probe-' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, 'probe') !== 5) {
        return false;
    }
    $renamed = @rename($probe, $probe . '-renamed');
    @unlink($renamed ? $probe . '-renamed' : $probe);

    return $renamed;
}
