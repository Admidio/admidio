<?php
/**
 ***********************************************************************************************
 * This class is used to create a htaccess file.
 * A folder can be protected with a htaccess file via this class.
 * Access from outside is then no longer permitted.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class Htaccess
{
    /**
     * @var string
     */
    protected $folderPath;

    /**
     * @param string $folderPath
     */
    public function __construct(string $folderPath)
    {
        $this->folderPath = $folderPath;
    }

    /**
     * Protect the passed folder
     * @return bool Returns true if protection is enabled
     */
    public function protectFolder(): bool
    {
        if (is_file($this->folderPath . '/.htaccess')) {
            return true;
        }

        try {
            FileSystemUtils::createDirectoryIfNotExists($this->folderPath);

            $lines = array(
                '<IfModule mod_version.c>',
                ' <IfVersion < 2.4>',
                '  Order Deny,Allow',
                '  Deny from All',
                ' </IfVersion>',
                ' <IfVersion >= 2.4>',
                '  Require all denied',
                ' </IfVersion>',
                '</IfModule>',
                '<IfModule !mod_version.c>',
                ' <IfModule !mod_authz_core.c>',
                '  Order Allow,Deny',
                '  Deny from All',
                ' </IfModule>',
                ' <IfModule mod_authz_core.c>',
                '  Require all denied',
                ' </IfModule>',
                '</IfModule>'
            );
            $data = implode("\n", $lines) . "\n";
            FileSystemUtils::writeFile($this->folderPath . '/.htaccess', $data);
        } catch (RuntimeException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Removes the folder protection (deletes the htaccess file)
     * @return bool Returns **true** if protection is disabled
     */
    public function unprotectFolder(): bool
    {
        try {
            FileSystemUtils::deleteFileIfExists($this->folderPath . '/.htaccess');
        } catch (RuntimeException $exception) {
            return false;
        }

        return true;
    }
}
