<?php
namespace Admidio\Infrastructure\Utils;

use Admidio\Categories\Entity\Category;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;

/**
 * @brief Class to implement useful method maintenance of data in the Admidio database.
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Maintenance
{
    /**
     * @var Database A database object with an existing database connection
     */
    private Database $database;

    /**
     * @param Database $database Object of the database that should be checked. A connection should be established.
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Reorganize the sequence of all categories. They will be ordered within each type. First, all categories with no
     * organization and then all categories with an organization will be sorted. The sequence number of categories
     * from each organization will start after the sequence of categories without an organization.
     * The current sequence of the categories will be considered.
     * @return void
     * @throws Exception
     */
    public function reorganizeCategories(): void
    {
        $currentCategoryType = '';
        $currentOrganization = 0;
        $newSequenceBase = 0;
        $newSequenceOrganization = 0;

        $sql = 'SELECT cat.*
                  FROM ' . TBL_CATEGORIES . ' cat
                 ORDER BY cat_type, cat_org_id, cat_sequence';
        $categoryStatement = $this->database->queryPrepared($sql);

        while ($row = $categoryStatement->fetch()) {
            if ($currentCategoryType != $row['cat_type']) {
                $newSequenceBase = 1;
                $newSequenceOrganization = 1;
                $currentCategoryType = $row['cat_type'];
                $currentOrganization = (int) $row['cat_org_id'];
            } elseif ($currentOrganization !== (int) $row['cat_org_id']) {
                $newSequenceOrganization = $newSequenceBase;
                $currentOrganization = (int) $row['cat_org_id'];
            }

            $category = new Category($this->database);
            $category->setArray($row);
            $category->setValue('cat_sequence', $newSequenceOrganization);
            $category->save();

            if ((int) $row['cat_org_id'] === 0) {
                $newSequenceBase++;
            }
            $newSequenceOrganization++;
        }
    }

    /**
     * Reset all path values of every folder in the database. The script will start at every root folder
     * of every organization and will set the path of every folder to the path of the root folder.
     * @return void
     * @throws Exception
     */
    public function repairDocumentsFilesPath(): void
    {
        $sql = 'SELECT fol_id, fol_name, fol_path
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_fol_id_parent IS NULL ';
        $rootFolderStatement = $this->database->queryPrepared($sql);

        while ($rowRootFolder = $rootFolderStatement->fetch()) {
            $rootFolder = new Folder($this->database, $rowRootFolder['fol_id']);

            $sql = 'SELECT fol_id, fol_name, fol_path
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_fol_id_parent = ? -- $rowRootFolder[\'fol_id\']';
            $folderStatement = $this->database->queryPrepared($sql, array($rowRootFolder['fol_id']));

            while ($row = $folderStatement->fetch()) {
                $folder = new Folder($this->database, $row['fol_id']);
                $folder->rename($row['fol_name'], $rootFolder->getValue('fol_path') . '/' . $rowRootFolder['fol_name']);
            }
        }
    }
}
