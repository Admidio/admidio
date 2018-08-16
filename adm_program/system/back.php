<?php
/**
 ***********************************************************************************************
 * This script should be linked to back buttons in forms. It will search for the
 * last url that should be shown. The script uses the navigation class to handle
 * the url stack.
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

try
{
    $gNavigation->goBack();
}
catch (\UnderflowException $exception)
{
    // if no page was found then show the default homepage
    admRedirect($gHomepage);
    // => EXIT
}
