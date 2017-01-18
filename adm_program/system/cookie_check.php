<?php
/**
 ***********************************************************************************************
 * Check if cookies could be created in current browser of the user
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * message_code : ID of the message from language-XML-file, that is shown after login
 ***********************************************************************************************
 */
require_once('common.php');

// Initialize and check the parameters
$getMessageCode = admFuncVariableIsValid($_GET, 'message_code', 'string', array('requireValue' => true));

// check if cookie is set
if(!isset($_COOKIE[$gCookiePraefix . '_ID']))
{
    unset($_SESSION['login_forward_url']);
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_COOKIE_NOT_SET', $gCurrentOrganization->getValue('org_homepage')));
    // => EXIT
}
else
{
    // remove login page of URL stack
    $gNavigation->deleteLastUrl();

    $showTime = 2000;
    if($getMessageCode !== 'SYS_LOGIN_SUCCESSFUL')
    {
        // Wenn es eine andere Meldung, als eine Standard-Meldung ist, dem User mehr Zeit zum lesen lassen
        $showTime = 0;
    }

    // pruefen ob eine Weiterleitungsseite gesetzt wurde, anonsten auf die Startseite verweisen
    if(!isset($_SESSION['login_forward_url']) || $_SESSION['login_forward_url'] === '')
    {
        $_SESSION['login_forward_url'] = $gHomepage;
    }

    $gMessage->setForwardUrl($_SESSION['login_forward_url'], $showTime);
    unset($_SESSION['login_forward_url']);

    $gMessage->show($gL10n->get($getMessageCode));
    // => EXIT
}
