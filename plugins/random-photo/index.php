<?php
use RandomPhoto\classes\RandomPhoto;

/**
 ***********************************************************************************************
 * Random Photo
 *
 * Plugin displays a randomly selected photo from the photo module and links the
 * corresponding album next to the image
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
    if (!class_exists(RandomPhoto::class, false)) {
        require_once(__DIR__ . '/classes/RandomPhoto.php');
    }

    $pluginRandomPhoto = RandomPhoto::getInstance();
    $pluginRandomPhoto->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
