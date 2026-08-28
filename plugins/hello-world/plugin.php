<?php
/**
 ***********************************************************************************************
 * Hello World - the example Admidio plugin.
 *
 * The entry file is included once when the plugin is loaded. It registers what the plugin
 * contributes and returns nothing: there is no plugin class, no interface and no base class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Hooks\Hooks;
use AdmidioPlugin\HelloWorld\Greeting;

/*
 * This file is included by the plugin loader and is never an entry point of its own. Without the
 * Admidio bootstrap it could do nothing anyway; the guard only turns a PHP error into a message.
 */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit('This page may not be called directly!');
}

/*
 * Put the greeting into the headline of every page, so that the plugin can be seen working without
 * visiting its own page. It does nothing until an administrator switches on the preference, which is
 * how a plugin should behave: installing it must not change what the installation looks like.
 */
Hooks::addFilter('page_headline', array(Greeting::class, 'decorateHeadline'), 20, 2, 'hello-world');
