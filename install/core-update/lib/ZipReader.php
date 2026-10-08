<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use ZipArchive;

/**
 * Reads an update package without trusting it.
 *
 * Every entry is checked before anything is extracted: its name must be a safe relative path, it
 * must not be a symbolic link, and no two entries may have the same name - also not in another
 * case, which a case-insensitive file system would turn into one file. Entries are read by their
 * index and never by name, so a second entry with a crafted name cannot take the place of a checked
 * one. The size an entry declares is not believed: the bytes that are actually written are counted
 * against a limit.
 *
 * A package may wrap everything in one top directory (admidio-5.1.0/...), as a GitHub archive does;
 * that directory is taken off the paths.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class ZipReader
{
    /** Bytes per read when an entry is copied. */
    private const CHUNK = 65536;

    /**
     * @param ZipArchive $zip
     * @param array<int,string> $entries index => path relative to the package root, files only.
     */
    private function __construct(private readonly ZipArchive $zip, private readonly array $entries)
    {
    }

    /**
     * Open a package and check all its entries.
     * @param string $file
     * @param int $maxTotalSize Upper limit of the sizes the entries declare together.
     * @return self
     * @throws UpdaterException SYS_CORE_UPDATE_PACKAGE_INVALID
     */
    public static function open(string $file, int $maxTotalSize): self
    {
        if (!extension_loaded('zip')) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS', 'ext-zip is not loaded.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            throw self::invalid('The package is no readable ZIP archive.');
        }

        try {
            $names = array();
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index, ZipArchive::FL_UNCHANGED);
                if (!is_string($name) || $name === '') {
                    throw self::invalid('Entry ' . $index . ' has no name.');
                }
                $names[$index] = $name;
            }

            $root = self::findRoot($names);
            $entries = array();
            $caseKeys = array();
            $declaredSize = 0;

            foreach ($names as $index => $name) {
                $path = substr($name, strlen($root));
                if ($path === '' || str_ends_with($path, '/')) {
                    // A directory entry; the directories come from the file paths.
                    if (!Paths::isSafeRelative(rtrim($root . $path, '/'))) {
                        throw self::invalid('The directory entry "' . $name . '" is not a safe path.');
                    }
                    continue;
                }
                if (!Paths::isSafeRelative($path)) {
                    throw self::invalid('The entry "' . $name . '" is not a safe path.');
                }
                if (self::isSymlink($zip, $index)) {
                    throw self::invalid('The entry "' . $name . '" is a symbolic link.');
                }
                if (isset($caseKeys[strtolower($path)])) {
                    throw self::invalid('The entry "' . $name . '" exists twice, possibly in another case.');
                }
                $caseKeys[strtolower($path)] = true;

                $declaredSize += (int)($zip->statIndex($index)['size'] ?? 0);
                if ($declaredSize > $maxTotalSize) {
                    throw self::invalid('The package is larger than ' . $maxTotalSize . ' bytes when extracted.');
                }

                $entries[$index] = $path;
            }
        } catch (UpdaterException $exception) {
            $zip->close();
            throw $exception;
        }

        return new self($zip, $entries);
    }

    /**
     * The files of the package, as index => path relative to the package root.
     * @return array<int,string>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * The index of a file, or **null** if the package does not contain it.
     * @param string $path
     * @return int|null
     */
    public function find(string $path): ?int
    {
        $index = array_search($path, $this->entries, true);

        return $index === false ? null : $index;
    }

    /**
     * Read a small file into memory.
     * @param int $index
     * @param int $maxBytes
     * @return string
     * @throws UpdaterException
     */
    public function read(int $index, int $maxBytes): string
    {
        $content = '';
        $this->copy($index, $maxBytes, static function (string $chunk) use (&$content): void {
            $content .= $chunk;
        });

        return $content;
    }

    /**
     * Write one file of the package.
     * @param int $index
     * @param string $targetFile
     * @param int $maxBytes The most bytes the file may have; what is written is counted.
     * @return string The SHA-256 of the bytes that were written.
     * @throws UpdaterException
     */
    public function extract(int $index, string $targetFile, int $maxBytes): string
    {
        $target = @fopen($targetFile, 'wb');
        if ($target === false) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }

        $hash = hash_init('sha256');
        try {
            $this->copy($index, $maxBytes, static function (string $chunk) use ($target, $hash, $targetFile): void {
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
     * Close the archive.
     * @return void
     */
    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * Stream an entry in chunks and stop when it has more bytes than allowed.
     * @param int $index
     * @param int $maxBytes
     * @param callable(string): void $consume
     * @return void
     */
    private function copy(int $index, int $maxBytes, callable $consume): void
    {
        if (!isset($this->entries[$index])) {
            throw self::invalid('There is no file with the index ' . $index . '.');
        }

        $stream = $this->zip->getStreamIndex($index, ZipArchive::FL_UNCHANGED);
        if ($stream === false) {
            throw self::invalid('The entry "' . $this->entries[$index] . '" cannot be read.');
        }

        try {
            $written = 0;
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK);
                if ($chunk === false) {
                    throw self::invalid('The entry "' . $this->entries[$index] . '" cannot be read.');
                }
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    throw self::invalid('The entry "' . $this->entries[$index] . '" is larger than ' . $maxBytes . ' bytes.');
                }
                $consume($chunk);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * The top directory all entries share, with its slash, or an empty string.
     * @param array<int,string> $names
     * @return string
     */
    private static function findRoot(array $names): string
    {
        $first = reset($names);
        $slash = $first === false ? false : strpos($first, '/');
        if ($slash === false) {
            return '';
        }

        $root = substr($first, 0, $slash + 1);
        foreach ($names as $name) {
            if (!str_starts_with($name, $root)) {
                return '';
            }
        }

        return $root;
    }

    /**
     * Whether an entry is a symbolic link: the Unix file type in the external attributes.
     * @param ZipArchive $zip
     * @param int $index
     * @return bool
     */
    private static function isSymlink(ZipArchive $zip, int $index): bool
    {
        if (!$zip->getExternalAttributesIndex($index, $system, $attributes)) {
            return false;
        }

        return $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000;
    }

    /**
     * @param string $detail
     * @return UpdaterException
     */
    private static function invalid(string $detail): UpdaterException
    {
        return UpdaterException::withDetail('SYS_CORE_UPDATE_PACKAGE_INVALID', $detail);
    }
}
