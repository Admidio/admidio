<?php
/**
 * Getting a release and checking it: HTTPS downloads, the signature of update.txt, the release
 * information in it, the manifest of a package and the safe reading of its ZIP archive.
 *
 * The trust is simple: update.txt on admidio.org names the version, the package URL and the
 * SHA-256 of the package, and update.txt is signed (update.txt.sig, RSA-SHA256 over its exact
 * bytes) with the one release key whose public part ships with the updater (release-key.pem). The
 * package may then come from any mirror. Inside it, install/update-manifest.json names the SHA-256
 * of every file; it needs no signature of its own, because the package hash covers it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use ZipArchive;

const MAX_REDIRECTS = 5;

// ------------------------------------------------------------------------------------------------
// HTTPS
// ------------------------------------------------------------------------------------------------

/**
 * Fetch a file. Redirects are followed here and not by cURL, so that every hop is checked again:
 * at most MAX_REDIRECTS, each one HTTPS. The size limit counts the bytes that arrive.
 *
 * Options: plainHttpBase - a URL prefix below which plain HTTP is allowed (only set in debug mode,
 * for a local test server); transport - replaces the network access in tests, a callable
 * fn(string $url, int $maxBytes, array $headers): array{status: int, location: string, body: string}.
 * @param array{plainHttpBase?: string, transport?: callable} $options
 */
function httpGet(string $url, int $maxBytes, array $options = array()): string
{
    return httpRequest($url, $maxBytes, $options)['body'];
}

/**
 * Fetch a part of a file with a range request. A server with range support answers 206 with that
 * part; one without answers 200 with the whole file, limited to $maxTotal bytes.
 * @param array{plainHttpBase?: string, transport?: callable} $options
 * @return array{status: int, body: string}
 */
function httpGetRange(string $url, int $offset, int $length, int $maxTotal, array $options = array()): array
{
    $response = httpRequest($url, max($length, $maxTotal), $options, array('Range: bytes=' . $offset . '-' . ($offset + $length - 1)));
    if ($response['status'] === 206 && strlen($response['body']) > $length) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'The server sent more than the requested range.', array(''));
    }

    return $response;
}

/**
 * @param array{plainHttpBase?: string, transport?: callable} $options
 * @param array<int,string> $headers
 * @return array{status: int, body: string}
 */
function httpRequest(string $url, int $maxBytes, array $options, array $headers = array()): array
{
    $transport = $options['transport'] ?? curlTransport(...);

    for ($hop = 0; $hop <= MAX_REDIRECTS; $hop++) {
        assertAllowedUrl($url, $options['plainHttpBase'] ?? '');
        $response = $transport($url, $maxBytes, $headers);
        $status = (int)$response['status'];

        if ($status >= 300 && $status < 400 && $response['location'] !== '') {
            $url = resolveRedirect($url, $response['location']);
            continue;
        }
        if ($status < 200 || $status >= 300) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'HTTP status ' . $status . ' for ' . $url, array('HTTP ' . $status));
        }

        return array('status' => $status, 'body' => (string)$response['body']);
    }

    throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'More than ' . MAX_REDIRECTS . ' redirects.', array(''));
}

/** Refuse a URL the updater must not request: anything but HTTPS without credentials. */
function assertAllowedUrl(string $url, string $plainHttpBase = ''): void
{
    $parts = parse_url($url);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    $plainHttpAllowed = $scheme === 'http' && $plainHttpBase !== '' && str_starts_with($url, $plainHttpBase);

    if (!is_array($parts) || ($scheme !== 'https' && !$plainHttpAllowed) || ($parts['host'] ?? '') === ''
        || isset($parts['user']) || isset($parts['pass'])) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', 'Refused URL: ' . $url);
    }
}

/** The absolute URL of a redirect target. */
function resolveRedirect(string $base, string $location): string
{
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $location) === 1) {
        return $location;
    }

    $parts = parse_url($base);
    if (str_starts_with($location, '//')) {
        return $parts['scheme'] . ':' . $location;
    }

    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }

    $path = $parts['path'] ?? '/';

    return $origin . substr($path, 0, (int)strrpos($path, '/') + 1) . $location;
}

/**
 * One request with cURL, without following redirects, with the certificate checked, a connect
 * timeout, and a transfer that stalls given up.
 * @param array<int,string> $headers
 * @return array{status: int, location: string, body: string}
 */
function curlTransport(string $url, int $maxBytes, array $headers = array()): array
{
    if (!extension_loaded('curl')) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS', 'ext-curl is not loaded.');
    }

    $body = '';
    $location = '';
    $tooLarge = false;

    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_LOW_SPEED_LIMIT => 1024,
        CURLOPT_LOW_SPEED_TIME => 60,
        CURLOPT_USERAGENT => 'Admidio core updater',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, strlen('Location:')));
            }

            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use (&$body, &$tooLarge, $maxBytes): int {
            if (strlen($body) + strlen($data) > $maxBytes) {
                $tooLarge = true;

                return 0; // ends the transfer
            }
            $body .= $data;

            return strlen($data);
        }
    ));

    $success = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($tooLarge) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'The download is larger than ' . $maxBytes . ' bytes: ' . $url, array(''));
    }
    if ($success !== true) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', $error . ': ' . $url, array($error));
    }

    return array('status' => $status, 'location' => $location, 'body' => $body);
}

// ------------------------------------------------------------------------------------------------
// Signature and release information
// ------------------------------------------------------------------------------------------------

/** Whether a signature (base64, as in a .sig file) over the exact bytes was made with the key. */
function verifySignature(string $data, string $signature, string $publicKeyPem): bool
{
    if (!extension_loaded('openssl')) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS', 'ext-openssl is not loaded.');
    }

    $bytes = base64_decode(trim($signature), true);
    $key = @openssl_pkey_get_public($publicKeyPem);

    return $bytes !== false && $bytes !== '' && $key !== false && openssl_verify($data, $bytes, $key, OPENSSL_ALGO_SHA256) === 1;
}

/**
 * Sign data, as the release builder does. Returns the content of the .sig file.
 * @param \OpenSSLAsymmetricKey|string $privateKey A key or its PEM.
 */
function signData(string $data, \OpenSSLAsymmetricKey|string $privateKey): string
{
    if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new UpdaterException('The data could not be signed: ' . (string)openssl_error_string());
    }

    return base64_encode($signature) . "\n";
}

/**
 * Fetch update.txt and its signature and verify them with the release key of the updater.
 * @param array<string,mixed> $config
 * @param array{plainHttpBase?: string, transport?: callable} $http
 * @return array{text: string, signature: string, key: string} What was verified, to keep in the workspace.
 */
function fetchUpdateInfo(array $config, string $updaterPath, array $http): array
{
    $keyFile = publicKeyFile($config, $updaterPath);
    $key = is_file($keyFile) ? (string)file_get_contents($keyFile) : '';
    if ($key === '' || @openssl_pkey_get_public($key) === false) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_NOT_AVAILABLE', 'There is no release key: ' . $keyFile);
    }

    $url = updateInfoUrl($config);
    $text = httpGet($url, 65536, $http);
    $signature = httpGet($url . '.sig', 4096, $http);
    if (!verifySignature($text, $signature, $key)) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_SIGNATURE_INVALID', 'update.txt is not signed with the release key.');
    }

    return array('text' => $text, 'signature' => $signature, 'key' => $key);
}

/**
 * The lines of update.txt as name => value; the first occurrence of a name wins, as in Admidio.
 * @return array<string,string>
 */
function parseUpdateInfo(string $text): array
{
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }

    $info = array();
    foreach (preg_split('/\R/', $text) ?: array() as $line) {
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name !== '' && !array_key_exists($name, $info)) {
                $info[$name] = trim($value);
            }
        }
    }

    return $info;
}

/**
 * The release a channel offers, from update.txt. A stable release is announced by the lines
 * Version, Package-URL, Package-SHA256, Package-Size, Minimum-Source-Version and
 * Minimum-PHP-Version; a beta by Beta-Version and Beta-Release with the same lines prefixed by
 * "Beta-". A version without package lines cannot be installed automatically.
 * @param array<string,string> $info
 * @return array{channel: string, version: string, versionText: string, packageUrl: string, packageSha256: string,
 *               packageSize: int, minimumSourceVersion: string, minimumPhpVersion: string}
 */
function releaseFromUpdateInfo(array $info, string $channel): array
{
    $prefix = $channel === 'beta' ? 'Beta-' : '';
    $version = $channel === 'beta'
        ? ($info['Beta-Version'] ?? '') . (($info['Beta-Release'] ?? '') !== '' ? '-beta.' . $info['Beta-Release'] : '')
        : ($info['Version'] ?? '');

    if (($info[$prefix . 'Package-URL'] ?? '') === '') {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_NOT_AVAILABLE', 'update.txt offers no package for the channel "' . $channel . '".');
    }

    $release = array(
        'channel' => $channel,
        'version' => $version,
        'versionText' => $version,
        'packageUrl' => $info[$prefix . 'Package-URL'],
        'packageSha256' => strtolower($info[$prefix . 'Package-SHA256'] ?? ''),
        'packageSize' => (int)($info[$prefix . 'Package-Size'] ?? 0),
        'minimumSourceVersion' => $info[$prefix . 'Minimum-Source-Version'] ?? '',
        'minimumPhpVersion' => $info[$prefix . 'Minimum-PHP-Version'] ?? ''
    );

    $problem = match (true) {
        !isCanonicalVersion($release['version']) => 'version',
        preg_match('/^[a-f0-9]{64}$/', $release['packageSha256']) !== 1 => 'Package-SHA256',
        $release['packageSize'] <= 0 => 'Package-Size',
        !isCanonicalVersion($release['minimumSourceVersion']) => 'Minimum-Source-Version',
        preg_match('/^\d+\.\d+(?:\.\d+)?$/', $release['minimumPhpVersion']) !== 1 => 'Minimum-PHP-Version',
        default => ''
    };
    if ($problem !== '') {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_INFORMATION_INVALID', 'update.txt: "' . $prefix . $problem . '" is missing or invalid.');
    }

    return $release;
}

/**
 * Refuse a release this installation is too old for, before anything is downloaded.
 * @param array{version: string, minimumSourceVersion: string, minimumPhpVersion: string} $release
 */
function checkCompatibility(array $release, string $installedVersion, string $phpVersion): void
{
    if (version_compare($installedVersion, $release['minimumSourceVersion']) < 0) {
        throw new UpdaterException('SYS_CORE_UPDATE_SOURCE_TOO_OLD', array($release['version'], $release['minimumSourceVersion'], $installedVersion));
    }
    if (version_compare($phpVersion, $release['minimumPhpVersion']) < 0) {
        throw new UpdaterException('SYS_CORE_UPDATE_PHP_TOO_OLD', array($release['version'], $release['minimumPhpVersion'], $phpVersion));
    }
}

/**
 * Read and check the manifest of a package: version, managedDirectories, managedRootFiles,
 * preservePaths, migrationPaths (optional: the paths the database update of the release moves or
 * removes), requirements (php, extensions), totalSize and files (path => SHA-256), every path
 * safe and no two names that differ only in case. developer (optional) marks the manifest that the
 * updater made for a source archive.
 * @return array{version: string, managedDirectories: array<int,string>, managedRootFiles: array<int,string>,
 *               preservePaths: array<int,string>, requirements: array{php: string, extensions: array<int,string>},
 *               totalSize: int, files: array<string,string>}
 */
function readManifest(string $json, string $expectedVersion): array
{
    $data = json_decode($json, true);
    if (!is_array($data) || ($data['version'] ?? null) !== $expectedVersion) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest is not the one of version ' . $expectedVersion . '.');
    }

    $isPathList = static function (mixed $value): bool {
        return is_array($value) && array_is_list($value)
            && array_filter($value, static fn(mixed $path): bool => !is_string($path) || !isSafeRelativePath($path)) === array();
    };
    $requirements = $data['requirements'] ?? null;

    $problem = match (true) {
        !$isPathList($data['managedDirectories'] ?? null) => 'managedDirectories',
        !$isPathList($data['managedRootFiles'] ?? null)
            || array_filter($data['managedRootFiles'], static fn(string $file): bool => str_contains($file, '/')) !== array() => 'managedRootFiles',
        !$isPathList($data['preservePaths'] ?? null) => 'preservePaths',
        !$isPathList($data['migrationPaths'] ?? array()) => 'migrationPaths',
        !is_array($requirements) || !is_string($requirements['php'] ?? null) || !is_array($requirements['extensions'] ?? null) => 'requirements',
        !is_int($data['totalSize'] ?? null) || $data['totalSize'] < 0 => 'totalSize',
        !is_bool($data['developer'] ?? false) => 'developer',
        !is_array($data['files'] ?? null) || $data['files'] === array() => 'files',
        default => ''
    };
    if ($problem !== '') {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest field "' . $problem . '" is missing or invalid.');
    }

    $caseKeys = array();
    foreach ($data['files'] as $path => $hash) {
        $path = (string)$path;
        if (!isSafeRelativePath($path) || !is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest entry "' . $path . '" is invalid.');
        }
        // A case-insensitive file system would write both entries to one file.
        if (isset($caseKeys[strtolower($path)])) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest lists "' . $path . '" twice in different case.');
        }
        $caseKeys[strtolower($path)] = true;
    }

    $data['requirements'] = array('php' => $requirements['php'], 'extensions' => array_values(array_filter($requirements['extensions'], 'is_string')));
    $data['migrationPaths'] = $data['migrationPaths'] ?? array();
    $data['developer'] = $data['developer'] ?? false;

    return $data;
}

/**
 * The paths that the database update of a release moves or removes itself, from
 * install/db_scripts/migration-paths.json of the release: a JSON list of relative paths. A release
 * without the file has none.
 * @return array<int,string>
 */
function readMigrationPaths(string $json): array
{
    if (trim($json) === '') {
        return array();
    }

    $paths = json_decode($json, true);
    if (!is_array($paths) || !array_is_list($paths)
        || array_filter($paths, static fn(mixed $path): bool => !is_string($path) || !isSafeRelativePath($path)) !== array()) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'install/db_scripts/migration-paths.json is no list of paths.');
    }

    return $paths;
}

// ------------------------------------------------------------------------------------------------
// ZIP packages
// ------------------------------------------------------------------------------------------------

/**
 * Open a package and check every entry before anything is extracted: a safe relative path, no
 * symbolic link, no two names that differ only in case, and the declared sizes together within the
 * limit. Entries are read by index, never by name. One top directory that all entries share
 * (admidio-5.1.0/, as in a GitHub archive) is taken off the paths.
 * @return array{zip: ZipArchive, entries: array<int,string>} entries: index => path, files only.
 */
function openPackage(string $file, int $maxTotalSize): array
{
    if (!extension_loaded('zip')) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS', 'ext-zip is not loaded.');
    }

    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::RDONLY) !== true) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', 'The package is no readable ZIP archive.');
    }

    try {
        $names = array();
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_string($name) || $name === '') {
                throw packageInvalid('Entry ' . $index . ' has no name.');
            }
            $names[$index] = $name;
        }

        // The top directory all entries share, if there is one.
        $first = reset($names);
        $root = ($first !== false && str_contains($first, '/')) ? substr($first, 0, strpos($first, '/') + 1) : '';
        foreach ($names as $name) {
            if ($root !== '' && !str_starts_with($name, $root)) {
                $root = '';
                break;
            }
        }

        $entries = array();
        $caseKeys = array();
        $declaredSize = 0;
        foreach ($names as $index => $name) {
            $path = substr($name, strlen($root));
            if ($path === '' || str_ends_with($path, '/')) {
                if (!isSafeRelativePath(rtrim($name, '/'))) {
                    throw packageInvalid('The directory entry "' . $name . '" is not a safe path.');
                }
                continue;
            }
            if (!isSafeRelativePath($path)) {
                throw packageInvalid('The entry "' . $name . '" is not a safe path.');
            }
            if ($zip->getExternalAttributesIndex($index, $system, $attributes)
                && $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                throw packageInvalid('The entry "' . $name . '" is a symbolic link.');
            }
            if (isset($caseKeys[strtolower($path)])) {
                throw packageInvalid('The entry "' . $name . '" exists twice, possibly in another case.');
            }
            $caseKeys[strtolower($path)] = true;

            $declaredSize += (int)($zip->statIndex($index)['size'] ?? 0);
            if ($declaredSize > $maxTotalSize) {
                throw packageInvalid('The package is larger than ' . $maxTotalSize . ' bytes when extracted.');
            }
            $entries[$index] = $path;
        }
    } catch (UpdaterException $exception) {
        $zip->close();
        throw $exception;
    }

    return array('zip' => $zip, 'entries' => $entries);
}

/**
 * The index of a file of a package, or null.
 * @param array{zip: ZipArchive, entries: array<int,string>} $package
 */
function packageFind(array $package, string $path): ?int
{
    $index = array_search($path, $package['entries'], true);

    return $index === false ? null : $index;
}

/**
 * Read a small file of a package.
 * @param array{zip: ZipArchive, entries: array<int,string>} $package
 */
function packageRead(array $package, int $index, int $maxBytes): string
{
    $content = '';
    packageCopy($package, $index, $maxBytes, static function (string $chunk) use (&$content): void {
        $content .= $chunk;
    });

    return $content;
}

/**
 * Write one file of a package. The bytes that are written are counted against the limit - the
 * size an entry declares is not believed - and a partly written file is removed.
 * @param array{zip: ZipArchive, entries: array<int,string>} $package
 * @return string The SHA-256 of what was written.
 */
function packageExtract(array $package, int $index, string $targetFile, int $maxBytes): string
{
    $target = @fopen($targetFile, 'wb');
    if ($target === false) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }

    $hash = hash_init('sha256');
    try {
        packageCopy($package, $index, $maxBytes, static function (string $chunk) use ($target, $hash, $targetFile): void {
            if (fwrite($target, $chunk) !== strlen($chunk)) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', 'Writing ' . $targetFile . ' failed.');
            }
            hash_update($hash, $chunk);
        });
    } catch (UpdaterException $exception) {
        fclose($target);
        @unlink($targetFile);
        throw $exception;
    }
    fclose($target);

    return hash_final($hash);
}

/**
 * @param array{zip: ZipArchive, entries: array<int,string>} $package
 * @param callable(string): void $consume
 */
function packageCopy(array $package, int $index, int $maxBytes, callable $consume): void
{
    if (!isset($package['entries'][$index])) {
        throw packageInvalid('There is no file with the index ' . $index . '.');
    }

    $stream = $package['zip']->getStreamIndex($index, ZipArchive::FL_UNCHANGED);
    if ($stream === false) {
        throw packageInvalid('The entry "' . $package['entries'][$index] . '" cannot be read.');
    }

    try {
        $written = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false) {
                throw packageInvalid('The entry "' . $package['entries'][$index] . '" cannot be read.');
            }
            $written += strlen($chunk);
            if ($written > $maxBytes) {
                throw packageInvalid('The entry "' . $package['entries'][$index] . '" is larger than ' . $maxBytes . ' bytes.');
            }
            $consume($chunk);
        }
    } finally {
        fclose($stream);
    }
}

function packageInvalid(string $detail): UpdaterException
{
    return UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', $detail);
}
