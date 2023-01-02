<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Birthday
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// What should visitors of the page see?
// 0 - (Default) Visitors will only see the number of persons who have birthday today, nothing more.
// 1 - Visitors will see all names (not the age) of birthday persons today, in the future and in the past
$plg_show_names_extern = 0;

// How should the name of the birthday child be displayed?
// 1 - (Default) First name Last name  (John Doe)
// 2 - Last name, First name (Doe, John)
// 3 - First name (John)
// 4 - Login name (John)
$plg_show_names = 1;

// Show the age of the birthday person (only for registered users)
// 0 - Don't show the age
// 1 - Show the age
$plg_show_age = 0;

// From which age of the birthday children the first name will be replaced
// by the salutation for visitors?
// 18 - (Default)
// If you don't want to use this function set the value to 99
$plg_show_alter_anrede = 18;

// Should the reference to the fact that there are no birthday children be omitted?
// 0 - (Default) The reference will be shown
// 1 - The reference will not be shown
$plg_show_hinweis_keiner = 0;

// Show all birthdays in the last x days
$plg_show_zeitraum = 1;

// Show all birthdays of the next x days
$plg_show_future = 2;

// How many birthdays should bei displayed as a maximum?
$plg_show_display_limit = 200;

// Should the e-mail address be linked to visitors?
// Registered users always have a link to the mail module
// 0 - (Default) Only the name of visitors without e-mail will be shown
// 1 - Name with e-mail will be shown for visitors
// 2 - Only the name without e-mail will be shown for visitors and registered users
$plg_show_email_extern = 0;

// Specification of the target in which the contents of the links are to be opened
// You can insert specified values of the html target attribute
$plg_link_target = '_self';

// You can list role ids (comma separated) whose members are allowed to view the content
// of this plugin. If the users doesn't have the right only the number of birthdays are shown.
// Example: $plg_roles_view_plugin_sql = array(2, 4, 10);
$plg_birthday_roles_view_plugin = array();

// Here you can define which roles users must have whose birthdays should be shown. Within the
// default setting birthdays of all users will be shown. Fill the array with ids of the roles to only
// allow birthdays of members of these roles.
// Example: $plg_rolle_sql = array(2, 4, 10);
$plg_rolle_sql = array();

// In which sort order should the birthdays be listed?
// The values could be ascending = 'ASC' or descending = 'DESC'
$plg_sort_sql = 'DESC';

// Should the header of the plugin be displayed?
// 1 - (Default) Header should be shown
// 0 - Header should not be shown
$plg_show_headline = 1;
