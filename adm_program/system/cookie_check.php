<?php
/******************************************************************************
 * Prueft, ob Cookies angelegt werden koennen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * message_code : Name der Nachricht aus message_text.php, 
 *                die nach Login angezeigt werden soll
 *
 *****************************************************************************/

require('common.php');

if(isset($_COOKIE[$cookie_praefix. '_ID']) == false)
{
    unset($_SESSION['login_forward_url']);
    $g_message->setForwardUrl($g_homepage);
    $g_message->show('no_cookie', $g_current_organization->getValue('org_homepage'));
}
else
{
    // Uebergabevariable pruefen     
    if(isset($_GET['message_code']) == false)
    {
        $g_message->show('invalid');
    }
    
    // Loginseite aus Url-Stack entfernen
    $_SESSION['navigation']->deleteLastUrl();
    
    $message_code = strStripTags($_GET['message_code']);
    $show_time = 2000;
    
    if($g_preferences['enable_forum_interface'])
    {
        // Je nach Forumsaktion, Meldung ausgeben und weiter zur ForwardUrl - Seite
        $g_message->addVariableContent($g_current_user->getValue('usr_login_name'));
        $g_message->addVariableContent($g_forum->sitename);
    }

    if($message_code != 'login')
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
    $g_message->show($message_code);
}
?>