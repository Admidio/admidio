<?php
/**
 ***********************************************************************************************
 * Show user profile
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid : Show profile of the user with this uuid. If this parameter is not set then
 *             the profile of the current user will be shown.
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/roles_functions.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

// create user object
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

// check if right to view profile exists
if (!$gCurrentUser->hasRightViewProfile($user)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

/**
 * this function returns the html code for a field with description, formatting the content correctly
 * @param string $fieldNameIntern
 * @param User   $user
 * @return false|array<string,string>
 */
function getFieldCode(string $fieldNameIntern, User $user)
{
    global $gCurrentUser, $gProfileFields, $gL10n;

    if (!$gCurrentUser->allowedViewProfileField($user, $fieldNameIntern)) {
        return false;
    }

    $html = array('label' => '', 'value' => '');

    // get value of field in html format
    $value = $user->getValue($fieldNameIntern, 'html');

    // if birthday then show age
    if ($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') === 'BIRTHDAY' && $value !== '') {
        $birthday = DateTime::createFromFormat('Y-m-d', $user->getValue($fieldNameIntern, 'Y-m-d'));
        $now = new DateTime('now');
        $value = $value. '&nbsp;&nbsp;&nbsp;('. $birthday->diff($now)->y. ' '.$gL10n->get('PRO_YEARS').')';
    } elseif (strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_icon')) > 0) {
        $value = $gProfileFields->getProperty($fieldNameIntern, 'usf_icon') . $value;
    }

    // show html of field, if user has a value for that field, or it's a checkbox field
    if (strlen($user->getValue($fieldNameIntern)) > 0 || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') === 'CHECKBOX') {
        $html['label'] = $gProfileFields->getProperty($fieldNameIntern, 'usf_name');
        $html['value'] = $value;
    }

    return $html;
}

unset($_SESSION['profile_request']);

$userId    = $user->getValue('usr_id');

// set headline
if ($userId === $gCurrentUserId) {
    $headline = $gL10n->get('PRO_MY_PROFILE');
} else {
    $headline = $gL10n->get('PRO_PROFILE_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
}

// if user UUID was not set and own profile should be shown then initialize navigation
if (!isset($_GET['user_uuid'])) {
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-user');
} else {
    $gNavigation->addUrl(CURRENT_URL, $headline);
}

// create html page object
$page = new HtmlPage('admidio-profile', $headline);

$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/zxcvbn/dist/zxcvbn.js');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.js');

$page->addJavascript('
    var profileJS = new ProfileJS(gRootPath);
    profileJS.userUuid                = "'.$getUserUuid.'";

    /**
     * @param {object} element
     */
    function showHideMembershipInformation(element) {
        $("#" + element.attr("id") + "_Content").toggle("fast");
    }

    function callbackProfilePhoto() {
        var imgSrc = $("#profile_photo").attr("src");
        var timestamp = new Date().getTime();
        $("#btn_delete_photo").hide();
        $("#profile_photo").attr("src", imgSrc + "&" + timestamp);
    }

    function callbackRoles() {
        if (profileJS) {
            profileJS.formerRoleCount++;
            profileJS.reloadFormerRoleMemberships();
        }
    }

    function callbackFormerRoles() {
        if (profileJS) {
            profileJS.formerRoleCount--;
            if (profileJS.formerRoleCount === 0) {
                $("#profile_former_roles_box").fadeOut("slow");
            }
        }
    }

    function callbackFutureRoles() {
        if (profileJS) {
            profileJS.futureRoleCount--;
            if (profileJS.futureRoleCount === 0) {
                $("#profile_future_roles_box").fadeOut("slow");
            }
        }
    }

    function formSubmitEvent(rolesAreaId = "") {
        $(rolesAreaId + " .admidio-form-membership-period").submit(function(event) {
            var memberUuid = $(this).attr("data-admidio");
            var formAlert  = $("#membership_period_form_" + memberUuid + " .form-alert");

            event.preventDefault(); // avoid to execute the actual submit of the form.
            formAlert.hide();

            $.post({
                url: $(this).attr("action"),
                data: $(this).serialize(),
                success: function(data)
                {
                    if (data === "success") {
                        formAlert.attr("class", "alert alert-success form-alert");
                        formAlert.html("<i class=\"fas fa-check\"></i><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                        formAlert.fadeIn("slow");
                        formAlert.animate({opacity: 1.0}, 5000);
                        formAlert.fadeOut("slow");

                        var membershipPeriod = $("#membership_period_" + memberUuid);
                        membershipPeriod.animate({opacity: 1.0}, 5000);
                        membershipPeriod.fadeOut("slow");

                        profileJS.reloadRoleMemberships();
                        profileJS.reloadFormerRoleMemberships();
                        profileJS.reloadFutureRoleMemberships();
                        formSubmitEvent();
                    } else {
                        formAlert.attr("class", "alert alert-danger form-alert");
                        formAlert.fadeIn();
                        formAlert.html("<i class=\"fas fa-exclamation-circle\"></i>" + data);
                    }
                }
             });
            return false;
        });
    }
');
$page->addJavascript(
    '
    $(".admMemberInfo").click(function() {
        showHideMembershipInformation($(this))
    });

    $("#menu_item_profile_password").attr("href", "javascript:void(0);");
    $("#menu_item_profile_password").attr("data-href", "'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('user_uuid' => $getUserUuid)).'");
    $("#menu_item_profile_password").attr("class", "nav-link btn btn-secondary openPopup");

    formSubmitEvent();',
    true
);

// if user has right then show link to edit profile
if ($gCurrentUser->hasRightEditProfile($user)) {
    $page->addPageFunctionsMenuItem(
        'menu_item_profile_edit',
        $gL10n->get('PRO_EDIT_PROFILE'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_uuid' => $user->getValue('usr_uuid'))),
        'fa-edit'
    );
}

// Password of own user could be changed
if ($userId === $gCurrentUserId) {
    $page->addPageFunctionsMenuItem(
        'menu_item_profile_password',
        $gL10n->get('SYS_CHANGE_PASSWORD'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('user_uuid' => $getUserUuid)),
        'fa-key'
    );
} elseif ($gCurrentUser->isAdministrator() && isMember($userId) && strlen($user->getValue('usr_login_name')) > 0) {
    // Administrators can change or send password if login is configured and user is member of current organization

    if (strlen($user->getValue('EMAIL')) > 0 && $gSettingsManager->getBool('system_notifications_enabled')) {
        // if email is set and systemmails are activated then administrator can send a new password to user
        $page->addPageFunctionsMenuItem(
            'menu_item_profile_send_password',
            $gL10n->get('ORG_SEND_NEW_PASSWORD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => '5')),
            'fa-key'
        );
    } else {
        // if user has no email or send email is disabled then administrator could set a new password
        $page->addPageFunctionsMenuItem(
            'menu_item_profile_password',
            $gL10n->get('SYS_CHANGE_PASSWORD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('user_uuid' => $getUserUuid)),
            'fa-key'
        );
    }
}

// show link to view profile field change history
if ($gSettingsManager->getBool('profile_log_edit_fields') && $gCurrentUser->hasRightEditProfile($user)) {
    $page->addPageFunctionsMenuItem(
        'menu_item_profile_change_history',
        $gL10n->get('SYS_CHANGE_HISTORY'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/profile_field_history.php', array('user_uuid' => $getUserUuid)),
        'fa-history'
    );
}

// show link to export the profile as vCard
$page->addPageFunctionsMenuItem(
    'menu_item_profile_vcard',
    $gL10n->get('PRO_EXPORT_VCARD'),
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php', array('mode' => '1', 'user_uuid' => $getUserUuid)),
    'fa-file-export'
);

// show link to create relations
if ($gSettingsManager->getBool('members_enable_user_relations') && $gCurrentUser->editUsers()) {
    $page->addPageFunctionsMenuItem(
        'menu_item_profile_user_relation_types',
        $gL10n->get('SYS_CREATE_RELATIONSHIP'),
        SecurityUtils::encodeUrl(ADMIDIO_URL .FOLDER_MODULES.'/userrelations/userrelations_new.php', array('user_uuid' => $getUserUuid)),
        'fa-people-arrows'
    );
}

// *******************************************************************************
// User data block
// *******************************************************************************

$page->addHtml('
<div class="card admidio-field-group" id="user_data_panel">
    <div class="card-header">'.$gL10n->get('SYS_BASIC_DATA').'</div>
    <div class="card-body">
        <div class="row">
        <div class="col-sm-8">');
            // create a static form
            $form = new HtmlForm('profile_basic_data_form');

            $bNameOutput = false;    // Flag whether the address has already been displayed
            $bAddressOutput = false;    // Flag whether the address has already been displayed

            // Loop over all fields of the master data

            foreach ($gProfileFields->getProfileFields() as $field) {
                // Display only fields of the basic data
                if ($field->getValue('cat_name_intern') === 'BASIC_DATA' && $gCurrentUser->allowedViewProfileField($user, $field->getValue('usf_name_intern'))) {
                    switch ($field->getValue('usf_name_intern')) {
                        case 'LAST_NAME': // fallthrough
                        case 'FIRST_NAME': // fallthrough
                        case 'GENDER':
                            if (!$bNameOutput) {
                                $bNameOutput = true;
                                // add lastname and firstname
                                if (strlen($user->getValue('GENDER')) > 0 && $gCurrentUser->allowedViewProfileField($user, 'GENDER')) {
                                    // Icon des Geschlechts anzeigen, wenn noetigen Rechte vorhanden
                                    $form->addStaticControl('name', $gL10n->get('SYS_NAME'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME') . ' ' . $user->getValue('GENDER', 'html'));
                                } else {
                                    $form->addStaticControl('name', $gL10n->get('SYS_NAME'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
                                }

                                // add login name
                                if (strlen($user->getValue('usr_login_name')) > 0) {
                                    $userName = '';

                                    if ($userId !== $gCurrentUserId && $gSettingsManager->getBool('enable_pm_module')) {
                                        $userName .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_type' => 'PM', 'user_uuid' => $getUserUuid)) . '" title="' . $gL10n->get('SYS_WRITE_PM') . '">' .
                                            '<i class="fas fa-comment-alt"></i>' . $user->getValue('usr_login_name') . '</a>';
                                    } else {
                                        $userName .= $user->getValue('usr_login_name');
                                    }

                                    if (!empty($user->getValue('usr_actual_login'))
                                        && ($userId === $gCurrentUserId || $gCurrentUser->isAdministrator())) {
                                        $userName .= HtmlForm::getHelpTextIcon($gL10n->get('SYS_LAST_LOGIN_ON', array($user->getValue('usr_actual_login', $gSettingsManager->getString('system_date')), $user->getValue('usr_actual_login', $gSettingsManager->getString('system_time')))));
                                    }

                                    $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'), $userName);
                                } else {
                                    $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'), $gL10n->get('SYS_NOT_REGISTERED'));
                                }
                            }
                            break;

                        case 'STREET': // fallthrough
                        case 'POSTCODE': // fallthrough
                        case 'CITY': // fallthrough
                        case 'COUNTRY':
                            $street   = $user->getValue('STREET');
                            $postcode = $user->getValue('POSTCODE');
                            $city     = $user->getValue('CITY');
                            $country  = $user->getValue('COUNTRY');

                            if (!$bAddressOutput // output of address only once
                            && (strlen($street) > 0 || strlen($postcode) > 0 || strlen($city) > 0 || strlen($country) > 0)) {
                                $bAddressOutput = true;
                                $urlParam = array();
                                $address  = array();

                                if (strlen($street) > 0 && $gCurrentUser->allowedViewProfileField($user, 'STREET')) {
                                    $urlParam[] = $street;
                                    $address[] = $street;
                                }

                                // City and postcode should be shown in one line
                                if (strlen($postcode) > 0 && $gCurrentUser->allowedViewProfileField($user, 'POSTCODE')) {
                                    $urlParam[] = $postcode;

                                    if (strlen($city) > 0 && $gCurrentUser->allowedViewProfileField($user, 'CITY')) {
                                        $urlParam[] = $city;

                                        // some countries have the order postcode city others have city postcode
                                        if ((int) $gProfileFields->getProperty('CITY', 'usf_sequence') > (int) $gProfileFields->getProperty('POSTCODE', 'usf_sequence')) {
                                            $address[] = $postcode. ' '. $city;
                                        } else {
                                            $address[] = $city. ' '. $postcode;
                                        }
                                    }
                                } elseif (strlen($city) > 0 && $gCurrentUser->allowedViewProfileField($user, 'CITY')) {
                                    $urlParam[] = $city;
                                    $address[] = $city;
                                }

                                if (strlen($country) > 0 && $gCurrentUser->allowedViewProfileField($user, 'COUNTRY')) {
                                    $urlParam[] = $country;
                                    $address[] = $country;
                                }

                                $urlParam = implode(', ', $urlParam);
                                $origin = implode(', ', array(
                                    $gCurrentUser->getValue('STREET'), $gCurrentUser->getValue('POSTCODE'),
                                    $gCurrentUser->getValue('CITY'), $gCurrentUser->getValue('COUNTRY')
                                ));
                                $mapUrl   = SecurityUtils::encodeUrl('https://www.google.com/maps/search/', array('api' => 1, 'query' => $urlParam));
                                $routeUrl = SecurityUtils::encodeUrl('https://www.google.com/maps/dir/', array('api' => 1, 'origin' => $origin, 'destination' => $urlParam));

                                $address = implode('<br />', $address) . '<br />';

                                // show route or address link if function is enabled and user has filled address or city
                                if ($gSettingsManager->getBool('profile_show_map_link') && strlen($street) > 0
                                && (strlen($postcode) > 0 || strlen($city) > 0)) {
                                    $address .= '
                                        <a class="admidio-icon-link" href="'. $mapUrl. '" target="_blank" title="'.$gL10n->get('SYS_MAP_LINK_HOME_DESC').'">
                                            <i class="fas fa-map-marker-alt"></i>'.$gL10n->get('SYS_MAP').'</a>';

                                    // show route link if it's not the profile of CurrentUser
                                    if ($userId !== $gCurrentUserId) {
                                        $address .= ' - <a href="'.$routeUrl.'" target="_blank" title="'.$gL10n->get('SYS_MAP_LINK_ROUTE_DESC').'">'.$gL10n->get('SYS_SHOW_ROUTE').'</a>';
                                    }
                                }

                                $form->addStaticControl('address', $gL10n->get('SYS_ADDRESS'), $address);
                            }
                            break;

                        default:
                            $fieldNameIntern = $field->getValue('usf_name_intern');
                            $field = getFieldCode($fieldNameIntern, $user);
                            if (is_array($field) && $field['value'] !== '') {
                                $form->addStaticControl(strtolower($fieldNameIntern), $field['label'], $field['value']);
                            }
                    }
                }
            }
            $page->addHtml($form->show());
        $page->addHtml('</div>
        <div class="col-sm-4 text-right" id="div_profile_photo">');

            // *******************************************************************************
            // Profile photo
            // *******************************************************************************

            $page->addHtml('<img id="profile_photo" class="rounded" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid)).'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');

            // Only authorized users are allowed to edit the profile photo
            if ($gCurrentUser->hasRightEditProfile($user)) {
                $page->addHtml('
                <ul id="profile_picture_links" class="list-unstyled">
                    <li><a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('user_uuid' => $getUserUuid)).'">
                        <i class="fas fa-upload"></i>'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'</a></li>');

                // the image can only be deleted if corresponding rights exist
                if (((string) $user->getValue('usr_photo') !== '' && (int) $gSettingsManager->get('profile_photo_storage') === 0)
                        || is_file(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/'.$userId.'.jpg') && (int) $gSettingsManager->get('profile_photo_storage') === 1) {
                    $page->addHtml('<li><a id="btn_delete_photo" class="admidio-icon-link openPopup" href="javascript:void(0);"
                                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'pro_pho', 'element_id' => 'no_element', 'database_id' => $getUserUuid)).
                                        '"><i class="fas fa-trash-alt"></i>'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'</a></li>');
                }
                $page->addHtml('</ul>');
            }
        $page->addHtml('</div>
        </div>
    </div>
</div>');

// *******************************************************************************
// Loop over all categories and profile fields except the basic data
// *******************************************************************************

$category = '';
foreach ($gProfileFields->getProfileFields() as $field) {
    $fieldNameIntern = $field->getValue('usf_name_intern');

    // Fields of the category basic data was already shown, now show all other hidden
    // fields only if you have the right to edit this profile
    if ($field->getValue('cat_name_intern') !== 'BASIC_DATA' && $gCurrentUser->allowedViewProfileField($user, $fieldNameIntern)) {
        // show new category header if new category and field has value or is a checkbox field
        if ($category !== $field->getValue('cat_name')
        && (strlen($user->getValue($fieldNameIntern)) > 0 || $field->getValue('usf_type') === 'CHECKBOX')) {
            if ($category !== '') {
                // new category then show last form and close div container
                $page->addHtml($form->show());
                $page->addHtml('</div></div>');
            }
            $category = $field->getValue('cat_name');

            $page->addHtml('
                <div class="card admidio-field-group" id="'.$field->getValue('cat_name_intern').'_data_panel">
                    <div class="card-header">'.$field->getValue('cat_name').'</div>
                    <div class="card-body">');

            // create a static form
            $form = new HtmlForm('profile_'.$field->getValue('cat_name_intern').'_form');
        }

        // show html of field, if user has a value for that field, or it's a checkbox field
        if (strlen($user->getValue($fieldNameIntern)) > 0 || $field->getValue('usf_type') === 'CHECKBOX') {
            $field = getFieldCode($fieldNameIntern, $user);
            if (is_array($field) && $field['value'] !== '') {
                $form->addStaticControl(strtolower($fieldNameIntern), $field['label'], $field['value']);
            }
        }
    }
}

if ($category !== '') {
    // new category then show last form and close div container
    $page->addHtml($form->show());
    $page->addHtml('</div></div>');
}

if ($gSettingsManager->getBool('profile_show_roles')) {
    // *******************************************************************************
    // Authorizations block
    // *******************************************************************************

    // Array with all permissions
    $rolesRights = array(
        'rol_all_lists_view',
        'rol_announcements',
        'rol_approve_users',
        'rol_assign_roles',
        'rol_dates',
        'rol_documents_files',
        'rol_edit_user',
        'rol_guestbook',
        'rol_guestbook_comments',
        'rol_mail_to_all',
        'rol_photo',
        'rol_profile',
        'rol_weblinks'
    );

    $rightsOrigin = array();

    // Abfragen der aktiven Rollen mit Berechtigung und Schreiben in ein Array
    foreach ($rolesRights as $rolesRightsDbName) {
        $sql = 'SELECT rol_name
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid  = true
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND mem_usr_id = ? -- $userId
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )
                   AND '.$rolesRightsDbName.' = true
              ORDER BY cat_org_id, cat_sequence, rol_name';
        $queryParams = array(DATE_NOW, DATE_NOW, $userId, $gCurrentOrgId);
        $roleStatement = $gDb->queryPrepared($sql, $queryParams);

        $roles = array();
        while ($roleName = $roleStatement->fetchColumn()) {
            $roles[] = $roleName;
        }

        if (count($roles) > 0) {
            $rightsOrigin[$rolesRightsDbName] = implode(', ', $roles);
        }
    }

    $page->addHtml('
    <div class="card admidio-field-group" id="profile_authorizations_box">
        <div class="card-header">'.$gL10n->get('SYS_PERMISSIONS').'</div>
        <div class="card-body" id="profile_authorizations_box_body">
            <div class="row">');

    if (count($rightsOrigin) > 0) {
        $profileRightsArray = array();

        if ($user->checkRolesRight('rol_assign_roles')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_assign_roles'],
                'right' => $gL10n->get('SYS_RIGHT_ASSIGN_ROLES'),
                'icon'  => 'fa-users'
            );
        }
        if ($user->checkRolesRight('rol_approve_users')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_approve_users'],
                'right' => $gL10n->get('SYS_RIGHT_APPROVE_USERS'),
                'icon'  => 'fa-address-card'
            );
        }
        if ($user->checkRolesRight('rol_edit_user')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_edit_user'],
                'right' => $gL10n->get('SYS_RIGHT_EDIT_USER'),
                'icon'  => 'fa-users-cog'
            );
        }

        if ($user->checkRolesRight('rol_mail_to_all')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_mail_to_all'],
                'right' => $gL10n->get('SYS_RIGHT_MAIL_TO_ALL'),
                'icon'  => 'fa-envelope'
            );
        }
        if ($user->checkRolesRight('rol_profile')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_profile'],
                'right' => $gL10n->get('SYS_RIGHT_PROFILE'),
                'icon'  => 'fa-user'
            );
        }
        if ($user->checkRolesRight('rol_announcements') && (int) $gSettingsManager->get('enable_announcements_module') > 0) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_announcements'],
                'right' => $gL10n->get('SYS_RIGHT_ANNOUNCEMENTS'),
                'icon'  => 'fa-newspaper'
            );
        }
        if ($user->checkRolesRight('rol_dates') && (int) $gSettingsManager->get('enable_dates_module') > 0) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_dates'],
                'right' => $gL10n->get('SYS_RIGHT_DATES'),
                'icon'  => 'fa-calendar-alt'
            );
        }
        if ($user->checkRolesRight('rol_photo') && (int) $gSettingsManager->get('enable_photo_module') > 0) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_photo'],
                'right' => $gL10n->get('SYS_RIGHT_PHOTOS'),
                'icon'  => 'fa-image'
            );
        }
        if ($user->checkRolesRight('rol_documents_files') && (int) $gSettingsManager->getBool('documents_files_enable_module')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_documents_files'],
                'right' => $gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'),
                'icon'  => 'fa-download'
            );
        }
        if ($user->checkRolesRight('rol_guestbook') && (int) $gSettingsManager->get('enable_guestbook_module') > 0) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_guestbook'],
                'right' => $gL10n->get('SYS_RIGHT_GUESTBOOK'),
                'icon'  => 'fa-book'
            );
        }
        if ($user->checkRolesRight('rol_guestbook_comments') && (int) $gSettingsManager->get('enable_guestbook_module') > 0) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_guestbook_comments'],
                'right' => $gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS'),
                'icon'  => 'fa-comment'
            );
        }
        if ($user->checkRolesRight('rol_weblinks') && (int) $gSettingsManager->get('enable_weblinks_module') > 0) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_weblinks'],
                'right' => $gL10n->get('SYS_RIGHT_WEBLINKS'),
                'icon'  => 'fa-link'
            );
        }
        if ($user->checkRolesRight('rol_all_lists_view')) {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_all_lists_view'],
                'right' => $gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'),
                'icon'  => 'fa-list'
            );
        }

        foreach ($profileRightsArray as $profileRight) {
            $page->addHtml('<div class="col-sm-6 col-md-4 admidio-profile-user-right" data-toggle="popover" data-html="true"
                data-trigger="hover click" data-placement="auto" data-content="'.$gL10n->get('PRO_ASSIGNED_BY_ROLES'). ':
                <strong>'. $profileRight['roles'].'</strong>"><i class="fas ' . $profileRight['icon'] . '"></i>'. $profileRight['right']. '</div>');
        }
    } else {
        $page->addHtml('<div class="col-sm-12">'.$gL10n->get('PRO_NO_PERMISSIONS_ASSIGNED').'</div>');
    }

    $page->addHtml('
            </div>
        </div>
    </div>');

    // *******************************************************************************
    // Current roles assignments
    // *******************************************************************************

    // List all roles assigned to the member
    $roleStatement  = getRolesFromDatabase($userId);
    $countRole      = $roleStatement->rowCount();

    $page->addHtml('
    <div class="card admidio-field-group" id="profile_roles_box">
        <div class="card-header">'.$gL10n->get('SYS_ROLE_MEMBERSHIPS'));
    // if you have the right to assign roles then show the link to assign new roles to this user
    if ($gCurrentUser->assignRoles()) {
        $page->addHtml('<a class="btn btn-secondary float-right openPopup" id="profile_role_memberships_change" data-class="modal-lg"
                    href="javascript:void(0);" data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php', array('user_uuid' => $getUserUuid, 'inline' => '1')).'">
                    <i class="fas fa-edit"></i>' . $gL10n->get('SYS_EDIT') . '</a>');
    }
    $page->addHtml('</div>
        <div class="card-body" id="profile_roles_box_body">
            '.getRoleMemberships('role_list', $user, $roleStatement).'
        </div>
    </div>');

    // *******************************************************************************
    // Future memberships assignments
    // *******************************************************************************

    $roleStatement  = getFutureRolesFromDatabase($userId);
    $countRole      = $roleStatement->rowCount();
    $visible        = '';

    if ($countRole === 0) {
        $visible = ' style="display: none;" ';
    } else {
        $page->addHtml('<script type="text/javascript">profileJS.futureRoleCount="'.$countRole.'";</script>');
    }

    $page->addHtml('
    <div class="card admidio-field-group" id="profile_future_roles_box" '.$visible.'>
        <div class="card-header">'.$gL10n->get('PRO_FUTURE_ROLE_MEMBERSHIP').'</div>
        <div class="card-body" id="profile_future_roles_box_body">
            '.getRoleMemberships('future_role_list', $user, $roleStatement).'
        </div>
    </div>');
}

if ($gSettingsManager->getBool('profile_show_former_roles')) {
    // *******************************************************************************
    // Former roles assignments
    // *******************************************************************************

    // List all roles that were assigned to the member

    $roleStatement  = getFormerRolesFromDatabase($userId);
    $countRole      = $roleStatement->rowCount();
    $visible        = '';

    if ($countRole === 0) {
        $visible = ' style="display: none;" ';
    } else {
        $page->addHtml('<script type="text/javascript">profileJS.formerRoleCount="'.$countRole.'";</script>');
    }

    $page->addHtml('
    <div class="card admidio-field-group" id="profile_former_roles_box" '.$visible.'>
        <div class="card-header">'.$gL10n->get('PRO_FORMER_ROLE_MEMBERSHIP').'</div>
        <div class="card-body" id="profile_former_roles_box_body">
            '.getRoleMemberships('former_role_list', $user, $roleStatement).'
        </div>
    </div>');
}

if ($gSettingsManager->getBool('profile_show_extern_roles')
&& ($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->isParentOrganization())) {
    // *******************************************************************************
    // Block with roles from other organizations
    // *******************************************************************************

    // list all roles where the viewed user has an active membership
    $sql = 'SELECT *
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
        INNER JOIN '.TBL_ORGANIZATIONS.'
                ON org_id = cat_org_id
             WHERE mem_usr_id  = ? -- $userId
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end    >= ? -- DATE_NOW
               AND rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND org_id     <> ? -- $gCurrentOrgId
          ORDER BY org_shortname, cat_sequence, rol_name';
    $roleStatement = $gDb->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $gCurrentOrgId));

    if ($roleStatement->rowCount() > 0) {
        $showRolesOtherOrganizations = false;
        $actualOrganization = 0;
        $role = new TableRoles($gDb);

        while ($row = $roleStatement->fetch()) {
            $orgId = (int) $row['org_id'];

            // if roles of new organization than read the rights of this organization
            if ($actualOrganization !== $orgId) {
                $gCurrentUser->setOrganization($orgId);
                $actualOrganization = $orgId;
            }

            // check if current user has right to view the role of that organization
            if ($gCurrentUser->hasRightViewRole($orgId)) {
                $role->clear();
                $role->setArray($row);

                if (!$showRolesOtherOrganizations) {
                    $page->addHtml('
                    <div class="card admidio-field-group" id="profile_other_orga_roles_box">
                        <div class="card-header">'.
                            $gL10n->get('PRO_ROLE_MEMBERSHIP_OTHER_ORG').HtmlForm::getHelpTextIcon('PRO_VIEW_ROLES_OTHER_ORGAS').'
                        </div>
                        <div class="card-body" id="profile_other_orga_roles_box_body">
                            <ul class="list-group admidio-list-roles-assign">');

                    $showRolesOtherOrganizations = true;
                }

                $startDate = DateTime::createFromFormat('Y-m-d', $row['mem_begin']);
                // jede einzelne Rolle anzeigen
                $page->addHtml('
                <li class="list-group-item">
                    <span>'.
                        $row['org_shortname'].' - '.$role->getValue('cat_name').' - '.$role->getValue('rol_name'));

                if ($row['mem_leader'] == 1) {
                    $page->addHtml(' - '.$gL10n->get('SYS_LEADER'));
                }
                $page->addHtml('&nbsp;
                    </span>
                    <span class="float-right">'.$gL10n->get('SYS_SINCE', array($startDate->format($gSettingsManager->getString('system_date')))).'</span>
                </li>');
            }
        }

        $gCurrentUser->setOrganization($gCurrentOrgId);

        if ($showRolesOtherOrganizations) {
            $page->addHtml('</ul></div></div>');
        }
    }
}

if ($gSettingsManager->getBool('members_enable_user_relations')) {
    // *******************************************************************************
    // user relations block
    // *******************************************************************************
    $sql = 'SELECT COUNT(*) AS count
              FROM ' . TBL_USER_RELATIONS . '
        INNER JOIN ' . TBL_USER_RELATION_TYPES . '
                ON ure_urt_id  = urt_id
             WHERE ure_usr_id1 = ? -- $userId
               AND urt_name        <> \'\'
               AND urt_name_male   <> \'\'
               AND urt_name_female <> \'\'';
    $statement = $gDb->queryPrepared($sql, array($userId));
    $count = (int) $statement->fetchColumn();

    if ($count > 0) {
        $page->addHtml('
        <div class="card admidio-field-group" id="profile_user_relations_box">
            <div class="card-header">' . $gL10n->get('SYS_USER_RELATIONS'));
        // show link to create relations
        if ($gSettingsManager->getBool('members_enable_user_relations') && $gCurrentUser->editUsers()) {
            $page->addHtml('
                        <a class="admidio-icon-link float-right" id="profile_relations_new_entry" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL .FOLDER_MODULES.'/userrelations/userrelations_new.php', array('user_uuid' => $getUserUuid)).'">
                            <i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_CREATE_RELATIONSHIP').'"></i></a>');
        }
        $page->addHtml('</div>
            <div class="card-body" id="profile_user_relations_box_body">');

        $sql = 'SELECT *
                  FROM '.TBL_USER_RELATIONS.'
            INNER JOIN '.TBL_USER_RELATION_TYPES.'
                    ON ure_urt_id  = urt_id
                 WHERE ure_usr_id1 = ? -- $userId
                   AND urt_name        <> \'\'
                   AND urt_name_male   <> \'\'
                   AND urt_name_female <> \'\'
              ORDER BY urt_name';
        $relationStatement = $gDb->queryPrepared($sql, array($userId));

        $relationType = new TableUserRelationType($gDb);
        $relation     = new TableUserRelation($gDb);
        $otherUser    = new User($gDb, $gProfileFields);

        $page->addHtml('<ul class="list-group admidio-list-roles-assign">');

        while ($row = $relationStatement->fetch()) {
            $editUserIcon = '';
            $relationType->clear();
            $relationType->setArray($row);
            $relation->clear();
            $relation->setArray($row);
            $otherUser->clear();
            $otherUser->readDataById($relation->getValue('ure_usr_id2'));

            $relationName = $relationType->getValue('urt_name');
            if ($otherUser->getValue('GENDER', 'text') === $gL10n->get('SYS_MALE')) {
                $relationName = $relationType->getValue('urt_name_male');
            } elseif ($otherUser->getValue('GENDER', 'text') === $gL10n->get('SYS_FEMALE')) {
                $relationName = $relationType->getValue('urt_name_female');
            }

            if ($gCurrentUser->hasRightEditProfile($otherUser)) {
                $editUserIcon = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_uuid' => $otherUser->getValue('usr_uuid'))) . '"><i
                    class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT_USER_IN_RELATION').'"></i></a>';
            }

            $page->addHtml('<li id="row_ure_'.(int) $relation->getValue('ure_id').'" class="list-group-item">');
            $page->addHtml('<div>');
            $page->addHtml('<span>'.$relationName.' - <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $otherUser->getValue('usr_uuid'))).
                           '">'.$otherUser->getValue('FIRST_NAME') . ' ' . $otherUser->getValue('LAST_NAME').'</a> ' . $editUserIcon . '<span>');
            $page->addHtml('<span class="float-right text-right">');

            if ($gCurrentUser->editUsers()) {
                $page->addHtml('<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'ure', 'element_id' => 'row_ure_'.(int) $relation->getValue('ure_id'), 'database_id' => (int) $relation->getValue('ure_id'),
                                'name' => $relationType->getValue('urt_name').': '.$otherUser->getValue('FIRST_NAME').' '.$otherUser->getValue('LAST_NAME').' -> '.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'))).'"><i
                                class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('PRO_CANCEL_USER_RELATION').'"></i></a>');
            }

            // only show info if system setting is activated
            if ((int) $gSettingsManager->get('system_show_create_edit') > 0) {
                $page->addHtml('<a class="admidio-icon-link admMemberInfo" id="relation_info_'.(int) $relation->getValue('ure_id').'" href="javascript:void(0)"><i
                    class="fas fa-info-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_INFORMATIONS').'"></i></a>');
            }

            $page->addHtml('</span></div>');
            if ((int) $gSettingsManager->get('system_show_create_edit') > 0) {
                $page->addHtml(
                    '<div id="relation_info_'.(int) $relation->getValue('ure_id').'_Content" style="display: none;">'.
                    admFuncShowCreateChangeInfoById(
                        (int) $relation->getValue('ure_usr_id_create'),
                        $relation->getValue('ure_timestamp_create'),
                        (int) $relation->getValue('ure_usr_id_change'),
                        $relation->getValue('ure_timestamp_change')
                    ).
                    '</div>'
                );
            }
            $page->addHtml('</li>');
        }

        $page->addHtml('</ul>');

        $page->addHtml('
            </div>
        </div>');
    }
}

// show information about user who creates the recordset and changed it
$page->addHtml(admFuncShowCreateChangeInfoById(
    (int) $user->getValue('usr_usr_id_create'),
    $user->getValue('usr_timestamp_create'),
    (int) $user->getValue('usr_usr_id_change'),
    $user->getValue('usr_timestamp_change')
));

$page->show();
