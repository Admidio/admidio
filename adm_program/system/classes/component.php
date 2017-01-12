<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Component
 * @brief Handle different components of Admidio (e.g. system, plugins or modules) and manage them in the database
 *
 * The class search in the database table @b adm_components for a specific component
 * and loads the data into this object. A component could be per default the @b SYSTEM
 * itself, a module or a plugin. There are methods to check the version of the system.
 * @par Examples
 * @code // check if database and filesystem have same version
 * try
 * {
 *     $systemComponent = new Component($gDb);
 *     $systemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
 *     $systemComponent->checkDatabaseVersion(true, 'webmaster@example.com');
 * }
 * catch(AdmException $e)
 * {
 *     $e->showHtml();
 * } @endcode
 */
class Component extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_component.
     * If the id is set than the specific component will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $comId    The recordset of the component with this id will be loaded.
     *                            If com_id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $comId = 0)
    {
        parent::__construct($database, TBL_COMPONENTS, 'com', $comId);
    }

    /**
     * Check version of component in database against the version of the file system.
     * There will be different messages shown if versions aren't equal. If database has minor
     * version than a link to update the database will be shown. If filesystem has minor version
     * than a link to download current version will be shown.
     * @throws AdmException SYS_DATABASE_VERSION_INVALID
     *                      SYS_FILESYSTEM_VERSION_INVALID
     * @return void Nothing will be returned. If the versions aren't equal a message will be shown.
     */
    public function checkDatabaseVersion()
    {
        global $gLogger;

        $dbVersion = $this->getValue('com_version');
        if ($this->getValue('com_beta') > 0)
        {
            $dbVersion .= '-Beta.' . $this->getValue('com_beta');
        }

        $filesystemVersion = ADMIDIO_VERSION;
        if (ADMIDIO_VERSION_BETA > 0)
        {
            $filesystemVersion .= '-Beta.' . ADMIDIO_VERSION_BETA;
        }

        $returnCode = version_compare($dbVersion, $filesystemVersion);

        if ($returnCode === -1) // database has minor version
        {
            $gLogger->warning(
                'UPDATE: Database-Version is lower than the filesystem!',
                array('versionDB' => $dbVersion, 'versionFileSystem' => $filesystemVersion)
            );

            throw new AdmException('SYS_DATABASE_VERSION_INVALID', $dbVersion, ADMIDIO_VERSION_TEXT,
                                   '<a href="' . ADMIDIO_URL . '/adm_program/installation/update.php">', '</a>');
        }
        elseif ($returnCode === 1) // filesystem has minor version
        {
            $gLogger->warning(
                'UPDATE: Filesystem-Version is lower than the database!',
                array('versionDB' => $dbVersion, 'versionFileSystem' => $filesystemVersion)
            );

            throw new AdmException('SYS_FILESYSTEM_VERSION_INVALID', $dbVersion, ADMIDIO_VERSION_TEXT,
                                   '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>');
        }
    }
}
