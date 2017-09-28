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

$headline = $gL10n->get('SYS_MENU');

// create html page object
$page = new HtmlPage($headline);
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

$gNavigation->addStartUrl(CURRENT_URL, $headline);

// define link to create new menu
$menuMenu->addItem('admMenuItemNew', $g_root_path.'/adm_program/modules/menu/menu_new.php',
                         $gL10n->get('SYS_CREATE_VAR', $gL10n->get('SYS_MENU')), 'add.png');

// Create table object
$menuOverview = new HtmlTable('tbl_menues', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    'Order',
    'need to enable in menu <img class="admidio-icon-info" src="'.THEME_PATH.'/icons/star.png" alt="'.$gL10n->get('ORG_ACCESS_TO_MODULE', $headline).'" title="'.$gL10n->get('ORG_ACCESS_TO_MODULE', $headline).'" />',
    'Standart Menu',
    '&nbsp;'
);
$menuOverview->setColumnAlignByArray(array('left', 'left', 'center', 'left', 'right'));
$menuOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT *
  FROM '.TBL_MENU.'
  where men_parent_id is null
 ORDER BY men_order';
$main_men_statement = $gDb->query($sql);

while ($main_men = $main_men_statement->fetchObject())
{

    $sql = 'SELECT *
              FROM '.TBL_MENU.'
              where men_parent_id = ? -- $main_men->men_id
             ORDER BY men_parent_id DESC, men_order';
            $menuStatement = $gDb->queryPrepared($sql, array($main_men->men_id));

    if($menuStatement->rowCount() > 0)
    {

        $menuGroup = 0;

        // Get data
        while($menu_row = $menuStatement->fetchObject())
        {

            if($menuGroup != $menu_row->men_parent_id)
            {
                $block_id = 'admMenu_'.$menu_row->men_parent_id;

                $menuOverview->addTableBody();
                $menuOverview->addRow('', array('class' => 'admidio-group-heading'));
                $menuOverview->addColumn('<span id="caret_'.$block_id.'" class="caret"></span>'.$gL10n->get($main_men->men_translate_name),
                                  array('id' => 'group_'.$block_id, 'colspan' => '8'), 'td');
                $menuOverview->addTableBody('id', $block_id);

                $menuGroup = $menu_row->men_parent_id;
            }

            $naming = $gL10n->get($menu_row->men_translate_name);

            $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:moveMenu(\'up\', '.$menu_row->men_id.')"><img
                                    src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', $headline).'" title="'.$gL10n->get('CAT_MOVE_UP', $headline).'" /></a>
                               <a class="admidio-icon-link" href="javascript:moveMenu(\'down\', '.$menu_row->men_id.')"><img
                                    src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', $headline).'" title="'.$gL10n->get('CAT_MOVE_DOWN', $headline).'" /></a>';


            $htmlEnabledMenu = '&nbsp;';
            if($menu_row->men_need_enable == 1)
            {
                $htmlEnabledMenu = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('ORG_ACCESS_TO_MODULE', $headline).'" title="'.$gL10n->get('ORG_ACCESS_TO_MODULE', $headline).'" />';
            }

            $htmlStandartMenu = '&nbsp;';
            if($menu_row->men_standart == 1)
            {
                $htmlStandartMenu = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('ORG_ACCESS_TO_MODULE', $headline).'" title="'.$gL10n->get('ORG_ACCESS_TO_MODULE', $headline).'" />';
            }

            $menuAdministration = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/menu/menu_new.php?men_id='. $menu_row->men_id. '"><img
                                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

            //don't allow delete for standart menus
            if($menu_row->men_standart == 0)
            {
                $menuAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                            href="'.$g_root_path.'/adm_program/system/popup_message.php?type=men&amp;element_id=row_men_'.
                                            $menu_row->men_id.'&amp;name='.urlencode($naming).'&amp;database_id='.$menu_row->men_id.'"><img
                                               src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
            }

            // create array with all column values
            $columnValues = array(
                $naming,
                $htmlMoveRow,
                $htmlEnabledMenu,
                $htmlStandartMenu,
                $menuAdministration
            );
            $menuOverview->addRowByArray($columnValues, 'row_'. $menu_row->men_id);
        }
    }
}

$page->addHtml($menuOverview->show(false));
$page->show();
