<?php
/******************************************************************************
 * Meldet den User bei Admidio an, wenn sich dieser einloggen darf
 * Cookies setzen
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_auto_login.php');

// Variablen initialisieren
$user_found   = 0;
$b_auto_login = false;
$loginname    = '';
$password     = '';

// Uebergabevariablen filtern
// hierbei muss beruecksichtigt werden, dass diese evtl. von dem Loginplugin
// oder vom Standarddialog kommen
if(isset($_POST['usr_login_name']) && strlen($_POST['usr_login_name']) > 0)
{
    $loginname = $_POST['usr_login_name'];
    $password  = $_POST['usr_password'];

    if($g_preferences['enable_auto_login'] == 1
    && isset($_POST['auto_login']) && $_POST['auto_login'] == 1)
    {
        $b_auto_login = true;
    }
}

if(isset($_POST['plg_usr_login_name']) && strlen($_POST['plg_usr_login_name']) > 0)
{
    $loginname = $_POST['plg_usr_login_name'];
    $password  = $_POST['plg_usr_password'];

    if($g_preferences['enable_auto_login'] == 1
    && isset($_POST['plg_auto_login']) && $_POST['plg_auto_login'] == 1)
    {
        $b_auto_login = true;
    }
}

if(strlen($loginname) == 0)
{
    $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_USERNAME')));
}

if(strlen($password) == 0)
{
    $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_PASSWORD')));
}
$password = md5($password);

// Name und Passwort pruefen
// Rolle muss mind. Mitglied sein

$sql    = 'SELECT usr_id
             FROM '. TBL_USERS. ', '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
            WHERE usr_login_name LIKE "'. $loginname. '"
              AND usr_valid      = 1
              AND mem_usr_id     = usr_id
              AND mem_rol_id     = rol_id
              AND mem_begin     <= "'.DATE_NOW.'"
              AND mem_end        > "'.DATE_NOW.'"
              AND rol_valid      = 1 
              AND rol_cat_id     = cat_id
              AND cat_org_id     = '. $g_current_organization->getValue('org_id');
$result = $g_db->query($sql);

$user_found = $g_db->num_rows($result);
$user_row   = $g_db->fetch_array($result);

if ($user_found >= 1)
{
    // Userobjekt anlegen
    $g_current_user = new User($g_db, $user_row['usr_id']);
    
    if($g_current_user->getValue('usr_number_invalid') >= 3)
    {
        // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
        if(time() - strtotime($g_current_session->getValue('ses_timestamp', 'Y-m-d H:i:s')) < 900)
        {
            $g_message->show($g_l10n->get('SYS_PHR_LOGIN_FAILED'));
        }
    }

    if($g_current_user->getValue('usr_password') == $password)
    {
        $g_current_session->setValue('ses_usr_id', $g_current_user->getValue('usr_id'));
        $g_current_session->save();

        // Cookies fuer die Anmeldung setzen und evtl. Ports entfernen
        $domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
        // soll der Besucher automatisch eingeloggt bleiben, dann verfaellt das Cookie erst nach einem Jahr
        if($b_auto_login == true)
        {
            $timestamp_expired = time() + 60*60*24*365;
            $auto_login = new TableAutoLogin($g_db, $g_session_id);
            
            // falls bereits ein Autologin existiert (Doppelanmeldung an 1 Browser), 
            // dann kein Neues anlegen, da dies zu 'Duplicate Key' fuehrt
            if(strlen($auto_login->getValue('atl_usr_id')) == 0)
            {
                $auto_login->setValue('atl_session_id', $g_session_id);
                $auto_login->setValue('atl_usr_id', $user_row['usr_id']);            
                $auto_login->save();
            }
        }
        else
        {
            $timestamp_expired = 0;
            $g_current_user->setValue('usr_last_session_id', NULL);
        }
        setcookie($cookie_praefix. '_ID', $g_session_id , $timestamp_expired, '/', $domain, 0);
        // User-Id und Autologin auch noch als Cookie speichern
        // vorher allerdings noch serialisieren, damit der Inhalt nicht so einfach ausgelesen werden kann
        setcookie($cookie_praefix. '_DATA', $b_auto_login. ';'. $g_current_user->getValue('usr_id') , $timestamp_expired, '/', $domain, 0);

        // Logins zaehlen und aktuelles Login-Datum aktualisieren
        $g_current_user->updateLoginData();

        // Parallel im Forum einloggen
        if($g_preferences['enable_forum_interface'])
        {
            $set_admin = false;
            if($g_preferences['forum_set_admin'] == 1 && $g_current_user->isWebmaster())
            {
                $set_admin = true;
            }
            $g_forum->userLogin($loginname, $password, $g_current_user->getValue('E-Mail'), $set_admin);
            $login_message = $g_forum->message;
        }
        else
        {
            // User gibt es im Forum nicht, also eine reine Admidio-Anmeldung.
            $login_message = 'SYS_PHR_LOGIN_SUCCESSFUL';
        }

        // bei einer Beta-Version noch einen Hinweis ausgeben !
        if(BETA_VERSION > 0 && $g_debug == false)
        {
            $login_message = 'SYS_PHR_BETA_VERSION';
        }

        // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf
        // die Startseite verweisen
        if(isset($_SESSION['login_forward_url']) == false)
        {
            $_SESSION['login_forward_url'] = $g_root_path. '/'. $g_preferences['homepage_login'];
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
        
        if($g_current_user->getValue('usr_number_invalid') >= 3)
        {
            $g_current_user->setValue('usr_number_invalid', 1);
        }
        else
        {
            $g_current_user->setValue('usr_number_invalid', $g_current_user->getValue('usr_number_invalid') + 1);
        }
        $g_current_user->setValue('usr_date_invalid', DATETIME_NOW);
        $g_current_user->b_set_last_change = false;
        $g_current_user->save();

        if($g_current_user->getValue('usr_number_invalid') >= 3)
        {
            $g_message->show($g_l10n->get('SYS_PHR_LOGIN_FAILED'));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_PASSWORD_UNKNOWN'));
        }
    }
}
else
{
    // nun noch pruefen ob Login ggf. noch nicht freigeschaltet wurde
    $sql    = 'SELECT usr_id
                 FROM '. TBL_USERS. '
                WHERE usr_login_name LIKE "'. $loginname. '"
                  AND usr_valid      = 0
                  AND usr_reg_org_shortname LIKE "'.$g_current_organization->getValue('org_shortname').'" ';
    $result = $g_db->query($sql);

    if($g_db->num_rows($result) == 1)
    {
        $g_message->show($g_l10n->get('SYS_PHR_LOGIN_NOT_ACTIVATED'));
    }
    else
    {
        $g_message->show($g_l10n->get('SYS_PHR_LOGIN_UNKNOWN'));
    }
}

?>