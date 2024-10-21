<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all categories
 *
 * @copyright The Admidio Team
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
 *         EVT = Calendars for events
 *
 ****************************************************************************/
use Admidio\Exception;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getType = admFuncVariableIsValid($_GET, 'type', 'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'EVT', 'AWA')));

    // check rights of the type
    if (($getType === 'ROL' && !$gCurrentUser->manageRoles())
        || ($getType === 'LNK' && !$gCurrentUser->editWeblinksRight())
        || ($getType === 'ANN' && !$gCurrentUser->editAnnouncements())
        || ($getType === 'USF' && !$gCurrentUser->editUsers())
        || ($getType === 'EVT' && !$gCurrentUser->editEvents())
        || ($getType === 'AWA' && !$gCurrentUser->editUsers())) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // set module headline
    $headline = $gL10n->get('SYS_CATEGORIES');
    $addButtonText = $gL10n->get('SYS_CREATE_CATEGORY');
    $visibleHeadline = $gL10n->get('SYS_VISIBLE_FOR');
    $navigationHeadline = $gL10n->get('SYS_CATEGORIES');
    $deleteMessage = 'SYS_WANT_DELETE_CATEGORY';
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

        case 'EVT':
            $component = 'EVENTS';
            $rolesRightsColumn = 'rol_events';
            $headline = $gL10n->get('SYS_EVENTS') . ' - ' . $gL10n->get('SYS_CALENDARS');
            $navigationHeadline = $gL10n->get('SYS_CALENDARS');
            $editableHeadline = $gL10n->get('SYS_EDIT_EVENTS');
            $addButtonText = $gL10n->get('SYS_CREATE_CALENDAR');
            $deleteMessage = 'SYS_DELETE_ENTRY';
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
            $editableHeadline = $gL10n->get('SYS_EDIT_PROFILE_FIELDS_PREF');
            break;

        case 'AWA':
            $component = 'CORE';
            $rolesRightsColumn = 'rol_edit_user';
            $headline = $gL10n->get('Awards') . ' - ' . $gL10n->get('SYS_CATEGORIES');
            break;

        default:
            throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // check if the current user has the right to
    if (!Component::isAdministrable($component)) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // read all administrator roles

    $sqlAdminRoles = 'SELECT rol_name
                    FROM ' . TBL_ROLES . '
              INNER JOIN ' . TBL_CATEGORIES . '
                      ON cat_id = rol_cat_id
                   WHERE rol_valid  = true
                     AND ' . $rolesRightsColumn . ' = true
                     AND cat_org_id = ? -- $gCurrentOrgId
                ORDER BY cat_sequence, rol_name';
    $statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrgId));

    $adminRoles = array();
    while ($roleName = $statementAdminRoles->fetchColumn()) {
        $adminRoles[] = $roleName;
    }

    $gNavigation->addUrl(CURRENT_URL, $navigationHeadline);

    // create html page object
    $page = new HtmlPage('admidio-categories', $headline);

    $page->addJavascript('
    $(".admidio-category-move").click(function() {
        moveTableRow(
            $(this).data("direction"),
            $(this).data("uuid"),
            "' . ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php",
            "' . $gCurrentSession->getCsrfToken() . '"
        );
    });
    ', true
    );

    // define link to create new category
    $page->addPageFunctionsMenuItem(
        'menu_item_categories_add',
        $addButtonText,
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_new.php', array('type' => $getType)),
        'bi-plus-circle-fill'
    );

    // Create table object
    $categoriesOverview = new HtmlTable('tbl_categories', $page, true);

    // create array with all column heading values
    $columnHeading = array(
        $gL10n->get('SYS_TITLE'),
        '&nbsp;',
        '<i class="bi bi-star-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DEFAULT_VAR', array($addButtonText)) . '"></i>',
        $visibleHeadline,
        $editableHeadline,
        '&nbsp;'
    );
    $categoriesOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'right'));
    $categoriesOverview->addRowHeadingByArray($columnHeading);

    $sql = 'SELECT *
          FROM ' . TBL_CATEGORIES . '
         WHERE (  cat_org_id  = ? -- $gCurrentOrgId
               OR cat_org_id IS NULL )
           AND cat_type = ? -- $getType
      ORDER BY cat_sequence';

    $categoryStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $getType));
    $flagTbodyWritten = false;
    $flagTbodyAllOrgasWritten = false;

    $category = new TableCategory($gDb);

    // Get data
    while ($catRow = $categoryStatement->fetch()) {
        $category->clear();
        $category->setArray($catRow);

        $catId = (int)$category->getValue('cat_id');
        $categoryUuid = $category->getValue('cat_uuid');

        if ($category->getValue('cat_system') == 1 && $getType === 'USF') {
            // Since the master data category may not be moved in USF, you have to fiddle around a bit here
            $categoriesOverview->addTableBody('id', 'cat_' . $catId);
        } elseif ((int)$category->getValue('cat_org_id') === 0 && $getType === 'USF') {
            // Categories across all organizations always come first
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
            $htmlMoveRow = '<a class="admidio-icon-link admidio-category-move" href="javascript:void(0)" data-uuid="' . $categoryUuid . '" data-direction="' . TableCategory::MOVE_UP . '">' .
                '<i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array($addButtonText)) . '"></i></a>
                        <a class="admidio-icon-link admidio-category-move" href="javascript:void(0)" data-uuid="' . $categoryUuid . '" data-direction="' . TableCategory::MOVE_DOWN . '">' .
                '<i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array($addButtonText)) . '"></i></a>';
        }

        $htmlDefaultCategory = '&nbsp;';
        if ($category->getValue('cat_default') == 1) {
            $htmlDefaultCategory = '<i class="bi bi-star-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DEFAULT_VAR', array($addButtonText)) . '"></i>';
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
                    if ((int)$category->getValue('cat_org_id') === 0) {
                        $htmlViewRolesNames = $gL10n->get('SYS_ALL_ORGANIZATIONS');
                    } else {
                        $htmlViewRolesNames = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                    }

                    if ($getType !== 'USF') {
                        $htmlViewRolesNames .= ' (' . $gL10n->get('SYS_ALSO_VISITORS') . ')';
                    }
                } else {
                    if ($getType === 'USF') {
                        $htmlViewRolesNames = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                    } else {
                        $htmlViewRolesNames = $gL10n->get('SYS_ALL') . ' (' . $gL10n->get('SYS_ALSO_VISITORS') . ')';
                    }
                }
            }
        }

        // create list with all roles that could edit the category
        if ($getType === 'ROL') {
            $htmlEditRolesNames = '';
        } else {
            if ((int)$category->getValue('cat_org_id') === 0 && $gCurrentOrganization->isChildOrganization()) {
                $htmlEditRolesNames = $gL10n->get('SYS_CATEGORIES_ALL_MODULE_ADMINISTRATORS_MOTHER_ORGA');
            } else {
                $rightCategoryEdit = new RolesRights($gDb, 'category_edit', $catId);
                $htmlEditRolesNames = implode(', ', array_merge($rightCategoryEdit->getRolesNames(), $adminRoles));
            }
        }

        if ($category->isEditable()) {
            $categoryAdministration = '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_new.php', array('cat_uuid' => $categoryUuid, 'type' => $getType)) . '">' .
                '<i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i></a>';

            if ($category->getValue('cat_system') == 1) {
                $categoryAdministration .= '<i class="bi bi-trash invisible"></i>';
            } else {
                $categoryAdministration .= '
                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                        data-message="' . $gL10n->get($deleteMessage, array($category->getValue('cat_name', 'database'))) . '"
                        data-href="callUrlHideElement(\'row_' . $categoryUuid . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php', array('mode' => 'delete', 'uuid' => $categoryUuid)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                        <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i></a>';
            }
        } else {
            $categoryAdministration = '<i class="bi bi-trash invisible"></i><i class="bi bi-trash invisible"></i>';
        }

        // create array with all column values
        $columnValues = array(
            '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_new.php', array('cat_uuid' => $categoryUuid, 'type' => $getType)) . '">' . $category->getValue('cat_name') . '</a>',
            $htmlMoveRow,
            $htmlDefaultCategory,
            $htmlViewRolesNames,
            $htmlEditRolesNames,
            $categoryAdministration
        );
        $categoriesOverview->addRowByArray($columnValues, 'row_' . $categoryUuid);
    }

    $page->addHtml($categoriesOverview->show());
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
