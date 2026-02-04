<?php
/**
 ***********************************************************************************************
 * Bulk delete Mobile Login Device (admin-only)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Exception; // if available

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

try {
    global $gL10n;
    $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
    if (!isUserAuthorizedForResidents($scriptUrl)) {
        http_response_code(403);
        echo 'FORBIDDEN';
        exit;
    }

    if (!isResidentsAdminBySettings()) {
        http_response_code(403);
        echo 'FORBIDDEN';
        exit;
    }

    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $deleted = 0;
    $failed = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) {
            continue;
    }
        // Delete Mobile Login Device
        $device = new TableResidentsDevice($gDb);
        if ($device->readDataById($id)) {
            if ($device->delete()) {
                $deleted++;
            } else {
                $failed++;
            }
    }
    }

    if ($failed > 0) {
        http_response_code(500);
        echo 'ERROR';
        exit;
    }

    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR';
}
