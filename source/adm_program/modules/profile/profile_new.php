<?php
/******************************************************************************
 * Create or edit a user profile
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * user_id    : ID of the user who should be edited
 * new_user   : 0 - Edit user of the user id
 *              1 - Create a new user
 *              2 - Create a registration
 *              3 - assign/accept a registration
 * lastname   : (Optional) Lastname could be set and will than be preassigned for new users
 * firstname  : (Optional) First name could be set and will than be preassigned for new users
 * remove_url : 1 - Removes the last url from navigation cache
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getUserId    = admFuncVariableIsValid($_GET, 'user_id', 'numeric', 0);
$getNewUser   = admFuncVariableIsValid($_GET, 'new_user', 'numeric', 0);
$getLastname  = admFuncVariableIsValid($_GET, 'lastname', 'string', '');
$getFirstname = admFuncVariableIsValid($_GET, 'firstname', 'string', '');
$getRemoveUrl = admFuncVariableIsValid($_GET, 'remove_url', 'boolean', 0);

$registrationOrgId = $gCurrentOrganization->getValue('org_id');

// set headline of the script
if($getNewUser == 1)
{
    $headline = $gL10n->get('PRO_ADD_USER');
}
elseif($getNewUser == 2)
{
    $headline = $gL10n->get('SYS_REGISTRATION');
}
elseif($getUserId == $gCurrentUser->getValue('usr_id'))
{
    $headline = $gL10n->get('PRO_EDIT_MY_PROFILE');
}
else
{
    $headline = $gL10n->get('PRO_EDIT_PROFILE');
}

// if current user has no login then only show registration dialog
if($gValidLogin == false)
{
    $getNewUser = 2;
}

// if new_user isn't set and no user id is set then show dialog to create a user
if($getUserId == 0 && $getNewUser == 0)
{
	$getNewUser = 1;
}

if($getRemoveUrl == 1)
{
    $gNavigation->deleteLastUrl();
}

// User-ID nur uebernehmen, wenn ein vorhandener Benutzer auch bearbeitet wird
if($getUserId > 0 && $getNewUser != 0 && $getNewUser != 3)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// read user data
$user = new User($gDb, $gProfileFields, $getUserId);

// pruefen, ob Modul aufgerufen werden darf
switch($getNewUser)
{
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if($gCurrentUser->editProfile($user) == false)
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

$gNavigation->addUrl(CURRENT_URL, $headline);

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
    if(isset($_SESSION['profile_request']['reg_org_id']))
    {
        $registrationOrgId = $_SESSION['profile_request']['reg_org_id'];
    }
    
    unset($_SESSION['profile_request']);
}

// create html page object
$page = new HtmlPage();
$page->addJavascriptFile($g_root_path.'/adm_program/system/js/date-functions.js');
$page->addJavascriptFile($g_root_path.'/adm_program/system/js/form.js');
$page->addJavascriptFile($g_root_path.'/adm_program/modules/profile/profile.js');
$page->addCssFile(THEME_PATH.'/css/calendar.css');

$page->addJavascript('
    var profileJS = new profileJSClass();
    profileJS.init();', true);

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// show headline of module
$page->addHeadline($headline);

// create html form
$form = new HtmlForm('edit_profile_form', $g_root_path.'/adm_program/modules/profile/profile_save.php?user_id='.$getUserId.'&amp;new_user='.$getNewUser, $page);

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
    && ($getUserId == $gCurrentUser->getValue('usr_id') || $gCurrentUser->editProfile($user)))
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
            // div-Container admGroupBoxBody und admGroupBox schliessen
            $form->closeGroupBox();
        }
        $category = $field->getValue('cat_name');

        $form->addHtml('<a name="cat-'. $field->getValue('cat_id'). '"></a>');
        $form->openGroupBox('gb_category_name', $field->getValue('cat_name'));
                
        if($field->getValue('cat_name_intern') == 'MASTER_DATA')
        {
            if($getUserId > 0 || $getNewUser == 2)
            {
                // add username to form
                $fieldProperty = FIELD_DEFAULT;
                $fieldHelpId   = null;
                
                if($gCurrentUser->isWebmaster() == false && $getNewUser == 0)
                {
                    $fieldProperty = FIELD_DISABLED;
                }
                elseif($getNewUser > 0)
                {
                    $fieldProperty = FIELD_MANDATORY;
                    $fieldHelpId   = 'PRO_USERNAME_DESCRIPTION';
                }
            
                $form->addTextInput('usr_login_name', $gL10n->get('SYS_USERNAME'), $user->getValue('usr_login_name'), 35, $fieldProperty, 'TEXT', $fieldHelpId, 'admTextInputSmall');

                if($getNewUser == 2)
                {
                    // at registration add password and password confirm to form
                    $form->addPasswordInput('usr_password', $gL10n->get('SYS_PASSWORD'), FIELD_MANDATORY, 'PRO_PASSWORD_DESCRIPTION', 'admTextInputSmall');
                    $form->addPasswordInput('password_confirm', $gL10n->get('SYS_CONFIRM_PASSWORD'), FIELD_MANDATORY, null, 'admTextInputSmall');

                    // show selectbox with all organizations of database
                    if($gPreferences['system_organization_select'] == 1)
                    {
                        $sql = 'SELECT org_id, org_longname FROM '.TBL_ORGANIZATIONS.' ORDER BY org_longname ASC, org_shortname ASC';
                        $form->addSelectBoxFromSql('reg_org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql, FIELD_MANDATORY, $registrationOrgId, true);
                    }
                }
                else
                {
                    // only show link if user is member of this organization.
                    // Password of own user could be changed.
                    // Webmasters are allowed to change password if no login was configured or no email is set to send a generated password.
                    if( isMember($user->getValue('usr_id')) 
                    && (  $gCurrentUser->getValue('usr_id') == $user->getValue('usr_id') 
                       || (   $gCurrentUser->isWebmaster() 
                          && (strlen($user->getValue('usr_login_name')) == 0 || strlen($user->getValue('EMAIL')) == 0))))
                    {
                        $form->addCustomContent('password_link', $gL10n->get('SYS_PASSWORD'), '            
                            <span class="admIconTextLink" id="password_link">
                                <a href="password.php?usr_id='.$getUserId.'"><img 
                                	src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" /></a>
                                <a href="password.php?usr_id='.$getUserId.'">'.$gL10n->get('SYS_CHANGE_PASSWORD').'</a>
                            </span>');
                    }
                }
                $form->addLine();
            }
        }
    }

    // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
    if($show_field == true)
    {
        // add profile fields to form
        $fieldProperty = FIELD_DEFAULT;
        $helpId        = null;
        
        if($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_disabled') == 1 && $gCurrentUser->editUsers() == false && $getNewUser == 0)
        {
        	// disable field if this is configured in profile field configuration
            $fieldProperty = FIELD_DISABLED;
        }
        elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_mandatory') == 1)
        {
            // set mandatory field
            $fieldProperty = FIELD_MANDATORY;
        }
        
        if(strlen($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_description')) > 0)
        {
            $helpId = array('user_field_description', $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name_intern'));
        }
    
        // code for different field types
        
        if($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name_intern') == 'COUNTRY')
        {
            // get default country
            $preassignedCountry = null;
            
    		if($user->getValue('usr_id') == 0 && strlen($gPreferences['default_country']) > 0)
    		{
        		$preassignedCountry = $gPreferences['default_country'];
    		}
    		elseif($user->getValue('usr_id') > 0 && strlen($user->getValue($field->getValue('usf_name_intern'))) > 0)
    		{
        		$preassignedCountry = $user->getValue($field->getValue('usf_name_intern'));
    		}
        
    		// create selectbox with all countries
    		$form->addSelectBox('usf-'. $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_id'), $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name'), 
    		    $gL10n->getCountries(), $fieldProperty, $preassignedCountry, true, $helpId);
        }
        elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'CHECKBOX')
        {
            $form->addCheckbox('usf-'. $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_id'), $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name'),
                $user->getValue($field->getValue('usf_name_intern')), $fieldProperty, $helpId);
        }
        elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'DROPDOWN')
        {
    		$arrListValues   = $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_value_list');
    		
            if($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_mandatory') == 1)
            {
                $setPleaseChoose = true;
            }
            else
            {
        		$setPleaseChoose = false;
                $arrListValues = array('' => '')+$arrListValues;
            }
    		
    		$form->addSelectBox('usf-'. $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_id'), $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name'), 
    		    $arrListValues, $fieldProperty, $user->getValue($field->getValue('usf_name_intern'), 'database'), $setPleaseChoose, $helpId);
    	}
        elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'RADIO_BUTTON')
        {
    		$arrListValues        = $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_value_list');
    		$showDummyRadioButton = false;
    		
    		if($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_mandatory') == 0)
    		{
        		$showDummyRadioButton = true;
    		}
    		
    		$form->addRadioButton('usf-'.$gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_id'), $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name'),
    		    $arrListValues, $fieldProperty, $user->getValue($field->getValue('usf_name_intern'), 'database'), $showDummyRadioButton, $helpId);
        }
        elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'TEXT_BIG')
        {
            $form->addMultilineTextInput('usf-'. $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_id'), $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name'), 
                $user->getValue($field->getValue('usf_name_intern')), 2, 255, $fieldProperty, $helpId);
        }
        else
        {
            $fieldType = 'TEXT';
            
            if($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'DATE')
            {
                $fieldType = 'DATE';
                $maxlength = '10';
                
                if($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name_intern') == 'BIRTHDAY')
                {
                    $fieldType = 'BIRTHDAY';
                }
            }
            elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'EMAIL' || $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_type') == 'URL')
            {
                $maxlength = '255';
            }
            elseif($gProfileFields->getProperty($field->getValue('usf_name_intern'), 'cat_name_intern') == 'SOCIAL_NETWORKS')
            {
                $maxlength = '255';
            }
            else
            {
                $maxlength = '50';
            }
    
            $form->addTextInput('usf-'. $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_id'), $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_name'),
                $user->getValue($field->getValue('usf_name_intern')), $maxlength, $fieldProperty, $fieldType, $helpId, null, $gProfileFields->getProperty($field->getValue('usf_name_intern'), 'usf_icon', 'database'));
        }
    }
}

// div-Container admGroupBoxBody und admGroupBox schliessen
$form->closeGroupBox();

// if captchas are enabled then visitors of the website must resolve this
if($getNewUser == 2 && $gPreferences['enable_registration_captcha'] == 1)
{
    $form->openGroupBox('gb_confirmation_of_input', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha', $gPreferences['captcha_type']);
    $form->closeGroupBox();
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
    // show informations about user who creates the recordset and changed it
    $form->addHtml(admFuncShowCreateChangeInfoById($user->getValue('usr_usr_id_create'), $user->getValue('usr_timestamp_create'), $user->getValue('usr_usr_id_change'), $user->getValue('usr_timestamp_change')));
}

$form->addSubmitButton('btn_save', $btn_text, THEME_PATH.'/icons/'.$btn_image);

$page->addHtml($form->show(false));
$page->show();

?>