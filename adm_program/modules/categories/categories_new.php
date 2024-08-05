<?php
/**
 ***********************************************************************************************
 * Create and edit categories
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ******************************************************************************
 * Parameters:
 *
 * cat_uuid : Uuid of the category, that should be edited
 * type     : Type of categories that could be maintained
 *            ROL = Categories for roles
 *            LNK = Categories for weblinks
 *            ANN = Categories for announcements
 *            USF = Categories for profile fields
 *            EVT = Calendars for events
 ****************************************************************************/
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getType = admFuncVariableIsValid($_GET, 'type', 'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'EVT', 'AWA')));

    $roleViewSet = array(0);
    $roleEditSet = array(0);
    $addButtonText = $gL10n->get('SYS_CATEGORY');

    // set headline of the script
    if ($getCatUuid !== '') {
        if ($getType === 'EVT') {
            $headlineSuffix = $gL10n->get('SYS_EDIT_CALENDAR');
        } else {
            $headlineSuffix = $gL10n->get('SYS_EDIT_CATEGORY');
        }
    } else {
        if ($getType === 'EVT') {
            $headlineSuffix = $gL10n->get('SYS_CREATE_CALENDAR');
        } else {
            $headlineSuffix = $gL10n->get('SYS_CREATE_CATEGORY');
        }
    }

    // set text strings for the different modules
    switch ($getType) {
        case 'ANN':
            $component = 'ANNOUNCEMENTS';
            $headline = $gL10n->get('SYS_ANNOUNCEMENTS') . ' - ' . $headlineSuffix;
            $rolesRightEditName = 'SYS_EDIT_ANNOUNCEMENTS';
            $rolesRightsColumn = 'rol_announcements';
            $rolesRightsName = 'SYS_RIGHT_ANNOUNCEMENTS';
            break;

        case 'EVT':
            $component = 'EVENTS';
            $headline = $gL10n->get('SYS_EVENTS') . ' - ' . $headlineSuffix;
            $rolesRightEditName = 'SYS_EDIT_EVENTS';
            $rolesRightsColumn = 'rol_events';
            $rolesRightsName = 'SYS_RIGHT_DATES';
            $addButtonText = $gL10n->get('SYS_CALENDAR');
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

        case 'AWA':
            $component = 'CORE';
            $headline = $gL10n->get('Awards') . ' - ' . $headlineSuffix;
            $rolesRightEditName = 'Not used, leave empty';
            $rolesRightsColumn = 'rol_edit_user';
            $rolesRightsName = 'SYS_RIGHT_EDIT_USER';
            break;

        default:
            $headline = $headlineSuffix;
    }

    // check if the current user has the right to
    if (!Component::isAdministrable($component)) {
        throw new AdmException('SYS_INVALID_PAGE_VIEW');
    }

    $gNavigation->addUrl(CURRENT_URL, $headlineSuffix);

    // create category object
    $category = new TableCategory($gDb);

    if ($getCatUuid !== '') {
        $category->readDataByUuid($getCatUuid);
        $catId = (int)$category->getValue('cat_id');

        // get assigned roles of this category
        $categoryViewRolesObject = new RolesRights($gDb, 'category_view', $catId);
        $roleViewSet = $categoryViewRolesObject->getRolesIds();
        $categoryEditRolesObject = new RolesRights($gDb, 'category_edit', $catId);
        $roleEditSet = $categoryEditRolesObject->getRolesIds();
    } else {
        // profile fields should be organization independent all other categories should be organization dependent as default
        if ($getType !== 'USF') {
            $category->setValue('cat_org_id', $gCurrentOrgId);
        }
    }

    // check if this category is editable by the current user and current organization
    if (!$category->isEditable()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // create html page object
    $page = new HtmlPage('admidio-categories-edit', $headline);

    $roleViewDescription = '';
    if ($getType === 'USF') {
        $roleViewDescription = 'SYS_CATEGORY_PROFILE_FIELDS_VISIBILITY';
    }

    if ($getType !== 'ROL' && $gCurrentOrganization->countAllRecords() > 1) {
        $page->addJavascript(
            '
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

    // show form
    $form = new Form(
        'categories_edit_form',
        'modules/categories.edit.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php',
        $page
    );
    // add a hidden field with context information
    $form->addInput(
        'mode',
        'mode',
        'edit',
        array('property' => HtmlForm::FIELD_HIDDEN)
    );
    $form->addInput(
        'uuid',
        'uuid',
        $getCatUuid,
        array('property' => HtmlForm::FIELD_HIDDEN)
    );
    $form->addInput(
        'type',
        'type',
        $getType,
        array('property' => HtmlForm::FIELD_HIDDEN)
    );

    // system categories should not be renamed
    $fieldPropertyCatName = HtmlForm::FIELD_REQUIRED;
    if ($category->getValue('cat_system') == 1) {
        $fieldPropertyCatName = HtmlForm::FIELD_DISABLED;
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
    if ($getType !== 'ROL' && ((bool)$category->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1)) {
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

            if ($getType !== 'USF') {
                $firstEntryName .= ' ('.$gL10n->get('SYS_ALSO_VISITORS').')';
            }
        } else {
            if ($getType === 'USF') {
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
                'property' => HtmlForm::FIELD_REQUIRED,
                'defaultValue' => $roleViewSet,
                'multiselect' => true,
                'firstEntry' => array('0', $firstEntryName, null),
                'helpTextId' => $roleViewDescription
            )
        );

        // until now, we don't use edit rights for profile fields
        if ($getType !== 'USF') {
            $form->addSelectBoxFromSql(
                'adm_categories_edit_right',
                $gL10n->get($rolesRightEditName),
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
    if ($getType !== 'ROL' && (bool)$category->getValue('cat_system') === false && $gCurrentOrganization->countAllRecords() > 1) {
        if ($gCurrentOrganization->isChildOrganization()) {
            $fieldProperty = HtmlForm::FIELD_DISABLED;
            $helpTextId = 'SYS_ONLY_SET_BY_MOTHER_ORGANIZATION';
        } else {
            // show all organizations where this organization is mother or child organization
            $organizations = implode(', ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

            $fieldProperty = HtmlForm::FIELD_DEFAULT;
            if ($getType === 'USF') {
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
            array('property' => HtmlForm::FIELD_DISABLED, 'helpTextId' => $gL10n->get('SYS_CATEGORIES_ADMINISTRATORS_DESC', array($rolesRightsName)))
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
    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3'));

    $page->assignSmartyVariable('nameUserCreated', $category->getNameOfCreatingUser());
    $page->assignSmartyVariable('timestampUserCreated', $category->getValue('cat_timestamp_create'));
    $page->assignSmartyVariable('nameLastUserEdited', $category->getNameOfLastEditingUser());
    $page->assignSmartyVariable('timestampLastUserEdited', $category->getValue('cat_timestamp_change'));
    $form->addToHtmlPage();
    $_SESSION['categories_edit_form'] = $form;

    $page->show();
} catch (AdmException|Exception $e) {
    $gMessage->show($e->getMessage());
}
