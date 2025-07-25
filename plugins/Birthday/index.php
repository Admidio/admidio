<?php
use Plugins\Birthday\classes\Birthday;

/**
 ***********************************************************************************************
 * Birthday
 *
 * The plugin lists all users who have birthday in a defined timespan.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');

    $pluginBirthday = Birthday::getInstance();
    $pluginBirthday->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
