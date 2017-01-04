<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_photos
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TablePhotos
 * Diese Klasse dient dazu ein Fotoveranstaltungsobjekt zu erstellen.
 * Eine Fotoveranstaltung kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * countImages($phoId = 0)     - Rekursive Funktion gibt die Anzahl aller Bilder
 *                               inkl. der Unteralben zurueck
 * shuffleImage($phoId = 0)    - Rekursive Funktion zum Auswaehlen eines
 *                               Beispielbildes aus einem moeglichst hohen Album
 * createFolder()              - erzeugt den entsprechenden Ordner unter adm_my_files/photos
 * deleteInDatabase($photoId)  - Rekursive Funktion die die uebergebene Veranstaltung
 *                               und alle Unterveranstaltungen loescht
 * deleteInFilesystem($folder) - Rekursive Funktion die alles innerhalb des uebergebenen
 *                               Ordners mit Unterordnern und allen Dateien loescht
 */
class TablePhotos extends TableAccess
{
    protected $hasChildAlbums; ///< Flag if this album has child albums

    /**
     * Constructor that will create an object of a recordset of the table adm_photos.
     * If the id is set than the specific photo album will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $phoId    The recordset of the photo album with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $phoId = 0)
    {
        $this->hasChildAlbums = null;

        parent::__construct($database, TBL_PHOTOS, 'pho', $phoId);
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

        // If no phoId is set, calculate the amount of pictures in the current album
        if ($phoId === 0)
        {
            $phoId = (int) $this->getValue('pho_id');
            $totalImages = (int) $this->getValue('pho_quantity');
        }

        // Get all sub-albums
        $sql = 'SELECT pho_id, pho_quantity
                  FROM '.TBL_PHOTOS.'
                 WHERE pho_pho_id_parent = '.$phoId.'
                   AND pho_locked = 0';
        $childAlbumsStatement = $this->db->query($sql);

        while ($phoRow = $childAlbumsStatement->fetch())
        {
            $totalImages += (int) $phoRow['pho_quantity'] + $this->countImages((int) $phoRow['pho_id']);
        }

        return $totalImages;
    }

    /**
     * Legt den Ordner fuer die Veranstaltung im Dateisystem an
     * @return string[]|null
     */
    public function createFolder()
    {
        // Pfad in adm_my_files pruefen und ggf. anlegen
        $myFilesPhotos = new MyFiles('PHOTOS');
        if (!$myFilesPhotos->checkSettings())
        {
            return array(
                'text' => $myFilesPhotos->errorText,
                'path' => $myFilesPhotos->errorPath
            );
        }

        // nun den Ordner fuer die Veranstaltung anlegen
        $folderName = $this->getValue('pho_begin', 'Y-m-d') . '_' . $this->getValue('pho_id');
        if (!$myFilesPhotos->createFolder($folderName, true))
        {
            return array(
                'text' => 'SYS_FOLDER_NOT_CREATED',
                'path' => 'adm_my_files/photos/'.$folderName
            );
        }

        return null;
    }

    /**
     * Deletes the selected photo album and all sub photo albums.
     * After that the class will be initialize.
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        if ($this->deleteInDatabase((int) $this->getValue('pho_id')))
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

        while ($phoId = $childAlbumStatement->fetchColumn())
        {
            if ($returnValue)
            {
                $returnValue = $this->deleteInDatabase((int) $phoId);
            }
        }

        // nun DB-Eintrag und Ordner loeschen
        if ($returnValue)
        {
            // Ordnerpfad zusammensetzen
            $folder = ADMIDIO_PATH . FOLDER_DATA. '/photos/'.$this->getValue('pho_begin', 'Y-m-d').'_'.$photoId;

            // aktuellen Ordner incl. Unterordner und Dateien loeschen, falls er existiert
            if (is_dir($folder))
            {
                // nun erst rekursiv den Ordner im Dateisystem loeschen
                $myFilesPhotos = new MyFiles('PHOTOS');
                $myFilesPhotos->setFolder($folder);
                $returnValue = $myFilesPhotos->delete($folder);
            }

            if ($returnValue)
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
        if ($this->hasChildAlbums === null)
        {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_PHOTOS.'
                     WHERE pho_pho_id_parent = '.$this->getValue('pho_id');
            $countChildAlbums = $this->db->query($sql);

            if ($countChildAlbums->fetchColumn() > 0)
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

        if ($this->new_record)
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
        if ($phoId === 0)
        {
            $phoId = (int) $this->getValue('pho_id');
            $shuffleImage['shuffle_pho_id']    = $phoId;
            $shuffleImage['shuffle_img_begin'] = $this->getValue('pho_begin', 'Y-m-d');

            if ($this->getValue('pho_quantity') > 0)
            {
                $shuffleImage['shuffle_img_nr'] = mt_rand(1, $this->getValue('pho_quantity'));
            }
        }

        if ($shuffleImage['shuffle_img_nr'] === 0)
        {
            // kein Bild vorhanden, dann in einem Unteralbum suchen
            $sql = 'SELECT pho_id, pho_begin, pho_quantity
                      FROM '.TBL_PHOTOS.'
                     WHERE pho_pho_id_parent = '.$phoId.'
                       AND pho_locked = 0
                  ORDER BY pho_quantity DESC';
            $childAlbumsStatement = $this->db->query($sql);

            while ($phoRow = $childAlbumsStatement->fetch())
            {
                if ($shuffleImage['shuffle_img_nr'] === 0)
                {
                    $shuffleImage['shuffle_pho_id']    = (int) $phoRow['pho_id'];
                    $shuffleImage['shuffle_img_begin'] = $phoRow['pho_begin'];

                    if ($phoRow['pho_quantity'] > 0)
                    {
                        $shuffleImage['shuffle_img_nr'] = mt_rand(1, $phoRow['pho_quantity']);
                    }
                    else
                    {
                        $shuffleImage = $this->shuffleImage((int) $phoRow['pho_id']);
                    }
                }
            }
        }

        return $shuffleImage;
    }
}
