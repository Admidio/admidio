<?php
/******************************************************************************
 * Show user profile
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * user_id : Show profile of the user with this is. If this parameter is not set then
 *           the profile of the current log will be shown.
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('roles_functions.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'user_id', 'numeric', array('defaultValue' => $gCurrentUser->getValue('usr_id')));

// create user object
$user = new User($gDb, $gProfileFields, $getUserId);

//Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->hasRightViewProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($fieldNameIntern, $user)
{
    global $gPreferences, $g_root_path, $gCurrentUser, $gProfileFields, $gL10n;
    $html      = array('label' => '', 'value' => '');
    $value     = '';
    $msg_image = '';

    if($gCurrentUser->hasRightEditProfile($user) == false && $gProfileFields->getProperty($fieldNameIntern, 'usf_hidden') == 1)
    {
        return '';
    }

    // get value of field in html format
    $value = $user->getValue($fieldNameIntern, 'html');

    // if birthday then show age
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'BIRTHDAY')
    {
        $birthday = new DateTimeExtended($user->getValue($fieldNameIntern, 'Y.m.d'), 'Y.m.d', 'date');
        $value = $value. '&nbsp;&nbsp;&nbsp;('. $birthday->getAge(). ' '.$gL10n->get('PRO_YEARS').')';
    }

    // Icons der Messenger anzeigen
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'ICQ')
    {
        if(strlen($user->getValue($fieldNameIntern)) > 0)
        {
            // Sonderzeichen aus der ICQ-Nummer entfernen (damit kommt www.icq.com nicht zurecht)
            preg_match_all('/\d+/', $user->getValue($fieldNameIntern), $matches);
            $icq_number = implode("", reset($matches));

            // ICQ Onlinestatus anzeigen
            $value = '
            <a class="admidio-icon-link" href="http://www.icq.com/people/cmd.php?uin='.$icq_number.'&amp;action=add"><img
                src="http://status.icq.com/online.gif?icq='.$icq_number.'&amp;img=5"
                alt="'.$gL10n->get('PRO_TO_ADD', $user->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'"
                title="'.$gL10n->get('PRO_TO_ADD', $user->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'" /></a> '.$value;
        }
    }
    elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'SKYPE')
    {
        if(strlen($user->getValue($fieldNameIntern)) > 0)
        {
            // Skype Onlinestatus anzeigen
            $value = '<script type="text/javascript" src="http://download.skype.com/share/skypebuttons/js/skypeCheck.js"></script>
            <a class="admidio-icon-link" href="skype:'.$user->getValue($fieldNameIntern).'?add"><img
                src="http://mystatus.skype.com/smallicon/'.$user->getValue($fieldNameIntern).'"
                title="'.$gL10n->get('PRO_TO_ADD', $user->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'"
                alt="'.$gL10n->get('PRO_TO_ADD', $user->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'" /></a> '.$value;
        }
    }
    elseif(strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_icon')) > 0)
    {
        $value = $gProfileFields->getProperty($fieldNameIntern, 'usf_icon').'&nbsp;&nbsp;'. $value;
    }

    // show html of field, if user has a value for that field or it's a checkbox field
    if(strlen($user->getValue($fieldNameIntern)) > 0 || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'CHECKBOX')
    {
        $html['label'] = $gProfileFields->getProperty($fieldNameIntern, 'usf_name');
        $html['value'] = $value;
    }

    return $html;
}

unset($_SESSION['profile_request']);

// set headline
if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
{
    $headline = $gL10n->get('PRO_MY_PROFILE');
}
else
{
    $headline = $gL10n->get('PRO_PROFILE_FROM', $user->getValue('LAST_NAME'), $user->getValue('FIRST_NAME'));
}

// if user id was not set and own profile should be shown then initialize navigation
if(isset($_GET['user_id']) == false)
{
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

if($gDebug)
{
    $page->addCssFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/css/bootstrap-datepicker3.css');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/js/bootstrap-datepicker.js');
}
else
{
    $page->addCssFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/css/bootstrap-datepicker3.min.css');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/js/bootstrap-datepicker.min.js');
}

$page->addJavascriptFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/locales/bootstrap-datepicker.'.$gL10n->getLanguageIsoCode().'.min.js');
$page->addJavascriptFile($g_root_path.'/adm_program/modules/profile/profile.js');

$page->addJavascript('
    var profileJS = new profileJSClass();
    profileJS.deleteRole_ConfirmText  = "'.$gL10n->get('ROL_MEMBERSHIP_DEL', '[rol_name]').'";
    profileJS.deleteFRole_ConfirmText = "'.$gL10n->get('ROL_LINK_MEMBERSHIP_DEL', '[rol_name]').'";
    profileJS.setBy_Text              = "'.$gL10n->get('SYS_SET_BY').'";
    profileJS.usr_id                  = '.$user->getValue('usr_id').';

    function showHideMembershipInformation(element) {
        id = "#" + element.attr("id") + "_Content";

        if($(id).css("display") == "none") {
            $(id).show("fast");
        }
        else {
            $(id).hide("fast");
        }
    }');
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

    $(".admidio-form-membership-period").submit(function(event) {
        var id = $(this).attr("id");
        var parentId = $("#"+id).parent().parent().attr("id");
        var action = $(this).attr("action");
        $("#"+id+" .form-alert").hide();

        // disable default form submit
        event.preventDefault();

        $.ajax({
            type:    "GET",
            url:     action,
            data:    $(this).serialize(),
            success: function(data) {
                if(data == "success") {
                    $("#"+id+" .form-alert").attr("class", "alert alert-success form-alert");
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    $("#"+id+" .form-alert").fadeIn("slow");
                    $("#"+id+" .form-alert").animate({opacity: 1.0}, 2500);
                    $("#"+id+" .form-alert").fadeOut("slow");
                    $("#"+parentId).animate({opacity: 1.0}, 2500);
                    $("#"+parentId).fadeOut("slow");
                    profileJS.reloadRoleMemberships();
                    profileJS.reloadFormerRoleMemberships();
                    profileJS.reloadFutureRoleMemberships();
                }
                else {
                    $("#"+id+" .form-alert").attr("class", "alert alert-danger form-alert");
                    $("#"+id+" .form-alert").fadeIn();
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>"+data);
                }
            }
        });
    });', true);

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
    $profileMenu->addItem('menu_item_new_entry', $g_root_path. '/adm_program/modules/profile/profile_new.php?user_id='.$user->getValue('usr_id'),
                        $gL10n->get('PRO_EDIT_PROFILE'), 'edit.png');
}

// Password of own user could be changed
if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
{
    $profileMenu->addItem('menu_item_password', $g_root_path. '/adm_program/modules/profile/password.php?usr_id='. $user->getValue('usr_id'),
                        $gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png');
}
elseif($gCurrentUser->isWebmaster() && isMember($user->getValue('usr_id'))
&& strlen($user->getValue('usr_login_name')) > 0)
{
    // Webmasters can change or send password if login is configured and user is member of current organization

    if(strlen($user->getValue('EMAIL')) > 0 && $gPreferences['enable_system_mails'] == 1)
    {
        // if email is set and systemmails are activated then webmaster can send a new password to user
        $profileMenu->addItem('menu_item_send_password', $g_root_path.'/adm_program/modules/members/members_function.php?usr_id='.$user->getValue('usr_id').'&amp;mode=5',
                            $gL10n->get('ORG_SEND_NEW_PASSWORD'), 'key.png');
    }
    else
    {
        // if user has no email or send email is disabled then webmaster could set a new password
        $profileMenu->addItem('menu_item_password', $g_root_path. '/adm_program/modules/profile/password.php?usr_id='. $user->getValue('usr_id'),
                            $gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png');
    }
}

// show link to view profile field change history
if($gPreferences['profile_log_edit_fields'] == 1)
{
    $profileMenu->addItem('menu_item_change_history', $g_root_path. '/adm_program/modules/members/profile_field_history.php?usr_id='. $user->getValue('usr_id'),
                        $gL10n->get('MEM_CHANGE_HISTORY'), 'clock.png');
}

$profileMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');

// show link to export the profile as vCard
$profileMenu->addItem('menu_item_vcard', $g_root_path.'/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id='. $user->getValue('usr_id'),
                        $gL10n->get('PRO_EXPORT_VCARD'), 'vcard.png', 'right', 'menu_item_extras');

// if you have the right to assign roles then show the link to assign new roles to this user
if($gCurrentUser->assignRoles())
{
    $profileMenu->addItem('menu_item_role_memberships_change', $g_root_path.'/adm_program/modules/profile/roles.php?usr_id='.$user->getValue('usr_id').'&amp;inline=1',
                            $gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE'), 'roles.png', 'right', 'menu_item_extras');
}

if($gCurrentUser->isWebmaster())
{
    // show link to maintain profile fields
    $profileMenu->addItem('menu_item_maintain_profile_fields', $g_root_path. '/adm_program/modules/preferences/fields.php',
                                $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras');

    // show link to system preferences of weblinks
    $profileMenu->addItem('menu_item_preferences_links', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=profile',
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
            && ($gCurrentUser->hasRightEditProfile($user) == true || $gProfileFields->getProperty('GENDER', 'usf_hidden') == 0))
            {
                // Icon des Geschlechts anzeigen, wenn noetigen Rechte vorhanden
                $form->addStaticControl('name', $gL10n->get('SYS_NAME'), $user->getValue('LAST_NAME'). ' '. $user->getValue('FIRST_NAME').' '.$user->getValue('GENDER', 'html'));
            }
            else
            {
                $form->addStaticControl('name', $gL10n->get('SYS_NAME'), $user->getValue('LAST_NAME'). ' '. $user->getValue('FIRST_NAME'));
            }

            // add loginname
            if(strlen($user->getValue('usr_login_name')) > 0)
            {
                if ($user->getValue('usr_id') != $gCurrentUser->getValue('usr_id'))
                {
                    $form->addStaticControl('username', $gL10n->get('SYS_USERNAME'), '<a href='.$g_root_path.'/adm_program/modules/messages/messages_write.php?msg_type=PM&usr_id='.$user->getValue('usr_id').'>'.$user->getValue('usr_login_name').'</a>');
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
                if($field->getValue('cat_name_intern') == 'MASTER_DATA'
                && ($gCurrentUser->hasRightEditProfile($user) == true || $field->getValue('usf_hidden') == 0))
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
                            if($bAddressOutput == false // output of address only once
                            && (strlen($user->getValue('ADDRESS')) > 0 || strlen($user->getValue('POSTCODE')) > 0
                               || strlen($user->getValue('CITY')) > 0 || strlen($user->getValue('COUNTRY')) > 0))
                            {
                                $bAddressOutput = true;
                                $htmlAddress    = '';
                                $address        = '';
                                $map_url        = 'http://maps.google.com/?q=';
                                $route_url      = 'http://maps.google.com/?f=d&amp;saddr='.
                                    urlencode($gCurrentUser->getValue('ADDRESS')).
                                    ',%20'. urlencode($gCurrentUser->getValue('POSTCODE')).
                                    ',%20'. urlencode($gCurrentUser->getValue('CITY')).
                                    ',%20'. urlencode($gCurrentUser->getValue('COUNTRY')).
                                    '&amp;daddr=';

                                if(strlen($user->getValue('ADDRESS')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) == true || $gProfileFields->getProperty('ADDRESS', 'usf_hidden') == 0))
                                {
                                    $address   .= $user->getValue('ADDRESS'). '<br />';
                                    $map_url   .= urlencode($user->getValue('ADDRESS'));
                                    $route_url .= urlencode($user->getValue('ADDRESS'));
                                }

                                if(strlen($user->getValue('POSTCODE')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) == true || $gProfileFields->getProperty('POSTCODE', 'usf_hidden') == 0))
                                {
                                    $address   .= $user->getValue('POSTCODE');
                                    $map_url   .= ',%20'. urlencode($user->getValue('POSTCODE'));
                                    $route_url .= ',%20'. urlencode($user->getValue('POSTCODE'));

                                    // City and postcode should be shown in one line
                                    if(strlen($user->getValue('CITY')) == 0
                                    || ($gCurrentUser->hasRightEditProfile($user) == false && $gProfileFields->getProperty('CITY', 'usf_hidden') == 1))
                                    {
                                        $address   .= '<br />';
                                    }
                                }

                                if(strlen($user->getValue('CITY')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) == true || $gProfileFields->getProperty('CITY', 'usf_hidden') == 0))
                                {
                                    // City and postcode should be shown in one line
                                    $address   .= ' '. $user->getValue('CITY'). '<br />';
                                    $map_url   .= ',%20'. urlencode($user->getValue('CITY'));
                                    $route_url .= ',%20'. urlencode($user->getValue('CITY'));
                                }

                                if(strlen($user->getValue('COUNTRY')) > 0
                                && ($gCurrentUser->hasRightEditProfile($user) == true || $gProfileFields->getProperty('COUNTRY', 'usf_hidden') == 0))
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
                                    <a class="btn" href="'. $map_url. '" target="_blank"><img src="'. THEME_PATH. '/icons/map.png"
                                        alt="'.$gL10n->get('SYS_MAP').'" />'.$gL10n->get('SYS_MAP').'</a>';

                                    // show route link if its not the profile of CurrentUser
                                    if($gCurrentUser->getValue('usr_id') != $user->getValue('usr_id'))
                                    {
                                        $htmlAddress .= ' - <a href="'.$route_url.'" target="_blank">'.$gL10n->get('SYS_SHOW_ROUTE').'</a>';
                                    }
                                }

                                $form->addStaticControl('address', $gL10n->get('SYS_ADDRESS'), $htmlAddress);
                            }
                            break;

                        default:
                            $field = getFieldCode($field->getValue('usf_name_intern'), $user);
                            if(strlen($field['value']) > 0)
                            {
                                $form->addStaticControl('address', $field['label'], $field['value']);
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

            $page->addHtml('<img id="profile_photo" class="thumbnail" src="profile_photo_show.php?usr_id='.$user->getValue('usr_id').'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');

            // Nur berechtigte User duerfen das Profilfoto editieren
            if($gCurrentUser->hasRightEditProfile($user) == true)
            {
                $page->addHtml('<div id="profile_picture_links" class="btn-group-vertical" role="group">
                    <a class="btn" href="'.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?usr_id='.$user->getValue('usr_id').'"><img
                        src="'.THEME_PATH.'/icons/photo_upload.png" alt="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" /> '.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'</a>');
                //Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
                if((strlen($user->getValue('usr_photo')) > 0 && $gPreferences['profile_photo_storage'] == 0)
                    || file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$user->getValue('usr_id').'.jpg') && $gPreferences['profile_photo_storage'] == 1)
                {
                    $page->addHtml('<a class="btn" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pro_pho&amp;element_id=no_element'.
                                    '&amp;database_id='.$user->getValue('usr_id').'"><img src="'. THEME_PATH. '/icons/delete.png"
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
    if($field->getValue('cat_name_intern') != 'MASTER_DATA'
    && ($gCurrentUser->hasRightEditProfile($user) == true
       || ($gCurrentUser->hasRightEditProfile($user) == false && $field->getValue('usf_hidden') == 0)))
    {
        // show new category header if new category and field has value or is a checkbox field
        if($category != $field->getValue('cat_name')
        && (strlen($user->getValue($field->getValue('usf_name_intern'))) > 0 || $field->getValue('usf_type') == 'CHECKBOX'))
        {
            if(strlen($category) > 0)
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
        if(strlen($user->getValue($field->getValue('usf_name_intern'))) > 0 || $field->getValue('usf_type') == 'CHECKBOX')
        {
            $field = getFieldCode($field->getValue('usf_name_intern'), $user);
            if(strlen($field['value']) > 0)
            {
                $form->addStaticControl('address', $field['label'], $field['value']);
            }
        }
    }
}

if(strlen($category) > 0)
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

    //Array mit allen Berechtigungen
    $authorizations = array('rol_assign_roles','rol_approve_users','rol_edit_user',
                            'rol_mail_to_all','rol_profile','rol_announcements',
                            'rol_dates','rol_photo','rol_download','rol_guestbook',
                            'rol_guestbook_comments','rol_weblinks', 'rol_all_lists_view');

    //Abfragen der aktiven Rollen mit Berechtigung und Schreiben in ein Array
    foreach($authorizations as $authorization_db_name)
    {
        $sql = 'SELECT rol_name
                  FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES.'
                 WHERE mem_rol_id = rol_id
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND mem_usr_id = '.$user->getValue('usr_id').'
                   AND rol_valid  = 1
                   AND rol_cat_id = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                   AND '.$authorization_db_name.' = 1
                 ORDER BY cat_org_id, cat_sequence, rol_name';
        $result_role = $gDb->query($sql);
        $berechtigungs_Herkunft[$authorization_db_name] = NULL;

        while($row = $gDb->fetch_array($result_role))
        {
            $berechtigungs_Herkunft[$authorization_db_name] = $berechtigungs_Herkunft[$authorization_db_name].', '.$row['rol_name'];
        }
    }

    $page->addHtml('
    <div class="panel panel-default" id="profile_authorizations_box">
        <div class="panel-heading">'.$gL10n->get('SYS_AUTHORIZATION').'</div>
        <div class="panel-body" id="profile_authorizations_box_body">
            <p>');

            //checkRolesRight($right)
            if($user->checkRolesRight('rol_assign_roles') == 1)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_assign_roles'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/roles.png"
                alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />');
            }
            if($user->checkRolesRight('rol_approve_users') == 1)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_approve_users'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/new_registrations.png"
                alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />');
            }
            if($user->checkRolesRight('rol_edit_user') == 1)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_edit_user'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/group.png"
                alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />');
            }

            if($user->checkRolesRight('rol_mail_to_all') == 1)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_mail_to_all'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/email.png"
                alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />');
            }
            if($user->checkRolesRight('rol_profile') == 1)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_profile'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/profile.png"
                alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />');
            }
            if($user->checkRolesRight('rol_announcements') == 1 && $gPreferences['enable_announcements_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_announcements'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/announcements.png"
                alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />');
            }
            if($user->checkRolesRight('rol_dates') == 1 && $gPreferences['enable_dates_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_dates'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/dates.png"
                alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />');
            }
            if($user->checkRolesRight('rol_photo') == 1 && $gPreferences['enable_photo_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_photo'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/photo.png"
                alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />');
            }
            if($user->checkRolesRight('rol_download') == 1 && $gPreferences['enable_download_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_download'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/download.png"
                alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />');
            }
            if($user->checkRolesRight('rol_guestbook') == 1 && $gPreferences['enable_guestbook_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_guestbook'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/guestbook.png"
                alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />');
            }
            if($user->checkRolesRight('rol_guestbook_comments') == 1 && $gPreferences['enable_guestbook_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_guestbook_comments'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/comment.png"
                alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />');
            }
            if($user->checkRolesRight('rol_weblinks') == 1 && $gPreferences['enable_weblinks_module'] > 0)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_weblinks'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/weblinks.png"
                alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />');
            }
            if($user->checkRolesRight('rol_all_lists_view') == 1)
            {
                $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_all_lists_view'], 2).'\')" class="admidio-icon-info" src="'.THEME_PATH.'/icons/lists.png"
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
    $result_role = getRolesFromDatabase($user->getValue('usr_id'));
    $count_role  = $gDb->num_rows($result_role);

    //Ausgabe
    $page->addHtml('
    <div class="panel panel-default" id="profile_roles_box">
        <div class="panel-heading">
            '.$gL10n->get('ROL_ROLE_MEMBERSHIPS').'
        </div>
        <div class="panel-body" id="profile_roles_box_body">
            '.getRoleMemberships('role_list', $user, $result_role, $count_role, false).'
        </div>
    </div>');

    // *******************************************************************************
    // block with future memberships
    // *******************************************************************************

    $count_show_roles = 0;
    $result_role = getFutureRolesFromDatabase($user->getValue('usr_id'));
    $count_role  = $gDb->num_rows($result_role);
    $visible     = "";

    if($count_role == 0)
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
            '.getRoleMemberships('future_role_list', $user, $result_role, $count_role, false).'
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
    $result_role = getFormerRolesFromDatabase($user->getValue('usr_id'));
    $count_role  = $gDb->num_rows($result_role);
    $visible     = "";

    if($count_role == 0)
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
            '.getRoleMemberships('former_role_list', $user, $result_role, $count_role, false).'
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
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_ORGANIZATIONS. '
             WHERE mem_rol_id = rol_id
               AND mem_begin <= \''.DATE_NOW.'\'
               AND mem_end   >= \''.DATE_NOW.'\'
               AND mem_usr_id = '.$user->getValue('usr_id').'
               AND rol_valid   = 1
               AND rol_visible = 1
               AND rol_cat_id  = cat_id
               AND cat_org_id  = org_id
               AND org_id    <> '. $gCurrentOrganization->getValue('org_id'). '
             ORDER BY org_shortname, cat_sequence, rol_name';
    $result_role = $gDb->query($sql);

    if($gDb->num_rows($result_role) > 0)
    {
        $showRolesOtherOrganizations = false;
        $actualOrganization = 0;
        $role = new TableRoles($gDb);

        while($row = $gDb->fetch_array($result_role))
        {
            // if roles of new organization than read the rights of this organization
            if($actualOrganization != $row['org_id'])
            {
                $gCurrentUser->setOrganization($row['org_id']);
                $actualOrganization = $row['org_id'];
            }

            // check if current user has right to view the role of that organization
            if($gCurrentUser->hasRightViewRole($row['rol_id']))
            {
                $role->clear();
                $role->setArray($row);

                if($showRolesOtherOrganizations == false)
                {
                    $page->addHtml('
                    <div class="panel panel-default" id="profile_other_orga_roles_box">
                        <div class="panel-heading">'.
                            $gL10n->get('PRO_ROLE_MEMBERSHIP_OTHER_ORG').'
                            <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PRO_VIEW_ROLES_OTHER_ORGAS&amp;inline=true"><img
                                src="'. THEME_PATH. '/icons/help.png" alt="Help" /></a>
                        </div>
                        <div class="panel-body" id="profile_other_orga_roles_box_body">
                            <ul class="list-group admidio-list-roles-assign">');

                    $showRolesOtherOrganizations = true;
                }

                $startDate = new DateTimeExtended($row['mem_begin'], 'Y-m-d', 'date');
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

        if($showRolesOtherOrganizations == true)
        {
            $page->addHtml('</ul></div></div>');
        }
    }
}

// show informations about user who creates the recordset and changed it
$page->addHtml(admFuncShowCreateChangeInfoById($user->getValue('usr_usr_id_create'), $user->getValue('usr_timestamp_create'), $user->getValue('usr_usr_id_change'), $user->getValue('usr_timestamp_change')));

$page->show();
?>
