<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_photos
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
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
    /**
     * @var bool|null Flag if this album has child albums
     */
    protected $hasChildAlbums;

    /**
     * Constructor that will create an object of a recordset of the table adm_photos.
     * If the id is set than the specific photo album will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $phoId    The recordset of the photo album with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $phoId = 0)
    {
        parent::__construct($database, TBL_PHOTOS, 'pho', $phoId);
    }

    /**
     * Initialize all necessary data of this object.
     * @return void
     */
    public function clear()
    {
        parent::clear();

        $this->hasChildAlbums = null;
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
        if ($phoId === 0) {
            $phoId = (int) $this->getValue('pho_id');
            $totalImages = (int) $this->getValue('pho_quantity');
        }

        // Get all sub-albums
        $sql = 'SELECT pho_id, pho_quantity
                  FROM '.TBL_PHOTOS.'
                 WHERE pho_pho_id_parent = ? -- $phoId
                   AND pho_locked = false';
        $childAlbumsStatement = $this->db->queryPrepared($sql, array($phoId));

        while ($phoRow = $childAlbumsStatement->fetch()) {
            $totalImages += (int) $phoRow['pho_quantity'] + $this->countImages((int) $phoRow['pho_id']);
        }

        return $totalImages;
    }

    /**
     * Legt den Ordner fuer die Veranstaltung im Dateisystem an
     * @return array<string,string>|null
     */
    public function createFolder()
    {
        // Ordner fuer die Veranstaltung anlegen
        $folderName = $this->getValue('pho_begin', 'Y-m-d') . '_' . (int) $this->getValue('pho_id');
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $folderName);
        } catch (\RuntimeException $exception) {
            return array(
                'text' => 'SYS_FOLDER_NOT_CREATED',
                'path' => 'adm_my_files/photos/' . $folderName
            );
        }

        return null;
    }

    /**
     * Deletes the selected photo album and all sub photo albums.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        if ($this->deleteInDatabase((int) $this->getValue('pho_id'))) {
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
                 WHERE pho_pho_id_parent = ? -- $photoId';
        $childAlbumStatement = $this->db->queryPrepared($sql, array($photoId));

        while ($phoId = $childAlbumStatement->fetchColumn()) {
            if ($returnValue) {
                $returnValue = $this->deleteInDatabase((int) $phoId);
            }
        }

        // nun DB-Eintrag und Ordner loeschen
        if ($returnValue) {
            // Ordnerpfad zusammensetzen
            $folder = ADMIDIO_PATH . FOLDER_DATA. '/photos/'.$this->getValue('pho_begin', 'Y-m-d').'_'.$photoId;

            // aktuellen Ordner incl. Unterordner und Dateien loeschen, falls er existiert
            try {
                $dirDeleted = FileSystemUtils::deleteDirectoryIfExists($folder, true);

                if ($dirDeleted) {
                    // Veranstaltung jetzt in DB loeschen
                    $sql = 'DELETE FROM '.TBL_PHOTOS.'
                             WHERE pho_id = ? -- $photoId';
                    $this->db->queryPrepared($sql, array($photoId));
                }
            } catch (\RuntimeException $exception) {
            }
        }

        $this->db->endTransaction();

        return $returnValue;
    }

    /**
     * Returns the name of the photographers. If there is no photographer(s) saved within this
     * album then the method will return the name "unknown".
     * @return string Name of the photographer(s)
     */
    public function getPhotographer(): string
    {
        global $gL10n;

        $photographer = (string) $this->getValue('pho_photographers');

        if ($photographer === '') {
            $photographer = $gL10n->get('SYS_UNKNOWN');
        }

        return $photographer;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format          Returns the field value in a special format **text**, **html**, **database**
     *                                or datetime (detailed description in method description)
     *                                * 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function
     *                                * 'html'  : returns the value in html-format if this is necessary for that field type.
     *                                * 'database' : returns the value that is stored in database with no format applied
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if ($columnName === 'pho_description' && $format === 'html') {
            $value = nl2br(parent::getValue($columnName));
        } else {
            $value = parent::getValue($columnName, $format);
        }

        return $value;
    }

    /**
     * Check if this album has one or more child albums.
     * @return bool Return **true** if child albums exists.
     */
    public function hasChildAlbums()
    {
        if ($this->hasChildAlbums === null) {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_PHOTOS.'
                     WHERE pho_pho_id_parent = ? -- $this->getValue(\'pho_id\')';
            $countChildAlbums = $this->db->queryPrepared($sql, array((int) $this->getValue('pho_id')));

            $this->hasChildAlbums = $countChildAlbums->fetchColumn() > 0;
        }

        return $this->hasChildAlbums;
    }

    /**
     * This method checks if the current user is allowed to edit this photo album. Therefore
     * the photo album must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage photo albums.
     * @return bool Return true if the current user is allowed to edit this photo album
     */
    public function isEditable()
    {
        global $gCurrentUser;

        return $gCurrentUser->editPhotoRight() && ($this->isVisible() || (int) $this->getValue('pho_id') === 0);
    }

    /**
     * This method checks if the current user is allowed to view this photo album. Therefore
     * the album must be from the current organization and should not be locked or the user
     * is a module administrator.
     * @return bool Return true if the current user is allowed to view this photo album
     */
    public function isVisible()
    {
        // current photo album must belong to current organization
        if ($this->getValue('pho_id') > 0 && (int) $this->getValue('pho_org_id') !== $GLOBALS['gCurrentOrgId']) {
            return false;
        }
        // locked photo album could only be viewed by module administrators
        elseif ($this->getValue('pho_locked') && !$GLOBALS['gCurrentUser']->editPhotoRight()) {
            return false;
        }

        return true;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * The current organization will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        if ($this->newRecord) {
            $this->setValue('pho_org_id', $GLOBALS['gCurrentOrgId']);
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
        if ($phoId === 0) {
            $phoId = (int) $this->getValue('pho_id');
            $shuffleImage['shuffle_pho_id']    = $phoId;
            $shuffleImage['shuffle_pho_uuid']  = $this->getValue('pho_uuid');
            $shuffleImage['shuffle_img_begin'] = $this->getValue('pho_begin', 'Y-m-d');

            if ($this->getValue('pho_quantity') > 0) {
                $shuffleImage['shuffle_img_nr'] = mt_rand(1, (int) $this->getValue('pho_quantity'));
            }
        }

        if ($shuffleImage['shuffle_img_nr'] === 0) {
            // kein Bild vorhanden, dann in einem Unteralbum suchen
            $sql = 'SELECT pho_id, pho_uuid, pho_begin, pho_quantity
                      FROM '.TBL_PHOTOS.'
                     WHERE pho_pho_id_parent = ? -- $phoId
                       AND pho_locked = false
                  ORDER BY pho_quantity DESC';
            $childAlbumsStatement = $this->db->queryPrepared($sql, array($phoId));

            while ($phoRow = $childAlbumsStatement->fetch()) {
                if ($shuffleImage['shuffle_img_nr'] === 0) {
                    $shuffleImage['shuffle_pho_id']    = (int) $phoRow['pho_id'];
                    $shuffleImage['shuffle_pho_uuid']  = $phoRow['pho_uuid'];
                    $shuffleImage['shuffle_img_begin'] = $phoRow['pho_begin'];

                    if ($phoRow['pho_quantity'] > 0) {
                        $shuffleImage['shuffle_img_nr'] = mt_rand(1, $phoRow['pho_quantity']);
                    } else {
                        $shuffleImage = $this->shuffleImage((int) $phoRow['pho_id']);
                    }
                }
            }
        }

        return $shuffleImage;
    }
}
