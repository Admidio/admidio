<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Login Form
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Show a link to register after the login dialog
// 0 - Don't show the link to register
// 1 - Show the link to register (Default)
$plg_show_register_link = 1;

// Show a link to send an email to the administrator if you have login problems
// 0 - Don't show the link to send an email
// 1 - Show the link to send an email (Default)
$plg_show_email_link = 1;

// After login a link to logout will be shown
// 0 - Don't show link to logout (Default)
// 1 - Show link to logout
$plg_show_logout_link = 0;

// Specification of the target in which the contents of the links are to be opened
// You can insert specified values of the html target attribute
$plg_link_target = '_self';

// A little gimmick
// Here you can define ranks depending on the number of logins. The rank will be shown
// after the username. If you don't want this feature just leave a empty array.
$plg_rank = array(
    '0'   => $gL10n->get('PLG_LOGIN_NEW_ONLINE_MEMBER'),
    '50'  => $gL10n->get('PLG_LOGIN_ONLINE_MEMBER'),
    '100' => $gL10n->get('PLG_LOGIN_SENIOR_ONLINE_MEMBER'),
    '200' => $gL10n->get('PLG_LOGIN_HONORARY_MEMBER')
    );
