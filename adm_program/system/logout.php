<?php
/******************************************************************************
 * User aus Admidio ausloggen
 * Cookies loeschen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_auto_login.php');

// User aus der Session entfernen 
$gCurrentSession->setValue('ses_usr_id', '');
$gCurrentSession->save();

// Inhalt der Cookies loeschen
$domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
setcookie($gCookiePraefix. '_ID', '' , time() - 1000, '/', $domain, 0);

// Autologin wieder entfernen
if(isset($_COOKIE[$gCookiePraefix. '_DATA']))
{
    setcookie($gCookiePraefix. '_DATA', '', time() - 1000, '/', $domain, 0);
    
    $auto_login = new TableAutoLogin($gDb, $gSessionId);
    $auto_login->delete(); 
}

unset($_SESSION['gCurrentUser']);

// da der Inhalt noch auf der eingeloggten Seite steht, hier umsetzen
$gHomepage = $g_root_path. '/'. $gPreferences['homepage_logout'];

$message_code = 'SYS_LOGOUT_SUCCESSFUL';

// Wenn die Session des Forums aktiv ist, diese ebenfalls loeschen.
if($gPreferences['enable_forum_interface'] && $gForum->session_valid)
{
    $gForum->userLogoff();
    $message_code = 'SYS_FORUM_LOGOUT';
}

// Hinweis auf erfolgreiches Ausloggen und weiter zur Startseite
$gMessage->setForwardUrl($gHomepage, 2000);
$gMessage->show($gL10n->get($message_code));
?>