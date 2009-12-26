<?php
/******************************************************************************
 * User aus Admidio ausloggen
 * Cookies loeschen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_auto_login.php');

// User aus der Session entfernen 
$g_current_session->setValue('ses_usr_id', '');
$g_current_session->save();

// Inhalt der Cookies loeschen
$domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
setcookie($cookie_praefix. '_ID', '' , time() - 1000, '/', $domain, 0);

// Autologin wieder entfernen
if(isset($_COOKIE[$cookie_praefix. '_DATA']))
{
    setcookie($cookie_praefix. '_DATA', '', time() - 1000, '/', $domain, 0);
    
    $auto_login = new TableAutoLogin($g_db, $g_session_id);
    $auto_login->delete(); 
}

unset($_SESSION['g_current_user']);

// da der Inhalt noch auf der eingeloggten Seite steht, hier umsetzen
$g_homepage = $g_root_path. '/'. $g_preferences['homepage_logout'];

$message_code = 'SYS_PHR_LOGOUT_SUCCESSFUL';

// Wenn die Session des Forums aktiv ist, diese ebenfalls loeschen.
if($g_preferences['enable_forum_interface'] && $g_forum->session_valid)
{
    $g_forum->userLogoff();
    $message_code = 'SYS_PHR_FORUM_LOGOUT';
}

// Hinweis auf erfolgreiches Ausloggen und weiter zur Startseite
$g_message->setForwardUrl($g_homepage, 2000);
$g_message->show($g_l10n->get($message_code));
?>