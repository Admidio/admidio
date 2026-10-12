<?php
/**
 * The release builder of Admidio: the package of a release for the automatic core updater, the
 * bootstrap ZIP of the updater, and update.txt with the lines of the release, signed with the
 * release key.
 *
 *   php build-release.php --key=<private key PEM> [--ref=v5.1.0] [--out=build]
 *       [--update-txt=<file or URL of the current update.txt>] [--package-url=<URL of the folder>]
 *       [--minimum-source-version=5.0.0] [--allow-dirty] [--key-rotation] [--test-release] [--no-composer]
 *   php build-release.php sign --key=<private key PEM> --update-txt=<file>
 *
 * A release is built from a clean export of a commit, a tag or HEAD (git archive), never from the
 * working tree, which therefore has to be clean. composer install --no-dev runs in the export, and
 * archive.exclude of its composer.json decides what the package contains - the rule of every
 * Admidio package, which the developer channels of the updater apply as well. The builder adds the
 * texts of the updater (install/core-update/texts/) and the manifest (install/update-manifest.json).
 *
 * The output in --out (default build/):
 *   admidio-<version>.zip               the package
 *   admidio-core-updater-<version>.zip  install/core-update/ alone, for installations without it
 *   update.txt, update.txt.sig          the given update.txt with the lines of this release, signed
 *
 * The private key comes from --key, or from the environment as ADMIDIO_RELEASE_KEY (the PEM) or
 * ADMIDIO_RELEASE_KEY_FILE, with ADMIDIO_RELEASE_KEY_PASSPHRASE if it is encrypted. It never
 * enters the package; its public part must be install/core-update/release-key.pem of the release,
 * except for the release that ships a new key (--key-rotation), which the old key still signs.
 * --test-release puts the public part of a test key into the package instead, for a local test
 * server; --no-composer leaves vendor/ out, for tests.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioReleaseBuilder;

use AdmidioCoreUpdater as U;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Throwable;
use ZipArchive;

require_once __DIR__ . '/install/core-update/lib/bootstrap.php';

/** The paths that no update writes, in the manifest of every release. */
const PRESERVE_PATHS = array('adm_my_files', 'install/core-update', 'custom_css.css', 'custom_favicon.png', 'custom_logo.png');

/** The paths the database update of a release moves or removes itself, kept in the repository. */
const MIGRATION_PATHS_FILE = 'install/db_scripts/migration-paths.json';

const RELEASE_KEY_FILE = 'install/core-update/release-key.pem';

/** The oldest release the package can update, unless --minimum-source-version says otherwise. */
const DEFAULT_MINIMUM_SOURCE_VERSION = '5.0.0';

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        exit(main(array_slice($argv, 1)));
    } catch (Throwable $exception) {
        fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

/**
 * @param array<int,string> $arguments
 */
function main(array $arguments): int
{
    $options = parseOptions($arguments);
    $key = privateKey($options['key'] ?? null);

    if (($options[0] ?? '') === 'sign') {
        $file = (string)($options['update-txt'] ?? '');
        if ($file === '' || !is_file($file)) {
            throw new RuntimeException('Use --update-txt=<file> with the update.txt to sign.');
        }
        file_put_contents($file . '.sig', U\signData((string)file_get_contents($file), $key));
        echo 'Signed: ' . $file . '.sig' . PHP_EOL;

        return 0;
    }

    $result = build(__DIR__, $key, $options);
    echo 'Version ' . $result['version'] . ' of commit ' . $result['commit'] . PHP_EOL
        . '  ' . $result['package'] . PHP_EOL . '    SHA-256 ' . $result['packageSha256'] . ', ' . $result['packageSize'] . ' bytes' . PHP_EOL
        . '  ' . $result['bootstrap'] . PHP_EOL . '    SHA-256 ' . $result['bootstrapSha256'] . PHP_EOL
        . '  ' . $result['updateTxt'] . ' and update.txt.sig' . PHP_EOL;

    return 0;
}

/**
 * --name=value and --flag options, and the words before them.
 * @param array<int,string> $arguments
 * @return array<int|string,string|bool>
 */
function parseOptions(array $arguments): array
{
    $options = array();
    foreach ($arguments as $argument) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $argument, $matches) === 1) {
            $options[$matches[1]] = $matches[2] ?? true;
        } else {
            $options[] = $argument;
        }
    }

    return $options;
}

/**
 * The release key from a file, or from the environment.
 */
function privateKey(mixed $file): OpenSSLAsymmetricKey
{
    $file = is_string($file) && $file !== '' ? $file : (string)getenv('ADMIDIO_RELEASE_KEY_FILE');
    $pem = $file !== '' ? (string)@file_get_contents($file) : (string)getenv('ADMIDIO_RELEASE_KEY');
    $passphrase = getenv('ADMIDIO_RELEASE_KEY_PASSPHRASE');
    $key = $pem !== '' ? openssl_pkey_get_private($pem, $passphrase === false ? '' : $passphrase) : false;
    if ($key === false) {
        throw new RuntimeException('No usable release key: use --key=<private key PEM>, or set ADMIDIO_RELEASE_KEY or ADMIDIO_RELEASE_KEY_FILE.');
    }

    return $key;
}

/**
 * Build a release of a commit of the repository.
 * @param array<int|string,string|bool> $options See the description of this file.
 * @return array{version: string, commit: string, package: string, packageSha256: string, packageSize: int,
 *               bootstrap: string, bootstrapSha256: string, updateTxt: string}
 */
function build(string $repository, OpenSSLAsymmetricKey $key, array $options): array
{
    $ref = is_string($options['ref'] ?? null) ? $options['ref'] : 'HEAD';
    $out = is_string($options['out'] ?? null) ? $options['out'] : $repository . '/build';
    $commit = git($repository, array('rev-parse', '--verify', $ref . '^{commit}'));
    if (empty($options['allow-dirty']) && git($repository, array('status', '--porcelain', '--untracked-files=no')) !== '') {
        throw new RuntimeException('The working tree has uncommitted changes, which would not be part of the release: commit them, or use --allow-dirty.');
    }

    $work = sys_get_temp_dir() . '/admidio-release-' . bin2hex(random_bytes(4));
    try {
        $tree = exportCommit($repository, $commit, $work);
        $version = U\versionFromConstants((string)@file_get_contents($tree . '/system/bootstrap/constants.php'));
        if ($version === null) {
            throw new RuntimeException('The version cannot be read from system/bootstrap/constants.php.');
        }
        $tag = tagOf($repository, $ref);
        if ($tag !== null && tagVersion($tag) !== $version) {
            throw new RuntimeException('The tag ' . $tag . ' does not match the version ' . $version . ' of the constants.');
        }

        if (!empty($options['test-release'])) {
            U\writeFile($tree . '/' . RELEASE_KEY_FILE, openssl_pkey_get_details($key)['key']);
        }
        $publicKey = (string)@file_get_contents($tree . '/' . RELEASE_KEY_FILE);
        if ($publicKey === '' || @openssl_pkey_get_public($publicKey) === false) {
            throw new RuntimeException(RELEASE_KEY_FILE . ' is missing in the release.');
        }
        if (empty($options['key-rotation']) && normalizedPem($publicKey) !== normalizedPem(openssl_pkey_get_details($key)['key'])) {
            throw new RuntimeException(RELEASE_KEY_FILE . ' is not the public part of the signing key (a release that ships a new key needs --key-rotation).');
        }

        if (empty($options['no-composer'])) {
            $composer = array('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-scripts', '--no-interaction',
                '--no-progress', '-d', $tree);
            // composer is a batch file on Windows, which finds itself only when cmd starts it by its
            // bare name.
            run(PHP_OS_FAMILY === 'Windows' ? 'composer ' . implode(' ', array_map('escapeshellarg', $composer)) : array_merge(array('composer'), $composer));
        }

        $files = releaseFiles($tree);
        $contents = array();
        foreach (updaterTexts($tree) as $language => $json) {
            $contents['install/core-update/texts/' . $language . '.json'] = $json;
        }
        $manifest = releaseManifest($tree, $version, $files, $contents);
        $contents['install/update-manifest.json'] = $manifest;

        U\ensureDirectory($out);
        $time = (int)git($repository, array('log', '-1', '--format=%ct', $commit));
        $package = $out . '/admidio-' . $version . '.zip';
        writeZip($package, 'admidio-' . $version . '/', $files, $contents, $time);
        $bootstrap = $out . '/admidio-core-updater-' . $version . '.zip';
        writeZip($bootstrap, '', array_filter($files, static fn(string $path): bool => str_starts_with($path, 'install/core-update/'), ARRAY_FILTER_USE_KEY),
            array_filter($contents, static fn(string $path): bool => str_starts_with($path, 'install/core-update/'), ARRAY_FILTER_USE_KEY), $time);

        $composer = json_decode((string)file_get_contents($tree . '/composer.json'), true);
        $lines = updateInfoLines($version, packageUrl($options, $tag, basename($package)), (string)hash_file('sha256', $package), (int)filesize($package),
            is_string($options['minimum-source-version'] ?? null) ? $options['minimum-source-version'] : DEFAULT_MINIMUM_SOURCE_VERSION,
            minimumPhpVersion($composer));
        $current = is_string($options['update-txt'] ?? null) ? readUpdateInfo($options['update-txt']) : '';
        $updateTxt = $out . '/update.txt';
        U\writeFile($updateTxt, mergeUpdateInfo($current, $lines));
        U\writeFile($updateTxt . '.sig', U\signData((string)file_get_contents($updateTxt), $key));

        checkRelease($package, $updateTxt, openssl_pkey_get_details($key)['key'], $version);
    } finally {
        U\deleteDirectory($work);
    }

    return array(
        'version' => $version,
        'commit' => $commit,
        'package' => $package,
        'packageSha256' => (string)hash_file('sha256', $package),
        'packageSize' => (int)filesize($package),
        'bootstrap' => $bootstrap,
        'bootstrapSha256' => (string)hash_file('sha256', $bootstrap),
        'updateTxt' => $updateTxt
    );
}

/**
 * Run git in the repository and return what it prints.
 * @param array<int,string> $arguments
 */
function git(string $repository, array $arguments): string
{
    return run(array_merge(array('git', '-C', $repository), $arguments));
}

/**
 * Run a command and return what it prints; a failure ends the build. A list is run without a
 * shell, a string by the shell.
 * @param array<int,string>|string $command
 */
function run(array|string $command): string
{
    // The messages go to a file: a full second pipe would block the command while its output is read.
    $messages = (string)tempnam(sys_get_temp_dir(), 'build');
    $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('file', $messages, 'w')), $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException((is_array($command) ? $command[0] : $command) . ' cannot be started.');
    }
    $output = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exitCode = proc_close($process);
    $errors = (string)file_get_contents($messages);
    unlink($messages);
    if ($exitCode !== 0) {
        throw new RuntimeException((is_array($command) ? implode(' ', $command) : $command) . ' failed: ' . trim($errors . $output));
    }

    return trim($output);
}

/**
 * The tree of a commit as git archive exports it: the committed files, without what export-ignore
 * of .gitattributes leaves out.
 * @return string The directory of the tree.
 */
function exportCommit(string $repository, string $commit, string $work): string
{
    U\ensureDirectory($work . '/tree');
    git($repository, array('archive', '--format=zip', '-o', $work . '/export.zip', $commit));
    $zip = new ZipArchive();
    if ($zip->open($work . '/export.zip') !== true || !$zip->extractTo($work . '/tree')) {
        throw new RuntimeException('The export of ' . $commit . ' cannot be unpacked.');
    }
    $zip->close();

    return $work . '/tree';
}

/**
 * The tag a reference names, or null for a branch or a commit.
 */
function tagOf(string $repository, string $ref): ?string
{
    $name = str_starts_with($ref, 'refs/tags/') ? substr($ref, 10) : $ref;
    try {
        git($repository, array('rev-parse', '--verify', '--quiet', 'refs/tags/' . $name));
    } catch (RuntimeException) {
        return null;
    }

    return $name;
}

/**
 * The version a tag stands for: v5.0.17 is 5.0.17, v5.0-Beta.1 is 5.0.0-beta.1.
 */
function tagVersion(string $tag): ?string
{
    if (preg_match('/^v?(\d+)\.(\d+)(?:\.(\d+))?(?:-beta\.?(\d+))?$/i', $tag, $matches) !== 1) {
        return null;
    }

    return U\versionFromParts((int)$matches[1], (int)$matches[2], (int)($matches[3] ?? 0), (int)($matches[4] ?? 0));
}

function normalizedPem(string $pem): string
{
    return (string)preg_replace('/\s+/', '', $pem);
}

/**
 * The files of the release in the exported tree, path => file: everything that archive.exclude of
 * its composer.json leaves in, except a manifest that is committed by mistake.
 * @return array<string,string>
 */
function releaseFiles(string $tree): array
{
    $composer = json_decode((string)file_get_contents($tree . '/composer.json'), true);
    $patterns = array_values(array_filter((array)($composer['archive']['exclude'] ?? array()), 'is_string'));

    $files = array();
    $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tree, \FilesystemIterator::SKIP_DOTS));
    foreach ($entries as $entry) {
        $path = substr(str_replace('\\', '/', $entry->getPathname()), strlen(str_replace('\\', '/', $tree)) + 1);
        if ($entry->isLink()) {
            throw new RuntimeException('A release must not contain symbolic links: ' . $path);
        }
        if ($entry->isFile() && $path !== 'install/update-manifest.json' && !U\isArchiveExcluded($path, $patterns)) {
            if (!U\isSafeRelativePath($path)) {
                throw new RuntimeException('The path ' . $path . ' cannot be part of a release.');
            }
            $files[$path] = $entry->getPathname();
        }
    }
    ksort($files, SORT_STRING);

    return $files;
}

/**
 * The texts of the updater, language => JSON: the SYS_CORE_UPDATE_* keys of every language file,
 * because the language files of an older installation lack them.
 * @return array<string,string>
 */
function updaterTexts(string $tree): array
{
    $texts = array();
    foreach (glob($tree . '/languages/*.xml') ?: array() as $file) {
        if (preg_match('#/([a-z]{2,3}(?:-[A-Za-z]{2,4})?)\.xml$#', $file, $matches) === 1) {
            $texts[$matches[1]] = json_encode(U\readLanguageFile($file), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
    ksort($texts, SORT_STRING);

    return $texts;
}

/**
 * install/update-manifest.json: the version, what the release manages and leaves alone, its
 * requirements and the SHA-256 of every file. The same release gives the same bytes.
 * @param array<string,string> $files path => file
 * @param array<string,string> $contents path => content of the generated files
 */
function releaseManifest(string $tree, string $version, array $files, array $contents): string
{
    $hashes = array();
    $totalSize = 0;
    foreach ($files as $path => $file) {
        $hashes[$path] = (string)hash_file('sha256', $file);
        $totalSize += (int)filesize($file);
    }
    foreach ($contents as $path => $content) {
        $hashes[$path] = hash('sha256', $content);
        $totalSize += strlen($content);
    }
    ksort($hashes, SORT_STRING);
    [$directories, $rootFiles] = U\managedPaths(array_keys($hashes));

    $composer = json_decode((string)file_get_contents($tree . '/composer.json'), true);
    $extensions = array();
    foreach (array_keys((array)($composer['require'] ?? array())) as $package) {
        if (is_string($package) && str_starts_with($package, 'ext-')) {
            $extensions[] = substr($package, 4);
        }
    }

    return json_encode(array(
        'version' => $version,
        'managedDirectories' => $directories,
        'managedRootFiles' => $rootFiles,
        'preservePaths' => PRESERVE_PATHS,
        'migrationPaths' => U\readMigrationPaths((string)@file_get_contents($tree . '/' . MIGRATION_PATHS_FILE)),
        'requirements' => array('php' => '>=' . minimumPhpVersion($composer), 'extensions' => $extensions),
        'totalSize' => $totalSize,
        'files' => $hashes
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

/**
 * The lowest PHP version that composer.json requires, for example 8.2 of "^8.2".
 * @param mixed $composer The decoded composer.json.
 */
function minimumPhpVersion(mixed $composer): string
{
    $php = $composer['require']['php'] ?? null;
    if (!is_string($php) || preg_match('/(\d+\.\d+(?:\.\d+)?)/', $php, $matches) !== 1) {
        throw new RuntimeException('composer.json names no PHP version.');
    }

    return $matches[1];
}

/**
 * Write a ZIP whose bytes depend only on its files: sorted entries, every entry with the time of
 * the commit.
 * @param array<string,string> $files path => file
 * @param array<string,string> $contents path => content
 */
function writeZip(string $target, string $prefix, array $files, array $contents, int $time): void
{
    // The files are read when the ZIP is written, not all at once.
    $entries = array_map(static fn(string $file): array => array('file' => $file), $files);
    $entries = array_map(static fn(string $content): array => array('content' => $content), $contents) + $entries;
    ksort($entries, SORT_STRING);

    @unlink($target);
    $zip = new ZipArchive();
    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException($target . ' cannot be created.');
    }
    foreach ($entries as $path => $entry) {
        $name = $prefix . $path;
        isset($entry['file']) ? $zip->addFile($entry['file'], $name) : $zip->addFromString($name, $entry['content']);
        $zip->setMtimeName($name, $time);
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
    }
    if (!$zip->close()) {
        throw new RuntimeException($target . ' cannot be written.');
    }
}

/**
 * The address of the package: below --package-url, or the asset of the GitHub release of the tag.
 * @param array<int|string,string|bool> $options
 */
function packageUrl(array $options, ?string $tag, string $file): string
{
    if (is_string($options['package-url'] ?? null) && $options['package-url'] !== '') {
        return rtrim($options['package-url'], '/') . '/' . $file;
    }
    if ($tag === null) {
        throw new RuntimeException('A release of a commit that is not a tag needs --package-url.');
    }

    return 'https://github.com/Admidio/admidio/releases/download/' . rawurlencode($tag) . '/' . $file;
}

/**
 * The lines of update.txt that announce the release: those of the stable release, or the Beta-
 * lines of a beta.
 * @return array<string,string>
 */
function updateInfoLines(string $version, string $packageUrl, string $sha256, int $size, string $minimumSourceVersion, string $minimumPhpVersion): array
{
    if (!U\isCanonicalVersion($minimumSourceVersion)) {
        throw new RuntimeException('The minimum source version ' . $minimumSourceVersion . ' is no version.');
    }

    $lines = array();
    $prefix = '';
    if (preg_match('/^(\d+\.\d+\.\d+)-beta\.(\d+)$/', $version, $matches) === 1) {
        $prefix = 'Beta-';
        $lines['Beta-Version'] = $matches[1];
        $lines['Beta-Release'] = $matches[2];
    } else {
        $lines['Version'] = $version;
    }

    return $lines + array(
        $prefix . 'Package-URL' => $packageUrl,
        $prefix . 'Package-SHA256' => $sha256,
        $prefix . 'Package-Size' => (string)$size,
        $prefix . 'Minimum-Source-Version' => $minimumSourceVersion,
        $prefix . 'Minimum-PHP-Version' => $minimumPhpVersion
    );
}

/**
 * The current update.txt, from a file or a URL.
 */
function readUpdateInfo(string $source): string
{
    if (preg_match('#^https?://#', $source) === 1) {
        return U\httpGet($source, 65536);
    }
    if (!is_file($source)) {
        throw new RuntimeException($source . ' does not exist.');
    }

    return (string)file_get_contents($source);
}

/**
 * update.txt with the lines of the release: a line that is there gets the new value in its place,
 * a new line is added at the end, and every other line stays as it is.
 * @param array<string,string> $lines
 */
function mergeUpdateInfo(string $current, array $lines): string
{
    $current = str_replace("\r\n", "\n", $current);
    $result = array();
    foreach ($current === '' ? array() : explode("\n", rtrim($current, "\n")) as $line) {
        $name = str_contains($line, '=') ? trim(explode('=', $line, 2)[0]) : '';
        if ($name !== '' && array_key_exists($name, $lines)) {
            $line = $name . '=' . $lines[$name];
            unset($lines[$name]);
        }
        $result[] = $line;
    }
    foreach ($lines as $name => $value) {
        $result[] = $name . '=' . $value;
    }

    return implode("\n", $result) . "\n";
}

/**
 * Check the release as the updater will: update.txt verifies with the signing key, which the
 * installations trust, and names the package, and every file of the package matches its manifest.
 */
function checkRelease(string $package, string $updateTxt, string $publicKey, string $version): void
{
    $text = (string)file_get_contents($updateTxt);
    if (!U\verifySignature($text, (string)file_get_contents($updateTxt . '.sig'), $publicKey)) {
        throw new RuntimeException('update.txt does not verify with the signing key.');
    }
    $release = U\releaseFromUpdateInfo(U\parseUpdateInfo($text), str_contains($version, '-beta.') ? 'beta' : 'stable');
    if ($release['version'] !== $version || $release['packageSha256'] !== hash_file('sha256', $package) || $release['packageSize'] !== filesize($package)) {
        throw new RuntimeException('update.txt does not describe the package.');
    }

    $zip = U\openPackage($package, U\MAX_EXTRACTED_SIZE);
    try {
        $index = U\packageFind($zip, 'install/update-manifest.json');
        $manifest = U\readManifest($index === null ? '' : U\packageRead($zip, $index, U\MAX_MANIFEST_SIZE), $version);
        foreach ($zip['entries'] as $index => $path) {
            if ($path === 'install/update-manifest.json') {
                continue;
            }
            $hash = hash_init('sha256');
            U\packageCopy($zip, $index, U\MAX_EXTRACTED_SIZE, static function (string $chunk) use ($hash): void {
                hash_update($hash, $chunk);
            });
            if (($manifest['files'][$path] ?? '') !== hash_final($hash)) {
                throw new RuntimeException($path . ' does not match the manifest.');
            }
        }
        if (count($zip['entries']) !== count($manifest['files']) + 1) {
            throw new RuntimeException('The package and its manifest do not list the same files.');
        }
    } finally {
        $zip['zip']->close();
    }
}
