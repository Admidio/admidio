<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin event list
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Number of events to be displayed (Default = 2)
$plg_max_number_events_shown = 2;

// Also show the to time/to date
// 0 - Do not display to-date and to-time
// 1 - Display to-date and to-time (Default)
$plg_show_date_end = 1;

// Shows a short preview text of the event.
// 0  - no short preview
// 70 - Number of characters of the preview text
$plg_events_show_preview = 70;

// If this option is set to true (1) than the full content of the
// description will be shown. Also images and other html content.
// 0 - only show text preview of description
// 1 - show full html content of description
$plgShowFullDescription = 0;

// Maximum number of characters in a word before a line break should be performed.
// Value 0 deactivates a line break.
$plg_max_char_per_word = 0;

// If you only want to show events of a special calendar you can list the calendars in this parameter
// just use the following syntax $plg_kal_cat = array('calendar-name-1','calendar-name-2')
// If you want to view all events just set $plg_kal_cat = array();
$plg_kal_cat = array();
