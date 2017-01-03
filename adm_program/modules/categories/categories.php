<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all categories
 *
 * @copyright 2004-2017 The Admidio Team
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
 *         INF = Categories for Inventory
 * title : Parameter for the synonym of the categorie
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getType  = admFuncVariableIsValid($_GET, 'type',  'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'DAT', 'INF', 'AWA')));
$getTitle = admFuncVariableIsValid($_GET, 'title', 'string');

// Modus und Rechte pruefen
if($getType === 'ROL' && !$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'LNK' && !$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'ANN' && !$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'USF' && !$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'DAT' && !$gCurrentUser->editDates())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'AWA' && !$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
if($getTitle === '')
{
    if($getType === 'ROL')
    {
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', $gL10n->get('SYS_ROLES'));
    }
    elseif($getType === 'LNK')
    {
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', $gL10n->get('LNK_WEBLINKS'));
    }
    elseif($getType === 'ANN')
    {
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', $gL10n->get('ANN_ANNOUNCEMENTS'));
    }
    elseif($getType === 'USF')
    {
        $headline = $gL10n->get('SYS_CATEGORIES_VAR', $gL10n->get('ORG_PROFILE_FIELDS'));
    }
    else
    {
        $headline = $gL10n->get('SYS_CATEGORIES');
    }

    $addButtonText = $gL10n->get('SYS_CATEGORY');
}
else
{
    $headline      = $getTitle;
    $addButtonText = $getTitle;
}

$gNavigation->addUrl(CURRENT_URL, $headline);
unset($_SESSION['categories_request']);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('
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
        if (direction === "up") {
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
            $.get(gRootPath + "/adm_program/modules/categories/categories_function.php?cat_id=" + catId + "&type='. $getType. '&mode=4&sequence=" + direction);
        }
    }');

$htmlIconLoginUser = '&nbsp;';
if($getType !== 'USF')
{
    $htmlIconLoginUser = '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $addButtonText).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $addButtonText).'" />';
}

// get module menu
$categoriesMenu = $page->getMenu();

// show back link
$categoriesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new category
$categoriesMenu->addItem('admMenuItemNewCategory', ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php?type='.$getType.'&amp;title='.$getTitle,
                         $gL10n->get('SYS_CREATE_VAR', $addButtonText), 'add.png');

// Create table object
$categoriesOverview = new HtmlTable('tbl_categories', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    $htmlIconLoginUser,
    '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $addButtonText).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $addButtonText).'" />',
    '&nbsp;'
);
$categoriesOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$categoriesOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT *
          FROM '.TBL_CATEGORIES.'
         WHERE (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
           AND cat_type   = \''.$getType.'\'
      ORDER BY cat_sequence ASC';

$categoryStatement = $gDb->query($sql);
$flagTbodyWritten = false;
$flagTbodyAllOrgasWritten = false;

$category = new TableCategory($gDb);

// Get data
while($cat_row = $categoryStatement->fetch())
{
    $category->clear();
    $category->setArray($cat_row);

    if($category->getValue('cat_system') == 1 && $getType === 'USF')
    {
        // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
        $categoriesOverview->addTableBody('id', 'cat_'.$category->getValue('cat_id'));
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
        $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\'up\', '.$category->getValue('cat_id').')"><img
                                src="'. THEME_URL. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', $addButtonText).'" title="'.$gL10n->get('CAT_MOVE_UP', $addButtonText).'" /></a>
                           <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\'down\', '.$category->getValue('cat_id').')"><img
                                src="'. THEME_URL. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', $addButtonText).'" title="'.$gL10n->get('CAT_MOVE_DOWN', $addButtonText).'" /></a>';
    }

    $htmlHideCategory = '&nbsp;';
    if($category->getValue('cat_hidden') == 1)
    {
        $htmlHideCategory = '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $addButtonText).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $addButtonText).'" />';
    }

    $htmlDefaultCategory = '&nbsp;';
    if($category->getValue('cat_default') == 1)
    {
        $htmlDefaultCategory = '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $addButtonText).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $addButtonText).'" />';
    }

    $categoryAdministration = '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php?cat_id='. $category->getValue('cat_id'). '&amp;type='.$getType.'&amp;title='.$getTitle.'"><img
                                    src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
    if($category->getValue('cat_system') == 1)
    {
        $categoryAdministration .= '<img class="admidio-icon-link" src="'. THEME_URL. '/icons/dummy.png" alt="dummy" />';
    }
    else
    {
        $categoryAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=cat&amp;element_id=row_'.
                                        $category->getValue('cat_id').'&amp;name='.urlencode($category->getValue('cat_name')).'&amp;database_id='.$category->getValue('cat_id').'&amp;database_id_2='.$getType.'"><img
                                           src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_new.php?cat_id='. $category->getValue('cat_id'). '&amp;type='.$getType.'&amp;title='.$getTitle.'">'. $category->getValue('cat_name'). '</a>',
        $htmlMoveRow,
        $htmlHideCategory,
        $htmlDefaultCategory,
        $categoryAdministration
    );
    $categoriesOverview->addRowByArray($columnValues, 'row_'. $category->getValue('cat_id'));
}

$page->addHtml($categoriesOverview->show());
$page->show();
