<?php
namespace Admidio\Categories\Service;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\RolesRights;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CategoryService
{
    protected Category $categoryRessource;
    protected Database $db;
    protected string $UUID;
    protected string $type;
    protected array|null $editableCategories = null;
    protected array|null $visibleCategories = null;
    protected int $count = 0;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $type Type of category that should be shown. Values are ROL, ANN, EVT, LNK, USF and AWA.
     * @param string $categoryUUID UUID if the category that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $type, string $categoryUUID = '')
    {
        $this->db = $database;
        $this->categoryRessource = new Category($database);
        $this->UUID = $categoryUUID;
        $this->type = $type;

        if ($categoryUUID !== '') {
            $this->categoryRessource->readDataByUuid($categoryUUID);
        }
    }

    /**
     * Returns the number of categories of this organization from the set type.
     * @return int Returns the number of categories of this organization from the set type.
     * @throws Exception
     */
    public function count(): int
    {
        global $gCurrentOrgId;

        if ($this->count === 0) {
            $sql = 'SELECT count(*) as count
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $this->type
                   AND (cat_org_id IS NULL OR cat_org_id = ?) -- $gCurrentOrgId';
            $pdoStatement = $this->db->queryPrepared($sql, array($this->type, $gCurrentOrgId));

            $row = $pdoStatement->fetch();
            $this->count = $row['count'];
        }
        return $this->count;
    }

    /**
     * Get an array with all categories of this organization from the set type which the current user is allowed to edit.
     * @return array<int,array> Array with all categories. Each category is an array with the keys 'cat_id', 'cat_uuid', 'cat_name', 'cat_default'
     * @throws Exception
     */
    public function getEditableCategories(): array
    {
        global $gCurrentUser;

        if ($this->editableCategories === null) {
            $editableCategoryIDs = $gCurrentUser->getAllEditableCategories($this->type);

            $sql = 'SELECT cat_id, cat_uuid, cat_name, cat_default
                      FROM ' . TBL_CATEGORIES . '
                     WHERE cat_id IN (' . Database::getQmForValues($editableCategoryIDs) . ')';
            $pdoStatement = $this->db->queryPrepared($sql, $editableCategoryIDs);

            while ($row = $pdoStatement->fetch()) {
                $this->editableCategories[] = $row;
            }
        }
        return $this->editableCategories;
    }


    /**
     * Get an array with UUIDs of all categories of this organization from the set type which the current user is allowed to edit.
     * @return array<int,string> Array with UUIDs of all editable categories.
     * @throws Exception
     */
    public function getEditableCategoryUUIDs(): array
    {
        $editableCategoryUUIDs = array();

        if ($this->editableCategories === null) {
            $this->getEditableCategories();
        }

        foreach ($this->editableCategories as $category) {
            $editableCategoryUUIDs[] = $category['cat_uuid'];
        }
        return $editableCategoryUUIDs;
    }

    /**
     * Get an array with all categories of this organization from the set type which the current user is allowed to view.
     * @return array<int,array> Array with all categories. Each category is an array with the keys 'cat_id', 'cat_uuid', 'cat_name', 'cat_default'
     * @throws Exception
     */
    public function getVisibleCategories(): array
    {
        global $gCurrentUser;

        if ($this->visibleCategories === null) {
            $visibleCategoryIDs = $gCurrentUser->getAllVisibleCategories($this->type);

            $sql = 'SELECT cat_id, cat_uuid, cat_name, cat_default
                      FROM ' . TBL_CATEGORIES . '
                     WHERE cat_id IN (' . Database::getQmForValues($visibleCategoryIDs) . ')';
            $pdoStatement = $this->db->queryPrepared($sql, $visibleCategoryIDs);

            while ($row = $pdoStatement->fetch()) {
                $this->visibleCategories[] = $row;
            }
        }
        return $this->visibleCategories;
    }

    /**
     * Save data from the category form into the database.
     * @throws Exception
     */
    public function save(): void
    {
        global $gCurrentOrganization, $gCurrentSession, $gCurrentOrgId, $gDb, $gProfileFields;

        // check form field input and sanitized it from malicious content
        $categoryEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $categoryEditForm->validate($_POST);

        if ($this->type !== 'ROL'
            && ((bool)$this->categoryRessource->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1)
            && !isset($_POST['adm_categories_view_right'])) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_VISIBLE_FOR'));
        }

        if (!isset($_POST['adm_categories_edit_right'])) {
            // The editing right does not have to be set, as the module administrators still have the right,
            // so initialize the parameter
            $_POST['adm_categories_edit_right'] = array();
        }

        // set a global category if it's not a role category and the flag was set,
        // if it's a profile field category and only 1 organization exists,
        // if it's the role category of events
        if (($this->type !== 'ROL' && isset($_POST['show_in_several_organizations']))
            || ($this->type === 'USF' && $gCurrentOrganization->countAllRecords() === 1)
            || ($this->type === 'ROL' && $this->categoryRessource->getValue('cat_name_intern') === 'EVENTS')) {
            $this->categoryRessource->setValue('cat_org_id', 0);
            $sqlSearchOrga = ' AND (  cat_org_id = ? -- $gCurrentOrgId
                               OR cat_org_id IS NULL )';
        } else {
            $this->categoryRessource->setValue('cat_org_id', $gCurrentOrgId);
            $sqlSearchOrga = ' AND cat_org_id = ? -- $gCurrentOrgId';
        }

        if ($this->categoryRessource->getValue('cat_name') !== $_POST['cat_name']) {
            // See if the category already exists
            $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $this->type
                   AND cat_name = ? -- $_POST[\'cat_name\']
                   AND cat_uuid <> ? -- $getCatUUID
                       ' . $sqlSearchOrga;
            $categoriesStatement = $gDb->queryPrepared($sql, array($this->type, $_POST['cat_name'], $this->UUID, $gCurrentOrgId));

            if ($categoriesStatement->fetchColumn() > 0) {
                throw new Exception('SYS_CATEGORY_EXISTS_IN_ORGA');
            }
        }

        // write form values in category object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'cat_')) {
                $this->categoryRessource->setValue($key, $value);
            }
        }

        $gDb->startTransaction();

        // write category into database
        $this->categoryRessource->setValue('cat_type', $this->type);
        $this->categoryRessource->save();

        if ($this->type !== 'ROL' && $this->categoryRessource->getValue('cat_name_intern') !== 'BASIC_DATA') {
            $rightCategoryView = new RolesRights($gDb, 'category_view', (int)$this->categoryRessource->getValue('cat_id'));

            // roles have their own preferences for visibility, so only allow this for other types
            // until now we do not support visibility for categories that belong to several organizations
            if ($this->categoryRessource->getValue('cat_org_id') > 0
                || ((int)$this->categoryRessource->getValue('cat_org_id') === 0 && $gCurrentOrganization->countAllRecords() === 1)) {
                // save changed roles rights of the category
                $rightCategoryView->saveRoles(array_map('intval', $_POST['adm_categories_view_right']));
            } else {
                // delete existing roles rights of the category
                $rightCategoryView->delete();
            }

            if ($this->type === 'USF') {
                // delete cache with profile categories rights
                $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
            } else {
                // until now, we don't use edit rights for profile fields
                $rightCategoryEdit = new RolesRights($gDb, 'category_edit', (int)$this->categoryRessource->getValue('cat_id'));
                $rightCategoryEdit->saveRoles(array_map('intval', $_POST['adm_categories_edit_right']));
            }
        }

        // if a category has been converted from all organizations to a specific one or the other way around,
        // then the sequence must be reset for all categories of this type
        $sequenceCategory = new Category($gDb);
        $sequence = 0;

        $sql = 'SELECT *
              FROM ' . TBL_CATEGORIES . '
             WHERE cat_type = ? -- $this->type
               AND (  cat_org_id  = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_org_id, cat_sequence';
        $categoriesStatement = $gDb->queryPrepared($sql, array($this->type, $gCurrentOrgId));

        while ($row = $categoriesStatement->fetch()) {
            ++$sequence;
            $sequenceCategory->clear();
            $sequenceCategory->setArray($row);

            $sequenceCategory->setValue('cat_sequence', $sequence);
            $sequenceCategory->save();
        }

        $gDb->endTransaction();
    }
}
