<?php
/**
 ***********************************************************************************************
 * Check if cookies could be created in current browser of the user
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

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

    // If no forward url has been set, then refer to the start page after login
    if(!isset($_SESSION['login_forward_url']) || $_SESSION['login_forward_url'] === '')
    {
        $_SESSION['login_forward_url'] = $gHomepage;
    }

    $fowardUrl = $_SESSION['login_forward_url'];
    unset($_SESSION['login_forward_url']);

    admRedirect($fowardUrl);
    // => EXIT
}
