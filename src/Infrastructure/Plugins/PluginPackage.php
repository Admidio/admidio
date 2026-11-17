<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use ZipArchive;

/**
 * A plugin as it arrives: one ZIP archive, from an upload or from the plugin store.
 *
 * The archive holds one top-level folder with the plugin in it. That folder is named after the
 * plugin ID, unless the manifest states the ID itself (**"id"** in plugin.json): then the folder may
 * be called anything, which is what makes the source archive GitHub generates for a release tag
 * (**repository-1.2.0/**) installable as it is.
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
     * The largest manifest that is read out of an archive before it is extracted, in bytes.
     */
    private const MAX_MANIFEST_BYTES = 1048576;

    /**
     * Archive entries that are never extracted: a path that has one of these names as any of its
     * segments is skipped.
     *
     * An archive built with create() does not contain them, but the source archive of a release
     * tag contains the whole repository, and the ZIP tool of macOS adds a **__MACOSX** folder of
     * resource forks next to the plugin folder. **tests** is skipped for the reason create()
     * leaves it out: Admidio runs the tests of every plugin that is present. vendor and
     * node_modules, which create() also leaves out, are extracted: an archive that carries them was
     * made that way on purpose.
     * @var array<int,string>
     */
    public const SKIPPED = array(
        '__MACOSX', '.git', '.github', '.svn', '.hg',
        'tests', 'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
        '.DS_Store', 'Thumbs.db', '.gitignore', '.gitattributes'
    );

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
        $contents = self::read($archivePath);
        $id = $contents['id'];

        if (!$replace && PluginRegistry::get($id) !== null) {
            throw new Exception('SYS_PLUGIN_ALREADY_EXISTS', array($id));
        }

        $temporary = self::extractToTemporaryDirectory($archivePath, $contents);

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
     * Read what an archive holds, without installing anything.
     *
     * The archive goes through the very gate install() puts it through - it is inspected, extracted
     * to a temporary directory of its own and its manifest is verified - and what came out is handed
     * back instead of being moved into place. An archive that describes cleanly will install.
     *
     * What comes back describes the manifest and nothing else. The **path** of the plugin is that
     * temporary directory, which is gone by then, so everything that reads the directory -
     * getDirectory(), getPages(), hasPages() - answers as it would for a plugin that is not there.
     * @param string $archivePath Absolute path of the ZIP archive.
     * @return Plugin The plugin the archive holds, read from its manifest.
     * @throws Exception
     */
    public static function describe(string $archivePath): Plugin
    {
        $contents = self::read($archivePath);
        $id = $contents['id'];
        $temporary = self::extractToTemporaryDirectory($archivePath, $contents);

        try {
            return self::verifyExtractedPlugin($temporary . '/' . $id, $id);
        } finally {
            self::deleteQuietly($temporary);
        }
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
        '.git', '.github', '.svn', '.hg', 'node_modules', 'vendor',
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
     * @return string The plugin ID: the "id" of the manifest, or else the name of the single
     *                top-level directory.
     * @throws Exception
     */
    public static function inspect(string $archivePath): string
    {
        return self::read($archivePath)['id'];
    }

    /**
     * The gate of inspect(), with everything extracting the archive needs.
     * @param string $archivePath Absolute path of the ZIP archive.
     * @return array{id:string,root:string,entries:array<int,string>} The plugin ID, the name of
     *         the top-level directory and the entries that are extracted.
     * @throws Exception
     */
    private static function read(string $archivePath): array
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
     * @return array{id:string,root:string,entries:array<int,string>}
     * @throws Exception
     */
    private static function readArchive(ZipArchive $archive): array
    {
        if ($archive->numFiles === 0) {
            throw new Exception('SYS_PLUGIN_PACKAGE_EMPTY');
        }

        if ($archive->numFiles > self::MAX_ENTRIES) {
            throw new Exception('SYS_PLUGIN_PACKAGE_TOO_LARGE');
        }

        $root = null;
        $extracted = 0;
        $manifestSize = null;
        $hasEntryFile = false;
        $entries = array();

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

            $segments = explode('/', trim($name, '/'));
            if (array_intersect($segments, self::SKIPPED) !== array()) {
                continue;
            }

            /*
             * The first segment of every entry has to be the same directory. An archive that unpacks
             * its files into the current directory would scatter them across plugins/, and one with
             * two top-level directories is not one plugin.
             */
            if (count($segments) < 2 && !str_ends_with($name, '/')) {
                throw new Exception('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');
            }

            if ($root === null) {
                $root = $segments[0];
            } elseif ($segments[0] !== $root) {
                throw new Exception('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');
            }

            if ($name === $root . '/' . Plugin::MANIFEST_FILE) {
                $manifestSize = (int)$entry['size'];
            }

            if ($name === $root . '/' . Plugin::ENTRY_FILE) {
                $hasEntryFile = true;
            }

            $entries[] = (string)$entry['name'];
        }

        if ($root === null) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');
        }

        if ($manifestSize === null) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NO_MANIFEST', array(Plugin::MANIFEST_FILE));
        }

        if (!$hasEntryFile) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NO_ENTRY_FILE', array(Plugin::ENTRY_FILE));
        }

        $id = $root;
        if ($manifestSize <= self::MAX_MANIFEST_BYTES) {
            $id = self::declaredId((string)$archive->getFromName($root . '/' . Plugin::MANIFEST_FILE)) ?? $root;
        }

        if (!Plugin::isValidId($id)) {
            throw new Exception('SYS_PLUGIN_PACKAGE_INVALID_ID', array($id));
        }

        return array('id' => $id, 'root' => $root, 'entries' => $entries);
    }

    /**
     * The plugin ID a manifest states itself, or null if it states none.
     *
     * A manifest that cannot be decoded states none here; it is refused once the plugin has been
     * extracted and read, with the error that says what is wrong with it.
     * @param string $raw The contents of plugin.json.
     * @return string|null
     */
    private static function declaredId(string $raw): ?string
    {
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || !array_key_exists('id', $manifest)) {
            return null;
        }

        return is_string($manifest['id']) ? $manifest['id'] : '';
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
     *
     * Only the entries the gate accepted are extracted, and the top-level directory is renamed to
     * the plugin ID if the archive named it differently.
     * @param string $archivePath
     * @param array{id:string,root:string,entries:array<int,string>} $contents What read() found.
     * @return string Absolute path of the temporary directory that now holds the plugin directory,
     *                named after the plugin ID.
     * @throws Exception
     */
    private static function extractToTemporaryDirectory(string $archivePath, array $contents): string
    {
        $id = $contents['id'];
        $temporary = rtrim(sys_get_temp_dir(), '/\\') . '/admidio-plugin-' . $id . '-' . uniqid('', true);

        if (!@mkdir($temporary, 0o700, true) && !is_dir($temporary)) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_EXTRACTED');
        }

        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            self::deleteQuietly($temporary);
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_A_ZIP');
        }

        $extracted = $archive->extractTo($temporary, $contents['entries']);
        $archive->close();

        if ($extracted === true && $contents['root'] !== $id) {
            $extracted = @rename($temporary . '/' . $contents['root'], $temporary . '/' . $id);
        }

        if ($extracted !== true) {
            self::deleteQuietly($temporary);
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_EXTRACTED');
        }

        return $temporary;
    }

    /**
     * Read the manifest of the extracted plugin and check that it is the plugin the archive claimed.
     *
     * The archive said which plugin it carries; the manifest says what the plugin is. They have to
     * agree, because the directory name is the identity the rest of Admidio uses.
     * @param string $directory The extracted plugin directory.
     * @param string $id The ID the archive claimed.
     * @return Plugin The plugin the manifest describes.
     * @throws Exception
     */
    private static function verifyExtractedPlugin(string $directory, string $id): Plugin
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

        return $plugin;
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

        $legacyConfig = self::readLegacyConfig($target, $id);
        if ($legacyConfig !== null
            && @file_put_contents($source . '/' . LegacyConfig::CARRIED_FILE, $legacyConfig) === false) {
            // before the old folder is deleted: it holds the only copy of the settings
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_INSTALLED', array($id));
        }

        if (is_dir($target)) {
            self::deleteQuietly($target);
        }

        if (is_dir($target) || !@rename($source, $target)) {
            /*
             * rename() fails across filesystems, which is the normal case when the system temporary
             * directory is on a different mount than the Admidio installation.
             */
            if (!self::copyDirectory($source, $target)) {
                // The old folder is gone, and the temporary directory goes too: its settings are put
                // back where the next attempt finds them.
                if ($legacyConfig !== null) {
                    self::restoreLegacyConfig($target, $legacyConfig);
                }
                throw new Exception('SYS_PLUGIN_PACKAGE_NOT_INSTALLED', array($id));
            }
        }
    }

    /**
     * The settings of a plugin from before the plugin system that the folder being replaced still
     * holds, or **null** if it holds none.
     *
     * A folder without a manifest is such a plugin, and its config.php holds the settings the
     * administrator made. A folder that already got them carried over by an earlier replacement
     * holds them as LegacyConfig::CARRIED_FILE until the update steps of the plugin took them over.
     * They are carried into the new version under that name, whatever files the new version brings,
     * so that its update steps can still read them (see LegacyConfig).
     * @param string $target The folder that is replaced.
     * @param string $id
     * @return string|null
     * @throws Exception If the file is there but cannot be read: the folder must not be deleted then.
     */
    private static function readLegacyConfig(string $target, string $id): ?string
    {
        if (is_file($target . '/' . LegacyConfig::CARRIED_FILE)) {
            $file = $target . '/' . LegacyConfig::CARRIED_FILE;
        } elseif (!is_file($target . '/' . Plugin::MANIFEST_FILE) && is_file($target . '/' . LegacyConfig::FILE)) {
            $file = $target . '/' . LegacyConfig::FILE;
        } else {
            return null;
        }

        $contents = is_readable($file) ? @file_get_contents($file) : false;
        if ($contents === false) {
            throw new Exception('SYS_PLUGIN_LEGACY_CONFIG_UNREADABLE', array($id, $file, 'The file cannot be read.'));
        }

        return $contents;
    }

    /**
     * Write the settings of a plugin from before the plugin system back into its folder, after
     * putting the new version in place failed. They go back as LegacyConfig::CARRIED_FILE, which
     * the next attempt carries over even if part of the new version, its manifest included, already
     * arrived in the folder.
     * @param string $target The folder of the plugin.
     * @param string $contents The contents of the old file.
     * @return void
     */
    private static function restoreLegacyConfig(string $target, string $contents): void
    {
        global $gLogger;

        $restored = (is_dir($target) || @mkdir($target, 0o755, true))
            && @file_put_contents($target . '/' . LegacyConfig::CARRIED_FILE, $contents) !== false;
        if (!$restored) {
            $gLogger->error('The old configuration of a plugin could not be put back.', array('file' => $target . '/' . LegacyConfig::CARRIED_FILE));
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
