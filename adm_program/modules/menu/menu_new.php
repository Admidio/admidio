<?php
/**
 ***********************************************************************************************
 * Create and edit categories
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * cat_id: Id of the category that should be edited
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
$getMenId = admFuncVariableIsValid($_GET, 'cat_id', 'int');
$getType  = admFuncVariableIsValid($_GET, 'type',   'string', array('requireValue' => false, 'validValues' => array('ROL', 'LNK', 'USF', 'DAT', 'INF', 'AWA')));

$men_groups = array('1' => 'Administration', '2' => 'Modules', '3' => 'Plugins');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set module headline
$headline = $gL10n->get('SYS_MENU');

// set headline of the script
if($getMenId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', $headline);
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', $headline);
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// UserField-objekt anlegen
$menu = new TableMenu($gDb);

if($getMenId > 0)
{
    $category->readDataById($getCatId);

    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue('cat_org_id') > 0
    && $category->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['categories_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $category->setArray($_SESSION['categories_request']);
    if(!isset($_SESSION['categories_request']['show_in_several_organizations']))
    {
        $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
    }
    unset($_SESSION['categories_request']);
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$categoryCreateMenu = $page->getMenu();
$categoryCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('categories_edit_form', $g_root_path.'/adm_program/modules/menu/menu_function.php?men_id='.$getMenId.'&amp;mode=1', $page);

// systemcategories should not be renamed
$fieldPropertyCatName = FIELD_REQUIRED;
if($menu->getValue('cat_system') == 1)
{
    $fieldPropertyCatName = FIELD_DISABLED;
}

$form->addSelectBox('men-group', 'Menu Group',  $men_groups,
                array('property' => FIELD_REQUIRED, 'defaultValue' => '3', 'firstEntry' => ''));

$form->addCheckbox('men_display_right', 'Display in right main menu', $menu->getValue('men_display_right'), array('property' => FIELD_REQUIRED));

$form->addCheckbox('men_display_index', 'Display in center menu', $menu->getValue('men_display_index'), array('property' => FIELD_REQUIRED));

$form->addCheckbox('men_display_boot', 'Display in bootstrap menu', $menu->getValue('men_display_boot'), array('property' => FIELD_REQUIRED));

$form->addInput('cat_name', $gL10n->get('SYS_NAME'), $menu->getValue('cat_name', 'database'),
                array('maxLength' => 100, 'property' => $fieldPropertyCatName));


$form->addCheckbox('cat_hidden', $gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_MENU')), $menu->getValue('cat_hidden'),
                   array('icon' => 'user_key.png'));

$form->addCheckbox('cat_default', $gL10n->get('CAT_DEFAULT_VAR', $gL10n->get('SYS_MENU')), $menu->getValue('cat_default'),
                   array('icon' => 'star.png'));

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($menu->getValue('cat_usr_id_create'), $menu->getValue('cat_timestamp_create'), $menu->getValue('cat_usr_id_change'), $menu->getValue('cat_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
