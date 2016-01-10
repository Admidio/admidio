<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_files
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Diese Klasse dient dazu ein Fileobjekt zu erstellen.
 * Eine Datei kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getFileForDownload($fileId)
 *                         - File mit der uebergebenen ID aus der Datenbank auslesen fuer
 *                           das Downloadmodul. Hier wird auch direkt ueberprueft ob die
 *                           Datei oder der Ordner gesperrt ist.
 * getCompletePathOfFile() - Gibt den kompletten Pfad der Datei zurueck
 *
 *****************************************************************************/
class TableFile extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_files.
     * If the id is set than the specific files will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $fil_id   The recordset of the files with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $fil_id = 0)
    {
        // read also data of assigned folder
        $this->connectAdditionalTable(TBL_FOLDERS, 'fol_id', 'fil_fol_id');

        parent::__construct($database, TBL_FILES, 'fil', $fil_id);
    }

    /**
     * Deletes the selected record of the table and the associated file in the file system.
     * After that the class will be initialize.
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        @chmod($this->getCompletePathOfFile(), 0777);
        @unlink($this->getCompletePathOfFile());

        // Auch wenn das Loeschen nicht klappt wird true zurueckgegeben,
        // damit der Eintrag aus der DB verschwindet.
        return parent::delete();
    }

    /**
     * Gibt den kompletten Pfad der Datei zurueck
     * @return string
     */
    public function getCompletePathOfFile()
    {
        // Dateinamen und Pfad zusammen setzen
        $fileName     = $this->getValue('fil_name');
        $folderPath   = $this->getValue('fol_path');
        $folderName   = $this->getValue('fol_name');
        $completePath = SERVER_PATH. $folderPath. '/'. $folderName. '/'. $fileName;

        return $completePath;
    }

    /**
     * Reads the file recordset from database table @b adm_folders and throws an AdmException
     * if the user has no right to see the corresponding folder or the file id doesn't exists.
     * @param int $fileId The id of the file.
     * @throws AdmException DOW_FOLDER_NO_RIGHTS
     *                      SYS_INVALID_PAGE_VIEW
     * @return true Returns @b true if everything is ok otherwise an AdmException is thrown.
     */
    public function getFileForDownload($fileId)
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        $this->readDataById($fileId);

        // Pruefen ob der aktuelle Benutzer Rechte an der Datei hat
        // Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fil_id') > 0)
        {
            // Falls die Datei gelocked ist und der User keine Downloadadminrechte hat, bekommt er nix zu sehen..
            if (!$gCurrentUser->editDownloadRight() && $this->getValue('fil_locked'))
            {
                $this->clear();
                throw new AdmException('DOW_FOLDER_NO_RIGHTS');
            }
            elseif (!$gValidLogin && !$this->getValue('fol_public'))
            {
                // Wenn der Ordner nicht public ist und der Benutzer nicht eingeloggt ist, bekommt er nix zu sehen..
                $this->clear();
                throw new AdmException('DOW_FOLDER_NO_RIGHTS');
            }
            elseif (!$gCurrentUser->editDownloadRight() && !$this->getValue('fol_public'))
            {
                // Wenn der Ordner nicht public ist und der Benutzer keine Downloadadminrechte hat, muessen die Rechte untersucht werden
                $sql_rights = 'SELECT COUNT(*) as count
                                 FROM '.TBL_FOLDER_ROLES.'
                           INNER JOIN '.TBL_MEMBERS.'
                                   ON mem_rol_id = flr_rol_id
                                WHERE flr_fol_id = '.$this->getValue('fol_id').'
                                  AND mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                  AND mem_begin <= \''.DATE_NOW.'\'
                                  AND mem_end    > \''.DATE_NOW.'\'';
                $rightsStatement = $this->db->query($sql_rights);
                $row_rights = $rightsStatement->fetch();
                $row_count  = $row_rights['count'];

                // Falls der User in keiner Rolle Mitglied ist, die Rechte an dem Ordner besitzt
                // wird auch kein Ordner geliefert.
                if ($row_count === 0)
                {
                    $this->clear();
                    throw new AdmException('DOW_FOLDER_NO_RIGHTS');
                }
                return true;
            }
            else
            {
                return true;
            }
        }
        throw new AdmException('SYS_INVALID_PAGE_VIEW');
    }

    /**
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        $value = parent::getValue($columnName, $format);

        // getValue transforms & to html chars. This must be undone.
        if($columnName === 'fil_name')
        {
            $value = htmlspecialchars_decode($value);
        }

        return $value;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For new records the user and timestamp will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentUser;

        if($this->new_record)
        {
            $this->setValue('fil_timestamp', DATETIME_NOW);
            $this->setValue('fil_usr_id', $gCurrentUser->getValue('usr_id'));
        }
        return parent::save($updateFingerPrint);
    }
}
