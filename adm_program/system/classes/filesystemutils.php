<?php
/**
 ***********************************************************************************************
 * This class handles the most necessary file-system operations
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class FileSystemUtils
 * This class handles the most necessary file-system operations like:
 * - Restrict all operations to specific directories
 * - Info: process owner/group info, path owner/group info, is path owner, path mode, path permissions
 * - Folder: create, is empty, get content, delete content, delete folder, copy, move, chmod
 * - File: delete, copy, move, chmod, read, write
 */
final class FileSystemUtils
{
    const CONTENT_TYPE_DIRECTORY = 'directory';
    const CONTENT_TYPE_FILE      = 'file';
    const CONTENT_TYPE_LINK      = 'link';

    const ROOT_ID = 0;
    const ROOT_FOLDER = '/';

    /**
     * @var array<int,string> The allowed directories
     */
    private static $allowedDirectories = array();

    /**
     * Restrict all operations of this class to specific directories
     * @param array<int,string> $directoryPaths The allowed directories
     * @throws \UnexpectedValueException Throws if a given directory does not exist
     */
    public static function setAllowedDirectories(array $directoryPaths = array())
    {
        foreach ($directoryPaths as $directoryPath)
        {
            if (!is_dir($directoryPath))
            {
                throw new \UnexpectedValueException('Directory does not exist!');
            }
        }

        self::$allowedDirectories = $directoryPaths;
    }

    /**
     * Check if a given path is in the allowed directories
     * @param string $path The path to check
     * @throws \RuntimeException Throws if the given path is not in an allowed directory
     */
    private static function checkIsInAllowedDirectories($path)
    {
        if (count(self::$allowedDirectories) === 0)
        {
            return;
        }

        foreach (self::$allowedDirectories as $allowedDirectory)
        {
            if (strpos($path, $allowedDirectory) === 0)
            {
                return;
            }
        }

        throw new \RuntimeException('Path is not in valid directory!');
    }

    // INFO STUFF

    /**
     * Gets info about the process owner
     * @return array<string,string|int> Returns info about the process owner
     */
    public static function getProcessOwnerInfo()
    {
        return posix_getpwuid(posix_geteuid());
    }

    /**
     * Gets info about the process group
     * @return array<string,string|int|array> Returns info about the process group
     */
    public static function getProcessGroupInfo()
    {
        return posix_getgrgid(posix_getegid());
    }

    /**
     * Gets info about the path owner
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the fileowner determination fails
     * @return array<string,string|int> Returns info about the path owner
     */
    public static function getPathOwnerInfo($path)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $fileOwnerResult = fileowner($path);
        if ($fileOwnerResult === false)
        {
            throw new \RuntimeException('File owner cannot be determined!');
        }

        return posix_getpwuid($fileOwnerResult);
    }

    /**
     * Gets info about the path group
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the groupowner determination fails
     * @return array<string,string|int|array> Returns info about the path group
     */
    public static function getPathGroupInfo($path)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $fileGroupResult = filegroup($path);
        if ($fileGroupResult === false)
        {
            throw new \RuntimeException('File group cannot be determined!');
        }

        return posix_getgrgid($fileGroupResult);
    }

    /**
     * Checks if the process is the path owner
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the fileowner determination fails
     * @return bool Returns true if process is the path owner
     */
    public static function hasPathOwnerRight($path)
    {
        self::checkIsInAllowedDirectories($path);

        $processOwnerInfo = self::getProcessOwnerInfo();
        $pathOwnerInfo = self::getPathOwnerInfo($path);

        return $processOwnerInfo['uid'] === self::ROOT_ID || $processOwnerInfo['uid'] === $pathOwnerInfo['uid'];
    }

    /**
     * Gets the mode permissions of a path
     * @param string $path  The path from which to get the information
     * @param bool   $octal Set true to get the octal instead of the string mode representation
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the permissions determination fails
     * @return string Returns the mode permissions of a path in octal or string representation
     */
    public static function getPathMode($path, $octal = false)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $perms = fileperms($path);
        if ($perms === false)
        {
            throw new \RuntimeException('File permissions cannot be read!');
        }

        if ($octal)
        {
            return substr(sprintf('%o', $perms), -4);
        }

        return self::convertPermsToString($perms);
    }

    /**
     * Convert file permissions to string representation
     * @param int $perms The file permissions
     * @return string Returns file permissions in string representation
     */
    private static function convertPermsToString($perms)
    {
        switch ($perms & 0xF000)
        {
            case 0xC000: // Socket
                $info = 's';
                break;
            case 0xA000: // Symbolic Link
                $info = 'l';
                break;
            case 0x8000: // Regular
                $info = '-'; // r
                break;
            case 0x6000: // Block special
                $info = 'b';
                break;
            case 0x4000: // Directory
                $info = 'd';
                break;
            case 0x2000: // Character special
                $info = 'c';
                break;
            case 0x1000: // FIFO pipe
                $info = 'p';
                break;
            default: // unknown
                $info = 'u';
        }

        // User
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040)
            ? (($perms & 0x0800) ? 's' : 'x' )
            : (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008)
            ? (($perms & 0x0400) ? 's' : 'x' )
            : (($perms & 0x0400) ? 'S' : '-'));

        // Other
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001)
            ? (($perms & 0x0200) ? 't' : 'x' )
            : (($perms & 0x0200) ? 'T' : '-'));

        return $info;
    }

    /**
     * Gets owner, group and mode info from a path
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if a info determination fails
     * @return array<string,string> Returns owner, group and mode info from a path
     */
    public static function getPathPermissions($path)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $ownerInfo = self::getPathOwnerInfo($path);
        $groupInfo = self::getPathGroupInfo($path);

        return array(
            'owner' => $ownerInfo['name'],
            'group' => $groupInfo['name'],
            'mode' => self::getPathMode($path)
        );
    }

    // FOLDER STUFF

    /**
     * @param string              $directoryPath
     * @param array<string,mixed> $options
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    public static function createDirectoryIfNotExists($directoryPath, array $options = array())
    {
        self::checkIsInAllowedDirectories($directoryPath);

        $options = array_merge(array('mode' => 0777, 'modeParents' => 0777, 'createDirectoryStructure' => true), $options);

        if (is_dir($directoryPath))
        {
            return false;
        }

        $parentDirectoryPath = dirname($directoryPath);
        if (!is_dir($parentDirectoryPath))
        {
            if ($options['createDirectoryStructure'])
            {
                $options['mode'] = $options['modeParents'];
                self::createDirectoryIfNotExists($parentDirectoryPath, $options);
            }
            else
            {
                throw new \UnexpectedValueException('Parent directory does not exist!');
            }
        }
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }
        if (!is_writable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not writable!');
        }

        $mkdirResult = mkdir($directoryPath, $options['mode']);
        if (!$mkdirResult)
        {
            throw new \RuntimeException('Directory cannot be created!');
        }

        return true;
    }

    /**
     * @param string $directoryPath
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    public static function isDirectoryEmpty($directoryPath)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }
        if (!is_readable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not readable!');
        }

        $dirHandle = opendir($directoryPath);
        if ($dirHandle === false)
        {
            throw new \RuntimeException('Directory cannot be opened!');
        }

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($entry !== '.' && $entry !== '..')
            {
                closedir($dirHandle);

                return false;
            }
        }
        closedir($dirHandle);

        return true;
    }

    /**
     * @param string            $directoryPath
     * @param bool              $recursive
     * @param bool              $fullPath
     * @param array<int,string> $includedContentTypes
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return array<string,string|array>
     */
    public static function getDirectoryContent($directoryPath, $recursive = false, $fullPath = true, array $includedContentTypes = array(self::CONTENT_TYPE_DIRECTORY, self::CONTENT_TYPE_FILE, self::CONTENT_TYPE_LINK))
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }
        if (!is_readable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not readable!');
        }

        $dirHandle = opendir($directoryPath);
        if ($dirHandle === false)
        {
            throw new \RuntimeException('Directory cannot be opened!');
        }

        $directoryContent = array();

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($entry === '.' || $entry === '..')
            {
                continue;
            }

            $directoryEntry = $directoryPath . DIRECTORY_SEPARATOR . $entry;
            $entryValue = $fullPath ? $directoryEntry : (string) $entry;

            if (is_dir($directoryEntry))
            {
                if ($recursive)
                {
                    $directoryContent[$entryValue] = self::getDirectoryContent($directoryEntry, $recursive, $fullPath, $includedContentTypes);
                }
                elseif (in_array(self::CONTENT_TYPE_DIRECTORY, $includedContentTypes, true))
                {
                    $directoryContent[$entryValue] = self::CONTENT_TYPE_DIRECTORY;
                }
            }
            elseif (is_file($directoryEntry) && in_array(self::CONTENT_TYPE_FILE, $includedContentTypes, true))
            {
                $directoryContent[$entryValue] = self::CONTENT_TYPE_FILE;
            }
            elseif (is_link($directoryEntry) && in_array(self::CONTENT_TYPE_LINK, $includedContentTypes, true))
            {
                $directoryContent[$entryValue] = self::CONTENT_TYPE_LINK;
            }
        }
        closedir($dirHandle);

        return $directoryContent;
    }

    /**
     * @param string $directoryPath
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    public static function deleteDirectoryContentIfExists($directoryPath)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }

        if (self::isDirectoryEmpty($directoryPath))
        {
            return false;
        }

        if (!is_writable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not writable!');
        }
        if (!is_readable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not readable!');
        }

        $dirHandle = opendir($directoryPath);
        if ($dirHandle === false)
        {
            throw new \RuntimeException('Directory cannot be opened!');
        }

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($entry === '.' || $entry === '..')
            {
                continue;
            }

            $directoryEntry = $directoryPath . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($directoryEntry))
            {
                self::deleteDirectoryIfExists($directoryEntry, true);
            }
            else
            {
                self::deleteFileIfExists($directoryEntry);
            }
        }
        closedir($dirHandle);

        return true;
    }

    /**
     * @param string $directoryPath
     * @param bool   $deleteWithContent
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    public static function deleteDirectoryIfExists($directoryPath, $deleteWithContent = false)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if ($directoryPath === self::ROOT_FOLDER)
        {
            throw new \UnexpectedValueException('Directory "root" cannot be deleted!');
        }

        $parentDirectoryPath = dirname($directoryPath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }
        if (!is_dir($directoryPath))
        {
            return false;
        }

        if (!self::isDirectoryEmpty($directoryPath))
        {
            if ($deleteWithContent)
            {
                self::deleteDirectoryContentIfExists($directoryPath);
            }
            else
            {
                throw new \UnexpectedValueException('Directory is not empty!');
            }
        }

        if (!is_writable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not writable!');
        }

        $rmdirResult = rmdir($directoryPath);
        if (!$rmdirResult)
        {
            throw new \RuntimeException('Directory cannot be deleted!');
        }

        return true;
    }

    /**
     * @param string             $oldDirectoryPath
     * @param string             $newDirectoryPath
     * @param array<string,bool> $options
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    private static function checkDirectoryPreconditions($oldDirectoryPath, $newDirectoryPath, array $options = array())
    {
        self::checkIsInAllowedDirectories($oldDirectoryPath);
        self::checkIsInAllowedDirectories($newDirectoryPath);

        $options = array_merge(array('createDirectoryStructure' => true, 'overwriteContent' => false), $options);

        if (!is_dir($oldDirectoryPath))
        {
            throw new \UnexpectedValueException('Source directory does not exist!');
        }
        if (!is_readable($oldDirectoryPath))
        {
            throw new \UnexpectedValueException('Source directory is not readable!');
        }

        if (!is_dir($newDirectoryPath))
        {
            if ($options['createDirectoryStructure'])
            {
                self::createDirectoryIfNotExists($newDirectoryPath);

                return false;
            }

            throw new \UnexpectedValueException('Destination directory does not exist!');
        }
        if (!is_executable($newDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination directory is not executable!');
        }
        if (!is_writable($newDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination directory is not writable!');
        }

        $oldDirectoryContentTree = self::getDirectoryContent($oldDirectoryPath, true, false);
        $newDirectoryContentTree = self::getDirectoryContent($newDirectoryPath, true, false);

        $collision = self::checkDirectoryContentTreeCollisions($oldDirectoryContentTree, $newDirectoryContentTree);
        if (!$collision)
        {
            return false;
        }
        if ($options['overwriteContent'])
        {
            return true;
        }

        throw new \UnexpectedValueException('Destination directory has collisions!');
    }

    /**
     * @param array<string,string|array> $directoryContentTree1
     * @param array<string,string|array> $directoryContentTree2
     * @param bool                       $considerDirectoryCollisions
     * @return bool
     */
    private static function checkDirectoryContentTreeCollisions(array $directoryContentTree1, array $directoryContentTree2, $considerDirectoryCollisions = false)
    {
        foreach ($directoryContentTree1 as $directoryContentName => $directoryContentType1)
        {
            if (array_key_exists($directoryContentName, $directoryContentTree2))
            {
                if ($considerDirectoryCollisions)
                {
                    return true;
                }

                $directoryContentType2 = $directoryContentTree2[$directoryContentName];

                if (!is_array($directoryContentType1) || !is_array($directoryContentType2))
                {
                    return true;
                }

                $collision = self::checkDirectoryContentTreeCollisions($directoryContentType1, $directoryContentType2, $considerDirectoryCollisions);
                if ($collision)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $oldDirectoryPath
     * @param string $newDirectoryPath
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     */
    private static function doCopyDirectory($oldDirectoryPath, $newDirectoryPath)
    {
        $oldDirectoryContent = self::getDirectoryContent($oldDirectoryPath, false, false);

        foreach ($oldDirectoryContent as $entry => $type)
        {
            $oldEntryPath = $oldDirectoryPath . DIRECTORY_SEPARATOR . $entry;
            $newEntryPath = $newDirectoryPath . DIRECTORY_SEPARATOR . $entry;

            if ($type === self::CONTENT_TYPE_DIRECTORY)
            {
                if (!is_dir($newEntryPath))
                {
                    self::createDirectoryIfNotExists($newEntryPath);
                }

                self::doCopyDirectory($oldEntryPath, $newEntryPath);
            }
            else
            {
                self::copyFile($oldEntryPath, $newEntryPath, array('overwrite' => true));
            }
        }
    }

    /**
     * @param string             $oldDirectoryPath
     * @param string             $newDirectoryPath
     * @param array<string,bool> $options
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    public static function copyDirectory($oldDirectoryPath, $newDirectoryPath, array $options = array())
    {
        $returnValue = self::checkDirectoryPreconditions($oldDirectoryPath, $newDirectoryPath, $options);

        self::doCopyDirectory($oldDirectoryPath, $newDirectoryPath);

        return $returnValue;
    }

    /**
     * @param string             $oldDirectoryPath
     * @param string             $newDirectoryPath
     * @param array<string,bool> $options
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @return bool
     */
    public static function moveDirectory($oldDirectoryPath, $newDirectoryPath, array $options = array())
    {
        $returnValue = self::checkDirectoryPreconditions($oldDirectoryPath, $newDirectoryPath, $options);

        self::doCopyDirectory($oldDirectoryPath, $newDirectoryPath);

        self::deleteDirectoryIfExists($oldDirectoryPath, true);

        return $returnValue;
    }

    /**
     * @param string $directoryPath
     * @param int    $mode
     * @param bool   $recursive
     * @param bool   $onlyDirectories
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     */
    public static function chmodDirectory($directoryPath, $mode, $recursive = false, $onlyDirectories = true)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }

        if (!self::hasPathOwnerRight($directoryPath))
        {
            throw new \UnexpectedValueException('Directory owner is different to process owner!');
        }

        $resultChmod = chmod($directoryPath, $mode);
        if (!$resultChmod)
        {
            throw new \RuntimeException('Directory mode cannot be changed!');
        }

        if ($recursive)
        {
            $directoryContent = self::getDirectoryContent($directoryPath);

            foreach ($directoryContent as $entryPath => $type)
            {
                if ($type === self::CONTENT_TYPE_DIRECTORY)
                {
                    self::chmodDirectory($entryPath, $mode, $recursive, $onlyDirectories);
                }
                elseif (!$onlyDirectories)
                {
                    self::chmodFile($entryPath, $mode);
                }
            }
        }
    }

    // FILE STUFF

    /**
     * Deletes a file if it exists
     * @param string $filePath The file to delete
     * @throws \UnexpectedValueException Throws if the file is not writable
     * @throws \RuntimeException         Throws if the delete process fails
     * @return bool Returns true if file was successfully deleted or false if file already did not exist
     */
    public static function deleteFileIfExists($filePath)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }
        if (!is_writable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not writable!');
        }

        if (!is_file($filePath))
        {
            return false;
        }

        $unlinkResult = unlink($filePath);
        if (!$unlinkResult)
        {
            throw new \RuntimeException('File cannot be deleted!');
        }

        return true;
    }

    /**
     * Checks the preconditions for tile copy and move
     * @param string             $mode        The operation mode (copy or move)
     * @param string             $oldFilePath The source path
     * @param string             $newFilePath The destination path
     * @param array<string,bool> $options     Operation options (createDirectoryStructure, overwrite)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the destination folder could not be created
     * @return bool Returns true if the destination path will be overwritten
     */
    private static function checkFilePreconditions($mode, $oldFilePath, $newFilePath, array $options = array())
    {
        $options = array_merge(array('createDirectoryStructure' => true, 'overwrite' => false), $options);

        self::checkIsInAllowedDirectories($oldFilePath);
        self::checkIsInAllowedDirectories($newFilePath);

        $oldParentDirectoryPath = dirname($oldFilePath);
        if ($mode === 'move' && !is_executable($oldParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Source parent directory is not executable!');
        }
        if (!is_writable($oldParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Source parent directory is not writable!');
        }

        if (!is_file($oldFilePath))
        {
            throw new \UnexpectedValueException('Source file does not exist!');
        }
        if ($mode === 'copy' && !is_readable($oldFilePath))
        {
            throw new \UnexpectedValueException('Source file is not readable!');
        }

        $newParentDirectoryPath = dirname($newFilePath);
        if (!is_dir($newParentDirectoryPath))
        {
            if ($options['createDirectoryStructure'])
            {
                self::createDirectoryIfNotExists($newParentDirectoryPath);

                return false;
            }

            throw new \UnexpectedValueException('Destination parent directory does not exist!');
        }
        if (!is_executable($newParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination parent directory is not executable!');
        }
        if (!is_writable($newParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination parent directory is not writable!');
        }

        if (!is_file($newFilePath))
        {
            return false;
        }
        if ($options['overwrite'])
        {
            return true;
        }

        throw new \UnexpectedValueException('Destination file already exist!');
    }

    /**
     * Copies a file
     * @param string             $oldFilePath The file to copy
     * @param string             $newFilePath The path where to copy to
     * @param array<string,bool> $options     Operation options (createDirectoryStructure, overwrite)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the copy process fails
     * @return bool Returns true if the destination path was overwritten
     */
    public static function copyFile($oldFilePath, $newFilePath, array $options = array())
    {
        $returnValue = self::checkFilePreconditions('copy', $oldFilePath, $newFilePath, $options);

        $copyResult = copy($oldFilePath, $newFilePath);
        if (!$copyResult)
        {
            throw new \RuntimeException('File cannot be copied!');
        }

        return $returnValue;
    }

    /**
     * Moves a file
     * @param string             $oldFilePath The file to move
     * @param string             $newFilePath The path where to move to
     * @param array<string,bool> $options     Operation options (createDirectoryStructure, overwrite)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the move process fails
     * @return bool Returns true if the destination path was overwritten
     */
    public static function moveFile($oldFilePath, $newFilePath, array $options = array())
    {
        $returnValue = self::checkFilePreconditions('move', $oldFilePath, $newFilePath, $options);

        $renameResult = rename($oldFilePath, $newFilePath);
        if (!$renameResult)
        {
            throw new \RuntimeException('File cannot be moved!');
        }

        return $returnValue;
    }

    /**
     * @param string $filePath The file to chmod
     * @param int    $mode     The mode to set in octal notation (e.g. 0755)
     * @throws \UnexpectedValueException Throws if the file does not exist or is not chmod-able
     * @throws \RuntimeException         Throws if the chmod process fails
     */
    public static function chmodFile($filePath, $mode)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!is_file($filePath))
        {
            throw new \UnexpectedValueException('File does not exist!');
        }
        if (!self::hasPathOwnerRight($filePath))
        {
            throw new \UnexpectedValueException('File owner is different to process owner!');
        }

        $chmodResult = chmod($filePath, $mode);
        if (!$chmodResult)
        {
            throw new \RuntimeException('File mode cannot be changed!');
        }
    }

    /**
     * @param string $filePath The file to read
     * @throws \UnexpectedValueException Throws if the file does not exist or is not readable
     * @throws \RuntimeException         Throws if the read process fails
     * @return string Returns the file content
     */
    public static function readFile($filePath)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!is_file($filePath))
        {
            throw new \UnexpectedValueException('File does not exist!');
        }
        if (!is_readable($filePath))
        {
            throw new \UnexpectedValueException('File is not readable!');
        }

        $data = file_get_contents($filePath);
        if ($data === false)
        {
            throw new \RuntimeException('File cannot be read!');
        }

        return $data;
    }

    /**
     * Write some data into a file
     * @param string $filePath The file to write
     * @param string $data     The data to write
     * @param bool   $append   If true the data gets appended instead of overwriting the content
     * @throws \UnexpectedValueException Throws if the file in not writable
     * @throws \RuntimeException         Throws if the write process fails
     * @return int Returns the written bytes
     */
    public static function writeFile($filePath, $data, $append = false)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (is_file($filePath))
        {
            if (!is_writable($filePath))
            {
                throw new \UnexpectedValueException('File is not writable!');
            }
        }
        else
        {
            if (!is_writable($parentDirectoryPath))
            {
                throw new \UnexpectedValueException('Parent directory is not writable!');
            }
        }

        $flags = 0;
        if ($append)
        {
            $flags = FILE_APPEND;
        }

        $bytes = file_put_contents($filePath, $data, $flags);
        if ($bytes === false)
        {
            throw new \RuntimeException('File cannot be written!');
        }

        return $bytes;
    }
}
