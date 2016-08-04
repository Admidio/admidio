<?php
/**
 ***********************************************************************************************
 * Create and edit relation types
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * urt_id : relation type id that should be edited
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUrtId = admFuncVariableIsValid($_GET, 'urt_id', 'int');

if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$headline = $gL10n->get('SYS_USER_RELATION_TYPES');
$gNavigation->addUrl(CURRENT_URL, $headline);

$relationtype1 = new TableUserRelationType($gDb);
$relationtype2 = new TableUserRelationType($gDb);

if($getUrtId > 0)
{
    $relationtype1->readDataById($getUrtId);
    $relationtype2->readDataById($relationtype1->getValue('urt_id_inverse'));
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$relationtypeEditMenu = $page->getMenu();
$relationtypeEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('relationtype_edit_form',
                     $g_root_path.'/adm_program/modules/userrelations/relationtypes_function.php?urt_id='.$getUrtId.'&amp;mode=1', $page);
$form->addInput('urt_name_singular', $gL10n->get('SYS_USER_RELATION_TYPE_FORWARD_SINGULAR'), $relationtype1->getValue('urt_name_singular'),
        array('maxLength' => 100, 'property' => FIELD_REQUIRED));
$form->addInput('urt_name_plural', $gL10n->get('SYS_USER_RELATION_TYPE_FORWARD_PLURAL'), $relationtype1->getValue('urt_name_plural'),
        array('maxLength' => 100));
$form->addInput('urt_name_singular_inverse', $gL10n->get('SYS_USER_RELATION_TYPE_BACKWARD_SINGULAR'), $relationtype2->getValue('urt_name_singular'),
        array('maxLength' => 100, 'property' => FIELD_REQUIRED));
$form->addInput('urt_name_plural_inverse', $gL10n->get('SYS_USER_RELATION_TYPE_BACKWARD_PLURAL'), $relationtype2->getValue('urt_name_plural'),
        array('maxLength' => 100));

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($relationtype1->getValue('urt_usr_id_create'),
               $relationtype1->getValue('urt_timestamp_create'), $relationtype1->getValue('urt_usr_id_change'),
               $relationtype1->getValue('urt_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
