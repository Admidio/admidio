<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Components\Entity\Component;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Categories\Entity\Category;
use Admidio\Changelog\Service\ChangelogService;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new MenuPresenter('adm_menu', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CategoriesPresenter extends PagePresenter
{
    /**
     * Create the data for the edit form of a menu entry.
     * @param string $type Type of category that should be shown. Values are ROL, ANN, EVT, LNK, USF and AWA.
     * @param string $categoryUUID UUID of the category that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $type, string $categoryUUID = '')
    {
        global $gCurrentSession, $gL10n, $gCurrentOrgId, $gCurrentOrganization, $gDb, $gSettingsManager;

        $roleViewSet = array(0);
        $roleEditSet = array(0);
        $addButtonText = $gL10n->get('SYS_CATEGORY');

        // create category object
        $category = new Category($gDb);

        if ($categoryUUID !== '') {
            $category->readDataByUuid($categoryUUID);
        }
        // If no type was passed as param, try to read it from the DB. If unsuccessfull, thrown an error.
        if (empty($type)) {
            $type = $category->getValue('cat_type');
        }
        if (empty($type)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // set headline of the script
        if ($categoryUUID !== '') {
            if ($type === 'EVT') {
                $headlineSuffix = $gL10n->get('SYS_EDIT_CALENDAR');
            } else {
                $headlineSuffix = $gL10n->get('SYS_EDIT_CATEGORY');
            }
        } else {
            if ($type === 'EVT') {
                $headlineSuffix = $gL10n->get('SYS_CREATE_CALENDAR');
            } else {
                $headlineSuffix = $gL10n->get('SYS_CREATE_CATEGORY');
            }
        }

        // set text strings for the different modules
        switch ($type) {
            case 'ANN':
                $component = 'ANNOUNCEMENTS';
                $headline = $gL10n->get('SYS_ANNOUNCEMENTS') . ' - ' . $headlineSuffix;
                $rolesRightEditName = 'SYS_EDIT_ANNOUNCEMENTS';
                $rolesRightsColumn = 'rol_announcements';
                $rolesRightsName = 'SYS_RIGHT_ANNOUNCEMENTS';
                break;

            case 'AWA':
                $component = 'CORE';
                $headline = $gL10n->get('Awards') . ' - ' . $headlineSuffix;
                $rolesRightEditName = 'Not used, leave empty';
                $rolesRightsColumn = 'rol_edit_user';
                $rolesRightsName = 'SYS_RIGHT_EDIT_USER';
                break;

            case 'EVT':
                $component = 'EVENTS';
                $headline = $gL10n->get('SYS_EVENTS') . ' - ' . $headlineSuffix;
                $rolesRightEditName = 'SYS_EDIT_EVENTS';
                $rolesRightsColumn = 'rol_events';
                $rolesRightsName = 'SYS_RIGHT_DATES';
                $addButtonText = $gL10n->get('SYS_CALENDAR');
                break;

            case 'FOT':
                $component = 'FORUM';
                $headline = $gL10n->get('SYS_FORUM') . ' - ' . $headlineSuffix;
                $rolesRightEditName = $gL10n->get('SYS_EDIT_VAR', array('SYS_TOPICS'));
                $rolesRightsColumn = 'rol_forum_admin';
                $rolesRightsName = 'SYS_RIGHT_FORUM';
                break;

            case 'LNK':
                $component = 'LINKS';
                $headline = $gL10n->get('SYS_WEBLINKS') . ' - ' . $headlineSuffix;
                $rolesRightEditName = 'SYS_EDIT_WEBLINKS';
                $rolesRightsColumn = 'rol_weblinks';
                $rolesRightsName = 'SYS_RIGHT_WEBLINKS';
                break;

            case 'ROL':
                $component = 'GROUPS-ROLES';
                $headline = $gL10n->get('SYS_ROLES') . ' - ' . $headlineSuffix;
                break;

            case 'USF':
                $component = 'CORE';
                $headline = $gL10n->get('ORG_PROFILE_FIELDS') . ' - ' . $headlineSuffix;
                $rolesRightEditName = 'SYS_EDIT_PROFILE_FIELDS_PREF';
                $rolesRightsColumn = 'rol_edit_user';
                $rolesRightsName = 'SYS_RIGHT_EDIT_USER';
                break;

            default:
                $headline = $headlineSuffix;
        }

        // check if the current user has the right to
        if (!Component::isAdministrable($component)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if ($categoryUUID !== '') {
            $catId = (int)$category->getValue('cat_id');

            // get assigned roles of this category
            $categoryViewRolesObject = new RolesRights($gDb, 'category_view', $catId);
            $roleViewSet = $categoryViewRolesObject->getRolesIds();
            $categoryEditRolesObject = new RolesRights($gDb, 'category_edit', $catId);
            $roleEditSet = $categoryEditRolesObject->getRolesIds();
        } else {
            // profile fields should be organization independent all other categories should be organization dependent as default
            if ($type !== 'USF') {
                $category->setValue('cat_org_id', $gCurrentOrgId);
            }
        }

        // check if this category is editable by the current user and current organization
        if (!$category->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->setHeadline($headline);

        $roleViewDescription = '';
        if ($type === 'USF') {
            $roleViewDescription = 'SYS_CATEGORY_PROFILE_FIELDS_VISIBILITY';
        }

        if ($type !== 'ROL' && $gCurrentOrganization->countAllRecords() > 1) {
            $this->addJavascript('
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

                showHideViewRightControl();', true
            );
        }

        ChangelogService::displayHistoryButton($this, 'categories', 'categories,roles_rights_data', !empty($categoryUUID), array('uuid' => $categoryUUID));


        // show form
        $form = new FormPresenter(
            'adm_categories_edit_form',
            'modules/categories.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('uuid' => $categoryUUID, 'mode' => 'save', 'type' => $type)),
            $this
        );

        // system categories should not be renamed
        $fieldPropertyCatName = FormPresenter::FIELD_REQUIRED;
        if ($category->getValue('cat_system') == 1) {
            $fieldPropertyCatName = FormPresenter::FIELD_DISABLED;
        }

        $form->addInput(
            'cat_name',
            $gL10n->get('SYS_NAME'),
            htmlentities($category->getValue('cat_name', 'database'), ENT_QUOTES),
            array('maxLength' => 100, 'property' => $fieldPropertyCatName)
        );

        // Roles have their own preferences for visibility, so only allow this for other types.
        // Until now, we do not support visibility for categories that belong to several organizations,
        // roles could be assigned if only 1 organization exists.
        if ($type !== 'ROL' && ((bool)$category->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1)) {
            // read all roles of the current organization
            $sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                       FROM ' . TBL_ROLES . '
                 INNER JOIN ' . TBL_CATEGORIES . '
                         ON cat_id = rol_cat_id
                      WHERE rol_valid  = true
                        AND rol_system = false
                        AND cat_name_intern <> \'EVENTS\'
                        AND cat_org_id = ? -- $gCurrentOrgId
                   ORDER BY cat_sequence, rol_name';
            $sqlDataView = array(
                'query' => $sqlViewRoles,
                'params' => array($gCurrentOrgId)
            );

            // if no roles are assigned then set "all users" as default
            if (count($roleViewSet) === 0) {
                $roleViewSet[] = 0;
            }

            // if no roles are assigned then set nothing as default
            if (count($roleEditSet) === 0) {
                $roleEditSet[] = '';
            }

            if ($gCurrentOrganization->countAllRecords() > 1) {
                if ((int) $category->getValue('cat_org_id') === 0) {
                    $firstEntryName = $gL10n->get('SYS_ALL_ORGANIZATIONS');
                } else {
                    $firstEntryName = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                }

                if ($type !== 'USF') {
                    $firstEntryName .= ' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
                }
            } else {
                if ($type === 'USF') {
                    $firstEntryName = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                } else {
                    $firstEntryName = $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
                }
            }

            // show selectbox with all assigned roles
            $form->addSelectBoxFromSql(
                'adm_categories_view_right',
                $gL10n->get('SYS_VISIBLE_FOR'),
                $gDb,
                $sqlDataView,
                array(
                    'property' => FormPresenter::FIELD_REQUIRED,
                    'defaultValue' => $roleViewSet,
                    'multiselect' => true,
                    'firstEntry' => array('0', $firstEntryName, null),
                    'helpTextId' => $roleViewDescription
                )
            );

            // until now, we don't use edit rights for profile fields
            if ($type !== 'USF') {
                $form->addSelectBoxFromSql(
                    'adm_categories_edit_right',
                    $rolesRightEditName,
                    $gDb,
                    $sqlDataView,
                    array(
                        'defaultValue' => $roleEditSet,
                        'multiselect' => true,
                        'placeholder' => $gL10n->get('SYS_NO_ADDITIONAL_PERMISSIONS_SET')
                    )
                );
            }
        }

        // if current organization has a parent organization or is child organizations then show option to set this category to global
        if ($type !== 'ROL' && (bool)$category->getValue('cat_system') === false && $gCurrentOrganization->countAllRecords() > 1) {
            if ($gCurrentOrganization->isChildOrganization()) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
                $helpTextId = 'SYS_ONLY_SET_BY_MOTHER_ORGANIZATION';
            } else {
                // show all organizations where this organization is mother or child organization
                $organizations = implode(', ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

                $fieldProperty = FormPresenter::FIELD_DEFAULT;
                if ($type === 'USF') {
                    $helpTextId = $gL10n->get('SYS_CATEGORY_VISIBLE_ALL_ORGA', array($organizations));
                } else {
                    $helpTextId = $gL10n->get('SYS_DATA_CATEGORY_GLOBAL', array($organizations));
                }
            }

            // read all administrator roles

            $sqlAdminRoles = 'SELECT rol_name
                        FROM ' . TBL_ROLES . '
                  INNER JOIN ' . TBL_CATEGORIES . '
                          ON cat_id = rol_cat_id
                       WHERE rol_valid    = true
                         AND ' . $rolesRightsColumn . ' = true
                         AND cat_org_id   = ? -- $gCurrentOrgId
                    ORDER BY cat_sequence, rol_name';
            $statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrgId));

            $adminRoles = array();
            while ($roleName = $statementAdminRoles->fetchColumn()) {
                $adminRoles[] = $roleName;
            }

            $form->addInput(
                'adm_administrators',
                $gL10n->get('SYS_ADMINISTRATORS'),
                implode(', ', $adminRoles),
                array('property' => FormPresenter::FIELD_DISABLED, 'helpTextId' => $gL10n->get('SYS_CATEGORIES_ADMINISTRATORS_DESC', array($rolesRightsName)))
            );

            $checked = false;
            if ((int)$category->getValue('cat_org_id') === 0) {
                $checked = true;
            }

            $form->addCheckbox(
                'show_in_several_organizations',
                $gL10n->get('SYS_DATA_MULTI_ORGA'),
                $checked,
                array('property' => $fieldProperty, 'helpTextId' => $helpTextId)
            );
        }

        $form->addCheckbox(
            'cat_default',
            $gL10n->get('SYS_DEFAULT_VAR', array($addButtonText)),
            (bool)$category->getValue('cat_default'),
            array('icon' => 'bi-star-fill')
        );
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $this->assignSmartyVariable('nameUserCreated', $category->getNameOfCreatingUser());
        $this->assignSmartyVariable('timestampUserCreated', $category->getValue('cat_timestamp_create'));
        $this->assignSmartyVariable('nameLastUserEdited', $category->getNameOfLastEditingUser());
        $this->assignSmartyVariable('timestampLastUserEdited', $category->getValue('cat_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the list with all categories and their preferences, depending on the type.
     * @param string $type Type of category that should be shown. Values are ROL, ANN, EVT, LNK, USF and AWA.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createList(string $type)
    {
        global $gL10n, $gCurrentOrgId, $gDb, $gCurrentSession, $gCurrentOrganization, $gSettingsManager;

        // set module headline
        $headline = $gL10n->get('SYS_CATEGORIES');
        $addButtonText = $gL10n->get('SYS_CREATE_CATEGORY');
        $visibleHeadline = $gL10n->get('SYS_VISIBLE_FOR');
        $navigationHeadline = $gL10n->get('SYS_CATEGORIES');
        $deleteMessage = 'SYS_WANT_DELETE_CATEGORY';
        $editableHeadline = '';

        switch ($type) {
            case 'ANN':
                $component = 'ANNOUNCEMENTS';
                $rolesRightsColumn = 'rol_announcements';
                $headline = $gL10n->get('SYS_ANNOUNCEMENTS') . ' - ' . $gL10n->get('SYS_CATEGORIES');
                $editableHeadline = $gL10n->get('SYS_EDIT_ANNOUNCEMENTS');
                break;

            case 'AWA':
                $component = 'CORE';
                $rolesRightsColumn = 'rol_edit_user';
                $headline = $gL10n->get('Awards') . ' - ' . $gL10n->get('SYS_CATEGORIES');
                break;

            case 'EVT':
                $component = 'EVENTS';
                $rolesRightsColumn = 'rol_events';
                $headline = $gL10n->get('SYS_EVENTS') . ' - ' . $gL10n->get('SYS_CALENDARS');
                $navigationHeadline = $gL10n->get('SYS_CALENDARS');
                $editableHeadline = $gL10n->get('SYS_EDIT_EVENTS');
                $addButtonText = $gL10n->get('SYS_CREATE_CALENDAR');
                $deleteMessage = 'SYS_DELETE_ENTRY';
                break;

            case 'FOT':
                $component = 'FORUM';
                $rolesRightsColumn = 'rol_forum_admin';
                $headline = $gL10n->get('SYS_FORUM') . ' - ' . $gL10n->get('SYS_CATEGORIES');
                $editableHeadline = $gL10n->get('SYS_EDIT_VAR', array('SYS_TOPICS'));
                break;

            case 'LNK':
                $component = 'LINKS';
                $rolesRightsColumn = 'rol_weblinks';
                $headline = $gL10n->get('SYS_WEBLINKS') . ' - ' . $gL10n->get('SYS_CATEGORIES');
                $editableHeadline = $gL10n->get('SYS_EDIT_WEBLINKS');
                break;

            case 'ROL':
                $component = 'GROUPS-ROLES';
                $rolesRightsColumn = 'rol_assign_roles';
                $headline = $gL10n->get('SYS_ROLES') . ' - ' . $gL10n->get('SYS_CATEGORIES');
                $visibleHeadline = '';
                break;

            case 'USF':
                $component = 'CORE';
                $rolesRightsColumn = 'rol_edit_user';
                $headline = $gL10n->get('ORG_PROFILE_FIELDS') . ' - ' . $gL10n->get('SYS_CATEGORIES');
                $editableHeadline = $gL10n->get('SYS_EDIT_PROFILE_FIELDS_PREF');
                break;

            default:
                throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // check if the current user has the right to
        if (!Component::isAdministrable($component)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // read all administrator roles

        $sqlAdminRoles = 'SELECT rol_name
                    FROM ' . TBL_ROLES . '
              INNER JOIN ' . TBL_CATEGORIES . '
                      ON cat_id = rol_cat_id
                   WHERE rol_valid  = true
                     AND ' . $rolesRightsColumn . ' = true
                     AND cat_org_id = ? -- $gCurrentOrgId
                ORDER BY cat_sequence, rol_name';
        $statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrgId));

        $adminRoles = array();
        while ($roleName = $statementAdminRoles->fetchColumn()) {
            $adminRoles[] = $roleName;
        }

        $this->setHeadline($headline);
        $this->addJavascript('
            $(".admidio-category-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/categories.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });', true
        );

        // define link to create new category
        $this->addPageFunctionsMenuItem(
            'menu_item_categories_add',
            $addButtonText,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('mode' => 'edit', 'type' => $type)),
            'bi-plus-circle-fill'
        );


        ChangelogService::displayHistoryButton($this, 'categories', 'categories');

        $sql = 'SELECT *
          FROM ' . TBL_CATEGORIES . '
         WHERE (  cat_org_id  = ? -- $gCurrentOrgId
               OR cat_org_id IS NULL )
           AND cat_type = ? -- $type
      ORDER BY cat_org_id, cat_sequence';

        $categoryStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $type));

        $category = new Category($gDb);
        $templateCategoryNodes = array();
        $templateCategories = array();
        $categoryOrganizationID = 0;

        // Get data
        while ($catRow = $categoryStatement->fetch()) {
            $category->clear();
            $category->setArray($catRow);

            if($categoryOrganizationID !== (int) $category->getValue('cat_org_id')
            && count($templateCategories) > 0) {
                $templateCategoryNodes[] = $templateCategories;
                $templateCategories = array();
                $categoryOrganizationID = $category->getValue('cat_org_id');
            }

            $templateCategory = array();
            $templateCategory['uuid'] = $category->getValue('cat_uuid');
            $templateCategory['name'] = $category->getValue('cat_name');
            $templateCategory['urlEdit'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('mode' => 'edit', 'uuid' => $category->getValue('cat_uuid'), 'type' => $type));
            $templateCategory['system'] = $category->getValue('cat_system');
            $templateCategory['default'] = $category->getValue('cat_default');

            // create list with all roles that could view the category
            if ($type === 'ROL') {
                $htmlViewRolesNames = '';
            } else {
                $rightCategoryView = new RolesRights($gDb, 'category_view', $category->getValue('cat_id'));
                $arrRolesIds = $rightCategoryView->getRolesIds();

                if (count($arrRolesIds) > 0) {
                    $htmlViewRolesNames = implode(', ', array_unique(array_merge($rightCategoryView->getRolesNames(), $adminRoles)));
                } else {
                    if ($gCurrentOrganization->countAllRecords() > 1) {
                        if ((int)$category->getValue('cat_org_id') === 0) {
                            $htmlViewRolesNames = $gL10n->get('SYS_ALL_ORGANIZATIONS');
                        } else {
                            $htmlViewRolesNames = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                        }

                        if ($type !== 'USF') {
                            $htmlViewRolesNames .= ' (' . $gL10n->get('SYS_ALSO_VISITORS') . ')';
                        }
                    } else {
                        if ($type === 'USF') {
                            $htmlViewRolesNames = $gL10n->get('SYS_ALL_THIS_ORGANIZATION');
                        } else {
                            $htmlViewRolesNames = $gL10n->get('SYS_ALL') . ' (' . $gL10n->get('SYS_ALSO_VISITORS') . ')';
                        }
                    }
                }
            }

            $templateCategory['visibleForRoles'] = $htmlViewRolesNames;

            // create list with all roles that could edit the category
            if ($type === 'ROL') {
                $htmlEditRolesNames = '';
            } else {
                if ((int)$category->getValue('cat_org_id') === 0 && $gCurrentOrganization->isChildOrganization()) {
                    $htmlEditRolesNames = $gL10n->get('SYS_CATEGORIES_ALL_MODULE_ADMINISTRATORS_MOTHER_ORGA');
                } else {
                    $rightCategoryEdit = new RolesRights($gDb, 'category_edit', $category->getValue('cat_id'));
                    $htmlEditRolesNames = implode(', ', array_unique(array_merge($rightCategoryEdit->getRolesNames(), $adminRoles)));
                }
            }

            $templateCategory['editableForRoles'] = $htmlEditRolesNames;

            if ($category->isEditable()) {
                $templateCategory['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('mode' => 'edit', 'uuid' => $category->getValue('cat_uuid'), 'type' => $type)),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT')
                );
                if (!$category->getValue('cat_system')) {
                    $templateCategory['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'adm_category_' . $category->getValue('cat_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('mode' => 'delete', 'uuid' => $category->getValue('cat_uuid'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get($deleteMessage, array($category->getValue('cat_name', 'database'))),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE')
                    );
                } else {
                    $templateCategory['actions'][] = array(
                        'url' => '',
                        'icon' => 'bi bi-trash invisible',
                        'tooltip' => ''
                    );
                }
            }

            $templateCategories[] = $templateCategory;
        }

        $templateCategoryNodes[] = $templateCategories;

        $this->smarty->assign('list', $templateCategoryNodes);
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('title', $navigationHeadline);
        $this->smarty->assign('columnTitleEditable', $editableHeadline);
        $this->pageContent .= $this->smarty->fetch('modules/categories.list.tpl');
    }
}
