<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The one rule for a path that comes from an update package or its manifest.
 *
 * Such a path is joined to the installation directory, so it must not be able to leave it: it is
 * relative, uses forward slashes, and has no empty, "." or ".." segment, no drive prefix and no NUL
 * byte.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Paths
{
    /**
     * Whether a path is a safe relative path inside the installation.
     * @param string $path
     * @return bool
     */
    public static function isSafeRelative(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')
            || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
