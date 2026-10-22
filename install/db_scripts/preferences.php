<?php
/**
 ***********************************************************************************************
 * System preferences for an organization
 *
 * Defaults and semantic constraints are defined centrally in PreferenceDefinitions so installer,
 * web UI and headless callers cannot drift apart.
 *
 * IMPORTANT: If preferences should get other values with an update,
 *            then you must set these values for every organization
 *            in the update scripts.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$defaultOrgPreferences = \Admidio\Preferences\Service\PreferenceDefinitions::defaults();
