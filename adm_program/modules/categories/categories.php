<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all categories
 *
 * @copyright 2004-2018 The Admidio Team
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
 * title : Parameter for the synonym of the categorie
 *
 ****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getType  = admFuncVariableIsValid($_GET, 'type',  'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'DAT', 'AWA')));
$getTitle = admFuncVariableIsValid($_GET, 'title', 'string');

// Modus und Rechte pruefen
if (($getType === 'ROL' && !$gCurrentUser->manageRoles())
||  ($getType === 'LNK' && !$gCurrentUser->editWeblinksRight())
||  ($getType === 'ANN' && !$gCurrentUser->editAnnouncements())
||  ($getType === 'USF' && !$gCurrentUser->editUsers())
||  ($getType === 'DAT' && !$gCurrentUser->editDates())
||  ($getType === 'AWA' && !$gCurrentUser->editUsers()))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
$headline         = $gL10n->get('SYS_CATEGORIES');
$addButtonText    = $gL10n->get('SYS_CATEGORY');
$visibleHeadline  = $gL10n->get('SYS_VISIBLE_FOR');
$editableHeadline = '';

switch ($getType)
{
    case 'ROL':
        $rolesRightsColumn = 'rol_assign_roles';
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', array($gL10n->get('SYS_ROLES')));
        $visibleHeadline = '';
        break;

    case 'ANN':
        $rolesRightsColumn = 'rol_announcements';
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', array($gL10n->get('ANN_ANNOUNCEMENTS')));
        $editableHeadline = $gL10n->get('ANN_EDIT_ANNOUNCEMENTS');
        break;

    case 'DAT':
        $rolesRightsColumn = 'rol_dates';
        $editableHeadline = $gL10n->get('DAT_EDIT_EVENTS');
        break;

    case 'LNK':
        $rolesRightsColumn = 'rol_weblinks';
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', array($gL10n->get('LNK_WEBLINKS')));
        $editableHeadline = $gL10n->get('LNK_EDIT_WEBLINKS');
        break;

    case 'USF':
        $rolesRightsColumn = 'rol_edit_user';
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', array($gL10n->get('ORG_PROFILE_FIELDS')));
        $editableHeadline = $gL10n->get('PRO_EDIT_PROFILE_FIELDS');
        break;

    case 'AWA':
        $rolesRightsColumn = 'rol_edit_user';
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', 'Awards');
        break;

    default:
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
}

if($getTitle !== '')
{
    $headline      = $getTitle;
    $addButtonText = $getTitle;
}

// read all administrator roles

$sqlAdminRoles = 'SELECT rol_name
                    FROM '.TBL_ROLES.'
              INNER JOIN '.TBL_CATEGORIES.'
                      ON cat_id = rol_cat_id
                   WHERE rol_valid  = 1
                     AND '. $rolesRightsColumn .' = 1
                     AND cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                ORDER BY cat_sequence, rol_name';
$statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrganization->getValue('org_id')));

$adminRoles = array();
while($roleName = $statementAdminRoles->fetchColumn())
{
    $adminRoles[] = $roleName;
}

$gNavigation->addUrl(CURRENT_URL, $headline);
unset($_SESSION['categories_request']);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('
    /**
     * @param {string} direction
     * @param {int}    catId
     */
    function moveCategory(direction, catId) {
        var actRow = document.getElementById("row_" + catId);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (var i = 0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_" + catId) {
                    actSequence = actRowCount;
                }

                if (actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if (direction === "UP") {
            if (prevNode !== null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if (nextNode !== null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if (secondSequence > 0) {
            // Nun erst mal die neue Position von der gewaehlten Kategorie aktualisieren
            $.get("' . safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php', array('type' => $getType, 'mode' => 4)) . '&cat_id=" + catId + "&sequence=" + direction);
        }
    }
');

// get module menu
$categoriesMenu = $page->getMenu();

// show back link
$categoriesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new category
$categoriesMenu->addItem(
    'admMenuItemNewCategory', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('type' => $getType, 'title' => $getTitle)),
    $gL10n->get('SYS_CREATE_VAR', array($addButtonText)), 'add.png'
);

// Create table object
$categoriesOverview = new HtmlTable('tbl_categories', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', array($addButtonText)).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', array($addButtonText)).'" />',
    $visibleHeadline,
    $editableHeadline,
    '&nbsp;'
);
$categoriesOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'right'));
$categoriesOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT *
          FROM '.TBL_CATEGORIES.'
         WHERE (  cat_org_id  = ? -- $gCurrentOrganization->getValue(\'org_id\')
               OR cat_org_id IS NULL )
           AND cat_type = ? -- $getType
      ORDER BY cat_sequence ASC';

$categoryStatement = $gDb->queryPrepared($sql, array($gCurrentOrganization->getValue('org_id'), $getType));
$flagTbodyWritten = false;
$flagTbodyAllOrgasWritten = false;

$category = new TableCategory($gDb);

// Get data
while($catRow = $categoryStatement->fetch())
{
    $category->clear();
    $category->setArray($catRow);

    $catId = (int) $category->getValue('cat_id');

    if($category->getValue('cat_system') == 1 && $getType === 'USF')
    {
        // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
        $categoriesOverview->addTableBody('id', 'cat_'.$catId);
    }
    elseif((int) $category->getValue('cat_org_id') === 0 && $getType === 'USF')
    {
        // Kategorien über alle Organisationen kommen immer zuerst
        if(!$flagTbodyAllOrgasWritten)
        {
            $flagTbodyAllOrgasWritten = true;
            $categoriesOverview->addTableBody('id', 'cat_all_orgas');
        }
    }
    else
    {
        if(!$flagTbodyWritten)
        {
            $flagTbodyWritten = true;
            $categoriesOverview->addTableBody('id', 'cat_list');
        }
    }

    $htmlMoveRow = '&nbsp;';
    if($category->getValue('cat_system') == 0 || $getType !== 'USF')
    {
        $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\''.TableCategory::MOVE_UP.'\', '.$catId.')"><img
                                src="'. THEME_URL. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', array($addButtonText)).'" title="'.$gL10n->get('CAT_MOVE_UP', array($addButtonText)).'" /></a>
                           <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\''.TableCategory::MOVE_DOWN.'\', '.$catId.')"><img
                                src="'. THEME_URL. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', array($addButtonText)).'" title="'.$gL10n->get('CAT_MOVE_DOWN', array($addButtonText)).'" /></a>';
    }

    $htmlDefaultCategory = '&nbsp;';
    if($category->getValue('cat_default') == 1)
    {
        $htmlDefaultCategory = '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', array($addButtonText)).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', array($addButtonText)).'" />';
    }

    // create list with all roles that could view the category
    if($getType === 'ROL')
    {
        $htmlViewRolesNames = '';
    }
    else
    {
        $rightCategoryView = new RolesRights($gDb, 'category_view', $catId);
        $arrRolesIds = $rightCategoryView->getRolesIds();

        if(count($arrRolesIds) > 0)
        {
            $htmlViewRolesNames = implode(', ', array_merge($rightCategoryView->getRolesNames(), $adminRoles));
        }
        else
        {
            if($gCurrentOrganization->countAllRecords() > 1)
            {
                if((int) $category->getValue('cat_org_id') === 0)
                {
                    $htmlViewRolesNames = $gL10n->get('SYS_ALL_ORGANIZATIONS');
                }
                else
                {
                    $htmlViewRolesNames = $gL10n->get('CAT_ALL_THIS_ORGANIZATION');
                }

                if($getType !== 'USF')
                {
                    $htmlViewRolesNames .= ' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
                }
            }
            else
            {
                if($getType === 'USF')
                {
                    $htmlViewRolesNames = $gL10n->get('CAT_ALL_THIS_ORGANIZATION');
                }
                else
                {
                    $htmlViewRolesNames = $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
                }
            }
        }
    }

    // create list with all roles that could edit the category
    if($getType === 'ROL')
    {
        $htmlEditRolesNames = '';
    }
    else
    {

        if((int) $category->getValue('cat_org_id') === 0 && $gCurrentOrganization->isChildOrganization())
        {
            $htmlEditRolesNames = $gL10n->get('CAT_ALL_MODULE_ADMINISTRATORS_MOTHER_ORGA');
        }
        else
        {
            $rightCategoryEdit  = new RolesRights($gDb, 'category_edit', $catId);
            $htmlEditRolesNames = implode(', ', array_merge($rightCategoryEdit->getRolesNames(), $adminRoles));
        }
    }

    if($category->isEditable())
    {
        $categoryAdministration = '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('cat_id' => $catId, 'type' => $getType, 'title' => $getTitle)).'"><img
                                        src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

        if($category->getValue('cat_system') == 1)
        {
            $categoryAdministration .= '<img class="admidio-icon-link" src="'. THEME_URL. '/icons/dummy.png" alt="dummy" />';
        }
        else
        {
            $categoryAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                            href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'cat', 'element_id' => 'row_'. $category->getValue('cat_id'), 'name' => $category->getValue('cat_name'), 'database_id' => $catId, 'database_id_2' => $getType)).'"><img
                                               src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
        }
    }
    else
    {
        $categoryAdministration = '<img class="admidio-icon-link" src="'. THEME_URL. '/icons/dummy.png" alt="dummy" /><img class="admidio-icon-link" src="'. THEME_URL. '/icons/dummy.png" alt="dummy" />';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php', array('cat_id' => $catId, 'type' => $getType, 'title' => $getTitle)).'">'. $category->getValue('cat_name'). '</a>',
        $htmlMoveRow,
        $htmlDefaultCategory,
        $htmlViewRolesNames,
        $htmlEditRolesNames,
        $categoryAdministration
    );
    $categoriesOverview->addRowByArray($columnValues, 'row_'. $catId);
}

$page->addHtml($categoriesOverview->show());
$page->show();
