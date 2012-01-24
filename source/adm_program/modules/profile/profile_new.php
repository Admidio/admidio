<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * user_id  :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Dialog um neue Benutzer hinzuzufuegen.
 *            2 - Dialog um Registrierung entgegenzunehmen
 *            3 - Registrierung zuordnen/akzeptieren
 * lastname   : Der Nachname kann uebergeben und bei neuen Benutzern vorbelegt werden
 * firstname  : Der Vorname kann uebergeben und bei neuen Benutzern vorbelegt werden
 * remove_usr : 1 - Entfernt die letzte Url aus dem Navigations-Cache
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getUserId    = admFuncVariableIsValid($_GET, 'user_id', 'numeric', 0);
$getNewUser   = admFuncVariableIsValid($_GET, 'new_user', 'numeric', 0);
$getLastname  = admFuncVariableIsValid($_GET, 'lastname', 'string', '');
$getFirstname = admFuncVariableIsValid($_GET, 'firstname', 'string', '');
$getRemoveUrl = admFuncVariableIsValid($_GET, 'remove_url', 'boolean', 0);

// im ausgeloggten Zustand koennen nur Registrierungen angelegt werden
if($gValidLogin == false)
{
    $getNewUser = 2;
}

if($getRemoveUrl == 1)
{
    $_SESSION['navigation']->deleteLastUrl();
}

// Falls das Catpcha in den Orgaeinstellungen aktiviert wurde und die Ausgabe als
// Rechenaufgabe eingestellt wurde, muss die Klasse f�r neue Registrierungen geladen werden
if ($getNewUser == 2 && $gPreferences['enable_registration_captcha'] == 1 && $gPreferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

// User-ID nur uebernehmen, wenn ein vorhandener Benutzer auch bearbeitet wird
if($getUserId > 0 && $getNewUser != 0 && $getNewUser != 3)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// User auslesen
$user = new User($gDb, $gProfileFields, $getUserId);

// pruefen, ob Modul aufgerufen werden darf
switch($getNewUser)
{
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if($gCurrentUser->editProfile($getUserId) == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        break;

    case 1:
        // prueft, ob der User die notwendigen Rechte hat, neue User anzulegen
        if($gCurrentUser->editUsers() == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        
        // wurde Nachname und Vorname uebergeben, dann diese bereits vorbelegen
        $user->setValue('LAST_NAME', $getLastname);
        $user->setValue('FIRST_NAME', $getFirstname);
        break;

    case 2:
    case 3:
        // Registrierung deaktiviert, also auch diesen Modus sperren
        if($gPreferences['registration_mode'] == 0)
        {
            $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        }
        break;
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Formular wurde ueber "Zurueck"-Funktion aufgerufen, also alle Felder mit den vorherigen Werten fuellen
if(isset($_SESSION['profile_request']))
{
    $user->noValueCheck();

    foreach($gProfileFields->mProfileFields as $field)
    {
        $field_name = 'usf-'. $field->getValue('usf_id');
        if(isset($_SESSION['profile_request'][$field_name]))
        {
            $user->setValue($field->getValue('usf_name_intern'), $_SESSION['profile_request'][$field_name]);
        }
    }

    if(isset($_SESSION['profile_request']['usr_login_name']))
    {
		$user->setArray(array('usr_login_name' => $_SESSION['profile_request']['usr_login_name']));
    }
    
    unset($_SESSION['profile_request']);
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($fieldNameIntern, $user, $getNewUser)
{
    global $gPreferences, $g_root_path, $gCurrentUser, $gL10n, $gProfileFields;
    $value    = '';
    
    // Felder sperren, falls dies so eingestellt wurde
    $disabled = '';
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_disabled') == 1 && $gCurrentUser->editUsers() == false && $getNewUser == 0)
    {
		$disabled = ' disabled="disabled" ';
    }

    // Code fuer die einzelnen Felder zusammensetzen    
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'COUNTRY')
    {
        //Laenderliste oeffnen
        $value = '
		<select size="1" id="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" name="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" '.$disabled.'>
			<option value="" ';
                if(strlen($gPreferences['default_country']) == 0
                && strlen($user->getValue($fieldNameIntern)) == 0)
                {
                    $value = $value. ' selected="selected" ';
                }
			$value = $value. '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
            if(strlen($gPreferences['default_country']) > 0)
            {
                $value = $value. ' <option value="">--------------------------------</option>
				<option value="'. $gPreferences['default_country']. '">'. $gL10n->getCountryByCode($gPreferences['default_country']). '</option>
                <option value="">--------------------------------</option>';
            }
			foreach($gL10n->getCountries() as $key => $country_name)
			{
				$value = $value. '<option value="'.$key.'" ';
				if($getNewUser > 0 && $key == $gPreferences['default_country'])
				{
					$value = $value. ' selected="selected" ';
				}
				if(!$getNewUser > 0 && $country_name == $user->getValue($fieldNameIntern))
				{
					$value = $value. ' selected="selected" ';
				}
				$value = $value. '>'.$country_name.'</option>';
			}
		$value = $value. '</select>';
    }
    elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'CHECKBOX')
    {
        $mode = '';
        if($user->getValue($fieldNameIntern) == 1)
        {
            $mode = ' checked="checked" ';
        }
        $value = '<input type="checkbox" id="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" name="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" '.$mode.' '.$disabled.' value="1" />';
    }
    elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'DROPDOWN')
    {
		$arrListValues = $gProfileFields->getProperty($fieldNameIntern, 'usf_value_list');
		$position = 1;
		$text     = '';
		
		$value = '<select size="1" name="usf-'.$gProfileFields->getProperty($fieldNameIntern, 'usf_id').'" id="usf-'.$gProfileFields->getProperty($fieldNameIntern, 'usf_id').'" '.$disabled.'>
			<option value="" ';
                if(strlen($user->getValue($fieldNameIntern)) == 0)
                {
                    $value .= ' selected="selected" ';
                }
                if($gProfileFields->getProperty($fieldNameIntern, 'usf_mandatory') == 1)
                {
                    $text  .= '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -';
                }
			$value .= '>'.$text.'</option>';

			// fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
			foreach($arrListValues as $key => $valueList)
			{
				$value .= '<option value="'.$position.'" '; 
				if($user->getValue($fieldNameIntern) == $valueList) 
				{
					$value .= ' selected="selected"';
				}
				$value .= '>'.$valueList.'</option>';
				$position++;
			}
		$value .= '</select>';
	}
    elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'RADIO_BUTTON')
    {
		$arrListValues = $gProfileFields->getProperty($fieldNameIntern, 'usf_value_list');
		$position = 1;
		$value = '';

        if($gProfileFields->getProperty($fieldNameIntern, 'usf_mandatory') == 0)
        {
	        $htmlChecked = '';
	        if(strlen($user->getValue($fieldNameIntern)) == 0)
	        {
	            $htmlChecked = ' checked="checked" ';
	        }
	        $value .= '<input type="radio" id="usf-'.$gProfileFields->getProperty($fieldNameIntern, 'usf_id').'-0" name="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" value="" '.$htmlChecked.' '.$disabled.' />
	            <label for="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id').'-0">---</label>&nbsp;&nbsp;';
        }

		// fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
		foreach($arrListValues as $key => $valueList)
		{
	        $htmlChecked = '';
	        if($user->getValue($fieldNameIntern) == $valueList)
	        {
	            $htmlChecked = ' checked="checked" ';
	        }
	        
	        $value .= '<input type="radio" id="usf-'.$gProfileFields->getProperty($fieldNameIntern, 'usf_id').'-'.$position.'" name="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" value="'.$position.'" '.$htmlChecked.' '.$disabled.' />
	            <label for="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id').'-'.$position.'">'.$valueList.'</label>&nbsp;&nbsp;';
			$position++;
		}
		
    }
    elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'TEXT_BIG')
    {
        $usfId = 'usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id');
        $value = '<script type="text/javascript">
                    $(document).ready(function(){
                        $(\'#'.$usfId.'\').NobleCount(\'#'.$usfId.'_counter\',{
                            max_chars: 255,
                            on_negative: \'systeminfoBad\',
                            block_negative: true
                        });
                    });
                 </script>         
        <textarea  name="'.$usfId.'" id="'.$usfId.'" '.$disabled.' style="width: 300px;" rows="2" cols="40">'. $user->getValue($fieldNameIntern).'</textarea>
        (<span id="'.$usfId.'_counter" class="">255</span>)';
    }
    else
    {
        if($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'DATE')
        {
            $width = '80px';
            $maxlength = '10';
        }
        elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'EMAIL' || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'URL')
        {
            $width     = '300px';
            $maxlength = '255';
        }
        elseif($gProfileFields->getProperty($fieldNameIntern, 'cat_name_intern') == 'SOCIAL_NETWORKS')
        {
            $width = '200px';
            $maxlength = '255';
        }
        else
        {
            $width = '200px';
            $maxlength = '50';
        }
        if($gProfileFields->getProperty($fieldNameIntern, 'usf_type') == 'DATE')
        {
            if($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') == 'BIRTHDAY')
            {
                $value = '<script type="text/javascript">
                            var calBirthday = new CalendarPopup("calendardiv");
                            calBirthday.setCssPrefix("calendar");
                            calBirthday.showNavigationDropdowns();
                            calBirthday.setYearSelectStartOffset(90);
                            calBirthday.setYearSelectEndOffset(0);
                        </script>';
                $calObject = 'calBirthday';
            }
            else
            {
                $value = '<script type="text/javascript">
                            var calDate = new CalendarPopup("calendardiv");
                            calDate.setCssPrefix("calendar");
                            calDate.showNavigationDropdowns();
                            calDate.setYearSelectStartOffset(50);
                            calDate.setYearSelectEndOffset(10);
                        </script>';
                $calObject = 'calDate';
            }
            $value .= '
                    <input type="text" id="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" name="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" style="width: '.$width.';" 
                        maxlength="'.$maxlength.'" '.$disabled.' value="'. $user->getValue($fieldNameIntern, $gPreferences['system_date']). '" '.$disabled.' />
                    <a class="iconLink" id="anchor_'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" href="javascript:'.$calObject.'.select(document.getElementById(\'usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '\'),\'anchor_'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '\',\''.$gPreferences['system_date'].'\');"><img 
                    	src="'. THEME_PATH. '/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
                    <span id="calendardiv" style="position: absolute; visibility: hidden;"></span>';
        }
        else
        {
            $value = '<input type="text" id="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" name="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '" style="width: '.$width.';" maxlength="'.$maxlength.'" '.$disabled.' value="'. $user->getValue($fieldNameIntern). '" '.$disabled.' />';
        }
    }
    
    // display icon of field
    $icon = '';
    if(strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_icon')) > 0)
    {
        $icon = $gProfileFields->getProperty($fieldNameIntern, 'usf_icon').'&nbsp;';
    }
        
    // Kennzeichen fuer Pflichtfeld setzen
    $mandatory = '';
    if($gProfileFields->getProperty($fieldNameIntern, 'usf_mandatory') == 1)
    {
        $mandatory = '<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
    }
    
    // Fragezeichen mit Feldbeschreibung anzeigen, wenn diese hinterlegt ist
    $description = '';
    if(strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_description')) > 0)
    {
        $description = '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern'). '&amp;inline=true"><img 
            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern'). '\',this)" onmouseout="ajax_hideTooltip()"
            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_HELP').'" title="" /></a>';
    }
    
    // nun den Html-Code fuer das Feld zusammensetzen
    $html = '<li>
                <dl>
                    <dt><label for="usf-'. $gProfileFields->getProperty($fieldNameIntern, 'usf_id'). '">'. $icon. $gProfileFields->getProperty($fieldNameIntern, 'usf_name'). ':</label></dt>
                    <dd>'. $value. $mandatory. $description. '</dd>
                </dl>
            </li>';
             
    return $html;
}

// Html-Kopf ausgeben
if($getNewUser == 1)
{
    $gLayout['title'] = $gL10n->get('PRO_ADD_USER');
}
elseif($getNewUser == 2)
{
    $gLayout['title'] = $gL10n->get('SYS_REGISTRATION');
}
elseif($getUserId == $gCurrentUser->getValue('usr_id'))
{
    $gLayout['title'] = $gL10n->get('PRO_EDIT_MY_PROFILE');
}
else
{
    $gLayout['title'] = $gL10n->get('PRO_EDIT_PROFILE');
}

$gLayout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.noblecount.min.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/form.js"></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/modules/profile/profile.js"></script>
    <link rel="stylesheet" href="'.THEME_PATH.'/css/calendar.css" type="text/css" />';

$gLayout['header'] .= '
        <script type="text/javascript"><!--
			var profileJS = new profileJSClass();
			$(document).ready(function() 
            {
				profileJS.init();
				';

// setzt den Focus bei Neuanlagen/Registrierung auf das erste Feld im Dialog
if($getNewUser == 1 || $getNewUser == 2)
{
    if($getNewUser == 1)
    {
    	$first_field = reset($gProfileFields->mProfileFields);
        $focusField = 'usf-'.$first_field->getValue('usf_id');
    }
    else
    {
        $focusField = 'usr_login_name';
    }
	$gLayout['header'] .= '$("#'.$focusField.'").focus();';
}
$gLayout['header'] .= '}); 
        //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<form action="'.$g_root_path.'/adm_program/modules/profile/profile_save.php?user_id='.$getUserId.'&amp;new_user='.$getNewUser.'" method="post">
<div class="formLayout" id="edit_profile_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">'; 
        // *******************************************************************************
        // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
        // *******************************************************************************

        $category = '';
        
        foreach($gProfileFields->mProfileFields as $field)
        {
            $show_field = false;
            
            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            // E-Mail ist Ausnahme und muss immer angezeigt werden
            if($getNewUser == 2 
            && $gPreferences['registration_mode'] == 1 
            && ($field->getValue('usf_mandatory') == 1 || $field->getValue('usf_name_intern') == 'EMAIL'))
            {
                $show_field = true;
            }
            elseif($getNewUser == 2
            && $gPreferences['registration_mode'] == 2)
            {
                // bei der vollstaendigen Registrierung alle Felder anzeigen
                $show_field = true;
            }
            elseif($getNewUser != 2 
            && ($getUserId == $gCurrentUser->getValue('usr_id') || $gCurrentUser->editUsers()))
            {
                // bei fremden Profilen duerfen versteckte Felder nur berechtigten Personen angezeigt werden
                // Leiter duerfen dies nicht !!!
                $show_field = true;
            }
        
            // Kategorienwechsel den Kategorienheader anzeigen
            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            if($category != $field->getValue('cat_name')
            && $show_field == true)
            {
                if(strlen($category) > 0)
                {
                    // div-Container groupBoxBody und groupBox schliessen
                    echo '</ul></div></div>';
                }
                $category = $field->getValue('cat_name');

                echo '<a name="cat-'. $field->getValue('cat_id'). '"></a>
                <div class="groupBox">
                    <div class="groupBoxHeadline">'. $field->getValue('cat_name'). '</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">';
                        
                if($field->getValue('cat_name_intern') == 'MASTER_DATA')
                {
                    // bei den Stammdaten erst einmal Benutzername und Passwort anzeigen
                    if($getUserId > 0 || $getNewUser == 2)
                    {
                        echo '<li>
                            <dl>
                                <dt><label for="usr_login_name">'.$gL10n->get('SYS_USERNAME').':</label></dt>
                                <dd>
                                    <input type="text" id="usr_login_name" name="usr_login_name" style="width: 200px;" maxlength="35" value="'. $user->getValue('usr_login_name'). '" ';
                                    if($gCurrentUser->isWebmaster() == false && $getNewUser == 0)
                                    {
                                        echo ' disabled="disabled" ';
                                    }
                                    echo ' />';
                                    if($getNewUser > 0)
                                    {
                                        echo '<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PRO_USERNAME_DESCRIPTION&amp;inline=true"><img 
                                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=PRO_USERNAME_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
                                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_HELP').'" title="" /></a>';
                                    }
                                echo '</dd>
                            </dl>
                        </li>';

                        if($getNewUser == 2)
                        {
                            echo '<li>
                                <dl>
                                    <dt><label for="usr_password">'.$gL10n->get('SYS_PASSWORD').':</label></dt>
                                    <dd>
                                        <input type="password" id="usr_password" name="usr_password" style="width: 130px;" maxlength="20" />
                                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PRO_PASSWORD_DESCRIPTION&amp;inline=true"><img 
                                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=PRO_PASSWORD_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
                                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_HELP').'" title="" /></a>
                                    </dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="password2">'.$gL10n->get('SYS_CONFIRM_PASSWORD').':</label></dt>
                                    <dd>
                                        <input type="password" id="password2" name="password2" style="width: 130px;" maxlength="20" />
                                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                                    </dd>
                                </dl>
                            </li>';
                        }
                        else
                        {
                            // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
                            if($gCurrentUser->isWebmaster() || $gCurrentUser->getValue("usr_id") == $getUserId )
                            {
                                echo '<li>
                                    <dl>
                                        <dt><label>'.$gL10n->get('SYS_PASSWORD').':</label></dt>
                                        <dd>
                                            <span class="iconTextLink">
                                                <a rel="colorboxPWContent" href="password.php?usr_id='. $getUserId. '&amp;inline=1"><img 
                                                	src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" /></a>
                                                <a rel="colorboxPWContent" href="password.php?usr_id='. $getUserId. '&amp;inline=1">'.$gL10n->get('SYS_CHANGE_PASSWORD').'</a>
                                            </span>
                                        </dd>
                                    </dl>
                                </li>';
                            }
                        }
                        echo '<li><hr /></li>';
                    }
                }
            }

            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            if($show_field == true)
            {
                // Html des Feldes ausgeben
                echo getFieldCode($field->getValue('usf_name_intern'), $user, $getNewUser);
            }
        }
        
        // div-Container groupBoxBody und groupBox schliessen
        echo '</ul></div></div>';

        // User, die sich registrieren wollen, bekommen jetzt noch das Captcha praesentiert,
        // falls es in den Orgaeinstellungen aktiviert wurde...
        if ($getNewUser == 2 && $gPreferences['enable_registration_captcha'] == 1)
        {
            echo '
            <ul class="formFieldList">
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
						<dd>
						';
			if($gPreferences['captcha_type']=='pic')
			{
				echo '<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$gL10n->get('SYS_CAPTCHA').'" />';
				$captcha_label = $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE');
				$captcha_description = 'SYS_CAPTCHA_DESCRIPTION';
			}
			else if($gPreferences['captcha_type']=='calc')
			{
				$captcha = new Captcha();
				$captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'),$gL10n->get('SYS_CAPTCHA_CALC_PART2'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$gL10n->get('SYS_CAPTCHA_CALC_PART4'));
				$captcha_label = $gL10n->get('SYS_CAPTCHA_CALC');
				$captcha_description = 'SYS_CAPTCHA_CALC_DESCRIPTION';
			}
			echo '
                    </dd>
					</dl>
                </li>
                <li>
                    <dl>
                        <dt>'.$captcha_label.':</dt>
                        <dd>
                            <input type="text" id="captcha" name="captcha" style="width: 200px;" maxlength="8" value="" />
                            <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id='.$captcha_description.'&amp;inline=true"><img 
					            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id='.$captcha_description.'\',this)" onmouseout="ajax_hideTooltip()"
					            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_HELP').'" title="" /></a>
                        </dd>
                    </dl>
                </li>
            </ul>
            <hr />';
        }

        // Bild und Text fuer den Speichern-Button
        if($getNewUser == 2)
        {
            // Registrierung
            $btn_image = 'email.png';
            $btn_text  = $gL10n->get('SYS_SEND');
        }
        else
        {
            $btn_image = 'disk.png';
            $btn_text  = $gL10n->get('SYS_SAVE');
        }

        if($getNewUser == 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $user->getValue('usr_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $user->getValue('usr_timestamp_create'));

                if($user->getValue('usr_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $user->getValue('usr_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $user->getValue('usr_timestamp_change'));
                }
            echo '</div>';
        }

        echo '
        <div class="formSubmit">
            <button id="btnSave" type="submit"><img 
                src="'. THEME_PATH. '/icons/'. $btn_image. '" alt="'. $btn_text. '" />
                &nbsp;'. $btn_text. '</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'. $g_root_path. '/adm_program/system/back.php"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'. $g_root_path. '/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>