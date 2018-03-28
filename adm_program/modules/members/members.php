<?php
/**
 ***********************************************************************************************
 * Show and manage all members of the organization
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * members - true : (Default) Show only active members of the current organization
 *           false  : Show active and inactive members of all organizations in database
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['import_request']);

// Initialize and check the parameters
$getMembers = admFuncVariableIsValid($_GET, 'members', 'bool', array('defaultValue' => true));

// if only active members should be shown then set parameter
if(!$gSettingsManager->getBool('members_show_all_users'))
{
    $getMembers = true;
}

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('MEM_USER_MANAGEMENT');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
$flagShowMembers = !$getMembers;

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('
    $("#menu_item_create_user").attr("data-toggle", "modal");
    $("#menu_item_create_user").attr("data-target", "#admidio_modal");

    // change mode of users that should be shown
    $("#mem_show_all").click(function() {
        window.location.replace("'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members.php', array('members' => $flagShowMembers)).'");
    });', true);

// get module menu
$membersAdministrationMenu = $page->getMenu();

$membersAdministrationMenu->addItem(
    'menu_item_create_user', ADMIDIO_URL.FOLDER_MODULES.'/members/members_new.php',
    $gL10n->get('MEM_CREATE_USER'), 'add.png'
);

if($gSettingsManager->getBool('profile_log_edit_fields'))
{
    // show link to view profile field change history
    $membersAdministrationMenu->addItem(
        'menu_item_change_history', ADMIDIO_URL.FOLDER_MODULES.'/members/profile_field_history.php',
        $gL10n->get('MEM_CHANGE_HISTORY'), 'clock.png'
    );
}

// show checkbox to select all users or only active members
if($gSettingsManager->getBool('members_show_all_users'))
{
    $navbarForm = new HtmlForm('navbar_show_all_users_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
    $navbarForm->addCheckbox('mem_show_all', $gL10n->get('MEM_SHOW_ALL_USERS'), $flagShowMembers, array('helpTextIdLabel' => 'MEM_SHOW_USERS_DESC'));
    $membersAdministrationMenu->addForm($navbarForm->show());
}

$membersAdministrationMenu->addItem('menu_item_extras', '', $gL10n->get('SYS_MORE_FEATURES'), '', 'right');

// show link to import users
$membersAdministrationMenu->addItem(
    'menu_item_import_users', ADMIDIO_URL.FOLDER_MODULES.'/members/import.php',
    $gL10n->get('MEM_IMPORT_USERS'), 'database_in.png', 'right', 'menu_item_extras'
);

if($gCurrentUser->isAdministrator())
{
    // show link to maintain profile fields
    $membersAdministrationMenu->addItem(
        'menu_item_maintain_profile_fields', ADMIDIO_URL.FOLDER_MODULES.'/preferences/fields.php',
        $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras'
    );

    if($gSettingsManager->getBool('members_enable_user_relations'))
    {
        // show link to relation types
        $membersAdministrationMenu->addItem(
            'menu_item_maintain_user_relation_types', ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes.php',
            $gL10n->get('SYS_MAINTAIN_USER_RELATION_TYPES'), 'user_administration.png', 'right', 'menu_item_extras'
        );
    }

    // show link to system preferences of weblinks
    $membersAdministrationMenu->addItem(
        'menu_item_preferences_links', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'user_management')),
        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras'
    );
}

$orgName = $gCurrentOrganization->getValue('org_longname');

// Create table object
$membersTable = new HtmlTable('tbl_members', $page, true, true, 'table table-condensed');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_ABR_NO'),
    '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/profile.png"
        alt="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($orgName)).'"
        title="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($orgName)).'" />',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_USER'),
    '<img class="admidio-icon-info" alt="'.$gL10n->get('SYS_GENDER').'" title="" src="'.THEME_URL.'/icons/gender.png" data-original-title="'.$gL10n->get('SYS_GENDER').'">',
    $gL10n->get('SYS_BIRTHDAY'),
    $gL10n->get('MEM_UPDATED_ON'),
    '&nbsp;'
);

$membersTable->setServerSideProcessing(safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_data.php', array('members' => $getMembers)));
$membersTable->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'left', 'left', 'right'));
$membersTable->disableDatatablesColumnsSort(array(1, count($columnHeading))); // disable sort in last column
$membersTable->addRowHeadingByArray($columnHeading);
$membersTable->setDatatablesRowsPerPage($gSettingsManager->getInt('members_users_per_page'));
$membersTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

$page->addHtml($membersTable->show());

// show html of complete page
$page->show();
