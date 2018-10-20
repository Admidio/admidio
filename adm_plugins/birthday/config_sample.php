<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Birthday
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// What should visitors of the page see?
// 0 = (Default) Visitors will only see the number of persons who have birthday today, nothing more.
// 1 = Visitors will see all names (not the age) of birthday persons today, in the future and in the past
$plg_show_names_extern = 0;

// How should the name of the birthday child be displayed?
// 1 = (Default) First name Last name  (Hans Mustermann)
// 2 = Last name, First name (Mustermann, Hans)
// 3 = First name (Hans)
// 4 = Loginname (Hansi)
$plg_show_names = 1;

// Show the age of the birthday person (only for registered users)
// 0 - Don't show the age
// 1 - Show the age
$plg_show_age = 0;

// From which age of the birthday children the first name will be replaced
// by the salutation for visitors?
// 18 = (Default)
// If you don't want to use this functio set the value to 99
$plg_show_alter_anrede = 18;

// Should the reference to the fact that there are no birthday children be omitted?
// 0 = (Default) The reference will be shown
// 1 = The reference will not be shown
$plg_show_hinweis_keiner = 0;

// Show all birthdays in the last x days
$plg_show_zeitraum = 5;

// Show all birthdays of the next x days
$plg_show_future = 10;

// Should the e-mail address be linked to visitors?
// Registered users always have a link to the mail module
// 0 = (Default) Only the name of visitors without e-mail will be shown
// 1 = Name with e-mail will be shown for visitors
// 2 = Only the name without e-mail will be shown for visitors and registered users
$plg_show_email_extern = 0;

// Specification of the target in which the contents of the links are to be opened
// You can insert specified values of the html target attribut
$plg_link_target = '_self';

// You can list role ids (comma separated) from which the birthday persons should
// be searched. If no role is set than the plugin will search for all active members.
// example value: '(2,5,13)'
$plg_rolle_sql = '';

// In which sort order should the birthdays be listed?
// The values could be ascendend = 'ASC' or descendend = 'DESC'
$plg_sort_sql = 'DESC';

// Should the header of the plugin be displayed?
// 1 = (Default) Header should be shown
// 0 = Header should not be shown
$plg_show_headline = 1;
