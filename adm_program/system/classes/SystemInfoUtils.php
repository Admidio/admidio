<?php
/**
 ***********************************************************************************************
 * Class to get system information
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

final class SystemInfoUtils
{
    const UNAME_MODE_A = 'a';
    const UNAME_MODE_S = 's';
    const UNAME_MODE_N = 'n';
    const UNAME_MODE_R = 'r';
    const UNAME_MODE_V = 'v';
    const UNAME_MODE_M = 'm';

    /**
     * @return string
     */
    public static function getOS()
    {
        return PHP_OS;
    }

    /**
     * @param string $mode (a, s, n, r, v, m)
     * @return string
     */
    public static function getUname($mode = self::UNAME_MODE_A)
    {
        return php_uname($mode);
    }

    /**
     * @return bool
     */
    public static function is32Bit()
    {
        return PHP_INT_SIZE === 4;
    }

    /**
     * @return bool
     */
    public static function is64Bit()
    {
        return PHP_INT_SIZE === 8;
    }

    /**
     * @return bool
     */
    public static function isUnixFileSystem()
    {
        return DIRECTORY_SEPARATOR === '/';
    }

    /**
     * @return string
     */
    public static function getDirectorySeparator()
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    public static function getPathSeparator()
    {
        return PATH_SEPARATOR;
    }

    /**
     * @return int
     */
    public static function getMaxPathLength()
    {
        return PHP_MAXPATHLEN;
    }
}
