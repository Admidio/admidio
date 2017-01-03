<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all relationtypes
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

if ($gPreferences['members_enable_user_relations'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
$headline = $gL10n->get('SYS_USER_RELATION_TYPES');
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

// get module menu
$relationtypesMenu = $page->getMenu();

// show back link
$relationtypesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new category
$relationtypesMenu->addItem('admMenuItemNewRelationType', ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_new.php',
                         $gL10n->get('SYS_CREATE_VAR', $gL10n->get('SYS_USER_RELATION_TYPE')), 'add.png');

// Create table object
$relationtypesOverview = new HtmlTable('tbl_relationtypes', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('REL_USER_RELATION_TYPE_FORWARD'),
    $gL10n->get('REL_USER_RELATION_TYPE_BACKWARD'),
    '&nbsp;'
);
$relationtypesOverview->setColumnAlignByArray(array('left', 'left', 'right'));
$relationtypesOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT urt1.*, urt2.urt_name AS urt_name_inverse, urt2.urt_name_male AS urt_name_male_inverse, urt2.urt_name_female AS urt_name_female_inverse
          FROM '.TBL_USER_RELATION_TYPES.' AS urt1
    LEFT OUTER JOIN '.TBL_USER_RELATION_TYPES.' AS urt2
            ON urt1.urt_id_inverse=urt2.urt_id
         WHERE urt1.urt_id <= urt1.urt_id_inverse OR urt1.urt_id_inverse IS NULL
      ORDER BY urt1.urt_name, urt2.urt_name';

$relationtypesStatement = $gDb->query($sql);

$relationtype1 = new TableUserRelationType($gDb);
$relationtype2 = new TableUserRelationType($gDb);

// Get data
while($relRow = $relationtypesStatement->fetch())
{
    $relationtype1->clear();
    $relationtype1->setArray($relRow);
    $relationtype2->clear();
    $relRow2 = $relRow;
    $relRow2['urt_id'] = $relRow2['urt_id_inverse'];
    $relRow2['urt_name'] = $relRow2['urt_name_inverse'];
    $relRow2['urt_name_male'] = $relRow2['urt_name_male_inverse'];
    $relRow2['urt_name_female'] = $relRow2['urt_name_female_inverse'];
    $relationtype2->setArray($relRow2);

    $relationtypeAdministration = '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_new.php?urt_id='. $relationtype1->getValue('urt_id'). '"><img
                                    src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
    $relationtypeAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=urt&amp;element_id=row_'.
                                        $relationtype1->getValue('urt_id').'&amp;name='.urlencode($relationtype1->getValue('urt_name').($relationtype1->isUnidirectional() ? '' : ('/'.$relationtype2->getValue('urt_name')))).'&amp;database_id='.$relationtype1->getValue('urt_id').'"><img
                                           src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';

    // create array with all column values
    $columnValues = array(
        $relationtype1->getValue('urt_name'),
        $relationtype2->getValue('urt_name'),
        $relationtypeAdministration
    );
    $relationtypesOverview->addRowByArray($columnValues, 'row_'. $relationtype1->getValue('urt_id'));
}

$page->addHtml($relationtypesOverview->show());
$page->show();
