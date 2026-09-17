<?php
/**
 ***********************************************************************************************
 * Event list - entry file.
 *
 * The entry file is included once when the plugin is loaded. It registers what the plugin
 * contributes - here an overview widget and a preferences panel - and returns nothing.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\EventList\EventList;

/*
 * This file is included by the plugin loader and is never an entry point of its own. Without the
 * Admidio bootstrap it could do nothing anyway; the guard only turns a PHP error into a message.
 */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit('This page may not be called directly!');
}

EventList::register();
