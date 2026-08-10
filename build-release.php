<?php
/**
 * Build an Admidio distribution ZIP for the automatic core updater.
 *
 * Usage:
 *   php build-release.php --private-key=/secure/path/admidio-release-private.pem
 *
 * Optional:
 *   --output-dir=build
 *   --package-name=admidio-5.1.0.zip
 *   --write-update-info
 *
 * The private key is never copied into the package. If the private key is encrypted, set
 * ADMIDIO_RELEASE_KEY_PASSPHRASE in the environment.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

const CORE_PLUGIN_DIRECTORIES = array(
    'announcement-list',
    'birthday',
    'calendar',
    'event-list',
    'latest-documents-files',
    'login-form',
    'random-photo',
    'who-is-online'
);

const PRESERVE_PATHS = array(
    'adm_my_files',
    'config.php',
    'custom_css.css',
    'custom_favicon.png',
    'custom_logo.png',
    '.htaccess',
    '.user.ini',
    'robots.txt',
    'install/core-update'
);

const MANAGED_DIRECTORIES = array(
    'install',
    'languages',
    'libs',
    'modules',
    'rss',
    'src',
    'system',
    'vendor',
    'themes/simple'
);

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('This build script must be run from the command line.');
    }
    if (!extension_loaded('zip') || !extension_loaded('openssl')) {
        throw new RuntimeException('The PHP zip and openssl extensions are required.');
    }

    $options = getopt('', array('private-key:', 'output-dir::', 'package-name::', 'write-update-info'));
    $privateKeyFile = isset($options['private-key']) ? (string) $options['private-key'] : '';
    if ($privateKeyFile === '' || !is_readable($privateKeyFile)) {
        throw new RuntimeException('Use --private-key=/secure/path/release-private-key.pem.');
    }

    $rootPath = __DIR__;
    $publicKeyFile = $rootPath . '/install/core-update/public-key.pem';
    if (!is_readable($publicKeyFile)) {
        throw new RuntimeException(
            'The trusted release public key is missing: install/core-update/public-key.pem'
        );
    }

    if (!is_file($rootPath . '/vendor/autoload.php')
        || !is_file($rootPath . '/vendor/composer/installed.php')) {
        throw new RuntimeException(
            'Production Composer dependencies are missing. Run '
            . '"composer install --no-dev --prefer-dist --optimize-autoloader" before building.'
        );
    }

    $version = readAdmidioVersion($rootPath . '/system/bootstrap/constants.php');
    $safeVersion = strtolower((string) preg_replace('/[^a-zA-Z0-9.-]+/', '-', $version));
    $outputDirectory = isset($options['output-dir']) ? (string) $options['output-dir'] : 'build';
    if (!isAbsolutePath($outputDirectory)) {
        $outputDirectory = $rootPath . '/' . $outputDirectory;
    }
    createDirectory($outputDirectory);

    $packageName = isset($options['package-name'])
        ? basename((string) $options['package-name'])
        : 'admidio-' . trim($safeVersion, '-') . '.zip';
    if (!str_ends_with(strtolower($packageName), '.zip')) {
        $packageName .= '.zip';
    }

    $packageFile = rtrim($outputDirectory, '/\\') . '/' . $packageName;
    $temporaryPackageFile = $packageFile . '.tmp';
    @unlink($temporaryPackageFile);
    @unlink($packageFile);

    $privateKeyPem = file_get_contents($privateKeyFile);
    if ($privateKeyPem === false) {
        throw new RuntimeException('The release signing key could not be read.');
    }

    $passphrase = getenv('ADMIDIO_RELEASE_KEY_PASSPHRASE');
    $privateKey = openssl_pkey_get_private(
        $privateKeyPem,
        $passphrase === false ? '' : $passphrase
    );
    if ($privateKey === false) {
        throw new RuntimeException('The release private key could not be loaded.');
    }

    // The package may deliberately contain the public key for the *next* release. Therefore the
    // build key is not compared with install/core-update/public-key.pem here. During a key rollover,
    // the transition release is signed with the previously trusted key and installs the new public key.

    $zip = new ZipArchive();
    if ($zip->open($temporaryPackageFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('The distribution ZIP could not be created.');
    }

    $files = array();

    try {
        $rootFiles = array(
            'LICENSE',
            'README.md',
            'composer.json',
            'composer.lock',
            'index.php'
        );

        foreach ($rootFiles as $relativePath) {
            addDistributionFile($zip, $rootPath, $relativePath, $files);
        }

        $directories = array(
            'install',
            'languages',
            'libs',
            'modules',
            'rss',
            'src',
            'system',
            'vendor',
            'themes/simple'
        );

        foreach ($directories as $relativePath) {
            addDistributionDirectory($zip, $rootPath, $relativePath, $files);
        }

        foreach (CORE_PLUGIN_DIRECTORIES as $pluginDirectory) {
            addDistributionDirectory(
                $zip,
                $rootPath,
                'plugins/' . $pluginDirectory,
                $files
            );
        }

        foreach (array(
            'adm_my_files/.htaccess',
            'adm_my_files/index.html',
            'adm_my_files/config_example.php'
        ) as $relativePath) {
            addDistributionFile($zip, $rootPath, $relativePath, $files);
        }
        foreach (array('adm_my_files/ecard_templates', 'adm_my_files/mail_templates') as $relativePath) {
            addDistributionDirectory($zip, $rootPath, $relativePath, $files);
        }

        ksort($files, SORT_STRING);

        $managedDirectories = MANAGED_DIRECTORIES;
        foreach (CORE_PLUGIN_DIRECTORIES as $pluginDirectory) {
            $managedDirectories[] = 'plugins/' . $pluginDirectory;
        }
        sort($managedDirectories, SORT_STRING);

        $manifest = array(
            'schema' => 1,
            'version' => $version,
            'manageRootFiles' => true,
            'managedDirectories' => $managedDirectories,
            'preservePaths' => PRESERVE_PATHS,
            'files' => $files
        );

        $manifestContents = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;

        $manifestSignature = '';
        if (!openssl_sign($manifestContents, $manifestSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The internal update manifest could not be signed.');
        }

        $zip->addFromString('install/update-manifest.json', $manifestContents);
        $zip->addFromString(
            'install/update-manifest.json.sig',
            base64_encode($manifestSignature) . PHP_EOL
        );
    } finally {
        $zip->close();
    }

    if (!@rename($temporaryPackageFile, $packageFile)) {
        @unlink($temporaryPackageFile);
        throw new RuntimeException('The completed distribution ZIP could not be moved into place.');
    }

    $packageHash = hash_file('sha256', $packageFile);
    if ($packageHash === false) {
        throw new RuntimeException('The distribution ZIP checksum could not be calculated.');
    }

    $release = array(
        'schema' => 1,
        'version' => $version,
        'packageSha256' => $packageHash,
        'packageSize' => filesize($packageFile),
        'manifestSha256' => hash('sha256', $manifestContents)
    );
    $releaseContents = json_encode(
        $release,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    $releaseSignature = '';
    if (!openssl_sign($releaseContents, $releaseSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('The release descriptor could not be signed.');
    }

    $releaseFile = preg_replace('/\.zip$/i', '.release.json', $packageFile);
    if (!is_string($releaseFile)) {
        throw new RuntimeException('The release descriptor filename could not be created.');
    }

    if (file_put_contents($releaseFile, $releaseContents, LOCK_EX) === false
        || file_put_contents($releaseFile . '.sig', base64_encode($releaseSignature) . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('The release descriptor could not be written.');
    }

    $updateInformationFile = '';
    if (array_key_exists('write-update-info', $options)) {
        $updateInformationFile = rtrim($outputDirectory, '/\\') . '/update.txt';
        $updateInformation = 'Version=' . $version . PHP_EOL
            . 'Beta-Version=' . PHP_EOL
            . 'Beta-Release=' . PHP_EOL
            . 'Package-URL=' . basename($packageFile) . PHP_EOL
            . 'Release-URL=' . basename($releaseFile) . PHP_EOL;

        if (file_put_contents($updateInformationFile, $updateInformation, LOCK_EX) === false) {
            throw new RuntimeException('The local update.txt could not be written.');
        }
    }

    echo 'Created:' . PHP_EOL;
    echo '  ' . $packageFile . PHP_EOL;
    echo '  ' . $releaseFile . PHP_EOL;
    echo '  ' . $releaseFile . '.sig' . PHP_EOL;
    if ($updateInformationFile !== '') {
        echo '  ' . $updateInformationFile . PHP_EOL;
    }
    echo PHP_EOL;
    echo 'SHA-256: ' . $packageHash . PHP_EOL;
    echo PHP_EOL;
    echo 'Publish these update.txt entries:' . PHP_EOL;
    echo '  Package-URL=https://.../' . basename($packageFile) . PHP_EOL;
    echo '  Release-URL=https://.../' . basename($releaseFile) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param ZipArchive $zip
 * @param string $rootPath
 * @param string $relativePath
 * @param array<string,string> $files
 */
function addDistributionFile(ZipArchive $zip, string $rootPath, string $relativePath, array &$files): void
{
    $relativePath = normalizeRelativePath($relativePath);

    if (!shouldIncludeDistributionFile($relativePath)) {
        return;
    }

    $absolutePath = $rootPath . '/' . $relativePath;
    if (!is_file($absolutePath) || is_link($absolutePath)) {
        throw new RuntimeException('Required distribution file is missing or is a symbolic link: ' . $relativePath);
    }

    $hash = hash_file('sha256', $absolutePath);
    if ($hash === false) {
        throw new RuntimeException('Could not calculate checksum for: ' . $relativePath);
    }

    if (!$zip->addFile($absolutePath, $relativePath)) {
        throw new RuntimeException('Could not add file to distribution ZIP: ' . $relativePath);
    }

    $files[$relativePath] = $hash;
}

/**
 * @param ZipArchive $zip
 * @param string $rootPath
 * @param string $relativeDirectory
 * @param array<string,string> $files
 */
function addDistributionDirectory(
    ZipArchive $zip,
    string $rootPath,
    string $relativeDirectory,
    array &$files
): void {
    $relativeDirectory = normalizeRelativePath($relativeDirectory);
    $absoluteDirectory = $rootPath . '/' . $relativeDirectory;

    if (!is_dir($absoluteDirectory) || is_link($absoluteDirectory)) {
        throw new RuntimeException(
            'Required distribution directory is missing or is a symbolic link: ' . $relativeDirectory
        );
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absoluteDirectory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException(
                'Symbolic links are not allowed in the distribution: ' . $entry->getPathname()
            );
        }
        if (!$entry->isFile()) {
            continue;
        }

        $relativePath = substr(
            str_replace('\\', '/', $entry->getPathname()),
            strlen(str_replace('\\', '/', $rootPath)) + 1
        );
        addDistributionFile($zip, $rootPath, $relativePath, $files);
    }
}

function shouldIncludeDistributionFile(string $relativePath): bool
{
    if ($relativePath === 'install/update-manifest.json'
        || $relativePath === 'install/update-manifest.json.sig') {
        return false;
    }

    $excludedPrefixes = array(
        'vendor/maennchen/zipstream-php/test/',
        'vendor/markbaker/complex/examples/',
        'vendor/markbaker/matrix/examples/',
        'vendor/smarty/smarty/changelog/',
        'vendor/smarty/smarty/demo/',
        'vendor/smarty/smarty/docs/',
        'vendor/tecnickcom/tcpdf/examples/'
    );

    foreach ($excludedPrefixes as $excludedPrefix) {
        if (str_starts_with($relativePath, $excludedPrefix)) {
            return false;
        }
    }

    if (str_starts_with($relativePath, 'vendor/tecnickcom/tcpdf/fonts/')) {
        $fileName = basename($relativePath);
        return preg_match('/^(courier|helvetica|pdfa|times)/i', $fileName) === 1;
    }

    if (str_starts_with($relativePath, 'vendor/twbs/bootstrap/')) {
        $subPath = substr($relativePath, strlen('vendor/twbs/bootstrap/'));
        return $subPath === 'license'
            || $subPath === 'LICENSE'
            || str_starts_with($subPath, 'dist/');
    }

    if (str_starts_with($relativePath, 'vendor/twbs/bootstrap-icons/')) {
        $subPath = substr($relativePath, strlen('vendor/twbs/bootstrap-icons/'));
        return $subPath === 'license'
            || $subPath === 'LICENSE'
            || str_starts_with($subPath, 'font/');
    }

    return true;
}

function normalizeRelativePath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');

    if ($path === ''
        || str_starts_with($path, '/')
        || preg_match('/^[a-zA-Z]:\//', $path)
        || str_contains($path, "\0")
        || in_array('..', explode('/', $path), true)) {
        throw new RuntimeException('Invalid distribution path: ' . $path);
    }

    return $path;
}

function createDirectory(string $directory): void
{
    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create directory: ' . $directory);
    }
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/')
        || str_starts_with($path, '\\')
        || preg_match('/^[a-zA-Z]:[\/\\\\]/', $path) === 1;
}

function readAdmidioVersion(string $constantsFile): string
{
    $contents = file_get_contents($constantsFile);
    if ($contents === false) {
        throw new RuntimeException('system/bootstrap/constants.php could not be read.');
    }

    $values = array();
    foreach (array('MAIN', 'MINOR', 'PATCH', 'BETA') as $part) {
        if (!preg_match(
            '/const\s+ADMIDIO_VERSION_' . $part . '\s*=\s*(\d+)\s*;/',
            $contents,
            $matches
        )) {
            throw new RuntimeException('The Admidio version could not be read from constants.php.');
        }
        $values[$part] = (int) $matches[1];
    }

    $version = $values['MAIN'] . '.' . $values['MINOR'] . '.' . $values['PATCH'];
    if ($values['BETA'] > 0) {
        $version .= ' Beta ' . $values['BETA'];
    }

    return $version;
}
