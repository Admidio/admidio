<?php
/******************************************************************************
 * Class manages access to database table adm_photos
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Fotoveranstaltungsobjekt zu erstellen.
 * Eine Fotoveranstaltung kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * countImages($pho_id = 0)    - Rekursive Funktion gibt die Anzahl aller Bilder 
 *                               inkl. der Unteralben zurueck
 * shuffleImage($pho_id = 0)   - Rekursive Funktion zum Auswaehlen eines 
 *                               Beispielbildes aus einem moeglichst hohen Album
 * createFolder()      - erzeugt den entsprechenden Ordner unter adm_my_files/photos
 * deleteInDatabase($photo_id) - Rekursive Funktion die die uebergebene Veranstaltung
 *                               und alle Unterveranstaltungen loescht
 * deleteInFilesystem($folder) - Rekursive Funktion die alles innerhalb des uebergebenen
 *                               Ordners mit Unterordnern und allen Dateien loescht
 *
 *****************************************************************************/

$absolute_path = substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1);
require_once($absolute_path.'/adm_program/system/classes/table_access.php');
require_once($absolute_path. '/adm_program/system/classes/my_files.php');

class TablePhotos extends TableAccess
{
    public function __construct(&$db, $photo_id = 0)
    {
        parent::__construct($db, TBL_PHOTOS, 'pho', $photo_id);
    }

    // Rekursive Funktion gibt die Anzahl aller Bilder inkl. der Unteralben zurueck
    // pho_id noetig fuer rekursiven Aufruf
    public function countImages($pho_id = 0)
    {
        $total_images = 0;

        // wurde keine ID uebergeben, dann Anzahl Bilder des aktuellen Albums ermitteln
        if($pho_id == 0)
        {
            $pho_id = $this->getValue('pho_id');
            $total_images = $this->getValue('pho_quantity');
        }

        // alle Unteralben ermitteln
        $sql = 'SELECT pho_id, pho_quantity
                  FROM '. TBL_PHOTOS. '
                 WHERE pho_pho_id_parent = '.$pho_id.'
                   AND pho_locked = 0';
        $pho_result = $this->db->query($sql);

        while($pho_row = $this->db->fetch_array($pho_result))
        {
            $total_images = $total_images + $pho_row['pho_quantity'] + $this->countImages($pho_row['pho_id']);
        }

        return $total_images;
    }

    // Legt den Ordner fuer die Veranstaltung im Dateisystem an
    public function createFolder()
    {
        $error = array('code' => '0', 'text' => '');

        // Pfad in adm_my_files pruefen und ggf. anlegen
        $myFilesPhotos = new MyFiles('PHOTOS');
        if($myFilesPhotos->checkSettings() == false)
        {
            $error['text'] = $myFilesPhotos->errorText;
            $error['path'] = $myFilesPhotos->errorPath;
            return $error;
        }
        
        // nun den Ordner fuer die Veranstaltung anlegen
        $folderName = $this->getValue('pho_begin', 'Y-m-d'). '_'. $this->getValue('pho_id');
        if($myFilesPhotos->createFolder($folderName, true) == false)
        {
            $error['text'] = 'SYS_FOLDER_NOT_CREATED';
            $error['path'] = 'adm_my_files/photos/'.$folderName;
        }
        return $error;
    }

    // interne Funktion, die die Fotoveranstaltung in Datenbank und File-System loeschen
    // die Funktion wird innerhalb von delete() aufgerufen
    public function delete()
    {
        if($this->deleteInDatabase($this->getValue('pho_id')))
        {
            return parent::delete();
        }
        return false;
    }    

    // Rekursive Funktion die die uebergebene Veranstaltung und alle
    // Unterveranstaltungen loescht
    public function deleteInDatabase($photo_id)
    {
        $return_code = true;
		$this->db->startTransaction();
    
        // erst einmal rekursiv zur tiefsten Tochterveranstaltung gehen
        $sql     = 'SELECT pho_id FROM '. TBL_PHOTOS. '
                     WHERE pho_pho_id_parent = '.$photo_id;
        $result1 = $this->db->query($sql);
        
        while($row = $this->db->fetch_array($result1))
        {
            if($return_code)
            {
                $return_code = $this->deleteInDatabase($row['pho_id']);
            }
        }

        // nun DB-Eintrag und Ordner loeschen
        if($return_code)
        {
            //Ordnerpfad zusammensetzen
            $folder = SERVER_PATH. '/adm_my_files/photos/'.$this->getValue('pho_begin', 'Y-m-d').'_'.$photo_id;
            
            // aktuellen Ordner incl. Unterordner und Dateien loeschen, falls er existiert
            if(file_exists($folder))
            {
                // nun erst rekursiv den Ordner im Dateisystem loeschen
                $myFilesPhotos = new MyFiles('PHOTOS');
                $myFilesPhotos->setFolder($folder);
                $return_code = $myFilesPhotos->delete($folder);
            }

            if($return_code)
            {
                // Veranstaltung jetzt in DB loeschen            
                $sql = 'DELETE FROM '. TBL_PHOTOS. '
                         WHERE pho_id = '.$photo_id;
                $this->db->query($sql);
            }
        }
        
		$this->db->endTransaction();
        return $return_code;
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;
        
        if($this->new_record)
        {
            $this->setValue('pho_org_shortname', $gCurrentOrganization->getValue('org_shortname'));
        }

        parent::save($updateFingerPrint);
    }

    // Rekursive Funktion zum Auswaehlen eines Beispielbildes aus einem moeglichst hohen Album
    // Rueckgabe eines Arrays mit allen noetigen Infos um den Link zu erstellen
    public function shuffleImage($pho_id = 0)
    {
        $shuffle_image = array('shuffle_pho_id' => 0, 'shuffle_img_nr' => 0, 'shuffle_img_begin' => '');

        // wurde keine ID uebergeben, dann versuchen das Zufallsbild aus dem aktuellen Album zu nehmen
        if($pho_id == 0)
        {
            $pho_id = $this->getValue('pho_id');
            $shuffle_image['shuffle_pho_id']    = $this->getValue('pho_id');
            $shuffle_image['shuffle_img_begin'] = $this->getValue('pho_begin', 'Y-m-d');

            if($this->getValue('pho_quantity') > 0)
            {
                $shuffle_image['shuffle_img_nr'] = mt_rand(1, $this->getValue('pho_quantity'));
            }
        }
        
        if($shuffle_image['shuffle_img_nr'] == 0)
        {   
            // kein Bild vorhanden, dann in einem Unteralbum suchen
            $sql = 'SELECT *
                      FROM '. TBL_PHOTOS. '
                     WHERE pho_pho_id_parent = '.$pho_id.'
                       AND pho_locked = 0
                     ORDER BY pho_quantity DESC';
            $result_child = $this->db->query($sql);
            
            while($pho_row = $this->db->fetch_array($result_child))
            {
                if($shuffle_image['shuffle_img_nr'] == 0)
                {
                    $shuffle_image['shuffle_pho_id'] = $pho_row['pho_id'];
                    $shuffle_image['shuffle_img_begin'] = $pho_row['pho_begin'];

                    if($pho_row['pho_quantity'] > 0)
                    {
                        $shuffle_image['shuffle_img_nr'] = mt_rand(1, $pho_row['pho_quantity']);
                    }
                    else
                    {
                        $shuffle_image = $this->shuffleImage($pho_row['pho_id']);
                    }
                }
            }
        }
        return $shuffle_image;
    }
}
?>