<?php
use LatestDocumentsFiles\classes\LatestDocumentsFiles;

/**
 ***********************************************************************************************
 * Latest documents & files
 *
 * This plugin lists the latest documents and files uploaded by users
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');
    // the plugin manager only loads the classes of plugins on the overview page, so load the main class
    // if the plugin is called directly; getInstance() then registers the autoloader for all other classes
    if (!class_exists(LatestDocumentsFiles::class, false)) {
        require_once(__DIR__ . '/classes/LatestDocumentsFiles.php');
    }

    $pluginLatestDocumentsFiles = LatestDocumentsFiles::getInstance();
    $pluginLatestDocumentsFiles->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
