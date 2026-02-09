<?php
use EventList\classes\EventList;

/**
 ***********************************************************************************************
 * Event list
 *
 * Plugin that lists the latest events in a slim interface and
 * can thus be ideally used in an overview page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');

    $pluginEventList = EventList::getInstance();
    $pluginEventList->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
