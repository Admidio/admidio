<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all relationtypes
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

if (!$gSettingsManager->getBool('members_enable_user_relations'))
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
$relationTypesMenu = $page->getMenu();

// show back link
$relationTypesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'fa-arrow-circle-left');

// define link to create new category
$relationTypesMenu->addItem(
    'admMenuItemNewRelationType', ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_new.php',
    $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_USER_RELATION_TYPE'))), 'fa-plus-circle'
);

// Create table object
$relationTypesOverview = new HtmlTable('tbl_relationtypes', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('REL_USER_RELATION_TYPE_FORWARD'),
    $gL10n->get('REL_USER_RELATION_TYPE_BACKWARD'),
    '&nbsp;'
);
$relationTypesOverview->setColumnAlignByArray(array('left', 'left', 'right'));
$relationTypesOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT urt1.*, urt2.urt_name AS urt_name_inverse, urt2.urt_name_male AS urt_name_male_inverse, urt2.urt_name_female AS urt_name_female_inverse, urt2.urt_edit_user AS urt_edit_user_inverse
          FROM '.TBL_USER_RELATION_TYPES.' AS urt1
    LEFT OUTER JOIN '.TBL_USER_RELATION_TYPES.' AS urt2
            ON urt1.urt_id_inverse = urt2.urt_id
         WHERE urt1.urt_id <= urt1.urt_id_inverse
            OR urt1.urt_id_inverse IS NULL
      ORDER BY urt1.urt_name, urt2.urt_name';

$relationTypesStatement = $gDb->queryPrepared($sql);

$relationType1 = new TableUserRelationType($gDb);
$relationType2 = new TableUserRelationType($gDb);

// Get data
while($relRow = $relationTypesStatement->fetch())
{
    $editUserIcon = '';
    $editUserInverseIcon = '';

    $relationType1->clear();
    $relationType1->setArray($relRow);
    $relationType2->clear();
    $relRow2 = $relRow;
    $relRow2['urt_id'] = $relRow2['urt_id_inverse'];
    $relRow2['urt_name'] = $relRow2['urt_name_inverse'];
    $relRow2['urt_name_male'] = $relRow2['urt_name_male_inverse'];
    $relRow2['urt_name_female'] = $relRow2['urt_name_female_inverse'];
    $relationType2->setArray($relRow2);

    if((bool) $relRow['urt_edit_user'])
    {
        $editUserIcon = '<i class="fas fa-pen-square" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT_USER_IN_RELATION').'"></i>';
    }

    if((bool) $relRow['urt_edit_user_inverse'])
    {
        $editUserInverseIcon = '<i class="fas fa-pen-square" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT_USER_IN_RELATION').'"></i>';
    }

    $relationTypeAdministration = '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_new.php', array('urt_id' => (int) $relationType1->getValue('urt_id'))). '">'.
                                        '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
    $relationTypeAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'urt', 'element_id' => 'row_'. (int) $relationType1->getValue('urt_id'),
                                        'name' => $relationType1->getValue('urt_name').($relationType1->isUnidirectional() ? '' : ('/'.$relationType2->getValue('urt_name'))),
                                        'database_id' => (int) $relationType1->getValue('urt_id'))).'">'.
                                        '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';

    // create array with all column values
    $columnValues = array(
        $relationType1->getValue('urt_name') . $editUserIcon,
        $relationType2->getValue('urt_name') . $editUserInverseIcon,
        $relationTypeAdministration
    );
    $relationTypesOverview->addRowByArray($columnValues, 'row_'. (int) $relationType1->getValue('urt_id'));
}

$page->addHtml($relationTypesOverview->show());
$page->show();
