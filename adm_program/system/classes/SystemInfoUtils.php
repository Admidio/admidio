<?php
/**
 ***********************************************************************************************
 * Class to get system information
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class SystemInfoUtils
{
    public const UNAME_MODE_A = 'a';
    public const UNAME_MODE_S = 's';
    public const UNAME_MODE_N = 'n';
    public const UNAME_MODE_R = 'r';
    public const UNAME_MODE_V = 'v';
    public const UNAME_MODE_M = 'm';

    /**
     * @return string
     */
    public static function getOS(): string
    {
        return PHP_OS;
    }

    /**
     * @param string $mode (a, s, n, r, v, m)
     * @return string
     */
    public static function getUname(string $mode = self::UNAME_MODE_A): string
    {
        return php_uname($mode);
    }

    /**
     * @return bool
     */
    public static function is32Bit(): bool
    {
        return PHP_INT_SIZE === 4;
    }

    /**
     * @return bool
     */
    public static function is64Bit(): bool
    {
        return PHP_INT_SIZE === 8;
    }

    /**
     * @return bool
     */
    public static function isUnixFileSystem(): bool
    {
        return DIRECTORY_SEPARATOR === '/';
    }

    /**
     * @return string
     */
    public static function getDirectorySeparator(): string
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    public static function getPathSeparator(): string
    {
        return PATH_SEPARATOR;
    }

    /**
     * @return int
     */
    public static function getMaxPathLength(): int
    {
        return PHP_MAXPATHLEN;
    }

    /**
     * Returns the maximum number of pixels the memory can handle.
     * @return int Returns the maximum number of pixels the memory can handle.
     */
    public static function getProcessableImageSize(): int
    {
        $memoryLimit = PhpIniUtils::getMemoryLimit();
        // if memory_limit is disabled in php.ini
        if (is_infinite($memoryLimit)) {
            $memoryLimit = 128 * 1024 * 1024; // 128MB
        }

        // For each Pixel 3 Bytes are necessary (RGB)
        $bytesPerPixel = 3;
        // current tests have shown that we don't need a factor to limit the processable image size
        $factor = 1;

        return (int) round($memoryLimit / ($bytesPerPixel * $factor));
    }
}
