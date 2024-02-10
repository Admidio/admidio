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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$postCatUUID = admFuncVariableIsValid($_POST, 'uuid', 'string');
$postType    = admFuncVariableIsValid($_POST, 'type', 'string', array('validValues' => array('ROL', 'LNK', 'USF', 'ANN', 'EVT', 'AWA')));
$postMode    = admFuncVariableIsValid($_POST, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));

if (in_array($postMode, array('delete', 'sequence'))) {
    $gMessage->showHtmlTextOnly();
}

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    if ($postMode === 'edit') {
        $exception->showHtml();
    } else {
        $exception->showText();
    }
    // => EXIT
}

// create category object
$category = new TableCategory($gDb);

if ($postCatUUID !== '') {
    $category->readDataByUuid($postCatUUID);

    // if system category then set cat_name to default
    if ($category->getValue('cat_system') == 1) {
        $_POST['cat_name'] = $category->getValue('cat_name');
    }
    if($postType === '') {
        $postType = $category->getValue('cat_type');
    }
} else {
    // create a new category
    $category->setValue('cat_org_id', $gCurrentOrgId);
    $category->setValue('cat_type', $postType);
}

// set text strings for the different modules
switch ($postType) {
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
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// check if this category is editable by the current user and current organization
if (!$category->isEditable()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if ($postMode === 'edit') {
    // create or edit category

    $_SESSION['categories_request'] = $_POST;

    if ((!array_key_exists('cat_name', $_POST) || $_POST['cat_name'] === '') && $category->getValue('cat_system') == 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    if ($postType !== 'ROL'
    && ((bool) $category->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1)
    && !isset($_POST['adm_categories_view_right'])) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_VISIBLE_FOR'))));
        // => EXIT
    }

    if (!isset($_POST['adm_categories_edit_right'])) {
        // edit right need not to be set because module administrators still have the right,
        // so initialize the parameter
        $_POST['adm_categories_edit_right'] = array();
    }

    // set a global category if its not a role category and the flag was set,
    // if its a profile field category and only 1 organization exists,
    // if its the role category of events
    if (($postType !== 'ROL' && isset($_POST['show_in_several_organizations']))
    || ($postType === 'USF' && $gCurrentOrganization->countAllRecords() === 1)
    || ($postType === 'ROL' && $category->getValue('cat_name_intern') === 'EVENTS')) {
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
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_type = ? -- $postType
                   AND cat_name = ? -- $_POST[\'cat_name\']
                   AND cat_uuid <> ? -- $postCatUUID
                       '.$sqlSearchOrga;
        $categoriesStatement = $gDb->queryPrepared($sql, array($postType, $_POST['cat_name'], $postCatUUID, $gCurrentOrgId));

        if ($categoriesStatement->fetchColumn() > 0) {
            $gMessage->show($gL10n->get('SYS_CATEGORY_EXISTS_IN_ORGA'));
            // => EXIT
        }
    }

    // for all checkboxes it must be checked whether a value was transferred here, if not,
    // hen set the value here to 0, since 0 is not transferred
    $checkboxes = array('cat_default');

    foreach ($checkboxes as $value) {
        if (!isset($_POST[$value]) || $_POST[$value] != 1) {
            $_POST[$value] = 0;
        }
    }

    // POST Writing variables to the UserField object
    foreach ($_POST as $key => $value) { // TODO possible security issue
        if (str_starts_with($key, 'cat_')) {
            $category->setValue($key, $value);
        }
    }

    $gDb->startTransaction();

    // write category into database
    $category->save();

    if ($postType !== 'ROL' && $category->getValue('cat_name_intern') !== 'BASIC_DATA') {
        $rightCategoryView = new RolesRights($gDb, 'category_view', (int) $category->getValue('cat_id'));

        // roles have their own preferences for visibility, so only allow this for other types
        // until now we do not support visibility for categories that belong to several organizations
        if ($category->getValue('cat_org_id') > 0
        || ((int) $category->getValue('cat_org_id') === 0 && $gCurrentOrganization->countAllRecords() === 1)) {
            // save changed roles rights of the category
            $rightCategoryView->saveRoles(array_map('intval', $_POST['adm_categories_view_right']));
        } else {
            // delete existing roles rights of the category
            $rightCategoryView->delete();
        }

        if ($postType === 'USF') {
            // delete cache with profile categories rights
            $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
        } else {
            // until now we don't use edit rights for profile fields
            $rightCategoryEdit = new RolesRights($gDb, 'category_edit', (int) $category->getValue('cat_id'));
            $rightCategoryEdit->saveRoles(array_map('intval', $_POST['adm_categories_edit_right']));
        }
    }

    // if a category has been converted from all orgs to a specific one or the other way around,
    // then the sequence must be reset for all categories of this type
    $sequenceCategory = new TableCategory($gDb);
    $sequence = 0;

    $sql = 'SELECT *
              FROM '.TBL_CATEGORIES.'
             WHERE cat_type = ? -- $postType
               AND (  cat_org_id  = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_org_id ASC, cat_sequence ASC';
    $categoriesStatement = $gDb->queryPrepared($sql, array($postType, $gCurrentOrgId));

    while ($row = $categoriesStatement->fetch()) {
        ++$sequence;
        $sequenceCategory->clear();
        $sequenceCategory->setArray($row);

        $sequenceCategory->setValue('cat_sequence', $sequence);
        $sequenceCategory->save();
    }

    $gDb->endTransaction();

    $gNavigation->deleteLastUrl();
    unset($_SESSION['categories_request']);

    admRedirect($gNavigation->getUrl());
    // => EXIT
} elseif ($postMode === 'delete') {
    // delete category
    try {
        if ($category->delete()) {
            echo 'done';
            exit();
        }
    } catch (AdmException $e) {
        $e->showText();
        // => EXIT
    }
} elseif ($postMode === 'sequence') {
    // Update category sequence
    $postSequence = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(TableCategory::MOVE_UP, TableCategory::MOVE_DOWN)));

    if ($category->moveSequence($postSequence)) {
        echo 'done';
    } else {
        echo 'Sequence could not be changed.';
    }
    exit();
}
