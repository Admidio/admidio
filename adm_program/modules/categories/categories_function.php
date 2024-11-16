<?php
/**
 ***********************************************************************************************
 * Various functions for categories
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Parameters:
 *
 * uuid : Uuid of the category, that should be edited
 * type     : Type of categories that could be maintained
 *            ROL = Categories for roles
 *            LNK = Categories for weblinks
 *            ANN = Categories for announcements
 *            USF = Categories for profile fields
 *            EVT = Calendars for events
 * mode     : edit     - Create or edit categories
 *            delete   - Delete category
 *            sequence - Change sequence for parameter cat_id
 * direction : Direction to change the sequence of the category
 *****************************************************************************/
use Admidio\Exception;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getCatUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    $getType = admFuncVariableIsValid($_GET, 'type', 'string', array('validValues' => array('ROL', 'LNK', 'USF', 'ANN', 'EVT', 'AWA')));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));

    // create category object
    $category = new TableCategory($gDb);

    if ($getCatUUID !== '') {
        $category->readDataByUuid($getCatUUID);

        // if system category then set cat_name to default
        if ($category->getValue('cat_system') == 1) {
            $_POST['cat_name'] = $category->getValue('cat_name');
        }
        if ($getType === '') {
            $getType = $category->getValue('cat_type');
        }
    } else {
        // create a new category
        $category->setValue('cat_org_id', $gCurrentOrgId);
        $category->setValue('cat_type', $getType);
    }

    // set text strings for the different modules
    switch ($getType) {
        case 'ANN':
            $component = 'ANNOUNCEMENTS';
            break;

        case 'EVT':
            $component = 'EVENTS';
            break;

        case 'LNK':
            $component = 'LINKS';
            break;

        case 'ROL':
            $component = 'GROUPS-ROLES';
            break;

        case 'USF': // fallthrough
        case 'AWA':
            $component = 'CORE';
            break;

        default:
            $component = '';
    }

    // check if the current user has the right to
    if (!Component::isAdministrable($component)) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // check if this category is editable by the current user and current organization
    if (!$category->isEditable()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getMode === 'edit') {
        // create or edit category

        // check form field input and sanitized it from malicious content
        $categoryEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $categoryEditForm->validate($_POST);

        if ($getType !== 'ROL'
            && ((bool)$category->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1)
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
        if (($getType !== 'ROL' && isset($_POST['show_in_several_organizations']))
            || ($getType === 'USF' && $gCurrentOrganization->countAllRecords() === 1)
            || ($getType === 'ROL' && $category->getValue('cat_name_intern') === 'EVENTS')) {
            $category->setValue('cat_org_id', 0);
            $sqlSearchOrga = ' AND (  cat_org_id = ? -- $gCurrentOrgId
                               OR cat_org_id IS NULL )';
        } else {
            $category->setValue('cat_org_id', $gCurrentOrgId);
            $sqlSearchOrga = ' AND cat_org_id = ? -- $gCurrentOrgId';
        }

        if ($category->getValue('cat_name') !== $_POST['cat_name']) {
            // See if the category already exists
            $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $getType
                   AND cat_name = ? -- $_POST[\'cat_name\']
                   AND cat_uuid <> ? -- $getCatUUID
                       ' . $sqlSearchOrga;
            $categoriesStatement = $gDb->queryPrepared($sql, array($getType, $_POST['cat_name'], $getCatUUID, $gCurrentOrgId));

            if ($categoriesStatement->fetchColumn() > 0) {
                throw new Exception('SYS_CATEGORY_EXISTS_IN_ORGA');
            }
        }

        // write form values in category object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'cat_')) {
                $category->setValue($key, $value);
            }
        }

        $gDb->startTransaction();

        // write category into database
        $category->save();

        if ($getType !== 'ROL' && $category->getValue('cat_name_intern') !== 'BASIC_DATA') {
            $rightCategoryView = new RolesRights($gDb, 'category_view', (int)$category->getValue('cat_id'));

            // roles have their own preferences for visibility, so only allow this for other types
            // until now we do not support visibility for categories that belong to several organizations
            if ($category->getValue('cat_org_id') > 0
                || ((int)$category->getValue('cat_org_id') === 0 && $gCurrentOrganization->countAllRecords() === 1)) {
                // save changed roles rights of the category
                $rightCategoryView->saveRoles(array_map('intval', $_POST['adm_categories_view_right']));
            } else {
                // delete existing roles rights of the category
                $rightCategoryView->delete();
            }

            if ($getType === 'USF') {
                // delete cache with profile categories rights
                $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
            } else {
                // until now, we don't use edit rights for profile fields
                $rightCategoryEdit = new RolesRights($gDb, 'category_edit', (int)$category->getValue('cat_id'));
                $rightCategoryEdit->saveRoles(array_map('intval', $_POST['adm_categories_edit_right']));
            }
        }

        // if a category has been converted from all organizations to a specific one or the other way around,
        // then the sequence must be reset for all categories of this type
        $sequenceCategory = new TableCategory($gDb);
        $sequence = 0;

        $sql = 'SELECT *
              FROM ' . TBL_CATEGORIES . '
             WHERE cat_type = ? -- $getType
               AND (  cat_org_id  = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_org_id, cat_sequence';
        $categoriesStatement = $gDb->queryPrepared($sql, array($getType, $gCurrentOrgId));

        while ($row = $categoriesStatement->fetch()) {
            ++$sequence;
            $sequenceCategory->clear();
            $sequenceCategory->setArray($row);

            $sequenceCategory->setValue('cat_sequence', $sequence);
            $sequenceCategory->save();
        }

        $gDb->endTransaction();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // delete category

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        if ($category->delete()) {
            echo json_encode(array('status' => 'success'));
            exit();
        }
    } elseif ($getMode === 'sequence') {
        // Update category sequence
        $postSequence = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(TableCategory::MOVE_UP, TableCategory::MOVE_DOWN)));

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        if ($category->moveSequence($postSequence)) {
            echo json_encode(array('status' => 'success'));
        } else {
            throw new Exception('Sequence could not be changed.');
        }
        exit();
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
