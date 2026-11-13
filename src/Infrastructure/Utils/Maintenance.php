<?php
namespace Admidio\Infrastructure\Utils;

use Admidio\Categories\Entity\Category;
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
     * Repair the path of every descendant folder using its parent folder's path and name.
     * The root paths of all organizations remain unchanged.
     * @return void
     * @throws Exception
     */
    public function repairDocumentsFilesPath(): void
    {
        $sql = 'SELECT fol_id, fol_name, fol_path
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_fol_id_parent IS NULL ';
        $rootFolders = $this->database->queryPrepared($sql)->fetchAll();
        $childSql = 'SELECT fol_id, fol_name, fol_path
                       FROM ' . TBL_FOLDERS . '
                      WHERE fol_fol_id_parent = ?';
        $updateSql = 'UPDATE ' . TBL_FOLDERS . ' SET fol_path = ? WHERE fol_id = ?';

        foreach ($rootFolders as $rowRootFolder) {
            // Maintenance runs across organizations, so folder entities cannot be loaded here:
            // Folder::readData() only accepts folders of the current organization.
            $folders = array($rowRootFolder);

            while ($folders !== array()) {
                $parent = array_pop($folders);
                $parentFullPath = $parent['fol_path'] . '/' . $parent['fol_name'];

                $children = $this->database->queryPrepared($childSql, array($parent['fol_id']))->fetchAll();

                foreach ($children as $folder) {
                    if ($folder['fol_path'] !== $parentFullPath) {
                        $this->database->queryPrepared($updateSql, array($parentFullPath, $folder['fol_id']));
                    }

                    $folder['fol_path'] = $parentFullPath;
                    $folders[] = $folder;
                }
            }
        }
    }
}
