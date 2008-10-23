<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_files
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Fileobjekt zu erstellen.
 * Eine Datei kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getFileForDownload($file_id)
 *                         - File mit der uebergebenen ID aus der Datenbank auslesen fuer 
 *                           das Downloadmodul. Hier wird auch direkt ueberprueft ob die 
 *                           Datei oder der Ordner gesperrt ist.
 * getCompletePathOfFile() - Gibt den kompletten Pfad der Datei zurueck
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class TableFile extends TableAccess
{
    // Konstruktor
    function TableFile(&$db, $file_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_FILES;
        $this->column_praefix = "fil";

        if($file_id > 0)
        {
            $this->readData($file_id);
        }
        else
        {
            $this->clear();
        }
    }


    // File mit der uebergebenen ID aus der Datenbank auslesen
    function readData($file_id)
    {
        global $g_current_organization;

        $tables    = TBL_FOLDERS;
        $condition = "     fil_id     = $file_id
                       AND fil_fol_id = fol_id
                       AND fol_org_id = ". $g_current_organization->getValue("org_id");
        parent::readData($file_id, $condition, $tables);
    }


    // File mit der uebergebenen ID aus der Datenbank auslesen fuer das Downloadmodul
    // Hier wird auch direkt ueberprueft ob die Datei oder der Ordner gesperrt ist.
    function getFileForDownload($file_id)
    {
        global $g_current_organization, $g_current_user, $g_valid_login;

        $tables    = TBL_FOLDERS;
        $condition = "     fil_id     = $file_id
                       AND fil_fol_id = fol_id
                       AND fol_type   = 'DOWNLOAD'
                       AND fol_org_id = ". $g_current_organization->getValue("org_id");
        $this->readData($file_id, $condition, $tables);

        //Pruefen ob der aktuelle Benutzer Rechte an der Datei hat
        //Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fil_id'))
        {

            //Falls die Datei gelocked ist und der User keine Downloadadminrechte hat, bekommt er nix zu sehen..
            if (!$g_current_user->editDownloadRight() && $this->getValue("fil_locked"))
            {
                $this->clear();
            }
            else if (!$g_valid_login && !$this->getValue("fol_public"))
            {
                //Wenn der Ordner nicht public ist und der Benutzer nicht eingeloggt ist, bekommt er nix zu sehen..
                $this->clear();
            }
            else if (!$g_current_user->editDownloadRight() && !$this->getValue("fol_public"))
            {
                //Wenn der Ordner nicht public ist und der Benutzer keine Downloadadminrechte hat, muessen die Rechte untersucht werden
                $sql_rights = "SELECT count(*)
                         FROM ". TBL_FOLDER_ROLES. ", ". TBL_MEMBERS. "
                        WHERE flr_fol_id        = ". $this->getValue("fol_id"). "
                          AND flr_rol_id         = mem_rol_id
                          AND mem_usr_id         = ". $g_current_user->getValue("usr_id"). "
                          AND mem_valid         = 1";
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
    function getCompletePathOfFile()
    {
        //Dateinamen und Pfad zusammen setzen
        $fileName     = $this->getValue("fil_name");
        $folderPath   = $this->getValue("fol_path");
        $folderName   = $this->getValue("fol_name");
        $completePath = SERVER_PATH. $folderPath. "/". $folderName. "/". $fileName;

        return $completePath;
    }


    // die Methode wird innerhalb von delete() aufgerufen
    //und loescht das File physikalisch von der Platte bevor es aus der DB geloescht wird
    function delete()
    {
        @chmod($this->getCompletePathOfFile(), 0777);
        @unlink($this->getCompletePathOfFile());

        //Auch wenn das Loeschen nicht klappt wird true zurueckgegeben,
        //damit der Eintrag aus der DB verschwindet.
        return parent::delete();
    }


    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_organization, $g_current_user;

        if($this->new_record)
        {
            $this->setValue("fil_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("fil_usr_id", $g_current_user->getValue("usr_id"));
        }
        parent::save();
    }
}
?>