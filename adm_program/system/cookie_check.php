<?php
/******************************************************************************
 * Prueft, ob Cookies angelegt werden koennen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * message_code : Name der Nachricht aus message_text.php, 
 *                die nach Login angezeigt werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("common.php");

if(isset($_COOKIE['admidio_session_id']) == false)
{
    unset($_SESSION['login_forward_url']);
    $g_message->setForwardUrl("home");
    $g_message->show("no_cookie", $g_current_organization->homepage);
}
else
{
    // Uebergabevariable pruefen     
    if(isset($_GET['message_code']) == false)
    {
        $g_message->show("invalid");
    }
    
    // Loginseite aus Url-Stack entfernen
    $_SESSION['navigation']->deleteLastUrl();
    
    $message_code = strStripTags($_GET['message_code']);
    $show_time = 2000;
    
    if($g_forum_integriert)
    {
        // Je nach Forumsaktion, Meldung ausgeben und weiter zur ForwardUrl - Seite
        $g_message->addVariableContent($g_current_user->login_name);
        $g_message->addVariableContent($g_forum->sitename);
        
        if($message_code != "loginforum")
        {
            // Wenn es eine andere Meldung, als eine Standard-Meldung ist, dem User mehr Zeit zum lesen lassen
            $show_time = 0;
        }
    }
    // pruefen ob eine Weiterleitungsseite gesetzt wurde, anonsten auf die Startseite verweisen
    if(strlen($_SESSION['login_forward_url']) == 0)
    {
        $_SESSION['login_forward_url'] = "home";
    }
    $g_message->setForwardUrl($_SESSION['login_forward_url'], $show_time);
    unset($_SESSION['login_forward_url']);  
    $g_message->show($message_code);
}
?>