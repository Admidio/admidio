<?php
/**
 ***********************************************************************************************
 * This script should be linked to back buttons in forms. It will search for the
 * last url that should be shown. The script uses the navigation class to handle
 * the url stack.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

// delete the last url from the stack. This should be the actual page.
$gNavigation->deleteLastUrl();

try {
    // now get the "new" last url from the stack. This should be the last page
    $nextUrl = $gNavigation->getUrl();
} catch (AdmException $e) {
    // if no page was found then show the default homepage
    $nextUrl = $gHomepage;
}

admRedirect($nextUrl);
// => EXIT
