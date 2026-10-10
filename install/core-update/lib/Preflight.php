<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The checks before an update is downloaded (P1 to P9 of the concept).
 *
 * They find what would make the update fail halfway - a file the web server may not replace, a full
 * disk, a database update that never finished - before anything is changed, and they say it in
 * plain words. An error blocks the update; a warning is shown and the administrator decides.
 *
 * P10, whether the workspace can be reached over HTTP, can only be answered by the browser, so the
 * updater page asks it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Preflight
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /**
     * The extensions the updater itself needs (P2).
     */
    private const UPDATER_EXTENSIONS = array('zip', 'curl', 'json', 'openssl', 'pdo');

    /**
     * What an installation without the manifest of its release manages (P9): the directories and
     * root files of every 5.x release.
     */
    private const DEFAULT_DIRECTORIES = array('src', 'modules', 'system', 'install', 'languages', 'libs', 'rss', 'vendor', 'themes/simple');
    private const DEFAULT_ROOT_FILES = array('index.php', 'admidio', 'sw.js', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', 'SECURITY.md');

    /**
     * The results so far.
     * @var array<int,array{check: string, status: string, text: string, params: array<int,string>}>
     */
    private array $results = array();

    /**
     * @param string $rootPath Directory of the installation.
     */
    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * Run the checks that need neither the package nor its manifest.
     * @param Descriptor $descriptor
     * @param string $installedVersion
     * @param string|null $databaseVersion **null** if the database version could not be read.
     * @param string $databaseType mysql or pgsql.
     * @param int $freeSpace Free bytes in adm_my_files, or -1 if unknown.
     * @return array<int,array{check: string, status: string, text: string, params: array<int,string>}>
     */
    public function run(Descriptor $descriptor, string $installedVersion, ?string $databaseVersion, string $databaseType, int $freeSpace): array
    {
        $this->results = array();

        $this->checkPhp($descriptor->minimumPhpVersion, $descriptor->version);
        $this->checkExtensions(self::UPDATER_EXTENSIONS, $databaseType);
        $this->checkVersion($descriptor, $installedVersion, $databaseVersion);
        $this->checkDatabase($installedVersion, $databaseVersion);
        $this->checkDataDirectory();
        // The extracted package is not known yet; three times the package is a generous estimate.
        $this->checkSpace($freeSpace, $descriptor->packageSize, 3 * $descriptor->packageSize);
        $this->checkOpenBasedir();
        $this->checkWritable();

        return $this->results;
    }

    /**
     * Run the checks that the manifest of the downloaded package makes exact: its PHP version and
     * extensions, and the disk space for its real size.
     * @param Manifest $manifest
     * @param int $packageSize
     * @param int $freeSpace
     * @return array<int,array{check: string, status: string, text: string, params: array<int,string>}>
     */
    public function runForManifest(Manifest $manifest, int $packageSize, int $freeSpace): array
    {
        $this->results = array();

        if (preg_match('/^>=\s*(\d+\.\d+(?:\.\d+)?)$/', $manifest->requirements['php'], $matches) === 1) {
            $this->checkPhp($matches[1], $manifest->version);
        }
        $this->checkExtensions($manifest->requirements['extensions'], '');
        // The package is already downloaded, so only the extracted files and the rollback copy count.
        $this->checkSpace($freeSpace, 0, $manifest->totalSize);

        return $this->results;
    }

    /**
     * Whether a list of results contains an error.
     * @param array<int,array{status: string}> $results
     * @return bool
     */
    public static function hasErrors(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['status'] === self::ERROR) {
                return true;
            }
        }

        return false;
    }

    /**
     * P1: the PHP version.
     */
    private function checkPhp(string $minimum, string $version): void
    {
        if (version_compare(PHP_VERSION, $minimum) < 0) {
            $this->add('P1', self::ERROR, 'SYS_CORE_UPDATE_PHP_TOO_OLD', array($version, $minimum, PHP_VERSION));
        } else {
            $this->add('P1', self::OK, 'SYS_CORE_UPDATE_CHECK_PHP', array(PHP_VERSION));
        }
    }

    /**
     * P2: the PHP extensions, with the PDO driver of the database.
     * @param array<int,string> $extensions
     */
    private function checkExtensions(array $extensions, string $databaseType): void
    {
        if ($databaseType !== '') {
            $extensions[] = $databaseType === 'mysql' ? 'pdo_mysql' : 'pdo_pgsql';
        }

        $missing = array_values(array_filter(array_unique($extensions), static fn(string $extension): bool => !extension_loaded($extension)));
        if ($missing !== array()) {
            $this->add('P2', self::ERROR, 'SYS_CORE_UPDATE_CHECK_EXTENSIONS_MISSING', array(implode(', ', $missing)));
        } else {
            $this->add('P2', self::OK, 'SYS_CORE_UPDATE_CHECK_EXTENSIONS');
        }
    }

    /**
     * P3: the installed version may be updated to the offered one.
     */
    private function checkVersion(Descriptor $descriptor, string $installedVersion, ?string $databaseVersion): void
    {
        try {
            $descriptor->checkCompatibility($installedVersion, PHP_VERSION);
            $rule = Version::check($descriptor->version, $installedVersion, $databaseVersion);
            $this->add('P3', self::OK, $rule === Version::REINSTALL ? 'SYS_CORE_UPDATE_CHECK_REINSTALL' : 'SYS_CORE_UPDATE_CHECK_VERSION',
                array($installedVersion, $descriptor->version));
        } catch (UpdaterException $exception) {
            $this->add('P3', self::ERROR, $exception->getMessage(), $exception->getParams());
        }
    }

    /**
     * P4: no database update is waiting. The database has the version of the files; if it is older,
     * install/update.php has not run or failed, and that has to be resolved first.
     */
    private function checkDatabase(string $installedVersion, ?string $databaseVersion): void
    {
        if ($databaseVersion === null) {
            $this->add('P4', self::ERROR, 'SYS_CORE_UPDATE_CHECK_DATABASE_UNKNOWN');
        } elseif (Version::compare($databaseVersion, $installedVersion) < 0) {
            $this->add('P4', self::ERROR, 'SYS_CORE_UPDATE_CHECK_DATABASE_PENDING', array($databaseVersion, $installedVersion));
        } else {
            $this->add('P4', self::OK, 'SYS_CORE_UPDATE_CHECK_DATABASE');
        }
    }

    /**
     * P5: adm_my_files can hold the workspace.
     */
    private function checkDataDirectory(): void
    {
        $directory = $this->rootPath . '/adm_my_files/core-update';
        if (self::canCreateAndRename($directory)) {
            $this->add('P5', self::OK, 'SYS_CORE_UPDATE_CHECK_DATA_DIRECTORY');
        } else {
            $this->add('P5', self::ERROR, 'SYS_CORE_UPDATE_CHECK_NOT_WRITABLE', array('adm_my_files/core-update'));
        }
    }

    /**
     * P6: enough free space for the package, the extracted files, the rollback copy and a margin.
     * Without an answer from the server, or under quotas the server does not report, it can only
     * be a warning.
     */
    private function checkSpace(int $freeSpace, int $packageSize, int $extractedSize): void
    {
        $needed = (int)ceil(($packageSize + 2 * $extractedSize) * 1.2);

        if ($freeSpace < 0) {
            $this->add('P6', self::WARNING, 'SYS_CORE_UPDATE_CHECK_SPACE_UNKNOWN', array(self::megabytes($needed)));
        } elseif ($freeSpace < $needed) {
            $this->add('P6', self::ERROR, 'SYS_CORE_UPDATE_CHECK_SPACE_MISSING', array(self::megabytes($needed), self::megabytes($freeSpace)));
        } else {
            $this->add('P6', self::OK, 'SYS_CORE_UPDATE_CHECK_SPACE', array(self::megabytes($freeSpace)));
        }
    }

    /**
     * P8: an open_basedir restriction must include the installation.
     */
    private function checkOpenBasedir(): void
    {
        $restriction = (string)ini_get('open_basedir');
        if ($restriction === '') {
            return;
        }

        $root = rtrim(str_replace('\\', '/', $this->rootPath), '/') . '/';
        foreach (explode(PATH_SEPARATOR, $restriction) as $allowed) {
            $allowed = rtrim(str_replace('\\', '/', $allowed), '/') . '/';
            if ($allowed !== '/' && str_starts_with($root, $allowed)) {
                $this->add('P8', self::OK, 'SYS_CORE_UPDATE_CHECK_OPEN_BASEDIR');

                return;
            }
        }
        $this->add('P8', self::ERROR, 'SYS_CORE_UPDATE_CHECK_OPEN_BASEDIR_MISSING');
    }

    /**
     * P9: a file can be created and renamed in every managed directory and next to every managed
     * root file. Files that the FTP user owns and the web server may not replace are found here,
     * before the download, instead of halfway through the installation.
     */
    private function checkWritable(): void
    {
        [$directories, $rootFiles] = $this->getManagedPaths();
        $failed = array();

        foreach ($directories as $directory) {
            if (is_dir($this->rootPath . '/' . $directory) && !self::canCreateAndRename($this->rootPath . '/' . $directory)) {
                $failed[] = $directory . '/';
            }
        }
        foreach ($rootFiles as $file) {
            if (is_file($this->rootPath . '/' . $file) && !is_writable($this->rootPath . '/' . $file)) {
                $failed[] = $file;
            }
        }
        if ($rootFiles !== array() && !self::canCreateAndRename($this->rootPath)) {
            $failed[] = '/';
        }

        if ($failed !== array()) {
            $this->add('P9', self::ERROR, 'SYS_CORE_UPDATE_CHECK_NOT_WRITABLE', array(implode(', ', $failed)));
        } else {
            $this->add('P9', self::OK, 'SYS_CORE_UPDATE_CHECK_WRITABLE');
        }
    }

    /**
     * The managed directories and root files of the installed release: from its manifest, or else
     * those of every 5.x release.
     * @return array{0: array<int,string>, 1: array<int,string>}
     */
    private function getManagedPaths(): array
    {
        $manifest = $this->rootPath . '/install/update-manifest.json';
        $data = is_file($manifest) ? json_decode((string)file_get_contents($manifest), true) : null;

        if (is_array($data) && is_array($data['managedDirectories'] ?? null) && is_array($data['managedRootFiles'] ?? null)) {
            return array(
                array_values(array_filter($data['managedDirectories'], static fn(mixed $path): bool => is_string($path) && Paths::isSafeRelative($path))),
                array_values(array_filter($data['managedRootFiles'], static fn(mixed $path): bool => is_string($path) && Paths::isSafeRelative($path)))
            );
        }

        return array(self::DEFAULT_DIRECTORIES, self::DEFAULT_ROOT_FILES);
    }

    /**
     * Whether a probe file can be created in a directory and renamed there - what replacing a file
     * of the installation needs.
     * @param string $directory
     * @return bool
     */
    public static function canCreateAndRename(string $directory): bool
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

    /**
     * @param int $bytes
     * @return string
     */
    private static function megabytes(int $bytes): string
    {
        return (string)max(1, (int)ceil($bytes / 1048576));
    }

    /**
     * @param string $check
     * @param string $status
     * @param string $text
     * @param array<int,string> $params
     */
    private function add(string $check, string $status, string $text, array $params = array()): void
    {
        $this->results[] = array('check' => $check, 'status' => $status, 'text' => $text, 'params' => $params);
    }
}
