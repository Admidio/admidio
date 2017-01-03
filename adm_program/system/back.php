<?php
/**
 ***********************************************************************************************
 * This script should be linked to back buttons in forms. It will search for the
 * last url that should be shown. The script uses the navigation class to handle
 * the url stack.
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
include('common.php');

// delete the last url from the stack. This should be the actual page.
$gNavigation->deleteLastUrl();

// now get the "new" last url from the stack. This should be the last page
$nextUrl = $gNavigation->getUrl();

// if no page was found then show the default homepage
if($nextUrl === '')
{
    $nextUrl = $gHomepage;
}

admRedirect($nextUrl);
// => EXIT
