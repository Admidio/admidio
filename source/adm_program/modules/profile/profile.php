<?php
/******************************************************************************
 * Profil anzeigen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * user_id: zeigt das Profil der uebergebenen user_id an
 *          (wird keine user_id uebergeben, dann Profil des eingeloggten Users anzeigen)
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');
require_once('roles_functions.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'user_id', 'numeric', $gCurrentUser->getValue('usr_id'));

//Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->viewProfile($getUserId))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($fieldNameIntern, $User)
{
    global $gPreferences, $g_root_path, $gCurrentUser, $gProfileFields, $gL10n;
    $html      = '';
    $value     = '';
    $msg_image = '';

    if($gCurrentUser->editProfile($User->getValue('usr_id')) == false && $gProfileFields->getProperty($fieldNameIntern, 'usf_hidden') == 1)
    {
        return '';
    }

	// get value of field in html format
	$value = $User->getValue($fieldNameIntern, 'html');

	// if birthday then show age
	if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'BIRTHDAY')
	{
		$birthday = new DateTimeExtended($User->getValue($fieldNameIntern, $gPreferences['system_date']), $gPreferences['system_date'], 'date');
		$value = $value. '&nbsp;&nbsp;&nbsp;('. $birthday->getAge(). ' '.$gL10n->get('PRO_YEARS').')';
	}

	// Icons der Messenger anzeigen
	if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'ICQ')
	{
		if(strlen($User->getValue($fieldNameIntern)) > 0)
		{
			// Sonderzeichen aus der ICQ-Nummer entfernen (damit kommt www.icq.com nicht zurecht)
			preg_match_all('/\d+/', $User->getValue($fieldNameIntern), $matches);
			$icq_number = implode("", reset($matches));

			// ICQ Onlinestatus anzeigen
			$value = '
			<a class="iconLink" href="http://www.icq.com/people/cmd.php?uin='.$icq_number.'&amp;action=add"><img
				src="http://status.icq.com/online.gif?icq='.$icq_number.'&amp;img=5"
				alt="'.$gL10n->get('PRO_TO_ADD', $User->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'"
				title="'.$gL10n->get('PRO_TO_ADD', $User->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'" /></a> '.$value;
		}
	}
	elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'SKYPE')
	{
		if(strlen($User->getValue($fieldNameIntern)) > 0)
		{
			// Skype Onlinestatus anzeigen
			$value = '<script type="text/javascript" src="http://download.skype.com/share/skypebuttons/js/skypeCheck.js"></script>
			<a class="iconLink" href="skype:'.$User->getValue($fieldNameIntern).'?add"><img
				src="http://mystatus.skype.com/smallicon/'.$User->getValue($fieldNameIntern).'"
				title="'.$gL10n->get('PRO_TO_ADD', $User->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'"
				alt="'.$gL10n->get('PRO_TO_ADD', $User->getValue($fieldNameIntern), $gProfileFields->getProperty($fieldNameIntern, 'usf_name')).'" /></a> '.$value;
		}
	}
	elseif(strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_icon')) > 0)
	{
		$value = $gProfileFields->getProperty($fieldNameIntern, 'usf_icon').'&nbsp;&nbsp;'. $value;
	}

	// show html of field, if user has a value for that field or it's a checkbox field
    if(strlen($User->getValue($fieldNameIntern)) > 0 || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'CHECKBOX')
    {
        $html = '<li>
                    <dl>
                        <dt>'. $gProfileFields->getProperty($fieldNameIntern, 'usf_name'). ':</dt>
                        <dd>'. $value. '&nbsp;</dd>
                    </dl>
                </li>';
    }

    return $html;
}

// User auslesen
$user = new User($gDb, $gProfileFields, $getUserId);

unset($_SESSION['profile_request']);
// Seiten fuer Zuruecknavigation merken
if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
{
    $gLayout['title'] = $gL10n->get('PRO_MY_PROFILE');
}
else
{
    $gLayout['title'] = $gL10n->get('PRO_PROFILE_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
}
$gLayout['header'] = '
    <link rel="stylesheet" href="'.THEME_PATH. '/css/calendar.css" type="text/css" />
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/form.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/modules/profile/profile.js"></script>
    <script type="text/javascript">
    <!--
        var profileJS = new profileJSClass();
            profileJS.deleteRole_ConfirmText 	= \''.$gL10n->get('ROL_MEMBERSHIP_DEL',"[rol_name]").'\';
            profileJS.deleteFRole_ConfirmText 	= \''.$gL10n->get('ROL_LINK_MEMBERSHIP_DEL',"[rol_name]").'\';
            profileJS.changeRoleDates_ErrorText = \''.$gL10n->get('ROL_CHANGE_ROLE_DATES_ERROR').'\';
            profileJS.setBy_Text				= \''.$gL10n->get('SYS_SET_BY').'\';
            profileJS.usr_id = '.$user->getValue('usr_id').';
			$(document).ready(function() {
				profileJS.init();
				$("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
			});
    //-->
    </script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<div class="formLayout" id="profile_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
        <div>';
            // *******************************************************************************
            // Userdaten-Block
            // *******************************************************************************

            echo '
            <div style="width: 65%; float: left;">
                <div id="admProfileMasterData" class="groupBox">
                    <div class="groupBoxHeadline">
                        <div style="float: left;">'. $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME');

                            // Icon des Geschlechts anzeigen, wenn noetigen Rechte vorhanden
                            if(strlen($user->getValue('GENDER')) > 0
                            && ($gCurrentUser->editProfile($user->getValue('usr_id')) == true || $gProfileFields->getProperty('GENDER', 'usf_hidden') == 0 ))
                            {
                                echo ' '.$user->getValue('GENDER');
                            }
                        echo '</div>
                        <div style="text-align: right;">
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id='. $user->getValue('usr_id'). '"><img
                                src="'. THEME_PATH. '/icons/vcard.png"
                                alt="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME')).'"
                                title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME')).'" /></a>';

                            // Nur berechtigte User duerfen das Passwort editieren
                            if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id') || $gCurrentUser->isWebmaster())
                            {
                                echo'
                                <a rel="colorboxPWContent" href="password.php?usr_id='. $user->getValue('usr_id'). '&amp;inline=1"><img
                                    src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" /></a>';
                            }
                            // Nur berechtigte User duerfen ein Profil editieren
                            if($gCurrentUser->editProfile($user->getValue('usr_id')) == true)
                            {
                                echo '
                                <a class="iconLink" href="'. $g_root_path. '/adm_program/modules/profile/profile_new.php?user_id='. $user->getValue('usr_id'). '"><img
                                    src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('PRO_EDIT_PROFILE').'" title="'.$gL10n->get('PRO_EDIT_PROFILE').'" /></a>';
                            }
                        echo '</div>
                    </div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt>'.$gL10n->get('SYS_USERNAME').':</dt>
                                    <dd><i>';
                                    if(strlen($user->getValue('usr_login_name')) > 0)
                                    {
                                        echo $user->getValue('usr_login_name');
                                    }
                                    else
                                    {
                                        echo $gL10n->get('SYS_NOT_REGISTERED');
                                    }
                                    echo '&nbsp;</i></dd>
                                </dl>
                            </li>';

                            $bAddressOutput = false;    // Merker, ob die Adresse schon angezeigt wurde

                            // Schleife ueber alle Felder der Stammdaten

                            foreach($gProfileFields->mProfileFields as $field)
                            {
                                // nur Felder der Stammdaten anzeigen
                                if($field->getValue('cat_name_intern') == 'MASTER_DATA'
                                && (  $gCurrentUser->editProfile($user->getValue('usr_id')) == true || $field->getValue('usf_hidden') == 0 ))
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
												echo '<li>
                                                    <dl>
                                                        <dt>'.$gL10n->get('SYS_ADDRESS').':</dt>
                                                        <dd>';
                                                            $address = '';
                                                            $map_url = 'http://maps.google.com/?q=';
                                                            $route_url = 'http://maps.google.com/?f=d&amp;saddr='. 
                                                                urlencode($gCurrentUser->getValue('ADDRESS')).
                                                                ',%20'. urlencode($gCurrentUser->getValue('POSTCODE')).
                                                                ',%20'. urlencode($gCurrentUser->getValue('CITY')).
                                                                ',%20'. urlencode($gCurrentUser->getValue('COUNTRY')).
                                                                '&amp;daddr=';

                                                            if(strlen($user->getValue('ADDRESS')) > 0
                                                            && ($gCurrentUser->editProfile($user->getValue('usr_id')) == true || $gProfileFields->getProperty('ADDRESS', 'usf_hidden') == 0))
                                                            {
                                                                $address   .= '<div>'.$user->getValue('ADDRESS'). '</div>';
                                                                $map_url   .= urlencode($user->getValue('ADDRESS'));
                                                                $route_url .= urlencode($user->getValue('ADDRESS'));
                                                            }

                                                            if(strlen($user->getValue('POSTCODE')) > 0
                                                            && ($gCurrentUser->editProfile($user->getValue('usr_id')) == true || $gProfileFields->getProperty('POSTCODE', 'usf_hidden') == 0))
                                                            {
                                                                $address   .= '<div>'.$user->getValue('POSTCODE');
                                                                $map_url   .= ',%20'. urlencode($user->getValue('POSTCODE'));
                                                                $route_url .= ',%20'. urlencode($user->getValue('POSTCODE'));

																// Ort und PLZ in eine Zeile schreiben, falls man beides sehen darf
	                                                            if(strlen($user->getValue('CITY')) == 0
	                                                            || ($gCurrentUser->editProfile($user->getValue('usr_id')) == false && $gProfileFields->getProperty('CITY', 'usf_hidden') == 1))
	                                                            {
	                                                                $address   .= '</div>';
	                                                            }
                                                            }

                                                            if(strlen($user->getValue('CITY')) > 0
                                                            && ($gCurrentUser->editProfile($user->getValue('usr_id')) == true || $gProfileFields->getProperty('CITY', 'usf_hidden') == 0))
                                                            {
                                                            	// Ort und PLZ in eine Zeile schreiben, falls man beides sehen darf
	                                                            if(strlen($user->getValue('POSTCODE')) == 0
	                                                            || ($gCurrentUser->editProfile($user->getValue('usr_id')) == false && $gProfileFields->getProperty('POSTCODE', 'usf_hidden') == 1))
	                                                            {
	                                                                $address   .= '<div>';
	                                                            }
                                                                $address   .= ' '. $user->getValue('CITY'). '</div>';
                                                                $map_url   .= ',%20'. urlencode($user->getValue('CITY'));
                                                                $route_url .= ',%20'. urlencode($user->getValue('CITY'));
                                                            }

                                                            if(strlen($user->getValue('COUNTRY')) > 0
                                                            && ($gCurrentUser->editProfile($user->getValue('usr_id')) == true || $gProfileFields->getProperty('COUNTRY', 'usf_hidden') == 0))
                                                            {
																$country    = $user->getValue('COUNTRY');
                                                                $address   .= '<div>'.$country. '</div>';
                                                                $map_url   .= ',%20'. urlencode($country);
                                                                $route_url .= ',%20'. urlencode($country);
                                                            }

                                                            echo $address;

															// show route or address link if function is enabled and user has filled address or city
                                                            if($gPreferences['profile_show_map_link'] && strlen($user->getValue('ADDRESS')) > 0 
															&& (strlen($user->getValue('POSTCODE')) > 0 || strlen($user->getValue('CITY')) > 0))
                                                            {
                                                                echo '<span class="iconTextLink">
                                                                    <a href="'. $map_url. '" target="_blank"><img
                                                                        src="'. THEME_PATH. '/icons/map.png" alt="'.$gL10n->get('SYS_MAP').'" /></a>
                                                                    <a href="'. $map_url. '" target="_blank">'.$gL10n->get('SYS_MAP').'</a>
                                                                </span>';

																// show route link if its not the profile of CurrentUser
                                                                if($gCurrentUser->getValue('usr_id') != $user->getValue('usr_id'))
                                                                {
                                                                    echo ' - <a href="'.$route_url.'" target="_blank">'.$gL10n->get('SYS_SHOW_ROUTE').'</a>';
                                                                }
                                                            }
                                                        echo '</dd>
                                                    </dl>
                                                </li>';
                                            }
                                            break;

                                        default:
                                            echo getFieldCode($field->getValue('usf_name_intern'), $user);
                                            break;
                                    }
                                }
                            }
                        echo '</ul>
                    </div>
                </div>
            </div>';

            echo '<div style="width: 28%; float: right">';

                // *******************************************************************************
                // Profile photo
                // *******************************************************************************

                echo '
                <div id="admProfilePhoto" class="groupBox">
                    <div class="groupBoxBody" style="text-align: center;">
                        <table width="100%" summary="Profilfoto" border="0" style="border:0px;" cellpadding="0" cellspacing="0" rules="none">
                            <tr>
                                <td>
                                	<img id="profile_picture" src="profile_photo_show.php?usr_id='.$user->getValue('usr_id').'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />
                                </td>
                            </tr>';
                             // Nur berechtigte User duerfen das Profilfoto editieren
                            if($gCurrentUser->editProfile($user->getValue('usr_id')) == true)
                            {
                                echo '
                                <tr>
                                    <td align="center">
                                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?usr_id='.$user->getValue('usr_id').'"><img
                                            src="'.THEME_PATH.'/icons/photo_upload.png" alt="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" title="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" /></a>';
                                    //Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
                                    if((strlen($user->getValue('usr_photo')) > 0 && $gPreferences['profile_photo_storage'] == 0)
                                    	|| file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$user->getValue('usr_id').'.jpg') && $gPreferences['profile_photo_storage'] == 1 )
                                    {
                                        echo '<a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pro_pho&amp;element_id=no_element'.
                                            '&amp;database_id='.$user->getValue('usr_id').'"><img src="'. THEME_PATH. '/icons/delete.png" 
                                            alt="'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'" title="'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'" /></a>';
                                    }
                                echo '</td>
                                </tr>';
                            }
                        echo '</table>
                    </div>
                </div>
            </div>
        </div>

        <div style="clear: left; font-size: 1pt;">&nbsp;</div>';

        // *******************************************************************************
        // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
        // *******************************************************************************

        $category = '';
        foreach($gProfileFields->mProfileFields as $field)
        {
            // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
            // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
            if($field->getValue('cat_name_intern') != 'MASTER_DATA'
            && (  $gCurrentUser->editProfile($user->getValue('usr_id')) == true
               || ($gCurrentUser->editProfile($user->getValue('usr_id')) == false && $field->getValue('usf_hidden') == 0 )))
            {
                // show new category header if new category and field has value or is a checkbox field
                if($category != $field->getValue('cat_name')
                && (strlen($user->getValue($field->getValue('usf_name_intern'))) > 0 || $field->getValue('usf_type') == 'CHECKBOX'))
                {
                    if(strlen($category) > 0)
                    {
                        // div-Container groupBoxBody und groupBox schliessen
                        echo '</ul></div></div>';
                    }
                    $category = $field->getValue('cat_name');

                    echo '<div class="groupBox">
                        <div class="groupBoxHeadline">
                            <div style="float: left;">'.$field->getValue('cat_name').'</div>';
                            // Nur berechtigte User duerfen ein Profil editieren
                            if($gCurrentUser->editProfile($user->getValue('usr_id')) == true)
                            {
                                echo '
                                <div style="text-align: right;">
                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='.$user->getValue('usr_id').'#cat-'.$field->getValue('cat_id').'"><img
                                        src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT_VAR',$field->getValue('cat_name')).'" title="'.$gL10n->get('SYS_EDIT_VAR',$field->getValue('cat_name')).'" /></a>
                                </div>';
                            }
                        echo '</div>
                        <div class="groupBoxBody">
                            <ul class="formFieldList">';
                }

				// show html of field, if user has a value for that field or it's a checkbox field
                if(strlen($user->getValue($field->getValue('usf_name_intern'))) > 0 || $field->getValue('usf_type') == 'CHECKBOX')
                {
                    echo getFieldCode($field->getValue('usf_name_intern'), $user);
                }
            }
        }

        if(strlen($category) > 0)
        {
            // div-Container groupBoxBody und groupBox schliessen
            echo '</ul></div></div>';
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

            echo '<div class="groupBox" id="profile_authorizations_box">
                     <div class="groupBoxHeadline">
                        <div style="float: left;">'.$gL10n->get('SYS_AUTHORIZATION').'&nbsp;</div>
                     </div>
                     <div class="groupBoxBody" onmouseout="profileJS.deleteShowInfo()">';
            //checkRolesRight($right)
              if($user->checkRolesRight('rol_assign_roles') == 1)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_assign_roles'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/roles.png"
                  alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />';
              }
              if($user->checkRolesRight('rol_approve_users') == 1)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_approve_users'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/new_registrations.png"
                  alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />';
              }
              if($user->checkRolesRight('rol_edit_user') == 1)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_edit_user'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/group.png"
                  alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />';
              }

              if($user->checkRolesRight('rol_mail_to_all') == 1)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_mail_to_all'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/email.png"
                  alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />';
              }
              if($user->checkRolesRight('rol_profile') == 1)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_profile'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/profile.png"
                  alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />';
              }
              if($user->checkRolesRight('rol_announcements') == 1 && $gPreferences['enable_announcements_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_announcements'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/announcements.png"
                  alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />';
              }
              if($user->checkRolesRight('rol_dates') == 1 && $gPreferences['enable_dates_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_dates'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/dates.png"
                  alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />';
              }
              if($user->checkRolesRight('rol_photo') == 1 && $gPreferences['enable_photo_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_photo'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/photo.png"
                  alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />';
              }
              if($user->checkRolesRight('rol_download') == 1 && $gPreferences['enable_download_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_download'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/download.png"
                  alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />';
              }
              if($user->checkRolesRight('rol_guestbook') == 1 && $gPreferences['enable_guestbook_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_guestbook'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/guestbook.png"
                  alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />';
              }
              if($user->checkRolesRight('rol_guestbook_comments') == 1 && $gPreferences['enable_guestbook_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_guestbook_comments'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/comments.png"
                  alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />';
              }
              if($user->checkRolesRight('rol_weblinks') == 1 && $gPreferences['enable_weblinks_module'] > 0)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_weblinks'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/weblinks.png"
                  alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />';
              }
              if($user->checkRolesRight('rol_all_lists_view') == 1)
              {
                  echo '<img onmouseover="profileJS.showInfo(\''.substr($berechtigungs_Herkunft['rol_all_lists_view'],2).'\')" class="iconInformation" src="'.THEME_PATH.'/icons/lists.png"
                  alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" />';
              }
              echo '</div><div><p id="anzeige">'.$gL10n->get('SYS_SET_BY').':</p></div>
              </div>';

            // *******************************************************************************
            // Rollen-Block
            // *******************************************************************************

            // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
            $count_show_roles = 0;
            $result_role = getRolesFromDatabase($gDb,$user->getValue('usr_id'),$gCurrentOrganization);
            $count_role  = $gDb->num_rows($result_role);

            //Ausgabe
            echo '<div class="groupBox" id="profile_roles_box">
                <div class="groupBoxHeadline">
                    <div style="float: left;">'.$gL10n->get('ROL_ROLE_MEMBERSHIPS').'&nbsp;</div>';
                        // Moderatoren & Gruppenleiter duerfen neue Rollen zuordnen
                        if(($gCurrentUser->assignRoles() || isGroupLeader($gCurrentUser->getValue('usr_id')))
                        && $user->getValue('usr_reg_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
                        {
                            echo '
                            <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
                            <script type="text/javascript">
                                    var calPopup = new CalendarPopup("calendardiv");
                                    calPopup.setCssPrefix("calendar");
                            </script>
                            <div style="text-align: right;">
                                <a rel="colorboxRoles" href="'.$g_root_path.'/adm_program/modules/profile/roles.php?usr_id='.$user->getValue('usr_id').'&amp;inline=1" title="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'">
                                    <img src="'.THEME_PATH.'/icons/edit.png" title="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'" alt="'.$gL10n->get('ROL_ROLE_MEMBERSHIPS_CHANGE').'" />
                                </a>
                            </div>';
                        }
                echo '</div>
                    <div id="profile_roles_box_body" class="groupBoxBody">
                        '.getRoleMemberships($gDb,$gCurrentUser,$user,$result_role,$count_role,false,$gL10n).'
                    </div>
                </div>';
        }

        if($gPreferences['profile_show_former_roles'] == 1)
        {
            // *******************************************************************************
            // Ehemalige Rollen Block
            // *******************************************************************************

            // Alle Rollen auflisten, die dem Mitglied zugeordnet waren

            $count_show_roles = 0;
            $result_role = getFormerRolesFromDatabase($gDb,$user->getValue('usr_id'),$gCurrentOrganization);
            $count_role  = $gDb->num_rows($result_role);
            $visible     = "";

            if($count_role == 0)
            {
                $visible = ' style="display: none;" ';
            }
            else
            {
                echo '<script type="text/javascript">profileJS.formerRoleCount="'.$count_role.'";</script>';	
            }
            echo '<div class="groupBox" id="profile_former_roles_box" '.$visible.'>
                  <div class="groupBoxHeadline">'.$gL10n->get('PRO_FORMER_ROLE_MEMBERSHIP').'&nbsp;</div>
                    <div id="profile_former_roles_box_body" class="groupBoxBody">
                    '.getFormerRoleMemberships($gDb,$gCurrentUser,$user,$result_role,$count_role,false,$gL10n).'
                    </div>
                  </div>';

            
        }

        if($gPreferences['profile_show_extern_roles'] == 1
        && (  $gCurrentOrganization->getValue('org_org_id_parent') > 0
           || $gCurrentOrganization->hasChildOrganizations() ))
        {
            // *******************************************************************************
            // Rollen-Block anderer Organisationen
            // *******************************************************************************

            // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
            $sql = 'SELECT *
                      FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_ORGANIZATIONS. '
                     WHERE mem_rol_id = rol_id
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end   >= \''.DATE_NOW.'\'
                       AND mem_usr_id = '.$user->getValue('usr_id').'
                       AND rol_valid  = 1
                       AND rol_this_list_view = 2
                       AND rol_cat_id = cat_id
                       AND cat_org_id = org_id
                       AND org_id    <> '. $gCurrentOrganization->getValue('org_id'). '
                     ORDER BY org_shortname, cat_sequence, rol_name';
            $result_role = $gDb->query($sql);

            if($gDb->num_rows($result_role) > 0)
            {
                echo '<div class="groupBox" id="profile_roles_box_other_orga">
                    <div class="groupBoxHeadline">'.$gL10n->get('PRO_ROLE_MEMBERSHIP_OTHER_ORG').'&nbsp;</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">';
							$role = new TableRoles($gDb);
							
                            while($row = $gDb->fetch_array($result_role))
                            {
								$role->clear();
								$role->setArray($row);
		
                                $startDate = new DateTimeExtended($row['mem_begin'], 'Y-m-d', 'date');
                                // jede einzelne Rolle anzeigen
                                echo '
                                <li>
                                    <dl>
                                        <dt>
                                            '. $row['org_shortname']. ' - '.
                                                $role->getValue('cat_name'). ' - '. $role->getValue('rol_name');
                                                if($row['mem_leader'] == 1)
                                                {
                                                    echo ' - '.$gL10n->get('SYS_LEADER');
                                                }
                                            echo '&nbsp;
                                        </dt>
                                        <dd>'.$gL10n->get('SYS_SINCE',$startDate->format($gPreferences['system_date'])).'</dd>
                                    </dl>
                                </li>';
                            }
                        echo '</ul>
                    </div>
                </div>';
            }
        }

        // Infos der Benutzer, die diesen DS erstellt und geaendert haben
        echo '<div class="editInformation">';
            $user_create = new User($gDb, $gProfileFields, $user->getValue('usr_usr_id_create'));
            echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $user->getValue('usr_timestamp_create'));

            if($user->getValue('usr_usr_id_change') > 0)
            {
                $user_change = new User($gDb, $gProfileFields, $user->getValue('usr_usr_id_change'));
                echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $user->getValue('usr_timestamp_change'));
            }
        echo '</div>    
    </div>
</div>';

if($user->getValue('usr_id') != $gCurrentUser->getValue('usr_id'))
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img
                src="'.THEME_PATH.'/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>