<?php
use WhoIsOnline\classes\WhoIsOnline;

/**
 ***********************************************************************************************
 * Who is online
 *
 * Plugin shows visitors and registered members of the homepage
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');

    $pluginWhoIsOnline = WhoIsOnline::getInstance();
    $pluginWhoIsOnline->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
