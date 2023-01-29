<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all menus
 *
 * @copyright 2004-2023 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// check rights to use this module
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$headline = $gL10n->get('SYS_MENU');

// create html page object
$page = new HtmlPage('admidio-menu', $headline);

$gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-stream');

$page->addJavascript(
    '
    $(".admidio-open-close-caret").click(function() {
        showHideBlock($(this).attr("id"));
    });',
    true
);

// define link to create new menu
$page->addPageFunctionsMenuItem(
    'menu_item_menu_new',
    $gL10n->get('SYS_CREATE_ENTRY'),
    ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_new.php',
    'fa-plus-circle'
);

// Create table object
$menuOverview = new HtmlTable('tbl_menues', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    $gL10n->get('SYS_URL'),
    '<i class="fas fa-star" data-toggle="tooltip" title="' . $gL10n->get('SYS_DEFAULT_VAR', array($gL10n->get('SYS_MENU_ITEM'))) . '"></i>',
    '&nbsp;'
);
$menuOverview->setColumnAlignByArray(array('left', 'left', 'left', 'center', 'right'));
$menuOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT men_id, men_uuid, men_name
          FROM '.TBL_MENU.'
         WHERE men_men_id_parent IS NULL
      ORDER BY men_order';
$mainMenStatement = $gDb->queryPrepared($sql);

while ($mainMen = $mainMenStatement->fetch()) {
    $sql = 'SELECT men_id, men_uuid, men_men_id_parent, men_name, men_description, men_standard, men_url
              FROM '.TBL_MENU.'
             WHERE men_men_id_parent = ? -- $mainMen[\'men_id\']
          ORDER BY men_men_id_parent DESC, men_order';
    $menuStatement = $gDb->queryPrepared($sql, array($mainMen['men_id']));

    $menuGroup = 0;

    // Get data
    while ($menuRow = $menuStatement->fetch()) {
        $menIdParent = (int) $menuRow['men_men_id_parent'];

        if ($menuGroup !== $menIdParent) {
            $blockId = 'admMenu_'.$menIdParent;

            $menuOverview->addTableBody();
            $menuOverview->addRow('', array('class' => 'admidio-group-heading'));
            $menuOverview->addColumn(
                '<a id="caret_'.$blockId.'" class="admidio-icon-link admidio-open-close-caret"><i class="fas fa-caret-down"></i></a>'.Language::translateIfTranslationStrId((string) $mainMen['men_name']),
                array('id' => 'group_'.$blockId, 'colspan' => '8')
            );
            $menuOverview->addTableBody('id', $blockId);

            $menuGroup = $menIdParent;
        }

        $menuName = Language::translateIfTranslationStrId((string) $menuRow['men_name']);
        $menuNameDesc = Language::translateIfTranslationStrId((string) $menuRow['men_description']);

        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $menuRow['men_url']) === 0) {
            $menuLink = ADMIDIO_URL . $menuRow['men_url'];
        } else {
            $menuLink = $menuRow['men_url'];
        }

        $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:moveTableRow(\'UP\', \'row_men_'.$menuRow['men_uuid'].'\',
                            \''.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('mode' => 3, 'menu_uuid' => $menuRow['men_uuid'], 'sequence' => 'UP')) . '\',
                            \''.$gCurrentSession->getCsrfToken().'\')">'.
                            '<i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array($headline)) . '"></i></a>
                        <a class="admidio-icon-link" href="javascript:moveTableRow(\'DOWN\', \'row_men_'.$menuRow['men_uuid'].'\',
                            \''.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('mode' => 3, 'menu_uuid' => $menuRow['men_uuid'], 'sequence' => 'DOWN')) . '\',
                            \''.$gCurrentSession->getCsrfToken().'\')">'.
                            '<i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array($headline)) . '"></i></a>';

        $htmlStandardMenu = '&nbsp;';
        if ($menuRow['men_standard']) {
            $htmlStandardMenu = '<i class="fas fa-star" data-toggle="tooltip" title="' . $gL10n->get('SYS_DEFAULT_VAR', array($gL10n->get('SYS_MENU_ITEM'))) . '"></i>';
        }

        $menuAdministration = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_new.php', array('menu_uuid' => $menuRow['men_uuid'])). '">'.
                                '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i>';

        // don't allow delete for standard menus
        if (!$menuRow['men_standard']) {
            $menuAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'men', 'element_id' => 'row_men_'.
                                        $menuRow['men_uuid'], 'name' => $menuName, 'database_id' => $menuRow['men_uuid'])).'">'.
                                        '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }

        // create array with all column values
        $columnValues = array(
            '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/menu/menu_new.php', array('menu_uuid' => $menuRow['men_uuid'])). '" title="'.$menuNameDesc.'">'.$menuName.'</a>',
            $htmlMoveRow,
            '<a href="'.$menuLink. '" title="'.$menuNameDesc.'">'. $menuRow['men_url']. '</a>',
            $htmlStandardMenu,
            $menuAdministration
        );
        $menuOverview->addRowByArray($columnValues, 'row_men_'. $menuRow['men_uuid']);
    }
}

$page->addHtml($menuOverview->show());
$page->show();
