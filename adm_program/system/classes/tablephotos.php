<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_photos
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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
class TablePhotos extends TableAccess
{
    protected $hasChildAlbums; ///< Flag if this album has child albums

    /**
     * Constructor that will create an object of a recordset of the table adm_photos.
     * If the id is set than the specific photo album will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $photo_id The recordset of the photo album with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $photo_id = 0)
    {
        parent::__construct($database, TBL_PHOTOS, 'pho', $photo_id);

        $hasChildAlbums = null;
    }

    /**
     * Rekursive Funktion gibt die Anzahl aller Bilder inkl. der Unteralben zurueck
     * pho_id noetig fuer rekursiven Aufruf
     * @param int $phoId
     * @return int
     */
    public function countImages($phoId = 0)
    {
        $totalImages = 0;

        // wurde keine ID uebergeben, dann Anzahl Bilder des aktuellen Albums ermitteln
        if($phoId == 0)
        {
            $phoId = $this->getValue('pho_id');
            $totalImages = $this->getValue('pho_quantity');
        }

        // alle Unteralben ermitteln
        $sql = 'SELECT pho_id, pho_quantity
                  FROM '.TBL_PHOTOS.'
                 WHERE pho_pho_id_parent = '.$phoId.'
                   AND pho_locked = 0';
        $childAlbumsStatement = $this->db->query($sql);

        while($pho_row = $childAlbumsStatement->fetch())
        {
            $totalImages = $totalImages + $pho_row['pho_quantity'] + $this->countImages($pho_row['pho_id']);
        }

        return $totalImages;
    }

    /**
     * Legt den Ordner fuer die Veranstaltung im Dateisystem an
     * @return array
     */
    public function createFolder()
    {
        $error = array('code' => '0', 'text' => '');

        // Pfad in adm_my_files pruefen und ggf. anlegen
        $myFilesPhotos = new MyFiles('PHOTOS');
        if(!$myFilesPhotos->checkSettings())
        {
            $error['text'] = $myFilesPhotos->errorText;
            $error['path'] = $myFilesPhotos->errorPath;
            return $error;
        }

        // nun den Ordner fuer die Veranstaltung anlegen
        $folderName = $this->getValue('pho_begin', 'Y-m-d'). '_'. $this->getValue('pho_id');
        if(!$myFilesPhotos->createFolder($folderName, true))
        {
            $error['text'] = 'SYS_FOLDER_NOT_CREATED';
            $error['path'] = 'adm_my_files/photos/'.$folderName;
        }
        return $error;
    }

    /**
     * Deletes the selected photo album and all sub photo albums.
     * After that the class will be initialize.
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        if($this->deleteInDatabase($this->getValue('pho_id')))
        {
            return parent::delete();
        }
        return false;
    }

    /**
     * Rekursive Funktion die die uebergebene Veranstaltung und alle Unterveranstaltungen loescht
     * @param int $photoId
     * @return bool
     */
    public function deleteInDatabase($photoId)
    {
        $returnValue = true;
        $this->db->startTransaction();

        // erst einmal rekursiv zur tiefsten Tochterveranstaltung gehen
        $sql = 'SELECT pho_id
                  FROM '.TBL_PHOTOS.'
                 WHERE pho_pho_id_parent = '.$photoId;
        $childAlbumStatement = $this->db->query($sql);

        while($row = $childAlbumStatement->fetch())
        {
            if($returnValue)
            {
                $returnValue = $this->deleteInDatabase($row['pho_id']);
            }
        }

        // nun DB-Eintrag und Ordner loeschen
        if($returnValue)
        {
            // Ordnerpfad zusammensetzen
            $folder = SERVER_PATH. '/adm_my_files/photos/'.$this->getValue('pho_begin', 'Y-m-d').'_'.$photoId;

            // aktuellen Ordner incl. Unterordner und Dateien loeschen, falls er existiert
            if(file_exists($folder))
            {
                // nun erst rekursiv den Ordner im Dateisystem loeschen
                $myFilesPhotos = new MyFiles('PHOTOS');
                $myFilesPhotos->setFolder($folder);
                $returnValue = $myFilesPhotos->delete($folder);
            }

            if($returnValue)
            {
                // Veranstaltung jetzt in DB loeschen
                $sql = 'DELETE FROM '.TBL_PHOTOS.'
                         WHERE pho_id = '.$photoId;
                $this->db->query($sql);
            }
        }

        $this->db->endTransaction();
        return $returnValue;
    }

    /**
     * Check if this album has one or more child albums.
     * @return bool Return @b true if child albums exists.
     */
    public function hasChildAlbums()
    {
        if($this->hasChildAlbums === null)
        {
            $sql = 'SELECT COUNT(*)
                      FROM '.TBL_PHOTOS.'
                     WHERE pho_pho_id_parent = '.$this->getValue('pho_id');
            $countChildAlbums = $this->db->query($sql);

            $row = $countChildAlbums->fetch();

            if($row[0] > 0)
            {
                $this->hasChildAlbums = true;
            }
            else
            {
                $this->hasChildAlbums = false;
            }
        }

        return $this->hasChildAlbums;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * The current organization will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;

        if($this->new_record)
        {
            $this->setValue('pho_org_id', $gCurrentOrganization->getValue('org_id'));
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Rekursive Funktion zum Auswaehlen eines Beispielbildes aus einem moeglichst hohen Album
     * Rueckgabe eines Arrays mit allen noetigen Infos um den Link zu erstellen
     * @param int $phoId
     * @return array
     */
    public function shuffleImage($phoId = 0)
    {
        $shuffleImage = array('shuffle_pho_id' => 0, 'shuffle_img_nr' => 0, 'shuffle_img_begin' => '');

        // wurde keine ID uebergeben, dann versuchen das Zufallsbild aus dem aktuellen Album zu nehmen
        if($phoId === 0)
        {
            $phoId = $this->getValue('pho_id');
            $shuffleImage['shuffle_pho_id']    = $this->getValue('pho_id');
            $shuffleImage['shuffle_img_begin'] = $this->getValue('pho_begin', 'Y-m-d');

            if($this->getValue('pho_quantity') > 0)
            {
                $shuffleImage['shuffle_img_nr'] = mt_rand(1, $this->getValue('pho_quantity'));
            }
        }

        if($shuffleImage['shuffle_img_nr'] == 0)
        {
            // kein Bild vorhanden, dann in einem Unteralbum suchen
            $sql = 'SELECT *
                      FROM '.TBL_PHOTOS.'
                     WHERE pho_pho_id_parent = '.$phoId.'
                       AND pho_locked = 0
                  ORDER BY pho_quantity DESC';
            $childAlbumsStatement = $this->db->query($sql);

            while($phoRow = $childAlbumsStatement->fetch())
            {
                if($shuffleImage['shuffle_img_nr'] == 0)
                {
                    $shuffleImage['shuffle_pho_id'] = $phoRow['pho_id'];
                    $shuffleImage['shuffle_img_begin'] = $phoRow['pho_begin'];

                    if($phoRow['pho_quantity'] > 0)
                    {
                        $shuffleImage['shuffle_img_nr'] = mt_rand(1, $phoRow['pho_quantity']);
                    }
                    else
                    {
                        $shuffleImage = $this->shuffleImage($phoRow['pho_id']);
                    }
                }
            }
        }
        return $shuffleImage;
    }
}
