<?php
/**
 ***********************************************************************************************
 * Show and manage all members of the organization
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * members - false : (Default) Show only active members of the current organization
 *           true  : Show active and inactive members of all organizations in database
 ***********************************************************************************************
 */
require_once('../../system/common.php');

unset($_SESSION['import_request']);

// Initialize and check the parameters
$getMembers = admFuncVariableIsValid($_GET, 'members', 'bool', array('defaultValue' => true));

// if only active members should be shown then set parameter
if($gPreferences['members_show_all_users'] == 0)
{
    $getMembers = true;
}

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
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
        window.location.replace("'.$g_root_path.'/adm_program/modules/members/members.php?members='.(int) $flagShowMembers.'");
    });', true);

// get module menu
$membersAdministrationMenu = $page->getMenu();

$membersAdministrationMenu->addItem('menu_item_create_user', $g_root_path.'/adm_program/modules/members/members_new.php', $gL10n->get('MEM_CREATE_USER'), 'add.png');

if($gPreferences['profile_log_edit_fields'] == 1)
{
    // show link to view profile field change history
    $membersAdministrationMenu->addItem('menu_item_change_history', $g_root_path.'/adm_program/modules/members/profile_field_history.php',
                                $gL10n->get('MEM_CHANGE_HISTORY'), 'clock.png');
}

// show checkbox to select all users or only active members
if($gPreferences['members_show_all_users'] == 1)
{
    $navbarForm = new HtmlForm('navbar_show_all_users_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
    $navbarForm->addCheckbox('mem_show_all', $gL10n->get('MEM_SHOW_ALL_USERS'), $flagShowMembers, array('helpTextIdLabel' => 'MEM_SHOW_USERS_DESC'));
    $membersAdministrationMenu->addForm($navbarForm->show(false));
}

$membersAdministrationMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');

// show link to import users
$membersAdministrationMenu->addItem('menu_item_import_users', $g_root_path.'/adm_program/modules/members/import.php',
                            $gL10n->get('MEM_IMPORT_USERS'), 'database_in.png', 'right', 'menu_item_extras');

if($gCurrentUser->isAdministrator())
{
    // show link to maintain profile fields
    $membersAdministrationMenu->addItem('menu_item_maintain_profile_fields', $g_root_path.'/adm_program/modules/preferences/fields.php',
                                $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras');

    // show link to system preferences of weblinks
    $membersAdministrationMenu->addItem('menu_item_preferences_links', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=user_management',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras');
}

$orgName = $gCurrentOrganization->getValue('org_longname');

// Create table object
$membersTable = new HtmlTable('tbl_members', $page, true, true, 'table table-condensed');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_ABR_NO'),
    '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/profile.png"
        alt="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $orgName).'"
        title="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $orgName).'" />',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_USER'),
    '<img class="admidio-icon-info" alt="'.$gL10n->get('SYS_GENDER').'" title="" src="'.THEME_PATH.'/icons/gender.png" data-original-title="'.$gL10n->get('SYS_GENDER').'">',
    $gL10n->get('SYS_BIRTHDAY'),
    $gL10n->get('MEM_UPDATED_ON'),
    '&nbsp;'
);

$membersTable->setServerSideProcessing($g_root_path.'/adm_program/modules/members/members_data.php');
$membersTable->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'left', 'left', 'right'));
$membersTable->disableDatatablesColumnsSort(count($columnHeading)); // disable sort in last column
$membersTable->addRowHeadingByArray($columnHeading);
$membersTable->setDatatablesRowsPerPage($gPreferences['members_users_per_page']);
$membersTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

$rowNumber = 0; // Zahler fuer die jeweilige Zeile
/*
while($row = $mglStatement->fetch())
{
    ++$rowNumber;

    $memberOfThisOrganization  = (bool) $row['member_this_orga'];
    $memberOfOtherOrganization = (bool) $row['member_other_orga'];

    // Create row and add first column "Rownumber"
    $columnValues = array($rowNumber);

    // Add icon for "Orgamitglied" or "Nichtmitglied"
    if($memberOfThisOrganization)
    {
        $icon = 'profile.png';
        $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $orgName);
    }
    else
    {
        $icon = 'no_profile.png';
        $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', $orgName);
    }

    $columnValues[] = array(
        'value' => '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'"><img
             src="'.THEME_PATH.'/icons/'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></a>',
        'order' => (int) $memberOfThisOrganization
    );

    // Add "Lastname" and "Firstname"
    $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a>';

    // Add "Loginname"
    if(strlen($row['usr_login_name']) > 0)
    {
        $columnValues[] = $row['usr_login_name'];
    }
    else
    {
        $columnValues[] = '';
    }

    // Add icon for "gender"
    if(strlen($row['gender']) > 0)
    {
        // show selected text of optionfield or combobox
        $arrListValues  = $gProfileFields->getProperty('GENDER', 'usf_value_list');
        $columnValues[] = array('value' => $arrListValues[$row['gender']], 'order' => $row['gender']);
    }
    else
    {
        $columnValues[] = array('value' => '', 'order' => '0');
    }

    // Add "birthday"
    if(strlen($row['birthday']) > 0)
    {
        // date must be formated
        $date = DateTime::createFromFormat('Y-m-d', $row['birthday']);
        $columnValues[] = $date->format($gPreferences['system_date']);
    }
    else
    {
        $columnValues[] = '';
    }

    // Add "change date"
    $timestampChange = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
    $columnValues[]  = $timestampChange->format($gPreferences['system_date'].' '.$gPreferences['system_time']);

    // Add "user-administration icons"
    $userAdministration = '';

    // Administrators can change or send password if login is configured and user is member of current organization
    if($memberOfThisOrganization && $gCurrentUser->isAdministrator()
    && strlen($row['usr_login_name']) > 0 && $row['usr_id'] != $gCurrentUser->getValue('usr_id'))
    {
        if(strlen($row['email']) > 0 && $gPreferences['enable_system_mails'] == 1)
        {
            // if email is set and systemmails are activated then administrators can send a new password to user
            $userAdministration = '
            <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='.$row['usr_id'].'&amp;mode=5"><img
                src="'.THEME_PATH.'/icons/key.png" alt="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" title="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" /></a>';
        }
        else
        {
            // if user has no email or send email is disabled then administrators could set a new password
            $userAdministration = '
            <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal" href="'.$g_root_path.'/adm_program/modules/profile/password.php?usr_id='.$row['usr_id'].'"><img
                src="'.THEME_PATH.'/icons/key.png" alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" /></a>';
        }
    }

    if(strlen($row['email']) > 0)
    {
        if($gPreferences['enable_mail_module'] != 1)
        {
            $mailLink = 'mailto:'.$row['email'];
        }
        else
        {
            $mailLink = $g_root_path.'/adm_program/modules/messages/messages_write.php?usr_id='.$row['usr_id'];
        }

        $userAdministration .= '<a class="admidio-icon-link" href="'.$mailLink.'"><img src="'.THEME_PATH.'/icons/email.png"
                                alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', $row['email']).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', $row['email']).'" /></a>';
    }

    // Link um User zu editieren
    // es duerfen keine Nicht-Mitglieder editiert werden, die Mitglied in einer anderen Orga sind
    if($memberOfThisOrganization || !$memberOfOtherOrganization)
    {
        $userAdministration .= '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='.$row['usr_id'].'"><img
                                    src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('MEM_EDIT_USER').'" title="'.$gL10n->get('MEM_EDIT_USER').'" /></a>';
    }

    // Mitglieder entfernen
    if(((!$memberOfOtherOrganization && $gCurrentUser->isAdministrator()) // kein Mitglied einer anderen Orga, dann duerfen Administratoren loeschen
        || $memberOfThisOrganization)                              // aktive Mitglieder duerfen von berechtigten Usern entfernt werden
        && $row['usr_id'] != $gCurrentUser->getValue('usr_id'))       // das eigene Profil darf keiner entfernen
    {
        $userAdministration .= '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='.$row['usr_id'].'&amp;mode=6"><img
                                    src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" title="'.$gL10n->get('MEM_REMOVE_USER').'" /></a>';
    }

    $columnValues[] = $userAdministration;

    $membersTable->addRowByArray($columnValues);
}*/

$page->addHtml($membersTable->show());

// show html of complete page
$page->show();
