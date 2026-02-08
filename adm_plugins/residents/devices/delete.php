<?php
/**
 ***********************************************************************************************
 * Delete a mobile login device (admins only)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isResidentsAdminBySettings()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

try {
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token'] ?? '');
} catch (Exception $e) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$deviceId = admFuncVariableIsValid($_POST, 'id', 'int');
if ($deviceId <= 0) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$device = new TableResidentsDevice($gDb, $deviceId);
if ($device->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$deleted = $device->delete();

$params = array('tab' => 'devices');
if ($deleted) {
    $params['device_status'] = 'deleted';
} else {
    $params['device_status'] = 'error';
    $params['device_message'] = 'Failed to delete device.';
}

admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $params));
