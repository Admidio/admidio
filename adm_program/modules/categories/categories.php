<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all categories
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * type  : Type of categories that could be maintained
 *         ROL = Categories for roles
 *         LNK = Categories for weblinks
 *         ANN = Categories for announcements
 *         USF = Categories for profile fields
 *         DAT = Calendars for events
 *
 ****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getType = admFuncVariableIsValid($_GET, 'type', 'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'DAT', 'AWA')));

// Modus und Rechte pruefen
if (($getType === 'ROL' && !$gCurrentUser->manageRoles())
||  ($getType === 'LNK' && !$gCurrentUser->editWeblinksRight())
||  ($getType === 'ANN' && !$gCurrentUser->editAnnouncements())
||  ($getType === 'USF' && !$gCurrentUser->editUsers())
||  ($getType === 'DAT' && !$gCurrentUser->editDates())
||  ($getType === 'AWA' && !$gCurrentUser->editUsers())) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
$headline         = $gL10n->get('SYS_CATEGORIES');
$addButtonText    = $gL10n->get('SYS_CREATE_CATEGORY');
$visibleHeadline  = $gL10n->get('SYS_VISIBLE_FOR');
$navigationHeadline = $gL10n->get('SYS_CATEGORIES');
$editableHeadline = '';

switch ($getType) {
    case 'ROL':
        $component = 'GROUPS-ROLES';
        $rolesRightsColumn = 'rol_assign_roles';
        $headline = $gL10n->get('SYS_ROLES') . ' - ' . $gL10n->get('SYS_CATEGORIES');
        $visibleHeadline = '';
        break;

    case 'ANN':
        $component = 'ANNOUNCEMENTS';
        $rolesRightsColumn = 'rol_announcements';
        $headline = $gL10n->get('SYS_ANNOUNCEMENTS') . ' - ' . $gL10n->get('SYS_CATEGORIES');
        $editableHeadline = $gL10n->get('SYS_EDIT_ANNOUNCEMENTS');
        break;

    case 'DAT':
        $component = 'DATES';
        $rolesRightsColumn = 'rol_dates';
        $headline = $gL10n->get('DAT_DATES') . ' - ' . $gL10n->get('SYS_CALENDARS');
        $navigationHeadline = $gL10n->get('SYS_CALENDARS');
        $editableHeadline = $gL10n->get('DAT_EDIT_EVENTS');
        $addButtonText    = $gL10n->get('SYS_CREATE_CALENDAR');
        break;

    case 'LNK':
        $component = 'LINKS';
        $rolesRightsColumn = 'rol_weblinks';
        $headline = $gL10n->get('SYS_WEBLINKS') . ' - ' . $gL10n->get('SYS_CATEGORIES');
        $editableHeadline = $gL10n->get('SYS_EDIT_WEBLINKS');
        break;

    case 'USF':
        $component = 'CORE';
        $rolesRightsColumn = 'rol_edit_user';
        $headline = $gL10n->get('ORG_PROFILE_FIELDS') . ' - ' . $gL10n->get('SYS_CATEGORIES');
        $editableHeadline = $gL10n->get('PRO_EDIT_PROFILE_FIELDS');
        break;

    case 'AWA':
        $component = 'CORE';
        $rolesRightsColumn = 'rol_edit_user';
        $headline = $gL10n->get('Awards') . ' - ' . $gL10n->get('SYS_CATEGORIES');
        break;

    default:
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
}

// check if the current user has the right to
if (!Component::isAdministrable($component)) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// read all administrator roles

$sqlAdminRoles = 'SELECT rol_name
                    FROM '.TBL_ROLES.'
              INNER JOIN '.TBL_CATEGORIES.'
                      ON cat_id = rol_cat_id
                   WHERE rol_valid  = true
                     AND '. $rolesRightsColumn .' = true
                     AND cat_org_id = ? -- $gCurrentOrgId
                ORDER BY cat_sequence, rol_name';
$statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrgId));

$adminRoles = array();
while ($roleName = $statementAdminRoles->fetchColumn()) {
    $adminRoles[] = $roleName;
}

$gNavigation->addUrl(CURRENT_URL, $navigationHeadline);
unset($_SESSION['categories_request']);

// create html page object
$page = new HtmlPage('admidio-categories', $headline);

// define link to create new category
$page->addPageFunctionsMenuItem(
    'menu_item_categories_add',
    $addButtonText,
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('type' => $getType)),
    'fa-plus-circle'
);

// Create table object
$categoriesOverview = new HtmlTable('tbl_categories', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    '<i class="fas fa-star" data-toggle="tooltip" title="' . $gL10n->get('SYS_DEFAULT_VAR', array($addButtonText)) . '"></i>',
    $visibleHeadline,
    $editableHeadline,
    '&nbsp;'
);
$categoriesOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'right'));
$categoriesOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT *
          FROM '.TBL_CATEGORIES.'
         WHERE (  cat_org_id  = ? -- $gCurrentOrgId
               OR cat_org_id IS NULL )
           AND cat_type = ? -- $getType
      ORDER BY cat_sequence ASC';

$categoryStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $getType));
$flagTbodyWritten = false;
$flagTbodyAllOrgasWritten = false;

$category = new TableCategory($gDb);

// Get data
while ($catRow = $categoryStatement->fetch()) {
    $category->clear();
    $category->setArray($catRow);

    $catId = (int) $category->getValue('cat_id');
    $categoryUuid = $category->getValue('cat_uuid');

    if ($category->getValue('cat_system') == 1 && $getType === 'USF') {
        // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
        $categoriesOverview->addTableBody('id', 'cat_'.$catId);
    } elseif ((int) $category->getValue('cat_org_id') === 0 && $getType === 'USF') {
        // Kategorien über alle Organisationen kommen immer zuerst
        if (!$flagTbodyAllOrgasWritten) {
            $flagTbodyAllOrgasWritten = true;
            $categoriesOverview->addTableBody('id', 'cat_all_orgas');
        }
    } else {
        if (!$flagTbodyWritten) {
            $flagTbodyWritten = true;
            $categoriesOverview->addTableBody('id', 'cat_list');
        }
    }

    $htmlMoveRow = '&nbsp;';
    if ($category->getValue('cat_system') == 0 || $getType !== 'USF') {
        $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:void(0)" onclick="moveTableRow(\''.TableCategory::MOVE_UP.'\', \'row_'.$categoryUuid.'\',
                            \''.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php', array('type' => $getType, 'mode' => 4, 'cat_uuid' => $categoryUuid, 'sequence' => TableCategory::MOVE_UP)) . '\',
                            \''.$gCurrentSession->getCsrfToken().'\')">'.
                            '<i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array($addButtonText)) . '"></i></a>
                        <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveTableRow(\''.TableCategory::MOVE_DOWN.'\', \'row_'.$categoryUuid.'\',
                            \''.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php', array('type' => $getType, 'mode' => 4, 'cat_uuid' => $categoryUuid, 'sequence' => TableCategory::MOVE_DOWN)) . '\',
                            \''.$gCurrentSession->getCsrfToken().'\')">'.
                            '<i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array($addButtonText)) . '"></i></a>';
    }

    $htmlDefaultCategory = '&nbsp;';
    if ($category->getValue('cat_default') == 1) {
        $htmlDefaultCategory = '<i class="fas fa-star" data-toggle="tooltip" title="' . $gL10n->get('SYS_DEFAULT_VAR', array($addButtonText)) . '"></i>';
    }

    // create list with all roles that could view the category
    if ($getType === 'ROL') {
        $htmlViewRolesNames = '';
    } else {
        $rightCategoryView = new RolesRights($gDb, 'category_view', $catId);
        $arrRolesIds = $rightCategoryView->getRolesIds();

        if (count($arrRolesIds) > 0) {
            $htmlViewRolesNames = implode(', ', array_merge($rightCategoryView->getRolesNames(), $adminRoles));
        } else {
            if ($gCurrentOrganization->countAllRecords() > 1) {
                if ((int) $category->getValue('cat_org_id') === 0) {
                    $htmlViewRolesNames = $gL10n->get('SYS_ALL_ORGANIZATIONS');
                } else {
                    $htmlViewRolesNames = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                }

                if ($getType !== 'USF') {
                    $htmlViewRolesNames .= ' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
                }
            } else {
                if ($getType === 'USF') {
                    $htmlViewRolesNames = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                } else {
                    $htmlViewRolesNames = $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
                }
            }
        }
    }

    // create list with all roles that could edit the category
    if ($getType === 'ROL') {
        $htmlEditRolesNames = '';
    } else {
        if ((int) $category->getValue('cat_org_id') === 0 && $gCurrentOrganization->isChildOrganization()) {
            $htmlEditRolesNames = $gL10n->get('SYS_CATEGORIES_ALL_MODULE_ADMINISTRATORS_MOTHER_ORGA');
        } else {
            $rightCategoryEdit  = new RolesRights($gDb, 'category_edit', $catId);
            $htmlEditRolesNames = implode(', ', array_merge($rightCategoryEdit->getRolesNames(), $adminRoles));
        }
    }

    if ($category->isEditable()) {
        $categoryAdministration = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('cat_uuid' => $categoryUuid, 'type' => $getType)).'">'.
                                    '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';

        if ($category->getValue('cat_system') == 1) {
            $categoryAdministration .= '<i class="fas fa-trash invisible"></i>';
        } else {
            $categoryAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'cat', 'element_id' => 'row_'. $categoryUuid, 'name' => $category->getValue('cat_name'), 'database_id' => $categoryUuid, 'database_id_2' => $getType)).'">'.
                                            '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }
    } else {
        $categoryAdministration = '<i class="fas fa-trash invisible"></i><i class="fas fa-trash invisible"></i>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('cat_uuid' => $categoryUuid, 'type' => $getType)).'">'. $category->getValue('cat_name'). '</a>',
        $htmlMoveRow,
        $htmlDefaultCategory,
        $htmlViewRolesNames,
        $htmlEditRolesNames,
        $categoryAdministration
    );
    $categoriesOverview->addRowByArray($columnValues, 'row_'. $categoryUuid);
}

$page->addHtml($categoriesOverview->show());
$page->show();
