<?php
/**
 ***********************************************************************************************
 * Save profile/registration data
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
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'user_id',  'int');
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'int');

// if current user has no login then only show registration dialog
if(!$gValidLogin)
{
    $getNewUser = 2;
}

// save form data in session for back navigation
$_SESSION['profile_request'] = $_POST;

if(!isset($_POST['usr_login_name']))
{
    $_POST['usr_login_name'] = '';
}
if(!isset($_POST['reg_org_id']))
{
    $_POST['reg_org_id'] = $gCurrentOrganization->getValue('org_id');
}

// read user data
if($getNewUser === 2 || $getNewUser === 3)
{
    // create user registration object and set requested organization
    $user = new UserRegistration($gDb, $gProfileFields, $getUserId);
    $user->setOrganization((int) $_POST['reg_org_id']);
}
else
{
    $user = new User($gDb, $gProfileFields, $getUserId);
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

/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// bei Registrierung muss Loginname und Pw geprueft werden
if($getNewUser === 2)
{
    if($_POST['usr_login_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_USERNAME'))));
        // => EXIT
    }

    if($_POST['usr_password'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_PASSWORD'))));
        // => EXIT
    }

    // Passwort muss mindestens 8 Zeichen lang sein
    if(strlen($_POST['usr_password']) < PASSWORD_MIN_LENGTH)
    {
        $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH'));
        // => EXIT
    }

    // beide Passwortfelder muessen identisch sein
    if($_POST['usr_password'] !== $_POST['password_confirm'])
    {
        $gMessage->show($gL10n->get('PRO_PASSWORDS_NOT_EQUAL'));
        // => EXIT
    }

    if(PasswordUtils::passwordStrength($_POST['usr_password'], $user->getPasswordUserData()) < $gSettingsManager->getInt('password_min_strength'))
    {
        $gMessage->show($gL10n->get('PRO_PASSWORD_NOT_STRONG_ENOUGH'));
        // => EXIT
    }
}

// now check all profile fields
foreach($gProfileFields->getProfileFields() as $field)
{
    $postId    = 'usf-'. $field->getValue('usf_id');
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

    // check and save only fields that aren't disabled
    if ($showField
    && ($field->getValue('usf_disabled') == 0
       || ($field->getValue('usf_disabled') == 1 && $gCurrentUser->hasRightEditProfile($user, false))
       || ($field->getValue('usf_disabled') == 1 && $getNewUser > 0)))
    {
        if(isset($_POST[$postId]))
        {
            // Pflichtfelder muessen gefuellt sein
            // E-Mail bei Registrierung immer !!!
            if((strlen($_POST[$postId]) === 0 && $field->getValue('usf_mandatory') == 1)
            || (strlen($_POST[$postId]) === 0 && $field->getValue('usf_name_intern') === 'EMAIL' && $getNewUser === 2))
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($field->getValue('usf_name'))));
                // => EXIT
            }

            // if social network then extract username from url
            if(in_array($field->getValue('usf_name_intern'), array('FACEBOOK', 'GOOGLE_PLUS', 'TWITTER', 'XING'), true))
            {
                if(strValidCharacters($_POST[$postId], 'url') && StringUtils::strContains($_POST[$postId], '/'))
                {
                    if(strrpos($_POST[$postId], '/profile.php?id=') > 0)
                    {
                        // extract facebook id (not facebook unique name) from url
                        $_POST[$postId] = substr($_POST[$postId], strrpos($_POST[$postId], '/profile.php?id=') + 16);
                    }
                    else
                    {
                        if(strrpos($_POST[$postId], '/posts') > 0)
                        {
                            $_POST[$postId] = substr($_POST[$postId], 0, strrpos($_POST[$postId], '/posts'));
                        }

                        $_POST[$postId] = substr($_POST[$postId], strrpos($_POST[$postId], '/') + 1);
                        if(strrpos($_POST[$postId], '?') > 0)
                        {
                            $_POST[$postId] = substr($_POST[$postId], 0, strrpos($_POST[$postId], '?'));
                        }
                    }
                }
            }

            // Wert aus Feld in das User-Klassenobjekt schreiben
            $returnCode = $user->setValue($field->getValue('usf_name_intern'), $_POST[$postId]);

            // Ausgabe der Fehlermeldung je nach Datentyp
            if(!$returnCode)
            {
                switch ($field->getValue('usf_type'))
                {
                    case 'CHECKBOX':
                        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                        // => EXIT
                        break;
                    case 'DATE':
                        $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($field->getValue('usf_name'), $gSettingsManager->getString('system_date'))));
                        // => EXIT
                        break;
                    case 'EMAIL':
                        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($field->getValue('usf_name'))));
                        // => EXIT
                        break;
                    case 'NUMBER':
                    case 'DECIMAL':
                        $gMessage->show($gL10n->get('PRO_FIELD_NUMERIC', array($field->getValue('usf_name'))));
                        // => EXIT
                        break;
                    case 'PHONE':
                        $gMessage->show($gL10n->get('SYS_PHONE_INVALID_CHAR', array($field->getValue('usf_name'))));
                        // => EXIT
                        break;
                    case 'URL':
                        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($field->getValue('usf_name'))));
                        // => EXIT
                        break;
                }
            }
        }
        else
        {
            // Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
            if($field->getValue('usf_type') === 'CHECKBOX')
            {
                $user->setValue($field->getValue('usf_name_intern'), '0');
            }
            elseif($field->getValue('usf_mandatory') == 1)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($field->getValue('usf_name'))));
                // => EXIT
            }
        }
    }
}

if($gCurrentUser->isAdministrator() || $getNewUser > 0)
{
    // Only administrators could change loginname or within a new registration
    if($_POST['usr_login_name'] !== $user->getValue('usr_login_name'))
    {
        if(strlen($_POST['usr_login_name']) > 0)
        {
            // pruefen, ob der Benutzername bereits vergeben ist
            $sql = 'SELECT usr_id
                      FROM '.TBL_USERS.'
                     WHERE usr_login_name = ?';
            $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usr_login_name']));

            if($pdoStatement->rowCount() > 0 && $pdoStatement->fetchColumn() !== $getUserId)
            {
                $gMessage->show($gL10n->get('PRO_LOGIN_NAME_EXIST'));
                // => EXIT
            }
        }

        if(!$user->setValue('usr_login_name', $_POST['usr_login_name']))
        {
            $gMessage->show($gL10n->get('SYS_FIELD_INVALID_CHAR', array($gL10n->get('SYS_USERNAME'))));
            // => EXIT
        }
    }
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($getNewUser === 2)
{
    $user->setPassword($_POST['usr_password']);

    // At user registration with activated captcha check the captcha input
    if ($gSettingsManager->getBool('enable_registration_captcha'))
    {
        try
        {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        }
        catch(AdmException $e)
        {
            $e->showHtml();
            // => EXIT
        }
    }
}

/*------------------------------------------------------------*/
// Save user data to database
/*------------------------------------------------------------*/
$gDb->startTransaction();

try
{
    $user->save();
}
catch(AdmException $e)
{
    unset($_SESSION['profile_request']);
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    $gNavigation->deleteLastUrl();
    $e->showHtml();
    // => EXIT
}

$gDb->endTransaction();

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if((int) $user->getValue('usr_id') === (int) $gCurrentUser->getValue('usr_id'))
{
    $gCurrentUser = $user;
}

unset($_SESSION['profile_request']);
$gNavigation->deleteLastUrl();

/*------------------------------------------------------------*/
// je nach Aufrufmodus auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($getNewUser === 1 || $getNewUser === 3)
{
    // assign a registration or create a new user

    if($getNewUser === 3)
    {
        try
        {
            // accept a registration, assign necessary roles and send a notification email
            $user->acceptRegistration();
            $messageId = 'PRO_ASSIGN_REGISTRATION_SUCCESSFUL';
        }
        catch(AdmException $e)
        {
            $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
            $e->showHtml();
            // => EXIT
        }
    }
    else
    {
        // a new user is created with the user management module
        // then the user must get the necessary roles
        $user->assignDefaultRoles();
        $messageId = 'SYS_SAVE_DATA';
    }

    // if current user has the right to assign roles then show roles dialog
    // otherwise go to previous url (default roles are assigned automatically)
    if($gCurrentUser->assignRoles())
    {
        admRedirect(safeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('usr_id' => $user->getValue('usr_id'), 'new_user' => $getNewUser)));
        // => EXIT
    }
    else
    {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
        $gMessage->show($gL10n->get($messageId));
        // => EXIT
    }
}
elseif($getNewUser === 2)
{
    // registration was successful then go to homepage
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_REGISTRATION_SAVED'));
    // => EXIT
}
elseif($getNewUser === 0 && $user->getValue('usr_valid') == 0)
{
    // a registration was edited then go back to profile view
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
else
{
    // go back to profile view
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
