<?php
/******************************************************************************
 * Class manages access to database table adm_files
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Fileobjekt zu erstellen.
 * Eine Datei kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getFileForDownload($file_id)
 *                         - File mit der uebergebenen ID aus der Datenbank auslesen fuer 
 *                           das Downloadmodul. Hier wird auch direkt ueberprueft ob die 
 *                           Datei oder der Ordner gesperrt ist.
 * getCompletePathOfFile() - Gibt den kompletten Pfad der Datei zurueck
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableFile extends TableAccess
{
    // Konstruktor
    public function __construct(&$db, $fil_id = 0)
    {
        parent::__construct($db, TBL_FILES, 'fil', $fil_id);
    }


    // File mit der uebergebenen ID aus der Datenbank auslesen
    public function readData($file_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        global $gCurrentOrganization;

        $sql_additional_tables .= TBL_FOLDERS;
        $sql_where_condition   .= '    fil_id     = '.$file_id.'
                                   AND fil_fol_id = fol_id
                                   AND fol_type   = \'DOWNLOAD\'
                                   AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
        return parent::readData($file_id, $sql_where_condition, $sql_additional_tables);
    }


    // File mit der uebergebenen ID aus der Datenbank auslesen fuer das Downloadmodul
    // Hier wird auch direkt ueberprueft ob die Datei oder der Ordner gesperrt ist.
    public function getFileForDownload($file_id)
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        $this->readData($file_id);

        //Pruefen ob der aktuelle Benutzer Rechte an der Datei hat
        //Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fil_id'))
        {

            //Falls die Datei gelocked ist und der User keine Downloadadminrechte hat, bekommt er nix zu sehen..
            if (!$gCurrentUser->editDownloadRight() && $this->getValue('fil_locked'))
            {
                $this->clear();
            }
            else if (!$gValidLogin && !$this->getValue('fol_public'))
            {
                //Wenn der Ordner nicht public ist und der Benutzer nicht eingeloggt ist, bekommt er nix zu sehen..
                $this->clear();
            }
            else if (!$gCurrentUser->editDownloadRight() && !$this->getValue('fol_public'))
            {
                //Wenn der Ordner nicht public ist und der Benutzer keine Downloadadminrechte hat, muessen die Rechte untersucht werden
                $sql_rights = 'SELECT count(*)
                         FROM '. TBL_FOLDER_ROLES. ', '. TBL_MEMBERS. '
                        WHERE flr_fol_id = '. $this->getValue('fol_id'). '
                          AND flr_rol_id = mem_rol_id
                          AND mem_usr_id = '. $gCurrentUser->getValue('usr_id'). '
                          AND mem_begin <= \''.DATE_NOW.'\'
                          AND mem_end    > \''.DATE_NOW.'\'';
                $result_rights = $this->db->query($sql_rights);
                $row_rights = $this->db->fetch_array($result_rights);
                $row_count  = $row_rights[0];

                //Falls der User in keiner Rolle Mitglied ist, die Rechte an dem Ordner besitzt
                //wird auch kein Ordner geliefert.
                if ($row_count == 0)
                {
                    $this->clear();
                }

            }
        }
    }


    //Gibt den kompletten Pfad der Datei zurueck
    public function getCompletePathOfFile()
    {
        //Dateinamen und Pfad zusammen setzen
        $fileName     = $this->getValue('fil_name');
        $folderPath   = $this->getValue('fol_path');
        $folderName   = $this->getValue('fol_name');
        $completePath = SERVER_PATH. $folderPath. '/'. $folderName. '/'. $fileName;

        return $completePath;
    }


    // die Methode wird innerhalb von delete() aufgerufen
    //und loescht das File physikalisch von der Platte bevor es aus der DB geloescht wird
    public function delete()
    {
        @chmod($this->getCompletePathOfFile(), 0777);
        @unlink($this->getCompletePathOfFile());

        //Auch wenn das Loeschen nicht klappt wird true zurueckgegeben,
        //damit der Eintrag aus der DB verschwindet.
        return parent::delete();
    }


    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentUser;

        if($this->new_record)
        {
            $this->setValue('fil_timestamp', DATETIME_NOW);
            $this->setValue('fil_usr_id', $gCurrentUser->getValue('usr_id'));
        }
        parent::save($updateFingerPrint);
    }
}
?>