<?php
/**
 ***********************************************************************************************
 * Create and edit categories
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ******************************************************************************
 * Parameters:
 *
 * cat_id: Id of the category that should be edited
 * type  : Type of categories that could be maintained
 *         ROL = Categories for roles
 *         LNK = Categories for weblinks
 *         ANN = Categories for announcements
 *         USF = Categories for profile fields
 *         DAT = Calendars for events
 * title : Parameter for the synonym of the categorie
 ****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getCatId = admFuncVariableIsValid($_GET, 'cat_id', 'int');
$getType  = admFuncVariableIsValid($_GET, 'type',   'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'DAT', 'AWA')));
$getTitle = admFuncVariableIsValid($_GET, 'title',  'string');

$roleViewSet = array(0);
$roleEditSet = array(0);

// set text strings for the different modules
switch ($getType)
{
    case 'ROL':
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('SYS_ROLES')));
        break;

    case 'LNK':
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('LNK_WEBLINKS')));
        $rolesRightEditName = 'LNK_EDIT_WEBLINKS';
        $rolesRightsColumn  = 'rol_weblinks';
        $rolesRightsName    = 'ROL_RIGHT_WEBLINKS';
        break;

    case 'ANN':
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('ANN_ANNOUNCEMENTS')));
        $rolesRightEditName = 'ANN_EDIT_ANNOUNCEMENTS';
        $rolesRightsColumn  = 'rol_announcements';
        $rolesRightsName    = 'ROL_RIGHT_ANNOUNCEMENTS';
        break;

    case 'USF':
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('ORG_PROFILE_FIELDS')));
        $rolesRightEditName = 'PRO_EDIT_PROFILE_FIELDS';
        $rolesRightsColumn  = 'rol_edit_user';
        $rolesRightsName    = 'ROL_RIGHT_EDIT_USER';
        break;

    case 'DAT':
        $rolesRightEditName = 'DAT_EDIT_EVENTS';
        $rolesRightsColumn  = 'rol_dates';
        $rolesRightsName    = 'ROL_RIGHT_DATES';
        break;

    case 'AWA':
        $headline = $gL10n->get('SYS_CATEGORY_VAR', 'Awards');
        $rolesRightEditName = 'Not used, leave empty';
        $rolesRightsColumn  = 'rol_edit_user';
        $rolesRightsName    = 'ROL_RIGHT_EDIT_USER';
        break;

    default:
        $headline = $gL10n->get('SYS_CATEGORY');
        break;
}

// set module headline and other strings
if($getTitle === '')
{
    $addButtonText = $gL10n->get('SYS_CATEGORY');
}
else
{
    $headline      = $getTitle;
    $addButtonText = $getTitle;
}

// set headline of the script
if($getCatId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', array($headline));
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', array($headline));
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create category object
$category = new TableCategory($gDb);

if(isset($_SESSION['categories_request']))
{
    // By wrong input, the user returned to this form now write the previously entered contents into the object

    $category->setArray($_SESSION['categories_request']);

    // get the selected roles for visibility
    if(isset($_SESSION['categories_request']['adm_categories_view_right']))
    {
        $roleViewSet = $_SESSION['categories_request']['adm_categories_view_right'];
    }

    if(isset($_SESSION['categories_request']['show_in_several_organizations']))
    {
        $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
    }
    unset($_SESSION['categories_request']);
}
else
{
    if($getCatId > 0)
    {
        $category->readDataById($getCatId);

        // get assigned roles of this category
        $categoryViewRolesObject = new RolesRights($gDb, 'category_view', (int) $category->getValue('cat_id'));
        $roleViewSet = $categoryViewRolesObject->getRolesIds();
        $categoryEditRolesObject = new RolesRights($gDb, 'category_edit', $category->getValue('cat_id'));
        $roleEditSet = $categoryEditRolesObject->getRolesIds();
    }
    else
    {
        // profile fields should be organization independent all other categories should be organization dependent as default
        if($getType !== 'USF')
        {
            $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
        }
    }
}

// check if this category is editable by the current user and current organization
if(!$category->isEditable())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// create html page object
$page = new HtmlPage($headline);

$roleViewDescription = '';
if($getType === 'USF')
{
    $roleViewDescription = 'CAT_PROFILE_FIELDS_VISIBILITY';
}

if($getType !== 'ROL' && $gCurrentOrganization->countAllRecords() > 1)
{
    $page->addJavascript('
        function showHideViewRightControl() {
            if ($("#show_in_several_organizations").is(":checked")) {
                $("#adm_categories_view_right_group").hide();
            } else {
                $("#adm_categories_view_right_group").show("slow");
            }
        }

        $("#show_in_several_organizations").click(function() {
            showHideViewRightControl();
        });

        showHideViewRightControl();',
        true
    );
}

// add back link to module menu
$categoryCreateMenu = $page->getMenu();
$categoryCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('categories_edit_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_function.php', array('cat_id' => $getCatId, 'type' => $getType, 'mode' => '1')), $page);

// systemcategories should not be renamed
$fieldPropertyCatName = HtmlForm::FIELD_REQUIRED;
if($category->getValue('cat_system') == 1)
{
    $fieldPropertyCatName = HtmlForm::FIELD_DISABLED;
}

$form->addInput(
    'cat_name', $gL10n->get('SYS_NAME'), $category->getValue('cat_name', 'database'),
    array('maxLength' => 100, 'property' => $fieldPropertyCatName)
);

// Roles have their own preferences for visibility, so only allow this for other types.
// Until now we do not support visibility for categories that belong to several organizations,
// roles could be assigned if only 1 organization exists.
if($getType !== 'ROL' && ((bool) $category->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1))
{
    // read all roles of the current organization
    $sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                       FROM '.TBL_ROLES.'
                 INNER JOIN '.TBL_CATEGORIES.'
                         ON cat_id = rol_cat_id
                      WHERE rol_valid  = 1
                        AND rol_system = 0
                        AND cat_name_intern <> \'EVENTS\'
                        AND cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   ORDER BY cat_sequence, rol_name';
    $sqlDataView = array(
        'query'  => $sqlViewRoles,
        'params' => array($gCurrentOrganization->getValue('org_id'))
    );

    // if no roles are assigned then set "all users" as default
    if(count($roleViewSet) === 0)
    {
        $roleViewSet[] = 0;
    }

    // if no roles are assigned then set nothing as default
    if(count($roleEditSet) === 0)
    {
        $roleEditSet[] = '';
    }

    // show selectbox with all assigned roles
    $form->addSelectBoxFromSql(
        'adm_categories_view_right', $gL10n->get('SYS_VISIBLE_FOR'), $gDb, $sqlDataView,
        array(
            'property'     => HtmlForm::FIELD_REQUIRED,
            'defaultValue' => $roleViewSet,
            'multiselect'  => true,
            'firstEntry'   => array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null),
            'helpTextIdInline' => $roleViewDescription
        )
    );

    // until now we don't use edit rights for profile fields
    if($getType !== 'USF')
    {
        $form->addSelectBoxFromSql(
            'adm_categories_edit_right', $gL10n->get($rolesRightEditName), $gDb, $sqlDataView,
            array(
                'property'     => FIELD_REQUIRED,
                'defaultValue' => $roleEditSet,
                'multiselect'  => true,
                'placeholder'  => $gL10n->get('DOW_NO_ADDITIONAL_PERMISSIONS_SET')
            )
        );
    }
}

// if current organization has a parent organization or is child organizations then show option to set this category to global
if($getType !== 'ROL' && $category->getValue('cat_system') == 0 && $gCurrentOrganization->countAllRecords() > 1)
{
    if($gCurrentOrganization->isChildOrganization())
    {
        $fieldProperty   = HtmlForm::FIELD_DISABLED;
        $helpTextIdLabel = 'CAT_ONLY_SET_BY_MOTHER_ORGANIZATION';
    }
    else
    {
        // show all organizations where this organization is mother or child organization
        $organizations = implode(', ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

        $fieldProperty = HtmlForm::FIELD_DEFAULT;
        if($getType === 'USF')
        {
            $helpTextIdLabel = array('CAT_CATEGORY_GLOBAL', $organizations);
        }
        else
        {
            $helpTextIdLabel = array('SYS_DATA_CATEGORY_GLOBAL', $organizations);
        }
    }

    // read all administrator roles

    $sqlAdminRoles = 'SELECT rol_name
                        FROM '.TBL_ROLES.'
                  INNER JOIN '.TBL_CATEGORIES.'
                          ON cat_id = rol_cat_id
                       WHERE rol_valid    = 1
                         AND '. $rolesRightsColumn .' = 1
                         AND cat_org_id   = ? -- $gCurrentOrganization->getValue(\'org_id\')
                    ORDER BY cat_sequence, rol_name';
    $statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrganization->getValue('org_id')));

    $adminRoles = array();
    while($roleName = $statementAdminRoles->fetchColumn())
    {
        $adminRoles[] = $roleName;
    }

    $form->addStaticControl(
        'adm_administrators', $gL10n->get('SYS_ADMINISTRATORS'), implode(', ', $adminRoles),
        array('helpTextIdLabel' => array('CAT_ADMINISTRATORS_DESC', $gL10n->get($rolesRightsName)))
    );

    $checked = false;
    if((int) $category->getValue('cat_org_id') === 0)
    {
        $checked = true;
    }

    $form->addCheckbox(
        'show_in_several_organizations', $gL10n->get('SYS_DATA_MULTI_ORGA'), $checked,
        array('property' => $fieldProperty, 'helpTextIdLabel' => $helpTextIdLabel)
    );
}

$form->addCheckbox(
    'cat_default', $gL10n->get('CAT_DEFAULT_VAR', array($addButtonText)), (bool) $category->getValue('cat_default'),
    array('icon' => 'star.png')
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $category->getValue('cat_usr_id_create'), $category->getValue('cat_timestamp_create'),
    (int) $category->getValue('cat_usr_id_change'), $category->getValue('cat_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
