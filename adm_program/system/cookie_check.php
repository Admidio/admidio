<?php
/******************************************************************************
 * Prueft, ob Cookies angelegt werden koennen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * message_code : ID der Nachricht aus der Sprach-XML-Datei, 
 *                die nach Login angezeigt werden soll
 *
 *****************************************************************************/

require('common.php');

if(isset($_COOKIE[$cookie_praefix. '_ID']) == false)
{
    unset($_SESSION['login_forward_url']);
    $g_message->setForwardUrl($g_homepage);
    $g_message->show($g_l10n->get('SYS_COOKIE_NOT_SET', $g_current_organization->getValue('org_homepage')));
}
else
{
    // Uebergabevariable pruefen     
    if(isset($_GET['message_code']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    
    // Loginseite aus Url-Stack entfernen
    $_SESSION['navigation']->deleteLastUrl();
    
    $message_code = strStripTags($_GET['message_code']);
    $show_time = 2000;

    if($message_code != 'SYS_LOGIN_SUCCESSFUL' && $message_code != 'SYS_FORUM_LOGIN_SUCCESSFUL')
    {
        // Wenn es eine andere Meldung, als eine Standard-Meldung ist, dem User mehr Zeit zum lesen lassen
        $show_time = 0;
    }
    
    // pruefen ob eine Weiterleitungsseite gesetzt wurde, anonsten auf die Startseite verweisen
    if(strlen($_SESSION['login_forward_url']) == 0)
    {
        $_SESSION['login_forward_url'] = $g_homepage;
    }
    $g_message->setForwardUrl($_SESSION['login_forward_url'], $show_time);
    unset($_SESSION['login_forward_url']);  
    
    if($g_preferences['enable_forum_interface'])
    {
        // Je nach Forumsaktion, Meldung ausgeben und weiter zur ForwardUrl - Seite
        $g_message->show($g_l10n->get($message_code, $g_current_user->getValue('usr_login_name'), $g_forum->sitename));
    }
    else
    {
        $g_message->show($g_l10n->get($message_code));
    }
}
?>