<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Sidebar-Announcements
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Number of announcements to be displayed (Default = 2)
$plg_announcements_count = 2;

// Shows a short preview text of the announcement.
// 0  - no short preview
// 70 - Number of characters of the preview text
$plg_show_preview = 70;

// If this option is set to true (1) than the full content of the
// description will be shown. Also images and other html content.
// 0 - only show text preview of description
// 1 - show full html content of description
$plgShowFullDescription = 0;

// Maximum number of characters in a word before a line break should be performed.
// Value 0 deactivates a line break.
$plg_max_char_per_word = 0;

// If you only want to show announcements of a special category you can list the categories in this parameter
// just use the following syntax $plg_categories = array('category-name-1','category-name-2')
// If you want to view all announcements just set $plg_categories = array();
$plg_categories = array();
