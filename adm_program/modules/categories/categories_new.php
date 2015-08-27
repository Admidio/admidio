<?php
/******************************************************************************
 * Create and edit categories
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
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
$getCatId = admFuncVariableIsValid($_GET, 'cat_id', 'numeric');
$getType  = admFuncVariableIsValid($_GET, 'type', 'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'USF', 'DAT', 'INF', 'AWA')));
$getTitle = admFuncVariableIsValid($_GET, 'title', 'string');

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
elseif($getType == 'AWA' && $gCurrentUser->editUsers() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set module headline
if($getTitle === '')
{
    if($getType === 'ROL')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', $gL10n->get('SYS_ROLES'));
    }
    elseif($getType === 'LNK')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', $gL10n->get('LNK_WEBLINKS'));
    }
    elseif($getType === 'USF')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', $gL10n->get('ORG_PROFILE_FIELDS'));
    }
    else
    {
        $headline = $gL10n->get('SYS_CATEGORY');
    }
}
else
{
    $headline = $getTitle;
}


// set headline of the script
if($getCatId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', $headline);
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', $headline);
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// UserField-objekt anlegen
$category = new TableCategory($gDb);

if($getCatId > 0)
{
    $category->readDataById($getCatId);

    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue('cat_org_id') >  0
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
    if(isset($_SESSION['categories_request']['show_in_several_organizations']) == false)
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
$form = new HtmlForm('categories_edit_form', $g_root_path.'/adm_program/modules/categories/categories_function.php?cat_id='.$getCatId.'&amp;type='. $getType. '&amp;mode=1', $page);

// systemcategories should not be renamed
$fieldPropertyCatName = FIELD_REQUIRED;
if($category->getValue('cat_system') == 1)
{
    $fieldPropertyCatName = FIELD_DISABLED;
}

$form->addInput('cat_name', $gL10n->get('SYS_NAME'), $category->getValue('cat_name', 'database'), array('maxLength' => 100, 'property' => $fieldPropertyCatName));

if($getType == 'USF')
{
    // if current organization has a parent organization or is child organizations then show option to set this category to global
    if($category->getValue('cat_system') == 0
    && $gCurrentOrganization->countAllRecords() > 1)
    {
        // show all organizations where this organization is mother or child organization
        $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));
        
        $value = 0;
        if($category->getValue('cat_org_id') == 0)
        {
            $value = 1;
        }
        
        $form->addCheckbox('show_in_several_organizations', $gL10n->get('SYS_ENTRY_MULTI_ORGA'), $value, array('helpTextIdLabel' => array('SYS_DATA_GLOBAL', $organizations)));
    }
}
else
{
    $form->addCheckbox('cat_hidden', $gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle), $category->getValue('cat_hidden'), array('icon' => 'user_key.png'));
}
$form->addCheckbox('cat_default', $gL10n->get('CAT_DEFAULT_VAR', $getTitle), $category->getValue('cat_default'), array('icon' => 'star.png'));
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($category->getValue('cat_usr_id_create'), $category->getValue('cat_timestamp_create'), $category->getValue('cat_usr_id_change'), $category->getValue('cat_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

?>