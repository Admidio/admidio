<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use Throwable;

/**
 * Versions and the rule which version may be installed.
 *
 * A version is written **X.Y.Z** for a release and **X.Y.Z-beta.N** for a beta, so that PHP's
 * version_compare() orders them: 6.0.0-beta.1 &lt; 6.0.0 &lt; 6.0.1.
 *
 * The rule: the target must be the same as or newer than the installed files and the database. The
 * same version is a reinstall - it resets local modifications. An older version is refused, because
 * the database may already be ahead of what its files expect.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Version
{
    /** The target is newer: an update. */
    public const UPDATE = 'update';
    /** The target is the installed version: a reinstall. */
    public const REINSTALL = 'reinstall';

    /**
     * Whether a string is a canonical version.
     * @param string $version
     * @return bool
     */
    public static function isCanonical(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-beta\.[1-9]\d*)?$/', $version) === 1;
    }

    /**
     * The canonical version of the version constants of Admidio.
     * @param int $main
     * @param int $minor
     * @param int $patch
     * @param int $beta 0 for a release.
     * @return string
     */
    public static function fromParts(int $main, int $minor, int $patch, int $beta): string
    {
        return $main . '.' . $minor . '.' . $patch . ($beta > 0 ? '-beta.' . $beta : '');
    }

    /**
     * Compare two canonical versions like version_compare().
     * @param string $a
     * @param string $b
     * @return int
     */
    public static function compare(string $a, string $b): int
    {
        return version_compare($a, $b);
    }

    /**
     * The version of the installed files: from the manifest of the installed release, or else from
     * the version constants. The constants file is read as text, never included, because it may
     * belong to another release than the updater.
     * @param string $rootPath
     * @return string
     * @throws UpdaterException The version cannot be determined.
     */
    public static function ofFiles(string $rootPath): string
    {
        $manifest = $rootPath . '/install/update-manifest.json';
        if (is_file($manifest)) {
            $version = json_decode((string)file_get_contents($manifest), true)['version'] ?? null;
            if (is_string($version) && self::isCanonical($version)) {
                return $version;
            }
        }

        $constants = (string)@file_get_contents($rootPath . '/system/bootstrap/constants.php');
        $parts = array();
        foreach (array('MAIN', 'MINOR', 'PATCH', 'BETA') as $part) {
            if (preg_match('/const\s+ADMIDIO_VERSION_' . $part . '\s*=\s*(\d+)\s*;/', $constants, $matches) !== 1) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_NOT_AVAILABLE', 'The installed version cannot be read from system/bootstrap/constants.php.');
            }
            $parts[] = (int)$matches[1];
        }

        return self::fromParts(...$parts);
    }

    /**
     * The version of the database, or **null** if it cannot be read.
     * @param Db $db
     * @return string|null
     */
    public static function ofDatabase(Db $db): ?string
    {
        try {
            $row = $db->fetchRow(
                'SELECT com_version, com_beta FROM ' . $db->table('components') . ' WHERE com_type = ? AND com_name_intern = ?',
                array('SYSTEM', 'CORE')
            );
        } catch (Throwable) {
            return null;
        }

        if ($row === null || preg_match('/^(\d+)\.(\d+)\.(\d+)/', (string)$row['com_version'], $matches) !== 1) {
            return null;
        }

        return self::fromParts((int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$row['com_beta']);
    }

    /**
     * Apply the version rule.
     * @param string $target
     * @param string $installed The version of the files.
     * @param string|null $database The version of the database, if known.
     * @return string UPDATE or REINSTALL.
     * @throws UpdaterException SYS_CORE_UPDATE_VERSION_OLDER or SYS_CORE_UPDATE_DATABASE_NEWER
     */
    public static function check(string $target, string $installed, ?string $database): string
    {
        if (self::compare($target, $installed) < 0) {
            throw new UpdaterException('SYS_CORE_UPDATE_VERSION_OLDER', array($target, $installed));
        }
        if ($database !== null && self::compare($target, $database) < 0) {
            throw new UpdaterException('SYS_CORE_UPDATE_DATABASE_NEWER', array($database, $target));
        }

        return self::compare($target, $installed) === 0 ? self::REINSTALL : self::UPDATE;
    }
}
