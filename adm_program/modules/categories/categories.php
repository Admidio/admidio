<?php
/******************************************************************************
 * Overview and maintenance of all categories
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * type  : Type of categories that could be maintained
 *         ROL = Categories for roles
 *         LNK = Categories for weblinks
 *         USF = Categories for profile fields
 *         DAT = Calendars for events
 *         INF = Categories for Inventory
 * title : Parameter for the synonym of the categorie
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getType  = admFuncVariableIsValid($_GET, 'type', 'string',  array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'USF', 'DAT', 'INF')));
$getTitle = admFuncVariableIsValid($_GET, 'title', 'string', array('defaultValue' => $gL10n->get('SYS_CATEGORY')));

// Modus und Rechte pruefen
if($getType == 'ROL' && $gCurrentUser->manageRoles() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'LNK' && $gCurrentUser->editWeblinksRight() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'USF' && $gCurrentUser->editUsers() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'DAT' && $gCurrentUser->editDates() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set module headline
$headline = $gL10n->get('SYS_ADMINISTRATION_VAR', $getTitle);

$gNavigation->addUrl(CURRENT_URL, $headline);
unset($_SESSION['categories_request']);

// create html page object
$page = new HtmlPage();
$page->activateModal();

$page->addJavascript('
	function moveCategory(direction, catID) {
		var actRow = document.getElementById("row_" + catID);
		var childs = actRow.parentNode.childNodes;
		var prevNode    = null;
		var nextNode    = null;
		var actRowCount = 0;
		var actSequence = 0;
		var secondSequence = 0;

		// erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
		for(i=0;i < childs.length; i++) {
			if(childs[i].tagName == "TR") {
				actRowCount++;
				if(actSequence > 0 && nextNode == null) {
					nextNode = childs[i];
				}

				if(childs[i].id == "row_" + catID) {
					actSequence = actRowCount;
				}

				if(actSequence == 0) {
					prevNode = childs[i];
				}
			}
		}

		// entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
		if(direction == "up") {
			if(prevNode != null) {
				actRow.parentNode.insertBefore(actRow, prevNode);
				secondSequence = actSequence - 1;
			}
		}
		else {
			if(nextNode != null) {
				actRow.parentNode.insertBefore(nextNode, actRow);
				secondSequence = actSequence + 1;
			}
		}

		if(secondSequence > 0) {
			// Nun erst mal die neue Position von der gewaehlten Kategorie aktualisieren
			$.get(gRootPath + "/adm_program/modules/categories/categories_function.php?cat_id=" + catID + "&type='. $getType. '&mode=4&sequence=" + direction);
		}
	}');

$page->addHeadline($headline);

$htmlIconLoginUser = '&nbsp;';
if($getType != 'USF')
{
    $htmlIconLoginUser = '<img class="icon-information" src="'.THEME_PATH.'/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />';
}

// create module menu
$categoriesMenu = new HtmlNavbar('admMenuCategories', $headline, $page);

// show back link
$categoriesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new category
$categoriesMenu->addItem('admMenuItemNewCategory', $g_root_path.'/adm_program/modules/categories/categories_new.php?type='.$getType.'&amp;title='.$getTitle,
							$gL10n->get('SYS_CREATE_VAR', $getTitle), 'add.png');
$page->addHtml($categoriesMenu->show(false));

//Create table object
$categoriesOverview = new HtmlTable('tbl_categories', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    $htmlIconLoginUser,
    '<img class="icon-information" src="'.THEME_PATH.'/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" />',
    $gL10n->get('SYS_FEATURES')
);
$categoriesOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$categoriesOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT * FROM '. TBL_CATEGORIES. '
            WHERE (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                OR cat_org_id IS NULL )
            AND cat_type   = \''.$getType.'\'
            ORDER BY cat_sequence ASC ';
            
$categoryResult = $gDb->query($sql);
$flagTbodyWritten = false;
$flagTbodyAllOrgasWritten = false;

$category = new TableCategory($gDb);

// Get data
while($cat_row = $gDb->fetch_array($categoryResult))
{
    $category->clear();
	$category->setArray($cat_row);

    if($category->getValue('cat_system') == 1 && $getType == 'USF')
    {
        // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
        $categoriesOverview->addTableBody('id', 'cat_'.$category->getValue('cat_id'));
    }
    elseif($category->getValue('cat_org_id') == 0 && $getType == 'USF')
    {
        // Kategorien über alle Organisationen kommen immer zuerst
        if($flagTbodyAllOrgasWritten == false)
        {
            $flagTbodyAllOrgasWritten = true;
            $categoriesOverview->addTableBody('id', 'cat_all_orgas');
        }
    }
    else
    {
        if($flagTbodyWritten == false)
        {
            $flagTbodyWritten = true;
            $categoriesOverview->addTableBody('id', 'cat_list');
        }
    }
        
    $htmlMoveRow = '&nbsp;';
    if($category->getValue('cat_system') == 0 || $getType != 'USF')
    {
        $htmlMoveRow = '<a class="icon-link" href="javascript:moveCategory(\'up\', '.$category->getValue('cat_id').')"><img
                                src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', $getTitle).'" title="'.$gL10n->get('CAT_MOVE_UP', $getTitle).'" /></a>
                           <a class="icon-link" href="javascript:moveCategory(\'down\', '.$category->getValue('cat_id').')"><img
                                src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', $getTitle).'" title="'.$gL10n->get('CAT_MOVE_DOWN', $getTitle).'" /></a>';
    }
    
    $htmlHideCategory = '&nbsp;';
    if($category->getValue('cat_hidden') == 1)
    {
        $htmlHideCategory = '<img class="icon-information" src="'. THEME_PATH. '/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />';
    }
    
    $htmlDefaultCategory = '&nbsp;';
    if($category->getValue('cat_default') == 1)
    {
        $htmlDefaultCategory = '<img class="icon-information" src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" />';
    }

    $categoryAdministration = '<a class="icon-link" href="'.$g_root_path.'/adm_program/modules/categories/categories_new.php?cat_id='. $category->getValue('cat_id'). '&amp;type='.$getType.'&amp;title='.$getTitle.'"><img
                                    src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
    if($category->getValue('cat_system') == 1)
    {
        $categoryAdministration .= '<img class="icon-link" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
    }
    else
    {
        $categoryAdministration .= '<a class="icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.$g_root_path.'/adm_program/system/popup_message.php?type=cat&amp;element_id=row_'.
                                        $category->getValue('cat_id').'&amp;name='.urlencode($category->getValue('cat_name')).'&amp;database_id='.$category->getValue('cat_id').'&amp;database_id_2='.$getType.'"><img
                                           src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
    }
    
    // create array with all column values
    $columnValues = array(
        '<a href="'.$g_root_path.'/adm_program/modules/categories/categories_new.php?cat_id='. $category->getValue('cat_id'). '&amp;type='.$getType.'&amp;title='.$getTitle.'">'. $category->getValue('cat_name'). '</a>',
        $htmlMoveRow,
        $htmlHideCategory,
        $htmlDefaultCategory,
        $categoryAdministration);
    $categoriesOverview->addRowByArray($columnValues, 'row_'. $category->getValue('cat_id'));
}

$page->addHtml($categoriesOverview->show(false));
$page->show();

?>