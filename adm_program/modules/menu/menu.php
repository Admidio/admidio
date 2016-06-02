<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all menus
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 ****************************************************************************/

require_once('../../system/common.php');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$getTitle = $gL10n->get('SYS_MENU');
$men_groups = array('1' => 'Administration', '2' => 'Modules', '3' => 'Plugins');

// create html page object
$page = new HtmlPage($gL10n->get('SYS_MENU'));
$page->enableModal();

$page->addJavascript('
    function moveMenu(direction, menID) {
        var actRow = document.getElementById("row_" + menID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (i=0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_" + menID) {
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
            $.get(gRootPath + "/adm_program/modules/menu/menu_function.php?men_id=" + menID + "&mode=3&sequence=" + direction);
        }
    }');

// get module menu
$menuMenu = $page->getMenu();

// define link to create new menu
$menuMenu->addItem('admMenuItemNewCategory', $g_root_path.'/adm_program/modules/menu/menu_new.php',
                         $gL10n->get('SYS_CREATE_VAR', $gL10n->get('SYS_MENU')), 'add.png');

// Create table object
$menuOverview = new HtmlTable('tbl_menues', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    'Order',
    '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/star.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />',
    '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/no_profile.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />',
    '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/user_key.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" />',
    '&nbsp;'
);
$menuOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'right'));
$menuOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT *
          FROM '.TBL_MENU.'
        ORDER BY men_group, men_order';

$menuStatement = $gDb->query($sql);

$menuGroup = 0;

$menu = new TableMenu($gDb);

// Get data
while($menu_row = $menuStatement->fetch())
{
    $menu->clear();
    $menu->setArray($menu_row);
    
    if($menuGroup != $menu->getValue('men_group'))
    {
        $block_id = 'admMenu_'.$menu->getValue('men_group');

        $menuOverview->addTableBody();
        $menuOverview->addRow('', array('class' => 'admidio-group-heading'));
        $menuOverview->addColumn('<span id="caret_'.$block_id.'" class="caret"></span>'.$men_groups[$menu->getValue('men_group')],
                          array('id' => 'group_'.$block_id, 'colspan' => '8'), 'td');
        $menuOverview->addTableBody('id', $block_id);

        $menuGroup = $menu->getValue('men_group');
    }

    $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:moveMenu(\'up\', '.$menu->getValue('men_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', $getTitle).'" title="'.$gL10n->get('CAT_MOVE_UP', $getTitle).'" /></a>
                       <a class="admidio-icon-link" href="javascript:moveMenu(\'down\', '.$menu->getValue('men_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', $getTitle).'" title="'.$gL10n->get('CAT_MOVE_DOWN', $getTitle).'" /></a>';


    $htmlEnabledMenu = '&nbsp;';
    if($menu->getValue('men_need_enable') == 1)
    {
        $htmlEnabledMenu = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" />';
    }
    
    $htmlNeedLogin = '&nbsp;';
    if($menu->getValue('men_need_login') == 1)
    {
        $htmlNeedLogin = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/no_profile.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />';
    }
    
    $htmlNeedAdmin = '&nbsp;';
    if($menu->getValue('men_need_login') == 1)
    {
        $htmlNeedAdmin = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />';
    }

    $menuAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/menu/menu_new.php?men_id='. $menu->getValue('men_id'). '&amp;title='.$getTitle.'"><img
                                    src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

    $menuAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.$g_root_path.'/adm_program/system/popup_message.php?type=men&amp;element_id=row_'.
                                    $menu->getValue('men_id').'&amp;name='.urlencode($menu->getValue('men_name')).'&amp;database_id='.$menu->getValue('men_id').'"><img
                                       src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';

    // create array with all column values
    $columnValues = array(
        $gL10n->get($menu->getValue('men_translat_name')),
        $htmlMoveRow,
        $htmlEnabledMenu,
        $htmlNeedLogin,
        $htmlNeedAdmin,
        $menuAdministration
    );
    $menuOverview->addRowByArray($columnValues, 'row_'. $menu->getValue('men_id'));
}

$page->addHtml($menuOverview->show(false));
$page->show();
