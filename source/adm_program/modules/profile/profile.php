<?php
/******************************************************************************
 * Show user profile
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
$getUserId = admFuncVariableIsValid($_GET, 'user_id', 'numeric', $gCurrentUser->getValue('usr_id'));

// create user object
$user = new User($gDb, $gProfileFields, $getUserId);

//Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->viewProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($fieldNameIntern, $user)
{
    global $gPreferences, $g_root_path, $gCurrentUser, $gProfileFields, $gL10n;
    $html      = '';
    $value     = '';
    $msg_image = '';

    if($gCurrentUser->editProfile($user) == false && $gProfileFields->getProperty($fieldNameIntern, 'usf_hidden') == 1)
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
			<a class="admIconLink" href="http://www.icq.com/people/cmd.php?uin='.$icq_number.'&amp;action=add"><img
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
			<a class="admIconLink" href="skype:'.$user->getValue($fieldNameIntern).'?add"><img
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
        $html = '
        <div class="admFieldRow">
            <div class="admFieldLabel">'.$gProfileFields->getProperty($fieldNameIntern, 'usf_name').':</div>
            <div class="admFieldElement">'. $value. '&nbsp;</div>
        </div>';
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
    $headline = $gL10n->get('PRO_PROFILE_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
}

// if user id was not set and own profile should be shown then initialize navigation
if(isset($_GET['user_id']) == false)
{
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage();

$page->addCssFile(THEME_PATH. '/css/calendar.css');
$page->addJavascriptFile($g_root_path.'/adm_program/system/js/date-functions.js');
$page->addJavascriptFile($g_root_path.'/adm_program/system/js/form.js');
$page->addJavascriptFile($g_root_path.'/adm_program/modules/profile/profile.js');

$page->addJavascript('
    var profileJS = new profileJSClass();
    profileJS.deleteRole_ConfirmText 	= "'.$gL10n->get('ROL_MEMBERSHIP_DEL','[rol_name]').'";
    profileJS.deleteFRole_ConfirmText 	= "'.$gL10n->get('ROL_LINK_MEMBERSHIP_DEL','[rol_name]').'";
    profileJS.changeRoleDates_ErrorText = "'.$gL10n->get('ROL_CHANGE_ROLE_DATES_ERROR').'";
    profileJS.setBy_Text				= "'.$gL10n->get('SYS_SET_BY').'";
    profileJS.usr_id                    = '.$user->getValue('usr_id').';
    
    function showHideMembershipInformation(element)
    {
        id = "#" + element.attr("id") + "_Content"; 

        if($(id).css("display") == "none") {
            $(id).show("fast");
        }
        else {
            $(id).hide("fast");
        }
    }');
$page->addJavascript('
    profileJS.init();
    $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
    $(".admMemberInfo").click(function () { showHideMembershipInformation($(this)) });', true);

// show back link
if($gNavigation->count() > 1)
{
    $page->addHtml($gNavigation->getHtmlBackButton());
}
    
// add headline and title of module
$page->addHeadline($headline);

// create module menu
$profileMenu = new ModuleMenu('menu_profile');


// if user has right then show link to edit profile
if($gCurrentUser->editProfile($user))
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
    	$profileMenu->addItem('menu_item_send_password', $g_root_path.'/adm_program/administration/members/members_function.php?usr_id='.$user->getValue('usr_id').'&amp;mode=5', 
    						$gL10n->get('ORG_SEND_NEW_PASSWORD'), 'key.png');
    }
    else
    {
        // if user has no email or send email is disabled then webmaster could set a new password
    	$profileMenu->addItem('menu_item_password', $g_root_path. '/adm_program/modules/profile/password.php?usr_id='. $user->getValue('usr_id'), 
    						$gL10n->get('SYS_CHANGE_PASSWORD'), 'key.png');        
    }
}

// show link to export the profile as vCard
$profileMenu->addItem('menu_item_vcard', $g_root_path.'/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id='. $user->getValue('usr_id'), 
                        $gL10n->get('PRO_EXPORT_VCARD'), 'vcard.png');

// show link to view profile field change history
if($gPreferences['profile_log_edit_fields'] == 1)
{
	$profileMenu->addItem('menu_item_change_history', $g_root_path. '/adm_program/administration/members/profile_field_history.php?usr_id='. $user->getValue('usr_id'), 
                        $gL10n->get('MEM_CHANGE_HISTORY'), 'clock.png');
}

$page->addHtml($profileMenu->show(false));

$page->addHtml('
<div id="profile_form">
    <div>');
        // *******************************************************************************
        // Userdaten-Block
        // *******************************************************************************

        $page->addHtml('
        <div style="width: 65%; float: left;">
            <div id="profile_master_data" class="admGroupBox">
                <div class="admGroupBoxHeadline">'.
                    $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));

                    // Icon des Geschlechts anzeigen, wenn noetigen Rechte vorhanden
                    if(strlen($user->getValue('GENDER')) > 0
                    && ($gCurrentUser->editProfile($user) == true || $gProfileFields->getProperty('GENDER', 'usf_hidden') == 0 ))
                    {
                        $page->addHtml(' '.$user->getValue('GENDER', 'html'));
                    }
                $page->addHtml('</div>
                <div class="admGroupBoxBody">
                    <div class="admFieldViewList">
                        <div class="admFieldRow">
                            <div class="admFieldLabel">'.$gL10n->get('SYS_USERNAME').':</div>
                            <div class="admFieldElement"><i>');
                                if(strlen($user->getValue('usr_login_name')) > 0)
                                {
                                    if ($user->getValue('usr_id') != $gCurrentUser->getValue('usr_id'))
                                    {
                                        $page->addHtml('<a href='.$g_root_path.'/adm_program/modules/messages/messages.php?msg_type=PM&usr_id='.$user->getValue('usr_id').'>'.$user->getValue('usr_login_name').'</a>');
                                    }
                                    else
                                    {
                                        $page->addHtml($user->getValue('usr_login_name'));
                                    }
                                }
                                else
                                {
                                    $page->addHtml($gL10n->get('SYS_NOT_REGISTERED'));
                                }
                                $page->addHtml('&nbsp;</i>
                            </div>
                        </div>');

                        $bAddressOutput = false;    // Merker, ob die Adresse schon angezeigt wurde

                        // Schleife ueber alle Felder der Stammdaten

                        foreach($gProfileFields->mProfileFields as $field)
                        {
                            // nur Felder der Stammdaten anzeigen
                            if($field->getValue('cat_name_intern') == 'MASTER_DATA'
                            && ($gCurrentUser->editProfile($user) == true || $field->getValue('usf_hidden') == 0 ))
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
                                        && (  strlen($user->getValue('ADDRESS')) > 0 || strlen($user->getValue('POSTCODE')) > 0 
                                           || strlen($user->getValue('CITY')) > 0 || strlen($user->getValue('COUNTRY')) > 0 ))   
                                        {
                                            $bAddressOutput = true;
                                            $page->addHtml('
                                            <div class="admFieldRow">
                                                <div class="admFieldLabel">'.$gL10n->get('SYS_ADDRESS').':</div>
                                                <div class="admFieldElement">');
                                                    $address = '';
                                                    $map_url = 'http://maps.google.com/?q=';
                                                    $route_url = 'http://maps.google.com/?f=d&amp;saddr='. 
                                                        urlencode($gCurrentUser->getValue('ADDRESS')).
                                                        ',%20'. urlencode($gCurrentUser->getValue('POSTCODE')).
                                                        ',%20'. urlencode($gCurrentUser->getValue('CITY')).
                                                        ',%20'. urlencode($gCurrentUser->getValue('COUNTRY')).
                                                        '&amp;daddr=';

                                                    if(strlen($user->getValue('ADDRESS')) > 0
                                                    && ($gCurrentUser->editProfile($user) == true || $gProfileFields->getProperty('ADDRESS', 'usf_hidden') == 0))
                                                    {
                                                        $address   .= '<div>'.$user->getValue('ADDRESS'). '</div>';
                                                        $map_url   .= urlencode($user->getValue('ADDRESS'));
                                                        $route_url .= urlencode($user->getValue('ADDRESS'));
                                                    }

                                                    if(strlen($user->getValue('POSTCODE')) > 0
                                                    && ($gCurrentUser->editProfile($user) == true || $gProfileFields->getProperty('POSTCODE', 'usf_hidden') == 0))
                                                    {
                                                        $address   .= '<div>'.$user->getValue('POSTCODE');
                                                        $map_url   .= ',%20'. urlencode($user->getValue('POSTCODE'));
                                                        $route_url .= ',%20'. urlencode($user->getValue('POSTCODE'));

                                                        // Ort und PLZ in eine Zeile schreiben, falls man beides sehen darf
                                                        if(strlen($user->getValue('CITY')) == 0
                                                        || ($gCurrentUser->editProfile($user) == false && $gProfileFields->getProperty('CITY', 'usf_hidden') == 1))
                                                        {
                                                            $address   .= '</div>';
                                                        }
                                                    }

                                                    if(strlen($user->getValue('CITY')) > 0
                                                    && ($gCurrentUser->editProfile($user) == true || $gProfileFields->getProperty('CITY', 'usf_hidden') == 0))
                                                    {
                                                        // Ort und PLZ in eine Zeile schreiben, falls man beides sehen darf
                                                        if(strlen($user->getValue('POSTCODE')) == 0
                                                        || ($gCurrentUser->editProfile($user) == false && $gProfileFields->getProperty('POSTCODE', 'usf_hidden') == 1))
                                                        {
                                                            $address   .= '<div>';
                                                        }
                                                        $address   .= ' '. $user->getValue('CITY'). '</div>';
                                                        $map_url   .= ',%20'. urlencode($user->getValue('CITY'));
                                                        $route_url .= ',%20'. urlencode($user->getValue('CITY'));
                                                    }

                                                    if(strlen($user->getValue('COUNTRY')) > 0
                                                    && ($gCurrentUser->editProfile($user) == true || $gProfileFields->getProperty('COUNTRY', 'usf_hidden') == 0))
                                                    {
                                                        $country    = $user->getValue('COUNTRY');
                                                        $address   .= '<div>'.$country. '</div>';
                                                        $map_url   .= ',%20'. urlencode($country);
                                                        $route_url .= ',%20'. urlencode($country);
                                                    }

                                                    $page->addHtml($address);

                                                    // show route or address link if function is enabled and user has filled address or city
                                                    if($gPreferences['profile_show_map_link'] && strlen($user->getValue('ADDRESS')) > 0 
                                                    && (strlen($user->getValue('POSTCODE')) > 0 || strlen($user->getValue('CITY')) > 0))
                                                    {
                                                        $page->addHtml('<span class="admIconTextLink">
                                                            <a href="'. $map_url. '" target="_blank"><img
                                                                src="'. THEME_PATH. '/icons/map.png" alt="'.$gL10n->get('SYS_MAP').'" /></a>
                                                            <a href="'. $map_url. '" target="_blank">'.$gL10n->get('SYS_MAP').'</a>
                                                        </span>');

                                                        // show route link if its not the profile of CurrentUser
                                                        if($gCurrentUser->getValue('usr_id') != $user->getValue('usr_id'))
                                                        {
                                                            $page->addHtml(' - <a href="'.$route_url.'" target="_blank">'.$gL10n->get('SYS_SHOW_ROUTE').'</a>');
                                                        }
                                                    }
                                                $page->addHtml('</div>
                                            </div>');
                                        }
                                        break;

                                    default:
                                        $page->addHtml(getFieldCode($field->getValue('usf_name_intern'), $user));
                                        break;
                                }
                            }
                        }
                    $page->addHtml('</div>
                </div>
            </div>
        </div>

        <div style="width: 28%; float: right">');

            // *******************************************************************************
            // Profile photo
            // *******************************************************************************

            $page->addHtml('
            <div id="admProfilePhoto" class="admGroupBox">
                <div class="admGroupBoxBody" style="text-align: center;">
                    <div id="profile_picture">
                        <img src="profile_photo_show.php?usr_id='.$user->getValue('usr_id').'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');

                        // Nur berechtigte User duerfen das Profilfoto editieren
                        if($gCurrentUser->editProfile($user) == true)
                        {
                            $pictureLinks = '<a class="admIconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?usr_id='.$user->getValue('usr_id').'"><img
                                            src="'.THEME_PATH.'/icons/photo_upload.png" alt="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" title="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" /></a>';
                            //Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
                            if((strlen($user->getValue('usr_photo')) > 0 && $gPreferences['profile_photo_storage'] == 0)
                                || file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$user->getValue('usr_id').'.jpg') && $gPreferences['profile_photo_storage'] == 1 )
                            {
                                $pictureLinks .= '<a class="admIconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pro_pho&amp;element_id=no_element'.
                                                '&amp;database_id='.$user->getValue('usr_id').'"><img src="'. THEME_PATH. '/icons/delete.png" 
                                                alt="'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'" title="'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'" /></a>';
                            }
                            $page->addHtml('<div id="profile_picture_links">'.$pictureLinks.'</div>');
                        }
                    $page->addHtml('
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="clear: left; font-size: 1pt;">&nbsp;</div>');

    // *******************************************************************************
    // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
    // *******************************************************************************

    $category = '';
    foreach($gProfileFields->mProfileFields as $field)
    {
        // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
        // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
        if($field->getValue('cat_name_intern') != 'MASTER_DATA'
        && (  $gCurrentUser->editProfile($user) == true
           || ($gCurrentUser->editProfile($user) == false && $field->getValue('usf_hidden') == 0 )))
        {
            // show new category header if new category and field has value or is a checkbox field
            if($category != $field->getValue('cat_name')
            && (strlen($user->getValue($field->getValue('usf_name_intern'))) > 0 || $field->getValue('usf_type') == 'CHECKBOX'))
            {
                if(strlen($category) > 0)
                {
                    // div-Container admGroupBoxBody und admGroupBox schliessen
                    $page->addHtml('</div></div></div>');
                }
                $category = $field->getValue('cat_name');

                $page->addHtml('<div class="admGroupBox">
                    <div class="admGroupBoxHeadline">'.$field->getValue('cat_name').'</div>
                    <div class="admGroupBoxBody">
                        <div class="admFieldViewList">');
            }

            // show html of field, if user has a value for that field or it's a checkbox field
            if(strlen($user->getValue($field->getValue('usf_name_intern'))) > 0 || $field->getValue('usf_type') == 'CHECKBOX')
            {
                $page->addHtml(getFieldCode($field->getValue('usf_name_intern'), $user));
            }
        }
    }

    if(strlen($category) > 0)
    {
        // div-Container admGroupBoxBody und admGroupBox schliessen
        $page->addHtml('</div></div></div>');
    }

    if($gPreferences['profile_show_roles'] == 1)
    {
        // *******************************************************************************
        // Berechtigungen-Block
        // *******************************************************************************

        //Array mit allen Berechtigungen
        $authorizations = Array('rol_assign_roles','rol_approve_users','rol_edit_user',
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
        <div class="admGroupBox" id="profile_authorizations_box">
            <div class="admGroupBoxHeadline">
                <div style="float: left;">'.$gL10n->get('SYS_AUTHORIZATION').'&nbsp;</div>
            </div>
            <div class="admGroupBoxBody" onmouseout="profileJS.deleteShowInfo()">');
                //checkRolesRight($right)
                if($user->checkRolesRight('rol_assign_roles') == 1)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_assign_roles'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/roles.png"
                    alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />');
                }
                if($user->checkRolesRight('rol_approve_users') == 1)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_approve_users'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/new_registrations.png"
                    alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />');
                }
                if($user->checkRolesRight('rol_edit_user') == 1)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_edit_user'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/group.png"
                    alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />');
                }

                if($user->checkRolesRight('rol_mail_to_all') == 1)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_mail_to_all'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/email.png"
                    alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />');
                }
                if($user->checkRolesRight('rol_profile') == 1)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_profile'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/profile.png"
                    alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />');
                }
                if($user->checkRolesRight('rol_announcements') == 1 && $gPreferences['enable_announcements_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_announcements'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/announcements.png"
                    alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />');
                }
                if($user->checkRolesRight('rol_dates') == 1 && $gPreferences['enable_dates_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_dates'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/dates.png"
                    alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />');
                }
                if($user->checkRolesRight('rol_photo') == 1 && $gPreferences['enable_photo_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_photo'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/photo.png"
                    alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />');
                }
                if($user->checkRolesRight('rol_download') == 1 && $gPreferences['enable_download_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_download'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/download.png"
                    alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />');
                }
                if($user->checkRolesRight('rol_guestbook') == 1 && $gPreferences['enable_guestbook_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_guestbook'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/guestbook.png"
                    alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />');
                }
                if($user->checkRolesRight('rol_guestbook_comments') == 1 && $gPreferences['enable_guestbook_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_guestbook_comments'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/comments.png"
                    alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />');
                }
                if($user->checkRolesRight('rol_weblinks') == 1 && $gPreferences['enable_weblinks_module'] > 0)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_weblinks'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/weblinks.png"
                    alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />');
                }
                if($user->checkRolesRight('rol_all_lists_view') == 1)
                {
                    $page->addHtml('<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_all_lists_view'],2).'\')" class="admIconInformation" src="'.THEME_PATH.'/icons/lists.png"
                    alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" />');
                }
            $page->addHtml('</div>
            <div><p id="anzeige">'.$gL10n->get('SYS_SET_BY').':</p></div>
        </div>');

        // *******************************************************************************
        // Rollen-Block
        // *******************************************************************************

        // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
        $count_show_roles = 0;
        $result_role = getRolesFromDatabase($user->getValue('usr_id'));
        $count_role  = $gDb->num_rows($result_role);

        //Ausgabe
        $page->addHtml('<div class="admGroupBox admProfileRolesBox" id="profile_roles_box">
            <div class="admGroupBoxHeadline">
                <div style="float: left;">'.$gL10n->get('ROL_ROLE_MEMBERSHIPS').'&nbsp;</div>');
                    // Moderatoren & Gruppenleiter duerfen neue Rollen zuordnen
                    if($gCurrentUser->assignRoles())
                    {
                        $page->addHtml('
                        <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
                        <script type="text/javascript">
                                var calPopup = new CalendarPopup("calendardiv");
                                calPopup.setCssPrefix("calendar");
                        </script>
                        <div style="text-align: right;">
                            <span class="admIconTextLink">
                                <a rel="colorboxRoles" href="'.$g_root_path.'/adm_program/modules/profile/roles.php?usr_id='.$user->getValue('usr_id').'&amp;inline=1"><img 
                                    src="'.THEME_PATH.'/icons/edit.png" title="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'" alt="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'" /></a>
                                <a rel="colorboxRoles" href="'.$g_root_path.'/adm_program/modules/profile/roles.php?usr_id='.$user->getValue('usr_id').'&amp;inline=1">'.$gL10n->get('SYS_EDIT').'</a>
                            </span>
                        </div>');
                    }
            $page->addHtml('</div>
            <div id="profile_roles_box_body" class="admGroupBoxBody">
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
        $page->addHtml('<div class="admGroupBox admProfileRolesBox" id="profile_future_roles_box" '.$visible.'>
            <div class="admGroupBoxHeadline">'.$gL10n->get('PRO_FUTURE_ROLE_MEMBERSHIP').'&nbsp;</div>
            <div id="profile_future_roles_box_body" class="admGroupBoxBody">
                '.getRoleMemberships('future_role_list',$user,$result_role,$count_role,false).'
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
        $page->addHtml('<div class="admGroupBox admProfileRolesBox" id="profile_former_roles_box" '.$visible.'>
            <div class="admGroupBoxHeadline">'.$gL10n->get('PRO_FORMER_ROLE_MEMBERSHIP').'&nbsp;</div>
            <div id="profile_former_roles_box_body" class="admGroupBoxBody">
                '.getRoleMemberships('former_role_list',$user,$result_role,$count_role,false).'
            </div>
        </div>');
    }

    if($gPreferences['profile_show_extern_roles'] == 1
    && (  $gCurrentOrganization->getValue('org_org_id_parent') > 0
       || $gCurrentOrganization->hasChildOrganizations() ))
    {
        // *******************************************************************************
        // Rollen-Block anderer Organisationen
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
                if($gCurrentUser->viewRole($row['rol_id']))
                {
                    $role->clear();
                    $role->setArray($row);
                    
                    if($showRolesOtherOrganizations == false)
                    {
                        $page->addHtml('<div class="admGroupBox admProfileRolesBox" id="profile_roles_box_other_orga">
                            <div class="admGroupBoxHeadline">'.$gL10n->get('PRO_ROLE_MEMBERSHIP_OTHER_ORG').'&nbsp;
                                <a class="admIconHelpLink" rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PRO_VIEW_ROLES_OTHER_ORGAS&amp;inline=true"><img 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=PRO_VIEW_ROLES_OTHER_ORGAS\',this)" onmouseout="ajax_hideTooltip()"
                                    src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                            </div>
                            <div class="admGroupBoxBody">
                                <div class="admFieldViewList">');
                        $showRolesOtherOrganizations = true;
                    }
                    
                    $startDate = new DateTimeExtended($row['mem_begin'], 'Y-m-d', 'date');
                    // jede einzelne Rolle anzeigen
                    $page->addHtml('
                    <div class="admFieldRow">
                        <div class="admFieldLabel">'.
                            $row['org_shortname'].' - '.$role->getValue('cat_name').' - '.$role->getValue('rol_name'));
                            
                            if($row['mem_leader'] == 1)
                            {
                                $page->addHtml(' - '.$gL10n->get('SYS_LEADER'));
                            }
                            $page->addHtml('&nbsp;
                        </div>
                        <div class="admFieldElement">'.$gL10n->get('SYS_SINCE',$startDate->format($gPreferences['system_date'])).'</div>
                    </div>');
                }
            }
            
            $gCurrentUser->setOrganization($gCurrentOrganization->getValue('org_id'));
            
            if($showRolesOtherOrganizations == true)
            {
                        $page->addHtml('</div>
                    </div>
                </div>');
            }
        }
    }

    // show informations about user who creates the recordset and changed it
    $page->addHtml(admFuncShowCreateChangeInfoById($user->getValue('usr_usr_id_create'), $user->getValue('usr_timestamp_create'), $user->getValue('usr_usr_id_change'), $user->getValue('usr_timestamp_change')).'
</div>');

$page->show();
?>