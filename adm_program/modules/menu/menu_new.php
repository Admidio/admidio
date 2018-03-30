<?php
/**
 ***********************************************************************************************
 * Create and edit categories
 *
 * @copyright 2004-2018 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * men_id: Id of the menu that should be edited
 *
 ****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getMenId = admFuncVariableIsValid($_GET, 'men_id', 'int');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

/**
 * @param array<int,string> $menuList
 * @param int               $level
 * @param int               $menId
 * @param int               $parentId
 */
function subMenu(&$menuList, $level, $menId, $parentId = null)
{
    global $gDb;

    $sqlConditionParentId = '';
    $queryParams = array($menId);

    // Erfassen des auszugebenden Menu
    if ($parentId > 0)
    {
        $sqlConditionParentId .= ' AND men_men_id_parent = ? -- $parentId';
        $queryParams[] = $parentId;
    }
    else
    {
        $sqlConditionParentId .= ' AND men_men_id_parent IS NULL';
    }

    $sql = 'SELECT *
              FROM '.TBL_MENU.'
             WHERE men_node = 1
               AND men_id  <> ? -- $menu->getValue(\'men_id\')
                   '.$sqlConditionParentId;
    $childStatement = $gDb->queryPrepared($sql, $queryParams);

    $parentMenu = new TableMenu($gDb);
    $einschub = str_repeat('&nbsp;', $level * 3) . '&#151;&nbsp;';

    while($menuEntry = $childStatement->fetch())
    {
        $parentMenu->clear();
        $parentMenu->setArray($menuEntry);

        // add entry to array of all menus
        $menuList[$parentMenu->getValue('men_id')] = $einschub . $parentMenu->getValue('men_name');

        subMenu($menuList, ++$level, $menId, $parentMenu->getValue('men_id'));
    }
}

// set module headline
if($getMenId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_MENU')));
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_MENU')));
}

// create menu object
$menu = new TableMenu($gDb);

// systemcategories should not be renamed
$roleViewSet[] = 0;

if($getMenId > 0)
{
    $menu->readDataById($getMenId);

    // Read current roles rights of the menu
    $display = new RolesRights($gDb, 'menu_view', $getMenId);
    $roleViewSet = $display->getRolesIds();
}

if(isset($_SESSION['menu_request']))
{
    // due to incorrect input, the user has returned to this form
    // Now write the previously entered content into the object
    $menu->setArray($_SESSION['menu_request']);
    unset($_SESSION['menu_request']);
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$menuCreateMenu = $page->getMenu();
$menuCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// alle aus der DB aus lesen
$sqlRoles = 'SELECT rol_id, rol_name, org_shortname, cat_name
               FROM '.TBL_ROLES.'
         INNER JOIN '.TBL_CATEGORIES.'
                 ON cat_id = rol_cat_id
         INNER JOIN '.TBL_ORGANIZATIONS.'
                 ON org_id = cat_org_id
              WHERE rol_valid  = 1
                AND rol_system = 0
                AND cat_name_intern <> \'EVENTS\'
           ORDER BY cat_name, rol_name';
$rolesViewStatement = $gDb->queryPrepared($sqlRoles);

$parentRoleViewSet = array();
while($rowViewRoles = $rolesViewStatement->fetch())
{
    // Jede Rolle wird nun dem Array hinzugefuegt
    $parentRoleViewSet[] = array(
        $rowViewRoles['rol_id'],
        $rowViewRoles['rol_name'] . ' (' . $rowViewRoles['org_shortname'] . ')',
        $rowViewRoles['cat_name']
    );
}

// show form
$form = new HtmlForm('menu_edit_form', safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('men_id' => $getMenId, 'mode' => 1)), $page);

$fieldRequired = FIELD_REQUIRED;
$fieldDefault  = FIELD_DEFAULT;

if((bool) $menu->getValue('men_standard'))
{
    $fieldRequired = FIELD_DISABLED;
    $fieldDefault  = FIELD_DISABLED;
}

$menuList = array();
subMenu($menuList, 1, (int) $menu->getValue('men_id'));

$form->addInput(
    'men_name', $gL10n->get('SYS_NAME'), $menu->getValue('men_name', 'database'),
    array('maxLength' => 100, 'property'=> FIELD_REQUIRED, 'helpTextIdLabel' => 'MEN_NAME_DESC')
);

if($getMenId > 0)
{
    $form->addInput(
        'men_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $menu->getValue('men_name_intern', 'database'),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED, 'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC')
    );
}

$form->addMultilineTextInput(
    'men_description', $gL10n->get('SYS_DESCRIPTION'), $menu->getValue('men_description', 'database'), 2,
    array('maxLength' => 4000)
);

$form->addSelectBox(
    'men_men_id_parent', $gL10n->get('MEN_MENU_LEVEL'), $menuList,
    array(
        'property'                       => FIELD_REQUIRED,
        'defaultValue'                   => $menu->getValue('men_men_id_parent'),
        'helpTextIdLabel'                => array('MEN_MENU_LEVEL_DESC', 'MAIN')
    )
);

$sql = 'SELECT com_id, com_name
          FROM '.TBL_COMPONENTS.'
      ORDER BY com_name';
$form->addSelectBoxFromSql(
    'men_com_id', $gL10n->get('MEN_MODULE_RIGHTS'), $gDb, $sql,
    array(
        'property'        => $fieldDefault,
        'defaultValue'    => $menu->getValue('men_com_id'),
        'helpTextIdLabel' => 'MEN_MODULE_RIGHTS_DESC'
    )
);

$form->addSelectBox(
    'menu_view', $gL10n->get('SYS_VISIBLE_FOR'), $parentRoleViewSet,
    array('defaultValue' => $roleViewSet, 'multiselect' => true)
);

if((bool) $menu->getValue('men_node') === false)
{
    $form->addInput(
        'men_url', $gL10n->get('ORG_URL'), $menu->getValue('men_url', 'database'),
        array('maxLength' => 100, 'property' => $fieldRequired)
    );
}

$arrayIcons  = admFuncGetDirectoryEntries(THEME_PATH . '/icons');
$defaultIcon = array_search($menu->getValue('men_icon', 'database'), $arrayIcons, true);
$form->addSelectBox(
    'men_icon', $gL10n->get('SYS_ICON'), $arrayIcons,
    array('defaultValue' => $defaultIcon, 'showContextDependentFirstEntry' => true)
);

$form->addSubmitButton(
    'btn_save', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png')
);

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
