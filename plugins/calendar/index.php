<?php
use Admidio\Infrastructure\Plugins\PluginManager;

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

    // Initialize and check the parameters
    $getDateId = admFuncVariableIsValid($_GET, 'date_id', 'string');

    // Load the plugin at runtime via the PluginManager. This is necessary due to AJAX calls
    $pluginName = basename(__DIR__);

    $pluginManager = new PluginManager();
    $pluginCalendar = $pluginManager->getPluginByName($pluginName);

    if ($pluginCalendar === null) {
        throw new RuntimeException('Calendar plugin could not be loaded.');
    }

    $pluginCalendar->initParams(array('date_id' => $getDateId));
    $pluginCalendar->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
