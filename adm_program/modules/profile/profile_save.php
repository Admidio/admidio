<?php
/******************************************************************************
 * Save profile/registration data
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * user_id    : ID of the user who should be edited
 * new_user   : 0 - Edit user of the user id
 *              1 - Create a new user
 *              2 - Create a registration
 *              3 - assign/accept a registration
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'user_id', 'numeric');
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'numeric');

// if current user has no login then only show registration dialog
if($gValidLogin == false)
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
if($getNewUser == 2 || $getNewUser == 3)
{
    // create user registration object and set requested organization
    $user = new UserRegistration($gDb, $gProfileFields, $getUserId);
    $user->setOrganization($_POST['reg_org_id']);
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
        if($gCurrentUser->hasRightEditProfile($user) == false)
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

/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// bei Registrierung muss Loginname und Pw geprueft werden
if($getNewUser == 2)
{
    if($_POST['usr_login_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_USERNAME')));
    }

    // Passwort muss mindestens 8 Zeichen lang sein
    if(strlen($_POST['usr_password']) < 8)
    {
        $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH'));
    }

    // beide Passwortfelder muessen identisch sein
    if($_POST['usr_password'] !== $_POST['password_confirm'])
    {
        $gMessage->show($gL10n->get('PRO_PASSWORDS_NOT_EQUAL'));
    }

    if($_POST['usr_password'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_PASSWORD')));
    }
}

// nun alle Profilfelder pruefen
foreach($gProfileFields->mProfileFields as $field)
{
    $post_id = 'usf-'. $field->getValue('usf_id');

    // check and save only fields that aren't disabled
    if($field->getValue('usf_disabled') == 0
    || ($field->getValue('usf_disabled') == 1 && $gCurrentUser->hasRightEditProfile($user, false))
    || ($field->getValue('usf_disabled') == 1 && $getNewUser > 0))
    {
        if(isset($_POST[$post_id]))
        {
            // Pflichtfelder muessen gefuellt sein
            // E-Mail bei Registrierung immer !!!
            if(($field->getValue('usf_mandatory') == 1 && strlen($_POST[$post_id]) == 0)
            || ($getNewUser == 2 && $field->getValue('usf_name_intern') == 'EMAIL' && strlen($_POST[$post_id]) == 0))
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('usf_name')));
            }

            // if social network then extract username from url
            if($field->getValue('usf_name_intern') == 'FACEBOOK'
            || $field->getValue('usf_name_intern') == 'GOOGLE_PLUS'
            || $field->getValue('usf_name_intern') == 'TWITTER'
            || $field->getValue('usf_name_intern') == 'XING')
            {
                if(strValidCharacters($_POST[$post_id], 'url')
                && strpos($_POST[$post_id], '/') !== false)
                {
                    if(strrpos($_POST[$post_id], '/profile.php?id=') > 0)
                    {
                        // extract facebook id (not facebook unique name) from url
                        $_POST[$post_id] = substr($_POST[$post_id], strrpos($_POST[$post_id], '/profile.php?id=') + 16);
                    }
                    else
                    {
                        if(strrpos($_POST[$post_id], '/posts') > 0)
                        {
                            $_POST[$post_id] = substr($_POST[$post_id], 0, strrpos($_POST[$post_id], '/posts'));
                        }

                        $_POST[$post_id] = substr($_POST[$post_id], strrpos($_POST[$post_id], '/') + 1);
                        if(strrpos($_POST[$post_id], '?') > 0)
                        {
                           $_POST[$post_id] = substr($_POST[$post_id], 0, strrpos($_POST[$post_id], '?'));
                        }
                    }
                }
            }

            // Wert aus Feld in das User-Klassenobjekt schreiben
            $returnCode = $user->setValue($field->getValue('usf_name_intern'), $_POST[$post_id]);

            // Ausgabe der Fehlermeldung je nach Datentyp
            if($returnCode == false)
            {
                if($field->getValue('usf_type') == 'CHECKBOX')
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }
                elseif($field->getValue('usf_type') == 'DATE')
                {
                    $gMessage->show($gL10n->get('SYS_DATE_INVALID', $field->getValue('usf_name'), $gPreferences['system_date']));
                }
                elseif($field->getValue('usf_type') == 'EMAIL')
                {
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $field->getValue('usf_name')));
                }
                elseif($field->getValue('usf_type') == 'NUMBER' || $field->getValue('usf_type') == 'DECIMAL')
                {
                    $gMessage->show($gL10n->get('PRO_FIELD_NUMERIC', $field->getValue('usf_name')));
                }
                elseif($field->getValue('usf_type') == 'PHONE')
                {
                    $gMessage->show($gL10n->get('SYS_PHONE_INVALID_CHAR', $field->getValue('usf_name')));
                }
                elseif($field->getValue('usf_type') == 'URL')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $field->getValue('usf_name')));
                }
            }
        }
        else
        {
            // Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
            if($field->getValue('usf_type') == 'CHECKBOX')
            {
                $user->setValue($field->getValue('usf_name_intern'), '0');
            }
            elseif($field->getValue('usf_mandatory') == 1)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('usf_name')));
            }
        }
    }
}

if($gCurrentUser->isWebmaster() || $getNewUser > 0)
{
    // Loginname darf nur vom Webmaster bzw. bei Neuanlage geaendert werden
    if($_POST['usr_login_name'] != $user->getValue('usr_login_name'))
    {
        if(strlen($_POST['usr_login_name']) > 0)
        {
            // pruefen, ob der Benutzername bereits vergeben ist
            $sql = 'SELECT usr_id FROM '. TBL_USERS. '
                     WHERE usr_login_name LIKE \''. $_POST['usr_login_name']. '\'';
            $pdoStatement = $gDb->query($sql);

            if($pdoStatement->rowCount() > 0)
            {
                $row = $pdoStatement->fetch();

                if(strcmp($row['usr_id'], $getUserId) != 0)
                {
                    $gMessage->show($gL10n->get('PRO_LOGIN_NAME_EXIST'));
                }
            }
        }

        if(!$user->setValue('usr_login_name', $_POST['usr_login_name']))
        {
            $gMessage->show($gL10n->get('SYS_FIELD_INVALID_CHAR', $gL10n->get('SYS_USERNAME')));
        }
    }
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($getNewUser == 2)
{
    $user->setPassword($_POST['usr_password']);
}

// Falls der User sich registrieren wollte, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if ($getNewUser == 2 && $gPreferences['enable_registration_captcha'] == 1)
{
    if (!isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']))
    {
        if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
        elseif($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
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
}

$gDb->endTransaction();

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
{
    $gCurrentUser = $user;
}

unset($_SESSION['profile_request']);
$gNavigation->deleteLastUrl();

/*------------------------------------------------------------*/
// je nach Aufrufmodus auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($getNewUser == 1 || $getNewUser == 3)
{
    // assign a registration or create a new user

    if($getNewUser == 3)
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
        header('Location: roles.php?usr_id='. $user->getValue('usr_id'). '&new_user='.$getNewUser);
        exit();
    }
    else
    {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
        $gMessage->show($gL10n->get($messageId));
    }
}
elseif($getNewUser == 2)
{
    // registration was successful then go to homepage
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_REGISTRATION_SAVED'));
}
elseif($getNewUser == 0 && $user->getValue('usr_valid') == 0)
{
    // a registration was edited then go back to profile view
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
else
{
    // go back to profile view
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
