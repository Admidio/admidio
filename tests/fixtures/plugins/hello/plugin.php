<?php
/**
 * The entry file of a plugin. It returns nothing, implements nothing and extends nothing; it only
 * registers what the plugin contributes.
 */

use Admidio\Hooks\Hooks;
use AdmidioPlugin\Hello\Greeter;

Hooks::addFilter('hello_greeting', array(Greeter::class, 'decorate'), 10, 2, 'hello');

$GLOBALS['helloPluginEntryFileRuns'] = ($GLOBALS['helloPluginEntryFileRuns'] ?? 0) + 1;

$GLOBALS['pluginLoadOrder'][] = 'hello';
