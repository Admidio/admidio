<?php
/******************************************************************************
 * Validate login data, create cookie and sign in the user to Admidio
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_auto_login.php');

// Initialize parameters
$userFound  = 0;
$bAutoLogin = false;
$loginname  = '';
$password   = '';
$organizationId = $gCurrentOrganization->getValue('org_id');

// Filter parameters
// parameters could be from login dialog or login plugin !!!
if(isset($_POST['usr_login_name']) && strlen($_POST['usr_login_name']) > 0)
{
    $loginname = $_POST['usr_login_name'];
    $password  = $_POST['usr_password'];

    if($gPreferences['enable_auto_login'] == 1
    && isset($_POST['auto_login']) && $_POST['auto_login'] == 1)
    {
        $bAutoLogin = true;
    }
	
	// if user can choose organization then save the selection
	if(isset($_POST['org_id']) && is_numeric($_POST['org_id']) && $_POST['org_id'] > 0)
	{
		$organizationId = $_POST['org_id'];
	}
}

if(isset($_POST['plg_usr_login_name']) && strlen($_POST['plg_usr_login_name']) > 0)
{
    $loginname = $_POST['plg_usr_login_name'];
    $password  = $_POST['plg_usr_password'];

    if($gPreferences['enable_auto_login'] == 1
    && isset($_POST['plg_auto_login']) && $_POST['plg_auto_login'] == 1)
    {
        $bAutoLogin = true;
    }

	// if user can choose organization then save the selection
	if(isset($_POST['plg_org_id']) && is_numeric($_POST['plg_org_id']) && $_POST['plg_org_id'] > 0)
	{
		$organizationId = $_POST['plg_org_id'];
	}
}

if(strlen($loginname) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_USERNAME')));
}

if(strlen($password) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_PASSWORD')));
}

// check name and password
// user must have membership of one role of the organization

$sql    = 'SELECT usr_id
             FROM '. TBL_USERS. ', '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
            WHERE UPPER(usr_login_name) LIKE UPPER(\''.$loginname.'\')
              AND usr_valid      = 1
              AND mem_usr_id     = usr_id
              AND mem_rol_id     = rol_id
              AND mem_begin     <= \''.DATE_NOW.'\'
              AND mem_end        > \''.DATE_NOW.'\'
              AND rol_valid      = 1
              AND rol_cat_id     = cat_id
              AND cat_org_id     = '.$organizationId;
$result = $gDb->query($sql);

$userFound = $gDb->num_rows($result);
$userRow   = $gDb->fetch_array($result);

if ($userFound >= 1)
{
	// if login organization is different to organization of config file then create new session variables
	if($organizationId != $gCurrentOrganization->getValue('org_id'))
	{
		// read organization of config file with their preferences
		$gCurrentOrganization->readData($organizationId);
		$gPreferences = $gCurrentOrganization->getPreferences();
		
		// create object with current user field structure und user object
		$gProfileFields = new ProfileFields($gDb, $gCurrentOrganization);
		
		// save all data in session variables
		$_SESSION['gCurrentOrganization'] =& $gCurrentOrganization;
		$_SESSION['gPreferences']         =& $gPreferences;
		$_SESSION['gProfileFields']       =& $gProfileFields;
	}

    // create user object
    $gCurrentUser = new User($gDb, $gProfileFields, $userRow['usr_id']);
    
    if($gCurrentUser->getValue('usr_number_invalid') >= 3)
    {
        // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
        if(time() - strtotime($gCurrentUser->getValue('usr_date_invalid', 'Y-m-d H:i:s')) < 900)
        {
            $gCurrentUser->clear();
            $gMessage->show($gL10n->get('SYS_LOGIN_FAILED'));
        }
    }

    if($gCurrentUser->checkPassword($password) == true)
    {
        $gCurrentSession->setValue('ses_usr_id', $gCurrentUser->getValue('usr_id'));
        $gCurrentSession->save();

        // Cookies fuer die Anmeldung setzen und evtl. Ports entfernen
        $domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));

        // soll der Besucher automatisch eingeloggt bleiben, dann verfaellt das Cookie erst nach einem Jahr
        if($bAutoLogin == true && $gPreferences['enable_auto_login'] == 1)
        {
            $timestamp_expired = time() + 60*60*24*365;
            $auto_login = new TableAutoLogin($gDb, $gSessionId);
            
            // falls bereits ein Autologin existiert (Doppelanmeldung an 1 Browser), 
            // dann kein Neues anlegen, da dies zu 'Duplicate Key' fuehrt
            if(strlen($auto_login->getValue('atl_usr_id')) == 0)
            {
                $auto_login->setValue('atl_session_id', $gSessionId);
                $auto_login->setValue('atl_usr_id', $userRow['usr_id']);            
                $auto_login->save();
            }
        }
        else
        {
            $timestamp_expired = 0;
            $gCurrentUser->setValue('usr_last_session_id', NULL);
        }
        setcookie($gCookiePraefix. '_ID', $gSessionId , $timestamp_expired, '/', $domain, 0);
        // User-Id und Autologin auch noch als Cookie speichern
        // vorher allerdings noch serialisieren, damit der Inhalt nicht so einfach ausgelesen werden kann
        setcookie($gCookiePraefix. '_DATA', $bAutoLogin. ';'. $gCurrentUser->getValue('usr_id') , $timestamp_expired, '/', $domain, 0);

        // Logins zaehlen und aktuelles Login-Datum aktualisieren
        $gCurrentUser->updateLoginData();

        // Parallel im Forum einloggen
        if($gPreferences['enable_forum_interface'])
        {
            $set_admin = false;
            if($gPreferences['forum_set_admin'] == 1 && $gCurrentUser->isWebmaster())
            {
                $set_admin = true;
            }
            $gForum->userLogin($loginname, $password, $gCurrentUser->getValue('EMAIL'), $set_admin);
            $login_message = $gForum->message;
        }
        else
        {
            // User gibt es im Forum nicht, also eine reine Admidio-Anmeldung.
            $login_message = 'SYS_LOGIN_SUCCESSFUL';
        }

        // bei einer Beta-Version noch einen Hinweis ausgeben !
        if(BETA_VERSION > 0 && $gDebug == false)
        {
            $login_message = 'SYS_BETA_VERSION';
        }

        // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf
        // die Startseite verweisen
        if(isset($_SESSION['login_forward_url']) == false)
        {
            $_SESSION['login_forward_url'] = $g_root_path. '/'. $gPreferences['homepage_login'];
        }

        // bevor zur entsprechenden Seite weitergeleitet wird, muss noch geprueft werden,
        // ob der Browser Cookies setzen darf -> sonst kein Login moeglich
        $location = 'Location: '.$g_root_path.'/adm_program/system/cookie_check.php?message_code='.$login_message;
        header($location);
        exit();
    }
    else
    {
        // ungueltige Logins werden mitgeloggt
        
        if($gCurrentUser->getValue('usr_number_invalid') >= 3)
        {
            $gCurrentUser->setValue('usr_number_invalid', 1);
        }
        else
        {
            $gCurrentUser->setValue('usr_number_invalid', $gCurrentUser->getValue('usr_number_invalid') + 1);
        }
        $gCurrentUser->setValue('usr_date_invalid', DATETIME_NOW);
        $gCurrentUser->save(false);   // Zeitstempel nicht aktualisieren
        $gCurrentUser->clear();

        if($gCurrentUser->getValue('usr_number_invalid') >= 3)
        {
            $gMessage->show($gL10n->get('SYS_LOGIN_FAILED'));
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_PASSWORD_UNKNOWN'));
        }
    }
}
else
{
    // nun noch pruefen ob Login ggf. noch nicht freigeschaltet wurde
    $sql    = 'SELECT usr_id
                 FROM '. TBL_USERS. '
                WHERE usr_login_name LIKE \''. $loginname. '\'
                  AND usr_valid      = 0
                  AND usr_reg_org_shortname LIKE \''.$gCurrentOrganization->getValue('org_shortname').'\' ';
    $result = $gDb->query($sql);

    if($gDb->num_rows($result) == 1)
    {
        $gMessage->show($gL10n->get('SYS_LOGIN_NOT_ACTIVATED'));
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_LOGIN_UNKNOWN'));
    }
}

?>