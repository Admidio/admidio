<?php
/**
 ***********************************************************************************************
 * Create or edit a user profile
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_id    : ID of the user who should be edited
 * new_user   : 0 - Edit user of the user id
 *              1 - Create a new user
 *              2 - Create a registration
 *              3 - assign/accept a registration
 * lastname   : (Optional) Lastname could be set and will than be preassigned for new users
 * firstname  : (Optional) First name could be set and will than be preassigned for new users
 * copy       : true - The user of the user_id will be copied and the base for this new user
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getUserId    = admFuncVariableIsValid($_GET, 'user_id',  'int');
$getNewUser   = admFuncVariableIsValid($_GET, 'new_user', 'int');
$getLastname  = stripslashes(admFuncVariableIsValid($_GET, 'lastname',  'string'));
$getFirstname = stripslashes(admFuncVariableIsValid($_GET, 'firstname', 'string'));
$getCopy      = admFuncVariableIsValid($_GET, 'copy',     'bool');

$registrationOrgId = $gCurrentOrganization->getValue('org_id');

// if current user has no login then only show registration dialog
if(!$gValidLogin)
{
    $getNewUser = 2;
}

// if new_user isn't set and no user id is set then show dialog to create a user
if($getUserId === 0 && $getNewUser === 0)
{
    $getNewUser = 1;
}

// User-ID nur uebernehmen, wenn ein vorhandener Benutzer auch bearbeitet wird
if($getUserId > 0 && $getNewUser !== 0 && $getNewUser !== 3)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// read user data
$user = new User($gDb, $gProfileFields, $getUserId);

// set headline of the script
if($getCopy)
{
    // if we want to copy the user than set id = 0
    $user->setValue('usr_id', 0);
    $getUserId = 0;
    $getNewUser = 1;
    $headline = $gL10n->get('SYS_COPY_VAR', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')));
}
elseif($getNewUser === 1)
{
    $headline = $gL10n->get('PRO_ADD_USER');
}
elseif($getNewUser === 2)
{
    $headline = $gL10n->get('SYS_REGISTRATION');
}
elseif($getUserId === (int) $gCurrentUser->getValue('usr_id'))
{
    $headline = $gL10n->get('PRO_EDIT_MY_PROFILE');
}
else
{
    $headline = $gL10n->get('PRO_EDIT_PROFILE');
}

// pruefen, ob Modul aufgerufen werden darf
switch($getNewUser)
{
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if(!$gCurrentUser->hasRightEditProfile($user))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        break;

    case 1:
        // prueft, ob der User die notwendigen Rechte hat, neue User anzulegen
        if(!$gCurrentUser->editUsers())
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        // wurde Nachname und Vorname uebergeben, dann diese bereits vorbelegen
        $user->setValue('LAST_NAME', $getLastname);
        $user->setValue('FIRST_NAME', $getFirstname);
        break;

    case 2:
    case 3:
        // Registrierung deaktiviert, also auch diesen Modus sperren
        if(!$gSettingsManager->getBool('registration_enable_module'))
        {
            $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
            // => EXIT
        }
        break;
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Formular wurde ueber "Zurueck"-Funktion aufgerufen, also alle Felder mit den vorherigen Werten fuellen
if(isset($_SESSION['profile_request']))
{
    $user->noValueCheck();

    foreach($gProfileFields->getProfileFields() as $field)
    {
        $fieldName = 'usf-'. $field->getValue('usf_id');
        if(isset($_SESSION['profile_request'][$fieldName]))
        {
            $user->setProfileFieldsValue($field->getValue('usf_name_intern'), stripslashes($_SESSION['profile_request'][$fieldName]));
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
$page = new HtmlPage($headline);
$page->enableModal();
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/zxcvbn/dist/zxcvbn.js');

// add back link to module menu
$profileEditMenu = $page->getMenu();
$profileEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// create html form
$form = new HtmlForm('edit_profile_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_save.php', array('user_id' => $getUserId, 'new_user' => $getNewUser)), $page);

// *******************************************************************************
// Loop over all categories and profile fields
// *******************************************************************************

$category = '';

foreach($gProfileFields->getProfileFields() as $field)
{
    $showField = false;

    // at registration check if the field is enabled for registration
    if($getNewUser === 2 && $field->getValue('usf_registration') == 1)
    {
        $showField = true;
    }
    // only allow to edit viewable fields, check for edit profile was done before
    elseif($getNewUser !== 2 && $gCurrentUser->allowedViewProfileField($user, $field->getValue('usf_name_intern')))
    {
        $showField = true;
    }

    // Kategorienwechsel den Kategorienheader anzeigen
    // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
    if($category !== $field->getValue('cat_name') && $showField)
    {
        if($category !== '')
        {
            // div-Container admGroupBoxBody und admGroupBox schliessen
            $form->closeGroupBox();
        }
        $category = $field->getValue('cat_name');

        $form->addHtml('<a id="cat-'. $field->getValue('cat_id'). '"></a>');
        $form->openGroupBox('gb_category_'.$field->getValue('cat_name_intern'), $field->getValue('cat_name'));

        if($field->getValue('cat_name_intern') === 'MASTER_DATA')
        {
            if($getUserId > 0 || $getNewUser === 2)
            {
                // add username to form
                $fieldProperty = HtmlForm::FIELD_DEFAULT;
                $fieldHelpId   = 'PRO_USERNAME_DESCRIPTION';

                if(!$gCurrentUser->isAdministrator() && $getNewUser === 0)
                {
                    $fieldProperty = HtmlForm::FIELD_DISABLED;
                    $fieldHelpId   = '';
                }
                elseif($getNewUser > 0)
                {
                    $fieldProperty = HtmlForm::FIELD_REQUIRED;
                }

                $form->addInput(
                    'usr_login_name', $gL10n->get('SYS_USERNAME'), $user->getValue('usr_login_name'),
                    array('maxLength' => 35, 'property' => $fieldProperty, 'helpTextIdLabel' => $fieldHelpId, 'class' => 'form-control-small')
                );

                if($getNewUser === 2)
                {
                    // at registration add password and password confirm to form
                    $form->addInput(
                        'usr_password', $gL10n->get('SYS_PASSWORD'), '',
                        array(
                            'type'             => 'password',
                            'property'         => HtmlForm::FIELD_REQUIRED,
                            'minLength'        => PASSWORD_MIN_LENGTH,
                            'passwordStrength' => true,
                            'helpTextIdLabel'  => 'PRO_PASSWORD_DESCRIPTION',
                            'class'            => 'form-control-small'
                        )
                    );
                    $form->addInput(
                        'password_confirm', $gL10n->get('SYS_CONFIRM_PASSWORD'), '',
                        array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH, 'class' => 'form-control-small')
                    );

                    // show selectbox with all organizations of database
                    if($gSettingsManager->getBool('system_organization_select'))
                    {
                        $sql = 'SELECT org_id, org_longname
                                  FROM '.TBL_ORGANIZATIONS.'
                              ORDER BY org_longname ASC, org_shortname ASC';
                        $form->addSelectBoxFromSql(
                            'reg_org_id', $gL10n->get('SYS_ORGANIZATION'), $gDb, $sql,
                            array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $registrationOrgId)
                        );
                    }
                }
                else
                {
                    // only show link if user is member of this organization.
                    // Password of own user could be changed.
                    // Administrators are allowed to change password if no login was configured or no email is set to send a generated password.
                    if(isMember($user->getValue('usr_id'))
                    && ((int) $gCurrentUser->getValue('usr_id') === (int) $user->getValue('usr_id')
                       || ($gCurrentUser->isAdministrator()
                          && (strlen($user->getValue('usr_login_name')) === 0 || strlen($user->getValue('EMAIL')) === 0))))
                    {
                        $form->addCustomContent($gL10n->get('SYS_PASSWORD'), '
                            <a id="password_link" class="btn" data-toggle="modal" data-target="#admidio_modal"
                                href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('usr_id' => $getUserId)).'"><img src="'. THEME_URL. '/icons/key.png"
                                alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" />'.$gL10n->get('SYS_CHANGE_PASSWORD').'</a>');
                    }
                }
                $form->addLine();
            }
        }
    }

    // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
    if($showField)
    {
        // add profile fields to form
        $fieldProperty = HtmlForm::FIELD_DEFAULT;
        $helpId        = '';
        $usfNameIntern = $field->getValue('usf_name_intern');

        if($gProfileFields->getProperty($usfNameIntern, 'usf_disabled') == 1
        && !$gCurrentUser->hasRightEditProfile($user, false) && $getNewUser === 0)
        {
            // disable field if this is configured in profile field configuration
            $fieldProperty = HtmlForm::FIELD_DISABLED;
        }
        elseif($gProfileFields->getProperty($usfNameIntern, 'usf_mandatory') == 1)
        {
            // set mandatory field
            $fieldProperty = HtmlForm::FIELD_REQUIRED;
        }

        if(strlen($gProfileFields->getProperty($usfNameIntern, 'usf_description')) > 0)
        {
            $helpId = array('user_field_description', $gProfileFields->getProperty($usfNameIntern, 'usf_name_intern'));
        }

        // code for different field types
        if($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'CHECKBOX')
        {
            $form->addCheckbox(
                'usf-'. $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                (bool) $user->getValue($usfNameIntern),
                array(
                    'property'        => $fieldProperty,
                    'helpTextIdLabel' => $helpId,
                    'icon'            => $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                )
            );
        }
        elseif($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'DROPDOWN' || $usfNameIntern === 'COUNTRY')
        {
            // set array with values and set default value
            if($usfNameIntern === 'COUNTRY')
            {
                $arrListValues = $gL10n->getCountries();
                $defaultValue  = null;

                if((int) $user->getValue('usr_id') === 0 && strlen($gSettingsManager->getString('default_country')) > 0)
                {
                    $defaultValue = $gSettingsManager->getString('default_country');
                }
                elseif($user->getValue('usr_id') > 0 && strlen($user->getValue($usfNameIntern)) > 0)
                {
                    $defaultValue = $user->getValue($usfNameIntern, 'database');
                }
            }
            else
            {
                $arrListValues = $gProfileFields->getProperty($usfNameIntern, 'usf_value_list');
                $defaultValue  = $user->getValue($usfNameIntern, 'database');
            }

            $form->addSelectBox(
                'usf-'. $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                $arrListValues,
                array(
                    'property'        => $fieldProperty,
                    'defaultValue'    => $defaultValue,
                    'helpTextIdLabel' => $helpId,
                    'icon'            => $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                )
            );
        }
        elseif($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'RADIO_BUTTON')
        {
            $showDummyRadioButton = false;
            if($gProfileFields->getProperty($usfNameIntern, 'usf_mandatory') == 0)
            {
                $showDummyRadioButton = true;
            }

            $form->addRadioButton(
                'usf-'.$gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                $gProfileFields->getProperty($usfNameIntern, 'usf_value_list'),
                array(
                    'property'          => $fieldProperty,
                    'defaultValue'      => $user->getValue($usfNameIntern, 'database'),
                    'showNoValueButton' => $showDummyRadioButton,
                    'helpTextIdLabel'   => $helpId,
                    'icon'              => $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                )
            );
        }
        elseif($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'TEXT_BIG')
        {
            $form->addMultilineTextInput(
                'usf-'. $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                $user->getValue($usfNameIntern),
                3,
                array(
                    'maxLength'       => 4000,
                    'property'        => $fieldProperty,
                    'helpTextIdLabel' => $helpId,
                    'icon'            => $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                )
            );
        }
        else
        {
            $fieldType = 'text';

            if($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'DATE')
            {
                if($usfNameIntern === 'BIRTHDAY')
                {
                    $fieldType = 'birthday';
                }
                else
                {
                    $fieldType = 'date';
                }
                $maxlength = '10';
            }
            elseif($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'EMAIL')
            {
                // email could not be longer than 254 characters
                $fieldType = 'email';
                $maxlength = '254';
            }
            elseif($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'URL')
            {
                // maximal browser compatible url length will be 2000 characters
                $maxlength = '2000';
            }
            elseif($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'NUMBER')
            {
                $fieldType = 'number';
                $maxlength = array(0, 9999999999, 1);
            }
            elseif($gProfileFields->getProperty($usfNameIntern, 'cat_name_intern') === 'SOCIAL_NETWORKS')
            {
                $maxlength = '255';
            }
            else
            {
                $maxlength = '50';
            }

            $form->addInput(
                'usf-'. $gProfileFields->getProperty($usfNameIntern, 'usf_id'), $gProfileFields->getProperty($usfNameIntern, 'usf_name'), $user->getValue($usfNameIntern),
                array(
                    'type'            => $fieldType,
                    'maxLength'       => $maxlength,
                    'property'        => $fieldProperty,
                    'helpTextIdLabel' => $helpId,
                    'icon'            => $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                )
            );
        }
    }
}

// div-Container admGroupBoxBody und admGroupBox schliessen
$form->closeGroupBox();

// if captchas are enabled then visitors of the website must resolve this
if($getNewUser === 2 && $gSettingsManager->getBool('enable_registration_captcha'))
{
    $form->openGroupBox('gb_confirmation_of_input', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha_code');
    $form->closeGroupBox();
}

// Bild und Text fuer den Speichern-Button
if($getNewUser === 2)
{
    // Registrierung
    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SEND'), array('icon' => THEME_URL.'/icons/email.png'));
}
else
{
    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
}

if($getNewUser === 0)
{
    // show information about user who creates the recordset and changed it
    $form->addHtml(admFuncShowCreateChangeInfoById(
        (int) $user->getValue('usr_usr_id_create'), $user->getValue('usr_timestamp_create'),
        (int) $user->getValue('usr_usr_id_change'), $user->getValue('usr_timestamp_change')
    ));
}

$page->addHtml($form->show());
$page->show();
