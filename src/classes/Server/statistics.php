<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Statistics
 * @brief Collect and manage all the data for the Admidio statistics
 */
class Statistics
{
    /**
     * @param string $domain
     * @return string
     */
    public static function createUUID($domain = 'localhost')
    {
        $dateTime = self::getDateTime();
        $random = PasswordHashing::genRandomPassword();

        return sha1($dateTime . $domain . $random);
    }

    /**
     * @return array
     */
    public static function getInfoData()
    {
        return array(
            'uuid'      => self::getUUID(),
            'timestamp' => self::getDateTime(),
            'php'       => array(
                'version'    => self::getPHPVersion(),
                'extensions' => self::getPHPExtensions()
            ),
            'database' => array(
                'available' => self::getAvailableDatabaseTypes(),
                'type'      => self::getDatabaseType(),
                'version'   => self::getDatabaseVersion()
            ),
            'admidio' => array(
                'version' => self::getAdmidioVersion(),
                'plugins' => self::getAdmidioPlugins()
            )
        );
    }

    /**
     * @return string
     */
    private static function getUUID()
    {
        global $gAdmidioUUID;

        if (!$gAdmidioUUID)
        {
            $gAdmidioUUID = self::createUUID();
        }

        return $gAdmidioUUID;
    }

    /**
     * @return string
     */
    private static function getDateTime()
    {
        $dateTime = new DateTime('now', new DateTimeZone('UTC'));

        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * @return string
     */
    private static function getPHPVersion()
    {
        return PHP_VERSION;
    }

    /**
     * @return string[]
     */
    private static function getPHPExtensions()
    {
        $extensions = get_loaded_extensions();
        asort($extensions);

        $extensionsVersions = array();
        foreach ($extensions as $extension)
        {
            $extensionsVersions[$extension] = phpversion($extension);
        }

        return $extensionsVersions;
    }

    /**
     * @return string[]
     */
    private static function getAvailableDatabaseTypes()
    {
        return PDO::getAvailableDrivers();
    }

    /**
     * @return string
     */
    private static function getDatabaseType()
    {
        global $gDbType;

        return $gDbType;
    }

    /**
     * @return string
     */
    private static function getDatabaseVersion()
    {
        global $gDb;

        $versionStatement = $gDb->query('SELECT version()');

        return $versionStatement->fetchColumn();
    }

    /**
     * @return string
     */
    private static function getAdmidioVersion()
    {
        return ADMIDIO_VERSION_TEXT;
    }

    /**
     * @return string[]
     */
    private static function getAdmidioPlugins()
    {
        $plugins = array();

        $pluginsFolder = SERVER_PATH . DIRECTORY_SEPARATOR . 'adm_plugins';

        $dirHandle = opendir($pluginsFolder);
        if ($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if ($entry === '.' || $entry === '..')
                {
                    continue;
                }

                $pluginFolder = $pluginsFolder . DIRECTORY_SEPARATOR . $entry;
                $pluginFile = $pluginFolder . DIRECTORY_SEPARATOR . $entry . '.php';

                if (is_dir($pluginFolder) && is_file($pluginFile))
                {
                    $fileHandle = fopen($pluginFile, 'r');
                    if ($fileHandle)
                    {
                        while (($line = fgets($fileHandle)) !== false)
                        {
                            preg_match('/^ \* Version (\d+\.\d+\.\d+.*$)/', $line, $matches);

                            if (count($matches) === 2)
                            {
                                $plugins[$entry] = $matches[1];
                                break;
                            }
                        }
                        fclose($fileHandle);
                    }

                    if (!array_key_exists($entry, $plugins))
                    {
                        $plugins[$entry] = true;
                    }
                }
            }
            closedir($dirHandle);
        }

        return array($plugins);
    }
}
