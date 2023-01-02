<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Random Photo
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Maximum number of characters in a word before a line break should be performed.
// Value 0 deactivates a line break.
$plg_max_char_per_word = 0;

// Maximum photo width of the preview image in pixels (Default 0)
// If it's set to 0 then the image will be dynamically scaled by the html container in
// which this plugin is placed.
$plg_photos_max_width = 0;

// Maximum photo height of the preview image in pixels (Default 0)
// If it's set to 0 then the image will be dynamically scaled by the html container in
// which this plugin is placed.
$plg_photos_max_height = 0;

// Number of albums the photo may come from.
// Counts from the most recent album in descending order.
// Default = 0 (No restriction)
$plg_photos_albums = 0;

// Number of the image from the album to be displayed.
// 0 - Random picture (Default)
// 1 - first picture of the album
// 2 - second picture etc.
$plg_photos_picnr = 0;

// Displays a link with the album name below the photo.
// false - Link will not be shown
// true  - Link will be shown (Default)
$plg_photos_show_link = true;

// Specification of the target in which the contents of the links are to be opened
// You can insert specified values of the html target attribute
$plg_link_target = '_self';

// Should the headline of the plugin be displayed
// 0 - Headline is not displayed
// 1 - Headline is displayed (Default)
$plg_show_headline = 1;
