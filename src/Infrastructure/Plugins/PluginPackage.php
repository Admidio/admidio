<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use ZipArchive;

/**
 * A plugin as it arrives: one ZIP archive, from an upload or from the plugin store.
 *
 * Everything an archive contains is written by somebody the installation does not know, so it is
 * checked before a single byte of it reaches the plugins directory. The archive is read twice: once
 * to decide whether it may be extracted at all, and once - into a temporary directory - to read the
 * manifest of what it actually contains. Only then is the directory moved into place.
 *
 * The class never writes into plugins/ except with that final move, and it never leaves a partial
 * plugin behind: a failure at any point removes the temporary directory and throws.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginPackage
{
    /**
     * The largest archive that is accepted, in bytes. A plugin is source code, a few templates and a
     * handful of language files; anything far beyond this is not a plugin.
     */
    public const MAX_ARCHIVE_BYTES = 33554432;

    /**
     * The largest total size the extracted files may reach, in bytes. Without this an archive of a
     * few kilobytes could expand until the disk is full.
     */
    public const MAX_EXTRACTED_BYTES = 134217728;

    /**
     * The largest number of entries an archive may contain.
     */
    public const MAX_ENTRIES = 5000;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Install a plugin from an archive.
     *
     * The plugin is only put on disk. Nothing is written to the database and the plugin is not
     * enabled: it becomes available, and enabling it is the separate decision that prepares it.
     * @param string $archivePath Absolute path of the ZIP archive.
     * @param bool $replace Whether an existing plugin of the same ID may be overwritten. The caller
     *                      asks the administrator; this class only obeys.
     * @return string The ID of the plugin that was installed.
     * @throws Exception
     */
    public static function install(string $archivePath, bool $replace = false): string
    {
        $id = self::inspect($archivePath);

        if (!$replace && PluginRegistry::get($id) !== null) {
            throw new Exception('SYS_PLUGIN_ALREADY_EXISTS', array($id));
        }

        $temporary = self::extractToTemporaryDirectory($archivePath, $id);

        try {
            self::verifyExtractedPlugin($temporary . '/' . $id, $id);
            self::moveIntoPlace($temporary . '/' . $id, $id);
        } finally {
            self::deleteQuietly($temporary);
        }

        PluginRegistry::reset();

        return $id;
    }


    /**
     * Everything that belongs to working on a plugin rather than to the plugin.
     *
     * A directory whose name matches is skipped whole; a file whose name matches is left out. The
     * list is deliberately short: a plugin author who keeps something unusual in the directory
     * should not find it silently missing from the archive.
     *
     * **tests** and the PHPUnit configuration beside it are excluded because Admidio runs the tests
     * of every plugin that is present, so a distributed one would run in installations that have
     * nothing to do with developing it.
     * @var array<int,string>
     */
    public const EXCLUDED = array(
        '.git', '.svn', '.hg', 'node_modules', 'vendor',
        'tests', 'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
        '.DS_Store', 'Thumbs.db', '.gitignore', '.gitattributes'
    );

    /**
     * Build the archive that distributes a plugin.
     *
     * The archive is what an administrator uploads and what the plugin store serves, so it is
     * checked with the very gate the installer uses before it is handed back. A plugin that packages
     * cleanly will install.
     * @param Plugin $plugin The plugin to package. It has to be readable and valid.
     * @param string $directory Absolute path of the directory the archive is written to.
     * @return string Absolute path of the archive.
     * @throws Exception
     */
    public static function create(Plugin $plugin, string $directory): string
    {
        if (!$plugin->isValid()) {
            throw new Exception('SYS_PLUGIN_PACKAGE_BROKEN_MANIFEST', array((string)$plugin->error));
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            throw new Exception('SYS_PLUGIN_ARCHIVE_NOT_WRITABLE', array($directory));
        }

        $archivePath = rtrim(str_replace('\\', '/', $directory), '/')
            . '/' . $plugin->id . '-' . $plugin->version . '.zip';

        /*
         * The file list is taken before the archive is created, so an archive written into the
         * plugin's own directory cannot end up inside itself.
         */
        $files = self::collectFiles($plugin->path);
        if ($files === array()) {
            throw new Exception('SYS_PLUGIN_PACKAGE_EMPTY');
        }

        $archive = new ZipArchive();
        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('SYS_PLUGIN_ARCHIVE_NOT_WRITABLE', array($directory));
        }

        foreach ($files as $relative => $absolute) {
            $archive->addFile($absolute, $plugin->id . '/' . $relative);
        }

        if ($archive->close() !== true) {
            throw new Exception('SYS_PLUGIN_ARCHIVE_NOT_WRITABLE', array($directory));
        }

        // The archive has to pass the gate it will be installed through.
        self::inspect($archivePath);

        return $archivePath;
    }

    /**
     * Every file of a plugin directory that belongs in its archive, as relative path => absolute
     * path, in a stable order.
     * @param string $directory
     * @return array<string,string>
     */
    private static function collectFiles(string $directory): array
    {
        $root = rtrim(str_replace('\\', '/', $directory), '/');
        $files = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $entry): bool {
                    return !in_array($entry->getFilename(), self::EXCLUDED, true);
                }
            )
        );

        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }

            $absolute = str_replace('\\', '/', $entry->getPathname());
            $files[substr($absolute, strlen($root) + 1)] = $absolute;
        }

        // A stable order makes two archives of the same source comparable.
        ksort($files);

        return $files;
    }

    /**
     * Read an archive and answer which plugin it holds, without extracting anything.
     *
     * This is the gate. Everything it rejects is rejected before any file is written, so a hostile
     * archive never reaches the filesystem at all.
     * @param string $archivePath Absolute path of the ZIP archive.
     * @return string The plugin ID, which is the name of the single top-level directory.
     * @throws Exception
     */
    public static function inspect(string $archivePath): string
    {
        if (!is_file($archivePath)) {
            throw new Exception('SYS_PLUGIN_PACKAGE_UNREADABLE');
        }

        if (filesize($archivePath) > self::MAX_ARCHIVE_BYTES) {
            throw new Exception('SYS_PLUGIN_PACKAGE_TOO_LARGE');
        }

        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_A_ZIP');
        }

        try {
            return self::readArchive($archive);
        } finally {
            $archive->close();
        }
    }

    /**
     * Walk every entry of an opened archive and decide whether it may be extracted.
     * @param ZipArchive $archive
     * @return string The plugin ID.
     * @throws Exception
     */
    private static function readArchive(ZipArchive $archive): string
    {
        if ($archive->numFiles === 0) {
            throw new Exception('SYS_PLUGIN_PACKAGE_EMPTY');
        }

        if ($archive->numFiles > self::MAX_ENTRIES) {
            throw new Exception('SYS_PLUGIN_PACKAGE_TOO_LARGE');
        }

        $root = null;
        $extracted = 0;
        $hasManifest = false;
        $hasEntryFile = false;

        for ($index = 0; $index < $archive->numFiles; ++$index) {
            $entry = $archive->statIndex($index);
            if ($entry === false) {
                throw new Exception('SYS_PLUGIN_PACKAGE_UNREADABLE');
            }

            $name = str_replace('\\', '/', (string)$entry['name']);
            self::rejectUnsafeName($name);

            $extracted += (int)$entry['size'];
            if ($extracted > self::MAX_EXTRACTED_BYTES) {
                throw new Exception('SYS_PLUGIN_PACKAGE_TOO_LARGE');
            }

            /*
             * The first segment of every entry has to be the same directory. An archive that unpacks
             * its files into the current directory would scatter them across plugins/, and one with
             * two top-level directories is not one plugin.
             */
            $segments = explode('/', trim($name, '/'));
            if (count($segments) < 2 && !str_ends_with($name, '/')) {
                throw new Exception('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');
            }

            if ($root === null) {
                $root = $segments[0];
            } elseif ($segments[0] !== $root) {
                throw new Exception('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');
            }

            if ($name === $root . '/' . Plugin::MANIFEST_FILE) {
                $hasManifest = true;
            }

            if ($name === $root . '/' . Plugin::ENTRY_FILE) {
                $hasEntryFile = true;
            }
        }

        if ($root === null || !Plugin::isValidId($root)) {
            throw new Exception('SYS_PLUGIN_PACKAGE_INVALID_ID', array((string)$root));
        }

        if (!$hasManifest) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NO_MANIFEST', array(Plugin::MANIFEST_FILE));
        }

        if (!$hasEntryFile) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NO_ENTRY_FILE', array(Plugin::ENTRY_FILE));
        }

        return $root;
    }

    /**
     * Refuse an archive entry whose name would write outside the directory it is extracted into.
     *
     * ZipArchive::extractTo() is not documented to defend against this, so the archive is refused
     * rather than sanitized: a name that has to be repaired is not a name a plugin author wrote by
     * accident.
     * @param string $name The entry name, with the separators already normalized to "/".
     * @return void
     * @throws Exception
     */
    private static function rejectUnsafeName(string $name): void
    {
        if ($name === '') {
            throw new Exception('SYS_PLUGIN_PACKAGE_UNSAFE_ENTRY', array($name));
        }

        // An absolute path, a Windows drive letter, a parent reference or a null byte.
        if (str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:/', $name) === 1
            || in_array('..', explode('/', $name), true)
            || str_contains($name, "\0")) {
            throw new Exception('SYS_PLUGIN_PACKAGE_UNSAFE_ENTRY', array($name));
        }
    }

    /**
     * Extract a checked archive into a temporary directory of its own.
     * @param string $archivePath
     * @param string $id The plugin ID, used only to name the temporary directory recognizably.
     * @return string Absolute path of the temporary directory that now holds the plugin directory.
     * @throws Exception
     */
    private static function extractToTemporaryDirectory(string $archivePath, string $id): string
    {
        $temporary = rtrim(sys_get_temp_dir(), '/\\') . '/admidio-plugin-' . $id . '-' . uniqid('', true);

        if (!@mkdir($temporary, 0o700, true) && !is_dir($temporary)) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_EXTRACTED');
        }

        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            self::deleteQuietly($temporary);
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_A_ZIP');
        }

        $extracted = $archive->extractTo($temporary);
        $archive->close();

        if ($extracted !== true) {
            self::deleteQuietly($temporary);
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_EXTRACTED');
        }

        return $temporary;
    }

    /**
     * Read the manifest of the extracted plugin and check that it is the plugin the archive claimed.
     *
     * The archive said which directory it carries; the manifest says what the plugin is. They have to
     * agree, because the directory name is the identity the rest of Admidio uses.
     * @param string $directory The extracted plugin directory.
     * @param string $id The ID the archive claimed.
     * @return void
     * @throws Exception
     */
    private static function verifyExtractedPlugin(string $directory, string $id): void
    {
        if (!is_dir($directory)) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_EXTRACTED');
        }

        $plugin = Plugin::read($directory);

        if (!$plugin->isValid()) {
            throw new Exception('SYS_PLUGIN_PACKAGE_BROKEN_MANIFEST', array((string)$plugin->error));
        }

        if ($plugin->id !== $id) {
            throw new Exception('SYS_PLUGIN_PACKAGE_INVALID_ID', array($plugin->id));
        }
    }

    /**
     * Put the checked plugin directory into plugins/, replacing what is there.
     * @param string $source The extracted plugin directory.
     * @param string $id
     * @return void
     * @throws Exception
     */
    private static function moveIntoPlace(string $source, string $id): void
    {
        $target = PluginRegistry::getPluginsPath() . '/' . $id;

        if (is_dir($target)) {
            self::deleteQuietly($target);
        }

        if (is_dir($target) || !@rename($source, $target)) {
            /*
             * rename() fails across filesystems, which is the normal case when the system temporary
             * directory is on a different mount than the Admidio installation.
             */
            if (!self::copyDirectory($source, $target)) {
                throw new Exception('SYS_PLUGIN_PACKAGE_NOT_INSTALLED', array($id));
            }
        }
    }

    /**
     * Copy a directory recursively, for the case where rename() cannot cross a filesystem boundary.
     * @param string $source
     * @param string $target
     * @return bool
     */
    private static function copyDirectory(string $source, string $target): bool
    {
        if (!is_dir($target) && !@mkdir($target, 0o755, true) && !is_dir($target)) {
            return false;
        }

        foreach (scandir($source) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $from = $source . '/' . $entry;
            $to = $target . '/' . $entry;

            $copied = is_dir($from) ? self::copyDirectory($from, $to) : @copy($from, $to);
            if (!$copied) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove a directory and everything below it, without turning a cleanup failure into the error
     * the caller reports.
     * @param string $directory
     * @return void
     */
    private static function deleteQuietly(string $directory): void
    {
        try {
            FileSystemUtils::deleteDirectoryIfExists($directory, true);
        } catch (\Throwable) {
            // A temporary directory that survives is untidy, not a reason to fail the operation.
        }
    }
}
