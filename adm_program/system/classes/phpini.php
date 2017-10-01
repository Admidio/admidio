<?php
/**
 ***********************************************************************************************
 * Class manages PHP-Ini stuff
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class PhpIni
 */
class PhpIni
{
    const BYTES_UNIT_FACTOR_1024 = 1024;
    const BYTES_UNIT_FACTOR_1000 = 1000;

    /**
     * Returns the calculated bytes of a string or -1 if unlimited
     * @param string $data  Could be empty string (not set), "-1" (no limit) or a float with a unit
     * @param int    $multi Factor to multiply. Default: 1024
     * @return int
     */
    private static function getBytesFromSize($data, $multi = self::BYTES_UNIT_FACTOR_1024)
    {
        if ($data === '' || $data === '-1')
        {
            return -1;
        }

        $value = (float) substr($data, 0, -1);
        $unit  = strtoupper(substr($data, -1));

        switch ($unit)
        {
            case 'T':
                $value *= $multi;
            case 'G':
                $value *= $multi;
            case 'M':
                $value *= $multi;
            case 'K':
                $value *= $multi;
        }

        return (int) $value;
    }

    /**
     * Returns if safe-mode is enabled
     * @deprecated 3.3.0:4.0.0 This function will be removed if PHP 5.3 support gets dropped
     * @return bool
     */
    public static function isSafeModeEnabled()
    {
        return (bool) ini_get('safe_mode');
    }

    /**
     * Returns the allowed base-dirs
     * @return string[]
     */
    public static function getBaseDirs()
    {
        return explode(PATH_SEPARATOR, ini_get('open_basedir'));
    }

    /**
     * Returns the memory limit
     * @return int
     */
    public static function getMemoryLimit()
    {
        return self::getBytesFromSize(ini_get('memory_limit'));
    }

    /**
     * Returns the maximum post size
     * @return int
     */
    public static function getPostMaxSize()
    {
        return self::getBytesFromSize(ini_get('post_max_size'));
    }

    /**
     * Returns if file-upload is enabled
     * @return bool
     */
    public static function isFileUploadEnabled()
    {
        return (bool) ini_get('file_uploads');
    }

    /**
     * Returns the file upload temporary directory
     * @return string
     */
    public static function getFileUploadTmpDir()
    {
        return ini_get('upload_tmp_dir');
    }

    /**
     * Returns the maximum upload filesize
     * @return int
     */
    public static function getFileUploadMaxFileSize()
    {
        return self::getBytesFromSize(ini_get('upload_max_filesize'));
    }

    /**
     * Returns the maximum file upload count
     * @return int
     */
    public static function getFileUploadMaxFileCount()
    {
        return (int) ini_get('max_file_uploads');
    }

    /**
     * Returns the maximum upload size out of memory-limit, max-post-size and max-file-size
     * @return int
     */
    public static function getUploadMaxSize()
    {
        return min(self::getMemoryLimit(), self::getPostMaxSize(), self::getFileUploadMaxFileSize());
    }

    /**
     * Checks if the size limits have valid values because they depend on each other
     * @return bool
     */
    public static function checkSizeLimits()
    {
        return (self::getMemoryLimit() === -1 || self::getMemoryLimit() >= self::getPostMaxSize())
            && (self::getPostMaxSize() === -1 || self::getPostMaxSize() >= self::getFileUploadMaxFileSize());
    }

    /**
     * Checks if a given path is in the allowed base-dirs
     * @param string $dirPath The path to check
     * @return bool
     */
    private static function isInBaseDirs($dirPath)
    {
        $baseDirs = self::getBaseDirs();

        if ($baseDirs[0] === '')
        {
            return true;
        }

        $isInBaseDirs = false;
        foreach ($baseDirs as $baseDir)
        {
            if (strpos($dirPath, $baseDir) === 0)
            {
                $isInBaseDirs = true;
            }
        }

        return $isInBaseDirs;
    }

    /**
     * Sets the allowed base-dirs
     * @param string[] $dirPaths The paths to set as allowed base-dirs
     * @return bool|string
     * @throws Exception
     */
    public static function setBaseDirs(array $dirPaths = array())
    {
        $realDirPaths = array_map('realpath', $dirPaths);

        foreach ($realDirPaths as $realDirPath)
        {
            if ($realDirPath === false)
            {
                throw new Exception('Not a valid or allowed directory');
            }
            if (!self::isInBaseDirs($realDirPath))
            {
                throw new Exception('Not in base-directory!');
            }
        }

        return ini_set('open_basedir', implode(PATH_SEPARATOR, $realDirPaths));
    }

    /**
     * Sets the file upload temporary directory
     * @param string $dirPath The path to set the file upload temporary directory
     * @return bool|string
     * @throws Exception
     */
    public static function setFileUploadTmpDir($dirPath)
    {
        $realDirPath = realpath($dirPath);

        if ($realDirPath === false)
        {
            throw new Exception('Not a valid or allowed directory');
        }
        if (!self::isInBaseDirs($realDirPath))
        {
            throw new Exception('Not in base-directory!');
        }

        return ini_set('upload_tmp_dir', $realDirPath);
    }
}
