<?php
/**
 ***********************************************************************************************
 * Reset a Mobile Login Device
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/common.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gCurrentUserId;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$deviceID = admFuncVariableIsValid($_GET, 'id', 'int');
$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
$device = new TableResidentsDevice($gDb, $deviceID);

$isActive = (int)$device->getValue('rde_is_active');
if (($deviceID >= 0 && $device->isNewRecord()) || ($isActive !== 1)) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}
elseif ($isActive === 1) {
    $apiKey = bin2hex(random_bytes(20));
    $device->setValue('rde_api_key', $apiKey);
    $device->setValue('rde_usr_id_change', $gCurrentUserId);
    $device->setValue('rde_timestamp_change', date('Y-m-d H:i:s'));
    $saved = $device->save();
    $params = array('tab' => 'devices');
    if ($saved) {
        $params['device_status'] = 'reset';
    } else {
        $params['device_status'] = 'error';
        $params['device_message'] = 'Failed to reset device.';
    }
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $params));
}
