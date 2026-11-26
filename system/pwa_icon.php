<?php
/**
 ***********************************************************************************************
 * Serves the app icons of the organization from the adm_my_files folder
 *
 * The folder adm_my_files is protected by a .htaccess file, so the app icons of the web app
 * manifest are delivered by this script. Only the fixed icon file names of AppIconService are
 * served, never a path from the request. The script is public because browsers load the icons
 * of the web app manifest without a login.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * size     : Size of the icon (64, 114, 180, 192 or 512)
 * maskable : If set to true, the maskable icon is served and the size is ignored
 * v        : Modification time of the icon file, only used to reload a changed icon
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Service\AppIconService;

try {
    require_once(__DIR__ . '/common.php');

    // Initialize and check the parameters
    $getSize = admFuncVariableIsValid($_GET, 'size', 'int', array('defaultValue' => 0, 'directOutput' => true));
    $getMaskable = admFuncVariableIsValid($_GET, 'maskable', 'bool', array('defaultValue' => false, 'directOutput' => true));

    $iconFile = AppIconService::getIconFile($getSize, $getMaskable);

    if ($iconFile === null) {
        http_response_code(404);
        exit();
    }

    $lastModified = filemtime($iconFile);
    $eTag = '"' . md5($iconFile . $lastModified . filesize($iconFile)) . '"';

    // replace the no-cache headers of the session, the icons may be cached
    header_remove('Pragma');
    header_remove('Expires');
    header('Cache-Control: public, max-age=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    header('ETag: ' . $eTag);
    header('X-Content-Type-Options: nosniff');

    if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $eTag)
    || (!isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
        && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified)) {
        http_response_code(304);
        exit();
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($iconFile));
    readfile($iconFile);
    exit();
} catch (Throwable $e) {
    handleException($e);
}
