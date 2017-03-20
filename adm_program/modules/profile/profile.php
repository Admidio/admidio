<?php
/**
 ***********************************************************************************************
 * Show user profile
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_id : Show profile of the user with this is. If this parameter is not set then
 *           the profile of the current log will be shown.
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('roles_functions.php');

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
 * @param \User  $user
 * @return false|string[]
 */
function getFieldCode($fieldNameIntern, User $user)
{
    global $gPreferences, $gCurrentUser, $gProfileFields, $gL10n;

    if(!$gCurrentUser->hasRightEditProfile($user) && $gProfileFields->getProperty($fieldNameIntern, 'usf_hidden') == 1)
    {
        return false;
    }

    $html = array('label' => '', 'value' => '');

    // get value of field in html format
    $value = $user->getValue($fieldNameIntern, 'html');

    // if birthday then show age
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') === 'BIRTHDAY' && $value !== '')
    {
        $birthday = DateTime::createFromFormat('Y-m-d', $user->getValue($fieldNameIntern, 'Y-m-d'));
        $value = $value. '&nbsp;&nbsp;&nbsp;('. $birthday->diff(new DateTime('now'))->y. ' '.$gL10n->get('PRO_YEARS').')';
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

$userId     = (int) $user->getValue('usr_id');
$currUserId = (int) $gCurrentUser->getValue('usr_id');

// set headline
if($userId === $currUserId)
{
    $headline = $gL10n->get('PRO_MY_PROFILE');
}
else
{
    $headline = $gL10n->get('PRO_PROFILE_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
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

$page->addCssFile('adm_program/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css');
$page->addJavascriptFile('adm_program/libs/bootstrap-datepicker/dist/js/bootstrap-datepicker.js');
$page->addJavascriptFile('adm_program/libs/bootstrap-datepicker/dist/locales/bootstrap-datepicker.'.$gL10n->getLanguageIsoCode().'.min.js');
$page->addJavascriptFile('adm_program/libs/zxcvbn/dist/zxcvbn.js');
$page->addJavascriptFile('adm_program/modules/profile/profile.js');

$page->addJavascript('
    var profileJS = new ProfileJS(gRootPath);
    profileJS.deleteRole_ConfirmText  = "'.$gL10n->get('ROL_MEMBERSHIP_DEL', '[rol_name]').'";
    profileJS.deleteFRole_ConfirmText = "'.$gL10n->get('ROL_LINK_MEMBERSHIP_DEL', '[rol_name]').'";
    profileJS.setBy_Text              = "'.$gL10n->get('SYS_SET_BY').'";
    profileJS.userId                  = '.$userId.';

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
    $(".admMemberInfo").click(function () { showHideMembershipInformation($(this)) });
    $("#profile_authorizations_box_body").mouseout(function () { profileJS.deleteShowInfo()});
    $("#menu_item_password").attr("data-toggle", "modal");
    $("#menu_item_password").attr("data-target", "#admidio_modal");
    $("#menu_item_role_memberships_change").attr("data-toggle", "modal");
    $("#menu_item_role_memberships_change").attr("data-target", "#admidio_modal");

    $("input[data-provide=\'datepicker\']").datepicker({
                            language: "'.$gL10n->getLanguageIsoCode().'",
                            format: "'.DateTimeExtended::getDateFormatForDatepicker($gPreferences['system_date']).'",
                            todayHighlight: "true",
                            autoclose: "true"
                        });
    formSubmitEvent(); ', true);

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
    $profileMenu->addItem('menu_item_new_entry', ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php?user_id='.$userId,
                        $gL10n->get('PRO_EDIT_PROFILE'), 'edit.png');
}

// Password of own user could be changed
if($userId === $currUserId)
{
    $profileMenu->addItem('menu_item_password', ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php?usr_id='. $userId,
                        $gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png');
}
elseif($gCurrentUser->isAdministrator() && isMember($userId) && strlen($user->getValue('usr_login_name')) > 0)
{
    // Administrators can change or send password if login is configured and user is member of current organization

    if(strlen($user->getValue('EMAIL')) > 0 && $gPreferences['enable_system_mails'] == 1)
    {
        // if email is set and systemmails are activated then administrator can send a new password to user
        $profileMenu->addItem('menu_item_send_password', ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php?usr_id='.$userId.'&amp;mode=5',
                            $gL10n->get('ORG_SEND_NEW_PASSWORD'), 'key.png');
    }
    else
    {
        // if user has no email or send email is disabled then administrator could set a new password
        $profileMenu->addItem('menu_item_password', ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php?usr_id='. $userId,
                            $gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png');
    }
}

// show link to view profile field change history
if($gPreferences['profile_log_edit_fields'] == 1 && $gCurrentUser->hasRightEditProfile($user))
{
    $profileMenu->addItem('menu_item_change_history', ADMIDIO_URL.FOLDER_MODULES.'/members/profile_field_history.php?usr_id='. $userId,
                        $gL10n->get('MEM_CHANGE_HISTORY'), 'clock.png');
}

$profileMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');

// show link to export the profile as vCard
$profileMenu->addItem('menu_item_vcard', ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php?mode=1&amp;user_id='. $userId,
                        $gL10n->get('PRO_EXPORT_VCARD'), 'vcard.png', 'right', 'menu_item_extras');

// if you have the right to assign roles then show the link to assign new roles to this user
if($gCurrentUser->assignRoles())
{
    $profileMenu->addItem('menu_item_role_memberships_change', ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php?usr_id='.$userId.'&amp;inline=1',
                            $gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE'), 'roles.png', 'right', 'menu_item_extras');
}

// show link to create relations
if($gPreferences['members_enable_user_relations'] == 1 && $gCurrentUser->editUsers())
{
    $profileMenu->addItem('menu_item_maintain_user_relation_types', ADMIDIO_URL .FOLDER_MODULES.'/userrelations/userrelations_new.php?usr_id=' . $userId,
        $gL10n->get('PRO_ADD_USER_RELATION'), 'user_administration.png', 'right', 'menu_item_extras');
}

if($gCurrentUser->isAdministrator())
{
    // show link to maintain profile fields
    $profileMenu->addItem('menu_item_maintain_profile_fields', ADMIDIO_URL.FOLDER_MODULES.'/preferences/fields.php',
                                $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras');

    // show link to system preferences of weblinks
    $profileMenu->addItem('menu_item_preferences_links', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=profile',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras');
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
            $form = new HtmlForm('profile_master_data_form', null);

            // add lastname and firstname
            if(strlen($user->getValue('GENDER')) > 0
            && ($gCurrentUser->hasRightEditProfile($user) || $gProfileFields->getProperty('GENDER', 'usf_hidden') == 0))
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
                if ($userId !== $currUserId && $gPreferences['enable_pm_module'] == 1)
                {
                    $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'),
                        '<img src="'.THEME_URL.'/icons/pm.png" alt="'.$gL10n->get('PMS_SEND_PM').'" />
                        <a href='.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?msg_type=PM&usr_id='.$userId.'>'.$user->getValue('usr_login_name').'</a>');
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

            foreach($gProfileFields->mProfileFields as $field)
            {
                // nur Felder der Stammdaten anzeigen
                if($field->getValue('cat_name_intern') === 'MASTER_DATA'
                && ($gCurrentUser->hasRightEditProfile($user) || $field->getValue('usf_hidden') == 0))
                {
                    switch($field->getValue('usf_name_intern'))
                    {
                        case 'LAST_NAME':
                        case 'FIRST_NAME':
                        case 'GENDER':
                            // don't show these fields in default profile list
                            break;

                        case 'ADDRESS':
                        case 'POSTCODE':
                        case 'CITY':
                        case 'COUNTRY':
                            if(!$bAddressOutput // output of address only once
                            && (strlen($user->getValue('ADDRESS')) > 0 || strlen($user->getValue('POSTCODE')) > 0
                               || strlen($user->getValue('CITY')) > 0 || strlen($user->getValue('COUNTRY')) > 0))
                            {
                                $bAddressOutput = true;
                                $htmlAddress    = '';
                                $address        = '';
                                $map_url        = 'https://maps.google.com/?q=';
                                $route_url      = 'https://maps.google.com/?f=d&amp;saddr='.
                                    urlencode($gCurrentUser->getValue('ADDRESS')).
                                    ',%20'. urlencode($gCurrentUser->getValue('POSTCODE')).
                                    ',%20'. urlencode($gCurrentUser->getValue('CITY')).
                                    ',%20'. urlencode($gCurrentUser->getValue('COUNTRY')).
                                    '&amp;daddr=';

                                if(strlen($user->getValue('ADDRESS')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) || $gProfileFields->getProperty('ADDRESS', 'usf_hidden') == 0))
                                {
                                    $address   .= $user->getValue('ADDRESS'). '<br />';
                                    $map_url   .= urlencode($user->getValue('ADDRESS'));
                                    $route_url .= urlencode($user->getValue('ADDRESS'));
                                }

                                if(strlen($user->getValue('POSTCODE')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) || $gProfileFields->getProperty('POSTCODE', 'usf_hidden') == 0))
                                {
                                    $address   .= $user->getValue('POSTCODE');
                                    $map_url   .= ',%20'. urlencode($user->getValue('POSTCODE'));
                                    $route_url .= ',%20'. urlencode($user->getValue('POSTCODE'));

                                    // City and postcode should be shown in one line
                                    if(strlen($user->getValue('CITY')) === 0
                                    || (!$gCurrentUser->hasRightEditProfile($user) && $gProfileFields->getProperty('CITY', 'usf_hidden') == 1))
                                    {
                                        $address   .= '<br />';
                                    }
                                }

                                if(strlen($user->getValue('CITY')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) || $gProfileFields->getProperty('CITY', 'usf_hidden') == 0))
                                {
                                    // City and postcode should be shown in one line
                                    $address   .= ' '. $user->getValue('CITY'). '<br />';
                                    $map_url   .= ',%20'. urlencode($user->getValue('CITY'));
                                    $route_url .= ',%20'. urlencode($user->getValue('CITY'));
                                }

                                if(strlen($user->getValue('COUNTRY')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) || $gProfileFields->getProperty('COUNTRY', 'usf_hidden') == 0))
                                {
                                    $country    = $user->getValue('COUNTRY');
                                    $address   .= $country. '<br />';
                                    $map_url   .= ',%20'. urlencode($country);
                                    $route_url .= ',%20'. urlencode($country);
                                }

                                $htmlAddress .= $address;

                                // show route or address link if function is enabled and user has filled address or city
                                if($gPreferences['profile_show_map_link'] && strlen($user->getValue('ADDRESS')) > 0
                                && (strlen($user->getValue('POSTCODE')) > 0 || strlen($user->getValue('CITY')) > 0))
                                {
                                    $htmlAddress .= '
                                    <a class="btn" href="'. $map_url. '" target="_blank"><img src="'. THEME_URL. '/icons/map.png"
                                        alt="'.$gL10n->get('SYS_MAP').'" />'.$gL10n->get('SYS_MAP').'</a>';

                                    // show route link if its not the profile of CurrentUser
                                    if($userId !== $currUserId)
                                    {
                                        $htmlAddress .= ' - <a href="'.$route_url.'" target="_blank">'.$gL10n->get('SYS_SHOW_ROUTE').'</a>';
                                    }
                                }

                                $form->addStaticControl('address', $gL10n->get('SYS_ADDRESS'), $htmlAddress);
                            }
                            break;

                        default:
                            $fieldNameIntern = $field->getValue('usf_name_intern');
                            $field = getFieldCode($fieldNameIntern, $user);
                            if(is_array($field) && $field['value'] !== '')
                            {
                                $form->addStaticControl(admStrToLower($fieldNameIntern), $field['label'], $field['value']);
                            }
                            break;
                    }
                }
            }
            $page->addHtml($form->show(false));
        $page->addHtml('</div>
        <div class="col-sm-4" id="div_profile_photo">');

            // *******************************************************************************
            // Profile photo
            // *******************************************************************************

            $page->addHtml('<img id="profile_photo" class="thumbnail" src="profile_photo_show.php?usr_id='.$userId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');

            // Nur berechtigte User duerfen das Profilfoto editieren
            if($gCurrentUser->hasRightEditProfile($user))
            {
                $page->addHtml('<div id="profile_picture_links" class="btn-group-vertical" role="group">
                    <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php?usr_id='.$userId.'"><img
                        src="'.THEME_URL.'/icons/photo_upload.png" alt="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" /> '.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'</a>');
                // Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
                if((strlen($user->getValue('usr_photo')) > 0 && $gPreferences['profile_photo_storage'] == 0)
                    || is_file(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/'.$userId.'.jpg') && $gPreferences['profile_photo_storage'] == 1)
                {
                    $page->addHtml('<a id="btn_delete_photo" class="btn" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=pro_pho&amp;element_id=no_element'.
                                    '&amp;database_id='.$userId.'"><img src="'. THEME_URL. '/icons/delete.png"
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
foreach($gProfileFields->mProfileFields as $field)
{
    // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
    // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
    if($field->getValue('cat_name_intern') !== 'MASTER_DATA'
    && ($gCurrentUser->hasRightEditProfile($user)
        || (!$gCurrentUser->hasRightEditProfile($user) && $field->getValue('usf_hidden') == 0)))
    {
        $fieldNameIntern = $field->getValue('usf_name_intern');

        // show new category header if new category and field has value or is a checkbox field
        if($category !== $field->getValue('cat_name')
        && (strlen($user->getValue($fieldNameIntern)) > 0 || $field->getValue('usf_type') === 'CHECKBOX'))
        {
            if($category !== '')
            {
                // new category then show last form and close div container
                $page->addHtml($form->show(false));
                $page->addHtml('</div></div>');
            }
            $category = $field->getValue('cat_name');

            $page->addHtml('
                <div class="panel panel-default" id="'.$field->getValue('cat_name_intern').'_data_panel">
                    <div class="panel-heading">'.$field->getValue('cat_name').'</div>
                    <div class="panel-body">');

            // create a static form
            $form = new HtmlForm('profile_'.$field->getValue('cat_name_intern').'_form', null);
        }

        // show html of field, if user has a value for that field or it's a checkbox field
        if(strlen($user->getValue($fieldNameIntern)) > 0 || $field->getValue('usf_type') === 'CHECKBOX')
        {
            $field = getFieldCode($fieldNameIntern, $user);
            if(is_array($field) && $field['value'] !== '')
            {
                $form->addStaticControl(admStrToLower($fieldNameIntern), $field['label'], $field['value']);
            }
        }
    }
}

if($category !== '')
{
    // new category then show last form and close div container
    $page->addHtml($form->show(false));
    $page->addHtml('</div></div>');
}

if($gPreferences['profile_show_roles'] == 1)
{
    // *******************************************************************************
    // Authorizations block
    // *******************************************************************************

    // Array mit allen Berechtigungen
    $authorizations = array(
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

    $berechtigungsHerkunft = array();

    // Abfragen der aktiven Rollen mit Berechtigung und Schreiben in ein Array
    foreach($authorizations as $authorization_db_name)
    {
        $sql = 'SELECT rol_name
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid  = 1
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND mem_usr_id = '.$userId.'
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                   AND '.$authorization_db_name.' = 1
              ORDER BY cat_org_id, cat_sequence, rol_name';
        $roleStatement = $gDb->query($sql);
        $berechtigungsHerkunft[$authorization_db_name] = null;

        while($roleName = $roleStatement->fetchColumn())
        {
            $berechtigungsHerkunft[$authorization_db_name] = $berechtigungsHerkunft[$authorization_db_name].', '.$roleName;
        }
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_authorizations_box">
        <div class="panel-heading">'.$gL10n->get('SYS_AUTHORIZATION').'</div>
        <div class="panel-body" id="profile_authorizations_box_body">
            <p>');

            //checkRolesRight($right)
            if($user->checkRolesRight('rol_assign_roles'))
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_assign_roles'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/roles.png"
                alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />');
            }
            if($user->checkRolesRight('rol_approve_users'))
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_approve_users'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/new_registrations.png"
                alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />');
            }
            if($user->checkRolesRight('rol_edit_user'))
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_edit_user'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/group.png"
                alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />');
            }

            if($user->checkRolesRight('rol_mail_to_all'))
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_mail_to_all'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/email.png"
                alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />');
            }
            if($user->checkRolesRight('rol_profile'))
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_profile'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/profile.png"
                alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />');
            }
            if($user->checkRolesRight('rol_announcements') && $gPreferences['enable_announcements_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_announcements'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/announcements.png"
                alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />');
            }
            if($user->checkRolesRight('rol_dates') && $gPreferences['enable_dates_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_dates'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/dates.png"
                alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />');
            }
            if($user->checkRolesRight('rol_photo') && $gPreferences['enable_photo_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_photo'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/photo.png"
                alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />');
            }
            if($user->checkRolesRight('rol_download') && $gPreferences['enable_download_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_download'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/download.png"
                alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />');
            }
            if($user->checkRolesRight('rol_guestbook') && $gPreferences['enable_guestbook_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_guestbook'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/guestbook.png"
                alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />');
            }
            if($user->checkRolesRight('rol_guestbook_comments') && $gPreferences['enable_guestbook_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_guestbook_comments'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/comment.png"
                alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />');
            }
            if($user->checkRolesRight('rol_weblinks') && $gPreferences['enable_weblinks_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_weblinks'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/weblinks.png"
                alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />');
            }
            if($user->checkRolesRight('rol_all_lists_view'))
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungsHerkunft['rol_all_lists_view'], 2).'\')" class="admidio-icon-info" src="'.THEME_URL.'/icons/lists.png"
                alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" />');
            }
            $page->addHtml('</p>
            <div><p class="alert alert-info" id="profile_authorization_content">'.$gL10n->get('SYS_SET_BY').':</p></div>
        </div>
    </div>');

    // *******************************************************************************
    // Roles block
    // *******************************************************************************

    // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
    $count_show_roles = 0;
    $roleStatement    = getRolesFromDatabase($userId);
    $count_role       = $roleStatement->rowCount();

    // Ausgabe
    $page->addHtml('
    <div class="panel panel-default" id="profile_roles_box">
        <div class="panel-heading">
            '.$gL10n->get('ROL_ROLE_MEMBERSHIPS').'
        </div>
        <div class="panel-body" id="profile_roles_box_body">
            '.getRoleMemberships('role_list', $user, $roleStatement, $count_role, false).'
        </div>
    </div>');

    // *******************************************************************************
    // block with future memberships
    // *******************************************************************************

    $count_show_roles = 0;
    $roleStatement    = getFutureRolesFromDatabase($userId);
    $count_role       = $roleStatement->rowCount();
    $visible          = '';

    if($count_role === 0)
    {
        $visible = ' style="display: none;" ';
    }
    else
    {
        $page->addHtml('<script type="text/javascript">profileJS.futureRoleCount="'.$count_role.'";</script>');
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_future_roles_box" '.$visible.'>
        <div class="panel-heading">'.$gL10n->get('PRO_FUTURE_ROLE_MEMBERSHIP').'</div>
        <div class="panel-body" id="profile_future_roles_box_body">
            '.getRoleMemberships('future_role_list', $user, $roleStatement, $count_role, false).'
        </div>
    </div>');
}

if($gPreferences['profile_show_former_roles'] == 1)
{
    // *******************************************************************************
    // Ehemalige Rollen Block
    // *******************************************************************************

    // Alle Rollen auflisten, die dem Mitglied zugeordnet waren

    $count_show_roles = 0;
    $roleStatement    = getFormerRolesFromDatabase($userId);
    $count_role       = $roleStatement->rowCount();
    $visible          = '';

    if($count_role === 0)
    {
        $visible = ' style="display: none;" ';
    }
    else
    {
        $page->addHtml('<script type="text/javascript">profileJS.formerRoleCount="'.$count_role.'";</script>');
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_former_roles_box" '.$visible.'>
        <div class="panel-heading">'.$gL10n->get('PRO_FORMER_ROLE_MEMBERSHIP').'</div>
        <div class="panel-body" id="profile_former_roles_box_body">
            '.getRoleMemberships('former_role_list', $user, $roleStatement, $count_role, false).'
        </div>
    </div>');
}

if($gPreferences['profile_show_extern_roles'] == 1
&& ($gCurrentOrganization->getValue('org_org_id_parent') > 0
    || $gCurrentOrganization->hasChildOrganizations()))
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
             WHERE mem_usr_id  = '.$userId.'
               AND mem_begin  <= \''.DATE_NOW.'\'
               AND mem_end    >= \''.DATE_NOW.'\'
               AND rol_valid   = 1
               AND rol_visible = 1
               AND org_id     <> '.$gCurrentOrganization->getValue('org_id').'
          ORDER BY org_shortname, cat_sequence, rol_name';
    $roleStatement = $gDb->query($sql);

    if($roleStatement->rowCount() > 0)
    {
        $showRolesOtherOrganizations = false;
        $actualOrganization = 0;
        $role = new TableRoles($gDb);

        while($row = $roleStatement->fetch())
        {
            // if roles of new organization than read the rights of this organization
            if($actualOrganization !== (int) $row['org_id'])
            {
                $gCurrentUser->setOrganization($row['org_id']);
                $actualOrganization = (int) $row['org_id'];
            }

            // check if current user has right to view the role of that organization
            if($gCurrentUser->hasRightViewRole($row['rol_id']))
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

                $startDate = DateTime::createFromFormat('Y-m-d', $row['mem_begin']);
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
                    <span class="pull-right">'.$gL10n->get('SYS_SINCE', $startDate->format($gPreferences['system_date'])).'</span>
                </li>');
            }
        }

        $gCurrentUser->setOrganization($gCurrentOrganization->getValue('org_id'));

        if($showRolesOtherOrganizations)
        {
            $page->addHtml('</ul></div></div>');
        }
    }
}

if($gPreferences['members_enable_user_relations'] == 1)
{
        // *******************************************************************************
        // user relations block
        // *******************************************************************************
    $sql = 'SELECT COUNT(*) AS count
              FROM ' . TBL_USER_RELATIONS . '
        INNER JOIN ' . TBL_USER_RELATION_TYPES . '
                ON ure_urt_id  = urt_id
             WHERE ure_usr_id1 = ' . $userId . '
               AND urt_name        <> \'\'
               AND urt_name_male   <> \'\'
               AND urt_name_female <> \'\'';
    $statement = $gDb->query($sql);
    $count = (int) $statement->fetchColumn();

    if($count > 0)
    {
        $page->addHtml('
        <div class="panel panel-default" id="profile_user_relations_box">
            <div class="panel-heading">' . $gL10n->get('SYS_USER_RELATIONS') . '</div>
            <div class="panel-body" id="profile_user_relations_box_body">');

        $sql = 'SELECT *
                  FROM '.TBL_USER_RELATIONS.'
            INNER JOIN '.TBL_USER_RELATION_TYPES.'
                    ON ure_urt_id  = urt_id
                 WHERE ure_usr_id1 = '.$userId.'
                   AND urt_name        <> \'\'
                   AND urt_name_male   <> \'\'
                   AND urt_name_female <> \'\'
              ORDER BY urt_name';
        $relationStatement = $gDb->query($sql);

        $relationtype = new TableUserRelationType($gDb);
        $relation     = new TableUserRelation($gDb);
        $otherUser    = new User($gDb, $gProfileFields);

        $page->addHtml('<ul class="list-group admidio-list-roles-assign">');

        while($row = $relationStatement->fetch())
        {
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

            $page->addHtml('<li id="row_ure_'.$relation->getValue('ure_id').'" class="list-group-item">');
            $page->addHtml('<div>');
            $page->addHtml('<span>'.$relationName.' - <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php?user_id='.
                            $otherUser->getValue('usr_id').'">'.$otherUser->getValue('FIRST_NAME') . ' ' . $otherUser->getValue('LAST_NAME').'</a><span>');
            $page->addHtml('<span class="pull-right text-right">');

             if($gCurrentUser->hasRightEditProfile($otherUser))
             {
                 $page->addHtml('<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                 href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=ure&amp;element_id=row_ure_'.
                                 $relation->getValue('ure_id').'&amp;database_id='.$relation->getValue('ure_id').
                                 '&amp;name='.urlencode($relationtype->getValue('urt_name').': '.$otherUser->getValue('FIRST_NAME').' '.$otherUser->getValue('LAST_NAME').' -> '.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')).'"><img
                                 src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('PRO_CANCEL_USER_RELATION').'" title="'.$gL10n->get('PRO_CANCEL_USER_RELATION').'" /></a>');
             }

            // only show info if system setting is activated
            if($gPreferences['system_show_create_edit'] > 0)
            {
                  $page->addHtml('<a class="admidio-icon-link admMemberInfo" id="relation_info_'.$relation->getValue('ure_id').'" href="javascript:void(0)"><img src="'.THEME_URL.'/icons/info.png" alt="'.$gL10n->get('SYS_INFORMATIONS').'" title="'.$gL10n->get('SYS_INFORMATIONS').'"/></a>');
            }
            $page->addHtml('</span></div>');
            if($gPreferences['system_show_create_edit'] > 0)
            {
                $page->addHtml(
                    '<div id="relation_info_'.$relation->getValue('ure_id').'_Content" style="display: none;">'.
                    admFuncShowCreateChangeInfoById($relation->getValue('ure_usr_id_create'), $relation->getValue('ure_timestamp_create'), $relation->getValue('ure_usr_id_change'), $relation->getValue('ure_timestamp_change')).
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
$page->addHtml(admFuncShowCreateChangeInfoById($user->getValue('usr_usr_id_create'), $user->getValue('usr_timestamp_create'), $user->getValue('usr_usr_id_change'), $user->getValue('usr_timestamp_change')));

$page->show();
