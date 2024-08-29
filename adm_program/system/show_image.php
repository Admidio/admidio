<?php
/**
 ***********************************************************************************************
 * Show an image of a module from adm_my_files folder
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * module : Name of module (folder name) in adm_my_files where the image lies
 * file   : Name of image file that should be shown (without path)
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/common.php');

    // Initialize and check the parameters
    $getModule = admFuncVariableIsValid($_GET, 'module', 'file', array('requireValue' => true, 'directOutput' => true));
    $getFile = admFuncVariableIsValid($_GET, 'file', 'file', array('requireValue' => true, 'directOutput' => true));

    // Initialize locale parameters
    $imageServerPath = ADMIDIO_PATH . FOLDER_DATA . '/' . $getModule . '/images/' . $getFile;

    // check if image exists
    if (!is_file($imageServerPath)) {
        http_response_code(404);
        exit();
    }

    $image = new Image($imageServerPath);
    header('Content-Type: ' . $image->getMimeType());
    $image->copyToBrowser();
    $image->delete();
} catch (Throwable $e) {
    $gMessage->show($e->getMessage());
}
