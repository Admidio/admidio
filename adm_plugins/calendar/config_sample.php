<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Calendar
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Show the calendar in an integrated ajax box or as static link. The ajax box will dynamically
// update only the month, the normal link will always load the whole page.
// 0 - Integrate as a static link
// 1 - Integrate with an ajax box (recommended)
$plg_ajaxbox = 1;

// Specification of the target for events in which the contents of the links are to be opened
// You can insert specified values of the html target attribute
$plg_link_target_termin = '_self';

// Specification of the target for birthdays in which the contents of the links are to be opened
// You can insert specified values of the html target attribute
$plg_link_target_geb = '_self';

// Show events within the month view. Therefor every day with an event gets a link
// and a small dialog if you hover over the link.
// 0 - Don't show events
// 1 - Show events (recommended)
$plg_ter_aktiv = 1;

// Show birthdays within the month view. Therefor every day with a birthday gets a link
// and a small dialog if you hover over the link.
// 0 - Don't show birthdays
// 1 - Show birthdays (default)
$plg_geb_aktiv = 1;

// Show birthdays only for registered members that have a valid login.
// 0 - Show birthdays to guests
// 1 - Don't show birthdays to guests only to members (recommended)
$plg_geb_login = 1;

// Show birthdays with an icon in the month view.
// 0 - Don't show birthday icon
// 1 - Show birthday icon (default)
$plg_geb_icon = 1;

// Flag that controls how the name of the person who has birthday should be shown
// 0 - "Lastname, Firstname"
// 1 - "Firstname" (Default)
// 2 - "Lastname"
$plg_geb_displayNames = 1;

// Here you can define which calendars should be shown. Within the default events
// of all calendars will be shown, but you could limit it to only a few calendars.
// Therefor you must add the name of the calendar to an array. Be careful that translatable
// calendar names have a different name e.g. "Common" has the name "SYS_COMMON".
// If you want to limit to some calendars use the following syntax:
// $plg_kal_cat = array('SYS_COMMON', 'My new own calendar', 'Maybe another calendar');
$plg_kal_cat = array('all');

// Should the calendar name also be shown at each event?
// 0 - Only the event name (Default)
// 1 - Also show the calendar to each event
$plg_kal_cat_show = 0;

// You can list role ids (comma separated) whose members are allowed to view the content
// of this plugin. If the users doesn't have the right only the number of birthdays are shown.
// Example: $plg_roles_view_plugin_sql = array(2, 4, 10);
$plg_calendar_roles_view_plugin = array();

// Here you can define which roles users must have whose birthdays should be shown. Within the
// default setting birthdays of all users will be shown. Fill the array with ids of the roles to only
// allow birthdays of members of these roles.
// Example: $plg_rolle_sql = array(2, 4, 10);
$plg_rolle_sql = array();

// Specification of the prefix URL for the call in Joomla
// if not specified then the default URL of Admidio is used
$plg_link_url = '';
