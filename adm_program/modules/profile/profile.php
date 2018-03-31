<?php
/**
 ***********************************************************************************************
 * Show user profile
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_id : Show profile of the user with this is. If this parameter is not set then
 *           the profile of the current log will be shown.
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/roles_functions.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'user_id', 'int', array('defaultValue' => (int) $gCurrentUser->getValue('usr_id')));

// create user object
$user = new User($gDb, $gProfileFields, $getUserId);

// Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->hasRightViewProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

/**
 * diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder dabei wird der Inhalt richtig formatiert
 * @param string $fieldNameIntern
 * @param User   $user
 * @return false|array<string,string>
 */
function getFieldCode($fieldNameIntern, User $user)
{
    global $gCurrentUser, $gProfileFields, $gL10n;

    if(!$gCurrentUser->allowedViewProfileField($user, $fieldNameIntern))
    {
        return false;
    }

    $html = array('label' => '', 'value' => '');

    // get value of field in html format
    $value = $user->getValue($fieldNameIntern, 'html');

    // if birthday then show age
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') === 'BIRTHDAY' && $value !== '')
    {
        $birthday = \DateTime::createFromFormat('Y-m-d', $user->getValue($fieldNameIntern, 'Y-m-d'));
        $now = new \DateTime('now');
        $value = $value. '&nbsp;&nbsp;&nbsp;('. $birthday->diff($now)->y. ' '.$gL10n->get('PRO_YEARS').')';
    }
    elseif(strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_icon')) > 0)
    {
        $value = $gProfileFields->getProperty($fieldNameIntern, 'usf_icon').'&nbsp;&nbsp;'. $value;
    }

    // show html of field, if user has a value for that field or it's a checkbox field
    if(strlen($user->getValue($fieldNameIntern)) > 0 || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') === 'CHECKBOX')
    {
        $html['label'] = $gProfileFields->getProperty($fieldNameIntern, 'usf_name');
        $html['value'] = $value;
    }

    return $html;
}

unset($_SESSION['profile_request']);

$userId    = (int) $user->getValue('usr_id');
$currUsrId = (int) $gCurrentUser->getValue('usr_id');

// set headline
if($userId === $currUsrId)
{
    $headline = $gL10n->get('PRO_MY_PROFILE');
}
else
{
    $headline = $gL10n->get('PRO_PROFILE_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
}

// if user id was not set and own profile should be shown then initialize navigation
if(!isset($_GET['user_id']))
{
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap-datepicker/dist/js/bootstrap-datepicker.js');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.'.$gL10n->getLanguageIsoCode().'.min.js');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/zxcvbn/dist/zxcvbn.js');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.js');

$page->addJavascript('
    var profileJS = new ProfileJS(gRootPath);
    profileJS.deleteRole_ConfirmText  = "'.$gL10n->get('ROL_MEMBERSHIP_DEL', array('[rol_name]')).'";
    profileJS.deleteFRole_ConfirmText = "'.$gL10n->get('ROL_LINK_MEMBERSHIP_DEL', array('[rol_name]')).'";
    profileJS.userId                  = '.$userId.';

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

    function formSubmitEvent() {
        $(".button-membership-period-form").click(function(event) {
            var memberId  = $(this).attr("data-admidio");
            var dateStart = $("#membership_start_date_" + memberId).val();
            var dateEnd   = $("#membership_end_date_" + memberId).val();
            var action    = $("#membership_period_form_" + memberId).attr("action") + "&membership_start_date_" + memberId + "=" + dateStart + "&membership_end_date_" + memberId + "=" + dateEnd;

            var formAlert = $("#membership_period_form_" + memberId + " .form-alert");
            formAlert.hide();

            $.get({
                url: action,
                success: function(data) {
                    if (data === "success") {
                        formAlert.attr("class", "alert alert-success form-alert");
                        formAlert.html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                        formAlert.fadeIn("slow");
                        formAlert.animate({opacity: 1.0}, 2500);
                        formAlert.fadeOut("slow");

                        var membershipPeriod = $("#membership_period_" + memberId);
                        membershipPeriod.animate({opacity: 1.0}, 2500);
                        membershipPeriod.fadeOut("slow");

                        profileJS.reloadRoleMemberships();
                        profileJS.reloadFormerRoleMemberships();
                        profileJS.reloadFutureRoleMemberships();
                    } else {
                        formAlert.attr("class", "alert alert-danger form-alert");
                        formAlert.fadeIn();
                        formAlert.html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>" + data);
                    }
                }
            });
        });
    }
');
$page->addJavascript('
    $(".admMemberInfo").click(function() {
        showHideMembershipInformation($(this))
    });
    $("#menu_item_password").attr("data-toggle", "modal");
    $("#menu_item_password").attr("data-target", "#admidio_modal");
    $("#menu_item_role_memberships_change").attr("data-toggle", "modal");
    $("#menu_item_role_memberships_change").attr("data-target", "#admidio_modal");
    $("#profile_role_memberships_change").attr("data-toggle", "modal");
    $("#profile_role_memberships_change").attr("data-target", "#admidio_modal");

    $("input[data-provide=\'datepicker\']").datepicker({
        language: "'.$gL10n->getLanguageIsoCode().'",
        format: "'.DateTimeExtended::getDateFormatForDatepicker($gSettingsManager->getString('system_date')).'",
        todayHighlight: true,
        autoclose: true
    });
    formSubmitEvent();',
    true
);

// get module menu
$profileMenu = $page->getMenu();

// show back link
if($gNavigation->count() > 1)
{
    $profileMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}

// if user has right then show link to edit profile
if($gCurrentUser->hasRightEditProfile($user))
{
    $profileMenu->addItem(
        'menu_item_new_entry', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_id' => $userId)),
        $gL10n->get('PRO_EDIT_PROFILE'), 'edit.png'
    );
}

// Password of own user could be changed
if($userId === $currUsrId)
{
    $profileMenu->addItem(
        'menu_item_password', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('usr_id' => $userId)),
        $gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png'
    );
}
elseif($gCurrentUser->isAdministrator() && isMember($userId) && strlen($user->getValue('usr_login_name')) > 0)
{
    // Administrators can change or send password if login is configured and user is member of current organization

    if(strlen($user->getValue('EMAIL')) > 0 && $gSettingsManager->getBool('enable_system_mails'))
    {
        // if email is set and systemmails are activated then administrator can send a new password to user
        $profileMenu->addItem(
            'menu_item_send_password', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $userId, 'mode' => '5')),
            $gL10n->get('ORG_SEND_NEW_PASSWORD'), 'key.png'
        );
    }
    else
    {
        // if user has no email or send email is disabled then administrator could set a new password
        $profileMenu->addItem(
            'menu_item_password', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('usr_id' => $userId)),
            $gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png'
        );
    }
}

// show link to view profile field change history
if($gSettingsManager->getBool('profile_log_edit_fields') && $gCurrentUser->hasRightEditProfile($user))
{
    $profileMenu->addItem(
        'menu_item_change_history', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/profile_field_history.php', array('usr_id' => $userId)),
        $gL10n->get('MEM_CHANGE_HISTORY'), 'clock.png'
    );
}

$profileMenu->addItem('menu_item_extras', '', $gL10n->get('SYS_MORE_FEATURES'), '', 'right');

// show link to export the profile as vCard
$profileMenu->addItem(
    'menu_item_vcard', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php', array('mode' => '1', 'user_id' => $userId)),
    $gL10n->get('PRO_EXPORT_VCARD'), 'vcard.png', 'right', 'menu_item_extras'
);

// if you have the right to assign roles then show the link to assign new roles to this user
if($gCurrentUser->assignRoles())
{
    $profileMenu->addItem(
        'menu_item_role_memberships_change', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php', array('usr_id' => $userId, 'inline' => '1')),
        $gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE'), 'roles.png', 'right', 'menu_item_extras'
    );
}

// show link to create relations
if($gSettingsManager->getBool('members_enable_user_relations') && $gCurrentUser->editUsers())
{
    $profileMenu->addItem(
        'menu_item_maintain_user_relation_types', safeUrl(ADMIDIO_URL .FOLDER_MODULES.'/userrelations/userrelations_new.php', array('usr_id' => $userId)),
        $gL10n->get('PRO_ADD_USER_RELATION'), 'user_administration.png', 'right', 'menu_item_extras'
    );
}

if($gCurrentUser->isAdministrator())
{
    // show link to maintain profile fields
    $profileMenu->addItem(
        'menu_item_maintain_profile_fields', ADMIDIO_URL.FOLDER_MODULES.'/preferences/fields.php',
        $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras'
    );

    // show link to system preferences of weblinks
    $profileMenu->addItem(
        'menu_item_preferences_links', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'profile')),
        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras'
    );
}

// *******************************************************************************
// User data block
// *******************************************************************************

$page->addHtml('
<div class="panel panel-default" id="user_data_panel">
    <div class="panel-heading">'.$gL10n->get('SYS_MASTER_DATA').'</div>
    <div class="panel-body row">
        <div class="col-sm-8">');
            // create a static form
            $form = new HtmlForm('profile_master_data_form');

            // add lastname and firstname
            if(strlen($user->getValue('GENDER')) > 0 && $gCurrentUser->allowedViewProfileField($user, 'GENDER'))
            {
                // Icon des Geschlechts anzeigen, wenn noetigen Rechte vorhanden
                $form->addStaticControl('name', $gL10n->get('SYS_NAME'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME').' '.$user->getValue('GENDER', 'html'));
            }
            else
            {
                $form->addStaticControl('name', $gL10n->get('SYS_NAME'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
            }

            // add loginname
            if(strlen($user->getValue('usr_login_name')) > 0)
            {
                if ($userId !== $currUsrId && $gSettingsManager->getBool('enable_pm_module'))
                {
                    $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'),
                        '<img src="'.THEME_URL.'/icons/pm.png" alt="'.$gL10n->get('PMS_SEND_PM').'" />
                        <a href='.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('msg_type' => 'PM', 'usr_id' => $userId)).'>'.$user->getValue('usr_login_name').'</a>');
                }
                else
                {
                    $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'), $user->getValue('usr_login_name'));
                }
            }
            else
            {
                $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'), $gL10n->get('SYS_NOT_REGISTERED'));
            }

            $bAddressOutput = false;    // Merker, ob die Adresse schon angezeigt wurde

            // Schleife ueber alle Felder der Stammdaten

            foreach($gProfileFields->getProfileFields() as $field)
            {
                // nur Felder der Stammdaten anzeigen
                if($field->getValue('cat_name_intern') === 'MASTER_DATA' && $gCurrentUser->allowedViewProfileField($user, $field->getValue('usf_name_intern')))
                {
                    switch($field->getValue('usf_name_intern'))
                    {
                        case 'LAST_NAME':
                        case 'FIRST_NAME':
                        case 'GENDER':
                            // don't show these fields in default profile list
                            break;

                        case 'STREET':
                        case 'POSTCODE':
                        case 'CITY':
                        case 'COUNTRY':
                            $street   = $user->getValue('STREET');
                            $postcode = $user->getValue('POSTCODE');
                            $city     = $user->getValue('CITY');
                            $country  = $user->getValue('COUNTRY');

                            if(!$bAddressOutput // output of address only once
                            && (strlen($street) > 0 || strlen($postcode) > 0 || strlen($city) > 0 || strlen($country) > 0))
                            {
                                $bAddressOutput = true;
                                $urlParam = array();
                                $address  = array();

                                if(strlen($street) > 0 && $gCurrentUser->allowedViewProfileField($user, 'STREET'))
                                {
                                    $urlParam[] = $street;
                                    $address[] = $street;
                                }

                                // City and postcode should be shown in one line
                                if(strlen($postcode) > 0 && $gCurrentUser->allowedViewProfileField($user, 'POSTCODE'))
                                {
                                    $urlParam[] = $postcode;

                                    if(strlen($city) > 0 && $gCurrentUser->allowedViewProfileField($user, 'CITY'))
                                    {
                                        $urlParam[] = $city;

                                        // some countries have the order postcode city others have city postcode
                                        if($gProfileFields->getProperty('CITY', 'usf_sequence') > $gProfileFields->getProperty('POSTCODE', 'usf_sequence'))
                                        {
                                            $address[] = $postcode. ' '. $city;
                                        }
                                        else
                                        {
                                            $address[] = $city. ' '. $postcode;
                                        }
                                    }
                                }
                                elseif(strlen($city) > 0 && $gCurrentUser->allowedViewProfileField($user, 'CITY'))
                                {
                                    $urlParam[] = $city;
                                    $address[] = $city;
                                }

                                if(strlen($country) > 0 && $gCurrentUser->allowedViewProfileField($user, 'COUNTRY'))
                                {
                                    $urlParam[] = $country;
                                    $address[] = $country;
                                }

                                $urlParam = implode(', ', $urlParam);
                                $origin = implode(', ', array(
                                    $gCurrentUser->getValue('STREET'), $gCurrentUser->getValue('POSTCODE'),
                                    $gCurrentUser->getValue('CITY'), $gCurrentUser->getValue('COUNTRY')
                                ));
                                $mapUrl   = safeUrl('https://www.google.com/maps/search/', array('api' => 1, 'query' => $urlParam));
                                $routeUrl = safeUrl('https://www.google.com/maps/dir/', array('api' => 1, 'origin' => $origin, 'destination' => $urlParam));

                                $address = implode('<br />', $address) . '<br />';

                                // show route or address link if function is enabled and user has filled address or city
                                if($gSettingsManager->getBool('profile_show_map_link') && strlen($street) > 0
                                && (strlen($postcode) > 0 || strlen($city) > 0))
                                {
                                    $address .= '
                                        <a class="btn" href="'. $mapUrl. '" target="_blank"><img src="'. THEME_URL. '/icons/map.png"
                                        alt="'.$gL10n->get('SYS_MAP').'" />'.$gL10n->get('SYS_MAP').'</a>';

                                    // show route link if its not the profile of CurrentUser
                                    if($userId !== $currUsrId)
                                    {
                                        $address .= ' - <a href="'.$routeUrl.'" target="_blank">'.$gL10n->get('SYS_SHOW_ROUTE').'</a>';
                                    }
                                }

                                $form->addStaticControl('address', $gL10n->get('SYS_ADDRESS'), $address);
                            }
                            break;

                        default:
                            $fieldNameIntern = $field->getValue('usf_name_intern');
                            $field = getFieldCode($fieldNameIntern, $user);
                            if(is_array($field) && $field['value'] !== '')
                            {
                                $form->addStaticControl(strtolower($fieldNameIntern), $field['label'], $field['value']);
                            }
                            break;
                    }
                }
            }
            $page->addHtml($form->show());
        $page->addHtml('</div>
        <div class="col-sm-4" id="div_profile_photo">');

            // *******************************************************************************
            // Profile photo
            // *******************************************************************************

            $page->addHtml('<img id="profile_photo" class="thumbnail" src="' . safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('usr_id' => $userId)).'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');

            // Nur berechtigte User duerfen das Profilfoto editieren
            if($gCurrentUser->hasRightEditProfile($user))
            {
                $page->addHtml('<div id="profile_picture_links" class="btn-group-vertical" role="group">
                    <a class="btn" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('usr_id' => $userId)).'"><img
                        src="'.THEME_URL.'/icons/photo_upload.png" alt="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" /> '.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'</a>');
                // Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
                if((strlen($user->getValue('usr_photo')) > 0 && (int) $gSettingsManager->get('profile_photo_storage') === 0)
                    || is_file(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/'.$userId.'.jpg') && (int) $gSettingsManager->get('profile_photo_storage') === 1)
                {
                    $page->addHtml('<a id="btn_delete_photo" class="btn" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'pro_pho', 'element_id' => 'no_element', 'database_id' => $userId)).
                                    '"><img src="'. THEME_URL. '/icons/delete.png"
                                    alt="'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'" /> '.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'</a>');
                }
                $page->addHtml('</div>');
            }
        $page->addHtml('</div>
    </div>
</div>');

// *******************************************************************************
// Loop over all categories and profile fields except the master data
// *******************************************************************************

$category = '';
foreach($gProfileFields->getProfileFields() as $field)
{
    $fieldNameIntern = $field->getValue('usf_name_intern');

    // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
    // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
    if($field->getValue('cat_name_intern') !== 'MASTER_DATA' && $gCurrentUser->allowedViewProfileField($user, $fieldNameIntern))
    {
        // show new category header if new category and field has value or is a checkbox field
        if($category !== $field->getValue('cat_name')
        && (strlen($user->getValue($fieldNameIntern)) > 0 || $field->getValue('usf_type') === 'CHECKBOX'))
        {
            if($category !== '')
            {
                // new category then show last form and close div container
                $page->addHtml($form->show());
                $page->addHtml('</div></div>');
            }
            $category = $field->getValue('cat_name');

            $page->addHtml('
                <div class="panel panel-default" id="'.$field->getValue('cat_name_intern').'_data_panel">
                    <div class="panel-heading">'.$field->getValue('cat_name').'</div>
                    <div class="panel-body">');

            // create a static form
            $form = new HtmlForm('profile_'.$field->getValue('cat_name_intern').'_form');
        }

        // show html of field, if user has a value for that field or it's a checkbox field
        if(strlen($user->getValue($fieldNameIntern)) > 0 || $field->getValue('usf_type') === 'CHECKBOX')
        {
            $field = getFieldCode($fieldNameIntern, $user);
            if(is_array($field) && $field['value'] !== '')
            {
                $form->addStaticControl(strtolower($fieldNameIntern), $field['label'], $field['value']);
            }
        }
    }
}

if($category !== '')
{
    // new category then show last form and close div container
    $page->addHtml($form->show());
    $page->addHtml('</div></div>');
}

if($gSettingsManager->getBool('profile_show_roles'))
{
    // *******************************************************************************
    // Authorizations block
    // *******************************************************************************

    // Array mit allen Berechtigungen
    $rolesRights = array(
        'rol_all_lists_view',
        'rol_announcements',
        'rol_approve_users',
        'rol_assign_roles',
        'rol_dates',
        'rol_download',
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
    foreach($rolesRights as $rolesRightsDbName)
    {
        $sql = 'SELECT rol_name
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid  = 1
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND mem_usr_id = ? -- $userId
                   AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                       OR cat_org_id IS NULL )
                   AND '.$rolesRightsDbName.' = 1
              ORDER BY cat_org_id, cat_sequence, rol_name';
        $queryParams = array(DATE_NOW, DATE_NOW, $userId, $gCurrentOrganization->getValue('org_id'));
        $roleStatement = $gDb->queryPrepared($sql, $queryParams);

        $roles = array();
        while($roleName = $roleStatement->fetchColumn())
        {
            $roles[] = $roleName;
        }

        if(count($roles) > 0)
        {
            $rightsOrigin[$rolesRightsDbName] = implode(', ', $roles);
        }
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_authorizations_box">
        <div class="panel-heading">'.$gL10n->get('SYS_PERMISSIONS').'</div>
        <div class="panel-body row" id="profile_authorizations_box_body">');

    if(count($rightsOrigin) > 0)
    {
        $profileRightsArray = array();

        if($user->checkRolesRight('rol_assign_roles'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_assign_roles'],
                'right' => $gL10n->get('ROL_RIGHT_ASSIGN_ROLES'),
                'icon'  => 'roles.png'
            );
        }
        if($user->checkRolesRight('rol_approve_users'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_approve_users'],
                'right' => $gL10n->get('ROL_RIGHT_APPROVE_USERS'),
                'icon'  => 'new_registrations.png'
            );
        }
        if($user->checkRolesRight('rol_edit_user'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_edit_user'],
                'right' => $gL10n->get('ROL_RIGHT_EDIT_USER'),
                'icon'  => 'group.png'
            );
        }

        if($user->checkRolesRight('rol_mail_to_all'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_mail_to_all'],
                'right' => $gL10n->get('ROL_RIGHT_MAIL_TO_ALL'),
                'icon'  => 'email.png'
            );
        }
        if($user->checkRolesRight('rol_profile'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_profile'],
                'right' => $gL10n->get('ROL_RIGHT_PROFILE'),
                'icon'  => 'profile.png'
            );
        }
        if($user->checkRolesRight('rol_announcements') && (int) $gSettingsManager->get('enable_announcements_module') > 0)
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_announcements'],
                'right' => $gL10n->get('ROL_RIGHT_ANNOUNCEMENTS'),
                'icon'  => 'announcements.png'
            );
        }
        if($user->checkRolesRight('rol_dates') && (int) $gSettingsManager->get('enable_dates_module') > 0)
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_dates'],
                'right' => $gL10n->get('ROL_RIGHT_DATES'),
                'icon'  => 'dates.png'
            );
        }
        if($user->checkRolesRight('rol_photo') && (int) $gSettingsManager->get('enable_photo_module') > 0)
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_photo'],
                'right' => $gL10n->get('ROL_RIGHT_PHOTO'),
                'icon'  => 'photo.png'
            );
        }
        if($user->checkRolesRight('rol_download') && (int) $gSettingsManager->getBool('enable_download_module'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_download'],
                'right' => $gL10n->get('ROL_RIGHT_DOWNLOAD'),
                'icon'  => 'download.png'
            );
        }
        if($user->checkRolesRight('rol_guestbook') && (int) $gSettingsManager->get('enable_guestbook_module') > 0)
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_guestbook'],
                'right' => $gL10n->get('ROL_RIGHT_GUESTBOOK'),
                'icon'  => 'guestbook.png'
            );
        }
        if($user->checkRolesRight('rol_guestbook_comments') && (int) $gSettingsManager->get('enable_guestbook_module') > 0)
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_guestbook_comments'],
                'right' => $gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS'),
                'icon'  => 'comment.png'
            );
        }
        if($user->checkRolesRight('rol_weblinks') && (int) $gSettingsManager->get('enable_weblinks_module') > 0)
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_weblinks'],
                'right' => $gL10n->get('ROL_RIGHT_WEBLINKS'),
                'icon'  => 'weblinks.png'
            );
        }
        if($user->checkRolesRight('rol_all_lists_view'))
        {
            $profileRightsArray[] = array(
                'roles' => $rightsOrigin['rol_all_lists_view'],
                'right' => $gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW'),
                'icon'  => 'lists.png'
            );
        }

        foreach($profileRightsArray as $profileRight)
        {
            $page->addHtml('<div class="col-sm-6 col-md-4 admidio-profile-user-right" data-toggle="popover" data-html="true" data-trigger="hover" data-placement="auto" data-content="'.$gL10n->get('PRO_ASSIGNED_BY_ROLES'). ': <strong>'. $profileRight['roles'].'</strong>"><img
            class="admidio-icon-info" src="'.THEME_URL.'/icons/'.$profileRight['icon'].'" alt="Help" title="" />'. $profileRight['right']. '</div>');
        }
    }
    else
    {
        $page->addHtml('<div class="col-sm-12">'.$gL10n->get('PRO_NO_PERMISSIONS_ASSIGNED').'</div>');
    }

    $page->addHtml('
        </div>
    </div>');

    // *******************************************************************************
    // Roles block
    // *******************************************************************************

    // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
    $roleStatement  = getRolesFromDatabase($userId);
    $countRole      = $roleStatement->rowCount();

    // Ausgabe
    $page->addHtml('
    <div class="panel panel-default" id="profile_roles_box">
        <div class="panel-heading"><div class="pull-left">'.$gL10n->get('ROL_ROLE_MEMBERSHIPS').'</div>');
            // if you have the right to assign roles then show the link to assign new roles to this user
            if($gCurrentUser->assignRoles())
            {
                $page->addHtml('<div class="pull-right text-right"><a class="admidio-icon-link" id="profile_role_memberships_change" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php', array('usr_id' => $userId, 'inline' => '1')).'"><img
                    src="'.THEME_URL.'/icons/edit.png" alt="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'" title="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'" /></a></div>');
            }
        $page->addHtml('</div>
        <div class="panel-body" id="profile_roles_box_body">
            '.getRoleMemberships('role_list', $user, $roleStatement).'
        </div>
    </div>');

    // *******************************************************************************
    // block with future memberships
    // *******************************************************************************

    $roleStatement  = getFutureRolesFromDatabase($userId);
    $countRole      = $roleStatement->rowCount();
    $visible        = '';

    if($countRole === 0)
    {
        $visible = ' style="display: none;" ';
    }
    else
    {
        $page->addHtml('<script type="text/javascript">profileJS.futureRoleCount="'.$countRole.'";</script>');
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_future_roles_box" '.$visible.'>
        <div class="panel-heading">'.$gL10n->get('PRO_FUTURE_ROLE_MEMBERSHIP').'</div>
        <div class="panel-body" id="profile_future_roles_box_body">
            '.getRoleMemberships('future_role_list', $user, $roleStatement).'
        </div>
    </div>');
}

if($gSettingsManager->getBool('profile_show_former_roles'))
{
    // *******************************************************************************
    // Ehemalige Rollen Block
    // *******************************************************************************

    // Alle Rollen auflisten, die dem Mitglied zugeordnet waren

    $roleStatement  = getFormerRolesFromDatabase($userId);
    $countRole      = $roleStatement->rowCount();
    $visible        = '';

    if($countRole === 0)
    {
        $visible = ' style="display: none;" ';
    }
    else
    {
        $page->addHtml('<script type="text/javascript">profileJS.formerRoleCount="'.$countRole.'";</script>');
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_former_roles_box" '.$visible.'>
        <div class="panel-heading">'.$gL10n->get('PRO_FORMER_ROLE_MEMBERSHIP').'</div>
        <div class="panel-body" id="profile_former_roles_box_body">
            '.getRoleMemberships('former_role_list', $user, $roleStatement).'
        </div>
    </div>');
}

if($gSettingsManager->getBool('profile_show_extern_roles')
&& ($gCurrentOrganization->getValue('org_org_id_parent') > 0
    || $gCurrentOrganization->isParentOrganization()))
{
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
               AND rol_valid   = 1
               AND cat_name_intern <> \'EVENTS\'
               AND org_id     <> ? -- $gCurrentOrganization->getValue(\'org_id\')
          ORDER BY org_shortname, cat_sequence, rol_name';
    $roleStatement = $gDb->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $gCurrentOrganization->getValue('org_id')));

    if($roleStatement->rowCount() > 0)
    {
        $showRolesOtherOrganizations = false;
        $actualOrganization = 0;
        $role = new TableRoles($gDb);

        while($row = $roleStatement->fetch())
        {
            $orgId = (int) $row['org_id'];

            // if roles of new organization than read the rights of this organization
            if($actualOrganization !== $orgId)
            {
                $gCurrentUser->setOrganization($orgId);
                $actualOrganization = $orgId;
            }

            // check if current user has right to view the role of that organization
            if($gCurrentUser->hasRightViewRole($orgId))
            {
                $role->clear();
                $role->setArray($row);

                if(!$showRolesOtherOrganizations)
                {
                    $page->addHtml('
                    <div class="panel panel-default" id="profile_other_orga_roles_box">
                        <div class="panel-heading">'.
                            $gL10n->get('PRO_ROLE_MEMBERSHIP_OTHER_ORG').HtmlForm::getHelpTextIcon('PRO_VIEW_ROLES_OTHER_ORGAS').'
                        </div>
                        <div class="panel-body" id="profile_other_orga_roles_box_body">
                            <ul class="list-group admidio-list-roles-assign">');

                    $showRolesOtherOrganizations = true;
                }

                $startDate = \DateTime::createFromFormat('Y-m-d', $row['mem_begin']);
                // jede einzelne Rolle anzeigen
                $page->addHtml('
                <li class="list-group-item">
                    <span>'.
                        $row['org_shortname'].' - '.$role->getValue('cat_name').' - '.$role->getValue('rol_name'));

                        if($row['mem_leader'] == 1)
                        {
                            $page->addHtml(' - '.$gL10n->get('SYS_LEADER'));
                        }
                        $page->addHtml('&nbsp;
                    </span>
                    <span class="pull-right">'.$gL10n->get('SYS_SINCE', array($startDate->format($gSettingsManager->getString('system_date')))).'</span>
                </li>');
            }
        }

        $gCurrentUser->setOrganization((int) $gCurrentOrganization->getValue('org_id'));

        if($showRolesOtherOrganizations)
        {
            $page->addHtml('</ul></div></div>');
        }
    }
}

if($gSettingsManager->getBool('members_enable_user_relations'))
{
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

    if($count > 0)
    {
        $page->addHtml('
        <div class="panel panel-default" id="profile_user_relations_box">
            <div class="panel-heading"><div class="pull-left">' . $gL10n->get('SYS_USER_RELATIONS') . '</div>');
                // show link to create relations
                if($gSettingsManager->getBool('members_enable_user_relations') && $gCurrentUser->editUsers())
                {
                    $page->addHtml('<div class="pull-right text-right"><a class="admidio-icon-link" id="profile_relations_new_entry" href="'.safeUrl(ADMIDIO_URL .FOLDER_MODULES.'/userrelations/userrelations_new.php', array('usr_id' => $userId)).'"><img
                        src="'.THEME_URL.'/icons/add.png" alt="'.$gL10n->get('PRO_ADD_USER_RELATION').'" title="'.$gL10n->get('PRO_ADD_USER_RELATION').'" /></a></div>');
                }
            $page->addHtml('</div>
            <div class="panel-body" id="profile_user_relations_box_body">');

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

        $relationtype = new TableUserRelationType($gDb);
        $relation     = new TableUserRelation($gDb);
        $otherUser    = new User($gDb, $gProfileFields);

        $page->addHtml('<ul class="list-group admidio-list-roles-assign">');

        while($row = $relationStatement->fetch())
        {
            $editUserIcon = '';
            $relationtype->clear();
            $relationtype->setArray($row);
            $relation->clear();
            $relation->setArray($row);
            $otherUser->clear();
            $otherUser->readDataById($relation->getValue('ure_usr_id2'));

            $relationName = $relationtype->getValue('urt_name');
            if ($otherUser->getValue('GENDER', 'text') === $gL10n->get('SYS_MALE'))
            {
                $relationName = $relationtype->getValue('urt_name_male');
            }
            elseif ($otherUser->getValue('GENDER', 'text') === $gL10n->get('SYS_FEMALE'))
            {
                $relationName = $relationtype->getValue('urt_name_female');
            }

            if($gCurrentUser->hasRightEditProfile($otherUser))
            {
                $editUserIcon = ' <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_id' => $otherUser->getValue('usr_id'))) . '"><img
                    src="'. THEME_URL. '/icons/profile_edit.png" alt="'.$gL10n->get('REL_EDIT_USER_IN_RELATION').'" title="'.$gL10n->get('REL_EDIT_USER_IN_RELATION').'" /></a>';
            }

            $page->addHtml('<li id="row_ure_'.$relation->getValue('ure_id').'" class="list-group-item">');
            $page->addHtml('<div>');
            $page->addHtml('<span>'.$relationName.' - <a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $otherUser->getValue('usr_id'))).
                           '">'.$otherUser->getValue('FIRST_NAME') . ' ' . $otherUser->getValue('LAST_NAME').'</a>' . $editUserIcon . '<span>');
            $page->addHtml('<span class="pull-right text-right">');

             if($gCurrentUser->editUsers())
             {
                 $page->addHtml('<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                 href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'ure', 'element_id' => 'row_ure_'.$relation->getValue('ure_id'), 'database_id' => $relation->getValue('ure_id'),
                                 'name' => $relationtype->getValue('urt_name').': '.$otherUser->getValue('FIRST_NAME').' '.$otherUser->getValue('LAST_NAME').' -> '.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'))).'"><img
                                 src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('PRO_CANCEL_USER_RELATION').'" title="'.$gL10n->get('PRO_CANCEL_USER_RELATION').'" /></a>');
             }

            // only show info if system setting is activated
            if((int) $gSettingsManager->get('system_show_create_edit') > 0)
            {
                  $page->addHtml('<a class="admidio-icon-link admMemberInfo" id="relation_info_'.$relation->getValue('ure_id').'" href="javascript:void(0)"><img src="'.THEME_URL.'/icons/info.png" alt="'.$gL10n->get('SYS_INFORMATIONS').'" title="'.$gL10n->get('SYS_INFORMATIONS').'"/></a>');
            }
            $page->addHtml('</span></div>');
            if((int) $gSettingsManager->get('system_show_create_edit') > 0)
            {
                $page->addHtml(
                    '<div id="relation_info_'.$relation->getValue('ure_id').'_Content" style="display: none;">'.
                    admFuncShowCreateChangeInfoById(
                        (int) $relation->getValue('ure_usr_id_create'), $relation->getValue('ure_timestamp_create'),
                        (int) $relation->getValue('ure_usr_id_change'), $relation->getValue('ure_timestamp_change')
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
    (int) $user->getValue('usr_usr_id_create'), $user->getValue('usr_timestamp_create'),
    (int) $user->getValue('usr_usr_id_change'), $user->getValue('usr_timestamp_change')
));

$page->show();
