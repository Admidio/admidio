<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin sidebar downloads
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Number of download files, that should be shown by this plugin (Default = 5)
$plg_downloads_count = 5;

// Number of characters of the file name to be displayed
// If set to 0 than the whole filename should be shown,
// otherwise only the first x characters are shown and the file extension
$plgMaxCharsFilename = 0;

// if set to true then the upload timestamp will be shown next to each file
$plg_show_upload_timestamp = true;

// Name of css class for links
// This must only be filled if your links should have another css class than Admidio use
$plg_link_class_downl = '';

// Should the headline of the plugin be shown
// 1 = (Default) Headline should be shown
// 0 = Headline should not be shown
$plg_show_headline = 1;
